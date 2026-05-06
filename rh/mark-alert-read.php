<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Marquer une alerte spécifique (via GET)
if (isset($_GET['alert_id'])) {
    $id = intval($_GET['alert_id']);
    $database->query("UPDATE rh_alerts SET is_read = 1 WHERE id = $id");
}

// Marquer toutes les alertes comme lues (via GET)
if (isset($_GET['mark_all'])) {
    $database->query("UPDATE rh_alerts SET is_read = 1 WHERE is_read = 0");
}

// Redirection vers la page précédente
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
?>