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
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt3 = $database->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt3->bind_param("si", $hashed, $doctor['user_id']);
            $stmt3->execute();
            $success .= " Le mot de passe a également été modifié.";
        }
        // Rafraîchir les données locales
        $doctor = getCurrentDoctor($database);
    } else {
        $error = "Erreur lors de la mise à jour.";
    }
}
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
                                <span class="text-small text-muted">Utilisez au moins 8 caractères avec des chiffres.</span>
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
        lucide.createIcons();
    </script>
</body>
</html>