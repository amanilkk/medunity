<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  assign-bed.php — Assigne un lit à un patient (POST handler)
//  ✔ Validation complète capacité + règles type de chambre
// ================================================================
require_once 'functions.php';
require_once 'bed_functions.php';
requireReceptionniste();
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: beds.php');
    exit;
}

$bed_id = (int)($_POST['bed_id']     ?? 0);
$pid    = (int)($_POST['patient_id'] ?? 0);

if ($bed_id <= 0 || $pid <= 0) {
    header('Location: beds.php?msg=err_val&detail=' . urlencode('Données manquantes.'));
    exit;
}

$err_code = '';
$err_msg  = '';
$ok = assignBed($database, $bed_id, $pid, $err_code, $err_msg);

if ($ok) {
    header('Location: beds.php?msg=assigned');
} else {
    header('Location: beds.php?msg=' . urlencode($err_code) . '&detail=' . urlencode($err_msg));
}
exit;