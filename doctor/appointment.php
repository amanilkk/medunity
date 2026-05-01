<?php
session_start();
include("../connection.php");

if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd'){
    header("location: ../login.php");
    exit();
}

$useremail = $_SESSION["user"];
$userrow = $database->query("SELECT doctors.id FROM doctors INNER JOIN users ON doctors.user_id = users.id WHERE users.email = '$useremail'");
$docid = ($userrow->fetch_assoc())['id'];

// Requête adaptée à votre SQL (Table appointments + patients)
$sqlmain = "SELECT a.*, p.name as pname, p.uhid FROM appointments a 
            INNER JOIN patients p ON a.patient_id = p.id 
            WHERE a.doctor_id = '$docid' 
            ORDER BY a.priority='high' DESC, a.appointment_date ASC";
$result = $database->query($sqlmain);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <title>Mes Rendez-vous</title>
</head>
<body>
    <div class="dash-body">
        <table class="sub-table" width="100%">
            <thead>
                <tr>
                    <th>UHID Patient</th>
                    <th>Nom du Patient</th>
                    <th>Date & Heure</th>
                    <th>Priorité</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['uhid']; ?></td>
                    <td><?php echo $row['pname']; ?></td>
                    <td><?php echo $row['appointment_date']; ?></td>
                    <td><?php echo ($row['priority'] == 'high') ? '<b style="color:red">URGENT</b>' : 'Normal'; ?></td>
                    <td>
                        <a href="consultation.php?id=<?php echo $row['id']; ?>" class="btn-primary-soft btn">🩺 Consulter</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>