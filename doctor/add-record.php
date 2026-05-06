<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
session_start();
include("../connection.php");

if($_POST){
    $useremail = $_SESSION["user"];
    $pid = $_POST['pid']; 
    $symptoms = addslashes($_POST['symptoms']);
    $diagnosis = addslashes($_POST['diagnosis']);
    $prescription = addslashes($_POST['prescription']);
    
    // Récupérer l'ID du docteur connecté
    $doctor_res = $database->query("SELECT id FROM doctors WHERE email='$useremail'");
    $doctor_data = $doctor_res->fetch_assoc();
    $did = $doctor_data['id'];

    // Insertion (Assure-toi que la table medical_records existe)
    $sql = "INSERT INTO medical_records (patient_id, doctor_id, symptoms, diagnosis, prescription, visit_date) 
            VALUES ('$pid', '$did', '$symptoms', '$diagnosis', '$prescription', NOW())";
    
    if($database->query($sql)){
        header("location: appointment.php?action=success");
    } else {
        echo "Erreur SQL : " . $database->error;
    }
}
?>