<?php
session_start();
include 'config.php';
include 'google_config.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // Admin check
    if ($email === "admin@admin.com" && $password === "admin") {
        $_SESSION['user_id'] = 0;
        $_SESSION['user'] = "Admin";
        $_SESSION['is_admin'] = 1;
        header("Location: admin_panel.php");
        exit;
    }

    // User check
    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user['username'];
            $_SESSION['is_admin'] = 0;
            header("Location: index.php");
            exit;
        } else {
            $error = "❌ Λάθος κωδικός";
        }
    } else {
        $error = "❌ Δεν βρέθηκε χρήστης";
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<link rel="stylesheet" href="style_log.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
.show { cursor:pointer; color:#888; margin-left:10px; }
.show:hover { color:#bc8a31; }
.msg { margin: 10px 0; font-weight: bold; color:red; }
</style>
</head>
<body>
<div class="bg-img">
  <div class="content">
    <header>Login Form</header>

    <?php if($error) echo "<p class='msg'>$error</p>"; ?>

    <form method="POST" autocomplete="off">
      <div class="field">
        <span class="fa fa-user"></span>
        <input type="text" name="email" placeholder="Email" required>
      </div>
      
      <div class="field space">
        <span class="fa fa-lock"></span>
        <input type="password" class="pass-key" name="password" placeholder="Password" required>
        <span class="show"><i class="fas fa-eye"></i></span>
      </div>

      <div class="pass">
        <a href="reset_request.php">Ξέχασες κωδικό;</a> |
        <a href="forgot_email.php">Ξέχασες email;</a>
      </div>

      <div class="field"><input type="submit" value="LOGIN"></div>
    </form>

   
    
    <div class="signup">
      Δεν έχεις λογαριασμό; <a href="signup.php">Εγγραφή</a>
    </div>
  </div>
</div>

<script>
// Show/hide password
const pass_field = document.querySelector('.pass-key');
const showBtn = document.querySelector('.show');
showBtn.addEventListener('click', function(){
 if(pass_field.type === "password"){
   pass_field.type = "text";
   showBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
 } else {
   pass_field.type = "password";
   showBtn.innerHTML = '<i class="fas fa-eye"></i>';
 }
});
</script>
</body>
</html>
