<?php
session_start();

// Καταστρέφουμε όλα τα session variables
$_SESSION = [];

// Καταστρέφουμε το session
session_destroy();

// Καθαρίζουμε το cookie του session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Θέτουμε ένα flash μήνυμα μέσω GET
header("Location: index.php?logout=1");
exit;
?>
