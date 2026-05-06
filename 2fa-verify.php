<?php
// 2fa-verify.php — Vérification du code TOTP après connexion
session_start();
date_default_timezone_set('Africa/Algiers');

// Si pas de session 2FA en attente → retour login
if (empty($_SESSION['2fa_pending'])) {
    header("location: login.php");
    exit();
}

include("connection.php");

$error = '';

// ─── Base32 Decode ────────────────────────────────────────────
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

// ─── TOTP Generate ────────────────────────────────────────────
function generateTOTP($secret, $timeSlice) {
    $key = base32Decode($secret);

    // ✅ Big-endian 8 bytes — works on Windows/XAMPP
    $msg = pack('N', 0) . pack('N', $timeSlice);

    $hash   = hash_hmac('sha1', $msg, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $code   = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % 1000000;
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

// ─── TOTP Verify ─────────────────────────────────────────────
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

// ─── Traitement POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id   = intval($_SESSION['2fa_pending']);
    $submitted = trim($_POST['totp_code'] ?? '');

    $stmt = $database->prepare(
            "SELECT u.id, u.otp_secret, u.email, u.full_name, r.role_name, r.id as role_id
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.two_factor_enabled = 1
         LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['otp_secret'])) {
        session_destroy();
        header("location: login.php");
        exit();
    }

    if (verifyTOTP($user['otp_secret'], $submitted)) {
        // ✅ Code correct — finaliser la session
        $role_name = $user['role_name'];

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['uid']        = $user['id'];
        $_SESSION['user']       = $user['email'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['role']       = $role_name;
        $_SESSION['role_id']    = $user['role_id'];

        switch ($role_name) {
            case 'admin_systeme':    $_SESSION['usertype'] = 'a'; break;
            case 'medecin':          $_SESSION['usertype'] = 'd'; break;
            case 'patient':          $_SESSION['usertype'] = 'p'; break;
            case 'infirmier':        $_SESSION['usertype'] = 'infirmier'; break;
            case 'pharmacien':       $_SESSION['usertype'] = 'pharmacien'; break;
            case 'laborantin':       $_SESSION['usertype'] = 'laborantin'; break;
            case 'receptionniste':   $_SESSION['usertype'] = 'receptionniste'; break;
            case 'gestionnaire_rh':  $_SESSION['usertype'] = 'gestionnaire_rh'; break;
            case 'comptable':        $_SESSION['usertype'] = 'comptable'; break;
            case 'agent_maintenance':$_SESSION['usertype'] = 'maintenance'; break;
            default:                 $_SESSION['usertype'] = 'other'; break;
        }

        unset($_SESSION['2fa_pending']);

        $database->query("UPDATE users SET last_login = NOW(), failed_login_attempts = 0 WHERE id = {$user['id']}");
        $ip = $_SERVER['REMOTE_ADDR'];
        $database->query("INSERT INTO login_logs (user_id, email, ip_address, success) VALUES ({$user['id']}, '{$user['email']}', '$ip', 1)");

        switch ($role_name) {
            case 'admin_systeme':    header("location: admin/index.php"); break;
            case 'medecin':          header("location: doctor/index.php"); break;
            case 'patient':          header("location: patient/index.php"); break;
            case 'infirmier':        header("location: gmg/gmg_index.php"); break;
            case 'pharmacien':       header("location: pharmacien/index.php"); break;
            case 'laborantin':       header("location: laborantin/index.php"); break;
            case 'receptionniste':   header("location: receptionniste/index.php"); break;
            case 'gestionnaire_rh':  header("location: rh/index.php"); break;
            case 'comptable':        header("location: comptable/index.php"); break;
            case 'agent_maintenance':header("location: gmg/gmg_index.php"); break;
            default:                 header("location: dashboard/index.php"); break;
        }
        exit();

    } else {
        $error = '❌ Code invalide ou expiré. Réessayez.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification 2FA — SGCM</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --green: #1A6B4A;
            --green-d: #145C3E;
            --white: #ffffff;
            --gray-100: #f4f7fb;
            --gray-200: #e2eaf4;
            --gray-800: #253247;
            --error: #e53e3e;
            --shadow: 0 24px 64px rgba(15,76,129,.18);
            --radius: 18px;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--green) 0%, var(--green-d) 100%);
            padding: 20px;
        }
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        h1 { font-size: 1.6rem; color: var(--gray-800); margin-bottom: 8px; }
        .subtitle { font-size: .85rem; color: #64748b; margin-bottom: 28px; line-height: 1.6; }
        .error-banner {
            background: #fff5f5;
            border-left: 4px solid var(--error);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: var(--error);
            font-size: 0.85rem;
        }
        .otp-input {
            width: 100%;
            padding: 16px;
            font-size: 1.8rem;
            text-align: center;
            letter-spacing: 8px;
            font-family: monospace;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            background: var(--gray-100);
            margin-bottom: 20px;
        }
        .otp-input:focus { border-color: var(--green); outline: none; }
        .btn-verify {
            width: 100%;
            padding: 14px;
            background: var(--green);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .btn-verify:hover { background: var(--green-d); }
        .timer { font-size: .8rem; color: #64748b; margin-bottom: 20px; }
        .timer span { color: var(--green); font-weight: bold; }
        .back-link a { color: var(--green); text-decoration: none; font-size: .85rem; }
    </style>
</head>
<body>
<div class="card">
    <div style="font-size: 48px; margin-bottom: 20px;">🔐</div>
    <h1>Vérification 2FA</h1>
    <p class="subtitle">
        Bonjour <strong><?= htmlspecialchars($_SESSION['2fa_full_name'] ?? '') ?></strong><br>
        Entrez le code à 6 chiffres de <strong>Google Authenticator</strong>
    </p>

    <?php if ($error): ?>
        <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="totp_code" id="totpCode" class="otp-input"
               placeholder="000000" maxlength="6" inputmode="numeric"
               autocomplete="one-time-code" autofocus required>
        <div class="timer">Code expire dans <span id="countdown">30</span> secondes</div>
        <button type="submit" class="btn-verify">✅ Vérifier</button>
    </form>

    <p class="back-link"><a href="login.php">← Retour à la connexion</a></p>
</div>

<script>
    const input = document.getElementById('totpCode');
    const form  = document.querySelector('form');

    input.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        if (this.value.length === 6) {
            setTimeout(() => form.submit(), 150);
        }
    });

    function updateCountdown() {
        document.getElementById('countdown').textContent =
            30 - (Math.floor(Date.now() / 1000) % 30);
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);
</script>
</body>
</html>