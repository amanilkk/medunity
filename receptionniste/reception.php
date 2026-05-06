<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  reception.php — Accueil patient
//  Flux :
//    - Rapide → ticket.php?pid=N (consultation normale)
//    - Hospitalisation → sélection médecin + réservation lit
//
//  ✅ Accès : patients, appointments, doctors, beds
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$error          = '';
$mode           = $_GET['mode'] ?? 'search';
$search         = trim($_GET['q'] ?? '');
$results        = [];
$phone_siblings = [];
$email_warning  = '';
$existing_patients_with_email = [];

// ─────────────────────────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────────────────────────
function patientAge(?string $dob): ?int {
    if (!$dob || $dob === '0000-00-00') return null;
    try { return (int)(new DateTime())->diff(new DateTime($dob))->y; }
    catch (Exception $e) { return null; }
}

function fmtDob(?string $dob): string {
    if (!$dob || $dob === '0000-00-00') return '—';
    try { return (new DateTime($dob))->format('d/m/Y'); }
    catch (Exception $e) { return $dob; }
}

// ─────────────────────────────────────────────────────────────────
//  POST — Créer nouveau patient
// ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $dob     = trim($_POST['dob']     ?? '');
    $gender  = trim($_POST['gender']  ?? '');
    $email   = trim($_POST['email']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $fm      = $_POST['fm'] ?? 'simple';

    if (!$name || !$phone) {
        $error = 'Nom et téléphone sont obligatoires.';
        $mode  = 'new';
    } else {

        // ── Vérifier doublons exacts ──────────────────────────────
        $exact_dup = false;
        if ($dob) {
            $chk = $database->prepare(
                    "SELECT pt.id FROM patients pt
                 INNER JOIN users u ON u.id = pt.user_id
                 WHERE u.phone = ? AND u.full_name = ? AND pt.dob = ?
                 LIMIT 1"
            );
            if ($chk) {
                $chk->bind_param('sss', $phone, $name, $dob);
                $chk->execute();
                $exact_dup = ($chk->get_result()->num_rows > 0);
            }
        }

        if ($exact_dup) {
            $error = 'Ce patient (même nom, téléphone et date de naissance) existe déjà.';
            $mode  = 'new';

        } elseif (!isset($_POST['confirm_family'])) {
            // ── Chercher d'autres patients avec ce téléphone ──────
            $chk_phone = $database->prepare(
                    "SELECT pt.id, pt.uhid, pt.dob, u.full_name
                 FROM patients pt
                 INNER JOIN users u ON u.id = pt.user_id
                 WHERE u.phone = ?
                 ORDER BY u.full_name ASC"
            );
            if ($chk_phone) {
                $chk_phone->bind_param('s', $phone);
                $chk_phone->execute();
                $cr = $chk_phone->get_result();
                if ($cr) while ($row = $cr->fetch_assoc()) $phone_siblings[] = $row;
            }
            if (count($phone_siblings) > 0) {
                $error = 'PHONE_EXISTS';
                $mode  = 'new';
            }
        }

        // ── Vérifier l'email (avertissement uniquement) ──────────
        if (!$error && !empty($email) && !isset($_POST['confirm_email'])) {
            $chk_email = $database->prepare(
                    "SELECT pt.id, pt.uhid, u.full_name, u.phone
                 FROM patients pt
                 INNER JOIN users u ON u.id = pt.user_id
                 WHERE u.email = ?
                 LIMIT 5"
            );
            if ($chk_email) {
                $chk_email->bind_param('s', $email);
                $chk_email->execute();
                $email_result = $chk_email->get_result();
                while ($row = $email_result->fetch_assoc()) {
                    $existing_patients_with_email[] = $row;
                }
            }

            if (count($existing_patients_with_email) > 0) {
                $email_warning = 'EMAIL_EXISTS';
                $mode = 'new';
            }
        }

        // ── Si confirmation email reçue OU pas d'email existant ──
        if (!$error && ($email_warning !== 'EMAIL_EXISTS' || isset($_POST['confirm_email']))) {
            $nic   = ($fm === 'complet' || $fm === 'hospitalisation') ? trim($_POST['nic']        ?? '') : '';
            $blood = ($fm === 'complet' || $fm === 'hospitalisation') ? trim($_POST['blood']       ?? '') : '';
            $alrg  = ($fm === 'complet' || $fm === 'hospitalisation') ? trim($_POST['allergies']   ?? '') : '';
            $en    = ($fm === 'complet' || $fm === 'hospitalisation') ? trim($_POST['emerg_name']  ?? '') : '';
            $ep    = ($fm === 'complet' || $fm === 'hospitalisation') ? trim($_POST['emerg_phone'] ?? '') : '';
            $hist  = ($fm === 'complet' || $fm === 'hospitalisation') ? trim($_POST['history']     ?? '') : '';

            // Générer UHID unique
            $uhid = generateUniqueUhid($database, $name, $dob);

            // Utiliser l'email tel quel
            $user_email = !empty($email) ? $email : strtolower(preg_replace('/[^A-Za-z0-9]/', '', str_replace(' ', '.', $name))) . '@clinic.local';

            // ── Insérer dans users ────────────────────────────────
            $test_col = $database->query("SHOW COLUMNS FROM users LIKE 'address'");
            $has_address = ($test_col && $test_col->num_rows > 0);

            if ($has_address) {
                $s1 = $database->prepare(
                        "INSERT INTO users (email, password, full_name, role_id, phone, address, is_active)
                     VALUES (?, '', ?, 3, ?, ?, 1)"
                );
                if ($s1) $s1->bind_param('ssss', $user_email, $name, $phone, $address);
            } else {
                $s1 = $database->prepare(
                        "INSERT INTO users (email, password, full_name, role_id, phone, is_active)
                     VALUES (?, '', ?, 3, ?, 1)"
                );
                if ($s1) $s1->bind_param('sss', $user_email, $name, $phone);
            }

            if (!$s1) {
                $error = 'Erreur BD users : ' . htmlspecialchars($database->error);
                $mode  = 'new';
            } elseif (!$s1->execute()) {
                $error = 'Erreur création utilisateur : ' . htmlspecialchars($s1->error);
                $mode  = 'new';
            } else {
                $uid     = $database->insert_id;
                $dob_val = $dob ?: null;

                $s2 = $database->prepare(
                        "INSERT INTO patients
                     (user_id, uhid, nic, dob, gender, blood_type,
                      allergies, medical_history,
                      emergency_contact_name, emergency_contact_phone)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if (!$s2) {
                    $error = 'Erreur BD patients : ' . htmlspecialchars($database->error);
                    $mode  = 'new';
                } else {
                    $s2->bind_param('isssssssss',
                            $uid, $uhid, $nic, $dob_val, $gender, $blood,
                            $alrg, $hist, $en, $ep
                    );
                    if (!$s2->execute()) {
                        $error = 'Erreur création patient : ' . htmlspecialchars($s2->error);
                        $mode  = 'new';
                    } else {
                        $new_pid = $database->insert_id;

                        // Redirection selon le mode
                        if ($fm === 'hospitalisation') {
                            // Rediriger vers la page de réservation de lit avec médecin
                            header("Location: hospitalization-setup.php?pid=$new_pid");
                        } else {
                            // Consultation normale
                            header("Location: ticket.php?pid=$new_pid");
                        }
                        exit;
                    }
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────
//  GET — Recherche classique (fallback sans JS)
// ─────────────────────────────────────────────────────────────────
if ($mode === 'search' && $search !== '') {
    $kw        = '%' . $search . '%';
    $year_part = null;
    $name_part = $search;

    if (preg_match('/\b(19[0-9]{2}|20[0-2][0-9])\b/', $search, $m)) {
        $year_part = (int)$m[1];
        $name_part = trim(str_replace($m[0], '', $search));
    }
    $kw2       = '%' . $name_part . '%';
    $year_cond = $year_part ? 'AND YEAR(pt.dob) = ?' : '';

    $st = $database->prepare(
            "SELECT pt.id, pt.uhid, pt.blood_type, pt.allergies, pt.dob,
                u.full_name, u.phone
         FROM patients pt
         INNER JOIN users u ON u.id = pt.user_id
         WHERE (u.full_name LIKE ? OR u.phone LIKE ? OR pt.uhid LIKE ?)
         $year_cond
         ORDER BY u.full_name ASC LIMIT 30"
    );
    if ($st) {
        if ($year_part) {
            $st->bind_param('sssi', $kw2, $kw, $kw, $year_part);
        } else {
            $st->bind_param('sss', $kw, $kw, $kw);
        }
        $st->execute();
        $r = $st->get_result();
        if ($r) while ($row = $r->fetch_assoc()) $results[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Accueil Patient</title>
    <link rel="stylesheet" href="recept.css">
    <style>
        .search-wrap { position: relative; }
        #liveDropdown {
            display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: var(--surface); border: 1.5px solid var(--border);
            border-radius: var(--r); box-shadow: 0 8px 32px rgba(0,0,0,.14);
            z-index: 200; max-height: 420px; overflow-y: auto;
        }
        #liveDropdown.open { display: block; }
        .dd-hint { padding: 8px 14px; font-size: .7rem; color: var(--text3);
            border-bottom: 1px solid var(--border); font-style: italic; }
        .dd-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; cursor: pointer; transition: background .1s;
            border-bottom: 1px solid var(--border);
        }
        .dd-item:last-of-type { border-bottom: none; }
        .dd-item:hover, .dd-item.focused { background: var(--green-l); }
        .dd-ava {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--green); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .88rem; flex-shrink: 0;
        }
        .dd-name { font-weight: 600; font-size: .88rem; }
        .dd-meta { font-size: .72rem; color: var(--text2); margin-top: 1px; }
        .dd-dob  { font-size: .7rem;  color: var(--text3); margin-top: 1px; }
        .dd-badges { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
        .dd-badge {
            font-size: .62rem; font-weight: 700; padding: 2px 7px;
            border-radius: 20px; white-space: nowrap;
        }
        .dd-badge.blood   { background: var(--red-l);   color: var(--red); }
        .dd-badge.allergy { background: var(--amber-l); color: var(--amber); }
        .dd-arrow { font-size: .72rem; color: var(--green); font-weight: 600;
            margin-left: auto; white-space: nowrap; }
        .dd-empty { padding: 20px 14px; text-align: center; color: var(--text2); font-size: .82rem; }
        .dd-spinner { padding: 16px 14px; text-align: center; color: var(--text3); font-size: .8rem; }
        .dd-footer {
            padding: 8px 14px; display: flex; justify-content: space-between;
            font-size: .7rem; color: var(--text3);
            border-top: 1px solid var(--border); background: var(--surf2);
        }
        mark { background: #FFF3CD; color: #7B5800; border-radius: 2px; padding: 0 1px; }
        .hero-search {
            background: linear-gradient(135deg, #0F1923, #1C3040);
            border-radius: var(--r); padding: 28px 30px; margin-bottom: 22px; color: #fff;
        }
        .hero-search h2 { font-size: 1.1rem; font-weight: 600; margin-bottom: 4px; }
        .hero-search p  { font-size: .78rem; color: rgba(255,255,255,.5); margin-bottom: 16px; }
        .hero-search .input {
            background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.15); color: #fff;
            font-size: .95rem; padding: 12px 15px;
        }
        .hero-search .input::placeholder { color: rgba(255,255,255,.35); }
        .hero-search .input:focus { border-color: var(--green); background: rgba(255,255,255,.15); }
        .family-box {
            background: var(--amber-l); border: 2px solid var(--amber);
            border-radius: var(--r); padding: 16px 18px; margin-bottom: 18px;
        }
        .family-box h4 { color: var(--amber); font-size: .88rem; margin-bottom: 10px; }
        .fam-item {
            display: flex; align-items: center; gap: 10px; padding: 8px 0;
            border-bottom: 1px dotted rgba(181,122,10,.25); font-size: .82rem;
        }
        .fam-item:last-child { border-bottom: none; }
        .fam-ava {
            width: 28px; height: 28px; border-radius: 50%; background: var(--amber);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; flex-shrink: 0;
        }
        .email-warning-box {
            background: var(--blue-l); border: 2px solid var(--blue);
            border-radius: var(--r); padding: 16px 18px; margin-bottom: 18px;
        }
        .email-warning-box h4 { color: var(--blue); font-size: .88rem; margin-bottom: 10px; }
        .email-item {
            display: flex; align-items: center; gap: 10px; padding: 8px 0;
            border-bottom: 1px dotted rgba(26,78,118,.25); font-size: .82rem;
        }
        .email-item:last-child { border-bottom: none; }
        .email-ava {
            width: 28px; height: 28px; border-radius: 50%; background: var(--blue);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; flex-shrink: 0;
        }
        .uhid-preview {
            background: var(--surf2); border-radius: var(--rs); padding: 10px 12px;
            font-size: .78rem; margin-bottom: 15px; font-family: 'DM Mono', monospace;
            color: var(--text2); border: 1px solid var(--border);
        }
        .uhid-preview strong { color: var(--green); font-size: .85rem; }
        .mode-buttons {
            display: flex; gap: 10px; margin-bottom: 20px;
        }
        .mode-btn {
            flex: 1; padding: 12px; border: 2px solid var(--border);
            border-radius: var(--r); background: var(--surface);
            cursor: pointer; text-align: center; transition: all 0.2s;
            font-weight: 600;
        }
        .mode-btn.active {
            border-color: var(--green);
            background: var(--green-l);
            color: var(--green);
        }
        .mode-btn.hospitalisation.active {
            border-color: var(--blue);
            background: var(--blue-l);
            color: var(--blue);
        }
        .mode-desc {
            font-size: .7rem; font-weight: normal; color: var(--text2); margin-top: 4px;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Accueil patient</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="reception.php?mode=new" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouveau patient
            </a>
        </div>
    </div>
    <div class="page-body" style="max-width:780px;margin:0 auto">

        <?php if ($error && $error !== 'PHONE_EXISTS' && $error !== 'EMAIL_EXISTS'): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'search'): ?>
            <!-- ════════ MODE RECHERCHE ════════ -->
            <div class="hero-search">
                <h2>🏥 Rechercher un patient existant</h2>
                <p>Saisir le nom, téléphone ou UHID — résultats en temps réel</p>
                <form method="GET" class="search-wrap" autocomplete="off">
                    <input type="hidden" name="mode" value="search">
                    <input class="input" type="text" name="q" id="searchInput"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Ex : Benali, 0555…, PT…"
                           style="width:100%">
                    <div id="liveDropdown"></div>
                </form>
            </div>

            <?php if ($search !== '' && count($results) === 0): ?>
                <div class="alert alert-warning">
                    Aucun patient trouvé pour « <?php echo htmlspecialchars($search); ?> ».
                    <a href="reception.php?mode=new" style="font-weight:700;color:var(--amber)">Créer un nouveau dossier →</a>
                </div>
            <?php elseif (count($results) > 0): ?>
                <div class="card">
                    <div class="card-head">
                        <h3>Résultats (<?php echo count($results); ?>)</h3>
                    </div>
                    <table class="tbl">
                        <thead><tr><th>Patient</th><th>Téléphone</th><th>UHID</th><th>Naissance</th><th>Allergie</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td style="font-weight:600"><?php echo htmlspecialchars($r['full_name']); ?></td>
                                <td style="font-size:.8rem"><?php echo htmlspecialchars($r['phone']); ?></td>
                                <td style="font-family:'DM Mono',monospace;font-size:.73rem;color:var(--text2)"><?php echo htmlspecialchars($r['uhid']); ?></td>
                                <td style="font-size:.79rem">
                                    <?php echo fmtDob($r['dob']); ?>
                                    <?php $age = patientAge($r['dob']); if ($age): ?>
                                        <div style="font-size:.68rem;color:var(--text3)"><?php echo $age; ?> ans</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['allergies']): ?>
                                        <span style="background:var(--red-l);color:var(--red);padding:2px 7px;border-radius:20px;font-size:.67rem;font-weight:700">
                                            ⚠ <?php echo htmlspecialchars(mb_substr($r['allergies'], 0, 18)); ?>
                                        </span>
                                    <?php else: echo '—'; endif; ?>
                                </td>
                                <td>
                                    <div class="tbl-actions">
                                        <a href="ticket.php?pid=<?php echo $r['id']; ?>" class="btn btn-primary btn-sm">
                                            🎟 Consultation
                                        </a>
                                        <a href="hospitalization-setup.php?pid=<?php echo $r['id']; ?>" class="btn btn-blue btn-sm">
                                            🏥 Hospitalisation
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div style="text-align:center;margin-top:22px;display:flex;gap:10px;justify-content:center">
                <a href="reception.php?mode=new&type=simple" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nouvelle consultation
                </a>
                <a href="reception.php?mode=new&type=hospitalisation" class="btn btn-blue">
                    <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/></svg>
                    Nouvelle hospitalisation
                </a>
            </div>

        <?php elseif ($mode === 'new'): ?>
            <!-- ════════ MODE NOUVEAU PATIENT ════════ -->

            <?php
            $selectedType = $_GET['type'] ?? ($_POST['fm'] ?? 'simple');
            $isHospitalisation = ($selectedType === 'hospitalisation');
            ?>

            <?php if ($error === 'PHONE_EXISTS' && count($phone_siblings) > 0): ?>
                <div class="family-box">
                    <h4>⚠️ Ce numéro est déjà enregistré — patients existants :</h4>
                    <?php foreach ($phone_siblings as $sib): ?>
                        <div class="fam-item">
                            <div class="fam-ava"><?php echo strtoupper(substr($sib['full_name'] ?? '?', 0, 1)); ?></div>
                            <div style="flex:1">
                                <strong><?php echo htmlspecialchars($sib['full_name']); ?></strong>
                                <span style="color:var(--text2);font-size:.75rem"> — <?php echo fmtDob($sib['dob']); ?></span>
                                <span style="font-family:'DM Mono',monospace;font-size:.7rem;color:var(--text3)"> <?php echo htmlspecialchars($sib['uhid']); ?></span>
                            </div>
                            <a href="ticket.php?pid=<?php echo $sib['id']; ?>" class="btn btn-primary btn-sm">🎟 Ticket</a>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top:12px;font-size:.78rem;color:var(--amber)">
                        Ce patient est différent ?
                        <button form="newPatientForm" name="confirm_family" value="1" type="submit"
                                class="btn btn-amber btn-sm" style="margin-left:6px">
                            ✓ Créer quand même
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($email_warning === 'EMAIL_EXISTS' && count($existing_patients_with_email) > 0 && !isset($_POST['confirm_email'])): ?>
                <div class="email-warning-box">
                    <h4>📧 Attention — Cet email est déjà utilisé par :</h4>
                    <?php foreach ($existing_patients_with_email as $existing): ?>
                        <div class="email-item">
                            <div class="email-ava"><?php echo strtoupper(substr($existing['full_name'] ?? '?', 0, 1)); ?></div>
                            <div style="flex:1">
                                <strong><?php echo htmlspecialchars($existing['full_name']); ?></strong>
                                <span style="color:var(--text2);font-size:.75rem"> — 📞 <?php echo htmlspecialchars($existing['phone']); ?></span>
                                <span style="font-family:'DM Mono',monospace;font-size:.7rem;color:var(--text3)"> <?php echo htmlspecialchars($existing['uhid']); ?></span>
                            </div>
                            <a href="ticket.php?pid=<?php echo $existing['id']; ?>" class="btn btn-primary btn-sm">🎟 Ticket</a>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top:12px;font-size:.78rem;color:var(--blue);">
                        <p style="margin-bottom:8px;">💡 Il peut s'agir d'un membre de la même famille.</p>
                        <button form="newPatientForm" name="confirm_email" value="1" type="submit"
                                class="btn btn-blue btn-sm">✓ Accepter et créer quand même</button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-head">
                    <h3><?php echo $isHospitalisation ? '🏥 Nouvelle hospitalisation' : '➕ Nouvelle consultation'; ?></h3>
                    <a href="reception.php" class="btn btn-secondary btn-sm">← Rechercher</a>
                </div>
                <div class="card-body">

                    <?php if ($isHospitalisation): ?>
                        <div class="alert alert-info" style="margin-bottom:16px">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div>
                                <strong>Mode Hospitalisation</strong><br>
                                Le patient sera hospitalisé. Vous pourrez ensuite lui assigner un médecin responsable et un lit.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="uhid-preview">
                        🔑 UHID généré : <strong id="uhidPreviewValue">-</strong>
                    </div>

                    <form method="POST" id="newPatientForm">
                        <?php if (isset($_POST['confirm_family'])): ?>
                            <input type="hidden" name="confirm_family" value="1">
                        <?php endif; ?>
                        <?php if (isset($_POST['confirm_email'])): ?>
                            <input type="hidden" name="confirm_email" value="1">
                        <?php endif; ?>
                        <input type="hidden" name="fm" id="fmInput" value="<?php echo $isHospitalisation ? 'hospitalisation' : 'complet'; ?>">

                        <!-- Champs de base -->
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nom complet <span class="req">*</span></label>
                                <input class="input" type="text" name="name" id="full_name"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="Ex : Ahmed Benali" required
                                       oninput="updateUhidPreview()">
                            </div>
                            <div class="form-group">
                                <label>Téléphone <span class="req">*</span></label>
                                <input class="input" type="tel" name="phone" id="phone_input"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       placeholder="0555 123 456" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input class="input" type="email" name="email" id="email_input"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="patient@email.com">
                            </div>
                            <div class="form-group">
                                <label>Adresse</label>
                                <input class="input" type="text" name="address"
                                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                                       placeholder="Rue, ville…">
                            </div>
                            <div class="form-group">
                                <label>Date de naissance</label>
                                <!-- Saisie rapide JJ / MM / AAAA -->
                                <div class="dob-wrap" style="display:flex;gap:6px;align-items:center">
                                    <input class="input dob-part" id="dob_dd" type="text" inputmode="numeric"
                                           maxlength="2" placeholder="JJ" style="width:54px;text-align:center"
                                           autocomplete="off">
                                    <span style="color:var(--text3);font-weight:700">/</span>
                                    <input class="input dob-part" id="dob_mm" type="text" inputmode="numeric"
                                           maxlength="2" placeholder="MM" style="width:54px;text-align:center"
                                           autocomplete="off">
                                    <span style="color:var(--text3);font-weight:700">/</span>
                                    <input class="input dob-part" id="dob_yyyy" type="text" inputmode="numeric"
                                           maxlength="4" placeholder="AAAA" style="width:76px;text-align:center"
                                           autocomplete="off">
                                </div>
                                <!-- Champ caché pour le backend (format YYYY-MM-DD) -->
                                <input type="hidden" name="dob" id="dob_input"
                                       value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                                <div id="dob_hint" style="font-size:.71rem;margin-top:4px;min-height:16px"></div>
                            </div>
                            <div class="form-group">
                                <label>Sexe</label>
                                <select class="input" name="gender">
                                    <option value="">—</option>
                                    <option value="M" <?php echo ($_POST['gender'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                    <option value="F" <?php echo ($_POST['gender'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                                </select>
                            </div>
                        </div>

                        <!-- Champs complets (toujours visibles pour hospitalisation) -->
                        <div id="extraFields">
                            <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>N° NIC / CNAS</label>
                                    <input class="input" type="text" name="nic" id="nic_input"
                                           value="<?php echo htmlspecialchars($_POST['nic'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Groupe sanguin</label>
                                    <select class="input" name="blood">
                                        <?php foreach (['','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                            <option value="<?php echo $bg; ?>" <?php echo ($_POST['blood'] ?? '') === $bg ? 'selected' : ''; ?>>
                                                <?php echo $bg ?: '—'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label>Allergies</label>
                                    <input class="input" type="text" name="allergies" id="aField"
                                           value="<?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?>"
                                           placeholder="Ex : Pénicilline, arachides…"
                                           oninput="chkAllergy(this.value)">
                                </div>
                                <div class="allergy-box" id="allergyBox">
                                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    <div>
                                        <div class="allergy-title">⚠ Allergie signalée</div>
                                        <div class="allergy-text" id="aText"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Contact urgence — Nom</label>
                                    <input class="input" type="text" name="emerg_name"
                                           value="<?php echo htmlspecialchars($_POST['emerg_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Contact urgence — Tél</label>
                                    <input class="input" type="tel" name="emerg_phone" id="emerg_phone_input"
                                           value="<?php echo htmlspecialchars($_POST['emerg_phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label>Antécédents médicaux</label>
                                    <textarea class="input" name="history" rows="3"
                                              placeholder="Diabète, HTA, chirurgie antérieure…"><?php echo htmlspecialchars($_POST['history'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;margin-top:20px">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <?php echo $isHospitalisation ? '✓ Enregistrer & Réserver un lit' : '✓ Enregistrer & Générer ticket'; ?>
                            </button>
                            <a href="reception.php" class="btn btn-secondary btn-lg">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

<script>
    // ══════════════════════════════════════════════════════
    //  UHID Preview
    // ══════════════════════════════════════════════════════
    function cleanForIdPreview(str) {
        if (!str) return '';
        str = str.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^A-Z0-9]/g,'');
        return str;
    }
    function extractNameParts(fullName) {
        const parts = fullName.trim().split(/\s+/);
        const firstName = parts[0] || '';
        const lastName = parts.length > 1 ? parts[parts.length - 1] : firstName;
        return { firstName, lastName };
    }
    function formatDateForUhid(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length !== 3) return '';
        return parts[2] + parts[1] + parts[0];
    }
    function updateUhidPreview() {
        const fullName = document.getElementById('full_name')?.value || '';
        const dob = document.getElementById('dob_input')?.value || '';
        const { firstName, lastName } = extractNameParts(fullName);
        let lastPart = (cleanForIdPreview(lastName).substring(0, 2) + 'XX').substring(0, 2);
        let firstPart = (cleanForIdPreview(firstName).substring(0, 2) + 'XX').substring(0, 2);
        let datePart = formatDateForUhid(dob);
        if (!datePart) {
            const t = new Date();
            datePart = String(t.getDate()).padStart(2,'0') + String(t.getMonth()+1).padStart(2,'0') + t.getFullYear();
        }
        const preview = 'PT' + lastPart + firstPart + datePart;
        const span = document.getElementById('uhidPreviewValue');
        if (span) span.textContent = preview;
    }

    function chkAllergy(v) {
        const b = document.getElementById('allergyBox');
        const t = document.getElementById('aText');
        if (!b || !t) return;
        t.textContent = v;
        b.classList.toggle('show', v.trim().length > 0);
    }

    // ══════════════════════════════════════════════════════
    //  VALIDATION TEMPS RÉEL
    // ══════════════════════════════════════════════════════
    const validators = {
        name: {
            validate(v) {
                if (!v.trim()) return { ok: false, msg: 'Le nom complet est obligatoire.' };
                if (v.trim().length < 3) return { ok: false, msg: 'Le nom doit contenir au moins 3 caractères.' };
                if (/\d/.test(v)) return { ok: false, msg: 'Le nom ne doit pas contenir de chiffres.' };
                return { ok: true };
            }
        },
        phone: {
            validate(v) {
                const clean = v.replace(/[\s\-().]/g,'');
                if (!clean) return { ok: false, msg: 'Le téléphone est obligatoire.' };
                // Formats algériens : 05/06/07 + 8 chiffres, ou +2135/6/7 + 8 chiffres
                const algLocal    = /^0[567]\d{8}$/.test(clean);
                const algIntl     = /^(\+213|00213)[567]\d{8}$/.test(clean);
                if (!algLocal && !algIntl) {
                    return { ok: false, msg: 'Format invalide. Ex : 0555 123 456 · 0661 234 567 · 0772 345 678' };
                }
                return { ok: true };
            }
        },
        email: {
            validate(v) {
                if (!v.trim()) return { ok: true }; // optionnel
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return { ok: false, msg: 'Email invalide. Ex : nom@domaine.com.' };
                return { ok: true };
            }
        },
        dob: {
            validate(v) {
                if (!v) return { ok: true }; // optionnel — la logique fine est dans le widget JJ/MM/AAAA
                const d = new Date(v);
                if (isNaN(d.getTime())) return { ok: false, msg: 'Date invalide.' };
                if (d >= new Date()) return { ok: false, msg: 'La date ne peut pas être dans le futur.' };
                const age = (Date.now() - d.getTime()) / 31557600000;
                if (age > 130) return { ok: false, msg: 'Date trop ancienne.' };
                return { ok: true };
            }
        },
        emerg_phone: {
            validate(v) {
                if (!v.trim()) return { ok: true }; // optionnel
                const clean = v.replace(/[\s\-().]/g,'');
                const algLocal = /^0[567]\d{8}$/.test(clean);
                const algIntl  = /^(\+213|00213)[567]\d{8}$/.test(clean);
                if (!algLocal && !algIntl) {
                    return { ok: false, msg: 'Format invalide. Ex : 0555 123 456 · 0661 234 567' };
                }
                return { ok: true };
            }
        },
        nic: {
            validate(v) {
                if (!v.trim()) return { ok: true };
                if (v.trim().length < 5) return { ok: false, msg: 'NIC trop court (min. 5 caractères).' };
                return { ok: true };
            }
        }
    };

    function getOrCreateHint(field) {
        let hint = field.parentNode.querySelector('.field-hint');
        if (!hint) {
            hint = document.createElement('div');
            hint.className = 'field-hint';
            hint.style.cssText = 'font-size:.71rem;margin-top:4px;min-height:16px;transition:opacity .2s';
            field.parentNode.appendChild(hint);
        }
        return hint;
    }

    function validateField(fieldName, field) {
        const v = validators[fieldName];
        if (!v) return true;
        const result = v.validate(field.value);
        const hint = getOrCreateHint(field);
        if (!result.ok) {
            field.style.borderColor = 'var(--red, #e53e3e)';
            field.style.boxShadow = '0 0 0 2px rgba(229,62,62,.15)';
            hint.style.color = 'var(--red, #e53e3e)';
            hint.textContent = '⚠ ' + result.msg;
        } else {
            field.style.borderColor = 'var(--green, #38a169)';
            field.style.boxShadow = '0 0 0 2px rgba(56,161,105,.12)';
            hint.style.color = 'var(--green, #38a169)';
            hint.textContent = field.value.trim() ? '✓' : '';
        }
        return result.ok;
    }

    function resetField(field) {
        field.style.borderColor = '';
        field.style.boxShadow = '';
        const hint = field.parentNode.querySelector('.field-hint');
        if (hint) hint.textContent = '';
    }

    function attachValidation(fieldName, inputId) {
        const el = document.getElementById(inputId) || document.querySelector(`[name="${fieldName}"]`);
        if (!el) return;
        let touched = false;
        el.addEventListener('blur', () => {
            touched = true;
            validateField(fieldName, el);
        });
        el.addEventListener('input', () => {
            if (touched) validateField(fieldName, el);
        });
    }

    // Validation au submit
    function attachFormValidation() {
        const form = document.getElementById('newPatientForm');
        if (!form) return;

        // ── Blocage caractères en temps réel ──────────────────────
        // Nom : interdire les chiffres à la frappe
        const nameEl = document.getElementById('full_name') || document.querySelector('[name="name"]');
        if (nameEl) {
            nameEl.addEventListener('input', function () {
                const pos = this.selectionStart;
                const cleaned = this.value.replace(/[0-9]/g, '');
                if (cleaned !== this.value) {
                    this.value = cleaned;
                    this.setSelectionRange(pos - 1, pos - 1);
                }
            });
            nameEl.addEventListener('keydown', function (e) {
                if (/^[0-9]$/.test(e.key)) e.preventDefault();
            });
        }

        // Téléphone : interdire les lettres à la frappe (garder +, espaces, tirets, chiffres)
        ['phone_input', 'emerg_phone_input'].forEach(id => {
            const el = document.getElementById(id) || document.querySelector(`[name="${id.replace('_input','')}"]`);
            if (!el) return;
            el.addEventListener('input', function () {
                const pos = this.selectionStart;
                const cleaned = this.value.replace(/[^0-9+\s\-().]/g, '');
                if (cleaned !== this.value) {
                    this.value = cleaned;
                    try { this.setSelectionRange(pos - 1, pos - 1); } catch(e) {}
                }
            });
            el.addEventListener('keydown', function (e) {
                // Bloquer les lettres (a-z, A-Z) et caractères spéciaux non autorisés
                if (/^[a-zA-ZéèêëàâùûüîïçœæÉÈÊËÀÂÙÛÜÎÏÇŒÆ]$/.test(e.key)) e.preventDefault();
            });
        });

        // Attacher les validateurs sur les champs
        attachValidation('name',        'full_name');
        attachValidation('phone',       'phone_input');
        attachValidation('email',       'email_input');
        attachValidation('dob',         'dob_input');
        attachValidation('emerg_phone', 'emerg_phone_input');
        attachValidation('nic',         'nic_input');

        form.addEventListener('submit', function(e) {
            let allOk = true;
            // Forcer la validation de tous les champs obligatoires
            [
                ['name',        'full_name'],
                ['phone',       'phone_input'],
                ['email',       'email_input'],
                ['dob',         'dob_input'],
                ['emerg_phone', 'emerg_phone_input'],
                ['nic',         'nic_input'],
            ].forEach(([fieldName, inputId]) => {
                const el = document.getElementById(inputId) || document.querySelector(`[name="${fieldName}"]`);
                if (el && validators[fieldName]) {
                    const ok = validateField(fieldName, el);
                    if (!ok) { allOk = false; el.focus && el.focus(); }
                }
            });
            if (!allOk) {
                e.preventDefault();
                // Scroll vers le premier champ en erreur
                const firstErr = form.querySelector('[style*="var(--red"]');
                if (firstErr) firstErr.scrollIntoView({ behavior:'smooth', block:'center' });
            }
        });
    }

    window.addEventListener('load', () => {
        const f = document.getElementById('aField');
        if (f && f.value.trim()) chkAllergy(f.value);
        updateUhidPreview();
        attachFormValidation();
    });

    // ══════════════════════════════════════════════════════
    //  RECHERCHE LIVE (dropdown)
    // ══════════════════════════════════════════════════════
    (function () {
        const inp  = document.getElementById('searchInput');
        const drop = document.getElementById('liveDropdown');
        if (!inp || !drop) return;

        let timer = null, focused = -1, lastQ = '', items = [];

        function escHtml(s) {
            return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function hl(str, q) {
            if (!q || !str) return escHtml(str);
            const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
            return escHtml(str).replace(re,'<mark>$1</mark>');
        }
        function fmtDob(dob) {
            if (!dob || dob === '0000-00-00') return null;
            const [y,m,d] = dob.split('-');
            return `${d}/${m}/${y}`;
        }

        function render(data, q) {
            items = data; focused = -1;
            const qName = q.replace(/\b(19|20)\d{2}\b/, '').trim();
            if (!data.length) {
                drop.innerHTML = `<div class="dd-hint">Astuce : <em>Benali 2000</em> pour filtrer par année</div>
                             <div class="dd-empty">Aucun résultat — <a href="reception.php?mode=new" style="color:var(--green)">Créer</a></div>`;
            } else {
                drop.innerHTML = `<div class="dd-hint">Astuce : <em>Benali 2000</em> pour filtrer par année</div>` +
                    data.map((p, i) => {
                        const ini = (p.full_name || '?').charAt(0).toUpperCase();
                        const dob = fmtDob(p.dob);
                        const agePart = p.age != null ? `${p.age} ans` : null;
                        const dobLine = [dob, agePart].filter(Boolean).join(' · ');
                        const badges = [
                            p.blood_type ? `<span class="dd-badge blood">${escHtml(p.blood_type)}</span>` : '',
                            p.allergies  ? `<span class="dd-badge allergy">⚠ Allergie</span>` : '',
                        ].filter(Boolean).join('');
                        return `<div class="dd-item" role="option" data-pid="${p.id}"
                                 onmousedown="window.location.href='ticket.php?pid=${p.id}'"
                                 onmouseover="moveFocus(${i})">
                              <div class="dd-ava">${ini}</div>
                              <div style="min-width:0;flex:1">
                                <div class="dd-name">${hl(p.full_name, qName)}</div>
                                <div class="dd-meta">📞 ${hl(p.phone, q)} · <span style="font-family:monospace">${hl(p.uhid, q)}</span></div>
                                ${dobLine ? `<div class="dd-dob">🎂 ${escHtml(dobLine)}</div>` : ''}
                                ${badges ? `<div class="dd-badges">${badges}</div>` : ''}
                              </div>
                              <span class="dd-arrow">→ Ticket</span>
                            </div>`;
                    }).join('');
            }
            drop.classList.add('open');
        }

        function close()     { drop.classList.remove('open'); focused = -1; }
        function moveFocus(idx) {
            const els = drop.querySelectorAll('.dd-item');
            els.forEach(e => e.classList.remove('focused'));
            if (idx >= 0 && idx < els.length) {
                els[idx].classList.add('focused');
                els[idx].scrollIntoView({ block:'nearest' });
            }
            focused = idx;
        }
        function doSearch(q) {
            if (q === lastQ) return; lastQ = q;
            if (q.length < 2) { close(); return; }
            drop.innerHTML = '<div class="dd-spinner">Recherche…</div>';
            drop.classList.add('open');
            fetch(`search_ajax.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => render(data, q))
                .catch(() => { drop.innerHTML = '<div class="dd-empty">Erreur réseau</div>'; });
        }

        inp.addEventListener('input', function() {
            clearTimeout(timer);
            const q = this.value.trim();
            if (q.length < 2) { close(); lastQ = ''; return; }
            timer = setTimeout(() => doSearch(q), 220);
        });
        inp.addEventListener('keydown', function(e) {
            const els = drop.querySelectorAll('.dd-item');
            if (!drop.classList.contains('open')) return;
            if (e.key === 'ArrowDown')  { e.preventDefault(); moveFocus(focused + 1); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); moveFocus(focused - 1); }
            else if (e.key === 'Enter' && focused >= 0 && items[focused]) {
                e.preventDefault();
                window.location.href = 'ticket.php?pid=' + items[focused].id;
            } else if (e.key === 'Escape') { close(); }
        });
        document.addEventListener('click', e => {
            if (!drop.contains(e.target) && e.target !== inp) close();
        });
    })();
    // ══════════════════════════════════════════════════════
    //  SAISIE DATE DE NAISSANCE (JJ / MM / AAAA)
    // ══════════════════════════════════════════════════════
    (function () {
        const dd   = document.getElementById('dob_dd');
        const mm   = document.getElementById('dob_mm');
        const yyyy = document.getElementById('dob_yyyy');
        const hidden = document.getElementById('dob_input');
        const hint   = document.getElementById('dob_hint');
        if (!dd || !mm || !yyyy || !hidden) return;

        // Pré-remplir depuis le champ caché (si retour POST)
        const existingVal = hidden.value;
        if (existingVal && existingVal !== '0000-00-00') {
            const parts = existingVal.split('-');
            if (parts.length === 3) {
                yyyy.value = parts[0];
                mm.value   = parts[1];
                dd.value   = parts[2];
            }
        }

        function setHint(msg, ok) {
            if (!hint) return;
            hint.textContent = msg;
            hint.style.color = ok ? 'var(--green,#38a169)' : 'var(--red,#e53e3e)';
        }

        function applyBorder(el, ok) {
            el.style.borderColor = ok ? 'var(--green,#38a169)' : 'var(--red,#e53e3e)';
            el.style.boxShadow   = ok
                ? '0 0 0 2px rgba(56,161,105,.12)'
                : '0 0 0 2px rgba(229,62,62,.15)';
        }

        function resetBorders() {
            [dd, mm, yyyy].forEach(el => { el.style.borderColor = ''; el.style.boxShadow = ''; });
            setHint('', true);
        }

        function validate() {
            const d = parseInt(dd.value, 10);
            const m = parseInt(mm.value, 10);
            const y = parseInt(yyyy.value, 10);

            if (!dd.value && !mm.value && !yyyy.value) { hidden.value = ''; resetBorders(); updateUhidPreview(); return; }

            if (!dd.value || !mm.value || !yyyy.value) return; // incomplet, on attend

            if (isNaN(d) || d < 1 || d > 31)  { applyBorder(dd,   false); setHint('⚠ Jour invalide (01–31)', false); return; }
            if (isNaN(m) || m < 1 || m > 12)  { applyBorder(mm,   false); setHint('⚠ Mois invalide (01–12)', false); return; }
            if (isNaN(y) || yyyy.value.length < 4) { applyBorder(yyyy, false); setHint('⚠ Année sur 4 chiffres', false); return; }
            if (y < 1900 || y > new Date().getFullYear()) { applyBorder(yyyy, false); setHint('⚠ Année hors limite', false); return; }

            // Vérifier que la date existe réellement
            const date = new Date(y, m - 1, d);
            if (date.getFullYear() !== y || date.getMonth() !== m - 1 || date.getDate() !== d) {
                [dd, mm].forEach(el => applyBorder(el, false));
                setHint('⚠ Date inexistante (ex: 30 février)', false);
                return;
            }
            if (date >= new Date()) { applyBorder(dd, false); setHint('⚠ Date dans le futur', false); return; }

            // ✅ Valide
            [dd, mm, yyyy].forEach(el => applyBorder(el, true));
            const iso = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            hidden.value = iso;
            const age = Math.floor((Date.now() - date.getTime()) / 31557600000);
            setHint(`✓ ${age} ans`, true);
            updateUhidPreview();
        }

        // Auto-avance : après 2 chiffres dans JJ → focus MM, après 2 dans MM → focus AAAA
        function onInput(e) {
            const el = e.target;
            // Garder seulement les chiffres
            el.value = el.value.replace(/\D/g,'');

            if (el === dd   && el.value.length === 2) mm.focus();
            if (el === mm   && el.value.length === 2) yyyy.focus();

            validate();
        }

        // Backspace depuis un champ vide → reculer
        function onKeydown(e) {
            if (e.key === 'Backspace' && e.target.value === '') {
                if (e.target === mm)   { e.preventDefault(); dd.focus();   dd.setSelectionRange(dd.value.length, dd.value.length); }
                if (e.target === yyyy) { e.preventDefault(); mm.focus();   mm.setSelectionRange(mm.value.length, mm.value.length); }
            }
        }

        [dd, mm, yyyy].forEach(el => {
            el.addEventListener('input',   onInput);
            el.addEventListener('keydown', onKeydown);
            el.addEventListener('blur',    validate);
        });
    })();

</script>
</body>
</html>