<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  pharmacien_orders.php — Gestion des commandes pour le pharmacien
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['role'] ?? null) !== 'pharmacien') {
    header('Location: ../login.php');
    exit;
}

include '../connection.php';
include 'functions.php';

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Actions POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_order_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status   = $_POST['status'] ?? '';
    $valid    = ['approved', 'rejected', 'completed'];

    if ($order_id && in_array($status, $valid)) {

        // Si la commande est approuvée, déduire du stock labo
        if ($status === 'approved') {
            // Récupérer les détails de la commande
            $order_stmt = $database->prepare("
                SELECT item_id, quantity, item_type 
                FROM orders 
                WHERE id = ?
            ");
            $order_stmt->bind_param('i', $order_id);
            $order_stmt->execute();
            $order_details = $order_stmt->get_result()->fetch_assoc();

            if ($order_details && $order_details['item_type'] === 'stock') {
                // Vérifier le stock disponible
                $stock_stmt = $database->prepare("
                    SELECT quantity, item_name 
                    FROM lab_stock 
                    WHERE id = ?
                ");
                $stock_stmt->bind_param('i', $order_details['item_id']);
                $stock_stmt->execute();
                $stock = $stock_stmt->get_result()->fetch_assoc();

                if ($stock && $stock['quantity'] >= $order_details['quantity']) {
                    // Diminuer le stock
                    $update_stmt = $database->prepare("
                        UPDATE lab_stock 
                        SET quantity = quantity - ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->bind_param('ii', $order_details['quantity'], $order_details['item_id']);
                    $update_stmt->execute();

                    // Enregistrer le mouvement
                    $movement_stmt = $database->prepare("
                        INSERT INTO lab_stock_movements 
                        (item_id, operation, quantity, reason, performed_by, created_at)
                        VALUES (?, 'remove', ?, ?, ?, NOW())
                    ");
                    $reason = "Commande laboratoire approuvée #" . $order_id;
                    $movement_stmt->bind_param('iisi', $order_details['item_id'], $order_details['quantity'], $reason, $user_id);
                    $movement_stmt->execute();
                } else {
                    // Stock insuffisant - ne pas approuver
                    $_SESSION['error_msg'] = "Stock insuffisant pour approuver cette commande";
                    header('Location: pharmacien_orders.php?error=stock_insufficient');
                    exit;
                }
            }
        }

        // Mettre à jour le statut de la commande
        $stmt = $database->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $order_id);
        $stmt->execute();

        // Enregistrer dans les logs (version corrigée)
        $details = json_encode([
                'action' => 'update_order_status',
                'order_id' => $order_id,
                'new_status' => $status,
                'user_id' => $user_id
        ]);

        $log = $database->prepare("
            INSERT INTO logs (user_id, action, details, created_at)
            VALUES (?, 'update_order_status', ?, NOW())
        ");
        $log->bind_param('is', $user_id, $details);
        $log->execute();

        header('Location: pharmacien_orders.php?msg=updated');
        exit;
    }
}

// ── Récupération des commandes ────────────────────────────────────
$status_filter = $_GET['status'] ?? '';

$sql = "
    SELECT o.*,
           u.full_name  AS requester_name,
           s.item_name  AS stock_item_name,
           s.unit       AS stock_unit,
           s.quantity   AS current_stock
    FROM orders o
    JOIN users u ON u.id = o.requester_id
    LEFT JOIN lab_stock s ON s.id = o.item_id AND o.item_type = 'stock'
    WHERE o.item_type = 'stock'
";

if ($status_filter) {
    $sql .= " AND o.status = ?";
    $stmt = $database->prepare($sql . " ORDER BY FIELD(o.status,'pending','approved','completed','rejected'), o.created_at DESC");
    $stmt->bind_param('s', $status_filter);
} else {
    $stmt = $database->prepare($sql . " ORDER BY FIELD(o.status,'pending','approved','completed','rejected'), o.created_at DESC");
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistiques
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
$all_stmt = $database->query("SELECT status, COUNT(*) c FROM orders WHERE item_type='stock' GROUP BY status");
while ($row = $all_stmt->fetch_assoc()) {
    if (isset($stats[$row['status']])) $stats[$row['status']] = $row['c'];
}

// Messages d'erreur
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commandes laboratoire - Pharmacien</title>
    <link rel="stylesheet" href="pharmacien.css">
    <style>
        .order-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); margin-bottom:15px; overflow:hidden; }
        .order-header { padding:15px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-left:5px solid var(--border); }
        .order-header.pending  { border-left-color:var(--amber);  background:var(--amber-l);  }
        .order-header.approved { border-left-color:var(--green);  background:var(--green-l);  }
        .order-header.rejected { border-left-color:var(--red);    background:var(--red-l);    }
        .order-header.completed{ border-left-color:#4caf50;       background:#E8F5E9;          }
        .order-body { padding:15px 20px; }
        .status-badge { padding:4px 12px; border-radius:20px; font-size:.7rem; font-weight:600; }
        .status-pending  { background:var(--amber-l); color:var(--amber); }
        .status-approved { background:var(--green-l); color:var(--green); }
        .status-rejected { background:var(--red-l);   color:var(--red);   }
        .status-completed{ background:#E8F5E9;         color:#4caf50;      }
        .filter-bar { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center; }
        .filter-btn { padding:6px 15px; border-radius:20px; text-decoration:none; font-size:.85rem; border:1px solid var(--border); background:var(--surface); color:var(--text); }
        .filter-btn.active { background:var(--green); color:white; border-color:var(--green); }
        .btn-sm { padding:5px 12px; font-size:.75rem; }
        .btn-approve  { background:var(--green); color:white; }
        .btn-reject   { background:var(--red);   color:white; }
        .btn-complete { background:#4caf50;       color:white; }
        .stock-info { background:var(--surf2); border-radius:6px; padding:8px 12px; font-size:.8rem; margin-top:10px; display:inline-block; }
        .stock-low  { color:var(--red); font-weight:700; }
        .alert-error-custom {
            background: var(--red-l);
            color: var(--red);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--red);
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">📦 Commandes du laboratoire</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="medicines.php" class="btn btn-secondary btn-sm">
                ← Retour
            </a>
        </div>
    </div>
    <div class="page-body">

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                ✅ Statut de la commande mis à jour avec succès
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert-error-custom">
                <svg viewBox="0 0 24 24" width="16" height="16" style="margin-right:8px;">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats">
            <div class="stat">
                <div class="stat-ico a">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num" style="color:var(--amber)"><?php echo $stats['pending']; ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico b">
                    <svg viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num" style="color:var(--green)"><?php echo $stats['approved']; ?></div>
                    <div class="stat-lbl">Approuvées</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico r">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num" style="color:var(--red)"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-lbl">Rejetées</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico y">
                    <svg viewBox="0 0 24 24">
                        <path d="M9 12h6M12 9v6"/>
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num" style="color:#4caf50"><?php echo $stats['completed']; ?></div>
                    <div class="stat-lbl">Livrées</div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filter-bar">
            <a href="?status="         class="filter-btn <?php echo !$status_filter ? 'active' : ''; ?>">Toutes (<?php echo array_sum($stats); ?>)</a>
            <a href="?status=pending"  class="filter-btn <?php echo $status_filter==='pending'  ? 'active' : ''; ?>">⏳ En attente (<?php echo $stats['pending']; ?>)</a>
            <a href="?status=approved" class="filter-btn <?php echo $status_filter==='approved' ? 'active' : ''; ?>">✅ Approuvées (<?php echo $stats['approved']; ?>)</a>
            <a href="?status=completed"class="filter-btn <?php echo $status_filter==='completed'? 'active' : ''; ?>">📦 Livrées (<?php echo $stats['completed']; ?>)</a>
            <a href="?status=rejected" class="filter-btn <?php echo $status_filter==='rejected' ? 'active' : ''; ?>">❌ Rejetées (<?php echo $stats['rejected']; ?>)</a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty" style="text-align:center; padding:60px; color:var(--text2);">
                <svg viewBox="0 0 24 24" width="60" height="60" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M16 3v2H8V3H6v2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3h-2zm-8 8h8v2H8v-2z"/>
                </svg>
                <h3>Aucune commande trouvée</h3>
                <p>Aucune commande laboratoire enregistrée</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order):
                $is_low = ($order['current_stock'] !== null) && ($order['current_stock'] <= 10); // Seuil fixe à 10 pour lab_stock
                ?>
                <div class="order-card">
                    <div class="order-header <?php echo htmlspecialchars($order['status']); ?>">
                        <div>
                            <strong>📦 Commande #<?php echo $order['id']; ?></strong><br>
                            <small style="color:var(--text2);">
                                Demandé par : <strong><?php echo htmlspecialchars($order['requester_name']); ?></strong>
                                — <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                            </small>
                        </div>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php
                            $labels = ['pending'=>'⏳ En attente','approved'=>'✅ Approuvée','rejected'=>'❌ Rejetée','completed'=>'📦 Livrée'];
                            echo $labels[$order['status']] ?? $order['status'];
                            ?>
                        </span>
                    </div>
                    <div class="order-body">
                        <p><strong>🧴 Produit :</strong> <?php echo htmlspecialchars($order['stock_item_name'] ?? 'N/A'); ?></p>
                        <p><strong>📊 Quantité demandée :</strong>
                            <strong><?php echo $order['quantity']; ?></strong>
                            <?php echo htmlspecialchars($order['stock_unit'] ?? ''); ?>
                        </p>

                        <?php if ($order['current_stock'] !== null): ?>
                            <div class="stock-info">
                                Stock actuel :
                                <span class="<?php echo $is_low ? 'stock-low' : ''; ?>">
                                    <?php echo $order['current_stock']; ?> <?php echo htmlspecialchars($order['stock_unit'] ?? ''); ?>
                                </span>
                                <?php if ($is_low): ?> ⚠️ Stock bas (seuil: 10)<?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Actions selon statut -->
                        <?php if ($order['status'] === 'pending'): ?>
                            <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                                <form method="POST" onsubmit="return confirmStock(<?php echo $order['id']; ?>, <?php echo $order['quantity']; ?>, <?php echo $order['current_stock'] ?? 0; ?>)">
                                    <input type="hidden" name="action"   value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="status"   value="approved">
                                    <button type="submit" class="btn btn-approve btn-sm">
                                        ✅ Approuver & déduire stock
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="action"   value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="status"   value="rejected">
                                    <button type="submit" class="btn btn-reject btn-sm"
                                            onclick="return confirm('Rejeter cette commande ?')">
                                        ❌ Rejeter
                                    </button>
                                </form>
                            </div>

                        <?php elseif ($order['status'] === 'approved'): ?>
                            <div style="margin-top:15px;">
                                <span class="badge badge-green">✅ Stock déduit</span>
                                <a href="lab_stock_movements.php?item_id=<?php echo $order['item_id']; ?>" class="btn btn-secondary btn-sm" style="margin-left:10px;">
                                    Voir mouvements
                                </a>
                            </div>

                        <?php elseif ($order['status'] === 'rejected'): ?>
                            <p style="margin-top:10px; color:var(--red); font-size:.85rem;">❌ Cette commande a été rejetée.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<script>
    function confirmStock(orderId, requestedQty, currentStock) {
        if (currentStock !== null && currentStock < requestedQty) {
            alert('⚠️ Stock insuffisant !\nStock actuel : ' + currentStock + '\nQuantité demandée : ' + requestedQty);
            return false;
        }
        return confirm('Approuver cette commande ?\nLa quantité sera déduite du stock du laboratoire.');
    }
</script>

</body>
</html>