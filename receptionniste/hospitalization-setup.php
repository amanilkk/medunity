<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  hospitalization-setup.php — Configuration hospitalisation
//  Sélection du médecin responsable + option garde malade
//  CORRIGÉ : Gestion correcte des 2 lits pour garde malade
// ================================================================
require_once 'functions.php';
require_once 'bed_functions.php';
requireReceptionniste();
include '../connection.php';

$patient_id = (int)($_GET['pid'] ?? 0);
$error = '';
$success = false;
$patient = null;

if ($patient_id > 0 && patientExists($database, $patient_id)) {
    $patient = getPatientById($database, $patient_id);
} else {
    header('Location: reception.php');
    exit;
}

$doctors = getDoctors($database);

// Récupérer tous les lits disponibles
$beds_sql = "SELECT b.* 
             FROM bed_management b 
             WHERE b.status = 'available' 
             ORDER BY b.room_number ASC, b.bed_number ASC";
$beds_result = $database->query($beds_sql);
$all_beds = [];

if ($beds_result) {
    while ($bed = $beds_result->fetch_assoc()) {
        $all_beds[] = $bed;
    }
}

// Organiser les lits par chambre
$beds_by_room = [];
foreach ($all_beds as $bed) {
    if (!isset($beds_by_room[$bed['room_number']])) {
        $beds_by_room[$bed['room_number']] = [
            'room_number' => $bed['room_number'],
            'beds' => [],
            'available_count' => 0
        ];
    }
    $beds_by_room[$bed['room_number']]['beds'][] = $bed;
    $beds_by_room[$bed['room_number']]['available_count']++;
}

// Pour le mode garde malade : chambres avec au moins 2 lits disponibles
$rooms_with_2_beds = [];
foreach ($beds_by_room as $room_number => $room_data) {
    if ($room_data['available_count'] >= 2) {
        $rooms_with_2_beds[] = $room_data;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $has_garde_malade = isset($_POST['has_garde_malade']) ? (int)$_POST['has_garde_malade'] : 0;
    $bed_ids = $_POST['bed_ids'] ?? [];
    $reason = trim($_POST['reason'] ?? 'Hospitalisation');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $accompanying_person_name = trim($_POST['accompanying_person_name'] ?? '');
    $accompanying_person_phone = trim($_POST['accompanying_person_phone'] ?? '');
    $hospitalization_type = $_POST['hospitalization_type'] ?? 'standard';

    $beds_needed = $has_garde_malade ? 2 : 1;

    if ($doctor_id <= 0) {
        $error = 'Veuillez sélectionner un médecin responsable.';
    } elseif (count($bed_ids) != $beds_needed) {
        $error = "Veuillez sélectionner exactement $beds_needed lit(s).";
    } elseif ($has_garde_malade) {
        // Vérifier que tous les lits sont dans la même chambre
        $first_bed_room = null;
        $all_same_room = true;
        $valid_beds = [];

        foreach ($bed_ids as $bed_id) {
            $found = false;
            foreach ($all_beds as $bed) {
                if ($bed['id'] == $bed_id) {
                    $found = true;
                    $valid_beds[] = $bed;
                    if ($first_bed_room === null) {
                        $first_bed_room = $bed['room_number'];
                    } elseif ($first_bed_room !== $bed['room_number']) {
                        $all_same_room = false;
                        break 2;
                    }
                }
            }
            if (!$found) {
                $error = "Lit ID $bed_id introuvable.";
                break;
            }
        }

        if (empty($error) && !$all_same_room) {
            $error = 'Les 2 lits doivent être dans la MÊME chambre pour le mode Garde malade.';
        }
    }

    if (empty($error)) {
        $database->begin_transaction();

        try {
            // ============================================================
            // MODE GARDE MALADE : Utiliser la fonction spéciale
            // ============================================================
            if ($has_garde_malade) {
                $err_code = '';
                $err_msg = '';
                $success_assign = assignMultipleBedsForPatient($database, $bed_ids, $patient_id, $err_code, $err_msg);

                if (!$success_assign) {
                    throw new Exception($err_msg);
                }
                $assigned_beds = $bed_ids;

                // Créer un rendez-vous pour la garde malade
                $appointment_date = date('Y-m-d');
                $appointment_time = date('H:i:s');
                $reason_text = "Hospitalisation avec garde malade - Lits: " . implode(',', $bed_ids);

                $stmt2 = $database->prepare(
                    "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, type, priority, reason, created_at) 
                     VALUES (?, ?, ?, ?, 'confirmed', 'garde_malade', 1, ?, NOW())"
                );

                if (!$stmt2) {
                    throw new Exception('Erreur prepare appointment: ' . $database->error);
                }

                $stmt2->bind_param('iisss', $patient_id, $doctor_id, $appointment_date, $appointment_time, $reason_text);

                if (!$stmt2->execute()) {
                    throw new Exception('Erreur insertion appointment: ' . $stmt2->error);
                }

                // Créer un enregistrement d'hospitalisation avec les notes
                $primary_bed_id = $bed_ids[0];
                $notes = "Type: $hospitalization_type | Garde malade: Oui";
                $notes .= " | Accompagnant: $accompanying_person_name (Tél: $accompanying_person_phone)";
                $notes .= " | Lits assignés: " . implode(',', $assigned_beds);

                $stmt = $database->prepare(
                    "INSERT INTO hospitalizations (patient_id, doctor_id, bed_id, admission_date, reason, diagnosis_entry, status, notes) 
                     VALUES (?, ?, ?, NOW(), ?, ?, 'admitted', ?)"
                );

                if (!$stmt) {
                    throw new Exception('Erreur prepare hospitalization: ' . $database->error);
                }

                $stmt->bind_param('iiisss', $patient_id, $doctor_id, $primary_bed_id, $reason, $diagnosis, $notes);

                if (!$stmt->execute()) {
                    throw new Exception('Erreur insertion hospitalization: ' . $stmt->error);
                }

            } else {
                // ============================================================
                // MODE NORMAL : Un seul lit
                // ============================================================
                $bed_id = $bed_ids[0];

                // Récupérer le lit pour vérifier
                $bed_info = null;
                foreach ($all_beds as $bed) {
                    if ($bed['id'] == $bed_id) {
                        $bed_info = $bed;
                        break;
                    }
                }

                if (!$bed_info) {
                    throw new Exception("Lit ID $bed_id introuvable");
                }

                $err_code = '';
                $err_msg = '';
                $bed_assigned = assignBed($database, (int)$bed_id, $patient_id, $err_code, $err_msg);

                if (!$bed_assigned) {
                    throw new Exception("Erreur lit : " . ($err_msg ?: 'Assignation impossible'));
                }
                $assigned_beds = [$bed_id];

                // Créer un rendez-vous normal
                $appointment_date = date('Y-m-d');
                $appointment_time = date('H:i:s');
                $type_val = $hospitalization_type;

                $stmt2 = $database->prepare(
                    "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, type, priority, reason, created_at) 
                     VALUES (?, ?, ?, ?, 'confirmed', ?, 1, ?, NOW())"
                );

                if (!$stmt2) {
                    throw new Exception('Erreur prepare appointment: ' . $database->error);
                }

                $stmt2->bind_param('iissss', $patient_id, $doctor_id, $appointment_date, $appointment_time, $type_val, $reason);

                if (!$stmt2->execute()) {
                    throw new Exception('Erreur insertion appointment: ' . $stmt2->error);
                }

                // Créer l'enregistrement d'hospitalisation
                $notes = "Type: $hospitalization_type | Garde malade: Non";

                $stmt = $database->prepare(
                    "INSERT INTO hospitalizations (patient_id, doctor_id, bed_id, admission_date, reason, diagnosis_entry, status, notes) 
                     VALUES (?, ?, ?, NOW(), ?, ?, 'admitted', ?)"
                );

                if (!$stmt) {
                    throw new Exception('Erreur prepare hospitalization: ' . $database->error);
                }

                $stmt->bind_param('iiisss', $patient_id, $doctor_id, $bed_id, $reason, $diagnosis, $notes);

                if (!$stmt->execute()) {
                    throw new Exception('Erreur insertion hospitalization: ' . $stmt->error);
                }
            }

            $database->commit();
            $success = true;
            $success_beds = $assigned_beds;
            $success_garde = $has_garde_malade;

        } catch (Exception $e) {
            $database->rollback();
            $error = $e->getMessage();
        }
    }
}

function calcAge($dob) {
    if (!$dob || $dob === '0000-00-00') return 'N/A';
    try { return (new DateTime())->diff(new DateTime($dob))->y . ' ans'; }
    catch (Exception $e) { return 'N/A'; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Hospitalisation - <?php echo htmlspecialchars($patient['full_name'] ?? ''); ?></title>
    <link rel="stylesheet" href="recept.css">
    <style>
        .patient-card {
            background: linear-gradient(135deg, #0F1923, #1C3040);
            border-radius: var(--r); padding: 18px 22px; margin-bottom: 22px;
            color: white;
        }
        .patient-card h3 { margin: 0 0 8px 0; font-size: 1.1rem; }
        .patient-card .meta { font-size: .8rem; color: rgba(255,255,255,.6); }
        .allergy-warn {
            background: rgba(184,50,40,.2); border-left: 3px solid var(--red);
            padding: 8px 12px; border-radius: 6px;
            font-size: .8rem; color: #f87171;
        }

        .garde-toggle {
            background: var(--surf2); border-radius: var(--r);
            padding: 15px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 15px;
            flex-wrap: wrap;
        }
        .garde-toggle label {
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; padding: 8px 16px;
            border-radius: var(--rs); transition: all 0.2s;
        }
        .garde-toggle input {
            width: 18px; height: 18px; cursor: pointer;
            accent-color: var(--blue);
        }
        .garde-toggle label:hover { background: var(--border); }

        .room-select {
            max-height: 500px; overflow-y: auto;
            border: 1px solid var(--border); border-radius: var(--rs);
            padding: 12px;
        }
        .room-card {
            border: 2px solid var(--border); border-radius: var(--r);
            margin-bottom: 15px; overflow: hidden;
        }
        .room-header {
            padding: 10px 15px; background: var(--surf2);
            display: flex; align-items: center; justify-content: space-between;
            cursor: pointer;
        }
        .room-number { font-family: monospace; font-weight: 700; font-size: .9rem; }
        .room-capacity { font-size: .7rem; color: var(--text2); }
        .room-beds {
            padding: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .bed-option {
            display: flex; align-items: center; gap: 10px; padding: 8px 12px;
            border: 1px solid var(--border); border-radius: var(--rs);
            cursor: pointer; background: var(--surface);
            transition: all 0.2s;
        }
        .bed-option:hover { background: var(--green-l); border-color: var(--green); }
        .bed-option.selected { background: var(--green-l); border-color: var(--green); }
        .bed-option input { margin: 0; transform: scale(1.1); cursor: pointer; }
        .bed-info { flex: 1; }
        .bed-number { font-family: monospace; font-weight: 600; font-size: .85rem; }
        .bed-type { font-size: .65rem; color: var(--text2); }

        .selected-beds-info {
            background: var(--surf2); border-radius: var(--rs);
            padding: 10px; margin-top: 12px; font-size: .75rem;
        }
        .selected-beds-info.green { background: var(--green-l); color: var(--green); }
        .selected-beds-info.amber { background: var(--amber-l); color: var(--amber); }

        .accompanying-fields {
            background: var(--blue-l); border-radius: var(--rs);
            padding: 12px; margin-top: 12px;
            border-left: 3px solid var(--blue);
        }

        .success-box {
            text-align: center; padding: 30px;
            background: var(--green-l); border-radius: var(--r);
            border: 2px solid var(--green);
        }

        .beds-needed-info {
            margin-top: 8px; font-size: .7rem;
            padding: 6px 10px; background: var(--amber-l); border-radius: var(--rs);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-group label {
            font-size: .7rem;
            font-weight: 700;
            color: var(--text2);
        }
        .input, select.input {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--rs);
            font-family: inherit;
            font-size: .85rem;
            background: var(--surface);
        }
        .btn-primary, .btn-secondary {
            padding: 10px 20px;
            border-radius: var(--rs);
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: var(--green);
            color: white;
        }
        .btn-primary:hover {
            background: var(--green-d);
        }
        .btn-secondary {
            background: var(--surf2);
            border: 1px solid var(--border);
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary:hover {
            background: var(--border);
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            margin-bottom: 20px;
        }
        .card-head {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
        }
        .card-body {
            padding: 20px;
        }
        .alert {
            padding: 12px 15px;
            border-radius: var(--rs);
            margin-bottom: 15px;
        }
        .alert-error {
            background: var(--red-l);
            color: var(--red);
            border-left: 3px solid var(--red);
        }
        .alert-success {
            background: var(--green-l);
            color: var(--green);
            border-left: 3px solid var(--green);
        }
        .type-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .type-option {
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: var(--rs);
            cursor: pointer;
            flex: 1;
            text-align: center;
            transition: all 0.2s;
        }
        .type-option.selected {
            border-color: var(--green);
            background: var(--green-l);
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">🏥 Hospitalisation</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body" style="max-width:900px;margin:0 auto">

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ Erreur :</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-box">
                <h2>✓ Hospitalisation enregistrée</h2>
                <p>Le patient a été admis avec succès.</p>
                <?php if ($success_garde): ?>
                    <p style="margin-top:10px;color:var(--blue);">
                        👨‍⚕️ Mode Garde Malade actif - 2 lits assignés
                    </p>
                    <p style="font-size:0.85rem;margin-top:5px;">
                        Lits: <?php echo implode(', ', $success_beds); ?>
                    </p>
                <?php endif; ?>
                <div style="margin-top:25px">
                    <a href="beds.php" class="btn-primary" style="padding:10px 20px;text-decoration:none;border-radius:8px;display:inline-block;">📊 Voir gestion des lits</a>
                    <a href="reception.php" class="btn-secondary" style="padding:10px 20px;text-decoration:none;border-radius:8px;margin-left:10px;display:inline-block;">← Retour accueil</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Patient info -->
            <div class="patient-card">
                <h3><?php echo htmlspecialchars($patient['full_name'] ?? ''); ?></h3>
                <div class="meta">
                    📞 <?php echo htmlspecialchars($patient['phone'] ?? ''); ?> |
                    🆔 <?php echo htmlspecialchars($patient['uhid'] ?? ''); ?> |
                    🎂 <?php echo calcAge($patient['dob'] ?? null); ?>
                    <?php if (!empty($patient['blood_type'])): ?>
                        | 🩸 <?php echo htmlspecialchars($patient['blood_type']); ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($patient['allergies'])): ?>
                    <div class="allergy-warn">⚠️ ALLERGIE : <?php echo htmlspecialchars($patient['allergies']); ?></div>
                <?php endif; ?>
            </div>

            <form method="POST" id="hospitalizationForm">
                <!-- Médecin responsable -->
                <div class="card">
                    <div class="card-head"><h3>👨‍⚕️ Médecin responsable</h3></div>
                    <div class="card-body">
                        <select class="input" name="doctor_id" id="doctor_id" required>
                            <option value="">— Sélectionner un médecin —</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?php echo $doc['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doc['name']); ?>
                                    <?php if (!empty($doc['specialty'])): ?>
                                        (<?php echo htmlspecialchars($doc['specialty']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Type d'hospitalisation -->
                <div class="card">
                    <div class="card-head"><h3>🏥 Type d'hospitalisation</h3></div>
                    <div class="card-body">
                        <div class="type-selector">
                            <div class="type-option selected" data-type="standard" onclick="selectType('standard')">
                                Standard
                            </div>
                            <div class="type-option" data-type="urgence" onclick="selectType('urgence')">
                                Urgence
                            </div>
                            <div class="type-option" data-type="chirurgie" onclick="selectType('chirurgie')">
                                Chirurgie
                            </div>
                        </div>
                        <input type="hidden" name="hospitalization_type" id="hospitalization_type" value="standard">
                    </div>
                </div>

                <!-- Option Garde Malade -->
                <div class="card">
                    <div class="card-head"><h3>👨‍⚕️ Garde malade</h3></div>
                    <div class="card-body">
                        <div class="garde-toggle">
                            <label>
                                <input type="radio" name="has_garde_malade" value="0" checked onchange="toggleGardeMalade(0)">
                                <span>🚫 Non</span>
                            </label>
                            <label>
                                <input type="radio" name="has_garde_malade" value="1" onchange="toggleGardeMalade(1)">
                                <span>👨‍⚕️ Oui (patient + accompagnant - 2 lits même chambre)</span>
                            </label>
                        </div>
                        <div class="beds-needed-info" id="bedsNeededInfo">
                            📌 Sélectionnez 1 lit
                        </div>

                        <div id="accompanyingFields" style="display:none;">
                            <div class="accompanying-fields">
                                <h4>👤 Informations de l'accompagnant</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Nom complet</label>
                                        <input class="input" type="text" name="accompanying_person_name" placeholder="Nom de l'accompagnant">
                                    </div>
                                    <div class="form-group">
                                        <label>Téléphone</label>
                                        <input class="input" type="tel" name="accompanying_person_phone" placeholder="Téléphone">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sélection des lits -->
                <div class="card">
                    <div class="card-head"><h3 id="bedsCardTitle">🛏️ Sélection du lit</h3></div>
                    <div class="card-body">
                        <div id="standardBedsView">
                            <?php if (count($all_beds) === 0): ?>
                                <div class="alert alert-error">Aucun lit disponible.</div>
                            <?php else: ?>
                                <div class="room-select">
                                    <?php foreach ($beds_by_room as $room_number => $room_data): ?>
                                        <div class="room-card" data-room="<?php echo $room_number; ?>">
                                            <div class="room-header" onclick="toggleRoom('<?php echo $room_number; ?>')">
                                                <div>
                                                    <span class="room-number">Salle <?php echo htmlspecialchars($room_number); ?></span>
                                                </div>
                                                <div class="room-capacity">
                                                    <?php echo $room_data['available_count']; ?> lit(s) disponible(s)
                                                </div>
                                            </div>
                                            <div class="room-beds" id="room-<?php echo $room_number; ?>" style="display:flex;">
                                                <?php foreach ($room_data['beds'] as $bed): ?>
                                                    <label class="bed-option" data-bed-id="<?php echo $bed['id']; ?>">
                                                        <input type="checkbox" name="bed_ids[]" value="<?php echo $bed['id']; ?>"
                                                               class="bed-checkbox"
                                                               onchange="updateBedSelection(this)">
                                                        <div class="bed-info">
                                                            <div class="bed-number">Lit <?php echo htmlspecialchars($bed['bed_number']); ?></div>
                                                            <div class="bed-type"><?php echo htmlspecialchars($bed['bed_type']); ?></div>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="gardeMaladeView" style="display:none;">
                            <?php if (count($rooms_with_2_beds) === 0): ?>
                                <div class="alert alert-error">⚠️ Aucune chambre avec 2 lits disponibles pour le mode Garde Malade.</div>
                            <?php else: ?>
                                <div class="room-select">
                                    <?php foreach ($rooms_with_2_beds as $room_data): ?>
                                        <div class="room-card" data-room="<?php echo $room_data['room_number']; ?>">
                                            <div class="room-header" onclick="toggleRoomGarde('<?php echo $room_data['room_number']; ?>')">
                                                <div>
                                                    <span class="room-number">Salle <?php echo htmlspecialchars($room_data['room_number']); ?></span>
                                                </div>
                                                <div class="room-capacity">
                                                    <?php echo $room_data['available_count']; ?> lit(s) disponible(s)
                                                </div>
                                            </div>
                                            <div class="room-beds" id="room-garde-<?php echo $room_data['room_number']; ?>" style="display:flex;">
                                                <div style="margin-bottom:10px;padding:8px;background:var(--green-l);color:var(--green);border-radius:var(--rs);width:100%;">
                                                    ✅ Chambre avec <?php echo $room_data['available_count']; ?> lits disponibles
                                                    <br>Sélectionnez EXACTEMENT 2 lits dans cette chambre
                                                </div>
                                                <?php foreach ($room_data['beds'] as $bed): ?>
                                                    <label class="bed-option" data-bed-id="<?php echo $bed['id']; ?>">
                                                        <input type="checkbox" name="bed_ids[]" value="<?php echo $bed['id']; ?>"
                                                               class="bed-checkbox-garde"
                                                               data-room="<?php echo $room_data['room_number']; ?>"
                                                               onchange="updateBedSelectionGarde(this)">
                                                        <div class="bed-info">
                                                            <div class="bed-number">Lit <?php echo htmlspecialchars($bed['bed_number']); ?></div>
                                                            <div class="bed-type"><?php echo htmlspecialchars($bed['bed_type']); ?></div>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="selectedBedsInfo" class="selected-beds-info" style="display:none;">
                            ✅ <span id="selectedBedsCount">0</span>
                            <span id="selectedBedsText">lit(s) sélectionné(s)</span>
                        </div>
                    </div>
                </div>

                <!-- Informations supplémentaires -->
                <div class="card">
                    <div class="card-head"><h3>📝 Informations</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Motif</label>
                            <input class="input" type="text" name="reason" value="Hospitalisation">
                        </div>
                        <div class="form-group">
                            <label>Diagnostic d'entrée</label>
                            <textarea class="input" name="diagnosis" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:20px;margin-bottom:30px;">
                    <button type="submit" class="btn-primary" style="padding:12px 24px;font-size:1rem;" id="submitBtn" disabled>
                        🏥 Confirmer l'hospitalisation
                    </button>
                    <a href="reception.php" class="btn-secondary" style="padding:12px 24px;text-decoration:none;font-size:1rem;">Annuler</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    let currentBedsNeeded = 1;
    let currentGardeMalade = 0;
    let selectedBedIds = [];
    let currentRoomForGarde = '';

    function selectType(type) {
        document.querySelectorAll('.type-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        document.querySelector(`.type-option[data-type="${type}"]`).classList.add('selected');
        document.getElementById('hospitalization_type').value = type;
    }

    function toggleGardeMalade(value) {
        currentGardeMalade = parseInt(value);
        currentBedsNeeded = currentGardeMalade === 1 ? 2 : 1;

        const infoDiv = document.getElementById('bedsNeededInfo');
        const bedsCardTitle = document.getElementById('bedsCardTitle');
        const accompanyingFields = document.getElementById('accompanyingFields');

        // Réinitialiser la sélection
        resetBedSelection();

        if (currentGardeMalade === 1) {
            infoDiv.innerHTML = '👨‍⚕️ Mode Garde Malade — Sélectionnez EXACTEMENT 2 lits dans la MÊME chambre';
            infoDiv.style.background = 'var(--blue-l)';
            bedsCardTitle.innerHTML = '🛏️ Sélection des lits (2 lits même chambre)';
            accompanyingFields.style.display = 'block';
            document.getElementById('standardBedsView').style.display = 'none';
            document.getElementById('gardeMaladeView').style.display = 'block';
        } else {
            infoDiv.innerHTML = '📌 Sélectionnez 1 lit';
            infoDiv.style.background = 'var(--amber-l)';
            bedsCardTitle.innerHTML = '🛏️ Sélection du lit';
            accompanyingFields.style.display = 'none';
            document.getElementById('standardBedsView').style.display = 'block';
            document.getElementById('gardeMaladeView').style.display = 'none';
        }

        updateSelectedBedsInfo();
    }

    function resetBedSelection() {
        selectedBedIds = [];
        currentRoomForGarde = '';

        document.querySelectorAll('.bed-checkbox, .bed-checkbox-garde').forEach(cb => {
            cb.checked = false;
            cb.closest('.bed-option')?.classList.remove('selected');
        });

        updateSelectedBedsInfo();
    }

    function updateBedSelection(checkbox) {
        const bedId = checkbox.value;
        const bedOption = checkbox.closest('.bed-option');

        if (checkbox.checked) {
            if (selectedBedIds.length >= currentBedsNeeded) {
                checkbox.checked = false;
                alert(`Veuillez sélectionner exactement ${currentBedsNeeded} lit(s).`);
                return;
            }
            selectedBedIds.push(bedId);
            bedOption.classList.add('selected');
        } else {
            const index = selectedBedIds.indexOf(bedId);
            if (index > -1) selectedBedIds.splice(index, 1);
            bedOption.classList.remove('selected');
        }
        updateSelectedBedsInfo();
    }

    function updateBedSelectionGarde(checkbox) {
        const bedId = checkbox.value;
        const bedRoom = checkbox.getAttribute('data-room');
        const bedOption = checkbox.closest('.bed-option');

        if (checkbox.checked) {
            // Vérifier la chambre
            if (currentRoomForGarde === '') {
                currentRoomForGarde = bedRoom;
            } else if (currentRoomForGarde !== bedRoom) {
                checkbox.checked = false;
                alert('Les 2 lits doivent être dans la MÊME chambre pour le mode Garde Malade !');
                return;
            }

            if (selectedBedIds.length >= 2) {
                checkbox.checked = false;
                alert('Maximum 2 lits pour le mode Garde Malade.');
                return;
            }

            selectedBedIds.push(bedId);
            bedOption.classList.add('selected');
        } else {
            const index = selectedBedIds.indexOf(bedId);
            if (index > -1) selectedBedIds.splice(index, 1);
            bedOption.classList.remove('selected');
            if (selectedBedIds.length === 0) {
                currentRoomForGarde = '';
            }
        }

        updateSelectedBedsInfo();
    }

    function updateSelectedBedsInfo() {
        const infoDiv = document.getElementById('selectedBedsInfo');
        const countSpan = document.getElementById('selectedBedsCount');
        const textSpan = document.getElementById('selectedBedsText');
        const submitBtn = document.getElementById('submitBtn');

        if (!countSpan) return;

        countSpan.textContent = selectedBedIds.length;

        if (selectedBedIds.length > 0) {
            infoDiv.style.display = 'block';

            if (selectedBedIds.length === currentBedsNeeded) {
                infoDiv.classList.remove('amber');
                infoDiv.classList.add('green');
                textSpan.textContent = `/ ${currentBedsNeeded} lit(s) sélectionné(s) - Prêt`;
                submitBtn.disabled = false;
            } else {
                infoDiv.classList.remove('green');
                infoDiv.classList.add('amber');
                textSpan.textContent = `/ ${currentBedsNeeded} lit(s) sélectionné(s)`;
                submitBtn.disabled = true;
            }
        } else {
            infoDiv.style.display = 'none';
            submitBtn.disabled = true;
        }
    }

    function toggleRoom(roomNumber) {
        const div = document.getElementById(`room-${roomNumber}`);
        if (div) {
            div.style.display = div.style.display !== 'none' ? 'none' : 'flex';
        }
    }

    function toggleRoomGarde(roomNumber) {
        const div = document.getElementById(`room-garde-${roomNumber}`);
        if (div) {
            div.style.display = div.style.display !== 'none' ? 'none' : 'flex';
        }
    }
</script>
</body>
</html>