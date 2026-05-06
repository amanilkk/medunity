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

$patients = getDoctorPatients($database, $doctor_id);

$patient       = null;
$history       = [];
$lab_history   = [];
$presc_history = [];

if ($patient_id > 0) {

    // Infos patient — colonnes réelles (p.dob, pas date_of_birth)
    $stmt = $database->prepare("
        SELECT p.id, p.uhid, p.dob, p.gender, p.blood_type, p.allergies, p.medical_history,
               u.full_name, u.email, u.phone, u.address
        FROM patients p JOIN users u ON u.id = p.user_id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();

    // ── Historique médical complet (diagnostics + notes cliniques)
    // Colonnes réelles de medical_records : diagnosis, symptoms, treatment_plan, clinical_notes
    // La colonne "notes" N'EXISTE PAS dans medical_records
    $history = $database->query("
        SELECT
            IF(diagnosis IS NOT NULL AND diagnosis != '', 'Diagnostic', 'Note médicale') AS type,
            COALESCE(NULLIF(diagnosis,''), clinical_notes, '—') AS title,
            CONCAT_WS('\n',
                IF(symptoms       IS NOT NULL AND symptoms       != '', CONCAT('Symptômes : ', symptoms),       NULL),
                IF(treatment_plan IS NOT NULL AND treatment_plan != '', CONCAT('Traitement : ', treatment_plan), NULL),
                IF(clinical_notes IS NOT NULL AND clinical_notes != '', CONCAT('Notes : ', clinical_notes),      NULL)
            ) AS detail,
            mr.created_at,
            IF(diagnosis IS NOT NULL AND diagnosis != '', 'blue', 'green') AS color_key,
            u.full_name AS doctor_name
        FROM medical_records mr
        JOIN doctors d ON d.id = mr.doctor_id
        JOIN users u   ON u.id = d.user_id
        WHERE mr.patient_id = $patient_id
          AND (
              (mr.diagnosis      IS NOT NULL AND mr.diagnosis      != '')
              OR
              (mr.clinical_notes IS NOT NULL AND mr.clinical_notes != '')
          )
        ORDER BY mr.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    // ── Historique analyses labo
    $lab_history = $database->query("
        SELECT lt.test_name, lt.priority, lt.status, lt.result, lt.unit_measure,
               lt.is_critical, lt.created_at, u.full_name AS doctor_name
        FROM lab_tests lt
        JOIN doctors d ON d.id = lt.doctor_id
        JOIN users u   ON u.id = d.user_id
        WHERE lt.patient_id = $patient_id
        ORDER BY lt.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    // ── Historique ordonnances avec médicaments
    $presc_history = $database->query("
        SELECT pr.prescription_date, pr.status, pr.notes,
               u.full_name AS doctor_name,
               GROUP_CONCAT(
                   CONCAT(m.name, IFNULL(CONCAT(' ', m.strength),''),
                          ' — ', pi.dosage,
                          IFNULL(CONCAT(' / ', pi.duration),''))
                   ORDER BY pi.id SEPARATOR '\n'
               ) AS meds
        FROM prescriptions pr
        JOIN doctors d  ON d.id  = pr.doctor_id
        JOIN users u    ON u.id  = d.user_id
        LEFT JOIN prescription_items pi ON pi.prescription_id = pr.id
        LEFT JOIN medicines m           ON m.id = pi.medicine_id
        WHERE pr.patient_id = $patient_id
        GROUP BY pr.id
        ORDER BY pr.prescription_date DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

$age = null;
if ($patient && !empty($patient['dob'])) {
    $age = (new DateTime($patient['dob']))->diff(new DateTime())->y;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique Médical</title>
    <link rel="stylesheet" href="doctor.css">
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">🕐 Historique Médical</span>
            <div class="topbar-right">
                <?php if ($patient_id): ?>
                    <a href="patient-profile.php?id=<?= $patient_id ?>" class="btn btn-secondary btn-sm">← Dossier patient</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-body">

            <!-- Sélecteur patient -->
            <div class="card" style="margin-bottom:1.5rem">
                <div class="card-body">
                    <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <label style="font-weight:600;font-size:0.875rem">Patient :</label>
                        <select name="patient_id" class="input" style="width:320px" onchange="this.form.submit()">
                            <option value="">— Sélectionner un patient —</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $p['id'] == $patient_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['uhid']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($patient_id): ?>
                            <a href="medical-history.php" class="btn btn-secondary btn-sm">Effacer</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if (!$patient): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <p style="font-size:1rem">👆 Sélectionnez un patient pour afficher son historique médical complet.</p>
                        </div>
                    </div>
                </div>

            <?php else: ?>

            <!-- En-tête patient -->
            <div class="card" style="margin-bottom:1.5rem">
                <div class="card-body">
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;justify-content:space-between">
                        <div style="display:flex;align-items:center;gap:14px">
                            <div style="width:52px;height:52px;background:var(--primary);color:white;border-radius:14px;
                                        display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;flex-shrink:0">
                                <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-size:1.1rem;font-weight:800"><?= htmlspecialchars($patient['full_name']) ?></div>
                                <div style="display:flex;gap:8px;margin-top:4px;flex-wrap:wrap">
                                    <span class="badge badge-info"><?= htmlspecialchars($patient['uhid']) ?></span>
                                    <?php if ($patient['blood_type']): ?>
                                        <span class="badge badge-urgent">🩸 <?= htmlspecialchars($patient['blood_type']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($age !== null): ?>
                                        <span class="text-muted text-small"><?= $age ?> ans</span>
                                    <?php endif; ?>
                                    <?php if ($patient['allergies']): ?>
                                        <span class="badge badge-cancelled">⚠️ <?= htmlspecialchars($patient['allergies']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <a href="diagnosis.php?patient_id=<?= $patient_id ?>"    class="btn btn-primary btn-sm">📋 Diagnostic</a>
                            <a href="prescription.php?patient_id=<?= $patient_id ?>" class="btn btn-success btn-sm">💊 Ordonnance</a>
                            <a href="medical-notes.php?patient_id=<?= $patient_id ?>" class="btn btn-secondary btn-sm">📝 Note</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Résumé chiffré -->
            <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem">
                <div class="stat-card">
                    <div class="stat-icon icon-blue">📋</div>
                    <div class="stat-info">
                        <h3><?= count($history) ?></h3>
                        <p>Entrées dossier médical</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-green">💊</div>
                    <div class="stat-info">
                        <h3><?= count($presc_history) ?></h3>
                        <p>Ordonnances</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-amber">🧪</div>
                    <div class="stat-info">
                        <h3><?= count($lab_history) ?></h3>
                        <p>Analyses réalisées</p>
                    </div>
                </div>
            </div>

            <!-- TIMELINE COMPLÈTE -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

                <!-- Colonne gauche : Dossier médical -->
                <div>
                    <div class="card">
                        <div class="card-head">
                            <h3>📋 Dossier médical</h3>
                            <span class="text-muted text-small"><?= count($history) ?> entrée(s)</span>
                        </div>
                        <div class="card-body" style="padding:16px">
                            <?php if (empty($history)): ?>
                                <div class="empty-state"><p>Aucune entrée.</p></div>
                            <?php else: ?>
                            <div class="timeline" style="padding-left:22px">
                                <?php foreach ($history as $h): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot <?= $h['color_key'] ?>"></div>
                                    <div class="timeline-card">
                                        <div class="timeline-date">
                                            <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                                            <?php if (!empty($h['doctor_name'])): ?>
                                                · Dr. <?= htmlspecialchars($h['doctor_name']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-title" style="font-size:0.82rem">
                                            <span class="badge badge-<?= $h['color_key']==='blue'?'confirmed':'completed' ?>" style="margin-right:6px;font-size:0.65rem">
                                                <?= $h['type'] ?>
                                            </span>
                                            <?= nl2br(htmlspecialchars($h['title'])) ?>
                                        </div>
                                        <?php if (!empty($h['detail'])): ?>
                                            <div class="timeline-body" style="font-size:0.75rem;margin-top:5px">
                                                <?= nl2br(htmlspecialchars($h['detail'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Ordonnances -->
                    <div class="card">
                        <div class="card-head">
                            <h3>💊 Ordonnances</h3>
                            <span class="text-muted text-small"><?= count($presc_history) ?></span>
                        </div>
                        <div class="card-body" style="padding:16px">
                            <?php if (empty($presc_history)): ?>
                                <div class="empty-state"><p>Aucune ordonnance.</p></div>
                            <?php else: ?>
                            <div class="timeline" style="padding-left:22px">
                                <?php foreach ($presc_history as $pr): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot green"></div>
                                    <div class="timeline-card">
                                        <div class="timeline-date">
                                            <?= date('d/m/Y', strtotime($pr['prescription_date'])) ?>
                                            · Dr. <?= htmlspecialchars($pr['doctor_name']) ?>
                                            <span class="badge badge-<?= $pr['status']==='active'?'confirmed':'completed' ?>" style="margin-left:6px;font-size:0.65rem">
                                                <?= $pr['status'] ?>
                                            </span>
                                        </div>
                                        <?php if ($pr['meds']): ?>
                                            <?php foreach (explode("\n", $pr['meds']) as $med): ?>
                                                <div style="font-size:0.78rem;padding:2px 0;border-bottom:1px solid #f1f5f9">
                                                    💊 <?= htmlspecialchars($med) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if ($pr['notes']): ?>
                                            <div class="timeline-body" style="font-size:0.75rem;margin-top:4px;font-style:italic">
                                                <?= htmlspecialchars($pr['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Colonne droite : Analyses labo -->
                <div>
                    <div class="card">
                        <div class="card-head">
                            <h3>🧪 Analyses & Résultats</h3>
                            <span class="text-muted text-small"><?= count($lab_history) ?></span>
                        </div>
                        <div class="card-body" style="padding:0">
                            <?php if (empty($lab_history)): ?>
                                <div class="empty-state" style="padding:2rem"><p>Aucune analyse.</p></div>
                            <?php else: ?>
                            <table class="tbl">
                                <thead>
                                    <tr>
                                        <th>Analyse</th>
                                        <th>Priorité</th>
                                        <th>Résultat</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lab_history as $l): ?>
                                    <tr>
                                        <td>
                                            <div class="text-bold" style="font-size:0.82rem"><?= htmlspecialchars($l['test_name']) ?></div>
                                            <div class="text-small text-muted">Dr. <?= htmlspecialchars($l['doctor_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $l['priority']==='critical'?'badge-cancelled':($l['priority']==='urgent'?'badge-pending':'badge-info') ?>">
                                                <?= htmlspecialchars($l['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($l['result']): ?>
                                                <strong <?= $l['is_critical']?'style="color:var(--danger)"':'' ?>>
                                                    <?= htmlspecialchars($l['result']) ?>
                                                    <?= $l['unit_measure'] ? ' '.htmlspecialchars($l['unit_measure']) : '' ?>
                                                </strong>
                                                <?= $l['is_critical'] ? ' 🚨' : '' ?>
                                            <?php else: ?>
                                                <span class="badge badge-<?= $l['status']==='completed'?'completed':($l['status']==='in_progress'?'confirmed':'pending') ?>">
                                                    <?= htmlspecialchars($l['status']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted text-small"><?= date('d/m/Y', strtotime($l['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
