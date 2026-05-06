<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// gmg_maintenance.php - Gestion de la Maintenance
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

// ── Créer une demande ──
if(isset($_POST['create_request'])){
    $equip  = $database->real_escape_string($_POST['equipment_name']);
    $loc    = $database->real_escape_string($_POST['equipment_location']??'');
    $desc   = $database->real_escape_string($_POST['issue_description']);
    $type   = $database->real_escape_string($_POST['maintenance_type']);
    $prio   = $database->real_escape_string($_POST['priority']);
    $sched  = $database->real_escape_string($_POST['scheduled_date']??'');
    $sched_sql = $sched ? "'$sched'" : "NULL";
    $uid    = intval($_SESSION['uid']??1);
    $database->query("INSERT INTO maintenance_requests (equipment_name,equipment_location,issue_description,maintenance_type,priority,status,reported_by,scheduled_date) VALUES ('$equip','$loc','$desc','$type','$prio','pending',$uid,$sched_sql)");
    header("location: gmg_maintenance.php?msg=created");
    exit();
}

// ── Mettre à jour statut ──
if(isset($_POST['update_status'])){
    $mid    = intval($_POST['maint_id']);
    $status = $database->real_escape_string($_POST['new_status']);
    $parts  = $database->real_escape_string($_POST['spare_parts']??'');
    $cost   = floatval($_POST['cost']??0);
    $allowed = ['pending','in_progress','completed','cancelled'];
    if(in_array($status, $allowed)){
        $done_sql   = $status == 'completed' ? ", completed_at=NOW()" : "";
        $parts_sql  = $parts ? ", spare_parts_used='$parts'" : "";
        $cost_sql   = $cost ? ", cost=$cost" : "";
        $database->query("UPDATE maintenance_requests SET status='$status' $done_sql $parts_sql $cost_sql WHERE id=$mid");
    }
    header("location: gmg_maintenance.php?msg=updated");
    exit();
}

// Filtres
$filter_status = $database->real_escape_string($_POST['filter_status']??'');
$filter_type   = $database->real_escape_string($_POST['filter_type']??'');
$filter_prio   = $database->real_escape_string($_POST['filter_prio']??'');
$where = "WHERE 1=1";
if($filter_status) $where .= " AND status='$filter_status'";
if($filter_type)   $where .= " AND maintenance_type='$filter_type'";
if($filter_prio)   $where .= " AND priority='$filter_prio'";

$requests  = $database->query("SELECT mr.*, u.full_name AS agent_name FROM maintenance_requests mr LEFT JOIN users u ON u.id=mr.assigned_to $where ORDER BY FIELD(priority,'critical','high','normal','low'), created_at DESC");
$total     = $database->query("SELECT COUNT(*) AS n FROM maintenance_requests")->fetch_assoc()['n'] ?? 0;
$nb_open   = $database->query("SELECT COUNT(*) AS n FROM maintenance_requests WHERE status IN ('pending','in_progress')")->fetch_assoc()['n'] ?? 0;
$nb_crit   = $database->query("SELECT COUNT(*) AS n FROM maintenance_requests WHERE priority='critical' AND status!='completed'")->fetch_assoc()['n'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMG — Gestion de la Maintenance</title>
    <link rel="stylesheet" href="gmg_style.css">
    <style>
        /* Styles spécifiques à la maintenance */
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
            max-width: 650px;
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
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .empty-state svg {
            width: 60px;
            height: 60px;
            stroke: #cbd5e1;
            margin-bottom: 16px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            white-space: nowrap;
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
            <a href="gmg_maintenance.php" class="nav-item active">
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
            <div class="page-title">🔧 Gestion de la Maintenance</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <!-- Messages d'alerte -->
            <?php if(isset($_GET['msg'])): ?>
                <div class="alert alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    <?= $_GET['msg'] == 'created' ? '✅ Demande créée avec succès.' : '✅ Statut mis à jour.' ?>
                </div>
            <?php endif; ?>

            <?php if($nb_crit > 0): ?>
                <div class="alert alert-warning">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    🚨 <?= $nb_crit ?> demande(s) de maintenance critique(s) nécessitent une attention immédiate !
                </div>
            <?php endif; ?>

            <!-- En-tête avec bouton nouvelle demande -->
            <div class="flex-between" style="margin-bottom: 20px;">
                <div>
                    <h2 style="font-size: 1.1rem; font-weight: 600;">📋 Demandes de maintenance</h2>
                    <p style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">Total : <?= $total ?> | En cours : <?= $nb_open ?></p>
                </div>
                <a href="?action=add" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Nouvelle demande
                </a>
            </div>

            <!-- Barre de filtres -->
            <form method="POST" class="filter-bar">
                <div class="filter-group">
                    <label>Statut</label>
                    <select name="filter_status" class="filter-select">
                        <option value="">Tous</option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="in_progress" <?= $filter_status == 'in_progress' ? 'selected' : '' ?>>En cours</option>
                        <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Terminé</option>
                        <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select name="filter_type" class="filter-select">
                        <option value="">Tous</option>
                        <option value="corrective" <?= $filter_type == 'corrective' ? 'selected' : '' ?>>Corrective</option>
                        <option value="preventive" <?= $filter_type == 'preventive' ? 'selected' : '' ?>>Préventive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Priorité</label>
                    <select name="filter_prio" class="filter-select">
                        <option value="">Toutes</option>
                        <option value="critical" <?= $filter_prio == 'critical' ? 'selected' : '' ?>>Critique</option>
                        <option value="high" <?= $filter_prio == 'high' ? 'selected' : '' ?>>Haute</option>
                        <option value="normal" <?= $filter_prio == 'normal' ? 'selected' : '' ?>>Normale</option>
                        <option value="low" <?= $filter_prio == 'low' ? 'selected' : '' ?>>Basse</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">🔍 Filtrer</button>
            </form>

            <!-- Tableau des demandes -->
            <div class="card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Équipement</th>
                            <th>Emplacement</th>
                            <th>Type</th>
                            <th>Priorité</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Coût</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if(!$requests || $requests->num_rows == 0): ?>
                            <tr><td colspan="8" class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p>Aucune demande de maintenance trouvée.</p>
                                    <a href="?action=add" class="btn btn-primary btn-sm" style="margin-top: 15px;">+ Créer une demande</a>
                                </td></tr>
                        <?php else:
                            while($m = $requests->fetch_assoc()):
                                $status_text = match($m['status']) {
                                    'pending' => 'En attente',
                                    'in_progress' => 'En cours',
                                    'completed' => 'Terminé',
                                    'cancelled' => 'Annulé',
                                    default => $m['status']
                                };
                                $type_text = $m['maintenance_type'] == 'corrective' ? 'Corrective' : 'Préventive';
                                ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars(substr($m['equipment_name'], 0, 25)) ?></td>
                                    <td style="color: #64748b;"><?= htmlspecialchars(substr($m['equipment_location'] ?? '—', 0, 20)) ?></td>
                                    <td><span class="badge badge-<?= $m['maintenance_type'] ?>"><?= $type_text ?></span></td>
                                    <td><span class="badge badge-<?= $m['priority'] ?>"><?= ucfirst($m['priority']) ?></span></td>
                                    <td><span class="badge badge-<?= $m['status'] ?>"><?= $status_text ?></span></td>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= substr($m['created_at'], 0, 10) ?></td>
                                    <td style="font-weight: 500;"><?= $m['cost'] ? number_format($m['cost'], 0, '.', ' ') . ' DA' : '—' ?></td>
                                    <td class="flex" style="gap: 6px;">
                                        <a href="?action=view&id=<?= $m['id'] ?>" class="btn btn-soft btn-sm">👁 Voir</a>
                                        <a href="?action=update&id=<?= $m['id'] ?>" class="btn btn-secondary btn-sm">✏ Modifier</a>
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

    <!-- MODAL : CRÉER UNE DEMANDE -->
    <?php if($action == 'add'): ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>➕ Nouvelle demande de maintenance</h2>
                <a href="gmg_maintenance.php" class="modal-close">&times;</a>
            </div>
            <form method="POST" action="gmg_maintenance.php">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Équipement <span class="required">*</span></label>
                            <input type="text" name="equipment_name" class="form-input" placeholder="Ex: Scanner IRM, Lit électrique" required>
                        </div>
                        <div class="form-group">
                            <label>Emplacement</label>
                            <input type="text" name="equipment_location" class="form-input" placeholder="Chambre / Étage / Service">
                        </div>
                        <div class="form-group full-width">
                            <label>Description du problème <span class="required">*</span></label>
                            <textarea name="issue_description" class="form-textarea" placeholder="Décrivez le problème ou l'intervention nécessaire..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Type de maintenance</label>
                            <select name="maintenance_type" class="form-select">
                                <option value="corrective">Corrective (panne / réparation)</option>
                                <option value="preventive">Préventive (entretien programmé)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priorité</label>
                            <select name="priority" class="form-select">
                                <option value="normal">Normale</option>
                                <option value="high">Haute</option>
                                <option value="critical">Critique</option>
                                <option value="low">Basse</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date programmée (préventive)</label>
                            <input type="datetime-local" name="scheduled_date" class="form-input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="gmg_maintenance.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="create_request" class="btn btn-primary">Créer la demande</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <!-- MODAL : VOIR LE DÉTAIL -->
    <?php if($action == 'view' && $id > 0):
    $m = $database->query("SELECT mr.*, r.full_name AS reporter, a.full_name AS agent FROM maintenance_requests mr LEFT JOIN users r ON r.id=mr.reported_by LEFT JOIN users a ON a.id=mr.assigned_to WHERE mr.id=$id")->fetch_assoc();
    if($m):
        $status_text = match($m['status']) {
            'pending' => 'En attente',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default => $m['status']
        };
        $type_text = $m['maintenance_type'] == 'corrective' ? 'Corrective' : 'Préventive';
        ?>
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>🔍 Détail de la demande #<?= $id ?></h2>
                    <a href="gmg_maintenance.php" class="modal-close">&times;</a>
                </div>
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div><strong>Équipement :</strong><br><?= htmlspecialchars($m['equipment_name']) ?></div>
                        <div><strong>Emplacement :</strong><br><?= htmlspecialchars($m['equipment_location'] ?? '—') ?></div>
                        <div><strong>Type :</strong><br><span class="badge badge-<?= $m['maintenance_type'] ?>"><?= $type_text ?></span></div>
                        <div><strong>Priorité :</strong><br><span class="badge badge-<?= $m['priority'] ?>"><?= ucfirst($m['priority']) ?></span></div>
                        <div><strong>Statut :</strong><br><span class="badge badge-<?= $m['status'] ?>"><?= $status_text ?></span></div>
                        <div><strong>Date de création :</strong><br><?= $m['created_at'] ?></div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <strong>Description :</strong>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-top: 6px;"><?= nl2br(htmlspecialchars($m['issue_description'])) ?></div>
                    </div>
                    <?php if($m['spare_parts_used']): ?>
                        <div style="margin-bottom: 12px;"><strong>Pièces utilisées :</strong><br><?= htmlspecialchars($m['spare_parts_used']) ?></div>
                    <?php endif; ?>
                    <?php if($m['cost']): ?>
                        <div style="margin-bottom: 12px;"><strong>Coût :</strong><br><?= number_format($m['cost'], 0, '.', ' ') ?> DA</div>
                    <?php endif; ?>
                    <div><strong>Signalé par / Assigné à :</strong><br><?= htmlspecialchars($m['reporter'] ?? '—') ?> / <?= htmlspecialchars($m['agent'] ?? 'Non assigné') ?></div>
                </div>
                <div class="modal-footer">
                    <a href="gmg_maintenance.php" class="btn btn-secondary">Fermer</a>
                    <a href="?action=update&id=<?= $id ?>" class="btn btn-primary">Modifier le statut</a>
                </div>
            </div>
        </div>
    <?php endif; endif; ?>

    <!-- MODAL : METTRE À JOUR LE STATUT -->
    <?php if($action == 'update' && $id > 0):
    $m = $database->query("SELECT * FROM maintenance_requests WHERE id=$id")->fetch_assoc();
    if($m):
        ?>
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>✏ Mettre à jour la demande #<?= $id ?></h2>
                    <a href="gmg_maintenance.php" class="modal-close">&times;</a>
                </div>
                <form method="POST" action="gmg_maintenance.php">
                    <div class="modal-body">
                        <input type="hidden" name="maint_id" value="<?= $id ?>">
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label>Équipement</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($m['equipment_name']) ?>" disabled style="background: #f1f5f9;">
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label>Nouveau statut</label>
                            <select name="new_status" class="form-select">
                                <option value="pending" <?= $m['status'] == 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="in_progress" <?= $m['status'] == 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                <option value="completed" <?= $m['status'] == 'completed' ? 'selected' : '' ?>>Terminé</option>
                                <option value="cancelled" <?= $m['status'] == 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label>Pièces utilisées (optionnel)</label>
                            <input type="text" name="spare_parts" class="form-input" placeholder="Liste des pièces utilisées" value="<?= htmlspecialchars($m['spare_parts_used'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Coût (DA)</label>
                            <input type="number" name="cost" class="form-input" placeholder="Coût de l'intervention" min="0" step="0.01" value="<?= $m['cost'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="gmg_maintenance.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" name="update_status" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; endif; ?>
<?php endif; ?>

</body>
</html>