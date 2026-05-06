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

// === TRAITEMENT FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update') {
        $updates = [];

        if (isset($_POST['selling_price']) && $_POST['selling_price'] !== '') {
            $updates['selling_price'] = floatval($_POST['selling_price']);
        }
        if (isset($_POST['expiry_date']) && $_POST['expiry_date'] !== '') {
            $updates['expiry_date'] = $_POST['expiry_date'];
        }
        if (isset($_POST['threshold_alert']) && $_POST['threshold_alert'] !== '') {
            $updates['threshold_alert'] = intval($_POST['threshold_alert']);
        }

        if (!empty($updates)) {
            $result = updateMedicine($database, intval($_POST['medicine_id']), $updates);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Aucune donnée à mettre à jour';
        }
    }
}

// === FILTRES ===
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// === RÉCUPÉRER LES MÉDICAMENTS ===
$medicines = getAllMedicines($database);

// Filtrer par recherche
if ($search) {
    $medicines = array_filter($medicines, function($m) use ($search) {
        return stripos($m['name'], $search) !== false ||
                stripos($m['generic_name'] ?? '', $search) !== false;
    });
}

// Filtrer par statut de stock
if ($status_filter === 'low_stock') {
    $low_stock_ids = array_map(fn($m) => $m['id'], getLowStockMedicines($database));
    $medicines = array_filter($medicines, fn($m) => in_array($m['id'], $low_stock_ids));
}

// Statistiques
$total_medicines = count($medicines);
$low_stock_count = count(array_filter($medicines, function($m) {
    return ($m['current_stock'] ?? 0) <= ($m['threshold_alert'] ?? 10);
}));
$expired_count = count(array_filter($medicines, function($m) {
    return $m['expiry_date'] && $m['expiry_date'] < date('Y-m-d');
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Médicaments — Pharmacie</title>
    <link rel="stylesheet" href="pharmacien.css">
    <style>
        /* ===== STATS MINI ===== */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-bottom: 22px;
        }
        .stat-mini {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 15px;
            text-align: center;
            box-shadow: var(--sh);
        }
        .stat-mini .value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-mini .label {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 5px;
        }
        .stat-mini.total .value { color: var(--blue); }
        .stat-mini.low .value { color: var(--red); }
        .stat-mini.expired .value { color: var(--orange); }

        /* ===== ROW ALERT ===== */
        .row-alert { background-color: var(--red-l); }

        /* ===== MODAL ===== */
        .modal {
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
        .modal-content {
            background-color: var(--surface);
            border-radius: var(--r);
            padding: 24px;
            max-width: 450px;
            width: 90%;
            box-shadow: var(--sh);
        }
        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .modal-head h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text3);
        }
        .modal-close:hover { color: var(--text); }

        /* ===== FORM INLINE ===== */
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

        /* ===== BADGES ===== */
        .badge-red { background: var(--red-l); color: var(--red); }
        .badge-green { background: var(--green-l); color: var(--green); }
        .badge-yellow { background: var(--amber-l); color: var(--amber); }

        /* ===== BUTTONS ===== */
        .btn-xs { padding: 4px 8px; font-size: 0.7rem; }
        .btn-red { background: var(--red-l); color: var(--red); border: 1px solid #FADBD8; }
        .btn-red:hover { background: #FADBD8; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des médicaments</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="medicine-form.php" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nouveau médicament
            </a>
        </div>
    </div>
    <div class="page-body">

        <!-- ALERTES -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- STATISTIQUES RAPIDES -->
        <div class="stats-mini">
            <div class="stat-mini total">
                <div class="value"><?php echo $total_medicines; ?></div>
                <div class="label">Total médicaments</div>
            </div>
            <div class="stat-mini low">
                <div class="value"><?php echo $low_stock_count; ?></div>
                <div class="label">Stock faible</div>
            </div>
            <div class="stat-mini expired">
                <div class="value"><?php echo $expired_count; ?></div>
                <div class="label">Expirés</div>
            </div>
            <div class="stat-mini">
                <div class="value"><?php echo $total_medicines - $low_stock_count - $expired_count; ?></div>
                <div class="label">En stock normal</div>
            </div>
        </div>

        <!-- FILTRES -->
        <div class="card">
            <div class="card-head">
                <h3>🔍 Filtrer les médicaments</h3>
            </div>
            <form method="GET" class="form-inline">
                <div class="form-group">
                    <label>Recherche</label>
                    <input type="text" name="search" class="input" placeholder="Nom, générique..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label>Statut stock</label>
                    <select name="status" class="input">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous</option>
                        <option value="low_stock" <?php echo $status_filter === 'low_stock' ? 'selected' : ''; ?>>Stock faible</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="medicines.php" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>

        <!-- LISTE DES MÉDICAMENTS -->
        <div class="card">
            <div class="card-head">
                <h3>📋 Liste des médicaments</h3>
                <span class="badge badge-blue"><?php echo count($medicines); ?> résultat(s)</span>
            </div>

            <?php if (count($medicines) === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" width="40" height="40">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                    </svg>
                    <h3>Aucun médicament trouvé</h3>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Générique</th>
                            <th>Dosage</th>
                            <th>Forme</th>
                            <th>Stock</th>
                            <th>Alerte</th>
                            <th>Prix</th>
                            <th>Expiration</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($medicines as $med):
                            $stock = $med['current_stock'] ?? 0;
                            $threshold = $med['threshold_alert'] ?? 10;
                            $is_low = $stock <= $threshold;
                            $is_expired = $med['expiry_date'] && $med['expiry_date'] < date('Y-m-d');
                            ?>
                            <tr class="<?php echo $is_low || $is_expired ? 'row-alert' : ''; ?>">
                                <td style="font-weight:600; font-family:monospace"><?php echo $med['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($med['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($med['generic_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($med['strength'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($med['dosage_form'] ?? '—'); ?></td>
                                <td>
                                    <span class="badge <?php echo $is_low ? 'badge-red' : 'badge-green'; ?>">
                                        <?php echo $stock; ?> unités
                                    </span>
                                </td>
                                <td><?php echo $threshold; ?> min</td>
                                <td style="color:var(--green); font-weight:600">
                                    <?php echo number_format($med['selling_price'] ?? 0, 0, ',', ' '); ?> DA
                                </td>
                                <td>
                                    <?php if ($is_expired): ?>
                                        <span class="badge badge-red">EXPIRÉ</span>
                                    <?php elseif ($med['expiry_date']):
                                        $expiry = new DateTime($med['expiry_date']);
                                        $today = new DateTime();
                                        $diff = $today->diff($expiry)->days;
                                        ?>
                                        <?php if ($diff < 30): ?>
                                        <span class="badge badge-yellow"><?php echo $diff; ?> j</span>
                                    <?php else: ?>
                                        <?php echo date('d/m/Y', strtotime($med['expiry_date'])); ?>
                                    <?php endif; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="tbl-actions">
                                        <button onclick="openMedicineModal(
                                        <?php echo $med['id']; ?>,
                                                '<?php echo addslashes($med['name']); ?>',
                                        <?php echo $med['selling_price'] ?? 0; ?>,
                                                '<?php echo $med['expiry_date']; ?>',
                                        <?php echo $med['threshold_alert'] ?? 10; ?>
                                                )" class="btn btn-secondary btn-xs">✏️ Éditer</button>
                                        <a href="stock_movements.php?medicine_id=<?php echo $med['id']; ?>" class="btn btn-blue btn-xs">📊 Mouvements</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- MODAL D'ÉDITION -->
<div id="medicineModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h3>✏️ Modifier le médicament</h3>
            <button onclick="closeMedicineModal()" class="modal-close">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="medicine_id" id="modalMedicineId">

            <div class="form-group">
                <label>Nom du médicament</label>
                <input type="text" id="modalMedicineName" class="input" readonly style="background: var(--surf2);">
            </div>
            <div class="form-group">
                <label>Prix de vente (DA)</label>
                <input type="number" id="modalSellingPrice" name="selling_price" class="input" step="1" min="0">
            </div>
            <div class="form-group">
                <label>Date d'expiration</label>
                <input type="date" id="modalExpiry" name="expiry_date" class="input">
            </div>
            <div class="form-group">
                <label>Seuil d'alerte stock</label>
                <input type="number" id="modalThresholdAlert" name="threshold_alert" class="input" min="0">
                <small style="font-size: 0.65rem; color: var(--text2);">
                    Alerte lorsque le stock est inférieur ou égal à ce seuil
                </small>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                <button type="button" onclick="closeMedicineModal()" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openMedicineModal(id, name, sellingPrice, expiryDate, thresholdAlert) {
        document.getElementById('modalMedicineId').value = id;
        document.getElementById('modalMedicineName').value = name;
        document.getElementById('modalSellingPrice').value = sellingPrice;
        document.getElementById('modalExpiry').value = expiryDate;
        document.getElementById('modalThresholdAlert').value = thresholdAlert;
        document.getElementById('medicineModal').style.display = 'flex';
    }

    function closeMedicineModal() {
        document.getElementById('medicineModal').style.display = 'none';
    }

    window.onclick = function(e) {
        let modal = document.getElementById('medicineModal');
        if (e.target === modal) closeMedicineModal();
    }
</script>

</body>
</html>