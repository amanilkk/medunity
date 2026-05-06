<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

// Vérifier que l'utilisateur est pharmacien
if ($_SESSION['role'] !== 'pharmacien') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// === TRAITEMENT DES ACTIONS ===
if (isset($_GET['action']) && isset($_GET['id'])) {
    $prescription_id = intval($_GET['id']);

    if ($_GET['action'] === 'prepare') {
        $result = preparePrescription($database, $prescription_id, $_SESSION['user_id']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }

    if ($_GET['action'] === 'reject' && isset($_GET['reason'])) {
        $reason = $_GET['reason'];
        $result = rejectPrescription($database, $prescription_id, $reason);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// === FILTRES ===
$status_filter = $_GET['status'] ?? 'active';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// === RÉCUPÉRATION DES DONNÉES ===
$prescriptions = getAllPrescriptions($database, $status_filter, $start_date, $end_date);

// Statistiques
$stats = [
        'total' => count($prescriptions),
        'active' => 0,
        'delivered' => 0,
        'cancelled' => 0
];

foreach ($prescriptions as $p) {
    if ($p['status'] == 'active') $stats['active']++;
    elseif ($p['status'] == 'delivered') $stats['delivered']++;
    elseif ($p['status'] == 'cancelled') $stats['cancelled']++;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Prescriptions — Pharmacie</title>
    <link rel="stylesheet" href="pharmacien.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 15px;
            text-align: center;
            box-shadow: var(--sh);
        }
        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 5px;
        }
        .stat-card.active .value { color: var(--amber); }
        .stat-card.delivered .value { color: var(--green); }
        .stat-card.cancelled .value { color: var(--red); }

        .filter-tabs {
            display: flex;
            gap: 10px;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            background: var(--surf2);
            color: var(--text2);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.15s;
        }
        .tab.active {
            background: var(--green);
            color: white;
        }
        .tab:hover:not(.active) {
            background: var(--border);
        }
        .badge-count {
            display: inline-block;
            background: rgba(0,0,0,0.1);
            border-radius: 20px;
            padding: 0px 6px;
            font-size: 0.7rem;
            min-width: 24px;
            text-align: center;
        }
        .tab.active .badge-count {
            background: rgba(255,255,255,0.2);
        }
        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 22px;
            padding: 15px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
        }
        .btn-xs {
            padding: 4px 10px;
            font-size: 0.7rem;
            border-radius: 4px;
        }
        .badge-active { background: var(--amber-l); color: var(--amber); }
        .badge-delivered { background: var(--green-l); color: var(--green); }
        .badge-cancelled { background: var(--red-l); color: var(--red); }

        .prescription-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Styles spécifiques pour la modale de rejet */
        .reject-modal-overlay {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .reject-modal-content {
            background-color: var(--surface);
            border-radius: var(--r);
            padding: 24px;
            max-width: 450px;
            width: 90%;
            box-shadow: var(--sh);
        }
        .reject-modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .reject-modal-head h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        .reject-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text3);
        }
        .reject-modal-close:hover {
            color: var(--text);
        }
        .btn-red {
            background: var(--red-l);
            color: var(--red);
            border: 1px solid #FADBD8;
        }
        .btn-red:hover {
            background: #FADBD8;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des prescriptions</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                🖨️ Imprimer
            </button>
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

        <!-- Statistiques -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="value"><?php echo $stats['total']; ?></div>
                <div class="label">Total prescriptions</div>
            </div>
            <div class="stat-card active">
                <div class="value"><?php echo $stats['active']; ?></div>
                <div class="label">En attente</div>
            </div>
            <div class="stat-card delivered">
                <div class="value"><?php echo $stats['delivered']; ?></div>
                <div class="label">Délivrées</div>
            </div>
            <div class="stat-card cancelled">
                <div class="value"><?php echo $stats['cancelled']; ?></div>
                <div class="label">Rejetées</div>
            </div>
        </div>

        <!-- Filtres par statut -->
        <div class="card">
            <div class="filter-tabs">
                <a href="?status=all&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    <span class="badge-count"><?php echo $stats['total']; ?></span> Toutes
                </a>
                <a href="?status=active&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab <?php echo $status_filter == 'active' ? 'active' : ''; ?>">
                    <span class="badge-count"><?php echo $stats['active']; ?></span> En attente
                </a>
                <a href="?status=delivered&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
                    <span class="badge-count"><?php echo $stats['delivered']; ?></span> Délivrées
                </a>
                <a href="?status=cancelled&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
                    <span class="badge-count"><?php echo $stats['cancelled']; ?></span> Rejetées
                </a>
            </div>
        </div>

        <!-- Filtres par date -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                <div class="form-group" style="margin: 0;">
                    <label>Du</label>
                    <input type="date" name="start_date" class="input" value="<?php echo $start_date; ?>" style="width: auto;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Au</label>
                    <input type="date" name="end_date" class="input" value="<?php echo $end_date; ?>" style="width: auto;">
                </div>
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="prescriptions.php?status=<?php echo $status_filter; ?>" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>

        <!-- Liste des prescriptions -->
        <div class="card">
            <div class="card-head">
                <h3>📋 Liste des prescriptions</h3>
            </div>
            <?php if (count($prescriptions) === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" width="40" height="40">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <h3>Aucune prescription trouvée</h3>
                    <p>Aucune prescription sur cette période</p>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Date</th>
                        <th>Articles</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($prescriptions as $p):
                        $items = getPrescriptionItems($database, $p['id']);
                        $items_count = count($items);
                        ?>
                        <tr class="<?php echo $p['status'] == 'active' ? 'row-alert' : ''; ?>">
                            <td style="font-weight:600; font-family:monospace;">#<?php echo $p['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($p['patient_name']); ?></strong><br>
                                <small style="color:var(--text2);"><?php echo htmlspecialchars($p['phone'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($p['doctor_name']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($p['prescription_date'])); ?></td>
                            <td>
                                <span class="badge badge-blue"><?php echo $items_count; ?> article(s)</span>
                                <?php if ($items_count > 0): ?>
                                    <div class="prescription-preview" style="font-size:0.65rem; color:var(--text2); margin-top:3px;">
                                        <?php
                                        $first_items = array_slice($items, 0, 2);
                                        $names = array_map(function($item) { return $item['medicine_name']; }, $first_items);
                                        echo implode(', ', $names);
                                        if ($items_count > 2) echo '...';
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                        <span class="badge badge-<?php echo $p['status']; ?>">
                            <?php
                            $status_labels = [
                                    'active' => '⏳ En attente',
                                    'delivered' => '✅ Délivrée',
                                    'cancelled' => '❌ Rejetée'
                            ];
                            echo $status_labels[$p['status']] ?? $p['status'];
                            ?>
                        </span>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    <a href="prescription-detail.php?id=<?php echo $p['id']; ?>" class="btn btn-secondary btn-xs">📄 Détails</a>
                                    <?php if ($p['status'] == 'active'): ?>
                                        <a href="?action=prepare&id=<?php echo $p['id']; ?>&status=<?php echo $status_filter; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                                           class="btn btn-blue btn-xs"
                                           onclick="return confirm('Préparer et délivrer cette prescription ? Le stock sera automatiquement mis à jour.')">
                                            💊 Préparer
                                        </a>
                                        <button type="button" onclick="openRejectModal(<?php echo $p['id']; ?>)" class="btn btn-red btn-xs">✗ Rejeter</button>
                                    <?php elseif ($p['status'] == 'delivered'): ?>
                                        <span style="color:var(--green); font-size:0.7rem;">✓ Délivrée</span>
                                    <?php else: ?>
                                        <span style="color:var(--red); font-size:0.7rem;">✗ Rejetée</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modale de rejet (corrigée avec des IDs uniques) -->
<div id="rejectModalOverlay" class="reject-modal-overlay">
    <div class="reject-modal-content">
        <div class="reject-modal-head">
            <h3>❌ Rejeter la prescription</h3>
            <button onclick="closeRejectModal()" class="reject-modal-close">×</button>
        </div>
        <form method="GET" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="rejectPrescriptionId">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">

            <div class="form-group">
                <label>Motif du rejet *</label>
                <select name="reason" class="input" required>
                    <option value="">-- Sélectionner un motif --</option>
                    <option value="Médicament non disponible">💊 Médicament non disponible</option>
                    <option value="Prescription expirée">📅 Prescription expirée</option>
                    <option value="Information manquante">📝 Information manquante</option>
                    <option value="Ordonnance non lisible">🔍 Ordonnance non lisible</option>
                    <option value="Contre-indication médicale">⚠️ Contre-indication médicale</option>
                    <option value="Autre">❓ Autre</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Confirmer le rejet</button>
                <button type="button" onclick="closeRejectModal()" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal(prescriptionId) {
        document.getElementById('rejectPrescriptionId').value = prescriptionId;
        document.getElementById('rejectModalOverlay').style.display = 'flex';
    }

    function closeRejectModal() {
        document.getElementById('rejectModalOverlay').style.display = 'none';
    }

    // Fermer la modale en cliquant en dehors
    window.onclick = function(e) {
        let modal = document.getElementById('rejectModalOverlay');
        if (e.target === modal) {
            closeRejectModal();
        }
    }
</script>

</body>
</html>