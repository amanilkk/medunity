<!DOCTYPE html>
<html lang="en">

<?php include("../includes/header.php"); ?>

<body>
<?php
// add-user.php - Ajout d'un utilisateur avec vérification de mot de passe
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

// Fonction pour vérifier la complexité du mot de passe
function check_password_complexity($password) {
    $errors = [];

    // Longueur minimale : 8 caractères
    if(strlen($password) < 8) {
        $errors[] = "au moins 8 caractères";
    }

    // Au moins une lettre majuscule
    if(!preg_match('/[A-Z]/', $password)) {
        $errors[] = "au moins une lettre majuscule";
    }

    // Au moins une lettre minuscule
    if(!preg_match('/[a-z]/', $password)) {
        $errors[] = "au moins une lettre minuscule";
    }

    // Au moins un chiffre
    if(!preg_match('/[0-9]/', $password)) {
        $errors[] = "au moins un chiffre";
    }

    // Au moins un caractère spécial
    if(!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        $errors[] = "au moins un caractère spécial (!@#$%^&* etc.)";
    }

    return $errors;
}

if($_POST){
    $full_name = $database->real_escape_string($_POST['full_name']);
    $email     = $database->real_escape_string($_POST['email']);
    $phone     = $database->real_escape_string($_POST['phone'] ?? '');
    $role_id   = intval($_POST['role_id']);
    $password  = $_POST['password'];
    $cpassword = $_POST['cpassword'];

    // Vérifier confirmation mot de passe
    if($password !== $cpassword){
        header("location: users.php?action=add&error=2");
        exit();
    }

    // ⭐⭐⭐ VÉRIFICATION DE LA COMPLEXITÉ DU MOT DE PASSE ⭐⭐⭐
    $complexity_errors = check_password_complexity($password);
    if(!empty($complexity_errors)){
        $error_msg = "Le mot de passe doit contenir : " . implode(", ", $complexity_errors);
        header("location: users.php?action=add&error=5&msg=" . urlencode($error_msg));
        exit();
    }

    // Vérifier email déjà utilisé
    $check = $database->query("SELECT id FROM users WHERE email='$email'");
    if($check->num_rows > 0){
        header("location: users.php?action=add&error=1");
        exit();
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $database->query("INSERT INTO users (email, password, full_name, role_id, phone, is_active, two_factor_enabled)
                      VALUES ('$email', '$hashed', '$full_name', $role_id, '$phone', 1, 0)");

    // Log dans audit
    $new_uid = $database->insert_id;
    $actor   = intval($_SESSION['uid'] ?? 1);
    $database->query("INSERT INTO logs (user_id, action, entity_type, entity_id, ip_address)
                      VALUES ($actor, 'CREATE_USER', 'users', $new_uid, '{$_SERVER['REMOTE_ADDR']}')");

    // ⭐ Si le rôle est médecin → insérer dans la table doctors
    $role_check = $database->query("SELECT role_name FROM roles WHERE id=$role_id")->fetch_assoc();
    $role_name_lc = strtolower($role_check['role_name'] ?? '');
    $is_doctor = strpos($role_name_lc, 'doctor') !== false
        || strpos($role_name_lc, 'medecin') !== false
        || strpos($role_name_lc, 'médecin') !== false
        || strpos($role_name_lc, 'physician') !== false;

    if($is_doctor) {
        $specialty_id = isset($_POST['specialty_id']) && $_POST['specialty_id'] !== '' ? intval($_POST['specialty_id']) : 'NULL';
        $database->query("INSERT INTO doctors (user_id, specialty_id, availability_status)
                          VALUES ($new_uid, $specialty_id, 'available')");
        $doctor_id = $database->insert_id;
        $database->query("INSERT INTO logs (user_id, action, entity_type, entity_id, ip_address)
                          VALUES ($actor, 'CREATE_DOCTOR', 'doctors', $doctor_id, '{$_SERVER['REMOTE_ADDR']}')");
    }

    header("location: users.php?action=add&error=4");
    exit();
}

header("location: users.php?action=add&error=3");
exit();
?>