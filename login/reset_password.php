<?php
include 'config.php';
session_start();
date_default_timezone_set("Europe/Athens");

if (!isset($_GET['token'])) {
    die("❌ Δεν υπάρχει token.");
}

$token = $conn->real_escape_string($_GET['token']);

// Έλεγχος token
$stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token=?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("❌ Το link δεν είναι έγκυρο.");
}

$user = $result->fetch_assoc();

// Έλεγχος λήξης
if (strtotime($user['reset_expires']) < time()) {
    die("❌ Το link έχει λήξει.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $update = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?");
    $update->bind_param("si", $new_password, $user['id']);
    if ($update->execute()) {
        header("Location: login.php");
        exit;
    } else {
        $error = "❌ Σφάλμα κατά την αλλαγή κωδικού.";
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Νέος Κωδικός</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<div class="bg-img">
   <div class="content">
      <header>Αλλαγή Κωδικού</header>

      <?php if(isset($error)) echo "<p class='msg' style='color:red;'>$error</p>"; ?>

      <form method="POST" action="">
         <label for="password" style="display:none;">Νέος κωδικός</label>
         <div class="field space">
            <span class="fa fa-lock"></span>
            <input type="password" id="password" name="password" required placeholder="Νέος κωδικός">
            <span class="show">SHOW</span>
         </div>

         <div class="field">
            <input type="submit" value="Αποθήκευση">
         </div>

         <div class="signup">
            <a href="login.php">🔙 Επιστροφή στο Login</a>
         </div>
      </form>
   </div>
</div>

<script>
const pass_field = document.querySelector('#password');
const showBtn = document.querySelector('.show');
showBtn.addEventListener('click', () => {
    if (pass_field.type === "password") {
        pass_field.type = "text";
        showBtn.textContent = "HIDE";
        showBtn.style.color = "#3498db";
    } else {
        pass_field.type = "password";
        showBtn.textContent = "SHOW";
        showBtn.style.color = "#222";
    }
});
</script>

</body>
</html>
