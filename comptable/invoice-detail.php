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
    header('Location: ../unauthorized.php');
    exit;
}

$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoice_id <= 0) {
    header('Location: invoices.php');
    exit;
}

$message = '';
$error = '';

// === RÉCUPÉRATION DES DÉTAILS DE LA FACTURE ===
$stmt = $database->prepare("
    SELECT i.*, 
           p.id as patient_id, p.uhid, p.dob, p.gender, p.blood_type,
           u.full_name as patient_name, u.email, u.phone, u.address,
           a.appointment_date, a.appointment_time, a.type as appointment_type, a.doctor_id,
           d.user_id as doctor_user_id, doc.full_name as doctor_name
    FROM invoices i
    INNER JOIN patients p ON p.id = i.patient_id
    INNER JOIN users u ON u.id = p.user_id
    LEFT JOIN appointments a ON a.id = i.appointment_id
    LEFT JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN users doc ON doc.id = d.user_id
    WHERE i.id = ?
");
$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Récupérer les articles de la facture
$items = getInvoiceItems($database, $invoice_id);

// Récupérer les paiements effectués
$payments = getInvoicePayments($database, $invoice_id);

// Calculer les totaux
$total_paid = 0;
foreach ($payments as $payment) {
    $total_paid += $payment['amount'];
}
$remaining = $invoice['total_amount'] - $total_paid;

// Vérifier si le patient a des allergies
$has_allergies = !empty($invoice['allergies']) && $invoice['allergies'] !== 'Aucune';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Facture #<?php echo htmlspecialchars($invoice['invoice_number']); ?> — Comptable</title>
    <link rel="stylesheet" href="comptable.css">
    <style>
        .invoice-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* En-tête de la facture */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .invoice-logo h2 {
            color: var(--green);
            margin-bottom: 5px;
        }

        .invoice-logo p {
            color: var(--text2);
            font-size: 0.8rem;
        }

        .invoice-info {
            text-align: right;
        }

        .invoice-info .invoice-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--green);
        }

        .invoice-info .invoice-date {
            color: var(--text2);
            font-size: 0.8rem;
        }

        .invoice-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 8px;
        }

        .status-paid {
            background: var(--green-l);
            color: var(--green);
        }

        .status-unpaid {
            background: var(--red-l);
            color: var(--red);
        }

        .status-draft {
            background: var(--amber-l);
            color: var(--amber);
        }

        /* Sections client et médecin */
        .parties-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 25px;
        }

        .party-card {
            background: var(--surf2);
            border-radius: var(--r);
            padding: 15px;
        }

        .party-card h4 {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            color: var(--green);
        }

        .party-card p {
            font-size: 0.8rem;
            margin: 5px 0;
        }

        .party-card .label {
            color: var(--text2);
            width: 100px;
            display: inline-block;
        }

        /* Allergie alerte */
        .allergy-alert {
            background: var(--red-l);
            border-left: 4px solid var(--red);
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: var(--rs);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .allergy-alert svg {
            flex-shrink: 0;
        }

        .allergy-alert strong {
            color: var(--red);
        }

        /* Tableau des articles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .items-table th {
            background: var(--surf2);
            padding: 10px 12px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
            border-bottom: 2px solid var(--border);
        }

        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .total-section {
            background: var(--surf2);
            border-radius: var(--r);
            padding: 15px 20px;
            margin-bottom: 25px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .total-line.total {
            border-top: 2px solid var(--border);
            margin-top: 8px;
            padding-top: 12px;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .total-line .label {
            color: var(--text2);
        }

        .total-line .amount {
            font-weight: 600;
        }

        .total-line.total .amount {
            color: var(--green);
            font-size: 1.3rem;
        }

        .remaining-amount {
            color: var(--red);
            font-weight: 700;
        }

        /* Paiements */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .payments-table th {
            background: var(--surf2);
            padding: 8px 10px;
            text-align: left;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text2);
        }

        .payments-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
        }

        /* Actions */
        .invoice-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        /* Print styles */
        @media print {
            .sidebar, .topbar, .invoice-actions, .no-print {
                display: none !important;
            }
            .main {
                margin-left: 0;
            }
            .page-body {
                padding: 0;
            }
            .invoice-container {
                max-width: 100%;
            }
            .allergy-alert {
                background: #FFF0F0;
                border-left: 4px solid #B83228;
            }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar no-print">
        <span class="topbar-title">Détail de la facture</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14">
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <path d="M6 9V3h12v6"/>
                    <rect x="6" y="15" width="12" height="6" rx="2"/>
                </svg>
                Imprimer
            </button>
            <a href="invoices.php" class="btn btn-secondary btn-sm">
                ← Retour
            </a>
        </div>
    </div>
    <div class="page-body">
        <div class="invoice-container">

            <!-- Alerte allergies -->
            <?php if ($has_allergies): ?>
                <div class="allergy-alert">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--red)" fill="none" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <div>
                        <strong>⚠️ Allergies connues :</strong> <?php echo htmlspecialchars($invoice['allergies']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- En-tête facture -->
            <div class="invoice-header">
                <div class="invoice-logo">
                    <h2>MedUnity</h2>
                    <p>Facture de consultation</p>
                </div>
                <div class="invoice-info">
                    <div class="invoice-number">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                    <div class="invoice-date">Date : <?php echo date('d/m/Y', strtotime($invoice['generated_date'])); ?></div>
                    <div>
                        <span class="invoice-status status-<?php echo $invoice['status']; ?>">
                            <?php
                            $status_labels = [
                                'paid' => '✓ PAYÉE',
                                'unpaid' => '⏳ IMPAYÉE',
                                'draft' => '📝 BROUILLON',
                                'pending_insurance' => '🏥 EN ATTENTE ASSURANCE',
                                'cancelled' => '✗ ANNULÉE'
                            ];
                            echo $status_labels[$invoice['status']] ?? strtoupper($invoice['status']);
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Informations patient et médecin -->
            <div class="parties-section">
                <div class="party-card">
                    <h4>👤 Patient</h4>
                    <p><span class="label">Nom :</span> <?php echo htmlspecialchars($invoice['patient_name']); ?></p>
                    <p><span class="label">Email :</span> <?php echo htmlspecialchars($invoice['email']); ?></p>
                    <p><span class="label">Téléphone :</span> <?php echo htmlspecialchars($invoice['phone'] ?? '—'); ?></p>
                    <p><span class="label">Adresse :</span> <?php echo htmlspecialchars($invoice['address'] ?? '—'); ?></p>
                    <?php if ($invoice['uhid']): ?>
                        <p><span class="label">N° dossier :</span> <?php echo htmlspecialchars($invoice['uhid']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="party-card">
                    <h4>👨‍⚕️ Médecin</h4>
                    <p><span class="label">Nom :</span> <?php echo htmlspecialchars($invoice['doctor_name'] ?? '—'); ?></p>
                    <p><span class="label">Date RDV :</span>
                        <?php
                        if ($invoice['appointment_date']) {
                            echo date('d/m/Y', strtotime($invoice['appointment_date'])) . ' à ' . substr($invoice['appointment_time'], 0, 5);
                        } else {
                            echo '—';
                        }
                        ?>
                    </p>
                    <p><span class="label">Type RDV :</span> <?php echo ucfirst($invoice['appointment_type'] ?? 'Consultation'); ?></p>
                </div>
            </div>

            <!-- Tableau des articles -->
            <h4 style="margin-bottom: 10px;">📋 Détail des prestations</h4>
            <table class="items-table">
                <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantité</th>
                    <th style="text-align: right;">Prix unitaire</th>
                    <th style="text-align: right;">Montant</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td style="text-align: right;"><?php echo number_format($item['unit_price'], 0, ',', ' '); ?> DA</td>
                        <td style="text-align: right; font-weight: 600;"><?php echo number_format($item['amount'], 0, ',', ' '); ?> DA</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totaux -->
            <div class="total-section">
                <div class="total-line">
                    <span class="label">Sous-total</span>
                    <span class="amount"><?php echo number_format($invoice['total_amount'], 0, ',', ' '); ?> DA</span>
                </div>
                <div class="total-line">
                    <span class="label">Remise</span>
                    <span class="amount">0 DA</span>
                </div>
                <div class="total-line total">
                    <span class="label">TOTAL À PAYER</span>
                    <span class="amount"><?php echo number_format($invoice['total_amount'], 0, ',', ' '); ?> DA</span>
                </div>
                <div class="total-line">
                    <span class="label">Déjà payé</span>
                    <span class="amount" style="color: var(--green);"><?php echo number_format($total_paid, 0, ',', ' '); ?> DA</span>
                </div>
                <div class="total-line">
                    <span class="label">Reste à payer</span>
                    <span class="amount remaining-amount"><?php echo number_format($remaining, 0, ',', ' '); ?> DA</span>
                </div>
            </div>

            <!-- Historique des paiements -->
            <?php if (!empty($payments)): ?>
                <h4 style="margin-bottom: 10px;">💰 Historique des paiements</h4>
                <table class="payments-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Mode</th>
                        <th style="text-align: right;">Montant</th>
                        <th>Reçu par</th>
                        <th>Notes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo ucfirst($payment['method']); ?></td>
                            <td style="text-align: right; font-weight: 600; color: var(--green);">
                                <?php echo number_format($payment['amount'], 0, ',', ' '); ?> DA
                            </td>
                            <td><?php echo htmlspecialchars($payment['received_by_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($payment['notes'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Notes -->
            <?php if ($invoice['notes']): ?>
                <div style="margin-top: 20px; padding: 12px; background: var(--surf2); border-radius: var(--rs);">
                    <strong>📝 Notes :</strong>
                    <p style="margin-top: 5px; font-size: 0.8rem;"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="invoice-actions no-print">
                <?php if ($invoice['status'] != 'paid' && $remaining > 0): ?>
                    <a href="payments.php?invoice_id=<?php echo $invoice_id; ?>" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="14" height="14">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        Enregistrer un paiement
                    </a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24" width="14" height="14">
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <path d="M6 9V3h12v6"/>
                        <rect x="6" y="15" width="12" height="6" rx="2"/>
                    </svg>
                    Imprimer la facture
                </button>
                <a href="invoices.php" class="btn btn-secondary">
                    ← Retour à la liste
                </a>
            </div>

        </div>
    </div>
</div>
</body>
</html>