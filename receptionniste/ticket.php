<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  ticket.php — Génération ticket de file d'attente par médecin
//
//  ✅ Accès : patients, appointments, doctors
//  ❌ Aucun accès : invoices, payments, facturation
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$patient_id = (int)($_GET['pid'] ?? 0);
$error      = '';
$success    = false;
$patient    = null;

if ($patient_id > 0 && patientExists($database, $patient_id)) {
    $patient = getPatientById($database, $patient_id);
} else {
    header('Location: reception.php');
    exit;
}

$doctors = getDoctors($database);

function calcAge($dob) {
    if (!$dob || $dob === '0000-00-00') return 'N/A';
    try { return (new DateTime())->diff(new DateTime($dob))->y . ' ans'; }
    catch (Exception $e) { return 'N/A'; }
}

// ── Résultats après succès ──────────────────────────────────────
$ticket_number  = null;
$doctor_name    = null;
$doctor_room    = null;
$doctor_spec    = null;
$appointment_id = null;

// ── Traitement POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $motif     = trim($_POST['motif'] ?? 'consultation');
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;

    if ($doctor_id <= 0) {
        $error = 'Veuillez sélectionner un médecin.';
    } else {
        // Récupérer les infos du médecin
        foreach ($doctors as $d) {
            if ((int)$d['id'] === $doctor_id) {
                $doctor_name = $d['name'];
                $doctor_room = $d['room_number'] ?? null;
                $doctor_spec = $d['specialty']   ?? null;
                break;
            }
        }

        $ticket_num = getTicketNumberForDoctor($database, $doctor_id);

        // Vérifier doublon dans la file du jour
        $chk = $database->prepare(
            "SELECT id FROM appointments
             WHERE patient_id = ? AND doctor_id = ?
               AND DATE(appointment_date) = CURDATE()
               AND status IN ('pending','confirmed')
             LIMIT 1"
        );
        $already_queued = false;
        if ($chk) {
            $chk->bind_param('ii', $patient_id, $doctor_id);
            $chk->execute();
            $already_queued = ($chk->get_result()->num_rows > 0);
        }

        if ($already_queued) {
            $error = "Ce patient est déjà dans la file d'attente de ce médecin aujourd'hui.";
        } else {
            // Valider le motif
            $valid_types = array_keys(TYPE_LABELS);
            if (!in_array($motif, $valid_types)) $motif = 'consultation';
            $type_val = ($motif === 'urgence') ? 'urgence' : 'consultation';

            $ins = $database->prepare(
                "INSERT INTO appointments
                 (patient_id, doctor_id, appointment_date, appointment_time,
                  status, type, priority, reason, created_at)
                 VALUES (?, ?, CURDATE(), CURTIME(), 'pending', ?, ?, ?, NOW())"
            );
            if (!$ins) {
                $error = 'Erreur BD : ' . htmlspecialchars($database->error);
            } else {
                // patient_id int, doctor_id int, type_val str, is_urgent int, motif str
                $ins->bind_param('iisis',
                    $patient_id, $doctor_id, $type_val, $is_urgent, $motif
                );
                if (!$ins->execute()) {
                    $error = 'Erreur enregistrement : ' . htmlspecialchars($ins->error);
                } else {
                    $appointment_id = $database->insert_id;
                    $ticket_number  = $ticket_num;
                    $success        = true;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ticket d'attente</title>
    <link rel="stylesheet" href="recept.css">
    <style>
        .patient-banner {
            background: linear-gradient(135deg, #0F1923, #1C3040);
            border-radius: var(--r); padding: 18px 22px; margin-bottom: 22px;
            color: white; display: flex; align-items: center; gap: 14px;
        }
        .patient-banner .ava {
            width: 46px; height: 46px; border-radius: 50%;
            background: var(--green); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; font-weight: 700; flex-shrink: 0;
        }
        .patient-banner h3 { margin: 0; font-size: 1.05rem; }
        .patient-banner .meta {
            font-size: .77rem; color: rgba(255,255,255,.55); margin-top: 4px;
            display: flex; gap: 14px; flex-wrap: wrap;
        }
        .allergy-warn {
            background: #3b0a0a; border: 1.5px solid #b83228;
            border-radius: 8px; padding: 8px 14px; margin-top: 10px;
            font-size: .77rem; color: #f87171;
        }
        .ticket-page {
            display: flex; flex-direction: column; align-items: center;
            padding: 30px 20px;
        }
        .ticket-print {
            width: 340px; background: #fff;
            border: 3px solid var(--green); border-radius: 18px;
            box-shadow: 0 10px 40px rgba(26,107,74,.2); overflow: hidden;
        }
        .ticket-print.urgent { border-color: var(--red); box-shadow: 0 10px 40px rgba(184,50,40,.2); }
        .ticket-header {
            background: var(--green); color: #fff;
            padding: 16px 20px 12px; text-align: center;
        }
        .ticket-print.urgent .ticket-header { background: var(--red); }
        .ticket-header .clinic-name { font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; opacity: .85; }
        .ticket-header .ticket-title { font-size: .9rem; font-weight: 600; margin-top: 2px; }
        .ticket-num-area { text-align: center; padding: 20px 20px 10px; }
        .ticket-num-label { font-size: .65rem; font-weight: 700; color: var(--text2); letter-spacing: .12em; text-transform: uppercase; }
        .ticket-num {
            font-family: 'DM Mono', monospace;
            font-size: 4.5rem; font-weight: 900; line-height: 1;
            color: var(--green); margin: 4px 0;
        }
        .ticket-print.urgent .ticket-num { color: var(--red); }
        .ticket-urgent-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: var(--red-l); color: var(--red);
            border: 1.5px solid var(--red); border-radius: 20px;
            font-size: .7rem; font-weight: 800; padding: 3px 10px;
            letter-spacing: .06em; margin-bottom: 4px;
        }
        .ticket-divider { border: none; border-top: 2px dashed var(--border); margin: 0 16px; }
        .ticket-body { padding: 14px 20px 18px; }
        .ticket-row {
            display: flex; justify-content: space-between; align-items: baseline;
            padding: 5px 0; font-size: .79rem; border-bottom: 1px dotted var(--border);
        }
        .ticket-row:last-child { border-bottom: none; }
        .ticket-row .lbl { color: var(--text2); font-size: .71rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .ticket-row .val { font-weight: 600; text-align: right; max-width: 60%; }
        .ticket-footer {
            background: var(--surf2); padding: 10px 20px; text-align: center;
            font-size: .68rem; color: var(--text3); border-top: 1px solid var(--border);
        }
        .ticket-actions {
            display: flex; gap: 10px; justify-content: center; margin-top: 20px; flex-wrap: wrap;
        }
        @media print {
            .sidebar, .topbar, .no-print, .ticket-actions { display: none !important; }
            .main { margin-left: 0; }
            .page-body { padding: 0; background: #fff; }
            body { background: #fff; }
            .ticket-print { box-shadow: none !important; border-color: #000 !important; margin: 0 auto; width: 280px; }
            .ticket-header { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .ticket-num { color: #000 !important; }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Ticket d'attente</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body" style="max-width:700px;margin:0 auto">

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success && $ticket_number !== null): ?>
            <!-- ══ TICKET GÉNÉRÉ ══ -->
            <div class="ticket-page">
                <div class="ticket-print <?php echo !empty($_POST['is_urgent']) ? 'urgent' : ''; ?>" id="ticketCard">
                    <div class="ticket-header">
                        <div class="clinic-name">🏥 MedUnity</div>
                        <div class="ticket-title">Ticket de file d'attente</div>
                    </div>
                    <div class="ticket-num-area">
                        <?php if (!empty($_POST['is_urgent'])): ?>
                            <div class="ticket-urgent-badge">⚡ URGENT — PRIORITÉ</div><br>
                        <?php endif; ?>
                        <div class="ticket-num-label">Votre numéro</div>
                        <div class="ticket-num"><?php echo str_pad($ticket_number, 2, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    <hr class="ticket-divider">
                    <div class="ticket-body">
                        <div class="ticket-row">
                            <span class="lbl">Patient</span>
                            <span class="val"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                        </div>
                        <div class="ticket-row">
                            <span class="lbl">Médecin</span>
                            <span class="val">Dr. <?php echo htmlspecialchars($doctor_name ?? ''); ?></span>
                        </div>
                        <?php if ($doctor_spec): ?>
                            <div class="ticket-row">
                                <span class="lbl">Spécialité</span>
                                <span class="val"><?php echo htmlspecialchars($doctor_spec); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($doctor_room): ?>
                            <div class="ticket-row">
                                <span class="lbl">Salle</span>
                                <span class="val">Salle <?php echo htmlspecialchars($doctor_room); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="ticket-row">
                            <span class="lbl">Date</span>
                            <span class="val"><?php echo date('d/m/Y'); ?></span>
                        </div>
                        <div class="ticket-row">
                            <span class="lbl">Heure d'accueil</span>
                            <span class="val" style="font-family:'DM Mono',monospace"><?php echo date('H:i'); ?></span>
                        </div>
                        <div class="ticket-row">
                            <span class="lbl">Motif</span>
                            <span class="val"><?php echo htmlspecialchars(TYPE_LABELS[$_POST['motif'] ?? 'consultation'] ?? 'Consultation'); ?></span>
                        </div>
                    </div>
                    <div class="ticket-footer">
                        Merci de patienter — N° <?php echo str_pad($ticket_number, 2, '0', STR_PAD_LEFT); ?> pour Dr. <?php echo htmlspecialchars($doctor_name ?? ''); ?>
                    </div>
                </div>

                <div class="ticket-actions no-print">
                    <button onclick="window.print()" class="btn btn-primary btn-lg">
                        <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        🖨 Imprimer le ticket
                    </button>
                    <a href="reception.php" class="btn btn-secondary btn-lg">← Nouveau patient</a>
                    <a href="appointments.php" class="btn btn-secondary btn-lg">File du jour →</a>
                </div>
            </div>

        <?php else: ?>
            <!-- ══ FORMULAIRE ══ -->
            <div class="patient-banner">
                <div class="ava"><?php echo strtoupper(mb_substr($patient['full_name'] ?? 'P', 0, 1)); ?></div>
                <div>
                    <h3><?php echo htmlspecialchars($patient['full_name'] ?? ''); ?></h3>
                    <div class="meta">
                        <span>📞 <?php echo htmlspecialchars($patient['phone'] ?? ''); ?></span>
                        <span>🆔 <?php echo htmlspecialchars($patient['uhid'] ?? ''); ?></span>
                        <span>🎂 <?php echo calcAge($patient['dob'] ?? null); ?></span>
                        <?php if (!empty($patient['blood_type'])): ?>
                            <span>🩸 <?php echo htmlspecialchars($patient['blood_type']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($patient['allergies'])): ?>
                        <div class="allergy-warn">⚠️ ALLERGIE : <?php echo htmlspecialchars($patient['allergies']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST">
                <div class="card" style="margin-bottom:16px">
                    <div class="card-head"><h3>🎟 Affecter à un médecin</h3></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group" style="grid-column:1/-1">
                                <label>Médecin <span class="req">*</span></label>
                                <select class="input" name="doctor_id" id="doctorSel" required
                                        onchange="loadQueueInfo(this.value)">
                                    <option value="">— Sélectionner un médecin —</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo $doc['id']; ?>"
                                            <?php echo ($_POST['doctor_id'] ?? '') == $doc['id'] ? 'selected' : ''; ?>>
                                            Dr. <?php echo htmlspecialchars($doc['name']); ?>
                                            <?php if (!empty($doc['specialty'])): ?>(<?php echo htmlspecialchars($doc['specialty']); ?>)<?php endif; ?>
                                            <?php if (!empty($doc['room_number'])): ?>— Salle <?php echo htmlspecialchars($doc['room_number']); ?><?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="queueInfo" style="grid-column:1/-1;display:none">
                                <div style="background:var(--blue-l);border:1.5px solid var(--blue-l);border-radius:var(--rs);padding:10px 15px;font-size:.82rem;color:var(--blue)">
                                    <span id="queueInfoText">…</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Motif de consultation</label>
                                <select class="input" name="motif">
                                    <?php foreach (TYPE_LABELS as $v => $l): ?>
                                        <option value="<?php echo $v; ?>"
                                            <?php echo ($_POST['motif'] ?? 'consultation') === $v ? 'selected' : ''; ?>>
                                            <?php echo $l; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="justify-content:flex-end;align-items:flex-end">
                                <label style="display:flex;align-items:center;gap:9px;cursor:pointer;padding-bottom:9px">
                                    <input type="checkbox" name="is_urgent" value="1"
                                           style="width:17px;height:17px;accent-color:var(--red)"
                                        <?php echo !empty($_POST['is_urgent']) ? 'checked' : ''; ?>>
                                    <span style="color:var(--red);font-weight:700">⚡ Marquer URGENT (priorité)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Générer le ticket
                    </button>
                    <a href="reception.php" class="btn btn-secondary btn-lg">Annuler</a>
                </div>
            </form>

        <?php endif; ?>

    </div>
</div>

<script>
    function loadQueueInfo(doctorId) {
        const box  = document.getElementById('queueInfo');
        const text = document.getElementById('queueInfoText');
        if (!doctorId) { box.style.display = 'none'; return; }
        text.textContent = 'Chargement de la file…';
        box.style.display = '';
        fetch(`queue_info.php?doctor_id=${encodeURIComponent(doctorId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(data => {
                if (data && data.count !== undefined) {
                    const next = data.count + 1;
                    text.innerHTML =
                        `📋 <strong>${data.count}</strong> patient(s) déjà en attente — ` +
                        `Ce patient recevra le numéro <strong>${String(next).padStart(2,'0')}</strong>`;
                } else {
                    text.textContent = 'Impossible de charger les infos de la file.';
                }
            })
            .catch(() => { text.textContent = 'Erreur réseau.'; });
    }
    window.addEventListener('load', () => {
        const sel = document.getElementById('doctorSel');
        if (sel && sel.value) loadQueueInfo(sel.value);
    });
</script>
</body>
</html>
