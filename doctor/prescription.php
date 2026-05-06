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
    $patient_id     = (int)$_POST['patient_id'];
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $medicines      = $_POST['medicines'] ?? [];
    $notes          = trim($_POST['notes'] ?? '');

    if ($patient_id <= 0 || empty($medicines)) {
        $error = "Veuillez sélectionner un patient et au moins un médicament.";
    } else {
        // Créer l'ordonnance
        $stmt = $database->prepare("
            INSERT INTO prescriptions (patient_id, doctor_id, appointment_id, notes, prescription_date, status)
            VALUES (?, ?, ?, ?, NOW(), 'active')
        ");
        $appt = $appointment_id ?: null;
        $stmt->bind_param("iiis", $patient_id, $doctor_id, $appt, $notes);
        $stmt->execute();
        $prescription_id = $database->insert_id;

        // Insérer les lignes — medicine_id est obligatoire (FK vers medicines)
        $item_stmt = $database->prepare("
            INSERT INTO prescription_items (prescription_id, medicine_id, dosage, duration, quantity, instructions)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $inserted = 0;
        foreach ($medicines as $med) {
            $med_id       = (int)($med['medicine_id'] ?? 0);
            $dosage       = trim($med['dosage'] ?? '');
            $duration     = trim($med['duration'] ?? '');
            $quantity     = (int)($med['quantity'] ?? 1);
            $instructions = trim($med['instructions'] ?? '');

            if ($med_id > 0) {
                $stmt2 = $database->prepare("
                    INSERT INTO prescription_items (prescription_id, medicine_id, dosage, duration, quantity, instructions)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt2->bind_param("iissis", $prescription_id, $med_id, $dosage, $duration, $quantity, $instructions);
                $stmt2->execute();
                $inserted++;
            }
        }

        if ($inserted > 0) {
            header("Location: patient-profile.php?id=$patient_id&msg=prescription_created");
            exit();
        } else {
            $error = "Aucun médicament valide sélectionné.";
            // Supprimer l'ordonnance vide
            $database->query("DELETE FROM prescriptions WHERE id = $prescription_id");
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

// Liste des médicaments disponibles (depuis la table medicines)
$medicines_db = $database->query("
    SELECT id, name, generic_name, dosage_form, strength, quantity, category
    FROM medicines
    WHERE quantity > 0
    ORDER BY name
")->fetch_all(MYSQLI_ASSOC);

// Grouper par catégorie
$meds_by_cat = [];
foreach ($medicines_db as $m) {
    $cat = $m['category'] ?: 'Autres';
    $meds_by_cat[$cat][] = $m;
}
ksort($meds_by_cat);

// Allergies du patient sélectionné
$patient_allergies = '';
if ($patient_id > 0) {
    $stmt = $database->prepare("SELECT allergies, medical_history FROM patients WHERE id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $pa = $stmt->get_result()->fetch_assoc();
    $patient_allergies = $pa['allergies'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Ordonnance</title>
    <link rel="stylesheet" href="doctor.css">
    <style>
        .med-search { position:relative; }
        .med-search input { width:100%; }
        .med-dropdown {
            position:absolute; top:100%; left:0; right:0; z-index:200;
            background:white; border:1.5px solid var(--primary); border-top:none;
            border-radius:0 0 10px 10px; max-height:220px; overflow-y:auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .med-option {
            padding:10px 14px; cursor:pointer; font-size:0.85rem;
            border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between;
        }
        .med-option:hover { background:var(--primary-light); }
        .med-option .med-name { font-weight:600; }
        .med-option .med-info { font-size:0.75rem; color:var(--text-muted); }
        .med-option .stock-ok  { color:var(--success); font-size:0.72rem; }
        .med-option .stock-low { color:var(--warning); font-size:0.72rem; }
    </style>
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">💊 Nouvelle Ordonnance</span>
            <div class="topbar-right">
                <?php if ($patient_id): ?>
                    <a href="patient-profile.php?id=<?= $patient_id ?>" class="btn btn-secondary btn-sm">← Dossier patient</a>
                <?php endif; ?>
                <span class="date-tag">Dr. <?= htmlspecialchars($doctor['full_name']) ?> · <?= date('d/m/Y') ?></span>
            </div>
        </div>

        <div class="page-body" style="max-width:1000px">

            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if ($patient_allergies): ?>
                <div class="alert alert-warning">⚠️ <strong>Allergies connues :</strong> <?= htmlspecialchars($patient_allergies) ?> — Vérifiez les interactions.</div>
            <?php endif; ?>

            <form method="POST" id="presc-form">
                <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

                    <!-- Formulaire ordonnance -->
                    <div class="card">
                        <div class="card-head">
                            <div>
                                <div class="rx-symbol">Rx</div>
                                <div style="font-size:0.72rem;color:var(--text-muted)">Prescription médicale</div>
                            </div>
                            <div style="text-align:right;font-size:0.78rem;color:var(--text-muted)">
                                <div style="font-weight:700;color:var(--text-dark)">Dr. <?= htmlspecialchars($doctor['full_name']) ?></div>
                                <div><?= htmlspecialchars($doctor['department'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="card-body">

                            <div class="section-title" style="margin-bottom:10px">Médicaments prescrits</div>

                            <!-- Headers -->
                            <div class="med-row" style="margin-bottom:4px">
                                <label style="margin:0">Médicament <span class="req">*</span></label>
                                <label style="margin:0">Dosage / Fréquence</label>
                                <label style="margin:0">Durée</label>
                                <label style="margin:0">Qté</label>
                                <span></span>
                            </div>

                            <div id="medicines-list"></div>

                            <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()" style="margin-top:6px">
                                + Ajouter un médicament
                            </button>

                            <div class="form-group" style="margin-top:20px">
                                <label>Instructions / Remarques pour le pharmacien</label>
                                <textarea name="notes" class="input" rows="3"
                                    placeholder="Prendre après les repas. Éviter l'alcool. Ne pas conduire..."></textarea>
                            </div>

                            <div style="display:flex;gap:10px;margin-top:8px">
                                <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer l'ordonnance</button>
                                <?php if ($patient_id): ?>
                                    <a href="patient-profile.php?id=<?= $patient_id ?>" class="btn btn-secondary">Annuler</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Config latérale -->
                    <div style="position:sticky;top:80px">
                        <div class="card">
                            <div class="card-head"><h3>Configuration</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Patient <span class="req">*</span></label>
                                    <select name="patient_id" class="input" required>
                                        <option value="">— Choisir un patient —</option>
                                        <?php foreach ($patients as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $patient_id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['full_name']) ?> (<?= $p['uhid'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="section-title" style="margin-bottom:8px">Médicaments disponibles</div>
                                <div style="max-height:300px;overflow-y:auto;font-size:0.8rem">
                                    <?php foreach ($meds_by_cat as $cat => $meds): ?>
                                    <div style="font-weight:700;color:var(--text-muted);font-size:0.7rem;text-transform:uppercase;padding:6px 0 3px;border-bottom:1px solid var(--border);margin-bottom:3px"><?= htmlspecialchars($cat) ?></div>
                                    <?php foreach ($meds as $m): ?>
                                        <div onclick="addMedFromList(<?= $m['id'] ?>, '<?= addslashes($m['name']) ?><?= $m['strength'] ? ' '.$m['strength'] : '' ?>')"
                                             style="padding:5px 4px;cursor:pointer;border-radius:6px;display:flex;justify-content:space-between"
                                             onmouseover="this.style.background='var(--primary-light)'" onmouseout="this.style.background=''">
                                            <span><?= htmlspecialchars($m['name']) ?> <?= htmlspecialchars($m['strength'] ?? '') ?></span>
                                            <span style="color:<?= $m['quantity'] > 20 ? 'var(--success)' : 'var(--warning)' ?>;font-size:0.7rem">
                                                <?= $m['quantity'] ?> unités
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Data médicaments pour JS -->
    <script>
    const MEDICINES = <?= json_encode($medicines_db) ?>;
    let rowCount = 0;

    function addRow(medId = '', medName = '') {
        rowCount++;
        const n = rowCount;
        const row = document.createElement('div');
        row.className = 'med-row';
        row.id = 'row-' + n;

        // Options médicaments
        let opts = '<option value="">— Choisir —</option>';
        MEDICINES.forEach(m => {
            const label = m.name + (m.strength ? ' ' + m.strength : '') + (m.dosage_form ? ' ['+m.dosage_form+']' : '');
            const sel   = (m.id == medId) ? 'selected' : '';
            opts += `<option value="${m.id}" ${sel}>${label} (Stock: ${m.quantity})</option>`;
        });

        row.innerHTML = `
            <div>
                <select name="medicines[${n}][medicine_id]" class="input" required onchange="checkStock(this)">
                    ${opts}
                </select>
                <div id="stock-info-${n}" style="font-size:0.7rem;color:var(--text-muted);margin-top:2px"></div>
            </div>
            <div><input type="text" name="medicines[${n}][dosage]" class="input" placeholder="3×/jour"></div>
            <div><input type="text" name="medicines[${n}][duration]" class="input" placeholder="7 jours"></div>
            <div><input type="number" name="medicines[${n}][quantity]" class="input" value="1" min="1" max="999"></div>
            <button type="button" class="del-btn" onclick="document.getElementById('row-${n}').remove()" title="Supprimer">×</button>
        `;
        document.getElementById('medicines-list').appendChild(row);

        if (medId) {
            // Sélectionner directement
            row.querySelector('select').value = medId;
        }
    }

    function checkStock(sel) {
        const n = sel.closest('.med-row').id.replace('row-','');
        const med = MEDICINES.find(m => m.id == sel.value);
        const info = document.getElementById('stock-info-' + n);
        if (med) {
            info.textContent = `Stock disponible : ${med.quantity} ${med.unit || 'unités'}`;
            info.style.color = med.quantity < 10 ? 'var(--warning)' : 'var(--success)';
        } else {
            info.textContent = '';
        }
    }

    function addMedFromList(id, name) {
        addRow(id, name);
    }

    // Ligne par défaut
    addRow();
    </script>
</body>
</html>
