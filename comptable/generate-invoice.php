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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_invoice') {
    $patient_id = intval($_POST['patient_id']);
    $appointment_id = !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
    $notes = $_POST['notes'] ?? '';

    // Récupérer les services
    $services = [];
    $descriptions = $_POST['service_description'] ?? [];
    $prices = $_POST['service_price'] ?? [];

    for ($i = 0; $i < count($descriptions); $i++) {
        if (!empty($descriptions[$i]) && !empty($prices[$i]) && $prices[$i] > 0) {
            $services[] = [
                'description' => htmlspecialchars($descriptions[$i]),
                'unit_price' => floatval($prices[$i])
            ];
        }
    }

    if (empty($services)) {
        $error = 'Veuillez ajouter au moins un service.';
    } elseif ($patient_id <= 0) {
        $error = 'Veuillez sélectionner un patient.';
    } else {
        $result = generateInvoice($database, $patient_id, $services, $appointment_id, $notes);

        if ($result['success']) {
            $message = 'Facture générée avec succès ! Numéro : ' . $result['invoice_number'];
            // Redirection après 2 secondes
            header("refresh:2;url=invoices.php");
        } else {
            $error = $result['message'];
        }
    }
}

// Récupérer la liste des patients pour le formulaire
$patients = $database->query("
    SELECT p.id, p.uhid, u.full_name, u.email, u.phone
    FROM patients p
    INNER JOIN users u ON u.id = p.user_id
    WHERE u.is_active = 1
    ORDER BY u.full_name
");

// Récupérer les rendez-vous non facturés pour le patient sélectionné
$appointments_unbilled = [];
$selected_patient_id = $_POST['patient_id'] ?? 0;
if ($selected_patient_id > 0) {
    $stmt = $database->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.type, d.user_id, u.full_name as doctor_name
        FROM appointments a
        LEFT JOIN doctors d ON d.id = a.doctor_id
        LEFT JOIN users u ON u.id = d.user_id
        WHERE a.patient_id = ? 
        AND a.status = 'completed'
        AND a.id NOT IN (SELECT appointment_id FROM invoices WHERE appointment_id IS NOT NULL)
        ORDER BY a.appointment_date DESC
    ");
    $stmt->bind_param('i', $selected_patient_id);
    $stmt->execute();
    $appointments_unbilled = $stmt->get_result();
}

// Prix prédéfinis par type de service
$service_templates = [
    ['name' => 'Consultation générale', 'price' => 1500],
    ['name' => 'Consultation spécialiste', 'price' => 2500],
    ['name' => 'Consultation urgence', 'price' => 3000],
    ['name' => 'Échographie', 'price' => 3500],
    ['name' => 'Radiographie', 'price' => 2000],
    ['name' => 'Scanner', 'price' => 8000],
    ['name' => 'IRM', 'price' => 15000],
    ['name' => 'Analyse sanguine', 'price' => 1200],
    ['name' => 'Test COVID', 'price' => 2500],
    ['name' => 'Petite chirurgie', 'price' => 5000],
    ['name' => 'Pansement / Soin', 'price' => 800],
    ['name' => 'Vaccination', 'price' => 1000],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Générer une facture - Comptable</title>
    <link rel="stylesheet" href="comptable.css">
    <style>
        .service-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .service-row input { flex: 1; }
        .service-row .remove-service { background: var(--red-l); color: var(--red); border: none; border-radius: var(--rs); padding: 8px 12px; cursor: pointer; }
        .add-service { margin-top: 10px; }
        .total-display { background: var(--green); color: white; padding: 15px 20px; border-radius: var(--r); margin-top: 20px; text-align: center; }
        .total-display .total-amount { font-size: 2rem; font-weight: 700; }
        .preset-services { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
        .preset-btn { background: var(--surf2); border: 1px solid var(--border); border-radius: var(--rs); padding: 5px 10px; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; }
        .preset-btn:hover { background: var(--green-l); border-color: var(--green); }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Générer une facture</span>
        <div class="topbar-right">
            <a href="invoices.php" class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Retour aux factures
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

        <div class="card">
            <div class="card-head">
                <h3>Nouvelle facture</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="invoiceForm">
                    <input type="hidden" name="action" value="generate_invoice">

                    <!-- Sélection patient -->
                    <div class="form-group">
                        <label>Patient * <span class="req">(obligatoire)</span></label>
                        <select name="patient_id" id="patient_id" class="input" required onchange="this.form.submit()">
                            <option value="">-- Sélectionner un patient --</option>
                            <?php while ($p = $patients->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($selected_patient_id == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['full_name']); ?> (<?php echo htmlspecialchars($p['uhid']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Rendez-vous associé (si disponible) -->
                    <?php if ($selected_patient_id > 0 && $appointments_unbilled->num_rows > 0): ?>
                        <div class="form-group">
                            <label>Associer à un rendez-vous (optionnel)</label>
                            <select name="appointment_id" class="input">
                                <option value="">-- Aucun rendez-vous --</option>
                                <?php while ($apt = $appointments_unbilled->fetch_assoc()): ?>
                                    <option value="<?php echo $apt['id']; ?>">
                                        <?php echo date('d/m/Y', strtotime($apt['appointment_date'])); ?> -
                                        <?php echo $apt['type']; ?> -
                                        Dr. <?php echo htmlspecialchars($apt['doctor_name'] ?? 'Inconnu'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Services pré-définis -->
                    <div class="form-group">
                        <label>Ajouter un service pré-défini :</label>
                        <div class="preset-services">
                            <?php foreach ($service_templates as $template): ?>
                                <button type="button" class="preset-btn" data-name="<?php echo htmlspecialchars($template['name']); ?>" data-price="<?php echo $template['price']; ?>">
                                    <?php echo htmlspecialchars($template['name']); ?> (<?php echo number_format($template['price'], 0, ',', ' '); ?> DA)
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Services facturés -->
                    <div class="form-group">
                        <label>Services facturés *</label>
                        <div id="services-container">
                            <div class="service-row">
                                <input type="text" name="service_description[]" class="input" placeholder="Description du service" required>
                                <input type="number" name="service_price[]" class="input" placeholder="Prix unitaire (DA)" step="0.01" required>
                                <button type="button" class="remove-service" onclick="removeService(this)" style="display:none;">✕</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm add-service" onclick="addService()">
                            <svg viewBox="0 0 24 24" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Ajouter un service
                        </button>
                    </div>

                    <!-- Total -->
                    <div class="total-display">
                        <div>Total à payer</div>
                        <div class="total-amount" id="totalAmount">0 DA</div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label>Notes (optionnel)</label>
                        <textarea name="notes" class="input" rows="3" placeholder="Informations complémentaires..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Générer la facture</button>
                        <a href="invoices.php" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aide à la facturation -->
        <div class="card" style="margin-top: 22px;">
            <div class="card-head">
                <h3>Informations</h3>
            </div>
            <div class="card-body">
                <ul style="margin-left: 20px; color: var(--text2);">
                    <li>La facture sera générée avec un numéro unique au format INV-YYYYMMDD-XXXX</li>
                    <li>Le statut initial de la facture sera "impayée"</li>
                    <li>Vous pourrez enregistrer un paiement depuis la liste des factures</li>
                    <li>Les services peuvent être modifiés avant la génération</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<script>
    // Ajouter une ligne de service
    function addService() {
        const container = document.getElementById('services-container');
        const newRow = document.createElement('div');
        newRow.className = 'service-row';
        newRow.innerHTML = `
        <input type="text" name="service_description[]" class="input" placeholder="Description du service" required>
        <input type="number" name="service_price[]" class="input" placeholder="Prix unitaire (DA)" step="0.01" required oninput="updateTotal()">
        <button type="button" class="remove-service" onclick="removeService(this)">✕</button>
    `;
        container.appendChild(newRow);
        updateTotal();
    }

    // Supprimer une ligne de service
    function removeService(btn) {
        const row = btn.parentElement;
        if (document.querySelectorAll('.service-row').length > 1) {
            row.remove();
            updateTotal();
        } else {
            alert('Vous devez conserver au moins un service.');
        }
    }

    // Mettre à jour le total
    function updateTotal() {
        let total = 0;
        const prices = document.querySelectorAll('input[name="service_price[]"]');
        prices.forEach(priceInput => {
            const price = parseFloat(priceInput.value);
            if (!isNaN(price) && price > 0) {
                total += price;
            }
        });
        document.getElementById('totalAmount').innerText = total.toLocaleString('fr-FR') + ' DA';
    }

    // Ajouter un service pré-défini
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            const price = this.getAttribute('data-price');

            const container = document.getElementById('services-container');
            const newRow = document.createElement('div');
            newRow.className = 'service-row';
            newRow.innerHTML = `
            <input type="text" name="service_description[]" class="input" value="${name}" required>
            <input type="number" name="service_price[]" class="input" value="${price}" step="0.01" required oninput="updateTotal()">
            <button type="button" class="remove-service" onclick="removeService(this)">✕</button>
        `;
            container.appendChild(newRow);
            updateTotal();
        });
    });

    // Écouter les changements de prix pour mise à jour du total
    document.addEventListener('input', function(e) {
        if (e.target && e.target.name === 'service_price[]') {
            updateTotal();
        }
    });

    // Initialiser le total au chargement
    document.addEventListener('DOMContentLoaded', function() {
        updateTotal();
    });
</script>

</body>
</html>