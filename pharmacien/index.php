<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

// Vérifier que l'utilisateur est pharmacien
if ($_SESSION['role'] !== 'pharmacien') {
    header('Location: ../login.php');
    exit;
}

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// === STATISTIQUES ===
$stats = getPharmacyStatistics($database, $month_start, $month_end);

// Prescriptions en attente
$pending_prescriptions = getPendingPrescriptions($database);

// Médicaments en rupture de stock
$low_stock = getLowStockMedicines($database);

// Derniers mouvements de stock
$recent_movements = getStockMovements($database, $today, $today);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard — Pharmacie</title>
    <link rel="stylesheet" href="pharmacien.css">
    <style>
        /* ===== STYLES POUR LES MOUVEMENTS DE STOCK ===== */
        .movements-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 500px;
            overflow-y: auto;
            padding: 5px;
        }

        .movement-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 16px;
            background: var(--surface);
            border-radius: var(--rs);
            border-left: 4px solid;
            transition: all 0.2s ease;
            box-shadow: var(--sh);
        }

        .movement-item:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Entrée de stock */
        .movement-item.in {
            border-left-color: var(--green);
        }

        /* Sortie de stock */
        .movement-item.out {
            border-left-color: var(--red);
        }

        /* Icône du mouvement */
        .movement-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .movement-item.in .movement-icon {
            background: var(--green-l);
            color: var(--green);
        }

        .movement-item.out .movement-icon {
            background: var(--red-l);
            color: var(--red);
        }

        .movement-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        /* Contenu du mouvement */
        .movement-content {
            flex: 1;
        }

        .movement-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text);
            margin-bottom: 4px;
        }

        .movement-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.7rem;
            color: var(--text2);
        }

        .movement-qty {
            font-weight: 600;
            color: var(--green);
        }

        .movement-item.out .movement-qty {
            color: var(--red);
        }

        .movement-reason {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--surf2);
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Heure du mouvement */
        .movement-time {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text2);
            white-space: nowrap;
        }

        .movement-time .hour {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text);
        }

        .movement-time .date {
            font-size: 0.6rem;
            color: var(--text3);
        }

        /* Badge de quantité */
        .quantity-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .quantity-badge.in {
            background: var(--green-l);
            color: var(--green);
        }

        .quantity-badge.out {
            background: var(--red-l);
            color: var(--red);
        }

        /* Animation pour les nouveaux mouvements */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .movement-item {
            animation: slideIn 0.3s ease-out;
        }

        /* Scrollbar personnalisée pour la liste des mouvements */
        .movements-list::-webkit-scrollbar {
            width: 6px;
        }

        .movements-list::-webkit-scrollbar-track {
            background: var(--surf2);
            border-radius: 10px;
        }

        .movements-list::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        .movements-list::-webkit-scrollbar-thumb:hover {
            background: var(--text3);
        }

        /* En-tête des mouvements */
        .movements-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .movements-header h4 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .movements-header .total-count {
            background: var(--green-l);
            color: var(--green);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        /* Stats mini dans la carte */
        .movement-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px;
            background: var(--surf2);
            border-radius: var(--rs);
        }

        .movement-stat {
            flex: 1;
            text-align: center;
        }

        .movement-stat .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .movement-stat .stat-label {
            font-size: 0.65rem;
            color: var(--text2);
        }

        .movement-stat.in .stat-value { color: var(--green); }
        .movement-stat.out .stat-value { color: var(--red); }

        /* Responsive */
        @media (max-width: 768px) {
            .movement-item {
                flex-wrap: wrap;
            }
            .movement-time {
                flex-direction: row;
                justify-content: space-between;
                width: 100%;
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid var(--border);
            }
            .movement-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Tableau de bord Pharmacie</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <a href="medicines.php" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
                </svg>
                Gérer médicaments
            </a>
        </div>
    </div>
    <div class="page-body">

        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <div class="stat-ico a">
                    <svg viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-5.04-6.71l-2.75 3.54-2.16-2.66c-.23-.29-.62-.29-.85 0-.23.29-.23.77 0 1.06l2.5 3.1c.23.29.62.29.85 0l3.54-4.46c.23-.29.23-.77 0-1.06-.23-.29-.62-.29-.85 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['total_medicines']; ?></div>
                    <div class="stat-lbl">Médicaments</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico b">
                    <svg viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/>
                        <line x1="9" y1="9" x2="15" y2="9"/>
                        <line x1="9" y1="13" x2="15" y2="13"/>
                        <line x1="9" y1="17" x2="13" y2="17"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['prescriptions_delivered']; ?></div>
                    <div class="stat-lbl">Délivrées ce mois</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico r">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['prescriptions_pending']; ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico y">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['low_stock_count']; ?></div>
                    <div class="stat-lbl">En rupture/alerte</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-ico p">
                    <svg viewBox="0 0 24 24">
                        <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['stock_movements']; ?></div>
                    <div class="stat-lbl">Mouvements aujourd'hui</div>
                </div>
            </div>
        </div>

        <!-- Prescriptions en attente -->
        <div class="card">
            <div class="card-head">
                <h3>📋 Prescriptions en attente de délivrance</h3>
                <a href="prescriptions.php?status=pending" class="btn btn-secondary btn-sm">Voir tout →</a>
            </div>
            <?php if (count($pending_prescriptions) === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" width="40" height="40">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <h3>Aucune prescription en attente</h3>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Date</th>
                        <th>Articles</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($pending_prescriptions, 0, 10) as $p): ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($p['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['doctor_name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['prescription_date'])); ?></td>
                            <td>
                                <span class="badge badge-blue">
                                    <?php
                                    $items = getPrescriptionItems($database, $p['id']);
                                    echo count($items) . ' article(s)';
                                    ?>
                                </span>
                            </td>
                            <td>
                                <a href="prescription-detail.php?id=<?php echo $p['id']; ?>" class="btn btn-secondary btn-xs">Détails</a>
                                <a href="prescriptions.php?action=prepare&id=<?php echo $p['id']; ?>" class="btn btn-blue btn-xs">Préparer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="row-2">
            <!-- Médicaments en rupture -->
            <div class="card">
                <div class="card-head">
                    <h3>⚠️ Médicaments en alerte stock</h3>
                    <a href="medicines.php?status=low_stock" class="btn btn-secondary btn-sm">Voir tout →</a>
                </div>
                <?php if (count($low_stock) === 0): ?>
                    <div class="empty">
                        <svg viewBox="0 0 24 24" width="40" height="40">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <h3>Tous les médicaments sont bien approvisionnés</h3>
                    </div>
                <?php else: ?>
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>Médicament</th>
                            <th>Stock</th>
                            <th>Alerte</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($low_stock, 0, 8) as $med): ?>
                            <tr class="row-alert">
                                <td style="font-weight:600"><?php echo htmlspecialchars($med['name']); ?></td>
                                <td class="badge badge-red"><?php echo $med['current_stock']; ?> unités</td>
                                <td><?php echo $med['threshold_alert']; ?> min</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Derniers mouvements - Version améliorée -->
            <div class="card">
                <div class="card-head">
                    <h3>📊 Mouvements d'aujourd'hui</h3>
                    <a href="stock_movements.php" class="btn btn-secondary btn-sm">Voir tout →</a>
                </div>
                <?php if (count($recent_movements) === 0): ?>
                    <div class="empty">
                        <svg viewBox="0 0 24 24" width="40" height="40">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <h3>Aucun mouvement aujourd'hui</h3>
                        <p>Ajoutez des entrées ou sorties de stock</p>
                    </div>
                <?php else:
                    // Calculer les totaux pour les stats
                    $total_in = 0;
                    $total_out = 0;
                    foreach ($recent_movements as $mv) {
                        if ($mv['type'] === 'in') $total_in += $mv['quantity'];
                        else $total_out += $mv['quantity'];
                    }
                    ?>
                    <!-- Mini statistiques des mouvements -->
                    <div class="movement-stats">
                        <div class="movement-stat in">
                            <div class="stat-value">+<?php echo $total_in; ?></div>
                            <div class="stat-label">Entrées</div>
                        </div>
                        <div class="movement-stat out">
                            <div class="stat-value">-<?php echo $total_out; ?></div>
                            <div class="stat-label">Sorties</div>
                        </div>
                        <div class="movement-stat">
                            <div class="stat-value"><?php echo count($recent_movements); ?></div>
                            <div class="stat-label">Mouvements</div>
                        </div>
                    </div>

                    <div class="movements-list">
                        <?php foreach (array_slice($recent_movements, 0, 8) as $mv): ?>
                            <div class="movement-item <?php echo $mv['type']; ?>">
                                <div class="movement-icon">
                                    <?php if ($mv['type'] === 'in'): ?>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                                            <path d="M2 12l10 5 10-5"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 22L2 17l10-5 10 5-10 5z"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="movement-content">
                                    <div class="movement-title"><?php echo htmlspecialchars($mv['medicine_name']); ?></div>
                                    <div class="movement-meta">
                                        <span class="movement-qty">
                                            <?php echo $mv['type'] === 'in' ? '+' : '-'; ?><?php echo $mv['quantity']; ?> unités
                                        </span>
                                        <span class="movement-reason"><?php echo ucfirst($mv['reason']); ?></span>
                                    </div>
                                </div>
                                <div class="movement-time">
                                    <span class="hour"><?php echo date('H:i', strtotime($mv['movement_date'])); ?></span>
                                    <span class="date"><?php echo date('d/m/Y', strtotime($mv['movement_date'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</body>
</html>