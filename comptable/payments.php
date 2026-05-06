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
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : (isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : null);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'] ?? 'cash';
    $notes = $_POST['notes'] ?? '';

    if ($amount <= 0) {
        $error = 'Le montant doit être supérieur à 0.';
    } elseif (empty($method)) {
        $error = 'Veuillez sélectionner un mode de paiement.';
    } else {
        $result = recordPayment($database, $invoice_id, $amount, $method, $_SESSION['user_id'], $notes);

        if ($result['success']) {
            $message = 'Paiement enregistré avec succès !';
            $invoice_id = null;
        } else {
            $error = $result['message'];
        }
    }
}

// Récupérer les détails de la facture si sélectionnée
$invoice_details = null;
$remaining = 0;
if ($invoice_id) {
    $stmt = $database->prepare("
        SELECT i.*, u.full_name as patient_name, u.email, u.phone
        FROM invoices i
        INNER JOIN patients p ON p.id = i.patient_id
        INNER JOIN users u ON u.id = p.user_id
        WHERE i.id = ?
    ");
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    $invoice_details = $stmt->get_result()->fetch_assoc();
    if ($invoice_details) {
        $remaining = $invoice_details['total_amount'] - $invoice_details['paid_amount'];
    }
}

// Liste des factures avec reste à payer
$stmt = $database->prepare("
    SELECT i.id, i.invoice_number, i.total_amount, i.paid_amount, i.status, u.full_name as patient_name,
           (i.total_amount - i.paid_amount) as remaining
    FROM invoices i
    INNER JOIN patients p ON p.id = i.patient_id
    INNER JOIN users u ON u.id = p.user_id
    WHERE i.status IN ('unpaid', 'draft') 
       OR (i.status = 'paid' AND i.paid_amount < i.total_amount)
    ORDER BY i.generated_date DESC
    LIMIT 50
");
$stmt->execute();
$unpaid_invoices = $stmt->get_result();

// Derniers paiements
$stmt = $database->prepare("
    SELECT p.*, i.invoice_number, u.full_name as received_by_name
    FROM payments p
    INNER JOIN invoices i ON i.id = p.invoice_id
    LEFT JOIN users u ON u.id = p.received_by
    ORDER BY p.payment_date DESC
    LIMIT 20
");
$stmt->execute();
$recent_payments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiements - Comptable</title>
    <link rel="stylesheet" href="comptable.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
        .invoice-summary {
            background: var(--surf2);
            padding: 15px;
            border-radius: var(--rs);
            margin-bottom: 20px;
        }
        .invoice-summary p {
            margin: 8px 0;
        }
        .invoice-summary .label {
            font-weight: 600;
            color: var(--text2);
            width: 120px;
            display: inline-block;
        }
        .remaining-amount {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--red);
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Enregistrement des paiements</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="invoices.php" class="btn btn-secondary btn-sm">
                ← Voir les factures
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

        <div class="stats-row">
            <!-- Formulaire de paiement -->
            <div class="card">
                <div class="card-head"><h3>💰 Enregistrer un paiement</h3></div>
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <div class="form-group">
                            <label>Sélectionner une facture *</label>
                            <select name="invoice_id" id="invoice_select" class="input" required>
                                <option value="">-- Choisir une facture --</option>
                                <?php
                                if ($unpaid_invoices->num_rows > 0) {
                                    $unpaid_invoices->data_seek(0);
                                }
                                while ($inv = $unpaid_invoices->fetch_assoc()):
                                    $selected = ($invoice_id == $inv['id']) ? 'selected' : '';
                                    $remaining_inv = $inv['total_amount'] - $inv['paid_amount'];
                                    ?>
                                    <option value="<?php echo $inv['id']; ?>" <?php echo $selected; ?> data-remaining="<?php echo $remaining_inv; ?>">
                                        <?php echo htmlspecialchars($inv['invoice_number']); ?> -
                                        <?php echo htmlspecialchars($inv['patient_name']); ?> -
                                        Reste: <?php echo number_format($remaining_inv, 0, ',', ' '); ?> DA
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Affichage des détails de la facture sélectionnée -->
                        <?php if ($invoice_details): ?>
                            <div class="invoice-summary">
                                <p><span class="label">Patient :</span> <?php echo htmlspecialchars($invoice_details['patient_name']); ?></p>
                                <p><span class="label">Email :</span> <?php echo htmlspecialchars($invoice_details['email']); ?></p>
                                <p><span class="label">Téléphone :</span> <?php echo htmlspecialchars($invoice_details['phone'] ?? '—'); ?></p>
                                <p><span class="label">N° Facture :</span> <?php echo htmlspecialchars($invoice_details['invoice_number']); ?></p>
                                <p><span class="label">Total :</span> <?php echo number_format($invoice_details['total_amount'], 0, ',', ' '); ?> DA</p>
                                <p><span class="label">Déjà payé :</span> <?php echo number_format($invoice_details['paid_amount'], 0, ',', ' '); ?> DA</p>
                                <p><span class="label">Reste à payer :</span> <span class="remaining-amount"><?php echo number_format($remaining, 0, ',', ' '); ?> DA</span></p>
                            </div>

                            <?php if ($remaining > 0): ?>
                                <div class="form-group">
                                    <label>Montant à payer *</label>
                                    <input type="number" name="amount" id="amount_input" class="input" step="0.01" min="0.01" max="<?php echo $remaining; ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Mode de paiement *</label>
                                    <select name="method" class="input" required>
                                        <option value="">-- Sélectionner --</option>
                                        <option value="cash">💰 Espèces</option>
                                        <option value="card">💳 Carte bancaire</option>
                                        <option value="bank_transfer">🏦 Virement bancaire</option>
                                        <option value="check">📝 Chèque</option>
                                        <option value="insurance">🏥 Assurance</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Notes (optionnel)</label>
                                    <textarea name="notes" class="input" rows="2" placeholder="Référence de transaction, numéro de chèque..."></textarea>
                                </div>

                                <button type="submit" name="submit_payment" class="btn btn-primary">
                                    💾 Enregistrer le paiement
                                </button>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    ✅ Cette facture est entièrement payée.
                                </div>
                            <?php endif; ?>
                        <?php elseif ($invoice_id): ?>
                            <div class="alert alert-warning">
                                ⚠️ Facture non trouvée ou déjà entièrement payée.
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Derniers paiements -->
            <div class="card">
                <div class="card-head"><h3>📋 Historique des paiements</h3></div>
                <?php if ($recent_payments->num_rows === 0): ?>
                    <div class="empty">
                        <svg viewBox="0 0 24 24" width="40" height="40">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <h3>Aucun paiement enregistré</h3>
                    </div>
                <?php else: ?>
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Facture</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Date</th>
                            <th>Reçu par</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($p = $recent_payments->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['invoice_number']); ?></strong></td>
                                <td style="font-weight:600;color:var(--green)"><?php echo number_format($p['amount'], 0, ',', ' '); ?> DA</td>
                                <td><?php echo ucfirst($p['method']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($p['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($p['received_by_name'] ?? '—'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
            // Redirection lors du changement de sélection
            const invoiceSelect = document.getElementById('invoice_select');
            if (invoiceSelect) {
                invoiceSelect.addEventListener('change', function() {
                    const selectedValue = this.value;
                    if (selectedValue) {
                        window.location.href = 'payments.php?invoice_id=' + selectedValue;
                    } else {
                        window.location.href = 'payments.php';
                    }
                });
            }

            // Validation du montant
            const amountInput = document.getElementById('amount_input');
            if (amountInput) {
                amountInput.addEventListener('input', function() {
                    let max = parseFloat(this.max);
                    let value = parseFloat(this.value);
                    if (value > max) {
                        this.value = max;
                    }
                    if (isNaN(value) || value < 0.01) {
                        this.value = '';
                    }
                });
            }

            // Remplir automatiquement le montant avec le reste à payer
            function fillRemainingAmount() {
                if (amountInput) {
                    const max = parseFloat(amountInput.max);
                    if (max > 0) {
                        amountInput.value = max;
                    }
                }
            }

            // Appeler la fonction si un bouton "Tout payer" est ajouté
        </script>

        <!-- Bouton pour payer la totalité -->
        <?php if ($invoice_details && $remaining > 0): ?>
            <div style="margin-top: 10px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="fillRemainingAmount()">
                    💰 Payer la totalité (<?php echo number_format($remaining, 0, ',', ' '); ?> DA)
                </button>
            </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>