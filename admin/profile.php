<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// admin/profile.php - Profil et paramètres de l'administrateur
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

// ⭐ CORRECTION : Chemin correct vers connection.php ⭐
include("../connection.php");
date_default_timezone_set('Africa/Algiers');

$user_id = $_SESSION['uid'] ?? $_SESSION['user_id'] ?? 0;
$success_msg = '';
$error_msg = '';

// Vérifier que l'utilisateur existe
if($user_id == 0){
    header("location: ../login.php");
    exit();
}

// Récupérer les informations de l'utilisateur
$user_query = $database->query("SELECT u.*, r.role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.id = $user_id");
if(!$user_query || $user_query->num_rows == 0){
    header("location: ../login.php");
    exit();
}
$user = $user_query->fetch_assoc();

// Mettre à jour le profil
if(isset($_POST['update_profile'])){
    $full_name = $database->real_escape_string($_POST['full_name']);
    $email = $database->real_escape_string($_POST['email']);
    $phone = $database->real_escape_string($_POST['phone']);

    // Vérifier si l'email existe déjà pour un autre utilisateur
    $check_email = $database->query("SELECT id FROM users WHERE email='$email' AND id != $user_id");
    if($check_email->num_rows > 0){
        $error_msg = "Cet email est déjà utilisé par un autre compte.";
    } else {
        $database->query("UPDATE users SET full_name='$full_name', email='$email', phone='$phone' WHERE id=$user_id");
        $_SESSION['user'] = $email;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        $success_msg = "Profil mis à jour avec succès !";

        // Recharger les données
        $user_query = $database->query("SELECT u.*, r.role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.id = $user_id");
        $user = $user_query->fetch_assoc();
    }
}

// Changer le mot de passe
if(isset($_POST['change_password'])){
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifier le mot de passe actuel
    if(!password_verify($current_password, $user['password'])){
        $error_msg = "Mot de passe actuel incorrect.";
    }
    elseif($new_password !== $confirm_password){
        $error_msg = "Les nouveaux mots de passe ne correspondent pas.";
    }
    else {
        $complexity_errors = [];
        if(strlen($new_password) < 8) $complexity_errors[] = "8 caractères minimum";
        if(!preg_match('/[A-Z]/', $new_password)) $complexity_errors[] = "une majuscule";
        if(!preg_match('/[a-z]/', $new_password)) $complexity_errors[] = "une minuscule";
        if(!preg_match('/[0-9]/', $new_password)) $complexity_errors[] = "un chiffre";
        if(!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $new_password)) $complexity_errors[] = "un caractère spécial";

        if(!empty($complexity_errors)){
            $error_msg = "Mot de passe faible : " . implode(", ", $complexity_errors);
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $database->query("UPDATE users SET password='$hashed_password' WHERE id=$user_id");
            $success_msg = "Mot de passe modifié avec succès !";

            // Journaliser le changement
            $ip = $_SERVER['REMOTE_ADDR'];
            $database->query("INSERT INTO logs (user_id, action, entity_type, ip_address) VALUES ($user_id, 'CHANGE_PASSWORD', 'profile', '$ip')");
        }
    }
}

// Récupérer la date de dernière connexion formatée
$last_login_display = 'Jamais';
if(!empty($user['last_login']) && $user['last_login'] != '0000-00-00 00:00:00'){
    $last_login_display = date('d/m/Y H:i', strtotime($user['last_login']));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Mon profil</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
            padding: 24px;
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
            color: white;
        }
        .profile-info h2 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .profile-info p {
            color: #64748b;
            font-size: 0.85rem;
        }
        .profile-stats {
            display: flex;
            gap: 24px;
            margin-top: 16px;
        }
        .profile-stat {
            text-align: center;
        }
        .profile-stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #10b981;
        }
        .profile-stat-label {
            font-size: 0.7rem;
            color: #64748b;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 24px;
        }
        .setting-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .setting-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #fafbfc;
        }
        .setting-card-header h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        .setting-card-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            transition: all 0.2s;
            outline: none;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.1);
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
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .mt-4 {
            margin-top: 16px;
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
            <a href="profile.php" class="nav-item active">
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
                <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="user-role"><?= htmlspecialchars($user['role_name']) ?></div>
                <a href="../logout.php" class="logout-link">Déconnexion</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">👤 Mon profil</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <!-- Messages -->
            <?php if($success_msg): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <?php if($error_msg): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['full_name'], 0, 2)) ?>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($user['full_name']) ?></h2>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?= htmlspecialchars($user['role_name']) ?></div>
                            <div class="profile-stat-label">Rôle</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?= $last_login_display ?></div>
                            <div class="profile-stat-label">Dernière connexion</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?= $user['two_factor_enabled'] ? 'Activé' : 'Désactivé' ?></div>
                            <div class="profile-stat-label">2FA</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Grid -->
            <div class="settings-grid">
                <!-- Informations personnelles -->
                <div class="setting-card">
                    <div class="setting-card-header">
                        <h3>📝 Informations personnelles</h3>
                    </div>
                    <div class="setting-card-body">
                        <form method="POST" action="profile.php">
                            <div class="form-group">
                                <label>Nom complet</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Téléphone</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">💾 Enregistrer les modifications</button>
                        </form>
                    </div>
                </div>

                <!-- Changer le mot de passe -->
                <div class="setting-card">
                    <div class="setting-card-header">
                        <h3>🔒 Changer le mot de passe</h3>
                    </div>
                    <div class="setting-card-body">
                        <form method="POST" action="profile.php">
                            <div class="form-group">
                                <label>Mot de passe actuel</label>
                                <input type="password" name="current_password" placeholder="••••••••" required>
                            </div>
                            <div class="form-group">
                                <label>Nouveau mot de passe</label>
                                <input type="password" name="new_password" id="new_password" placeholder="••••••••" required
                                       onkeyup="checkPasswordStrength(this.value)">
                                <div id="password-strength" class="password-strength"></div>
                            </div>
                            <div class="form-group">
                                <label>Confirmer le nouveau mot de passe</label>
                                <input type="password" name="confirm_password" placeholder="••••••••" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">🔐 Changer le mot de passe</button>
                        </form>
                    </div>
                </div>

                <!-- Authentification à deux facteurs -->
                <div class="setting-card">
                    <div class="setting-card-header">
                        <h3>🔐 Authentification à deux facteurs (2FA)</h3>
                    </div>
                    <div class="setting-card-body">
                        <div class="flex-between">
                            <div>
                                <strong>Statut actuel :</strong>
                                <?php if($user['two_factor_enabled']): ?>
                                    <span class="badge badge-active" style="margin-left: 8px;">✅ Activé</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive" style="margin-left: 8px;">❌ Désactivé</span>
                                <?php endif; ?>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 8px;">
                                    La 2FA ajoute une couche de sécurité supplémentaire.
                                    Un code à usage unique vous sera demandé à chaque connexion.
                                </p>
                            </div>
                            <a href="2fa-setup.php" class="btn btn-primary btn-sm">
                                <?= $user['two_factor_enabled'] ? '⚙️ Gérer la 2FA' : '🔒 Activer la 2FA' ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Activité récente -->
                <div class="setting-card">
                    <div class="setting-card-header">
                        <h3>📊 Activité récente</h3>
                    </div>
                    <div class="setting-card-body">
                        <?php
                        $recent_logs = $database->query("SELECT action, created_at, ip_address FROM logs WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5");
                        ?>
                        <?php if($recent_logs && $recent_logs->num_rows > 0): ?>
                            <ul style="list-style: none; padding: 0;">
                                <?php while($log = $recent_logs->fetch_assoc()): ?>
                                    <li style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.8rem;">
                                        <strong><?= htmlspecialchars($log['action']) ?></strong><br>
                                        <span style="font-size: 0.7rem; color: #64748b;"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?> • IP: <?= htmlspecialchars($log['ip_address']) ?></span>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center" style="color: #64748b;">Aucune activité récente</p>
                        <?php endif; ?>
                        <div class="mt-4 text-center">
                            <a href="security.php" class="btn btn-secondary btn-sm">Voir tous les logs</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function checkPasswordStrength(password) {
        let strength = 0;
        if(password.length >= 8) strength++;
        if(password.match(/[A-Z]/)) strength++;
        if(password.match(/[a-z]/)) strength++;
        if(password.match(/[0-9]/)) strength++;
        if(password.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/)) strength++;

        let indicator = document.getElementById('password-strength');
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

</body>
</html>