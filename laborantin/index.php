<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// ================================================================
//  index.php — Dashboard + gestion analyses laborantin
//  ✅ Recherche patient AJAX temps réel
//  ✅ Catalogue analyses par catégorie avec prix
//  ✅ Panier avec calcul automatique
//  ✅ GROUPEMENT des analyses par patient et date
//  ✅ Filtres par statut, priorité et date
//  ✅ Saisie des résultats avec unités déroulantes et valeurs de référence
//  ✅ Impression et envoi d'email des résultats
// ================================================================

require_once 'functions.php';
requireLaborantin();
include '../connection.php';

$page = $_GET['page'] ?? 'dashboard';
$test_id = (int)($_GET['id'] ?? 0);
$group_patient_id = (int)($_GET['patient_id'] ?? 0);
$group_date = $_GET['date'] ?? '';

$doctors = getDoctorsForLab($database);

if ($page === 'dashboard') {
    $stats = getLabStats($database);
    $groupedTests = getGroupedLabTests($database, []);
    $groupedTests = array_slice($groupedTests, 0, 10);
    $alerts = getLabStockItems($database, 'low');
}

// Catalogue des analyses par catégorie avec prix (DZD)
$catalogue = [
    'hématologie' => [
        'name' => 'Hématologie',
        'icon' => '🩸',
        'tests' => [
            'Hémogramme (NFS)' => 600,
            'Groupe sanguin + RAI' => 800,
            'Ferritine' => 700,
            'Fer sérique' => 600,
            'VS' => 500,
            'Frottis sanguin' => 800,
            'Réticulocytes' => 400,
            'Myélogramme' => 1500,
            'Bilharziose' => 600,
            'Électrophorèse Hb' => 1200,
        ]
    ],
    'biochimie' => [
        'name' => 'Biochimie',
        'icon' => '🧪',
        'tests' => [
            'Glycémie' => 700,
            'HbA1c' => 1200,
            'Créatinine' => 700,
            'Urée' => 600,
            'Acide urique' => 600,
            'Cholestérol total' => 700,
            'HDL / LDL' => 900,
            'Triglycérides' => 700,
            'CRP' => 800,
            'ALAT / ASAT' => 850,
            'Gamma-GT' => 750,
            'PAL' => 700,
            'Bilirubine' => 600,
            'Albumine' => 500,
            'Protéines totales' => 500,
            'Électrophorèse des prot.' => 1200,
            'Ionogramme sanguin' => 800,
            'Lactates' => 700,
            'Amylase' => 800,
            'Lipase' => 800,
            'CPK' => 700,
            'LDH' => 700,
        ]
    ],
    'endocrinologie' => [
        'name' => 'Endocrinologie',
        'icon' => '🦋',
        'tests' => [
            'TSH' => 1200,
            'T3 libre' => 1400,
            'T4 libre' => 1400,
            'PSA total' => 1300,
            'PSA libre' => 1500,
            'Bêta-HCG' => 1100,
            'Cortisol' => 1500,
            'Testostérone' => 1400,
            'Progestérone' => 1200,
            'Oestradiol' => 1200,
            'Prolactine' => 1100,
            'Insuline' => 1300,
            'Vitamine D' => 1500,
            'Vitamine B12' => 1200,
            'Folate' => 1000,
            'Aldostérone' => 1600,
            'Rénine' => 1400,
            'GH' => 1400,
            'IGF-1' => 1500,
        ]
    ],
    'coagulation' => [
        'name' => 'Coagulation',
        'icon' => '🩹',
        'tests' => [
            'TP / TCA' => 900,
            'D-Dimères' => 1200,
            'INR' => 850,
            'Fibrinogène' => 800,
            'Temps de saignement' => 500,
            'Temps de thrombine' => 700,
            'Facteur VIII' => 1200,
            'Facteur IX' => 1200,
        ]
    ],
    'sérologie' => [
        'name' => 'Sérologie',
        'icon' => '🧫',
        'tests' => [
            'VHB (Antigène HBs)' => 1200,
            'VHC (Ac anti-VHC)' => 1200,
            'VIH (ELISA)' => 1500,
            'TPHA / VDRL' => 1000,
            'Rubéole IgM/IgG' => 1100,
            'Toxoplasmose IgM/IgG' => 1100,
            'CMV IgM/IgG' => 1200,
            'HSV 1/2' => 1300,
            'SARS-CoV-2 (Covid)' => 800,
            'Dengue' => 1000,
            'Paludisme (goutte épaisse)' => 600,
            'Chikungunya' => 1000,
            'Leptospirose' => 1200,
        ]
    ],
    'urine' => [
        'name' => 'Analyse d\'urine',
        'icon' => '💧',
        'tests' => [
            'ECBU (Examen cyto-bactério)' => 800,
            'Bandelettes urinaires' => 500,
            'Protéinurie 24h' => 700,
            'Créatininurie' => 600,
            'Bence Jones' => 900,
            'Test grossesse urine' => 400,
        ]
    ],
    'marqueurs_tumoraux' => [
        'name' => 'Marqueurs tumoraux',
        'icon' => '🎯',
        'tests' => [
            'ACE' => 1400,
            'CA 125' => 1500,
            'CA 15-3' => 1500,
            'CA 19-9' => 1500,
            'AFP' => 1300,
            'NSE' => 1400,
            'CYFRA 21-1' => 1600,
        ]
    ],
    'autres' => [
        'name' => 'Autres analyses',
        'icon' => '🔬',
        'tests' => [
            'Analyse personnalisée' => 0,
        ]
    ]
];

// Unités de mesure courantes
$common_units = [
    'g/L' => 'g/L',
    'g/dL' => 'g/dL',
    'mg/L' => 'mg/L',
    'mg/dL' => 'mg/dL',
    'µg/L' => 'µg/L',
    'ng/mL' => 'ng/mL',
    'UI/L' => 'UI/L',
    'mUI/L' => 'mUI/L',
    'mmol/L' => 'mmol/L',
    'µmol/L' => 'µmol/L',
    'mg/24h' => 'mg/24h',
    'g/24h' => 'g/24h',
    '/mm³' => '/mm³',
    'x10⁹/L' => 'x10⁹/L',
    'x10¹²/L' => 'x10¹²/L',
    '%' => '%',
    'secondes' => 'secondes',
    'ratio' => 'ratio',
    'Positif/Négatif' => 'Positif/Négatif'
];

// Valeurs de référence par type d'analyse
$reference_ranges = [
    'Hémogramme (NFS)' => [
        'Homme' => 'Globules rouges: 4.5-5.9 x10¹²/L | Hémoglobine: 13.5-17.5 g/dL | Hématocrite: 40-52% | Globules blancs: 4-10 x10⁹/L | Plaquettes: 150-400 x10⁹/L',
        'Femme' => 'Globules rouges: 4.0-5.2 x10¹²/L | Hémoglobine: 12-16 g/dL | Hématocrite: 36-46% | Globules blancs: 4-10 x10⁹/L | Plaquettes: 150-400 x10⁹/L',
        'Enfant' => 'Variable selon âge - consulter courbes de croissance',
        'Personnalisé' => ''
    ],
    'Glycémie' => [
        'Homme' => 'Jeûne: 0.70-1.10 g/L (3.9-6.1 mmol/L)',
        'Femme' => 'Jeûne: 0.70-1.10 g/L (3.9-6.1 mmol/L)',
        'Enfant' => 'Jeûne: 0.60-1.00 g/L (3.3-5.6 mmol/L)',
        'Personnalisé' => ''
    ],
    'HbA1c' => [
        'Homme' => '4-6% (20-42 mmol/mol)',
        'Femme' => '4-6% (20-42 mmol/mol)',
        'Enfant' => '4-6% (20-42 mmol/mol)',
        'Personnalisé' => ''
    ],
    'Créatinine' => [
        'Homme' => '60-110 µmol/L (0.7-1.2 mg/dL)',
        'Femme' => '45-90 µmol/L (0.5-1.0 mg/dL)',
        'Enfant' => '20-60 µmol/L (selon âge)',
        'Personnalisé' => ''
    ],
    'Urée' => [
        'Homme' => '2.5-7.5 mmol/L (15-45 mg/dL)',
        'Femme' => '2.5-7.5 mmol/L (15-45 mg/dL)',
        'Enfant' => '1.5-6.0 mmol/L',
        'Personnalisé' => ''
    ],
    'Cholestérol total' => [
        'Homme' => '< 2.00 g/L (5.2 mmol/L) - Désirable',
        'Femme' => '< 2.00 g/L (5.2 mmol/L) - Désirable',
        'Enfant' => '< 1.70 g/L (4.4 mmol/L)',
        'Personnalisé' => ''
    ],
    'TSH' => [
        'Homme' => '0.4-4.0 mUI/L',
        'Femme' => '0.4-4.0 mUI/L',
        'Enfant' => '0.5-5.0 mUI/L (selon âge)',
        'Personnalisé' => ''
    ],
    'CRP' => [
        'Homme' => '< 5 mg/L',
        'Femme' => '< 5 mg/L',
        'Enfant' => '< 5 mg/L',
        'Personnalisé' => ''
    ],
    'Ferritine' => [
        'Homme' => '30-300 µg/L',
        'Femme' => '15-200 µg/L',
        'Enfant' => '7-140 µg/L (selon âge)',
        'Personnalisé' => ''
    ],
    'Vitamine D' => [
        'Homme' => 'Insuffisance: 20-30 ng/mL | Carence: <20 ng/mL | Suffisance: >30 ng/mL',
        'Femme' => 'Insuffisance: 20-30 ng/mL | Carence: <20 ng/mL | Suffisance: >30 ng/mL',
        'Enfant' => 'Insuffisance: 20-30 ng/mL | Carence: <20 ng/mL | Suffisance: >30 ng/mL',
        'Personnalisé' => ''
    ],
    'Groupe sanguin + RAI' => [
        'Homme' => 'Résultat: A, B, AB, ou O | Rhésus: Positif ou Négatif | RAI: Négatif',
        'Femme' => 'Résultat: A, B, AB, ou O | Rhésus: Positif ou Négatif | RAI: Négatif',
        'Enfant' => 'Résultat: A, B, AB, ou O | Rhésus: Positif ou Négatif',
        'Personnalisé' => ''
    ],
    'VS' => [
        'Homme' => '< 15 mm/h',
        'Femme' => '< 20 mm/h',
        'Enfant' => '< 10 mm/h',
        'Personnalisé' => ''
    ],
    'Triglycérides' => [
        'Homme' => '< 1.50 g/L (1.7 mmol/L)',
        'Femme' => '< 1.50 g/L (1.7 mmol/L)',
        'Enfant' => '< 1.00 g/L',
        'Personnalisé' => ''
    ],
    'Vitamine B12' => [
        'Homme' => '200-900 pg/mL',
        'Femme' => '200-900 pg/mL',
        'Enfant' => '200-900 pg/mL',
        'Personnalisé' => ''
    ]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Laborantin</title>
    <link rel="stylesheet" href="../receptionniste/recept.css">
    <style>
        /* =======================================================
           VARIABLES CSS (à définir dans recept.css si besoin)
           ======================================================= */
        :root {
            --green: #2c7da0;
            --green-l: #d4efe2;
            --red: #cc0000;
            --red-l: #ffe5e5;
            --amber: #ff9800;
            --amber-l: #fff3e0;
            --surface: #ffffff;
            --surf2: #f8f9fa;
            --border: #e0e0e0;
            --border-light: #eeeeee;
            --text: #2c3e50;
            --text2: #6c757d;
            --text3: #adb5bd;
            --r: 12px;
            --rs: 8px;
            --sh: 0 4px 12px rgba(0,0,0,0.1);
            --sh-lg: 0 8px 24px rgba(0,0,0,0.15);
        }

        /* =======================================================
           STYLES GÉNÉRAUX
           ======================================================= */
        * {
            transition: all 0.2s ease;
        }

        /* =======================================================
           CATALOGUE DES ANALYSES
           ======================================================= */
        .catalogue-section {
            margin-bottom: 25px;
        }
        .catalogue-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text);
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            margin-bottom: 15px;
        }
        .test-item {
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--rs);
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        .test-item:hover {
            background: var(--green-l);
            border-color: var(--green);
            transform: translateX(2px);
        }
        .test-name {
            font-size: 0.8rem;
            font-weight: 500;
        }
        .test-price {
            font-family: monospace;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--green);
            background: white;
            padding: 2px 6px;
            border-radius: 12px;
        }

        /* =======================================================
           PANIER
           ======================================================= */
        .panier {
            position: sticky;
            top: 80px;
            background: var(--surface);
            border: 2px solid var(--green);
            border-radius: var(--r);
            padding: 15px;
            margin-bottom: 20px;
        }
        .panier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border);
        }
        .panier-title {
            font-weight: 700;
            font-size: 1rem;
        }
        .panier-badge {
            background: var(--green);
            color: white;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.75rem;
        }
        .panier-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dotted var(--border);
            font-size: 0.8rem;
        }
        .panier-item-name {
            flex: 1;
        }
        .panier-item-price {
            font-family: monospace;
            color: var(--green);
            margin-right: 10px;
        }
        .panier-item-remove {
            color: var(--red);
            cursor: pointer;
            font-weight: 700;
            background: none;
            border: none;
            font-size: 1.1rem;
        }
        .panier-total {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            margin-top: 8px;
            border-top: 2px solid var(--border);
            font-weight: 800;
            font-size: 1rem;
        }
        .panier-total-price {
            font-family: monospace;
            color: var(--green);
            font-size: 1.2rem;
        }
        .empty-panier {
            text-align: center;
            padding: 20px;
            color: var(--text3);
            font-size: 0.8rem;
        }

        /* =======================================================
           RECHERCHE PATIENT
           ======================================================= */
        .search-wrapper {
            position: relative;
            width: 100%;
        }
        .patient-search-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--rs);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--sh-lg);
            display: none;
        }
        .patient-search-dropdown.show {
            display: block;
        }
        .patient-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
        }
        .patient-item:hover {
            background: var(--green-l);
        }
        .patient-name {
            font-weight: 700;
            margin-bottom: 4px;
        }
        .patient-meta {
            font-size: 0.7rem;
            color: var(--text2);
        }
        .patient-card-selected {
            background: linear-gradient(135deg, var(--green-l), #c8e6d9);
            border-radius: var(--r);
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid var(--green);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .blood-type {
            background: var(--red-l);
            color: var(--red);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .allergy-badge {
            background: var(--amber-l);
            color: var(--amber);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
        }

        /* =======================================================
           MISE EN PAGE
           ======================================================= */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
        }
        .prescription-notes {
            margin-top: 15px;
        }
        .btn-icon {
            padding: 4px 8px;
            font-size: 0.7rem;
        }

        /* =======================================================
           GROUPEMENT DES ANALYSES
           ======================================================= */
        .group-card {
            background: var(--surface);
            border-radius: var(--r);
            margin-bottom: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .group-card:hover {
            box-shadow: var(--sh);
        }
        .group-header {
            padding: 15px 20px;
            background: var(--surf2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
            border-bottom: 2px solid var(--green);
        }
        .group-header.urgent {
            background: var(--red-l);
            border-bottom-color: var(--red);
        }
        .patient-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .patient-avatar {
            width: 50px;
            height: 50px;
            background: var(--green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .patient-details h4 {
            margin: 0 0 4px 0;
            font-size: 1rem;
        }
        .patient-details p {
            margin: 0;
            font-size: 0.7rem;
            color: var(--text2);
        }
        .group-stats {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .stat-badge {
            background: white;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .group-tests-list {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
        }
        .group-test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-light);
        }
        .group-test-item:last-child {
            border-bottom: none;
        }
        .test-status-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .group-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            border-top: 1px solid var(--border);
            padding-top: 15px;
            flex-wrap: wrap;
        }

        /* =======================================================
           FILTRES
           ======================================================= */
        .filter-container {
            padding: 16px;
            background: var(--surf2);
            border-bottom: 1px solid var(--border);
        }
        .status-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 6px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .filter-btn:hover {
            transform: translateY(-2px);
        }
        .filter-count {
            background: rgba(0,0,0,0.1);
            padding: 0 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        .active-filters {
            padding: 8px 16px;
            background: var(--green-l);
            font-size: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .active-filters .badge {
            display: inline-block;
            background: white;
            padding: 2px 8px;
            border-radius: 12px;
            margin: 0 5px;
            font-size: 0.7rem;
        }
        .advanced-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .advanced-filters .form-group label {
            font-weight: 600;
            color: var(--text2);
            font-size: 0.7rem;
            display: block;
            margin-bottom: 3px;
        }

        /* =======================================================
           SAISIE DES RÉSULTATS
           ======================================================= */
        .result-block {
            border: 1px solid var(--border);
            border-radius: var(--rs);
            padding: 20px;
            margin-bottom: 25px;
            background: var(--surface);
        }
        .result-block:hover {
            box-shadow: var(--sh);
        }
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--green);
        }
        .result-header h4 {
            margin: 0;
            font-size: 1.1rem;
        }
        .form-row-2cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        .ref-values-panel {
            background: var(--surf2);
            border-radius: var(--rs);
            padding: 12px;
            margin-top: 10px;
            border-left: 3px solid var(--green);
        }
        .ref-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .ref-btn {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.7rem;
            cursor: pointer;
        }
        .ref-btn:hover {
            background: var(--green);
            color: white;
            border-color: var(--green);
        }
        .ref-btn.active {
            background: var(--green);
            color: white;
            border-color: var(--green);
        }
        .ref-custom-input {
            margin-top: 10px;
            display: none;
        }
        .ref-custom-input.show {
            display: block;
        }
        .ref-value-display {
            font-size: 0.8rem;
            padding: 8px;
            background: white;
            border-radius: var(--rs);
            margin-top: 8px;
        }

        /* =======================================================
           MODALS
           ======================================================= */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-box {
            background: white;
            border-radius: var(--r);
            padding: 25px;
            max-width: 700px;
            width: 90%;
            box-shadow: var(--sh-lg);
        }
        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--green);
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .email-preview-content {
            max-height: 500px;
            overflow-y: auto;
            background: white;
            padding: 20px;
            border-radius: var(--rs);
        }
        .print-area {
            display: none;
        }

        /* =======================================================
           RESPONSIVE
           ======================================================= */
        @media (max-width: 900px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
            .panier {
                position: static;
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .form-row-2cols {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .status-filters {
                gap: 8px;
            }
            .filter-btn {
                padding: 4px 10px;
                font-size: 0.75rem;
            }
            .group-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .group-stats {
                width: 100%;
                justify-content: flex-start;
            }
            .modal-box {
                width: 95%;
                padding: 15px;
            }
        }

        @media print {
            body > *:not(.print-area) {
                display: none;
            }
            .print-area {
                display: block;
            }
        }
    </style>
</head>
<body>
<?php include 'lab_menu.php'; ?>
<div class="main">
    <div class="topbar">
        <span class="topbar-title">🔬 <?php echo match($page) {
                'dashboard'=>'Tableau de bord',
                'tests'=>'Liste des analyses',
                'create'=>'Nouvelle analyse',
                'view'=>'Détail analyse',
                'batch_result'=>'Saisie des résultats',
                default=>'Laboratoire'
            }; ?></span>
        <div class="topbar-right">
            <span class="date-tag"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    <div class="page-body">

        <?php if ($page === 'dashboard'): ?>
            <!-- ==================== DASHBOARD ==================== -->
            <div class="stats">
                <div class="stat">
                    <div class="stat-ico a">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <div class="stat-num"><?php echo $stats['pending']; ?></div>
                        <div class="stat-lbl">En attente</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat-ico b">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <div class="stat-num"><?php echo $stats['in_progress']; ?></div>
                        <div class="stat-lbl">En cours</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat-ico r">
                        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    </div>
                    <div>
                        <div class="stat-num"><?php echo $stats['urgent']; ?></div>
                        <div class="stat-lbl">Urgentes</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat-ico g">
                        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div>
                        <div class="stat-num"><?php echo $stats['completed_today']; ?></div>
                        <div class="stat-lbl">Aujourd'hui</div>
                    </div>
                </div>
            </div>

            <?php if (!empty($alerts)): ?>
                <div class="alert alert-warning">
                    ⚠️ <strong>Alertes stock faible :</strong>
                    <?php echo implode(', ', array_map(fn($a)=>$a['item_name'].' ('.$a['quantity'].' restants)', array_slice($alerts,0,5))); ?>
                    <a href="stock.php?alerts=1" style="float:right">Gérer →</a>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-head">
                    <h3>📋 Analyses par patient et date</h3>
                    <a href="index.php?page=tests" class="btn btn-secondary btn-sm">Voir tout →</a>
                </div>
                <?php if (empty($groupedTests)): ?>
                    <div class="empty"><p>Aucune analyse récente.</p></div>
                <?php else: ?>
                    <?php foreach ($groupedTests as $group):
                        $test_names = explode('|||', $group['test_names']);
                        $test_statuses = explode('|||', $group['test_statuses']);
                        $has_urgent = $group['has_urgent'];
                        $pending_count = count(array_filter($test_statuses, fn($s) => $s === 'pending'));
                        $in_progress_count = count(array_filter($test_statuses, fn($s) => $s === 'in_progress'));
                        $completed_count = count(array_filter($test_statuses, fn($s) => $s === 'completed'));
                        ?>
                        <div class="group-card">
                            <div class="group-header <?php echo $has_urgent ? 'urgent' : ''; ?>" onclick="toggleGroup(this)">
                                <div class="patient-info">
                                    <div class="patient-avatar"><?php echo strtoupper(substr($group['patient_name'], 0, 2)); ?></div>
                                    <div class="patient-details">
                                        <h4><?php echo htmlspecialchars($group['patient_name']); ?></h4>
                                        <p>📞 <?php echo htmlspecialchars($group['patient_phone']); ?> | 🆔 <?php echo htmlspecialchars($group['uhid']); ?> | 📅 <?php echo date('d/m/Y', strtotime($group['test_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="group-stats">
                                    <span class="stat-badge">📊 <?php echo $group['tests_count']; ?> analyse(s)</span>
                                    <?php if ($pending_count > 0): ?>
                                        <span class="stat-badge" style="background:#FFE5E5;color:#cc0000">⏳ <?php echo $pending_count; ?> en attente</span>
                                    <?php endif; ?>
                                    <?php if ($in_progress_count > 0): ?>
                                        <span class="stat-badge" style="background:#FFF3E0;color:#ff9800">🔄 <?php echo $in_progress_count; ?> en cours</span>
                                    <?php endif; ?>
                                    <?php if ($completed_count > 0): ?>
                                        <span class="stat-badge" style="background:#E8F5E9;color:#4caf50">✅ <?php echo $completed_count; ?> terminées</span>
                                    <?php endif; ?>
                                    <?php if ($has_urgent): ?>
                                        <span class="stat-badge" style="background:#ff9800;color:white">⚡ Urgent</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="group-tests-list" style="display: none;">
                                <?php foreach ($test_names as $idx => $name): ?>
                                    <div class="group-test-item">
                                        <span>🔬 <?php echo htmlspecialchars($name); ?></span>
                                        <span class="test-status-badge badge <?php echo LAB_STATUS_BADGE[$test_statuses[$idx]] ?? 'badge-pending'; ?>">
                                            <?php echo LAB_STATUS_LABELS[$test_statuses[$idx]] ?? 'En attente'; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="group-actions">
                                    <?php if ($pending_count > 0 || $in_progress_count > 0): ?>
                                        <a href="index.php?page=batch_result&patient_id=<?php echo $group['patient_id']; ?>&date=<?php echo urlencode($group['test_date']); ?>" class="btn btn-primary btn-sm">
                                            📝 Saisir les résultats
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($completed_count > 0): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="viewResults(<?php echo $group['patient_id']; ?>, '<?php echo $group['test_date']; ?>')">
                                            📄 Voir les résultats
                                        </button>
                                        <button type="button" class="btn btn-blue btn-sm" onclick="printResults(<?php echo $group['patient_id']; ?>, '<?php echo $group['test_date']; ?>')">
                                            🖨️ Imprimer
                                        </button>
                                        <button type="button" class="btn btn-green btn-sm" onclick="sendResultsEmail(<?php echo $group['patient_id']; ?>, '<?php echo $group['test_date']; ?>', '<?php echo htmlspecialchars($group['patient_email']); ?>', '<?php echo htmlspecialchars($group['patient_name']); ?>')">
                                            📧 Envoyer par email
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'tests'): ?>
            <!-- ==================== LISTE DES ANALYSES GROUPÉES AVEC FILTRES ==================== -->
            <?php
            $filters = [
                'status' => $_GET['status'] ?? '',
                'priority' => $_GET['priority'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? ''
            ];

            $groupedTests = getGroupedLabTests($database, $filters);

            // Compter les analyses par statut
            $all_tests = getGroupedLabTests($database, []);
            $status_counts = ['all' => count($all_tests), 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
            foreach ($all_tests as $group) {
                $test_statuses = explode('|||', $group['test_statuses']);
                foreach ($test_statuses as $status) {
                    if (isset($status_counts[$status])) $status_counts[$status]++;
                }
            }
            ?>
            <div class="card">
                <div class="card-head">
                    <h3>📋 Toutes les analyses (par patient)</h3>
                    <a href="index.php?page=create" class="btn btn-primary btn-sm">+ Nouvelle analyse</a>
                </div>

                <!-- Filtres -->
                <div class="filter-container">
                    <div class="status-filters">
                        <a href="?page=tests&status=" class="filter-btn" style="background: <?php echo empty($filters['status']) ? 'var(--green)' : 'var(--surface)'; ?>; color: <?php echo empty($filters['status']) ? 'white' : 'var(--text)'; ?>;">
                            📊 Toutes <span class="filter-count"><?php echo $status_counts['all']; ?></span>
                        </a>
                        <a href="?page=tests&status=pending" class="filter-btn" style="background: <?php echo $filters['status'] === 'pending' ? '#FFE5E5' : 'var(--surface)'; ?>; color: <?php echo $filters['status'] === 'pending' ? '#cc0000' : 'var(--text)'; ?>;">
                            ⏳ En attente <span class="filter-count"><?php echo $status_counts['pending']; ?></span>
                        </a>
                        <a href="?page=tests&status=in_progress" class="filter-btn" style="background: <?php echo $filters['status'] === 'in_progress' ? '#FFF3E0' : 'var(--surface)'; ?>; color: <?php echo $filters['status'] === 'in_progress' ? '#ff9800' : 'var(--text)'; ?>;">
                            🔄 En cours <span class="filter-count"><?php echo $status_counts['in_progress']; ?></span>
                        </a>
                        <a href="?page=tests&status=completed" class="filter-btn" style="background: <?php echo $filters['status'] === 'completed' ? '#E8F5E9' : 'var(--surface)'; ?>; color: <?php echo $filters['status'] === 'completed' ? '#4caf50' : 'var(--text)'; ?>;">
                            ✅ Terminées <span class="filter-count"><?php echo $status_counts['completed']; ?></span>
                        </a>
                        <a href="?page=tests&status=cancelled" class="filter-btn" style="background: <?php echo $filters['status'] === 'cancelled' ? '#E0E0E0' : 'var(--surface)'; ?>; color: <?php echo $filters['status'] === 'cancelled' ? '#666' : 'var(--text)'; ?>;">
                            ❌ Annulées <span class="filter-count"><?php echo $status_counts['cancelled']; ?></span>
                        </a>
                    </div>

                    <form method="GET" class="advanced-filters" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                        <input type="hidden" name="page" value="tests">
                        <input type="hidden" name="status" value="<?php echo $filters['status']; ?>">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Priorité</label>
                            <select class="input" name="priority" style="width: 120px; padding: 6px 10px;">
                                <option value="">Toutes</option>
                                <?php foreach(LAB_PRIORITY_LABELS as $k=>$l): ?>
                                    <option value="<?php echo $k; ?>" <?php echo ($filters['priority'] == $k) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Date du</label>
                            <input class="input" type="date" name="date_from" value="<?php echo $filters['date_from']; ?>" style="width: 130px; padding: 6px 10px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Date au</label>
                            <input class="input" type="date" name="date_to" value="<?php echo $filters['date_to']; ?>" style="width: 130px; padding: 6px 10px;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="padding: 6px 15px;">🔍 Filtrer</button>
                        <a href="index.php?page=tests" class="btn btn-secondary btn-sm" style="padding: 6px 15px;">🔄 Réinitialiser</a>
                        <a href="actions.php?action=export_csv&status=<?php echo $filters['status']; ?>" class="btn btn-secondary btn-sm" style="padding: 6px 15px;">📥 Exporter CSV</a>
                    </form>
                </div>

                <?php if (!empty($filters['status']) || !empty($filters['priority']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                    <div class="active-filters">
                        🔍 Filtres actifs :
                        <?php if (!empty($filters['status'])): ?>
                            <span class="badge">Statut: <?php echo LAB_STATUS_LABELS[$filters['status']]; ?></span>
                        <?php endif; ?>
                        <?php if (!empty($filters['priority'])): ?>
                            <span class="badge">Priorité: <?php echo LAB_PRIORITY_LABELS[$filters['priority']]; ?></span>
                        <?php endif; ?>
                        <?php if (!empty($filters['date_from'])): ?>
                            <span class="badge">Du: <?php echo date('d/m/Y', strtotime($filters['date_from'])); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($filters['date_to'])): ?>
                            <span class="badge">Au: <?php echo date('d/m/Y', strtotime($filters['date_to'])); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="grouped-results">
                    <?php if (empty($groupedTests)): ?>
                        <div class="empty" style="padding: 40px; text-align: center;">
                            <p>📭 Aucune analyse trouvée avec ces filtres.</p>
                            <a href="index.php?page=tests" class="btn btn-secondary btn-sm">Voir toutes les analyses</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($groupedTests as $group):
                            $test_names = explode('|||', $group['test_names']);
                            $test_statuses = explode('|||', $group['test_statuses']);
                            $has_urgent = $group['has_urgent'];
                            $pending_count = count(array_filter($test_statuses, fn($s) => $s === 'pending'));
                            $in_progress_count = count(array_filter($test_statuses, fn($s) => $s === 'in_progress'));
                            $completed_count = count(array_filter($test_statuses, fn($s) => $s === 'completed'));
                            ?>
                            <div class="group-card">
                                <div class="group-header <?php echo $has_urgent ? 'urgent' : ''; ?>" onclick="toggleGroup(this)">
                                    <div class="patient-info">
                                        <div class="patient-avatar"><?php echo strtoupper(substr($group['patient_name'], 0, 2)); ?></div>
                                        <div class="patient-details">
                                            <h4><?php echo htmlspecialchars($group['patient_name']); ?></h4>
                                            <p>📞 <?php echo htmlspecialchars($group['patient_phone']); ?> | 🆔 <?php echo htmlspecialchars($group['uhid']); ?> | 📅 <?php echo date('d/m/Y', strtotime($group['test_date'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="group-stats">
                                        <span class="stat-badge">📊 <?php echo $group['tests_count']; ?> analyse(s)</span>
                                        <?php if ($pending_count > 0): ?>
                                            <span class="stat-badge" style="background:#FFE5E5;color:#cc0000">⏳ <?php echo $pending_count; ?> en attente</span>
                                        <?php endif; ?>
                                        <?php if ($in_progress_count > 0): ?>
                                            <span class="stat-badge" style="background:#FFF3E0;color:#ff9800">🔄 <?php echo $in_progress_count; ?> en cours</span>
                                        <?php endif; ?>
                                        <?php if ($completed_count > 0): ?>
                                            <span class="stat-badge" style="background:#E8F5E9;color:#4caf50">✅ <?php echo $completed_count; ?> terminées</span>
                                        <?php endif; ?>
                                        <?php if ($has_urgent): ?>
                                            <span class="stat-badge" style="background:#ff9800;color:white">⚡ Urgent</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="group-tests-list" style="display: none;">
                                    <?php foreach ($test_names as $idx => $name): ?>
                                        <div class="group-test-item">
                                            <span>🔬 <?php echo htmlspecialchars($name); ?></span>
                                            <span class="test-status-badge badge <?php echo LAB_STATUS_BADGE[$test_statuses[$idx]] ?? 'badge-pending'; ?>">
                                                <?php echo LAB_STATUS_LABELS[$test_statuses[$idx]] ?? 'En attente'; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="group-actions">
                                        <?php if ($pending_count > 0 || $in_progress_count > 0): ?>
                                            <a href="index.php?page=batch_result&patient_id=<?php echo $group['patient_id']; ?>&date=<?php echo urlencode($group['test_date']); ?>" class="btn btn-primary btn-sm">
                                                📝 Saisir les résultats
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($completed_count > 0): ?>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="viewResults(<?php echo $group['patient_id']; ?>, '<?php echo $group['test_date']; ?>')">
                                                📄 Voir les résultats
                                            </button>
                                            <button type="button" class="btn btn-blue btn-sm" onclick="printResults(<?php echo $group['patient_id']; ?>, '<?php echo $group['test_date']; ?>')">
                                                🖨️ Imprimer
                                            </button>
                                            <button type="button" class="btn btn-green btn-sm" onclick="sendResultsEmail(<?php echo $group['patient_id']; ?>, '<?php echo $group['test_date']; ?>', '<?php echo htmlspecialchars($group['patient_email']); ?>', '<?php echo htmlspecialchars($group['patient_name']); ?>')">
                                                📧 Envoyer par email
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($page === 'create'): ?>
            <!-- ==================== CRÉATION D'ANALYSE ==================== -->
            <div class="two-columns">
                <div>
                    <!-- Recherche patient -->
                    <div class="card" style="margin-bottom:20px">
                        <div class="card-head"><h3>👤 Patient</h3></div>
                        <div class="card-body">
                            <div class="search-wrapper">
                                <input class="input" type="text" id="patientSearch" placeholder="Rechercher par nom, téléphone ou UHID..." autocomplete="off" style="width:100%">
                                <div id="patientDropdown" class="patient-search-dropdown"></div>
                            </div>
                            <input type="hidden" id="selectedPatientId">
                            <div id="selectedPatientCard" style="display:none" class="patient-card-selected">
                                <div style="display:flex; justify-content:space-between; align-items:center">
                                    <div>
                                        <strong id="selectedPatientName" style="font-size:1rem"></strong>
                                        <div class="patient-meta">📞 <span id="selectedPatientPhone"></span> | 🆔 <span id="selectedPatientUhid"></span></div>
                                        <div style="margin-top:5px"><span id="selectedPatientBlood" class="blood-type"></span><span id="selectedPatientAllergy" class="allergy-badge" style="margin-left:5px"></span></div>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-icon" onclick="clearSelectedPatient()">Changer</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sélection médecin -->
                    <div class="card" style="margin-bottom:20px">
                        <div class="card-head"><h3>👨‍⚕️ Médecin prescripteur</h3></div>
                        <div class="card-body">
                            <select class="input" id="doctorSelect" style="width:100%">
                                <option value="">— Sélectionner un médecin —</option>
                                <?php foreach($doctors as $d): ?>
                                    <option value="<?php echo $d['id']; ?>">Dr. <?php echo htmlspecialchars($d['name']); ?> <?php echo $d['specialty'] ? " (" . htmlspecialchars($d['specialty']) . ")" : ''; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Catalogue -->
                    <div class="card">
                        <div class="card-head"><h3>📚 Catalogue des analyses</h3></div>
                        <div class="card-body">
                            <?php foreach ($catalogue as $cat_key => $cat): ?>
                                <div class="catalogue-section">
                                    <div class="catalogue-title"><span><?php echo $cat['icon']; ?></span><span><?php echo $cat['name']; ?></span></div>
                                    <div class="test-grid">
                                        <?php foreach ($cat['tests'] as $test_name => $price): ?>
                                            <div class="test-item" onclick="addToCart('<?php echo addslashes($test_name); ?>', <?php echo $price; ?>, '<?php echo $cat_key; ?>')">
                                                <span class="test-name"><?php echo htmlspecialchars($test_name); ?></span>
                                                <?php if ($price > 0): ?>
                                                    <span class="test-price"><?php echo number_format($price, 0, ',', ' '); ?> DZD</span>
                                                <?php else: ?>
                                                    <span class="test-price">Prix libre</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Panier -->
                <div>
                    <div class="panier">
                        <div class="panier-header"><span class="panier-title">🛒 Panier d'analyses</span><span class="panier-badge" id="cartCount">0</span></div>
                        <div id="cartItems"><div class="empty-panier">Aucune analyse sélectionnée</div></div>
                        <div class="panier-total"><span>Total</span><span class="panier-total-price" id="cartTotal">0 DZD</span></div>
                        <div class="prescription-notes">
                            <label style="font-size:0.75rem; font-weight:600; color:var(--text2)">📝 Notes globales</label>
                            <textarea id="globalNotes" class="input" rows="3" placeholder="Jeûne, conditions de prélèvement, informations particulières..." style="margin-top:5px; font-size:0.8rem"></textarea>
                        </div>
                        <button id="submitBtn" class="btn btn-primary" style="width:100%; margin-top:20px" onclick="submitAnalyses()" disabled>✅ Créer l'analyse (0)</button>
                    </div>
                </div>
            </div>

            <form id="submitForm" method="POST" action="actions.php" style="display:none">
                <input type="hidden" name="action" value="create_multiple_tests">
                <input type="hidden" name="patient_id" id="formPatientId">
                <input type="hidden" name="doctor_id" id="formDoctorId">
                <input type="hidden" name="tests_json" id="formTestsJson">
                <input type="hidden" name="notes" id="formNotes">
            </form>

        <?php elseif ($page === 'batch_result'): ?>
            <!-- ==================== SAISIE DES RÉSULTATS EN LOT ==================== -->
            <?php
            $patient_id = (int)($_GET['patient_id'] ?? 0);
            $test_date = $_GET['date'] ?? '';
            if (!$patient_id || !$test_date) {
                header('Location: index.php?page=tests');
                exit;
            }
            $tests = getGroupDetails($database, $patient_id, $test_date);
            $patient = $tests[0] ?? null;
            ?>
            <div class="card">
                <div class="card-head">
                    <h3>📝 Saisie des résultats</h3>
                    <div>
                        <span class="badge badge-completed">Patient: <?php echo htmlspecialchars($patient['patient_name'] ?? ''); ?></span>
                        <span class="badge badge-inprogress">Date: <?php echo date('d/m/Y', strtotime($test_date)); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="batch_actions.php" id="batchResultForm">
                        <input type="hidden" name="action" value="save_batch_results">
                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                        <input type="hidden" name="test_date" value="<?php echo $test_date; ?>">

                        <?php foreach ($tests as $test):
                            $test_name = $test['test_name'];
                            $has_refs = isset($reference_ranges[$test_name]);
                            ?>
                            <div class="result-block">
                                <div class="result-header">
                                    <h4>🔬 <?php echo htmlspecialchars($test_name); ?></h4>
                                    <span class="badge <?php echo LAB_STATUS_BADGE[$test['status']]; ?>"><?php echo LAB_STATUS_LABELS[$test['status']]; ?></span>
                                </div>
                                <input type="hidden" name="test_ids[]" value="<?php echo $test['id']; ?>">
                                <input type="hidden" name="test_names[<?php echo $test['id']; ?>]" value="<?php echo htmlspecialchars($test_name); ?>">

                                <div class="form-group">
                                    <label>Résultat *</label>
                                    <textarea class="input" name="results[<?php echo $test['id']; ?>]" rows="3" required <?php echo $test['status'] === 'completed' ? 'disabled' : ''; ?> placeholder="Saisir le résultat ici..."><?php echo htmlspecialchars($test['result'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-row-2cols">
                                    <div class="form-group">
                                        <label>Unité de mesure</label>
                                        <select class="input unit-select" name="units[<?php echo $test['id']; ?>]" <?php echo $test['status'] === 'completed' ? 'disabled' : ''; ?>>
                                            <option value="">-- Sélectionner --</option>
                                            <?php foreach ($common_units as $value => $label): ?>
                                                <option value="<?php echo $value; ?>" <?php echo ($test['unit_measure'] ?? '') == $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                            <option value="autre">📝 Autre</option>
                                        </select>
                                        <input type="text" class="input" name="units_custom[<?php echo $test['id']; ?>]" placeholder="Unité personnalisée" style="margin-top:5px; display:none;">
                                    </div>
                                    <div class="form-group">
                                        <label><input type="checkbox" name="critical[<?php echo $test['id']; ?>]" value="1" <?php echo ($test['is_critical'] ?? 0) ? 'checked' : ''; ?>> ⚠️ Critique</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>📊 Valeurs de référence</label>
                                    <?php if ($has_refs): ?>
                                        <div class="ref-values-panel">
                                            <div class="ref-buttons">
                                                <button type="button" class="ref-btn" data-test="<?php echo $test['id']; ?>" data-type="Homme">👨 Homme</button>
                                                <button type="button" class="ref-btn" data-test="<?php echo $test['id']; ?>" data-type="Femme">👩 Femme</button>
                                                <button type="button" class="ref-btn" data-test="<?php echo $test['id']; ?>" data-type="Enfant">👶 Enfant</button>
                                                <button type="button" class="ref-btn" data-test="<?php echo $test['id']; ?>" data-type="Personnalisé">✏️ Personnalisé</button>
                                            </div>
                                            <div id="ref_display_<?php echo $test['id']; ?>" class="ref-value-display"><?php echo !empty($test['reference_range']) ? htmlspecialchars($test['reference_range']) : 'Sélectionnez un profil'; ?></div>
                                            <input type="hidden" name="ref_ranges[<?php echo $test['id']; ?>]" id="ref_range_<?php echo $test['id']; ?>" value="<?php echo htmlspecialchars($test['reference_range'] ?? ''); ?>">
                                            <div id="ref_custom_<?php echo $test['id']; ?>" class="ref-custom-input">
                                                <input type="text" class="input" placeholder="Saisir les valeurs de référence personnalisées...">
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <input type="text" class="input" name="ref_ranges[<?php echo $test['id']; ?>]" placeholder="Ex: 4.5-11.0 g/L" value="<?php echo htmlspecialchars($test['reference_range'] ?? ''); ?>">
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label>📝 Notes</label>
                                    <input type="text" class="input" name="notes[<?php echo $test['id']; ?>]" placeholder="Informations complémentaires..." value="<?php echo htmlspecialchars($test['notes'] ?? ''); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="form-group" style="margin-top:20px; padding:15px; background:var(--surf2); border-radius:var(--rs);">
                            <label><input type="checkbox" name="send_email" value="1" checked> 📧 Envoyer les résultats par email</label>
                        </div>

                        <div class="form-actions" style="display:flex; gap:15px; margin-top:20px;">
                            <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer tous les résultats</button>
                            <a href="index.php?page=tests" class="btn btn-secondary btn-lg">← Retour</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'view' && $test_id): ?>
            <!-- ==================== DÉTAIL ANALYSE ==================== -->
            <?php $test = getLabTestById($database, $test_id); if (!$test): ?>
                <div class="alert alert-error">Analyse introuvable</div>
            <?php else: ?>
                <div class="test-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap">
                        <div>
                            <strong>🔬 <?php echo htmlspecialchars($test['test_name']); ?></strong><br>
                            <span style="font-size:.75rem; color:var(--text2)">Patient: <?php echo htmlspecialchars($test['patient_name']); ?> | Médecin: Dr. <?php echo htmlspecialchars($test['doctor_name'] ?? '—'); ?></span>
                        </div>
                        <div>
                            <span class="badge <?php echo LAB_STATUS_BADGE[$test['status']]; ?>"><?php echo LAB_STATUS_LABELS[$test['status']]; ?></span>
                            <?php if($test['priority']=='urgent'): ?><span class="badge badge-urgent">⚡ Urgent</span><?php endif; ?>
                            <?php if($test['is_critical']): ?><span class="badge badge-cancelled" style="background:var(--red-l);color:var(--red)">⚠️ Critique</span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($test['status'] !== 'completed'): ?>
                    <div style="display:flex; gap:10px; margin:15px 0; flex-wrap:wrap">
                        <form method="POST" action="actions.php">
                            <input type="hidden" name="action" value="update_status"><input type="hidden" name="test_id" value="<?php echo $test_id; ?>"><input type="hidden" name="status" value="in_progress">
                            <button type="submit" class="btn btn-blue btn-sm">▶️ Démarrer</button>
                        </form>
                        <form method="POST" action="actions.php">
                            <input type="hidden" name="action" value="update_status"><input type="hidden" name="test_id" value="<?php echo $test_id; ?>"><input type="hidden" name="status" value="pending">
                            <button type="submit" class="btn btn-secondary btn-sm">⏸ En attente</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="lab-result-form">
                    <h4>📝 Saisir les résultats</h4>
                    <form method="POST" action="actions.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_result">
                        <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
                        <div class="form-group"><label>Résultat *</label><textarea class="input" name="result" rows="4" required><?php echo htmlspecialchars($test['result'] ?? ''); ?></textarea></div>
                        <div class="form-row-2cols">
                            <div class="form-group"><label>Unité</label><select class="input" name="unit_measure"><option value="">-- Sélectionner --</option><?php foreach($common_units as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                            <div class="form-group"><label><input type="checkbox" name="is_critical" value="1"> ⚠️ Critique</label></div>
                        </div>
                        <div class="form-group"><label>Valeurs de référence</label><input class="input" type="text" name="reference_range" placeholder="Ex: 4.5-11.0 g/L"></div>
                        <div class="form-group"><label>Fichier</label><input class="input" type="file" name="result_file" accept=".pdf,.jpg,.png"></div>
                        <div class="form-group"><label><input type="checkbox" name="send_email" value="1" checked> 📧 Envoyer au patient</label></div>
                        <div class="form-group"><label>Notes</label><input class="input" type="text" name="notes"></div>
                        <button type="submit" class="btn btn-primary">✅ Valider</button>
                    </form>
                </div>

                <?php if ($test['result']): ?>
                    <div class="card" style="margin-top:20px">
                        <div class="card-head"><h3>📄 Résultat final</h3></div>
                        <div class="card-body">
                            <p><strong>Résultat :</strong> <?php echo nl2br(htmlspecialchars($test['result'])); ?></p>
                            <?php if ($test['unit_measure']): ?><p><strong>Unité :</strong> <?php echo htmlspecialchars($test['unit_measure']); ?></p><?php endif; ?>
                            <?php if ($test['reference_range']): ?><p><strong>Référence :</strong> <?php echo htmlspecialchars($test['reference_range']); ?></p><?php endif; ?>
                            <p><strong>Date :</strong> <?php echo date('d/m/Y H:i', strtotime($test['result_date'])); ?></p>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="viewSingleResult(<?php echo $test_id; ?>)">📄 Voir</button>
                            <button type="button" class="btn btn-blue btn-sm" onclick="printSingleResult(<?php echo $test_id; ?>)">🖨️ Imprimer</button>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modals -->
<div id="resultsModal" class="modal-overlay" style="display:none">
    <div class="modal-box email-preview-modal">
        <div class="modal-title">📄 Résultats d'analyses</div>
        <div id="resultsModalContent" class="email-preview-content"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Fermer</button>
            <button type="button" class="btn btn-primary" onclick="printModalContent()">🖨️ Imprimer</button>
        </div>
    </div>
</div>

<div id="emailModal" class="modal-overlay" style="display:none">
    <div class="modal-box email-preview-modal">
        <div class="modal-title">📧 Email au patient</div>
        <div id="emailPreviewContent" class="email-preview-content"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="sendConfirmedEmail()">✅ Confirmer l'envoi</button>
        </div>
    </div>
</div>

<div id="printArea" class="print-area"></div>

<script>
    // ==================== VARIABLES ====================
    let cart = [];
    let selectedPatientId = null;
    let selectedDoctorId = null;
    let pendingEmailData = null;

    // ==================== TOGGLE GROUP ====================
    function toggleGroup(element) {
        const testsList = element.nextElementSibling;
        if (testsList) testsList.style.display = testsList.style.display === 'block' ? 'none' : 'block';
    }

    // ==================== RECHERCHE PATIENT ====================
    const patientSearch = document.getElementById('patientSearch');
    const patientDropdown = document.getElementById('patientDropdown');
    const selectedPatientCard = document.getElementById('selectedPatientCard');

    if (patientSearch) {
        let searchTimer;
        patientSearch.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 2) { patientDropdown.classList.remove('show'); return; }
            searchTimer = setTimeout(() => {
                fetch(`ajax.php?action=search_patient&q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.length) { patientDropdown.classList.remove('show'); return; }
                        patientDropdown.innerHTML = data.map(p => `<div class="patient-item" onclick="selectPatient(${p.id}, '${escapeHtml(p.full_name)}', '${escapeHtml(p.phone)}', '${escapeHtml(p.uhid)}', '${escapeHtml(p.blood_type || '')}', '${escapeHtml(p.allergies || '')}')"><div class="patient-name">${escapeHtml(p.full_name)}</div><div class="patient-meta">📞 ${escapeHtml(p.phone)} | 🆔 ${escapeHtml(p.uhid)}${p.blood_type ? ` | 🩸 ${escapeHtml(p.blood_type)}` : ''}</div></div>`).join('');
                        patientDropdown.classList.add('show');
                    }).catch(() => {});
            }, 300);
        });
        document.addEventListener('click', e => { if (patientSearch && !patientSearch.contains(e.target) && patientDropdown && !patientDropdown.contains(e.target)) patientDropdown.classList.remove('show'); });
    }

    function selectPatient(id, name, phone, uhid, bloodType, allergies) {
        selectedPatientId = id;
        document.getElementById('selectedPatientId').value = id;
        document.getElementById('selectedPatientName').innerHTML = name;
        document.getElementById('selectedPatientPhone').innerHTML = phone;
        document.getElementById('selectedPatientUhid').innerHTML = uhid;
        document.getElementById('selectedPatientBlood').innerHTML = bloodType ? `🩸 ${bloodType}` : '';
        document.getElementById('selectedPatientAllergy').innerHTML = allergies ? `⚠️ ${allergies.substring(0, 30)}` : '';
        selectedPatientCard.style.display = 'block';
        patientSearch.value = name;
        patientDropdown.classList.remove('show');
        updateSubmitButton();
    }

    function clearSelectedPatient() {
        selectedPatientId = null;
        document.getElementById('selectedPatientId').value = '';
        selectedPatientCard.style.display = 'none';
        patientSearch.value = '';
        updateSubmitButton();
    }

    // ==================== PANIER ====================
    function addToCart(testName, price, category) {
        if (cart.find(item => item.name === testName)) { showTemporaryMessage('⚠️ Déjà dans le panier', 'warning'); return; }
        cart.push({ name: testName, price: price, category: category });
        renderCart();
        updateSubmitButton();
        showTemporaryMessage(`✓ ${testName} ajouté`, 'success');
    }

    function removeFromCart(index) {
        const removed = cart[index];
        cart.splice(index, 1);
        renderCart();
        updateSubmitButton();
        showTemporaryMessage(`✗ ${removed.name} retiré`, 'info');
    }

    function renderCart() {
        const container = document.getElementById('cartItems');
        const countSpan = document.getElementById('cartCount');
        const totalSpan = document.getElementById('cartTotal');
        let total = 0;
        if (cart.length === 0) {
            container.innerHTML = '<div class="empty-panier">Aucune analyse sélectionnée</div>';
            countSpan.textContent = '0';
            totalSpan.textContent = '0 DZD';
            return;
        }
        let html = '';
        cart.forEach((item, index) => {
            total += item.price;
            html += `<div class="panier-item"><span class="panier-item-name">${escapeHtml(item.name)}</span><span class="panier-item-price">${item.price === 0 ? 'Prix libre' : item.price.toLocaleString('fr-DZ') + ' DZD'}</span><button class="panier-item-remove" onclick="removeFromCart(${index})">✕</button></div>`;
        });
        container.innerHTML = html;
        countSpan.textContent = cart.length;
        totalSpan.textContent = total.toLocaleString('fr-DZ') + ' DZD';
    }

    function updateSubmitButton() {
        const submitBtn = document.getElementById('submitBtn');
        const doctorSelect = document.getElementById('doctorSelect');
        selectedDoctorId = doctorSelect ? doctorSelect.value : null;
        const isValid = selectedPatientId && selectedDoctorId && cart.length > 0;
        if (submitBtn) {
            submitBtn.disabled = !isValid;
            if (isValid) submitBtn.innerHTML = `✅ Créer l'analyse (${cart.length})`;
            else submitBtn.innerHTML = !selectedPatientId ? '❌ Patient requis' : (!selectedDoctorId ? '❌ Médecin requis' : '❌ Ajoutez des analyses');
        }
    }

    function showTemporaryMessage(message, type) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:1000;padding:10px 15px;font-size:0.8rem';
        toast.innerHTML = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function submitAnalyses() {
        if (!selectedPatientId) { showTemporaryMessage('❌ Sélectionnez un patient', 'warning'); return; }
        const doctorSelect = document.getElementById('doctorSelect');
        if (!doctorSelect.value) { showTemporaryMessage('❌ Sélectionnez un médecin', 'warning'); return; }
        if (cart.length === 0) { showTemporaryMessage('❌ Ajoutez des analyses', 'warning'); return; }
        const globalNotes = document.getElementById('globalNotes').value;
        const testsData = cart.map(item => ({ name: item.name, price: item.price, category: item.category }));
        document.getElementById('formPatientId').value = selectedPatientId;
        document.getElementById('formDoctorId').value = doctorSelect.value;
        document.getElementById('formTestsJson').value = JSON.stringify(testsData);
        document.getElementById('formNotes').value = globalNotes;
        document.getElementById('submitForm').submit();
    }

    document.getElementById('doctorSelect')?.addEventListener('change', updateSubmitButton);

    // ==================== UNITÉS ET RÉFÉRENCES ====================
    document.querySelectorAll('.unit-select').forEach(select => {
        const customInput = select.closest('.form-group')?.querySelector('input[name^="units_custom"]');
        if (customInput) {
            select.addEventListener('change', () => { customInput.style.display = select.value === 'autre' ? 'block' : 'none'; });
            customInput.style.display = select.value === 'autre' ? 'block' : 'none';
        }
    });

    <?php if ($page === 'batch_result' && isset($tests)): foreach ($tests as $test): $test_name = $test['test_name']; if (isset($reference_ranges[$test_name])): ?>
    (function() {
        const testId = <?php echo $test['id']; ?>;
        const refs = <?php echo json_encode($reference_ranges[$test_name]); ?>;
        const displayDiv = document.getElementById(`ref_display_${testId}`);
        const hiddenInput = document.getElementById(`ref_range_${testId}`);
        const customDiv = document.getElementById(`ref_custom_${testId}`);
        const customInput = customDiv ? customDiv.querySelector('input') : null;
        document.querySelectorAll(`.ref-btn[data-test="${testId}"]`).forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.dataset.type;
                document.querySelectorAll(`.ref-btn[data-test="${testId}"]`).forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                if (type === 'Personnalisé') {
                    if (customDiv) customDiv.classList.add('show');
                    if (customInput) { customInput.focus(); customInput.value = ''; }
                    if (displayDiv) displayDiv.innerHTML = '<em>Saisir une valeur...</em>';
                    if (hiddenInput) hiddenInput.value = '';
                } else {
                    if (customDiv) customDiv.classList.remove('show');
                    const value = refs[type];
                    if (value && displayDiv) displayDiv.innerHTML = value;
                    if (value && hiddenInput) hiddenInput.value = value;
                    if (customInput) customInput.value = '';
                }
            });
        });
        if (customInput) {
            customInput.addEventListener('change', function() {
                if (this.value && displayDiv) displayDiv.innerHTML = this.value;
                if (this.value && hiddenInput) hiddenInput.value = this.value;
            });
        }
    })();
    <?php endif; endforeach; endif; ?>

    // ==================== RÉSULTATS ET EMAIL ====================
    function viewResults(patientId, testDate) {
        const modalContent = document.getElementById('resultsModalContent');
        if (modalContent) modalContent.innerHTML = '<div style="text-align:center;padding:40px;">⏳ Chargement...</div>';
        document.getElementById('resultsModal').style.display = 'flex';
        fetch(`get_results.php?patient_id=${patientId}&date=${encodeURIComponent(testDate)}`)
            .then(r => r.json())
            .then(data => { if (data.success) document.getElementById('resultsModalContent').innerHTML = data.html;
            else document.getElementById('resultsModalContent').innerHTML = `<div class="alert alert-error">❌ ${data.error}</div>`; })
            .catch(err => { document.getElementById('resultsModalContent').innerHTML = '<div class="alert alert-error">❌ Erreur réseau</div>'; });
    }

    function viewSingleResult(testId) {
        const modalContent = document.getElementById('resultsModalContent');
        if (modalContent) modalContent.innerHTML = '<div style="text-align:center;padding:40px;">⏳ Chargement...</div>';
        document.getElementById('resultsModal').style.display = 'flex';
        fetch(`get_results.php?test_id=${testId}`)
            .then(r => r.json())
            .then(data => { if (data.success) document.getElementById('resultsModalContent').innerHTML = data.html;
            else document.getElementById('resultsModalContent').innerHTML = `<div class="alert alert-error">❌ ${data.error}</div>`; })
            .catch(err => { document.getElementById('resultsModalContent').innerHTML = '<div class="alert alert-error">❌ Erreur réseau</div>'; });
    }

    function printResults(patientId, testDate) {
        window.open(`get_results.php?patient_id=${patientId}&date=${encodeURIComponent(testDate)}&print=1`, '_blank', 'width=800,height=600');
    }

    function printSingleResult(testId) {
        window.open(`get_results.php?test_id=${testId}&print=1`, '_blank', 'width=800,height=600');
    }

    function sendResultsEmail(patientId, testDate, email, patientName) {
        if (!email) { showTemporaryMessage('❌ Pas d\'email', 'warning'); return; }
        document.getElementById('emailPreviewContent').innerHTML = '<div style="text-align:center;padding:40px;">⏳ Préparation...</div>';
        document.getElementById('emailModal').style.display = 'flex';
        fetch(`get_results.php?patient_id=${patientId}&date=${encodeURIComponent(testDate)}&email_preview=1`)
            .then(r => r.text())
            .then(html => {
                pendingEmailData = { patient_id: patientId, test_date: testDate, email: email, patient_name: patientName, html: html };
                document.getElementById('emailPreviewContent').innerHTML = html;
            })
            .catch(err => { document.getElementById('emailPreviewContent').innerHTML = '<div class="alert alert-error">❌ Erreur réseau</div>'; });
    }

    function closeEmailModal() { document.getElementById('emailModal').style.display = 'none'; pendingEmailData = null; }

    function sendConfirmedEmail() {
        if (!pendingEmailData) return;
        const sendBtn = document.querySelector('#emailModal .btn-primary');
        if (sendBtn) { sendBtn.innerHTML = '⏳ Envoi...'; sendBtn.disabled = true; }
        fetch('send_results_email.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(pendingEmailData)
        }).then(r => r.json()).then(data => {
            if (sendBtn) { sendBtn.innerHTML = '✅ Confirmer l\'envoi'; sendBtn.disabled = false; }
            if (data.success) { showTemporaryMessage('✅ Email envoyé', 'success'); closeEmailModal(); }
            else showTemporaryMessage('❌ Erreur: ' + (data.error || 'Inconnue'), 'error');
        }).catch(err => { if (sendBtn) { sendBtn.innerHTML = '✅ Confirmer l\'envoi'; sendBtn.disabled = false; } showTemporaryMessage('❌ Erreur réseau', 'error'); });
    }

    function printModalContent() {
        const content = document.getElementById('resultsModalContent').innerHTML;
        const printWin = window.open('', '_blank', 'width=800,height=600');
        printWin.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Impression</title></head><body>${content}</body></html>`);
        printWin.document.close();
        printWin.print();
    }

    function closeModal() { document.getElementById('resultsModal').style.display = 'none'; }

    function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }

    updateSubmitButton();
</script>
</body>
</html>