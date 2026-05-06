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

$message = '';
$error = '';

// === TRAITEMENT FORMULAIRE (Ajout) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_supplier') {
        $name = trim($_POST['name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $supplier_type = $_POST['supplier_type'];

        if (empty($name)) {
            $error = "Le nom du fournisseur est obligatoire";
        } else {
            $result = addSupplier($database, $name, $contact_person, $phone, $email, $address, $supplier_type);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }

    // === TRAITEMENT FORMULAIRE (Modification) ===
    if ($_POST['action'] === 'edit_supplier') {
        $supplier_id = intval($_POST['supplier_id']);
        $name = trim($_POST['name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $supplier_type = $_POST['supplier_type'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            $error = "Le nom du fournisseur est obligatoire";
        } else {
            $result = updateSupplier($database, $supplier_id, $name, $contact_person, $phone, $email, $address, $supplier_type, $is_active);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }

    // === TRAITEMENT SUPPRESSION ===
    if ($_POST['action'] === 'delete_supplier' && isset($_POST['supplier_id'])) {
        $supplier_id = intval($_POST['supplier_id']);
        $result = deleteSupplier($database, $supplier_id);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// === FILTRES ===
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// === RÉCUPÉRATION DES DONNÉES ===
$suppliers = getAllSuppliers($database, $status_filter !== 'inactive');

// Filtrer par recherche
if ($search) {
    $suppliers = array_filter($suppliers, function($s) use ($search) {
        return stripos($s['name'], $search) !== false ||
            stripos($s['contact_person'] ?? '', $search) !== false ||
            stripos($s['phone'] ?? '', $search) !== false;
    });
}

// Filtrer par type
if ($type_filter !== 'all') {
    $suppliers = array_filter($suppliers, function($s) use ($type_filter) {
        return $s['supplier_type'] === $type_filter;
    });
}

// Statistiques
$total_suppliers = count($suppliers);
$active_suppliers = count(array_filter($suppliers, function($s) { return $s['is_active'] == 1; }));
$inactive_suppliers = $total_suppliers - $active_suppliers;

// Types de fournisseurs pour les statistiques
$type_stats = [];
$type_counts = [
    'medicines' => 0,
    'equipment' => 0,
    'food' => 0,
    'other' => 0
];
foreach ($suppliers as $s) {
    if (isset($type_counts[$s['supplier_type']])) {
        $type_counts[$s['supplier_type']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Fournisseurs — Pharmacie</title>
    <link rel="stylesheet" href="pharmacien.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 15px;
            text-align: center;
            box-shadow: var(--sh);
        }
        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 5px;
        }
        .stat-card.primary .value { color: var(--green); }
        .form-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
            padding: 15px 20px;
        }
        .form-inline .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 150px;
        }
        .form-inline .form-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: var(--surface);
            border-radius: var(--r);
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .modal-head h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text3);
        }
        .modal-close:hover {
            color: var(--text);
        }
        .btn-xs {
            padding: 3px 8px;
            font-size: 0.7rem;
        }
        .btn-red {
            background: var(--red-l);
            color: var(--red);
            border: 1px solid #FADBD8;
        }
        .btn-red:hover {
            background: #FADBD8;
        }
        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .type-medicines { background: var(--green-l); color: var(--green); }
        .type-equipment { background: var(--blue-l); color: var(--blue); }
        .type-food { background: var(--amber-l); color: var(--amber); }
        .type-other { background: var(--purple-l); color: var(--purple); }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des fournisseurs</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
            <button onclick="openAddModal()" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouveau fournisseur
            </button>
        </div>
    </div>
    <div class="page-body">

        <?php if ($message): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-row">
            <div class="stat-card primary">
                <div class="value"><?php echo $total_suppliers; ?></div>
                <div class="label">Total fournisseurs</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--green);"><?php echo $active_suppliers; ?></div>
                <div class="label">Actifs</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--red);"><?php echo $inactive_suppliers; ?></div>
                <div class="label">Inactifs</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--blue);"><?php echo $type_counts['medicines']; ?></div>
                <div class="label">Médicaments</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card">
            <div class="card-head">
                <h3>Filtrer les fournisseurs</h3>
            </div>
            <form method="GET" class="form-inline">
                <div class="form-group">
                    <label>Recherche</label>
                    <input type="text" name="search" class="input" placeholder="Nom, contact, téléphone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="input">
                        <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>Tous</option>
                        <option value="medicines" <?php echo $type_filter == 'medicines' ? 'selected' : ''; ?>>Médicaments</option>
                        <option value="equipment" <?php echo $type_filter == 'equipment' ? 'selected' : ''; ?>>Équipement</option>
                        <option value="food" <?php echo $type_filter == 'food' ? 'selected' : ''; ?>>Alimentation</option>
                        <option value="other" <?php echo $type_filter == 'other' ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="status" class="input">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Tous</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Actifs</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="suppliers.php" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>

        <!-- Liste des fournisseurs -->
        <div class="card">
            <div class="card-head">
                <h3>Liste des fournisseurs (<?php echo count($suppliers); ?>)</h3>
            </div>
            <?php if (count($suppliers) === 0): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" width="40" height="40">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <h3>Aucun fournisseur trouvé</h3>
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Contact</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td style="font-weight:600"><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><?php echo htmlspecialchars($s['contact_person'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['phone'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['email'] ?? '—'); ?></td>
                            <td>
                        <span class="type-badge type-<?php echo $s['supplier_type']; ?>">
                            <?php
                            $type_labels = [
                                'medicines' => '💊 Médicaments',
                                'equipment' => '🔧 Équipement',
                                'food' => '🍎 Alimentation',
                                'other' => '📦 Autre'
                            ];
                            echo $type_labels[$s['supplier_type']] ?? $s['supplier_type'];
                            ?>
                        </span>
                            </td>
                            <td>
                        <span class="badge <?php echo $s['is_active'] ? 'badge-green' : 'badge-red'; ?>">
                            <?php echo $s['is_active'] ? 'Actif' : 'Inactif'; ?>
                        </span>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    <button onclick="openEditModal(<?php echo $s['id']; ?>)" class="btn btn-secondary btn-xs">✏️ Éditer</button>
                                    <button onclick="confirmDelete(<?php echo $s['id']; ?>, '<?php echo addslashes($s['name']); ?>')" class="btn btn-red btn-xs">🗑️ Supprimer</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal Ajout -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h3>➕ Ajouter un fournisseur</h3>
            <button onclick="closeAddModal()" class="modal-close">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_supplier">

            <div class="form-group">
                <label>Nom *</label>
                <input type="text" name="name" class="input" required>
            </div>
            <div class="form-group">
                <label>Personne de contact</label>
                <input type="text" name="contact_person" class="input">
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="tel" name="phone" class="input">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="input">
            </div>
            <div class="form-group">
                <label>Adresse</label>
                <textarea name="address" class="input" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Type de fournisseur</label>
                <select name="supplier_type" class="input">
                    <option value="medicines">💊 Médicaments</option>
                    <option value="equipment">🔧 Équipement médical</option>
                    <option value="food">🍎 Alimentation</option>
                    <option value="other">📦 Autre</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Ajouter</button>
            <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Annuler</button>
        </form>
    </div>
</div>

<!-- Modal Modification -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h3>✏️ Modifier le fournisseur</h3>
            <button onclick="closeEditModal()" class="modal-close">×</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit_supplier">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">

            <div class="form-group">
                <label>Nom *</label>
                <input type="text" name="name" id="edit_name" class="input" required>
            </div>
            <div class="form-group">
                <label>Personne de contact</label>
                <input type="text" name="contact_person" id="edit_contact_person" class="input">
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="tel" name="phone" id="edit_phone" class="input">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit_email" class="input">
            </div>
            <div class="form-group">
                <label>Adresse</label>
                <textarea name="address" id="edit_address" class="input" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Type de fournisseur</label>
                <select name="supplier_type" id="edit_supplier_type" class="input">
                    <option value="medicines">💊 Médicaments</option>
                    <option value="equipment">🔧 Équipement médical</option>
                    <option value="food">🍎 Alimentation</option>
                    <option value="other">📦 Autre</option>
                </select>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                    Actif
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Annuler</button>
        </form>
    </div>
</div>

<!-- Formulaire de suppression -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_supplier">
    <input type="hidden" name="supplier_id" id="delete_supplier_id">
</form>

<script>
    function openAddModal() {
        document.getElementById('addModal').style.display = 'flex';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function openEditModal(id) {
        // Récupérer les données via fetch ou les passer via PHP
        fetch(`get_supplier.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_supplier_id').value = data.id;
                document.getElementById('edit_name').value = data.name;
                document.getElementById('edit_contact_person').value = data.contact_person || '';
                document.getElementById('edit_phone').value = data.phone || '';
                document.getElementById('edit_email').value = data.email || '';
                document.getElementById('edit_address').value = data.address || '';
                document.getElementById('edit_supplier_type').value = data.supplier_type;
                document.getElementById('edit_is_active').checked = data.is_active == 1;
                document.getElementById('editModal').style.display = 'flex';
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des données');
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function confirmDelete(id, name) {
        if (confirm(`Supprimer le fournisseur "${name}" ?`)) {
            document.getElementById('delete_supplier_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    window.onclick = function(e) {
        if (e.target === document.getElementById('addModal')) closeAddModal();
        if (e.target === document.getElementById('editModal')) closeEditModal();
    }
</script>

<!-- Alternative: Passer les données directement via PHP pour l'édition -->
<?php
// Alternative sans fetch : stocker les données dans un tableau JavaScript
$suppliers_json = json_encode(array_values($suppliers));
?>
<script>
    const suppliersData = <?php echo $suppliers_json; ?>;
    function openEditModal(id) {
        const supplier = suppliersData.find(s => s.id == id);
        if (supplier) {
            document.getElementById('edit_supplier_id').value = supplier.id;
            document.getElementById('edit_name').value = supplier.name;
            document.getElementById('edit_contact_person').value = supplier.contact_person || '';
            document.getElementById('edit_phone').value = supplier.phone || '';
            document.getElementById('edit_email').value = supplier.email || '';
            document.getElementById('edit_address').value = supplier.address || '';
            document.getElementById('edit_supplier_type').value = supplier.supplier_type;
            document.getElementById('edit_is_active').checked = supplier.is_active == 1;
            document.getElementById('editModal').style.display = 'flex';
        }
    }
</script>

</body>
</html>