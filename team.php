<?php
session_start();
include 'config.php';
$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;

$logoutMessage = '';
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $logoutMessage = "✅ Αποσυνδεθήκατε με επιτυχία!";
}
include 'config.php'; // Σύνδεση DB

// Παίρνουμε όλους τους παίκτες, ταξινομημένους ανά θέση
$sql = "SELECT * FROM players ORDER BY 
        FIELD(position, 'GK','DEF','MID','ATT'), name ASC";
$result = $conn->query($sql);

// Ομαδοποίηση παικτών ανά θέση
$positions = [
    'GK' => 'Τερματοφύλακες',
    'DEF' => 'Αμυντικοί',
    'MID' => 'Μέσοι',
    'ATT' => 'Επιθετικοί'
];

$playersByPos = [];
while ($row = $result->fetch_assoc()) {
    $playersByPos[$row['position']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
  <title>SER LEO F.C</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>

    <link rel="icon" href="images/logo.png" type="image/png">
</head>
<body>
   <style>
    @media (max-width: 768px) {

    .squad-container {
        margin: 100px 10px 30px;
        padding: 10px;
    }

    .position-group h2 {
        font-size: 1.5rem;
        text-align: center;
        margin-bottom: 15px;
    }

    .players-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 12px;
    }

    .player-card {
        text-align: center;
        padding: 8px;
    }

 

    .player-card p {
        font-size: 0.9rem;
        color: #fff;
        margin: 0;
    }

    .player-card:hover img {
        transform: scale(1.05);
    }

    /* Popup */
    .player-popup {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        display: none;
        justify-content: center;
        align-items: center;
        background: rgba(0,0,0,0.95);
        z-index: 9999;
        padding: 20px;
        overflow-y: auto;
    }

    .player-popup .popup-content {
        background: #111;
        padding: 15px;
        border-radius: 12px;
        width: 100%;
        max-width: 400px;
        color: #fff;
    }

    .popup-content h3 {
        font-size: 1.4rem;
        margin-bottom: 10px;
        color: #bc8a31;
    }

    .player-image-container img {
        width: 100%;
        max-height: 250px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .player-stats li {
        font-size: 0.9rem;
        margin-bottom: 6px;
    }

    .close-btn {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 1.8rem;
        color: #bc8a31;
        cursor: pointer;
    }
}

    </style>

<header class="header">
    
<!-- MENU -->
<?php include 'nav.php'; ?>


</header>

<main class="squad-container">
    <?php foreach ($positions as $posKey => $posName): ?>
        <?php if (!empty($playersByPos[$posKey])): ?>
            <section class="position-group">
                <h2><?= $posName ?></h2>
                <div class="players-grid">
                    <?php foreach ($playersByPos[$posKey] as $player): ?>
                        <div class="player-card" 
                             onclick="showPlayerInfo(<?= htmlspecialchars(json_encode($player), ENT_QUOTES, 'UTF-8') ?>)">
                            <img src="<?= htmlspecialchars($player['image']) ?>" 
                                 alt="<?= htmlspecialchars($player['name']) ?>">
                            <p><?= htmlspecialchars($player['name']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</main>

<!-- Popup Πληροφοριών Παίκτη -->

<!-- Popup Πληροφοριών Παίκτη -->
<div class="player-popup" id="player-popup">
    <div class="popup-content">
        <span class="close-btn" onclick="closePlayerInfo()">&times;</span>
        <h3 id="player-name"></h3>
        <div class="player-image-container">
            <img src="" alt="Player Image" id="player-image">
        </div>
        <ul class="player-stats" id="player-stats"></ul>

        <!-- NEW: More button στο κάτω μέρος -->
        <div style="text-align:center; margin-top:12px;">
            <a href="#" id="player-more-link" class="more-btn" style="
                display:inline-block;
                padding:8px 14px;
                background:#bc8a31;
                color:#111;
                border-radius:8px;
                text-decoration:none;
                font-weight:600;
            ">More</a>
        </div>
    </div>
</div>

<script>
let currentPlayerId = null; // αποθηκεύει το id για το More link

function showPlayerInfo(player) {
    document.getElementById('player-name').textContent = player.name;
    document.getElementById('player-image').src = player.image || '';

    currentPlayerId = player.id || null;
    const moreLink = document.getElementById('player-more-link');
    if (currentPlayerId) {
        moreLink.href = 'player_details.php?id=' + encodeURIComponent(currentPlayerId);
        moreLink.style.display = 'inline-block';
    } else {
        moreLink.style.display = 'none';
    }

    let statsHtml = '';

    const stats = {
        'Ηλικία': player.age,
        'Συμμετοχές': player.matches,
        'Γκολ': player.goals,
        'Ασίστ': player.assists,
        'xGoals': player.xgoals,
        'Clean Sheets': player.clean_sheets,
        'Saves': player.saves
    };

    for (const [label, value] of Object.entries(stats)) {
        // εμφάνιση μόνο όταν υπάρχει τιμή και όχι null/undefined/'' 
        if (value !== null && value !== undefined && value !== '' && Number(value) !== 0) {
            statsHtml += `<li><strong>${label}:</strong> ${value}</li>`;
        }
    }

    if (!statsHtml) statsHtml = '<li>Δεν υπάρχουν διαθέσιμα στατιστικά.</li>';

    document.getElementById('player-stats').innerHTML = statsHtml;
    document.getElementById('player-popup').style.display = 'flex';
}

function closePlayerInfo() {
    document.getElementById('player-popup').style.display = 'none';
}

function toggleContactForm() {
    const form = document.getElementById('contactFormBubble');
    form.style.display = form.style.display === 'block' ? 'none' : 'block';
}

 // Εμφάνιση / Απόκρυψη burger menu
  const menuToggle = document.getElementById('menu-toggle');
  const navLinks = document.getElementById('nav-links');

  menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
  });

</script>

<?php include 'footer.html'; ?>

<script src="navbar.js"></script>
</footer>
</body>
</html>
