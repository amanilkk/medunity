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
    $patient_id = (int)$_POST['patient_id'];
    $note       = trim($_POST['note'] ?? '');

    if ($patient_id <= 0 || empty($note)) {
        $error = "Veuillez sélectionner un patient et saisir une note.";
    } else {
        // Colonne correcte : clinical_notes (PAS "notes" qui n'existe pas dans medical_records)
        $stmt = $database->prepare("
            INSERT INTO medical_records (patient_id, doctor_id, clinical_notes, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $patient_id, $doctor_id, $note);
        if ($stmt->execute()) {
            $success = "Note médicale enregistrée avec succès.";
        } else {
            $error = "Erreur : " . $database->error;
        }
    }
}

// Patients du médecin
$patients = getDoctorPatients($database, $doctor_id);

// Notes récentes — colonne correcte : clinical_notes
$recent_notes = [];
if ($patient_id > 0) {
    $stmt = $database->prepare("
        SELECT mr.clinical_notes, mr.created_at, u.full_name AS doctor_name
        FROM medical_records mr
        JOIN doctors d ON d.id = mr.doctor_id
        JOIN users u   ON u.id = d.user_id
        WHERE mr.patient_id = ?
          AND (mr.diagnosis IS NULL OR mr.diagnosis = '')
          AND mr.clinical_notes IS NOT NULL AND mr.clinical_notes != ''
        ORDER BY mr.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $recent_notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$templates = [
    'Suivi stable'    => "Patient vu en consultation de suivi. État général stable. Pas de modification du traitement en cours.",
    'Amélioration'    => "Nette amélioration clinique depuis la dernière consultation. Symptômes en régression. Poursuite du traitement actuel.",
    'Urgence'         => "Patient vu en urgence pour [motif]. Examen clinique : [résultats]. Conduite tenue : [traitement/orientation].",
    'Bilan annuel'    => "Bilan annuel réalisé. Poids : __ kg. TA : __/__. Pouls : __/min. Aucune anomalie clinique notable.",
    'Post-opératoire' => "Suivi post-opératoire. Cicatrisation en cours. Pas de signes infectieux. Prochain contrôle dans [délai].",
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes Médicales</title>
    <link rel="stylesheet" href="doctor.css">
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">📝 Notes Médicales</span>
            <div class="topbar-right">
                <?php if ($patient_id): ?>
                    <a href="patient-profile.php?id=<?= $patient_id ?>" class="btn btn-secondary btn-sm">← Dossier patient</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-body">

            <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">

                <!-- Formulaire -->
                <div class="card">
                    <div class="card-head"><h3>📝 Ajouter une note médicale</h3></div>
                    <div class="card-body">
                        <form method="POST">

                            <div class="form-group">
                                <label>Patient <span class="req">*</span></label>
                                <div style="display:flex;gap:8px">
                                    <select name="patient_id" class="input" required id="patient-select">
                                        <option value="">— Choisir un patient —</option>
                                        <?php foreach ($patients as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $patient_id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['uhid']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-secondary" onclick="loadPatient()">Charger</button>
                                </div>
                            </div>

                            <!-- Templates rapides -->
                            <div class="form-group">
                                <div class="section-title">Templates rapides</div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
                                    <?php foreach ($templates as $name => $tpl): ?>
                                        <button type="button" class="btn btn-secondary btn-sm"
                                            style="font-size:0.74rem"
                                            onclick="setNote(<?= json_encode($tpl) ?>)">
                                            <?= htmlspecialchars($name) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Observation / Note clinique <span class="req">*</span></label>
                                <textarea name="note" id="note-field" class="input" rows="9"
                                    placeholder="Évolution du patient, observations cliniques, recommandations..."
                                    required></textarea>
                                <div style="text-align:right;font-size:0.7rem;color:var(--text-light);margin-top:4px">
                                    <span id="char-count">0</span> caractères
                                </div>
                            </div>

                            <div style="display:flex;gap:12px">
                                <button type="submit" class="btn btn-primary">💾 Enregistrer la note</button>
                                <?php if ($patient_id): ?>
                                    <a href="patient-profile.php?id=<?= $patient_id ?>" class="btn btn-secondary">Voir dossier</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Notes récentes -->
                <div>
                    <?php if ($patient_id && !empty($recent_notes)): ?>
                    <div class="card">
                        <div class="card-head"><h3>🕐 Notes récentes</h3></div>
                        <div class="card-body" style="padding:16px">
                            <div class="timeline" style="padding-left:22px">
                                <?php foreach ($recent_notes as $n): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot green"></div>
                                    <div class="timeline-card">
                                        <div class="timeline-date">
                                            <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?>
                                            · Dr. <?= htmlspecialchars($n['doctor_name']) ?>
                                        </div>
                                        <div class="timeline-body">
                                            <?= nl2br(htmlspecialchars($n['clinical_notes'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($patient_id): ?>
                    <div class="card">
                        <div class="card-body"><div class="empty-state"><p>Aucune note précédente pour ce patient.</p></div></div>
                    </div>
                    <?php else: ?>
                    <div class="card">
                        <div class="card-body" style="padding:24px;color:var(--text-muted);font-size:0.85rem">
                            <p>👆 Sélectionnez un patient pour voir ses notes médicales.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script>
    function setNote(text) {
        const ta = document.getElementById('note-field');
        ta.value = text;
        ta.focus();
        updateCount();
    }
    function updateCount() {
        const ta = document.getElementById('note-field');
        document.getElementById('char-count').textContent = ta ? ta.value.length : 0;
    }
    function loadPatient() {
        const sel = document.getElementById('patient-select');
        if (sel.value) window.location.href = 'medical-notes.php?patient_id=' + sel.value;
    }
    const noteField = document.getElementById('note-field');
    if (noteField) noteField.addEventListener('input', updateCount);
    </script>
</body>
</html>
