<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
require_once 'doctor_functions.php';
requireDoctor();
include '../connection.php';

$doctor = getCurrentDoctor($database);
$doctor_id = $doctor['doctor_id'];
$success = $error = '';

// Traitement de la mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $room = trim($_POST['room_number']);
    $experience = (int)$_POST['experience_years'];
    $new_password = $_POST['new_password'];

    // Mise à jour des infos de base
    $stmt1 = $database->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt1->bind_param("ssi", $full_name, $phone, $doctor['user_id']);
    
    $stmt2 = $database->prepare("UPDATE doctors SET room_number = ?, experience_years = ? WHERE id = ?");
    $stmt2->bind_param("sii", $room, $experience, $doctor_id);

    if ($stmt1->execute() && $stmt2->execute()) {
        $success = "Paramètres mis à jour avec succès.";
        // Mise à jour du mot de passe si rempli
        if (!empty($new_password)) {
            if (strlen($new_password) >= 6) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt3 = $database->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt3->bind_param("si", $hashed, $doctor['user_id']);
                $stmt3->execute();
                $success .= " Le mot de passe a également été modifié.";
            } else {
                $error = "Le mot de passe doit contenir au moins 6 caractères.";
            }
        }
        // Rafraîchir les données locales
        if (empty($error)) {
            $doctor = getCurrentDoctor($database);
        }
    } else {
        $error = "Erreur lors de la mise à jour.";
    }
}

// Récupérer le statut 2FA
$stmt = $database->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
$stmt->bind_param("i", $doctor['user_id']);
$stmt->execute();
$user_2fa = $stmt->get_result()->fetch_assoc();
$two_factor_enabled = $user_2fa['two_factor_enabled'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètres — Dr. <?= htmlspecialchars($doctor['full_name']) ?></title>
    <link rel="stylesheet" href="doctor.css">
    <style>
        .settings-grid { display: grid; grid-template-columns: 280px 1fr; gap: 30px; margin-top: 20px; }
        .settings-nav { background: white; border-radius: 12px; padding: 10px; border: 1px solid var(--border); height: fit-content; }
        .s-nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; color: var(--text-light); text-decoration: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .s-nav-item:hover { background: var(--bg-main); color: var(--primary); }
        .s-nav-item.active { background: var(--primary-light); color: var(--primary); }
        
        .settings-content { display: none; animation: fadeIn 0.3s; }
        .settings-content.active { display: block; }
        
        /* Styles 2FA */
        .twofa-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 15px 0;
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
        .info-note-2fa {
            background: var(--info-light);
            border-left: 4px solid var(--info);
            padding: 12px;
            margin-top: 15px;
            border-radius: var(--radius);
            font-size: 0.75rem;
            color: var(--info);
        }
        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .btn-secondary {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">⚙️ Paramètres du compte</span>
        </div>

        <div class="page-body">
            <?php if($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>
            <?php if($error): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>

            <div class="settings-grid">
                <div class="settings-nav">
                    <div class="s-nav-item active" onclick="tab('profil', this)"><i data-lucide="user"></i> Profil Personnel</div>
                    <div class="s-nav-item" onclick="tab('cabinet', this)"><i data-lucide="briefcase"></i> Cabinet & Expérience</div>
                    <div class="s-nav-item" onclick="tab('securite', this)"><i data-lucide="shield-check"></i> Sécurité</div>
                    <div class="s-nav-item" onclick="tab('twofa', this)"><i data-lucide="key"></i> Authentification 2FA</div>
                </div>

                <div class="card">
                    <form method="POST" class="card-body">
                        
                        <div id="profil" class="settings-content active">
                            <h3 style="margin-bottom: 20px;">Mon Profil</h3>
                            <div class="form-group">
                                <label>Nom complet</label>
                                <input type="text" name="full_name" class="input" value="<?= htmlspecialchars($doctor['full_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email (Non modifiable)</label>
                                <input type="email" class="input" value="<?= htmlspecialchars($doctor['email']) ?>" disabled style="background:#f1f5f9">
                            </div>
                            <div class="form-group">
                                <label>Numéro de téléphone</label>
                                <input type="text" name="phone" class="input" value="<?= htmlspecialchars($doctor['phone']) ?>">
                            </div>
                        </div>

                        <div id="cabinet" class="settings-content">
                            <h3 style="margin-bottom: 20px;">Informations Professionnelles</h3>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px">
                                <div class="form-group">
                                    <label>Numéro de bureau / Salle</label>
                                    <input type="text" name="room_number" class="input" value="<?= htmlspecialchars($doctor['room_number']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Années d'expérience</label>
                                    <input type="number" name="experience_years" class="input" value="<?= $doctor['experience_years'] ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Département / Spécialité</label>
                                <input type="text" class="input" value="<?= htmlspecialchars($doctor['department']) ?>" disabled style="background:#f1f5f9">
                                <span class="text-small text-muted">Contactez l'administrateur pour changer de département.</span>
                            </div>
                        </div>

                        <div id="securite" class="settings-content">
                            <h3 style="margin-bottom: 20px;">Sécurité du compte</h3>
                            <div class="form-group">
                                <label>Nouveau mot de passe</label>
                                <input type="password" name="new_password" class="input" placeholder="Laisser vide pour ne pas changer">
                                <span class="text-small text-muted">Utilisez au moins 6 caractères.</span>
                            </div>
                        </div>

                        <div id="twofa" class="settings-content">
                            <h3 style="margin-bottom: 20px;">🔐 Authentification à deux facteurs (2FA)</h3>
                            <div class="twofa-row">
                                <div>
                                    <strong>Statut actuel :</strong>
                                    <?php if($two_factor_enabled): ?>
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
                                    <?= $two_factor_enabled ? '⚙️ Gérer la 2FA' : '🔒 Activer la 2FA' ?>
                                </a>
                            </div>
                            <div class="info-note-2fa">
                                💡 <strong>Recommandé :</strong> Activez la 2FA pour protéger votre compte médical
                                et les données sensibles de vos patients.
                            </div>
                        </div>

                        <div style="margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px;">
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function tab(id, btn) {
            // Cacher tous les contenus
            document.querySelectorAll('.settings-content').forEach(c => c.classList.remove('active'));
            // Retirer l'état actif des boutons
            document.querySelectorAll('.s-nav-item').forEach(b => b.classList.remove('active'));
            
            // Afficher le bon contenu
            document.getElementById(id).classList.add('active');
            btn.classList.add('active');
        }
        
        // Initialiser Lucide icons si disponible
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>