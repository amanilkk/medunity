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
        <p>Ressources Humaines</p>
    </div>

    <div class="sb-user">
        <div class="ava">
            <?php echo strtoupper(substr($_SESSION['user_email'] ?? 'R', 0, 1)); ?>
        </div>
        <div>
            <div>RH</div>
            <div><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
        </div>
    </div>

    <nav class="sb-nav">

        <!-- Dashboard -->
        <?php nav('index.php', '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>', 'Dashboard', $current); ?>

        <!-- Gestion du personnel -->
        <div class="nav-section">
            <div class="nav-section-title">Personnel</div>
            <?php nav('employees.php', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'Employés', $current); ?>
            <?php nav('attendance.php', '<path d="M9 11l3 3L22 4"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>', 'Présences/Absences', $current); ?>
            <?php nav('assignments.php', '<path d="M18 8A6 6 0 0 0 6 8m12 0v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8m12 0L12 2m0 0L6 8"/>', 'Affectations', $current); ?>
        </div>

        <!-- Gestion congés et planning -->
        <div class="nav-section">
            <div class="nav-section-title">Congés & Planning</div>
            <?php nav('leaves.php', '<path d="M3 9l9-7 9 7v11H3z"/><polyline points="9 22 9 12 15 12 15 22"/>', 'Congés', $current); ?>
            <?php nav('planning.php', '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', 'Planning', $current); ?>
        </div>

        <!-- Documents et contrats -->
        <div class="nav-section">
            <div class="nav-section-title">Dossiers</div>
            <?php nav('documents.php', '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>', 'Documents', $current); ?>
            <?php nav('reports.php', '<path d="M3 3v18h18"/><polyline points="18 17 13 12 8 17 3 9"/>', 'Rapports', $current); ?>
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