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

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// === STATISTIQUES ===
// Employés actifs
$stat_employees = safeCount($database,
        "SELECT COUNT(*) c FROM users WHERE role_id = 8 AND is_active = 1",
        '');

// Présences d'aujourd'hui
$stat_present = safeCount($database,
        "SELECT COUNT(*) c FROM attendances WHERE attendance_date = ? AND status = 'present'",
        's', [$today]);

// Absences d'aujourd'hui
$stat_absent = safeCount($database,
        "SELECT COUNT(*) c FROM attendances WHERE attendance_date = ? AND status IN ('absent', 'late')",
        's', [$today]);

// Congés en attente
$stat_leaves_pending = safeCount($database,
        "SELECT COUNT(*) c FROM leave_requests WHERE status = 'pending'",
        '');

// Contrats expirant ce mois
$stat_contracts_expiring = safeCount($database,
        "SELECT COUNT(*) c FROM employee_contracts WHERE end_date BETWEEN ? AND ? AND end_date IS NOT NULL",
        'ss', [$month_start, $month_end]);

// === PRÉSENCES DU JOUR ===
$stmt = $database->prepare(
        "SELECT a.*, u.full_name, u.phone
     FROM attendances a
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.attendance_date = ?
     ORDER BY u.full_name ASC
     LIMIT 20"
);
$attendances = null;
$db_error = '';
if ($stmt) {
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $attendances = $stmt->get_result();
} else {
    $db_error = $database->error;
}

// === CONGÉS EN ATTENTE ===
$stmt = $database->prepare(
        "SELECT lr.*, u.full_name
     FROM leave_requests lr
     INNER JOIN users u ON u.id = lr.user_id
     WHERE lr.status = 'pending'
     ORDER BY lr.created_at DESC
     LIMIT 10"
);
$leaves = null;
if ($stmt) {
    $stmt->execute();
    $leaves = $stmt->get_result();
} else {
    $db_error = $database->error;
}

// === ALERTES RH ===
$show_read = isset($_GET['show_read']) ? intval($_GET['show_read']) : 0;

if ($show_read == 1) {
    // Afficher les alertes lues
    $stmt = $database->prepare(
            "SELECT * FROM rh_alerts
         WHERE is_read = 1
         ORDER BY created_at DESC
         LIMIT 20"
    );
} else {
    // Afficher les alertes non lues
    $stmt = $database->prepare(
            "SELECT * FROM rh_alerts
         WHERE is_read = 0
         ORDER BY created_at DESC
         LIMIT 10"
    );
}
$alerts = null;
if ($stmt) {
    $stmt->execute();
    $alerts = $stmt->get_result();
} else {
    $db_error = $database->error;
}

// Compter le nombre d'alertes non lues
$unread_count = safeCount($database, "SELECT COUNT(*) c FROM rh_alerts WHERE is_read = 0", '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard — Ressources Humaines</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        /* ===== STYLES POUR LES ALERTES RH ===== */
        .alerts-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .alert-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 16px;
            background: var(--surface);
            border-radius: var(--rs);
            border-left: 4px solid;
            transition: all 0.2s ease;
            box-shadow: var(--sh);
        }

        .alert-item:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Icône d'alerte */
        .alert-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.2rem;
        }

        /* Types d'alertes */
        .alert-item.contract_expiry {
            border-left-color: var(--orange);
        }
        .alert-item.contract_expiry .alert-icon {
            background: var(--orange-l);
            color: var(--orange);
        }

        .alert-item.leave_request {
            border-left-color: var(--blue);
        }
        .alert-item.leave_request .alert-icon {
            background: var(--blue-l);
            color: var(--blue);
        }

        .alert-item.absence {
            border-left-color: var(--red);
        }
        .alert-item.absence .alert-icon {
            background: var(--red-l);
            color: var(--red);
        }

        .alert-item.document_missing {
            border-left-color: var(--amber);
        }
        .alert-item.document_missing .alert-icon {
            background: var(--amber-l);
            color: var(--amber);
        }

        /* Contenu de l'alerte */
        .alert-content {
            flex: 1;
        }

        .alert-message {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text);
            line-height: 1.4;
        }

        .alert-time {
            font-size: 0.65rem;
            color: var(--text2);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .alert-time::before {
            content: "🕐";
            font-size: 0.6rem;
        }

        /* Actions de l'alerte */
        .alert-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-mark-read {
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--rs);
            padding: 5px 10px;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text2);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            display: inline-block;
        }

        .btn-mark-read:hover {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }

        /* En-tête des alertes */
        .alerts-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .alerts-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alerts-title h3 {
            font-size: 0.92rem;
            font-weight: 600;
            margin: 0;
        }

        .alerts-badge {
            background: var(--red);
            color: white;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .alerts-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-mark-all {
            background: none;
            border: none;
            font-size: 0.7rem;
            color: var(--blue);
            cursor: pointer;
            text-decoration: underline;
            padding: 5px 10px;
        }

        .btn-mark-all:hover {
            color: var(--green);
        }

        .btn-toggle-read {
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--rs);
            padding: 5px 10px;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text2);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            display: inline-block;
        }

        .btn-toggle-read:hover {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }

        /* Animation de pulsation pour les nouvelles alertes */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .alert-item.new {
            animation: pulse 1s ease-in-out;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .alert-item {
                flex-direction: column;
            }
            .alert-actions {
                align-self: flex-end;
                margin-top: 8px;
            }
            .alerts-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Styles pour les boutons existants */
        .btn-xs {
            padding: 4px 8px;
            font-size: 0.7rem;
        }
        .btn-blue {
            background: var(--blue-l);
            color: var(--blue);
            border: 1px solid #C5DEF5;
        }
        .btn-blue:hover {
            background: #C5DEF5;
        }
        .btn-red {
            background: var(--red-l);
            color: var(--red);
            border: 1px solid #FADBD8;
        }
        .btn-red:hover {
            background: #FADBD8;
        }

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        @media (max-width: 768px) {
            .row-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Tableau de bord RH</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="attendance.php" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Enregistrer présence
            </a>
        </div>
    </div>
    <div class="page-body">

        <?php if ($db_error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Erreur BD : <?php echo htmlspecialchars($db_error); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <div class="stat-ico a"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div><div class="stat-num"><?php echo $stat_employees; ?></div><div class="stat-lbl">Employés actifs</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico b"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div><div class="stat-num"><?php echo $stat_present; ?></div><div class="stat-lbl">Présents aujourd'hui</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico r"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                <div><div class="stat-num"><?php echo $stat_absent; ?></div><div class="stat-lbl">Absences/retards</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico y"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11H3z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                <div><div class="stat-num"><?php echo $stat_leaves_pending; ?></div><div class="stat-lbl">Congés en attente</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico p"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                <div><div class="stat-num"><?php echo $stat_contracts_expiring; ?></div><div class="stat-lbl">Contrats à expirer</div></div>
            </div>
        </div>

        <!-- Présences du jour -->
        <div class="card">
            <div class="card-head">
                <h3>📋 Présences du jour — <?php echo date('d/m/Y'); ?></h3>
                <a href="attendance.php" class="btn btn-secondary btn-sm">Voir tout →</a>
            </div>
            <?php if (!$attendances || $attendances->num_rows === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" width="40" height="40"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <h3>Aucune présence enregistrée</h3>
                    <p><a href="attendance.php" style="color:var(--green);font-weight:600">Enregistrer les présences →</a></p>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Téléphone</th>
                        <th>Statut</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Heures</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($r = $attendances->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($r['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['phone'] ?? '—'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $r['status']; ?>">
                                    <?php
                                    $status_labels = [
                                            'present' => '✅ Présent',
                                            'absent' => '❌ Absent',
                                            'late' => '⏰ Retard',
                                            'excused' => '📋 Justifié',
                                            'holiday' => '🏖️ Congé'
                                    ];
                                    echo $status_labels[$r['status']] ?? $r['status'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $r['check_in'] ?? '—'; ?></td>
                            <td><?php echo $r['check_out'] ?? '—'; ?></td>
                            <td><?php echo $r['hours_worked'] ?? '0'; ?>h</td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="row-2">
            <!-- Congés en attente -->
            <div class="card">
                <div class="card-head">
                    <h3>📅 Demandes de congé en attente</h3>
                    <a href="leaves.php" class="btn btn-secondary btn-sm">Gérer →</a>
                </div>
                <?php if (!$leaves || $leaves->num_rows === 0): ?>
                    <div class="empty">
                        <svg viewBox="0 0 24 24" width="40" height="40"><path d="M3 9l9-7 9 7v11H3z"/></svg>
                        <h3>Aucune demande en attente</h3>
                    </div>
                <?php else: ?>
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Type</th>
                            <th>Période</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($l = $leaves->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight:600"><?php echo htmlspecialchars($l['full_name']); ?></td>
                                <td><?php echo ucfirst($l['leave_type']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($l['start_date'])) . ' → ' . date('d/m/Y', strtotime($l['end_date'])); ?></td>
                                <td>
                                    <a href="leaves.php?action=approve&id=<?php echo $l['id']; ?>" class="btn btn-blue btn-xs" onclick="return confirm('Approuver cette demande ?')">✓ Valider</a>
                                    <a href="leaves.php?action=reject&id=<?php echo $l['id']; ?>" class="btn btn-red btn-xs" onclick="return confirm('Refuser cette demande ?')">✗ Refuser</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Alertes RH -->
            <div class="card">
                <div class="card-head">
                    <div class="alerts-title">
                        <h3>🔔 Alertes RH</h3>
                        <?php if (!$show_read && $unread_count > 0): ?>
                            <span class="alerts-badge"><?php echo $unread_count; ?> nouvelle(s)</span>
                        <?php endif; ?>
                    </div>
                    <div class="alerts-actions">
                        <?php if (!$show_read && $unread_count > 0): ?>
                            <a href="mark-alert-read.php?mark_all=1" class="btn-mark-all" onclick="return confirm('Marquer toutes les alertes comme lues ?')">✓ Tout marquer comme lu</a>
                        <?php endif; ?>
                        <a href="?show_read=<?php echo $show_read ? 0 : 1; ?>" class="btn-toggle-read">
                            <?php if ($show_read): ?>
                                🔔 Voir les alertes non lues (<?php echo $unread_count; ?>)
                            <?php else: ?>
                                📜 Voir les alertes déjà lues
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <?php if (!$alerts || $alerts->num_rows === 0): ?>
                    <div class="empty">
                        <svg viewBox="0 0 24 24" width="40" height="40">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <h3>Aucune alerte</h3>
                        <p><?php echo $show_read ? 'Aucune alerte dans l\'historique' : 'Tout est à jour !'; ?></p>
                    </div>
                <?php else: ?>
                    <div class="alerts-container">
                        <?php while ($a = $alerts->fetch_assoc()):
                            $alert_icons = [
                                    'contract_expiry' => '📄',
                                    'leave_request' => '🏖️',
                                    'absence' => '⚠️',
                                    'document_missing' => '📁'
                            ];
                            $icon = $alert_icons[$a['alert_type']] ?? '🔔';
                            ?>
                            <div class="alert-item <?php echo $a['alert_type']; ?>">
                                <div class="alert-icon">
                                    <?php echo $icon; ?>
                                </div>
                                <div class="alert-content">
                                    <div class="alert-message"><?php echo htmlspecialchars($a['message']); ?></div>
                                    <div class="alert-time"><?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?></div>
                                </div>
                                <div class="alert-actions">
                                    <?php if (!$show_read): ?>
                                        <a href="mark-alert-read.php?alert_id=<?php echo $a['id']; ?>" class="btn-mark-read" onclick="return confirm('Marquer cette alerte comme lue ?')">
                                            ✓ Marquer lu
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</body>
</html>