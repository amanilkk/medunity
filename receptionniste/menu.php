<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  menu.php — Barre de navigation réceptionniste
//  ✅ Liens : dashboard, accueil patient, patients, file du jour, lits, mon profil
//  ❌ Supprimé : facturation, paiements
// ================================================================
if (session_status() === PHP_SESSION_NONE) session_start();

$current = basename($_SERVER['PHP_SELF']);

function nav($file, $icon, $label, $current) {
    $active = ($file === $current) ? 'active' : '';
    echo "<a href='$file' class='nav-item $active'>
            <svg viewBox='0 0 24 24'>$icon</svg>
            <span>$label</span>
          </a>";
}
?>
<aside class="sidebar">
    <div class="sb-brand">
        <h1>MedUnity</h1>
        <p>Réception</p>
    </div>
    <div class="sb-user">
        <div class="ava">
            <?php echo strtoupper(substr($_SESSION['user_email'] ?? 'R', 0, 1)); ?>
        </div>
        <div>
            <div class="urole">Réceptionniste</div>
            <div class="uemail"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
        </div>
    </div>
    <nav class="sb-nav">
        <?php
        nav('index.php',
            '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
            'Dashboard', $current);
        nav('reception.php',
            '<path d="M3 9l9-7 9 7v11H3z"/>',
            'Accueil patient', $current);
        nav('patients.php',
            '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
            'Patients', $current);
        nav('appointments.php',
            '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'File du jour', $current);
        nav('beds.php',
            '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>',
            'Gestion des lits', $current);
        nav('profile.php',
            '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'Mon profil', $current);
        ?>
    </nav>
    <div class="sb-footer">
        <a href="../logout.php" class="logout-btn">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Déconnexion
        </a>
    </div>
</aside>