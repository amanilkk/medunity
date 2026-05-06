<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  queue_info.php — Endpoint AJAX : infos file d'attente médecin
//  Retourne JSON : { doctor_id, count, next_number }
//
//  ✅ Accès : appointments uniquement
//  ❌ Aucun accès : invoices, payments
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

header('Content-Type: application/json; charset=utf-8');

$doctor_id = (int)($_GET['doctor_id'] ?? 0);

if ($doctor_id <= 0) {
    echo json_encode(['error' => 'doctor_id invalide']);
    exit;
}

// Compter les patients en attente ou en consultation pour ce médecin aujourd'hui
$stmt = $database->prepare(
    "SELECT COUNT(*) AS c
     FROM appointments
     WHERE doctor_id = ?
       AND DATE(created_at) = CURDATE()
       AND status IN ('pending', 'confirmed', 'completed')"
);

if (!$stmt) {
    echo json_encode(['error' => 'Erreur BD']);
    exit;
}

$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$row   = $stmt->get_result()->fetch_assoc();
$count = (int)($row['c'] ?? 0);

echo json_encode([
    'doctor_id'   => $doctor_id,
    'count'       => $count,
    'next_number' => $count + 1,
], JSON_UNESCAPED_UNICODE);