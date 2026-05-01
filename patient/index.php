<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <title>Dashboard</title>
    <style>
        .dashbord-tables{ animation: transitionIn-Y-over 0.5s; }
        .filter-container{ animation: transitionIn-Y-bottom 0.5s; }
        .sub-table,.anime{ animation: transitionIn-Y-bottom 0.5s; }
    </style>
</head>
<body>
<?php
session_start();

// ── Vérification session ──────────────────────────────────────
if (isset($_SESSION["user"])) {
    if ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'p') {
        header("location: ../login.php");
        exit();
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

date_default_timezone_set('Africa/Algiers');
$today    = date('Y-m-d');
$nextweek = date("Y-m-d", strtotime("+1 week"));

// ── Infos du patient connecté (nouvelle BD) ───────────────────
$stmtPat = $database->prepare(
    "SELECT u.id AS user_id, u.full_name, u.email,
            p.id AS patient_id, p.uhid, p.blood_type, p.allergies
     FROM users u
     JOIN patients p ON p.user_id = u.id
     WHERE u.email = ?
     LIMIT 1"
);
$stmtPat->bind_param("s", $useremail);
$stmtPat->execute();
$resPat = $stmtPat->get_result();

if ($resPat->num_rows === 0) {
    die("Erreur : patient introuvable pour cet email.");
}
$patient    = $resPat->fetch_assoc();
$patient_id = $patient['patient_id'];
$username   = $patient['full_name'];

// ── Statistiques globales ─────────────────────────────────────
$totalDoctors  = $database->query("SELECT COUNT(*) AS n FROM doctors")->fetch_assoc()['n'];
$totalPatients = $database->query("SELECT COUNT(*) AS n FROM patients")->fetch_assoc()['n'];

$stmtAppo = $database->prepare(
    "SELECT COUNT(*) AS n FROM appointments
     WHERE patient_id = ? AND appointment_date >= ?"
);
$stmtAppo->bind_param("is", $patient_id, $today);
$stmtAppo->execute();
$totalAppointments = $stmtAppo->get_result()->fetch_assoc()['n'];

$stmtToday = $database->prepare(
    "SELECT COUNT(*) AS n FROM appointments
     WHERE patient_id = ? AND appointment_date = ?"
);
$stmtToday->bind_param("is", $patient_id, $today);
$stmtToday->execute();
$todaySessions = $stmtToday->get_result()->fetch_assoc()['n'];

// ── Liste médecins pour datalist recherche ────────────────────
$doctorsList = $database->query(
    "SELECT u.full_name, u.email
     FROM users u
     JOIN doctors d ON d.user_id = u.id
     WHERE u.is_active = 1"
);

// ── Prochains rendez-vous du patient ─────────────────────────
$stmtUpcoming = $database->prepare(
    "SELECT a.id, a.appointment_date, a.appointment_time,
            a.status, a.type, a.reason,
            u.full_name AS doctor_name,
            s.sname AS specialty
     FROM appointments a
     JOIN doctors d  ON d.id = a.doctor_id
     JOIN users u    ON u.id = d.user_id
     LEFT JOIN specialties s ON s.id = d.specialty_id
     WHERE a.patient_id = ?
       AND a.appointment_date >= ?
     ORDER BY a.appointment_date ASC, a.appointment_time ASC"
);
$stmtUpcoming->bind_param("is", $patient_id, $today);
$stmtUpcoming->execute();
$upcomingResult = $stmtUpcoming->get_result();
?>

<div class="container">
    <!-- ── MENU LATÉRAL ── -->
    <div class="menu">
        <table class="menu-container" border="0">
            <tr>
                <td style="padding:10px" colspan="2">
                    <table border="0" class="profile-container">
                        <tr>
                            <td width="30%" style="padding-left:20px">
                                <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                            </td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title"><?php echo htmlspecialchars(substr($username, 0, 13)); ?>..</p>
                                <p class="profile-subtitle"><?php echo htmlspecialchars(substr($useremail, 0, 22)); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="../logout.php"><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-home menu-active menu-icon-home-active">
                    <a href="index.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Home</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-doctor">
                    <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">All Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-session">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Scheduled Sessions</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">My Bookings</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-settings">
                    <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Settings</p></div></a>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── CORPS PRINCIPAL ── -->
    <div class="dash-body" style="margin-top:15px">
        <table border="0" width="100%" style="border-spacing:0;margin:0;padding:0;">

            <!-- Barre top -->
            <tr>
                <td colspan="1" class="nav-bar">
                    <p style="font-size:23px;padding-left:12px;font-weight:600;margin-left:20px;">Home</p>
                </td>
                <td width="25%"></td>
                <td width="15%">
                    <p style="font-size:14px;color:rgb(119,119,119);padding:0;margin:0;text-align:right;">Today's Date</p>
                    <p class="heading-sub12" style="padding:0;margin:0;"><?php echo $today; ?></p>
                </td>
                <td width="10%">
                    <button class="btn-label" style="display:flex;justify-content:center;align-items:center;">
                        <img src="../img/calendar.svg" width="100%">
                    </button>
                </td>
            </tr>

            <!-- Message de bienvenue + recherche médecin -->
            <tr>
                <td colspan="4">
                    <center>
                        <table class="filter-container doctor-header patient-header" style="border:none;width:95%" border="0">
                            <tr>
                                <td>
                                    <h3>Welcome!</h3>
                                    <h1><?php echo htmlspecialchars($username); ?>.</h1>
                                    <p>
                                        Haven't any idea about doctors? no problem let's jump to
                                        <a href="doctors.php" class="non-style-link"><b>"All Doctors"</b></a> section or
                                        <a href="schedule.php" class="non-style-link"><b>"Sessions"</b></a><br>
                                        Track your past and future appointments history.<br>
                                        Also find out the expected arrival time of your doctor or medical consultant.<br><br>
                                    </p>
                                    <h3>Channel a Doctor Here</h3>
                                    <form action="schedule.php" method="post" style="display:flex">
                                        <input type="search" name="search" class="input-text"
                                               placeholder="Search Doctor and We will Find The Session Available"
                                               list="doctors" style="width:45%;">&nbsp;&nbsp;

                                        <datalist id="doctors">
                                            <?php while ($doc = $doctorsList->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($doc['full_name']); ?>">
                                                <?php endwhile; ?>
                                        </datalist>

                                        <input type="submit" value="Search" class="login-btn btn-primary btn"
                                               style="padding:10px 25px;">
                                    </form>
                                    <br><br>
                                </td>
                            </tr>
                        </table>
                    </center>
                </td>
            </tr>

            <!-- Stats + Upcoming bookings -->
            <tr>
                <td colspan="4">
                    <table border="0" width="100%">
                        <tr>
                            <!-- Statistiques -->
                            <td width="50%">
                                <center>
                                    <table class="filter-container" style="border:none;" border="0">
                                        <tr>
                                            <td colspan="4">
                                                <p style="font-size:20px;font-weight:600;padding-left:12px;">Status</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="width:25%;">
                                                <div class="dashboard-items" style="padding:20px;margin:auto;width:95%;display:flex">
                                                    <div>
                                                        <div class="h1-dashboard"><?php echo $totalDoctors; ?></div><br>
                                                        <div class="h3-dashboard">All Doctors &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                                                    </div>
                                                    <div class="btn-icon-back dashboard-icons" style="background-image:url('../img/icons/doctors-hover.svg');"></div>
                                                </div>
                                            </td>
                                            <td style="width:25%;">
                                                <div class="dashboard-items" style="padding:20px;margin:auto;width:95%;display:flex;">
                                                    <div>
                                                        <div class="h1-dashboard"><?php echo $totalPatients; ?></div><br>
                                                        <div class="h3-dashboard">All Patients &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                                                    </div>
                                                    <div class="btn-icon-back dashboard-icons" style="background-image:url('../img/icons/patients-hover.svg');"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="width:25%;">
                                                <div class="dashboard-items" style="padding:20px;margin:auto;width:95%;display:flex;">
                                                    <div>
                                                        <div class="h1-dashboard"><?php echo $totalAppointments; ?></div><br>
                                                        <div class="h3-dashboard">My Bookings &nbsp;&nbsp;</div>
                                                    </div>
                                                    <div class="btn-icon-back dashboard-icons" style="background-image:url('../img/icons/book-hover.svg');"></div>
                                                </div>
                                            </td>
                                            <td style="width:25%;">
                                                <div class="dashboard-items" style="padding:20px;margin:auto;width:95%;display:flex;padding-top:21px;padding-bottom:21px;">
                                                    <div>
                                                        <div class="h1-dashboard"><?php echo $todaySessions; ?></div><br>
                                                        <div class="h3-dashboard" style="font-size:15px">Today Sessions</div>
                                                    </div>
                                                    <div class="btn-icon-back dashboard-icons" style="background-image:url('../img/icons/session-iceblue.svg');"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </center>
                            </td>

                            <!-- Prochains rendez-vous -->
                            <td>
                                <p style="font-size:20px;font-weight:600;padding-left:40px;" class="anime">
                                    Your Upcoming Bookings
                                </p>
                                <center>
                                    <div class="abc scroll" style="height:250px;padding:0;margin:0;">
                                        <table width="85%" class="sub-table scrolldown" border="0">
                                            <thead>
                                            <tr>
                                                <th class="table-headin">Doctor</th>
                                                <th class="table-headin">Specialty</th>
                                                <th class="table-headin">Date & Time</th>
                                                <th class="table-headin">Status</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php if ($upcomingResult->num_rows === 0): ?>
                                                <tr>
                                                    <td colspan="4">
                                                        <br><br><br>
                                                        <center>
                                                            <img src="../img/notfound.svg" width="25%"><br>
                                                            <p class="heading-main12" style="font-size:20px;color:rgb(49,49,49)">
                                                                Nothing to show here!
                                                            </p>
                                                            <a class="non-style-link" href="schedule.php">
                                                                <button class="login-btn btn-primary-soft btn"
                                                                        style="display:flex;justify-content:center;align-items:center;margin-left:20px;">
                                                                    &nbsp; Channel a Doctor &nbsp;
                                                                </button>
                                                            </a>
                                                        </center>
                                                        <br><br><br>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php while ($row = $upcomingResult->fetch_assoc()): ?>
                                                    <tr>
                                                        <td style="padding:20px;font-weight:600;">
                                                            &nbsp;<?php echo htmlspecialchars(substr($row['doctor_name'], 0, 20)); ?>
                                                        </td>
                                                        <td style="padding:20px;font-size:13px;">
                                                            <?php echo htmlspecialchars(substr($row['specialty'] ?? 'N/A', 0, 20)); ?>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <?php echo $row['appointment_date']; ?>
                                                            <?php echo substr($row['appointment_time'], 0, 5); ?>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <span style="
                                                                    padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;
                                                                    background:<?php echo $row['status']==='confirmed' ? '#d1fae5' : ($row['status']==='pending' ? '#fef3c7' : '#fee2e2'); ?>;
                                                                    color:<?php echo $row['status']==='confirmed' ? '#065f46' : ($row['status']==='pending' ? '#92400e' : '#991b1b'); ?>;">
                                                                <?php echo htmlspecialchars($row['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </center>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

        </table>
    </div>
</div>

</body>
</html>