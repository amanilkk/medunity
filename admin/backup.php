<?php
// backup.php - Gestion des sauvegardes (manuel + automatique)
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
date_default_timezone_set('Africa/Algiers');
$today = date('Y-m-d');
$msg = "";

// Créer le dossier de sauvegarde s'il n'existe pas
$backup_dir = __DIR__ . '/backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Fonction pour créer une sauvegarde
function create_backup($database, $backup_type, $uid, $ip, $backup_note = '') {
    global $backup_dir;
    $date = date('Y-m-d_H-i-s');
    $filename = "backup_{$date}_{$backup_type}.sql";
    $filepath = $backup_dir . $filename;

    // Récupérer toutes les tables
    $tables = [];
    $result = $database->query("SHOW TABLES");
    while($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    // Créer le contenu SQL
    $sql_content = "-- Backup created on " . date('Y-m-d H:i:s') . "\n";
    $sql_content .= "-- Backup type: $backup_type\n";
    $sql_content .= "-- Note: $backup_note\n";
    $sql_content .= "-- --------------------------------------------------------\n\n";

    // Désactiver les contraintes de clés étrangères
    $sql_content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach($tables as $table) {
        $result = $database->query("SELECT * FROM $table");
        $num_fields = $result->field_count;

        // Drop table si elle existe
        $sql_content .= "DROP TABLE IF EXISTS `$table`;\n";

        // Create table
        $create = $database->query("SHOW CREATE TABLE $table")->fetch_row();
        $sql_content .= $create[1] . ";\n\n";

        // Insert data
        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $sql_content .= "INSERT INTO `$table` VALUES(";
                $values = [];
                foreach($row as $value) {
                    if($value === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . addslashes($value) . "'";
                    }
                }
                $sql_content .= implode(",", $values) . ");\n";
            }
            $sql_content .= "\n";
        }
    }

    // Réactiver les contraintes
    $sql_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    // Écrire le fichier
    file_put_contents($filepath, $sql_content);

    // Compression en .gz
    $gz_content = gzencode($sql_content, 9);
    file_put_contents($filepath . '.gz', $gz_content);

    // Enregistrer dans les logs
    $database->query("INSERT INTO logs (user_id, action, entity_type, entity_id, ip_address) 
                      VALUES ($uid, 'MANUAL_BACKUP', '$backup_type', '$filename', '$ip')");

    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

// Sauvegarde automatique (à exécuter via cron)
if(isset($_GET['auto_backup'])) {
    $uid = 1;
    $ip = $_SERVER['REMOTE_ADDR'];
    $result = create_backup($database, 'auto_full', $uid, $ip, 'Sauvegarde automatique quotidienne');
    if($result['success']) {
        $files = glob($backup_dir . "*.sql.gz");
        foreach($files as $file) {
            if(filemtime($file) < strtotime('-30 days')) {
                unlink($file);
                $sql_file = str_replace('.gz', '', $file);
                if(file_exists($sql_file)) {
                    unlink($sql_file);
                }
            }
        }
        echo "OK - Backup created: " . $result['filename'];
    }
    exit;
}

// Télécharger une sauvegarde
if(isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filepath = $backup_dir . $file;
    if(file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

// Fonction pour exécuter les requêtes SQL de restauration
function execute_sql_queries($database, $sql) {
    // Supprimer les commentaires SQL
    $lines = explode("\n", $sql);
    $clean_sql = "";
    foreach($lines as $line) {
        if(substr(trim($line), 0, 2) != '--' && substr(trim($line), 0, 1) != '#') {
            $clean_sql .= $line . "\n";
        }
    }

    // Désactiver les contraintes avant la restauration
    $database->query("SET FOREIGN_KEY_CHECKS = 0");

    // Séparer les requêtes par point-virgule
    $queries = explode(";\n", $clean_sql);
    $errors = [];

    foreach($queries as $query) {
        $query = trim($query);
        if(!empty($query)) {
            if(!$database->query($query)) {
                $errors[] = $database->error;
            }
        }
    }

    // Réactiver les contraintes
    $database->query("SET FOREIGN_KEY_CHECKS = 1");

    return $errors;
}

// Restaurer une sauvegarde
if(isset($_POST['restore'])) {
    $file = basename($_POST['restore_file']);
    $filepath = $backup_dir . $file;

    if(file_exists($filepath)) {
        // Lire le contenu du fichier
        if(pathinfo($filepath, PATHINFO_EXTENSION) == 'gz') {
            $sql = gzdecode(file_get_contents($filepath));
        } else {
            $sql = file_get_contents($filepath);
        }

        if($sql) {
            $errors = execute_sql_queries($database, $sql);

            if(empty($errors)) {
                $uid = intval($_SESSION['uid']??1);
                $ip = $_SERVER['REMOTE_ADDR'];
                $database->query("INSERT INTO logs (user_id, action, entity_type, entity_id, ip_address) 
                                  VALUES ($uid, 'RESTORE_BACKUP', 'restore', '$file', '$ip')");
                $msg = "success:Sauvegarde restaurée avec succès !";
            } else {
                $msg = "error:Erreurs lors de la restauration : " . implode(", ", $errors);
            }
        } else {
            $msg = "error:Impossible de lire le fichier de sauvegarde.";
        }
    } else {
        $msg = "error:Fichier de sauvegarde introuvable.";
    }
}

// Sauvegarde manuelle
if(isset($_POST['do_backup'])){
    $bk_type = $database->real_escape_string($_POST['backup_type'] ?? 'full');
    $bk_note = $database->real_escape_string($_POST['backup_note'] ?? '');
    $uid     = intval($_SESSION['uid']??1);
    $ip      = $_SERVER['REMOTE_ADDR'];

    $result = create_backup($database, $bk_type, $uid, $ip, $bk_note);
    if($result['success']) {
        $msg = "success:Sauvegarde lancée avec succès. Fichier : " . $result['filename'];
    } else {
        $msg = "error:Erreur lors de la création de la sauvegarde.";
    }
}

// Supprimer une sauvegarde
if(isset($_GET['delete_backup'])) {
    $file = basename($_GET['delete_backup']);
    $filepath = $backup_dir . $file;
    if(file_exists($filepath)) {
        unlink($filepath);
        $gz_file = $filepath . '.gz';
        if(file_exists($gz_file)) {
            unlink($gz_file);
        }
        $msg = "success:Sauvegarde supprimée avec succès.";
    }
}

// Récupérer la liste des sauvegardes
$backup_files = glob($backup_dir . "*.sql.gz");
if(empty($backup_files)) {
    $backup_files = glob($backup_dir . "*.sql");
}
rsort($backup_files);

// Statistiques DB
$db_size = $database->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) AS size FROM information_schema.tables WHERE table_schema=DATABASE()")->fetch_assoc();
$nb_tables = $database->query("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE()")->fetch_assoc()['n'];
$last_bk = $database->query("SELECT l.created_at, l.entity_type, u.full_name FROM logs l LEFT JOIN users u ON u.id=l.user_id WHERE l.action IN ('MANUAL_BACKUP','AUTO_BACKUP') ORDER BY l.created_at DESC LIMIT 1")->fetch_assoc();
$bk_history = $database->query("SELECT l.created_at, l.action, l.entity_type, l.ip_address, u.full_name FROM logs l LEFT JOIN users u ON u.id=l.user_id WHERE l.action IN ('MANUAL_BACKUP','AUTO_BACKUP') ORDER BY l.created_at DESC LIMIT 30");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Sauvegarde</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .backup-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .backup-stat {
            text-align: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
        }
        .backup-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
        }
        .backup-stat-label {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-input, .filter-select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.8rem;
            outline: none;
        }
        .filter-input:focus, .filter-select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.1);
        }
        .backup-file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .backup-file-item:last-child {
            border-bottom: none;
        }
        .backup-file-name {
            font-weight: 500;
            font-family: monospace;
            font-size: 0.8rem;
        }
        .backup-file-size {
            font-size: 0.7rem;
            color: #64748b;
        }
        .backup-actions {
            display: flex;
            gap: 8px;
        }
        .badge-auto {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-manual {
            background: #fef3c7;
            color: #92400e;
        }
        .cron-instruction {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 0.8rem;
            border-left: 3px solid #10b981;
        }
        .flex {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
<div class="app">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-area">
            <div class="logo">Admin<span>Clinique</span></div>
            <div class="logo-sub">Gestion Administrative</div>
        </div>
        <nav>
            <a href="index.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2h-5v-8H7v8H5a2 2 0 0 1-2-2z"/>
                </svg>
                <span>Statistiques</span>
            </a>
            <a href="users.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span>Utilisateurs</span>
            </a>
            <a href="roles.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/>
                </svg>
                <span>Rôles</span>
            </a>
            <a href="security.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <span>Sécurité</span>
            </a>
            <a href="backup.php" class="nav-item active">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 15 7 15 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                <span>Sauvegarde</span>
            </a>
            <a href="profile.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">💾 Sauvegarde & Restauration</div>
            <div class="date-badge"><?= $today ?></div>
        </div>
        <div class="content">

            <!-- Messages -->
            <?php if($msg):
                $parts = explode(':', $msg, 2);
                $type = $parts[0];
                $text = $parts[1] ?? '';
                $alert_class = $type == 'success' ? 'alert-success' : 'alert-error';
                ?>
                <div class="alert <?= $alert_class ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?php if($type == 'success'): ?>
                            <path d="M20 6L9 17l-5-5"/>
                        <?php else: ?>
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        <?php endif; ?>
                    </svg>
                    <?= htmlspecialchars($text) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques DB -->
            <div class="backup-info">
                <div class="backup-stat">
                    <div class="backup-stat-value"><?= $db_size['size'] ?? '?' ?> MB</div>
                    <div class="backup-stat-label">Taille de la base</div>
                </div>
                <div class="backup-stat">
                    <div class="backup-stat-value"><?= $nb_tables ?></div>
                    <div class="backup-stat-label">Tables</div>
                </div>
                <div class="backup-stat">
                    <div class="backup-stat-value"><?= $last_bk ? substr($last_bk['created_at'], 0, 10) : 'Jamais' ?></div>
                    <div class="backup-stat-label">Dernière sauvegarde</div>
                </div>
                <div class="backup-stat">
                    <div class="backup-stat-value"><?= count($backup_files) ?></div>
                    <div class="backup-stat-label">Sauvegardes disponibles</div>
                </div>
            </div>

            <!-- Sauvegarde manuelle -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">🔄 Sauvegarde manuelle</div>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="POST" action="backup.php" class="flex" style="gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                        <div class="filter-group">
                            <label>Type de sauvegarde</label>
                            <select name="backup_type" class="filter-select">
                                <option value="full">Complète (Base de données)</option>
                                <option value="database">Base de données uniquement</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Note (optionnelle)</label>
                            <input type="text" name="backup_note" class="filter-input" placeholder="Raison de la sauvegarde..." style="width: 250px;">
                        </div>
                        <div>
                            <button type="submit" name="do_backup" class="btn btn-primary">
                                💾 Lancer la sauvegarde
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sauvegarde automatique -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">⏰ Sauvegarde automatique quotidienne</div>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <div class="alert alert-info" style="margin-bottom: 16px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="12" x2="12" y2="16"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        Les sauvegardes automatiques sont créées chaque jour à 02h00. Les anciennes sauvegardes (plus de 30 jours) sont automatiquement supprimées.
                    </div>

                    <div class="cron-instruction">
                        <strong>📋 Configuration automatique (Cron Job) :</strong><br>
                        Ajoutez cette ligne à votre crontab pour exécuter la sauvegarde automatique chaque jour à 2h00 :<br>
                        <code>0 2 * * * php <?= __DIR__ ?>/backup.php?auto_backup=1 >> <?= __DIR__ ?>/backup_log.txt 2>&1</code>
                    </div>

                    <div class="flex" style="margin-top: 16px; gap: 12px;">
                        <a href="?auto_backup=1" class="btn btn-secondary" onclick="return confirm('Lancer une sauvegarde automatique maintenant ?')">
                            ▶️ Lancer la sauvegarde automatique maintenant
                        </a>
                    </div>
                </div>
            </div>

            <!-- Liste des sauvegardes -->
            <div class="card-header" style="margin-bottom: 0;">
                <div class="card-title">📁 Sauvegardes disponibles (<?= count($backup_files) ?>)</div>
            </div>
            <div class="card">
                <div class="card-body" style="padding: 0;">
                    <?php if(empty($backup_files)): ?>
                        <div class="empty-state">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 15 7 15 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            <p>Aucune sauvegarde disponible.</p>
                            <p style="font-size: 0.8rem;">Lancez votre première sauvegarde ci-dessus.</p>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach($backup_files as $file):
                                $filename = basename($file);
                                $filesize = round(filesize($file) / 1024 / 1024, 2);
                                $filedate = date('Y-m-d H:i:s', filemtime($file));
                                $is_auto = strpos($filename, 'auto_full') !== false;
                                ?>
                                <div class="backup-file-item">
                                    <div style="flex: 2;">
                                        <div class="backup-file-name"><?= htmlspecialchars($filename) ?></div>
                                        <div class="backup-file-size">
                                            <?= $filedate ?> • <?= $filesize ?> MB
                                            <?php if($is_auto): ?>
                                                <span class="badge badge-auto" style="margin-left: 8px;">Automatique</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="backup-actions">
                                        <a href="?download=<?= urlencode($filename) ?>" class="btn btn-soft btn-sm" download>
                                            ⬇️ Télécharger
                                        </a>
                                        <form method="POST" action="backup.php" style="display: inline;" onsubmit="return confirm('⚠️ Attention : La restauration va remplacer toutes les données actuelles. Êtes-vous sûr de vouloir continuer ?')">
                                            <input type="hidden" name="restore_file" value="<?= htmlspecialchars($filename) ?>">
                                            <button type="submit" name="restore" class="btn btn-secondary btn-sm">
                                                🔄 Restaurer
                                            </button>
                                        </form>
                                        <a href="?delete_backup=<?= urlencode($filename) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette sauvegarde ?')">
                                            🗑 Supprimer
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historique des sauvegardes -->
            <div class="card-header" style="margin-top: 24px; margin-bottom: 0;">
                <div class="card-title">📋 Historique des sauvegardes</div>
            </div>
            <div class="card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Date / Heure</th>
                            <th>Mode</th>
                            <th>Type</th>
                            <th>Lancé par</th>
                            <th>IP</th>
                            <th>Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if(!$bk_history || $bk_history->num_rows == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 40px;">Aucun historique de sauvegarde.</td>
                            </tr>
                        <?php else:
                            while($bk = $bk_history->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-size: 0.75rem;"><?= substr($bk['created_at'], 0, 16) ?></td>
                                    <td>
                                        <span class="badge <?= $bk['action'] == 'AUTO_BACKUP' ? 'badge-auto' : 'badge-manual' ?>">
                                            <?= $bk['action'] == 'AUTO_BACKUP' ? 'Automatique' : 'Manuel' ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.75rem;"><?= htmlspecialchars($bk['entity_type'] ?? 'full') ?></td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($bk['full_name'] ?? 'Système') ?></td>
                                    <td style="font-size: 0.7rem;"><?= htmlspecialchars($bk['ip_address'] ?? '—') ?></td>
                                    <td><span class="badge badge-active">✅ Succès</span></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Instructions -->
            <div class="alert alert-info" style="margin-top: 24px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="12" x2="12" y2="16"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                <strong>💡 Information :</strong> Les sauvegardes sont stockées dans le dossier <code>admin/backups/</code>.
                Vous pouvez télécharger n'importe quelle version et la restaurer à tout moment.
                La restauration remplacera TOUTES les données actuelles par celles de la sauvegarde sélectionnée.
            </div>
        </div>
    </div>
</div>
</body>
</html>