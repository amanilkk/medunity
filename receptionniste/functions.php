<?php
// ================================================================
//  functions.php — Bibliothèque partagée réceptionniste
//  ✔ Auth, générateurs, patients, médecins, constantes
//  ✔ safeCount, logBedAction (utilisé par bed_functions.php)
// ================================================================
if (session_status() === PHP_SESSION_NONE) session_start();

/* ================= AUTH ================= */
function requireReceptionniste() {
    if (($_SESSION['usertype'] ?? null) !== 'receptionniste') {
        header('Location: ../login.php');
        exit;
    }
}

/* ================= GENERATORS ================= */
function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
}

/**
 * Génère le numéro de ticket du jour (ordre de passage)
 */
function generateQueueNumber($db) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS c FROM invoices WHERE DATE(created_at) = CURDATE()"
    );
    if (!$stmt) return 1;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0) + 1;
}

/**
 * Génère le numéro de ticket pour un médecin spécifique
 */
function getTicketNumberForDoctor($db, $doctor_id) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS c FROM appointments 
         WHERE doctor_id = ? 
         AND DATE(appointment_date) = CURDATE()
         AND status IN ('pending', 'confirmed', 'completed')"
    );
    if (!$stmt) return 1;
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0) + 1;
}

/* ================= SAFE COUNT ================= */
function safeCount($db, $sql, $types = '', $params = []) {
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

/* ================= PATIENT ================= */
function getPatientById($db, $id) {
    $stmt = $db->prepare("
        SELECT pt.*, u.full_name, u.phone, u.email, u.address
        FROM patients pt
        JOIN users u ON u.id = pt.user_id
        WHERE pt.id = ?
    ");
    if (!$stmt) return null;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function patientExists($db, $id) {
    $stmt = $db->prepare("SELECT id FROM patients WHERE id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/* ================= DOCTORS ================= */
function getDoctors($db) {
    $data = [];
    $sql = "
        SELECT d.id, d.consultation_fee, d.room_number, d.department,
               u.full_name AS name,
               sp.sname AS specialty
        FROM doctors d
        JOIN users u ON u.id = d.user_id
        LEFT JOIN specialties sp ON sp.id = d.specialty_id
        WHERE u.is_active = 1
        ORDER BY u.full_name ASC
    ";
    $res = $db->query($sql);
    if ($res) while ($row = $res->fetch_assoc()) $data[] = $row;
    return $data;
}

/* ================= LOG ACTION ================= */
function logBedAction($db, string $action, string $details): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $db->prepare(
        "INSERT INTO logs (user_id, action, details, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    if (!$stmt) return;
    $stmt->bind_param('iss', $user_id, $action, $details);
    $stmt->execute();
}

/* ================= GENERATE UNIQUE UHID ================= */
function generateUniqueUhid($db, $fullName, $dob) {
    // Prendre les 2 premières lettres du nom (si nom contient plusieurs mots, prendre le premier mot)
    $nameParts = explode(' ', trim($fullName));
    $firstName = $nameParts[0] ?? '';
    $lastName = isset($nameParts[1]) ? end($nameParts) : '';

    // Extraire 2 lettres du prénom
    $firstPart = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $firstName), 0, 2));
    $firstPart = str_pad($firstPart, 2, 'X', STR_PAD_RIGHT);

    // Extraire 2 lettres du nom
    $lastPart = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $lastName ?: $firstName), 0, 2));
    $lastPart = str_pad($lastPart, 2, 'X', STR_PAD_RIGHT);

    // Formater la date
    $datePart = '';
    if (!empty($dob) && $dob !== '0000-00-00') {
        try {
            $date = new DateTime($dob);
            $datePart = $date->format('dmY');
        } catch (Exception $e) {
            $datePart = date('dmY');
        }
    } else {
        $datePart = date('dmY');
    }

    $baseUhid = 'PT' . $lastPart . $firstPart . $datePart;

    // Gérer les doublons
    $suffix = 0;
    $finalUhid = $baseUhid;
    while (checkUhidExists($db, $finalUhid)) {
        $suffix++;
        $finalUhid = $baseUhid . '-' . $suffix;
    }

    return $finalUhid;
}

function checkUhidExists($db, $uhid) {
    $stmt = $db->prepare("SELECT id FROM patients WHERE uhid = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('s', $uhid);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function generateUniqueEmailForPatient($db, $baseEmail, $fullName) {
    if (empty($baseEmail)) {
        $cleanName = strtolower(preg_replace('/[^A-Za-z0-9]/', '', str_replace(' ', '.', $fullName)));
        $defaultEmail = $cleanName . '@clinic.local';

        $suffix = 0;
        $finalEmail = $defaultEmail;
        while (checkEmailExists($db, $finalEmail)) {
            $suffix++;
            $finalEmail = $cleanName . $suffix . '@clinic.local';
        }
        return $finalEmail;
    }

    if (checkEmailExists($db, $baseEmail)) {
        $parts = explode('@', $baseEmail);
        $username = $parts[0];
        $domain = $parts[1] ?? 'clinic.local';

        $suffix = 1;
        $finalEmail = $username . '@' . $domain;
        while (checkEmailExists($db, $finalEmail)) {
            $finalEmail = $username . $suffix . '@' . $domain;
            $suffix++;
        }
        return $finalEmail;
    }

    return $baseEmail;
}

function checkEmailExists($db, $email) {
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/* ================= CONSTANTS ================= */
const STATUS_LABELS = [
    'pending'   => 'En attente',
    'confirmed' => 'Confirmé',
    'completed' => 'Terminé',
    'cancelled' => 'Annulé',
    'urgent'    => 'Urgent',
    'no_show'   => 'Absent',
];

const STATUS_BADGE = [
    'pending'   => 'badge-pending',
    'confirmed' => 'badge-confirmed',
    'completed' => 'badge-completed',
    'cancelled' => 'badge-cancelled',
    'urgent'    => 'badge-urgent',
    'no_show'   => 'badge-noshow',
];

const TYPE_LABELS = [
    'consultation' => 'Consultation',
    'urgence'      => 'Urgence',
    'suivi'        => 'Suivi',
    'chirurgie'    => 'Chirurgie',
    'pediatrie'    => 'Pédiatrie',
];

const INV_STATUS_LABELS = [
    'pending' => 'En attente',
    'paid'    => 'Payée',
    'unpaid'  => 'Impayée',
    'draft'   => 'Brouillon',
];

const INV_STATUS_BADGE = [
    'pending' => 'badge-pending',
    'paid'    => 'badge-paid',
    'unpaid'  => 'badge-amber',
    'draft'   => 'badge-noshow',
];