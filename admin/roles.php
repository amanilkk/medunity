<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// roles.php - Gestion des rôles et permissions
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

if(isset($_POST['change_role'])){
    $uid      = intval($_POST['uid']);
    $new_role = intval($_POST['new_role']);
    $database->query("UPDATE users SET role_id=$new_role WHERE id=$uid");
    header("location: roles.php?msg=updated");
    exit();
}

$total_roles = $database->query("SELECT COUNT(*) AS n FROM roles")->fetch_assoc()['n'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Rôles & Permissions</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .badge-role {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .role-name {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
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
            <a href="roles.php" class="nav-item active">
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
            <div class="page-title">🔐 Rôles & Permissions</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <!-- Message de confirmation -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
                <div class="alert alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    ✅ Rôle mis à jour avec succès.
                </div>
            <?php endif; ?>

            <!-- Liste des rôles système -->
            <div class="flex-between" style="margin-bottom: 20px;">
                <h2 style="font-size: 1rem; font-weight: 600;">📋 Rôles système (<?= $total_roles ?>)</h2>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Nom du rôle</th>
                            <th>Description</th>
                            <th class="text-center">Utilisateurs</th>
                            <th class="text-center">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $roles = $database->query("SELECT r.*, COUNT(u.id) AS nb FROM roles r LEFT JOIN users u ON u.role_id=r.id AND u.is_active=1 GROUP BY r.id ORDER BY r.id");
                        if($roles->num_rows == 0): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p>Aucun rôle trouvé.</p>
                                </td>
                            </tr>
                        <?php else:
                            while($ro = $roles->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center" style="font-size: 1.1rem; font-weight: 600; color: #10b981;"><?= $ro['id'] ?></td>
                                    <td><span class="badge-role"><?= htmlspecialchars($ro['role_name']) ?></span></td>
                                    <td style="color: #64748b; font-size: 0.8rem;"><?= htmlspecialchars(substr($ro['description'] ?? '', 0, 65)) ?></td>
                                    <td class="text-center" style="font-size: 1.2rem; font-weight: 600; color: #10b981;"><?= $ro['nb'] ?></td>
                                    <td class="text-center">
                                        <a href="?action=view&id=<?= $ro['id'] ?>" class="btn btn-soft btn-sm">
                                            👁 Voir les utilisateurs
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Changer le rôle d'un utilisateur -->
            <div class="flex-between" style="margin-top: 32px; margin-bottom: 16px;">
                <h2 style="font-size: 1rem; font-weight: 600;">🔄 Changer le rôle d'un utilisateur</h2>
            </div>

            <div class="card" style="padding: 20px;">
                <form method="POST" action="roles.php" class="flex" style="gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                    <div class="filter-group" style="flex: 2;">
                        <label>Utilisateur</label>
                        <select name="uid" class="filter-select" style="width: 100%;" required>
                            <option value="">Sélectionner un utilisateur</option>
                            <?php
                            $all_u = $database->query("SELECT u.id, u.full_name, r.role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 ORDER BY u.full_name");
                            while($u = $all_u->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role_name']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group" style="flex: 1;">
                        <label>Nouveau rôle</label>
                        <select name="new_role" class="filter-select" style="width: 100%;" required>
                            <?php
                            $rlist = $database->query("SELECT * FROM roles ORDER BY id");
                            while($r = $rlist->fetch_assoc()): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="change_role" class="btn btn-primary">
                            🔄 Appliquer le changement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL : Voir les utilisateurs d'un rôle -->
<?php if(isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])):
    $rid = intval($_GET['id']);
    $role_info = $database->query("SELECT * FROM roles WHERE id=$rid")->fetch_assoc();
    $role_users = $database->query("SELECT u.id, u.full_name, u.email, u.is_active, u.last_login FROM users u WHERE u.role_id=$rid ORDER BY u.full_name");
    if($role_info):
        ?>
        <div class="modal-overlay">
            <div class="modal" style="max-width: 750px;">
                <div class="modal-header">
                    <h2>👥 Utilisateurs du rôle : <span class="badge-role"><?= htmlspecialchars($role_info['role_name']) ?></span></h2>
                    <a href="roles.php" class="modal-close">&times;</a>
                </div>
                <div class="modal-body">
                    <?php if($role_info['description']): ?>
                        <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 20px;"><?= htmlspecialchars($role_info['description']) ?></p>
                    <?php endif; ?>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if($role_users->num_rows == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center" style="padding: 40px;">Aucun utilisateur assigné à ce rôle.</td>
                                </tr>
                            <?php else:
                                while($u = $role_users->fetch_assoc()):
                                    // Formater la date de dernière connexion
                                    $last_login_display = 'Jamais connecté';
                                    if(!empty($u['last_login']) && $u['last_login'] != '0000-00-00 00:00:00'){
                                        $last_login_display = date('d/m/Y H:i', strtotime($u['last_login']));
                                    }
                                    ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= htmlspecialchars(substr($u['full_name'], 0, 30)) ?></td>
                                        <td style="font-size: 0.8rem;"><?= htmlspecialchars(substr($u['email'], 0, 30)) ?></td>
                                        <td>
                                            <?php if($u['is_active']): ?>
                                                <span class="badge badge-active">Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-inactive">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 0.75rem; color: #64748b;"><?= $last_login_display ?></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="roles.php" class="btn btn-secondary">Fermer</a>
                </div>
            </div>
        </div>
    <?php endif; endif; ?>

</body>
</html>