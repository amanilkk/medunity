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
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id    = (int)$_POST['patient_id'];
    $diagnosis     = trim($_POST['diagnosis'] ?? '');
    $symptoms      = trim($_POST['symptoms'] ?? '');
    $clinical_notes = trim($_POST['clinical_notes'] ?? '');
    $treatment_plan = trim($_POST['treatment_plan'] ?? '');

    if ($patient_id <= 0 || empty($diagnosis)) {
        $error = "Veuillez sélectionner un patient et entrer un diagnostic.";
    } else {
        // Colonnes correctes : diagnosis, symptoms, clinical_notes, treatment_plan
        $stmt = $database->prepare("
            INSERT INTO medical_records (patient_id, doctor_id, diagnosis, symptoms, clinical_notes, treatment_plan, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iissss", $patient_id, $doctor_id, $diagnosis, $symptoms, $clinical_notes, $treatment_plan);
        if ($stmt->execute()) {
            $success = "Diagnostic enregistré avec succès.";
        } else {
            $error = "Erreur : " . $database->error;
        }
    }
}

// Patients accessibles (tous les patients)
$stmt = $database->prepare("
    SELECT DISTINCT p.id, u.full_name, p.uhid, p.allergies
    FROM patients p JOIN users u ON u.id = p.user_id
    ORDER BY u.full_name
");
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Historique si patient sélectionné
$recent_records = [];
$selected_patient = null;
if ($patient_id > 0) {
    $stmt = $database->prepare("
        SELECT mr.diagnosis, mr.symptoms, mr.treatment_plan, mr.clinical_notes, mr.created_at, u.full_name AS doctor_name
        FROM medical_records mr
        JOIN doctors d ON d.id = mr.doctor_id
        JOIN users u ON u.id = d.user_id
        WHERE mr.patient_id = ? AND mr.diagnosis IS NOT NULL AND mr.diagnosis != ''
        ORDER BY mr.created_at DESC LIMIT 5
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $recent_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Infos du patient sélectionné
    $stmt2 = $database->prepare("SELECT p.allergies, p.medical_history, u.full_name FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
    $stmt2->bind_param("i", $patient_id);
    $stmt2->execute();
    $selected_patient = $stmt2->get_result()->fetch_assoc();
}

$cim10 = [
    'Hypertension artérielle (I10)',
    'Diabète type 2 (E11.9)',
    'Inf. voies respiratoires sup. (J06.9)',
    'Lombalgie (M54.5)',
    'Gastro-entérite aiguë (A09)',
    'Anémie ferriprive (D50.9)',
    'Asthme bronchique (J45.9)',
    'Dépression réactionnelle (F32.9)',
    'Rhinite allergique (J30.4)',
    'Hypothyroïdie (E03.9)',
    'Cystite aiguë (N30.0)',
    'Otite moyenne aiguë (H66.9)',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau Diagnostic</title>
    <link rel="stylesheet" href="doctor.css">
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">📋 Nouveau Diagnostic</span>
            <div class="topbar-right">
                <?php if ($patient_id): ?>
                    <a href="patient-profile.php?id=<?= $patient_id ?>" class="btn btn-secondary btn-sm">← Dossier patient</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-body">
            <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <?php if ($selected_patient && $selected_patient['allergies']): ?>
                <div class="alert alert-warning">⚠️ <strong>Allergies connues :</strong> <?= htmlspecialchars($selected_patient['allergies']) ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">

                <!-- Formulaire principal -->
                <div class="card">
                    <div class="card-head"><h3>📋 Saisir le diagnostic</h3></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>Patient <span class="req">*</span></label>
                                <div style="display:flex;gap:8px">
                                    <select name="patient_id" class="input" required id="patient-select">
                                        <option value="">— Sélectionner un patient —</option>
                                        <?php foreach ($patients as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $patient_id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['full_name']) ?> (<?= $p['uhid'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-secondary" onclick="loadPatient()">Charger</button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Diagnostic principal (CIM-10) <span class="req">*</span></label>
                                <textarea name="diagnosis" id="diag-field" class="input" rows="4"
                                    placeholder="Ex: Hypertension artérielle essentielle (I10)" required></textarea>
                                <div style="margin-top:8px">
                                    <div class="section-title">Suggestions CIM-10 rapides</div>
                                    <div style="display:flex;flex-wrap:wrap;gap:5px">
                                        <?php foreach ($cim10 as $c): ?>
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                style="font-size:0.72rem"
                                                onclick="appendDiag('<?= addslashes($c) ?>')">
                                                <?= htmlspecialchars($c) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Symptômes observés</label>
                                <textarea name="symptoms" class="input" rows="3"
                                    placeholder="Fièvre, toux, douleur thoracique..."></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Plan de traitement</label>
                                    <textarea name="treatment_plan" class="input" rows="4"
                                        placeholder="Médicaments prescrits, durée, suivi..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Notes cliniques</label>
                                    <textarea name="clinical_notes" class="input" rows="4"
                                        placeholder="Observations, examens réalisés..."></textarea>
                                </div>
                            </div>

                            <div style="display:flex;gap:10px;margin-top:8px">
                                <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer le diagnostic</button>
                                <?php if ($patient_id): ?>
                                    <a href="prescription.php?patient_id=<?= $patient_id ?>" class="btn btn-success">💊 Prescrire</a>
                                    <a href="patient-profile.php?id=<?= $patient_id ?>" class="btn btn-secondary">Dossier</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Colonne droite -->
                <div>
                    <?php if ($patient_id && !empty($recent_records)): ?>
                    <div class="card">
                        <div class="card-head"><h3>🕐 Diagnostics récents</h3></div>
                        <div class="card-body" style="padding:14px">
                            <div class="timeline" style="padding-left:20px">
                                <?php foreach ($recent_records as $r): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot blue"></div>
                                    <div class="timeline-card">
                                        <div class="timeline-date"><?= date('d/m/Y', strtotime($r['created_at'])) ?></div>
                                        <div class="timeline-title" style="font-size:0.8rem"><?= nl2br(htmlspecialchars($r['diagnosis'])) ?></div>
                                        <?php if ($r['treatment_plan']): ?>
                                            <div class="timeline-body" style="font-size:0.75rem">🔹 <?= nl2br(htmlspecialchars($r['treatment_plan'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($patient_id): ?>
                    <div class="card">
                        <div class="card-body"><div class="empty-state"><p>Aucun diagnostic précédent.</p></div></div>
                    </div>
                    <?php else: ?>
                    <div class="card">
                        <div class="card-body" style="color:var(--text-muted);font-size:0.85rem;padding:20px">
                            Sélectionnez un patient pour voir son historique.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script>
    function appendDiag(text) {
        const ta = document.getElementById('diag-field');
        ta.value = (ta.value ? ta.value + '\n' : '') + text;
        ta.focus();
    }
    function loadPatient() {
        const sel = document.getElementById('patient-select');
        if (sel.value) {
            window.location.href = 'diagnosis.php?patient_id=' + sel.value;
        }
    }
    </script>
</body>
</html>
