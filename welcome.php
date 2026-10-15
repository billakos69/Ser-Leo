<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Welcome</title>
  <!-- κάνει refresh μετά από 5 δευτερόλεπτα -->
  <meta http-equiv="refresh" content="5;url=dashboard.php">
  <style>
    body {
      background: #0d0d0d;
      color: #ff0000ff;
      font-family: Arial, sans-serif;
      display: flex;
      height: 100vh;
      justify-content: center;
      align-items: center;
      flex-direction: column;
    }
  </style>
</head>
<body>
  <h1>Καλώς ήρθες, <?= htmlspecialchars($username) ?> 👋</h1>
  <p>Σε λίγα δευτερόλεπτα θα μεταφερθείς στην αρχική σου σελίδα...</p>
</body>
</html>
