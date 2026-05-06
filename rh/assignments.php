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
$user_id = $_GET['user_id'] ?? null;

// === TRAITEMENT FORMULAIRE D'AFFECTATION ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign') {
    // Vérifier que tous les champs nécessaires sont présents
    if (isset($_POST['user_id']) && isset($_POST['department_id']) && isset($_POST['position']) && isset($_POST['start_date'])) {

        $user_id = intval($_POST['user_id']);
        $department_id = intval($_POST['department_id']);
        $position = trim($_POST['position']);
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        // Validation
        if (empty($position)) {
            $error = "Le poste est obligatoire";
        } elseif ($department_id <= 0) {
            $error = "Veuillez sélectionner un département";
        } else {
            $result = assignEmployee($database, $user_id, $department_id, $position, $start_date, $end_date);

            if ($result['success']) {
                $message = $result['message'];
                // Recharger les données après modification
                $current_assignment = getCurrentAssignment($database, $user_id);
                $assignment_history = getEmployeeAssignments($database, $user_id);
            } else {
                $error = $result['message'];
            }
        }
    } else {
        $error = "Tous les champs obligatoires doivent être remplis";
    }
}

// === TRAITEMENT SUPPRESSION AFFECTATION ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unassign' && isset($_POST['assignment_id'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $stmt = $database->prepare("UPDATE employee_assignments SET is_primary = 0, end_date = CURDATE() WHERE id = ?");
    $stmt->bind_param('i', $assignment_id);
    if ($stmt->execute()) {
        $message = "Affectation terminée";
        // Recharger les données
        $current_assignment = getCurrentAssignment($database, $user_id);
        $assignment_history = getEmployeeAssignments($database, $user_id);
    } else {
        $error = "Erreur lors de la suppression";
    }
}

// === RÉCUPÉRER LES DONNÉES ===

// Liste des employés (exclut admin et patients)
$employees = [];
$stmt = $database->prepare("
    SELECT u.id, u.full_name, u.email, u.phone, u.is_active, r.role_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE u.role_id NOT IN (1, 3) AND u.is_active = 1
    ORDER BY u.full_name
");
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Départements
$departments = [];
$departments_stmt = $database->prepare("SELECT id, name, description FROM departments WHERE is_active = 1 ORDER BY name");
if ($departments_stmt) {
    $departments_stmt->execute();
    $departments = $departments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Affectation actuelle (si user_id sélectionné)
$current_assignment = null;
$assignment_history = [];
$employee_details = null;

if ($user_id) {
    $current_assignment = getCurrentAssignment($database, $user_id);
    $assignment_history = getEmployeeAssignments($database, $user_id);

    // Récupérer les détails de l'employé
    $stmt = $database->prepare("SELECT u.*, r.role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $employee_details = $stmt->get_result()->fetch_assoc();
}

// Statistiques départements
$department_stats = [];
$stats_result = $database->query("
    SELECT 
        d.id, d.name,
        COUNT(ea.user_id) as employee_count
    FROM departments d
    LEFT JOIN employee_assignments ea ON ea.department_id = d.id AND ea.is_primary = 1 AND (ea.end_date IS NULL OR ea.end_date >= CURDATE())
    WHERE d.is_active = 1
    GROUP BY d.id, d.name
    ORDER BY employee_count DESC
");
if ($stats_result) {
    $department_stats = $stats_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Affectations — RH</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }
        .info-card {
            background: var(--surf2);
            border-radius: var(--r);
            padding: 15px;
            margin-bottom: 15px;
        }
        .info-card p {
            margin: 8px 0;
            font-size: 0.85rem;
        }
        .info-card .label {
            font-weight: 600;
            color: var(--text2);
            width: 120px;
            display: inline-block;
        }
        .badge-active {
            background: var(--green-l);
            color: var(--green);
        }
        .badge-inactive {
            background: var(--surf2);
            color: var(--text2);
        }
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .department-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        .department-stat .count {
            font-weight: 700;
            color: var(--green);
        }
        .empty-departments {
            text-align: center;
            padding: 20px;
            color: var(--text2);
        }
        .btn-danger {
            background: var(--red-l);
            color: var(--red);
            border: 1px solid #FADBD8;
        }
        .btn-danger:hover {
            background: #FADBD8;
        }
        .employee-selector {
            margin-bottom: 20px;
        }
        .employee-selector select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--rs);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Affectations des employés</span>
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

        <div class="two-columns">
            <!-- Colonne gauche : Sélection employé + Formulaire -->
            <div>
                <!-- Sélection de l'employé -->
                <div class="card">
                    <div class="card-head">
                        <h3>Sélectionner un employé</h3>
                    </div>
                    <div class="card-body employee-selector">
                        <form method="GET" id="employeeSelectForm">
                            <select name="user_id" class="input" onchange="this.form.submit()">
                                <option value="">-- Choisir un employé --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo ($user_id == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['role_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Formulaire d'affectation (visible seulement si employé sélectionné) -->
                <?php if ($user_id && $employee_details): ?>
                    <div class="card">
                        <div class="card-head">
                            <h3>Nouvelle affectation</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                                <div class="form-group">
                                    <label>Département *</label>
                                    <select name="department_id" class="input" required>
                                        <option value="">-- Sélectionner un département --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" <?php echo ($current_assignment && $current_assignment['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Poste *</label>
                                    <input type="text" name="position" class="input" required placeholder="Ex: Médecin chef, Infirmier, Secrétaire..." value="<?php echo htmlspecialchars($current_assignment['position'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Date de début *</label>
                                    <input type="date" name="start_date" class="input" required value="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Date de fin (optionnel)</label>
                                    <input type="date" name="end_date" class="input">
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <?php echo $current_assignment ? 'Modifier l\'affectation' : 'Affecter'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Liste des départements et effectifs -->
                <div class="card">
                    <div class="card-head">
                        <h3>Effectifs par département</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($department_stats)): ?>
                            <div class="empty-departments">
                                <p>Aucun département configuré</p>
                                <p style="font-size:0.75rem; margin-top:5px;">Ajoutez des départements dans la base de données</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($department_stats as $stat): ?>
                                <div class="department-stat">
                                    <span><?php echo htmlspecialchars($stat['name']); ?></span>
                                    <span class="count"><?php echo $stat['employee_count']; ?> employé(s)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonne droite : Détail de l'employé -->
            <div>
                <?php if ($user_id && $employee_details): ?>
                    <!-- Informations employé -->
                    <div class="card">
                        <div class="card-head">
                            <h3>Informations employé</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-card">
                                <p><span class="label">Nom :</span> <?php echo htmlspecialchars($employee_details['full_name']); ?></p>
                                <p><span class="label">Email :</span> <?php echo htmlspecialchars($employee_details['email']); ?></p>
                                <p><span class="label">Téléphone :</span> <?php echo htmlspecialchars($employee_details['phone'] ?? '—'); ?></p>
                                <p><span class="label">Rôle :</span> <?php echo htmlspecialchars($employee_details['role_name']); ?></p>
                                <p><span class="label">Statut :</span>
                                    <span class="badge <?php echo $employee_details['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $employee_details['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Affectation actuelle -->
                    <div class="card">
                        <div class="card-head">
                            <h3>Affectation actuelle</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($current_assignment): ?>
                                <div class="info-card">
                                    <p><span class="label">Département :</span> <?php echo htmlspecialchars($current_assignment['department_name']); ?></p>
                                    <p><span class="label">Poste :</span> <?php echo htmlspecialchars($current_assignment['position']); ?></p>
                                    <p><span class="label">Depuis le :</span> <?php echo date('d/m/Y', strtotime($current_assignment['start_date'])); ?></p>
                                    <?php if ($current_assignment['end_date']): ?>
                                        <p><span class="label">Jusqu'au :</span> <?php echo date('d/m/Y', strtotime($current_assignment['end_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" onsubmit="return confirm('Confirmer la fin de cette affectation ?')">
                                    <input type="hidden" name="action" value="unassign">
                                    <input type="hidden" name="assignment_id" value="<?php echo $current_assignment['id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Terminer l'affectation</button>
                                </form>
                            <?php else: ?>
                                <p class="empty" style="text-align:center; padding:20px;">Aucune affectation en cours</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Historique des affectations -->
                    <?php if (!empty($assignment_history)): ?>
                        <div class="card">
                            <div class="card-head">
                                <h3>Historique des affectations</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($assignment_history as $hist): ?>
                                    <div class="history-item">
                                        <div>
                                            <strong><?php echo htmlspecialchars($hist['department_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($hist['position']); ?></small>
                                        </div>
                                        <div class="date" style="font-size:0.7rem; color:var(--text2);">
                                            <?php echo date('d/m/Y', strtotime($hist['start_date'])); ?>
                                            <?php if ($hist['end_date']): ?>
                                                → <?php echo date('d/m/Y', strtotime($hist['end_date'])); ?>
                                            <?php else: ?>
                                                → Présent
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif ($user_id && !$employee_details): ?>
                    <div class="card">
                        <div class="card-head">
                            <h3>Employé non trouvé</h3>
                        </div>
                        <div class="card-body">
                            <div class="empty">
                                <p>L'employé sélectionné n'existe pas</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-head">
                            <h3>Sélectionnez un employé</h3>
                        </div>
                        <div class="card-body">
                            <div class="empty">
                                <svg viewBox="0 0 24 24" width="40" height="40"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <h3>Choisissez un employé dans la liste</h3>
                                <p>Pour voir ses affectations et en créer une nouvelle</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</body>
</html>