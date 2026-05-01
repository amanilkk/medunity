<?php
session_start();
$_SESSION = [];
date_default_timezone_set('Africa/Algiers');
$_SESSION['date'] = date('Y-m-d');

include("connection.php");

$error    = '';
$redirect = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['useremail'] ?? '');
    $password = trim($_POST['userpassword'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {

        $stmt = $database->prepare(
            "SELECT u.id, u.email, u.password, u.full_name, u.is_active, u.role_id, u.two_factor_enabled,
                    r.role_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.email = ?
             LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (!$user['is_active']) {
                $error = 'Compte désactivé. Contactez l\'administrateur.';

            } elseif (password_verify($password, $user['password'])) {

                /*/ ⭐⭐⭐ VÉRIFICATION 2FA ⭐⭐⭐
                if($user['two_factor_enabled'] == 1){
                    $_SESSION['2fa_pending'] = $user['id'];
                    $_SESSION['2fa_email'] = $user['email'];
                    $_SESSION['2fa_full_name'] = $user['full_name'];
                    $_SESSION['2fa_role'] = $user['role_name'];
                    $_SESSION['2fa_role_id'] = $user['role_id'];
                    header("location: 2fa-verify.php");
                    exit();
                }*/

                // Mettre à jour la date de dernière connexion et réinitialiser les tentatives échouées
                $update_sql = "UPDATE users SET last_login = NOW(), failed_login_attempts = 0 WHERE id = " . $user['id'];
                $database->query($update_sql);

                // Journaliser la connexion réussie
                $ip = $_SERVER['REMOTE_ADDR'];
                $database->query("INSERT INTO login_logs (user_id, email, ip_address, success) VALUES ({$user['id']}, '$email', '$ip', 1)");

                // Variables session
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['full_name']  = $user['full_name'];
                $_SESSION['role']       = $user['role_name'];
                $_SESSION['role_id']    = $user['role_id'];
                $_SESSION['user'] = $user['email'];

                // Redirection selon le rôle
                $redirect = getRedirectUrl($user['role_name']);

                if ($redirect) {
                    header("location: $redirect");
                    exit();
                }

            } else {
                // Mot de passe incorrect
                $ip = $_SERVER['REMOTE_ADDR'];
                $database->query("INSERT INTO login_logs (user_id, email, ip_address, success) VALUES ({$user['id']}, '$email', '$ip', 0)");

                // Incrémenter le compteur de tentatives échouées
                $database->query("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = " . $user['id']);

                $error = 'Email ou mot de passe incorrect.';
            }

        } else {
            // Email non trouvé
            $ip = $_SERVER['REMOTE_ADDR'];
            $database->query("INSERT INTO login_logs (user_id, email, ip_address, success) VALUES (NULL, '$email', '$ip', 0)");
            $error = 'Aucun compte trouvé pour cet email.';
        }

        $stmt->close();
    }
}

// Fonction pour obtenir l'URL de redirection selon le rôle
function getRedirectUrl($role_name) {
    switch ($role_name) {
        case 'admin_systeme':
            $_SESSION['usertype'] = 'a';
            return 'admin/index.php';
        case 'medecin':
            $_SESSION['usertype'] = 'd';
            return 'doctor/index.php';
        case 'patient':
            $_SESSION['usertype'] = 'p';
            return 'patient/index.php';
        case 'infirmier':
            $_SESSION['usertype'] = 'infirmier';
            return 'gmg/gmg_index.php';
        case 'pharmacien':
            $_SESSION['usertype'] = 'pharmacien';
            return 'pharmacien/index.php';
        case 'laborantin':
            $_SESSION['usertype'] = 'laborantin';
            return 'laborantin/lab_index.php';
        case 'receptionniste':
            $_SESSION['usertype'] = 'receptionniste';
            return 'receptionniste/index.php';
        case 'gestionnaire_rh':
            $_SESSION['usertype'] = 'gestionnaire_rh';
            return 'rh/index.php';
        case 'comptable':
            $_SESSION['usertype'] = 'comptable';
            return 'comptable/index.php';
        case 'agent_maintenance':
            $_SESSION['usertype'] = 'maintenance';
            return 'gmg/gmg_index.php';
        default:
            $_SESSION['usertype'] = 'other';
            return 'dashboard/index.php';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — SGCM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue-deep:   #0f4c81;
            --blue-mid:    #1a73c0;
            --blue-light:  #4fa3e0;
            --blue-pale:   #e8f4fd;
            --teal:        #00a896;
            --teal-light:  #ccf0eb;
            --green:       #1A6B4A;
            --green-l:     #E8F5EF;
            --green-d:     #145C3E;
            --white:       #ffffff;
            --gray-100:    #f4f7fb;
            --gray-200:    #e2eaf4;
            --gray-400:    #9baec8;
            --gray-600:    #5a6a80;
            --gray-800:    #253247;
            --error:       #e53e3e;
            --error-bg:    #fff5f5;
            --error-border:#feb2b2;
            --shadow-card: 0 24px 64px rgba(15,76,129,.18), 0 4px 16px rgba(15,76,129,.10);
            --radius:      18px;
            --transition:  .25s cubic-bezier(.4,0,.2,1);
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', sans-serif;
            background: linear-gradient(135deg, var(--green) 0%, var(--green-d) 40%, var(--teal) 100%);
            overflow: hidden;
            padding: 20px;
        }

        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .35;
            pointer-events: none;
            z-index: 0;
        }
        body::before {
            width: 600px; height: 600px;
            background: radial-gradient(circle, var(--green-l), transparent 70%);
            top: -150px; left: -150px;
            animation: blobA 12s ease-in-out infinite alternate;
        }
        body::after {
            width: 500px; height: 500px;
            background: radial-gradient(circle, var(--teal), transparent 70%);
            bottom: -120px; right: -120px;
            animation: blobB 14s ease-in-out infinite alternate;
        }
        @keyframes blobA { to { transform: translate(80px, 60px) scale(1.15); } }
        @keyframes blobB { to { transform: translate(-60px,-80px) scale(1.1); } }

        .page-wrapper {
            position: relative;
            z-index: 1;
            display: flex;
            width: 100%;
            max-width: 960px;
            min-height: 560px;
            border-radius: calc(var(--radius) + 4px);
            overflow: hidden;
            box-shadow: var(--shadow-card);
            animation: fadeInUp .7s ease both;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform: translateY(32px); }
            to   { opacity:1; transform: translateY(0); }
        }

        .left-panel {
            flex: 1;
            background: linear-gradient(160deg, var(--green) 0%, var(--green-d) 100%);
            padding: 52px 44px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
            pointer-events: none;
        }
        .brand {
            position: relative;
            z-index: 1;
        }
        .brand-icon {
            width: 56px; height: 56px;
            background: rgba(255,255,255,.12);
            border: 1.5px solid rgba(255,255,255,.25);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
            backdrop-filter: blur(6px);
        }
        .brand-icon svg { width: 30px; height: 30px; }
        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            letter-spacing: .02em;
            margin-bottom: 6px;
        }
        .brand-tagline {
            font-size: .85rem;
            font-weight: 400;
            color: rgba(255,255,255,.65);
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .features { position: relative; z-index: 1; }
        .feature-item {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 22px;
            animation: fadeInUp .6s ease both;
        }
        .feature-item:nth-child(1) { animation-delay: .2s; }
        .feature-item:nth-child(2) { animation-delay: .3s; }
        .feature-item:nth-child(3) { animation-delay: .4s; }
        .feature-dot {
            width: 36px; height: 36px; flex-shrink: 0;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .feature-dot svg { width: 18px; height: 18px; }
        .feature-text { font-size: .9rem; color: rgba(255,255,255,.8); font-weight: 500; }

        .left-footer {
            position: relative; z-index: 1;
            font-size: .75rem; color: rgba(255,255,255,.4);
        }

        .right-panel {
            flex: 1.1;
            background: var(--white);
            padding: 52px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .form-header { margin-bottom: 36px; }
        .form-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.85rem;
            color: var(--gray-800);
            margin-bottom: 8px;
        }
        .form-subtitle {
            font-size: .9rem;
            color: var(--gray-400);
            font-weight: 500;
        }

        .error-banner {
            display: flex; align-items: flex-start; gap: 10px;
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            border-left: 4px solid var(--error);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 24px;
            animation: shake .4s ease;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 60%{transform:translateX(6px)}
        }
        .error-banner svg { width:18px; height:18px; color:var(--error); flex-shrink:0; margin-top:1px; }
        .error-banner span { font-size:.875rem; color:var(--error); font-weight:600; }

        .field-group { margin-bottom: 20px; }
        .field-label {
            display: block;
            font-size: .8rem;
            font-weight: 700;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 8px;
        }
        .input-wrapper {
            position: relative;
            display: flex; align-items: center;
        }
        .input-icon {
            position: absolute; left: 14px;
            width: 18px; height: 18px;
            color: var(--gray-400);
            pointer-events: none;
            transition: color var(--transition);
        }
        .input-field {
            width: 100%;
            padding: 13px 44px 13px 44px;
            border: 1.5px solid var(--gray-200);
            border-radius: 12px;
            font-family: 'Nunito', sans-serif;
            font-size: .95rem;
            color: var(--gray-800);
            background: var(--gray-100);
            outline: none;
            transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
        }
        .input-field::placeholder { color: var(--gray-400); }
        .input-field:focus {
            border-color: var(--green);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(26,107,74,.1);
        }
        .input-field:focus + .input-icon,
        .input-wrapper:focus-within .input-icon { color: var(--green); }
        .input-wrapper:focus-within .input-icon { color: var(--green); }

        .toggle-password {
            position: absolute; right: 14px;
            background: none; border: none; cursor: pointer; padding: 0;
            color: var(--gray-400);
            display: flex; align-items: center;
            transition: color var(--transition);
        }
        .toggle-password:hover { color: var(--green); }
        .toggle-password svg { width: 18px; height: 18px; }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--green) 0%, var(--green-d) 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-family: 'Nunito', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: .04em;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform var(--transition), box-shadow var(--transition), opacity var(--transition);
            margin-top: 8px;
            box-shadow: 0 6px 20px rgba(26,107,74,.35);
        }
        .btn-submit::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.15), transparent);
            opacity: 0;
            transition: opacity var(--transition);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(26,107,74,.45); }
        .btn-submit:hover::before { opacity: 1; }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: .7; cursor: not-allowed; transform: none; }

        .btn-content { display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2.5px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading .btn-spinner { display: block; }
        .loading .btn-text { opacity: .8; }

        .form-footer {
            text-align: center;
            margin-top: 28px;
            font-size: .88rem;
            color: var(--gray-400);
        }

        .accent-bar {
            height: 4px;
            background: linear-gradient(90deg, var(--green), var(--teal));
            border-radius: 0 0 0 0;
            position: absolute;
            bottom: 0; left: 0; right: 0;
        }

        @media (max-width: 720px) {
            .page-wrapper { flex-direction: column; max-width: 440px; min-height: unset; }
            .left-panel { padding: 32px 28px; }
            .features { display: none; }
            .brand-name { font-size: 1.6rem; }
            .right-panel { padding: 36px 28px 44px; }
        }
        @media (max-width: 420px) {
            .right-panel { padding: 28px 20px 36px; }
            .left-panel  { padding: 24px 20px; }
            .form-title  { font-size: 1.55rem; }
        }
    </style>
</head>
<body>

<?php if ($redirect): ?>
    <script>window.location.href = "<?php echo $redirect; ?>";</script>
<?php endif; ?>

<div class="page-wrapper">

    <div class="left-panel">
        <div class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="13" y="4" width="6" height="24" rx="3" fill="white" fill-opacity=".9"/>
                    <rect x="4" y="13" width="24" height="6" rx="3" fill="white" fill-opacity=".9"/>
                </svg>
            </div>
            <div class="brand-name">SGCM</div>
            <div class="brand-tagline">Système de Gestion Clinique &amp; Médicale</div>
        </div>

        <div class="features">
            <div class="feature-item">
                <div class="feature-dot">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <span class="feature-text">Accès sécurisé par rôle</span>
            </div>
            <div class="feature-item">
                <div class="feature-dot">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
                    </svg>
                </div>
                <span class="feature-text">Gestion des dossiers patients</span>
            </div>
            <div class="feature-item">
                <div class="feature-dot">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <span class="feature-text">Disponible 24h/24, 7j/7</span>
            </div>
        </div>

        <div class="left-footer">© <?php echo date('Y'); ?> SGCM — Tous droits réservés</div>
    </div>

    <div class="right-panel">

        <div class="form-header">
            <div class="form-title">Bienvenue 👋</div>
            <div class="form-subtitle">Connectez-vous à votre espace personnel</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-banner" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="loginForm" novalidate>

            <div class="field-group">
                <label for="useremail" class="field-label">Adresse e-mail</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <input type="email" name="useremail" id="useremail" class="input-field"
                           placeholder="exemple@hopital.dz"
                           value="<?php echo htmlspecialchars($_POST['useremail'] ?? ''); ?>"
                           autocomplete="email" required>
                </div>
            </div>

            <div class="field-group">
                <label for="userpassword" class="field-label">Mot de passe</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" name="userpassword" id="userpassword" class="input-field"
                           placeholder="••••••••"
                           autocomplete="current-password" required>
                    <button type="button" class="toggle-password" id="togglePwd" aria-label="Afficher/Masquer le mot de passe">
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <span class="btn-content">
                    <span class="btn-spinner" id="spinner"></span>
                    <span class="btn-text">Se connecter</span>
                </span>
            </button>

        </form>

    </div>
</div>

<script>
    const toggleBtn = document.getElementById('togglePwd');
    const pwdInput  = document.getElementById('userpassword');
    const eyeIcon   = document.getElementById('eyeIcon');

    const eyeOpen   = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    const eyeSlash  = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                     <line x1="1" y1="1" x2="23" y2="23"/>`;

    let visible = false;
    toggleBtn.addEventListener('click', () => {
        visible = !visible;
        pwdInput.type = visible ? 'text' : 'password';
        eyeIcon.innerHTML = visible ? eyeSlash : eyeOpen;
    });

    const form      = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const spinner   = document.getElementById('spinner');

    form.addEventListener('submit', () => {
        if (form.checkValidity()) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            spinner.style.display = 'block';
        }
    });
</script>
</body>
</html>