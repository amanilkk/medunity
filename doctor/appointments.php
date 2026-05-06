<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
require_once 'doctor_functions.php';
requireDoctor();
include '../connection.php';

$doctor = getCurrentDoctor($database);
$doctor_id = $doctor['doctor_id'];

// Récupération des filtres
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT a.*, u.full_name, p.uhid, p.id as patient_id 
        FROM appointments a 
        JOIN patients p ON p.id = a.patient_id 
        JOIN users u ON u.id = p.user_id 
        WHERE a.doctor_id = ?";

if ($status_filter) $sql .= " AND a.status = '$status_filter'";
if ($search) $sql .= " AND (u.full_name LIKE '%$search%' OR p.uhid LIKE '%$search%')";

$sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$stmt = $database->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statusLabels = ['pending'=>'En attente', 'confirmed'=>'Confirmé', 'completed'=>'Terminé', 'cancelled'=>'Annulé'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planning — Dr. <?= explode(' ', $doctor['full_name'])[0] ?></title>
    <link rel="stylesheet" href="doctor.css">
</head>
<body>
    <?php include 'doctor_menu.php'; ?>
    <div class="main">
        <div class="topbar">
            <span class="topbar-title">📅 Mon Planning</span>
            <div class="topbar-right">
                <form method="GET" style="display:flex; gap:10px;">
                    <input type="text" name="search" class="input" placeholder="Chercher patient..." value="<?= htmlspecialchars($search) ?>" style="width:200px">
                    <select name="status" class="input" onchange="this.form.submit()">
                        <option value="">Tous les statuts</option>
                        <?php foreach($statusLabels as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $status_filter===$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <div class="page-body">
            <div class="card">
                <div class="card-body" style="padding:0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date & Heure</th>
                                <th>Patient</th>
                                <th>Motif</th>
                                <th>Statut</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $app): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600"><?= date('d M Y', strtotime($app['appointment_date'])) ?></div>
                                    <div class="text-small text-muted"><?= substr($app['appointment_time'], 0, 5) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600"><?= htmlspecialchars($app['full_name']) ?></div>
                                    <div class="text-small text-muted">ID: <?= $app['uhid'] ?></div>
                                </td>
                                <td class="text-small"><?= htmlspecialchars($app['reason'] ?? 'Consultation') ?></td>
                                <td>
                                    <span class="badge badge-<?= $app['status'] ?>">
                                        <?= $statusLabels[$app['status']] ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a href="patient-profile.php?id=<?= $app['patient_id'] ?>" class="btn btn-primary btn-sm">Ouvrir Dossier</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if(empty($appointments)): ?>
                        <div class="empty-state"><p>Aucun rendez-vous trouvé.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
</body>
</html>