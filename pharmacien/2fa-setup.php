<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// pharmacien/2fa-setup.php - Configuration 2FA pour le pharmacien
session_start();
include '../connection.php';
include 'functions.php';

// Vérifier que l'utilisateur est pharmacien
if ($_SESSION['role'] !== 'pharmacien') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// ── Générer un secret Base32 ──────────────────────────────────
function generateSecret($length = 16) {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $secret;
}

// ── Générer l'URL du QR code ──────────────────────────────────
function generateQRCode($secret, $email) {
    $issuer  = 'MEDUNITY - Pharmacie';
    $label   = urlencode($issuer . ':' . $email);
    $otpauth = "otpauth://totp/$label?secret=$secret&issuer=" . urlencode($issuer);
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);
}

// ── Base32 Decode ─────────────────────────────────────────────
function base32Decode($secret) {
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(trim($secret));
    $result = '';
    $buffer = 0;
    $bits   = 0;
    for ($i = 0; $i < strlen($secret); $i++) {
        $val = strpos($base32chars, $secret[$i]);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bits  += 5;
        if ($bits >= 8) {
            $bits  -= 8;
            $result .= chr(($buffer >> $bits) & 0xFF);
        }
    }
    return $result;
}

// ── TOTP Generate ─────────────────────────────────────────────
function generateTOTP($secret, $timeSlice) {
    $key = base32Decode($secret);
    $msg    = pack('N', 0) . pack('N', $timeSlice);
    $hash   = hash_hmac('sha1', $msg, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $code   = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % 1000000;
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

// ── TOTP Verify ───────────────────────────────────────────────
function verifyTOTP($secret, $code) {
    $code = str_pad(trim($code), 6, '0', STR_PAD_LEFT);
    $time = floor(time() / 30);
    for ($i = -2; $i <= 2; $i++) {
        if (hash_equals(generateTOTP($secret, $time + $i), $code)) {
            return true;
        }
    }
    return false;
}

// ── Charger l'utilisateur ─────────────────────────────────────
$stmt = $database->prepare("
    SELECT u.*, r.role_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: ../logout.php');
    exit;
}

// ── Désactiver la 2FA ─────────────────────────────────────────
if (isset($_POST['disable_2fa'])) {
    $stmt = $database->prepare("UPDATE users SET two_factor_enabled = 0, otp_secret = NULL WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user['two_factor_enabled'] = 0;
    $user['otp_secret'] = null;
    $message = "✅ 2FA désactivée avec succès !";
}

// ── Vérifier et activer la 2FA ────────────────────────────────
if (isset($_POST['verify_2fa'])) {
    $user_secret = trim($_POST['secret'] ?? '');
    $user_code   = trim($_POST['verification_code'] ?? '');

    if (verifyTOTP($user_secret, $user_code)) {
        $stmt = $database->prepare("UPDATE users SET two_factor_enabled = 1, otp_secret = ? WHERE id = ?");
        $stmt->bind_param('si', $user_secret, $user_id);
        $stmt->execute();
        $user['two_factor_enabled'] = 1;
        $user['otp_secret'] = $user_secret;
        $message = "✅ 2FA vérifiée et activée avec succès !";
    } else {
        $message = "❌ Code invalide. Veuillez réessayer.";
    }
}

$current_secret = $user['otp_secret'] ?: generateSecret();
$qr_code_url    = generateQRCode($current_secret, $user['email']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Configuration 2FA - Pharmacie</title>
    <link rel="stylesheet" href="pharmacien.css">
    <style>
        .container-2fa {
            max-width: 600px;
            margin: 0 auto;
        }
        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid var(--border);
        }
        .qr-code img {
            max-width: 200px;
        }
        .secret-key {
            background: var(--surf2);
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 1rem;
            letter-spacing: 2px;
            margin: 10px 0;
            text-align: center;
            word-break: break-all;
            color: var(--green);
            font-weight: 600;
        }
        .code-input {
            padding: 10px;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 4px;
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 180px;
            margin: 10px auto;
            display: block;
            font-family: monospace;
        }
        .code-input:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 2px rgba(16,185,129,0.15);
        }
        .text-center { text-align: center; }
        .mt-4 { margin-top: 20px; }
        .mb-4 { margin-bottom: 20px; }
        .info-note {
            background: var(--blue-l);
            border-left: 4px solid var(--blue);
            padding: 12px;
            margin-top: 15px;
            border-radius: var(--rs);
            font-size: 0.8rem;
            color: var(--blue);
        }
        .steps-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 16px 0 6px 0;
        }
        .steps-sub {
            font-size: 0.78rem;
            color: var(--text2);
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">🔐 Configuration 2FA</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body">
        <div class="container-2fa">
            <div class="card">
                <div class="card-head">
                    <h3>🔐 Authentification à deux facteurs</h3>
                </div>
                <div class="card-body">

                    <?php if ($message): ?>
                        <div class="alert <?= strpos($message, '✅') !== false ? 'alert-success' : 'alert-error' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($user['two_factor_enabled']): ?>

                        <div class="alert alert-success">
                            ✅ La 2FA est actuellement <strong>activée</strong> sur votre compte.
                        </div>

                        <div class="text-center mt-4">
                            <form method="POST">
                                <button type="submit" name="disable_2fa" class="btn btn-danger"
                                        onclick="return confirm('Êtes-vous sûr de vouloir désactiver la 2FA ?')">
                                    🔓 Désactiver la 2FA
                                </button>
                            </form>
                        </div>

                    <?php else: ?>

                        <div class="text-center mb-4">
                            <p class="steps-title">📱 Étape 1 : Scannez le QR code</p>
                            <p class="steps-sub">Ouvrez Google Authenticator → <strong>+</strong> → Scanner un QR code</p>
                            <div class="qr-code">
                                <img src="<?= $qr_code_url ?>" alt="QR Code 2FA">
                            </div>

                            <p class="steps-title">🔑 Étape 2 : Ou entrez la clé manuellement</p>
                            <div class="secret-key"><?= chunk_split($current_secret, 4, ' ') ?></div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="secret" value="<?= htmlspecialchars($current_secret) ?>">
                            <p class="text-center steps-sub" style="margin-bottom: 8px;">
                                Étape 3 : Entrez le code affiché dans l'application :
                            </p>
                            <input type="text" name="verification_code" class="code-input"
                                   placeholder="000000" maxlength="6" inputmode="numeric"
                                   autocomplete="off" required>
                            <div class="text-center mt-4">
                                <button type="submit" name="verify_2fa" class="btn btn-primary">
                                    ✅ Vérifier et activer
                                </button>
                            </div>
                        </form>

                        <div class="info-note mt-4">
                            💡 <strong>Important :</strong> Notez la clé secrète ci-dessus dans un endroit sûr.
                            Vous en aurez besoin si vous changez de téléphone.
                        </div>

                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="profile.php" class="btn btn-secondary">← Retour au profil</a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>