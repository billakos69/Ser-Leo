<?php
session_start();
require_once 'google_config.php';
include 'config.php';

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token['access_token']);

    $google_service = new Google_Service_Oauth2($client);
    $data = $google_service->userinfo->get();

    $email = $data['email'];
    $name = $data['name'];

    // Έλεγχος χρήστη
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res->num_rows == 0){
        $insert = $conn->prepare("INSERT INTO users (username,email) VALUES (?,?)");
        $insert->bind_param("ss",$name,$email);
        $insert->execute();
        $user_id = $insert->insert_id;
    } else {
        $row = $res->fetch_assoc();
        $user_id = $row['id'];
    }

    $_SESSION['user_id'] = $user_id;
    $_SESSION['user'] = $name;
    $_SESSION['is_admin'] = 0;

    header("Location: index.php");
    exit;
}
?>
