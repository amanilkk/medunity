<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// gmg_rooms.php - Gestion des Chambres et Lits
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

// ── Ajouter un lit ──
if(isset($_POST['add_bed'])){
    $rnum    = $database->real_escape_string($_POST['room_number']);
    $bednum  = $database->real_escape_string($_POST['bed_number']);
    $bedtype = $database->real_escape_string($_POST['bed_type']);
    $database->query("INSERT INTO bed_management (room_number, bed_number, bed_type, status) VALUES ('$rnum','$bednum','$bedtype','available')");
    header("location: gmg_rooms.php?msg=bed_added");
    exit();
}

// ── Changer statut d'un lit ──
if(isset($_GET['bed_status'])){
    $bid = intval($_GET['id']);
    $st  = $database->real_escape_string($_GET['bed_status']);
    $allowed = ['available','occupied','cleaning','maintenance','reserved'];
    if(in_array($st, $allowed)) {
        $database->query("UPDATE bed_management SET status='$st' WHERE id=$bid");
    }
    header("location: gmg_rooms.php");
    exit();
}

// ── Supprimer un lit ──
if(isset($_GET['delete_bed'])){
    $bid = intval($_GET['delete_bed']);
    $database->query("DELETE FROM bed_management WHERE id=$bid");
    header("location: gmg_rooms.php");
    exit();
}

// Filtres
$filter_type   = $database->real_escape_string($_POST['filter_type']??'');
$filter_status = $database->real_escape_string($_POST['filter_status']??'');
$filter_room   = $database->real_escape_string($_POST['filter_room']??'');
$where = "WHERE 1=1";
if($filter_type)   $where .= " AND bed_type='$filter_type'";
if($filter_status) $where .= " AND status='$filter_status'";
if($filter_room)   $where .= " AND room_number='$filter_room'";

// Statistiques par salle
$room_stats = $database->query("SELECT room_number, COUNT(*) AS total_beds, SUM(status='available') AS free, SUM(status='occupied') AS occupied FROM bed_management GROUP BY room_number ORDER BY room_number");
$beds       = $database->query("SELECT * FROM bed_management $where ORDER BY room_number, bed_number");
$total_beds = $database->query("SELECT COUNT(*) AS n FROM bed_management")->fetch_assoc()['n'] ?? 0;
$rooms_list = $database->query("SELECT DISTINCT room_number FROM bed_management ORDER BY room_number");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMG — Chambres & Lits</title>
    <link rel="stylesheet" href="gmg_style.css">
    <style>
        /* Styles spécifiques aux chambres et lits */
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
        .progress-bar-custom {
            background: #e2e8f0;
            border-radius: 4px;
            height: 8px;
            width: 100%;
            overflow: hidden;
        }
        .progress-fill-custom {
            border-radius: 4px;
            height: 8px;
        }
        .room-number {
            font-weight: 700;
            font-size: 1rem;
            color: #10b981;
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
            <a href="gmg_rooms.php" class="nav-item active">
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
            <div class="page-title">🛏️ Gestion des Chambres & Lits</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <!-- Message de confirmation -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'bed_added'): ?>
                <div class="alert alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    ✅ Lit ajouté avec succès.
                </div>
            <?php endif; ?>

            <!-- Résumé des chambres -->
            <div class="flex-between">
                <h2 style="font-size: 1rem; font-weight: 600;">📊 Résumé des chambres</h2>
            </div>

            <div class="card" style="margin-bottom: 28px;">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th class="text-center">Chambre #</th>
                            <th class="text-center">Total lits</th>
                            <th class="text-center">Libres</th>
                            <th class="text-center">Occupés</th>
                            <th class="text-center">Taux d'occupation</th>
                            <th class="text-center">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if(!$room_stats || $room_stats->num_rows == 0): ?>
                            <tr><td colspan="6" class="text-center" style="padding: 40px; color: #64748b;">
                                    🏥 Aucune chambre définie pour le moment.
                                </td></tr>
                        <?php else:
                            while($r = $room_stats->fetch_assoc()):
                                $occ_pct = $r['total_beds'] > 0 ? round($r['occupied'] / $r['total_beds'] * 100) : 0;
                                $bar_color = $occ_pct > 80 ? '#ef4444' : '#22c55e';
                                ?>
                                <tr>
                                    <td class="text-center room-number"><?= htmlspecialchars($r['room_number']) ?></td>
                                    <td class="text-center" style="font-weight: 500;"><?= $r['total_beds'] ?></td>
                                    <td class="text-center"><span class="badge badge-available"><?= $r['free'] ?></span></td>
                                    <td class="text-center"><span class="badge <?= $r['occupied'] > 0 ? 'badge-occupied' : 'badge-available' ?>"><?= $r['occupied'] ?></span></td>
                                    <td style="min-width: 130px;">
                                        <div class="progress-bar-custom"><div class="progress-fill-custom" style="width: <?= $occ_pct ?>%; background: <?= $bar_color ?>;"></div></div>
                                        <span style="font-size: 0.7rem; color: #64748b;"><?= $occ_pct ?>%</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="?action=view_room&room=<?= urlencode($r['room_number']) ?>" class="btn btn-soft btn-sm">👁 Voir les lits</a>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- En-tête liste des lits -->
            <div class="flex-between">
                <div>
                    <h2 style="font-size: 1rem; font-weight: 600;">🛌 Tous les lits (<?= $total_beds ?>)</h2>
                </div>
                <a href="?action=add_bed" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Ajouter un lit
                </a>
            </div>

            <!-- Barre de filtres -->
            <form method="POST" class="filter-bar">
                <div class="filter-group">
                    <label>Chambre</label>
                    <select name="filter_room" class="filter-select">
                        <option value="">Toutes les chambres</option>
                        <?php
                        $rooms_list->data_seek(0);
                        while($rl = $rooms_list->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($rl['room_number']) ?>" <?= $filter_room == $rl['room_number'] ? 'selected' : '' ?>><?= htmlspecialchars($rl['room_number']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Type de lit</label>
                    <select name="filter_type" class="filter-select">
                        <option value="">Tous les types</option>
                        <option value="standard" <?= $filter_type == 'standard' ? 'selected' : '' ?>>Standard</option>
                        <option value="icu" <?= $filter_type == 'icu' ? 'selected' : '' ?>>USI (Réanimation)</option>
                        <option value="pediatric" <?= $filter_type == 'pediatric' ? 'selected' : '' ?>>Pédiatrique</option>
                        <option value="isolation" <?= $filter_type == 'isolation' ? 'selected' : '' ?>>Isolation</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Statut</label>
                    <select name="filter_status" class="filter-select">
                        <option value="">Tous</option>
                        <option value="available" <?= $filter_status == 'available' ? 'selected' : '' ?>>Libre</option>
                        <option value="occupied" <?= $filter_status == 'occupied' ? 'selected' : '' ?>>Occupé</option>
                        <option value="cleaning" <?= $filter_status == 'cleaning' ? 'selected' : '' ?>>Nettoyage</option>
                        <option value="maintenance" <?= $filter_status == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                        <option value="reserved" <?= $filter_status == 'reserved' ? 'selected' : '' ?>>Réservé</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">🔍 Filtrer</button>
            </form>

            <!-- Tableau des lits -->
            <div class="card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th class="text-center">Chambre #</th>
                            <th class="text-center">Lit #</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Patient</th>
                            <th>Admission</th>
                            <th class="text-center">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if(!$beds || $beds->num_rows == 0): ?>
                            <tr><td colspan="7" class="empty-state">
                                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p>Aucun lit trouvé.</p>
                                    <a href="?action=add_bed" class="btn btn-primary btn-sm" style="margin-top: 15px;">+ Ajouter un lit</a>
                                </td></tr>
                        <?php else:
                            while($b = $beds->fetch_assoc()):
                                $next_status = match($b['status']) {
                                    'available' => 'occupied',
                                    'occupied' => 'cleaning',
                                    'cleaning' => 'available',
                                    'maintenance' => 'available',
                                    'reserved' => 'available',
                                    default => 'available'
                                };
                                $next_label = match($next_status) {
                                    'available' => 'Libre',
                                    'occupied' => 'Occuper',
                                    'cleaning' => 'Nettoyage',
                                    'maintenance' => 'Maintenance',
                                    'reserved' => 'Réservé',
                                    default => ucfirst($next_status)
                                };
                                $status_label = match($b['status']) {
                                    'available' => 'Libre',
                                    'occupied' => 'Occupé',
                                    'cleaning' => 'Nettoyage',
                                    'maintenance' => 'Maintenance',
                                    'reserved' => 'Réservé',
                                    default => ucfirst($b['status'])
                                };
                                $type_label = match($b['bed_type']) {
                                    'standard' => 'Standard',
                                    'icu' => 'USI',
                                    'pediatric' => 'Pédiatrique',
                                    'isolation' => 'Isolation',
                                    default => ucfirst($b['bed_type'])
                                };
                                ?>
                                <tr>
                                    <td class="text-center room-number"><?= htmlspecialchars($b['room_number']) ?></td>
                                    <td class="text-center" style="font-weight: 500;"><?= htmlspecialchars($b['bed_number']) ?></td>
                                    <td><span class="badge badge-<?= $b['bed_type'] ?>"><?= $type_label ?></span></td>
                                    <td><span class="badge badge-<?= $b['status'] ?>"><?= $status_label ?></span></td>
                                    <td style="color: #64748b;"><?= $b['patient_id'] ? 'Patient #' . $b['patient_id'] : '—' ?></td>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= $b['admission_date'] ? substr($b['admission_date'], 0, 10) : '—' ?></td>
                                    <td class="text-center">
                                        <div class="flex" style="gap: 6px; justify-content: center;">
                                            <a href="?bed_status=<?= $next_status ?>&id=<?= $b['id'] ?>" class="btn btn-soft btn-sm">→ <?= $next_label ?></a>
                                            <a href="?delete_bed=<?= $b['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer le lit <?= htmlspecialchars($b['bed_number']) ?> ?')">🗑 Supprimer</a>
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
    ?>

    <!-- MODAL : AJOUTER UN LIT -->
    <?php if($action == 'add_bed'): ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>➕ Ajouter un nouveau lit</h2>
                <a href="gmg_rooms.php" class="modal-close">&times;</a>
            </div>
            <form method="POST" action="gmg_rooms.php">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Numéro de chambre <span class="required">*</span></label>
                            <input type="text" name="room_number" class="form-input" placeholder="Ex: A101, VIP-02, USI-1" required>
                        </div>
                        <div class="form-group">
                            <label>Numéro de lit <span class="required">*</span></label>
                            <input type="text" name="bed_number" class="form-input" placeholder="Ex: B1, LIT-A, 01" required>
                        </div>
                        <div class="form-group">
                            <label>Type de lit</label>
                            <select name="bed_type" class="form-select">
                                <option value="standard">Standard</option>
                                <option value="icu">USI (Réanimation)</option>
                                <option value="pediatric">Pédiatrique</option>
                                <option value="isolation">Isolation</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="gmg_rooms.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="add_bed" class="btn btn-primary">Ajouter le lit</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <!-- MODAL : VUE DES LITS D'UNE SALLE -->
    <?php if($action == 'view_room' && isset($_GET['room'])):
    $rnum = $database->real_escape_string($_GET['room']);
    $room_beds = $database->query("SELECT bm.*, u.full_name AS patient_name FROM bed_management bm LEFT JOIN patients p ON p.id=bm.patient_id LEFT JOIN users u ON u.id=p.user_id WHERE bm.room_number='$rnum' ORDER BY bm.bed_number");
    $room_summary = $database->query("SELECT COUNT(*) AS total, SUM(status='available') AS free, SUM(status='occupied') AS occ FROM bed_management WHERE room_number='$rnum'")->fetch_assoc();
    ?>
    <div class="modal-overlay">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h2>🛏️ Chambre <?= htmlspecialchars($rnum) ?> — <?= $room_summary['total'] ?> lits (<?= $room_summary['free'] ?> libres)</h2>
                <a href="gmg_rooms.php" class="modal-close">&times;</a>
            </div>
            <div class="modal-body">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Lit #</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Patient</th>
                            <th>Changer statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if(!$room_beds || $room_beds->num_rows == 0): ?>
                            <tr><td colspan="5" class="text-center" style="padding: 40px;">Aucun lit dans cette chambre.</td></tr>
                        <?php else:
                            while($b = $room_beds->fetch_assoc()):
                                $status_label = match($b['status']) {
                                    'available' => 'Libre',
                                    'occupied' => 'Occupé',
                                    'cleaning' => 'Nettoyage',
                                    'maintenance' => 'Maintenance',
                                    'reserved' => 'Réservé',
                                    default => ucfirst($b['status'])
                                };
                                ?>
                                <tr>
                                    <td style="font-weight: 500; text-align: center;"><?= htmlspecialchars($b['bed_number']) ?></td>
                                    <td><span class="badge badge-<?= $b['bed_type'] ?>"><?= ucfirst($b['bed_type']) ?></span></td>
                                    <td><span class="badge badge-<?= $b['status'] ?>"><?= $status_label ?></span></td>
                                    <td style="color: #64748b;"><?= $b['patient_name'] ?? ($b['patient_id'] ? 'Patient #' . $b['patient_id'] : '—') ?></td>
                                    <td>
                                        <select onchange="location='?bed_status='+this.value+'&id=<?= $b['id'] ?>'" class="filter-select" style="padding: 5px 8px; font-size: 0.75rem;">
                                            <option value="">Changer...</option>
                                            <?php foreach(['available','occupied','cleaning','maintenance','reserved'] as $st):
                                                $st_label = match($st) {
                                                    'available' => 'Libre',
                                                    'occupied' => 'Occupé',
                                                    'cleaning' => 'Nettoyage',
                                                    'maintenance' => 'Maintenance',
                                                    'reserved' => 'Réservé',
                                                    default => ucfirst($st)
                                                };
                                                ?>
                                                <option value="<?= $st ?>" <?= $b['status'] == $st ? 'selected' : '' ?>><?= $st_label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <a href="gmg_rooms.php" class="btn btn-secondary">Fermer</a>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php endif; ?>

</body>
</html>