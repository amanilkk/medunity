<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Vérifier le rôle
if ($_SESSION['role'] !== 'medecin') {
    header('Location: ../login.php');
    exit;
}

// === RÉCUPÉRATION DES DONNÉES UTILISATEUR ===
$stmt = $database->prepare("
    SELECT u.*, r.role_name 
    FROM users u 
    INNER JOIN roles r ON r.id = u.role_id 
    WHERE u.id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: ../logout.php');
    exit;
}

// Récupérer les informations du médecin
$stmt = $database->prepare("
    SELECT * FROM doctors WHERE user_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$doctor_info = $stmt->get_result()->fetch_assoc();

// === TRAITEMENT MODIFICATION PROFIL ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Modification des informations personnelles
    if ($_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $specialty = trim($_POST['specialty'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');

        if (empty($full_name)) {
            $error = "Le nom est obligatoire";
        } else {
            // Mettre à jour users
            $stmt = $database->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param('sssi', $full_name, $phone, $address, $user_id);

            if ($stmt->execute()) {
                // Mettre à jour doctors si nécessaire
                if ($doctor_info && ($specialty || $license_number)) {
                    $stmt2 = $database->prepare("UPDATE doctors SET specialty = ?, license_number = ? WHERE user_id = ?");
                    $stmt2->bind_param('ssi', $specialty, $license_number, $user_id);
                    $stmt2->execute();
                }
                
                $message = "Profil mis à jour avec succès";
                $_SESSION['user_name'] = $full_name;
                $user['full_name'] = $full_name;
                $user['phone'] = $phone;
                $user['address'] = $address;
            } else {
                $error = "Erreur lors de la mise à jour";
            }
        }
    }

    // Changement de mot de passe
    if ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Tous les champs sont obligatoires";
        } elseif ($new_password !== $confirm_password) {
            $error = "Les nouveaux mots de passe ne correspondent pas";
        } elseif (strlen($new_password) < 6) {
            $error = "Le mot de passe doit contenir au moins 6 caractères";
        } else {
            $stmt = $database->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $db_password = $stmt->get_result()->fetch_assoc()['password'];

            $password_valid = false;
            if (password_verify($current_password, $db_password)) {
                $password_valid = true;
            } elseif ($current_password === $db_password) {
                $password_valid = true;
            }

            if (!$password_valid) {
                $error = "Mot de passe actuel incorrect";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $database->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param('si', $hashed_password, $user_id);

                if ($stmt->execute()) {
                    $message = "Mot de passe modifié avec succès";
                } else {
                    $error = "Erreur lors de la modification du mot de passe";
                }
            }
        }
    }
}

// === STATISTIQUES ===
// Nombre de patients vus
$stmt = $database->prepare("
    SELECT COUNT(DISTINCT patient_id) as count FROM medical_records WHERE doctor_id = ?
");
$stmt->bind_param('i', $doctor_info['id'] ?? 0);
$stmt->execute();
$patients_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Nombre de prescriptions
$stmt = $database->prepare("
    SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?
");
$stmt->bind_param('i', $doctor_info['id'] ?? 0);
$stmt->execute();
$prescriptions_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Dernières activités
$stmt = $database->prepare("
    SELECT * FROM logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mon profil - Médecin</title>
    <link rel="stylesheet" href="doctor.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 22px;
        }
        .profile-sidebar {
            text-align: center;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: white;
            margin: 0 auto 15px auto;
        }
        .profile-role {
            display: inline-block;
            padding: 4px 12px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin: 10px 0;
        }
        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        .profile-stat {
            background: var(--surface2);
            border-radius: var(--radius);
            padding: 10px;
            text-align: center;
        }
        .profile-stat .value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        .profile-stat .label {
            font-size: 0.65rem;
            color: var(--text-muted);
        }
        .profile-stat-2fa .value {
            font-size: 0.85rem;
        }
        .form-card {
            margin-bottom: 22px;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-note {
            background: var(--info-light);
            border-left: 4px solid var(--info);
            padding: 12px;
            margin-top: 15px;
            border-radius: var(--radius);
            font-size: 0.75rem;
            color: var(--info);
        }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            width: 32px;
            height: 32px;
            background: var(--surface2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        .activity-detail {
            flex: 1;
        }
        .activity-action {
            font-weight: 600;
            font-size: 0.85rem;
        }
        .activity-date {
            font-size: 0.65rem;
            color: var(--text-muted);
        }
        .twofa-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .badge-2fa-on {
            display: inline-block;
            margin-left: 8px;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--success-light);
            color: var(--success);
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-2fa-off {
            display: inline-block;
            margin-left: 8px;
            padding: 3px 10px;
            border-radius: 20px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include 'doctor_menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Mon profil</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body">

        <?php if ($message): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Colonne gauche : Avatar et infos -->
            <div class="profile-sidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        <div class="profile-role">👨‍⚕️ <?php echo htmlspecialchars($user['role_name']); ?></div>
                        <p style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($user['email']); ?></p>

                        <?php if ($doctor_info): ?>
                            <div style="margin-top: 10px;">
                                <div class="info-badge"><?php echo htmlspecialchars($doctor_info['specialty'] ?? 'Spécialité non définie'); ?></div>
                                <?php if ($doctor_info['license_number']): ?>
                                    <div class="text-muted text-small" style="margin-top: 5px;">Licence: <?php echo htmlspecialchars($doctor_info['license_number']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="profile-stats">
                            <div class="profile-stat">
                                <div class="value"><?php echo $patients_count; ?></div>
                                <div class="label">Patients vus</div>
                            </div>
                            <div class="profile-stat">
                                <div class="value"><?php echo $prescriptions_count; ?></div>
                                <div class="label">Ordonnances</div>
                            </div>
                            <div class="profile-stat">
                                <div class="value"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                                <div class="label">Membre depuis</div>
                            </div>
                            <div class="profile-stat profile-stat-2fa">
                                <div class="value"><?= $user['two_factor_enabled'] ? '✅ Activé' : '❌ Désactivé' ?></div>
                                <div class="label">2FA</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonne droite : Formulaires -->
            <div>
                <!-- Modification du profil -->
                <div class="card form-card">
                    <div class="card-head">
                        <h3>📝 Informations personnelles</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nom complet *</label>
                                    <input type="text" name="full_name" class="input" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" class="input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                    <small style="font-size: 0.65rem; color: var(--text-muted);">L'email ne peut pas être modifié</small>
                                </div>
                                <div class="form-group">
                                    <label>Téléphone</label>
                                    <input type="tel" name="phone" class="input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Adresse</label>
                                    <textarea name="address" class="input" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Spécialité</label>
                                    <input type="text" name="specialty" class="input" value="<?php echo htmlspecialchars($doctor_info['specialty'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Numéro de licence</label>
                                    <input type="text" name="license_number" class="input" value="<?php echo htmlspecialchars($doctor_info['license_number'] ?? ''); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Mettre à jour</button>
                        </form>
                    </div>
                </div>

                <!-- Changement de mot de passe -->
                <div class="card form-card">
                    <div class="card-head">
                        <h3>🔒 Changer le mot de passe</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Mot de passe actuel *</label>
                                    <input type="password" name="current_password" class="input" required>
                                </div>
                                <div class="form-group">
                                    <label>Nouveau mot de passe *</label>
                                    <input type="password" name="new_password" class="input" required>
                                </div>
                                <div class="form-group">
                                    <label>Confirmer le nouveau mot de passe *</label>
                                    <input type="password" name="confirm_password" class="input" required>
                                </div>
                            </div>

                            <div class="info-note">
                                🔐 Le mot de passe doit contenir au moins 6 caractères.
                            </div>

                            <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                        </form>
                    </div>
                </div>

                <!-- 2FA SECTION -->
                <div class="card form-card">
                    <div class="card-head">
                        <h3>🔐 Authentification à deux facteurs (2FA)</h3>
                    </div>
                    <div class="card-body">
                        <div class="twofa-row">
                            <div>
                                <strong>Statut actuel :</strong>
                                <?php if($user['two_factor_enabled']): ?>
                                    <span class="badge-2fa-on">✅ Activé</span>
                                <?php else: ?>
                                    <span class="badge-2fa-off">❌ Désactivé</span>
                                <?php endif; ?>
                                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px;">
                                    La 2FA ajoute une couche de sécurité supplémentaire.
                                    Un code à usage unique vous sera demandé à chaque connexion.
                                </p>
                            </div>
                            <a href="2fa-setup.php" class="btn btn-primary">
                                <?= $user['two_factor_enabled'] ? '⚙️ Gérer la 2FA' : '🔒 Activer la 2FA' ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Dernières activités -->
                <div class="card">
                    <div class="card-head">
                        <h3>📋 Dernières activités</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activities)): ?>
                            <div class="empty" style="padding: 20px;">
                                <p>Aucune activité récente</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                        $icon = '📌';
                                        if (strpos($activity['action'], 'CREATE') !== false) $icon = '➕';
                                        elseif (strpos($activity['action'], 'UPDATE') !== false) $icon = '✏️';
                                        elseif (strpos($activity['action'], 'DELETE') !== false) $icon = '🗑️';
                                        elseif (strpos($activity['action'], 'LOGIN') !== false) $icon = '🔐';
                                        echo $icon;
                                        ?>
                                    </div>
                                    <div class="activity-detail">
                                        <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
                                        <div class="activity-date"><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
</body>
</html>