<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  appointments.php — File d'attente du jour
//  ✅ Accès : appointments, patients, doctors
//  ❌ Aucun accès : invoices, payments, facturation
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$today       = date('Y-m-d');
$filter_date = $_GET['date']   ?? $today;
$filter_stat = $_GET['status'] ?? '';
$filter_doc  = intval($_GET['doctor_id'] ?? 0);

$all_docs = getDoctors($database);

$sql = "SELECT a.id, a.status, a.type, a.appointment_time, a.priority, a.reason,
               u_p.full_name patient_name, u_p.phone,
               u_d.full_name doctor_name, d.room_number, d.id doctor_id,
               pt.allergies, pt.blood_type, pt.id patient_db_id
        FROM appointments a
        INNER JOIN patients pt ON pt.id = a.patient_id
        INNER JOIN users u_p   ON u_p.id = pt.user_id
        INNER JOIN doctors d   ON d.id = a.doctor_id
        INNER JOIN users u_d   ON u_d.id = d.user_id
        WHERE a.appointment_date = ?";

$params = [$filter_date];
$types  = 's';
if ($filter_stat) { $sql .= ' AND a.status=?'; $params[] = $filter_stat; $types .= 's'; }
if ($filter_doc)  { $sql .= ' AND d.id=?';     $params[] = $filter_doc;  $types .= 'i'; }
$sql .= ' ORDER BY a.priority DESC, a.appointment_time ASC';

$stmt = $database->prepare($sql);
if (!$stmt) die('Erreur BD: ' . htmlspecialchars($database->error));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result();

$stat_total   = safeCount($database, "SELECT COUNT(*) c FROM appointments WHERE appointment_date=?", 's', [$filter_date]);
$stat_pending = safeCount($database, "SELECT COUNT(*) c FROM appointments WHERE appointment_date=? AND status='pending'", 's', [$filter_date]);
$stat_done    = safeCount($database, "SELECT COUNT(*) c FROM appointments WHERE appointment_date=? AND status='completed'", 's', [$filter_date]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>File d'attente</title>
<link rel="stylesheet" href="recept.css">
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
  <div class="topbar">
    <span class="topbar-title">File d'attente — <?php echo date('d/m/Y', strtotime($filter_date)); ?></span>
    <div class="topbar-right">
      <span class="date-tag"><?php echo date('H:i'); ?></span>
      <a href="reception.php" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Accueillir patient
      </a>
    </div>
  </div>
  <div class="page-body">

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-<?php echo $_GET['msg'] === 'ok' ? 'success' : ($_GET['msg'] === 'done' ? 'success' : 'warning'); ?>">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php
        $msgs = ['ok' => 'Patient appelé.', 'done' => 'Consultation terminée.', 'cancelled' => 'Entrée annulée.'];
        echo $msgs[$_GET['msg']] ?? htmlspecialchars($_GET['msg']);
      ?>
    </div>
    <?php endif; ?>

    <!-- Stats rapides -->
    <div class="stats" style="margin-bottom:18px">
      <div class="stat"><div class="stat-ico b"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div><div class="stat-num"><?php echo $stat_total; ?></div><div class="stat-lbl">Total du jour</div></div></div>
      <div class="stat"><div class="stat-ico a"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div><div class="stat-num"><?php echo $stat_pending; ?></div><div class="stat-lbl">En attente</div></div></div>
      <div class="stat"><div class="stat-ico g"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div><div class="stat-num"><?php echo $stat_done; ?></div><div class="stat-lbl">Consultés</div></div></div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="filter-bar">
      <label>Date :</label>
      <input class="input" type="date" name="date" value="<?php echo $filter_date; ?>" style="width:150px">
      <label>Médecin :</label>
      <select class="input" name="doctor_id" style="width:210px">
        <option value="">Tous les médecins</option>
        <?php foreach ($all_docs as $d): ?>
          <option value="<?php echo $d['id']; ?>" <?php echo $filter_doc === (int)$d['id'] ? 'selected' : ''; ?>>
            Dr. <?php echo htmlspecialchars($d['name']); ?>
            <?php if ($d['specialty']): ?>(<?php echo htmlspecialchars($d['specialty']); ?>)<?php endif; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <label>Statut :</label>
      <select class="input" name="status" style="width:145px">
        <option value="">Tous</option>
        <?php foreach (STATUS_LABELS as $v => $l): ?>
          <option value="<?php echo $v; ?>" <?php echo $filter_stat === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
      <a href="appointments.php" class="btn btn-secondary btn-sm">Réinitialiser</a>
    </form>

    <div class="card">
      <div class="card-head">
        <h3>File du <?php echo date('d/m/Y', strtotime($filter_date)); ?></h3>
        <span style="font-size:.76rem;color:var(--text2)"><?php echo $rows->num_rows; ?> patient(s)</span>
      </div>

      <?php if ($rows->num_rows === 0): ?>
        <div class="empty">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <h3>File vide</h3>
          <p>Aucun patient en attente pour cette sélection.</p>
        </div>
      <?php else: ?>
      <table class="tbl">
        <thead><tr>
          <th>#</th><th>Patient</th><th>Médecin / Salle</th>
          <th>Type</th><th>Allergie</th><th>Statut</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php $i = 0; while ($r = $rows->fetch_assoc()): $i++; ?>
        <tr <?php echo $r['priority'] ? 'style="background:#FFF8F8"' : ''; ?>>
          <td style="font-family:'DM Mono',monospace;font-weight:900;font-size:1rem;color:<?php echo $r['priority'] ? 'var(--red)' : 'var(--text2)'; ?>">
            <?php echo $r['priority'] ? '⚡' : '#'; ?><?php echo $i; ?>
          </td>
          <td>
            <div style="font-weight:700"><?php echo htmlspecialchars($r['patient_name']); ?></div>
            <div style="font-size:.73rem;color:var(--text2)"><?php echo htmlspecialchars($r['phone']); ?></div>
            <?php if ($r['appointment_time']): ?>
              <div style="font-size:.68rem;color:var(--text3);font-family:'DM Mono',monospace"><?php echo substr($r['appointment_time'], 0, 5); ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div><?php echo htmlspecialchars($r['doctor_name']); ?></div>
            <?php if ($r['room_number']): ?>
              <div style="font-size:.74rem;color:var(--text2)">Salle <?php echo htmlspecialchars($r['room_number']); ?></div>
            <?php endif; ?>
          </td>
          <td><?php echo TYPE_LABELS[$r['type']] ?? htmlspecialchars($r['type'] ?? '—'); ?></td>
          <td>
            <?php if ($r['allergies']): ?>
              <span style="background:var(--red-l);color:var(--red);padding:2px 7px;border-radius:20px;font-size:.68rem;font-weight:700">
                ⚠ <?php echo htmlspecialchars(mb_substr($r['allergies'], 0, 20)); ?>
              </span>
            <?php else: echo '<span style="color:var(--text3);font-size:.74rem">—</span>'; endif; ?>
          </td>
          <td>
            <span class="badge <?php echo STATUS_BADGE[$r['status']] ?? 'badge-pending'; ?>">
              <?php echo STATUS_LABELS[$r['status']] ?? $r['status']; ?>
            </span>
          </td>
          <td>
            <div class="tbl-actions">
              <?php if ($r['status'] === 'pending'): ?>
                <a href="confirm-appointment.php?id=<?php echo $r['id']; ?>&date=<?php echo $filter_date; ?>"
                   class="btn btn-blue btn-sm">✓ Appeler</a>
              <?php endif; ?>
              <?php if ($r['status'] === 'confirmed'): ?>
                <a href="done-appointment.php?id=<?php echo $r['id']; ?>&date=<?php echo $filter_date; ?>"
                   class="btn btn-primary btn-sm">✔ Terminé</a>
              <?php endif; ?>
              <?php if (!in_array($r['status'], ['completed','cancelled','no_show'])): ?>
                <a href="cancel-appointment.php?id=<?php echo $r['id']; ?>&date=<?php echo $filter_date; ?>"
                   onclick="return confirm('Annuler cette entrée ?')"
                   class="btn btn-danger btn-sm">✕</a>
              <?php endif; ?>
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
</body></html>
