<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  orders.php — Suivi des commandes pour le laborantin
// ================================================================

require_once 'functions.php';
requireLaborantin();
include '../connection.php';

$user_id = getCurrentLaborantinId();

// Récupérer les commandes du laborantin
$stmt = $database->prepare("
    SELECT o.*, 
           s.item_name as stock_item_name,
           s.unit as stock_unit
    FROM orders o
    LEFT JOIN lab_stock s ON s.id = o.item_id AND o.item_type = 'stock'
    WHERE o.requester_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stats = getOrderStats($database, $user_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes commandes - Laborantin</title>
    <link rel="stylesheet" href="../receptionniste/recept.css">
    <style>
        .order-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            margin-bottom: 15px;
            overflow: hidden;
        }
        .order-header {
            padding: 15px 20px;
            background: var(--surf2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .order-header.pending { border-left: 4px solid var(--amber); }
        .order-header.approved { border-left: 4px solid var(--green); }
        .order-header.rejected { border-left: 4px solid var(--red); }
        .order-header.completed { border-left: 4px solid #4caf50; }
        .order-body {
            padding: 15px 20px;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending { background: var(--amber-l); color: var(--amber); }
        .status-approved { background: var(--green-l); color: var(--green); }
        .status-rejected { background: var(--red-l); color: var(--red); }
        .status-completed { background: #E8F5E9; color: #4caf50; }
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 15px 25px;
            text-align: center;
            flex: 1;
        }
        .stat-card .number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .stat-card .label {
            font-size: 0.75rem;
            color: var(--text2);
        }
    </style>
</head>
<body>
<?php include 'lab_menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">📦 Mes commandes</span>
    </div>
    <div class="page-body">

        <!-- Statistiques -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="number"><?php echo $stats['pending']; ?></div>
                <div class="label">En attente</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['approved']; ?></div>
                <div class="label">Approuvées</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['rejected']; ?></div>
                <div class="label">Rejetées</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['completed']; ?></div>
                <div class="label">Livrées</div>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty" style="text-align:center; padding:60px;">
                <p>📭 Aucune commande effectuée</p>
                <p>Les commandes apparaîtront ici lorsque vous créerez des analyses.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header <?php echo $order['status']; ?>">
                        <div>
                            <strong>📦 Commande #<?php echo $order['id']; ?></strong>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php
                                $status_labels = [
                                    'pending' => '⏳ En attente',
                                    'approved' => '✅ Approuvée',
                                    'rejected' => '❌ Rejetée',
                                    'completed' => '📦 Livrée'
                                ];
                                echo $status_labels[$order['status']] ?? $order['status'];
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="order-body">
                        <p><strong>📦 Produit:</strong> <?php echo htmlspecialchars($order['stock_item_name'] ?? 'N/A'); ?></p>
                        <p><strong>📊 Quantité:</strong> <?php echo $order['quantity']; ?> <?php echo htmlspecialchars($order['stock_unit'] ?? ''); ?></p>
                        <p><strong>📅 Date:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                        <?php if ($order['status'] === 'rejected'): ?>
                            <p class="alert-error" style="margin-top:10px; padding:8px;">❌ Commande rejetée</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>