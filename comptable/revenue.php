<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
require_once 'functions.php';

if ($_SESSION['role'] !== 'comptable') {
    header('Location: ../login.php');
    exit;
}

// Période par défaut : mois en cours
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$view = $_GET['view'] ?? 'monthly'; // monthly, yearly, daily

// Dates pour les filtres
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

// ==================== STATISTIQUES ====================

// Revenus totaux de la période
$stmt = $database->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total
    FROM invoices 
    WHERE generated_date BETWEEN ? AND ? AND status = 'paid'
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$total_revenue = $stmt->get_result()->fetch_assoc()['total'];

// Nombre de factures payées
$stmt = $database->prepare("
    SELECT COUNT(*) as count
    FROM invoices 
    WHERE generated_date BETWEEN ? AND ? AND status = 'paid'
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$paid_invoices_count = $stmt->get_result()->fetch_assoc()['count'];

// Ticket moyen
$average_ticket = ($paid_invoices_count > 0) ? $total_revenue / $paid_invoices_count : 0;

// Revenus par mode de paiement
$stmt = $database->prepare("
    SELECT p.method, COALESCE(SUM(p.amount), 0) as total
    FROM payments p
    INNER JOIN invoices i ON i.id = p.invoice_id
    WHERE i.generated_date BETWEEN ? AND ? AND i.status = 'paid'
    GROUP BY p.method
    ORDER BY total DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$payments_by_method = $stmt->get_result();

// ==================== ÉVOLUTION MENSUELLE ====================
$monthly_data = [];
for ($m = 1; $m <= 12; $m++) {
    $month_start = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
    $month_end = date('Y-m-t', strtotime($month_start));

    $stmt = $database->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM invoices 
        WHERE generated_date BETWEEN ? AND ? AND status = 'paid'
    ");
    $stmt->bind_param('ss', $month_start, $month_end);
    $stmt->execute();
    $month_total = $stmt->get_result()->fetch_assoc()['total'];
    $monthly_data[$m] = $month_total;
}

// ==================== ÉVOLUTION JOURNALIÈRE (mois en cours) ====================
$days_in_month = date('t', strtotime($start_date));
$daily_data = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $day_date = sprintf("%s-%s-%02d", $year, $month, $d);
    $next_date = sprintf("%s-%s-%02d", $year, $month, $d + 1);

    $stmt = $database->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM invoices 
        WHERE generated_date >= ? AND generated_date < ? AND status = 'paid'
    ");
    $stmt->bind_param('ss', $day_date, $next_date);
    $stmt->execute();
    $daily_data[$d] = $stmt->get_result()->fetch_assoc()['total'];
}

// ==================== REVENUS PAR SERVICE ====================
$stmt = $database->prepare("
    SELECT 
        a.type as service_type,
        COUNT(DISTINCT i.id) as invoice_count,
        COALESCE(SUM(i.total_amount), 0) as total_amount
    FROM invoices i
    LEFT JOIN appointments a ON a.id = i.appointment_id
    WHERE i.generated_date BETWEEN ? AND ? AND i.status = 'paid'
    GROUP BY a.type
    ORDER BY total_amount DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$revenue_by_service = $stmt->get_result();

// ==================== REVENUS PAR MÉDECIN ====================
$stmt = $database->prepare("
    SELECT 
        d.id,
        u.full_name as doctor_name,
        COUNT(DISTINCT i.id) as invoice_count,
        COALESCE(SUM(i.total_amount), 0) as total_amount
    FROM invoices i
    LEFT JOIN appointments a ON a.id = i.appointment_id
    LEFT JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN users u ON u.id = d.user_id
    WHERE i.generated_date BETWEEN ? AND ? AND i.status = 'paid'
    GROUP BY d.id, u.full_name
    ORDER BY total_amount DESC
    LIMIT 10
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$revenue_by_doctor = $stmt->get_result();

// ==================== ÉVOLUTION ANNUELLE ====================
$yearly_data = [];
$current_year = date('Y');
for ($y = $current_year - 2; $y <= $current_year; $y++) {
    $year_start = "$y-01-01";
    $year_end = "$y-12-31";

    $stmt = $database->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM invoices 
        WHERE generated_date BETWEEN ? AND ? AND status = 'paid'
    ");
    $stmt->bind_param('ss', $year_start, $year_end);
    $stmt->execute();
    $yearly_data[$y] = $stmt->get_result()->fetch_assoc()['total'];
}

// ==================== COMPARAISON AVEC MOIS PRÉCÉDENT ====================
$prev_month_date = date('Y-m-01', strtotime("$start_date -1 month"));
$prev_month_start = $prev_month_date;
$prev_month_end = date('Y-m-t', strtotime($prev_month_date));

$stmt = $database->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total
    FROM invoices 
    WHERE generated_date BETWEEN ? AND ? AND status = 'paid'
");
$stmt->bind_param('ss', $prev_month_start, $prev_month_end);
$stmt->execute();
$prev_month_revenue = $stmt->get_result()->fetch_assoc()['total'];

$evolution = 0;
if ($prev_month_revenue > 0) {
    $evolution = (($total_revenue - $prev_month_revenue) / $prev_month_revenue) * 100;
}

// ==================== MEILLEURS JOURS ====================
$stmt = $database->prepare("
    SELECT 
        DAYNAME(i.generated_date) as day_name,
        COUNT(*) as invoice_count,
        COALESCE(SUM(i.total_amount), 0) as total_amount
    FROM invoices i
    WHERE i.generated_date BETWEEN ? AND ? AND i.status = 'paid'
    GROUP BY DAYOFWEEK(i.generated_date), DAYNAME(i.generated_date)
    ORDER BY total_amount DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$best_days = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Analyse des revenus - Comptable</title>
    <link rel="stylesheet" href="comptable.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container { background: var(--surface); border-radius: var(--r); padding: 20px; margin-bottom: 22px; border: 1px solid var(--border); }
        .chart-container canvas { max-height: 300px; }
        .evolution-positive { color: var(--green); font-weight: 700; }
        .evolution-negative { color: var(--red); font-weight: 700; }
        .stat-value-large { font-size: 2rem; font-weight: 700; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Analyse des revenus</span>
        <div class="topbar-right">
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3h12v6"/><rect x="6" y="15" width="12" height="6" rx="2"/></svg>
                Imprimer
            </button>
        </div>
    </div>
    <div class="page-body">

        <!-- Filtres -->
        <div class="filter-bar">
            <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <label>Année :</label>
                <select name="year" class="input" style="width:auto;">
                    <?php for ($y = date('Y')-2; $y <= date('Y'); $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <label>Mois :</label>
                <select name="month" class="input" style="width:auto;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $m == intval($month) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </form>
        </div>

        <!-- Cartes KPI -->
        <div class="stats">
            <div class="revenue-card" style="background:var(--green)">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <div>
                    <div class="r-num"><?php echo number_format($total_revenue, 0, ',', ' '); ?> DA</div>
                    <div class="r-lbl">Revenus totaux</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico b"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg></div>
                <div><div class="stat-num"><?php echo $paid_invoices_count; ?></div><div class="stat-lbl">Factures payées</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico a"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div>
                <div><div class="stat-num"><?php echo number_format($average_ticket, 0, ',', ' '); ?> DA</div><div class="stat-lbl">Ticket moyen</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico <?php echo $evolution >= 0 ? 'g' : 'r'; ?>">
                    <svg viewBox="0 0 24 24"><polyline points="<?php echo $evolution >= 0 ? '18 15 12 9 6 15' : '6 9 12 15 18 9'; ?>"/></svg>
                </div>
                <div>
                    <div class="stat-num <?php echo $evolution >= 0 ? 'evolution-positive' : 'evolution-negative'; ?>">
                        <?php echo $evolution >= 0 ? '+' : ''; ?><?php echo round($evolution, 1); ?>%
                    </div>
                    <div class="stat-lbl">vs mois précédent</div>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:22px; margin-bottom:22px;">
            <!-- Graphique mensuel -->
            <div class="chart-container">
                <h3 style="margin-bottom:15px;">Évolution mensuelle <?php echo $year; ?></h3>
                <canvas id="monthlyChart"></canvas>
            </div>

            <!-- Graphique journalier -->
            <div class="chart-container">
                <h3 style="margin-bottom:15px;">Revenus journaliers (<?php echo date('F Y', strtotime($start_date)); ?>)</h3>
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <!-- Graphique annuel -->
        <div class="chart-container" style="margin-bottom:22px;">
            <h3 style="margin-bottom:15px;">Évolution annuelle (comparaison)</h3>
            <canvas id="yearlyChart"></canvas>
        </div>

        <!-- Répartition par mode de paiement et service -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:22px; margin-bottom:22px;">
            <div class="card">
                <div class="card-head"><h3>Répartition par mode de paiement</h3></div>
                <div class="card-body">
                    <canvas id="paymentMethodChart" style="max-height:250px;"></canvas>
                    <table class="tbl" style="margin-top:15px;">
                        <?php while ($pm = $payments_by_method->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo ucfirst($pm['method']); ?></td>
                                <td style="text-align:right; font-weight:600;"><?php echo number_format($pm['total'], 0, ',', ' '); ?> DA</td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Revenus par service</h3></div>
                <div class="card-body">
                    <canvas id="serviceChart" style="max-height:250px;"></canvas>
                    <table class="tbl" style="margin-top:15px;">
                        <?php while ($svc = $revenue_by_service->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo ucfirst($svc['service_type'] ?? 'Consultation'); ?></td>
                                <td style="text-align:right; font-weight:600;"><?php echo number_format($svc['total_amount'], 0, ',', ' '); ?> DA</td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top médecins et meilleurs jours -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:22px;">
            <div class="card">
                <div class="card-head"><h3>Top 10 médecins</h3></div>
                <?php if ($revenue_by_doctor->num_rows === 0): ?>
                    <div class="empty"><p>Aucune donnée</p></div>
                <?php else: ?>
                    <table class="tbl">
                        <thead><tr><th>Médecin</th><th>Factures</th><th>CA généré</th></tr></thead>
                        <tbody>
                        <?php while ($doc = $revenue_by_doctor->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['doctor_name'] ?? '—'); ?></td>
                                <td><?php echo $doc['invoice_count']; ?></td>
                                <td style="font-weight:600;color:var(--green)"><?php echo number_format($doc['total_amount'], 0, ',', ' '); ?> DA</td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-head"><h3>Meilleurs jours de la semaine</h3></div>
                <?php if ($best_days->num_rows === 0): ?>
                    <div class="empty"><p>Aucune donnée</p></div>
                <?php else: ?>
                    <table class="tbl">
                        <thead><tr><th>Jour</th><th>Factures</th><th>CA généré</th></tr></thead>
                        <tbody>
                        <?php while ($day = $best_days->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($day['day_name']); ?></td>
                                <td><?php echo $day['invoice_count']; ?></td>
                                <td style="font-weight:600;color:var(--green)"><?php echo number_format($day['total_amount'], 0, ',', ' '); ?> DA</td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    // Graphique mensuel
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [{
                label: 'Revenus (DA)',
                data: [<?php echo implode(',', $monthly_data); ?>],
                backgroundColor: 'rgba(26, 107, 74, 0.7)',
                borderColor: 'var(--green)',
                borderWidth: 1
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    // Graphique journalier
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: [<?php for($d=1; $d<=$days_in_month; $d++) echo "'$d',"; ?>],
            datasets: [{
                label: 'Revenus journaliers (DA)',
                data: [<?php echo implode(',', $daily_data); ?>],
                backgroundColor: 'rgba(26, 107, 74, 0.1)',
                borderColor: 'var(--green)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    // Graphique annuel
    const yearlyCtx = document.getElementById('yearlyChart').getContext('2d');
    new Chart(yearlyCtx, {
        type: 'bar',
        data: {
            labels: [<?php foreach($yearly_data as $y => $v) echo "'$y',"; ?>],
            datasets: [{
                label: 'Revenus annuels (DA)',
                data: [<?php echo implode(',', $yearly_data); ?>],
                backgroundColor: 'rgba(26, 107, 74, 0.7)',
                borderColor: 'var(--green)',
                borderWidth: 1
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    // Graphique mode de paiement
    const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php
                $payments_by_method = $database->query("
                SELECT p.method, COALESCE(SUM(p.amount), 0) as total
                FROM payments p
                INNER JOIN invoices i ON i.id = p.invoice_id
                WHERE i.generated_date BETWEEN '$start_date' AND '$end_date' AND i.status = 'paid'
                GROUP BY p.method
            ");
                $labels = []; $values = [];
                while($pm = $payments_by_method->fetch_assoc()) {
                    $labels[] = "'" . ucfirst($pm['method']) . "'";
                    $values[] = $pm['total'];
                }
                echo implode(',', $labels);
                ?>],
            datasets: [{
                data: [<?php echo implode(',', $values); ?>],
                backgroundColor: ['#1A6B4A', '#B57A0A', '#1A4E76', '#5E3A8A', '#C05A00']
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    // Graphique par service
    const serviceCtx = document.getElementById('serviceChart').getContext('2d');
    new Chart(serviceCtx, {
        type: 'pie',
        data: {
            labels: [<?php
                $revenue_by_service = $database->query("
                SELECT COALESCE(a.type, 'Consultation') as service_type, COALESCE(SUM(i.total_amount), 0) as total_amount
                FROM invoices i
                LEFT JOIN appointments a ON a.id = i.appointment_id
                WHERE i.generated_date BETWEEN '$start_date' AND '$end_date' AND i.status = 'paid'
                GROUP BY a.type
            ");
                $labels = []; $values = [];
                while($svc = $revenue_by_service->fetch_assoc()) {
                    $labels[] = "'" . ucfirst($svc['service_type']) . "'";
                    $values[] = $svc['total_amount'];
                }
                echo implode(',', $labels);
                ?>],
            datasets: [{
                data: [<?php echo implode(',', $values); ?>],
                backgroundColor: ['#1A6B4A', '#B57A0A', '#1A4E76', '#5E3A8A', '#C05A00', '#B83228']
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });
</script>

</body>
</html>