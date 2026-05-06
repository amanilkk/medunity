<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include '../connection.php';
include 'functions.php';

// Vérifier que l'utilisateur est RH
if ($_SESSION['role'] !== 'gestionnaire_rh') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';
$user_id = $_GET['user_id'] ?? null;
$action = $_GET['action'] ?? null;

// === TRAITEMENT UPLOAD DOCUMENT ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $user_id = intval($_POST['user_id']);
    $document_type = $_POST['document_type'];

    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document_file'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

        if (!in_array($file['type'], $allowed_types)) {
            $error = "Type de fichier non autorisé. Formats acceptés: PDF, JPEG, PNG, DOC, DOCX";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = "Fichier trop volumineux. Maximum 5 Mo.";
        } else {
            $result = uploadEmployeeDocument($database, $user_id, $document_type, $file, $_SESSION['user_id']);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    } else {
        $error = "Veuillez sélectionner un fichier";
    }
}

// === TRAITEMENT SUPPRESSION DOCUMENT ===
if ($action === 'delete' && isset($_GET['doc_id'])) {
    $doc_id = intval($_GET['doc_id']);
    $result = deleteEmployeeDocument($database, $doc_id);
    if ($result) {
        $message = "Document supprimé avec succès";
    } else {
        $error = "Erreur lors de la suppression";
    }
}

// === RÉCUPÉRER LES DONNÉES ===

// Liste des employés
$employees = getAllEmployees($database, true);

// Détails de l'employé sélectionné
$employee_details = null;
$documents = [];
if ($user_id) {
    $stmt = $database->prepare("SELECT u.*, r.role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $employee_details = $stmt->get_result()->fetch_assoc();

    $documents = getEmployeeDocuments($database, $user_id);
}

// Types de documents avec icônes
$document_types = [
    'cv' => ['label' => 'CV', 'icon' => '📄', 'color' => 'blue'],
    'diploma' => ['label' => 'Diplôme', 'icon' => '🎓', 'color' => 'green'],
    'certificate' => ['label' => 'Certificat', 'icon' => '🏅', 'color' => 'amber'],
    'id_card' => ['label' => 'Carte d\'identité', 'icon' => '🪪', 'color' => 'purple'],
    'contract' => ['label' => 'Contrat', 'icon' => '📑', 'color' => 'orange'],
    'other' => ['label' => 'Autre', 'icon' => '📎', 'color' => 'gray']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Documents employés — RH</title>
    <link rel="stylesheet" href="rh.css">
    <style>
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 22px;
        }
        .info-card {
            background: var(--surf2);
            border-radius: var(--r);
            padding: 15px;
            margin-bottom: 15px;
        }
        .info-card p {
            margin: 8px 0;
            font-size: 0.85rem;
        }
        .info-card .label {
            font-weight: 600;
            color: var(--text2);
            width: 120px;
            display: inline-block;
        }
        .document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background: var(--surf2);
            border-radius: var(--rs);
            margin-bottom: 10px;
            transition: all 0.15s;
        }
        .document-item:hover {
            background: var(--border);
        }
        .document-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .document-icon.blue { background: var(--blue-l); }
        .document-icon.green { background: var(--green-l); }
        .document-icon.amber { background: var(--amber-l); }
        .document-icon.purple { background: var(--purple-l); }
        .document-icon.orange { background: var(--orange-l); }
        .document-icon.gray { background: var(--surf2); }

        .document-details {
            flex: 1;
        }
        .document-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .document-meta {
            font-size: 0.7rem;
            color: var(--text2);
            margin-top: 3px;
        }
        .document-actions {
            display: flex;
            gap: 8px;
        }
        .btn-icon {
            padding: 6px 10px;
            font-size: 0.75rem;
        }
        .empty-docs {
            text-align: center;
            padding: 40px;
            color: var(--text2);
        }
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--r);
            padding: 20px;
            text-align: center;
            transition: all 0.15s;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: var(--green);
            background: var(--green-l);
        }
        .upload-area input {
            display: none;
        }
        .type-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .type-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: var(--rs);
            background: var(--surface);
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
        }
        .type-btn.selected {
            border-color: var(--green);
            background: var(--green-l);
        }
        .type-btn .icon {
            font-size: 1.2rem;
        }
        .type-btn .label {
            font-size: 0.7rem;
            font-weight: 600;
        }
        .file-info {
            margin-top: 10px;
            font-size: 0.7rem;
            color: var(--text2);
        }
        .badge-doc-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-cv { background: var(--blue-l); color: var(--blue); }
        .badge-diploma { background: var(--green-l); color: var(--green); }
        .badge-certificate { background: var(--amber-l); color: var(--amber); }
        .badge-id_card { background: var(--purple-l); color: var(--purple); }
        .badge-contract { background: var(--orange-l); color: var(--orange); }
        .badge-other { background: var(--surf2); color: var(--text2); }

        .employee-selector {
            margin-bottom: 20px;
        }
        .employee-selector select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--rs);
            font-size: 0.9rem;
        }
        .stats-docs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-doc {
            text-align: center;
            padding: 10px;
            background: var(--surf2);
            border-radius: var(--rs);
        }
        .stat-doc .number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--green);
        }
        .stat-doc .label {
            font-size: 0.65rem;
            color: var(--text2);
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">Gestion des documents</span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
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

        <div class="two-columns">
            <!-- Colonne gauche : Sélection employé + Upload -->
            <div>
                <!-- Sélection de l'employé -->
                <div class="card">
                    <div class="card-head">
                        <h3>👤 Sélectionner un employé</h3>
                    </div>
                    <div class="card-body employee-selector">
                        <form method="GET" id="employeeSelectForm">
                            <select name="user_id" class="input" onchange="this.form.submit()">
                                <option value="">-- Choisir un employé --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo ($user_id == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['role_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Formulaire d'upload (visible seulement si employé sélectionné) -->
                <?php if ($user_id && $employee_details): ?>
                    <div class="card">
                        <div class="card-head">
                            <h3>📤 Uploader un document</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                <input type="hidden" name="action" value="upload">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                                <div class="form-group">
                                    <label>Type de document *</label>
                                    <div class="type-buttons" id="typeButtons">
                                        <?php foreach ($document_types as $key => $type): ?>
                                            <div class="type-btn" data-type="<?php echo $key; ?>">
                                                <div class="icon"><?php echo $type['icon']; ?></div>
                                                <div class="label"><?php echo $type['label']; ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="document_type" id="document_type" required>
                                </div>

                                <div class="form-group">
                                    <label>Fichier *</label>
                                    <div class="upload-area" id="uploadArea">
                                        <div>📁 Cliquez ou glissez un fichier</div>
                                        <div style="font-size:0.7rem; margin-top:5px;">PDF, JPEG, PNG, DOC, DOCX (Max 5 Mo)</div>
                                        <input type="file" name="document_file" id="document_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    </div>
                                    <div class="file-info" id="fileInfo"></div>
                                </div>

                                <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>Uploader</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Colonne droite : Détails et documents -->
            <div>
                <?php if ($user_id && $employee_details): ?>
                    <!-- Informations employé -->
                    <div class="card">
                        <div class="card-head">
                            <h3>📋 Informations employé</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-card">
                                <p><span class="label">Nom :</span> <?php echo htmlspecialchars($employee_details['full_name']); ?></p>
                                <p><span class="label">Email :</span> <?php echo htmlspecialchars($employee_details['email']); ?></p>
                                <p><span class="label">Téléphone :</span> <?php echo htmlspecialchars($employee_details['phone'] ?? '—'); ?></p>
                                <p><span class="label">Rôle :</span> <?php echo htmlspecialchars($employee_details['role_name']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques des documents -->
                    <div class="stats-docs">
                        <div class="stat-doc">
                            <div class="number"><?php echo count($documents); ?></div>
                            <div class="label">Total documents</div>
                        </div>
                        <div class="stat-doc">
                            <div class="number">
                                <?php
                                $cv_count = count(array_filter($documents, function($d) { return $d['document_type'] == 'cv'; }));
                                echo $cv_count;
                                ?>
                            </div>
                            <div class="label">CV</div>
                        </div>
                        <div class="stat-doc">
                            <div class="number">
                                <?php
                                $diploma_count = count(array_filter($documents, function($d) { return $d['document_type'] == 'diploma'; }));
                                echo $diploma_count;
                                ?>
                            </div>
                            <div class="label">Diplômes</div>
                        </div>
                    </div>

                    <!-- Liste des documents -->
                    <div class="card">
                        <div class="card-head">
                            <h3>📄 Documents (<?php echo count($documents); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($documents)): ?>
                                <div class="empty-docs">
                                    <div style="font-size: 3rem;">📂</div>
                                    <h3>Aucun document</h3>
                                    <p>Utilisez le formulaire pour uploader des documents</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($documents as $doc):
                                    $type_info = $document_types[$doc['document_type']] ?? $document_types['other'];
                                    ?>
                                    <div class="document-item">
                                        <div class="document-info">
                                            <div class="document-icon <?php echo $type_info['color']; ?>">
                                                <?php echo $type_info['icon']; ?>
                                            </div>
                                            <div class="document-details">
                                                <div class="document-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                                <div class="document-meta">
                                                    <span class="badge-doc-type badge-<?php echo $doc['document_type']; ?>">
                                                        <?php echo $type_info['label']; ?>
                                                    </span>
                                                    <span>• <?php echo date('d/m/Y H:i', strtotime($doc['uploaded_at'])); ?></span>
                                                    <span>• <?php echo round($doc['file_size'] / 1024, 1); ?> Ko</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="document-actions">
                                            <a href="<?php echo $doc['file_path']; ?>" class="btn btn-secondary btn-icon" target="_blank">👁️ Voir</a>
                                            <a href="documents.php?action=delete&doc_id=<?php echo $doc['id']; ?>&user_id=<?php echo $user_id; ?>"
                                               class="btn btn-danger btn-icon"
                                               onclick="return confirm('Supprimer ce document ?')">🗑️ Supprimer</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($user_id && !$employee_details): ?>
                    <div class="card">
                        <div class="card-head">
                            <h3>Employé non trouvé</h3>
                        </div>
                        <div class="card-body">
                            <div class="empty-docs">
                                <p>L'employé sélectionné n'existe pas</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-head">
                            <h3>Sélectionnez un employé</h3>
                        </div>
                        <div class="card-body">
                            <div class="empty-docs">
                                <div style="font-size: 3rem;">👈</div>
                                <h3>Choisissez un employé dans la liste</h3>
                                <p>Pour voir et gérer ses documents</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    // Sélecteur de type de document
    const typeBtns = document.querySelectorAll('.type-btn');
    const docTypeInput = document.getElementById('document_type');

    typeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            typeBtns.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            docTypeInput.value = this.dataset.type;
            checkUploadReady();
        });
    });

    // Upload area
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('document_file');
    const fileInfo = document.getElementById('fileInfo');
    const uploadBtn = document.getElementById('uploadBtn');

    uploadArea.addEventListener('click', () => fileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--green)';
        uploadArea.style.background = 'var(--green-l)';
    });

    uploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--border)';
        uploadArea.style.background = 'transparent';
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--border)';
        uploadArea.style.background = 'transparent';

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileInfo(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            updateFileInfo(e.target.files[0]);
        } else {
            fileInfo.innerHTML = '';
            uploadBtn.disabled = true;
        }
    });

    function updateFileInfo(file) {
        const fileSize = (file.size / 1024).toFixed(1);
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

        if (!allowedTypes.includes(file.type)) {
            fileInfo.innerHTML = '<span style="color: var(--red);">❌ Type de fichier non autorisé</span>';
            uploadBtn.disabled = true;
        } else if (file.size > 5 * 1024 * 1024) {
            fileInfo.innerHTML = '<span style="color: var(--red);">❌ Fichier trop volumineux (max 5 Mo)</span>';
            uploadBtn.disabled = true;
        } else {
            fileInfo.innerHTML = `<span style="color: var(--green);">✅ ${file.name} (${fileSize} Ko)</span>`;
            checkUploadReady();
        }
    }

    function checkUploadReady() {
        const hasType = docTypeInput.value;
        const hasFile = fileInput.files.length > 0;
        uploadBtn.disabled = !(hasType && hasFile);
    }

    // Sélection par défaut
    if (typeBtns.length > 0 && !docTypeInput.value) {
        typeBtns[0].click();
    }
</script>
</body>
</html>