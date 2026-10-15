<?php
session_start();
include 'config.php';
$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;

if(isset($_GET['match_id'])) {
    $id = (int)$_GET['match_id'];
    $stmt = $conn->prepare("
        SELECT m.*, 
               th.name AS home_name, th.logo AS home_logo,
               ta.name AS away_name, ta.logo AS away_logo,
               ms.ball_possession_home, ms.ball_possession_away, 
               ms.total_shots_home, ms.total_shots_away,
               ms.corner_kicks_home, ms.corner_kicks_away,
               ms.fouls_home, ms.fouls_away
        FROM matches m
        JOIN teams th ON m.home_team = th.id
        JOIN teams ta ON m.away_team = ta.id
        LEFT JOIN match_stats ms ON m.id = ms.match_id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    
    if($match) {
        echo json_encode($match);
    } else {
        echo json_encode(['error'=>'Match not found']);
    }
    exit;
}

// ===================
// JSON request για συγκεκριμένο αγώνα
// ===================
if(isset($_GET['match_id'])) {
    $id = (int)$_GET['match_id'];
    $stmt = $conn->prepare("
        SELECT m.*, 
               th.name AS home_name, th.logo AS home_logo,
               ta.name AS away_name, ta.logo AS away_logo
        FROM matches m
        JOIN teams th ON m.home_team = th.id
        JOIN teams ta ON m.away_team = ta.id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    
    if($match){
        $match['stats'] = [
            'home_shots' => rand(5,15),
            'away_shots' => rand(5,15),
            'yellow_cards' => rand(0,5),
            'red_cards' => rand(0,2)
        ];
        echo json_encode($match);
    } else {
        echo json_encode(['error'=>'Match not found']);
    }
    exit;
}

// ===================
// Pop-up JSON Request για ομάδα
// ===================
if(isset($_GET['team_id'])) {
    $id = (int)$_GET['team_id'];
    $stmt = $conn->prepare("SELECT id, name, logo, coach, founded, stadium FROM teams WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    if($team) {
        echo json_encode($team);
    } else {
        echo json_encode(['error'=>'Team not found']);
    }
    exit;
}

// ===================
// Κανονική σελίδα league.php
// ===================

// Υπολογισμός βαθμών (3 νίκες, 1 ισοπαλία)
$result = $mysqli->query("
    SELECT 
        t.id, t.name, t.logo,
        COUNT(m.id) AS played,
        SUM(CASE WHEN (t.id = m.home_team AND m.home_score > m.away_score) OR 
                     (t.id = m.away_team AND m.away_score > m.home_score) THEN 1 ELSE 0 END) AS wins,
        SUM(CASE WHEN m.home_score = m.away_score THEN 1 ELSE 0 END) AS draws,
        SUM(CASE WHEN (t.id = m.home_team AND m.home_score < m.away_score) OR 
                     (t.id = m.away_team AND m.away_score < m.home_score) THEN 1 ELSE 0 END) AS losses,
        SUM(CASE WHEN t.id = m.home_team THEN m.home_score - m.away_score
                 WHEN t.id = m.away_team THEN m.away_score - m.home_score END) AS gd,
        SUM(CASE WHEN (t.id = m.home_team AND m.home_score > m.away_score) OR 
                     (t.id = m.away_team AND m.away_score > m.home_score) THEN 3
                 WHEN m.home_score = m.away_score THEN 1 ELSE 0 END) AS points
    FROM teams t
    LEFT JOIN matches m ON t.id = m.home_team OR t.id = m.away_team
    GROUP BY t.id
    ORDER BY points DESC, gd DESC
");
$teams = $result->fetch_all(MYSQLI_ASSOC);

// Τελευταίοι αγώνες
$recent_matches = $mysqli->query("
    SELECT m.*, 
           th.name as home_name, th.logo as home_logo, 
           ta.name as away_name, ta.logo as away_logo
    FROM matches m
    JOIN teams th ON m.home_team = th.id
    JOIN teams ta ON m.away_team = ta.id
    WHERE m.home_score IS NOT NULL AND m.away_score IS NOT NULL
    ORDER BY match_date DESC
    LIMIT 2
")->fetch_all(MYSQLI_ASSOC);

// Επόμενοι αγώνες
$upcoming_matches = $mysqli->query("
    SELECT m.*, 
           th.name as home_name, ta.name as away_name
    FROM matches m
    JOIN teams th ON m.home_team = th.id
    JOIN teams ta ON m.away_team = ta.id
    WHERE m.match_date >= CURDATE()
    ORDER BY match_date ASC
    LIMIT 2
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Πρωτάθλημα</title>
<link rel="icon" href="images/logo.png" type="image/png">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
<style>
/* ---------------- Popups ---------------- */
.match-popup, .team-popup { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.9); justify-content: center; align-items: center; z-index: 2000; }
.popup-content { background:#1f1f1f; padding:20px; border-radius:12px; text-align:center; position:relative; color:#fff; max-width:500px; width:90%; box-shadow: 0 0 20px rgba(255,185,94,0.6);}
.popup-content img { object-fit: contain; width:100px; margin-bottom:15px; filter: drop-shadow(0 0 10px #bc8a31);}
.popup-content img:hover { transform: scale(1.05); transition: all 0.3s ease;}
.popup-content button { margin-top:12px; padding:8px 16px; background:#bc8a31; border:none; border-radius:6px; font-weight:bold; color:#111; cursor:pointer; transition:0.3s;}
.popup-content button:hover { background:#ffcc66; }
.close-btn { position:absolute; top:12px; right:18px; font-size:2rem; cursor:pointer; color:#fff; transition:0.3s;}
.close-btn:hover { color:#bc8a31; }

/* ---------------- Πίνακας ---------------- */
table { width:90%; margin:50px auto; border-collapse: collapse; box-shadow:0 0 20px rgba(0,0,0,0.5);}
thead th { background:#1f1f1f; color:#bc8a31; padding:12px; text-align:center; }
tbody td { background:#111; padding:12px; border-bottom:1px solid #333; text-align:center; }
tbody td:first-child { text-align:left; }
tbody tr:hover { background:rgba(255,185,94,0.2); cursor:pointer; transition:0.3s;}
.team-logo { width:50px; height:50px; object-fit:contain; margin-right:10px; vertical-align:middle; box-shadow:0 0 8px rgba(255,185,94,0.6); }

/* ---------------- Matches ---------------- */
.matches { max-width:800px; margin:50px auto;}
.matches h3 { color:#bc8a31; margin-bottom:10px; }
.matches div { background:#111; padding:12px; margin-bottom:8px; border-radius:10px; display:flex; align-items:center; justify-content:space-between; transition:0.3s; cursor:pointer;}
.matches div:hover { background: rgba(255,185,94,0.2);}
.matches img { width:30px; height:30px; object-fit:contain; margin:0 10px; }

/* Responsive */
@media screen and (max-width:600px){
    .popup-content { padding:20px; }
    .popup-content img { width:40px; height:40px; margin:0 5px; }
    .popup-content p { font-size:0.9rem; }
}

.match-content { 
    background:#1f1f1f; 
    padding:25px; 
    border-radius:15px; 
    max-width:550px; 
    width:90%; 
    color:#fff;
    box-shadow: 0 0 25px rgba(255,185,94,0.6);
    font-family: "Inter", sans-serif;
}
.match-header { display:flex; align-items:center; justify-content:center; margin-bottom:15px; }
.match-header img { width:70px; height:70px; object-fit:contain; margin:0 15px; }
.match-score { font-size:1.8rem; font-weight:bold; color:#bc8a31; margin:0 10px; }
.match-date { text-align:center; color:#aaa; margin-bottom:20px; font-size:0.95rem; }
/* Container για τα Στατιστικά */
/* Στυλ για την περιοχή των στατιστικών */
/* Στυλ για τα Στατιστικά */
/* Ευθυγράμμιση των στοιχείων στον πίνακα των στατιστικών στο match popup */
/* Στυλ για την περιοχή των στατιστικών */
.match-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 20px;
    font-size: 16px;
}

.stat {
    display: flex;
    flex-direction: column;
    align-items: center; /* Κεντράρει τα περιεχόμενα κάθε στατιστικού */
    justify-content: center;
    padding: 10px;
    background-color: #333;
    border-radius: 5px;
}

.stat-name {
    font-weight: bold;
    color: #ccc;
    font-size: 14px;
    margin-bottom: 5px;
}

.stat-value {
    font-size: 18px;
    font-weight: bold;
    color: #f0f0f0;
    background-color: #6e5203ff; /* Χρώμα για να ξεχωρίζει */
    padding: 5px 10px;
    border-radius: 5px;
}

.stat:last-child {
    border-bottom: none; /* Χωρίς γραμμή στην τελευταία γραμμή */
}

.stat-name {
    color: #f0ad4e;
    font-size: 14px;
    font-weight: normal;
}



.stat-item:hover {
    background-color: #444; /* Ελαφρύ highlight όταν περνάει το ποντίκι */
}


.stat-item .team-name {
    font-weight: bold;
    color: #f0ad4e;
}




/* Κεντράρισμα της γραμμής για τις στατιστικές */
.stat-row:hover {
    background-color: #444; /* Ελαφρύ highlight όταν περνάει το ποντίκι */
}

/* Στυλ για τα στατιστικά με κεντρικό alignment */
.match-popup {
    display: none;  /* Ξεκινάει με 'none' για να είναι κρυφό */
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 20px;
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.match-popup.show {
    display: flex;  /* Εμφανίζεται με την κλάση 'show' */
    flex-direction: column;
    align-items: center; /* Κεντράρει τα στοιχεία */
    justify-content: center;
}

.match-header {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
}

.match-header img {
    max-width: 50px;
    max-height: 50px;
    margin: 0 10px;
}

.match-score {
    font-size: 30px;
    font-weight: bold;
    color: #f0f0f0;
    margin: 0 10px;
}

.match-date {
    margin-top: 10px;
    font-size: 14px;
    color: #ccc;
}



.match-popup .match-header img {
    width: 80px;
    height: 80px;
    object-fit: contain;
    margin: 0 15px;
}

.match-popup .match-score {
    font-size: 2rem;
    font-weight: bold;
    color: #f0ad4e;
    margin: 0 10px;
}

.match-popup .match-date {
    color: #aaa;
    font-size: 1rem;
    margin-bottom: 20px;
}


/* Περιεχόμενο του popup */
#match-popup-body {
    margin-top: 20px;
    font-size: 16px;
    color: #333;
    line-height: 1.5;
}



/* Στυλ για τα κουμπιά */
button.more-btn {
    background-color: #444;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.3s ease;
}

button.more-btn a {
    color: white;
    text-decoration: none;
}

button.more-btn:hover {
    background-color: #666;
}

#extra-stats { display:none; margin-top:12px; padding:12px; background:#111; border-radius:10px; }
</style>
</head>
<body>

<!-- MENU -->
<?php include 'nav.php'; ?>


<h1 style="text-align:center;color:#bc8a31;margin-top:90px;">Πρωτάθλημα</h1>

<!-- Πίνακας -->
<table>
<thead>
<tr>
    <th>Ομάδα</th>
    <th>P</th>
    <th>N</th>
    <th>I</th>
    <th>H</th>
    <th>GD</th>
    <th>Pts</th>
</tr>
</thead>
<tbody>
<?php foreach($teams as $team): ?>
<tr class="team-row" data-id="<?= $team['id'] ?>">
    <td data-label="Ομάδα"><img src="<?= $team['logo'] ?>" class="team-logo"><?= $team['name'] ?></td>
    <td data-label="P"><?= $team['played'] ?></td>
    <td data-label="N"><?= $team['wins'] ?></td>
    <td data-label="I"><?= $team['draws'] ?></td>
    <td data-label="H"><?= $team['losses'] ?></td>
    <td data-label="GD"><?= $team['gd'] ?></td>
    <td data-label="Pts"><?= $team['points'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- Τελευταίοι και Επόμενοι Αγώνες -->
<div class="matches">
<h3>Τελευταίοι αγώνες</h3>
<?php foreach($recent_matches as $m): ?>
<div class="recent-match" data-id="<?= $m['id'] ?>">
    <img src="<?= $m['home_logo'] ?>" class="team-logo"><?= $m['home_score'] ?>
    -<?= $m['away_score'] ?><img src="<?= $m['away_logo'] ?>" class="team-logo">
    (<?= $m['match_date'] ?>)
</div>
<?php endforeach; ?>

<h3>Επόμενοι αγώνες</h3>
<?php foreach($upcoming_matches as $m): ?>
<div><?= $m['home_name'] ?> vs <?= $m['away_name'] ?> (<?= $m['match_date'] ?>)</div>
<?php endforeach; ?>
</div>

<!-- Popups -->
<div class="team-popup" id="team-popup">
<div class="popup-content">
<span class="close-btn" id="close-popup">&times;</span>
<div id="popup-body"></div>
</div>
</div>

<div class="match-popup" id="match-popup">
  <div class="popup-content match-content">
    <span class="close-btn" id="close-match-popup">&times;</span>
    <div id="match-popup-body"></div>
  </div>
</div>

<script>
// Burger menu
document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.getElementById('menu-toggle');
    const navLinks = document.getElementById('nav-links');
    const userMenu = document.querySelector('.user-menu');

    menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
    });
    userMenu.addEventListener('click', e => { e.stopPropagation(); userMenu.classList.toggle('active'); });
    document.addEventListener('click', ()=> userMenu.classList.remove('active'));

    // ---------------- Team Popup ----------------
    const rows = document.querySelectorAll('.team-row');
    const popup = document.getElementById('team-popup');
    const popupBody = document.getElementById('popup-body');
    const closeBtn = document.getElementById('close-popup');

    rows.forEach(row=>{
        row.addEventListener('click', ()=>{
            const teamId = row.dataset.id;
            if(!teamId) return;
            fetch(`league.php?team_id=${teamId}`)
            .then(res=>res.json())
            .then(data=>{
                popupBody.innerHTML = `<h2>${data.name}</h2>
                    <img src="${data.logo}">
                    <p>Προπονητής: ${data.coach}</p>
                    <p>Έτος Ίδρυσης: ${data.founded}</p>
                    <p>Στάδιο: ${data.stadium}</p>`;
                popup.style.display='flex';
            });
        });
    });
    closeBtn.addEventListener('click', ()=> popup.style.display='none');
    window.addEventListener('click', e=> { if(e.target==popup) popup.style.display='none'; });

    // ---------------- Match Popup ----------------
// ---------------- Match Popup ----------------
// ---------------- Match Popup ----------------
const recentRows = document.querySelectorAll('.recent-match');
const matchPopup = document.getElementById('match-popup');
const matchPopupBody = document.getElementById('match-popup-body');
const closeMatchBtn = document.getElementById('close-match-popup');

recentRows.forEach(row => {
    row.addEventListener('click', () => {
        const matchId = row.dataset.id;
        if (!matchId) return;
        fetch(`league.php?match_id=${matchId}`)
            .then(res => res.json())
            .then(data => {
    if (data.error) {
        alert(data.error); 
        return;
    }
    matchPopupBody.innerHTML = `
        <div class="match-header">
            <img src="${data.home_logo}" alt="${data.home_name}">
            <div class="match-score">${data.home_score} - ${data.away_score}</div>
            <img src="${data.away_logo}" alt="${data.away_name}">
        </div>
        <div class="match-date">Ημερομηνία: ${data.match_date}</div>
        <div class="match-stats">
            <section class="stat">
                <div class="stat-name">${data.home_name} Κατοχή</div>
                <div class="stat-value">${data.ball_possession_home}%</div>
            </section>
            <section class="stat">
                <div class="stat-name">${data.away_name} Κατοχή</div>
                <div class="stat-value">${data.ball_possession_away}%</div>
            </section>

            <section class="stat">
                <div class="stat-name">${data.home_name} Σουτ</div>
                <div class="stat-value">${data.total_shots_home}</div>
            </section>
            <section class="stat">
                <div class="stat-name">${data.away_name} Σουτ</div>
                <div class="stat-value">${data.total_shots_away}</div>
            </section>

            <section class="stat">
                <div class="stat-name">${data.home_name} Κόρνερ</div>
                <div class="stat-value">${data.corner_kicks_home}</div>
            </section>
            <section class="stat">
                <div class="stat-name">${data.away_name} Κόρνερ</div>
                <div class="stat-value">${data.corner_kicks_away}</div>
            </section>

            <section class="stat">
                <div class="stat-name">${data.home_name} Φάουλ</div>
                <div class="stat-value">${data.fouls_home}</div>
            </section>
            <section class="stat">
                <div class="stat-name">${data.away_name} Φάουλ</div>
                <div class="stat-value">${data.fouls_away}</div>
            </section>
        </div>

        <button class="more-btn">
            <a href="more_gamestats.php?match_id=${matchId}">Περισσότερα Στατιστικά</a>
        </button>
    `;
    matchPopup.style.display = 'flex';
});

    });
});







closeMatchBtn.addEventListener('click', ()=> matchPopup.style.display='none');
window.addEventListener('click', e=> { if(e.target==matchPopup) matchPopup.style.display='none'; });
});
</script>

<?php include 'footer.html'; ?>
<script src="navbar.js"></script>
</body>
</html>
