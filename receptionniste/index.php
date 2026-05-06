<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  index.php — Dashboard réceptionniste
//  ✅ Accès : patients, appointments, doctors
//  ❌ Aucun accès : invoices, payments, facturation
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$today = date('Y-m-d');

$stat_attente = safeCount($database, "SELECT COUNT(*) c FROM appointments WHERE appointment_date=? AND status='pending'",   's', [$today]);
$stat_conf    = safeCount($database, "SELECT COUNT(*) c FROM appointments WHERE appointment_date=? AND status='confirmed'", 's', [$today]);
$stat_done    = safeCount($database, "SELECT COUNT(*) c FROM appointments WHERE appointment_date=? AND status='completed'", 's', [$today]);
$stat_pts     = safeCount($database, "SELECT COUNT(*) c FROM patients");

// File d'attente du jour (20 premiers)
$stmt = $database->prepare(
    "SELECT a.id, a.status, a.type, a.appointment_time, a.priority,
            u_p.full_name patient_name, u_p.phone,
            u_d.full_name doctor_name, d.room_number,
            pt.allergies
     FROM appointments a
     INNER JOIN patients pt ON pt.id = a.patient_id
     INNER JOIN users u_p   ON u_p.id = pt.user_id
     INNER JOIN doctors d   ON d.id = a.doctor_id
     INNER JOIN users u_d   ON u_d.id = d.user_id
     WHERE a.appointment_date = ?
       AND a.status NOT IN ('cancelled','no_show')
     ORDER BY a.priority DESC, a.appointment_time ASC
     LIMIT 20"
);
$rdvs     = null;
$db_error = '';
if ($stmt) {
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $rdvs = $stmt->get_result();
} else {
    $db_error = $database->error;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — Réceptionniste</title>
<link rel="stylesheet" href="recept.css">
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
  <div class="topbar">
    <span class="topbar-title">Tableau de bord</span>
    <div class="topbar-right">
      <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
      <a href="reception.php" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11H3z"/></svg>
        Accueillir patient
      </a>
    </div>
  </div>
  <div class="page-body">

    <?php if ($db_error): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Erreur BD : <?php echo htmlspecialchars($db_error); ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats">
      <div class="stat"><div class="stat-ico a"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div><div class="stat-num"><?php echo $stat_attente; ?></div><div class="stat-lbl">En attente</div></div></div>
      <div class="stat"><div class="stat-ico b"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div><div class="stat-num"><?php echo $stat_conf; ?></div><div class="stat-lbl">En consultation</div></div></div>
      <div class="stat"><div class="stat-ico g"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div><div class="stat-num"><?php echo $stat_done; ?></div><div class="stat-lbl">Consultés</div></div></div>
      <div class="stat"><div class="stat-ico p"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div><div class="stat-num"><?php echo $stat_pts; ?></div><div class="stat-lbl">Patients total</div></div></div>
    </div>

    <!-- File du jour -->
    <div class="card">
      <div class="card-head">
        <h3>File d'attente — <?php echo date('d/m/Y'); ?></h3>
        <a href="appointments.php" class="btn btn-secondary btn-sm">Voir tout →</a>
      </div>
      <?php if (!$rdvs || $rdvs->num_rows === 0): ?>
        <div class="empty">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <h3>File vide aujourd'hui</h3>
          <p><a href="reception.php" style="color:var(--green);font-weight:600">Accueillir le premier patient →</a></p>
        </div>
      <?php else: ?>
      <table class="tbl">
        <thead><tr>
          <th>#</th><th>Patient</th><th>Téléphone</th>
          <th>Médecin</th><th>Salle</th><th>Type</th><th>Allergie</th><th>Statut</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php $i = 0; while ($r = $rdvs->fetch_assoc()): $i++; ?>
        <tr <?php echo $r['priority'] ? 'style="background:#FFF8F8"' : ''; ?>>
          <td style="font-weight:900;font-size:.95rem;color:<?php echo $r['priority'] ? 'var(--red)' : 'var(--text3)'; ?>">
            <?php echo $r['priority'] ? '⚡' : '#'; ?><?php echo $i; ?>
          </td>
          <td style="font-weight:600"><?php echo htmlspecialchars($r['patient_name']); ?></td>
          <td style="font-size:.8rem"><?php echo htmlspecialchars($r['phone']); ?></td>
          <td><?php echo htmlspecialchars($r['doctor_name']); ?></td>
          <td><?php echo htmlspecialchars($r['room_number'] ?: '—'); ?></td>
          <td><?php echo TYPE_LABELS[$r['type']] ?? htmlspecialchars($r['type'] ?? '—'); ?></td>
          <td>
            <?php if ($r['allergies']): ?>
              <span style="background:var(--red-l);color:var(--red);padding:1px 7px;border-radius:20px;font-size:.67rem;font-weight:700">⚠</span>
            <?php else: echo '—'; endif; ?>
          </td>
          <td><span class="badge <?php echo STATUS_BADGE[$r['status']] ?? 'badge-pending'; ?>"><?php echo STATUS_LABELS[$r['status']] ?? $r['status']; ?></span></td>
          <td>
            <div class="tbl-actions">
              <?php if ($r['status'] === 'pending'): ?>
                <a href="confirm-appointment.php?id=<?php echo $r['id']; ?>" class="btn btn-blue btn-sm">✓ Appeler</a>
              <?php endif; ?>
              <?php if ($r['status'] === 'confirmed'): ?>
                <a href="done-appointment.php?id=<?php echo $r['id']; ?>" class="btn btn-primary btn-sm">✔ Terminé</a>
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
