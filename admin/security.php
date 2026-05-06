<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// security.php - Sécurité et audit
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

if(isset($_GET['reset_attempts'])){
    $uid = intval($_GET['reset_attempts']);
    $database->query("UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE id=$uid");
    header("location: security.php?msg=reset");
    exit();
}

$filter_action = $database->real_escape_string($_POST['filter_action']??'');
$filter_user   = $database->real_escape_string($_POST['filter_user']??'');
$where = "WHERE 1=1";
if($filter_action) $where .= " AND l.action LIKE '%$filter_action%'";
if($filter_user)   $where .= " AND (u.full_name LIKE '%$filter_user%' OR u.email LIKE '%$filter_user%')";

$total_logs = $database->query("SELECT COUNT(*) AS n FROM logs l LEFT JOIN users u ON u.id=l.user_id $where")->fetch_assoc()['n'] ?? 0;
$logs       = $database->query("SELECT l.id,l.action,l.entity_type,l.entity_id,l.ip_address,l.created_at,u.full_name,u.email FROM logs l LEFT JOIN users u ON u.id=l.user_id $where ORDER BY l.created_at DESC LIMIT 100");
$at_risk    = $database->query("SELECT id,full_name,email,failed_login_attempts,locked_until FROM users WHERE failed_login_attempts>0 ORDER BY failed_login_attempts DESC");
$active_sessions = $database->query("SELECT s.*,u.full_name,u.email FROM sessions s INNER JOIN users u ON u.id=s.user_id WHERE s.expires_at>NOW() ORDER BY s.created_at DESC LIMIT 15");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Sécurité</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .log-info    { background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .log-warning { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .log-danger  { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .log-success { background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .filter-bar-security {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 20px;
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
        .filter-input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.8rem;
            width: 200px;
            outline: none;
        }
        .filter-input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.1);
        }
        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 16px;
            padding-left: 4px;
            border-left: 3px solid #10b981;
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
            <a href="index.php" class="nav-item">
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
            <a href="security.php" class="nav-item active">
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
            <div class="page-title">🛡️ Sécurité & Audit</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <!-- Message de confirmation -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'reset'): ?>
                <div class="alert alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    ✅ Tentatives de connexion réinitialisées avec succès.
                </div>
            <?php endif; ?>

            <!-- Comptes à risque -->
            <div class="section-title">⚠️ Comptes à risque</div>
            <div class="card" style="margin-bottom: 24px;">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th class="text-center">Tentatives échouées</th>
                            <th class="text-center">Verrouillé jusqu'au</th>
                            <th class="text-center">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if($at_risk->num_rows == 0): ?>
                            <tr><td colspan="5" class="text-center" style="padding: 40px;">✅ Aucun compte à risque détecté</td></tr>
                        <?php else:
                            while($ar = $at_risk->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars(substr($ar['full_name'], 0, 30)) ?></td>
                                    <td style="font-size: 0.8rem;"><?= htmlspecialchars(substr($ar['email'], 0, 30)) ?></td>
                                    <td class="text-center"><span class="log-danger"><?= $ar['failed_login_attempts'] ?> tentative(s)</span></td>
                                    <td class="text-center" style="font-size: 0.75rem; color: #64748b;"><?= $ar['locked_until'] ? substr($ar['locked_until'], 0, 16) : '—' ?></td>
                                    <td class="text-center">
                                        <a href="?reset_attempts=<?= $ar['id'] ?>" class="btn btn-soft btn-sm" onclick="return confirm('Réinitialiser les tentatives pour cet utilisateur ?')">
                                            🔄 Réinitialiser
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sessions actives -->
            <div class="section-title">🖥️ Sessions actives</div>
            <div class="card" style="margin-bottom: 24px;">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th class="text-center">2FA vérifié</th>
                            <th>Adresse IP</th>
                            <th>Expiration</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if(!$active_sessions || $active_sessions->num_rows == 0): ?>
                            <tr><td colspan="5" class="text-center" style="padding: 40px;">Aucune session active trouvée</td></tr>
                        <?php else:
                            while($sess = $active_sessions->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars(substr($sess['full_name'], 0, 25)) ?></td>
                                    <td style="font-size: 0.8rem;"><?= htmlspecialchars(substr($sess['email'], 0, 25)) ?></td>
                                    <td class="text-center"><?= $sess['otp_verified'] ? '✅ Oui' : '❌ Non' ?></td>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($sess['ip_address'] ?? '—') ?></td>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= substr($sess['expires_at'], 0, 16) ?></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Journal d'audit -->
            <div class="section-title">📋 Journal d'audit (<?= $total_logs ?> entrées)</div>

            <!-- Filtres -->
            <form method="POST" class="filter-bar-security">
                <div class="filter-group">
                    <label>Action</label>
                    <input type="text" name="filter_action" class="filter-input" placeholder="ex: LOGIN, DELETE..." value="<?= htmlspecialchars($filter_action) ?>">
                </div>
                <div class="filter-group">
                    <label>Utilisateur</label>
                    <input type="text" name="filter_user" class="filter-input" placeholder="Nom ou email..." value="<?= htmlspecialchars($filter_user) ?>">
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary">🔍 Filtrer</button>
                    <a href="security.php" class="btn btn-soft">🔄 Réinitialiser</a>
                </div>
            </form>

            <!-- Tableau des logs -->
            <div class="card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Date / Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Entité</th>
                            <th class="text-center">ID</th>
                            <th>Adresse IP</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if(!$logs || $logs->num_rows == 0): ?>
                            <tr><td colspan="6" class="empty-state">
                                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p>Aucune entrée de journal trouvée.</p>
                                    <a href="security.php" class="btn btn-primary btn-sm">Afficher tous les logs</a>
                                </td></tr>
                        <?php else:
                            while($lg = $logs->fetch_assoc()):
                                $al = strtolower($lg['action']);
                                $badge_class = match(true) {
                                    str_contains($al, 'delete') || str_contains($al, 'deactivate') => 'log-danger',
                                    str_contains($al, 'create') || str_contains($al, 'add') || str_contains($al, 'insert') => 'log-info',
                                    str_contains($al, 'login') => 'log-success',
                                    default => 'log-warning'
                                };
                                ?>
                                <tr>
                                    <td style="font-size: 0.7rem; color: #64748b;"><?= substr($lg['created_at'] ?? '', 0, 16) ?></td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($lg['full_name'] ?? 'Système') ?></td>
                                    <td><span class="<?= $badge_class ?>"><?= htmlspecialchars($lg['action']) ?></span></td>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($lg['entity_type'] ?? '—') ?></td>
                                    <td class="text-center"><?= $lg['entity_id'] ?? '—' ?></td>
                                    <td style="font-size: 0.7rem; color: #94a3b8;"><?= htmlspecialchars($lg['ip_address'] ?? '—') ?></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>