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

// === TRAITEMENT FORMULAIRE (ajout mouvement) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_movement') {
        $medicine_id = intval($_POST['medicine_id']);
        $type = $_POST['type'];
        $quantity = intval($_POST['quantity']);
        $reason = $_POST['reason'];
        $notes = $_POST['notes'] ?? '';

        // Pour les sorties, récupérer la référence (prescription ou département)
        $reference_id = null;
        $reference_type = null;
        $destination_department = null;

        if ($type === 'out') {
            $reference_type = $_POST['reference_type'] ?? '';
            if ($reference_type === 'prescription') {
                $reference_id = !empty($_POST['prescription_id']) ? intval($_POST['prescription_id']) : null;
            } elseif ($reference_type === 'department') {
                $destination_department = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
                $reference_id = $destination_department;
                $reference_type = 'department';
            }
        }

        if ($quantity <= 0) {
            $error = "La quantité doit être supérieure à 0";
        } elseif ($type === 'out' && $reference_type && !$reference_id) {
            $error = "Veuillez sélectionner une prescription ou un département";
        } else {
            // Ajouter le département dans les notes si c'est une sortie vers département
            if ($destination_department && $reference_type === 'department') {
                $dept_name = getDepartmentName($database, $destination_department);
                $notes = "Département: " . $dept_name . " | " . $notes;
            }

            $result = recordStockMovement($database, $medicine_id, $type, $quantity, $reason, $_SESSION['user_id'], $notes, $reference_id, $reference_type);

            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// === RÉCUPÉRATION DES PRESCRIPTIONS EN ATTENTE ===
$pending_prescriptions = getPendingPrescriptions($database);

// === RÉCUPÉRATION DES DÉPARTEMENTS ACTIFS DEPUIS LA TABLE departments ===
// === RÉCUPÉRATION DES DÉPARTEMENTS ACTIFS SANS DOUBLONS ===
$departments = [];
$dept_stmt = $database->prepare("SELECT id, name, description FROM departments WHERE is_active = 1 ORDER BY name ASC");
if ($dept_stmt) {
    $dept_stmt->execute();
    $all_depts = $dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Filtrer les doublons par nom (garder le premier rencontré)
    $seen_names = [];
    foreach ($all_depts as $dept) {
        if (!in_array($dept['name'], $seen_names)) {
            $seen_names[] = $dept['name'];
            $departments[] = $dept;
        }
    }
}
// === FILTRES ===
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$medicine_filter = isset($_GET['medicine_id']) && $_GET['medicine_id'] !== '' ? intval($_GET['medicine_id']) : null;
$type_filter = $_GET['type'] ?? 'all';

// === RÉCUPÉRATION DES DONNÉES ===
$medicines = getAllMedicines($database);
$movements = getStockMovements($database, $start_date, $end_date, $medicine_filter);

if ($type_filter !== 'all') {
    $movements = array_filter($movements, function($m) use ($type_filter) {
        return $m['type'] === $type_filter;
    });
}

// Statistiques
$total_in = 0;
$total_out = 0;
$total_value_in = 0;
$total_value_out = 0;
$stats_by_department = [];

foreach ($movements as $m) {
    if ($m['type'] === 'in') {
        $total_in += $m['quantity'];
        $total_value_in += $m['quantity'] * ($m['purchase_price'] ?? 0);
    } else {
        $total_out += $m['quantity'];
        $total_value_out += $m['quantity'] * ($m['selling_price'] ?? 0);

        // Extraire le département des notes si présent
        if ($m['notes'] && preg_match('/Département: ([^|]+)/', $m['notes'], $matches)) {
            $dept_name = trim($matches[1]);
            if (!isset($stats_by_department[$dept_name])) {
                $stats_by_department[$dept_name] = 0;
            }
            $stats_by_department[$dept_name] += $m['quantity'];
        }
    }
}

$selected_medicine_name = '';
if ($medicine_filter) {
    foreach ($medicines as $med) {
        if ($med['id'] == $medicine_filter) {
            $selected_medicine_name = $med['name'];
            break;
        }
    }
}

// Fonction pour récupérer le nom d'un département
function getDepartmentName($db, $department_id) {
    $stmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->bind_param('i', $department_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? $result['name'] : 'Département inconnu';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mouvements de stock — Pharmacie</title>
    <link rel="stylesheet" href="pharmacien.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 15px;
            text-align: center;
            box-shadow: var(--sh);
        }
        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 5px;
        }
        .stat-card.in .value { color: var(--green); }
        .stat-card.out .value { color: var(--red); }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .type-selector {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }
        .type-option {
            flex: 1;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: var(--rs);
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        .type-option.in {
            background: var(--green-l);
            color: var(--green);
        }
        .type-option.out {
            background: var(--red-l);
            color: var(--red);
        }
        .type-option.selected {
            border-color: currentColor;
            background: currentColor;
            color: white;
        }
        .type-option.in.selected {
            background: var(--green);
            border-color: var(--green);
        }
        .type-option.out.selected {
            background: var(--red);
            border-color: var(--red);
        }
        .type-option .icon {
            font-size: 1.2rem;
        }
        .type-option .label {
            font-size: 0.7rem;
            font-weight: 600;
        }
        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 22px;
            padding: 15px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
        }
        .badge-red { background: var(--red-l); color: var(--red); }
        .badge-green { background: var(--green-l); color: var(--green); }
        .badge-blue { background: var(--blue-l); color: var(--blue); }
        .row-alert { background-color: var(--red-l); }

        .reference-group {
            display: none;
            margin-top: 10px;
        }
        .reference-group.active {
            display: block;
        }
        .reference-select {
            width: 100%;
            padding: 10px;
            border: 1.5px solid var(--border);
            border-radius: var(--rs);
            font-size: 0.85rem;
        }
        .info-badge {
            background: var(--blue-l);
            color: var(--blue);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .dest-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .dept-option {
            padding: 10px 12px;
            margin: 5px 0;
            border: 1px solid var(--border);
            border-radius: var(--rs);
            cursor: pointer;
            transition: all 0.2s;
            background: var(--surface);
        }
        .dept-option:hover {
            border-color: var(--blue);
            background: var(--blue-l);
        }
        .dept-option.selected {
            background: var(--green-l);
            border-color: var(--green);
            border-width: 2px;
        }
        .dept-option .dept-name {
            font-weight: 600;
        }
        .dept-option .dept-desc {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 4px;
        }
        .auto-filled {
            background: var(--green-l) !important;
            border-color: var(--green) !important;
        }
        .info-note {
            background: var(--blue-l);
            border-left: 4px solid var(--blue);
            padding: 10px 15px;
            margin-top: 15px;
            border-radius: var(--rs);
            font-size: 0.75rem;
            color: var(--blue);
        }
        .section-title-small {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">
            Mouvements de stock
            <?php if ($selected_medicine_name): ?>
                - <?php echo htmlspecialchars($selected_medicine_name); ?>
            <?php endif; ?>
        </span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                🖨️ Imprimer
            </button>
        </div>
    </div>
    <div class="page-body">

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

        <!-- Formulaire d'ajout de mouvement -->
        <div class="card">
            <div class="card-head">
                <h3>➕ Ajouter un mouvement de stock</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="movementForm">
                    <input type="hidden" name="action" value="add_movement">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Médicament *</label>
                            <select name="medicine_id" id="medicine_id" class="input" required>
                                <option value="">-- Sélectionner un médicament --</option>
                                <?php foreach ($medicines as $med): ?>
                                    <option value="<?php echo $med['id']; ?>" data-stock="<?php echo $med['current_stock'] ?? 0; ?>" <?php echo ($medicine_filter == $med['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($med['name']); ?>
                                        (Stock: <?php echo $med['current_stock'] ?? 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Type de mouvement *</label>
                            <div class="type-selector">
                                <div class="type-option in" data-type="in">
                                    <div class="icon">📥</div>
                                    <div class="label">Entrée</div>
                                </div>
                                <div class="type-option out" data-type="out">
                                    <div class="icon">📤</div>
                                    <div class="label">Sortie</div>
                                </div>
                            </div>
                            <input type="hidden" name="type" id="movement_type" required>
                        </div>

                        <div class="form-group">
                            <label>Quantité *</label>
                            <input type="number" name="quantity" id="quantity" class="input" step="1" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Raison *</label>
                            <select name="reason" id="reason" class="input" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="commande">📦 Commande fournisseur</option>
                                <option value="distribution">💊 Distribution</option>
                                <option value="retour">🔄 Retour</option>
                                <option value="perte">⚠️ Perte / Péremption</option>
                                <option value="inventaire">📋 Ajustement inventaire</option>
                                <option value="don">🎁 Don</option>
                            </select>
                        </div>

                        <!-- Section pour les sorties : sélection référence -->
                        <div id="reference_section" style="display: none;">
                            <div class="form-group">
                                <label>Type de sortie *</label>
                                <select id="reference_type" name="reference_type" class="input">
                                    <option value="">-- Sélectionner --</option>
                                    <option value="prescription">💊 Prescription médicale</option>
                                    <option value="department">🏥 Service / Département</option>
                                </select>
                            </div>

                            <!-- Prescriptions -->
                            <div id="prescription_group" class="reference-group">
                                <div class="form-group">
                                    <label>Prescription *</label>
                                    <select name="prescription_id" id="prescription_id" class="reference-select">
                                        <option value="">-- Sélectionner une prescription --</option>
                                        <?php foreach ($pending_prescriptions as $presc): ?>
                                            <option value="<?php echo $presc['id']; ?>">
                                                #<?php echo $presc['id']; ?> -
                                                <?php echo htmlspecialchars($presc['patient_name']); ?> -
                                                Dr. <?php echo htmlspecialchars($presc['doctor_name']); ?>
                                                (<?php echo date('d/m/Y', strtotime($presc['prescription_date'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Départements (depuis la table departments) -->
                            <div id="department_group" class="reference-group">
                                <div class="section-title-small">📋 Sélectionner le département destinataire</div>
                                <div class="dest-list">
                                    <input type="hidden" name="department_id" id="department_id" value="">
                                    <?php if (count($departments) > 0): ?>
                                        <?php foreach ($departments as $dept): ?>
                                            <div class="dept-option" data-dept-id="<?php echo $dept['id']; ?>">
                                                <div class="dept-name"><?php echo htmlspecialchars($dept['name']); ?></div>
                                                <?php if (!empty($dept['description'])): ?>
                                                    <div class="dept-desc"><?php echo htmlspecialchars(substr($dept['description'], 0, 50)); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="info-note" style="margin: 0;">
                                            ⚠️ Aucun département actif trouvé. Contactez l'administrateur.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes (optionnel)</label>
                            <input type="text" name="notes" id="notes" class="input" placeholder="Informations complémentaires...">
                        </div>
                    </div>

                    <div id="autoFillInfo" class="info-note" style="display: none;">
                        🔄 <strong>Remplissage automatique</strong> : Les champs Médicament et Quantité ont été pré-remplis.
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer le mouvement</button>
                </form>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-row">
            <div class="stat-card in">
                <div class="value"><?php echo $total_in; ?></div>
                <div class="label">Entrées (unités)</div>
            </div>
            <div class="stat-card out">
                <div class="value"><?php echo $total_out; ?></div>
                <div class="label">Sorties (unités)</div>
            </div>
            <div class="stat-card in">
                <div class="value"><?php echo number_format($total_value_in, 0, ',', ' '); ?> DA</div>
                <div class="label">Valeur entrées</div>
            </div>
            <div class="stat-card out">
                <div class="value"><?php echo number_format($total_value_out, 0, ',', ' '); ?> DA</div>
                <div class="label">Valeur sorties</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                <div class="form-group" style="margin: 0;">
                    <label>Du</label>
                    <input type="date" name="start_date" class="input" value="<?php echo $start_date; ?>" style="width: auto;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Au</label>
                    <input type="date" name="end_date" class="input" value="<?php echo $end_date; ?>" style="width: auto;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Médicament</label>
                    <select name="medicine_id" class="input" style="width: auto;">
                        <option value="">-- Tous --</option>
                        <?php foreach ($medicines as $med): ?>
                            <option value="<?php echo $med['id']; ?>" <?php echo ($medicine_filter == $med['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($med['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Type</label>
                    <select name="type" class="input" style="width: auto;">
                        <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>Tous</option>
                        <option value="in" <?php echo $type_filter == 'in' ? 'selected' : ''; ?>>Entrées</option>
                        <option value="out" <?php echo $type_filter == 'out' ? 'selected' : ''; ?>>Sorties</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="stock_movements.php" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>

        <!-- Statistiques par département -->
        <?php if (!empty($stats_by_department)): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-head">
                    <h3>📊 Sorties par service</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                        <?php foreach ($stats_by_department as $dept_name => $qty): ?>
                            <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: var(--surf2); border-radius: var(--rs);">
                                <span style="font-weight: 600;"><?php echo htmlspecialchars($dept_name); ?></span>
                                <span style="font-weight: 700; color: var(--red);"><?php echo $qty; ?> unités</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Liste des mouvements -->
        <div class="card">
            <div class="card-head">
                <h3>📊 Historique des mouvements</h3>
                <div>
                    <span class="badge badge-blue">Total: <?php echo count($movements); ?> mouvements</span>
                </div>
            </div>
            <?php if (count($movements) === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" width="40" height="40">
                        <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <h3>Aucun mouvement trouvé</h3>
                    <p>Aucun mouvement de stock sur cette période</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Médicament</th>
                            <th>Type</th>
                            <th>Quantité</th>
                            <th>Raison</th>
                            <th>Référence</th>
                            <th>Effectué par</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($movements as $mv): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo date('d/m/Y H:i', strtotime($mv['movement_date'])); ?></td>
                                <td style="font-weight:600"><?php echo htmlspecialchars($mv['medicine_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $mv['type'] === 'in' ? 'badge-green' : 'badge-red'; ?>">
                                        <?php echo $mv['type'] === 'in' ? '📥 Entrée' : '📤 Sortie'; ?>
                                    </span>
                                </td>
                                <td style="font-weight:600; <?php echo $mv['type'] === 'in' ? 'color:var(--green)' : 'color:var(--red)'; ?>">
                                    <?php echo $mv['quantity']; ?> unités
                                </td>
                                <td>
                                    <?php
                                    $reason_labels = [
                                            'commande' => '📦 Commande',
                                            'distribution' => '💊 Distribution',
                                            'retour' => '🔄 Retour',
                                            'perte' => '⚠️ Perte',
                                            'inventaire' => '📋 Inventaire',
                                            'don' => '🎁 Don'
                                    ];
                                    echo $reason_labels[$mv['reason']] ?? ucfirst($mv['reason']);
                                    ?>
                                </td>
                                <td>
                                    <?php if ($mv['reference_type'] == 'prescription'): ?>
                                        <span class="info-badge">💊 Prescription #<?php echo $mv['reference_id']; ?></span>
                                    <?php elseif ($mv['reference_type'] == 'department' && preg_match('/Département: ([^|]+)/', $mv['notes'] ?? '', $matches)): ?>
                                        <span class="info-badge">🏥 <?php echo htmlspecialchars(trim($matches[1])); ?></span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($mv['performed_by_name'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Graphique récapitulatif -->
        <div class="card">
            <div class="card-head">
                <h3>📈 Résumé de la période</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 10px;">Entrées vs Sorties</div>
                        <div style="height: 20px; background: var(--surf2); border-radius: var(--rs); overflow: hidden;">
                            <?php
                            $total = $total_in + $total_out;
                            $in_percent = $total > 0 ? ($total_in / $total) * 100 : 0;
                            $out_percent = $total > 0 ? ($total_out / $total) * 100 : 0;
                            ?>
                            <div style="width: <?php echo $in_percent; ?>%; height: 100%; background: var(--green); float: left;"></div>
                            <div style="width: <?php echo $out_percent; ?>%; height: 100%; background: var(--red); float: left;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                            <span style="font-size: 0.7rem;">📥 Entrées: <?php echo $total_in; ?></span>
                            <span style="font-size: 0.7rem;">📤 Sorties: <?php echo $total_out; ?></span>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 10px;">Valeurs</div>
                        <div style="display: flex; justify-content: space-around;">
                            <div>
                                <div style="font-size: 0.7rem; color: var(--text2);">Entrées</div>
                                <div style="font-weight: 700; color: var(--green);"><?php echo number_format($total_value_in, 0, ',', ' '); ?> DA</div>
                            </div>
                            <div>
                                <div style="font-size: 0.7rem; color: var(--text2);">Sorties</div>
                                <div style="font-weight: 700; color: var(--red);"><?php echo number_format($total_value_out, 0, ',', ' '); ?> DA</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // Sélecteur de type de mouvement
    const typeOptions = document.querySelectorAll('.type-option');
    const typeInput = document.getElementById('movement_type');
    const referenceSection = document.getElementById('reference_section');
    const referenceType = document.getElementById('reference_type');
    const prescriptionGroup = document.getElementById('prescription_group');
    const departmentGroup = document.getElementById('department_group');
    const medicineSelect = document.getElementById('medicine_id');
    const quantityInput = document.getElementById('quantity');
    const autoFillInfo = document.getElementById('autoFillInfo');
    const departmentIdInput = document.getElementById('department_id');
    const deptOptions = document.querySelectorAll('.dept-option');
    const notesInput = document.getElementById('notes');

    // Stocker les valeurs originales
    let originalMedicineValue = '';
    let originalQuantityValue = '';

    // Sélection du type de mouvement
    // Sélection du type de mouvement
    typeOptions.forEach(option => {
        option.addEventListener('click', function() {
            typeOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            const selectedType = this.dataset.type;
            typeInput.value = selectedType;
            const reasonSelect = document.getElementById('reason');

            if (selectedType === 'out') {
                referenceSection.style.display = 'block';
                // Forcer la raison à "distribution" pour les sorties et désactiver
                reasonSelect.value = 'distribution';
                reasonSelect.disabled = true;
                // Ajouter une classe visuelle pour indiquer que c'est automatique
                reasonSelect.style.opacity = '0.7';
                reasonSelect.style.cursor = 'not-allowed';
            } else {
                referenceSection.style.display = 'none';
                referenceType.value = '';
                prescriptionGroup.classList.remove('active');
                departmentGroup.classList.remove('active');
                autoFillInfo.style.display = 'none';
                // Réactiver et réinitialiser la raison
                reasonSelect.disabled = false;
                reasonSelect.value = '';
                reasonSelect.style.opacity = '1';
                reasonSelect.style.cursor = 'pointer';
                // Restaurer les valeurs originales
                if (originalMedicineValue) {
                    medicineSelect.value = originalMedicineValue;
                    quantityInput.value = originalQuantityValue;
                    medicineSelect.disabled = false;
                    quantityInput.disabled = false;
                }
                // Réinitialiser la sélection de département
                deptOptions.forEach(opt => opt.classList.remove('selected'));
                if (departmentIdInput) departmentIdInput.value = '';
                // Réinitialiser les notes
                if (notesInput) notesInput.value = '';
            }
        });
    });

    // Gestion du type de référence (Prescription ou Département)
    if (referenceType) {
        referenceType.addEventListener('change', function() {
            const value = this.value;
            prescriptionGroup.classList.remove('active');
            departmentGroup.classList.remove('active');

            // Réactiver les champs
            medicineSelect.disabled = false;
            quantityInput.disabled = false;
            medicineSelect.classList.remove('auto-filled');
            quantityInput.classList.remove('auto-filled');
            autoFillInfo.style.display = 'none';

            // Réinitialiser la sélection de département
            deptOptions.forEach(opt => opt.classList.remove('selected'));
            if (departmentIdInput) departmentIdInput.value = '';

            if (value === 'prescription') {
                prescriptionGroup.classList.add('active');
            } else if (value === 'department') {
                departmentGroup.classList.add('active');
            }
        });
    }

    // Sélection d'un département
    deptOptions.forEach(option => {
        option.addEventListener('click', function() {
            deptOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            const deptId = this.dataset.deptId;
            const deptName = this.querySelector('.dept-name')?.textContent || 'Service';
            if (departmentIdInput) departmentIdInput.value = deptId;

            // Pré-remplir les notes automatiquement
            if (notesInput && !notesInput.value) {
                notesInput.value = 'Distribution vers ' + deptName;
            }
        });
    });

    // Sélection par défaut
    if (typeOptions.length > 0 && !typeInput.value) {
        typeOptions[0].click();
    }
</script>

</body>
</html>