<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// done-appointment.php — Marque consultation comme terminée
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$id   = (int)($_GET['id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

if ($id > 0) {
    $stmt = $database->prepare("UPDATE appointments SET status='completed' WHERE id=? AND status='confirmed'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}
header("Location: appointments.php?date=$date&msg=done");
exit;
