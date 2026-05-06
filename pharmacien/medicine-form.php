<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

// Vérifier que l'utilisateur est pharmacien
if ($_SESSION['role'] !== 'pharmacien') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';
$is_edit = false;
$medicine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$medicine = null;

// Si modification, récupérer les données du médicament
if ($medicine_id > 0) {
    $is_edit = true;
    $medicine = getMedicineById($database, $medicine_id);
    if (!$medicine) {
        header('Location: medicines.php');
        exit;
    }
}

// === TRAITEMENT FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // AJOUT D'UN MÉDICAMENT
    if ($_POST['action'] === 'add_medicine') {
        $data = [
            'name' => trim($_POST['name']),
            'generic_name' => trim($_POST['generic_name'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'dosage_form' => trim($_POST['dosage_form'] ?? ''),
            'strength' => trim($_POST['strength'] ?? ''),
            'quantity' => intval($_POST['quantity'] ?? 0),
            'unit' => $_POST['unit'] ?? 'boîte',
            'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
            'threshold_alert' => intval($_POST['threshold_alert'] ?? 10),
            'purchase_price' => floatval($_POST['purchase_price'] ?? 0),
            'selling_price' => floatval($_POST['selling_price'] ?? 0),
            'supplier_id' => !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null
        ];

        if (empty($data['name'])) {
            $error = "Le nom du médicament est obligatoire";
        } elseif ($data['purchase_price'] < 0 || $data['selling_price'] < 0) {
            $error = "Les prix ne peuvent pas être négatifs";
        } else {
            $result = addMedicine($database, $data);
            if ($result['success']) {
                $message = $result['message'];
                // Redirection après 2 secondes
                header("refresh:2;url=medicines.php");
            } else {
                $error = $result['message'];
            }
        }
    }

    // MODIFICATION D'UN MÉDICAMENT
    if ($_POST['action'] === 'edit_medicine' && $medicine_id > 0) {
        $updates = [];

        if (isset($_POST['name']) && $_POST['name'] !== $medicine['name']) {
            $updates['name'] = trim($_POST['name']);
        }
        if (isset($_POST['generic_name']) && $_POST['generic_name'] !== ($medicine['generic_name'] ?? '')) {
            $updates['generic_name'] = trim($_POST['generic_name']);
        }
        if (isset($_POST['category']) && $_POST['category'] !== ($medicine['category'] ?? '')) {
            $updates['category'] = trim($_POST['category']);
        }
        if (isset($_POST['dosage_form']) && $_POST['dosage_form'] !== ($medicine['dosage_form'] ?? '')) {
            $updates['dosage_form'] = trim($_POST['dosage_form']);
        }
        if (isset($_POST['strength']) && $_POST['strength'] !== ($medicine['strength'] ?? '')) {
            $updates['strength'] = trim($_POST['strength']);
        }
        if (isset($_POST['quantity']) && intval($_POST['quantity']) !== ($medicine['quantity'] ?? 0)) {
            $updates['quantity'] = intval($_POST['quantity']);
        }
        if (isset($_POST['unit']) && $_POST['unit'] !== ($medicine['unit'] ?? 'boîte')) {
            $updates['unit'] = $_POST['unit'];
        }
        if (isset($_POST['expiry_date']) && $_POST['expiry_date'] !== ($medicine['expiry_date'] ?? '')) {
            $updates['expiry_date'] = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        }
        if (isset($_POST['threshold_alert']) && intval($_POST['threshold_alert']) !== ($medicine['threshold_alert'] ?? 10)) {
            $updates['threshold_alert'] = intval($_POST['threshold_alert']);
        }
        if (isset($_POST['purchase_price']) && floatval($_POST['purchase_price']) !== ($medicine['purchase_price'] ?? 0)) {
            $updates['purchase_price'] = floatval($_POST['purchase_price']);
        }
        if (isset($_POST['selling_price']) && floatval($_POST['selling_price']) !== ($medicine['selling_price'] ?? 0)) {
            $updates['selling_price'] = floatval($_POST['selling_price']);
        }
        if (isset($_POST['supplier_id']) && intval($_POST['supplier_id']) !== ($medicine['supplier_id'] ?? 0)) {
            $updates['supplier_id'] = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
        }

        if (!empty($updates)) {
            $result = updateMedicine($database, $medicine_id, $updates);
            if ($result['success']) {
                $message = $result['message'];
                // Recharger les données
                $medicine = getMedicineById($database, $medicine_id);
            } else {
                $error = $result['message'];
            }
        } else {
            $message = "Aucune modification apportée";
        }
    }
}

// Récupérer les fournisseurs pour le select
$suppliers = getAllSuppliers($database, true);

// Unités disponibles
$units = [
    'boîte' => 'Boîte',
    'comprimé' => 'Comprimé',
    'gélule' => 'Gélule',
    'ampoule' => 'Ampoule',
    'flacon' => 'Flacon',
    'sachet' => 'Sachet',
    'ml' => 'Millilitre (ml)',
    'mg' => 'Milligramme (mg)',
    'g' => 'Gramme (g)'
];

// Formes dosage
$dosage_forms = [
    'comprimé' => 'Comprimé',
    'gélule' => 'Gélule',
    'sirop' => 'Sirop',
    'injection' => 'Injection',
    'pommade' => 'Pommade',
    'crème' => 'Crème',
    'suppositoire' => 'Suppositoire',
    'spray' => 'Spray',
    'patch' => 'Patch'
];

// Catégories
$categories = [
    'Antibiotique' => 'Antibiotique',
    'Antalgique' => 'Antalgique',
    'Anti-inflammatoire' => 'Anti-inflammatoire',
    'Antihypertenseur' => 'Antihypertenseur',
    'Antidiabétique' => 'Antidiabétique',
    'Antidépresseur' => 'Antidépresseur',
    'Antihistaminique' => 'Antihistaminique',
    'Antiviral' => 'Antiviral',
    'Antifongique' => 'Antifongique',
    'Vitaminé' => 'Vitaminé',
    'Autre' => 'Autre'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $is_edit ? 'Modifier' : 'Ajouter'; ?> un médicament — Pharmacie</title>
    <link rel="stylesheet" href="pharmacien.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-group-full {
            grid-column: span 2;
        }
        .info-note {
            background: var(--blue-l);
            border-left: 4px solid var(--blue);
            padding: 12px 15px;
            margin: 20px 0;
            border-radius: var(--rs);
            font-size: 0.8rem;
            color: var(--blue);
        }
        .price-group {
            display: flex;
            gap: 10px;
        }
        .price-group .form-group {
            flex: 1;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group-full {
                grid-column: span 1;
            }
        }
        .stock-info {
            background: var(--surf2);
            padding: 12px;
            border-radius: var(--rs);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stock-info .label {
            font-weight: 600;
            color: var(--text2);
        }
        .stock-info .value {
            font-weight: 700;
            font-size: 1.1rem;
        }
        .stock-info .value.low { color: var(--red); }
        .stock-info .value.normal { color: var(--green); }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">
            <?php echo $is_edit ? '✏️ Modifier le médicament' : '➕ Ajouter un médicament'; ?>
        </span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="medicines.php" class="btn btn-secondary btn-sm">
                ← Retour à la liste
            </a>
        </div>
    </div>
    <div class="page-body">
        <div class="form-container">

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <svg viewBox="0 0 24 24" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Informations stock (si modification) -->
            <?php if ($is_edit && $medicine):
                $current_stock = $medicine['current_stock'] ?? 0;
                $threshold = $medicine['threshold_alert'] ?? 10;
                $stock_status = $current_stock <= $threshold ? 'low' : 'normal';
                ?>
                <div class="stock-info">
                    <span class="label">📊 Stock actuel :</span>
                    <span class="value <?php echo $stock_status; ?>">
                        <?php echo $current_stock; ?> unités
                        <?php if ($stock_status == 'low'): ?>
                            <span style="color: var(--red);">⚠️ Stock faible (alerte: <?php echo $threshold; ?>)</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <div class="card">
                <div class="card-head">
                    <h3>💊 Informations du médicament</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit_medicine' : 'add_medicine'; ?>">

                        <div class="form-grid">
                            <!-- Nom (obligatoire) -->
                            <div class="form-group">
                                <label>Nom commercial *</label>
                                <input type="text" name="name" class="input" required
                                       value="<?php echo htmlspecialchars($medicine['name'] ?? ''); ?>">
                            </div>

                            <!-- Nom générique -->
                            <div class="form-group">
                                <label>Nom générique</label>
                                <input type="text" name="generic_name" class="input"
                                       value="<?php echo htmlspecialchars($medicine['generic_name'] ?? ''); ?>">
                            </div>

                            <!-- Catégorie -->
                            <div class="form-group">
                                <label>Catégorie</label>
                                <select name="category" class="input">
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($categories as $key => $cat): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (($medicine['category'] ?? '') == $key) ? 'selected' : ''; ?>>
                                            <?php echo $cat; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Forme dosage -->
                            <div class="form-group">
                                <label>Forme du médicament</label>
                                <select name="dosage_form" class="input">
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($dosage_forms as $key => $form): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (($medicine['dosage_form'] ?? '') == $key) ? 'selected' : ''; ?>>
                                            <?php echo $form; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Dosage -->
                            <div class="form-group">
                                <label>Dosage</label>
                                <input type="text" name="strength" class="input" placeholder="Ex: 500mg, 10ml..."
                                       value="<?php echo htmlspecialchars($medicine['strength'] ?? ''); ?>">
                            </div>

                            <!-- Unité -->
                            <div class="form-group">
                                <label>Unité de conditionnement</label>
                                <select name="unit" class="input">
                                    <?php foreach ($units as $key => $unit): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (($medicine['unit'] ?? 'boîte') == $key) ? 'selected' : ''; ?>>
                                            <?php echo $unit; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Quantité (modifiable uniquement par mouvements) -->
                            <div class="form-group">
                                <label>Quantité initiale</label>
                                <input type="number" name="quantity" class="input" step="1" min="0"
                                       value="<?php echo $medicine['quantity'] ?? 0; ?>"
                                    <?php echo $is_edit ? 'readonly style="background:var(--surf2);"' : ''; ?>>
                                <?php if ($is_edit): ?>
                                    <small style="font-size:0.65rem; color:var(--orange);">
                                        ⚠️ Modifiez le stock via les mouvements de stock
                                    </small>
                                <?php endif; ?>
                            </div>

                            <!-- Seuil d'alerte -->
                            <div class="form-group">
                                <label>Seuil d'alerte stock</label>
                                <input type="number" name="threshold_alert" class="input" step="1" min="0"
                                       value="<?php echo $medicine['threshold_alert'] ?? 10; ?>">
                                <small style="font-size:0.65rem; color:var(--text2);">
                                    Alerte lorsque le stock est ≤ ce seuil
                                </small>
                            </div>

                            <!-- Date d'expiration -->
                            <div class="form-group">
                                <label>Date d'expiration</label>
                                <input type="date" name="expiry_date" class="input"
                                       value="<?php echo $medicine['expiry_date'] ?? ''; ?>">
                            </div>

                            <!-- Fournisseur -->
                            <div class="form-group">
                                <label>Fournisseur</label>
                                <select name="supplier_id" class="input">
                                    <option value="">-- Aucun --</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?php echo $sup['id']; ?>" <?php echo (($medicine['supplier_id'] ?? 0) == $sup['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sup['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Prix d'achat et vente -->
                            <div class="price-group form-group-full">
                                <div class="form-group">
                                    <label>Prix d'achat (DA)</label>
                                    <input type="number" name="purchase_price" class="input" step="1" min="0"
                                           value="<?php echo $medicine['purchase_price'] ?? 0; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Prix de vente (DA) *</label>
                                    <input type="number" name="selling_price" class="input" step="1" min="0" required
                                           value="<?php echo $medicine['selling_price'] ?? 0; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="info-note">
                            💡 <strong>Informations :</strong><br>
                            - Le stock initial sera enregistré comme un mouvement d'entrée.<br>
                            - Pour modifier le stock après ajout, utilisez la page <strong>Mouvements de stock</strong>.<br>
                            - Les médicaments expirés ne seront pas proposés à la délivrance.
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $is_edit ? '💾 Mettre à jour' : '➕ Ajouter le médicament'; ?>
                            </button>
                            <a href="medicines.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>