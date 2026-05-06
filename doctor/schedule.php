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

// Semaine courante
$week_offset = (int)($_GET['week'] ?? 0);
$monday = new DateTime('monday this week');
$monday->modify("$week_offset weeks");
$sunday = clone $monday;
$sunday->modify('+6 days');

// Rendez-vous de la semaine
$stmt = $database->prepare("
    SELECT a.appointment_date, a.appointment_time, a.status, a.reason, a.type,
           u.full_name AS patient_name, p.id AS patient_id
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN users u ON u.id = p.user_id
    WHERE a.doctor_id = ?
      AND a.appointment_date BETWEEN ? AND ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$mon_str = $monday->format('Y-m-d');
$sun_str = $sunday->format('Y-m-d');
$stmt->bind_param("iss", $doctor_id, $mon_str, $sun_str);
$stmt->execute();
$week_appts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Organiser par jour
$by_day = [];
foreach ($week_appts as $a) {
    $by_day[$a['appointment_date']][] = $a;
}

// Sessions planifiées — vraies colonnes : day_of_week, start_time, end_time, type_session, status, max_patients
$schedules = $database->query("
    SELECT * FROM schedules 
    WHERE doctor_id = $doctor_id 
    ORDER BY day_of_week ASC, start_time ASC
")->fetch_all(MYSQLI_ASSOC);

$today = date('Y-m-d');
$days_fr = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Planning</title>
    <link rel="stylesheet" href="doctor.css">
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">📆 Mon Planning</span>
            <div class="topbar-right">
                <a href="?week=<?= $week_offset - 1 ?>" class="btn btn-secondary btn-sm">← Préc.</a>
                <?php if ($week_offset !== 0): ?>
                    <a href="?week=0" class="btn btn-secondary btn-sm">Aujourd'hui</a>
                <?php endif; ?>
                <a href="?week=<?= $week_offset + 1 ?>" class="btn btn-secondary btn-sm">Suiv. →</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Vue semaine -->
            <div class="card">
                <div class="card-head">
                    <h3>📅 Semaine du <?= $monday->format('d/m') ?> au <?= $sunday->format('d/m/Y') ?></h3>
                    <span class="text-muted text-small"><?= count($week_appts) ?> rendez-vous cette semaine</span>
                </div>
                <div class="card-body">
                    <div class="schedule-week">
                        <?php
                        $cur = clone $monday;
                        for ($i = 0; $i < 7; $i++):
                            $d = $cur->format('Y-m-d');
                            $is_today = ($d === $today);
                            $day_appts = $by_day[$d] ?? [];
                        ?>
                        <div class="day-col" <?= $is_today ? 'style="border-color:var(--primary)"' : '' ?>>
                            <div class="day-head <?= $is_today ? 'today' : '' ?>">
                                <div><?= $days_fr[$i] ?></div>
                                <div style="font-size:1rem;font-weight:700;<?= $is_today ? '' : 'color:var(--text)' ?>"><?= $cur->format('d') ?></div>
                            </div>
                            <div class="day-slots">
                                <?php if (empty($day_appts)): ?>
                                    <div style="text-align:center;padding:12px 4px;font-size:0.7rem;color:var(--text-light)">—</div>
                                <?php else: ?>
                                    <?php foreach ($day_appts as $a): ?>
                                    <a href="patient-profile.php?id=<?= $a['patient_id'] ?>" style="text-decoration:none;display:block">
                                        <div class="slot-item" title="<?= htmlspecialchars($a['patient_name']) ?>">
                                            <?= substr($a['appointment_time'], 0, 5) ?><br>
                                            <span style="font-weight:400;font-size:0.68rem;overflow:hidden;white-space:nowrap;display:block;text-overflow:ellipsis">
                                                <?= htmlspecialchars($a['patient_name']) ?>
                                            </span>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php $cur->modify('+1 day'); endfor; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($schedules)): ?>
            <div class="card">
                <div class="card-head"><h3>🗓 Sessions programmées</h3></div>
                <div class="card-body">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Jour</th>
                                <th>Début</th>
                                <th>Fin</th>
                                <th>Type</th>
                                <th>Max patients</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $jours = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
                            $typeLabels = [
                                'consultation' => 'Consultation',
                                'urgence'      => 'Urgence',
                                'chirurgie'    => 'Chirurgie',
                                'garde'        => 'Garde',
                            ];
                            foreach ($schedules as $s):
                            ?>
                            <tr>
                                <td><strong><?= $jours[(int)$s['day_of_week']] ?? '—' ?></strong></td>
                                <td><?= substr($s['start_time'], 0, 5) ?></td>
                                <td><?= substr($s['end_time'],   0, 5) ?></td>
                                <td><?= htmlspecialchars($typeLabels[$s['type_session']] ?? ucfirst($s['type_session'])) ?></td>
                                <td><?= (int)$s['max_patients'] ?> patients</td>
                                <td>
                                    <?php $st = $s['status'] ?? 'actif'; ?>
                                    <span class="badge <?= $st === 'actif' ? 'badge-confirmed' : ($st === 'annule' ? 'badge-cancelled' : 'badge-pending') ?>">
                                        <?= ucfirst($st) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
