<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// confirm-appointment.php — Passe le statut à 'confirmed' (médecin appelle le patient)
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$id   = (int)($_GET['id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

if ($id > 0) {
    $stmt = $database->prepare("UPDATE appointments SET status='confirmed' WHERE id=? AND status='pending'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}
header("Location: appointments.php?date=$date&msg=ok");
exit;
