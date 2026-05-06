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
    header('location: ../login.php');
    exit;
}
$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// === STATISTIQUES DU JOUR ===
// Revenus du jour (factures payées)
$stat_rev_today = safeCount($database,
        "SELECT COALESCE(SUM(total_amount),0) c FROM invoices WHERE generated_date=? AND status='paid'",
        's', [$today]);

// Paiements reçus du jour
$stat_paid_today = safeCount($database,
        "SELECT COUNT(*) c FROM invoices WHERE generated_date=? AND status='paid'",
        's', [$today]);

// Factures en attente
$stat_pending = safeCount($database,
        "SELECT COUNT(*) c FROM invoices WHERE status='unpaid'",
        's');

// === STATISTIQUES DU MOIS ===
// Revenus du mois
$stat_rev_month = safeCount($database,
        "SELECT COALESCE(SUM(total_amount),0) c FROM invoices WHERE generated_date BETWEEN ? AND ? AND status='paid'",
        'ss', [$month_start, $month_end]);

// Dépenses du mois (commandes livrées)
$stat_expenses = safeCount($database,
        "SELECT COALESCE(SUM(po.total_amount),0) c FROM purchase_orders po
     WHERE po.order_date BETWEEN ? AND ? AND po.status='delivered'",
        'ss', [$month_start, $month_end]);

// Résultat net du mois
$net_income = $stat_rev_month - $stat_expenses;

// === FACTURES RÉCENTES EN ATTENTE ===
$stmt_invoices = $database->prepare(
        "SELECT i.id, i.invoice_number, i.total_amount, i.generated_date, i.status,
            p.id patient_id, u.full_name patient_name, u.email, u.phone
     FROM invoices i
     INNER JOIN patients p ON p.id = i.patient_id
     INNER JOIN users u ON u.id = p.user_id
     WHERE i.status IN ('unpaid', 'draft')
     ORDER BY i.generated_date DESC
     LIMIT 10"
);
$invoices = null;
$db_error = '';
if ($stmt_invoices) {
    $stmt_invoices->execute();
    $invoices = $stmt_invoices->get_result();
} else {
    $db_error = $database->error;
}

// === DÉPENSES RÉCENTES (Commandes livrées) ===
$stmt_expenses = $database->prepare(
        "SELECT po.id, po.order_number, po.total_amount, po.order_date, s.name as supplier_name
     FROM purchase_orders po
     LEFT JOIN suppliers s ON s.id = po.supplier_id
     WHERE po.status='delivered'
     ORDER BY po.order_date DESC
     LIMIT 10"
);
$expenses_data = null;
if ($stmt_expenses) {
    $stmt_expenses->execute();
    $expenses_data = $stmt_expenses->get_result();
} else {
    $db_error = $database->error;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard — Comptable</title>
    <link rel="stylesheet" href="comptable.css">
    <style>
        /* Styles supplémentaires pour les cartes revenus/dépenses */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 22px;
        }

        .financial-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            overflow: hidden;
            box-shadow: var(--sh);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .financial-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .financial-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .financial-card-header .icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .financial-card-header .icon svg {
            width: 20px;
            height: 20px;
            stroke: white;
            fill: none;
            stroke-width: 2;
        }

        .financial-card-header .icon.revenue {
            background: linear-gradient(135deg, var(--green) 0%, var(--green-d) 100%);
        }

        .financial-card-header .icon.expense {
            background: linear-gradient(135deg, var(--red) 0%, #9e1e16 100%);
        }

        .financial-card-header h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            flex: 1;
        }

        .financial-card-body {
            padding: 25px 20px;
            text-align: center;
        }

        .financial-card-body .amount {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .financial-card-body .amount.revenue {
            color: var(--green);
        }

        .financial-card-body .amount.expense {
            color: var(--red);
        }

        .financial-card-body .period {
            font-size: 0.75rem;
            color: var(--text2);
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .financial-card-footer {
            padding: 12px 20px;
            background: var(--surf2);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
        }

        .financial-card-footer .trend-up {
            color: var(--green);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .financial-card-footer .trend-down {
            color: var(--red);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .financial-card-footer .badge-info {
            background: var(--blue-l);
            color: var(--blue);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Animation pour les chiffres */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .financial-card-body .amount {
            animation: fadeInUp 0.5s ease-out;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .financial-card-body .amount {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Tableau de bord financier</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="payments.php" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Enregistrer paiement
            </a>
        </div>
    </div>
    <div class="page-body">

        <?php if ($db_error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Erreur BD : <?php echo htmlspecialchars($db_error); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Principales -->
        <div class="stats">
            <div class="revenue-card">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <div>
                    <div class="r-num"><?php echo number_format($stat_rev_today, 0, ',', ' '); ?> DA</div>
                    <div class="r-lbl">Revenus aujourd'hui</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico a"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="15" x2="15" y2="15"/></svg></div>
                <div><div class="stat-num"><?php echo $stat_paid_today; ?></div><div class="stat-lbl">Paiements aujourd'hui</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico b"><svg viewBox="0 0 24 24"><path d="M19 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2z"/><rect x="3" y="5" width="18" height="3"/></svg></div>
                <div><div class="stat-num"><?php echo $stat_pending; ?></div><div class="stat-lbl">Factures en attente</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico g"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
                <div><div class="stat-num"><?php echo number_format($net_income, 0, ',', ' '); ?> DA</div><div class="stat-lbl">Résultat net (mois)</div></div>
            </div>
        </div>

        <!-- Statistiques Mensuelles - Version améliorée -->
        <div class="stats-row">
            <!-- Carte Revenus du mois -->
            <div class="financial-card">
                <div class="financial-card-header">
                    <div class="icon revenue">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <h3>Revenus du mois</h3>
                    <span class="badge-info">Mois en cours</span>
                </div>
                <div class="financial-card-body">
                    <div class="amount revenue">
                        <?php echo number_format($stat_rev_month, 0, ',', ' '); ?> DA
                    </div>
                    <div class="period">
                        <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Depuis le 1er du mois
                    </div>
                </div>
                <div class="financial-card-footer">
                    <span>💰 Total encaissé</span>
                    <?php if ($stat_rev_month > 0): ?>
                        <span class="trend-up">
                            <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none">
                                <polyline points="18 15 12 9 6 15"/>
                            </svg>
                            +<?php echo number_format($stat_rev_month, 0, ',', ' '); ?> DA
                        </span>
                    <?php else: ?>
                        <span class="trend-down">
                            <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                            0 DA
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Carte Dépenses du mois -->
            <div class="financial-card">
                <div class="financial-card-header">
                    <div class="icon expense">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="16"/>
                            <line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                    </div>
                    <h3>Dépenses du mois</h3>
                    <span class="badge-info">Charges</span>
                </div>
                <div class="financial-card-body">
                    <div class="amount expense">
                        <?php echo number_format($stat_expenses, 0, ',', ' '); ?> DA
                    </div>
                    <div class="period">
                        <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Commandes livrées
                    </div>
                </div>
                <div class="financial-card-footer">
                    <span>📦 Achats fournisseurs</span>
                    <span class="badge-info" style="background: var(--red-l); color: var(--red);">
                        <?php echo number_format($stat_expenses, 0, ',', ' '); ?> DA
                    </span>
                </div>
            </div>
        </div>

        <!-- Factures en attente -->
        <div class="card">
            <div class="card-head">
                <h3>Factures en attente de paiement</h3>
                <a href="invoices.php" class="btn btn-secondary btn-sm">Voir tout →</a>
            </div>
            <?php if (!$invoices || $invoices->num_rows === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <h3>Aucune facture en attente</h3>
                    <p><a href="invoices.php" style="color:var(--green);font-weight:600">Générer une nouvelle facture →</a></p>
                </div>
            <?php else: ?>
            <table class="tbl">
                <thead>
                <tr>
                    <th>#Facture</th>
                    <th>Patient</th>
                    <th>Montant</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($r = $invoices->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:600"><?php echo htmlspecialchars($r['invoice_number']); ?></td>
                        <td><?php echo htmlspecialchars($r['patient_name']); ?></td>
                        <td style="font-weight:600"><?php echo number_format($r['total_amount'], 0, ',', ' '); ?> DA</td>
                        <td><?php echo date('d/m/Y', strtotime($r['generated_date'])); ?></td>
                        <td><span class="badge badge-<?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                        <td>
                            <div class="tbl-actions">
                                <a href="payments.php?invoice_id=<?php echo $r['id']; ?>" class="btn btn-blue btn-sm">Paiement</a>
                                <a href="invoice-detail.php?id=<?php echo $r['id']; ?>" class="btn btn-secondary btn-sm">Détail</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
                </tr>
                <?php endif; ?>
        </div>

        <!-- Dépenses récentes -->
        <div class="card">
            <div class="card-head">
                <h3>Commandes récentes</h3>
                <a href="purchase-orders.php" class="btn btn-secondary btn-sm">Gérer commandes →</a>
            </div>
            <?php if (!$expenses_data || $expenses_data->num_rows === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                    <h3>Aucune commande enregistrée</h3>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Fournisseur</th>
                        <th>Montant</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($e = $expenses_data->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($e['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($e['supplier_name'] ?? '—'); ?></td>
                            <td style="font-weight:600"><?php echo number_format($e['total_amount'], 0, ',', ' '); ?> DA</td>
                            <td><?php echo date('d/m/Y', strtotime($e['order_date'])); ?></td>
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