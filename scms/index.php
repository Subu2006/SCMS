<?php
session_start();

// Central Entry Router Logic
// Automatically directs users to their respective God-Level dashboards upon visiting the root URL
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'faculty') {
        // Direct staff/admins to the central enterprise command center
        header("Location: dashboard/index.php");
    } else {
        // Direct students to their specialized academic hub
        header("Location: dashboard/student.php");
    }
    exit;
} else {
    // Unauthenticated users are sent securely to the login portal
    header("Location: auth/login.php");
    exit;
}
?>