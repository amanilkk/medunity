<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

if ($_SESSION['role'] !== 'gestionnaire_rh') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// === TRAITEMENT FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $current_status = isset($_POST['current_status']) ? intval($_POST['current_status']) : 1;
        $new_status = $current_status == 1 ? 0 : 1;

        $stmt = $database->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param('ii', $new_status, $user_id);

        if ($stmt->execute()) {
            $message = 'Statut de l\'employé mis à jour';
        } else {
            $error = 'Erreur lors de la mise à jour';
        }
    }
}

// Filtres
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? 'all';
$status_filter = $_GET['status'] ?? 'active';

// Récupérer les employés avec leurs affectations actuelles
$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.created_at, u.role_id,
        d.name as department_name, ea.position,
        ec.salary, ec.contract_type, ec.start_date as contract_start,
        r.role_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        LEFT JOIN employee_assignments ea ON ea.user_id = u.id AND ea.is_primary = 1
        LEFT JOIN departments d ON d.id = ea.department_id
        LEFT JOIN employee_contracts ec ON ec.user_id = u.id AND (ec.end_date IS NULL OR ec.end_date >= CURDATE())
        WHERE u.role_id NOT IN (1, 3)";

$types = '';
$params = [];

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = '%' . $search . '%';
    $types .= 'sss';
    $params = [$search_param, $search_param, $search_param];
}

if ($department_filter != 'all') {
    $sql .= " AND ea.department_id = ?";
    $types .= 'i';
    $params[] = intval($department_filter);
}

if ($status_filter == 'active') {
    $sql .= " AND u.is_active = 1";
} elseif ($status_filter == 'inactive') {
    $sql .= " AND u.is_active = 0";
}

$sql .= " ORDER BY u.full_name";

$stmt = $database->prepare($sql);
if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$employees = $stmt->get_result();

// Récupérer les départements pour le filtre
$departments = [];
$departments_stmt = $database->prepare("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
if ($departments_stmt) {
    $departments_stmt->execute();
    $departments = $departments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Statistiques
$total_active = safeCount($database,
        "SELECT COUNT(*) c FROM users WHERE role_id NOT IN (1, 3) AND is_active = 1",
        '');
$total_inactive = safeCount($database,
        "SELECT COUNT(*) c FROM users WHERE role_id NOT IN (1, 3) AND is_active = 0",
        '');
$total_employees = $total_active + $total_inactive;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Employés — RH</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .form-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
            padding: 15px 20px;
        }
        .form-inline .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 150px;
        }
        .form-inline .form-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
        }
        .form-inline .form-group input,
        .form-inline .form-group select {
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: var(--rs);
            font-family: inherit;
            font-size: 0.8rem;
        }
        .form-inline button {
            margin-top: 0;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des employés</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="contract-form.php" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouvel employé
            </a>
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

        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <div class="stat-ico a"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div><div class="stat-num"><?php echo $total_employees; ?></div><div class="stat-lbl">Total</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico b"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div><div class="stat-num"><?php echo $total_active; ?></div><div class="stat-lbl">Actifs</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico r"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                <div><div class="stat-num"><?php echo $total_inactive; ?></div><div class="stat-lbl">Inactifs</div></div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card">
            <div class="card-head">
                <h3>Filtrer les employés</h3>
            </div>
            <form method="GET" class="form-inline">
                <div class="form-group">
                    <label>Recherche</label>
                    <input type="text" name="search" class="input" placeholder="Nom, email, téléphone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label>Département</label>
                    <select name="department" class="input">
                        <option value="all">Tous</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php if ($department_filter == $dept['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="status" class="input">
                        <option value="active" <?php if ($status_filter == 'active') echo 'selected'; ?>>Actifs</option>
                        <option value="inactive" <?php if ($status_filter == 'inactive') echo 'selected'; ?>>Inactifs</option>
                        <option value="all" <?php if ($status_filter == 'all') echo 'selected'; ?>>Tous</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="employees.php" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>

        <!-- Liste des employés -->
        <div class="card">
            <div class="card-head">
                <h3>Liste des employés (<?php echo $employees->num_rows; ?>)</h3>
            </div>
            <?php if ($employees->num_rows === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" width="40" height="40"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <h3>Aucun employé trouvé</h3>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Poste</th>
                        <th>Service</th>
                        <th>Type contrat</th>
                        <th>Salaire</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($emp = $employees->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($emp['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><?php echo htmlspecialchars($emp['phone'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($emp['position'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($emp['department_name'] ?? '—'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($emp['contract_type'] ?? 'none'); ?>">
                                    <?php echo htmlspecialchars($emp['contract_type'] ?? '—'); ?>
                                </span>
                            </td>
                            <td style="color:var(--green); font-weight:600"><?php echo number_format($emp['salary'] ?? 0, 0, ',', ' '); ?> DA</td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $emp['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $emp['is_active']; ?>">
                                    <button type="submit" class="btn btn-xs <?php echo $emp['is_active'] ? 'btn-red' : 'btn-blue'; ?>" onclick="return confirm('Changer le statut de cet employé ?')">
                                        <?php echo $emp['is_active'] ? 'Désactiver' : 'Activer'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    <a href="employee-detail.php?id=<?php echo $emp['id']; ?>" class="btn btn-secondary btn-sm">Voir</a>
                                    <a href="assignments.php?user_id=<?php echo $emp['id']; ?>" class="btn btn-blue btn-sm">Affecter</a>
                                    <a href="documents.php?user_id=<?php echo $emp['id']; ?>" class="btn btn-secondary btn-sm">Documents</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>
</body>
</html>