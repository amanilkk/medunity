<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// index.php - Tableau de bord des statistiques (Admin)
session_start();
if(isset($_SESSION["user"])){
    if($_SESSION["user"]=="" or $_SESSION['usertype']!='a'){
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
$month = date('Y-m');

$nb_patients   = $database->query("SELECT COUNT(*) AS n FROM patients pt INNER JOIN users u ON u.id=pt.user_id WHERE u.is_active=1")->fetch_assoc()['n'] ?? 0;
$nb_doctors    = $database->query("SELECT COUNT(*) AS n FROM doctors d INNER JOIN users u ON u.id=d.user_id WHERE u.is_active=1")->fetch_assoc()['n'] ?? 0;
$nb_users      = $database->query("SELECT COUNT(*) AS n FROM users WHERE is_active=1")->fetch_assoc()['n'] ?? 0;
$nb_appo_today = $database->query("SELECT COUNT(*) AS n FROM appointments WHERE appointment_date='$today'")->fetch_assoc()['n'] ?? 0;
$nb_appo_month = $database->query("SELECT COUNT(*) AS n FROM appointments WHERE appointment_date LIKE '$month%'")->fetch_assoc()['n'] ?? 0;
$nb_appo_total = $database->query("SELECT COUNT(*) AS n FROM appointments")->fetch_assoc()['n'] ?? 0;
$rev_total = $database->query("SELECT COALESCE(SUM(paid_amount),0) AS t FROM invoices WHERE status='paid'")->fetch_assoc()['t'] ?? 0;
$appo_by_status= $database->query("SELECT status, COUNT(*) AS nb FROM appointments GROUP BY status ORDER BY nb DESC");
$top_doctors   = $database->query("SELECT u.full_name, COUNT(a.id) AS nb FROM appointments a INNER JOIN doctors d ON d.id=a.doctor_id INNER JOIN users u ON u.id=d.user_id GROUP BY d.id ORDER BY nb DESC LIMIT 5");
$recent_patients=$database->query("SELECT u.full_name, u.email, u.created_at FROM patients pt INNER JOIN users u ON u.id=pt.user_id ORDER BY pt.id DESC LIMIT 8");
$appo_week_res = $database->query("SELECT appointment_date, COUNT(*) AS nb FROM appointments WHERE appointment_date >= DATE_SUB('$today', INTERVAL 6 DAY) GROUP BY appointment_date ORDER BY appointment_date");
$days_data=[]; $max_day=1;
while($d=$appo_week_res->fetch_assoc()){ $days_data[]=$d; if($d['nb']>$max_day) $max_day=$d['nb']; }

// Mapping des statuts pour l'affichage
$status_labels = [
    'confirmed' => 'Confirmé',
    'completed' => 'Terminé',
    'pending' => 'En attente',
    'cancelled' => 'Annulé',
    'urgent' => 'Urgent',
    'no_show' => 'Absent'
];

$status_colors = [
    'confirmed' => ['bg' => '#d1fae5', 'color' => '#065f46'],
    'completed' => ['bg' => '#dbeafe', 'color' => '#1e40af'],
    'pending' => ['bg' => '#fef3c7', 'color' => '#92400e'],
    'cancelled' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
    'urgent' => ['bg' => '#ede9fe', 'color' => '#5b21b6'],
    'no_show' => ['bg' => '#f1f5f9', 'color' => '#475569']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Statistiques</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .stat-bar-wrap { background: #e2e8f0; border-radius: 4px; height: 10px; width: 100%; }
        .stat-bar-fill { border-radius: 4px; height: 10px; background: #10b981; }
        .rank-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: #10b981;
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
            <div class="logo-sub">Gestion Administrative</div>
        </div>
        <nav>
            <a href="index.php" class="nav-item active">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2h-5v-8H7v8H5a2 2 0 0 1-2-2z"/>
                </svg>
                <span>Statistiques</span>
            </a>
            <a href="users.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span>Utilisateurs</span>
            </a>
            <a href="roles.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/>
                </svg>
                <span>Rôles</span>
            </a>
            <a href="security.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <span>Sécurité</span>
            </a>
            <a href="backup.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 15 7 15 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                <span>Sauvegarde</span>
            </a>
            <a href="profile.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span>Mon profil</span>
            </a>
        </nav>
        <div class="user-info">
            <div class="user-avatar">AD</div>
            <div class="user-details">
                <div class="user-name">Administrateur</div>
                <div class="user-role">admin@edoc.com</div>
                <a href="../logout.php" class="logout-link">Déconnexion</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">📊 Statistiques & Rapports</div>
            <div class="date-badge"><?= $today ?></div>
        </div>
        <div class="content">

            <!-- KPI Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_users ?></div>
                        <div class="stat-label">Utilisateurs actifs</div>
                    </div>
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_patients ?></div>
                        <div class="stat-label">Patients</div>
                    </div>
                    <div class="stat-icon">🩺</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_appo_today ?></div>
                        <div class="stat-label">Rendez-vous aujourd'hui</div>
                    </div>
                    <div class="stat-icon">📅</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= number_format($rev_total, 0, '.', ' ') ?> DA</div>
                        <div class="stat-label">Chiffre d'affaires</div>
                    </div>
                    <div class="stat-icon">💰</div>
                </div>
            </div>

            <!-- Two Columns -->
            <div class="two-columns">
                <!-- Appointments by Status -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📋 Rendez-vous par statut</div>
                        <span class="badge badge-pending">Total: <?= $nb_appo_total ?></span>
                    </div>
                    <div class="card-body">
                        <div class="scrollable-y" style="max-height: 280px;">
                            <table class="data-table">
                                <thead>
                                <tr><th>Statut</th><th>Nombre</th><th>Part</th></tr>
                                </thead>
                                <tbody>
                                <?php while($s = $appo_by_status->fetch_assoc()):
                                    $pct = $nb_appo_total > 0 ? round($s['nb'] / $nb_appo_total * 100) : 0;
                                    $colors = $status_colors[$s['status']] ?? ['bg' => '#f1f5f9', 'color' => '#475569'];
                                    $label = $status_labels[$s['status']] ?? ucfirst($s['status']);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge" style="background: <?= $colors['bg'] ?>; color: <?= $colors['color'] ?>;">
                                                <?= $label ?>
                                            </span>
                                        </td>
                                        <td class="text-center" style="font-size: 1.2rem; font-weight: 600;"><?= $s['nb'] ?></td>
                                        <td>
                                            <div class="stat-bar-wrap"><div class="stat-bar-fill" style="width: <?= $pct ?>%;"></div></div>
                                            <span style="font-size: 0.7rem; color: #64748b;"><?= $pct ?>%</span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="security.php" class="btn btn-secondary btn-block">🔒 Voir les journaux de sécurité</a>
                    </div>
                </div>

                <!-- Top 5 Doctors -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">🏆 Top 5 médecins</div>
                        <span class="badge badge-primary">Classés par nombre de rendez-vous</span>
                    </div>
                    <div class="card-body">
                        <div class="scrollable-y" style="max-height: 280px;">
                            <table class="data-table">
                                <thead>
                                <tr><th>#</th><th>Nom du médecin</th><th>Rendez-vous</th></tr>
                                </thead>
                                <tbody>
                                <?php $rk = 1; while($d = $top_doctors->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center rank-number"><?= $rk++ ?></td>
                                        <td style="font-weight: 500;"><?= htmlspecialchars(substr($d['full_name'], 0, 30)) ?></td>
                                        <td class="text-center" style="font-size: 1.1rem; font-weight: 600; color: #10b981;"><?= $d['nb'] ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="users.php" class="btn btn-secondary btn-block">👨‍⚕️ Gérer les utilisateurs</a>
                    </div>
                </div>
            </div>

            <!-- Second Row -->
            <div class="two-columns">
                <!-- Appointments Last 7 Days -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📈 Rendez-vous — 7 derniers jours</div>
                    </div>
                    <div class="card-body">
                        <div class="scrollable-y" style="max-height: 250px;">
                            <table class="data-table">
                                <thead>
                                <tr><th>Date</th><th>Nombre</th><th>Volume</th></tr>
                                </thead>
                                <tbody>
                                <?php if(empty($days_data)): ?>
                                    <tr><td colspan="3" class="text-center" style="padding: 30px;">✅ Aucun rendez-vous cette semaine</td></tr>
                                <?php else: foreach($days_data as $d):
                                    $pct = $max_day > 0 ? round($d['nb'] / $max_day * 100) : 0;
                                    ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= $d['appointment_date'] ?></td>
                                        <td class="text-center" style="font-size: 1.2rem; font-weight: 600;"><?= $d['nb'] ?></td>
                                        <td>
                                            <div class="stat-bar-wrap"><div class="stat-bar-fill" style="width: <?= $pct ?>%;"></div></div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="backup.php" class="btn btn-secondary btn-block">💾 Accéder à la sauvegarde</a>
                    </div>
                </div>

                <!-- Recent Patients -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">🆕 Derniers patients</div>
                        <span class="badge badge-primary">8 derniers inscrits</span>
                    </div>
                    <div class="card-body">
                        <div class="scrollable-y" style="max-height: 250px;">
                            <table class="data-table">
                                <thead>
                                <tr><th>Nom</th><th>Email</th><th>Inscription</th></tr>
                                </thead>
                                <tbody>
                                <?php while($p = $recent_patients->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= htmlspecialchars(substr($p['full_name'], 0, 25)) ?></td>
                                        <td style="font-size: 0.75rem;"><?= htmlspecialchars(substr($p['email'], 0, 22)) ?></td>
                                        <td class="text-center" style="font-size: 0.7rem; color: #64748b;"><?= substr($p['created_at'], 0, 10) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="roles.php" class="btn btn-secondary btn-block">⚙️ Gérer les rôles</a>
                    </div>
                </div>
            </div>

            <!-- Additional Stats Row -->
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-top: 0;">
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_doctors ?></div>
                        <div class="stat-label">Médecins actifs</div>
                    </div>
                    <div class="stat-icon">👨‍⚕️</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_appo_month ?></div>
                        <div class="stat-label">Rendez-vous ce mois</div>
                    </div>
                    <div class="stat-icon">📆</div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="stat-value"><?= $nb_appo_total > 0 ? round(($nb_appo_today / $nb_appo_total) * 100) : 0 ?>%</div>
                        <div class="stat-label">Taux quotidien</div>
                    </div>
                    <div class="stat-icon">📊</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>