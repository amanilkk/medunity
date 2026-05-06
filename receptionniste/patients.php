<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  patients.php — Liste et recherche des patients
// ================================================================
require_once 'functions.php';
requireReceptionniste();
include '../connection.php';

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

// ── Requête ──────────────────────────────────────────────────────
$where  = '1=1';
$params = [];
$types  = '';

if ($search !== '') {
    $kw      = '%' . $search . '%';
    $where   = "(u.full_name LIKE ? OR u.phone LIKE ? OR pt.uhid LIKE ? OR pt.nic LIKE ?)";
    $params  = [$kw, $kw, $kw, $kw];
    $types   = 'ssss';
}

// Total pour pagination
$count_sql  = "SELECT COUNT(*) c FROM patients pt INNER JOIN users u ON u.id = pt.user_id WHERE $where";
$total_rows = safeCount($database, $count_sql, $types, $params);
$total_pages = max(1, (int)ceil($total_rows / $limit));

// Données
$sql = "SELECT pt.id, pt.uhid, pt.dob, pt.gender, pt.blood_type, pt.allergies,
               u.full_name, u.phone, u.email,
               pt.created_at
        FROM patients pt
        INNER JOIN users u ON u.id = pt.user_id
        WHERE $where
        ORDER BY pt.id DESC
        LIMIT ? OFFSET ?";

$all_params = array_merge($params, [$limit, $offset]);
$all_types  = $types . 'ii';

$stmt = $database->prepare($sql);
if (!$stmt) die('Erreur BD: ' . htmlspecialchars($database->error));
if ($all_params) $stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$patients = $stmt->get_result();

function fmtDobPat(?string $dob): string {
    if (!$dob || $dob === '0000-00-00') return '—';
    try { return (new DateTime($dob))->format('d/m/Y'); }
    catch (Exception $e) { return $dob; }
}
function calcAgePat(?string $dob): string {
    if (!$dob || $dob === '0000-00-00') return '—';
    try { return (int)(new DateTime())->diff(new DateTime($dob))->y . ' ans'; }
    catch (Exception $e) { return '—'; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Patients</title>
    <link rel="stylesheet" href="recept.css">
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Patients</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="reception.php" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouveau patient
            </a>
        </div>
    </div>
    <div class="page-body">

        <!-- Barre de recherche live -->
        <form method="GET" class="filter-bar" id="searchForm" autocomplete="off">
            <label>Rechercher :</label>
            <div style="position:relative;width:280px">
                <input class="input" type="text" name="q" id="liveSearchInput"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Nom, téléphone, UHID…"
                       style="width:100%;padding-right:32px"
                       autocomplete="off">
                <span id="searchSpinner" style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:.75rem;color:var(--text3)">⏳</span>
                <?php if ($search): ?>
                    <button type="button" id="clearBtn" onclick="clearSearch()"
                            style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);font-size:1rem;line-height:1">×</button>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
            <?php if ($search): ?>
                <a href="patients.php" class="btn btn-secondary btn-sm" id="resetBtn">Réinitialiser</a>
            <?php endif; ?>
            <span style="margin-left:auto;font-size:.75rem;color:var(--text2)" id="countLabel">
        <?php echo $total_rows; ?> patient(s)
      </span>
        </form>

        <div class="card">
            <div class="card-head">
                <h3>Liste des patients<?php echo $search ? ' — "' . htmlspecialchars($search) . '"' : ''; ?></h3>
                <span style="font-size:.75rem;color:var(--text2)"><?php echo $patients->num_rows; ?> / <?php echo $total_rows; ?></span>
            </div>

            <?php if ($patients->num_rows === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <h3>Aucun patient trouvé</h3>
                    <p><a href="reception.php" style="color:var(--green);font-weight:600">Créer un nouveau patient →</a></p>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead><tr>
                        <th>UHID</th><th>Nom</th><th>Téléphone</th>
                        <th>Date de naissance</th><th>Groupe sanguin</th>
                        <th>Allergie</th><th>Inscrit le</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php while ($r = $patients->fetch_assoc()): ?>
                        <tr>
                            <td style="font-family:'DM Mono',monospace;font-size:.73rem;color:var(--text2)">
                                <?php echo htmlspecialchars($r['uhid']); ?>
                            </td>
                            <td>
                                <div style="font-weight:600"><?php echo htmlspecialchars($r['full_name']); ?></div>
                                <?php $g = $r['gender'] === 'M' ? '♂' : ($r['gender'] === 'F' ? '♀' : ''); ?>
                                <?php if ($g): ?><div style="font-size:.7rem;color:var(--text3)"><?php echo $g; ?></div><?php endif; ?>
                            </td>
                            <td style="font-size:.8rem"><?php echo htmlspecialchars($r['phone'] ?? '—'); ?></td>
                            <td style="font-size:.8rem">
                                <?php echo fmtDobPat($r['dob']); ?>
                                <?php if ($r['dob'] && $r['dob'] !== '0000-00-00'): ?>
                                    <div style="font-size:.7rem;color:var(--text3)"><?php echo calcAgePat($r['dob']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.8rem;font-weight:700;color:var(--red)">
                                <?php echo $r['blood_type'] ? htmlspecialchars($r['blood_type']) : '<span style="color:var(--text3);font-weight:400">—</span>'; ?>
                            </td>
                            <td>
                                <?php if ($r['allergies']): ?>
                                    <span style="background:var(--red-l);color:var(--red);padding:2px 7px;border-radius:20px;font-size:.68rem;font-weight:700">
                ⚠ <?php echo htmlspecialchars(mb_substr($r['allergies'], 0, 22)); ?>
              </span>
                                <?php else: echo '<span style="color:var(--text3);font-size:.74rem">—</span>'; endif; ?>
                            </td>
                            <td style="font-size:.73rem;color:var(--text2)">
                                <?php echo $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '—'; ?>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    <a href="ticket.php?pid=<?php echo $r['id']; ?>" class="btn btn-primary btn-sm">
                                        🎟 Ticket
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="display:flex;gap:6px;align-items:center;padding:14px 16px;border-top:1px solid var(--border);flex-wrap:wrap">
                        <?php if ($page > 1): ?>
                            <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>" class="btn btn-secondary btn-sm">← Préc.</a>
                        <?php endif; ?>
                        <?php for ($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
                            <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"
                               class="btn btn-sm <?php echo $p === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>" class="btn btn-secondary btn-sm">Suiv. →</a>
                        <?php endif; ?>
                        <span style="margin-left:auto;font-size:.73rem;color:var(--text2)">
          Page <?php echo $page; ?> / <?php echo $total_pages; ?>
        </span>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>
</div>

<script>
    (function () {
        const inp     = document.getElementById('liveSearchInput');
        const tbody   = document.querySelector('.tbl tbody');
        const tbl     = document.querySelector('.tbl');
        const empty   = document.querySelector('.empty');
        const spinner = document.getElementById('searchSpinner');
        const countLbl= document.getElementById('countLabel');
        const cardHead= document.querySelector('.card-head h3');

        if (!inp) return;

        let timer = null, lastQ = inp.value.trim();

        function escHtml(s) {
            return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }
        function fmtDob(d) {
            if (!d || d === '0000-00-00') return '—';
            const [y,m,dd] = d.split('-'); return `${dd}/${m}/${y}`;
        }
        function calcAge(d) {
            if (!d || d === '0000-00-00') return null;
            const diff = Date.now() - new Date(d).getTime();
            return Math.floor(diff / 31557600000);
        }

        function renderRows(data, q) {
            if (!tbody) return;

            // Mettre à jour le compteur
            countLbl && (countLbl.textContent = data.length + ' patient(s)');
            if (cardHead) {
                cardHead.textContent = 'Liste des patients' + (q ? ` — "${q}"` : '');
            }

            if (data.length === 0) {
                // Afficher état vide
                tbl && (tbl.style.display = 'none');
                if (!document.getElementById('liveEmpty')) {
                    const div = document.createElement('div');
                    div.id = 'liveEmpty';
                    div.className = 'empty';
                    div.innerHTML = `<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <h3>Aucun patient trouvé</h3>
                    <p><a href="reception.php" style="color:var(--green);font-weight:600">Créer un nouveau patient →</a></p>`;
                    tbl && tbl.parentNode.appendChild(div);
                }
                return;
            }

            // Retirer état vide si présent
            const liveEmpty = document.getElementById('liveEmpty');
            if (liveEmpty) liveEmpty.remove();
            if (empty) empty.style.display = 'none';
            tbl && (tbl.style.display = '');

            const hl = (str, kw) => {
                if (!str || !kw) return escHtml(str || '');
                const re = new RegExp('(' + kw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
                return escHtml(str).replace(re, '<mark style="background:#FFF3CD;color:#7B5800;border-radius:2px;padding:0 1px">$1</mark>');
            };

            tbody.innerHTML = data.map(r => {
                const g = r.gender === 'M' ? '♂' : (r.gender === 'F' ? '♀' : '');
                const age = calcAge(r.dob);
                const allergyHtml = r.allergies
                    ? `<span style="background:var(--red-l);color:var(--red);padding:2px 7px;border-radius:20px;font-size:.68rem;font-weight:700">⚠ ${escHtml(r.allergies.substring(0,22))}</span>`
                    : '<span style="color:var(--text3);font-size:.74rem">—</span>';
                const bloodHtml = r.blood_type
                    ? `<span style="font-size:.8rem;font-weight:700;color:var(--red)">${escHtml(r.blood_type)}</span>`
                    : '<span style="color:var(--text3);font-weight:400">—</span>';
                const createdAt = r.created_at ? r.created_at.substring(0,10).split('-').reverse().join('/') : '—';

                return `<tr>
                <td style="font-family:'DM Mono',monospace;font-size:.73rem;color:var(--text2)">${hl(r.uhid, q)}</td>
                <td>
                    <div style="font-weight:600">${hl(r.full_name, q)}</div>
                    ${g ? `<div style="font-size:.7rem;color:var(--text3)">${g}</div>` : ''}
                </td>
                <td style="font-size:.8rem">${hl(r.phone || '—', q)}</td>
                <td style="font-size:.8rem">
                    ${fmtDob(r.dob)}
                    ${age != null ? `<div style="font-size:.7rem;color:var(--text3)">${age} ans</div>` : ''}
                </td>
                <td>${bloodHtml}</td>
                <td>${allergyHtml}</td>
                <td style="font-size:.73rem;color:var(--text2)">${createdAt}</td>
                <td>
                    <div class="tbl-actions">
                        <a href="ticket.php?pid=${r.id}" class="btn btn-primary btn-sm">🎟 Ticket</a>
                    </div>
                </td>
            </tr>`;
            }).join('');
        }

        function doSearch(q) {
            if (q === lastQ) return;
            lastQ = q;

            if (q.length < 2) {
                // Réinitialiser : recharger la page sans filtre
                if (q.length === 0) window.location.href = 'patients.php';
                return;
            }

            spinner && (spinner.style.display = 'inline');

            fetch(`search_ajax.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    spinner && (spinner.style.display = 'none');
                    renderRows(data, q);
                    // Mettre à jour l'URL sans recharger
                    history.replaceState(null, '', `patients.php?q=${encodeURIComponent(q)}`);
                })
                .catch(() => {
                    spinner && (spinner.style.display = 'none');
                });
        }

        inp.addEventListener('input', function () {
            clearTimeout(timer);
            const q = this.value.trim();
            // Effacer bouton X
            let clrBtn = document.getElementById('clearBtn');
            if (!clrBtn && q.length > 0) {
                clrBtn = document.createElement('button');
                clrBtn.id = 'clearBtn';
                clrBtn.type = 'button';
                clrBtn.innerHTML = '×';
                clrBtn.onclick = () => window.clearSearch();
                clrBtn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);font-size:1rem;line-height:1';
                inp.parentNode.appendChild(clrBtn);
            } else if (clrBtn && q.length === 0) {
                clrBtn.remove();
            }
            timer = setTimeout(() => doSearch(q), 280);
        });

        window.clearSearch = function() {
            inp.value = '';
            lastQ = '';
            window.location.href = 'patients.php';
        };
    })();
</script>
</body>
</html>