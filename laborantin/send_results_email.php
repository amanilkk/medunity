<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  send_results_email.php — Envoi d'email avec PHPMailer
// ================================================================

require_once 'functions.php';
requireLaborantin();
include '../connection.php';

// Inclure l'autoloader de Composer
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$patient_id = (int)($data['patient_id'] ?? 0);
$test_date = $data['test_date'] ?? '';
$email = $data['email'] ?? '';
$patient_name = $data['patient_name'] ?? 'Patient';
$html_content = $data['html'] ?? '';

// Vérifier les paramètres
if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Adresse email manquante']);
    exit;
}

// Si le HTML n'est pas fourni, le récupérer
if (empty($html_content)) {
    if ($patient_id > 0 && $test_date) {
        // Construire l'URL absolue
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}/laborantin/get_results.php?patient_id={$patient_id}&date=" . urlencode($test_date) . "&email_preview=1";

        $html_content = @file_get_contents($url);
        if ($html_content === false) {
            // Fallback: générer le HTML localement
            $html_content = generateLocalEmailHTML($database, $patient_id, $test_date, $patient_name);
        }
    } else if ($test_id > 0) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}/laborantin/get_results.php?test_id={$test_id}&email_preview=1";
        $html_content = @file_get_contents($url);
    }
}

if (empty($html_content)) {
    echo json_encode(['success' => false, 'error' => 'Impossible de générer le contenu de l\'email']);
    exit;
}

// ==================== CONFIGURATION SMTP ====================
// MODIFIEZ CES INFORMATIONS SELON VOTRE SERVEUR EMAIL

// Configuration Gmail (recommandé pour les tests)
$smtp_config = [
    'host' => 'smtp.gmail.com',      // Serveur SMTP
    'port' => 587,                    // Port (587 pour TLS, 465 pour SSL)
    'auth' => true,                   // Authentification SMTP
    'username' => 'votre_email@gmail.com',  // Votre email (À MODIFIER)
    'password' => 'votre_mot_de_passe',     // Votre mot de passe (À MODIFIER)
    'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
    'from_email' => 'laboratoire@exemple.com',
    'from_name' => 'Laboratoire Médical'
];

// Pour Outlook/Office 365
// $smtp_config = [
//     'host' => 'smtp.office365.com',
//     'port' => 587,
//     'auth' => true,
//     'username' => 'votre_email@exemple.com',
//     'password' => 'votre_mot_de_passe',
//     'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
//     'from_email' => 'laboratoire@exemple.com',
//     'from_name' => 'Laboratoire Médical'
// ];

// Pour OVH
// $smtp_config = [
//     'host' => 'ssl0.ovh.net',
//     'port' => 587,
//     'auth' => true,
//     'username' => 'contact@votredomaine.com',
//     'password' => 'votre_mot_de_passe',
//     'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
//     'from_email' => 'contact@votredomaine.com',
//     'from_name' => 'Laboratoire Médical'
// ];

$mail = new PHPMailer(true);

try {
    // Configuration du serveur SMTP
    $mail->isSMTP();
    $mail->Host       = $smtp_config['host'];
    $mail->SMTPAuth   = $smtp_config['auth'];
    $mail->Username   = $smtp_config['username'];
    $mail->Password   = $smtp_config['password'];
    $mail->SMTPSecure = $smtp_config['encryption'];
    $mail->Port       = $smtp_config['port'];

    // Optionnel: Désactiver la vérification SSL pour les tests (à ne pas faire en production)
    // $mail->SMTPOptions = array(
    //     'ssl' => array(
    //         'verify_peer' => false,
    //         'verify_peer_name' => false,
    //         'allow_self_signed' => true
    //     )
    // );

    // Activer le debug pour voir les erreurs (à désactiver en production)
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

    // Expéditeur
    $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
    $mail->addReplyTo($smtp_config['from_email'], $smtp_config['from_name']);

    // Destinataire
    $mail->addAddress($email, $patient_name);

    // Contenu
    $mail->isHTML(true);
    $mail->Subject = '=?UTF-8?B?' . base64_encode('Vos résultats d\'analyses médicales') . '?=';
    $mail->Body    = $html_content;
    $mail->AltBody = strip_tags($html_content);

    // Envoyer l'email
    $mail->send();
    $success = true;
    $message = 'Email envoyé avec succès à ' . $email;

    // Journalisation
    $log_stmt = $database->prepare("
        INSERT INTO logs (user_id, action, details, created_at)
        VALUES (?, 'email_sent', CONCAT('Email envoyé à ', ?, ' via SMTP'), NOW())
    ");
    $log_stmt->bind_param('is', getCurrentLaborantinId(), $email);
    $log_stmt->execute();

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $error_message = $mail->ErrorInfo;

    // Journalisation de l'erreur
    $log_stmt = $database->prepare("
        INSERT INTO logs (user_id, action, details, created_at)
        VALUES (?, 'email_error', CONCAT('Erreur envoi à ', ?, ': ', ?), NOW())
    ");
    $log_stmt->bind_param('iss', getCurrentLaborantinId(), $email, $error_message);
    $log_stmt->execute();

    echo json_encode([
        'success' => false,
        'error' => 'Erreur SMTP: ' . $error_message,
        'debug_info' => [
            'host' => $smtp_config['host'],
            'port' => $smtp_config['port'],
            'user' => $smtp_config['username']
        ]
    ]);
}
exit;

/**
 * Génère le HTML localement (fallback)
 */
function generateLocalEmailHTML($database, $patient_id, $test_date, $patient_name) {
    $stmt = $database->prepare("
        SELECT lt.*
        FROM lab_tests lt
        WHERE lt.patient_id = ? AND DATE(lt.created_at) = ?
        ORDER BY lt.id ASC
    ");
    $stmt->bind_param('is', $patient_id, $test_date);
    $stmt->execute();
    $tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $has_results = false;
    foreach ($tests as $test) {
        if (!empty($test['result'])) {
            $has_results = true;
            break;
        }
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Résultats d\'analyses</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; }
            .header { background: #2c7da0; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .patient-info { background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .result-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .result-table th, .result-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            .result-table th { background: #f5f5f5; }
            .critical { background: #ffe5e5; color: #cc0000; }
            .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Laboratoire d\'Analyses Médicales</h2>
                <p>Vos résultats d\'analyses</p>
            </div>
            <div class="content">
                <div class="patient-info">
                    <strong>Patient :</strong> ' . htmlspecialchars($patient_name) . '<br>
                    <strong>Date des analyses :</strong> ' . date('d/m/Y', strtotime($test_date)) . '
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
                        <tr><th>Analyse</th><th>Résultat</th><th>Unité</th><th>Date</th></tr>
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
                            <td>' . ($test['result_date'] ? date('d/m/Y', strtotime($test['result_date'])) : '-') . '</td>
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
                <p>Ce message est généré automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}
?>