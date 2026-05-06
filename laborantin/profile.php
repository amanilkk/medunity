<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  profile.php — Gestion du profil du laborantin
// ================================================================

require_once 'functions.php';
requireLaborantin();
include '../connection.php';

$user_id = getCurrentLaborantinId();
$success_message = '';
$error_message = '';

// Récupérer les informations actuelles
$stmt = $database->prepare("
    SELECT u.*, 
           DATE_FORMAT(u.created_at, '%d/%m/%Y') as member_since
    FROM users u
    WHERE u.id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Récupérer les dernières activités
$stmt = $database->prepare("
    SELECT * FROM logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Compter les actions par type
$stmt = $database->prepare("
    SELECT action, COUNT(*) as count 
    FROM logs 
    WHERE user_id = ? 
    GROUP BY action
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$action_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // Mise à jour des informations personnelles
        if ($_POST['action'] === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($full_name) {
                $update = $database->prepare("
                    UPDATE users 
                    SET full_name = ?, phone = ?, address = ? 
                    WHERE id = ?
                ");
                $update->bind_param('sssi', $full_name, $phone, $address, $user_id);

                if ($update->execute()) {
                    $success_message = "✅ Profil mis à jour avec succès";
                    // Recharger les données
                    $stmt = $database->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
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
                $check->bind_param('i', $user_id);
                $check->execute();
                $current = $check->get_result()->fetch_assoc();

                if (password_verify($current_password, $current['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $database->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->bind_param('si', $hashed_password, $user_id);

                    if ($update->execute()) {
                        $success_message = "✅ Mot de passe modifié avec succès";
                    } else {
                        $error_message = "❌ Erreur lors du changement de mot de passe";
                    }
                } else {
                    $error_message = "❌ Mot de passe actuel incorrect";
                }
            }
        }
    }
}

// Statistiques supplémentaires
$stats = [
    'tests_created' => 0,
    'results_added' => 0,
    'stock_movements' => 0
];

$stmt = $database->prepare("
    SELECT COUNT(*) as count FROM lab_tests WHERE performed_by = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stats['results_added'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

$stmt = $database->prepare("
    SELECT COUNT(*) as count FROM lab_stock_movements WHERE performed_by = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stats['stock_movements'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Récupérer le nombre total d'analyses créées (via logs)
$stmt = $database->prepare("
    SELECT COUNT(*) as count FROM logs 
    WHERE user_id = ? AND action IN ('create_test', 'create_multiple_tests')
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stats['tests_created'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon profil - Laborantin</title>
    <link rel="stylesheet" href="../receptionniste/recept.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .profile-card {
            background: var(--surface);
            border-radius: var(--r);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .profile-card.hero {
            background: linear-gradient(135deg, var(--green), #1a5d7a);
            color: white;
        }

        .profile-card.hero .card-head {
            border-bottom-color: rgba(255,255,255,0.2);
        }

        .profile-card .card-head {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
        }

        .profile-card .card-head h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .profile-card .card-body {
            padding: 20px;
        }

        .avatar-large {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .role-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-bottom: 15px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: var(--text2);
            font-size: 0.8rem;
        }

        .stat-value {
            font-weight: 700;
            font-size: 1rem;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            background: var(--surf2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .activity-details {
            flex: 1;
        }

        .activity-action {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .activity-date {
            font-size: 0.7rem;
            color: var(--text3);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 5px;
        }

        .input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--rs);
            font-size: 0.9rem;
        }

        .input:focus {
            outline: none;
            border-color: var(--green);
        }

        .btn {
            padding: 10px 20px;
            border-radius: var(--rs);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--green);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: #1a5d7a;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--surf2);
            border: 1px solid var(--border);
            color: var(--text);
        }

        .alert-success {
            background: #E8F5E9;
            color: #2e7d32;
            padding: 12px 15px;
            border-radius: var(--rs);
            margin-bottom: 20px;
        }

        .alert-error {
            background: #FFEBEE;
            color: #c62828;
            padding: 12px 15px;
            border-radius: var(--rs);
            margin-bottom: 20px;
        }

        .password-requirements {
            font-size: 0.7rem;
            color: var(--text3);
            margin-top: 5px;
        }

        .full-width {
            grid-column: span 2;
        }

        /* Styles 2FA */
        .profile-stat-2fa {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.2);
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

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
<?php include 'lab_menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">👤 Mon profil</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body">

        <?php if ($success_message): ?>
            <div class="alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Carte d'identité -->
            <div class="profile-card hero">
                <div class="card-body" style="text-align: center;">
                    <div class="avatar-large" style="margin: 0 auto 15px auto;">
                        <?php echo strtoupper(substr($user['full_name'] ?? 'L', 0, 2)); ?>
                    </div>
                    <h2 style="margin: 0 0 5px 0; font-size: 1.3rem;"><?php echo htmlspecialchars($user['full_name'] ?? 'Laborantin'); ?></h2>
                    <div class="role-badge">🔬 Laborantin</div>
                    <div style="font-size: 0.8rem; opacity: 0.9;">
                        📧 <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                    </div>
                    <!-- Statut 2FA -->
                    <div class="profile-stat-2fa">
                        <div class="stat-value" style="color: white;">
                            <?= $user['two_factor_enabled'] ? '✅ Activé' : '❌ Désactivé' ?>
                        </div>
                        <div class="stat-label" style="color: rgba(255,255,255,0.7);">2FA</div>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="profile-card">
                <div class="card-head">
                    <h3>📊 Mon activité</h3>
                </div>
                <div class="card-body">
                    <div class="stat-row">
                        <span class="stat-label">🔬 Analyses créées</span>
                        <span class="stat-value"><?php echo $stats['tests_created']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">✅ Résultats saisis</span>
                        <span class="stat-value"><?php echo $stats['results_added']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">📦 Mouvements de stock</span>
                        <span class="stat-value"><?php echo $stats['stock_movements']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">📅 Membre depuis</span>
                        <span class="stat-value"><?php echo $user['member_since'] ?? date('d/m/Y'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Formulaire informations personnelles -->
            <div class="profile-card">
                <div class="card-head">
                    <h3>✏️ Informations personnelles</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label>Nom complet *</label>
                            <input type="text" name="full_name" class="input" required
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                            <small style="font-size: 0.7rem; color: var(--text3);">L'email ne peut pas être modifié</small>
                        </div>

                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="phone" class="input"
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Adresse</label>
                            <textarea name="address" class="input" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">💾 Mettre à jour</button>
                    </form>
                </div>
            </div>

            <!-- Formulaire changement mot de passe -->
            <div class="profile-card">
                <div class="card-head">
                    <h3>🔒 Changer le mot de passe</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">

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

                        <div class="password-requirements">
                            🔐 Le mot de passe doit contenir au moins 6 caractères.
                        </div>

                        <button type="submit" class="btn btn-primary">🔄 Changer le mot de passe</button>
                    </form>
                </div>
            </div>

            <!-- Section 2FA -->
            <div class="profile-card">
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

            <!-- Dernières activités -->
            <div class="profile-card full-width">
                <div class="card-head">
                    <h3>📋 Dernières activités</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activities)): ?>
                        <div style="text-align: center; padding: 30px; color: var(--text3);">
                            Aucune activité récente
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php
                                    $icon = '📝';
                                    if (str_contains($activity['action'], 'create')) $icon = '➕';
                                    elseif (str_contains($activity['action'], 'update')) $icon = '✏️';
                                    elseif (str_contains($activity['action'], 'delete')) $icon = '🗑️';
                                    elseif (str_contains($activity['action'], 'stock')) $icon = '📦';
                                    elseif (str_contains($activity['action'], 'result')) $icon = '🔬';
                                    elseif (str_contains($activity['action'], 'email')) $icon = '📧';
                                    echo $icon;
                                    ?>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-action">
                                        <?php
                                        $action_labels = [
                                            'create_test' => 'Création d\'analyse',
                                            'create_multiple_tests' => 'Création multiple d\'analyses',
                                            'update_status' => 'Changement de statut',
                                            'add_result' => 'Ajout de résultat',
                                            'update_stock' => 'Mise à jour stock',
                                            'add_stock_item' => 'Ajout consommable',
                                            'request_stock' => 'Demande réappro',
                                            'cancel_test' => 'Annulation analyse',
                                            'email_sent' => 'Email envoyé',
                                        ];
                                        $label = $action_labels[$activity['action']] ?? $activity['action'];
                                        echo htmlspecialchars($label);
                                        ?>
                                    </div>
                                    <div class="activity-date">
                                        <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                    </div>
                                    <?php if ($activity['details']): ?>
                                        <div style="font-size: 0.7rem; color: var(--text3); margin-top: 4px;">
                                            <?php echo htmlspecialchars($activity['details']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>