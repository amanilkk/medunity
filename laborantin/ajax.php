
<?php
require_once 'functions.php';
requireLaborantin();
include '../connection.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create_test':
            $patient_id = (int)$_POST['patient_id'];
            $doctor_id  = (int)$_POST['doctor_id'];
            $test_name  = trim($_POST['test_name']);
            $category   = $_POST['category'];
            $priority   = $_POST['priority'];
            $notes      = trim($_POST['notes'] ?? '');

            if ($patient_id && $doctor_id && $test_name) {
                $stmt = $database->prepare("
                    INSERT INTO lab_tests (patient_id, doctor_id, test_name, test_category, priority, status, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                $stmt->bind_param('iissss', $patient_id, $doctor_id, $test_name, $category, $priority, $notes);
                if ($stmt->execute()) {
                    header('Location: index.php?page=tests&msg=created');
                    exit;
                }
            }
            header('Location: index.php?page=create&error=1');
            break;

        case 'update_status':
            $test_id = (int)$_POST['test_id'];
            $status  = $_POST['status'];
            if (updateTestStatus($database, $test_id, $status)) {
                header('Location: index.php?page=view&id=' . $test_id . '&msg=status');
            } else {
                header('Location: index.php?page=view&id=' . $test_id . '&error=1');
            }
            break;

        case 'add_result':
            $test_id     = (int)$_POST['test_id'];
            $result      = trim($_POST['result']);
            $is_critical = isset($_POST['is_critical']) ? 1 : 0;
            $unit        = trim($_POST['unit_measure']);
            $notes       = trim($_POST['notes']);
            $file_url    = null;

            if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] === 0) {
                $upload_dir = '../uploads/lab_results/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext      = pathinfo($_FILES['result_file']['name'], PATHINFO_EXTENSION);
                $filename = 'result_' . $test_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['result_file']['tmp_name'], $upload_dir . $filename)) {
                    $file_url = 'uploads/lab_results/' . $filename;
                }
            }

            if (addResult($database, $test_id, $result, $is_critical, $unit, $notes, $file_url)) {
                if (isset($_POST['send_email'])) sendResultEmailLog($database, $test_id);
                header('Location: index.php?page=view&id=' . $test_id . '&msg=result');
            } else {
                header('Location: index.php?page=view&id=' . $test_id . '&error=1');
            }
            break;

        case 'request_stock':
            $item_id = (int)$_POST['item_id'];
            if (requestReapprovisionnement($database, $item_id)) {
                header('Location: stock.php?msg=requested');
            } else {
                header('Location: stock.php?error=1');
            }
            break;

        case 'update_stock':
            $item_id = (int)$_POST['item_id'];
            $new_qty = (int)$_POST['quantity'];
            $reason  = trim($_POST['reason']);

            $stmt = $database->prepare("SELECT quantity, item_name FROM lab_stock WHERE id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                $diff         = $new_qty - $item['quantity'];
                $operation    = $diff >= 0 ? 'in' : 'out';
                $abs_diff     = abs($diff);
                $performed_by = getCurrentLaborantinId();

                $database->prepare("UPDATE lab_stock SET quantity = ? WHERE id = ?")
                    ->bind_param('ii', $new_qty, $item_id)->execute();
                $database->prepare("INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by) VALUES (?, ?, ?, ?, ?)")
                    ->bind_param('isiss', $item_id, $operation, $abs_diff, $reason, $performed_by)->execute();
                checkLowStockAlert($database, $item_id);
            }
            header('Location: stock.php?msg=updated');
            break;

        case 'add_stock_item':
            $item_name = trim($_POST['item_name']);
            $category  = $_POST['category'];
            $quantity  = (int)$_POST['quantity'];
            $unit      = trim($_POST['unit']);
            $threshold = (int)$_POST['threshold'];
            $location  = trim($_POST['location']);

            if ($item_name) {
                $stmt = $database->prepare("INSERT INTO lab_stock (item_name, category, quantity, unit, threshold_alert, location, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param('ssisss', $item_name, $category, $quantity, $unit, $threshold, $location);
                $stmt->execute();
            }
            header('Location: stock.php?msg=added');
            break;
    }
    exit;
}

// GET actions
if ($action === 'search_patient') {
    $q = trim($_GET['q'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    echo json_encode(getPatientsAjax($database, $q));
    exit;
}

if ($action === 'download') {
    $file = $_GET['file'] ?? '';
    if ($file && file_exists('../' . $file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile('../' . $file);
    }
    exit;
}