<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
/**
 * FONCTIONS POUR LE MODULE PHARMACIEN
 * Gestion du stock, mouvements, prescriptions et médicaments
 */

// ============================================================================
// 1. GESTION DES MÉDICAMENTS
// ============================================================================

/**
 * Ajouter un nouveau médicament
 * @param mysqli $db
 * @param array $data
 * @return array [success => bool, message => string, id => int|null]
 */
/**
 * Ajouter un nouveau médicament
 * @param mysqli $db
 * @param array $data
 * @return array [success => bool, message => string, id => int|null]
 */
function addMedicine($db, $data) {
    try {
        // Préparer les variables avec des valeurs par défaut
        $name = $data['name'] ?? '';
        $generic_name = $data['generic_name'] ?? null;
        $category = $data['category'] ?? null;
        $dosage_form = $data['dosage_form'] ?? null;
        $strength = $data['strength'] ?? null;
        $quantity = $data['quantity'] ?? 0;
        $unit = $data['unit'] ?? 'boîte';
        $expiry_date = !empty($data['expiry_date']) ? $data['expiry_date'] : null;
        $threshold_alert = $data['threshold_alert'] ?? 10;
        $purchase_price = $data['purchase_price'] ?? 0;
        $selling_price = $data['selling_price'] ?? 0;
        $supplier_id = $data['supplier_id'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO medicines (name, generic_name, category, dosage_form, strength, quantity, unit, expiry_date, threshold_alert, purchase_price, selling_price, supplier_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        // CORRECTION: Utiliser des variables, pas des expressions
        $stmt->bind_param(
            'sssssisddddi',
            $name,
            $generic_name,
            $category,
            $dosage_form,
            $strength,
            $quantity,
            $unit,
            $expiry_date,
            $threshold_alert,
            $purchase_price,
            $selling_price,
            $supplier_id
        );

        if ($stmt->execute()) {
            // Enregistrer le mouvement de stock initial
            $medicine_id = $db->insert_id;

            // Si quantité > 0, enregistrer un mouvement d'entrée
            if ($quantity > 0) {
                recordStockMovement($db, $medicine_id, 'in', $quantity, 'commande', $_SESSION['user_id'] ?? 1);
            }

            return ['success' => true, 'message' => 'Médicament ajouté avec succès', 'id' => $medicine_id];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
/**
 * Obtenir tous les médicaments
 * @param mysqli $db
 * @param bool $only_active
 * @return array
 */
function getAllMedicines($db, $only_active = true) {
    $sql = "SELECT m.*, s.name as supplier_name,
            COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE -sm.quantity END), 0) as current_stock
            FROM medicines m
            LEFT JOIN suppliers s ON s.id = m.supplier_id
            LEFT JOIN stock_movements sm ON sm.medicine_id = m.id
            GROUP BY m.id
            ORDER BY m.name ASC";

    $result = $db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Obtenir un médicament par son ID
 * @param mysqli $db
 * @param int $medicine_id
 * @return array|null
 */
function getMedicineById($db, $medicine_id) {
    $stmt = $db->prepare("
        SELECT m.*, s.name as supplier_name,
        COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE -sm.quantity END), 0) as current_stock
        FROM medicines m
        LEFT JOIN suppliers s ON s.id = m.supplier_id
        LEFT JOIN stock_movements sm ON sm.medicine_id = m.id
        WHERE m.id = ?
        GROUP BY m.id
    ");
    $stmt->bind_param('i', $medicine_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Mettre à jour les détails d'un médicament
 * @param mysqli $db
 * @param int $medicine_id
 * @param array $updates
 * @return array [success => bool, message => string]
 */
function updateMedicine($db, $medicine_id, $updates) {
    try {
        $fields = [];
        $types = '';
        $values = [];

        if (isset($updates['name'])) {
            $fields[] = 'name = ?';
            $types .= 's';
            $values[] = $updates['name'];
        }
        if (isset($updates['generic_name'])) {
            $fields[] = 'generic_name = ?';
            $types .= 's';
            $values[] = $updates['generic_name'];
        }
        if (isset($updates['category'])) {
            $fields[] = 'category = ?';
            $types .= 's';
            $values[] = $updates['category'];
        }
        if (isset($updates['dosage_form'])) {
            $fields[] = 'dosage_form = ?';
            $types .= 's';
            $values[] = $updates['dosage_form'];
        }
        if (isset($updates['strength'])) {
            $fields[] = 'strength = ?';
            $types .= 's';
            $values[] = $updates['strength'];
        }
        if (isset($updates['quantity'])) {
            $fields[] = 'quantity = ?';
            $types .= 'i';
            $values[] = $updates['quantity'];
        }
        if (isset($updates['unit'])) {
            $fields[] = 'unit = ?';
            $types .= 's';
            $values[] = $updates['unit'];
        }
        if (isset($updates['expiry_date'])) {
            $fields[] = 'expiry_date = ?';
            $types .= 's';
            $values[] = $updates['expiry_date'];
        }
        if (isset($updates['threshold_alert'])) {
            $fields[] = 'threshold_alert = ?';
            $types .= 'i';
            $values[] = $updates['threshold_alert'];
        }
        if (isset($updates['purchase_price'])) {
            $fields[] = 'purchase_price = ?';
            $types .= 'd';
            $values[] = $updates['purchase_price'];
        }
        if (isset($updates['selling_price'])) {
            $fields[] = 'selling_price = ?';
            $types .= 'd';
            $values[] = $updates['selling_price'];
        }
        if (isset($updates['supplier_id'])) {
            $fields[] = 'supplier_id = ?';
            $types .= 'i';
            $values[] = $updates['supplier_id'];
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'Aucune donnée à mettre à jour'];
        }

        $fields[] = 'updated_at = NOW()';
        $values[] = $medicine_id;
        $types .= 'i';

        $query = "UPDATE medicines SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($query);

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Médicament mis à jour'];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Supprimer un médicament
 * @param mysqli $db
 * @param int $medicine_id
 * @return array [success => bool, message => string]
 */
function deleteMedicine($db, $medicine_id) {
    try {
        $stmt = $db->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->bind_param('i', $medicine_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Médicament supprimé'];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Obtenir les médicaments en rupture de stock
 * @param mysqli $db
 * @return array
 */
function getLowStockMedicines($db) {
    $stmt = $db->prepare("
        SELECT m.*,
               COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE -sm.quantity END), 0) as current_stock
        FROM medicines m
        LEFT JOIN stock_movements sm ON sm.medicine_id = m.id
        GROUP BY m.id
        HAVING current_stock <= m.threshold_alert
        ORDER BY current_stock ASC
    ");

    if (!$stmt) return [];

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Obtenir les médicaments expirant bientôt
 * @param mysqli $db
 * @param int $days
 * @return array
 */
function getExpiringMedicines($db, $days = 30) {
    $today = date('Y-m-d');
    $future = date('Y-m-d', strtotime("+$days days"));

    $stmt = $db->prepare("
        SELECT m.*,
               COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE -sm.quantity END), 0) as current_stock
        FROM medicines m
        LEFT JOIN stock_movements sm ON sm.medicine_id = m.id
        WHERE m.expiry_date BETWEEN ? AND ?
        GROUP BY m.id
        ORDER BY m.expiry_date ASC
    ");
    $stmt->bind_param('ss', $today, $future);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// 2. GESTION DES MOUVEMENTS DE STOCK
// ============================================================================

/**
 * Obtenir le stock actuel d'un médicament
 * @param mysqli $db
 * @param int $medicine_id
 * @return int
 */
function getCurrentStock($db, $medicine_id) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0) as stock
        FROM stock_movements
        WHERE medicine_id = ?
    ");
    $stmt->bind_param('i', $medicine_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return (int)$result['stock'];
}

/**

 * Enregistrer un mouvement de stock
 * @param mysqli $db
 * @param int $medicine_id
 * @param string $type (in/out)
 * @param int $quantity
 * @param string $reason
 * @param int $performed_by
 * @return array [success => bool, message => string]
 */
function recordStockMovement($db, $medicine_id, $type, $quantity, $reason, $performed_by, $notes = '', $reference_id = null, $reference_type = null) {
    try {
        if (!in_array($type, ['in', 'out'])) {
            return ['success' => false, 'message' => 'Type de mouvement invalide'];
        }

        $stmt = $db->prepare("
            INSERT INTO stock_movements (medicine_id, type, quantity, reason, reference_id, reference_type, performed_by, notes, movement_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('isisisss', $medicine_id, $type, $quantity, $reason, $reference_id, $reference_type, $performed_by, $notes);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Mouvement enregistré'];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
function recordLabStockMovement($db, $item_id, $operation, $quantity, $reason, $performed_by) {
    // operation = 'remove' (pour l'instant)
    $stmt = $db->prepare("
        INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('isdii', $item_id, $operation, $quantity, $reason, $performed_by);
    return $stmt->execute();
}
function approveLabOrder($db, $order_id, $pharmacien_id) {
    // 1. Récupérer la commande
    $order = getOrderById($db, $order_id);
    if (!$order || $order['status'] !== 'pending') return false;

    // 2. Diminuer lab_stock
    $stmt = $db->prepare("UPDATE lab_stock SET quantity = quantity - ? WHERE id = ?");
    $stmt->bind_param('ii', $order['quantity'], $order['item_id']);
    if (!$stmt->execute()) return false;

    // 3. Enregistrer mouvement
    $reason = "Commande laboratoire #" . $order_id;
    recordLabStockMovement($db, $order['item_id'], 'remove', $order['quantity'], $reason, $pharmacien_id);

    // 4. Mettre à jour statut commande
    $stmt = $db->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    return $stmt->execute();
}
/**
 * Obtenir les mouvements de stock d'une période
 * @param mysqli $db
 * @param string $start_date
 * @param string $end_date
 * @param int|null $medicine_id
 * @return array
 */
function getStockMovements($db, $start_date, $end_date, $medicine_id = null) {
    $sql = "SELECT sm.*, m.name as medicine_name,
                   u.full_name as performed_by_name
            FROM stock_movements sm
            INNER JOIN medicines m ON m.id = sm.medicine_id
            LEFT JOIN users u ON u.id = sm.performed_by
            WHERE DATE(sm.movement_date) BETWEEN ? AND ?";

    if ($medicine_id) {
        $sql .= " AND sm.medicine_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ssi', $start_date, $end_date, $medicine_id);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $start_date, $end_date);
    }

    if (!$stmt) return [];
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Obtenir les mouvements de stock d'un médicament
 * @param mysqli $db
 * @param int $medicine_id
 * @param int $limit
 * @return array
 */
function getMedicineStockMovements($db, $medicine_id, $limit = 20) {
    $stmt = $db->prepare("
        SELECT sm.*, u.full_name as performed_by_name
        FROM stock_movements sm
        LEFT JOIN users u ON u.id = sm.performed_by
        WHERE sm.medicine_id = ?
        ORDER BY sm.movement_date DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $medicine_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// 3. GESTION DES PRESCRIPTIONS/ORDONNANCES
// ============================================================================

/**
 * Obtenir les prescriptions en attente
 * @param mysqli $db
 * @return array
 */
function getPendingPrescriptions($db) {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name as patient_name, u.phone, u.email,
               d.full_name as doctor_name
        FROM prescriptions pr
        INNER JOIN patients p ON p.id = pr.patient_id
        INNER JOIN users u ON u.id = p.user_id
        INNER JOIN doctors doc ON doc.id = pr.doctor_id
        INNER JOIN users d ON d.id = doc.user_id
        WHERE pr.status = 'active'
        ORDER BY pr.prescription_date DESC
    ");

    if (!$stmt) return [];

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Obtenir les détails d'une prescription
 * @param mysqli $db
 * @param int $prescription_id
 * @return array|null
 */
function getPrescriptionDetails($db, $prescription_id) {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name as patient_name, u.phone, u.email,
               d.full_name as doctor_name, p.uhid, p.blood_type, p.allergies
        FROM prescriptions pr
        INNER JOIN patients p ON p.id = pr.patient_id
        INNER JOIN users u ON u.id = p.user_id
        INNER JOIN doctors doc ON doc.id = pr.doctor_id
        INNER JOIN users d ON d.id = doc.user_id
        WHERE pr.id = ?
    ");

    if (!$stmt) return null;

    $stmt->bind_param('i', $prescription_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Obtenir les articles d'une prescription
 * @param mysqli $db
 * @param int $prescription_id
 * @return array
 */
function getPrescriptionItems($db, $prescription_id) {
    $stmt = $db->prepare("
        SELECT pi.*, m.name as medicine_name, m.dosage_form, m.strength,
               COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE -sm.quantity END), 0) as current_stock
        FROM prescription_items pi
        INNER JOIN medicines m ON m.id = pi.medicine_id
        LEFT JOIN stock_movements sm ON sm.medicine_id = m.id
        WHERE pi.prescription_id = ?
        GROUP BY pi.id
        ORDER BY pi.id
    ");

    if (!$stmt) return [];

    $stmt->bind_param('i', $prescription_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Préparer une ordonnance (délivrer les médicaments)
 * @param mysqli $db
 * @param int $prescription_id
 * @param int $prepared_by (user_id du pharmacien)
 * @return array [success => bool, message => string]
 */
function preparePrescription($db, $prescription_id, $prepared_by) {
    try {
        // Obtenir les articles de la prescription
        $items = getPrescriptionItems($db, $prescription_id);

        if (empty($items)) {
            return ['success' => false, 'message' => 'Aucun article trouvé dans la prescription'];
        }

        // Vérifier la disponibilité de chaque médicament
        foreach ($items as $item) {
            $required_quantity = (int)$item['quantity'];
            $medicine_id = (int)$item['medicine_id'];
            $current_stock = getCurrentStock($db, $medicine_id);

            if ($current_stock < $required_quantity) {
                $medicine = getMedicineById($db, $medicine_id);
                return ['success' => false, 'message' => 'Stock insuffisant pour: ' . ($medicine['name'] ?? 'Médicament inconnu')];
            }
        }

        // Enregistrer les sorties de stock
        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $medicine_id = (int)$item['medicine_id'];

            // Enregistrer le mouvement de stock
            $result = recordStockMovement(
                $db,
                $medicine_id,
                'out',
                $quantity,
                'distribution',
                $prepared_by,
                'Délivrance ordonnance #' . $prescription_id
            );

            if (!$result['success']) {
                return $result;
            }
        }

        // Marquer la prescription comme délivrée
        $stmt = $db->prepare("
            UPDATE prescriptions SET status = 'delivered', prepared_by = ?, prepared_at = NOW() WHERE id = ?
        ");

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('ii', $prepared_by, $prescription_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Ordonnance délivrée et stock mis à jour'];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Rejeter une prescription
 * @param mysqli $db
 * @param int $prescription_id
 * @param string $reason
 * @return array [success => bool, message => string]
 */
function rejectPrescription($db, $prescription_id, $reason) {
    try {
        $stmt = $db->prepare("
            UPDATE prescriptions SET status = 'cancelled', notes = ? WHERE id = ?
        ");

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('si', $reason, $prescription_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Ordonnance rejetée'];
        } else {
            return ['success' => false, 'message' => $stmt->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Obtenir toutes les prescriptions avec historique
 * @param mysqli $db
 * @param string $status (optional: active|delivered|cancelled)
 * @param string $start_date (optional)
 * @param string $end_date (optional)
 * @return array
 */
function getAllPrescriptions($db, $status = null, $start_date = null, $end_date = null) {
    $query = "SELECT pr.*, u.full_name as patient_name, u.phone,
                     d.full_name as doctor_name
              FROM prescriptions pr
              INNER JOIN patients p ON p.id = pr.patient_id
              INNER JOIN users u ON u.id = p.user_id
              INNER JOIN doctors doc ON doc.id = pr.doctor_id
              INNER JOIN users d ON d.id = doc.user_id
              WHERE 1=1";

    $types = '';
    $params = [];

    if ($status) {
        $query .= " AND pr.status = ?";
        $types .= 's';
        $params[] = $status;
    }

    if ($start_date && $end_date) {
        $query .= " AND pr.prescription_date BETWEEN ? AND ?";
        $types .= 'ss';
        $params[] = $start_date;
        $params[] = $end_date;
    }

    $query .= " ORDER BY pr.prescription_date DESC";

    $stmt = $db->prepare($query);
    if (!$stmt) return [];

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// 4. GESTION DES FOURNISSEURS
// ============================================================================

/**
 * Obtenir tous les fournisseurs
 * @param mysqli $db
 * @param bool $active_only
 * @return array
 */
function getAllSuppliers($db, $active_only = true) {
    $sql = "SELECT * FROM suppliers";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name ASC";

    $result = $db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Obtenir un fournisseur par son ID
 * @param mysqli $db
 * @param int $supplier_id
 * @return array|null
 */
function getSupplierById($db, $supplier_id) {
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ============================================================================
// 5. STATISTIQUES ET RAPPORTS
// ============================================================================

/**
 * Obtenir les statistiques du pharmacien
 * @param mysqli $db
 * @param string $start_date (Y-m-d)
 * @param string $end_date (Y-m-d)
 * @return array
 */
function getPharmacyStatistics($db, $start_date, $end_date) {
    $stats = [];

    // Total médicaments
    $stmt = $db->query("SELECT COUNT(*) as count FROM medicines");
    $stats['total_medicines'] = $stmt ? (int)$stmt->fetch_assoc()['count'] : 0;

    // Prescriptions délivrées
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'delivered'");
    $stmt->execute();
    $stats['prescriptions_delivered'] = (int)$stmt->get_result()->fetch_assoc()['count'];

    // Prescriptions en attente
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'active'");
    $stmt->execute();
    $stats['prescriptions_pending'] = (int)$stmt->get_result()->fetch_assoc()['count'];

    // Médicaments en rupture (stock <= threshold_alert)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM medicines m
        WHERE COALESCE((
            SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END)
            FROM stock_movements
            WHERE medicine_id = m.id
        ), 0) <= m.threshold_alert
    ");
    $stmt->execute();
    $stats['low_stock_count'] = (int)$stmt->get_result()->fetch_assoc()['count'];

    // Mouvements de stock aujourd'hui
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM stock_movements 
        WHERE DATE(movement_date) = ?
    ");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $stats['stock_movements'] = (int)$stmt->get_result()->fetch_assoc()['count'];

    return $stats;
}

/**
 * Obtenir les statistiques de ventes
 * @param mysqli $db
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function getSalesStatistics($db, $start_date, $end_date) {
    $stats = [];

    // Total des ventes
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(quantity), 0) as total_quantity
        FROM stock_movements
        WHERE type = 'out' AND reason = 'distribution'
        AND DATE(movement_date) BETWEEN ? AND ?
    ");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['total_sales'] = $result['count'] ?? 0;
    $stats['total_items_sold'] = $result['total_quantity'] ?? 0;

    // Top médicaments vendus
    $stmt = $db->prepare("
        SELECT m.name, SUM(sm.quantity) as total_sold
        FROM stock_movements sm
        INNER JOIN medicines m ON m.id = sm.medicine_id
        WHERE sm.type = 'out' AND sm.reason = 'distribution'
        AND DATE(sm.movement_date) BETWEEN ? AND ?
        GROUP BY sm.medicine_id, m.name
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $stats['top_medicines'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return $stats;
}

// ============================================================================
// 6. FONCTIONS UTILITAIRES
// ============================================================================

/**
 * Fonction utilitaire pour compter
 * @param mysqli $db
 * @param string $sql
 * @param string $types
 * @param array $params
 * @return int
 */
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

/**
 * Vérifier la disponibilité d'un médicament
 * @param mysqli $db
 * @param int $medicine_id
 * @param int $required_quantity
 * @return bool
 */
function isMedicineAvailable($db, $medicine_id, $required_quantity) {
    $current_stock = getCurrentStock($db, $medicine_id);
    return $current_stock >= $required_quantity;
}

/**
 * Formater le statut
 * @param string $status
 * @return string
 */
function formatStatus($status) {
    $labels = [
        'active' => 'En attente',
        'delivered' => 'Délivrée',
        'cancelled' => 'Rejetée',
        'in' => 'Entrée',
        'out' => 'Sortie',
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'shipped' => 'Expédiée'
    ];
    return $labels[$status] ?? $status;
}

/**
 * Générer un numéro de lot unique
 * @return string
 */
function generateBatchNumber() {
    return 'BATCH-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Calculer la valeur totale du stock
 * @param mysqli $db
 * @return float
 */
function getTotalStockValue($db) {
    $stmt = $db->prepare("
        SELECT SUM(m.purchase_price * COALESCE((
            SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END)
            FROM stock_movements
            WHERE medicine_id = m.id
        ), 0)) as total_value
        FROM medicines m
    ");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return (float)($result['total_value'] ?? 0);
}
// ============================================================================
// 4. GESTION DES FOURNISSEURS (AJOUTER CES FONCTIONS)
// ============================================================================

/**
 * Ajouter un fournisseur
 * @param mysqli $db
 * @param string $name
 * @param string|null $contact_person
 * @param string|null $phone
 * @param string|null $email
 * @param string|null $address
 * @param string $supplier_type
 * @return array [success => bool, message => string, id => int|null]
 */
function addSupplier($db, $name, $contact_person = null, $phone = null, $email = null, $address = null, $supplier_type = 'medicines') {
    try {
        $stmt = $db->prepare("
            INSERT INTO suppliers (name, contact_person, phone, email, address, supplier_type, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('ssssss', $name, $contact_person, $phone, $email, $address, $supplier_type);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Fournisseur ajouté avec succès', 'id' => $db->insert_id];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Mettre à jour un fournisseur
 * @param mysqli $db
 * @param int $supplier_id
 * @param string $name
 * @param string|null $contact_person
 * @param string|null $phone
 * @param string|null $email
 * @param string|null $address
 * @param string $supplier_type
 * @param int $is_active
 * @return array [success => bool, message => string]
 */
function updateSupplier($db, $supplier_id, $name, $contact_person = null, $phone = null, $email = null, $address = null, $supplier_type = 'medicines', $is_active = 1) {
    try {
        $stmt = $db->prepare("
            UPDATE suppliers 
            SET name = ?, contact_person = ?, phone = ?, email = ?, address = ?, supplier_type = ?, is_active = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            return ['success' => false, 'message' => 'Erreur: ' . $db->error];
        }

        $stmt->bind_param('ssssssii', $name, $contact_person, $phone, $email, $address, $supplier_type, $is_active, $supplier_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Fournisseur mis à jour'];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Supprimer un fournisseur
 * @param mysqli $db
 * @param int $supplier_id
 * @return array [success => bool, message => string]
 */
function deleteSupplier($db, $supplier_id) {
    try {
        // Vérifier si le fournisseur a des médicaments associés
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM medicines WHERE supplier_id = ?");
        $stmt->bind_param('i', $supplier_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];

        if ($count > 0) {
            return ['success' => false, 'message' => "Impossible de supprimer: ce fournisseur est lié à $count médicament(s)"];
        }

        $stmt = $db->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->bind_param('i', $supplier_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Fournisseur supprimé'];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Obtenir un fournisseur par son ID (détaillé)
 * @param mysqli $db
 * @param int $supplier_id
 * @return array|null
 */
function getSupplierByIdDetailed($db, $supplier_id) {
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
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

?>