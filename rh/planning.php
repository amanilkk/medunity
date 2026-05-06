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

// === TRAITEMENT FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_schedule') {
        $user_id = intval($_POST['user_id']);
        $planning_date = $_POST['planning_date'];
        $shift_start = $_POST['shift_start'];
        $shift_end = $_POST['shift_end'];
        $shift_type = $_POST['shift_type'];
        $notes = $_POST['notes'] ?? '';

        // Vérifier conflit
        $conflict = checkScheduleConflict($database, $user_id, $planning_date, $shift_start, $shift_end);
        if ($conflict['has_conflict']) {
            $error = $conflict['message'];
        } else {
            $result = createPlanning($database, $user_id, $planning_date, $shift_start, $shift_end, $shift_type, $notes, $_SESSION['user_id']);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }

    if ($_POST['action'] === 'delete_schedule' && isset($_POST['schedule_id'])) {
        $stmt = $database->prepare("DELETE FROM staff_planning WHERE id = ?");
        $stmt->bind_param('i', $_POST['schedule_id']);
        if ($stmt->execute()) {
            $message = "Planning supprimé";
        } else {
            $error = "Erreur lors de la suppression";
        }
    }
}

// === FILTRES ===
$week_offset = isset($_GET['week']) ? intval($_GET['week']) : 0;
$filter_user = $_GET['user'] ?? null;
$filter_department = $_GET['department'] ?? null;

// Calcul de la semaine
$week_start = new DateTime();
$week_start->modify('monday this week');
if ($week_offset > 0) {
    $week_start->modify("+$week_offset weeks");
} elseif ($week_offset < 0) {
    $week_start->modify("$week_offset weeks");
}
$week_start_date = $week_start->format('Y-m-d');
$week_end_date = (clone $week_start)->modify('+6 days')->format('Y-m-d');

// Récupérer les plannings de la semaine
$sql = "SELECT sp.*, u.full_name, u.email, r.role_name
        FROM staff_planning sp
        INNER JOIN users u ON u.id = sp.user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE sp.planning_date BETWEEN ? AND ?";

$params = [$week_start_date, $week_end_date];
$types = 'ss';

if ($filter_user) {
    $sql .= " AND sp.user_id = ?";
    $types .= 'i';
    $params[] = $filter_user;
}

$sql .= " ORDER BY sp.planning_date, u.full_name";

$stmt = $database->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Organisation par date et employé
$schedule_by_date = [];
foreach ($schedules as $sch) {
    $schedule_by_date[$sch['planning_date']][$sch['user_id']] = $sch;
}

// Récupérer tous les employés actifs
$employees = getAllEmployees($database, true);

// Récupérer les départements
$departments = getAllDepartments($database, true);

// Jours de la semaine
$days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
$days_short = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

// Types de shift avec labels
$shift_types = [
    'matin' => ['label' => 'Matin', 'icon' => '🌅', 'time' => '08:00-16:00'],
    'soir' => ['label' => 'Soir', 'icon' => '🌙', 'time' => '16:00-00:00'],
    'nuit' => ['label' => 'Nuit', 'icon' => '🌃', 'time' => '00:00-08:00'],
    'garde' => ['label' => 'Garde', 'icon' => '🚨', 'time' => '24h'],
    'urgence' => ['label' => 'Urgence', 'icon' => '⚠️', 'time' => 'Variable']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Planning — RH</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .week-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }
        .week-nav-buttons {
            display: flex;
            gap: 10px;
        }
        .week-date {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
            background: var(--surf2);
            padding: 8px 16px;
            border-radius: var(--rs);
        }
        .planning-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
        }
        .planning-table th,
        .planning-table td {
            border: 1px solid var(--border);
            padding: 10px;
            vertical-align: top;
            min-width: 120px;
        }
        .planning-table th {
            background: var(--surf2);
            font-weight: 600;
            text-align: center;
        }
        .employee-name {
            font-weight: 600;
            font-size: 0.85rem;
        }
        .employee-role {
            font-size: 0.65rem;
            color: var(--text2);
        }
        .shift-cell {
            min-height: 80px;
        }
        .shift-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: var(--rs);
            font-size: 0.7rem;
            font-weight: 600;
            margin: 2px 0;
        }
        .shift-matin { background: var(--blue-l); color: var(--blue); }
        .shift-soir { background: var(--amber-l); color: var(--amber); }
        .shift-nuit { background: var(--purple-l); color: var(--purple); }
        .shift-garde { background: var(--red-l); color: var(--red); }
        .shift-urgence { background: var(--orange-l); color: var(--orange); }
        .empty-shift {
            color: var(--text3);
            font-size: 0.7rem;
            text-align: center;
            padding: 10px;
        }
        .delete-btn {
            background: none;
            border: none;
            color: var(--red);
            cursor: pointer;
            font-size: 0.7rem;
            margin-top: 5px;
        }
        .delete-btn:hover {
            text-decoration: underline;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .shift-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .shift-option {
            flex: 1;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: var(--rs);
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        .shift-option.selected {
            border-color: var(--green);
            background: var(--green-l);
        }
        .shift-option:hover:not(.selected) {
            border-color: var(--green);
        }
        .shift-option .icon {
            font-size: 1.2rem;
        }
        .shift-option .label {
            font-size: 0.75rem;
            font-weight: 600;
        }
        .shift-option .time {
            font-size: 0.65rem;
            color: var(--text2);
        }
        .legend {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
        }
        @media (max-width: 768px) {
            .planning-table {
                font-size: 0.7rem;
            }
            .planning-table th,
            .planning-table td {
                padding: 5px;
                min-width: 80px;
            }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Planning du personnel</span>
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

        <!-- Navigation semaine -->
        <div class="week-navigation">
            <div class="week-nav-buttons">
                <a href="?week=<?php echo $week_offset - 1; ?>&user=<?php echo $filter_user; ?>&department=<?php echo $filter_department; ?>" class="btn btn-secondary btn-sm">← Semaine précédente</a>
                <a href="?week=0&user=<?php echo $filter_user; ?>&department=<?php echo $filter_department; ?>" class="btn btn-secondary btn-sm">Semaine actuelle</a>
                <a href="?week=<?php echo $week_offset + 1; ?>&user=<?php echo $filter_user; ?>&department=<?php echo $filter_department; ?>" class="btn btn-secondary btn-sm">Semaine suivante →</a>
            </div>
            <div class="week-date">
                📅 Semaine du <?php echo date('d/m/Y', strtotime($week_start_date)); ?> au <?php echo date('d/m/Y', strtotime($week_end_date)); ?>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card">
            <div class="card-head">
                <h3>Filtrer le planning</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <input type="hidden" name="week" value="<?php echo $week_offset; ?>">
                    <div class="form-group">
                        <label>Employé</label>
                        <select name="user" class="input">
                            <option value="">-- Tous --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo ($filter_user == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Département</label>
                        <select name="department" class="input">
                            <option value="">-- Tous --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($filter_department == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="planning.php?week=<?php echo $week_offset; ?>" class="btn btn-secondary">Réinitialiser</a>
                </form>
            </div>
        </div>

        <!-- Formulaire ajout planning -->
        <div class="card">
            <div class="card-head">
                <h3>➕ Ajouter un planning</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_schedule">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Employé *</label>
                            <select name="user_id" class="input" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['role_name']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="planning_date" class="input" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Type de shift *</label>
                        <div class="shift-selector" id="shiftSelector">
                            <?php foreach ($shift_types as $key => $shift): ?>
                                <div class="shift-option" data-shift="<?php echo $key; ?>" data-start="<?php echo explode('-', $shift['time'])[0]; ?>" data-end="<?php echo explode('-', $shift['time'])[1] ?? ''; ?>">
                                    <div class="icon"><?php echo $shift['icon']; ?></div>
                                    <div class="label"><?php echo $shift['label']; ?></div>
                                    <div class="time"><?php echo $shift['time']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="shift_type" id="shift_type" required>
                        <input type="hidden" name="shift_start" id="shift_start">
                        <input type="hidden" name="shift_end" id="shift_end">
                    </div>

                    <div class="form-group">
                        <label>Notes (optionnel)</label>
                        <textarea name="notes" class="input" rows="2" placeholder="Informations complémentaires..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Ajouter au planning</button>
                </form>
            </div>
        </div>

        <!-- Planning visuel -->
        <div class="card">
            <div class="card-head">
                <h3>📅 Planning du <?php echo date('d/m/Y', strtotime($week_start_date)); ?> au <?php echo date('d/m/Y', strtotime($week_end_date)); ?></h3>
            </div>
            <div class="card-body" style="overflow-x: auto;">
                <table class="planning-table">
                    <thead>
                    <tr>
                        <th>Employé</th>
                        <?php foreach ($days as $index => $day):
                            $current_date = (clone $week_start)->modify("+$index days")->format('d/m');
                            ?>
                            <th><?php echo $day; ?><br><small><?php echo $current_date; ?></small></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td>
                                <div class="employee-name"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                <div class="employee-role"><?php echo htmlspecialchars($emp['role_name']); ?></div>
                            </td>
                            <?php for ($i = 0; $i < 7; $i++):
                                $current_date = (clone $week_start)->modify("+$i days")->format('Y-m-d');
                                $schedule = $schedule_by_date[$current_date][$emp['id']] ?? null;
                                ?>
                                <td class="shift-cell">
                                    <?php if ($schedule):
                                        $shift_class = 'shift-' . $schedule['shift_type'];
                                        $shift_label = $shift_types[$schedule['shift_type']]['label'] ?? $schedule['shift_type'];
                                        $shift_icon = $shift_types[$schedule['shift_type']]['icon'] ?? '📅';
                                        ?>
                                        <div class="shift-badge <?php echo $shift_class; ?>">
                                            <span><?php echo $shift_icon; ?></span>
                                            <span><?php echo $shift_label; ?></span>
                                        </div>
                                        <?php if ($schedule['shift_start'] && $schedule['shift_end']): ?>
                                        <div style="font-size:0.65rem; color:var(--text2); margin-top:3px;">
                                            <?php echo substr($schedule['shift_start'], 0, 5); ?> - <?php echo substr($schedule['shift_end'], 0, 5); ?>
                                        </div>
                                    <?php endif; ?>
                                        <?php if ($schedule['notes']): ?>
                                        <div style="font-size:0.6rem; color:var(--text3); margin-top:3px;">📝 <?php echo htmlspecialchars(substr($schedule['notes'], 0, 30)); ?></div>
                                    <?php endif; ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_schedule">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                            <button type="submit" class="delete-btn" onclick="return confirm('Supprimer ce planning ?')">🗑️ Supprimer</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="empty-shift">—</div>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Légende -->
        <div class="card">
            <div class="card-head">
                <h3>📖 Légende des shifts</h3>
            </div>
            <div class="card-body">
                <div class="legend">
                    <?php foreach ($shift_types as $key => $shift): ?>
                        <div class="legend-item">
                            <div class="shift-badge shift-<?php echo $key; ?>">
                                <span><?php echo $shift['icon']; ?></span>
                                <span><?php echo $shift['label']; ?></span>
                            </div>
                            <span style="font-size:0.7rem;"><?php echo $shift['time']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // Sélecteur de shift
    const shiftOptions = document.querySelectorAll('.shift-option');
    const shiftTypeInput = document.getElementById('shift_type');
    const shiftStartInput = document.getElementById('shift_start');
    const shiftEndInput = document.getElementById('shift_end');

    shiftOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Retirer la sélection de tous
            shiftOptions.forEach(opt => opt.classList.remove('selected'));
            // Ajouter la sélection
            this.classList.add('selected');

            const shiftType = this.dataset.shift;
            const startTime = this.dataset.start;
            const endTime = this.dataset.end;

            shiftTypeInput.value = shiftType;
            shiftStartInput.value = startTime || '08:00:00';
            shiftEndInput.value = endTime || '16:00:00';
        });
    });

    // Sélection par défaut
    if (shiftOptions.length > 0 && !shiftTypeInput.value) {
        shiftOptions[0].click();
    }
</script>
</body>
</html>