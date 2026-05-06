<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// gmg_suppliers.php - Gestion des Fournisseurs et Commandes
session_start();
if(isset($_SESSION["user"])){
    if($_SESSION["user"]=="" or $_SESSION['usertype']!='maintenance'){
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");
date_default_timezone_set('Africa/Algiers');

// ── Ajouter fournisseur ──
if(isset($_POST['add_supplier'])){
    $name  = $database->real_escape_string($_POST['name']);
    $cp    = $database->real_escape_string($_POST['contact_person']??'');
    $phone = $database->real_escape_string($_POST['phone']??'');
    $email = $database->real_escape_string($_POST['email']??'');
    $addr  = $database->real_escape_string($_POST['address']??'');
    $type  = $database->real_escape_string($_POST['supplier_type']);
    $database->query("INSERT INTO suppliers (name,contact_person,phone,email,address,supplier_type,is_active) VALUES ('$name','$cp','$phone','$email','$addr','$type',1)");
    header("location: gmg_suppliers.php?tab=suppliers&msg=added");
    exit();
}

// ── Activer/Désactiver fournisseur ──
if(isset($_GET['toggle_supplier'])){
    $sid = intval($_GET['toggle_supplier']);
    $cur = $database->query("SELECT is_active FROM suppliers WHERE id=$sid")->fetch_assoc()['is_active'];
    $database->query("UPDATE suppliers SET is_active=".($cur?0:1)." WHERE id=$sid");
    header("location: gmg_suppliers.php?tab=suppliers");
    exit();
}

// ── Créer commande ──
if(isset($_POST['create_order'])){
    $sid    = intval($_POST['supplier_id']);
    $odate  = $database->real_escape_string($_POST['order_date']);
    $ddate  = $database->real_escape_string($_POST['delivery_date']??'');
    $notes  = $database->real_escape_string($_POST['notes']??'');
    $total  = floatval($_POST['total_amount']??0);
    $uid    = intval($_SESSION['uid']??1);
    $ddate_sql = $ddate ? "'$ddate'" : "NULL";
    $onum   = 'CMD-'.date('Ymd').'-'.rand(100,999);
    $database->query("INSERT INTO purchase_orders (order_number,supplier_id,order_date,delivery_date,status,total_amount,created_by,notes) VALUES ('$onum',$sid,'$odate',$ddate_sql,'pending',$total,$uid,'$notes')");
    header("location: gmg_suppliers.php?tab=orders&msg=order_created");
    exit();
}

// ── Mettre à jour statut commande ──
if(isset($_POST['update_order'])){
    $oid = intval($_POST['order_id']);
    $st  = $database->real_escape_string($_POST['new_status']);
    $allowed = ['pending','confirmed','shipped','delivered','cancelled'];
    if(in_array($st, $allowed)) {
        $database->query("UPDATE purchase_orders SET status='$st' WHERE id=$oid");
    }
    header("location: gmg_suppliers.php?tab=orders&msg=updated");
    exit();
}

$tab = $_GET['tab'] ?? 'suppliers';

$suppliers     = $database->query("SELECT * FROM suppliers ORDER BY is_active DESC, name ASC");
$orders        = $database->query("SELECT po.*, s.name AS supplier_name FROM purchase_orders po INNER JOIN suppliers s ON s.id=po.supplier_id ORDER BY po.order_date DESC LIMIT 50");
$total_sup     = $database->query("SELECT COUNT(*) AS n FROM suppliers WHERE is_active=1")->fetch_assoc()['n'] ?? 0;
$total_orders  = $database->query("SELECT COUNT(*) AS n FROM purchase_orders")->fetch_assoc()['n'] ?? 0;
$orders_open   = $database->query("SELECT COUNT(*) AS n FROM purchase_orders WHERE status IN ('pending','confirmed','shipped')")->fetch_assoc()['n'] ?? 0;
$all_sup_order = $database->query("SELECT id, name, supplier_type FROM suppliers WHERE is_active=1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMG — Fournisseurs & Commandes</title>
    <link rel="stylesheet" href="gmg_style.css">
    <style>
        /* Styles spécifiques aux fournisseurs */
        .tabs-container {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 12px;
        }
        .tab-btn {
            padding: 8px 24px;
            background: transparent;
            border: none;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .tab-btn.active {
            background: #10b981;
            color: #fff;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal {
            background: #fff;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        .modal-wide {
            max-width: 750px;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: #fff;
            border-radius: 20px 20px 0 0;
        }
        .modal-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
        }
        .modal-close {
            font-size: 1.5rem;
            font-weight: 300;
            color: #94a3b8;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-close:hover {
            color: #ef4444;
        }
        .modal-body {
            padding: 24px;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #fafbfc;
            border-radius: 0 0 20px 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
        }
        .required {
            color: #ef4444;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.1);
        }
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .text-center {
            text-align: center;
        }
        .supplier-detail-row {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 12px;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .supplier-detail-label {
            font-weight: 600;
            color: #475569;
        }
        .sub-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        .sub-table th {
            background: #f8fafc;
            padding: 10px 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        .sub-table td {
            padding: 10px 12px;
            font-size: 0.8rem;
            border-bottom: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>
<div class="app">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-area">
           <div class="logo">MED<span>UNITY</span></div>
            <div class="logo-sub">Gestion des Moyens Généraux</div>
        </div>
        <nav>
            <a href="gmg_index.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2h-5v-8H7v8H5a2 2 0 0 1-2-2z"/>
                </svg>
                <span>Tableau de bord</span>
            </a>
            <a href="gmg_rooms.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="4" y="4" width="16" height="16" rx="2"/>
                    <path d="M9 8h6M9 12h6M9 16h4"/>
                </svg>
                <span>Chambres & Lits</span>
            </a>
            <a href="gmg_operating.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a5 5 0 0 0-5 5c0 2.5 2 4.5 5 7 3-2.5 5-4.5 5-7a5 5 0 0 0-5-5zm0 7a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/>
                    <path d="M8 21h8"/>
                </svg>
                <span>Blocs opératoires</span>
            </a>
            <a href="gmg_stock.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 7h-4.5M15 4H9M4 7h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"/>
                    <path d="M9 4v3M15 4v3"/>
                </svg>
                <span>Stock</span>
            </a>
            <a href="gmg_maintenance.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.5 6.5L3 14v4h4l7.5-7.5M16 8l2-2 2 2-2 2M8 21h12a2 2 0 0 0 2-2v-2"/>
                </svg>
                <span>Maintenance</span>
            </a>
            <a href="gmg_suppliers.php" class="nav-item active">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M5 20v-2a7 7 0 0 1 14 0v2"/>
                </svg>
                <span>Fournisseurs</span>
            </a>
        </nav>
        <a href="profile.php" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <span>Mon profil</span>
        </a>
        <div class="user-info">
            <div class="user-avatar">GM</div>
            <div class="user-details">
                <div class="user-name">Gestion Moyens</div>
                <div class="user-role">gmg@clinique.com</div>
                <a href="../logout.php" class="logout-link">Déconnexion</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">🏢 Fournisseurs & Commandes</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <!-- Messages de confirmation -->
            <?php if(isset($_GET['msg'])): ?>
                <div class="alert alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    <?= match($_GET['msg']) {
                        'added' => '✅ Fournisseur ajouté avec succès.',
                        'order_created' => '✅ Commande créée avec succès.',
                        'updated' => '✅ Statut mis à jour.',
                        default => '✅ Opération réussie.'
                    } ?>
                </div>
            <?php endif; ?>

            <!-- Onglets -->
            <div class="tabs-container">
                <a href="?tab=suppliers" class="tab-btn <?= $tab == 'suppliers' ? 'active' : '' ?>">
                    📋 Fournisseurs (<?= $total_sup ?>)
                </a>
                <a href="?tab=orders" class="tab-btn <?= $tab == 'orders' ? 'active' : '' ?>">
                    📦 Commandes (<?= $orders_open ?> en cours)
                </a>
                <?php if($tab == 'suppliers'): ?>
                    <a href="?tab=suppliers&action=add" class="btn btn-primary btn-sm" style="margin-left: auto;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Ajouter un fournisseur
                    </a>
                <?php else: ?>
                    <a href="?tab=orders&action=create_order" class="btn btn-primary btn-sm" style="margin-left: auto;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Nouvelle commande
                    </a>
                <?php endif; ?>
            </div>

            <!-- TAB FOURNISSEURS -->
            <?php if($tab == 'suppliers'): ?>
                <div class="card">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Type</th>
                                <th>Contact</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if(!$suppliers || $suppliers->num_rows == 0): ?>
                                <td><td colspan="7" class="empty-state">
                                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M5 20v-2a7 7 0 0 1 14 0v2"/>
                                    </svg>
                                    <p>Aucun fournisseur trouvé.</p>
                                    <a href="?tab=suppliers&action=add" class="btn btn-primary btn-sm" style="margin-top: 15px;">+ Ajouter un fournisseur</a>
                                </td></tr>
                            <?php else:
                                while($s = $suppliers->fetch_assoc()):
                                    $type_label = match($s['supplier_type']) {
                                        'medicines' => 'Médicaments',
                                        'equipment' => 'Équipement médical',
                                        'food' => 'Alimentation',
                                        'other' => 'Autre',
                                        default => ucfirst($s['supplier_type'])
                                    };
                                    ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= htmlspecialchars(substr($s['name'], 0, 30)) ?></td>
                                        <td><span class="badge badge-<?= $s['supplier_type'] ?>"><?= $type_label ?></span></td>
                                        <td style="color: #64748b;"><?= htmlspecialchars(substr($s['contact_person'] ?? '—', 0, 20)) ?></td>
                                        <td><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars(substr($s['email'] ?? '—', 0, 25)) ?></td>
                                        <td><span class="badge <?= $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $s['is_active'] ? 'Actif' : 'Inactif' ?></span></td>
                                        <td>
                                            <div class="flex" style="gap: 6px;">
                                                <a href="?action=view_sup&id=<?= $s['id'] ?>&tab=suppliers" class="btn btn-soft btn-sm">👁 Voir</a>
                                                <a href="?toggle_supplier=<?= $s['id'] ?>" class="btn btn-<?= $s['is_active'] ? 'danger' : 'primary' ?> btn-sm">
                                                    <?= $s['is_active'] ? '🔴 Désactiver' : '🟢 Activer' ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB COMMANDES -->
            <?php else: ?>
                <div class="card">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Commande #</th>
                                <th>Fournisseur</th>
                                <th>Date commande</th>
                                <th>Livraison prévue</th>
                                <th>Montant (DA)</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if(!$orders || $orders->num_rows == 0): ?>
                            <td><td colspan="7" class="empty-state">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                    <path d="M20 7h-4.5M15 4H9M4 7h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"/>
                                </svg>
                                <p>Aucune commande trouvée.</p>
                                <a href="?tab=orders&action=create_order" class="btn btn-primary btn-sm" style="margin-top: 15px;">+ Créer une commande</a>
                            </td></table>
                        <?php else:
                            while($o = $orders->fetch_assoc()):
                                $status_label = match($o['status']) {
                                    'pending' => 'En attente',
                                    'confirmed' => 'Confirmée',
                                    'shipped' => 'Expédiée',
                                    'delivered' => 'Livrée',
                                    'cancelled' => 'Annulée',
                                    default => ucfirst($o['status'])
                                };
                                ?>
                                <tr>
                                    <td style="font-weight: 500; font-size: 0.8rem;"><?= htmlspecialchars($o['order_number']) ?></td>
                                    <td><?= htmlspecialchars(substr($o['supplier_name'], 0, 25)) ?></td>
                                    <td class="text-center" style="color: #64748b;"><?= $o['order_date'] ?></td>
                                    <td class="text-center" style="color: #64748b;"><?= $o['delivery_date'] ?? '—' ?></td>
                                    <td class="text-center" style="font-weight: 600; color: #059669;"><?= number_format($o['total_amount'] ?? 0, 0, '.', ' ') ?> DA</td>
                                    <td><span class="badge badge-<?= $o['status'] ?>"><?= $status_label ?></span></td>
                                    <td>
                                        <a href="?action=update_order&id=<?= $o['id'] ?>&tab=orders" class="btn btn-soft btn-sm">✏ Modifier</a>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODALS -->
<?php if(isset($_GET['action'])):
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);
    ?>

    <!-- MODAL : AJOUTER UN FOURNISSEUR -->
    <?php if($action == 'add'): ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>➕ Ajouter un fournisseur</h2>
                <a href="gmg_suppliers.php?tab=suppliers" class="modal-close">&times;</a>
            </div>
            <form method="POST" action="gmg_suppliers.php">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nom de l'entreprise <span class="required">*</span></label>
                            <input type="text" name="name" class="form-input" placeholder="Nom du fournisseur" required>
                        </div>
                        <div class="form-group">
                            <label>Type de fournisseur</label>
                            <select name="supplier_type" class="form-select">
                                <option value="medicines">Médicaments / Pharma</option>
                                <option value="equipment">Équipement médical</option>
                                <option value="food">Alimentation</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Personne de contact</label>
                            <input type="text" name="contact_person" class="form-input" placeholder="Nom du représentant">
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="phone" class="form-input" placeholder="Numéro de téléphone">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-input" placeholder="Adresse email">
                        </div>
                        <div class="form-group full-width">
                            <label>Adresse</label>
                            <input type="text" name="address" class="form-input" placeholder="Adresse complète">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="gmg_suppliers.php?tab=suppliers" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="add_supplier" class="btn btn-primary">Ajouter le fournisseur</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <!-- MODAL : CRÉER UNE COMMANDE -->
    <?php if($action == 'create_order'): ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>📦 Créer une nouvelle commande</h2>
                <a href="gmg_suppliers.php?tab=orders" class="modal-close">&times;</a>
            </div>
            <form method="POST" action="gmg_suppliers.php">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Fournisseur <span class="required">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Sélectionner un fournisseur</option>
                                <?php
                                $all_sup_order->data_seek(0);
                                while($s = $all_sup_order->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= match($s['supplier_type']) {
                                            'medicines' => 'Médicaments',
                                            'equipment' => 'Équipement',
                                            'food' => 'Alimentation',
                                            default => ucfirst($s['supplier_type'])
                                        } ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date de commande <span class="required">*</span></label>
                            <input type="date" name="order_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Date de livraison prévue</label>
                            <input type="date" name="delivery_date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>Montant total (DA)</label>
                            <input type="number" name="total_amount" class="form-input" placeholder="Montant estimé" min="0" step="0.01">
                        </div>
                        <div class="form-group full-width">
                            <label>Notes (articles commandés)</label>
                            <textarea name="notes" class="form-textarea" placeholder="Liste des articles commandés, instructions particulières..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="gmg_suppliers.php?tab=orders" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="create_order" class="btn btn-primary">Créer la commande</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <!-- MODAL : METTRE À JOUR LE STATUT D'UNE COMMANDE -->
    <?php if($action == 'update_order' && $id > 0):
    $o = $database->query("SELECT po.*, s.name AS sname FROM purchase_orders po INNER JOIN suppliers s ON s.id=po.supplier_id WHERE po.id=$id")->fetch_assoc();
    if($o):
        ?>
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>✏ Modifier la commande</h2>
                    <a href="gmg_suppliers.php?tab=orders" class="modal-close">&times;</a>
                </div>
                <form method="POST" action="gmg_suppliers.php">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" value="<?= $id ?>">
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label>Commande</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($o['order_number']) ?> — <?= htmlspecialchars($o['sname']) ?>" disabled style="background: #f1f5f9;">
                        </div>
                        <div class="form-group">
                            <label>Nouveau statut</label>
                            <select name="new_status" class="form-select">
                                <option value="pending" <?= $o['status'] == 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="confirmed" <?= $o['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmée</option>
                                <option value="shipped" <?= $o['status'] == 'shipped' ? 'selected' : '' ?>>Expédiée</option>
                                <option value="delivered" <?= $o['status'] == 'delivered' ? 'selected' : '' ?>>Livrée</option>
                                <option value="cancelled" <?= $o['status'] == 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="gmg_suppliers.php?tab=orders" class="btn btn-secondary">Annuler</a>
                        <button type="submit" name="update_order" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; endif; ?>

    <!-- MODAL : VOIR LES DÉTAILS D'UN FOURNISSEUR -->
    <?php if($action == 'view_sup' && $id > 0):
    $s = $database->query("SELECT * FROM suppliers WHERE id=$id")->fetch_assoc();
    $s_orders = $database->query("SELECT * FROM purchase_orders WHERE supplier_id=$id ORDER BY order_date DESC LIMIT 5");
    if($s):
        $type_label = match($s['supplier_type']) {
            'medicines' => 'Médicaments',
            'equipment' => 'Équipement médical',
            'food' => 'Alimentation',
            'other' => 'Autre',
            default => ucfirst($s['supplier_type'])
        };
        ?>
        <div class="modal-overlay">
            <div class="modal modal-wide">
                <div class="modal-header">
                    <h2>🔍 Détails du fournisseur</h2>
                    <a href="gmg_suppliers.php?tab=suppliers" class="modal-close">&times;</a>
                </div>
                <div class="modal-body">
                    <div class="supplier-detail-row">
                        <div class="supplier-detail-label">Nom :</div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($s['name']) ?></div>
                    </div>
                    <div class="supplier-detail-row">
                        <div class="supplier-detail-label">Type / Statut :</div>
                        <div>
                            <span class="badge badge-<?= $s['supplier_type'] ?>"><?= $type_label ?></span>
                            <span class="badge <?= $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $s['is_active'] ? 'Actif' : 'Inactif' ?></span>
                        </div>
                    </div>
                    <div class="supplier-detail-row">
                        <div class="supplier-detail-label">Contact :</div>
                        <div><?= htmlspecialchars($s['contact_person'] ?? '—') ?></div>
                    </div>
                    <div class="supplier-detail-row">
                        <div class="supplier-detail-label">Téléphone :</div>
                        <div><?= htmlspecialchars($s['phone'] ?? '—') ?></div>
                    </div>
                    <div class="supplier-detail-row">
                        <div class="supplier-detail-label">Email :</div>
                        <div><?= htmlspecialchars($s['email'] ?? '—') ?></div>
                    </div>
                    <div class="supplier-detail-row">
                        <div class="supplier-detail-label">Adresse :</div>
                        <div><?= htmlspecialchars($s['address'] ?? '—') ?></div>
                    </div>

                    <h3 style="font-size: 0.9rem; margin: 20px 0 12px 0;">📋 Dernières commandes (5)</h3>
                    <table class="sub-table">
                        <thead>
                        <tr><th>Commande #</th><th>Date</th><th>Montant</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                        <?php if(!$s_orders || $s_orders->num_rows == 0): ?>
                            <tr><td colspan="4" class="text-center" style="padding: 20px; color: #64748b;">Aucune commande pour ce fournisseur</td></tr>
                        <?php else:
                            while($o = $s_orders->fetch_assoc()):
                                $status_label = match($o['status']) {
                                    'pending' => 'En attente',
                                    'confirmed' => 'Confirmée',
                                    'shipped' => 'Expédiée',
                                    'delivered' => 'Livrée',
                                    'cancelled' => 'Annulée',
                                    default => ucfirst($o['status'])
                                };
                                ?>
                                <tr>
                                    <td style="font-size: 0.75rem;"><?= htmlspecialchars($o['order_number']) ?></td>
                                    <td style="font-size: 0.8rem;"><?= $o['order_date'] ?></td>
                                    <td style="font-weight: 600;"><?= number_format($o['total_amount'] ?? 0, 0, '.', ' ') ?> DA</td>
                                    <td><span class="badge badge-<?= $o['status'] ?>"><?= $status_label ?></span></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <a href="gmg_suppliers.php?tab=suppliers" class="btn btn-secondary">Fermer</a>
                    <a href="?toggle_supplier=<?= $s['id'] ?>" class="btn btn-<?= $s['is_active'] ? 'danger' : 'primary' ?>">
                        <?= $s['is_active'] ? 'Désactiver' : 'Activer' ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; endif; ?>
<?php endif; ?>

</body>
</html>