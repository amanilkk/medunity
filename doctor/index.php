<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
require_once 'doctor_functions.php';
requireDoctor();
include '../connection.php';

$doctor    = getCurrentDoctor($database);
$doctor_id = $doctor['doctor_id'];
$stats     = getDoctorDashboardStats($database, $doctor_id);
$upcoming  = getUpcomingAppointments($database, $doctor_id, 6);

// Résultats labo urgents
$urgent_labs = $database->query("
    SELECT l.id, l.test_name, l.result, l.priority, l.updated_at, l.patient_id,
           u.full_name AS patient_name
    FROM lab_tests l
    JOIN patients p ON p.id = l.patient_id
    JOIN users u ON u.id = p.user_id
    WHERE l.doctor_id = $doctor_id
      AND l.status = 'completed'
      AND (l.is_critical = 1 OR l.priority = 'critical')
    ORDER BY l.updated_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$prenom = explode(' ', $doctor['full_name'])[0];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Dr. <?= htmlspecialchars($prenom) ?></title>
    <link rel="stylesheet" href="doctor.css">
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">Bonjour, Dr. <?= htmlspecialchars($prenom) ?> 👋</span>
            <div class="topbar-right">
                <span class="date-tag"><?= date('l d F Y') ?></span>
                <a href="lab-requests.php" class="btn btn-primary btn-sm">+ Nouvelle analyse</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue">📅</div>
                    <div class="stat-info">
                        <h3><?= $stats['today_appointments'] ?></h3>
                        <p>RDV aujourd'hui</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-purple">⏳</div>
                    <div class="stat-info">
                        <h3><?= $stats['pending_appointments'] ?></h3>
                        <p>En attente</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-green">👥</div>
                    <div class="stat-info">
                        <h3><?= $stats['total_patients'] ?></h3>
                        <p>Patients suivis</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-red">🚨</div>
                    <div class="stat-info">
                        <h3><?= count($urgent_labs) ?></h3>
                        <p>Alertes labo</p>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem">

                <!-- File d'attente -->
                <div class="card">
                    <div class="card-head">
                        <h3>📋 Prochains rendez-vous</h3>
                        <a href="appointments.php" class="btn btn-secondary btn-sm">Voir tout</a>
                    </div>
                    <div class="card-body" style="padding:0">
                        <?php if (empty($upcoming)): ?>
                            <div class="empty-state"><p>Aucun rendez-vous à venir.</p></div>
                        <?php else: ?>
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Heure</th>
                                    <th>Statut</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming as $a): ?>
                                <tr>
                                    <td>
                                        <div class="flex-center">
                                            <div class="patient-initials"><?= strtoupper(substr($a['patient_name'],0,2)) ?></div>
                                            <div>
                                                <div class="text-bold"><?= htmlspecialchars($a['patient_name']) ?></div>
                                                <div class="text-small text-muted"><?= $a['uhid'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($a['appointment_date'])) ?></td>
                                    <td><span class="badge badge-info"><?= substr($a['appointment_time'],0,5) ?></span></td>
                                    <td><span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                                    <td>
                                        <a href="patient-profile.php?id=<?= $a['patient_id'] ?>" class="btn btn-primary btn-sm">Dossier</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alertes labo -->
                <div class="card">
                    <div class="card-head">
                        <h3 style="color:var(--danger)">🚨 Résultats critiques</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($urgent_labs)): ?>
                            <div class="empty-state"><p>Aucune alerte critique.</p></div>
                        <?php else: ?>
                            <?php foreach ($urgent_labs as $lab): ?>
                            <div style="padding:12px;background:var(--danger-light);border-radius:12px;margin-bottom:10px;border-left:3px solid var(--danger)">
                                <div style="font-weight:700;color:#991b1b;font-size:0.85rem"><?= htmlspecialchars($lab['patient_name']) ?></div>
                                <div style="font-size:0.8rem;margin-top:2px"><?= htmlspecialchars($lab['test_name']) ?>
                                    <?php if ($lab['result']): ?>
                                        : <strong style="color:var(--danger)"><?= htmlspecialchars($lab['result']) ?></strong>
                                    <?php endif; ?>
                                </div>
                                <a href="patient-profile.php?id=<?= $lab['patient_id'] ?>" style="font-size:0.75rem;color:var(--primary);font-weight:600;text-decoration:none">Voir dossier →</a>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Raccourcis rapides -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:4px">
                <a href="diagnosis.php"      class="btn btn-secondary" style="justify-content:center;padding:14px">📋 Nouveau diagnostic</a>
                <a href="prescription.php"   class="btn btn-secondary" style="justify-content:center;padding:14px">💊 Ordonnance</a>
                <a href="medical-notes.php"  class="btn btn-secondary" style="justify-content:center;padding:14px">📝 Note médicale</a>
                <a href="lab-requests.php"   class="btn btn-secondary" style="justify-content:center;padding:14px">🧪 Demande labo</a>
            </div>

        </div>
    </div>
</body>
</html>
