<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// gmg_index.php - Tableau de bord GMG (Gestion des Moyens Généraux)
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
$today = date('Y-m-d');

// BD réelle: table bed_management
$nb_beds_total = $database->query("SELECT COUNT(*) AS n FROM bed_management")->fetch_assoc()['n'] ?? 0;
$nb_beds_free  = $database->query("SELECT COUNT(*) AS n FROM bed_management WHERE status='available'")->fetch_assoc()['n'] ?? 0;
$nb_rooms      = $database->query("SELECT COUNT(DISTINCT room_number) AS n FROM bed_management")->fetch_assoc()['n'] ?? 0;

// Blocs opératoires
$nb_operating_total = $database->query("SELECT COUNT(*) AS n FROM operating_rooms")->fetch_assoc()['n'] ?? 0;
$nb_operating_available = $database->query("SELECT COUNT(*) AS n FROM operating_rooms WHERE status='available'")->fetch_assoc()['n'] ?? 0;
$nb_operating_in_use = $database->query("SELECT COUNT(*) AS n FROM operating_rooms WHERE status='in_use'")->fetch_assoc()['n'] ?? 0;

// BD réelle: table maintenance_requests
$nb_maint_open = $database->query("SELECT COUNT(*) AS n FROM maintenance_requests WHERE status IN ('pending','in_progress')")->fetch_assoc()['n'] ?? 0;
$nb_maint_crit = $database->query("SELECT COUNT(*) AS n FROM maintenance_requests WHERE priority='critical' AND status!='completed'")->fetch_assoc()['n'] ?? 0;

// BD réelle: table medicines
$nb_stock_low  = $database->query("SELECT COUNT(*) AS n FROM medicines WHERE quantity <= threshold_alert")->fetch_assoc()['n'] ?? 0;
$nb_suppliers  = $database->query("SELECT COUNT(*) AS n FROM suppliers WHERE is_active=1")->fetch_assoc()['n'] ?? 0;
$orders_open   = $database->query("SELECT COUNT(*) AS n FROM purchase_orders WHERE status IN ('pending','confirmed','shipped')")->fetch_assoc()['n'] ?? 0;

$recent_maint  = $database->query("SELECT equipment_name, equipment_location, priority, status, created_at FROM maintenance_requests ORDER BY created_at DESC LIMIT 6");
$low_stock     = $database->query("SELECT name, quantity, threshold_alert FROM medicines WHERE quantity <= threshold_alert ORDER BY quantity ASC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMG — Tableau de bord</title>
    <link rel="stylesheet" href="gmg_style.css">
    <style>
        .badge-operating {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-available {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-in_use {
            background: #fee2e2;
            color: #991b1b;
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
            <a href="gmg_index.php" class="nav-item active">
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
            <div class="page-title">Tableau de bord</div>
            <div class="date-badge"><?= $today ?></div>
        </div>
        <div class="content">
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_beds_free ?>/<?= $nb_beds_total ?></div>
                        <div class="stat-label">Lits libres</div>
                    </div>
                    <div class="stat-icon">🛏️</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_rooms ?></div>
                        <div class="stat-label">Chambres distinctes</div>
                    </div>
                    <div class="stat-icon">🚪</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_operating_total ?></div>
                        <div class="stat-label">Blocs opératoires</div>
                    </div>
                    <div class="stat-icon">🏥</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_operating_available ?></div>
                        <div class="stat-label">Blocs disponibles</div>
                    </div>
                    <div class="stat-icon">✅</div>
                </div>
            </div>

            <!-- Deuxième ligne de stats -->
            <div class="stats-grid" style="margin-top: 0;">
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_maint_open ?></div>
                        <div class="stat-label">Demandes de maintenance</div>
                    </div>
                    <div class="stat-icon">🔧</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_stock_low ?></div>
                        <div class="stat-label">Articles en stock bas</div>
                    </div>
                    <div class="stat-icon">⚠️</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_suppliers ?></div>
                        <div class="stat-label">Fournisseurs actifs</div>
                    </div>
                    <div class="stat-icon">🏢</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $orders_open ?></div>
                        <div class="stat-label">Commandes en cours</div>
                    </div>
                    <div class="stat-icon">📦</div>
                </div>
            </div>

            <!-- Two Columns -->
            <div class="two-columns">
                <!-- Maintenance Requests -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📋 Demandes de maintenance récentes</div>
                        <span class="badge badge-critical"><?= $nb_maint_crit ?> critique(s)</span>
                    </div>
                    <div class="card-body">
                        <div class="scrollable-y" style="max-height: 260px;">
                            <table class="data-table">
                                <thead>
                                <tr><th>Équipement</th><th>Emplacement</th><th>Priorité</th><th>Statut</th></tr>
                                </thead>
                                <tbody>
                                <?php if(!$recent_maint || $recent_maint->num_rows == 0): ?>
                                    <tr><td colspan="4" class="text-center" style="padding: 40px;">✅ Aucune demande de maintenance</td></tr>
                                <?php else: while($m = $recent_maint->fetch_assoc()):
                                    $status_text = match($m['status']) {
                                        'pending' => 'En attente',
                                        'in_progress' => 'En cours',
                                        'completed' => 'Terminé',
                                        'cancelled' => 'Annulé',
                                        default => $m['status']
                                    };
                                    ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= htmlspecialchars(substr($m['equipment_name'], 0, 22)) ?></td>
                                        <td style="color: #64748b;"><?= htmlspecialchars(substr($m['equipment_location'] ?? '—', 0, 18)) ?></td>
                                        <td><span class="badge badge-<?= $m['priority'] ?>"><?= ucfirst($m['priority']) ?></span></td>
                                        <td><span class="badge badge-<?= $m['status'] ?>"><?= $status_text ?></span></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="gmg_maintenance.php" class="btn btn-secondary btn-block">Voir toute la maintenance →</a>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">⚠️ Alertes stock critique</div>
                        <span class="badge badge-stock-low"><?= $nb_stock_low ?> alerte(s)</span>
                    </div>
                    <div class="card-body">
                        <div class="scrollable-y" style="max-height: 260px;">
                            <table class="data-table">
                                <thead>
                                <tr><th>Médicament</th><th>Stock</th><th>Seuil</th><th>Niveau</th></tr>
                                </thead>
                                <tbody>
                                <?php if(!$low_stock || $low_stock->num_rows == 0): ?>
                                    <tr><td colspan="4" class="text-center" style="padding: 40px;">✅ Tous les stocks sont suffisants</td></tr>
                                <?php else: while($s = $low_stock->fetch_assoc()):
                                    $pct = $s['threshold_alert'] > 0 ? min(100, round($s['quantity'] / $s['threshold_alert'] * 100)) : 100;
                                    $bar_color = $pct <= 30 ? '#ef4444' : '#f59e0b';
                                    $level_text = $pct <= 30 ? 'Critique' : ($pct <= 60 ? 'Bas' : 'Alerte');
                                    ?>
                                    <td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars(substr($s['name'], 0, 22)) ?></td>
                                    <td style="font-weight: 600; color: #059669;"><?= $s['quantity'] ?></td>
                                    <td style="color: #64748b;"><?= $s['threshold_alert'] ?></td>
                                    <td>
                                        <div class="progress-bar"><div class="progress-fill" style="width: <?= $pct ?>%; background: <?= $bar_color ?>;"></div></div>
                                        <span style="font-size: 0.7rem; margin-left: 6px;"><?= $level_text ?></span>
                                    </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="gmg_stock.php" class="btn btn-secondary btn-block">Voir tout le stock →</a>
                    </div>
                </div>
            </div>

            <!-- Info supplémentaires -->
            <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 0;">
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_beds_total > 0 ? round(($nb_beds_total - $nb_beds_free) / $nb_beds_total * 100) : 0 ?>%</div>
                        <div class="stat-label">Taux occupation lits</div>
                    </div>
                    <div class="stat-icon">📊</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_operating_total > 0 ? round($nb_operating_in_use / $nb_operating_total * 100) : 0 ?>%</div>
                        <div class="stat-label">Taux occupation blocs</div>
                    </div>
                    <div class="stat-icon">📈</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_operating_in_use ?></div>
                        <div class="stat-label">Interventions en cours</div>
                    </div>
                    <div class="stat-icon">🔪</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_operating_available ?></div>
                        <div class="stat-label">Blocs prêts</div>
                    </div>
                    <div class="stat-icon">✅</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>