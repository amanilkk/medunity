<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
require_once 'functions.php';

// Vérifier que l'utilisateur est un comptable
if ($_SESSION['role'] !== 'comptable') {
    header('Location: ../login.php');
    exit;
}

// Filtres
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Requête avec filtres
$sql = "SELECT i.id, i.invoice_number, i.total_amount, i.paid_amount, i.status, i.generated_date,
        p.id as patient_id, u.full_name as patient_name
        FROM invoices i
        INNER JOIN patients p ON p.id = i.patient_id
        INNER JOIN users u ON u.id = p.user_id
        WHERE 1=1";

$params = [];
$types = '';

if ($status_filter !== 'all') {
    $sql .= " AND i.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $sql .= " AND (i.invoice_number LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$sql .= " ORDER BY i.generated_date DESC";

$stmt = $database->prepare($sql);
if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$invoices = $stmt->get_result();

// Statistiques
$total_unpaid = safeCount($database, "SELECT COUNT(*) c FROM invoices WHERE status IN ('unpaid', 'draft')", 's');
$total_paid = safeCount($database, "SELECT COUNT(*) c FROM invoices WHERE status = 'paid'", 's');
$total_amount_unpaid = safeCount($database, "SELECT COALESCE(SUM(total_amount - paid_amount),0) c FROM invoices WHERE status IN ('unpaid', 'draft')", 's');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Factures - Comptable</title>
    <link rel="stylesheet" href="comptable.css">
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des factures</span>
        <div class="topbar-right">
            <a href="generate-invoice.php" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouvelle facture
            </a>
        </div>
    </div>
    <div class="page-body">

        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <div class="stat-ico b"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                <div><div class="stat-num"><?php echo $total_unpaid; ?></div><div class="stat-lbl">Factures impayées</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico g"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div><div class="stat-num"><?php echo $total_paid; ?></div><div class="stat-lbl">Factures payées</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico a"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                <div><div class="stat-num"><?php echo number_format($total_amount_unpaid, 0, ',', ' '); ?> DA</div><div class="stat-lbl">Montant impayé</div></div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filter-bar">
            <label>Filtrer :</label>
            <a href="?status=all" class="btn btn-sm <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-secondary'; ?>">Toutes</a>
            <a href="?status=unpaid" class="btn btn-sm <?php echo $status_filter == 'unpaid' ? 'btn-primary' : 'btn-secondary'; ?>">Impayées</a>
            <a href="?status=paid" class="btn btn-sm <?php echo $status_filter == 'paid' ? 'btn-primary' : 'btn-secondary'; ?>">Payées</a>
            <a href="?status=draft" class="btn btn-sm <?php echo $status_filter == 'draft' ? 'btn-primary' : 'btn-secondary'; ?>">Brouillons</a>

            <form method="GET" style="margin-left:auto; display:flex; gap:8px;">
                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                <input type="text" name="search" class="input" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>" style="width:200px;">
                <button type="submit" class="btn btn-secondary btn-sm">🔍</button>
            </form>
        </div>

        <!-- Tableau des factures -->
        <div class="card">
            <div class="card-head">
                <h3>Liste des factures</h3>
            </div>
            <?php if ($invoices->num_rows === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <h3>Aucune facture trouvée</h3>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>N° Facture</th><th>Patient</th><th>Montant</th><th>Payé</th><th>Reste</th><th>Date</th><th>Statut</th><th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $invoices->fetch_assoc()):
                        $remaining = $row['total_amount'] - $row['paid_amount'];
                        $status_class = '';
                        if ($row['status'] == 'paid') $status_class = 'badge-paid';
                        elseif ($row['status'] == 'unpaid') $status_class = 'badge-pending';
                        else $status_class = 'badge-noshow';
                        ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                            <td><?php echo number_format($row['total_amount'], 0, ',', ' '); ?> DA</td>
                            <td><?php echo number_format($row['paid_amount'], 0, ',', ' '); ?> DA</td>
                            <td style="color:var(--red);font-weight:600"><?php echo number_format($remaining, 0, ',', ' '); ?> DA</td>
                            <td><?php echo date('d/m/Y', strtotime($row['generated_date'])); ?></td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <td>
                                <div class="tbl-actions">
                                    <a href="invoice-detail.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm">Détail</a>
                                    <?php if ($row['status'] != 'paid'): ?>
                                        <a href="payments.php?invoice_id=<?php echo $row['id']; ?>" class="btn btn-blue btn-sm">Paiement</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>