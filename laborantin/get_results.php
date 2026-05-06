<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  get_results.php — Récupération des résultats pour affichage/email
// ================================================================

require_once 'functions.php';
requireLaborantin();
include '../connection.php';

// Déterminer le type de réponse
$is_ajax = true; // Par défaut, on retourne du JSON
$is_print = isset($_GET['print']);
$is_email_preview = isset($_GET['email_preview']);

if ($is_print || $is_email_preview) {
    $is_ajax = false;
    header('Content-Type: text/html; charset=utf-8');
} else {
    header('Content-Type: application/json');
}

$patient_id = (int)($_GET['patient_id'] ?? 0);
$test_date = $_GET['date'] ?? '';
$test_id = (int)($_GET['test_id'] ?? 0);

// Cas 1: Affichage d'une analyse simple par ID
if ($test_id > 0 && !$patient_id) {
    $stmt = $database->prepare("
        SELECT lt.*, u.full_name as patient_name, u.phone, u.email, u2.full_name as doctor_name
        FROM lab_tests lt
        JOIN patients p ON p.id = lt.patient_id
        JOIN users u ON u.id = p.user_id
        LEFT JOIN doctors d ON d.id = lt.doctor_id
        LEFT JOIN users u2 ON u2.id = d.user_id
        WHERE lt.id = ?
    ");
    $stmt->bind_param('i', $test_id);
    $stmt->execute();
    $tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($tests)) {
        $error_msg = 'Aucun résultat trouvé pour cette analyse';
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => $error_msg]);
        } else {
            echo "<div class='alert alert-error'>$error_msg</div>";
        }
        exit;
    }

    $patient = $tests[0];
    $test_date = date('Y-m-d', strtotime($patient['created_at'] ?? 'now'));

}
// Cas 2: Récupération par patient et date
else if ($patient_id > 0 && $test_date) {
    $stmt = $database->prepare("
        SELECT lt.*, u.full_name as patient_name, u.phone, u.email, u2.full_name as doctor_name
        FROM lab_tests lt
        JOIN patients p ON p.id = lt.patient_id
        JOIN users u ON u.id = p.user_id
        LEFT JOIN doctors d ON d.id = lt.doctor_id
        LEFT JOIN users u2 ON u2.id = d.user_id
        WHERE lt.patient_id = ? AND DATE(lt.created_at) = ?
        ORDER BY lt.id ASC
    ");
    $stmt->bind_param('is', $patient_id, $test_date);
    $stmt->execute();
    $tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($tests)) {
        $error_msg = 'Aucune analyse trouvée pour ce patient à cette date';
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => $error_msg]);
        } else {
            echo "<div class='alert alert-error'>$error_msg</div>";
        }
        exit;
    }

    $patient = $tests[0];
}
else {
    $error_msg = 'Paramètres manquants: patient_id+date ou test_id requis';
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => $error_msg]);
    } else {
        echo "<div class='alert alert-error'>$error_msg</div>";
    }
    exit;
}

// Vérifier si des résultats existent
$has_results = false;
foreach ($tests as $test) {
    if (!empty($test['result'])) {
        $has_results = true;
        break;
    }
}

// Générer le HTML des résultats
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Résultats d\'analyses - ' . htmlspecialchars($patient['patient_name'] ?? 'Patient') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { background: #2c7da0; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .result-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .result-table th, .result-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .result-table th { background: #f5f5f5; }
        .critical { background: #ffe5e5; color: #cc0000; }
        .pending-result { background: #fff3e0; color: #ff9800; }
        .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; }
        .patient-info { margin-bottom: 20px; padding: 10px; background: #f0f0f0; border-radius: 5px; }
        .no-results { text-align: center; padding: 40px; color: #999; }
        .alert-error { background: #ffe5e5; color: #cc0000; padding: 15px; border-radius: 5px; text-align: center; }
        @media print {
            body { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laboratoire d\'Analyses Médicales</h2>
        <p>Compte-rendu des résultats d\'analyses</p>
    </div>
    <div class="content">
        <div class="patient-info">
            <strong>Patient :</strong> ' . htmlspecialchars($patient['patient_name'] ?? 'Non spécifié') . '<br>
            <strong>Date des analyses :</strong> ' . date('d/m/Y', strtotime($test_date)) . '<br>
            <strong>Médecin traitant :</strong> Dr. ' . htmlspecialchars($patient['doctor_name'] ?? 'Non spécifié') . '
        </div>';

if (!$has_results) {
    $html .= '
        <div class="no-results">
            <p>⚠️ Aucun résultat n\'a encore été saisi pour ces analyses.</p>
            <p>Veuillez consulter votre médecin pour plus d\'informations.</p>
        </div>';
} else {
    $html .= '
        <table class="result-table">
            <thead>
                <tr><th>Analyse</th><th>Résultat</th><th>Unité</th><th>Date résultat</th></tr>
            </thead>
            <tbody>';

    foreach ($tests as $test) {
        if (empty($test['result'])) continue;

        $critical_class = ($test['is_critical'] ?? 0) ? 'critical' : '';
        $html .= '
                <tr class="' . $critical_class . '">
                    <td><strong>' . htmlspecialchars($test['test_name']) . '</strong></td>
                    <td>' . nl2br(htmlspecialchars($test['result'])) . '</td>
                    <td>' . htmlspecialchars($test['unit_measure'] ?? '-') . '</td>
                    <td>' . ($test['result_date'] ? date('d/m/Y H:i', strtotime($test['result_date'])) : '-') . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>
        
        <p><em>Ces résultats sont communiqués à titre informatif. Seul votre médecin traitant est habilité à les interpréter.</em></p>';
}

$html .= '
    </div>
    <div class="footer">
        <p>Laboratoire d\'Analyses Médicales</p>
        <p>Document généré le ' . date('d/m/Y H:i') . '</p>
    </div>
</body>
</html>';

// Retourner la réponse appropriée
// Retourner la réponse appropriée
if ($is_print) {
    // Pour l'impression, on retourne directement le HTML
    echo $html;
    exit;
} elseif ($is_email_preview) {
    // Pour l'aperçu email, on retourne aussi du HTML
    echo $html;
    exit;
} else {
    // Pour les requêtes AJAX normales, on retourne du JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}
exit;