<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  beds.php — Gestion des lits
//  ✔ Capacité par salle depuis tables GMG (rooms / room_types)
//  ✔ Règles VIP (1), Garde Malade (2), Standard (capacité)
//  ✔ Résumé capacité temps réel dans modal d'assignation
//  ✔ Double vue : tableau liste + cartes par salle
// ================================================================
require_once 'functions.php';
require_once 'bed_functions.php';
requireReceptionniste();
include '../connection.php';

$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type']   ?? '';
$filter_room   = trim($_GET['room'] ?? '');
$view_mode     = $_GET['view'] ?? 'table'; // 'table' ou 'rooms'

// ── Requête principale des lits ──────────────────────────────────
$sql = "SELECT b.id, b.room_number, b.bed_number, b.bed_type, b.status,
               b.patient_id, b.admission_date,
               u.full_name  patient_name,
               u.phone      patient_phone,
               pt.uhid      patient_uhid,
               pt.allergies patient_allergies,
               pt.blood_type patient_blood_type,
               (SELECT COUNT(*) FROM cleaning_tasks ct
                WHERE ct.bed_id = b.id AND ct.status = 'pending') AS pending_cleaning
        FROM bed_management b
        LEFT JOIN patients pt ON pt.id = b.patient_id
        LEFT JOIN users u     ON u.id  = pt.user_id
        WHERE 1=1";

$params = [];
$types  = '';
if ($filter_status) { $sql .= ' AND b.status = ?';         $params[] = $filter_status; $types .= 's'; }
if ($filter_type)   { $sql .= ' AND b.bed_type = ?';       $params[] = $filter_type;   $types .= 's'; }
if ($filter_room)   { $sql .= ' AND b.room_number LIKE ?'; $params[] = '%' . $filter_room . '%'; $types .= 's'; }
$sql .= ' ORDER BY b.room_number ASC, b.bed_number ASC';

$stmt = $database->prepare($sql);
if (!$stmt) die('Erreur BD: ' . htmlspecialchars($database->error));
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$beds_result = $stmt->get_result();

// Charger tous les lits en tableau
$all_beds = [];
while ($b = $beds_result->fetch_assoc()) $all_beds[] = $b;

// Capacité de toutes les salles
$rooms_capacity = getAllRoomsCapacity($database);

// Statistiques globales
$stats = getBedStats($database);

// Patients disponibles (fallback sans AJAX)
$patients_list = getAvailablePatientsForBed($database);

// Types de lits distincts
$types_res = $database->query("SELECT DISTINCT bed_type FROM bed_management ORDER BY bed_type");
$bed_types  = [];
if ($types_res) while ($t = $types_res->fetch_assoc()) $bed_types[] = $t['bed_type'];

// Grouper par salle pour vue cartes
$beds_by_room = [];
foreach ($all_beds as $b) {
    $beds_by_room[$b['room_number']][] = $b;
}

// Index capacité par salle O(1)
$rc_idx = [];
foreach ($rooms_capacity as $rc) $rc_idx[$rc['room_number']] = $rc;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestion des lits</title>
    <link rel="stylesheet" href="recept.css">
    <style>
        /* ── Statuts lits ── */
        .bed-status-available   { background:var(--green-l); color:var(--green); border:1px solid #C3E8D6; }
        .bed-status-occupied    { background:var(--red-l);   color:var(--red);   border:1px solid #FADBD8; }
        .bed-status-cleaning    { background:var(--amber-l); color:var(--amber); border:1px solid #F9E4B0; }
        .bed-status-reserved    { background:var(--blue-l);  color:var(--blue);  border:1px solid #C5DEF5; }
        .bed-status-maintenance { background:var(--surf2);   color:var(--text2); border:1px solid var(--border); }

        .bed-indicator { display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:4px;flex-shrink:0; }
        .ind-available   { background:var(--green); }
        .ind-occupied    { background:var(--red); }
        .ind-cleaning    { background:var(--amber); }
        .ind-reserved    { background:var(--blue); }
        .ind-maintenance { background:var(--text3); }

        /* ── Barre de capacité ── */
        .cap-bar  { height:7px;border-radius:4px;background:var(--surf2);overflow:hidden;margin-top:5px; }
        .cap-fill { height:100%;background:var(--green);border-radius:4px;transition:width .5s cubic-bezier(.4,0,.2,1); }
        .cap-fill.warn { background:var(--amber); }
        .cap-fill.crit { background:var(--red); }

        /* ── Cartes par salle ── */
        .room-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;margin-top:4px; }
        .room-card { background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden; }
        .room-card-head { padding:14px 18px 12px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:10px; }
        .room-card-number { font-family:'DM Mono',monospace;font-size:1.1rem;font-weight:700;color:var(--text); }
        .room-card-type { font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:2px 8px;border-radius:20px;margin-top:2px;display:inline-block; }
        .rtype-vip          { background:#f3e8ff;color:#7c3aed; }
        .rtype-prive        { background:#dbeafe;color:#1d4ed8; }
        .rtype-garde_malade { background:#fef3c7;color:#92400e; }
        .rtype-standard     { background:var(--green-l);color:var(--green); }
        .rtype-default      { background:var(--surf2);color:var(--text2); }
        .room-cap-label { font-size:.73rem;color:var(--text2);margin-top:8px;display:flex;align-items:center;justify-content:space-between; }
        .room-cap-nums  { font-family:'DM Mono',monospace;font-weight:700;font-size:.78rem; }
        .room-cap-nums .occ { color:var(--red); }
        .room-cap-nums .avl { color:var(--green); }
        .room-rule-badge { display:inline-flex;align-items:center;gap:4px;font-size:.64rem;font-weight:700;padding:2px 7px;border-radius:20px;margin-top:5px; }
        .rule-vip   { background:#f3e8ff;color:#7c3aed;border:1px solid #ddd6fe; }
        .rule-garde { background:#fef3c7;color:#92400e;border:1px solid #fde68a; }
        .rule-full  { background:var(--red-l);color:var(--red);border:1px solid #FADBD8; }
        .room-beds    { padding:10px 12px;display:flex;flex-wrap:wrap;gap:7px; }
        .bed-chip { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:.74rem;font-weight:500;cursor:default;transition:all .15s; }
        .bed-chip.available { background:var(--green-l);color:var(--green);border:1px solid #C3E8D6;cursor:pointer; }
        .bed-chip.available:hover { background:#C3E8D6;transform:translateY(-1px);box-shadow:0 2px 8px rgba(26,107,74,.15); }
        .bed-chip.occupied    { background:var(--red-l);  color:var(--red);  border:1px solid #FADBD8; }
        .bed-chip.cleaning    { background:var(--amber-l);color:var(--amber);border:1px solid #F9E4B0; }
        .bed-chip.reserved    { background:var(--blue-l); color:var(--blue); border:1px solid #C5DEF5; }
        .bed-chip.maintenance { background:var(--surf2);  color:var(--text2);border:1px solid var(--border); }
        .room-actions { padding:10px 12px;border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap; }

        /* ── Bascule de vue ── */
        .view-toggle { display:flex;border:1.5px solid var(--border);border-radius:var(--rs);overflow:hidden;background:var(--surface); }
        .view-btn { padding:7px 14px;font-size:.76rem;font-weight:600;cursor:pointer;background:transparent;color:var(--text2);border:none;font-family:inherit;display:flex;align-items:center;gap:5px;transition:.15s; }
        .view-btn svg { width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2; }
        .view-btn.active { background:var(--green);color:#fff; }
        .view-btn:not(.active):hover { background:var(--surf2); }

        /* ── Modal ── */
        .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:16px; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--surface);border-radius:var(--r);box-shadow:0 12px 48px rgba(0,0,0,.22);width:min(560px,100%);max-height:90vh;overflow-y:auto;padding:28px;animation:slideUp .2s ease; }
        @keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
        .modal-title    { font-size:1rem;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:9px; }
        .modal-title svg{ width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2; }
        .modal-subtitle { font-size:.8rem;color:var(--text2);margin-bottom:20px; }
        .modal-actions  { display:flex;gap:8px;margin-top:22px;justify-content:flex-end; }
        .modal-cap-box  { background:var(--surf2);border-radius:var(--rs);padding:12px 14px;margin-bottom:16px;font-size:.78rem; }
        .modal-cap-box.warn { background:var(--amber-l);border:1px solid #F9E4B0; }
        .modal-cap-box.crit { background:var(--red-l);border:1px solid #FADBD8; }
        .modal-cap-title { font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px; }
        .modal-cap-row   { display:flex;justify-content:space-between;margin-top:4px;color:var(--text2); }
        .modal-cap-val   { font-family:'DM Mono',monospace;font-weight:700; }

        /* ── Légende ── */
        .legend { display:flex;gap:14px;flex-wrap:wrap;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:var(--rs);font-size:.72rem;margin-bottom:16px; }
        .legend-item { display:flex;align-items:center;gap:5px;color:var(--text2);font-weight:500; }

        /* ── Source tag ── */
        .src-tag { display:inline-flex;align-items:center;gap:4px;font-size:.62rem;padding:1px 6px;border-radius:10px;background:var(--blue-l);color:var(--blue);font-weight:600; }
        .src-tag.fallback { background:var(--surf2);color:var(--text3); }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des lits</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body">

        <!-- ── MESSAGE D'ALERTE ── -->
        <?php if (isset($_GET['msg'])): ?>
            <?php
            $raw_detail = htmlspecialchars($_GET['detail'] ?? '');
            $mmap = [
                'assigned'      => ['success', 'Lit assigné avec succès.'],
                'released'      => ['success', 'Lit libéré. Tâche de nettoyage créée automatiquement.'],
                'cleaned'       => ['success', 'Lit marqué disponible après nettoyage.'],
                'err_occ'       => ['error',   'Ce lit est déjà occupé.'],
                'err_free'      => ['error',   'Ce lit est déjà libre.'],
                'err_pid'       => ['error',   'Patient introuvable.'],
                'err_bid'       => ['error',   'Lit introuvable.'],
                'err_db'        => ['error',   'Erreur de base de données. Opération annulée.'],
                'err_val'       => ['error',   'Données invalides.'],
                'err_cap_full'  => ['error',   'Capacité de la salle dépassée.'],
                'err_cap_vip'   => ['error',   '🏆 Règle VIP : chambre strictement 1 patient.'],
                'err_cap_garde' => ['error',   '👨‍⚕️ Règle Garde Malade : maximum 2 occupants.'],
                'err_already'   => ['warning', 'Ce patient est déjà assigné à un lit.'],
            ];
            $m = $mmap[$_GET['msg']] ?? ['warning', htmlspecialchars($_GET['msg'])];
            $detail_str = ($raw_detail && $raw_detail !== $m[1])
                ? " <em style='opacity:.8'>({$raw_detail})</em>"
                : '';
            ?>
            <div class="alert alert-<?php echo $m[0]; ?>">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo $m[1] . $detail_str; ?>
            </div>
        <?php endif; ?>

        <!-- ── STATISTIQUES ── -->
        <div class="stats" style="margin-bottom:18px">
            <div class="stat">
                <div class="stat-ico g"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></div>
                <div>
                    <div class="stat-num"><?php echo (int)$stats['total']; ?></div>
                    <div class="stat-lbl">Lits total</div>
                    <?php $pct_global = $stats['total'] > 0 ? round($stats['occupied'] / $stats['total'] * 100) : 0; ?>
                    <div class="cap-bar" style="width:100px">
                        <div class="cap-fill <?php echo $pct_global > 85 ? 'crit' : ($pct_global > 60 ? 'warn' : ''); ?>"
                             style="width:<?php echo $pct_global; ?>%"></div>
                    </div>
                    <div style="font-size:.64rem;color:var(--text3);margin-top:2px"><?php echo $pct_global; ?>% occupés</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico g"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div><div class="stat-num"><?php echo (int)$stats['available']; ?></div><div class="stat-lbl">Disponibles</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico r"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                <div><div class="stat-num"><?php echo (int)$stats['occupied']; ?></div><div class="stat-lbl">Occupés</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico a"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11H3z"/></svg></div>
                <div><div class="stat-num"><?php echo (int)$stats['cleaning']; ?></div><div class="stat-lbl">En nettoyage</div></div>
            </div>
            <div class="stat">
                <div class="stat-ico b"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg></div>
                <div><div class="stat-num"><?php echo count($rooms_capacity); ?></div><div class="stat-lbl">Salles</div></div>
            </div>
        </div>

        <!-- ── LÉGENDE ── -->
        <div class="legend">
            <strong style="color:var(--text);font-size:.72rem">Statuts :</strong>
            <?php foreach (BED_STATUS_LABELS as $k => $l): ?>
                <span class="legend-item">
          <span class="bed-indicator ind-<?php echo $k; ?>" style="width:9px;height:9px"></span>
          <?php echo $l; ?>
        </span>
            <?php endforeach; ?>
            <span style="flex:1"></span>
            <span class="legend-item" style="gap:8px;font-size:.65rem">
        <span>🏆 VIP = 1 patient</span>
        <span>👨‍⚕️ Garde Malade ≤ 2</span>
        <span>🏨 Standard = capacité GMG</span>
      </span>
        </div>

        <!-- ── FILTRES + BASCULE VUE ── -->
        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px">
            <form method="GET" class="filter-bar" style="flex:1;margin-bottom:0">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
                <label>Salle :</label>
                <input class="input" type="text" name="room"
                       value="<?php echo htmlspecialchars($filter_room); ?>"
                       placeholder="Ex : 101" style="width:100px">
                <label>Type :</label>
                <select class="input" name="type" style="width:155px">
                    <option value="">Tous les types</option>
                    <?php foreach ($bed_types as $bt): ?>
                        <option value="<?php echo htmlspecialchars($bt); ?>"
                            <?php echo $filter_type === $bt ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(BED_TYPE_LABELS[$bt] ?? ucfirst($bt)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Statut :</label>
                <select class="input" name="status" style="width:145px">
                    <option value="">Tous</option>
                    <?php foreach (BED_STATUS_LABELS as $v => $l): ?>
                        <option value="<?php echo $v; ?>" <?php echo $filter_status === $v ? 'selected' : ''; ?>>
                            <?php echo $l; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
                <a href="beds.php?view=<?php echo htmlspecialchars($view_mode); ?>" class="btn btn-secondary btn-sm">Réinitialiser</a>
            </form>

            <div class="view-toggle">
                <button type="button" class="view-btn <?php echo $view_mode === 'table' ? 'active' : ''; ?>"
                        onclick="window.location='beds.php?view=table&room=<?php echo urlencode($filter_room); ?>&type=<?php echo urlencode($filter_type); ?>&status=<?php echo urlencode($filter_status); ?>'">
                    <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    Liste
                </button>
                <button type="button" class="view-btn <?php echo $view_mode === 'rooms' ? 'active' : ''; ?>"
                        onclick="window.location='beds.php?view=rooms&room=<?php echo urlencode($filter_room); ?>&type=<?php echo urlencode($filter_type); ?>&status=<?php echo urlencode($filter_status); ?>'">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    Par salle
                </button>
            </div>
        </div>

        <?php if ($view_mode === 'rooms'): ?>
            <!-- ════════════════ VUE CARTES PAR SALLE ════════════════ -->
            <?php if (empty($beds_by_room)): ?>
                <div class="card"><div class="empty">
                        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/></svg>
                        <h3>Aucun lit trouvé</h3>
                        <p>Modifiez les filtres ou vérifiez la table <code>bed_management</code>.</p>
                    </div></div>
            <?php else: ?>
                <div class="room-grid">
                    <?php foreach ($beds_by_room as $room_num => $room_beds):
                        $rc    = $rc_idx[$room_num] ?? null;
                        $pct_r = ($rc && $rc['capacity'] > 0)
                            ? round($rc['occupied'] / $rc['capacity'] * 100) : 0;
                        $type_code = $rc['room_type_code'] ?? 'standard';
                        $type_css  = 'rtype-' . $type_code;
                        ?>
                        <div class="room-card">
                            <div class="room-card-head">
                                <div>
                                    <div class="room-card-number">Salle <?php echo htmlspecialchars($room_num); ?></div>
                                    <?php if ($rc): ?>
                                        <span class="room-card-type <?php echo htmlspecialchars($type_css); ?>">
                <?php echo htmlspecialchars($rc['room_type']); ?>
              </span>
                                        <?php if ($rc['source'] === 'gmg'): ?>
                                            <span class="src-tag" title="Données du Gestionnaire des Moyens Généraux">GMG</span>
                                        <?php else: ?>
                                            <span class="src-tag fallback" title="Capacité basée sur le nombre de lits">auto</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($rc): ?>
                                    <div style="text-align:right;min-width:90px">
                                        <div class="room-cap-label">
                                            <span>Occupation</span>
                                            <span class="room-cap-nums">
                <span class="occ"><?php echo $rc['occupied']; ?></span>
                <span style="color:var(--text3)">/</span>
                <span><?php echo $rc['capacity']; ?></span>
              </span>
                                        </div>
                                        <div class="cap-bar">
                                            <div class="cap-fill <?php echo $pct_r > 85 ? 'crit' : ($pct_r > 60 ? 'warn' : ''); ?>"
                                                 style="width:<?php echo $pct_r; ?>%"></div>
                                        </div>
                                        <?php if ($rc['is_vip']): ?>
                                            <div><span class="room-rule-badge rule-vip">🏆 VIP</span></div>
                                        <?php elseif ($rc['is_garde_malade']): ?>
                                            <div><span class="room-rule-badge rule-garde">👨‍⚕️ Garde</span></div>
                                        <?php endif; ?>
                                        <?php if ($rc['available'] <= 0): ?>
                                            <div><span class="room-rule-badge rule-full">🚫 Complète</span></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="room-beds">
                                <?php foreach ($room_beds as $b):
                                    $can_assign_chip = !$rc || $rc['available'] > 0;
                                    ?>
                                    <div class="bed-chip <?php echo htmlspecialchars($b['status']); ?>"
                                        <?php if ($b['status'] === 'available' && $can_assign_chip): ?>
                                            onclick="openAssignModal(<?php echo $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($room_num)); ?>', '<?php echo htmlspecialchars(addslashes($b['bed_number'])); ?>')"
                                            title="Cliquer pour assigner un patient"
                                        <?php elseif ($b['status'] === 'occupied'): ?>
                                            title="<?php echo htmlspecialchars($b['patient_name'] ?? 'Occupé'); ?>"
                                        <?php endif; ?>>
            <span class="bed-chip-ico">
              <?php echo match($b['status']) {
                  'available'   => '🛏',
                  'occupied'    => '🔴',
                  'cleaning'    => '🧹',
                  'reserved'    => '📌',
                  'maintenance' => '🔧',
                  default       => '❓',
              }; ?>
            </span>
                                        <span>Lit <?php echo htmlspecialchars($b['bed_number']); ?></span>
                                        <?php if ($b['status'] === 'occupied' && $b['patient_name']): ?>
                                            <span style="font-size:.65rem;opacity:.75;max-width:70px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                — <?php echo htmlspecialchars(mb_substr($b['patient_name'], 0, 12)); ?>
              </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php
                            // Actions disponibles dans cette salle
                            $has_occupied = false;
                            $has_cleaning = false;
                            foreach ($room_beds as $b) {
                                if ($b['status'] === 'occupied') $has_occupied = true;
                                if ($b['status'] === 'cleaning') $has_cleaning = true;
                            }
                            if ($has_occupied || $has_cleaning): ?>
                                <div class="room-actions">
                                    <?php if ($has_cleaning): ?>
                                        <?php foreach ($room_beds as $b): if ($b['status'] === 'cleaning'): ?>
                                            <a href="cleaning-done.php?id=<?php echo $b['id']; ?>"
                                               onclick="return confirm('Marquer lit <?php echo htmlspecialchars($b['bed_number']); ?> comme nettoyé ?')"
                                               class="btn btn-primary btn-sm">✔ Lit <?php echo htmlspecialchars($b['bed_number']); ?> nettoyé</a>
                                        <?php endif; endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- ════════════════ VUE TABLEAU ════════════════ -->
            <div class="card">
                <div class="card-head">
                    <h3>Tous les lits</h3>
                    <span style="font-size:.76rem;color:var(--text2)"><?php echo count($all_beds); ?> lit(s)</span>
                </div>

                <?php if (empty($all_beds)): ?>
                    <div class="empty">
                        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/></svg>
                        <h3>Aucun lit trouvé</h3>
                        <p>Modifiez les filtres ou vérifiez la table <code>bed_management</code>.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto">
                        <table class="tbl">
                            <thead><tr>
                                <th>Salle</th><th>Capacité</th><th>Lit</th><th>Type</th>
                                <th>Statut</th><th>Patient</th><th>Admission</th><th>Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($all_beds as $b):
                                $rc = $rc_idx[$b['room_number']] ?? null;
                                ?>
                                <tr>
                                    <td style="font-family:'DM Mono',monospace;font-weight:700">
                                        <?php echo htmlspecialchars($b['room_number']); ?>
                                    </td>
                                    <td style="font-size:.75rem">
                                        <?php if ($rc): ?>
                                            <div style="display:flex;align-items:center;gap:7px">
                                                <span style="font-family:'DM Mono',monospace;font-weight:600;color:var(--red)"><?php echo $rc['occupied']; ?></span>
                                                <span style="color:var(--text3)">/</span>
                                                <span style="font-family:'DM Mono',monospace;font-weight:600"><?php echo $rc['capacity']; ?></span>
                                                <?php $p = $rc['capacity'] > 0 ? round($rc['occupied'] / $rc['capacity'] * 100) : 0; ?>
                                                <div class="cap-bar" style="width:50px;display:inline-block">
                                                    <div class="cap-fill <?php echo $p > 85 ? 'crit' : ($p > 60 ? 'warn' : ''); ?>"
                                                         style="width:<?php echo $p; ?>%"></div>
                                                </div>
                                            </div>
                                            <?php if ($rc['is_vip']): ?>
                                                <span class="room-rule-badge rule-vip" style="font-size:.6rem">🏆 VIP</span>
                                            <?php elseif ($rc['is_garde_malade']): ?>
                                                <span class="room-rule-badge rule-garde" style="font-size:.6rem">👨‍⚕️ Garde</span>
                                            <?php endif; ?>
                                        <?php else: echo '<span style="color:var(--text3)">—</span>'; endif; ?>
                                    </td>
                                    <td style="font-family:'DM Mono',monospace"><?php echo htmlspecialchars($b['bed_number']); ?></td>
                                    <td style="font-size:.78rem">
                                        <?php echo htmlspecialchars(BED_TYPE_LABELS[$b['bed_type']] ?? $b['bed_type']); ?>
                                    </td>
                                    <td>
            <span class="badge bed-status-<?php echo htmlspecialchars($b['status']); ?>">
              <span class="bed-indicator ind-<?php echo htmlspecialchars($b['status']); ?>"></span>
              <?php echo BED_STATUS_LABELS[$b['status']] ?? htmlspecialchars($b['status']); ?>
            </span>
                                        <?php if ((int)$b['pending_cleaning'] > 0): ?>
                                            <span class="badge badge-pending" style="margin-left:4px;font-size:.61rem">🧹 Nettoyage</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b['patient_name']): ?>
                                            <div style="font-weight:600;font-size:.82rem"><?php echo htmlspecialchars($b['patient_name']); ?></div>
                                            <div style="font-size:.68rem;color:var(--text2);font-family:'DM Mono',monospace">
                                                <?php echo htmlspecialchars($b['patient_uhid']); ?>
                                            </div>
                                            <?php if ($b['patient_allergies']): ?>
                                                <span style="font-size:.62rem;background:var(--red-l);color:var(--red);padding:1px 5px;border-radius:10px">⚠ Allergie</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text3);font-size:.78rem">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:.73rem;color:var(--text2)">
                                        <?php echo $b['admission_date']
                                            ? date('d/m/Y H:i', strtotime($b['admission_date']))
                                            : '—'; ?>
                                    </td>
                                    <td>
                                        <div class="tbl-actions">
                                            <?php if ($b['status'] === 'available'):
                                                $can_assign = !$rc || $rc['available'] > 0;
                                                ?>
                                                <?php if ($can_assign): ?>
                                                <button class="btn btn-blue btn-sm"
                                                        onclick="openAssignModal(<?php echo $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['room_number'])); ?>', '<?php echo htmlspecialchars(addslashes($b['bed_number'])); ?>')">
                                                    🛏 Assigner
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bed-status-maintenance"
                                                      title="<?php echo htmlspecialchars($rc['rule_msg'] ?: 'Salle complète'); ?>">
                    🚫 Salle pleine
                  </span>
                                            <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if ($b['status'] === 'occupied'): ?>
                                                <a href="release-bed.php?id=<?php echo $b['id']; ?>"
                                                   onclick="return confirm('Libérer ce lit et créer une tâche de nettoyage ?')"
                                                   class="btn btn-amber btn-sm">🔓 Libérer</a>
                                            <?php endif; ?>

                                            <?php if ($b['status'] === 'cleaning'): ?>
                                                <a href="cleaning-done.php?id=<?php echo $b['id']; ?>"
                                                   onclick="return confirm('Marquer le nettoyage comme terminé ?')"
                                                   class="btn btn-primary btn-sm">✔ Nettoyé</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; // fin vue tableau ?>

    </div>
</div>

<!-- ════════════════ MODAL : ASSIGNER UN LIT ════════════════ -->
<div class="modal-overlay" id="assignModal">
    <div class="modal-box">
        <div class="modal-title">
            <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            Assigner un lit
        </div>
        <div class="modal-subtitle">
            Salle <strong id="modal-room"></strong> — Lit <strong id="modal-bed"></strong>
        </div>

        <!-- Capacité temps réel -->
        <div class="modal-cap-box" id="modalCapBox">
            <div class="modal-cap-title" id="modalCapTitle">
                <svg viewBox="0 0 24 24" width="14" height="14" style="stroke:currentColor;fill:none;stroke-width:2">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                </svg>
                Capacité de la salle
            </div>
            <div style="color:var(--text2)">
                <div class="modal-cap-row">
                    <span>Capacité totale</span>
                    <span class="modal-cap-val" id="cap-total">…</span>
                </div>
                <div class="modal-cap-row">
                    <span>Lits occupés</span>
                    <span class="modal-cap-val" id="cap-occ" style="color:var(--red)">…</span>
                </div>
                <div class="modal-cap-row">
                    <span>Disponibles</span>
                    <span class="modal-cap-val" id="cap-avl" style="color:var(--green)">…</span>
                </div>
                <div class="cap-bar" style="margin-top:8px">
                    <div class="cap-fill" id="cap-bar-fill" style="width:0%"></div>
                </div>
            </div>
            <div id="modalRuleMsg" style="margin-top:8px;font-size:.72rem;font-weight:600;display:none"></div>
        </div>

        <!-- Alerte salle pleine -->
        <div id="modalAlert" style="display:none;margin-bottom:14px"></div>

        <form method="POST" action="assign-bed.php" id="assignForm">
            <input type="hidden" name="bed_id"     id="modal-bed-id">
            <input type="hidden" name="patient_id" id="selected-patient-id">

            <div class="form-group" style="margin-bottom:14px">
                <label>Rechercher un patient <span class="req">*</span></label>
                <input class="input" type="text" id="patSearch" autocomplete="off"
                       placeholder="Nom, téléphone, UHID…">
                <div id="patResults"
                     style="border:1px solid var(--border);border-radius:var(--rs);background:var(--surface);
                    display:none;max-height:220px;overflow-y:auto;margin-top:4px;box-shadow:var(--sh)">
                </div>
                <div id="selected-patient-info"
                     style="display:none;margin-top:8px;padding:9px 12px;background:var(--green-l);
                    border-radius:var(--rs);font-size:.78rem;color:var(--green)">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Annuler</button>
                <button type="submit" class="btn btn-primary" id="assignSubmitBtn" disabled>
                    🛏 Confirmer l'assignation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    /* ═══════════════════════════════════
       MODAL
    ═══════════════════════════════════ */
    function openAssignModal(bedId, room, bed) {
        document.getElementById('modal-bed-id').value         = bedId;
        document.getElementById('modal-room').textContent     = room;
        document.getElementById('modal-bed').textContent      = bed;
        document.getElementById('selected-patient-id').value  = '';
        document.getElementById('selected-patient-info').style.display = 'none';
        document.getElementById('patSearch').value            = '';
        document.getElementById('patResults').style.display   = 'none';
        document.getElementById('assignSubmitBtn').disabled   = true;
        document.getElementById('modalAlert').style.display   = 'none';
        document.getElementById('assignModal').classList.add('open');
        loadRoomCapacity(room);
    }

    function closeAssignModal() {
        document.getElementById('assignModal').classList.remove('open');
    }

    document.getElementById('assignModal').addEventListener('click', function(e) {
        if (e.target === this) closeAssignModal();
    });

    /* ═══════════════════════════════════
       CAPACITÉ SALLE EN TEMPS RÉEL
    ═══════════════════════════════════ */
    function loadRoomCapacity(room) {
        const box       = document.getElementById('modalCapBox');
        const ruleDiv   = document.getElementById('modalRuleMsg');
        const alert     = document.getElementById('modalAlert');
        const submitBtn = document.getElementById('assignSubmitBtn');
        const srch      = document.getElementById('patSearch');

        box.className = 'modal-cap-box';
        ruleDiv.style.display = 'none';
        alert.style.display   = 'none';

        ['cap-total','cap-occ','cap-avl'].forEach(id =>
            document.getElementById(id).textContent = '…'
        );
        document.getElementById('cap-bar-fill').style.width = '0%';

        fetch('room_capacity_ajax.php?room=' + encodeURIComponent(room))
            .then(r => r.json())
            .then(d => {
                document.getElementById('cap-total').textContent = d.capacity ?? '—';
                document.getElementById('cap-occ').textContent   = d.occupied ?? '—';
                document.getElementById('cap-avl').textContent   = d.available ?? '—';

                const pct  = d.pct ?? 0;
                const fill = document.getElementById('cap-bar-fill');
                fill.style.width = pct + '%';
                fill.className   = 'cap-fill' + (pct > 85 ? ' crit' : pct > 60 ? ' warn' : '');

                if (d.is_vip) {
                    box.className = 'modal-cap-box warn';
                    ruleDiv.textContent   = '🏆 Chambre VIP — 1 patient strictement autorisé. Pas de partage.';
                    ruleDiv.style.display = 'block';
                    ruleDiv.style.color   = '#7c3aed';
                } else if (d.is_garde_malade) {
                    box.className = 'modal-cap-box warn';
                    ruleDiv.textContent   = '👨‍⚕️ Chambre Garde Malade — Maximum 2 occupants.';
                    ruleDiv.style.display = 'block';
                    ruleDiv.style.color   = '#92400e';
                }

                if (!d.can_assign) {
                    box.className = 'modal-cap-box crit';
                    alert.innerHTML = `<div class="alert alert-error" style="margin:0">
          <svg viewBox="0 0 24 24" width="15" height="15">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <strong>Salle complète.</strong> ${d.rule_msg || 'Aucun lit disponible dans cette salle.'}
        </div>`;
                    alert.style.display   = 'block';
                    submitBtn.disabled    = true;
                    srch.disabled         = true;
                    srch.placeholder      = 'Salle complète — aucune assignation possible';
                } else {
                    srch.disabled    = false;
                    srch.placeholder = 'Nom, téléphone, UHID…';
                }
            })
            .catch(() => {
                ['cap-total','cap-occ','cap-avl'].forEach(id =>
                    document.getElementById(id).textContent = 'N/A'
                );
            });
    }

    /* ═══════════════════════════════════
       RECHERCHE PATIENT EN DIRECT
    ═══════════════════════════════════ */
    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    let searchTimer;
    document.getElementById('patSearch').addEventListener('input', function() {
        clearTimeout(searchTimer);
        const q   = this.value.trim();
        const box = document.getElementById('patResults');
        if (q.length < 2) { box.style.display = 'none'; return; }

        searchTimer = setTimeout(() => {
            fetch('search_ajax.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.length) { box.style.display = 'none'; return; }
                    box.innerHTML = data.map(p => `
          <div class="pat-result"
               data-id="${p.id}"
               data-name="${escHtml(p.full_name)}"
               data-uhid="${escHtml(p.uhid)}"
               data-phone="${escHtml(p.phone || '')}"
               data-blood="${escHtml(p.blood_type || '')}"
               data-allergy="${escHtml(p.allergies || '')}"
               style="padding:9px 13px;cursor:pointer;border-bottom:1px solid var(--border);font-size:.81rem;transition:.1s"
               onmouseover="this.style.background='var(--surf2)'"
               onmouseout="this.style.background=''">
            <strong>${escHtml(p.full_name)}</strong>
            <span style="font-family:'DM Mono',monospace;font-size:.69rem;color:var(--text3);margin-left:6px">
              ${escHtml(p.uhid)}
            </span>
            <div style="font-size:.7rem;color:var(--text2);margin-top:2px">
              ${p.phone ? escHtml(p.phone) : ''}
              ${p.blood_type ? ' · 🩸 ' + escHtml(p.blood_type) : ''}
              ${p.age != null ? ' · ' + p.age + ' ans' : ''}
              ${p.allergies ? ' · <span style="color:var(--red);font-weight:700">⚠ Allergie</span>' : ''}
            </div>
          </div>`).join('');
                    box.style.display = 'block';

                    box.querySelectorAll('.pat-result').forEach(el => {
                        el.addEventListener('click', function() {
                            const pid   = this.dataset.id;
                            const pname = this.dataset.name;
                            const puhid = this.dataset.uhid;
                            const pph   = this.dataset.phone;
                            const pbl   = this.dataset.blood;
                            const pal   = this.dataset.allergy;

                            document.getElementById('selected-patient-id').value = pid;
                            document.getElementById('patSearch').value = pname;
                            box.style.display = 'none';

                            const info = document.getElementById('selected-patient-info');
                            info.innerHTML = `✔ <strong>${escHtml(pname)}</strong>
              <span style="font-family:'DM Mono',monospace;font-size:.73rem;margin-left:4px">${escHtml(puhid)}</span>
              ${pph ? '· 📞 ' + escHtml(pph) : ''}
              ${pbl ? '· 🩸 ' + escHtml(pbl) : ''}
              ${pal ? '<br><span style="color:var(--red);font-weight:700;font-size:.74rem">⚠ Allergie : ' + escHtml(pal) + '</span>' : ''}`;
                            info.style.display = 'block';

                            // Activer le bouton uniquement si la salle n'est pas pleine
                            const alertBox = document.getElementById('modalAlert');
                            if (alertBox.style.display === 'none') {
                                document.getElementById('assignSubmitBtn').disabled = false;
                            }
                        });
                    });
                })
                .catch(() => { box.style.display = 'none'; });
        }, 280);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#patSearch') && !e.target.closest('#patResults')) {
            document.getElementById('patResults').style.display = 'none';
        }
    });
</script>
</body>
</html>