<?php
// ================================================================
//  functions.php — Toutes les fonctions pour le laborantin
// ================================================================

require_once '../connection.php';

function requireLaborantin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (($_SESSION['usertype'] ?? null) !== 'laborantin') {
        header('Location: ../login.php');
        exit;
    }
}

function getCurrentLaborantinId() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user_id'] ?? 0;
}

function getCurrentLaborantinName($db) {
    $user_id = getCurrentLaborantinId();
    if (!$user_id) return 'Laborantin';
    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['full_name'] ?? 'Laborantin';
}

// ==================== FONCTION DE LOGS SÉCURISÉE (VERSION UNIQUE) ====================
/**
 * Fonction sécurisée pour insérer dans les logs
 * Force le format JSON pour respecter la contrainte CHECK
 */
function secureLogInsert($db, $user_id, $action_name, $details_message) {
    // ========== 1. VALIDATION user_id ==========
    if (empty($user_id) || !is_numeric($user_id) || $user_id <= 0) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $_SESSION['user_id'] ?? 1;
        if ($user_id <= 0) $user_id = 1;
    } else {
        $user_id = (int)$user_id;
    }

    // ========== 2. VALIDATION action_name ==========
    if (empty($action_name) || !is_string($action_name)) {
        $action_name = 'unknown_action';
    }
    $action_name = substr(preg_replace('/[^a-zA-Z0-9_-]/', '_', $action_name), 0, 100);

    // ========== 3. FORCER LE FORMAT JSON ==========
    // Si ce n'est pas un tableau, le convertir en tableau
    if (!is_array($details_message)) {
        $details_message = ['message' => (string)$details_message];
    }

    // Encoder en JSON
    $details_message = json_encode($details_message, JSON_UNESCAPED_UNICODE);

    // Si l'encodage échoue, mettre un message par défaut
    if ($details_message === false || $details_message === null) {
        $details_message = json_encode(['message' => 'Erreur de sérialisation']);
    }

    // Limiter la longueur si nécessaire
    if (strlen($details_message) > 10000) {
        $details_message = substr($details_message, 0, 10000);
    }

    // ========== 4. EXÉCUTION ==========
    $log = $db->prepare("
        INSERT INTO logs (user_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    if (!$log) {
        error_log("Erreur prepare logs: " . $db->error);
        return false;
    }

    $log->bind_param('iss', $user_id, $action_name, $details_message);

    if (!$log->execute()) {
        error_log("Erreur insertion logs: " . $log->error);
        return false;
    }

    return true;
}

function getLabStats($db) {
    $today = date('Y-m-d');
    return [
        'pending' => safeCount($db, "SELECT COUNT(*) c FROM lab_tests WHERE status = 'pending'"),
        'in_progress' => safeCount($db, "SELECT COUNT(*) c FROM lab_tests WHERE status = 'in_progress'"),
        'urgent' => safeCount($db, "SELECT COUNT(*) c FROM lab_tests WHERE priority = 'urgent' AND status IN ('pending','in_progress')"),
        'completed_today' => safeCount($db, "SELECT COUNT(*) c FROM lab_tests WHERE status = 'completed' AND DATE(created_at) = ?", 's', [$today]),
        'low_stock' => safeCount($db, "SELECT COUNT(*) c FROM lab_stock WHERE quantity <= threshold_alert")
    ];
}

function safeCount($db, $sql, $types = '', $params = []) {
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

function getPatientsAjax($db, $q) {
    $kw = "%$q%";
    $stmt = $db->prepare("
        SELECT pt.id, pt.uhid, u.full_name, u.phone, pt.dob, pt.blood_type, pt.allergies, pt.gender
        FROM patients pt JOIN users u ON u.id = pt.user_id
        WHERE u.full_name LIKE ? OR u.phone LIKE ? OR pt.uhid LIKE ?
        LIMIT 15
    ");
    $stmt->bind_param('sss', $kw, $kw, $kw);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getDoctorsForLab($db) {
    $res = $db->query("
        SELECT d.id, u.full_name AS name, sp.sname AS specialty
        FROM doctors d JOIN users u ON u.id = d.user_id
        LEFT JOIN specialties sp ON sp.id = d.specialty_id
        WHERE u.is_active = 1 ORDER BY u.full_name ASC
    ");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function getLabTests($db, $filters = []) {
    $sql = "
        SELECT lt.*, u.full_name as patient_name, u.phone, u.email, u2.full_name as doctor_name,
               p.uhid, p.gender as patient_sex,
               TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as patient_age
        FROM lab_tests lt
        JOIN patients p ON p.id = lt.patient_id
        JOIN users u ON u.id = p.user_id
        LEFT JOIN doctors d ON d.id = lt.doctor_id
        LEFT JOIN users u2 ON u2.id = d.user_id
        WHERE 1=1
    ";
    $params = []; $types = '';
    if (!empty($filters['status'])) {
        $sql .= " AND lt.status = ?";
        $params[] = $filters['status']; $types .= 's';
    }
    if (!empty($filters['priority'])) {
        $sql .= " AND lt.priority = ?";
        $params[] = $filters['priority']; $types .= 's';
    }
    if (!empty($filters['date'])) {
        $sql .= " AND DATE(lt.created_at) = ?";
        $params[] = $filters['date']; $types .= 's';
    }
    $sql .= " ORDER BY lt.created_at DESC";

    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Récupérer les analyses groupées par patient et date
function getGroupedLabTests($db, $filters = []) {
    $sql = "
        SELECT 
            lt.patient_id,
            u.full_name as patient_name,
            u.phone as patient_phone,
            u.email as patient_email,
            p.uhid,
            p.gender as patient_sex,
            TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as patient_age,
            DATE(lt.created_at) as test_date,
            COUNT(lt.id) as tests_count,
            GROUP_CONCAT(lt.id ORDER BY lt.id) as test_ids,
            GROUP_CONCAT(lt.test_name ORDER BY lt.id SEPARATOR '|||') as test_names,
            GROUP_CONCAT(lt.status ORDER BY lt.id SEPARATOR '|||') as test_statuses,
            GROUP_CONCAT(lt.priority ORDER BY lt.id SEPARATOR '|||') as test_priorities,
            GROUP_CONCAT(lt.result ORDER BY lt.id SEPARATOR '|||') as test_results,
            GROUP_CONCAT(lt.result_date ORDER BY lt.id SEPARATOR '|||') as result_dates,
            MAX(CASE WHEN lt.priority = 'urgent' THEN 1 ELSE 0 END) as has_urgent,
            MAX(lt.created_at) as latest_created
        FROM lab_tests lt
        JOIN patients p ON p.id = lt.patient_id
        JOIN users u ON u.id = p.user_id
        WHERE 1=1
    ";
    $params = [];
    $types = '';

    if (!empty($filters['status'])) {
        $sql .= " AND lt.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }

    if (!empty($filters['priority'])) {
        $sql .= " AND lt.priority = ?";
        $params[] = $filters['priority'];
        $types .= 's';
    }

    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(lt.created_at) >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(lt.created_at) <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }

    $sql .= " GROUP BY lt.patient_id, DATE(lt.created_at)
              ORDER BY has_urgent DESC, latest_created DESC";

    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Récupérer les détails d'un groupe d'analyses
function getGroupDetails($db, $patient_id, $test_date) {
    $sql = "
        SELECT lt.*, u.full_name as patient_name, u.phone, u.email, u2.full_name as doctor_name,
               p.uhid, p.gender as patient_sex, 
               TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as patient_age
        FROM lab_tests lt
        JOIN patients p ON p.id = lt.patient_id
        JOIN users u ON u.id = p.user_id
        LEFT JOIN doctors d ON d.id = lt.doctor_id
        LEFT JOIN users u2 ON u2.id = d.user_id
        WHERE lt.patient_id = ? AND DATE(lt.created_at) = ?
        ORDER BY lt.id ASC
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('is', $patient_id, $test_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getLabTestById($db, $id) {
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name as patient_name, u.phone, u.email, u2.full_name as doctor_name,
               p.uhid, p.gender as patient_sex,
               TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as patient_age
        FROM lab_tests lt
        JOIN patients p ON p.id = lt.patient_id
        JOIN users u ON u.id = p.user_id
        LEFT JOIN doctors d ON d.id = lt.doctor_id
        LEFT JOIN users u2 ON u2.id = d.user_id
        WHERE lt.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getLabStockItems($db, $filter = '') {
    $sql = "SELECT * FROM lab_stock ORDER BY item_name ASC";
    $res = $db->query($sql);
    $items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    if ($filter === 'low') {
        $items = array_filter($items, fn($i) => $i['quantity'] <= $i['threshold_alert']);
    }
    return $items;
}

function getStockMovements($db, $item_id = null) {
    $sql = "SELECT m.*, u.full_name as performed_by_name, s.item_name
            FROM lab_stock_movements m
            LEFT JOIN users u ON u.id = m.performed_by
            LEFT JOIN lab_stock s ON s.id = m.item_id
            ORDER BY m.created_at DESC LIMIT 50";
    if ($item_id) {
        $sql = "SELECT m.*, u.full_name as performed_by_name, s.item_name
                FROM lab_stock_movements m
                LEFT JOIN users u ON u.id = m.performed_by
                LEFT JOIN lab_stock s ON s.id = m.item_id
                WHERE m.item_id = ? ORDER BY m.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $res = $db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function consumeStock($db, $test_id, $items) {
    $errors = [];
    foreach ($items as $item_id => $qty) {
        $stmt = $db->prepare("SELECT quantity, item_name FROM lab_stock WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $stock = $stmt->get_result()->fetch_assoc();
        if (!$stock) { $errors[] = "Consommable introuvable"; continue; }
        if ($stock['quantity'] < $qty) { $errors[] = "Stock insuffisant: {$stock['item_name']}"; continue; }

        $new_qty = $stock['quantity'] - $qty;
        $db->prepare("UPDATE lab_stock SET quantity = ? WHERE id = ?")->bind_param('di', $new_qty, $item_id)->execute();
        $db->prepare("INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by) VALUES (?, 'out', ?, CONCAT('Analyse #', ?), ?)")
            ->bind_param('idii', $item_id, $qty, $test_id, getCurrentLaborantinId())->execute();
        $db->prepare("INSERT INTO lab_test_consumables (test_id, item_id, quantity_used) VALUES (?, ?, ?)")
            ->bind_param('iid', $test_id, $item_id, $qty)->execute();

        checkLowStockAlert($db, $item_id);
    }
    return $errors;
}

function checkLowStockAlert($db, $item_id) {
    $stmt = $db->prepare("SELECT id, item_name, quantity, threshold_alert FROM lab_stock WHERE id = ? AND quantity <= threshold_alert");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    if ($item) {
        $check = $db->prepare("SELECT id FROM alerts WHERE type='stock_request' AND reference_id=? AND is_read=0");
        $check->bind_param('i', $item_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $role = $db->query("SELECT id FROM roles WHERE role_name='pharmacien' LIMIT 1")->fetch_assoc();
            if ($role) {
                $db->prepare("INSERT INTO alerts (type, severity, message, reference_id, target_role_id, created_at) VALUES ('stock_request', 'high', CONCAT('Stock faible: ', ?), ?, ?, NOW())")
                    ->bind_param('sii', $item['item_name'], $item_id, $role['id'])->execute();
            }
        }
    }
}

function requestReapprovisionnement($db, $item_id, $quantity = 1) {
    $stmt = $db->prepare("SELECT item_name, unit FROM lab_stock WHERE id = ?");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    if ($item) {
        $requester_id = getCurrentLaborantinId();
        $order = $db->prepare("
            INSERT INTO orders (item_id, item_type, quantity, status, requester_id, created_at)
            VALUES (?, 'stock', ?, 'pending', ?, NOW())
        ");
        $order->bind_param('iii', $item_id, $quantity, $requester_id);
        if ($order->execute()) {
            return true;
        }
    }
    return false;
}

function sendResultEmailWithPDF($db, $test_ids, $patient_email, $patient_name, $results_data) {
    $html_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .header { background: #2c7da0; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .result-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .result-table th, .result-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            .result-table th { background: #f5f5f5; }
            .critical { background: #ffe5e5; color: #cc0000; }
            .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>Laboratoire d'Analyses Médicales</h2>
            <p>Résultats d'analyses</p>
        </div>
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($patient_name) . "</strong>,</p>
            <p>Veuillez trouver ci-dessous les résultats de vos analyses :</p>
            <table class='result-table'>
                <thead>
                    <tr><th>Analyse</th><th>Résultat</th><th>Unité</th><th>Date</th><th>Valeurs de référence</th></tr>
                </thead>
                <tbody>";

    foreach ($results_data as $result) {
        $critical_class = $result['is_critical'] ? 'critical' : '';
        $html_content .= "
                    <tr class='{$critical_class}'>
                        <td><strong>" . htmlspecialchars($result['test_name']) . "</strong></td>
                        <td>" . nl2br(htmlspecialchars($result['result'])) . "</td>
                        <td>" . htmlspecialchars($result['unit_measure'] ?? '-') . "</td>
                        <td>" . date('d/m/Y', strtotime($result['result_date'])) . "</td>
                        <td>" . htmlspecialchars($result['reference_range'] ?? '-') . "</td>
                    </tr>";
    }

    $html_content .= "
                </tbody>
            </table>
            <p><em>Ces résultats sont communiqués à titre informatif. Seul votre médecin traitant est habilité à les interpréter.</em></p>
        </div>
        <div class='footer'>
            <p>Laboratoire - Tél: 0XXX XX XX XX</p>
            <p>Ce message est généré automatiquement, merci de ne pas y répondre.</p>
        </div>
    </body>
    </html>";

    // Utilisation de la nouvelle fonction de log sécurisée
    $log_details = 'Email résultats envoyé à ' . $patient_email . ' pour ' . count($results_data) . ' analyses';
    secureLogInsert($db, getCurrentLaborantinId(), 'email_result_pdf', $log_details);

    return $html_content;
}

function updateTestStatus($db, $test_id, $status) {
    $stmt = $db->prepare("UPDATE lab_tests SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $test_id);
    return $stmt->execute();
}

function addResult($db, $test_id, $result, $is_critical, $unit, $notes, $file_url = null) {
    $result_date = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        UPDATE lab_tests 
        SET result = ?, is_critical = ?, unit_measure = ?, notes = ?, result_file_url = ?, 
            result_date = ?, status = 'completed', performed_by = ?
        WHERE id = ?
    ");
    $performed_by = getCurrentLaborantinId();
    $stmt->bind_param('sissssii', $result, $is_critical, $unit, $notes, $file_url, $result_date, $performed_by, $test_id);
    return $stmt->execute();
}

function addBatchResults($db, $test_results) {
    $success = 0;
    $errors = [];

    foreach ($test_results as $data) {
        $stmt = $db->prepare("
            UPDATE lab_tests 
            SET result = ?, is_critical = ?, unit_measure = ?, notes = ?, 
                reference_range = ?,
                result_date = ?, status = 'completed', performed_by = ?
            WHERE id = ?
        ");
        $performed_by = getCurrentLaborantinId();
        $result_date = date('Y-m-d H:i:s');
        $stmt->bind_param('sissisii',
            $data['result'],
            $data['is_critical'],
            $data['unit_measure'],
            $data['notes'],
            $data['reference_range'],
            $result_date,
            $performed_by,
            $data['test_id']
        );
        if ($stmt->execute()) {
            $success++;
        } else {
            $errors[] = "Test #{$data['test_id']}: " . $stmt->error;
        }
    }

    return ['success' => $success, 'errors' => $errors];
}

// ==================== GÉNÉRATION AUTOMATIQUE DES VALEURS DE RÉFÉRENCE ====================

function getReferenceRangesByAnalysis() {
    return [
        'Hémogramme (NFS)' => [
            'Homme' => 'Globules rouges: 4.5-5.9 x10¹²/L | Hémoglobine: 13.5-17.5 g/dL | Hématocrite: 40-52% | VGM: 80-100 fL | TCMH: 27-34 pg | CCMH: 32-36 g/dL | Globules blancs: 4-10 x10⁹/L | Plaquettes: 150-400 x10⁹/L',
            'Femme' => 'Globules rouges: 4.0-5.2 x10¹²/L | Hémoglobine: 12-16 g/dL | Hématocrite: 36-46% | VGM: 80-100 fL | TCMH: 27-34 pg | CCMH: 32-36 g/dL | Globules blancs: 4-10 x10⁹/L | Plaquettes: 150-400 x10⁹/L',
            'Enfant' => 'Globules rouges: 3.8-5.2 x10¹²/L | Hémoglobine: 11-15 g/dL | Hématocrite: 34-45% | Globules blancs: 4-15 x10⁹/L | Plaquettes: 150-450 x10⁹/L'
        ],
        'Glycémie' => [
            'Homme' => 'Jeûne: 0.70-1.10 g/L (3.9-6.1 mmol/L)',
            'Femme' => 'Jeûne: 0.70-1.10 g/L (3.9-6.1 mmol/L)',
            'Enfant' => 'Jeûne: 0.60-1.00 g/L (3.3-5.6 mmol/L)'
        ],
        'HbA1c' => [
            'Homme' => 'Normal: 4-6% (20-42 mmol/mol)',
            'Femme' => 'Normal: 4-6% (20-42 mmol/mol)',
            'Enfant' => 'Normal: 4-5.7% (20-39 mmol/mol)'
        ],
        'Créatinine' => [
            'Homme' => '60-110 µmol/L (0.7-1.2 mg/dL)',
            'Femme' => '45-90 µmol/L (0.5-1.0 mg/dL)',
            'Enfant' => '20-60 µmol/L'
        ],
        'Cholestérol total' => [
            'Homme' => 'Optimal: < 2.00 g/L (5.2 mmol/L)',
            'Femme' => 'Optimal: < 2.00 g/L (5.2 mmol/L)',
            'Enfant' => '< 1.70 g/L (4.4 mmol/L)'
        ],
        'TSH' => [
            'Homme' => '0.4-4.0 mUI/L',
            'Femme' => '0.4-4.0 mUI/L',
            'Enfant' => '0.5-5.0 mUI/L'
        ],
        'CRP' => [
            'Homme' => '< 5 mg/L',
            'Femme' => '< 5 mg/L',
            'Enfant' => '< 5 mg/L'
        ],
        'Ferritine' => [
            'Homme' => '30-300 µg/L',
            'Femme' => '15-200 µg/L',
            'Enfant' => '7-100 µg/L'
        ],
        'Vitamine D' => [
            'Homme' => '30-60 ng/mL',
            'Femme' => '30-60 ng/mL',
            'Enfant' => '30-60 ng/mL'
        ],
        'VS' => [
            'Homme' => '< 15 mm/h',
            'Femme' => '< 20 mm/h',
            'Enfant' => '< 10 mm/h'
        ]
    ];
}

function getAutoReferenceRange($test_name, $patient_sex = 'Homme', $patient_age = 30) {
    $refs = getReferenceRangesByAnalysis();

    if (!isset($refs[$test_name])) {
        return null;
    }

    $test_refs = $refs[$test_name];

    // Pour les enfants (moins de 18 ans)
    if ($patient_age !== null && $patient_age < 18 && isset($test_refs['Enfant'])) {
        return $test_refs['Enfant'];
    }

    // Par sexe (convertir 'M' ou 'Male' ou 'Homme')
    $sex = $patient_sex;
    if ($sex === 'M' || $sex === 'Male') $sex = 'Homme';
    if ($sex === 'F' || $sex === 'Female') $sex = 'Femme';

    if ($sex && isset($test_refs[$sex])) {
        return $test_refs[$sex];
    }

    // Fallback
    if (isset($test_refs['Homme'])) {
        return $test_refs['Homme'];
    }

    return null;
}

// ==================== CONSOMMATION AUTOMATIQUE DE STOCK ====================

/**
 * Récupère la liste des consommables nécessaires pour une analyse
 * @param bool $auto_only - Si true, ne retourne que les consommables à déduction automatique
 */
function getRequiredConsumablesForTest($db, $test_name, $auto_only = true) {
    $sql = "
        SELECT r.item_id, r.quantity_required, r.is_auto_deduct,
               s.item_name, s.quantity as current_stock, s.unit, s.threshold_alert
        FROM lab_test_consumables_required r
        JOIN lab_stock s ON s.id = r.item_id
        WHERE r.test_name = ?
    ";

    if ($auto_only) {
        $sql .= " AND r.is_auto_deduct = 1";
    }

    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $test_name);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Consomme automatiquement les consommables pour une analyse
 * NE consomme que les consommables marqués is_auto_deduct = 1
 */
function autoConsumeStockForTest($db, $test_id, $test_name) {
    $required_items = getRequiredConsumablesForTest($db, $test_name, true);

    if (empty($required_items)) {
        return ['success' => true, 'errors' => [], 'consumed' => []];
    }

    $errors = [];
    $consumed = [];

    foreach ($required_items as $item) {
        $item_id = $item['item_id'];
        $qty_required = $item['quantity_required'];

        // Vérifier le stock
        if ($item['current_stock'] < $qty_required) {
            $errors[] = "Stock insuffisant pour {$item['item_name']} (disponible: {$item['current_stock']} {$item['unit']}, requis: $qty_required)";
            continue;
        }

        // Calculer le nouveau stock
        $new_qty = $item['current_stock'] - $qty_required;

        // Mettre à jour le stock
        $update = $db->prepare("UPDATE lab_stock SET quantity = ? WHERE id = ?");
        $update->bind_param('di', $new_qty, $item_id);
        $update->execute();

        // Enregistrer le mouvement
        $movement = $db->prepare("
            INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
            VALUES (?, 'out', ?, CONCAT('Auto - Analyse #', ?, ' - ', ?), ?, NOW())
        ");
        $movement->bind_param('idisi', $item_id, $qty_required, $test_id, $test_name, getCurrentLaborantinId());
        $movement->execute();

        // Enregistrer dans lab_test_consumables
        $consumable_log = $db->prepare("
            INSERT INTO lab_test_consumables (test_id, item_id, quantity_used)
            VALUES (?, ?, ?)
        ");
        $consumable_log->bind_param('iid', $test_id, $item_id, $qty_required);
        $consumable_log->execute();

        // Vérifier alerte stock faible
        if ($new_qty <= $item['threshold_alert']) {
            checkLowStockAlert($db, $item_id);
        }

        $consumed[] = [
            'item_name' => $item['item_name'],
            'quantity' => $qty_required,
            'unit' => $item['unit'],
            'auto_deducted' => true
        ];
    }

    return [
        'success' => empty($errors),
        'errors' => $errors,
        'consumed' => $consumed
    ];
}

/**
 * Vérifie si le stock est suffisant pour une analyse
 */
function checkStockAvailabilityForTest($db, $test_name) {
    $required_items = getRequiredConsumablesForTest($db, $test_name, true);

    if (empty($required_items)) {
        return ['available' => true, 'errors' => [], 'items' => []];
    }

    $errors = [];
    foreach ($required_items as $item) {
        if ($item['current_stock'] < $item['quantity_required']) {
            $errors[] = "{$item['item_name']}: {$item['current_stock']} {$item['unit']} disponible, {$item['quantity_required']} requis";
        }
    }

    return [
        'available' => empty($errors),
        'errors' => $errors,
        'items' => $required_items
    ];
}

/**
 * Récupère l'historique des consommations pour une analyse
 */
function getConsumedStockForTest($db, $test_id) {
    $stmt = $db->prepare("
        SELECT c.*, s.item_name, s.unit
        FROM lab_test_consumables c
        JOIN lab_stock s ON s.id = c.item_id
        WHERE c.test_id = ?
    ");
    $stmt->bind_param('i', $test_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Récupère les consommables à gestion manuelle
 */
function getManualConsumables($db) {
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.item_name, s.quantity, s.unit, s.threshold_alert, s.location
        FROM lab_stock s
        JOIN lab_test_consumables_required r ON r.item_id = s.id
        WHERE r.is_auto_deduct = 0
        ORDER BY s.item_name
    ");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ==================== COMMANDES ====================

/**
 * Récupère toutes les commandes pour le pharmacien
 */
function getPharmacistOrders($db, $status = null) {
    $sql = "
        SELECT o.*, 
               u.full_name as requester_name,
               s.item_name as stock_item_name,
               s.unit as stock_unit
        FROM orders o
        JOIN users u ON u.id = o.requester_id
        LEFT JOIN lab_stock s ON s.id = o.item_id AND o.item_type = 'stock'
        WHERE o.pharmacist_id = ? OR o.pharmacist_id IS NULL
    ";

    if ($status) {
        $sql .= " AND o.status = ?";
        $stmt = $db->prepare($sql . " ORDER BY o.created_at DESC");
        $stmt->bind_param('is', getCurrentLaborantinId(), $status);
    } else {
        $stmt = $db->prepare($sql . " ORDER BY o.created_at DESC");
        $stmt->bind_param('i', getCurrentLaborantinId());
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Récupère les commandes en attente pour le pharmacien
 */
function getPendingOrders($db) {
    return getPharmacistOrders($db, 'pending');
}

/**
 * Met à jour le statut d'une commande
 */
function updateOrderStatus($db, $order_id, $status) {
    $valid_status = ['pending', 'approved', 'rejected', 'completed'];
    if (!in_array($status, $valid_status)) {
        return false;
    }

    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $order_id);
    return $stmt->execute();
}

/**
 * Récupère les statistiques des commandes pour le laborantin */
function getOrderStats($db, $laborantin_id) {
    $stats = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'completed' => 0,
        'total' => 0
    ];

    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM orders 
        WHERE requester_id = ?
        GROUP BY status
    ");
    $stmt->bind_param('i', $laborantin_id);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($results as $row) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['count'];
        }
        $stats['total'] += $row['count'];
    }

    return $stats;
}

// ==================== CONSTANTES ====================
const LAB_STATUS_LABELS  = ['pending' => 'En attente', 'in_progress' => 'En cours', 'completed' => 'Terminé', 'cancelled' => 'Annulé'];
const LAB_STATUS_BADGE   = ['pending' => 'badge-pending', 'in_progress' => 'badge-inprogress', 'completed' => 'badge-completed', 'cancelled' => 'badge-cancelled'];
const LAB_PRIORITY_LABELS = ['normal' => 'Normal', 'urgent' => 'Urgent'];
const LAB_PRIORITY_BADGE  = ['normal' => 'badge-pending', 'urgent' => 'badge-urgent'];
const LAB_CATEGORIES = ['hématologie' => 'Hématologie', 'biochimie' => 'Biochimie', 'microbiologie' => 'Microbiologie', 'immunologie' => 'Immunologie', 'urine' => 'Analyse d\'urine', 'autres' => 'Autres'];

?>