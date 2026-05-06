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
        <p>Comptabilité</p>
    </div>

    <div class="sb-user" style="padding: 12px 20px; border-bottom: 1px solid rgba(255,255,255,.05); display: flex; align-items: center; gap: 9px;">
        <div class="ava" style="width: 28px; height: 28px; border-radius: 50%; background: var(--green); display: flex; align-items: center; justify-content: center; font-size: .68rem; font-weight: 700; color: #fff; flex-shrink: 0;">
            <?php echo strtoupper(substr($_SESSION['user_email'] ?? 'C', 0, 1)); ?>
        </div>
        <div style="flex: 1;">
            <div style="font-size: .72rem; font-weight: 600; color: #ffffff;">Comptable</div>
            <div style="font-size: .62rem; color: rgba(255, 255, 255, 0.6);"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
        </div>
    </div>
    <nav class="sb-nav">

        <!-- Tableau de bord -->
        <?php nav('index.php', '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>', 'Dashboard', $current); ?>

        <!-- Gestion des paiements -->
        <div class="nav-section">
            <div class="nav-section-title">Paiements</div>
            <?php nav('payments.php', '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>', 'Enregistrer paiement', $current); ?>
            <?php nav('invoices.php', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>', 'Factures', $current); ?>
        </div>

        <!-- Gestion financière -->
        <div class="nav-section">
            <div class="nav-section-title">Finances</div>
            <?php nav('expenses.php', '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>', 'Dépenses', $current); ?>
            <?php nav('salaries.php', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'Salaires', $current); ?>
            <?php nav('reports.php', '<path d="M3 3v18h18"/><polyline points="18 17 13 12 8 17 3 9"/>', 'Rapports', $current); ?>
        </div>

        <!-- Suivi -->
        <div class="nav-section">
            <div class="nav-section-title">Suivi</div>
            <?php nav('revenue.php', '<path d="M12 2c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm0 2c5.523 0 10 4.477 10 10s-4.477 10-10 10-10-4.477-10-10 4.477-10 10-10zm3.5-2h-1v2.25h-5v-2.25h-1v2h-2.5a1.5 1.5 0 0 0-1.5 1.5v9a1.5 1.5 0 0 0 1.5 1.5h9a1.5 1.5 0 0 0 1.5-1.5v-9a1.5 1.5 0 0 0-1.5-1.5h-2.5v-2z"/>', 'Revenus', $current); ?>
        </div>
        <!-- Mon profil (NOUVEAU) -->
        <div class="nav-section">
            <div class="nav-section-title">Mon compte</div>
            <?php nav('profil.php', '<circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>', 'Mon profil', $current); ?>
        </div>

    </nav>

    <div class="sb-footer">
        <a href="../logout.php" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: rgba(255, 255, 255, 0.1); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 5px; font-family: inherit; font-size: 0.77rem; font-weight: 500; text-decoration: none; width: 100%; transition: 0.15s;">
            <svg viewBox="0 0 24 24" width="14" height="14" style="stroke: #ffffff; fill: none; stroke-width: 2;">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Déconnexion
        </a>
    </div>
</aside>