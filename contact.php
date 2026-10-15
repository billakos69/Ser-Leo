<?php
session_start();
$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Contact - SERLEO</title>
<link rel="icon" href="images/logo.png" type="image/png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css?v=5">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
<style>
/* === LAYOUT === */


/* Το main κρατά το περιεχόμενο στη μέση */
main {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  padding-top: 120px; /* Απόσταση από το navbar */
  padding-bottom: 60px; /* Απόσταση πριν το footer */
}

/* === CONTACT FORM === */
.container {
  width: 90%;
  max-width: 600px;
  background: #8f5909;
  padding: 25px;
  border-radius: 20px;
  box-shadow: 0 0 10px rgba(0,0,0,0.5);
}
h1 {
  text-align: center;
  margin-bottom: 20px;
}
input, textarea {
  width: 100%;
  padding: 10px;
  margin: 10px 0;
  border-radius: 8px;
  border: none;
  outline: none;
}
button {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  background: #000;
  color: #fff;
  font-weight: 700;
  transition: all 0.3s ease;
}
button:hover {
  background: #fff;
  color: #000;
  transform: scale(1.05);
}

/* === FOOTER πάντα στο κάτω μέρος === */
footer {
  background: #000;
  color: #ccc;
  text-align: center;
  padding: 20px 0;
  border-top: 1px solid rgba(255,255,255,0.1);
}
.footer .row {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 40px;
  margin: 10px 0;
}

/* Προσαρμογή burger menu */
#menu-section nav {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  z-index: 1000;
  background: rgba(0,0,0,0.9);
}
body {
  padding-top: 70px; /* Ώστε το navbar να μην καλύπτει τίποτα */
}
</style>
</head>
<body>
  <!-- MENU -->
  <?php include 'nav.php'; ?>

  <main>
    <div class="container">
      <h1>Επικοινωνία</h1>
      <form action="https://formspree.io/f/mrbavyjg" method="POST">
        <input type="text" name="name" placeholder="Όνομα" required>
        <input type="email" name="email" placeholder="Email" required>
        <textarea name="message" rows="5" placeholder="Μήνυμα" required></textarea>
        <button type="submit">Αποστολή</button>
      </form>
    </div>
  </main>

  <?php include 'footer.html'; ?>

  <script src="navbar.js"></script>
  <script>
    // Εμφάνιση / Απόκρυψη burger menu
    const menuToggle = document.getElementById('menu-toggle');
    const navLinks = document.getElementById('nav-links');

    if(menuToggle){
      menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
      });
    }

    // Μήνυμα αποσύνδεσης fade out
    const msg = document.getElementById('logout-msg');
    if(msg){
        setTimeout(() => {
            msg.style.opacity = '0';
            setTimeout(() => { msg.remove(); }, 1000);
        }, 3000);
    }
  </script>
</body>
</html>
