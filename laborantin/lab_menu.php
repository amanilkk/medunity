
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sb-brand"><h1>LABORATOIRE</h1><p>Analyses & Stock</p></div>
    <div class="sb-user">
        <div class="ava"><?php echo strtoupper(substr($_SESSION['user_email'] ?? 'L', 0, 1)); ?></div>
        <div><div class="urole">Laborantin</div><div class="uemail"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div></div>
    </div>
    <nav class="sb-nav">
        <a href="index.php" class="nav-item <?php echo $current=='index.php'?'active':''; ?>"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
        <a href="index.php?page=tests" class="nav-item <?php echo ($_GET['page']??'')=='tests'?'active':''; ?>"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9-4-18-3 9H2"/></svg><span>Analyses</span></a>
        <a href="index.php?page=create" class="nav-item"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span>Nouvelle analyse</span></a>
        <a href="stock.php" class="nav-item <?php echo $current=='stock.php'?'active':''; ?>"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg><span>Consommables</span></a>
        <a href="stock.php?alerts=1" class="nav-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span>Alertes stock</span></a>
        <a href="orders.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg><span>Mes commandes</span></a>
        <!-- Lien vers Mon profil -->
        <a href="profile.php" class="nav-item <?php echo $current=='profile.php'?'active':''; ?>"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Mon profil</span></a>
    </nav>
    <div class="sb-footer"><a href="../logout.php" class="logout-btn"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Déconnexion</a></div>
</aside>