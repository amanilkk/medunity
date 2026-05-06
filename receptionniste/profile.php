<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  profile.php — Gestion du profil du réceptionniste
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$user_id = $_SESSION['user_id'] ?? 0;
$success_message = '';
$error_message = '';

// Vérifier que l'utilisateur existe
if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les informations actuelles
$stmt = $database->prepare("
    SELECT u.*, 
           DATE_FORMAT(u.created_at, '%d/%m/%Y') as member_since
    FROM users u
    WHERE u.id = ?
");
if (!$stmt) {
    $error_message = "Erreur de base de données: " . $database->error;
    $user = [];
} else {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        header('Location: ../login.php');
        exit;
    }
}

// Récupérer les dernières activités (table logs)
$recent_activities = [];
$stmt = $database->prepare("
    SELECT * FROM logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // Mise à jour des informations personnelles
        if ($_POST['action'] === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            // Vérifier si la colonne address existe
            $check_columns = $database->query("SHOW COLUMNS FROM users LIKE 'address'");
            $has_address = $check_columns && $check_columns->num_rows > 0;

            if ($full_name) {
                if ($has_address) {
                    $update = $database->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ?, address = ? 
                        WHERE id = ?
                    ");
                    if ($update) {
                        $update->bind_param('sssi', $full_name, $phone, $address, $user_id);
                    }
                } else {
                    $update = $database->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ? 
                        WHERE id = ?
                    ");
                    if ($update) {
                        $update->bind_param('ssi', $full_name, $phone, $user_id);
                    }
                }

                if ($update && $update->execute()) {
                    $success_message = "✅ Profil mis à jour avec succès";
                    // Recharger les données
                    $stmt = $database->prepare("SELECT * FROM users WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $user = $stmt->get_result()->fetch_assoc();
                    }
                } else {
                    $error_message = "❌ Erreur lors de la mise à jour";
                }
            } else {
                $error_message = "❌ Le nom complet est requis";
            }
        }

        // Changement de mot de passe
        elseif ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = "❌ Tous les champs sont requis";
            } elseif (strlen($new_password) < 6) {
                $error_message = "❌ Le nouveau mot de passe doit contenir au moins 6 caractères";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "❌ Les mots de passe ne correspondent pas";
            } else {
                // Vérifier le mot de passe actuel
                $check = $database->prepare("SELECT password FROM users WHERE id = ?");
                if ($check) {
                    $check->bind_param('i', $user_id);
                    $check->execute();
                    $current = $check->get_result()->fetch_assoc();

                    if (password_verify($current_password, $current['password'] ?? '')) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update = $database->prepare("UPDATE users SET password = ? WHERE id = ?");
                        if ($update) {
                            $update->bind_param('si', $hashed_password, $user_id);

                            if ($update->execute()) {
                                $success_message = "✅ Mot de passe modifié avec succès";
                            } else {
                                $error_message = "❌ Erreur lors du changement de mot de passe";
                            }
                        } else {
                            $error_message = "❌ Erreur de préparation de la requête";
                        }
                    } else {
                        $error_message = "❌ Mot de passe actuel incorrect";
                    }
                }
            }
        }
    }
}

// Statistiques pour le réceptionniste
$stats = [
    'total_patients' => 0
];

// Compter les patients (tous les patients)
$stmt = $database->prepare("SELECT COUNT(*) as count FROM patients");
if ($stmt) {
    $stmt->execute();
    $stats['total_patients'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

// Compter les activités aujourd'hui
$today = date('Y-m-d');
$stmt = $database->prepare("
    SELECT COUNT(*) as count FROM logs 
    WHERE user_id = ? 
    AND DATE(created_at) = ?
");
if ($stmt) {
    $stmt->bind_param('is', $user_id, $today);
    $stmt->execute();
    $stats['today_activities'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

// Calculer le temps écoulé depuis l'inscription
$member_since_date = DateTime::createFromFormat('d/m/Y', $user['member_since'] ?? date('d/m/Y'));
$now = new DateTime();
$member_days = $member_since_date ? $member_since_date->diff($now)->days : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon profil - Réceptionniste</title>
    <link rel="stylesheet" href="recept.css">
    <style>
        /* Animations et transitions */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            animation: fadeIn 0.4s ease-out;
        }

        .profile-card {
            background: var(--surface);
            border-radius: var(--r);
            border: 1px solid var(--border);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .profile-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--sh-lg);
        }

        .profile-card.hero {
            background: linear-gradient(135deg, #0F1923, #1a2a38);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .profile-card.hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        .profile-card.hero .card-head {
            border-bottom-color: rgba(255,255,255,0.1);
        }

        .profile-card .card-head {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
        }

        .profile-card .card-head h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-card .card-body {
            padding: 20px;
        }

        /* Avatar amélioré */
        .avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--green), #0F5A3E);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0 auto 15px auto;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            border: 3px solid rgba(255,255,255,0.2);
            transition: transform 0.2s ease;
        }

        .avatar-large:hover {
            transform: scale(1.05);
        }

        .role-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 15px;
            backdrop-filter: blur(4px);
        }

        /* Statistiques stylisées */
        .stats-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
            transition: background 0.2s ease;
        }

        .stat-item:hover {
            background: var(--surf2);
            padding-left: 8px;
            margin-left: -8px;
            padding-right: 8px;
            margin-right: -8px;
            border-radius: 8px;
        }

        .stat-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--green-l), #d4efe2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .stat-label {
            color: var(--text2);
            font-size: 0.8rem;
            font-weight: 500;
        }

        .stat-value {
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--green);
            font-family: 'DM Mono', monospace;
        }

        /* Timeline des activités */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--green), var(--green-l));
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        .timeline-dot {
            position: absolute;
            left: -26px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--green);
            border: 2px solid var(--surface);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .timeline-dot.create { background: #4caf50; }
        .timeline-dot.update { background: #ff9800; }
        .timeline-dot.delete { background: var(--red); }
        .timeline-dot.default { background: var(--blue); }

        .timeline-content {
            background: var(--surf2);
            border-radius: var(--rs);
            padding: 12px 15px;
            transition: transform 0.2s ease;
        }

        .timeline-content:hover {
            transform: translateX(5px);
            background: var(--green-l);
        }

        .timeline-title {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .timeline-date {
            font-size: 0.65rem;
            color: var(--text3);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .timeline-details {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed var(--border);
        }

        /* Formulaire amélioré */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text2);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--rs);
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .input:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(26,107,74,0.1);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            font-size: 1rem;
        }

        .input-icon + input {
            padding-left: 38px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: var(--rs);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green), var(--green-d));
            color: white;
            box-shadow: 0 2px 6px rgba(26,107,74,0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26,107,74,0.4);
        }

        .btn-secondary {
            background: var(--surf2);
            border: 1.5px solid var(--border);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: var(--border);
            transform: translateY(-1px);
        }

        .alert-success {
            background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
            color: #2e7d32;
            padding: 14px 18px;
            border-radius: var(--rs);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid #4caf50;
            animation: slideIn 0.3s ease;
        }

        .alert-error {
            background: linear-gradient(135deg, #FFEBEE, #FFCDD2);
            color: #c62828;
            padding: 14px 18px;
            border-radius: var(--rs);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid var(--red);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .password-requirements {
            font-size: 0.65rem;
            color: var(--text3);
            margin-top: 8px;
            padding: 8px;
            background: var(--surf2);
            border-radius: var(--rs);
        }

        /* Badges de statut */
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--blue-l);
            color: var(--blue);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Styles 2FA */
        .profile-stat-2fa {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.15);
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
            background: #4caf50;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-2fa-off {
            display: inline-block;
            margin-left: 8px;
            padding: 3px 10px;
            border-radius: 20px;
            background: #f44336;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--rs);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .profile-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Divider élégant */
        .divider {
            margin: 20px 0;
            border: none;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--border), transparent);
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">👤 Mon profil</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body">

        <?php if ($success_message): ?>
            <div class="alert-success">
                <span>✅</span>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert-error">
                <span>⚠️</span>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Carte d'identité - agrandie -->
            <div class="profile-card hero" style="grid-row: span 2;">
                <div class="card-body" style="text-align: center;">
                    <div class="avatar-large">
                        <?php echo strtoupper(substr($user['full_name'] ?? 'R', 0, 2)); ?>
                    </div>
                    <h2 style="margin: 0 0 5px 0; font-size: 1.4rem;"><?php echo htmlspecialchars($user['full_name'] ?? 'Réceptionniste'); ?></h2>
                    <div class="role-badge">
                        📋 Réceptionniste · ID #<?php echo $user_id; ?>
                    </div>
                    <div style="margin-top: 15px;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.85rem; opacity: 0.9; margin-bottom: 8px;">
                            <span>📧</span> <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.85rem; opacity: 0.9;">
                            <span>📱</span> <?php echo htmlspecialchars($user['phone'] ?? 'Non renseigné'); ?>
                        </div>
                    </div>
                    <div class="divider" style="background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent);"></div>
                    <div style="font-size: 0.75rem; opacity: 0.7;">
                        <span>📅 Membre depuis <?php echo $user['member_since'] ?? date('d/m/Y'); ?></span>
                        <?php if ($member_days > 0): ?>
                            <span> (<?php echo $member_days; ?> jours)</span>
                        <?php endif; ?>
                    </div>
                    <!-- Statut 2FA -->
                    <div class="profile-stat-2fa">
                        <div class="stat-value" style="color: white; font-size: 1rem;">
                            <?= $user['two_factor_enabled'] ? '✅ 2FA Activé' : '❌ 2FA Désactivé' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations supplémentaires -->
            <div class="profile-card">
                <div class="card-head">
                    <h3>
                        <span>📊</span> Statistiques
                    </h3>
                </div>
                <div class="card-body">
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-info">
                                <div class="stat-icon">👥</div>
                                <span class="stat-label">Total patients</span>
                            </div>
                            <div class="stat-value"><?php echo number_format($stats['total_patients'] ?? 0, 0, ',', ' '); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-info">
                                <div class="stat-icon">📅</div>
                                <span class="stat-label">Activités aujourd'hui</span>
                            </div>
                            <div class="stat-value"><?php echo $stats['today_activities']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-info">
                                <div class="stat-icon">⭐</div>
                                <span class="stat-label">Ancienneté</span>
                            </div>
                            <div class="stat-value"><?php echo $member_days; ?> jours</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire informations personnelles -->
            <div class="profile-card">
                <div class="card-head">
                    <h3>
                        <span>✏️</span> Informations personnelles
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label>Nom complet <span class="req">*</span></label>
                            <div class="input-group">
                                <input type="text" name="full_name" class="input" required
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                       placeholder="Votre nom complet">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <div class="input-group">
                                <input type="email" class="input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                            </div>
                            <small style="font-size: 0.65rem; color: var(--text3);">L'email ne peut pas être modifié</small>
                        </div>

                        <div class="form-group">
                            <label>Téléphone</label>
                            <div class="input-group">
                                <input type="tel" name="phone" class="input"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="Numéro de téléphone">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Adresse</label>
                            <textarea name="address" class="input" rows="2" placeholder="Votre adresse"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            💾 Mettre à jour
                        </button>
                    </form>
                </div>
            </div>

            <!-- Formulaire changement mot de passe -->
            <div class="profile-card">
                <div class="card-head">
                    <h3>
                        <span>🔒</span> Changer le mot de passe
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label>Mot de passe actuel</label>
                            <div class="input-group">
                                <input type="password" name="current_password" class="input" required placeholder="••••••">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Nouveau mot de passe</label>
                            <div class="input-group">
                                <input type="password" name="new_password" class="input" required placeholder="••••••">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirmer le mot de passe</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" class="input" required placeholder="••••••">
                            </div>
                        </div>

                        <div class="password-requirements">
                            <span>🔐</span> Le mot de passe doit contenir au moins 6 caractères.
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                            🔄 Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>

            <!-- Section 2FA -->
            <div class="profile-card">
                <div class="card-head">
                    <h3>
                        <span>🔐</span> Authentification à deux facteurs (2FA)
                    </h3>
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
                            <p style="font-size: 0.75rem; color: var(--text3); margin-top: 8px;">
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

            <!-- Dernières activités - version timeline -->
            <div class="profile-card" style="grid-column: span 1;">
                <div class="card-head">
                    <h3>
                        <span>📋</span> Dernières activités
                        <span class="info-badge" style="margin-left: auto;">
                            <?php echo count($recent_activities); ?> récentes
                        </span>
                    </h3>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($recent_activities)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text3);">
                            <span style="font-size: 2rem;">📭</span>
                            <p style="margin-top: 10px;">Aucune activité récente</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                                <?php
                                $dot_class = 'default';
                                if (str_contains($activity['action'] ?? '', 'create')) $dot_class = 'create';
                                elseif (str_contains($activity['action'] ?? '', 'update')) $dot_class = 'update';
                                elseif (str_contains($activity['action'] ?? '', 'delete')) $dot_class = 'delete';

                                $action_labels = [
                                    'create_patient' => '➕ Création patient',
                                    'create_appointment' => '📅 Prise de rendez-vous',
                                    'generate_ticket' => '🎟 Génération ticket',
                                    'update_appointment' => '✏️ Modification rendez-vous',
                                    'cancel_appointment' => '🚫 Annulation rendez-vous',
                                ];
                                $label = $action_labels[$activity['action'] ?? ''] ?? ($activity['action'] ?? '📝 Action');
                                ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot <?php echo $dot_class; ?>"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-title"><?php echo htmlspecialchars($label); ?></div>
                                        <div class="timeline-date">
                                            <span>🕒</span> <?php echo date('d/m/Y H:i', strtotime($activity['created_at'] ?? 'now')); ?>
                                        </div>
                                        <?php if (!empty($activity['details'])): ?>
                                            <div class="timeline-details">
                                                <?php echo htmlspecialchars(mb_substr($activity['details'], 0, 100)); ?>
                                                <?php if (mb_strlen($activity['details']) > 100): ?>...<?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>