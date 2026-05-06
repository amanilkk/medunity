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

$message = '';
$error = '';

// === TRAITEMENT FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'approve' && isset($_GET['id'])) {
        $result = approveLeaveRequest($database, $_GET['id'], $_SESSION['user_id'], 'approved');
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($_GET['action'] === 'reject' && isset($_GET['id'])) {
        $result = approveLeaveRequest($database, $_GET['id'], $_SESSION['user_id'], 'rejected');
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// === RÉCUPÉRER LES DONNÉES ===
$filter_status = $_GET['status'] ?? 'pending';

// Récupérer les demandes de congé avec les infos employés
$sql = "SELECT lr.*, u.full_name, u.email, u.phone 
        FROM leave_requests lr
        INNER JOIN users u ON u.id = lr.user_id";

if ($filter_status !== 'all') {
    $sql .= " WHERE lr.status = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param('s', $filter_status);
} else {
    $stmt = $database->prepare($sql);
}
$stmt->execute();
$leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === STATISTIQUES POUR LES ONGLETS ===
$stats_sql = "SELECT status, COUNT(*) as count FROM leave_requests GROUP BY status";
$stats_result = $database->query($stats_sql);
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
while ($row = $stats_result->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
}

// === STATISTIQUES ANNUELLES ===
$current_year = date('Y');
$yearly_stats = [];
for ($m = 1; $m <= 12; $m++) {
    $month_start = "$current_year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
    $month_end = date('Y-m-t', strtotime($month_start));

    $stmt = $database->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved' AND start_date BETWEEN ? AND ?");
    $stmt->bind_param('ss', $month_start, $month_end);
    $stmt->execute();
    $yearly_stats[$m] = $stmt->get_result()->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestion des Congés — RH</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            background: var(--surf2);
            color: var(--text2);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.15s;
        }
        .tab.active {
            background: var(--green);
            color: white;
        }
        .tab:hover:not(.active) {
            background: var(--border);
        }
        .badge-count {
            display: inline-block;
            background: rgba(0,0,0,0.1);
            border-radius: 20px;
            padding: 0px 6px;
            font-size: 0.7rem;
            min-width: 24px;
            text-align: center;
        }
        .tab.active .badge-count {
            background: rgba(255,255,255,0.2);
        }
        .btn-xs {
            padding: 4px 8px;
            font-size: 0.7rem;
        }
        .btn-red {
            background: var(--red-l);
            color: var(--red);
            border: 1px solid #FADBD8;
        }
        .btn-red:hover {
            background: #FADBD8;
        }
        .stats-leaves {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-bottom: 22px;
        }
        .stat-leave {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 15px;
            text-align: center;
            box-shadow: var(--sh);
        }
        .stat-leave .number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .stat-leave .label {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 5px;
        }
        .stat-leave.pending .number { color: var(--amber); }
        .stat-leave.approved .number { color: var(--green); }
        .stat-leave.rejected .number { color: var(--red); }
        .stat-leave.total .number { color: var(--blue); }

        .yearly-chart {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 150px;
            margin-top: 15px;
        }
        .bar-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        .bar {
            width: 100%;
            background: var(--green);
            border-radius: 4px 4px 0 0;
            transition: height 0.3s;
            min-height: 2px;
        }
        .bar-label {
            font-size: 0.65rem;
            color: var(--text2);
            text-align: center;
        }
        .bar-value {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--green);
        }
        .info-leave {
            background: var(--blue-l);
            border-left: 4px solid var(--blue);
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des congés</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body">

        <?php if ($message): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques des congés -->
        <div class="stats-leaves">
            <div class="stat-leave pending">
                <div class="number"><?php echo $stats['pending']; ?></div>
                <div class="label">En attente</div>
            </div>
            <div class="stat-leave approved">
                <div class="number"><?php echo $stats['approved']; ?></div>
                <div class="label">Approuvées</div>
            </div>
            <div class="stat-leave rejected">
                <div class="number"><?php echo $stats['rejected']; ?></div>
                <div class="label">Rejetées</div>
            </div>
            <div class="stat-leave total">
                <div class="number"><?php echo array_sum($stats); ?></div>
                <div class="label">Total</div>
            </div>
        </div>

        <!-- Évolution mensuelle des congés approuvés -->
        <div class="card">
            <div class="card-head">
                <h3>📊 Congés approuvés par mois (<?php echo $current_year; ?>)</h3>
            </div>
            <div class="card-body">
                <div class="yearly-chart">
                    <?php
                    $max_value = max($yearly_stats) > 0 ? max($yearly_stats) : 1;
                    $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                    for ($m = 1; $m <= 12; $m++):
                        $height = ($yearly_stats[$m] / $max_value) * 120;
                        ?>
                        <div class="bar-container">
                            <div class="bar-value"><?php echo $yearly_stats[$m]; ?></div>
                            <div class="bar" style="height: <?php echo max($height, 4); ?>px;"></div>
                            <div class="bar-label"><?php echo $months[$m-1]; ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card">
            <div class="filter-tabs">
                <a href="?status=pending" class="tab <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
                    <span class="badge-count"><?php echo $stats['pending']; ?></span> En attente
                </a>
                <a href="?status=approved" class="tab <?php echo $filter_status === 'approved' ? 'active' : ''; ?>">
                    <span class="badge-count"><?php echo $stats['approved']; ?></span> Approuvées
                </a>
                <a href="?status=rejected" class="tab <?php echo $filter_status === 'rejected' ? 'active' : ''; ?>">
                    <span class="badge-count"><?php echo $stats['rejected']; ?></span> Rejetées
                </a>
                <a href="?status=all" class="tab <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                    Toutes
                </a>
            </div>
        </div>

        <!-- Tableau des congés -->
        <div class="card">
            <div class="card-head">
                <h3>📋 Demandes de congé</h3>
            </div>
            <?php if (count($leaves) === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" width="40" height="40"><path d="M3 9l9-7 9 7v11H3z"/></svg>
                    <h3>Aucune demande <?php echo $filter_status === 'all' ? '' : $filter_status; ?></h3>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Type</th>
                        <th>Date début</th>
                        <th>Date fin</th>
                        <th>Jours</th>
                        <th>Raison</th>
                        <th>Demandé le</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($leaves as $l):
                        $start = new DateTime($l['start_date']);
                        $end = new DateTime($l['end_date']);
                        $days = $end->diff($start)->days + 1;

                        // Icône selon type de congé
                        $type_icon = '';
                        switch($l['leave_type']) {
                            case 'annual': $type_icon = '🏖️'; break;
                            case 'sick': $type_icon = '🤒'; break;
                            case 'unpaid': $type_icon = '💰'; break;
                            case 'maternity': $type_icon = '👶'; break;
                            default: $type_icon = '📅';
                        }
                        ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($l['full_name']); ?>
                                <small style="color:var(--text2); display:block; font-size:0.65rem;"><?php echo htmlspecialchars($l['email']); ?></small>
                            </td>
                            <td><?php echo $type_icon . ' ' . ucfirst(str_replace('_', ' ', $l['leave_type'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($l['start_date'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($l['end_date'])); ?></td>
                            <td style="font-weight:600; color:var(--blue);"><?php echo $days; ?> j</td>
                            <td><?php echo htmlspecialchars($l['reason'] ?? '—'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($l['created_at'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $l['status']; ?>">
                                    <?php
                                    $status_labels = ['pending' => 'En attente', 'approved' => 'Approuvée', 'rejected' => 'Rejetée', 'cancelled' => 'Annulée'];
                                    echo $status_labels[$l['status']] ?? $l['status'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($l['status'] === 'pending'): ?>
                                    <div class="tbl-actions">
                                        <a href="?action=approve&id=<?php echo $l['id']; ?>&status=<?php echo $filter_status; ?>" class="btn btn-blue btn-xs" onclick="return confirm('Approuver cette demande de congé ?')">
                                            ✓ Approuver
                                        </a>
                                        <a href="?action=reject&id=<?php echo $l['id']; ?>&status=<?php echo $filter_status; ?>" class="btn btn-red btn-xs" onclick="return confirm('Rejeter cette demande de congé ?')">
                                            ✗ Rejeter
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--text2); font-size:0.7rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Informations sur les congés -->
        <div class="card">
            <div class="card-head">
                <h3>ℹ️ Règles et informations</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div class="info-leave" style="padding: 12px; border-radius: var(--rs);">
                        <strong> Congés annuels</strong>
                        <p style="font-size:0.75rem; margin-top:5px;">25 jours par an, à prendre sur la période.</p>
                    </div>
                    <div class="info-leave" style="padding: 12px; border-radius: var(--rs);">
                        <strong> Congés maladie</strong>
                        <p style="font-size:0.75rem; margin-top:5px;">Justificatif médical requis après 3 jours.</p>
                    </div>
                    <div class="info-leave" style="padding: 12px; border-radius: var(--rs);">
                        <strong> Délai de prévenance</strong>
                        <p style="font-size:0.75rem; margin-top:5px;">15 jours recommandés pour les congés annuels.</p>
                    </div>
                    <div class="info-leave" style="padding: 12px; border-radius: var(--rs);">
                        <strong> Validation</strong>
                        <p style="font-size:0.75rem; margin-top:5px;">Les demandes doivent être approuvées par les RH.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
</body>
</html>