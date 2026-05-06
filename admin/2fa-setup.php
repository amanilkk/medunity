<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// admin/2fa-setup.php - Configuration 2FA pour l'administrateur
session_start();
if(isset($_SESSION["user"])){
    if($_SESSION["user"]=="" or $_SESSION['usertype']!='a'){
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

$user_id = $_SESSION['uid'] ?? $_SESSION['user_id'] ?? 0;
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
    $issuer  = 'Clinique ';
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

    // ✅ Big-endian 8 bytes — fonctionne sur Windows/XAMPP
    // pack('J') est little-endian sur Windows, donc on utilise deux pack('N')
    $msg = pack('N', 0) . pack('N', $timeSlice);

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

// ── Désactiver la 2FA ─────────────────────────────────────────
if (isset($_POST['disable_2fa'])) {
    $database->query("UPDATE users SET two_factor_enabled=0, otp_secret=NULL WHERE id=$user_id");
    $message = "✅ 2FA désactivée avec succès !";
}

// ── Vérifier et activer la 2FA ────────────────────────────────
if (isset($_POST['verify_2fa'])) {
    $user_secret = trim($_POST['secret'] ?? '');
    $user_code   = trim($_POST['verification_code'] ?? '');

    if (verifyTOTP($user_secret, $user_code)) {
        $safe_secret = $database->real_escape_string($user_secret);
        $database->query("UPDATE users SET two_factor_enabled=1, otp_secret='$safe_secret' WHERE id=$user_id");
        $message = "✅ 2FA vérifiée et activée avec succès !";
    } else {
        $message = "❌ Code invalide. Veuillez réessayer.";
    }
}

// ── Charger l'utilisateur ─────────────────────────────────────
$user           = $database->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$current_secret = $user['otp_secret'] ?: generateSecret();
$qr_code_url    = generateQRCode($current_secret, $user['email']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration 2FA - Admin</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .container-2fa {
            max-width: 600px;
            margin: 50px auto;
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        .qr-code { background: white; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: center; }
        .qr-code img { max-width: 200px; }
        .secret-key {
            background: #f1f5f9; padding: 12px; border-radius: 8px;
            font-family: monospace; font-size: 1rem; letter-spacing: 2px;
            margin: 10px 0; text-align: center; word-break: break-all;
        }
        .code-input {
            padding: 10px; font-size: 1.2rem; text-align: center;
            letter-spacing: 4px; border: 1px solid #e2e8f0; border-radius: 8px;
            width: 180px; margin: 10px auto; display: block;
        }
        .text-center { text-align: center; }
        .mt-4 { margin-top: 20px; }
        .mb-4 { margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="app">
    <div class="sidebar">
        <div class="logo-area">
           <div class="logo">MED<span>UNITY</span></div>
            <div class="logo-sub">Gestion Administrative</div>
        </div>
        <nav>
            <a href="index.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2h-5v-8H7v8H5a2 2 0 0 1-2-2z"/></svg>
                <span>Statistiques</span>
            </a>
            <a href="users.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Utilisateurs</span>
            </a>
            <a href="roles.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/></svg>
                <span>Rôles</span>
            </a>
            <a href="security.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Sécurité</span>
            </a>
            <a href="backup.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 15 7 15 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <span>Sauvegarde</span>
            </a>
            <a href="profile.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Mon profil</span>
            </a>
        </nav>
        <div class="user-info">
            <div class="user-avatar">AD</div>
            <div class="user-details">
                <div class="user-name">Administrateur</div>
                <div class="user-role">admin@edoc.com</div>
                <a href="../logout.php" class="logout-link">Déconnexion</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">🔐 Configuration 2FA</div>
            <div class="date-badge"><?= date('Y-m-d') ?></div>
        </div>
        <div class="content">
            <div class="container-2fa">

                <?php if ($message): ?>
                    <div class="alert <?= strpos($message, '✅') !== false ? 'alert-success' : 'alert-error' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($user['two_factor_enabled']): ?>
                    <div class="alert alert-success">
                        ✅ La 2FA est actuellement <strong>activée</strong> sur votre compte.
                    </div>
                    <div class="text-center">
                        <form method="POST" class="mt-4">
                            <button type="submit" name="disable_2fa" class="btn btn-danger"
                                    onclick="return confirm('Désactiver la 2FA ?')">
                                🔓 Désactiver la 2FA
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="text-center mb-4">
                        <h3>📱 Étape 1 : Scannez le QR code</h3>
                        <p style="color:#64748b; margin: 8px 0;">Ouvrez Google Authenticator → <strong>+</strong> → Scanner un QR code</p>
                        <div class="qr-code">
                            <img src="<?= $qr_code_url ?>" alt="QR Code 2FA">
                        </div>
                        <h3>🔑 Étape 2 : Ou entrez la clé manuellement</h3>
                        <div class="secret-key"><?= chunk_split($current_secret, 4, ' ') ?></div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="secret" value="<?= htmlspecialchars($current_secret) ?>">
                        <p class="text-center" style="margin-bottom:8px;">Entrez le code affiché dans l'application :</p>
                        <input type="text" name="verification_code" class="code-input"
                               placeholder="000000" maxlength="6" inputmode="numeric"
                               autocomplete="off" required>
                        <div class="text-center mt-4">
                            <button type="submit" name="verify_2fa" class="btn btn-primary">
                                ✅ Vérifier et activer
                            </button>
                        </div>
                    </form>

                    <div class="alert alert-info mt-4">
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
</body>
</html>