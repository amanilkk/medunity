<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
require_once 'functions.php';

if ($_SESSION['role'] !== 'comptable') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// Mise à jour d'un salaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_salary') {
    $user_id = intval($_POST['user_id']);
    $new_salary = floatval($_POST['salary']);

    $result = updateEmployeeSalary($database, $user_id, $new_salary);
    if ($result['success']) {
        $message = 'Salaire mis à jour avec succès !';
    } else {
        $error = $result['message'];
    }
}

// Récupérer les employés avec leurs contrats actifs
$employees = $database->query("
    SELECT u.id, u.full_name, u.email, u.phone, ec.salary, ec.contract_type, ec.start_date
    FROM users u
    INNER JOIN employee_contracts ec ON ec.user_id = u.id
    WHERE u.is_active = 1 AND (ec.end_date IS NULL OR ec.end_date >= CURDATE())
    ORDER BY u.full_name
");

// Statistiques
$total_salaries = safeCount($database,
    "SELECT COALESCE(SUM(ec.salary),0) c FROM employee_contracts ec
     WHERE (ec.end_date IS NULL OR ec.end_date >= CURDATE())", 's');

$active_employees = $employees->num_rows;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Salaires - Comptable</title>
    <link rel="stylesheet" href="comptable.css">
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des salaires</span>
        <div class="topbar-right">
            <a href="payroll.php" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Paie mensuelle
            </a>
        </div>
    </div>
    <div class="page-body">

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <div class="stat-ico p"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div><div class="stat-num"><?php echo $active_employees; ?></div><div class="stat-lbl">Employés actifs</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico g"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
                <div><div class="stat-num"><?php echo number_format($total_salaries, 0, ',', ' '); ?> DA</div><div class="stat-lbl">Masse salariale mensuelle</div></div>
            </div>
        </div>

        <!-- Liste des employés -->
        <div class="card">
            <div class="card-head">
                <h3>Employés et salaires</h3>
            </div>
            <?php if ($employees->num_rows === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                    <h3>Aucun employé avec contrat actif</h3>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>Employé</th><th>Email</th><th>Téléphone</th><th>Type contrat</th><th>Salaire actuel</th><th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($e = $employees->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($e['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($e['email']); ?></td>
                            <td><?php echo htmlspecialchars($e['phone'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($e['contract_type']); ?></td>
                            <td style="color:var(--green);font-weight:600"><?php echo number_format($e['salary'], 0, ',', ' '); ?> DA</td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="openModal(<?php echo $e['id']; ?>, '<?php echo addslashes($e['full_name']); ?>', <?php echo $e['salary']; ?>)">
                                    Modifier salaire
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal modification salaire -->
<div id="salaryModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:var(--r); width:400px; max-width:90%; padding:20px;">
        <h3 style="margin-bottom:15px">Modifier le salaire</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_salary">
            <input type="hidden" name="user_id" id="modal_user_id">
            <div class="form-group">
                <label>Employé : <span id="modal_employee_name"></span></label>
            </div>
            <div class="form-group">
                <label>Nouveau salaire (DA) *</label>
                <input type="number" name="salary" id="modal_salary" class="input" step="0.01" required>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id, name, salary) {
        document.getElementById('modal_user_id').value = id;
        document.getElementById('modal_employee_name').innerText = name;
        document.getElementById('modal_salary').value = salary;
        document.getElementById('salaryModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('salaryModal').style.display = 'none';
    }
</script>
</body>
</html>