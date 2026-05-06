<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// doctor/doctor_functions.php
if (session_status() === PHP_SESSION_NONE) session_start();

function requireDoctor() {
    if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'd') {
        header("Location: ../login.php");
        exit();
    }
}

function getCurrentDoctor($db) {
    $email = $_SESSION['user'];
    $stmt = $db->prepare("
        SELECT u.id AS user_id, u.full_name, u.email, u.phone,
               d.id AS doctor_id, d.specialty_id, d.room_number, d.department,
               d.consultation_fee, d.availability_status, d.experience_years
        FROM users u
        JOIN doctors d ON d.user_id = u.id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getDoctorDashboardStats($db, $doctor_id) {
    $stats = [];
    $today    = date('Y-m-d');
    $nextweek = date('Y-m-d', strtotime('+7 days'));

    $r = $db->query("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id = $doctor_id");
    $stats['total_patients'] = $r->fetch_assoc()['c'] ?? 0;

    $r = $db->query("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = $doctor_id AND appointment_date = '$today'");
    $stats['today_appointments'] = $r->fetch_assoc()['c'] ?? 0;

    $r = $db->query("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = $doctor_id AND appointment_date BETWEEN '$today' AND '$nextweek'");
    $stats['upcoming_appointments'] = $r->fetch_assoc()['c'] ?? 0;

    $r = $db->query("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = $doctor_id AND status = 'pending'");
    $stats['pending_appointments'] = $r->fetch_assoc()['c'] ?? 0;

    return $stats;
}

function getUpcomingAppointments($db, $doctor_id, $limit = 10) {
    $today = date('Y-m-d');
    $stmt  = $db->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason,
               u.full_name AS patient_name, p.uhid, p.id AS patient_id
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN users u ON u.id = p.user_id
        WHERE a.doctor_id = ? AND a.appointment_date >= ?
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT ?
    ");
    $stmt->bind_param("isi", $doctor_id, $today, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Récupère tous les patients (accessibles au médecin)
function getDoctorPatients($db, $doctor_id, $search = '') {
    if ($search) {
        $s    = "%$search%";
        $stmt = $db->prepare("
            SELECT p.id, p.uhid, p.blood_type, p.dob, p.gender, p.allergies,
                   u.full_name, u.phone, u.address
            FROM patients p
            JOIN users u ON u.id = p.user_id
            WHERE (u.full_name LIKE ? OR p.uhid LIKE ? OR u.phone LIKE ?)
            ORDER BY u.full_name
        ");
        $stmt->bind_param("sss", $s, $s, $s);
    } else {
        $stmt = $db->prepare("
            SELECT p.id, p.uhid, p.blood_type, p.dob, p.gender, p.allergies,
                   u.full_name, u.phone, u.address
            FROM patients p
            JOIN users u ON u.id = p.user_id
            ORDER BY u.full_name
        ");
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Alias global — retourne TOUS les patients (sans filtre par médecin)
// Utilisé par lab-requests, medical-notes, medical-history
function getAllPatients($db) {
    $stmt = $db->prepare("
        SELECT p.id, p.uhid, p.blood_type, p.dob, p.gender, p.allergies,
               u.full_name, u.phone, u.address
        FROM patients p
        JOIN users u ON u.id = p.user_id
        ORDER BY u.full_name
    ");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}