<?php
session_start();
include 'config.php';

if (!isset($_GET['id'])) die("❌ Δεν βρέθηκε παίκτης.");

$id = (int)$_GET['id'];
$sql = "SELECT * FROM players WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$id);
$stmt->execute();
$result = $stmt->get_result();
$player = $result->fetch_assoc();
if (!$player) die("❌ Ο παίκτης δεν υπάρχει.");
$flag_url = !empty($player['country_flag']) ? $player['country_flag'] : "flags/placeholder.png";

$strengths = [];
$strength_res = $conn->prepare("SELECT stat_name FROM player_strengths WHERE player_id = ?");
$strength_res->bind_param("i", $id);
$strength_res->execute();
$res_s = $strength_res->get_result();
while($row = $res_s->fetch_assoc()) {
    $strengths[] = $row['stat_name'];
}


// Παίρνουμε όλους τους παίκτες για Compare
$all_players = [];
$res = $conn->query("SELECT id,name,image,position,matches,goals,assists,xgoals FROM players");
while($row = $res->fetch_assoc()) $all_players[] = $row;

$positions = ['GK'=>'Τερματοφύλακας','DEF'=>'Αμυντικός','MID'=>'Μέσος','ATT'=>'Επιθετικός'];
$shots = !empty($player['shot']) ? json_decode($player['shot'], true) : [];
$heatmap = !empty($player['heatmap']) ? $player['heatmap'] : '';

// Extra stats
$extra_stats = [
    'GK'=>['goals_conceded'=>'Goals Conceded','save_percentage'=>'Save %','punches'=>'Punches','pass_accuracy'=>'Pass Accuracy %'],
    'DEF'=>['pass_accuracy'=>'Pass Accuracy %','tackles'=>'Tackles','interceptions'=>'Interceptions','clearances'=>'Clearances','blocks'=>'Blocks','key_passes'=>'Key Passes','duels_won'=>'Duels Won','shots'=>'Shots','shots_on_target'=>'Shots on Target','shot_accuracy'=>'Shot Accuracy %','xassists'=>'xAssists', 'top_speed'=>'Top Speed'],
    'MID'=>['pass_accuracy'=>'Pass Accuracy %','tackles'=>'Tackles','interceptions'=>'Interceptions','clearances'=>'Clearances','blocks'=>'Blocks','key_passes'=>'Key Passes','duels_won'=>'Duels Won','shots'=>'Shots','shots_on_target'=>'Shots on Target','shot_accuracy'=>'Shot Accuracy %','xassists'=>'xAssists','dribbles'=>'Dribbles','successful_dribbles'=>'Successful Dribbles', 'top_speed'=>'Top Speed'],
    'ATT'=>['pass_accuracy'=>'Pass Accuracy %','tackles'=>'Tackles','interceptions'=>'Interceptions','clearances'=>'Clearances','blocks'=>'Blocks','key_passes'=>'Key Passes','duels_won'=>'Duels Won','shots'=>'Shots','shots_on_target'=>'Shots on Target','shot_accuracy'=>'Shot Accuracy %','xassists'=>'xAssists','dribbles'=>'Dribbles','successful_dribbles'=>'Successful Dribbles', 'top_speed'=>'Top Speed']
];

// Performance metrics
$performance_metrics = [
    'GK'=>['Save %'=>'save_percentage','Goals Conceded'=>'goals_conceded','Punches'=>'punches','Pass Accuracy'=>'pass_accuracy'],
    'DEF'=>['Pass Accuracy'=>'pass_accuracy','Tackles'=>'tackles','Interceptions'=>'interceptions','Clearances'=>'clearances','Duels Won'=>'duels_won','Shot Accuracy'=>'shot_accuracy','Successful Dribbles'=>'successful_dribbles'],
    'MID'=>['Pass Accuracy'=>'pass_accuracy','Key Passes'=>'key_passes','Assists'=>'assists','Shot Accuracy'=>'shot_accuracy','xAssists'=>'xassists','Dribbles'=>'dribbles','Successful Dribbles'=>'successful_dribbles'],
    'ATT'=>['Goals'=>'goals','Assists'=>'assists','Shot Accuracy'=>'shot_accuracy','xGoals'=>'xgoals','xAssists'=>'xassists','Dribbles'=>'dribbles','Successful Dribbles'=>'successful_dribbles', 'Top Speed'=>'top_speed']
];

$performance_labels = array_keys($performance_metrics[$player['position']]);
$performance_values = [];
foreach($performance_metrics[$player['position']] as $label => $field){
    $performance_values[] = $player[$field] ?? 0;
}

// Form Last 5 Games
$form_metrics = [];
$weights = [];
$stats = [];
$max_values = [];

switch($player['position']){
    case 'GK':
        $stats = ['save_percentage','goals_conceded','clean_sheets'];
        $weights = [0.5, -0.3, 0.2];
        $max_values = ['save_percentage'=>100,'goals_conceded'=>10,'clean_sheets'=>10];
        break;
    case 'DEF':
        $stats = ['tackles','interceptions','clearances','pass_accuracy'];
        $weights = [0.3,0.3,0.2,0.2];
        $max_values = ['tackles'=>10,'interceptions'=>10,'clearances'=>10,'pass_accuracy'=>100];
        break;
    case 'MID':
        $stats = ['key_passes','assists','pass_accuracy','dribbles'];
        $weights = [0.25,0.25,0.25,0.25];
        $max_values = ['key_passes'=>10,'assists'=>5,'pass_accuracy'=>100,'dribbles'=>10];
        break;
    case 'ATT':
        $stats = ['goals','assists','shot_accuracy','xgoals','dribbles'];
        $weights = [0.3,0.2,0.2,0.2,0.1];
        $max_values = ['goals'=>5,'assists'=>5,'shot_accuracy'=>100,'xgoals'=>5,'dribbles'=>10];
        break;
}

// Υπολογισμός μέσου score
$base_score = 0;
for($i=0;$i<count($stats);$i++){
    $val = $player[$stats[$i]] ?? 0;
    $max_val = $max_values[$stats[$i]] ?? 100;
    $normalized = min(100, ($val/$max_val)*100);
    $base_score += $normalized * $weights[$i];
}

// Δημιουργία καμπύλης για 5 παιχνίδια
mt_srand($player['id']); // σταθερό random
$prev = max(40, min(80, round($base_score))); // αρχική τιμή όχι πολύ ψηλά
for($i=0;$i<5;$i++){
    $change = mt_rand(-8,8); // μεγαλύτερη διακύμανση
    $value = max(40, min(90, $prev + $change)); // όρια 40-90
    $form_metrics[] = $value;
    $prev = $value; // επόμενη τιμή βασίζεται στην προηγούμενη για smoothness
}


// Efficiency metrics
$efficiency_labels = [];
$efficiency_values = [];
switch($player['position']){
    case 'GK':
        $efficiency_labels = ['Saves/Match','Goals Conceded/Match'];
        $efficiency_values = [$player['matches']>0?round($player['saves']/$player['matches'],2):0,$player['matches']>0?round($player['goals_conceded']/$player['matches'],2):0];
        break;
    case 'DEF':
        $efficiency_labels = ['Tackles/Match','Interceptions/Match'];
        $efficiency_values = [$player['matches']>0?round($player['tackles']/$player['matches'],2):0,$player['matches']>0?round($player['interceptions']/$player['matches'],2):0];
        break;
    case 'MID':
        $efficiency_labels = ['Assists/Match','Key Passes/Match'];
        $efficiency_values = [$player['matches']>0?round($player['assists']/$player['matches'],2):0,$player['matches']>0?round($player['key_passes']/$player['matches'],2):0];
        break;
    case 'ATT':
        $efficiency_labels = ['Goals/Match','Assists/Match'];
        $efficiency_values = [$player['matches']>0?round($player['goals']/$player['matches'],2):0,$player['matches']>0?round($player['assists']/$player['matches'],2):0];
        break;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($player['name']) ?> - Player Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="images/logo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{margin:0;font-family:'Poppins',sans-serif;background:#0a0a0f;color:#eee;}
.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 25px;
    background: #111;
    border-radius: 20px;
    box-shadow: 0 0 15px #bc8a31,
                0 0 30px #bc8a31,
                0 0 45px #bc8a31;
}
.back-button{color:#bc8a31;text-decoration:none;font-weight:600;display:inline-block;margin-bottom:20px;}
.back-button:hover{color:#664305;}
.header{display:flex;flex-wrap:wrap;gap:20px;align-items:center;margin-bottom:30px;}
.header img{max-width:300px;border-radius:31px;}
.header-info{flex:1;}
.header-info h1{margin:0;color:#ffcc00;}
.header-info p{margin:5px 0;}
.section-flex{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:30px;justify-content:center;}
.card{flex:1;min-width:280px;background:rgba(255,255,255,0.05);border-radius:15px;padding:20px;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:15px;margin-top:10px;}
.stat-box{background:linear-gradient(145deg,#1a1a1f,#111115);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:12px;text-align:center;transition:all 0.25s ease;}
.stat-box:hover{background:linear-gradient(145deg,#222228,#111115);transform:translateY(-3px);}
.stat-box h4{font-size:0.85rem;color:#ffcc00;margin-bottom:6px;}
.stat-box p{font-size:1.2rem;font-weight:600;color:#fff;}
canvas{background:rgba(0,0,0,0.2);border-radius:12px;}
.compare-card{flex:1;background:#222;padding:20px;border-radius:15px;color:#fff;}
.compare-card h4{color:#bc8a31;text-align:center;margin-bottom:10px;}
.player-card {
    background: #222;
    border-radius: 15px;
    padding: 10px;
    width: 140px;            /* ✅ σταθερό */
    max-width: 100%;         /* ✅ δεν ξεφεύγει */
    text-align: center;
    color: #fff;
    cursor: pointer;
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
    flex-shrink: 0;          /* ✅ δεν μικραίνει υπερβολικά */
}

.player-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(212, 102, 0, 0.5); }
.player-card img { width: 200px; border-radius: 50%; margin-bottom: 5px; }
.player-card input[type=checkbox] { display:none; }
.remove-player {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 1rem;
    color: #ff0000ff;
    background: #111;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    text-align: center;
    line-height: 20px;
    cursor: pointer;
}
.card {
    position: relative; /* χρειάζεται για το απόλυτο positioning του Χ */
    flex:1;
    min-width:280px;
    background:rgba(255,255,255,0.05);
    border-radius:15px;
    padding:20px;
}

.player-header-img {
    width: 250px;   /* επιθυμητό μέγεθος */
    height: 250px;  /* επιθυμητό μέγεθος */
    border-radius: 50%; /* αν θέλεις στρογγυλό */
    object-fit: cover;  /* για να μην παραμορφώνεται */
}


.compare-player-img {
    display: block;
    margin: 15px auto; /* κεντράρισμα */
    width: 120px;
    height: 120px;
    object-fit: cover; /* διατηρεί αναλογία και γεμίζει το κουτάκι */
    border-radius: 50%;
    box-shadow: 0 0 15px #bc8a31; /* glow effect */
    transition: transform 0.3s, box-shadow 0.3s;
}

.compare-player-img:hover {
    transform: scale(1.1);
    box-shadow: 0 0 25px #bc8a31;
}



.compare-card {
    position: relative;
    flex: 1;
    background:#222;
    padding:20px;
    border-radius:15px;
    color:#fff;
}

.compare-card .close-player {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 1.4rem;
    color: #ff0000ff;
    cursor: pointer;
    background: rgba(17,17,17,0.8);
    border-radius: 50%;
    width: 25px;
    height: 25px;
    text-align: center;
    line-height: 25px;
    transition: background 0.2s;
}
.compare-card .close-player:hover {
    background: #ff0000ff;
    color: #111;
}

/* Modal Background */
/* Modal Background */
#compareModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    z-index: 1000;
    padding: 20px;
    overflow: hidden;  /* ❌ κρατάει τις κάρτες εκτός view */
}


/* Modal Content */
#compareModal > div {
    background: #111; /* σκούρο μαύρο για να φαίνονται όλα τα στοιχεία */
    padding: 30px;
    border-radius: 20px;
    max-width: 1200px;
    margin: 20px auto; /* πιο πάνω */
    position: relative;
    max-height: 90vh; /* scroll μόνο αν χρειαστεί */
    overflow-y: auto;  /* scroll μέσα στο modal */
    box-shadow: 0 0 30px rgba(188, 138, 49, 0.5); /* glow γύρω */
    border: 1px solid #bc8a31; /* όμορφη ένδειξη πλαισίου */
    color: #fff; /* ώστε τα κείμενα να φαίνονται */
}

#compareModal {
    scrollbar-width: thin;          /* Firefox */
    scrollbar-color: #bc8a31 #111; /* thumb | track */
}

#compareModal {
    overflow: auto; /* ✅ επιτρέπει scrolling μέσα στο modal */
}


/* Webkit browsers */
#compareModal::-webkit-scrollbar {
    width: 12px;
}

#compareModal::-webkit-scrollbar-track {
    background: #111;
}

#compareModal::-webkit-scrollbar-thumb {
    background-color: #bc8a31;
    border-radius: 10px;
    border: 3px solid #111;
}

#compareModal::-webkit-scrollbar-thumb:hover {
    background-color: #ffcc00;
}


.stat-star {
    color: #ffcc00;
    font-size: 1rem;
    margin-left: 5px;
}

.star-highlight {
    box-shadow: 0 0 10px rgba(255, 204, 0, 0.5);
    border: 1px solid #ffcc00;
}
#compareModal .compare-players-container {
    display: flex;
    flex-wrap: wrap;         /* ✅ επιτρέπει να πηγαίνουν σε νέα γραμμή */
    gap: 20px;
    justify-content: center; /* ✅ κεντράρει τις κάρτες */
    max-width: 100%;         /* ✅ δεν ξεχειλώνει */
    box-sizing: border-box;
}

.player-card img {
    width: 100%;
    height: auto;
    border-radius: 50%;
    margin-bottom: 5px;
    object-fit: cover;
}

</style>
</head>
<body>
<div class="container">
<a href="team.php" class="back-button">← Πίσω στην Ομάδα</a>

<div class="header">
<img src="<?= htmlspecialchars($player['image']) ?>" alt="<?= htmlspecialchars($player['name']) ?>" class="player-header-img">
<div class="header-info">
<h1><?= htmlspecialchars($player['name']) ?></h1>
<p><strong>Θέση:</strong> <?= $positions[$player['position']] ?></p>
<p><strong>Ηλικία:</strong> <?= $player['age'] ?? '—' ?></p>
<p>
    <strong>Χώρα:</strong> 
    <?= htmlspecialchars($player['country'] ?? '—') ?>
<img src="<?= htmlspecialchars($flag_url) ?>" 
     alt="<?= htmlspecialchars($player['country'] ?? '') ?>" 
     style="width:auto; height:22px; margin-left:5px; vertical-align:middle;">
</p>
<p><strong>Foot:</strong> <?= $player['preferred_foot'] ?? '—' ?> | <strong>Weak Foot:</strong> <?= $player['weak_foot'] ?? '—' ?>/5</p>

<!-- Traits Icons -->
<?php if(!empty($player['traits_icons'])): ?>
<p>
    <?php 
    // Αν το πεδίο είναι comma-separated URLs
    $icons = explode(',', $player['traits_icons']); 
    foreach($icons as $icon_url): 
        $icon_url = trim($icon_url);
        if($icon_url === '') continue;
    ?>
        <img src="<?= htmlspecialchars($icon_url) ?>" 
             alt="Trait Icon" 
             style="width:50px; height:50px; margin-right:5px; vertical-align:middle;">
    <?php endforeach; ?>
</p>
<?php endif; ?>


</div>


</div>

<!-- Compare Button -->
<button id="compareBtn" style="padding:10px 20px;background:#bc8a31;color:#fff;border:none;border-radius:8px;cursor:pointer;">Compare Players</button>

<!-- Compare Modal -->
<div id="compareModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); overflow:auto; z-index:1000; padding:50px;">
    <div style="background:#111; padding:30px; border-radius:15px; max-width:1200px; margin:auto; position:relative;">
        <!-- X για κλείσιμο -->
<span id="closeModal" style="
    position:absolute;
    top:10px;
    right:10px;
    cursor:pointer;
    font-size:1.8rem;
    color:#bc8a31;
    z-index:10;
">×</span>
        <h2 style="color:#bc8a31;">Select Players to Compare</h2>
        <div id="playerList" style="display:flex; flex-wrap:wrap; gap:15px; margin-top:20px;"></div>
        <button id="doCompare" style="margin-top:20px;padding:10px 20px;background:#bc8a31;color:#fff;border:none;border-radius:8px;cursor:pointer;">Compare</button>
        <div id="compareResults" style="display:flex; gap:20px; margin-top:30px; flex-wrap:wrap;"></div>
    </div>
</div>

<div class="section-flex">

    <div class="card">
        <h3>Βασικά Στατιστικά</h3>
        <div class="stats-grid">
        <?php
        $basic_stats = [
            'matches' => 'Συμμετοχές',
            'goals' => 'Γκολ',
            'assists' => 'Ασίστ',
            'xgoals' => 'xGoals',
            'clean_sheets' => 'Clean Sheets',
            'saves' => 'Saves'
        ];

        foreach($basic_stats as $field => $label):
            $is_star = in_array($field, $strengths); // ✅ Έλεγχος για αστέρι
        ?>
            <div class="stat-box<?= $is_star ? ' star-highlight' : '' ?>">
                <h4>
                    <?= htmlspecialchars($label) ?>
                    <?php if ($is_star): ?>
                        <span class="stat-star">⭐</span>
                    <?php endif; ?>
                </h4>
                <p><?= $player[$field] ?? 0 ?></p>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h3>Extra Στατιστικά</h3>
        <div class="stats-grid">
        <?php foreach($extra_stats[$player['position']] as $field => $label): 
            $is_star = in_array($field, $strengths);
        ?>
            <div class="stat-box<?= $is_star ? ' star-highlight' : '' ?>">
                <h4>
                    <?= htmlspecialchars($label) ?>
                    <?php if ($is_star): ?>
                        <span class="stat-star">⭐</span>
                    <?php endif; ?>
                </h4>
                <p><?= $player[$field] ?? 0 ?></p>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

</div>


<div class="section-flex">
<div class="card">
<h3>Heat Map</h3>
<img src="<?= $heatmap ?: 'images/heatmap_placeholder.png' ?>" style="width:100%;">
</div>
<div class="card">
<h3>Shot Map</h3>
<canvas id="shotMap" width="550" height="300"></canvas>
</div>
</div>

<div class="section-flex">
<div class="card">
<canvas id="radarChart"></canvas>
</div>
<div class="card">
<canvas id="barChart"></canvas>
</div>
</div>

<div class="section-flex">
<div class="card">
<h3>Form Last 5 Games</h3>
<canvas id="formChart"></canvas>
</div>
</div>

</div>

<script>

// Shot Map
const canvas = document.getElementById('shotMap');
const ctx = canvas.getContext('2d');
const ballImg = new Image(); ballImg.src = 'images/ball.png';
const gloveImg = new Image(); gloveImg.src = 'images/gloves.png';
const goalImg = new Image(); goalImg.src = 'images/goal.png';
goalImg.onload = function(){
ctx.drawImage(goalImg,0,0,canvas.width,canvas.height);
const shots = <?= json_encode($shots) ?>;
const position = '<?= $player['position'] ?>';
shots.forEach(shot=>{
const px = shot.x/100*canvas.width;
const py = shot.y/100*canvas.height;
if(position==='GK'){
if(shot.goal){ctx.drawImage(gloveImg,px-15,py-15,30,30);}
else{ctx.beginPath();ctx.arc(px,py,10,0,2*Math.PI);ctx.fillStyle='red';ctx.fill();}
}else{
if(shot.goal){ctx.drawImage(ballImg,px-15,py-15,30,30);}
else{ctx.beginPath();ctx.arc(px,py,10,0,2*Math.PI);ctx.fillStyle='red';ctx.fill();}
}
});
};

// Radar Chart
new Chart(document.getElementById('radarChart'),{
type:'radar',
data:{ labels: <?= json_encode($performance_labels) ?>, datasets:[{label:'Performance', data: <?= json_encode($performance_values) ?>, fill:true, borderColor:'#ffcc00', backgroundColor:'rgba(255,204,0,0.3)', pointBackgroundColor:'#ffcc00'}]},
options:{responsive:true, scales:{r:{angleLines:{color:'#333'},grid:{color:'#444'},pointLabels:{color:'#fff'},ticks:{color:'#fff',backdropColor:'#111'}}}, plugins:{legend:{labels:{color:'#fff'}}}}
});

// Bar Chart
new Chart(document.getElementById('barChart'),{
type:'bar',
data:{ labels: <?= json_encode($efficiency_labels) ?>, datasets:[{label:'Efficiency', data: <?= json_encode($efficiency_values) ?>, backgroundColor:['#ffcc00','#00bcd4']}]},
options:{responsive:true, scales:{y:{beginAtZero:true,ticks:{color:'#fff'}}, x:{ticks:{color:'#fff'}}}, plugins:{legend:{labels:{color:'#fff'}}}}
});

// Form Chart
new Chart(document.getElementById('formChart'),{
type:'line',
data:{ labels:['Game 1','Game 2','Game 3','Game 4','Game 5'], datasets:[{label:'Form', data: <?= json_encode($form_metrics) ?>, borderColor:'#00bcd4', backgroundColor:'rgba(0,188,212,0.3)', fill:true, tension:0.4, pointBackgroundColor:'#ffcc00'}]},
options:{responsive:true, scales:{y:{beginAtZero:true,max:100,ticks:{color:'#fff'}},x:{ticks:{color:'#fff'}}}, plugins:{legend:{labels:{color:'#fff'}}}}
});

// Charts (Radar, Bar, Form)
const allPlayers = <?= json_encode($all_players) ?>;

// Compare Modal
const compareBtn = document.getElementById('compareBtn');
const modal = document.getElementById('compareModal');
const closeModal = document.getElementById('closeModal');
const playerList = document.getElementById('playerList');
const doCompare = document.getElementById('doCompare');
const compareResults = document.getElementById('compareResults');

compareBtn.onclick = () => {
    modal.style.display = 'block';
    playerList.innerHTML = '';
    allPlayers.forEach(p => {
        const div = document.createElement('div');
        div.className = 'player-card';
        div.innerHTML = `
            <input type="checkbox" value="${p.id}" id="player_${p.id}">
            <img src="${p.image}" alt="${p.name}">
            <div>${p.name}</div>
        `;

        const checkbox = div.querySelector('input[type=checkbox]');

        // κλικ στην κάρτα toggle checkbox
        div.onclick = e => {
            checkbox.checked = !checkbox.checked;
            div.style.border = checkbox.checked ? '2px solid #bc8a31' : 'none';
        };

        playerList.appendChild(div);
    });
};


// Κλείσιμο modal με X
closeModal.onclick = () => modal.style.display = 'none';

// Compare button
// Compare button
doCompare.onclick = () => {
    const selectedIds = Array.from(playerList.querySelectorAll('input[type=checkbox]:checked')).map(i=>i.value);
    compareResults.innerHTML = ''; // καθαρίζουμε προηγούμενα
    selectedIds.forEach(id => {
        const p = allPlayers.find(x=>x.id==id);
        const card = document.createElement('div');
        card.className = 'compare-card';
        card.innerHTML = `
            <span class="close-player">×</span>
            <h4>${p.name}</h4>
            <img src="${p.image}" class="compare-player-img">
            <p>Position: ${p.position}</p>
            <p>Matches: ${p.matches}</p>
            <p>Goals: ${p.goals}</p>
            <p>Assists: ${p.assists}</p>
            <p>xGoals: ${p.xgoals}</p>
        `;
        compareResults.appendChild(card);

        // JS για να κλείνει η κάρτα του παίκτη
        card.querySelector('.close-player').addEventListener('click', () => {
            card.remove();
            const cb = playerList.querySelector(`input[value='${p.id}']`);
            if (cb) {
                cb.checked = false;
                cb.parentElement.style.border = 'none'; // ✅ ξε-επιλογή οπτικά
            }
        });
    });
};



</script>
</body>
</html>
