<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$current = basename($_SERVER['PHP_SELF']);

function nav($file, $icon, $label, $current) {
    $active = ($file === $current) ? 'active' : '';
    echo "
    <a href='$file' class='nav-item $active'>
        <svg viewBox='0 0 24 24'>$icon</svg>
        <span>$label</span>
    </a>";
}
?>

<aside class="sidebar">

    <div class="sb-brand">

        <h1>MedUnity</h1>
        <p>Pharmacie</p>
    </div>

    <div class="sb-user">
        <div class="ava">
            <?php echo strtoupper(substr($_SESSION['user_email'] ?? 'P', 0, 1)); ?>
        </div>
        <div class="user-info">
            <div class="user-role">Pharmacien</div>
            <div class="user-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
        </div>
    </div>

    <nav class="sb-nav">

        <!-- Dashboard -->
        <?php nav('index.php', '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>', 'Dashboard', $current); ?>

        <!-- Gestion des médicaments -->
        <div class="nav-section">
            <div class="nav-section-title">Médicaments</div>
            <?php nav('medicines.php', '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>', 'Tous les médicaments', $current); ?>
            <?php nav('stock_movements.php', '<path d="M13 10V3L4 14h7v7l9-11h-7z"/>', 'Mouvements stock', $current); ?>
            <?php nav('suppliers.php', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'Fournisseurs', $current); ?>
        </div>

        <!-- Commandes laboratoire (NOUVEAU) -->
        <div class="nav-section">
            <div class="nav-section-title">Commandes</div>
            <?php nav('pharmacien_orders.php', '<path d="M16 3v2H8V3H6v2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3h-2zm-8 8h8v2H8v-2z"/><rect x="8" y="13" width="8" height="2"/>', 'Commandes labo', $current); ?>
        </div>

        <!-- Prescriptions -->
        <div class="nav-section">
            <div class="nav-section-title">Prescriptions</div>
            <?php nav('prescriptions.php', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>', 'Ordonnances', $current); ?>
        </div>

        <!-- Mon profil -->
        <div class="nav-section">
            <div class="nav-section-title">Mon compte</div>
            <?php nav('profil.php', '<circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>', 'Mon profil', $current); ?>
        </div>

    </nav>

    <div class="sb-footer">
        <a href="../logout.php" class="logout-btn">
            <svg viewBox="0 0 24 24" width="14" height="14">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Déconnexion
        </a>
    </div>

</aside>