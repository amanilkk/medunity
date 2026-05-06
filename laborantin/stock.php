<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
require_once 'functions.php';
requireLaborantin();
include '../connection.php';

$alerts_view = isset($_GET['alerts']);
$items = getLabStockItems($database, $alerts_view ? 'low' : '');
$movements = getStockMovements($database);
$categories = LAB_CATEGORIES;
$manual_consumables = getManualConsumables($database);

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // AJOUTER une quantité (réapprovisionnement)
    if ($action === 'add_stock') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $quantity_to_add = (float)str_replace(',', '.', $_POST['add_quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Réapprovisionnement');
        $performed_by = getCurrentLaborantinId();

        if ($item_id && $quantity_to_add > 0) {
            $stmt = $database->prepare("SELECT quantity, item_name, threshold_alert, unit FROM lab_stock WHERE id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                $old_quantity = $item['quantity'];
                $new_quantity = $old_quantity + $quantity_to_add;

                $update = $database->prepare("UPDATE lab_stock SET quantity = ? WHERE id = ?");
                $update->bind_param('di', $new_quantity, $item_id);
                $update->execute();

                $movement = $database->prepare("
                    INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
                    VALUES (?, 'in', ?, ?, ?, NOW())
                ");
                $movement->bind_param('idsi', $item_id, $quantity_to_add, $reason, $performed_by);
                $movement->execute();

                header('Location: stock.php?msg=added');
                exit;
            }
        }
        header('Location: stock.php?error=add_amount');
        exit;
    }

    // RETIRER une quantité (consommation manuelle)
    if ($action === 'remove_stock') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $quantity_to_remove = (float)str_replace(',', '.', $_POST['remove_quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Consommation');
        $performed_by = getCurrentLaborantinId();

        if ($item_id && $quantity_to_remove > 0) {
            $stmt = $database->prepare("SELECT quantity, item_name, threshold_alert, unit FROM lab_stock WHERE id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                if ($item['quantity'] < $quantity_to_remove) {
                    header('Location: stock.php?error=not_enough&item=' . urlencode($item['item_name']));
                    exit;
                }

                $old_quantity = $item['quantity'];
                $new_quantity = $old_quantity - $quantity_to_remove;

                $update = $database->prepare("UPDATE lab_stock SET quantity = ? WHERE id = ?");
                $update->bind_param('di', $new_quantity, $item_id);
                $update->execute();

                $movement = $database->prepare("
                    INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
                    VALUES (?, 'out', ?, ?, ?, NOW())
                ");
                $movement->bind_param('idsi', $item_id, $quantity_to_remove, $reason, $performed_by);
                $movement->execute();

                if ($new_quantity <= $item['threshold_alert']) {
                    checkLowStockAlert($database, $item_id);
                }

                header('Location: stock.php?msg=removed');
                exit;
            }
        }
        header('Location: stock.php?error=remove_amount');
        exit;
    }

    // CONSOMMATION MANUELLE SPÉCIFIQUE (depuis l'interface dédiée)
    if ($action === 'manual_consume') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $quantity = (float)str_replace(',', '.', $_POST['quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Consommation manuelle');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $performed_by = getCurrentLaborantinId();

        if ($item_id && $quantity > 0) {
            $stmt = $database->prepare("SELECT quantity, item_name, threshold_alert, unit FROM lab_stock WHERE id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                if ($item['quantity'] < $quantity) {
                    header('Location: stock.php?error=not_enough&item=' . urlencode($item['item_name']));
                    exit;
                }

                $new_qty = $item['quantity'] - $quantity;

                $update = $database->prepare("UPDATE lab_stock SET quantity = ? WHERE id = ?");
                $update->bind_param('di', $new_qty, $item_id);
                $update->execute();

                $reason_detail = $reason;
                if ($test_id > 0) {
                    $reason_detail = $reason . " (Analyse #$test_id)";
                }

                $movement = $database->prepare("
                    INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
                    VALUES (?, 'out', ?, ?, ?, NOW())
                ");
                $movement->bind_param('idsi', $item_id, $quantity, $reason_detail, $performed_by);
                $movement->execute();

                if ($test_id > 0) {
                    $consumable_log = $database->prepare("
                        INSERT INTO lab_test_consumables (test_id, item_id, quantity_used)
                        VALUES (?, ?, ?)
                    ");
                    $consumable_log->bind_param('iid', $test_id, $item_id, $quantity);
                    $consumable_log->execute();
                }

                if ($new_qty <= $item['threshold_alert']) {
                    checkLowStockAlert($database, $item_id);
                }

                header('Location: stock.php?msg=manual_consumed');
                exit;
            }
        }
        header('Location: stock.php?error=manual');
        exit;
    }

    // MODIFICATION directe (remplacement total)
    if ($action === 'update_stock') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $new_quantity = (float)str_replace(',', '.', $_POST['quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Ajustement manuel');
        $performed_by = getCurrentLaborantinId();

        if ($item_id) {
            $stmt = $database->prepare("SELECT quantity, item_name, threshold_alert FROM lab_stock WHERE id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                $old_quantity = $item['quantity'];
                $diff = $new_quantity - $old_quantity;
                $operation = $diff > 0 ? 'in' : ($diff < 0 ? 'out' : 'none');
                $abs_diff = abs($diff);

                if ($operation !== 'none') {
                    $update = $database->prepare("UPDATE lab_stock SET quantity = ? WHERE id = ?");
                    $update->bind_param('di', $new_quantity, $item_id);
                    $update->execute();

                    $movement = $database->prepare("
                        INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $movement->bind_param('isdsi', $item_id, $operation, $abs_diff, $reason, $performed_by);
                    $movement->execute();

                    if ($new_quantity <= $item['threshold_alert']) {
                        checkLowStockAlert($database, $item_id);
                    }
                }
                header('Location: stock.php?msg=updated');
                exit;
            }
        }
        header('Location: stock.php?error=stock');
        exit;
    }

    // AJOUTER un nouveau consommable
    if ($action === 'add_stock_item') {
        $item_name = trim($_POST['item_name'] ?? '');
        $category = $_POST['category'] ?? 'autres';
        $quantity = (float)str_replace(',', '.', $_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'unité');
        $threshold_alert = (float)str_replace(',', '.', $_POST['threshold'] ?? 10);
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($item_name) {
            $stmt = $database->prepare("
                INSERT INTO lab_stock (item_name, category, description, quantity, unit, threshold_alert, location, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('sssdsis', $item_name, $category, $description, $quantity, $unit, $threshold_alert, $location);

            if ($stmt->execute()) {
                $item_id = $database->insert_id;

                if ($quantity > 0) {
                    $movement = $database->prepare("
                        INSERT INTO lab_stock_movements (item_id, operation, quantity, reason, performed_by, created_at)
                        VALUES (?, 'in', ?, 'Création initiale', ?, NOW())
                    ");
                    $movement->bind_param('idi', $item_id, $quantity, getCurrentLaborantinId());
                    $movement->execute();
                }
                header('Location: stock.php?msg=added');
                exit;
            }
        }
        header('Location: stock.php?error=add');
        exit;
    }

    // Demande de réapprovisionnement → insère dans orders
    if ($action === 'request_stock') {
        $item_id  = (int)($_POST['item_id']  ?? 0);
        $quantity = (int)($_POST['quantity']  ?? 1);
        if ($quantity < 1) $quantity = 1;

        if ($item_id) {
            if (requestReapprovisionnement($database, $item_id, $quantity)) {
                header('Location: stock.php?msg=requested');
                exit;
            }
        }
        header('Location: stock.php?error=request');
        exit;
    }
}

// Fonction pour formater les nombres
function formatQuantity($qty) {
    if ($qty === null) return '0';
    if (floor($qty) == $qty) {
        return number_format($qty, 0, ',', ' ');
    }
    return number_format($qty, 2, ',', ' ');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Consommables - Laborantin</title>
    <link rel="stylesheet" href="../receptionniste/recept.css">
    <style>
        .stock-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .stock-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .stock-badge.low { background: #FFE5E5; color: #cc0000; }
        .stock-badge.normal { background: #E8F5E9; color: #4caf50; }
        .stock-badge.warning { background: #FFF3E0; color: #ff9800; }
        .stock-quantity { font-weight: 700; font-size: 1rem; }
        .stock-quantity.low { color: #cc0000; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
        .modal-box { background: white; border-radius: 12px; padding: 25px; max-width: 450px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--green); }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-sm { padding: 5px 12px; font-size: 0.75rem; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 5px; color: var(--text2); }
        .input { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; }
        .movement-item.in { border-left: 3px solid #4caf50; }
        .movement-item.out { border-left: 3px solid #cc0000; }
        .stock-low { background: #FFF3E0 !important; }
        .alert-success { background: #E8F5E9; color: #2e7d32; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-error { background: #FFEBEE; color: #c62828; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .tbl-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn-green { background: #4caf50; color: white; }
        .btn-amber { background: #ff9800; color: white; }
        .btn-blue { background: #2196f3; color: white; }
        .btn-secondary { background: #9e9e9e; color: white; }
        .manual-section { background: var(--surf2); border-radius: var(--r); padding: 15px; margin-bottom: 20px; border-left: 4px solid var(--amber); }
    </style>
</head>
<body>
<?php include 'lab_menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">🧴 Gestion des consommables</span>
        <div class="topbar-right"><span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span></div>
    </div>
    <div class="page-body">

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert-success" style="padding:12px; margin-bottom:20px; border-radius:8px;">
                <?php
                $msg = $_GET['msg'];
                if ($msg === 'updated') echo '✅ Stock mis à jour avec succès';
                elseif ($msg === 'added') echo '✅ Consommable ajouté avec succès';
                elseif ($msg === 'removed') echo '✅ Quantité retirée du stock';
                elseif ($msg === 'manual_consumed') echo '✅ Consommation manuelle enregistrée';
                elseif ($msg === 'requested') echo '✅ Demande de réapprovisionnement envoyée';
                else echo '✅ Opération réussie';
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error" style="padding:12px; margin-bottom:20px; border-radius:8px;">
                <?php
                $error = $_GET['error'];
                if ($error === 'not_enough') {
                    echo '❌ Stock insuffisant pour ' . htmlspecialchars($_GET['item'] ?? 'ce produit');
                } elseif ($error === 'add_amount') {
                    echo '❌ Veuillez saisir une quantité valide à ajouter';
                } elseif ($error === 'remove_amount') {
                    echo '❌ Veuillez saisir une quantité valide à retirer';
                } elseif ($error === 'manual') {
                    echo '❌ Erreur lors de la consommation manuelle';
                } else {
                    echo '❌ Une erreur est survenue';
                }
                ?>
            </div>
        <?php endif; ?>

        <div style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap">
            <!-- Liste des consommables -->
            <div style="flex:2; min-width:300px;">
                <div class="card">
                    <div class="card-head" style="display:flex; justify-content:space-between; align-items:center; padding:15px;">
                        <h3 style="margin:0;"><?php echo $alerts_view ? '⚠️ Alertes stock faible' : '📦 Tous les consommables'; ?></h3>
                        <a href="stock.php<?php echo $alerts_view ? '' : '?alerts=1'; ?>" class="btn <?php echo $alerts_view ? 'btn-secondary' : 'btn-danger'; ?> btn-sm">
                            <?php echo $alerts_view ? 'Voir tout' : 'Voir alertes'; ?>
                        </a>
                    </div>
                    <div style="overflow-x:auto">
                        <table class="tbl" style="width:100%; border-collapse:collapse;">
                            <thead style="background:var(--surf2);">
                            <tr>
                                <th style="padding:12px; text-align:left;">Produit</th>
                                <th style="padding:12px; text-align:left;">Catégorie</th>
                                <th style="padding:12px; text-align:left;">Stock</th>
                                <th style="padding:12px; text-align:left;">Seuil</th>
                                <th style="padding:12px; text-align:left;">Emplacement</th>
                                <th style="padding:12px; text-align:left;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $item):
                                $is_low = $item['quantity'] <= $item['threshold_alert'];
                                $status_class = $is_low ? 'low' : ($item['quantity'] <= $item['threshold_alert'] * 2 ? 'warning' : 'normal');
                                ?>
                                <tr class="<?php echo $is_low ? 'stock-low' : ''; ?>" style="border-bottom:1px solid var(--border);">
                                    <td style="padding:12px;">
                                        <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>
                                        <span class="stock-badge <?php echo $status_class; ?>"><?php echo $status_class === 'low' ? '⚠️ Stock bas' : ($status_class === 'warning' ? '📊 Stock moyen' : '✅ Stock OK'); ?></span>
                                    </td>
                                    <td style="padding:12px;"><?php echo LAB_CATEGORIES[$item['category']] ?? $item['category']; ?></td>
                                    <td style="padding:12px;" class="stock-quantity <?php echo $is_low ? 'low' : ''; ?>">
                                        <strong><?php echo formatQuantity($item['quantity']); ?></strong> <?php echo htmlspecialchars($item['unit']); ?>
                                    </td>
                                    <td style="padding:12px;"><?php echo formatQuantity($item['threshold_alert']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td style="padding:12px;"><?php echo htmlspecialchars($item['location'] ?: '—'); ?></td>
                                    <td style="padding:12px;">
                                        <div class="tbl-actions">
                                            <button class="btn btn-green btn-sm" onclick="openAddModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', <?php echo $item['quantity']; ?>, '<?php echo addslashes($item['unit']); ?>')">
                                                ➕ Ajouter
                                            </button>
                                            <button class="btn btn-amber btn-sm" onclick="openRemoveModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', <?php echo $item['quantity']; ?>, '<?php echo addslashes($item['unit']); ?>')">
                                                ➖ Retirer
                                            </button>
                                            <button class="btn btn-secondary btn-sm" onclick="openStockModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', <?php echo $item['quantity']; ?>, '<?php echo addslashes($item['unit']); ?>')">
                                                ✏️ Modifier
                                            </button>
                                            <button type="button" class="btn btn-blue btn-sm" onclick="openOrderModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', '<?php echo addslashes($item['unit']); ?>')">
                                                📦 Commander
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="6" class="empty" style="text-align:center; padding:40px;">Aucun consommable trouvé</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section Gestion Manuelle des Consommables Généraux -->
                <div class="manual-section">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 style="margin:0;">🧴 Consommables à gestion manuelle</h3>
                        <button class="btn btn-primary btn-sm" onclick="showManualConsumptionModal()">➕ Ajouter consommation manuelle</button>
                    </div>
                    <div style="overflow-x:auto">
                        <table class="tbl" style="width:100%; border-collapse:collapse;">
                            <thead style="background:var(--surf2);">
                            <tr>
                                <th style="padding:12px; text-align:left;">Produit</th>
                                <th style="padding:12px; text-align:left;">Stock actuel</th>
                                <th style="padding:12px; text-align:left;">Unité</th>
                                <th style="padding:12px; text-align:left;">Seuil</th>
                                <th style="padding:12px; text-align:left;">Emplacement</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($manual_consumables as $item):
                                $is_low_manual = $item['quantity'] <= $item['threshold_alert'];
                                ?>
                                <tr style="border-bottom:1px solid var(--border);">
                                    <td style="padding:12px;"><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                    <td style="padding:12px;" class="<?php echo $is_low_manual ? 'stock-quantity low' : ''; ?>"><?php echo formatQuantity($item['quantity']); ?></td>
                                    <td style="padding:12px;"><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td style="padding:12px;"><?php echo formatQuantity($item['threshold_alert']); ?></td>
                                    <td style="padding:12px;"><?php echo htmlspecialchars($item['location'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($manual_consumables)): ?>
                                <tr><td colspan="5" class="empty" style="text-align:center; padding:40px;">Aucun consommable à gestion manuelle</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info" style="margin-top:15px; font-size:0.75rem;">
                        💡 <strong>Info :</strong> Ces consommables (alcool, coton, gants, etc.) ne sont pas déduits automatiquement.
                        Utilisez le bouton "Ajouter consommation manuelle" pour les retirer du stock.
                    </div>
                </div>

                <!-- Historique des mouvements -->
                <div class="card" style="margin-top:20px">
                    <div class="card-head" style="padding:15px;"><h3 style="margin:0;">📋 Historique des mouvements</h3></div>
                    <div style="overflow-x:auto">
                        <table class="tbl" style="width:100%; border-collapse:collapse;">
                            <thead style="background:var(--surf2);">
                            <tr>
                                <th style="padding:12px; text-align:left;">Date</th>
                                <th style="padding:12px; text-align:left;">Produit</th>
                                <th style="padding:12px; text-align:left;">Opération</th>
                                <th style="padding:12px; text-align:left;">Quantité</th>
                                <th style="padding:12px; text-align:left;">Raison</th>
                                <th style="padding:12px; text-align:left;">Par</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_slice($movements, 0, 30) as $m): ?>
                                <tr class="movement-item <?php echo $m['operation']; ?>" style="border-bottom:1px solid var(--border);">
                                    <td style="padding:12px; font-size:.7rem;"><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                    <td style="padding:12px;"><strong><?php echo htmlspecialchars($m['item_name'] ?? '—'); ?></strong></td>
                                    <td style="padding:12px;">
                                        <span class="badge <?php echo $m['operation'] === 'in' ? 'badge-completed' : 'badge-pending'; ?>">
                                            <?php echo $m['operation'] === 'in' ? '+ Entrée' : '- Sortie'; ?>
                                        </span>
                                    </td>
                                    <td style="padding:12px;"><strong><?php echo formatQuantity($m['quantity']); ?></strong></td>
                                    <td style="padding:12px; font-size:.75rem;"><?php echo htmlspecialchars($m['reason'] ?: '—'); ?></td>
                                    <td style="padding:12px;"><?php echo htmlspecialchars($m['performed_by_name'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Ajouter un consommable -->
            <div style="flex:1; max-width:350px; min-width:280px;">
                <div class="card">
                    <div class="card-head" style="padding:15px;"><h3 style="margin:0;">➕ Ajouter un consommable</h3></div>
                    <div class="card-body" style="padding:15px;">
                        <form method="POST" action="stock.php">
                            <input type="hidden" name="action" value="add_stock_item">
                            <div class="form-group">
                                <label>Nom *</label>
                                <input class="input" type="text" name="item_name" required>
                            </div>
                            <div class="form-group">
                                <label>Catégorie</label>
                                <select class="input" name="category">
                                    <?php foreach($categories as $k=>$l) echo "<option value='$k'>$l</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantité initiale</label>
                                <input class="input" type="number" step="0.01" name="quantity" value="0">
                            </div>
                            <div class="form-group">
                                <label>Unité</label>
                                <input class="input" type="text" name="unit" placeholder="ml, g, pièce, litre...">
                            </div>
                            <div class="form-group">
                                <label>Seuil d'alerte</label>
                                <input class="input" type="number" step="0.01" name="threshold" value="10">
                            </div>
                            <div class="form-group">
                                <label>Emplacement</label>
                                <input class="input" type="text" name="location" placeholder="Réfrigérateur A1, Armoire B2...">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%">➕ Ajouter</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal AJOUTER une quantité -->
<div id="addModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-title">➕ Ajouter au stock</div>
        <form method="POST" action="stock.php">
            <input type="hidden" name="action" value="add_stock">
            <input type="hidden" name="item_id" id="add_item_id">
            <div class="form-group">
                <label>Produit</label>
                <div id="add_item_name" style="font-weight:700; font-size:1rem;"></div>
            </div>
            <div class="form-group">
                <label>Stock actuel</label>
                <div id="add_current_stock" style="padding:5px 0;"></div>
            </div>
            <div class="form-group">
                <label>Quantité à ajouter *</label>
                <input class="input" type="number" step="0.01" name="add_quantity" id="add_quantity" required placeholder="Ex: 10, 5.5, 100">
            </div>
            <div class="form-group">
                <label>Raison</label>
                <select class="input" name="reason">
                    <option value="Réapprovisionnement">📦 Réapprovisionnement</option>
                    <option value="Retour fournisseur">🔄 Retour fournisseur</option>
                    <option value="Ajustement inventaire (+)">📊 Ajustement inventaire (+)</option>
                    <option value="Don / Réception">🎁 Don / Réception</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">✅ Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal RETIRER une quantité -->
<div id="removeModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-title">➖ Retirer du stock</div>
        <form method="POST" action="stock.php">
            <input type="hidden" name="action" value="remove_stock">
            <input type="hidden" name="item_id" id="remove_item_id">
            <div class="form-group">
                <label>Produit</label>
                <div id="remove_item_name" style="font-weight:700; font-size:1rem;"></div>
            </div>
            <div class="form-group">
                <label>Stock actuel</label>
                <div id="remove_current_stock" style="padding:5px 0;"></div>
            </div>
            <div class="form-group">
                <label>Quantité à retirer *</label>
                <input class="input" type="number" step="0.01" name="remove_quantity" id="remove_quantity" required placeholder="Ex: 5, 2.5, 50">
                <small style="color: var(--text2); font-size:0.7rem">Maximum disponible: <span id="remove_max_qty">0</span></small>
            </div>
            <div class="form-group">
                <label>Raison</label>
                <select class="input" name="reason">
                    <option value="Consommation analyse">🔬 Consommation pour analyse</option>
                    <option value="Perte / Casse">💔 Perte / Casse</option>
                    <option value="Périmé - Retrait">⚠️ Périmé - Retrait</option>
                    <option value="Ajustement inventaire (-)">📊 Ajustement inventaire (-)</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRemoveModal()">Annuler</button>
                <button type="submit" class="btn btn-amber">✅ Retirer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal CONSOMMATION MANUELLE -->
<div id="manualConsumptionModal" class="modal-overlay" style="display:none">
    <div class="modal-box" style="max-width:450px">
        <div class="modal-title">📝 Consommation manuelle</div>
        <form method="POST" action="stock.php">
            <input type="hidden" name="action" value="manual_consume">
            <div class="form-group">
                <label>Produit *</label>
                <select class="input" name="item_id" id="manual_item_id" required>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($manual_consumables as $item): ?>
                        <option value="<?php echo $item['id']; ?>" data-unit="<?php echo htmlspecialchars($item['unit']); ?>" data-stock="<?php echo $item['quantity']; ?>">
                            <?php echo htmlspecialchars($item['item_name']); ?> (Stock: <?php echo formatQuantity($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantité *</label>
                <input class="input" type="number" step="0.01" name="quantity" id="manual_quantity" required placeholder="Ex: 10, 5.5, 100">
                <small id="manual_stock_warning" style="color: #ff9800; display:none;">⚠️ Attention: La quantité dépasse le stock disponible !</small>
            </div>
            <div class="form-group">
                <label>Raison</label>
                <select class="input" name="reason">
                    <option value="Consommation manuelle">📝 Consommation manuelle</option>
                    <option value="Perte / Casse">💔 Perte / Casse</option>
                    <option value="Périmé - Retrait">⚠️ Périmé - Retrait</option>
                    <option value="Ajustement inventaire (-)">📊 Ajustement inventaire (-)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Analyse associée (optionnel)</label>
                <select class="input" name="test_id">
                    <option value="">-- Aucune --</option>
                    <?php
                    $recent_tests = $database->query("
                        SELECT lt.id, lt.test_name, u.full_name 
                        FROM lab_tests lt
                        JOIN patients p ON p.id = lt.patient_id
                        JOIN users u ON u.id = p.user_id
                        WHERE lt.status = 'completed'
                        ORDER BY lt.id DESC LIMIT 20
                    ");
                    while($test = $recent_tests->fetch_assoc()):
                        ?>
                        <option value="<?php echo $test['id']; ?>">#<?php echo $test['id']; ?> - <?php echo htmlspecialchars($test['test_name']); ?> (<?php echo htmlspecialchars($test['full_name']); ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeManualModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">✅ Consommer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal MODIFICATION directe -->
<div id="stockModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-title">✏️ Modifier le stock (remplacement total)</div>
        <form method="POST" action="stock.php">
            <input type="hidden" name="action" value="update_stock">
            <input type="hidden" name="item_id" id="modal_item_id">
            <div class="form-group">
                <label>Produit</label>
                <div id="modal_item_name" style="font-weight:700;"></div>
            </div>
            <div class="form-group">
                <label>Nouvelle quantité totale</label>
                <input class="input" type="number" step="0.01" name="quantity" id="modal_quantity" required>
            </div>
            <div class="form-group">
                <label>Raison</label>
                <select class="input" name="reason">
                    <option value="Réapprovisionnement">📦 Réapprovisionnement</option>
                    <option value="Ajustement inventaire">📊 Ajustement inventaire</option>
                    <option value="Correction erreur">🔧 Correction erreur</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal(id, name, currentQty, unit) {
        document.getElementById('add_item_id').value = id;
        document.getElementById('add_item_name').innerHTML = name;
        document.getElementById('add_current_stock').innerHTML = formatNumber(currentQty) + ' ' + unit;
        document.getElementById('add_quantity').value = '';
        document.getElementById('addModal').style.display = 'flex';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function openRemoveModal(id, name, currentQty, unit) {
        document.getElementById('remove_item_id').value = id;
        document.getElementById('remove_item_name').innerHTML = name;
        document.getElementById('remove_current_stock').innerHTML = formatNumber(currentQty) + ' ' + unit;
        document.getElementById('remove_quantity').value = '';
        document.getElementById('remove_quantity').max = currentQty;
        document.getElementById('remove_max_qty').innerHTML = formatNumber(currentQty) + ' ' + unit;
        document.getElementById('removeModal').style.display = 'flex';
    }

    function closeRemoveModal() {
        document.getElementById('removeModal').style.display = 'none';
    }

    function openStockModal(id, name, qty, unit) {
        document.getElementById('modal_item_id').value = id;
        document.getElementById('modal_item_name').innerHTML = name + ' (' + unit + ')';
        document.getElementById('modal_quantity').value = qty;
        document.getElementById('stockModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('stockModal').style.display = 'none';
    }

    function showManualConsumptionModal() {
        document.getElementById('manualConsumptionModal').style.display = 'flex';
    }

    function closeManualModal() {
        document.getElementById('manualConsumptionModal').style.display = 'none';
    }

    // Fermer les modals en cliquant à l'extérieur
    document.getElementById('addModal')?.addEventListener('click', function(e) {
        if(e.target === this) closeAddModal();
    });
    document.getElementById('removeModal')?.addEventListener('click', function(e) {
        if(e.target === this) closeRemoveModal();
    });
    document.getElementById('stockModal')?.addEventListener('click', function(e) {
        if(e.target === this) closeModal();
    });
    document.getElementById('manualConsumptionModal')?.addEventListener('click', function(e) {
        if(e.target === this) closeManualModal();
    });

    function formatNumber(n) {
        if (n === null || n === undefined) return '0';
        if (Math.floor(parseFloat(n)) == parseFloat(n)) {
            return new Intl.NumberFormat('fr-DZ', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(n);
        }
        return new Intl.NumberFormat('fr-DZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
    }

    // Validation de la quantité à retirer
    document.getElementById('remove_quantity')?.addEventListener('change', function() {
        let max = parseFloat(this.max);
        let val = parseFloat(this.value);
        if (val > max) {
            alert('La quantité à retirer ne peut pas dépasser le stock disponible (' + formatNumber(max) + ')');
            this.value = max;
        }
    });

    // Validation manuelle - vérification du stock
    const manualItemSelect = document.getElementById('manual_item_id');
    const manualQuantity = document.getElementById('manual_quantity');
    const manualWarning = document.getElementById('manual_stock_warning');

    function checkManualStock() {
        if (manualItemSelect && manualQuantity) {
            const selectedOption = manualItemSelect.options[manualItemSelect.selectedIndex];
            const maxStock = selectedOption ? parseFloat(selectedOption.dataset.stock) : 0;
            const qty = parseFloat(manualQuantity.value) || 0;

            if (qty > maxStock && maxStock > 0) {
                manualWarning.style.display = 'block';
            } else {
                manualWarning.style.display = 'none';
            }
        }
    }

    manualItemSelect?.addEventListener('change', checkManualStock);
    manualQuantity?.addEventListener('input', checkManualStock);
</script>
<!-- Modal COMMANDER (insère dans orders) -->
<div id="orderModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-title">📦 Commander un réapprovisionnement</div>
        <form method="POST" action="stock.php">
            <input type="hidden" name="action" value="request_stock">
            <input type="hidden" name="item_id" id="order_item_id">
            <div class="form-group">
                <label>Produit</label>
                <div id="order_item_name" style="font-weight:700; font-size:1rem;"></div>
            </div>
            <div class="form-group">
                <label>Quantité à commander *</label>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input class="input" type="number" name="quantity" id="order_quantity" min="1" value="1" required style="flex:1;">
                    <span id="order_unit" style="color:var(--text2); font-size:0.85rem;"></span>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Annuler</button>
                <button type="submit" class="btn btn-blue">📦 Envoyer la commande</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openOrderModal(id, name, unit) {
        document.getElementById('order_item_id').value = id;
        document.getElementById('order_item_name').innerHTML = name;
        document.getElementById('order_unit').innerHTML = unit;
        document.getElementById('order_quantity').value = 1;
        document.getElementById('orderModal').style.display = 'flex';
    }
    function closeOrderModal() {
        document.getElementById('orderModal').style.display = 'none';
    }
    document.getElementById('orderModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeOrderModal();
    });
</script>

</body>
</html>