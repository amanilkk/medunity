<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  cancel-appointment.php — Annule un rendez-vous (pending/confirmed)
//  ✅ Accès : appointments uniquement
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$id   = (int)($_GET['id']   ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

if ($id > 0) {
    $stmt = $database->prepare(
        "UPDATE appointments SET status='cancelled'
         WHERE id=? AND status IN ('pending','confirmed')"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
}
header("Location: appointments.php?date=$date&msg=cancelled");
exit;
