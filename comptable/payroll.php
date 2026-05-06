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

$message = '';
$error = '';
$month = $_GET['month'] ?? date('Y-m');
$month_start = $month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Récupérer tous les employés actifs avec leurs contrats
$stmt = $database->prepare("
    SELECT u.id, u.full_name, u.email, u.phone, 
           ec.id as contract_id, ec.salary, ec.contract_type, ec.start_date, ec.end_date,
           d.consultation_fee, d.specialty_id
    FROM users u
    INNER JOIN employee_contracts ec ON ec.user_id = u.id
    LEFT JOIN doctors d ON d.user_id = u.id
    WHERE u.is_active = 1 
    AND (ec.end_date IS NULL OR ec.end_date >= ?)
    ORDER BY u.full_name
");
$stmt->bind_param('s', $month_start);
$stmt->execute();
$employees = $stmt->get_result();

// Calculer le total des salaires
$total_salaries = 0;
$payroll_data = [];

while ($emp = $employees->fetch_assoc()) {
    $salary = floatval($emp['salary'] ?? 0);
    $total_salaries += $salary;

    // Calculer les charges (exemple: 25% de charges patronales)
    $social_charges = $salary * 0.25;
    $total_cost = $salary + $social_charges;

    $payroll_data[] = [
        'id' => $emp['id'],
        'full_name' => $emp['full_name'],
        'email' => $emp['email'],
        'contract_type' => $emp['contract_type'],
        'base_salary' => $salary,
        'social_charges' => $social_charges,
        'total_cost' => $total_cost
    ];
}

// Statistiques
$active_employees = count($payroll_data);
$total_charges = $total_salaries * 0.25;
$grand_total = $total_salaries + $total_charges;

// Récupérer les paies précédentes
$stmt = $database->prepare("
    SELECT * FROM financial_reports 
    WHERE report_type = 'monthly' 
    ORDER BY report_date DESC 
    LIMIT 12
");
$stmt->execute();
$previous_payrolls = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Paie mensuelle - Comptable</title>
    <link rel="stylesheet" href="comptable.css">
    <style>
        .payroll-summary {
            background: linear-gradient(135deg, var(--green) 0%, var(--green-d) 100%);
            color: white;
            border-radius: var(--r);
            padding: 20px;
            margin-bottom: 22px;
        }
        .payroll-summary h3 { margin-bottom: 15px; font-size: 1rem; opacity: 0.9; }
        .payroll-numbers { display: flex; gap: 30px; flex-wrap: wrap; }
        .payroll-number { flex: 1; }
        .payroll-number .value { font-size: 1.8rem; font-weight: 700; }
        .payroll-number .label { font-size: 0.75rem; opacity: 0.8; margin-top: 5px; }
        .badge-cdi { background: var(--green-l); color: var(--green); }
        .badge-cdd { background: var(--blue-l); color: var(--blue); }
        .badge-stage { background: var(--purple-l); color: var(--purple); }
        .badge-freelance { background: var(--amber-l); color: var(--amber); }
        .print-hide { }
        @media print {
            .print-hide { display: none; }
            .card { break-inside: avoid; }
            .payroll-summary { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar print-hide">
        <span class="topbar-title">Paie mensuelle</span>
        <div class="topbar-right">
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3h12v6"/><rect x="6" y="15" width="12" height="6" rx="2"/></svg>
                Imprimer
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

        <!-- Sélecteur de mois -->
        <div class="filter-bar print-hide">
            <form method="GET" style="display:flex; gap:10px; align-items:center;">
                <label>Période :</label>
                <input type="month" name="month" class="input" value="<?php echo $month; ?>" style="width:auto;">
                <button type="submit" class="btn btn-primary">Afficher</button>
            </form>
        </div>

        <!-- Résumé de la paie -->
        <div class="payroll-summary">
            <h3>📊 Bilan du mois de <?php echo date('F Y', strtotime($month_start)); ?></h3>
            <div class="payroll-numbers">
                <div class="payroll-number">
                    <div class="value"><?php echo $active_employees; ?></div>
                    <div class="label">Employés actifs</div>
                </div>
                <div class="payroll-number">
                    <div class="value"><?php echo number_format($total_salaries, 0, ',', ' '); ?> DA</div>
                    <div class="label">Masse salariale brute</div>
                </div>
                <div class="payroll-number">
                    <div class="value"><?php echo number_format($total_charges, 0, ',', ' '); ?> DA</div>
                    <div class="label">Charges sociales (25%)</div>
                </div>
                <div class="payroll-number">
                    <div class="value"><?php echo number_format($grand_total, 0, ',', ' '); ?> DA</div>
                    <div class="label">Coût total employeur</div>
                </div>
            </div>
        </div>

        <!-- Détail des salaires -->
        <div class="card">
            <div class="card-head">
                <h3>Détail des salaires - <?php echo date('F Y', strtotime($month_start)); ?></h3>
                <button onclick="exportToCSV()" class="btn btn-secondary btn-sm print-hide">
                    📎 Exporter CSV
                </button>
            </div>
            <?php if (empty($payroll_data)): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                    <h3>Aucun employé avec contrat actif</h3>
                    <p>Veuillez d'abord créer des contrats dans la section RH.</p>
                </div>
            <?php else: ?>
                <table class="tbl" id="payrollTable">
                    <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Email</th>
                        <th>Type contrat</th>
                        <th>Salaire brut</th>
                        <th>Charges (25%)</th>
                        <th>Coût total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payroll_data as $emp):
                        $contract_class = '';
                        switch($emp['contract_type']) {
                            case 'CDI': $contract_class = 'badge-cdi'; break;
                            case 'CDD': $contract_class = 'badge-cdd'; break;
                            case 'stage': $contract_class = 'badge-stage'; break;
                            case 'freelance': $contract_class = 'badge-freelance'; break;
                            default: $contract_class = 'badge-noshow';
                        }
                        ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($emp['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><span class="badge <?php echo $contract_class; ?>"><?php echo strtoupper($emp['contract_type']); ?></span></td>
                            <td style="color:var(--green); font-weight:600"><?php echo number_format($emp['base_salary'], 0, ',', ' '); ?> DA</td>
                            <td style="color:var(--amber);"><?php echo number_format($emp['social_charges'], 0, ',', ' '); ?> DA</td>
                            <td style="color:var(--blue); font-weight:600"><?php echo number_format($emp['total_cost'], 0, ',', ' '); ?> DA</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background:var(--surf2); font-weight:700;">
                    <tr>
                        <td colspan="3">TOTAUX</td>
                        <td><?php echo number_format($total_salaries, 0, ',', ' '); ?> DA</td>
                        <td><?php echo number_format($total_charges, 0, ',', ' '); ?> DA</td>
                        <td><?php echo number_format($grand_total, 0, ',', ' '); ?> DA</td>
                    </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>

        <!-- Historique des paies -->
        <div class="card print-hide">
            <div class="card-head">
                <h3>Historique des paies</h3>
            </div>
            <?php if ($previous_payrolls->num_rows === 0): ?>
                <div class="empty"><p>Aucun historique disponible</p></div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>Mois</th>
                        <th>Revenus</th>
                        <th>Dépenses</th>
                        <th>Masse salariale</th>
                        <th>Résultat net</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($prev = $previous_payrolls->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($prev['report_date'])); ?></td>
                            <td><?php echo number_format($prev['total_revenue'] ?? 0, 0, ',', ' '); ?> DA</td>
                            <td><?php echo number_format($prev['total_expenses'] ?? 0, 0, ',', ' '); ?> DA</td>
                            <td style="color:var(--green); font-weight:600"><?php echo number_format($prev['total_salaries'] ?? 0, 0, ',', ' '); ?> DA</td>
                            <td style="color:<?php echo ($prev['net_profit'] ?? 0) >= 0 ? 'var(--green)' : 'var(--red)'; ?>">
                                <?php echo number_format($prev['net_profit'] ?? 0, 0, ',', ' '); ?> DA
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Notes explicatives -->
        <div class="card print-hide">
            <div class="card-head">
                <h3>Informations</h3>
            </div>
            <div class="card-body">
                <ul style="margin-left: 20px; color: var(--text2);">
                    <li>Les charges sociales sont calculées à 25% du salaire brut (taux standard).</li>
                    <li>Seuls les employés avec un contrat actif sont inclus (date de fin non dépassée).</li>
                    <li>Les médecins ayant des honoraires variables ne sont pas inclus dans cette liste.</li>
                    <li>Pour modifier un salaire, allez dans la page <strong>Salaires</strong>.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<script>
    function exportToCSV() {
        <?php if (!empty($payroll_data)): ?>
        let csv = "Employé;Email;Type contrat;Salaire brut (DA);Charges sociales (DA);Coût total (DA)\n";
        <?php foreach ($payroll_data as $emp): ?>
        csv += "<?php echo addslashes($emp['full_name']); ?>;";
        csv += "<?php echo addslashes($emp['email']); ?>;";
        csv += "<?php echo $emp['contract_type']; ?>;";
        csv += "<?php echo $emp['base_salary']; ?>;";
        csv += "<?php echo $emp['social_charges']; ?>;";
        csv += "<?php echo $emp['total_cost']; ?>;\n";
        <?php endforeach; ?>
        csv += "TOTAUX;;;<?php echo $total_salaries; ?>;<?php echo $total_charges; ?>;<?php echo $grand_total; ?>\n";

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.setAttribute('download', 'paie_<?php echo $month; ?>.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        <?php endif; ?>
    }
</script>

</body>
</html>