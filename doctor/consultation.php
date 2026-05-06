<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include("../connection.php");

if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd'){
    header("location: ../login.php");
    exit();
}

$appid = $_GET['id']; // ID du rendez-vous
// On récupère l'ID du patient via le rendez-vous
$sql = "SELECT a.*, p.full_name, p.id as pid FROM appointments a 
        INNER JOIN patients p ON a.patient_id = p.id WHERE a.id = '$appid'";
$res = $database->query($sql);
$data = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <title>Consultation - <?php echo $data['full_name']; ?></title>
</head>
<body>
    <div class="dash-body" style="margin-top: 50px;">
        <center>
            <div style="width: 50%; background: white; padding: 30px; border-radius: 10px; border: 1px solid #ddd; text-align: left;">
                <h2>Examen de : <?php echo $data['full_name']; ?></h2>
                <form action="add-record.php" method="POST">
                    <input type="hidden" name="pid" value="<?php echo $data['pid']; ?>">
                    
                    <label>Symptômes :</label><br>
                    <textarea name="symptoms" class="input-text" style="width:100%; height:80px;" required></textarea><br><br>
                    
                    <label>Diagnostic :</label><br>
                    <input type="text" name="diagnosis" class="input-text" style="width:100%;" required><br><br>
                    
                    <label>Prescription :</label><br>
                    <textarea name="prescription" class="input-text" style="width:100%; height:80px;"></textarea><br><br>
                    
                    <button type="submit" class="btn-primary btn" style="width:100%">✅ Enregistrer le Rapport Médical</button>
                </form>
            </div>
        </center>
    </div>
</body>
</html>