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

// Paramètres de filtrage
$report_type = $_GET['report_type'] ?? 'attendance';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$department_id = $_GET['department'] ?? null;
$export = $_GET['export'] ?? null;

// Calcul des dates
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

// === RÉCUPÉRATION DES DONNÉES SELON LE TYPE DE RAPPORT ===

// 1. RAPPORT DE PRÉSENCE
$attendance_report = [];
if ($report_type == 'attendance') {
    $attendance_report = generateAttendanceReport($database, $start_date, $end_date);
}

// 2. STATISTIQUES RH
$rh_stats = getHRStatistics($database, $start_date, $end_date);

// 3. CONTRATS EXPIRANT
$expiring_contracts = getExpiringContracts($database, 60);

// 4. DÉPARTEMENTS
$department_stats = getDepartmentStats($database);

// 5. ABSENCES PAR SERVICE
$absence_by_department = [];
$stmt = $database->prepare("
    SELECT d.name as department_name, 
           COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
           COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count
    FROM attendances a
    INNER JOIN users u ON u.id = a.user_id
    LEFT JOIN employee_assignments ea ON ea.user_id = u.id AND ea.is_primary = 1
    LEFT JOIN departments d ON d.id = ea.department_id
    WHERE a.attendance_date BETWEEN ? AND ?
    GROUP BY d.id, d.name
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$absence_by_department = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 6. CONGÉS PAR MOIS
$leaves_by_month = [];
for ($m = 1; $m <= 12; $m++) {
    $month_start = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
    $month_end = date('Y-m-t', strtotime($month_start));

    $stmt = $database->prepare("
        SELECT COUNT(*) as count 
        FROM leave_requests 
        WHERE status = 'approved' 
        AND start_date BETWEEN ? AND ?
    ");
    $stmt->bind_param('ss', $month_start, $month_end);
    $stmt->execute();
    $leaves_by_month[$m] = $stmt->get_result()->fetch_assoc()['count'];
}

// 7. RÉPARTITION DES RÔLES
$stmt = $database->query("
    SELECT r.role_name, COUNT(u.id) as employee_count
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE u.role_id NOT IN (1, 3) AND u.is_active = 1
    GROUP BY r.id, r.role_name
    ORDER BY employee_count DESC
");
$role_distribution = $stmt->fetch_all(MYSQLI_ASSOC);

// 8. ANCIENNETÉ DES EMPLOYÉS
$stmt = $database->prepare("
    SELECT u.full_name, ec.start_date, 
           TIMESTAMPDIFF(YEAR, ec.start_date, CURDATE()) as seniority_years
    FROM employee_contracts ec
    INNER JOIN users u ON u.id = ec.user_id
    WHERE (ec.end_date IS NULL OR ec.end_date >= CURDATE())
    ORDER BY seniority_years DESC
    LIMIT 10
");
$stmt->execute();
$seniority = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === EXPORT CSV ===
if ($export == 'csv' && $report_type == 'attendance') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_presence_' . $year . '_' . $month . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employé', 'Email', 'Jours présents', 'Jours absents', 'Retards', 'Justifiés', 'Total heures', 'Heures sup']);

    foreach ($attendance_report as $row) {
        fputcsv($output, [
            $row['full_name'],
            $row['email'],
            $row['present_days'],
            $row['absent_days'],
            $row['late_days'],
            $row['excused_days'],
            $row['total_hours'],
            $row['total_overtime']
        ]);
    }
    fclose($output);
    exit;
}

if ($export == 'csv' && $report_type == 'contracts') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contrats_expirant.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employé', 'Email', 'Type contrat', 'Date fin', 'Jours restants']);

    foreach ($expiring_contracts as $row) {
        $days_left = (strtotime($row['end_date']) - strtotime(date('Y-m-d'))) / 86400;
        fputcsv($output, [
            $row['full_name'],
            $row['email'],
            $row['contract_type'],
            date('d/m/Y', strtotime($row['end_date'])),
            round($days_left) . ' jours'
        ]);
    }
    fclose($output);
    exit;
}

// Maximum pour les graphiques
$max_absences = !empty($absence_by_department) ? max(array_column($absence_by_department, 'absent_count')) : 1;
$max_leaves = max($leaves_by_month) > 0 ? max($leaves_by_month) : 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Rapports RH</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .report-tab {
            padding: 10px 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--rs);
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            transition: all 0.15s;
        }
        .report-tab.active {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }
        .report-tab:hover:not(.active) {
            background: var(--surf2);
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            font-size: 1.8rem;
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

        .chart-bar-container {
            background: var(--surf2);
            border-radius: var(--rs);
            margin: 10px 0;
        }
        .chart-bar {
            background: var(--green);
            height: 30px;
            border-radius: var(--rs);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .chart-bar.red { background: var(--red); }
        .chart-bar.blue { background: var(--blue); }
        .chart-bar.amber { background: var(--amber); }

        .export-btn {
            background: var(--blue);
            color: white;
        }
        .export-btn:hover {
            background: var(--blue-l);
            color: var(--blue);
        }
        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 22px;
            padding: 15px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
        }
        .yearly-chart {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 150px;
            margin: 20px 0;
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
            min-height: 2px;
        }
        .role-distribution {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .role-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: var(--surf2);
            border-radius: var(--rs);
        }
        .role-item .count {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--green);
        }
        @media print {
            .report-tabs, .filter-bar, .btn, .no-print {
                display: none !important;
            }
            .card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Rapports RH</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <button onclick="window.print()" class="btn btn-secondary btn-sm no-print">
                🖨️ Imprimer
            </button>
        </div>
    </div>
    <div class="page-body">

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Onglets des rapports -->
        <div class="report-tabs">
            <a href="?report_type=attendance&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="report-tab <?php echo $report_type == 'attendance' ? 'active' : ''; ?>">
                📊 Présences
            </a>
            <a href="?report_type=statistics&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="report-tab <?php echo $report_type == 'statistics' ? 'active' : ''; ?>">
                📈 Statistiques RH
            </a>
            <a href="?report_type=contracts&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="report-tab <?php echo $report_type == 'contracts' ? 'active' : ''; ?>">
                📋 Contrats
            </a>
            <a href="?report_type=departments&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="report-tab <?php echo $report_type == 'departments' ? 'active' : ''; ?>">
                🏢 Départements
            </a>
        </div>

        <!-- Filtres (pour rapports avec période) -->
        <?php if ($report_type != 'contracts' && $report_type != 'departments'): ?>
            <div class="filter-bar">
                <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                    <div class="form-group" style="margin:0;">
                        <label>Année</label>
                        <select name="year" class="input" style="width:auto;">
                            <?php for ($y = date('Y')-2; $y <= date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Mois</label>
                        <select name="month" class="input" style="width:auto;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $m == intval($month) ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ==================== RAPPORT DE PRÉSENCE ==================== -->
        <?php if ($report_type == 'attendance'): ?>

            <div class="stats-row">
                <div class="stat-card green">
                    <div class="value"><?php echo $rh_stats['total_employees'] ?? 0; ?></div>
                    <div class="label">Employés actifs</div>
                </div>
                <div class="stat-card green">
                    <div class="value"><?php echo $rh_stats['total_present'] ?? 0; ?></div>
                    <div class="label">Présences</div>
                </div>
                <div class="stat-card red">
                    <div class="value"><?php echo $rh_stats['total_absent'] ?? 0; ?></div>
                    <div class="label">Absences/Retards</div>
                </div>
                <div class="stat-card blue">
                    <div class="value"><?php echo number_format($rh_stats['total_overtime'] ?? 0, 1); ?>h</div>
                    <div class="label">Heures supplémentaires</div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>📋 Rapport de présence - <?php echo date('F Y', strtotime($start_date)); ?></h3>
                    <a href="?report_type=attendance&year=<?php echo $year; ?>&month=<?php echo $month; ?>&export=csv" class="btn btn-primary btn-sm export-btn">
                        📎 Exporter CSV
                    </a>
                </div>
                <?php if (empty($attendance_report)): ?>
                    <div class="empty"><p>Aucune donnée de présence pour cette période</p></div>
                <?php else: ?>
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Présents</th>
                            <th>Absents</th>
                            <th>Retards</th>
                            <th>Justifiés</th>
                            <th>Heures</th>
                            <th>Heures sup</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($attendance_report as $row): ?>
                            <tr>
                                <td style="font-weight:600"><?php echo htmlspecialchars($row['full_name']); ?><br><small><?php echo htmlspecialchars($row['email']); ?></small></td>
                                <td class="green" style="color:var(--green); font-weight:600;"><?php echo $row['present_days']; ?></td>
                                <td class="red" style="color:var(--red);"><?php echo $row['absent_days']; ?></td>
                                <td class="amber" style="color:var(--amber);"><?php echo $row['late_days']; ?></td>
                                <td class="blue" style="color:var(--blue);"><?php echo $row['excused_days']; ?></td>
                                <td><?php echo number_format($row['total_hours'], 1); ?>h</td>
                                <td><?php echo number_format($row['total_overtime'], 1); ?>h</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Graphique des absences par département -->
            <div class="card">
                <div class="card-head">
                    <h3>📊 Absences par département</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($absence_by_department as $dept): ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                                <span><?php echo htmlspecialchars($dept['department_name'] ?? 'Non affecté'); ?></span>
                                <span style="color:var(--red); font-weight:600;"><?php echo $dept['absent_count']; ?> absences</span>
                            </div>
                            <div class="chart-bar-container">
                                <div class="chart-bar red" style="width: <?php echo ($dept['absent_count'] / max($max_absences, 1)) * 100; ?>%;">
                                    <?php echo $dept['absent_count']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ==================== STATISTIQUES RH ==================== -->
        <?php elseif ($report_type == 'statistics'): ?>

            <div class="stats-row">
                <div class="stat-card green">
                    <div class="value"><?php echo $rh_stats['total_employees'] ?? 0; ?></div>
                    <div class="label">Employés actifs</div>
                </div>
                <div class="stat-card blue">
                    <div class="value"><?php echo $rh_stats['total_approved_leaves'] ?? 0; ?></div>
                    <div class="label">Congés approuvés</div>
                </div>
                <div class="stat-card amber">
                    <div class="value"><?php echo count($expiring_contracts); ?></div>
                    <div class="label">Contrats expirant (60j)</div>
                </div>
            </div>

            <!-- Congés par mois -->
            <div class="card">
                <div class="card-head">
                    <h3>📅 Congés approuvés par mois (<?php echo $year; ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="yearly-chart">
                        <?php
                        $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                        for ($m = 1; $m <= 12; $m++):
                            $height = ($leaves_by_month[$m] / $max_leaves) * 120;
                            ?>
                            <div class="bar-container">
                                <div class="bar-value" style="font-size:0.6rem;"><?php echo $leaves_by_month[$m]; ?></div>
                                <div class="bar" style="height: <?php echo max($height, 4); ?>px;"></div>
                                <div class="bar-label" style="font-size:0.65rem;"><?php echo $months[$m-1]; ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Répartition des rôles -->
            <div class="card">
                <div class="card-head">
                    <h3>👥 Répartition par rôle</h3>
                </div>
                <div class="card-body">
                    <div class="role-distribution">
                        <?php foreach ($role_distribution as $role): ?>
                            <div class="role-item">
                                <div class="count"><?php echo $role['employee_count']; ?></div>
                                <div class="label"><?php echo htmlspecialchars($role['role_name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Ancienneté -->
            <div class="card">
                <div class="card-head">
                    <h3>⭐ Ancienneté des employés (Top 10)</h3>
                </div>
                <?php if (empty($seniority)): ?>
                    <div class="empty"><p>Aucune donnée</p></div>
                <?php else: ?>
                    <table class="tbl">
                        <thead><tr><th>Employé</th><th>Date début</th><th>Ancienneté</th></tr></thead>
                        <tbody>
                        <?php foreach ($seniority as $emp): ?>
                            <tr>
                                <td style="font-weight:600"><?php echo htmlspecialchars($emp['full_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($emp['start_date'])); ?></td>
                                <td><?php echo $emp['seniority_years']; ?> an(s)</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ==================== RAPPORT CONTRATS ==================== -->
        <?php elseif ($report_type == 'contracts'): ?>

            <div class="stats-row">
                <div class="stat-card green">
                    <div class="value"><?php echo count($expiring_contracts); ?></div>
                    <div class="label">Contrats expirant (60j)</div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>📋 Contrats expirant dans les 60 jours</h3>
                    <a href="?report_type=contracts&export=csv" class="btn btn-primary btn-sm export-btn">📎 Exporter CSV</a>
                </div>
                <?php if (empty($expiring_contracts)): ?>
                    <div class="empty"><p>Aucun contrat expirant dans les 60 jours</p></div>
                <?php else: ?>
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Email</th>
                            <th>Type contrat</th>
                            <th>Date fin</th>
                            <th>Jours restants</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($expiring_contracts as $contract):
                            $days_left = (strtotime($contract['end_date']) - strtotime(date('Y-m-d'))) / 86400;
                            ?>
                            <tr>
                                <td style="font-weight:600"><?php echo htmlspecialchars($contract['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($contract['email']); ?></td>
                                <td><?php echo $contract['contract_type']; ?></td>
                                <td style="color:var(--red);"><?php echo date('d/m/Y', strtotime($contract['end_date'])); ?></td>
                                <td><?php echo round($days_left); ?> jours</td>
                                <td><a href="contracts.php?user_id=<?php echo $contract['user_id']; ?>" class="btn btn-blue btn-sm">Renouveler</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ==================== RAPPORT DÉPARTEMENTS ==================== -->
        <?php elseif ($report_type == 'departments'): ?>

            <div class="card">
                <div class="card-head">
                    <h3>🏢 Effectifs par département</h3>
                </div>
                <?php if (empty($department_stats)): ?>
                    <div class="empty"><p>Aucun département configuré</p></div>
                <?php else: ?>
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Département</th>
                            <th>Nombre d'employés</th>
                            <th>Répartition</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $max_employees = max(array_column($department_stats, 'employee_count'));
                        foreach ($department_stats as $dept):
                            ?>
                            <tr>
                                <td style="font-weight:600"><?php echo htmlspecialchars($dept['name']); ?></td>
                                <td style="color:var(--green); font-weight:600;"><?php echo $dept['employee_count']; ?></td>
                                <td style="width: 50%;">
                                    <div class="chart-bar-container">
                                        <div class="chart-bar" style="width: <?php echo ($dept['employee_count'] / max($max_employees, 1)) * 100; ?>%; background: var(--blue);">
                                            <?php echo $dept['employee_count']; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>
</div>
</body>
</html>