<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// gmg_operating.php - Gestion des blocs opératoires avec planification
session_start();
if(isset($_SESSION["user"])){
    if($_SESSION["user"]=="" or $_SESSION['usertype']!='maintenance'){
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");
date_default_timezone_set('Africa/Algiers');

// Récupérer la liste du personnel médical
$nurses = $database->query("SELECT u.id, u.full_name FROM users u WHERE u.role_id IN (SELECT id FROM roles WHERE role_name = 'infirmier') AND u.is_active = 1 ORDER BY u.full_name");
$anesthesiologists = $database->query("SELECT d.id, u.full_name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.specialty_id = 3 AND u.is_active = 1 ORDER BY u.full_name");
$surgeons = $database->query("SELECT d.id, u.full_name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.specialty_id IN (11, 15, 19, 25, 30, 37, 42, 44, 52, 55) AND u.is_active = 1 ORDER BY u.full_name");

// Récupérer la liste des patients
$patients = $database->query("SELECT p.id, u.full_name, p.uhid FROM patients p JOIN users u ON u.id = p.user_id WHERE u.is_active = 1 ORDER BY u.full_name");

// Récupérer les types d'intervention
$surgery_types = $database->query("SELECT id, name, duration FROM surgery_types WHERE is_active = 1 ORDER BY name");

// Ajouter une planification
if(isset($_POST['add_schedule'])){
    $room_id = intval($_POST['room_id']);
    $patient_id = intval($_POST['patient_id']);
    $surgery_type_id = intval($_POST['surgery_type_id']);
    $surgery_type_custom = $database->real_escape_string($_POST['surgery_type_custom'] ?? '');
    $scheduled_date = $database->real_escape_string($_POST['scheduled_date']);
    $scheduled_time = $database->real_escape_string($_POST['scheduled_time']);
    $duration = intval($_POST['duration']);
    $doctor_id = intval($_POST['surgeon']);
    $notes = $database->real_escape_string($_POST['notes'] ?? '');
    $week_start = $_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'));

    $errors = [];
    if($patient_id <= 0) $errors[] = "Veuillez sélectionner un patient.";
    if($doctor_id <= 0) $errors[] = "Veuillez sélectionner un chirurgien.";
    if(empty($scheduled_date)) $errors[] = "Veuillez sélectionner une date.";
    if(empty($scheduled_time)) $errors[] = "Veuillez sélectionner une heure.";

    if($surgery_type_id > 0){
        $type_result = $database->query("SELECT name, duration FROM surgery_types WHERE id = $surgery_type_id");
        $type_info = $type_result->fetch_assoc();
        $surgery_type_name = $type_info['name'];
        $duration = $type_info['duration'];
    } else {
        $surgery_type_name = $surgery_type_custom;
        if(empty($surgery_type_name)) $errors[] = "Veuillez sélectionner ou saisir un type d'intervention.";
    }

    if(empty($errors)){
        $room_check = $database->query("SELECT status, room_number FROM operating_rooms WHERE id=$room_id")->fetch_assoc();
        $blocked_statuses = ['in_use', 'cleaning', 'maintenance', 'sterilization', 'reserved'];

        if(in_array($room_check['status'], $blocked_statuses)){
            $status_messages = [
                'in_use'        => 'en cours d\'utilisation',
                'cleaning'      => 'en nettoyage',
                'maintenance'   => 'en maintenance',
                'sterilization' => 'en stérilisation',
                'reserved'      => 'réservé'
            ];
            $msg = urlencode("Le bloc " . $room_check['room_number'] . " est actuellement " . $status_messages[$room_check['status']] . ". Impossible de programmer une intervention.");
            header("location: gmg_operating.php?action=add_schedule&room_id=$room_id&date=$scheduled_date&week_start=$week_start&error=$msg");
            exit();
        } else {
            $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time;
            $end_time = date('H:i:s', strtotime($scheduled_time . ' + ' . $duration . ' minutes'));
            $end_datetime = $scheduled_date . ' ' . $end_time;

            $conflict = $database->query("SELECT id FROM surgery_schedule 
                                          WHERE operating_room_id = $room_id 
                                          AND status NOT IN ('cancelled')
                                          AND (
                                              ('$scheduled_datetime' >= scheduled_start AND '$scheduled_datetime' < scheduled_end)
                                              OR ('$end_datetime' > scheduled_start AND '$end_datetime' <= scheduled_end)
                                              OR ('$scheduled_datetime' <= scheduled_start AND '$end_datetime' >= scheduled_end)
                                          )")->fetch_assoc();

            if($conflict){
                $msg = urlencode("Ce bloc a déjà une intervention programmée sur ce créneau horaire.");
                header("location: gmg_operating.php?action=add_schedule&room_id=$room_id&date=$scheduled_date&week_start=$week_start&error=$msg");
                exit();
            } else {
                $database->query("INSERT INTO surgery_schedule (operating_room_id, patient_id, doctor_id, surgery_type, scheduled_start, scheduled_end, team_notes, status) 
                                  VALUES ($room_id, $patient_id, $doctor_id, '$surgery_type_name', '$scheduled_datetime', '$end_datetime', '$notes', 'scheduled')");
                header("location: gmg_operating.php?msg=schedule_added&week_start=$week_start");
                exit();
            }
        }
    } else {
        $msg = urlencode(implode(" | ", $errors));
        header("location: gmg_operating.php?action=add_schedule&room_id=$room_id&date=$scheduled_date&week_start=$week_start&error=$msg");
        exit();
    }
}

// Modifier une planification
if(isset($_POST['edit_schedule'])){
    $schedule_id = intval($_POST['schedule_id']);
    $status = $database->real_escape_string($_POST['status']);
    $notes = $database->real_escape_string($_POST['notes'] ?? '');
    $week_start = $_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    $database->query("UPDATE surgery_schedule SET status='$status', team_notes='$notes' WHERE id=$schedule_id");
    header("location: gmg_operating.php?msg=schedule_updated&week_start=$week_start");
    exit();
}

// Supprimer une planification
if(isset($_GET['delete_schedule'])){
    $id = intval($_GET['delete_schedule']);
    $week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    $database->query("DELETE FROM surgery_schedule WHERE id=$id");
    header("location: gmg_operating.php?msg=schedule_deleted&week_start=$week_start");
    exit();
}

// Ajouter un bloc opératoire
if(isset($_POST['add_operating_room'])){
    $room_number = $database->real_escape_string($_POST['room_number']);
    $room_name = $database->real_escape_string($_POST['room_name']);
    $room_type = $database->real_escape_string($_POST['room_type']);
    $equipment = $database->real_escape_string($_POST['equipment_available']);
    $nurse = $database->real_escape_string($_POST['nurse_assigned'] ?? '');
    $anesthesiologist = $database->real_escape_string($_POST['anesthesiologist'] ?? '');
    $surgeon = $database->real_escape_string($_POST['surgeon'] ?? '');
    $database->query("INSERT INTO operating_rooms (room_number, room_name, room_type, equipment_available, nurse_assigned, anesthesiologist, surgeon, status) 
                      VALUES ('$room_number', '$room_name', '$room_type', '$equipment', '$nurse', '$anesthesiologist', '$surgeon', 'available')");
    header("location: gmg_operating.php?msg=added");
    exit();
}

// Modifier un bloc
if(isset($_POST['edit_operating_room'])){
    $id = intval($_POST['room_id']);
    $room_number = $database->real_escape_string($_POST['room_number']);
    $room_name = $database->real_escape_string($_POST['room_name']);
    $room_type = $database->real_escape_string($_POST['room_type']);
    $status = $database->real_escape_string($_POST['status']);
    $equipment = $database->real_escape_string($_POST['equipment_available']);
    $nurse = $database->real_escape_string($_POST['nurse_assigned'] ?? '');
    $anesthesiologist = $database->real_escape_string($_POST['anesthesiologist'] ?? '');
    $surgeon = $database->real_escape_string($_POST['surgeon'] ?? '');
    $notes = $database->real_escape_string($_POST['notes'] ?? '');
    $database->query("UPDATE operating_rooms SET 
                      room_number='$room_number', room_name='$room_name', room_type='$room_type', 
                      status='$status', equipment_available='$equipment', nurse_assigned='$nurse',
                      anesthesiologist='$anesthesiologist', surgeon='$surgeon', notes='$notes'
                      WHERE id=$id");
    header("location: gmg_operating.php?msg=updated");
    exit();
}

// Changer le statut d'un bloc
if(isset($_GET['change_status'])){
    $id = intval($_GET['id']);
    $status = $database->real_escape_string($_GET['status']);
    $allowed = ['available', 'in_use', 'cleaning', 'maintenance', 'reserved', 'sterilization'];
    if(in_array($status, $allowed)){
        $database->query("UPDATE operating_rooms SET status='$status' WHERE id=$id");
    }
    header("location: gmg_operating.php");
    exit();
}

// Supprimer un bloc
if(isset($_GET['delete_room'])){
    $id = intval($_GET['delete_room']);
    $database->query("DELETE FROM operating_rooms WHERE id=$id");
    header("location: gmg_operating.php?msg=deleted");
    exit();
}

// Filtres pour les blocs
$filter_type = $database->real_escape_string($_POST['filter_type'] ?? '');
$filter_status = $database->real_escape_string($_POST['filter_status'] ?? '');
$where = "WHERE 1=1";
if($filter_type) $where .= " AND room_type='$filter_type'";
if($filter_status) $where .= " AND status='$filter_status'";

$rooms = $database->query("SELECT * FROM operating_rooms $where ORDER BY room_number");
$total_rooms = $database->query("SELECT COUNT(*) AS n FROM operating_rooms")->fetch_assoc()['n'] ?? 0;
$available_rooms = $database->query("SELECT COUNT(*) AS n FROM operating_rooms WHERE status='available'")->fetch_assoc()['n'] ?? 0;
$in_use = $database->query("SELECT COUNT(*) AS n FROM operating_rooms WHERE status='in_use'")->fetch_assoc()['n'] ?? 0;

// Récupérer la semaine actuelle
$week_start = isset($_GET['week_start']) ? $_GET['week_start'] : date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));

// Navigation semaines
$prev_week = date('Y-m-d', strtotime($week_start . ' -7 days'));
$next_week = date('Y-m-d', strtotime($week_start . ' +7 days'));

// Récupérer les planifications de la semaine
$schedules = $database->query("SELECT s.*, r.room_number, r.room_name,
                               u.full_name AS patient_name,
                               ud.full_name AS doctor_name
                               FROM surgery_schedule s 
                               JOIN operating_rooms r ON r.id = s.operating_room_id
                               JOIN patients p ON p.id = s.patient_id
                               JOIN users u ON u.id = p.user_id
                               JOIN doctors d ON d.id = s.doctor_id
                               JOIN users ud ON ud.id = d.user_id
                               WHERE s.scheduled_start BETWEEN '$week_start 00:00:00' AND '$week_end 23:59:59'
                               ORDER BY s.scheduled_start ASC");

// Jours de la semaine
$days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

// Types de blocs
$room_types = [
    'standard'       => 'Standard',
    'urgences'       => 'Urgences',
    'cardiologie'    => 'Cardiologie',
    'neurochirurgie' => 'Neurochirurgie',
    'orthopedie'     => 'Orthopédie',
    'pediatrique'    => 'Pédiatrique'
];

// Statuts blocs
$status_labels = [
    'available'     => ['label' => 'Disponible',   'class' => 'badge-available'],
    'in_use'        => ['label' => 'En cours',      'class' => 'badge-occupied'],
    'cleaning'      => ['label' => 'Nettoyage',     'class' => 'badge-cleaning'],
    'maintenance'   => ['label' => 'Maintenance',   'class' => 'badge-maintenance'],
    'reserved'      => ['label' => 'Réservé',       'class' => 'badge-reserved'],
    'sterilization' => ['label' => 'Stérilisation', 'class' => 'badge-sterilization']
];

// Statuts planifications
$schedule_status = [
    'scheduled'   => ['label' => 'Programmé',   'class' => 'badge-info'],
    'preparing'   => ['label' => 'Préparation', 'class' => 'badge-warning'],
    'in_progress' => ['label' => 'En cours',    'class' => 'badge-warning'],
    'paused'      => ['label' => 'Pausé',        'class' => 'badge-warning'],
    'completed'   => ['label' => 'Terminé',     'class' => 'badge-success'],
    'cancelled'   => ['label' => 'Annulé',      'class' => 'badge-danger'],
    'post_op'     => ['label' => 'Post-op',     'class' => 'badge-info']
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMG — Blocs opératoires</title>
    <link rel="stylesheet" href="gmg_style.css">
    <style>
        .badge-sterilization { background: #ede9fe; color: #5b21b6; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-info { background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-warning { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-danger { background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }

        .operating-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .operating-stat { background: #fff; border-radius: 12px; padding: 16px; text-align: center; border: 1px solid #e2e8f0; }
        .operating-stat-value { font-size: 1.8rem; font-weight: 700; color: #10b981; }
        .operating-stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; margin-top: 4px; }

        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; padding: 16px 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-select { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 0.8rem; background: #fff; cursor: pointer; }

        .week-navigation { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .week-title { font-size: 1.1rem; font-weight: 600; }
        .schedule-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; }
        .schedule-table th, .schedule-table td { border: 1px solid #e2e8f0; padding: 10px; vertical-align: top; }
        .schedule-table th { background: #f8fafc; font-weight: 600; font-size: 0.85rem; }
        .schedule-event { background: #f0fdf4; border-left: 3px solid #10b981; padding: 8px; margin-bottom: 6px; border-radius: 6px; font-size: 0.75rem; }
        .schedule-event .time { font-weight: 600; color: #10b981; }
        .schedule-event .patient { font-weight: 600; }
        .schedule-event .surgery { color: #64748b; }
        .event-actions { margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap; }

        .modal { max-width: 650px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
        .full-width { grid-column: 1 / -1; }
        .required-field { color: #ef4444; font-size: 0.7rem; margin-left: 4px; }
        .error-msg { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem; border: 1px solid #fca5a5; }

        .tab-buttons { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
        .tab-btn { padding: 8px 20px; border: none; background: none; cursor: pointer; font-size: 0.9rem; border-radius: 8px; transition: all 0.2s; }
        .tab-btn.active { background: #10b981; color: white; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
    </style>
</head>
<body>
<div class="app">
    <div class="sidebar">
        <div class="logo-area">
            <div class="logo">MED<span>UNITY</span></div>
            <div class="logo-sub">Gestion des Moyens Généraux</div>
        </div>
        <nav>
            <a href="gmg_index.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2h-5v-8H7v8H5a2 2 0 0 1-2-2z"/></svg><span>Tableau de bord</span></a>
            <a href="gmg_rooms.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg><span>Chambres & Lits</span></a>
            <a href="gmg_operating.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a5 5 0 0 0-5 5c0 2.5 2 4.5 5 7 3-2.5 5-4.5 5-7a5 5 0 0 0-5-5zm0 7a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/><path d="M8 21h8"/></svg><span>Blocs opératoires</span></a>
            <a href="gmg_stock.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-4.5M15 4H9M4 7h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"/></svg><span>Stock</span></a>
            <a href="gmg_maintenance.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.5 6.5L3 14v4h4l7.5-7.5M16 8l2-2 2 2-2 2"/></svg><span>Maintenance</span></a>
            <a href="gmg_suppliers.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M5 20v-2a7 7 0 0 1 14 0v2"/></svg><span>Fournisseurs</span></a>
            <a href="profile.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Mon profil</span></a>
        </nav>
        <div class="user-info">
            <div class="user-avatar">GM</div>
            <div class="user-details">
                <div class="user-name">Gestion Moyens</div>
                <div class="user-role">gmg@clinique.com</div>
                <a href="../logout.php" class="logout-link">Déconnexion</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">🏥 Blocs opératoires - Planification</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('planning', this)">📅 Planification hebdomadaire</button>
                <button class="tab-btn" onclick="showTab('rooms', this)">🏥 Gestion des blocs</button>
            </div>

            <?php if(isset($_GET['msg'])): ?>
                <?php if($_GET['msg'] == 'schedule_added'): ?>
                    <div class="alert alert-success">✅ Intervention programmée avec succès.</div>
                <?php elseif($_GET['msg'] == 'schedule_updated'): ?>
                    <div class="alert alert-success">✅ Planification modifiée avec succès.</div>
                <?php elseif($_GET['msg'] == 'schedule_deleted'): ?>
                    <div class="alert alert-success">✅ Intervention annulée.</div>
                <?php elseif($_GET['msg'] == 'added'): ?>
                    <div class="alert alert-success">✅ Bloc opératoire ajouté avec succès.</div>
                <?php elseif($_GET['msg'] == 'updated'): ?>
                    <div class="alert alert-success">✅ Bloc opératoire modifié avec succès.</div>
                <?php elseif($_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success">✅ Bloc opératoire supprimé.</div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Onglet Planification -->
            <div id="planning" class="tab-pane active">
                <div class="operating-stats">
                    <div class="operating-stat"><div class="operating-stat-value"><?= $total_rooms ?></div><div class="operating-stat-label">Total blocs</div></div>
                    <div class="operating-stat"><div class="operating-stat-value"><?= $available_rooms ?></div><div class="operating-stat-label">Disponibles</div></div>
                    <div class="operating-stat"><div class="operating-stat-value"><?= $in_use ?></div><div class="operating-stat-label">En cours</div></div>
                    <div class="operating-stat"><div class="operating-stat-value"><?= $schedules->num_rows ?></div><div class="operating-stat-label">Interventions semaine</div></div>
                </div>

                <div class="week-navigation">
                    <a href="?week_start=<?= $prev_week ?>" class="btn btn-secondary btn-sm">← Semaine précédente</a>
                    <div class="week-title">Semaine du <?= date('d/m/Y', strtotime($week_start)) ?> au <?= date('d/m/Y', strtotime($week_end)) ?></div>
                    <a href="?week_start=<?= $next_week ?>" class="btn btn-secondary btn-sm">Semaine suivante →</a>
                </div>

                <div style="overflow-x: auto;">
                    <table class="schedule-table">
                        <thead>
                        <tr>
                            <th style="width: 120px;">Bloc / Jour</th>
                            <?php foreach($days as $i => $day):
                                $col_date = date('d/m', strtotime($week_start . ' +' . $i . ' days')); ?>
                                <th><?= $day ?><br><span style="font-size:0.7rem;color:#64748b;"><?= $col_date ?></span></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $rooms_temp = $database->query("SELECT * FROM operating_rooms ORDER BY room_number");
                        if($rooms_temp->num_rows == 0): ?>
                            <tr><td colspan="8" class="text-center" style="padding: 60px;">Aucun bloc opératoire. <a href="?action=add" class="btn btn-primary btn-sm">+ Ajouter un bloc</a></td></tr>
                        <?php else:
                            while($room = $rooms_temp->fetch_assoc()):
                                $room_status_info = $status_labels[$room['status']] ?? ['label' => ucfirst($room['status']), 'class' => 'badge-available'];
                                ?>
                                <tr>
                                    <td style="font-weight: 600; background: #f8fafc;">
                                        <?= htmlspecialchars($room['room_number']) ?><br>
                                        <span style="font-size: 0.7rem; color: #64748b;"><?= htmlspecialchars($room['room_name']) ?></span><br>
                                        <span class="badge <?= $room_status_info['class'] ?>" style="font-size:0.6rem; margin-top:4px;"><?= $room_status_info['label'] ?></span>
                                    </td>
                                    <?php for($i = 0; $i < 7; $i++):
                                        $current_date = date('Y-m-d', strtotime($week_start . ' +' . $i . ' days'));
                                        $day_schedules = [];
                                        $schedules->data_seek(0);
                                        while($s = $schedules->fetch_assoc()){
                                            $schedule_date = date('Y-m-d', strtotime($s['scheduled_start']));
                                            if($s['operating_room_id'] == $room['id'] && $schedule_date == $current_date){
                                                $day_schedules[] = $s;
                                            }
                                        }
                                        ?>
                                        <td style="min-width: 160px;">
                                            <?php foreach($day_schedules as $s):
                                                $status_info = $schedule_status[$s['status']] ?? ['label' => 'Programmé', 'class' => 'badge-info'];
                                                ?>
                                                <div class="schedule-event">
                                                    <div class="time"><?= date('H:i', strtotime($s['scheduled_start'])) ?> - <?= date('H:i', strtotime($s['scheduled_end'])) ?></div>
                                                    <div class="patient">👤 <?= htmlspecialchars($s['patient_name']) ?></div>
                                                    <div class="surgery">🔪 <?= htmlspecialchars($s['surgery_type']) ?></div>
                                                    <div class="surgery">👨‍⚕️ <?= htmlspecialchars($s['doctor_name']) ?></div>
                                                    <div class="event-actions">
                                                        <span class="badge <?= $status_info['class'] ?>" style="font-size: 0.6rem;"><?= $status_info['label'] ?></span>
                                                        <a href="?action=edit_schedule&id=<?= $s['id'] ?>&week_start=<?= $week_start ?>" class="btn btn-soft btn-sm" style="padding: 2px 6px; font-size: 0.6rem;">✏️</a>
                                                        <a href="?delete_schedule=<?= $s['id'] ?>&week_start=<?= $week_start ?>" class="btn btn-danger btn-sm" style="padding: 2px 6px; font-size: 0.6rem;" onclick="return confirm('Annuler cette intervention ?')">🗑</a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <a href="?action=add_schedule&room_id=<?= $room['id'] ?>&date=<?= $current_date ?>&week_start=<?= $week_start ?>" class="btn btn-primary btn-sm" style="width: 100%; margin-top: 5px; padding: 4px; font-size: 0.7rem;">+ Programmer</a>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Onglet Gestion des blocs -->
            <div id="rooms" class="tab-pane">
                <div class="flex-between" style="margin-bottom: 20px;">
                    <h2 style="font-size: 1rem; font-weight: 600;">📋 Liste des blocs opératoires</h2>
                    <a href="?action=add" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Ajouter un bloc
                    </a>
                </div>

                <form method="POST" class="filter-bar">
                    <div class="filter-group">
                        <label>Type de bloc</label>
                        <select name="filter_type" class="filter-select">
                            <option value="">Tous</option>
                            <?php foreach($room_types as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $filter_type == $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Statut</label>
                        <select name="filter_status" class="filter-select">
                            <option value="">Tous</option>
                            <?php foreach($status_labels as $key => $info): ?>
                                <option value="<?= $key ?>" <?= $filter_status == $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary">🔍 Filtrer</button>
                </form>

                <div class="card">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Salle</th><th>Type</th><th>Statut</th><th>Équipements</th>
                                <th>Infirmier(ère)</th><th>Anesthésiste</th><th>Chirurgien</th><th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $rooms->data_seek(0);
                            if($rooms->num_rows == 0): ?>
                                <tr><td colspan="8" class="empty-state">Aucun bloc opératoire. <a href="?action=add" class="btn btn-primary btn-sm">+ Ajouter</a></td></tr>
                            <?php else: while($r = $rooms->fetch_assoc()):
                                $type_label = $room_types[$r['room_type']] ?? ucfirst($r['room_type']);
                                $status_info = $status_labels[$r['status']] ?? ['label' => ucfirst($r['status']), 'class' => 'badge-available'];
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['room_number']) ?></strong><br><span style="font-size:0.75rem;"><?= htmlspecialchars($r['room_name']) ?></span></td>
                                    <td><span class="badge-role"><?= $type_label ?></span></td>
                                    <td><span class="badge <?= $status_info['class'] ?>"><?= $status_info['label'] ?></span></td>
                                    <td><?= htmlspecialchars(substr($r['equipment_available'] ?? '', 0, 40)) ?></td>
                                    <td><?= htmlspecialchars($r['nurse_assigned'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($r['anesthesiologist'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($r['surgeon'] ?? '—') ?></td>
                                    <td>
                                        <div class="flex" style="gap:6px;">
                                            <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-soft btn-sm">✏️ Modifier</a>
                                            <select onchange="location='?change_status='+this.value+'&id=<?= $r['id'] ?>'" class="filter-select" style="padding:4px 8px; font-size:0.7rem;">
                                                <option value="">Changer statut</option>
                                                <?php foreach($status_labels as $key => $info): ?>
                                                    <option value="<?= $key ?>" <?= $r['status'] == $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="?delete_room=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce bloc ?')">🗑</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL : PROGRAMMER UNE INTERVENTION -->
<?php if(isset($_GET['action']) && $_GET['action'] == 'add_schedule' && isset($_GET['room_id'])):
    $room_id = intval($_GET['room_id']);
    $selected_date = $_GET['date'] ?? date('Y-m-d');
    $week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    $room_info = $database->query("SELECT * FROM operating_rooms WHERE id=$room_id")->fetch_assoc();
    ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>📅 Programmer une intervention — <?= htmlspecialchars($room_info['room_number']) ?></h2>
                <a href="gmg_operating.php?week_start=<?= $week_start ?>" class="modal-close">&times;</a>
            </div>
            <form method="POST" action="gmg_operating.php" onsubmit="return validateScheduleForm()">
                <input type="hidden" name="room_id" value="<?= $room_id ?>">
                <input type="hidden" name="week_start" value="<?= $week_start ?>">
                <div class="modal-body">

                    <?php if(isset($_GET['error'])): ?>
                        <div class="error-msg">❌ <?= htmlspecialchars(urldecode($_GET['error'])) ?></div>
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Patient <span class="required-field">*</span></label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">-- Sélectionner un patient --</option>
                                <?php $patients->data_seek(0); while($p = $patients->fetch_assoc()): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['uhid'] ?? '') ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label>Type d'intervention <span class="required-field">*</span></label>
                            <select name="surgery_type_id" id="surgery_type_select" class="form-select" onchange="toggleCustomSurgery()">
                                <option value="">-- Sélectionner un type --</option>
                                <?php $surgery_types->data_seek(0); while($st = $surgery_types->fetch_assoc()): ?>
                                    <option value="<?= $st['id'] ?>" data-duration="<?= $st['duration'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= $st['duration'] ?> min)</option>
                                <?php endwhile; ?>
                                <option value="custom">+ Autre (saisir manuellement)</option>
                            </select>
                            <input type="text" name="surgery_type_custom" id="surgery_type_custom" class="form-input" style="margin-top: 8px; display: none;" placeholder="Saisir le type d'intervention">
                        </div>

                        <div class="form-group">
                            <label>Date <span class="required-field">*</span></label>
                            <input type="date" name="scheduled_date" class="form-input" value="<?= $selected_date ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Heure de début <span class="required-field">*</span></label>
                            <input type="time" name="scheduled_time" class="form-input" value="08:00" required>
                        </div>

                        <div class="form-group">
                            <label>Durée (minutes)</label>
                            <input type="number" name="duration" id="duration" class="form-input" value="60" readonly style="background: #f1f5f9;">
                        </div>

                        <div class="form-group">
                            <label>Chirurgien <span class="required-field">*</span></label>
                            <select name="surgeon" class="form-select" required>
                                <option value="">-- Sélectionner un chirurgien --</option>
                                <?php $surgeons->data_seek(0); while($s = $surgeons->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes" class="form-textarea" rows="2" placeholder="Informations complémentaires..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="gmg_operating.php?week_start=<?= $week_start ?>" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="add_schedule" class="btn btn-primary">✅ Programmer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleCustomSurgery() {
            var select = document.getElementById('surgery_type_select');
            var customInput = document.getElementById('surgery_type_custom');
            var durationField = document.getElementById('duration');
            if(select.value == 'custom'){
                customInput.style.display = 'block';
                customInput.required = true;
                durationField.readOnly = false;
                durationField.style.background = '#fff';
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
                durationField.readOnly = true;
                durationField.style.background = '#f1f5f9';
                var selectedOption = select.options[select.selectedIndex];
                var duration = selectedOption.getAttribute('data-duration');
                if(duration) durationField.value = duration;
            }
        }

        function validateScheduleForm() {
            var patient = document.querySelector('select[name="patient_id"]').value;
            var surgeon = document.querySelector('select[name="surgeon"]').value;
            var surgerySelect = document.getElementById('surgery_type_select').value;
            var surgeryCustom = document.getElementById('surgery_type_custom').value;
            var date = document.querySelector('input[name="scheduled_date"]').value;
            var time = document.querySelector('input[name="scheduled_time"]').value;
            if(!patient){ alert("Veuillez sélectionner un patient."); return false; }
            if(!surgeon){ alert("Veuillez sélectionner un chirurgien."); return false; }
            if(!surgerySelect){ alert("Veuillez sélectionner un type d'intervention."); return false; }
            if(surgerySelect == 'custom' && !surgeryCustom){ alert("Veuillez saisir le type d'intervention."); return false; }
            if(!date){ alert("Veuillez sélectionner une date."); return false; }
            if(!time){ alert("Veuillez sélectionner une heure."); return false; }
            return true;
        }
    </script>
<?php endif; ?>

<!-- MODAL : MODIFIER LE STATUT D'UNE INTERVENTION -->
<?php if(isset($_GET['action']) && $_GET['action'] == 'edit_schedule' && isset($_GET['id'])):
    $schedule_id = intval($_GET['id']);
    $week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    $schedule = $database->query("SELECT s.*, u.full_name AS patient_name, ud.full_name AS doctor_name
                                  FROM surgery_schedule s
                                  JOIN patients p ON p.id = s.patient_id
                                  JOIN users u ON u.id = p.user_id
                                  JOIN doctors d ON d.id = s.doctor_id
                                  JOIN users ud ON ud.id = d.user_id
                                  WHERE s.id=$schedule_id")->fetch_assoc();
    if($schedule):
        ?>
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>✏️ Modifier l'intervention</h2>
                    <a href="gmg_operating.php?week_start=<?= $week_start ?>" class="modal-close">&times;</a>
                </div>
                <form method="POST" action="gmg_operating.php">
                    <input type="hidden" name="schedule_id" value="<?= $schedule_id ?>">
                    <input type="hidden" name="week_start" value="<?= $week_start ?>">
                    <div class="modal-body">
                        <div class="form-grid">
                            <div class="form-group"><label>Patient</label><input type="text" class="form-input" value="<?= htmlspecialchars($schedule['patient_name']) ?>" disabled></div>
                            <div class="form-group"><label>Chirurgien</label><input type="text" class="form-input" value="<?= htmlspecialchars($schedule['doctor_name']) ?>" disabled></div>
                            <div class="form-group"><label>Type d'intervention</label><input type="text" class="form-input" value="<?= htmlspecialchars($schedule['surgery_type']) ?>" disabled></div>
                            <div class="form-group">
                                <label>Statut <span class="required-field">*</span></label>
                                <select name="status" class="form-select" required>
                                    <?php foreach($schedule_status as $key => $info): ?>
                                        <option value="<?= $key ?>" <?= $schedule['status'] == $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label>Notes</label>
                                <textarea name="notes" class="form-textarea" rows="2"><?= htmlspecialchars($schedule['team_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="gmg_operating.php?week_start=<?= $week_start ?>" class="btn btn-secondary">Annuler</a>
                        <button type="submit" name="edit_schedule" class="btn btn-primary">💾 Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; endif; ?>

<!-- MODAL : AJOUTER / MODIFIER UN BLOC -->
<?php if(isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'edit')):
    $is_edit = ($_GET['action'] == 'edit' && isset($_GET['id']));
    $room = null;
    if($is_edit){
        $id = intval($_GET['id']);
        $room = $database->query("SELECT * FROM operating_rooms WHERE id=$id")->fetch_assoc();
    }
    ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2><?= $is_edit ? '✏️ Modifier le bloc' : '➕ Ajouter un bloc opératoire' ?></h2>
                <a href="gmg_operating.php" class="modal-close">&times;</a>
            </div>
            <form method="POST" action="gmg_operating.php">
                <?php if($is_edit): ?><input type="hidden" name="room_id" value="<?= $room['id'] ?>"><?php endif; ?>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Numéro de salle <span class="required">*</span></label>
                            <input type="text" name="room_number" class="form-input" value="<?= $is_edit ? htmlspecialchars($room['room_number']) : '' ?>" placeholder="Ex: OR-01" required>
                        </div>
                        <div class="form-group">
                            <label>Nom de la salle</label>
                            <input type="text" name="room_name" class="form-input" value="<?= $is_edit ? htmlspecialchars($room['room_name']) : '' ?>" placeholder="Ex: Bloc A - Salle 1">
                        </div>
                        <div class="form-group">
                            <label>Type de bloc</label>
                            <select name="room_type" class="form-select">
                                <?php foreach($room_types as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $is_edit && $room['room_type'] == $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if($is_edit): ?>
                            <div class="form-group">
                                <label>Statut</label>
                                <select name="status" class="form-select">
                                    <?php foreach($status_labels as $key => $info): ?>
                                        <option value="<?= $key ?>" <?= $room['status'] == $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group full-width">
                            <label>Équipements disponibles</label>
                            <textarea name="equipment_available" class="form-textarea" rows="2" placeholder="Arthroscope, C-arm, Monitor..."><?= $is_edit ? htmlspecialchars($room['equipment_available'] ?? '') : '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Infirmier(ère) assigné(e)</label>
                            <select name="nurse_assigned" class="form-select">
                                <option value="">-- Non assigné --</option>
                                <?php $nurses->data_seek(0); while($n = $nurses->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($n['full_name']) ?>" <?= $is_edit && $room['nurse_assigned'] == $n['full_name'] ? 'selected' : '' ?>><?= htmlspecialchars($n['full_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Anesthésiste assigné(e)</label>
                            <select name="anesthesiologist" class="form-select">
                                <option value="">-- Non assigné --</option>
                                <?php $anesthesiologists->data_seek(0); while($a = $anesthesiologists->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($a['full_name']) ?>" <?= $is_edit && $room['anesthesiologist'] == $a['full_name'] ? 'selected' : '' ?>><?= htmlspecialchars($a['full_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Chirurgien assigné(e)</label>
                            <select name="surgeon" class="form-select">
                                <option value="">-- Non assigné --</option>
                                <?php $surgeons->data_seek(0); while($s = $surgeons->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($s['full_name']) ?>" <?= $is_edit && $room['surgeon'] == $s['full_name'] ? 'selected' : '' ?>><?= htmlspecialchars($s['full_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes" class="form-textarea" rows="2"><?= $is_edit ? htmlspecialchars($room['notes'] ?? '') : '' ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="gmg_operating.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="<?= $is_edit ? 'edit_operating_room' : 'add_operating_room' ?>" class="btn btn-primary"><?= $is_edit ? '💾 Enregistrer' : '✅ Ajouter' ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function showTab(tabId, btn) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        btn.classList.add('active');
    }
</script>

</body>
</html>