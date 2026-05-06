<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// users.php - Gestion complète des utilisateurs (CRUD)
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

// Activer/Désactiver un utilisateur
if(isset($_GET['deactivate'])){
    $uid = intval($_GET['deactivate']);
    $database->query("UPDATE users SET is_active=0 WHERE id=$uid AND role_id != 1");
    header("location: users.php");
    exit();
}
if(isset($_GET['activate'])){
    $uid = intval($_GET['activate']);
    $database->query("UPDATE users SET is_active=1 WHERE id=$uid");
    header("location: users.php");
    exit();
}

// Supprimer un utilisateur
if(isset($_GET['delete'])){
    $uid = intval($_GET['delete']);
    // Vérifier que ce n'est pas l'admin principal
    $check = $database->query("SELECT role_id FROM users WHERE id=$uid")->fetch_assoc();
    if($check['role_id'] != 1){
        $database->query("DELETE FROM users WHERE id=$uid");
        header("location: users.php?msg=deleted");
        exit();
    }
}

// Modifier un utilisateur
if(isset($_POST['edit_user'])){
    $uid = intval($_POST['user_id']);
    $full_name = $database->real_escape_string($_POST['full_name']);
    $email = $database->real_escape_string($_POST['email']);
    $phone = $database->real_escape_string($_POST['phone']);
    $role_id = intval($_POST['role_id']);
    $two_factor = isset($_POST['two_factor_enabled']) ? 1 : 0;

    // Vérifier si l'email existe déjà pour un autre utilisateur
    $check = $database->query("SELECT id FROM users WHERE email='$email' AND id != $uid");
    if($check->num_rows > 0){
        header("location: users.php?action=edit&id=$uid&error=email_exists");
        exit();
    }

    $database->query("UPDATE users SET full_name='$full_name', email='$email', phone='$phone', role_id=$role_id, two_factor_enabled=$two_factor WHERE id=$uid");

    // Log
    $actor = intval($_SESSION['uid'] ?? 1);
    $database->query("INSERT INTO logs (user_id, action, entity_type, entity_id, ip_address) VALUES ($actor, 'EDIT_USER', 'users', $uid, '{$_SERVER['REMOTE_ADDR']}')");

    header("location: users.php?msg=updated");
    exit();
}

// Réinitialiser le mot de passe d'un utilisateur
if(isset($_POST['reset_password'])){
    $uid = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if($new_password !== $confirm_password){
        header("location: users.php?action=edit&id=$uid&error=password_mismatch");
        exit();
    }

    // Vérifier la complexité
    $complexity_errors = [];
    if(strlen($new_password) < 8) $complexity_errors[] = "8 caractères minimum";
    if(!preg_match('/[A-Z]/', $new_password)) $complexity_errors[] = "une majuscule";
    if(!preg_match('/[a-z]/', $new_password)) $complexity_errors[] = "une minuscule";
    if(!preg_match('/[0-9]/', $new_password)) $complexity_errors[] = "un chiffre";
    if(!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $new_password)) $complexity_errors[] = "un caractère spécial";

    if(!empty($complexity_errors)){
        $error_msg = "Mot de passe faible : " . implode(", ", $complexity_errors);
        header("location: users.php?action=edit&id=$uid&error=" . urlencode($error_msg));
        exit();
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $database->query("UPDATE users SET password='$hashed' WHERE id=$uid");

    // Log
    $actor = intval($_SESSION['uid'] ?? 1);
    $database->query("INSERT INTO logs (user_id, action, entity_type, entity_id, ip_address) VALUES ($actor, 'RESET_PASSWORD', 'users', $uid, '{$_SERVER['REMOTE_ADDR']}')");

    header("location: users.php?msg=password_reset");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Utilisateurs</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .badge-role {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
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
        .stats-grid-mini {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-mini {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .stat-mini-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
        }
        .stat-mini-label {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .modal {
            max-width: 600px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        .full-width {
            grid-column: 1 / -1;
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
        .password-strength {
            margin-top: 8px;
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 6px;
        }
        .password-strength.weak {
            background: #fee2e2;
            color: #991b1b;
        }
        .password-strength.medium {
            background: #fef3c7;
            color: #92400e;
        }
        .password-strength.strong {
            background: #d1fae5;
            color: #065f46;
        }
        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-icon {
            padding: 6px 12px;
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
            <a href="users.php" class="nav-item active">
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
            <div class="page-title">👥 Gestion des utilisateurs</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <!-- Messages -->
            <?php if(isset($_GET['msg'])): ?>
                <?php if($_GET['msg'] == 'updated'): ?>
                    <div class="alert alert-success">✅ Utilisateur modifié avec succès !</div>
                <?php elseif($_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success">🗑️ Utilisateur supprimé avec succès !</div>
                <?php elseif($_GET['msg'] == 'password_reset'): ?>
                    <div class="alert alert-success">🔐 Mot de passe réinitialisé avec succès !</div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Statistiques -->
            <?php
            $total_active = $database->query("SELECT COUNT(*) AS n FROM users WHERE is_active=1")->fetch_assoc()['n'] ?? 0;
            $total_inactive = $database->query("SELECT COUNT(*) AS n FROM users WHERE is_active=0")->fetch_assoc()['n'] ?? 0;
            $total_2fa = $database->query("SELECT COUNT(*) AS n FROM users WHERE two_factor_enabled=1")->fetch_assoc()['n'] ?? 0;
            $total_users = $database->query("SELECT COUNT(*) AS n FROM users")->fetch_assoc()['n'] ?? 0;
            ?>
            <div class="stats-grid-mini">
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $total_active ?></div>
                    <div class="stat-mini-label">Actifs</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $total_inactive ?></div>
                    <div class="stat-mini-label">Inactifs</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $total_2fa ?></div>
                    <div class="stat-mini-label">2FA activé</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $total_users ?></div>
                    <div class="stat-mini-label">Total</div>
                </div>
            </div>

            <!-- En-tête avec bouton ajout -->
            <div class="flex-between">
                <div>
                    <h2 style="font-size: 1rem; font-weight: 600;">📋 Liste des utilisateurs</h2>
                </div>
                <div class="search-bar">
                    <form action="" method="post" class="search-bar">
                        <input type="search" name="search" class="search-input" placeholder="Rechercher par nom ou email..." list="userlist">
                        <?php
                        echo '<datalist id="userlist">';
                        $ul = $database->query("SELECT full_name, email FROM users WHERE is_active=1");
                        while($ur = $ul->fetch_assoc()){
                            echo "<option value='".htmlspecialchars($ur['full_name'])."'>";
                            echo "<option value='".htmlspecialchars($ur['email'])."'>";
                        }
                        echo '</datalist>';
                        ?>
                        <button type="submit" class="btn btn-primary btn-sm">🔍 Rechercher</button>
                    </form>
                    <a href="?action=add&error=0" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Ajouter
                    </a>
                </div>
            </div>

            <!-- Tableau des utilisateurs -->
            <?php
            if($_POST && !empty($_POST['search'])){
                $kw = $database->real_escape_string($_POST['search']);
                $sqlmain = "SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.two_factor_enabled, u.last_login, r.role_name 
                            FROM users u INNER JOIN roles r ON r.id=u.role_id 
                            WHERE u.full_name LIKE '%$kw%' OR u.email LIKE '%$kw%' 
                            ORDER BY u.id DESC";
            } else {
                $sqlmain = "SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.two_factor_enabled, u.last_login, r.role_name 
                            FROM users u INNER JOIN roles r ON r.id=u.role_id 
                            ORDER BY u.id DESC";
            }
            $result = $database->query($sqlmain);
            ?>
            <div class="card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Rôle</th>
                            <th class="text-center">2FA</th>
                            <th class="text-center">Statut</th>
                            <th>Dernière connexion</th>
                            <th class="text-center">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if($result->num_rows == 0): ?>
                            <tr><td colspan="8" class="empty-state">
                                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    <p>Aucun utilisateur trouvé.</p>
                                    <a href="?action=add&error=0" class="btn btn-primary btn-sm" style="margin-top: 15px;">+ Ajouter un utilisateur</a>
                                </td></tr>
                        <?php else:
                            while($row = $result->fetch_assoc()):
                                $last_login_display = 'Jamais';
                                if(!empty($row['last_login']) && $row['last_login'] != '0000-00-00 00:00:00'){
                                    $last_login_display = date('d/m/Y H:i', strtotime($row['last_login']));
                                }
                                ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars(substr($row['full_name'], 0, 25)) ?></td>
                                    <td style="font-size: 0.8rem;"><?= htmlspecialchars(substr($row['email'], 0, 25)) ?></td>
                                    <td style="font-size: 0.75rem;"><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                                    <td><span class="badge-role"><?= htmlspecialchars($row['role_name']) ?></span></td>
                                    <td class="text-center"><?= $row['two_factor_enabled'] ? '✅' : '—' ?></td>
                                    <td class="text-center">
                                        <?php if($row['is_active']): ?>
                                            <span class="badge badge-active">Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.7rem; color: #64748b;"><?= $last_login_display ?></td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <a href="?action=edit&id=<?= $row['id'] ?>" class="btn btn-soft btn-sm btn-icon">✏️ Modifier</a>
                                            <?php if($row['is_active']): ?>
                                                <a href="?deactivate=<?= $row['id'] ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Désactiver cet utilisateur ?')">🔴 Désactiver</a>
                                            <?php else: ?>
                                                <a href="?activate=<?= $row['id'] ?>" class="btn btn-primary btn-sm btn-icon">🟢 Activer</a>
                                            <?php endif; ?>
                                            <?php if($row['role_name'] != 'admin_systeme'): ?>
                                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Supprimer définitivement cet utilisateur ?')">🗑️ Supprimer</a>
                                            <?php endif; ?>
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

<!-- MODAL : MODIFIER UN UTILISATEUR -->
<?php if(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])):
    $id = intval($_GET['id']);
    $u = $database->query("SELECT u.*, r.role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.id=$id")->fetch_assoc();
    $roles_list = $database->query("SELECT * FROM roles ORDER BY id");
    $error_msg = $_GET['error'] ?? '';
    if($u):
        ?>
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>✏️ Modifier l'utilisateur</h2>
                    <a href="users.php" class="modal-close">&times;</a>
                </div>
                <div class="modal-body">
                    <?php if($error_msg == 'email_exists'): ?>
                        <div class="alert alert-error">❌ Cet email est déjà utilisé par un autre compte.</div>
                    <?php elseif($error_msg == 'password_mismatch'): ?>
                        <div class="alert alert-error">❌ Les mots de passe ne correspondent pas.</div>
                    <?php elseif(strpos($error_msg, 'Mot de passe') !== false): ?>
                        <div class="alert alert-error">❌ <?= htmlspecialchars($error_msg) ?></div>
                    <?php endif; ?>

                    <!-- Formulaire modification informations -->
                    <form method="POST" action="users.php">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Nom complet <span class="required">*</span></label>
                                <input type="text" name="full_name" class="form-input" value="<?= htmlspecialchars($u['full_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($u['email']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Téléphone</label>
                                <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($u['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Rôle <span class="required">*</span></label>
                                <select name="role_id" class="form-select" required>
                                    <?php while($r = $roles_list->fetch_assoc()): ?>
                                        <option value="<?= $r['id'] ?>" <?= $r['id'] == $u['role_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($r['role_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Authentification 2 facteurs</label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="two_factor_enabled" value="1" <?= $u['two_factor_enabled'] ? 'checked' : '' ?>>
                                    Activer la 2FA
                                </label>
                            </div>
                        </div>
                        <div class="flex-between" style="margin-top: 20px;">
                            <button type="submit" name="edit_user" class="btn btn-primary">💾 Enregistrer les modifications</button>
                            <a href="users.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>

                    <!-- Section réinitialisation mot de passe -->
                    <hr style="margin: 24px 0; border-color: #e2e8f0;">

                    <h3 style="font-size: 0.9rem; margin-bottom: 16px;">🔐 Réinitialiser le mot de passe</h3>
                    <form method="POST" action="users.php">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nouveau mot de passe</label>
                                <input type="password" name="new_password" class="form-input" placeholder="••••••••" required
                                       onkeyup="checkPasswordStrengthEdit(this.value)">
                                <div id="password-strength-edit" class="password-strength"></div>
                            </div>
                            <div class="form-group">
                                <label>Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" class="form-input" placeholder="••••••••" required>
                            </div>
                        </div>
                        <div class="flex-between" style="margin-top: 16px;">
                            <button type="submit" name="reset_password" class="btn btn-primary">🔑 Réinitialiser le mot de passe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function checkPasswordStrengthEdit(password) {
                let strength = 0;
                if(password.length >= 8) strength++;
                if(password.match(/[A-Z]/)) strength++;
                if(password.match(/[a-z]/)) strength++;
                if(password.match(/[0-9]/)) strength++;
                if(password.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/)) strength++;

                let indicator = document.getElementById('password-strength-edit');
                if(indicator) {
                    if(strength <= 2) {
                        indicator.innerHTML = '⚠️ Mot de passe faible (8 caractères, majuscule, minuscule, chiffre, caractère spécial)';
                        indicator.className = 'password-strength weak';
                    } else if(strength <= 4) {
                        indicator.innerHTML = '⚡ Mot de passe moyen';
                        indicator.className = 'password-strength medium';
                    } else {
                        indicator.innerHTML = '✅ Mot de passe fort';
                        indicator.className = 'password-strength strong';
                    }
                }
            }
        </script>
    <?php endif; endif; ?>

<!-- MODAL : AJOUTER UN UTILISATEUR -->
<?php if(isset($_GET['action']) && $_GET['action'] == 'add' && $_GET['error'] != '4'):
    $error_1 = $_GET['error'] ?? '0';
    $error_messages = [
        '1' => '❌ Cet email est déjà utilisé par un autre compte.',
        '2' => '❌ Les mots de passe ne correspondent pas.',
        '3' => '❌ Erreur lors de l\'ajout de l\'utilisateur.',
        '5' => '❌ ' . urldecode($_GET['msg'] ?? 'Mot de passe trop faible'),
        '0' => ''
    ];
    $roles_list = $database->query("SELECT * FROM roles ORDER BY id");
    ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>➕ Ajouter un utilisateur</h2>
                <a href="users.php" class="modal-close">&times;</a>
            </div>
            <form action="add-user.php" method="POST">
                <div class="modal-body">
                    <?php if(!empty($error_messages[$error_1])): ?>
                        <div class="alert alert-error" style="margin-bottom: 16px;"><?= $error_messages[$error_1] ?></div>
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nom complet <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-input" placeholder="Nom et prénom" required>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-input" placeholder="adresse@email.com" required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="phone" class="form-input" placeholder="Numéro de téléphone">
                        </div>
                        <div class="form-group">
                            <label>Rôle <span class="required">*</span></label>
                            <select name="role_id" class="form-select" required id="role_select_add" onchange="toggleSpecialtyField(this)">
                                <option value="" data-name="">Sélectionner un rôle</option>
                                <?php while($r = $roles_list->fetch_assoc()): ?>
                                    <option value="<?= $r['id'] ?>" data-name="<?= htmlspecialchars(strtolower($r['role_name'])) ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Champ spécialité — visible uniquement si rôle = médecin -->
                        <div class="form-group full-width" id="specialty_field" style="display:none;">
                            <label>Spécialité <span class="required">*</span></label>
                            <?php $specialties = $database->query("SELECT id, sname FROM specialties ORDER BY sname"); ?>
                            <select name="specialty_id" class="form-select" id="specialty_select">
                                <option value="">— Sélectionner une spécialité —</option>
                                <?php while($sp = $specialties->fetch_assoc()): ?>
                                    <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['sname']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Mot de passe <span class="required">*</span></label>
                            <input type="password" name="password" class="form-input" placeholder="••••••••" required
                                   onkeyup="checkPasswordStrengthAdd(this.value)">
                            <div id="password-strength-add" class="password-strength"></div>
                        </div>
                        <div class="form-group">
                            <label>Confirmer le mot de passe <span class="required">*</span></label>
                            <input type="password" name="cpassword" class="form-input" placeholder="••••••••" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="users.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">Créer l'utilisateur</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSpecialtyField(select) {
            const roleName = select.options[select.selectedIndex].getAttribute('data-name') || '';
            const isDoctor = roleName.includes('doctor') || roleName.includes('medecin') || roleName.includes('médecin') || roleName.includes('physician');
            const field = document.getElementById('specialty_field');
            const specialtySelect = document.getElementById('specialty_select');
            field.style.display = isDoctor ? 'block' : 'none';
            specialtySelect.required = isDoctor;
        }

        function checkPasswordStrengthAdd(password) {
            let strength = 0;
            if(password.length >= 8) strength++;
            if(password.match(/[A-Z]/)) strength++;
            if(password.match(/[a-z]/)) strength++;
            if(password.match(/[0-9]/)) strength++;
            if(password.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/)) strength++;

            let indicator = document.getElementById('password-strength-add');
            if(indicator) {
                if(strength <= 2) {
                    indicator.innerHTML = '⚠️ Mot de passe faible (8 caractères, majuscule, minuscule, chiffre, caractère spécial)';
                    indicator.className = 'password-strength weak';
                } else if(strength <= 4) {
                    indicator.innerHTML = '⚡ Mot de passe moyen';
                    indicator.className = 'password-strength medium';
                } else {
                    indicator.innerHTML = '✅ Mot de passe fort';
                    indicator.className = 'password-strength strong';
                }
            }
        }
    </script>
<?php elseif(isset($_GET['action']) && $_GET['action'] == 'add' && $_GET['error'] == '4'): ?>
    <div class="modal-overlay">
        <div class="modal" style="max-width: 400px; text-align: center;">
            <div class="modal-header">
                <h2>✅ Succès !</h2>
                <a href="users.php" class="modal-close">&times;</a>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Utilisateur créé avec succès !
                </div>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <a href="users.php" class="btn btn-primary">OK</a>
            </div>
        </div>
    </div>
<?php endif; ?>

</body>
</html>