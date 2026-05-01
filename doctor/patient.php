<?php
session_start();
if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd'){
    header("location: ../login.php");
    exit();
}
$useremail = $_SESSION["user"];

include("../connection.php");

// Récupération des infos du docteur (ID et Nom selon ta base)
$userrow = $database->query("SELECT id, full_name FROM doctors WHERE email='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["id"];
$username = $userfetch["full_name"];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <title>Mes Patients | SGCM</title>
</head>
<body>
    <div class="container">
        <div class="dash-body">
            <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
                <tr>
                    <td colspan="4">
                        <p style="font-size: 23px;padding-left:12px;font-weight: 600;">Mes Patients</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <center>
                        <table class="sub-table" width="95%">
                            <thead>
                                <tr>
                                    <th>Nom du Patient</th>
                                    <th>Téléphone</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                                // Requête vers la table 'patients' (avec un s comme dans ton SQL)
                                $sqlmain = "SELECT DISTINCT p.id, p.full_name, p.phone, p.email 
                                           FROM patients p 
                                           INNER JOIN appointments a ON p.id = a.patient_id 
                                           WHERE a.doctor_id = '$userid'";
                                $result = $database->query($sqlmain);

                                if($result->num_rows == 0){
                                    echo '<tr><td colspan="4"><center><br>Aucun patient suivi.</center></td></tr>';
                                } else {
                                    while($row = $result->fetch_assoc()){
                                        echo '<tr>
                                            <td>'.$row["full_name"].'</td>
                                            <td>'.$row["phone"].'</td>
                                            <td>'.$row["email"].'</td>
                                            <td>
                                                <a href="view-patient.php?id='.$row["id"].'" class="btn-primary-soft btn">📑 Voir Historique</a>
                                            </td>
                                        </tr>';
                                    }
                                }
                            ?>
                            </tbody>
                        </table>
                        </center>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>