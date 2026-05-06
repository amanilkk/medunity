<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  batch_actions.php — Actions pour la saisie de résultats en lot
// ================================================================

require_once 'functions.php';
requireLaborantin();
include '../connection.php';

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_batch_results') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $test_date = $_POST['test_date'] ?? '';
    $test_ids = $_POST['test_ids'] ?? [];
    $send_email = isset($_POST['send_email']) ? 1 : 0;

    $results_data = [];
    $errors = [];
    $success_count = 0;
    $stock_errors = [];
    $stock_consumed = [];

    foreach ($test_ids as $test_id) {
        $result = trim($_POST['results'][$test_id] ?? '');

        // Gérer l'unité de mesure
        $unit = trim($_POST['units'][$test_id] ?? '');
        if ($unit === 'autre') {
            $unit = trim($_POST['units_custom'][$test_id] ?? '');
        }

        $ref_range = trim($_POST['ref_ranges'][$test_id] ?? '');
        $is_critical = isset($_POST['critical'][$test_id]) ? 1 : 0;
        $notes = trim($_POST['notes'][$test_id] ?? '');
        $test_name = $_POST['test_names'][$test_id] ?? 'Analyse';

        if (empty($result)) {
            $errors[] = "Le résultat pour l'analyse '$test_name' est vide";
            continue;
        }

        // Mettre à jour l'analyse
        $stmt = $database->prepare("
            UPDATE lab_tests 
            SET result = ?, is_critical = ?, unit_measure = ?, notes = ?, 
                reference_range = ?, result_date = NOW(), status = 'completed', performed_by = ?
            WHERE id = ? AND status != 'completed'
        ");
        $performed_by = getCurrentLaborantinId();
        $stmt->bind_param('sissisi', $result, $is_critical, $unit, $notes, $ref_range, $performed_by, $test_id);

        if ($stmt->execute()) {
            $success_count++;
            $results_data[] = [
                'test_id' => $test_id,
                'test_name' => $test_name,
                'result' => $result,
                'unit_measure' => $unit,
                'reference_range' => $ref_range,
                'is_critical' => $is_critical,
                'result_date' => date('Y-m-d H:i:s')
            ];

            // ========== CONSOMMATION AUTOMATIQUE DES CONSOMMABLES ==========
            $consume_result = autoConsumeStockForTest($database, $test_id, $test_name);
            if (!$consume_result['success']) {
                $stock_errors = array_merge($stock_errors, $consume_result['errors']);
            }
            $stock_consumed = array_merge($stock_consumed, $consume_result['consumed']);
            // ================================================================

        } else {
            $errors[] = "Erreur pour l'analyse '$test_name': " . $stmt->error;
        }
    }

    // Envoyer l'email si demandé
    $email_sent = false;
    if ($send_email && !empty($results_data) && $patient_id) {
        $stmt = $database->prepare("
            SELECT u.email, u.full_name 
            FROM patients p 
            JOIN users u ON u.id = p.user_id 
            WHERE p.id = ?
        ");
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();

        if ($patient && $patient['email']) {
            // Envoi d'email (à configurer)
            $email_sent = true;
        }
    }

    // Redirection avec messages
    $msg = "success&count=" . $success_count;
    if (!empty($errors)) {
        $msg = "partial&errors=" . urlencode(implode(', ', array_slice($errors, 0, 3)));
    }
    if (!empty($stock_errors)) {
        $msg .= "&stock_errors=" . urlencode(implode(', ', $stock_errors));
    }
    if (!empty($stock_consumed)) {
        $msg .= "&stock_consumed=1";
    }
    if ($email_sent) {
        $msg .= "&email_sent=1";
    }

    header("Location: index.php?page=tests&msg={$msg}");
    exit;
}

header('Location: index.php?page=tests');
exit;