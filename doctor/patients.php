
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
$search    = trim($_GET['search'] ?? '');

$patients = getDoctorPatients($database, $doctor_id, $search);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Patients</title>
    <link rel="stylesheet" href="doctor.css">
</head>
<body>
    <?php include 'doctor_menu.php'; ?>

    <div class="main">
        <div class="topbar">
            <span class="topbar-title">👥 Mes Patients</span>
            <div class="topbar-right">
                <span class="date-tag"><?= date('d/m/Y') ?></span>
            </div>
        </div>

        <div class="page-body">
            <div class="card">
                <div class="card-head">
                    <h3>Liste des patients (<?= count($patients) ?>)</h3>
                    <form method="GET" class="filter-bar">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                               class="input" placeholder="Nom, UHID, téléphone..." style="width:260px">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                        <?php if ($search): ?>
                            <a href="patients.php" class="btn btn-secondary">Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card-body" style="padding:0">
                    <?php if (empty($patients)): ?>
                        <div class="empty-state"><p>Aucun patient trouvé.</p></div>
                    <?php else: ?>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>UHID</th>
                                <th>Groupe sanguin</th>
                                <th>Téléphone</th>
                                <th>Genre</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $p): ?>
                            <tr>
                                <td>
                                    <div class="flex-center">
                                        <div class="patient-initials"><?= strtoupper(substr($p['full_name'],0,2)) ?></div>
                                        <div>
                                            <div class="text-bold"><?= htmlspecialchars($p['full_name']) ?></div>
                                            <?php if ($p['dob']): ?>
                                                <div class="text-small text-muted">
                                                    <?= date('d/m/Y', strtotime($p['dob'])) ?>
                                                    (<?= floor((time() - strtotime($p['dob'])) / 31557600) ?> ans)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($p['uhid']) ?></span></td>
                                <td>
                                    <?php if ($p['blood_type']): ?>
                                        <span class="badge badge-urgent"><?= htmlspecialchars($p['blood_type']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['phone'] ?? '—') ?></td>
                                <td>
                                    <?php
                                    $g = ['M'=>'♂ Homme','F'=>'♀ Femme','other'=>'Autre'];
                                    echo $g[$p['gender']] ?? '—';
                                    ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px">
                                        <a href="patient-profile.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Dossier</a>
                                        <a href="medical-history.php?patient_id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Historique</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
