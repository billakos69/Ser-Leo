<?php
// nav.php
include 'config.php'; // το αρχείο με $conn

// default values
$isLoggedIn = isset($_SESSION['user_id']);
$username = '';
$user_pfp = 'images/default.png'; // put your default here

if ($isLoggedIn) {
    // ασφαλής ανάθεση integer
    $user_id = (int) $_SESSION['user_id'];

    // prepared statement για ασφάλεια
    $stmt = $conn->prepare("SELECT username, user_pfp FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $username = $row['username'] ?? '';
        // αν υπάρχει τιμή στη βάση, χρησιμοποιήσέ την
        if (!empty($row['user_pfp'])) {
            // προαιρετικά: έλεγχος αν το αρχείο υπάρχει
            $candidate = $row['user_pfp'];
            if (file_exists($candidate)) {
                $user_pfp = $candidate;
            } else {
                // αν αποθηκεύεις μονο σχετικές διαδρομές, ίσως χρειαστεί να προσαρμόσεις εδώ
                $user_pfp = $candidate; // ή keep default αν προτιμάς
            }
        }
    }
    $stmt->close();
}
?>
<!-- HTML navigation -->
<section id="menu-section">
  <nav>
    <div class="logo">SER LEO</div>

    <!-- Burger Icon -->
    <div class="menu-toggle" id="menu-toggle">☰</div>

    <ul id="nav-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="team.php">Ομάδα</a></li>
      <li><a href="news.php">Ειδήσεις</a></li>
      <li><a href="shop.php">Κατάστημα</a></li>
      <li><a href="league.php">Πρωτάθλημα</a></li>
      <li><a href="tickets.php">Εισιτήρια</a></li>

      <!-- USER MENU -->
      <li class="user-menu relative group">
        <img src="<?= htmlspecialchars($user_pfp) ?>" alt="User Avatar" 
             class="w-10 h-10 rounded-full object-cover border-2 border-yellow-400 cursor-pointer transition-transform transform group-hover:scale-110">

        <ul class="dropdown absolute right-0 mt-2 bg-black bg-opacity-90 rounded-lg shadow-lg hidden group-hover:block p-2 min-w-[150px] text-sm">
          <?php if ($isLoggedIn): ?>
            <li class="px-3 py-2 text-yellow-300 font-semibold border-b border-gray-700"><?= htmlspecialchars($username) ?></li>
            <li><a href="account.php" class="block px-3 py-2 text-gray-300 hover:text-yellow-300">Account</a></li>
            <li><a href="logout.php" class="block px-3 py-2 text-gray-300 hover:text-yellow-300">Αποσύνδεση</a></li>
          <?php else: ?>
            <li><a href="login.php" class="block px-3 py-2 text-gray-300 hover:text-yellow-300">Σύνδεση</a></li>
            <li><a href="signup.php" class="block px-3 py-2 text-gray-300 hover:text-yellow-300">Εγγραφή</a></li>
          <?php endif; ?>
        </ul>
      </li>
    </ul>
  </nav>
</section>

<!-- Στυλ (μπορείς να το βάλεις style.css αντί εδώ) -->
<style>

.user-menu { position:relative; display:flex; align-items:center;}
.user-menu img { width:40px; height:40px; }
.dropdown { display:none; }
.user-menu:hover .dropdown { display:block; }
</style>
