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

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'summary';

// Générer le rapport
$financial_data = getFinancialSummary($database, $start_date, $end_date);
$revenue_by_patient = getRevenueByPatient($database, $start_date, $end_date);

// Détail des factures sur la période
$stmt = $database->prepare("
    SELECT i.invoice_number, i.total_amount, i.paid_amount, i.status, i.generated_date,
           u.full_name as patient_name
    FROM invoices i
    INNER JOIN patients p ON p.id = i.patient_id
    INNER JOIN users u ON u.id = p.user_id
    WHERE i.generated_date BETWEEN ? AND ?
    ORDER BY i.generated_date DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$invoices_period = $stmt->get_result();

// Détail des dépenses sur la période - CORRIGÉ : total_amount au lieu de total_price
$stmt = $database->prepare("
    SELECT po.order_number, po.total_amount, po.order_date, po.status, s.name as supplier_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.order_date BETWEEN ? AND ? AND po.status = 'delivered'
    ORDER BY po.order_date DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$expenses_period = $stmt->get_result();

// Calculer le total des dépenses pour la période
$total_expenses = 0;
$expenses_list = [];
while ($exp = $expenses_period->fetch_assoc()) {
    $total_expenses += $exp['total_amount'];
    $expenses_list[] = $exp;
}
// Remettre le curseur au début pour l'affichage
$expenses_period = $expenses_list;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapports financiers - Comptable</title>
    <link rel="stylesheet" href="comptable.css">
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Rapports financiers</span>
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
            <form method="GET" style="display:flex; gap:10px; align-items:center; width:100%;">
                <label>Du :</label>
                <input type="date" name="start_date" class="input" value="<?php echo $start_date; ?>" style="width:auto;">
                <label>Au :</label>
                <input type="date" name="end_date" class="input" value="<?php echo $end_date; ?>" style="width:auto;">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </form>
        </div>

        <!-- Résumé financier -->
        <div class="stats">
            <div class="revenue-card" style="background:var(--green)">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <div>
                    <div class="r-num"><?php echo number_format($financial_data['revenues'], 0, ',', ' '); ?> DA</div>
                    <div class="r-lbl">Revenus</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico r"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div>
                <div><div class="stat-num"><?php echo number_format($financial_data['expenses'], 0, ',', ' '); ?> DA</div><div class="stat-lbl">Dépenses</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico p"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div><div class="stat-num"><?php echo number_format($financial_data['salaries'], 0, ',', ' '); ?> DA</div><div class="stat-lbl">Salaires</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico <?php echo $financial_data['net_income'] >= 0 ? 'g' : 'r'; ?>">
                    <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
                </div>
                <div><div class="stat-num" style="color:<?php echo $financial_data['net_income'] >= 0 ? 'var(--green)' : 'var(--red)'; ?>">
                        <?php echo number_format($financial_data['net_income'], 0, ',', ' '); ?> DA
                    </div><div class="stat-lbl">Résultat net</div></div>
            </div>
        </div>

        <!-- Ratios -->
        <div class="stats-row" style="display:grid; grid-template-columns:1fr 1fr; gap:22px; margin-bottom:22px;">
            <div class="card">
                <div class="card-head"><h3>Taux de dépenses</h3></div>
                <div class="card-body">
                    <div style="font-size:2rem; font-weight:700; color:var(--amber)"><?php echo round($financial_data['expense_ratio'], 1); ?>%</div>
                    <p>des revenus consacrés aux dépenses fournisseurs</p>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3>Taux de masse salariale</h3></div>
                <div class="card-body">
                    <div style="font-size:2rem; font-weight:700; color:var(--blue)"><?php echo round($financial_data['salary_ratio'], 1); ?>%</div>
                    <p>des revenus consacrés aux salaires</p>
                </div>
            </div>
        </div>

        <!-- Top patients -->
        <div class="card" style="margin-bottom:22px;">
            <div class="card-head"><h3>Top patients (revenus)</h3></div>
            <?php if (empty($revenue_by_patient)): ?>
                <div class="empty"><p>Aucune donnée sur la période</p></div>
            <?php else: ?>
                <table class="tbl">
                    <thead><tr><th>Patient</th><th>Nb factures</th><th>Total dépensé</th></tr></thead>
                    <tbody>
                    <?php foreach ($revenue_by_patient as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                            <td><?php echo $p['invoice_count']; ?></td>
                            <td style="font-weight:600;color:var(--green)"><?php echo number_format($p['total_amount'], 0, ',', ' '); ?> DA</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Factures de la période -->
        <div class="card" style="margin-bottom:22px;">
            <div class="card-head"><h3>Factures (<?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?>)</h3></div>
            <?php if ($invoices_period->num_rows === 0): ?>
                <div class="empty"><p>Aucune facture sur cette période</p></div>
            <?php else: ?>
                <table class="tbl">
                    <thead><tr><th>N° Facture</th><th>Patient</th><th>Montant</th><th>Payé</th><th>Date</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php while ($inv = $invoices_period->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars($inv['patient_name']); ?></td>
                            <td><?php echo number_format($inv['total_amount'], 0, ',', ' '); ?> DA</td>
                            <td><?php echo number_format($inv['paid_amount'], 0, ',', ' '); ?> DA</td>
                            <td><?php echo date('d/m/Y', strtotime($inv['generated_date'])); ?></td>
                            <td><span class="badge badge-<?php echo $inv['status']; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Dépenses de la période -->
        <div class="card">
            <div class="card-head"><h3>Dépenses fournisseurs (livrées)</h3></div>
            <?php if (empty($expenses_period)): ?>
                <div class="empty"><p>Aucune dépense sur cette période</p></div>
            <?php else: ?>
                <table class="tbl">
                    <thead><tr><th>N° Commande</th><th>Fournisseur</th><th>Montant</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($expenses_period as $exp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exp['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($exp['supplier_name'] ?? '—'); ?></td>
                            <td style="font-weight:600;color:var(--red)"><?php echo number_format($exp['total_amount'], 0, ',', ' '); ?> DA</td>
                            <td><?php echo date('d/m/Y', strtotime($exp['order_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr style="background:var(--surf2); font-weight:700;">
                        <td colspan="2">Total</td>
                        <td><?php echo number_format($total_expenses, 0, ',', ' '); ?> DA</td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>
</body>
</html>