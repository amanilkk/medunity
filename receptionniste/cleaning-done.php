<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  cleaning-done.php — Marque un lit comme nettoyé → available
// ================================================================
require_once 'functions.php';
require_once 'bed_functions.php';
requireReceptionniste();
include '../connection.php';

$bed_id = (int)($_GET['id'] ?? 0);

if ($bed_id <= 0) {
    header('Location: beds.php?msg=err_val&detail=' . urlencode('Identifiant de lit invalide.'));
    exit;
}

$err_code = '';
$err_msg  = '';
$ok = markCleaningDone($database, $bed_id, $err_code, $err_msg);

if ($ok) {
    header('Location: beds.php?msg=cleaned');
} else {
    header('Location: beds.php?msg=' . urlencode($err_code) . '&detail=' . urlencode($err_msg));
}
exit;