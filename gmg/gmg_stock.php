<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body><?php
// gmg_stock.php - Gestion du Stock
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

// ── Ajouter un article ──
if(isset($_POST['add_item'])){
    $name       = $database->real_escape_string($_POST['name']);
    $generic    = $database->real_escape_string($_POST['generic_name']??'');
    $cat        = $database->real_escape_string($_POST['category']??'');
    $dosage_form= $database->real_escape_string($_POST['dosage_form']??'');
    $strength   = $database->real_escape_string($_POST['strength']??'');
    $unit       = $database->real_escape_string($_POST['unit']??'boîte');
    $qty        = intval($_POST['quantity']);
    $threshold  = intval($_POST['threshold_alert']);
    $expiry     = $database->real_escape_string($_POST['expiry_date']??'');
    $price_buy  = floatval($_POST['purchase_price']??0);
    $price_sell = floatval($_POST['selling_price']??0);
    $exp_sql    = $expiry ? "'$expiry'" : "NULL";
    $database->query("INSERT INTO medicines (name,generic_name,category,dosage_form,strength,unit,quantity,threshold_alert,expiry_date,purchase_price,selling_price) VALUES ('$name','$generic','$cat','$dosage_form','$strength','$unit',$qty,$threshold,$exp_sql,$price_buy,$price_sell)");
    header("location: gmg_stock.php?msg=added");
    exit();
}

// ── Réapprovisionner ──
if(isset($_POST['restock'])){
    $mid     = intval($_POST['med_id']);
    $add_qty = intval($_POST['add_qty']);
    $uid     = intval($_SESSION['uid']??1);
    $database->query("UPDATE medicines SET quantity=quantity+$add_qty WHERE id=$mid");
    $database->query("INSERT INTO stock_movements (medicine_id, type, quantity, reason, performed_by) VALUES ($mid,'in',$add_qty,'Réapprovisionnement manuel GMG',$uid)");
    header("location: gmg_stock.php?msg=restocked");
    exit();
}

// ── Supprimer un article ──
if(isset($_GET['delete_item'])){
    $mid = intval($_GET['delete_item']);
    $database->query("DELETE FROM medicines WHERE id=$mid");
    header("location: gmg_stock.php");
    exit();
}
// Filtres
$filter_cat   = $database->real_escape_string($_POST['filter_cat']??'');
$filter_alert = $_POST['filter_alert']??'';
$kw           = $database->real_escape_string($_POST['search']??'');
$where = "WHERE 1=1";
if($filter_cat)            $where .= " AND m.category='$filter_cat'";
if($filter_alert=='low')   $where .= " AND m.quantity <= m.threshold_alert";
if($kw)                    $where .= " AND (m.name LIKE '%$kw%' OR m.generic_name LIKE '%$kw%')";

// Requête corrigée
$items      = $database->query("SELECT m.*, s.name AS supplier_name FROM medicines m LEFT JOIN suppliers s ON s.id=m.supplier_id $where ORDER BY m.quantity ASC, m.name ASC");
$total      = $database->query("SELECT COUNT(*) AS n FROM medicines")->fetch_assoc()['n'] ?? 0;
$total_low  = $database->query("SELECT COUNT(*) AS n FROM medicines WHERE quantity<=threshold_alert")->fetch_assoc()['n'] ?? 0;
$categories = $database->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL AND category!='' ORDER BY category");
$all_names  = $database->query("SELECT id, name FROM medicines ORDER BY name");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMG — Gestion du Stock</title>
    <link rel="stylesheet" href="gmg_style.css">
    <style>
        /* Styles spécifiques au stock */
        .search-bar {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .search-input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.8rem;
            width: 250px;
            outline: none;
        }
        .search-input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.1);
        }
        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 24px;
            padding: 16px 20px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.8rem;
            background: #fff;
            color: #0f172a;
            cursor: pointer;
            outline: none;
        }
        .filter-select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.1);
        }
        .progress-bar-small {
            background: #e2e8f0;
            border-radius: 4px;
            height: 6px;
            width: 80px;
            display: inline-block;
            vertical-align: middle;
        }
        .progress-fill-small {
            border-radius: 4px;
            height: 6px;
        }
        .stock-level {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .expiry-warning {
            color: #991b1b;
            font-weight: 600;
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
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
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
        .form-input, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus, .form-select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.1);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .text-center {
            text-align: center;
        }
        .current-stock {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
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
            <a href="gmg_stock.php" class="nav-item active">
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
            <a href="gmg_suppliers.php" class="nav-item">
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
            <div class="page-title">📦 Gestion du Stock</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <!-- Messages d'alerte -->
            <?php if(isset($_GET['msg'])): ?>
                <div class="alert alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    <?= $_GET['msg'] == 'added' ? '✅ Article ajouté avec succès.' : '✅ Stock mis à jour avec succès.' ?>
                </div>
            <?php endif; ?>

            <?php if($total_low > 0): ?>
                <div class="alert alert-warning">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    ⚠️ <?= $total_low ?> article(s) en dessous ou égal au seuil d'alerte !
                </div>
            <?php endif; ?>

            <!-- En-tête avec barre de recherche et bouton ajout -->
            <div class="flex-between">
                <div>
                    <h2 style="font-size: 1rem; font-weight: 600;">📋 Tous les articles (<?= $total ?>)</h2>
                </div>
                <div class="search-bar">
                    <form action="" method="post" class="search-bar">
                        <input type="search" name="search" class="search-input" placeholder="Rechercher par nom ou générique..." list="med_list">
                        <?php
                        echo '<datalist id="med_list">';
                        $all_names->data_seek(0);
                        while($m = $all_names->fetch_assoc()) {
                            echo '<option value="'.htmlspecialchars($m['name']).'">';
                        }
                        echo '</datalist>';
                        ?>
                        <button type="submit" class="btn btn-primary">🔍 Rechercher</button>
                    </form>
                    <a href="?action=add" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Ajouter un article
                    </a>
                </div>
            </div>

            <!-- Barre de filtres -->
            <form method="POST" class="filter-bar">
                <div class="filter-group">
                    <label>Catégorie</label>
                    <select name="filter_cat" class="filter-select">
                        <option value="">Toutes les catégories</option>
                        <?php
                        $categories->data_seek(0);
                        while($c = $categories->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($c['category']) ?>" <?= $filter_cat == $c['category'] ? 'selected' : '' ?>><?= htmlspecialchars($c['category']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Alerte</label>
                    <select name="filter_alert" class="filter-select">
                        <option value="">Tous les articles</option>
                        <option value="low" <?= $filter_alert == 'low' ? 'selected' : '' ?>>Stock bas uniquement</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">🔍 Filtrer</button>
            </form>

            <!-- Tableau des articles -->
            <div class="card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Forme / Dosage</th>
                            <th>Catégorie</th>
                            <th>Quantité</th>
                            <th>Seuil</th>
                            <th>Niveau</th>
                            <th>Expiration</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if(!$items || $items->num_rows == 0): ?>
                            <tr><td colspan="8" class="empty-state">
                                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p>Aucun article trouvé.</p>
                                    <a href="?action=add" class="btn btn-primary btn-sm" style="margin-top: 15px;">+ Ajouter un article</a>
                                </td></tr>
                        <?php else:
                            while($s = $items->fetch_assoc()):
                                $pct = $s['threshold_alert'] > 0 ? min(100, round($s['quantity'] / $s['threshold_alert'] * 100)) : 100;
                                $level_class = $s['quantity'] <= $s['threshold_alert'] ? 'badge-stock-low' : ($s['quantity'] <= $s['threshold_alert'] * 2 ? 'badge-stock-medium' : 'badge-stock-ok');
                                $level_text = $s['quantity'] <= $s['threshold_alert'] ? 'Critique' : ($s['quantity'] <= $s['threshold_alert'] * 2 ? 'Moyen' : 'OK');
                                $bar_color = $s['quantity'] <= $s['threshold_alert'] ? '#ef4444' : ($s['quantity'] <= $s['threshold_alert'] * 2 ? '#f59e0b' : '#22c55e');
                                $exp_warn = $s['expiry_date'] && $s['expiry_date'] <= date('Y-m-d', strtotime('+30 days'));
                                ?>
                                <tr>
                                    <td style="font-weight: 500;">
                                        <?= htmlspecialchars(substr($s['name'], 0, 25)) ?>
                                        <?php if($s['generic_name']): ?>
                                            <br><span style="font-size: 0.7rem; color: #64748b;"><?= htmlspecialchars(substr($s['generic_name'], 0, 20)) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.75rem; color: #64748b;">
                                        <?= htmlspecialchars($s['dosage_form'] ?? '') ?>
                                        <?= $s['strength'] ? ' — ' . htmlspecialchars($s['strength']) : '' ?>
                                    </td>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($s['category'] ?? '—') ?></td>
                                    <td class="text-center" style="font-weight: 600; color: #059669;">
                                        <?= $s['quantity'] ?> <span style="font-size: 0.7rem;"><?= $s['unit'] ?></span>
                                    </td>
                                    <td class="text-center" style="color: #64748b;"><?= $s['threshold_alert'] ?></td>
                                    <td>
                                        <div class="stock-level">
                                            <div class="progress-bar-small"><div class="progress-fill-small" style="width: <?= min(100, $pct) ?>%; background: <?= $bar_color ?>;"></div></div>
                                            <span class="badge <?= $level_class ?>"><?= $level_text ?></span>
                                        </div>
                                    </td>
                                    <td class="<?= $exp_warn ? 'expiry-warning' : '' ?>" style="font-size: 0.7rem;">
                                        <?= $s['expiry_date'] ?? '—' ?> <?= $exp_warn ? '⚠️' : '' ?>
                                    </td>
                                    <td>
                                        <div class="flex" style="gap: 6px; justify-content: center;">
                                            <a href="?action=restock&id=<?= $s['id'] ?>" class="btn btn-soft btn-sm">📦 Réappro.</a>
                                            <a href="?delete_item=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cet article ?')">🗑 Supprimer</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<?php if(isset($_GET['action'])):
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);
    ?>

    <!-- MODAL : AJOUTER UN ARTICLE -->
    <?php if($action == 'add'): ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>➕ Ajouter un nouvel article</h2>
                <a href="gmg_stock.php" class="modal-close">&times;</a>
            </div>
            <form method="POST" action="gmg_stock.php">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nom <span class="required">*</span></label>
                            <input type="text" name="name" class="form-input" placeholder="Nom du médicament ou article" required>
                        </div>
                        <div class="form-group">
                            <label>Nom générique</label>
                            <input type="text" name="generic_name" class="form-input" placeholder="Nom générique / alternatif">
                        </div>
                        <div class="form-group">
                            <label>Catégorie</label>
                            <input type="text" name="category" class="form-input" placeholder="Ex: Antibiotiques, Consommables">
                        </div>
                        <div class="form-group">
                            <label>Forme galénique</label>
                            <select name="dosage_form" class="form-select">
                                <option value="">—</option>
                                <option value="comprimé">Comprimé</option>
                                <option value="gélule">Gélule</option>
                                <option value="sirop">Sirop</option>
                                <option value="injection">Injection</option>
                                <option value="pommade">Pommade</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Dosage (ex: 500mg)</label>
                            <input type="text" name="strength" class="form-input" placeholder="Ex: 500mg, 10ml">
                        </div>
                        <div class="form-group">
                            <label>Unité</label>
                            <select name="unit" class="form-select">
                                <option value="boîte">Boîte</option>
                                <option value="comprimé">Comprimé</option>
                                <option value="gélule">Gélule</option>
                                <option value="flacon">Flacon</option>
                                <option value="ampoule">Ampoule</option>
                                <option value="pièce">Pièce</option>
                                <option value="ml">ml</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantité initiale <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-input" placeholder="Quantité en stock" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Seuil d'alerte <span class="required">*</span></label>
                            <input type="number" name="threshold_alert" class="form-input" placeholder="Alerte en dessous de" min="0" value="10" required>
                        </div>
                        <div class="form-group">
                            <label>Date d'expiration</label>
                            <input type="date" name="expiry_date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>Prix d'achat (DA)</label>
                            <input type="number" name="purchase_price" class="form-input" placeholder="Prix d'achat" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Prix de vente (DA)</label>
                            <input type="number" name="selling_price" class="form-input" placeholder="Prix de vente" min="0" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="gmg_stock.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="add_item" class="btn btn-primary">Ajouter l'article</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <!-- MODAL : RÉAPPROVISIONNER -->
    <?php if($action == 'restock' && $id > 0):
    $item = $database->query("SELECT * FROM medicines WHERE id=$id")->fetch_assoc();
    if($item):
        ?>
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>📦 Réapprovisionner</h2>
                    <a href="gmg_stock.php" class="modal-close">&times;</a>
                </div>
                <form method="POST" action="gmg_stock.php">
                    <div class="modal-body">
                        <input type="hidden" name="med_id" value="<?= $id ?>">
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label>Article</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($item['name']) ?><?= $item['strength'] ? ' — ' . htmlspecialchars($item['strength']) : '' ?>" disabled style="background: #f1f5f9;">
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label>Stock actuel</label>
                            <div class="current-stock"><?= $item['quantity'] ?> <?= $item['unit'] ?></div>
                        </div>
                        <div class="form-group">
                            <label>Quantité à ajouter <span class="required">*</span></label>
                            <input type="number" name="add_qty" class="form-input" placeholder="Quantité reçue du fournisseur" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="gmg_stock.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" name="restock" class="btn btn-primary">Confirmer le réapprovisionnement</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; endif; ?>
<?php endif; ?>

</body>
</html>