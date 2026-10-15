<?php
include 'config.php';
session_start();
date_default_timezone_set("Europe/Athens");

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);

    // Έλεγχος αν υπάρχει ο χρήστης
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows == 1) {
        $token = bin2hex(random_bytes(16));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $sql = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE email=?");
        $sql->bind_param("sss", $token, $expires, $email);
        if ($sql->execute()) {
            $success = "<span class='success-text'>✅ Σου στείλαμε link επαναφοράς: </span>
            <a class='reset-link' href='reset_password.php?token=$token'>Πάτησε εδώ</a>";

        } else {
            $error = "❌ Σφάλμα κατά την ενημέρωση.";
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
    <title>Επαναφορά Κωδικού</title>
    <link rel="icon" href="images/logo.png" type="image/png">
      <link rel="stylesheet" href="style_log.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<div class="bg-img">
   <div class="content">
      <header>Reset Password</header>

      <?php if($success) echo "<p class='msg' style='color:lightgreen;'>$success</p>"; ?>
      <?php if($error) echo "<p class='msg' style='color:red;'>$error</p>"; ?>

      <form method="POST" action="">
         <label for="email" style="display:none;">Email</label>
         <div class="field">
            <span class="fa fa-envelope"></span>
            <input type="email" id="email" name="email" required placeholder="Το email σου">
         </div>

         <div class="field">
            <input type="submit" value="Αίτημα Reset">
         </div>

         <div class="signup">
            <a href="login.php">🔙 Επιστροφή στο Login</a>
         </div>
      </form>
   </div>
</div>

</body>
</html>
