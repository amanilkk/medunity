<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

// Vérifier que l'utilisateur est RH
if ($_SESSION['role'] !== 'gestionnaire_rh') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';
$contract_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// === RÉCUPÉRATION DES DONNÉES POUR MODIFICATION ===
$contract_data = null;
$employee_data = null;

if ($contract_id) {
    // Modifier un contrat existant
    $stmt = $database->prepare("
        SELECT ec.*, u.full_name, u.email, u.phone, u.role_id, r.role_name
        FROM employee_contracts ec
        INNER JOIN users u ON u.id = ec.user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE ec.id = ?
    ");
    $stmt->bind_param('i', $contract_id);
    $stmt->execute();
    $contract_data = $stmt->get_result()->fetch_assoc();
    $user_id = $contract_data['user_id'];
}

if ($user_id && !$contract_id) {
    // Nouveau contrat pour un employé existant
    $stmt = $database->prepare("
        SELECT u.*, r.role_name 
        FROM users u 
        INNER JOIN roles r ON r.id = u.role_id 
        WHERE u.id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $employee_data = $stmt->get_result()->fetch_assoc();
}

// Liste des employés sans contrat actif (pour nouveau contrat)
$employees_without_contract = [];
if (!$user_id && !$contract_id) {
    $stmt = $database->prepare("
        SELECT u.id, u.full_name, u.email, u.phone, r.role_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.role_id NOT IN (1, 3) 
        AND u.is_active = 1
        AND u.id NOT IN (
            SELECT user_id FROM employee_contracts 
            WHERE end_date IS NULL OR end_date >= CURDATE()
        )
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $employees_without_contract = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// === TRAITEMENT FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_contract') {
        $user_id = intval($_POST['user_id']);
        $contract_type = $_POST['contract_type'];
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $salary = floatval($_POST['salary']);
        $position = $_POST['position'];
        $department = $_POST['department'];
        $existing_contract_id = !empty($_POST['contract_id']) ? intval($_POST['contract_id']) : null;

        // Validation
        if (empty($user_id) || empty($contract_type) || empty($start_date) || empty($salary) || empty($position)) {
            $error = "Veuillez remplir tous les champs obligatoires";
        } elseif ($end_date && $end_date < $start_date) {
            $error = "La date de fin doit être postérieure à la date de début";
        } else {
            $result = saveContract($database, $user_id, $contract_type, $start_date, $salary, $position, $department, $end_date, $existing_contract_id);

            if ($result['success']) {
                $message = $result['message'];
                // Redirection après 2 secondes
                header("refresh:2;url=employees.php");
            } else {
                $error = $result['message'];
            }
        }
    }

    if ($_POST['action'] === 'create_user') {
        // Créer un nouvel utilisateur (employé)
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $phone = $_POST['phone'];
        $role_id = intval($_POST['role_id']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Vérifier si l'email existe déjà
        $stmt = $database->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Cet email est déjà utilisé";
        } else {
            $stmt = $database->prepare("
                INSERT INTO users (email, password, full_name, role_id, phone, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->bind_param('sssis', $email, $password, $full_name, $role_id, $phone);

            if ($stmt->execute()) {
                $new_user_id = $database->insert_id;
                $message = "Utilisateur créé avec succès. Vous pouvez maintenant ajouter son contrat.";
                // Redirection vers le formulaire de contrat pour ce nouvel employé
                header("refresh:2;url=contract-form.php?user_id=" . $new_user_id);
            } else {
                $error = "Erreur lors de la création: " . $stmt->error;
            }
        }
    }
}

// Récupérer les rôles disponibles (employés uniquement)
$roles = [];
$stmt = $database->prepare("
    SELECT id, role_name FROM roles 
    WHERE id NOT IN (1, 3) 
    ORDER BY role_name
");
$stmt->execute();
$roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Types de contrat
$contract_types = [
    'CDI' => 'CDI (Contrat à Durée Indéterminée)',
    'CDD' => 'CDD (Contrat à Durée Déterminée)',
    'stage' => 'Stage',
    'freelance' => 'Freelance / Indépendant'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $contract_id ? 'Modifier' : 'Nouveau'; ?> Contrat — RH</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 20px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--green);
            color: var(--green);
        }
        .employee-info {
            background: var(--green-l);
            border-radius: var(--r);
            padding: 15px;
            margin-bottom: 20px;
        }
        .employee-info p {
            margin: 5px 0;
        }
        .two-columns-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .toggle-buttons {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }
        .toggle-btn {
            flex: 1;
            padding: 8px;
            text-align: center;
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--rs);
            cursor: pointer;
            transition: all 0.15s;
        }
        .toggle-btn.active {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }
        .info-note {
            background: var(--blue-l);
            border-left: 4px solid var(--blue);
            padding: 12px;
            margin-top: 20px;
            border-radius: var(--rs);
            font-size: 0.75rem;
            color: var(--blue);
        }
        .btn-back {
            margin-right: 10px;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">
            <?php
            if ($contract_id) echo "✏️ Modifier le contrat";
            elseif ($user_id) echo "📝 Nouveau contrat pour " . htmlspecialchars($employee_data['full_name'] ?? '');
            else echo "➕ Nouvel employé / Contrat";
            ?>
        </span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="employees.php" class="btn btn-secondary btn-sm">
                ← Retour aux employés
            </a>
        </div>
    </div>
    <div class="page-body">
        <div class="form-container">

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Formulaire de création d'employé (si aucun employé sélectionné) -->
            <?php if (!$user_id && !$contract_id): ?>
                <div class="card">
                    <div class="card-head">
                        <h3>👤 Créer un nouvel employé</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_user">

                            <div class="two-columns-form">
                                <div class="form-group">
                                    <label>Nom complet *</label>
                                    <input type="text" name="full_name" class="input" required placeholder="Jean Dupont">
                                </div>
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" class="input" required placeholder="jean@clinique.com">
                                </div>
                                <div class="form-group">
                                    <label>Téléphone</label>
                                    <input type="tel" name="phone" class="input" placeholder="05 XX XX XX XX">
                                </div>
                                <div class="form-group">
                                    <label>Mot de passe *</label>
                                    <input type="password" name="password" class="input" required placeholder="••••••••">
                                </div>
                                <div class="form-group">
                                    <label>Rôle *</label>
                                    <select name="role_id" class="input" required>
                                        <option value="">-- Sélectionner un rôle --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="info-note">
                                ℹ️ Après avoir créé l'employé, vous pourrez ajouter son contrat (salaire, type de contrat, etc.)
                            </div>

                            <button type="submit" class="btn btn-primary">Créer l'employé</button>
                        </form>
                    </div>
                </div>

                <!-- Ou sélectionner un employé existant sans contrat -->
                <?php if (!empty($employees_without_contract)): ?>
                    <div class="card">
                        <div class="card-head">
                            <h3>📋 Ou sélectionner un employé existant</h3>
                        </div>
                        <div class="card-body">
                            <div class="employee-list">
                                <?php foreach ($employees_without_contract as $emp): ?>
                                    <div class="employee-item" style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid var(--border);">
                                        <div>
                                            <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($emp['email']); ?> | <?php echo htmlspecialchars($emp['role_name']); ?></small>
                                        </div>
                                        <a href="contract-form.php?user_id=<?php echo $emp['id']; ?>" class="btn btn-blue btn-sm">Ajouter un contrat</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Formulaire de contrat (si employé sélectionné) -->
            <?php if (($user_id && $employee_data) || $contract_data):
                $current_employee = $contract_data ?? $employee_data;
                ?>
                <div class="card">
                    <div class="card-head">
                        <h3>📄 <?php echo $contract_id ? 'Modification du contrat' : 'Nouveau contrat'; ?></h3>
                    </div>
                    <div class="card-body">
                        <!-- Informations employé -->
                        <div class="employee-info">
                            <p><strong>👤 Employé :</strong> <?php echo htmlspecialchars($current_employee['full_name']); ?></p>
                            <p><strong>📧 Email :</strong> <?php echo htmlspecialchars($current_employee['email']); ?></p>
                            <p><strong>📱 Téléphone :</strong> <?php echo htmlspecialchars($current_employee['phone'] ?? '—'); ?></p>
                            <p><strong>🎭 Rôle :</strong> <?php echo htmlspecialchars($current_employee['role_name']); ?></p>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="save_contract">
                            <input type="hidden" name="user_id" value="<?php echo $current_employee['id']; ?>">
                            <?php if ($contract_id): ?>
                                <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                            <?php endif; ?>

                            <div class="two-columns-form">
                                <div class="form-group">
                                    <label>Type de contrat *</label>
                                    <select name="contract_type" class="input" required>
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($contract_types as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo ($contract_data['contract_type'] ?? '') == $key ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Salaire mensuel (DA) *</label>
                                    <input type="number" name="salary" class="input" step="1000" min="0" required value="<?php echo $contract_data['salary'] ?? ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label>Poste / Fonction *</label>
                                    <input type="text" name="position" class="input" required placeholder="Ex: Médecin chef, Infirmier, Secrétaire..." value="<?php echo htmlspecialchars($contract_data['position'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Département / Service</label>
                                    <input type="text" name="department" class="input" placeholder="Ex: Cardiologie, Urgences..." value="<?php echo htmlspecialchars($contract_data['department'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Date de début *</label>
                                    <input type="date" name="start_date" class="input" required value="<?php echo $contract_data['start_date'] ?? date('Y-m-d'); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Date de fin (optionnel)</label>
                                    <input type="date" name="end_date" class="input" value="<?php echo $contract_data['end_date'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="info-note">
                                📌 <strong>Informations importantes :</strong><br>
                                - Le salaire est exprimé en Dinar Algérien (DA)<br>
                                - Pour un CDI, laissez la date de fin vide<br>
                                - Pour un CDD, indiquez une date de fin<br>
                                - Les charges sociales (25%) seront calculées automatiquement
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $contract_id ? 'Mettre à jour' : 'Enregistrer le contrat'; ?>
                                </button>
                                <a href="employees.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Si modification, afficher l'historique des contrats -->
                <?php if ($contract_id && $contract_data): ?>
                <div class="card">
                    <div class="card-head">
                        <h3>📜 Historique des contrats</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $database->prepare("
                            SELECT * FROM employee_contracts 
                            WHERE user_id = ? 
                            ORDER BY start_date DESC
                        ");
                        $stmt->bind_param('i', $contract_data['user_id']);
                        $stmt->execute();
                        $contract_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>
                        <table class="tbl">
                            <thead>
                            <tr>
                                <th>Type</th>
                                <th>Début</th>
                                <th>Fin</th>
                                <th>Salaire</th>
                                <th>Poste</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($contract_history as $hist): ?>
                                <tr>
                                    <td><?php echo $hist['contract_type']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($hist['start_date'])); ?></td>
                                    <td><?php echo $hist['end_date'] ? date('d/m/Y', strtotime($hist['end_date'])) : '—'; ?></td>
                                    <td><?php echo number_format($hist['salary'], 0, ',', ' '); ?> DA</td>
                                    <td><?php echo htmlspecialchars($hist['position'] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    // Gestion des dates : si CDI, désactiver la date de fin
    const contractTypeSelect = document.querySelector('select[name="contract_type"]');
    const endDateInput = document.querySelector('input[name="end_date"]');

    if (contractTypeSelect) {
        contractTypeSelect.addEventListener('change', function() {
            if (this.value === 'CDI') {
                endDateInput.disabled = true;
                endDateInput.value = '';
                endDateInput.placeholder = 'Non applicable (CDI)';
            } else {
                endDateInput.disabled = false;
                endDateInput.placeholder = 'JJ/MM/AAAA';
            }
        });

        // Initialisation
        if (contractTypeSelect.value === 'CDI') {
            endDateInput.disabled = true;
        }
    }
</script>
</body>
</html>