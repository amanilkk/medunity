<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  bed_functions.php — Fonctions utilitaires pour la gestion des lits
//  ✔ Adapted for bed_management table only (no rooms/room_types tables)
//  ✔ Compatible avec le module Gestionnaire des Moyens Généraux (GMG)
//  ✔ Ne modifie aucun module existant
//  ✔ logBedAction() déclaré ici si functions.php non chargé (fallback)
// ================================================================
if (session_status() === PHP_SESSION_NONE) session_start();

// Désactive le mode exception mysqli pour éviter les pages blanches
// causées par des erreurs SQL transformées en exceptions fatales non catchées
mysqli_report(MYSQLI_REPORT_OFF);

/* ================= CONSTANTS ================= */

const BED_STATUS_LABELS = [
    'available'   => 'Disponible',
    'occupied'    => 'Occupé',
    'cleaning'    => 'Nettoyage',
    'reserved'    => 'Réservé',
    'maintenance' => 'Maintenance',
];

const BED_TYPE_LABELS = [
    'standard'  => 'Standard',
    'icu'       => 'Soins intensifs',
    'pediatric' => 'Pédiatrique',
    'isolation' => 'Isolement',
];

// Règles par type de lit (basées sur bed_type de bed_management)
const BED_TYPE_MAX_PATIENTS = [
    'standard'  => null,  // Suit le nombre de lits dans la chambre
    'icu'       => 1,     // Soins intensifs: 1 patient strict
    'pediatric' => null,  // Suit le nombre de lits
    'isolation' => 1,     // Isolement: 1 patient
];

/* ================= LOG ACTION (fallback si functions.php absent) ================= */
if (!function_exists('logBedAction')) {
    function logBedAction($db, string $action, string $details): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id     = $_SESSION['user_id'] ?? null;
        $json_details = json_encode(['message' => $details], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare(
            "INSERT INTO logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())"
        );
        if (!$stmt) return;
        $stmt->bind_param('iss', $user_id, $action, $json_details);
        $stmt->execute();
    }
}

/* ================= BED STATS ================= */

/**
 * Retourne les statistiques globales des lits.
 */
function getBedStats($db): array {
    $default = ['total'=>0,'available'=>0,'occupied'=>0,'cleaning'=>0,'reserved'=>0,'maintenance'=>0];
    $sql = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='available'   THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN status='occupied'    THEN 1 ELSE 0 END) AS occupied,
        SUM(CASE WHEN status='cleaning'    THEN 1 ELSE 0 END) AS cleaning,
        SUM(CASE WHEN status='reserved'    THEN 1 ELSE 0 END) AS reserved,
        SUM(CASE WHEN status='maintenance' THEN 1 ELSE 0 END) AS maintenance
    FROM bed_management";
    $res = $db->query($sql);
    if (!$res) return $default;
    return $res->fetch_assoc() ?? $default;
}

/* ================= GET BED BY ID ================= */

/**
 * Retourne les données d'un lit par son id.
 */
function getBedById($db, int $id): ?array {
    $stmt = $db->prepare("SELECT * FROM bed_management WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/* ================= ROOM CAPACITY INFO ================= */

/**
 * Retourne les informations de capacité d'une salle.
 * Basé uniquement sur bed_management (pas de tables rooms/room_types).
 */
function getRoomCapacityInfo($db, string $room_number): array {
    // Compter les lits dans bed_management pour cette salle
    $bed_count_sql = "
        SELECT
            COUNT(*) AS total_beds,
            SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) AS occupied_beds,
            SUM(CASE WHEN bed_type='icu' THEN 1 ELSE 0 END) AS icu_beds,
            SUM(CASE WHEN bed_type='isolation' THEN 1 ELSE 0 END) AS isolation_beds
        FROM bed_management
        WHERE room_number = ?";

    $stmt = $db->prepare($bed_count_sql);
    $bed_counts = ['total_beds' => 0, 'occupied_beds' => 0, 'icu_beds' => 0, 'isolation_beds' => 0];

    if ($stmt) {
        $stmt->bind_param('s', $room_number);
        $stmt->execute();
        $bed_counts = $stmt->get_result()->fetch_assoc() ?? $bed_counts;
    }

    $total_beds = (int)($bed_counts['total_beds'] ?? 0);
    $occupied   = (int)($bed_counts['occupied_beds'] ?? 0);
    $icu_beds   = (int)($bed_counts['icu_beds'] ?? 0);
    $isolation_beds = (int)($bed_counts['isolation_beds'] ?? 0);

    // Déterminer le type de chambre principal
    $room_type = 'standard';
    $is_vip = false;
    $is_isolation = false;
    $is_icu = false;

    if ($icu_beds > 0 && $icu_beds == $total_beds) {
        $room_type = 'Soins intensifs';
        $is_icu = true;
    } elseif ($isolation_beds > 0 && $isolation_beds == $total_beds) {
        $room_type = 'Isolement';
        $is_isolation = true;
    }

    return [
        'room_number'     => $room_number,
        'room_type'       => $room_type,
        'room_type_code'  => strtolower(str_replace(' ', '_', $room_type)),
        'capacity'        => $total_beds,
        'occupied'        => $occupied,
        'available'       => max(0, $total_beds - $occupied),
        'is_vip'          => false,
        'is_garde_malade' => false,
        'is_icu'          => $is_icu,
        'is_isolation'    => $is_isolation,
        'rule_msg'        => '',
        'source'          => 'bed_management',
    ];
}

/* ================= GET ROOM WITH BEDS ================= */

/**
 * Récupère les informations d'une salle avec ses lits disponibles
 */
function getRoomWithBeds($db, string $room_number): array {
    $sql = "SELECT b.*, 
                   u.full_name as patient_name,
                   pt.allergies as patient_allergies
            FROM bed_management b
            LEFT JOIN patients pt ON pt.id = b.patient_id
            LEFT JOIN users u ON u.id = pt.user_id
            WHERE b.room_number = ?
            ORDER BY b.bed_number ASC";

    $stmt = $db->prepare($sql);
    if (!$stmt) return ['room_number' => $room_number, 'beds' => []];

    $stmt->bind_param('s', $room_number);
    $stmt->execute();
    $result = $stmt->get_result();

    $beds = [];
    while ($bed = $result->fetch_assoc()) {
        $beds[] = $bed;
    }

    $capacityInfo = getRoomCapacityInfo($db, $room_number);

    return [
        'room_number' => $room_number,
        'beds' => $beds,
        'capacity' => $capacityInfo['capacity'],
        'occupied' => $capacityInfo['occupied'],
        'available' => $capacityInfo['available'],
        'room_type' => $capacityInfo['room_type']
    ];
}

/* ================= ALL ROOMS WITH CAPACITY ================= */

/**
 * Retourne toutes les salles avec leur résumé de capacité.
 */
function getAllRoomsCapacity($db): array {
    $res = $db->query("SELECT DISTINCT room_number FROM bed_management ORDER BY room_number ASC");
    $rooms = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rooms[] = getRoomCapacityInfo($db, $row['room_number']);
        }
    }
    return $rooms;
}

/* ================= CAPACITY CHECK ================= */

/**
 * Vérifie si on peut assigner un lit dans cette salle.
 * @return array ['ok' => bool, 'code' => string, 'msg' => string]
 */
function checkRoomCapacity($db, string $room_number): array {
    $info = getRoomCapacityInfo($db, $room_number);

    // Vérifier s'il reste des lits disponibles
    if ($info['available'] <= 0) {
        return [
            'ok'   => false,
            'code' => 'err_cap_full',
            'msg'  => "Salle {$room_number} est complète ({$info['occupied']}/{$info['capacity']} lits occupés).",
        ];
    }

    return ['ok' => true, 'code' => '', 'msg' => ''];
}

/* ================= CHECK BED TYPE RESTRICTIONS ================= */

/**
 * Vérifie si un patient peut être assigné à un lit selon son type.
 */
function checkBedTypeRestrictions($db, int $bed_id, int $patient_id): array {
    $bed = getBedById($db, $bed_id);
    if (!$bed) {
        return ['ok' => false, 'code' => 'err_bid', 'msg' => 'Lit introuvable.'];
    }

    if ($bed['bed_type'] === 'pediatric') {
        // Calculer l'âge depuis dob (la table patients n'a pas de colonne age)
        $stmt = $db->prepare("SELECT dob FROM patients WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $patient_id);
            $stmt->execute();
            $patient = $stmt->get_result()->fetch_assoc();
            if ($patient && !empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
                try {
                    $age = (int)(new DateTime())->diff(new DateTime($patient['dob']))->y;
                    if ($age > 14) {
                        return ['ok' => false, 'code' => 'err_age',
                            'msg' => 'Lit pédiatrique réservé aux enfants de moins de 14 ans.'];
                    }
                } catch (Exception $e) { /* dob invalide, on ignore */ }
            }
        }
    }

    return ['ok' => true, 'code' => '', 'msg' => ''];
}

/* ================= AVAILABLE PATIENTS FOR BED ================= */

/**
 * Patients qui ne sont pas actuellement dans un lit occupé.
 * Utilisé par beds.php pour la liste déroulante (fallback sans AJAX).
 */
function getAvailablePatientsForBed($db): array {
    $sql = "SELECT pt.id, u.full_name, u.phone, pt.uhid
            FROM patients pt
            INNER JOIN users u ON u.id = pt.user_id
            WHERE pt.id NOT IN (
                SELECT patient_id FROM bed_management
                WHERE status = 'occupied' AND patient_id IS NOT NULL
            )
            ORDER BY u.full_name ASC
            LIMIT 200";
    $res = $db->query($sql);
    $out = [];
    if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

/* ================= ASSIGN BED ================= */

/**
 * Assigne un lit à un patient de façon atomique (transaction).
 * ✔ Vérifie la capacité de la salle
 * ✔ Respecte les restrictions par type de lit
 */
function assignBed($db, int $bed_id, int $pid, string &$err_code, string &$err_msg = ''): bool {
    // Valider le lit
    $bed = getBedById($db, $bed_id);
    if (!$bed) {
        $err_code = 'err_bid';
        $err_msg  = 'Lit introuvable.';
        return false;
    }
    if ($bed['status'] !== 'available') {
        $err_code = 'err_occ';
        $err_msg  = 'Ce lit est déjà ' . (BED_STATUS_LABELS[$bed['status']] ?? $bed['status']) . '.';
        return false;
    }

    // Valider le patient
    $stmt = $db->prepare("SELECT id FROM patients WHERE id = ? LIMIT 1");
    if (!$stmt) { $err_code = 'err_db'; $err_msg = 'Erreur de base de données.'; return false; }
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $err_code = 'err_pid';
        $err_msg  = 'Patient introuvable.';
        return false;
    }

    // Vérifier que le patient n'est pas déjà dans un lit
    $stmt_chk = $db->prepare(
        "SELECT id FROM bed_management WHERE patient_id = ? AND status = 'occupied' LIMIT 1"
    );
    if ($stmt_chk) {
        $stmt_chk->bind_param('i', $pid);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows > 0) {
            $err_code = 'err_already';
            $err_msg  = 'Ce patient est déjà assigné à un lit.';
            return false;
        }
    }

    // Vérifier les restrictions de type de lit
    $type_check = checkBedTypeRestrictions($db, $bed_id, $pid);
    if (!$type_check['ok']) {
        $err_code = $type_check['code'];
        $err_msg  = $type_check['msg'];
        return false;
    }

    // Vérifier capacité de la salle
    $cap_check = checkRoomCapacity($db, $bed['room_number']);
    if (!$cap_check['ok']) {
        $err_code = $cap_check['code'];
        $err_msg  = $cap_check['msg'];
        return false;
    }

    // Transaction atomique
    $db->begin_transaction();
    try {
        // 1. Mettre à jour le lit
        $s1 = $db->prepare(
            "UPDATE bed_management
             SET status = 'occupied', patient_id = ?, admission_date = NOW()
             WHERE id = ? AND status = 'available'"
        );
        if (!$s1) throw new Exception('Erreur prepare bed update.');
        $s1->bind_param('ii', $pid, $bed_id);
        $s1->execute();
        if ($s1->affected_rows !== 1) {
            $db->rollback();
            $err_code = 'err_occ';
            $err_msg  = "Ce lit vient d'être pris par un autre utilisateur.";
            return false;
        }

        // 2. Créer l'enregistrement d'hospitalisation
        $s2 = $db->prepare(
            "INSERT INTO hospitalizations (patient_id, doctor_id, bed_id, admission_date, status)
             VALUES (?, COALESCE(
                 (SELECT doctor_id FROM appointments
                  WHERE patient_id = ? ORDER BY id DESC LIMIT 1),
                 0
             ), ?, NOW(), 'admitted')"
        );
        if ($s2) {
            $s2->bind_param('iii', $pid, $pid, $bed_id);
            $s2->execute();
        }

        // 3. Log d'audit
        $room_info = getRoomCapacityInfo($db, $bed['room_number']);
        logBedAction($db, 'ASSIGN_BED',
            "Lit ID={$bed_id} (Salle {$bed['room_number']}, Lit {$bed['bed_number']}) " .
            "assigné au patient ID={$pid}. " .
            "Occupation salle: " . ($room_info['occupied'] + 1) . "/{$room_info['capacity']}"
        );

        $db->commit();
        $err_code = '';
        $err_msg  = '';
        return true;

    } catch (Throwable $e) {
        $db->rollback();
        $err_code = 'err_db';
        $err_msg  = 'Erreur de base de données. Opération annulée.';
        return false;
    }
}

/* ================= ASSIGN MULTIPLE BEDS FOR SAME PATIENT (GARDE MALADE) ================= */

/**
 * Assigne MULTIPLES lits pour le même patient (mode garde malade)
 * Cette fonction contourne la vérification "patient déjà assigné" car en mode garde malade,
 * un patient peut occuper plusieurs lits (lui + son accompagnant)
 *
 * @param mysqli $db Connexion à la base de données
 * @param array $bed_ids Tableau des IDs de lits à assigner
 * @param int $pid ID du patient
 * @param string &$err_code Code d'erreur (référence)
 * @param string &$err_msg Message d'erreur (référence)
 * @return bool Succès ou échec de l'opération
 */
function assignMultipleBedsForPatient($db, array $bed_ids, int $pid, string &$err_code, string &$err_msg = ''): bool {
    if (count($bed_ids) < 1) {
        $err_code = 'err_min';
        $err_msg = 'Au moins 1 lit requis.';
        return false;
    }

    // Vérifier que tous les lits existent et sont disponibles
    $first_room = null;
    $beds_info = [];

    foreach ($bed_ids as $bed_id) {
        $bed = getBedById($db, $bed_id);
        if (!$bed) {
            $err_code = 'err_bid';
            $err_msg = "Lit ID $bed_id introuvable.";
            return false;
        }
        if ($bed['status'] !== 'available') {
            $err_code = 'err_occ';
            $err_msg = "Lit {$bed['bed_number']} n'est pas disponible (statut: {$bed['status']}).";
            return false;
        }

        // Pour le mode garde malade (2 lits), vérifier qu'ils sont dans la même chambre
        if (count($bed_ids) >= 2) {
            if ($first_room === null) {
                $first_room = $bed['room_number'];
            } elseif ($first_room !== $bed['room_number']) {
                $err_code = 'err_room';
                $err_msg = 'Tous les lits doivent être dans la MÊME chambre pour le mode Garde Malade.';
                return false;
            }
        }

        // Vérifier les restrictions par type de lit
        $type_check = checkBedTypeRestrictions($db, $bed_id, $pid);
        if (!$type_check['ok']) {
            $err_code = $type_check['code'];
            $err_msg = $type_check['msg'];
            return false;
        }

        $beds_info[] = $bed;
    }

    // Vérifier la capacité de la chambre (assez de lits disponibles)
    if ($first_room) {
        $cap_check = checkRoomCapacity($db, $first_room);
        if (!$cap_check['ok']) {
            $err_code = $cap_check['code'];
            $err_msg = $cap_check['msg'];
            return false;
        }
    }

    // Transaction atomique pour assigner tous les lits
    $db->begin_transaction();
    try {
        $assigned_count = 0;

        foreach ($beds_info as $bed) {
            // Mettre à jour le lit (sans vérifier si le patient a déjà un lit)
            $s1 = $db->prepare(
                "UPDATE bed_management
                 SET status = 'occupied', patient_id = ?, admission_date = NOW()
                 WHERE id = ? AND status = 'available'"
            );
            if (!$s1) throw new Exception('Erreur prepare bed update.');
            $s1->bind_param('ii', $pid, $bed['id']);
            $s1->execute();

            if ($s1->affected_rows !== 1) {
                throw new Exception("Le lit {$bed['bed_number']} (ID {$bed['id']}) n'a pas pu être assigné - il a peut-être été pris par un autre utilisateur.");
            }
            $assigned_count++;
        }

        // Créer un seul enregistrement d'hospitalisation (ou mettre à jour si existant)
        $check_hosp = $db->prepare(
            "SELECT id FROM hospitalizations WHERE patient_id = ? AND status = 'admitted' LIMIT 1"
        );
        $hosp_exists = false;
        if ($check_hosp) {
            $check_hosp->bind_param('i', $pid);
            $check_hosp->execute();
            $hosp_exists = $check_hosp->get_result()->num_rows > 0;
        }

        $primary_bed_id = $bed_ids[0];
        $notes = "GARDE MALADE - Lits assignés: " . implode(', ', $bed_ids);

        if (!$hosp_exists) {
            $s2 = $db->prepare(
                "INSERT INTO hospitalizations (patient_id, doctor_id, bed_id, admission_date, status, notes)
                 VALUES (?, COALESCE(
                     (SELECT doctor_id FROM appointments
                      WHERE patient_id = ? ORDER BY id DESC LIMIT 1),
                     0
                 ), ?, NOW(), 'admitted', ?)"
            );
            if ($s2) {
                $s2->bind_param('iiis', $pid, $pid, $primary_bed_id, $notes);
                $s2->execute();
            }
        } else {
            // Mettre à jour les notes de l'hospitalisation existante
            $s2 = $db->prepare(
                "UPDATE hospitalizations 
                 SET notes = CONCAT(IFNULL(notes, ''), ' | ', ?)
                 WHERE patient_id = ? AND status = 'admitted'
                 ORDER BY id DESC LIMIT 1"
            );
            if ($s2) {
                $update_notes = "AJOUT LITS: " . implode(', ', $bed_ids);
                $s2->bind_param('si', $update_notes, $pid);
                $s2->execute();
            }
        }

        // Log d'audit
        $bed_list = [];
        foreach ($beds_info as $bed) {
            $bed_list[] = "{$bed['room_number']}-{$bed['bed_number']}";
        }
        logBedAction($db, 'ASSIGN_MULTIPLE_BEDS',
            "Lits [" . implode(', ', $bed_list) . "] assignés au patient ID={$pid} (Mode Garde Malade - {$assigned_count} lit(s))"
        );

        $db->commit();
        $err_code = '';
        $err_msg = '';
        return true;

    } catch (Throwable $e) {
        $db->rollback();
        $err_code = 'err_db';
        $err_msg = $e->getMessage();
        return false;
    }
}

/* ================= RELEASE BED ================= */

/**
 * Libère un lit et crée automatiquement une tâche de nettoyage.
 */
function releaseBed($db, int $bed_id, string &$err_code, string &$err_msg = ''): bool {
    $bed = getBedById($db, $bed_id);
    if (!$bed) {
        $err_code = 'err_bid';
        $err_msg  = 'Lit introuvable.';
        return false;
    }
    if ($bed['status'] !== 'occupied') {
        $err_code = 'err_free';
        $err_msg  = "Ce lit n'est pas occupé.";
        return false;
    }

    $db->begin_transaction();
    try {
        // 1. Lit → nettoyage
        $s1 = $db->prepare(
            "UPDATE bed_management
             SET status = 'cleaning', patient_id = NULL, admission_date = NULL
             WHERE id = ? AND status = 'occupied'"
        );
        if (!$s1) throw new Exception('Erreur prepare release.');
        $s1->bind_param('i', $bed_id);
        $s1->execute();
        if ($s1->affected_rows !== 1) {
            $db->rollback();
            $err_code = 'err_free';
            $err_msg  = "Le lit n'était plus occupé.";
            return false;
        }

        // 2. Clôturer l'hospitalisation active
        $s2 = $db->prepare(
            "UPDATE hospitalizations
             SET discharge_date = NOW(), status = 'discharged'
             WHERE bed_id = ? AND status = 'admitted'
             ORDER BY id DESC LIMIT 1"
        );
        if ($s2) {
            $s2->bind_param('i', $bed_id);
            $s2->execute();
        }

        // 3. Créer une tâche de nettoyage
        $s3 = $db->prepare(
            "INSERT INTO cleaning_tasks (bed_id, room_number, area_type, task_type, priority, status, created_at)
             VALUES (?, ?, 'chambre', 'discharge', 'high', 'pending', NOW())"
        );
        if ($s3) {
            $s3->bind_param('is', $bed_id, $bed['room_number']);
            $s3->execute();
        }

        // 4. Log d'audit
        logBedAction($db, 'RELEASE_BED',
            "Lit ID={$bed_id} (Salle {$bed['room_number']}, Lit {$bed['bed_number']}) " .
            "libéré. Tâche de nettoyage créée."
        );

        $db->commit();
        $err_code = '';
        $err_msg  = '';
        return true;

    } catch (Throwable $e) {
        $db->rollback();
        $err_code = 'err_db';
        $err_msg  = 'Erreur de base de données. Opération annulée.';
        return false;
    }
}

/* ================= MARK CLEANING DONE ================= */

/**
 * Marque le nettoyage d'un lit comme terminé → statut "available".
 */
function markCleaningDone($db, int $bed_id, string &$err_code, string &$err_msg = ''): bool {
    $bed = getBedById($db, $bed_id);
    if (!$bed) {
        $err_code = 'err_bid';
        $err_msg  = 'Lit introuvable.';
        return false;
    }
    if ($bed['status'] !== 'cleaning') {
        $err_code = 'err_val';
        $err_msg  = "Ce lit n'est pas en cours de nettoyage.";
        return false;
    }

    $db->begin_transaction();
    try {
        // 1. Lit → disponible
        $s1 = $db->prepare(
            "UPDATE bed_management SET status = 'available' WHERE id = ? AND status = 'cleaning'"
        );
        if (!$s1) throw new Exception('Erreur prepare cleaning done.');
        $s1->bind_param('i', $bed_id);
        $s1->execute();
        if ($s1->affected_rows !== 1) {
            $db->rollback();
            $err_code = 'err_val';
            $err_msg  = "L'état du lit a changé entre-temps.";
            return false;
        }

        // 2. Clôturer les tâches de nettoyage en attente
        $s2 = $db->prepare(
            "UPDATE cleaning_tasks
             SET status = 'completed', completed_at = NOW()
             WHERE bed_id = ? AND status = 'pending'"
        );
        if ($s2) {
            $s2->bind_param('i', $bed_id);
            $s2->execute();
        }

        // 3. Log d'audit
        logBedAction($db, 'CLEANING_DONE',
            "Nettoyage terminé pour lit ID={$bed_id} " .
            "(Salle {$bed['room_number']}, Lit {$bed['bed_number']})."
        );

        $db->commit();
        $err_code = '';
        $err_msg  = '';
        return true;

    } catch (Throwable $e) {
        $db->rollback();
        $err_code = 'err_db';
        $err_msg  = 'Erreur de base de données. Opération annulée.';
        return false;
    }
}

/* ================= ROOM SUMMARY FOR AJAX ================= */

/**
 * Retourne le résumé JSON d'une salle (pour room_capacity_ajax.php).
 */
function getRoomSummaryJson($db, string $room_number): array {
    $info = getRoomCapacityInfo($db, $room_number);
    $pct  = ($info['capacity'] > 0)
        ? round($info['occupied'] / $info['capacity'] * 100)
        : 0;
    return [
        'room_number'     => $info['room_number'],
        'room_type'       => $info['room_type'],
        'capacity'        => $info['capacity'],
        'occupied'        => $info['occupied'],
        'available'       => $info['available'],
        'pct'             => $pct,
        'is_vip'          => $info['is_vip'],
        'is_garde_malade' => $info['is_garde_malade'],
        'rule_msg'        => $info['rule_msg'],
        'can_assign'      => ($info['available'] > 0),
        'source'          => $info['source'],
    ];
}
?>