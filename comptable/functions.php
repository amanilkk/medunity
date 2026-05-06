<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
/**
 * FONCTIONS POUR LE MODULE COMPTABLE
 * Gestion des paiements, factures, dépenses, salaires et rapports financiers
 * Adapté au schéma réel de la base de données
 */

// ============================================================================
// 1. GESTION DES PAIEMENTS
// ============================================================================

/**
 * Enregistrer un paiement pour une facture
 * @param mysqli $db
 * @param int $invoice_id
 * @param float $amount
 * @param string $method (cash, card, bank_transfer, insurance, check)
 * @param int $received_by (user_id du comptable)
 * @param string $notes
 * @return array [success => bool, message => string, payment_id => int|null]
 */
function recordPayment($db, $invoice_id, $amount, $method, $received_by, $notes = '') {
    try {
        $stmt = $db->prepare("SELECT total_amount, paid_amount FROM invoices WHERE id = ?");
        $stmt->bind_param('i', $invoice_id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();

        if (!$invoice) {
            return ['success' => false, 'message' => 'Facture non trouvée'];
        }

        $remaining = $invoice['total_amount'] - $invoice['paid_amount'];
        if ($amount > $remaining) {
            return ['success' => false, 'message' => 'Montant dépasse le solde restant'];
        }

        $stmt = $db->prepare(
            "INSERT INTO payments (invoice_id, amount, method, received_by, notes) 
             VALUES (?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        // CORRIGÉ: 'idsss' au lieu de 'idsas' (a n'existe pas comme type)
        // i = integer, d = double, s = string
        $stmt->bind_param('idsss', $invoice_id, $amount, $method, $received_by, $notes);

        if ($stmt->execute()) {
            updateInvoicePaidAmount($db, $invoice_id);
            return [
                'success' => true,
                'message' => 'Paiement enregistré',
                'payment_id' => $db->insert_id
            ];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
/**
 * Mettre à jour le paid_amount et le statut d'une facture
 * @param mysqli $db
 * @param int $invoice_id
 * @return bool
 */
function updateInvoicePaidAmount($db, $invoice_id) {
    $stmt = $db->prepare("SELECT total_amount FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();

    if (!$invoice) return false;

    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) as paid_amount FROM payments WHERE invoice_id = ?"
    );
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    $paid = (float)$stmt->get_result()->fetch_assoc()['paid_amount'];

    $status = ($paid >= $invoice['total_amount']) ? 'paid' : 'unpaid';

    $stmt = $db->prepare(
        "UPDATE invoices SET paid_amount = ?, status = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->bind_param('dsi', $paid, $status, $invoice_id);
    return $stmt->execute();
}

/**
 * Obtenir les paiements d'une facture
 * @param mysqli $db
 * @param int $invoice_id
 * @return array
 */
function getInvoicePayments($db, $invoice_id) {
    $stmt = $db->prepare(
        "SELECT p.*, u.full_name as received_by_name
         FROM payments p
         LEFT JOIN users u ON u.id = p.received_by
         WHERE p.invoice_id = ? 
         ORDER BY p.payment_date DESC"
    );
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Annuler un paiement
 * @param mysqli $db
 * @param int $payment_id
 * @return array [success => bool, message => string]
 */
function cancelPayment($db, $payment_id) {
    $stmt = $db->prepare("SELECT invoice_id FROM payments WHERE id = ?");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();

    if (!$payment) {
        return ['success' => false, 'message' => 'Paiement non trouvé'];
    }

    $stmt = $db->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->bind_param('i', $payment_id);

    if ($stmt->execute()) {
        updateInvoicePaidAmount($db, $payment['invoice_id']);
        return ['success' => true, 'message' => 'Paiement annulé'];
    } else {
        return ['success' => false, 'message' => $stmt->error];
    }
}

// ============================================================================
// 2. GESTION DES FACTURES
// ============================================================================

/**
 * Générer une facture
 * @param mysqli $db
 * @param int $patient_id
 * @param array $services [['description' => '', 'unit_price' => 0], ...]
 * @param int $appointment_id (optional)
 * @param string $notes
 * @return array [success => bool, message => string, invoice_id => int|null]
 */
function generateInvoice($db, $patient_id, $services, $appointment_id = null, $notes = '') {
    try {
        $total_amount = 0;
        foreach ($services as $service) {
            $total_amount += (float)$service['unit_price'];
        }

        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare(
            "INSERT INTO invoices (invoice_number, patient_id, appointment_id, total_amount, paid_amount, status, generated_date, notes, created_at)
             VALUES (?, ?, ?, ?, 0, 'unpaid', NOW(), ?, NOW())"
        );

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('siids', $invoice_number, $patient_id, $appointment_id, $total_amount, $notes);

        if (!$stmt->execute()) {
            return ['success' => false, 'message' => $stmt->error];
        }

        $invoice_id = $db->insert_id;

        $stmt = $db->prepare(
            "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, amount)
             VALUES (?, ?, 1, ?, ?)"
        );

        foreach ($services as $service) {
            $unit_price = (float)$service['unit_price'];
            $stmt->bind_param('isdd', $invoice_id, $service['description'], $unit_price, $unit_price);
            $stmt->execute();
        }

        return [
            'success' => true,
            'message' => 'Facture générée',
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Obtenir les détails d'une facture
 * @param mysqli $db
 * @param int $invoice_id
 * @return array|null
 */
function getInvoiceDetails($db, $invoice_id) {
    $stmt = $db->prepare(
        "SELECT i.*, p.user_id, u.full_name, u.email, u.phone, u.address
         FROM invoices i
         INNER JOIN patients p ON p.id = i.patient_id
         INNER JOIN users u ON u.id = p.user_id
         WHERE i.id = ?"
    );
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Obtenir les articles d'une facture
 * @param mysqli $db
 * @param int $invoice_id
 * @return array
 */
function getInvoiceItems($db, $invoice_id) {
    $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Modifier une facture (avant paiement)
 * @param mysqli $db
 * @param int $invoice_id
 * @param array $updates ['total_amount' => 0, 'notes' => '']
 * @return array [success => bool, message => string]
 */
function updateInvoice($db, $invoice_id, $updates) {
    $stmt = $db->prepare("SELECT status FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();

    if (!$invoice) {
        return ['success' => false, 'message' => 'Facture non trouvée'];
    }

    if ($invoice['status'] === 'paid') {
        return ['success' => false, 'message' => 'Impossible de modifier une facture payée'];
    }

    $fields = [];
    $types = '';
    $values = [];

    if (isset($updates['total_amount'])) {
        $fields[] = 'total_amount = ?';
        $types .= 'd';
        $values[] = $updates['total_amount'];
    }
    if (isset($updates['notes'])) {
        $fields[] = 'notes = ?';
        $types .= 's';
        $values[] = $updates['notes'];
    }

    if (empty($fields)) {
        return ['success' => false, 'message' => 'Aucune donnée à mettre à jour'];
    }

    $fields[] = 'updated_at = NOW()';
    $values[] = $invoice_id;
    $types .= 'i';

    $query = "UPDATE invoices SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($query);

    if (!$stmt) {
        return ['success' => false, 'message' => 'Erreur: ' . $db->error];
    }

    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Facture mise à jour'];
    } else {
        return ['success' => false, 'message' => $stmt->error];
    }
}

// ============================================================================
// 3. GESTION DES COMMANDES/DÉPENSES
// ============================================================================

/**
 * Créer une commande fournisseur
 * @param mysqli $db
 * @param int $supplier_id
 * @param float $total_price
 * @param int $created_by
 * @param string $notes
 * @return array [success => bool, message => string, order_id => int|null]
 */
/**
 * Créer une commande fournisseur
 * @param mysqli $db
 * @param int $supplier_id
 * @param float $total_amount
 * @param int $created_by
 * @param string $notes
 * @return array [success => bool, message => string, order_id => int|null]
 */
function createPurchaseOrder($db, $supplier_id, $total_amount, $created_by, $notes = '') {
    try {
        $order_number = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare(
            "INSERT INTO purchase_orders (order_number, supplier_id, total_amount, status, created_by, notes, order_date)
             VALUES (?, ?, ?, 'pending', ?, ?, NOW())"
        );

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('sidss', $order_number, $supplier_id, $total_amount, $created_by, $notes);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Commande créée',
                'order_id' => $db->insert_id,
                'order_number' => $order_number
            ];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Ajouter un article à une commande
 * @param mysqli $db
 * @param int $order_id
 * @param string $item_name
 * @param string $item_type (medicine|food|equipment|other)
 * @param int $item_id
 * @param float $quantity
 * @param string $unit
 * @param float $unit_price
 * @return array [success => bool, message => string]
 */
function addPurchaseOrderItem($db, $order_id, $item_name, $item_type, $item_id, $quantity, $unit, $unit_price) {
    try {
        $total_price = $quantity * $unit_price;

        $stmt = $db->prepare(
            "INSERT INTO purchase_order_items (order_id, item_name, item_type, item_id, quantity, unit, unit_price, total_price)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('isisdsdd', $order_id, $item_name, $item_type, $item_id, $quantity, $unit, $unit_price, $total_price);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Article ajouté'];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Obtenir les dépenses (commandes livrées) d'une période
 * @param mysqli $db
 * @param string $start_date (Y-m-d)
 * @param string $end_date (Y-m-d)
 * @return float
 */
// ✅ CORRECT
// ✅ CORRECT - Fonction complète
function getTotalExpensesByPeriod($db, $start_date, $end_date) {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM purchase_orders 
         WHERE order_date BETWEEN ? AND ? AND status = 'delivered'"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_assoc()['total'];
}
// ============================================================================
// 4. GESTION DES SALAIRES
// ============================================================================

/**
 * Obtenir le salaire actuel d'un employé
 * @param mysqli $db
 * @param int $user_id
 * @return float|null
 */
function getEmployeeSalary($db, $user_id) {
    $stmt = $db->prepare(
        "SELECT salary FROM employee_contracts 
         WHERE user_id = ? AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY start_date DESC LIMIT 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();
    return $contract ? (float)$contract['salary'] : null;
}

/**
 * Mettre à jour le salaire d'un employé
 * @param mysqli $db
 * @param int $user_id
 * @param float $new_salary
 * @return array [success => bool, message => string]
 */
function updateEmployeeSalary($db, $user_id, $new_salary) {
    try {
        $stmt = $db->prepare(
            "SELECT id FROM employee_contracts 
             WHERE user_id = ? AND (end_date IS NULL OR end_date >= CURDATE())
             LIMIT 1"
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $contract = $stmt->get_result()->fetch_assoc();

        if (!$contract) {
            return ['success' => false, 'message' => 'Aucun contrat actif trouvé'];
        }

        $stmt = $db->prepare("UPDATE employee_contracts SET salary = ? WHERE id = ?");
        $stmt->bind_param('di', $new_salary, $contract['id']);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Salaire mis à jour'];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Calculer la masse salariale d'une période
 * @param mysqli $db
 * @param string $start_date (Y-m-d)
 * @param string $end_date (Y-m-d)
 * @return float
 */
// ✅ CORRECT - Calculer la masse salariale mensuelle
function getTotalSalariesByPeriod($db, $start_date, $end_date) {
    // Récupérer le mois de la période
    $month = substr($start_date, 0, 7); // Extrait "2026-04" de "2026-04-01"

    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(ec.salary), 0) as total
         FROM employee_contracts ec
         INNER JOIN users u ON u.id = ec.user_id
         WHERE ec.start_date <= ? 
         AND (ec.end_date IS NULL OR ec.end_date >= ?)
         AND u.is_active = 1"
    );
    $stmt->bind_param('ss', $end_date, $start_date);
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Obtenir les contrats actifs d'un mois
 * @param mysqli $db
 * @param string $month (Y-m)
 * @return array
 */
function getActiveContractsByMonth($db, $month) {
    $month_start = $month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));

    $stmt = $db->prepare(
        "SELECT ec.*, u.full_name, u.email
         FROM employee_contracts ec
         INNER JOIN users u ON u.id = ec.user_id
         WHERE ec.start_date <= ? AND (ec.end_date IS NULL OR ec.end_date >= ?)
         AND u.is_active = 1
         ORDER BY u.full_name"
    );
    $stmt->bind_param('ss', $month_end, $month_start);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// 5. RAPPORTS FINANCIERS
// ============================================================================

/**
 * Calculer le résumé financier d'une période
 * @param mysqli $db
 * @param string $start_date (Y-m-d)
 * @param string $end_date (Y-m-d)
 * @return array
 */
function getFinancialSummary($db, $start_date, $end_date) {
    // Revenus
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM invoices 
         WHERE generated_date BETWEEN ? AND ? AND status = 'paid'"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $revenues = (float)$stmt->get_result()->fetch_assoc()['total'];

    // Dépenses
    $expenses = getTotalExpensesByPeriod($db, $start_date, $end_date);

    // Salaires
    $salaries = getTotalSalariesByPeriod($db, $start_date, $end_date);

    // Nombre de factures payées
    $stmt = $db->prepare(
        "SELECT COUNT(*) as count FROM invoices 
         WHERE generated_date BETWEEN ? AND ? AND status = 'paid'"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $invoice_count = (int)$stmt->get_result()->fetch_assoc()['count'];

    $net_income = $revenues - $expenses - $salaries;

    return [
        'revenues' => $revenues,
        'expenses' => $expenses,
        'salaries' => $salaries,
        'net_income' => $net_income,
        'invoice_count' => $invoice_count,
        'expense_ratio' => $revenues > 0 ? ($expenses / $revenues) * 100 : 0,
        'salary_ratio' => $revenues > 0 ? ($salaries / $revenues) * 100 : 0
    ];
}

/**
 * Obtenir les revenus par patient
 * @param mysqli $db
 * @param string $start_date (Y-m-d)
 * @param string $end_date (Y-m-d)
 * @return array
 */
function getRevenueByPatient($db, $start_date, $end_date) {
    $stmt = $db->prepare(
        "SELECT u.full_name, COUNT(i.id) as invoice_count, 
                SUM(i.total_amount) as total_amount
         FROM invoices i
         INNER JOIN patients p ON p.id = i.patient_id
         INNER JOIN users u ON u.id = p.user_id
         WHERE i.generated_date BETWEEN ? AND ? AND i.status = 'paid'
         GROUP BY p.id, u.full_name
         ORDER BY total_amount DESC
         LIMIT 20"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Générer un rapport financier complet
 * @param mysqli $db
 * @param string $start_date (Y-m-d)
 * @param string $end_date (Y-m-d)
 * @return array
 */
function generateFinancialReport($db, $start_date, $end_date) {
    $summary = getFinancialSummary($db, $start_date, $end_date);
    $revenue_by_patient = getRevenueByPatient($db, $start_date, $end_date);

    return [
        'period' => [
            'start' => $start_date,
            'end' => $end_date
        ],
        'summary' => $summary,
        'revenue_by_patient' => $revenue_by_patient,
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

// ============================================================================
// 6. FONCTIONS UTILITAIRES
// ============================================================================

/**
 * Obtenir le solde impayé total
 * @param mysqli $db
 * @return float
 */
function getTotalOutstanding($db) {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(total_amount - paid_amount), 0) as total
         FROM invoices WHERE status = 'unpaid'"
    );
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Obtenir le taux de recouvrement
 * @param mysqli $db
 * @param string $month (Y-m)
 * @return float (pourcentage)
 */
function getCollectionRate($db, $month = null) {
    if (!$month) {
        $month = date('Y-m');
    }

    $month_start = $month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));

    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) as generated
         FROM invoices WHERE generated_date BETWEEN ? AND ?"
    );
    $stmt->bind_param('ss', $month_start, $month_end);
    $stmt->execute();
    $generated = (float)$stmt->get_result()->fetch_assoc()['generated'];

    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) as paid
         FROM invoices WHERE generated_date BETWEEN ? AND ? AND status = 'paid'"
    );
    $stmt->bind_param('ss', $month_start, $month_end);
    $stmt->execute();
    $paid = (float)$stmt->get_result()->fetch_assoc()['paid'];

    return $generated > 0 ? ($paid / $generated) * 100 : 0;
}
function safeCount($db, $sql, $types = '', $params = []) {
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

// ============================================================================
// FONCTIONS POUR profil.php
// ============================================================================

/**
 * Récupérer les statistiques de présence pour un employé sur un mois
 * @param mysqli $db
 * @param int $user_id
 * @param int $year
 * @param int $month
 * @return array
 */
function getAttendanceStatsForEmployee($db, $user_id, $year, $month)
{
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));

    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days,
            COALESCE(SUM(hours_worked), 0) as total_hours,
            COALESCE(SUM(overtime_hours), 0) as total_overtime
        FROM attendances
        WHERE user_id = ? AND attendance_date BETWEEN ? AND ?
    ");
    $stmt->bind_param('iss', $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        return [
            'present_days' => 0,
            'absent_days' => 0,
            'late_days' => 0,
            'excused_days' => 0,
            'total_hours' => 0,
            'total_overtime' => 0
        ];
    }
    return $result;
}

/**
 * Calculer les jours de congé restants pour un employé (25 jours par an)
 * @param mysqli $db
 * @param int $user_id
 * @param int $year
 * @return int
 */
function getRemainingLeaveDays($db, $user_id, $year)
{
    $stmt = $db->prepare("
        SELECT SUM(DATEDIFF(end_date, start_date) + 1) as used_days 
        FROM leave_requests 
        WHERE user_id = ? AND YEAR(start_date) = ? AND status = 'approved'
    ");
    $stmt->bind_param('ii', $user_id, $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $used_days = (int)($result['used_days'] ?? 0);
    $total_allowed = 25;
    return max(0, $total_allowed - $used_days);
}
// ============================================================================
// 3. GESTION DES COMMANDES/DÉPENSES (FONCTIONS CRUD COMPLÈTES)
// ============================================================================


/**
 * Mettre à jour une commande fournisseur
 * @param mysqli $db
 * @param int $order_id
 * @param int $supplier_id
 * @param float $total_amount
 * @param string $notes
 * @return array [success => bool, message => string]
 */
function updatePurchaseOrder($db, $order_id, $supplier_id, $total_amount, $notes = '') {
    try {
        $stmt = $db->prepare(
            "UPDATE purchase_orders 
             SET supplier_id = ?, total_amount = ?, notes = ? 
             WHERE id = ?"
        );

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('idsi', $supplier_id, $total_amount, $notes, $order_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Commande mise à jour'];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Supprimer une commande fournisseur
 * @param mysqli $db
 * @param int $order_id
 * @return array [success => bool, message => string]
 */
function deletePurchaseOrder($db, $order_id) {
    try {
        // Vérifier si la commande peut être supprimée (uniquement si non livrée)
        $stmt = $db->prepare("SELECT status FROM purchase_orders WHERE id = ?");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order) {
            return ['success' => false, 'message' => 'Commande non trouvée'];
        }

        if ($order['status'] == 'delivered') {
            return ['success' => false, 'message' => 'Impossible de supprimer une commande déjà livrée'];
        }

        $stmt = $db->prepare("DELETE FROM purchase_orders WHERE id = ?");
        $stmt->bind_param('i', $order_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Commande supprimée'];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Mettre à jour le statut d'une commande fournisseur
 * @param mysqli $db
 * @param int $order_id
 * @param string $status (pending, confirmed, shipped, delivered, cancelled)
 * @return array [success => bool, message => string]
 */
function updatePurchaseOrderStatus($db, $order_id, $status) {
    try {
        $allowed_status = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed_status)) {
            return ['success' => false, 'message' => 'Statut invalide'];
        }

        // Si le statut devient "delivered", mettre à jour la date de livraison
        $stmt = $db->prepare("SELECT status FROM purchase_orders WHERE id = ?");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order) {
            return ['success' => false, 'message' => 'Commande non trouvée'];
        }

        if ($status == 'delivered') {
            $stmt = $db->prepare("UPDATE purchase_orders SET status = ?, delivery_date = NOW() WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        }

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        if ($status == 'delivered') {
            $stmt->bind_param('si', $status, $order_id);
        } else {
            $stmt->bind_param('si', $status, $order_id);
        }

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Statut mis à jour'];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Obtenir une commande par son ID
 * @param mysqli $db
 * @param int $order_id
 * @return array|null
 */
function getPurchaseOrderById($db, $order_id) {
    $stmt = $db->prepare(
        "SELECT po.*, s.name as supplier_name 
         FROM purchase_orders po
         LEFT JOIN suppliers s ON s.id = po.supplier_id
         WHERE po.id = ?"
    );
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
?>