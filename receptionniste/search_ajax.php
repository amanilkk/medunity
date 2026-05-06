<?php
// ================================================================
//  search_ajax.php  — Endpoint AJAX recherche live patients
//  Retourne JSON : [{id, uhid, full_name, phone, dob, age,
//                    blood_type, allergies,
//                    emergency_contact_name, emergency_contact_phone}]
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

header('Content-Type: application/json; charset=utf-8');

$q   = trim($_GET['q'] ?? '');
$out = [];

if (mb_strlen($q) < 2) {
    echo json_encode($out);
    exit;
}

// ── Helper âge ─────────────────────────────────────────────────
function calcAge(?string $dob): ?int {
    if (!$dob || $dob === '0000-00-00') return null;
    try {
        return (int)(new DateTime())->diff(new DateTime($dob))->y;
    }
    catch (Exception $e) {
        return null;
    }
}

// ── Détecter année dans la query (ex: "benali 2000") ───────────
$year_match = null;
$name_part  = $q;
if (preg_match('/\b(19[0-9]{2}|20[0-2][0-9])\b/', $q, $m)) {
    $year_match = $m[1];
    $name_part  = trim(str_replace($m[0], '', $q));
}

// ── Requête principale ─────────────────────────────────────────
$kw     = '%' . $q . '%';
$kw_nm  = $name_part !== '' ? '%' . $name_part . '%' : $kw;

$base_sql = "
    SELECT pt.id, pt.uhid, pt.blood_type, pt.allergies,
           pt.dob,
           pt.emergency_contact_name,
           pt.emergency_contact_phone,
           u.full_name, u.phone
    FROM patients pt
    INNER JOIN users u ON u.id = pt.user_id
    WHERE (
        u.full_name LIKE ?
        OR u.phone     LIKE ?
        OR pt.uhid     LIKE ?
        OR pt.nic      LIKE ?
        OR pt.emergency_contact_name  LIKE ?
        OR pt.emergency_contact_phone LIKE ?
    )
";

if ($year_match) {
    $base_sql .= " AND YEAR(pt.dob) = ?";
}

$base_sql .= " ORDER BY u.full_name ASC LIMIT 15";

$st = $database->prepare($base_sql);
if ($st) {
    if ($year_match) {
        $st->bind_param('ssssssi',
                $kw_nm, $kw, $kw, $kw, $kw, $kw,
                (int)$year_match
        );
    } else {
        $st->bind_param('ssssss',
                $kw, $kw, $kw, $kw, $kw, $kw
        );
    }
    $st->execute();
    $r = $st->get_result();
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $out[] = [
                    'id'                      => (int)$row['id'],
                    'uhid'                    => $row['uhid'],
                    'full_name'               => $row['full_name'],
                    'phone'                   => $row['phone'],
                    'dob'                     => $row['dob'] ?? '',
                    'age'                     => calcAge($row['dob'] ?? null),
                    'blood_type'              => $row['blood_type'] ?? '',
                    'allergies'               => $row['allergies']  ?? '',
                    'emergency_contact_name'  => $row['emergency_contact_name']  ?? '',
                    'emergency_contact_phone' => $row['emergency_contact_phone'] ?? '',
            ];
        }
    }
}

// ── Complément fuzzy par tokens si < 5 résultats ───────────────
if (count($out) < 5 && preg_match('/^[a-zA-ZÀ-ÿ\s]+$/', $name_part ?: $q)) {
    $existing_ids = array_column($out, 'id');
    $tokens       = preg_split('/\s+/', trim($name_part ?: $q));
    $conditions   = [];
    $params       = '';
    $vals         = [];

    foreach ($tokens as $tok) {
        if (mb_strlen($tok) >= 2) {
            $conditions[] = 'u.full_name LIKE ?';
            $params      .= 's';
            $vals[]       = '%' . $tok . '%';
        }
    }

    if ($conditions) {
        $where = implode(' AND ', $conditions);
        $excl  = $existing_ids
                ? 'AND pt.id NOT IN (' . implode(',', array_fill(0, count($existing_ids), '?')) . ')'
                : '';
        foreach ($existing_ids as $eid) {
            $params .= 'i';
            $vals[] = $eid;
        }

        $fuzzy_sql = "
            SELECT pt.id, pt.uhid, pt.blood_type, pt.allergies, pt.dob,
                   pt.emergency_contact_name, pt.emergency_contact_phone,
                   u.full_name, u.phone
            FROM patients pt
            INNER JOIN users u ON u.id = pt.user_id
            WHERE ($where) $excl
            ORDER BY u.full_name ASC LIMIT 5
        ";

        $st2 = $database->prepare($fuzzy_sql);
        if ($st2) {
            $st2->bind_param($params, ...$vals);
            $st2->execute();
            $r2 = $st2->get_result();
            if ($r2) {
                while ($row = $r2->fetch_assoc()) {
                    $out[] = [
                            'id'                      => (int)$row['id'],
                            'uhid'                    => $row['uhid'],
                            'full_name'               => $row['full_name'],
                            'phone'                   => $row['phone'],
                            'dob'                     => $row['dob'] ?? '',
                            'age'                     => calcAge($row['dob'] ?? null),
                            'blood_type'              => $row['blood_type'] ?? '',
                            'allergies'               => $row['allergies']  ?? '',
                            'emergency_contact_name'  => $row['emergency_contact_name']  ?? '',
                            'emergency_contact_phone' => $row['emergency_contact_phone'] ?? '',
                    ];
                }
            }
        }
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>