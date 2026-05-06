<!DOCTYPE html>
<html lang="fr">

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

$today = date('Y-m-d');
$message = '';
$error = '';

// === TRAITEMENT FORMULAIRE (enregistrement d'absence) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'record_absence') {
        // Enregistrer l'absence pour l'employé sélectionné
        $result = recordAttendance(
                $database,
                $_POST['user_id'],
                $_POST['attendance_date'],
                $_POST['status'], // absent, late, excused, holiday
                null, // check_in
                null, // check_out
                $_POST['notes'] ?? ''
        );

        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// === FILTRES ===
$filter_date = $_GET['date'] ?? $today;
$filter_user = $_GET['user'] ?? null;

// === RÉCUPÉRER LES DONNÉES ===
// Tous les employés (non patients, non admin)
$stmt = $database->prepare(
        "SELECT u.id, u.full_name, u.email, u.phone, r.role_name
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE u.role_id NOT IN (1, 3) AND u.is_active = 1
     ORDER BY u.full_name ASC"
);
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Présences/absences du mois
$start_date = date('Y-m-01', strtotime($filter_date));
$end_date = date('Y-m-t', strtotime($filter_date));
$attendances = getAttendancesByPeriod($database, $start_date, $end_date, $filter_user);

// Statistiques du mois
$stats = getAttendanceStats($database, date('Y', strtotime($filter_date)), date('m', strtotime($filter_date)));

// Créer un tableau des absences par employé et par jour pour faciliter l'affichage
$absenceMap = [];
foreach ($attendances as $att) {
    $absenceMap[$att['user_id']][$att['attendance_date']] = $att;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Présences/Absences — RH</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .form-group.full {
            grid-column: 1 / -1;
        }
        .stats-mini {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-mini {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--rs);
            padding: 10px 15px;
            flex: 1;
            text-align: center;
        }
        .stat-mini .value {
            font-size: 1.4rem;
            font-weight: 700;
        }
        .stat-mini .label {
            font-size: 0.7rem;
            color: var(--text2);
        }

        /* Styles pour le tableau des absences */
        .absence-table {
            width: 100%;
            border-collapse: collapse;
        }
        .absence-table th,
        .absence-table td {
            padding: 8px 12px;
            border: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }
        .absence-table th {
            background: var(--surf2);
            font-weight: 600;
            font-size: 0.8rem;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-present { background: var(--green-l); color: var(--green); }
        .status-absent { background: var(--red-l); color: var(--red); }
        .status-late { background: var(--amber-l); color: var(--amber); }
        .status-excused { background: var(--blue-l); color: var(--blue); }
        .status-holiday { background: var(--purple-l); color: var(--purple); }

        .absence-form {
            background: var(--amber-l);
            padding: 15px;
            border-radius: var(--rs);
            margin-bottom: 20px;
        }
        .absence-form h4 {
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .day-column {
            min-width: 80px;
            text-align: center;
        }
        .weekend {
            background: var(--surf2);
        }

        .action-btn {
            padding: 4px 8px;
            font-size: 0.7rem;
            border-radius: 4px;
            cursor: pointer;
            background: var(--red);
            color: white;
            border: none;
        }
        .action-btn:hover {
            opacity: 0.8;
        }

        .note-cell {
            max-width: 150px;
            font-size: 0.7rem;
            color: var(--text2);
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des présences / absences</span>
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

        <!-- Mini statistiques -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="value" style="color:var(--green)"><?php echo $stats['present_days'] ?? 0; ?></div>
                <div class="label">Présences enregistrées</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color:var(--red)"><?php echo $stats['absent_days'] ?? 0; ?></div>
                <div class="label">Absences</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color:var(--amber)"><?php echo $stats['late_days'] ?? 0; ?></div>
                <div class="label">Retards</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color:var(--blue)"><?php echo $stats['excused_days'] ?? 0; ?></div>
                <div class="label">Absences justifiées</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color:var(--purple)"><?php echo $stats['holiday_days'] ?? 0; ?></div>
                <div class="label">Congés</div>
            </div>
        </div>

        <!-- Formulaire d'enregistrement d'absence (le RH ne marque que les absences) -->
        <div class="absence-form">
            <h4>📝 Enregistrer une absence / retard / congé</h4>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="record_absence">
                <div class="form-grid">
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
                        <input type="date" name="attendance_date" class="input" value="<?php echo $today; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Statut *</label>
                        <select name="status" class="input" required>
                            <option value="absent">❌ Absent</option>
                            <option value="late">⏰ Retard</option>
                            <option value="excused">📋 Absence justifiée</option>
                            <option value="holiday">🌴 Congé</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Motif / Notes</label>
                        <textarea name="notes" class="input" rows="2" placeholder="Motif de l'absence, du retard, etc."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer l'absence</button>
            </form>
        </div>

        <!-- Filtres -->
        <div class="card">
            <div class="card-head">
                <h3>Filtrer le relevé</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <label>Mois</label>
                        <input type="month" name="date" class="input" value="<?php echo substr($filter_date, 0, 7); ?>">
                    </div>
                    <div class="form-group">
                        <label>Employé</label>
                        <select name="user" class="input">
                            <option value="">-- Tous --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php if ($filter_user == $emp['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="attendances.php" class="btn btn-secondary">Réinitialiser</a>
                </form>
            </div>
        </div>

        <!-- Tableau des présences/absences -->
        <div class="card">
            <div class="card-head">
                <h3>Relevé des présences - <?php echo date('F Y', strtotime($filter_date)); ?></h3>
                <span class="badge badge-blue">Présent par défaut ✓</span>
            </div>
            <div class="card-body" style="overflow-x: auto;">
                <?php if (count($employees) === 0): ?>
                    <div class="empty">
                        <svg viewBox="0 0 24 24" width="40" height="40"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
                        <h3>Aucun employé trouvé</h3>
                    </div>
                <?php else: ?>
                    <table class="absence-table">
                        <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Poste</th>
                            <?php
                            // Générer les colonnes pour chaque jour du mois
                            $days_in_month = date('t', strtotime($filter_date));
                            for ($d = 1; $d <= $days_in_month; $d++):
                                $current_date = date('Y-m-d', strtotime("$filter_date-$d"));
                                $day_name = date('D', strtotime($current_date));
                                $is_weekend = (date('N', strtotime($current_date)) >= 6);
                                ?>
                                <th class="day-column <?php echo $is_weekend ? 'weekend' : ''; ?>">
                                    <?php echo $d; ?><br>
                                    <small><?php echo $day_name; ?></small>
                                </th>
                            <?php endfor; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td style="font-weight:600"><?php echo htmlspecialchars($emp['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['role_name']); ?></td>
                                <?php
                                for ($d = 1; $d <= $days_in_month; $d++):
                                    $current_date = date('Y-m-d', strtotime("$filter_date-$d"));
                                    $attendance = $absenceMap[$emp['id']][$current_date] ?? null;
                                    $is_weekend = (date('N', strtotime($current_date)) >= 6);

                                    // Déterminer le statut à afficher
                                    if ($attendance) {
                                        // Une absence a été enregistrée
                                        $status = $attendance['status'];
                                        $notes = $attendance['notes'] ?? '';
                                        $status_class = match($status) {
                                            'absent' => 'status-absent',
                                            'late' => 'status-late',
                                            'excused' => 'status-excused',
                                            'holiday' => 'status-holiday',
                                            default => 'status-present'
                                        };
                                        $status_label = match($status) {
                                            'absent' => '❌',
                                            'late' => '⏰',
                                            'excused' => '📋',
                                            'holiday' => '🌴',
                                            default => '✓'
                                        };
                                    } else {
                                        // Par défaut : PRÉSENT
                                        $status = 'present';
                                        $status_class = 'status-present';
                                        $status_label = '✓';
                                        $notes = '';
                                    }
                                    ?>
                                    <td class="<?php echo $is_weekend ? 'weekend' : ''; ?>">
                                            <span class="status-badge <?php echo $status_class; ?>" title="<?php echo htmlspecialchars($notes); ?>">
                                                <?php echo $status_label; ?>
                                            </span>
                                        <?php if ($notes): ?>
                                            <div class="note-cell"><?php echo htmlspecialchars(substr($notes, 0, 30)); ?></div>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Légende -->
        <div class="card">
            <div class="card-head">
                <h3>📖 Légende</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div><span class="status-badge status-present">✓</span> = Présent (par défaut)</div>
                    <div><span class="status-badge status-absent">❌</span> = Absent</div>
                    <div><span class="status-badge status-late">⏰</span> = Retard</div>
                    <div><span class="status-badge status-excused">📋</span> = Absence justifiée</div>
                    <div><span class="status-badge status-holiday">🌴</span> = Congé</div>
                </div>
                <p class="info-note" style="margin-top: 15px; font-size: 0.7rem;">
                    💡 <strong>Note :</strong> Par défaut, tous les employés sont considérés <strong>PRÉSENTS</strong>.
                    Le responsable RH n'a besoin d'enregistrer que les <strong>absences, retards, congés ou absences justifiées</strong>.
                </p>
            </div>
        </div>

    </div>
</div>
</body>
</html>