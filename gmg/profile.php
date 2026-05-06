<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// gmg/profile.php - Profil et paramètres pour GMG (Maintenance)
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

    $check_email = $database->query("SELECT id FROM users WHERE email='$email' AND id != $user_id");
    if($check_email->num_rows > 0){
        $error_msg = "Cet email est déjà utilisé par un autre compte.";
    } else {
        $database->query("UPDATE users SET full_name='$full_name', email='$email', phone='$phone' WHERE id=$user_id");
        $_SESSION['user'] = $email;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        $success_msg = "Profil mis à jour avec succès !";
        $user_query = $database->query("SELECT u.*, r.role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.id = $user_id");
        $user = $user_query->fetch_assoc();
    }
}

// Changer le mot de passe
if(isset($_POST['change_password'])){
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

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
            $ip = $_SERVER['REMOTE_ADDR'];
            $database->query("INSERT INTO logs (user_id, action, entity_type, ip_address) VALUES ($user_id, 'CHANGE_PASSWORD', 'profile', '$ip')");
        }
    }
}

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
    <title>GMG — Mon profil</title>
    <link rel="stylesheet" href="gmg_style.css">
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
            background: linear-gradient(135deg, #1A6B4A 0%, #145C3E 100%);
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
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            transition: all 0.2s;
            outline: none;
        }
        .form-group input:focus {
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
                    <rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>
                </svg>
                <span>Chambres & Lits</span>
            </a>
            <a href="gmg_operating.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a5 5 0 0 0-5 5c0 2.5 2 4.5 5 7 3-2.5 5-4.5 5-7a5 5 0 0 0-5-5zm0 7a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/><path d="M8 21h8"/>
                </svg>
                <span>Blocs opératoires</span>
            </a>
            <a href="gmg_stock.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 7h-4.5M15 4H9M4 7h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"/>
                </svg>
                <span>Stock</span>
            </a>
            <a href="gmg_maintenance.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.5 6.5L3 14v4h4l7.5-7.5M16 8l2-2 2 2-2 2"/>
                </svg>
                <span>Maintenance</span>
            </a>
            <a href="gmg_suppliers.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="4"/><path d="M5 20v-2a7 7 0 0 1 14 0v2"/>
                </svg>
                <span>Fournisseurs</span>
            </a>
            <a href="profile.php" class="nav-item active">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                <span>Mon profil</span>
            </a>
        </nav>
        <div class="user-info">
            <div class="user-avatar">GM</div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="user-role"><?= htmlspecialchars($user['role_name']) ?></div>
                <a href="../logout.php" class="logout-link">Déconnexion</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">👤 Mon profil</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">

            <?php if($success_msg): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <?php if($error_msg): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

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

            <div class="settings-grid">
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
                            <button type="submit" name="update_profile" class="btn btn-primary">💾 Enregistrer</button>
                        </form>
                    </div>
                </div>

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
                                <label>Confirmer</label>
                                <input type="password" name="confirm_password" placeholder="••••••••" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">🔐 Changer</button>
                        </form>
                    </div>
                </div>

                <!-- ⭐ 2FA - Gestion par l'utilisateur lui-même ⭐ -->
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
                indicator.innerHTML = '⚠️ Mot de passe faible';
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