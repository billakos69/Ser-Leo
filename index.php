<?php
session_start();
include 'config.php';
$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;

$logoutMessage = '';
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $logoutMessage = "✅ Αποσυνδεθήκατε με επιτυχία!";
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <title>SER LEO F.C</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="images/logo.png" type="image/png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<style>
  body {
    background-image: url('https://i.imgur.com/IF21m0g.jpeg');
    background-size: cover;
    background-attachment: fixed;
  }
</style>
<body>
<?php if($logoutMessage): ?>
<div id="logout-msg" class="logout-msg">
    <?= htmlspecialchars($logoutMessage) ?>
</div>
<?php endif; ?>

<!-- MENU -->
<?php include 'nav.php'; ?>

<!-- HERO -->
<section class="hero">
  <img src="images/logo.png" alt="Λογότυπο Ομάδας" style="width:180px; height:auto;">
  <h1>Καλώς ήρθατε στην επίσημη σελίδα</h1>
  <p>Μαζί γράφουμε ιστορία στο γήπεδο!</p>
  <a href="league.php">Δες τα επόμενα παιχνίδια</a>
</section>

<!-- ΝΕΑ -->
<section class="features">
  <div class="feature">
    <h2>📰 Τελευταία Νέα</h2>
    <p>Διαβάστε τις τελευταίες ανακοινώσεις και εξελίξεις της ομάδας.</p>
    <a href="news.php">Περισσότερα</a>
  </div>
  <div class="feature">
    <h2>⚽ Αγώνες & Αποτελέσματα</h2>
    <p>Ενημερωθείτε για το πρόγραμμα και τα αποτελέσματα της σεζόν.</p>
    <a href="league.php">Δες αναλυτικά</a>
  </div>
  <div class="feature">
    <h2>🎁 Ένα δώρο από εμάς </h2>
    <p>Κάθε νέος χρήστης με το κωδικό "LEO10" έχει 10% έκπτωση στην πρώτη του παραγγελία.</p>
    <a href="shop.php">Κατάστημα</a>
  </div>
</section>

<?php include 'footer.html'; ?>
<?php include 'popup.html'; ?>

<script src="navbar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.getElementById('menu-toggle');
  const navLinks = document.getElementById('nav-links');
  const userMenu = document.querySelector('.user-menu');

  // Toggle main burger menu
  menuToggle.addEventListener('click', (e) => {
    navLinks.classList.toggle('active');
    menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
  });

  // Toggle user dropdown only on mobile
  userMenu.addEventListener('click', (e) => {
    if(window.innerWidth <= 768){
      e.stopPropagation(); // Δεν κλείνουμε το nav
      userMenu.classList.toggle('active');
    }
  });

  // Κλείσιμο dropdown αν κλικάρει αλλού
  document.addEventListener('click', () => {
    if(userMenu.classList.contains('active')){
      userMenu.classList.remove('active');
    }
  });
});




  // Απόκρυψη μηνύματος αποσύνδεσης
  const msg = document.getElementById('logout-msg');
  if (msg) {
    setTimeout(() => {
      msg.style.opacity = '0';
      setTimeout(() => msg.remove(), 1000);
    }, 3000);
  }
</script>

</body>
</html>
