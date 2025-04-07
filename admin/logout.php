<?php
require_once '../includes/functions.php';

// Logout user
$result = logoutUser();

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login page
header('Location: ../student/login.php');
exit();
?> 