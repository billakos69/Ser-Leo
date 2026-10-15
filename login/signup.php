<?php
include 'config.php';
session_start();

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $email    = $conn->real_escape_string($_POST['email']);
    $phone    = $conn->real_escape_string($_POST['phone']);
    $password = $_POST['password'];

    // Έλεγχος αν υπάρχει ήδη αυτό το email
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $res = $check->get_result();

    if($res->num_rows > 0){
        $error = "❌ Το email υπάρχει ήδη.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = $conn->prepare("INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)");
        $sql->bind_param("ssss", $username, $email, $phone, $hashed);
        if($sql->execute()){
            $success = "✅ Εγγραφή ολοκληρώθηκε! <a href='login.php' style='color:lightgreen;'>Συνδέσου εδώ</a>";
        } else {
            $error = "❌ Σφάλμα κατά την εγγραφή.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
   <head>
      <meta charset="utf-8">
      <title>Εγγραφή Χρήστη</title>
      <link rel="stylesheet" href="style_log.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
   </head>
<body>

 <div class="bg-img">
   <div class="content">
      <header>Εγγραφή</header>

      <?php if($success) echo "<p class='msg' style='color:lightgreen;'>$success</p>"; ?>
      <?php if($error) echo "<p class='msg' style='color:red;'>$error</p>"; ?>

      <form method="POST" action="">

         <label for="username" style="display:none;">Username</label>
         <div class="field">
            <span class="fa fa-user"></span>
            <input type="text" id="username" name="username" required placeholder="Όνομα χρήστη">
         </div>

         <label for="email" style="display:none;">Email</label>
         <div class="field space">
            <span class="fa fa-envelope"></span>
            <input type="email" id="email" name="email" required placeholder="Email">
         </div>

         <label for="phone" style="display:none;">Τηλέφωνο</label>
         <div class="field space">
            <span class="fa fa-phone"></span>
            <input type="text" id="phone" name="phone" placeholder="Τηλέφωνο">
         </div>

         <label for="password" style="display:none;">Κωδικός</label>
         <div class="field space">
            <span class="fa fa-lock"></span>
            <input type="password" id="password" name="password" class="pass-key" required placeholder="Κωδικός">
            <span class="show">SHOW</span>
         </div>

         <div class="field">
            <input type="submit" value="Εγγραφή">
         </div>

         <div class="signup">
            Έχεις ήδη λογαριασμό;
            <a href="login.php">Σύνδεση</a>
         </div>

      </form>
   </div>
 </div>

<script>
const pass_field = document.querySelector('.pass-key');
const showBtn = document.querySelector('.show');
showBtn.addEventListener('click', function(){
    if(pass_field.type === "password"){
       pass_field.type = "text";
       showBtn.textContent = "HIDE";
       showBtn.style.color = "#3498db";
    }else{
       pass_field.type = "password";
       showBtn.textContent = "SHOW";
       showBtn.style.color = "#222";
    }
});
</script>

</body>
</html>
