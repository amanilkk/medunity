<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current  = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['full_name'] ?? 'Dr. Médecin';
?>
<aside class="sidebar">
    <div class="brand">
        <i data-lucide="activity"></i>
        <span>CLINIQUE PRO</span>
    </div>

    <div class="sb-user">
        <div class="sb-avatar"><?= strtoupper(substr($username, 0, 2)) ?></div>
        <div style="overflow:hidden">
            <div class="sb-name"><?= htmlspecialchars($username) ?></div>
            <div class="sb-role">Médecin</div>
        </div>
    </div>

    <nav style="flex:1;overflow-y:auto">
        <div class="nav-section">Principal</div>

        <a href="index.php" class="nav-link <?= $current==='index.php' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i> Dashboard
        </a>
        <a href="patients.php" class="nav-link <?= in_array($current, ['patients.php','patient-profile.php']) ? 'active' : '' ?>">
            <i data-lucide="users"></i> Mes Patients
        </a>
        <a href="appointments.php" class="nav-link <?= $current==='appointments.php' ? 'active' : '' ?>">
            <i data-lucide="calendar"></i> Rendez-vous
        </a>
        <a href="schedule.php" class="nav-link <?= $current==='schedule.php' ? 'active' : '' ?>">
            <i data-lucide="calendar-days"></i> Planning
        </a>

        <div class="nav-section" style="margin-top:10px">Dossier Médical</div>

        <a href="diagnosis.php" class="nav-link <?= $current==='diagnosis.php' ? 'active' : '' ?>">
            <i data-lucide="clipboard-list"></i> Diagnostic
        </a>
        <a href="prescription.php" class="nav-link <?= $current==='prescription.php' ? 'active' : '' ?>">
            <i data-lucide="pill"></i> Ordonnances
        </a>
        <a href="medical-notes.php" class="nav-link <?= $current==='medical-notes.php' ? 'active' : '' ?>">
            <i data-lucide="file-text"></i> Notes Médicales
        </a>
        <a href="medical-history.php" class="nav-link <?= $current==='medical-history.php' ? 'active' : '' ?>">
            <i data-lucide="history"></i> Historique Médical
        </a>

        <div class="nav-section" style="margin-top:10px">Examens</div>

        <a href="lab-requests.php" class="nav-link <?= $current==='lab-requests.php' ? 'active' : '' ?>">
            <i data-lucide="microscope"></i> Analyses Labo
        </a>

        <div class="nav-section" style="margin-top:10px">Compte</div>

        <a href="settings.php" class="nav-link <?= $current==='settings.php' ? 'active' : '' ?>">
            <i data-lucide="settings"></i> Paramètres
        </a>
    </nav>

    <hr class="nav-divider">
    <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link danger">
            <i data-lucide="log-out"></i> Déconnexion
        </a>
    </div>
</aside>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
