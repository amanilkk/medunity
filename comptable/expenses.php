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

// === TRAITEMENT DES ACTIONS CRUD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // AJOUTER une commande
    if ($_POST['action'] === 'add_order') {
        $supplier_id = intval($_POST['supplier_id']);
        $total_amount = floatval($_POST['total_amount']);
        $notes = $_POST['notes'] ?? '';

        $result = createPurchaseOrder($database, $supplier_id, $total_amount, $_SESSION['user_id'], $notes);
        if ($result['success']) {
            $message = 'Commande créée avec succès !';
        } else {
            $error = $result['message'];
        }
    }

    // MODIFIER une commande
    if ($_POST['action'] === 'edit_order') {
        $order_id = intval($_POST['order_id']);
        $supplier_id = intval($_POST['supplier_id']);
        $total_amount = floatval($_POST['total_amount']);
        $notes = $_POST['notes'] ?? '';

        $result = updatePurchaseOrder($database, $order_id, $supplier_id, $total_amount, $notes);
        if ($result['success']) {
            $message = 'Commande modifiée avec succès !';
        } else {
            $error = $result['message'];
        }
    }

    // SUPPRIMER une commande
    if ($_POST['action'] === 'delete_order') {
        $order_id = intval($_POST['order_id']);

        $result = deletePurchaseOrder($database, $order_id);
        if ($result['success']) {
            $message = 'Commande supprimée avec succès !';
        } else {
            $error = $result['message'];
        }
    }

    // CHANGER LE STATUT d'une commande
    if ($_POST['action'] === 'change_status') {
        $order_id = intval($_POST['order_id']);
        $new_status = $_POST['new_status'];

        $result = updatePurchaseOrderStatus($database, $order_id, $new_status);
        if ($result['success']) {
            $message = 'Statut de la commande mis à jour !';
        } else {
            $error = $result['message'];
        }
    }
}

// === RÉCUPÉRATION D'UNE COMMANDE POUR MODIFICATION ===
$edit_order = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $database->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $edit_order = $stmt->get_result()->fetch_assoc();
}

// === FILTRES ===
$status_filter = $_GET['status'] ?? 'all';
$month_filter = $_GET['month'] ?? date('Y-m');

$sql = "SELECT po.*, s.name as supplier_name 
        FROM purchase_orders po
        LEFT JOIN suppliers s ON s.id = po.supplier_id
        WHERE 1=1";

if ($status_filter !== 'all') {
    $sql .= " AND po.status = '$status_filter'";
}
if ($month_filter) {
    $sql .= " AND DATE_FORMAT(po.order_date, '%Y-%m') = '$month_filter'";
}
$sql .= " ORDER BY po.order_date DESC";

$orders = $database->query($sql);

// Fournisseurs
$suppliers = $database->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");

// Statistiques
$total_expenses_month = safeCount($database,
        "SELECT COALESCE(SUM(total_amount),0) c FROM purchase_orders 
     WHERE DATE_FORMAT(order_date, '%Y-%m') = ? AND status = 'delivered'",
        's', [$month_filter]);

$pending_orders = safeCount($database,
        "SELECT COUNT(*) c FROM purchase_orders WHERE status IN ('pending', 'confirmed')", 's');

$total_orders = safeCount($database, "SELECT COUNT(*) c FROM purchase_orders", 's');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dépenses - Comptable</title>
    <link rel="stylesheet" href="comptable.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: var(--surface);
            border-radius: var(--r);
            padding: 24px;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--sh);
        }
        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text3);
        }
        .btn-xs {
            padding: 4px 8px;
            font-size: 0.7rem;
        }
        .status-select {
            padding: 4px 8px;
            border-radius: var(--rs);
            border: 1px solid var(--border);
            font-size: 0.7rem;
        }
        /* Styles pour la section filtres */
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 20px;
            padding: 20px;
            background: var(--surface);
            border-radius: var(--r);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 180px;
        }

        .filter-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-icon {
            stroke: var(--green);
            fill: none;
            stroke-width: 2;
        }

        .filter-select {
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--rs);
            background: var(--surface);
            font-family: inherit;
            font-size: 0.85rem;
            color: var(--text);
            cursor: pointer;
            transition: all 0.15s;
        }

        .filter-select:hover {
            border-color: var(--green);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(26, 107, 74, 0.1);
        }

        .filter-month {
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--rs);
            background: var(--surface);
            font-family: inherit;
            font-size: 0.85rem;
            color: var(--text);
            cursor: pointer;
            transition: all 0.15s;
        }

        .filter-month:hover {
            border-color: var(--green);
        }

        .filter-month:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(26, 107, 74, 0.1);
        }

        .filter-actions {
            display: flex;
            align-items: center;
        }

        .btn-reset {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--rs);
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text2);
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-reset:hover {
            background: var(--border);
            color: var(--text);
        }

        .btn-reset svg {
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .filter-group {
                min-width: auto;
            }

            .filter-actions {
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des dépenses</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary btn-sm">
                ➕ Nouvelle commande
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

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="value" style="color: var(--blue);"><?php echo $total_orders; ?></div>
                <div class="label">Total commandes</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--red);"><?php echo number_format($total_expenses_month, 0, ',', ' '); ?> DA</div>
                <div class="label">Dépenses du mois</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--amber);"><?php echo $pending_orders; ?></div>
                <div class="label">Commandes en attente</div>
            </div>
        </div>

        <!-- Filtres -->
        <!-- Filtres -->
        <div class="card">
            <div class="card-head">
                <h3>🔍 Filtrer les commandes</h3>
            </div>
            <div class="filter-container">
                <div class="filter-group">
                    <label class="filter-label">
                        <svg viewBox="0 0 24 24" width="14" height="14" class="filter-icon">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        Statut
                    </label>
                    <select onchange="window.location.href='?status='+this.value+'&month=<?php echo $month_filter; ?>'" class="filter-select">
                        <option value="all" <?php echo $status_filter=='all'?'selected':''; ?>>📋 Tous statuts</option>
                        <option value="pending" <?php echo $status_filter=='pending'?'selected':''; ?>>⏳ En attente</option>
                        <option value="confirmed" <?php echo $status_filter=='confirmed'?'selected':''; ?>>✓ Confirmée</option>
                        <option value="shipped" <?php echo $status_filter=='shipped'?'selected':''; ?>>📦 Expédiée</option>
                        <option value="delivered" <?php echo $status_filter=='delivered'?'selected':''; ?>>✅ Livrée</option>
                        <option value="cancelled" <?php echo $status_filter=='cancelled'?'selected':''; ?>>❌ Annulée</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">
                        <svg viewBox="0 0 24 24" width="14" height="14" class="filter-icon">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Mois
                    </label>
                    <input type="month" value="<?php echo $month_filter; ?>" onchange="window.location.href='?status=<?php echo $status_filter; ?>&month='+this.value" class="filter-month">
                </div>

                <div class="filter-actions">
                    <button onclick="window.location.href='?status=all&month=<?php echo date('Y-m'); ?>'" class="btn-reset">
                        <svg viewBox="0 0 24 24" width="14" height="14">
                            <path d="M3 12a9 9 0 1 0 9-9m-4 4 4-4 4 4M5 5v4h4"/>
                        </svg>
                        Réinitialiser
                    </button>
                </div>
            </div>
        </div>

        <!-- Liste des commandes -->
        <div class="card">
            <div class="card-head">
                <h3>Commandes fournisseurs</h3>
            </div>
            <?php if ($orders->num_rows === 0): ?>
                <div class="empty"><p>Aucune commande trouvée</p></div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Fournisseur</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($o = $orders->fetch_assoc()):
                            $status_class = '';
                            if ($o['status'] == 'delivered') $status_class = 'badge-paid';
                            elseif ($o['status'] == 'cancelled') $status_class = 'badge-cancelled';
                            elseif ($o['status'] == 'confirmed') $status_class = 'badge-approved';
                            elseif ($o['status'] == 'shipped') $status_class = 'badge-info';
                            else $status_class = 'badge-pending';
                            ?>
                            <tr>
                                <td style="font-weight:600"><?php echo htmlspecialchars($o['order_number']); ?></td>
                                <td><?php echo htmlspecialchars($o['supplier_name'] ?? '—'); ?></td>
                                <td style="font-weight:600"><?php echo number_format($o['total_amount'], 0, ',', ' '); ?> DA</td>
                                <td><?php echo date('d/m/Y', strtotime($o['order_date'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                        <select name="new_status" class="status-select" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $o['status'] == 'pending' ? 'selected' : ''; ?>>⏳ En attente</option>
                                            <option value="confirmed" <?php echo $o['status'] == 'confirmed' ? 'selected' : ''; ?>>✓ Confirmée</option>
                                            <option value="shipped" <?php echo $o['status'] == 'shipped' ? 'selected' : ''; ?>>📦 Expédiée</option>
                                            <option value="delivered" <?php echo $o['status'] == 'delivered' ? 'selected' : ''; ?>>✅ Livrée</option>
                                            <option value="cancelled" <?php echo $o['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Annulée</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <div class="tbl-actions">
                                        <button onclick="editOrder(<?php echo $o['id']; ?>)" class="btn btn-secondary btn-xs">✏️ Modifier</button>
                                        <button onclick="confirmDelete(<?php echo $o['id']; ?>, '<?php echo addslashes($o['order_number']); ?>')" class="btn btn-danger btn-xs">🗑️ Supprimer</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal AJOUT -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h3>➕ Nouvelle commande</h3>
            <button onclick="closeModal('addModal')" class="modal-close">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_order">
            <div class="form-group">
                <label>Fournisseur *</label>
                <select name="supplier_id" class="input" required>
                    <option value="">-- Sélectionner --</option>
                    <?php
                    $suppliers->data_seek(0);
                    while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Montant total *</label>
                <input type="number" name="total_amount" class="input" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="input" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Créer la commande</button>
            <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">Annuler</button>
        </form>
    </div>
</div>

<!-- Modal MODIFICATION -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h3>✏️ Modifier la commande</h3>
            <button onclick="closeModal('editModal')" class="modal-close">×</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit_order">
            <input type="hidden" name="order_id" id="edit_order_id">
            <div class="form-group">
                <label>Fournisseur *</label>
                <select name="supplier_id" id="edit_supplier_id" class="input" required>
                    <option value="">-- Sélectionner --</option>
                    <?php
                    $suppliers->data_seek(0);
                    while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Montant total *</label>
                <input type="number" name="total_amount" id="edit_total_amount" class="input" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="edit_notes" class="input" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">Annuler</button>
        </form>
    </div>
</div>

<!-- Formulaire suppression -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_order">
    <input type="hidden" name="order_id" id="delete_order_id">
</form>

<script>
    // Stocker les données des commandes pour l'édition
    const ordersData = <?php
            $orders->data_seek(0);
            $orders_array = [];
            while ($o = $orders->fetch_assoc()) {
                $orders_array[] = $o;
            }
            echo json_encode($orders_array);
            ?>;

    function editOrder(id) {
        const order = ordersData.find(o => o.id == id);
        if (order) {
            document.getElementById('edit_order_id').value = order.id;
            document.getElementById('edit_supplier_id').value = order.supplier_id;
            document.getElementById('edit_total_amount').value = order.total_amount;
            document.getElementById('edit_notes').value = order.notes || '';
            document.getElementById('editModal').style.display = 'flex';
        }
    }

    function confirmDelete(id, orderNumber) {
        if (confirm(`Supprimer la commande "${orderNumber}" ? Cette action est irréversible.`)) {
            document.getElementById('delete_order_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Fermer modale en cliquant en dehors
    window.onclick = function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    }
</script>

</body>
</html>
