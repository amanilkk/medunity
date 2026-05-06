<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  room_capacity_ajax.php — Retourne JSON de capacité d'une salle
//  Utilisé par beds.php pour l'affichage temps réel dans le modal
// ================================================================
require_once 'functions.php';
require_once 'bed_functions.php';
requireReceptionniste();
include '../connection.php';

header('Content-Type: application/json; charset=utf-8');

$room_number = trim($_GET['room'] ?? '');
if ($room_number === '') {
    echo json_encode(['error' => 'Paramètre room requis.']);
    exit;
}

echo json_encode(getRoomSummaryJson($database, $room_number), JSON_UNESCAPED_UNICODE);