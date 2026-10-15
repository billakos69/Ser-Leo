<?php
session_start();

$client_id = 'Ov23liMvLDTOmMCy239w'; // GitHub Client ID
$redirect_uri = 'http://localhost/serleo/github_callback.php';
$scope = 'user:email';

$url = "https://github.com/login/oauth/authorize?client_id=$client_id&redirect_uri=$redirect_uri&scope=$scope";

header("Location: $url");
exit;
?>
