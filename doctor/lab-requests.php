<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
require_once 'doctor_functions.php';
requireDoctor();
include '../connection.php';

$doctor    = getCurrentDoctor($database);
$doctor_id = $doctor['doctor_id'];

$patient_id = (int)($_GET['patient_id'] ?? 0);
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p_id      = (int)$_POST['patient_id'];
    $priority  = in_array($_POST['priority'] ?? '', ['normal','urgent','critical'])
                 ? $_POST['priority'] : 'normal';
    $preset    = array_filter($_POST['preset_tests'] ?? []);
    $custom    = array_filter(array_map('trim', $_POST['custom_tests'] ?? []));
    $all_tests = array_merge(array_values($preset), array_values($custom));

    if ($p_id <= 0 || empty($all_tests)) {
        $error = "Veuillez sélectionner un patient et au moins une analyse.";
    } else {
        $stmt = $database->prepare("
            INSERT INTO lab_tests
                (patient_id, doctor_id, test_name, priority, status, created_at)
            VALUES (?, ?, ?, ?, 'pending_payment', NOW())
        ");
        foreach ($all_tests as $t) {
            if ($t) {
                $stmt->bind_param("iiss", $p_id, $doctor_id, $t, $priority);
                $stmt->execute();
            }
        }
        $success = count($all_tests) . " demande(s) envoyée(s) au laboratoire avec succès.";
        $patient_id = $p_id;
    }
}

// Patients du médecin courant
$patients = getAllPatients($database);

// Catégories d'analyses
$preset_categories = [
    'Hématologie'    => [
        'Hémogramme (NFS)', 'Réticulocytes', 'Frottis sanguin',
        'Groupe sanguin ABO/Rh', 'Test de Coombs',
    ],
    'Biochimie'      => [
        'Glycémie à jeun', 'HbA1c', 'Créatinine / DFG', 'Urée', 'Acide urique',
        'Bilan lipidique (CT, TG, HDL, LDL)', 'ALAT / ASAT', 'Gamma GT',
        'Phosphatases alcalines', 'Bilirubine totale/directe', 'Albumine', 'Protéines totales',
    ],
    'Endocrinologie' => [
        'TSH ultrasensible', 'T3 libre', 'T4 libre', 'Cortisol',
        'Insuline à jeun', 'Testostérone', 'FSH / LH', 'Prolactine',
    ],
    'Infectiologie'  => [
        'CRP', 'VS', 'Procalcitonine', 'ECBU', 'Coproculture',
        'Hémoculture', 'Sérologie Helicobacter pylori',
    ],
    'Marqueurs'      => [
        'PSA total', 'CA 125', 'CA 19-9', 'AFP', 'CEA',
        'Ferritine', 'Vitamine B12', 'Vitamine D',
    ],
    'Hémostase'      => ['TP / INR', 'TCA', 'Fibrinogène', 'D-Dimères'],
    'Ionogramme'     => ['Sodium', 'Potassium', 'Calcium', 'Magnésium', 'Chlore', 'Bicarbonates'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes d'Analyses</title>
    <link rel="stylesheet" href="doctor.css">
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">🧪 Demandes d'Analyses</span>
            <div class="topbar-right">
                <?php if ($patient_id): ?>
                    <a href="patient-profile.php?id=<?= $patient_id ?>"
                       class="btn btn-secondary btn-sm">← Dossier patient</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-body">

            <?php if ($error):   ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 280px;gap:24px;align-items:start">

                <!-- Analyses -->
                <div class="card fade-up">
                    <div class="card-head">
                        <h3>🧪 Sélectionner les analyses</h3>
                        <span class="text-muted" style="font-size:0.75rem" id="count-selected">
                            0 analyse(s) sélectionnée(s)
                        </span>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="lab-form">

                            <?php foreach ($preset_categories as $cat => $tests): ?>
                            <div style="margin-bottom:22px">
                                <div class="section-title"><?= htmlspecialchars($cat) ?></div>
                                <div class="lab-grid" style="margin-top:8px">
                                    <?php foreach ($tests as $test): ?>
                                        <label class="lab-checkbox">
                                            <input type="checkbox" name="preset_tests[]"
                                                   value="<?= htmlspecialchars($test) ?>"
                                                   onchange="updateCount()">
                                            <span><?= htmlspecialchars($test) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <hr class="divider">

                            <!-- Analyses personnalisées -->
                            <div class="section-title">Analyses personnalisées</div>
                            <div id="custom-tests" style="margin-top:8px">
                                <div class="form-group">
                                    <input type="text" name="custom_tests[]" class="input"
                                           placeholder="Ex: ANCA, Électrophorèse des protéines...">
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm"
                                    onclick="addCustom()" style="margin-bottom:24px">
                                + Ajouter une analyse
                            </button>

                            <!-- Bouton submit (dupliqué ici pour facilité) -->
                            <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
                                🧪 Envoyer au Laboratoire
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Configuration sticky -->
                <div style="position:sticky;top:80px">
                    <div class="card fade-up" style="animation-delay:0.08s;margin-bottom:16px">
                        <div class="card-head"><h3>⚙️ Configuration</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Patient <span class="req">*</span></label>
                                <select name="patient_id" class="input" required form="lab-form">
                                    <option value="">— Choisir un patient —</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?= $p['id'] ?>"
                                            <?= $p['id'] == $patient_id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['full_name']) ?>
                                            (<?= htmlspecialchars($p['uhid']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Priorité</label>
                                <div style="display:flex;gap:8px;margin-top:4px">
                                    <?php
                                    $priorities = [
                                        'normal' => ['label'=>'Normal', 'color'=>'var(--primary-dim)',   'border'=>'rgba(59,123,255,0.3)', 'tc'=>'var(--primary)'],
                                        'urgent'  => ['label'=>'Urgent',  'color'=>'var(--warning-dim)',   'border'=>'var(--warning-border)', 'tc'=>'var(--warning)'],
                                        'critical' => ['label'=>'Critique',    'color'=>'var(--danger-dim)',    'border'=>'var(--danger-border)',  'tc'=>'var(--danger)'],
                                    ];
                                    foreach ($priorities as $val => $p):
                                    ?>
                                    <label style="flex:1;cursor:pointer">
                                        <input type="radio" name="priority" value="<?= $val ?>"
                                               form="lab-form"
                                               <?= $val === 'normal' ? 'checked' : '' ?>
                                               style="display:none"
                                               onchange="highlightPrio(this)">
                                        <div class="prio-label" data-prio="<?= $val ?>"
                                             style="text-align:center;padding:8px 4px;
                                                    border-radius:var(--r-sm);
                                                    border:1px solid var(--border);
                                                    font-size:0.72rem;font-weight:700;
                                                    color:var(--text-muted);
                                                    transition:all 0.2s;cursor:pointer">
                                            <?= $p['label'] ?>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info box -->
                    <div class="card fade-up" style="animation-delay:0.14s">
                        <div class="card-body" style="padding:16px">
                            <div style="font-size:0.72rem;color:var(--text-light);line-height:1.6">
                                <p style="margin-bottom:6px">
                                    ℹ️ Les demandes envoyées apparaissent dans
                                    <strong style="color:var(--text)">le module Laboratoire</strong>
                                    avec le statut <em>En attente de paiement</em>.
                                </p>
                                <p>
                                    Les résultats seront automatiquement intégrés
                                    au dossier du patient.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
    function addCustom() {
        const div = document.createElement('div');
        div.className = 'form-group';
        div.innerHTML = '<input type="text" name="custom_tests[]" class="input" placeholder="Nom de l\'analyse...">';
        document.getElementById('custom-tests').appendChild(div);
        div.querySelector('input').focus();
    }

    function updateCount() {
        const checked = document.querySelectorAll('input[name="preset_tests[]"]:checked').length;
        document.getElementById('count-selected').textContent =
            checked + ' analyse(s) sélectionnée(s)';
    }

    const PRIO_COLORS = {
        normal:   { bg:'var(--primary-dim)',  border:'rgba(59,123,255,0.35)', tc:'var(--primary)' },
        urgent:  { bg:'var(--warning-dim)',  border:'var(--warning-border)', tc:'var(--warning)' },
        critical: { bg:'var(--danger-dim)',   border:'var(--danger-border)',  tc:'var(--danger)'  },
    };

    function highlightPrio(radio) {
        document.querySelectorAll('.prio-label').forEach(el => {
            el.style.background    = '';
            el.style.borderColor   = 'var(--border)';
            el.style.color         = 'var(--text-muted)';
        });
        const label = document.querySelector(`.prio-label[data-prio="${radio.value}"]`);
        if (label) {
            const c = PRIO_COLORS[radio.value];
            label.style.background  = c.bg;
            label.style.borderColor = c.border;
            label.style.color       = c.tc;
        }
    }

    // Initialiser le style de "normal" au chargement
    document.addEventListener('DOMContentLoaded', () => {
        const checked = document.querySelector('input[name="priority"]:checked');
        if (checked) highlightPrio(checked);
    });
    </script>
</body>
</html>