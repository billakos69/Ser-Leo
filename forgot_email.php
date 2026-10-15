<?php
session_start();
include 'config.php';
include 'send_username_email.php'; // ✉️ νέο αρχείο για αποστολή email
date_default_timezone_set("Europe/Athens");

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);

    // Εύρεση username βάσει email
    $sql = "SELECT username FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $username = $row['username'];

        // Αποστολή mail με το username
        if (sendUsernameEmail($email, $username)) {
            $success = "✅ Σου στείλαμε το username σου στο <b>$email</b>";
        } else {
            $error = "❌ Σφάλμα αποστολής email. Δοκίμασε ξανά αργότερα.";
        }
    } else {
        $error = "❌ Δεν βρέθηκε χρήστης με αυτό το email.";
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Ξέχασα το Username</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">
    <style>
      body { background-color:#080710; font-family:'Poppins',sans-serif; }
      .container {
          width:400px; margin:100px auto; background:rgba(255,255,255,0.1);
          padding:30px; border-radius:10px; color:#fff; text-align:center;
      }
      input {
          width:100%; padding:10px; margin:10px 0; border:none;
          border-radius:5px; background:rgba(255,255,255,0.2); color:#fff;
      }
      button {
          padding:10px 20px; border:none; border-radius:5px;
          background:#fff; color:#080710; font-weight:600; cursor:pointer;
      }
      .msg { margin-top:15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Ξέχασα το Username</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="Το email σου" required>
            <button type="submit">Ανάκτηση Username</button>
        </form>

        <?php 
          if($success) echo "<p class='msg' style='color:lightgreen;'>$success</p>"; 
          if($error) echo "<p class='msg' style='color:red;'>$error</p>"; 
        ?>
    </div>
</body>
</html>
