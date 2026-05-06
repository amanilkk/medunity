<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

if ($_SESSION['role'] !== 'pharmacien') {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: prescriptions.php');
    exit;
}

$prescription_id = $_GET['id'];
$prescription = getPrescriptionDetails($database, $prescription_id);

if (!$prescription) {
    header('Location: prescriptions.php');
    exit;
}

$items = getPrescriptionItems($database, $prescription_id);
$message = '';
$error = '';

// === TRAITEMENT FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'deliver') {
        $result = preparePrescription($database, $prescription_id, $_SESSION['user_id']);
        if ($result['success']) {
            $message = $result['message'];
            // Recharger les données
            $prescription = getPrescriptionDetails($database, $prescription_id);
        } else {
            $error = $result['message'];
        }
    }
}

// Vérifier la disponibilité de tous les médicaments
$all_available = true;
$unavailable_medicines = [];
foreach ($items as $item) {
    if (!isMedicineAvailable($database, $item['medicine_id'], $item['quantity'])) {
        $all_available = false;
        $unavailable_medicines[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Détail Prescription — Pharmacie</title>
    <link rel="stylesheet" href="pharmacien.css">
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Détail de la prescription #<?php echo $prescription_id; ?></span>
        <div class="topbar-right">
            <a href="prescriptions.php" class="btn btn-secondary btn-sm">← Retour</a>
        </div>
    </div>
    <div class="page-body">

        <?php if ($message): ?>
        <div class="alert alert-success">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if (!$all_available && $prescription['status'] === 'pending'): ?>
        <div class="alert alert-error" style="background:#fee2e2; border-color:#ef4444;">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <strong>⚠️ Médicaments non disponibles :</strong>
                <ul style="margin-top:8px; margin-left:20px;">
                <?php foreach ($unavailable_medicines as $med):
                    $stock = getCurrentStock($database, $med['medicine_id']);
                ?>
                    <li><?php echo htmlspecialchars($med['medicine_name']); ?> : <?php echo $med['quantity']; ?> demandés, <?php echo $stock; ?> disponibles</li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="row-2">
            <!-- Informations patient -->
            <div class="card">
                <div class="card-head">
                    <h3>Informations du patient</h3>
                </div>
                <div class="info-block">
                    <div class="info-row">
                        <span class="info-label">Nom</span>
                        <span class="info-value" style="font-weight:600"><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">UHID</span>
                        <span class="info-value"><?php echo htmlspecialchars($prescription['uhid'] ?? '—'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Groupe sanguin</span>
                        <span class="info-value" style="color:var(--red); font-weight:600"><?php echo htmlspecialchars($prescription['blood_type'] ?? '—'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Allergies</span>
                        <span class="info-value">
                            <?php if ($prescription['allergies']): ?>
                                <span style="background:#fee2e2; color:#ef4444; padding:4px 8px; border-radius:4px; font-weight:600;">
                                    ⚠️ <?php echo htmlspecialchars($prescription['allergies']); ?>
                                </span>
                            <?php else: ?>
                                Aucune connue
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Téléphone</span>
                        <span class="info-value"><?php echo htmlspecialchars($prescription['phone'] ?? '—'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($prescription['email'] ?? '—'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Informations prescription -->
            <div class="card">
                <div class="card-head">
                    <h3>Informations prescription</h3>
                </div>
                <div class="info-block">
                    <div class="info-row">
                        <span class="info-label">Médecin</span>
                        <span class="info-value" style="font-weight:600"><?php echo htmlspecialchars($prescription['doctor_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date prescription</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($prescription['prescription_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Statut</span>
                        <span class="info-value">
                            <span class="badge badge-<?php 
                                echo $prescription['status'] === 'pending' ? 'yellow' : 
                                     ($prescription['status'] === 'delivered' ? 'green' : 'red'); 
                            ?>">
                                <?php echo ucfirst(formatStatus($prescription['status'])); ?>
                            </span>
                        </span>
                    </div>
                    <?php if ($prescription['status'] === 'delivered'): ?>
                    <div class="info-row">
                        <span class="info-label">Délivrée le</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($prescription['prepared_at'])); ?></span>
                    </div>
                    <?php elseif ($prescription['status'] === 'rejected'): ?>
                    <div class="info-row">
                        <span class="info-label">Raison rejet</span>
                        <span class="info-value"><?php echo htmlspecialchars($prescription['rejection_reason'] ?? '—'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Notes</span>
                        <span class="info-value"><?php echo htmlspecialchars($prescription['notes'] ?? '—'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Articles de la prescription -->
        <div class="card">
            <div class="card-head">
                <h3>Articles (<?php echo count($items); ?>)</h3>
            </div>
            <?php if (count($items) === 0): ?>
                <div class="empty">
                    <p>Aucun article dans cette prescription</p>
                </div>
            <?php else: ?>
            <table class="tbl">
                <thead>
                <tr>
                    <th>Médicament</th>
                    <th>Dosage</th>
                    <th>Forme</th>
                    <th>Quantité</th>
                    <th>Stock</th>
                    <th>Posologie</th>
                    <th>Disponibilité</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item):
                    $stock = getCurrentStock($database, $item['medicine_id']);
                    $available = $stock >= $item['quantity'];
                ?>
                <tr <?php if (!$available) echo 'style="background-color:var(--red-l)"'; ?>>
                    <td style="font-weight:600"><?php echo htmlspecialchars($item['medicine_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['strength'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($item['dosage_form'] ?? '—'); ?></td>
                    <td><?php echo $item['quantity']; ?> unités</td>
                    <td>
                        <span class="badge badge-<?php echo $available ? 'green' : 'red'; ?>">
                            <?php echo $stock; ?> en stock
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($item['instructions'] ?? '—'); ?></td>
                    <td>
                        <?php if ($available): ?>
                            <span class="badge badge-green">✓ Disponible</span>
                        <?php else: ?>
                            <span class="badge badge-red">✗ Insuffisant</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <?php if ($prescription['status'] === 'pending'): ?>
        <div class="card">
            <div class="card-head">
                <h3>Actions</h3>
            </div>
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="deliver">
                <button type="submit" class="btn btn-primary" <?php if (!$all_available) echo 'disabled'; ?>>
                    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Préparer et délivrer
                </button>
                <a href="prescriptions.php?action=reject&id=<?php echo $prescription_id; ?>" class="btn btn-red">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    Rejeter
                </a>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
.row-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.info-block {
    padding: 16px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--text3);
    font-size: 13px;
}

.info-value {
    text-align: right;
}
</style>
</body>
</html>
