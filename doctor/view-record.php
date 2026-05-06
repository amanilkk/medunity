<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();

// 1. Vérification de la session
if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='d'){
        header("location: ../login.php");
    }else{
        $useremail=$_SESSION["user"];
    }
}else{
    header("location: ../login.php");
}

// 2. Connexion à la base de données
include("../connection.php");

if(isset($_GET['id'])){
    $record_id = $_GET['id'];
    
    // Requête pour récupérer les détails du rapport, du patient et du docteur
    // Note : On utilise 'doctors' et 'patients' selon votre structure phpMyAdmin
    $sql = "SELECT mr.*, p.pname, p.paddress, p.ptel, d.dname 
            FROM medical_records mr
            INNER JOIN patient p ON mr.patient_id = p.pid
            INNER JOIN doctors d ON mr.doctor_id = d.did
            WHERE mr.id = '$record_id'";
            
    $result = $database->query($sql);
    if($result->num_rows > 0){
        $row = $result->fetch_assoc();
    } else {
        echo "Rapport introuvable.";
        exit();
    }
} else {
    header("location: patient.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Médical - SGCM</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .report-container {
            width: 80%;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .report-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .report-body { margin-top: 30px; line-height: 1.6; }
        .info-section { margin-bottom: 20px; display: flex; justify-content: space-between; }
        .content-box { background: #f9f9f9; padding: 15px; border-left: 5px solid #0a76d8; margin: 10px 0; }
        @media print {
            .no-print { display: none; }
            .report-container { box-shadow: none; border: none; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="padding: 20px;">
        <a href="patient.php" class="btn-primary-soft btn"> < Retour</a>
        <button onclick="window.print()" class="btn-primary btn" style="margin-left:10px;">🖨️ Imprimer en PDF</button>
    </div>

    <div class="report-container">
        <div class="report-header">
            <h1>SYSTÈME DE GESTION DE CLINIQUE MÉDICALE</h1>
            <p>Rapport de Consultation Officiel</p>
        </div>

        <div class="report-body">
            <div class="info-section">
                <div>
                    <strong>Patient :</strong> <?php echo $row['pname']; ?><br>
                    <strong>Téléphone :</strong> <?php echo $row['ptel']; ?>
                </div>
                <div style="text-align: right;">
                    <strong>Date de visite :</strong> <?php echo $row['visit_date']; ?><br>
                    <strong>Docteur :</strong> <?php echo $row['dname']; ?>
                </div>
            </div>

            <hr>

            <h3>Symptômes rapportés :</h3>
            <div class="content-box">
                <?php echo nl2br($row['symptoms']); ?>
            </div>

            <h3>Diagnostic :</h3>
            <div class="content-box" style="font-weight: bold; color: #d82a0a;">
                <?php echo $row['diagnosis']; ?>
            </div>

            <h3>Prescription Médicale :</h3>
            <div class="content-box">
                <?php echo nl2br($row['prescription']); ?>
            </div>
        </div>

        <div class="report-footer" style="margin-top: 50px; text-align: right;">
            <p>Signature du Docteur :</p>
            <br><br>
            <p>__________________________</p>
            <p>Dr. <?php echo $row['dname']; ?></p>
        </div>
    </div>
</body>
</html>