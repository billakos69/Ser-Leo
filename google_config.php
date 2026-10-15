<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Σωστό path προς vendor

$client = new Google_Client();
$client->setClientId('915186988604-l3dam6vaqunsdq3e85afj64inu1eveau.apps.googleusercontent.com');
$client->setClientSecret('ΤΟ_CLIENT_SECRET_ΣΟΥ');
$client->setRedirectUri('http://localhost/google_callback.php'); // ή live URL
$client->addScope("email");
$client->addScope("profile");
?>
