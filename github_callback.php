<?php
session_start();
include 'config.php';

$client_id = 'Ov23liMvLDTOmMCy239w';
$client_secret = '2da89ab8ddd41ecde8b557dae82c60bfb0d8e07b'; // Βάλε εδώ το Client Secret

if (!isset($_GET['code'])) {
    die("❌ Δεν έλαβε κωδικό από GitHub.");
}

$code = $_GET['code'];

// Ζήτα το access token
$token_url = "https://github.com/oauth/access_token";
$data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
    ],
];

$context  = stream_context_create($options);
$result = file_get_contents($token_url, false, $context);
$response = json_decode($result, true);

if (!isset($response['access_token'])) {
    die("❌ Δεν πήραμε access token από GitHub.");
}

$access_token = $response['access_token'];

// Πάρε τα user data
$user_data = file_get_contents("https://api.github.com/user", false, stream_context_create([
    'http' => [
        'header' => "User-Agent: PHP\r\nAuthorization: token $access_token\r\n"
    ]
]));
$user = json_decode($user_data, true);

// Παράδειγμα: έλεγξε αν υπάρχει χρήστης στη βάση
$email = $user['email'] ?? $user['login'];
$username = $user['login'];

$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows === 0){
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, '')");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $user_id = $stmt->insert_id;
} else {
    $user_row = $res->fetch_assoc();
    $user_id = $user_row['id'];
}

// Συνδέσου
$_SESSION['user_id'] = $user_id;
$_SESSION['user'] = $username;
$_SESSION['is_admin'] = 0;

header("Location: index.php");
exit;
