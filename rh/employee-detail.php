<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

// Vérifier que l'utilisateur est RH
if ($_SESSION['role'] !== 'gestionnaire_rh') {
    header('Location: ../login.php');
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    header('Location: employees.php');
    exit;
}

$message = '';
$error = '';

// === RÉCUPÉRATION DES DONNÉES EMPLOYÉ ===
$stmt = $database->prepare("
    SELECT u.*, r.role_name 
    FROM users u 
    INNER JOIN roles r ON r.id = u.role_id 
    WHERE u.id = ? AND u.role_id NOT IN (1, 3)
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    header('Location: employees.php');
    exit;
}

// === RÉCUPÉRATION DES DONNÉES ASSOCIÉES ===

// Contrat actif
$active_contract = getActiveContract($database, $user_id);

// Historique des contrats
$contracts_history = getEmployeeContracts($database, $user_id);

// Affectation actuelle
$current_assignment = getCurrentAssignment($database, $user_id);

// Historique des affectations
$assignments_history = getEmployeeAssignments($database, $user_id);

// Présences du mois en cours
$current_month = date('m');
$current_year = date('Y');
$attendances = getMonthlyAttendance($database, $user_id, $current_year, $current_month);

// Statistiques de présence
$attendance_stats = getAttendanceStatsForEmployee($database, $user_id, $current_year, $current_month);

// Congés de l'année
$leaves = getEmployeeLeaveRequests($database, $user_id);
$remaining_days = getRemainingLeaveDays($database, $user_id, date('Y'));

// Documents
$documents = getEmployeeDocuments($database, $user_id);

// Planning du mois
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');
$schedules = getEmployeeSchedule($database, $user_id, $start_date, $end_date);

// Statistiques globales
$total_presence = safeCount($database, "SELECT COUNT(*) c FROM attendances WHERE user_id = ? AND status = 'present'", 'i', [$user_id]);
$total_absences = safeCount($database, "SELECT COUNT(*) c FROM attendances WHERE user_id = ? AND status IN ('absent', 'late')", 'i', [$user_id]);
$total_leaves = safeCount($database, "SELECT COUNT(*) c FROM leave_requests WHERE user_id = ? AND status = 'approved'", 'i', [$user_id]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($employee['full_name']); ?> — Fiche employé</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .profile-header {
            display: flex;
            gap: 25px;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: var(--green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
        }
        .profile-info {
            flex: 1;
        }
        .profile-info h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        .profile-info .role {
            color: var(--green);
            font-weight: 600;
            margin-bottom: 10px;
        }
        .profile-info .contact {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 0.85rem;
            color: var(--text2);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 13px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 15px;
            text-align: center;
            box-shadow: var(--sh);
        }
        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 5px;
        }
        .stat-card.green .value { color: var(--green); }
        .stat-card.red .value { color: var(--red); }
        .stat-card.blue .value { color: var(--blue); }
        .stat-card.amber .value { color: var(--amber); }

        .info-section {
            margin-bottom: 25px;
        }
        .info-section h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--green);
            color: var(--green);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: var(--surf2);
            border-radius: var(--rs);
            padding: 12px 15px;
        }
        .info-item .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-item .value {
            font-size: 0.9rem;
            font-weight: 500;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-active { background: var(--green-l); color: var(--green); }
        .status-inactive { background: var(--red-l); color: var(--red); }
        .attendance-bar {
            background: var(--surf2);
            border-radius: var(--rs);
            overflow: hidden;
            margin: 10px 0;
        }
        .attendance-bar-fill {
            background: var(--green);
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .schedule-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        .schedule-item:last-child {
            border-bottom: none;
        }
        .schedule-date {
            font-weight: 600;
            min-width: 100px;
        }
        .shift-badge-sm {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 15px;
            font-size: 0.65rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Fiche employé</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="employees.php" class="btn btn-secondary btn-sm">
                ← Retour à la liste
            </a>
        </div>
    </div>
    <div class="page-body">

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- En-tête du profil -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($employee['full_name']); ?></h1>
                <div class="role"><?php echo htmlspecialchars($employee['role_name']); ?></div>
                <div class="contact">
                    <span>📧 <?php echo htmlspecialchars($employee['email']); ?></span>
                    <span>📱 <?php echo htmlspecialchars($employee['phone'] ?? 'Non renseigné'); ?></span>
                    <span>🆔 ID: <?php echo $employee['id']; ?></span>
                    <span>
                        <span class="status-badge <?php echo $employee['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $employee['is_active'] ? 'Actif' : 'Inactif'; ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="action-buttons">
            <a href="contract-form.php?user_id=<?php echo $user_id; ?>" class="btn btn-primary btn-sm">
                📄 Gérer contrat
            </a>
            <a href="assignments.php?user_id=<?php echo $user_id; ?>" class="btn btn-blue btn-sm">
                🏢 Affectation
            </a>
            <a href="attendance.php?user=<?php echo $user_id; ?>" class="btn btn-secondary btn-sm">
                📊 Voir présences
            </a>
            <a href="documents.php?user_id=<?php echo $user_id; ?>" class="btn btn-secondary btn-sm">
                📁 Documents
            </a>
            <a href="planning.php?user=<?php echo $user_id; ?>" class="btn btn-secondary btn-sm">
                📅 Planning
            </a>
        </div>

        <!-- Statistiques rapides -->
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="value"><?php echo $total_presence; ?></div>
                <div class="label">Jours présents</div>
            </div>
            <div class="stat-card red">
                <div class="value"><?php echo $total_absences; ?></div>
                <div class="label">Absences/Retards</div>
            </div>
            <div class="stat-card blue">
                <div class="value"><?php echo $total_leaves; ?></div>
                <div class="label">Congés pris</div>
            </div>
            <div class="stat-card amber">
                <div class="value"><?php echo $remaining_days; ?></div>
                <div class="label">Jours restants</div>
            </div>
        </div>

        <!-- Deux colonnes principales -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 22px;">

            <!-- Colonne gauche -->
            <div>
                <!-- Contrat actuel -->
                <div class="card">
                    <div class="card-head">
                        <h3>📄 Contrat actuel</h3>
                        <a href="contract-form.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                    </div>
                    <div class="card-body">
                        <?php if ($active_contract): ?>
                            <div class="info-item" style="margin-bottom: 10px;">
                                <div class="label">Type de contrat</div>
                                <div class="value">
                                    <span class="badge badge-<?php echo strtolower($active_contract['contract_type']); ?>">
                                        <?php echo $active_contract['contract_type']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item" style="margin-bottom: 10px;">
                                <div class="label">Salaire mensuel</div>
                                <div class="value" style="color: var(--green); font-weight: 700;">
                                    <?php echo number_format($active_contract['salary'], 0, ',', ' '); ?> DA
                                </div>
                            </div>
                            <div class="info-item" style="margin-bottom: 10px;">
                                <div class="label">Poste</div>
                                <div class="value"><?php echo htmlspecialchars($active_contract['position'] ?? '—'); ?></div>
                            </div>
                            <div class="info-item" style="margin-bottom: 10px;">
                                <div class="label">Département</div>
                                <div class="value"><?php echo htmlspecialchars($active_contract['department'] ?? '—'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="label">Période</div>
                                <div class="value">
                                    <?php echo date('d/m/Y', strtotime($active_contract['start_date'])); ?>
                                    →
                                    <?php echo $active_contract['end_date'] ? date('d/m/Y', strtotime($active_contract['end_date'])) : 'Aujourd\'hui'; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty" style="padding: 20px;">
                                <p>Aucun contrat actif</p>
                                <a href="contract-form.php?user_id=<?php echo $user_id; ?>" class="btn btn-primary btn-sm">Ajouter un contrat</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Affectation actuelle -->
                <div class="card">
                    <div class="card-head">
                        <h3>🏢 Affectation</h3>
                        <a href="assignments.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                    </div>
                    <div class="card-body">
                        <?php if ($current_assignment): ?>
                            <div class="info-item" style="margin-bottom: 10px;">
                                <div class="label">Département</div>
                                <div class="value"><?php echo htmlspecialchars($current_assignment['department_name']); ?></div>
                            </div>
                            <div class="info-item" style="margin-bottom: 10px;">
                                <div class="label">Poste</div>
                                <div class="value"><?php echo htmlspecialchars($current_assignment['position']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="label">Depuis le</div>
                                <div class="value"><?php echo date('d/m/Y', strtotime($current_assignment['start_date'])); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="empty" style="padding: 20px;">
                                <p>Aucune affectation en cours</p>
                                <a href="assignments.php?user_id=<?php echo $user_id; ?>" class="btn btn-primary btn-sm">Ajouter une affectation</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Présences du mois -->
                <div class="card">
                    <div class="card-head">
                        <h3>📊 Présences - <?php echo date('F Y'); ?></h3>
                        <a href="attendances.php?user=<?php echo $user_id; ?>" class="btn btn-sm btn-secondary">Détail</a>
                    </div>
                    <div class="card-body">
                        <?php if ($attendance_stats): ?>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; margin-bottom: 15px;">
                                <div>
                                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--green);"><?php echo $attendance_stats['present_days'] ?? 0; ?></div>
                                    <div style="font-size: 0.65rem;">Présents</div>
                                </div>
                                <div>
                                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--red);"><?php echo ($attendance_stats['absent_days'] ?? 0) + ($attendance_stats['late_days'] ?? 0); ?></div>
                                    <div style="font-size: 0.65rem;">Absences/Retards</div>
                                </div>
                                <div>
                                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--blue);"><?php echo number_format($attendance_stats['total_hours'] ?? 0, 1); ?>h</div>
                                    <div style="font-size: 0.65rem;">Heures travaillées</div>
                                </div>
                            </div>
                            <div class="attendance-bar">
                                <?php
                                $total_days = ($attendance_stats['present_days'] ?? 0) + ($attendance_stats['absent_days'] ?? 0) + ($attendance_stats['late_days'] ?? 0) + ($attendance_stats['excused_days'] ?? 0);
                                $present_percent = $total_days > 0 ? (($attendance_stats['present_days'] ?? 0) / $total_days) * 100 : 0;
                                ?>
                                <div class="attendance-bar-fill" style="width: <?php echo $present_percent; ?>%;">
                                    <?php echo round($present_percent); ?>%
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty" style="padding: 20px;">
                                <p>Aucune donnée de présence pour ce mois</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonne droite -->
            <div>
                <!-- Planning du mois -->
                <div class="card">
                    <div class="card-head">
                        <h3>📅 Planning du mois</h3>
                        <a href="planning.php?user=<?php echo $user_id; ?>" class="btn btn-sm btn-secondary">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($schedules)): ?>
                            <?php
                            $display_schedules = array_slice($schedules, 0, 5);
                            foreach ($display_schedules as $schedule):
                                $shift_class = 'shift-' . $schedule['shift_type'];
                                $shift_labels = ['matin' => '🌅 Matin', 'soir' => '🌙 Soir', 'nuit' => '🌃 Nuit', 'garde' => '🚨 Garde', 'urgence' => '⚠️ Urgence'];
                                ?>
                                <div class="schedule-item">
                                    <div class="schedule-date"><?php echo date('d/m/Y', strtotime($schedule['planning_date'])); ?></div>
                                    <div>
                                        <span class="shift-badge-sm <?php echo $shift_class; ?>">
                                            <?php echo $shift_labels[$schedule['shift_type']] ?? $schedule['shift_type']; ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 0.7rem; color: var(--text2);">
                                        <?php echo substr($schedule['shift_start'], 0, 5); ?> - <?php echo substr($schedule['shift_end'], 0, 5); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($schedules) > 5): ?>
                                <div style="text-align: center; margin-top: 10px;">
                                    <small style="color: var(--text2);">+<?php echo count($schedules) - 5; ?> autres jours</small>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty" style="padding: 20px;">
                                <p>Aucun planning pour ce mois</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Congés récents -->
                <div class="card">
                    <div class="card-head">
                        <h3>🏖️ Congés (<?php echo date('Y'); ?>)</h3>
                        <a href="leaves.php?user=<?php echo $user_id; ?>" class="btn btn-sm btn-secondary">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($leaves)): ?>
                            <?php
                            $display_leaves = array_slice($leaves, 0, 3);
                            foreach ($display_leaves as $leave):
                                $status_class = $leave['status'] == 'approved' ? 'badge-paid' : ($leave['status'] == 'pending' ? 'badge-pending' : 'badge-cancelled');
                                $status_label = $leave['status'] == 'approved' ? 'Approuvé' : ($leave['status'] == 'pending' ? 'En attente' : 'Rejeté');
                                ?>
                                <div class="schedule-item">
                                    <div>
                                        <div style="font-weight: 600;">
                                            <?php echo ucfirst(str_replace('_', ' ', $leave['leave_type'])); ?>
                                        </div>
                                        <div style="font-size: 0.7rem; color: var(--text2);">
                                            <?php echo date('d/m/Y', strtotime($leave['start_date'])); ?> → <?php echo date('d/m/Y', strtotime($leave['end_date'])); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty" style="padding: 20px;">
                                <p>Aucune demande de congé</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Documents récents -->
                <div class="card">
                    <div class="card-head">
                        <h3>📄 Documents récents</h3>
                        <a href="documents.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-secondary">Gérer</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($documents)): ?>
                            <?php
                            $display_docs = array_slice($documents, 0, 3);
                            foreach ($display_docs as $doc):
                                $doc_icons = ['cv' => '📄', 'diploma' => '🎓', 'certificate' => '🏅', 'id_card' => '🪪', 'contract' => '📑', 'other' => '📎'];
                                $icon = $doc_icons[$doc['document_type']] ?? '📄';
                                ?>
                                <div class="schedule-item">
                                    <div>
                                        <div style="font-weight: 600;"><?php echo $icon . ' ' . htmlspecialchars($doc['document_name']); ?></div>
                                        <div style="font-size: 0.65rem; color: var(--text2);">
                                            <?php echo date('d/m/Y', strtotime($doc['uploaded_at'])); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-blue" target="_blank">Voir</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty" style="padding: 20px;">
                                <p>Aucun document</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique des contrats -->
        <?php if (count($contracts_history) > 1): ?>
            <div class="card">
                <div class="card-head">
                    <h3>📜 Historique des contrats</h3>
                </div>
                <div class="card-body">
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Type</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Salaire</th>
                            <th>Poste</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($contracts_history as $contract): ?>
                            <tr>
                                <td><?php echo $contract['contract_type']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($contract['start_date'])); ?></td>
                                <td><?php echo $contract['end_date'] ? date('d/m/Y', strtotime($contract['end_date'])) : 'En cours'; ?></td>
                                <td><?php echo number_format($contract['salary'], 0, ',', ' '); ?> DA</td>
                                <td><?php echo htmlspecialchars($contract['position'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Historique des affectations -->
        <?php if (count($assignments_history) > 1): ?>
            <div class="card">
                <div class="card-head">
                    <h3>🏢 Historique des affectations</h3>
                </div>
                <div class="card-body">
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Département</th>
                            <th>Poste</th>
                            <th>Début</th>
                            <th>Fin</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assignments_history as $assignment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['position']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($assignment['start_date'])); ?></td>
                                <td><?php echo $assignment['end_date'] ? date('d/m/Y', strtotime($assignment['end_date'])) : 'En cours'; ?></td>
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