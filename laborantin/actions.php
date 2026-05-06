<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  actions.php — Toutes les actions POST pour le laborantin
// ================================================================

// Activer les rapports d'erreurs MySQL détaillés
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once 'functions.php';
requireLaborantin();
include '../connection.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ==================== FONCTIONS ====================

/**
 * Récupère l'ID du pharmacien
 */
function getPharmacistId($db) {
    $stmt = $db->prepare("
        SELECT u.id 
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.role_name = 'pharmacien' 
        AND u.is_active = 1 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        return (int)$result['id'];
    }

    return null;
}

/**
 * Récupère les consommables nécessaires pour les analyses
 */
function getRequiredItemsForTests($db, $tests) {
    $required_items = [];

    foreach ($tests as $test) {
        $test_name = $test['name'];

        $stmt = $db->prepare("
            SELECT r.item_id, r.quantity_required
            FROM lab_test_consumables_required r
            WHERE r.test_name = ? AND r.is_auto_deduct = 1
        ");

        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('s', $test_name);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($items as $item) {
            $item_id = $item['item_id'];
            if (!isset($required_items[$item_id])) {
                $name_stmt = $db->prepare("SELECT item_name, unit FROM lab_stock WHERE id = ?");
                $name_stmt->bind_param('i', $item_id);
                $name_stmt->execute();
                $stock = $name_stmt->get_result()->fetch_assoc();

                $required_items[$item_id] = [
                    'item_id' => $item_id,
                    'item_name' => $stock['item_name'] ?? 'Produit #' . $item_id,
                    'quantity_needed' => 0,
                    'unit' => $stock['unit'] ?? ''
                ];
            }
            $required_items[$item_id]['quantity_needed'] += (float)$item['quantity_required'];
        }
    }

    return array_values($required_items);
}

// ==================== ACTIONS POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------------------------------------------------------
    // 1. CRÉER UNE NOUVELLE ANALYSE
    // ---------------------------------------------------------
    if ($action === 'create_test') {
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $test_name = trim($_POST['test_name'] ?? '');
        $test_name_new = trim($_POST['test_name_new'] ?? '');
        $category = $_POST['category_select'] ?? $_POST['category'] ?? 'autres';
        $priority = $_POST['priority'] === 'urgent' ? 'urgent' : 'normal';
        $notes = trim($_POST['notes'] ?? '');
        $current_laborantin_id = getCurrentLaborantinId();

        $final_test_name = $test_name;
        if (empty($final_test_name) && !empty($test_name_new)) {
            $final_test_name = $test_name_new;
        }

        if ($patient_id && $doctor_id && $final_test_name) {
            $stmt = $database->prepare("
                INSERT INTO lab_tests (patient_id, doctor_id, test_name, test_category, priority, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->bind_param('iissss', $patient_id, $doctor_id, $final_test_name, $category, $priority, $notes);

            if ($stmt->execute()) {
                $details_message = 'Analyse créée: ' . $final_test_name . ' pour patient #' . $patient_id;
                secureLogInsert($database, $current_laborantin_id, 'create_test', $details_message);

                header('Location: index.php?page=tests&msg=created');
                exit;
            }
        }
        header('Location: index.php?page=create&error=missing');
        exit;
    }

    // ---------------------------------------------------------
    // 2. CRÉER PLUSIEURS ANALYSES (panier) ET ENVOYER AU PHARMACIEN
    // ---------------------------------------------------------
    if ($action === 'create_multiple_tests') {
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id  = (int)($_POST['doctor_id'] ?? 0);
        $notes      = trim($_POST['notes'] ?? '');
        $tests_raw  = $_POST['tests_json'] ?? '[]';
        $tests      = json_decode($tests_raw, true) ?: [];
        $current_laborantin_id = getCurrentLaborantinId();

        if ($patient_id && $doctor_id && !empty($tests)) {
            $created = 0;
            $stmt = $database->prepare("
                INSERT INTO lab_tests (patient_id, doctor_id, test_name, test_category, priority, status, notes, created_at)
                VALUES (?, ?, ?, ?, 'normal', 'pending', ?, NOW())
            ");

            foreach ($tests as $t) {
                $name     = trim($t['name'] ?? '');
                $category = trim($t['category'] ?? 'autres');
                if (!$name) continue;
                $stmt->bind_param('iisss', $patient_id, $doctor_id, $name, $category, $notes);
                if ($stmt->execute()) $created++;
            }

            // ========== ENVOI DE LA COMMANDE AU PHARMACIEN ==========
            if ($created > 0) {
                $pharmacist_id = getPharmacistId($database);

                if ($pharmacist_id) {
                    $required_items = getRequiredItemsForTests($database, $tests);

                    if (!empty($required_items)) {
                        foreach ($required_items as $item) {
                            // Vérifier que l'item existe dans lab_stock
                            $check_stmt = $database->prepare("SELECT id FROM lab_stock WHERE id = ?");
                            $check_stmt->bind_param('i', $item['item_id']);
                            $check_stmt->execute();
                            if ($check_stmt->get_result()->num_rows > 0) {
                                $order_stmt = $database->prepare("
                                    INSERT INTO orders (requester_id, pharmacist_id, item_type, item_id, quantity, status, created_at)
                                    VALUES (?, ?, 'stock', ?, ?, 'pending', NOW())
                                ");
                                $order_stmt->bind_param('iiii', $current_laborantin_id, $pharmacist_id, $item['item_id'], $item['quantity_needed']);
                                $order_stmt->execute();
                            }
                        }

                        // Journaliser l'envoi
                        $count_items = count($required_items);
                        $details_message = $count_items . ' commandes envoyées au pharmacien (ID: ' . $pharmacist_id . ')';
                        secureLogInsert($database, $current_laborantin_id, 'send_orders', $details_message);
                    }
                } else {
                    // Log si aucun pharmacien trouvé
                    secureLogInsert($database, $current_laborantin_id, 'send_orders_failed', 'Aucun pharmacien trouvé dans la base');
                }
            }
            // ========================================================

            if ($created > 0) {
                header('Location: index.php?page=tests&msg=created&count=' . $created);
            } else {
                header('Location: index.php?page=create&error=db');
            }
            exit;
        }
        header('Location: index.php?page=create&error=missing');
        exit;
    }

    // ---------------------------------------------------------
    // 3. METTRE À JOUR LE STATUT D'UNE ANALYSE
    // ---------------------------------------------------------
    if ($action === 'update_status') {
        $test_id = (int)($_POST['test_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $current_laborantin_id = getCurrentLaborantinId();

        $valid_status = ['pending', 'in_progress', 'completed', 'cancelled'];
        if ($test_id && in_array($status, $valid_status)) {
            $stmt = $database->prepare("UPDATE lab_tests SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $test_id);

            if ($stmt->execute()) {
                $details_message = 'Analyse #' . $test_id . ' → statut: ' . $status;
                secureLogInsert($database, $current_laborantin_id, 'update_status', $details_message);

                header('Location: index.php?page=view&id=' . $test_id . '&msg=status');
                exit;
            }
        }
        header('Location: index.php?page=view&id=' . $test_id . '&error=status');
        exit;
    }

    // ---------------------------------------------------------
    // 4. AJOUTER UN RÉSULTAT D'ANALYSE
    // ---------------------------------------------------------
    if ($action === 'add_result') {
        $test_id = (int)($_POST['test_id'] ?? 0);
        $result = trim($_POST['result'] ?? '');
        $is_critical = isset($_POST['is_critical']) ? 1 : 0;
        $unit_measure = trim($_POST['unit_measure'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $send_email = isset($_POST['send_email']) ? 1 : 0;
        $result_date = date('Y-m-d H:i:s');
        $performed_by = getCurrentLaborantinId();

        $file_url = null;

        if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/lab_results/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $ext = strtolower(pathinfo($_FILES['result_file']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            if (in_array($ext, $allowed_ext)) {
                $filename = 'result_' . $test_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['result_file']['tmp_name'], $upload_dir . $filename)) {
                    $file_url = 'uploads/lab_results/' . $filename;
                }
            }
        }

        if ($test_id && $result) {
            $stmt = $database->prepare("
                UPDATE lab_tests 
                SET result = ?, is_critical = ?, unit_measure = ?, notes = ?, 
                    result_file_url = ?, result_date = ?, status = 'completed', performed_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param('sissssii', $result, $is_critical, $unit_measure, $notes, $file_url, $result_date, $performed_by, $test_id);

            if ($stmt->execute()) {
                $critical_text = $is_critical ? ' ⚠️ CRITIQUE' : '';
                $details_message = 'Résultat ajouté pour analyse #' . $test_id . $critical_text;
                secureLogInsert($database, $performed_by, 'add_result', $details_message);

                if ($send_email) {
                    sendResultEmailLog($database, $test_id);
                }

                header('Location: index.php?page=view&id=' . $test_id . '&msg=result');
                exit;
            }
        }
        header('Location: index.php?page=view&id=' . $test_id . '&error=result');
        exit;
    }

    // ---------------------------------------------------------
    // 5. DEMANDER UN RÉAPPROVISIONNEMENT (STOCK) - VERS PHARMACIEN
    // ---------------------------------------------------------
    if ($action === 'request_stock') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $current_laborantin_id = getCurrentLaborantinId();

        if ($item_id) {
            $stmt = $database->prepare("SELECT item_name, quantity, threshold_alert FROM lab_stock WHERE id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                $pharmacist_id = getPharmacistId($database);

                if ($pharmacist_id) {
                    // Créer une commande dans la table orders
                    $order_stmt = $database->prepare("
                        INSERT INTO orders (requester_id, pharmacist_id, item_type, item_id, quantity, status, created_at)
                        VALUES (?, ?, 'stock', ?, ?, 'pending', NOW())
                    ");
                    $default_qty = 100;
                    $order_stmt->bind_param('iiii', $current_laborantin_id, $pharmacist_id, $item_id, $default_qty);
                    $order_stmt->execute();

                    $details_message = 'Demande réappro pour: ' . $item['item_name'];
                    secureLogInsert($database, $current_laborantin_id, 'request_stock', $details_message);

                    header('Location: stock.php?msg=requested');
                    exit;
                }
            }
        }
        header('Location: stock.php?error=request');
        exit;
    }

    // ---------------------------------------------------------
    // 6. METTRE À JOUR LE STOCK D'UN CONSOMMABLE
    // ---------------------------------------------------------
    if ($action === 'update_stock') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $new_quantity = (int)($_POST['quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Ajustement manuel');
        $performed_by = getCurrentLaborantinId();

        if ($item_id) {
            $stmt = $database->prepare("SELECT quantity, item_name, threshold_alert FROM lab_stock WHERE id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                $old_quantity = $item['quantity'];
                $diff = $new_quantity - $old_quantity;
                $operation = $diff > 0 ? 'in' : ($diff < 0 ? 'out' : 'none');
                $abs_diff = abs($diff);

                if ($operation !== 'none') {
                    $update = $database->prepare("UPDATE lab_stock SET quantity = ? WHERE id = ?");
                    $update->bind_param('ii', $new_quantity, $item_id);
                    $update->execute();

                    $movement = $database->prepare("
                        INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $movement->bind_param('isisi', $item_id, $operation, $abs_diff, $reason, $performed_by);
                    $movement->execute();

                    if ($new_quantity <= $item['threshold_alert']) {
                        checkLowStockAlert($database, $item_id);
                    }

                    $details_message = 'Stock mis à jour: ' . $item['item_name'] . ' (' . $old_quantity . ' → ' . $new_quantity . ')';
                    secureLogInsert($database, $performed_by, 'update_stock', $details_message);
                }

                header('Location: stock.php?msg=updated');
                exit;
            }
        }
        header('Location: stock.php?error=stock');
        exit;
    }

    // ---------------------------------------------------------
    // 7. AJOUTER UN NOUVEAU CONSOMMABLE
    // ---------------------------------------------------------
    if ($action === 'add_stock_item') {
        $item_name = trim($_POST['item_name'] ?? '');
        $category = $_POST['category'] ?? 'autres';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'unité');
        $threshold_alert = (int)($_POST['threshold'] ?? 10);
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $performed_by = getCurrentLaborantinId();

        if ($item_name) {
            $stmt = $database->prepare("
                INSERT INTO lab_stock (item_name, category, description, quantity, unit, threshold_alert, location, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('sssisss', $item_name, $category, $description, $quantity, $unit, $threshold_alert, $location);

            if ($stmt->execute()) {
                $item_id = $database->insert_id;

                if ($quantity > 0) {
                    $movement = $database->prepare("
                        INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
                        VALUES (?, 'in', ?, 'Création initiale', ?, NOW())
                    ");
                    $movement->bind_param('iii', $item_id, $quantity, $performed_by);
                    $movement->execute();
                }

                $details_message = 'Nouveau consommable: ' . $item_name;
                secureLogInsert($database, $performed_by, 'add_stock_item', $details_message);

                header('Location: stock.php?msg=added');
                exit;
            }
        }
        header('Location: stock.php?error=add');
        exit;
    }

    // ---------------------------------------------------------
    // 8. AUTRES ACTIONS
    // ---------------------------------------------------------
    if ($action === 'consume_stock') {
        $test_id = (int)($_POST['test_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);

        if ($test_id && !empty($items)) {
            $errors = consumeStock($database, $test_id, $items);

            if (empty($errors)) {
                header('Location: index.php?page=view&id=' . $test_id . '&msg=stock_consumed');
                exit;
            } else {
                $error_msg = implode(', ', $errors);
                header('Location: index.php?page=view&id=' . $test_id . '&error=stock&detail=' . urlencode($error_msg));
                exit;
            }
        }
        header('Location: index.php?page=view&id=' . $test_id . '&error=stock');
        exit;
    }

    if ($action === 'cancel_test') {
        $test_id = (int)($_POST['test_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Annulé par laborantin');
        $current_laborantin_id = getCurrentLaborantinId();

        if ($test_id) {
            $stmt = $database->prepare("UPDATE lab_tests SET status = 'cancelled', notes = CONCAT(notes, ' [ANNULÉ: ', ?, ']') WHERE id = ?");
            $stmt->bind_param('si', $reason, $test_id);

            if ($stmt->execute()) {
                $details_message = 'Analyse #' . $test_id . ' annulée: ' . $reason;
                secureLogInsert($database, $current_laborantin_id, 'cancel_test', $details_message);

                header('Location: index.php?page=tests&msg=cancelled');
                exit;
            }
        }
        header('Location: index.php?page=view&id=' . $test_id . '&error=cancel');
        exit;
    }

    if ($action === 'reactivate_test') {
        $test_id = (int)($_POST['test_id'] ?? 0);

        if ($test_id) {
            $stmt = $database->prepare("UPDATE lab_tests SET status = 'pending' WHERE id = ? AND status = 'cancelled'");
            $stmt->bind_param('i', $test_id);

            if ($stmt->execute()) {
                header('Location: index.php?page=view&id=' . $test_id . '&msg=reactivated');
                exit;
            }
        }
        header('Location: index.php?page=view&id=' . $test_id . '&error=reactivate');
        exit;
    }

    if ($action === 'send_result_email') {
        $test_id = (int)($_POST['test_id'] ?? 0);

        if ($test_id) {
            if (sendResultEmailLog($database, $test_id)) {
                header('Location: index.php?page=view&id=' . $test_id . '&msg=email_sent');
                exit;
            }
        }
        header('Location: index.php?page=view&id=' . $test_id . '&error=email');
        exit;
    }
}

// ==================== ACTIONS GET ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if ($action === 'download_result') {
        $test_id = (int)($_GET['test_id'] ?? 0);

        if ($test_id) {
            $stmt = $database->prepare("SELECT result_file_url FROM lab_tests WHERE id = ?");
            $stmt->bind_param('i', $test_id);
            $stmt->execute();
            $test = $stmt->get_result()->fetch_assoc();

            if ($test && $test['result_file_url'] && file_exists('../' . $test['result_file_url'])) {
                $file = '../' . $test['result_file_url'];
                $filename = basename($file);

                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
            }
        }
        header('Location: index.php?page=view&id=' . $test_id . '&error=file');
        exit;
    }

    if ($action === 'export_csv') {
        $status = $_GET['status'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';

        $sql = "
            SELECT lt.id, lt.test_name, lt.test_category, lt.priority, lt.status, 
                   lt.result, lt.result_date, lt.created_at,
                   u.full_name as patient_name, u.phone as patient_phone,
                   u2.full_name as doctor_name
            FROM lab_tests lt
            JOIN patients p ON p.id = lt.patient_id
            JOIN users u ON u.id = p.user_id
            LEFT JOIN doctors d ON d.id = lt.doctor_id
            LEFT JOIN users u2 ON u2.id = d.user_id
            WHERE 1=1
        ";
        $params = [];
        $types = '';

        if ($status) {
            $sql .= " AND lt.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        if ($date_from) {
            $sql .= " AND DATE(lt.created_at) >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        if ($date_to) {
            $sql .= " AND DATE(lt.created_at) <= ?";
            $params[] = $date_to;
            $types .= 's';
        }

        $sql .= " ORDER BY lt.created_at DESC";

        $stmt = $database->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $results = $stmt->get_result();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analyses_lab_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Patient', 'Téléphone', 'Médecin', 'Analyse', 'Catégorie', 'Priorité', 'Statut', 'Résultat', 'Date résultat', 'Date création']);

        while ($row = $results->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['patient_name'],
                $row['patient_phone'],
                $row['doctor_name'] ?? '',
                $row['test_name'],
                $row['test_category'],
                $row['priority'],
                $row['status'],
                $row['result'],
                $row['result_date'],
                $row['created_at']
            ]);
        }

        fclose($output);
        exit;
    }

    if ($action === 'export_stock') {
        $stmt = $database->query("
            SELECT item_name, category, quantity, unit, threshold_alert, location, created_at
            FROM lab_stock
            ORDER BY item_name ASC
        ");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stock_lab_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Produit', 'Catégorie', 'Quantité', 'Unité', 'Seuil alerte', 'Emplacement', 'Date création']);

        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                fputcsv($output, [
                    $row['item_name'],
                    $row['category'],
                    $row['quantity'],
                    $row['unit'],
                    $row['threshold_alert'],
                    $row['location'],
                    $row['created_at']
                ]);
            }
        }

        fclose($output);
        exit;
    }
}

header('Location: index.php');
exit;