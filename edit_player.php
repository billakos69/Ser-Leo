<?php
session_start();
include 'config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) { 
    header("Location:index.php"); 
    exit; 
}

// Παίκτης που επεξεργαζόμαστε
$id = $_GET['id'] ?? null;

// Αρχικά πεδία
$fields = [
    'name'=>'', 'position'=>'', 'age'=>null, 'matches'=>null, 'goals'=>null, 'assists'=>null,
    'xgoals'=>null, 'clean_sheets'=>null, 'saves'=>null, 'image'=>'',
    'goals_conceded'=>null, 'save_percentage'=>null, 'punches'=>null, 'pass_accuracy'=>null,
    'tackles'=>null, 'interceptions'=>null, 'clearances'=>null, 'blocks'=>null,
    'key_passes'=>null, 'duels_won'=>null, 'shots'=>null, 'shots_on_target'=>null,
    'shot_accuracy'=>null, 'xassists'=>null, 'dribbles'=>0, 'successful_dribbles'=>0,
    'top_speed'=>null, 'heatmap'=>'', 'country'=>'', 'country_flag'=>'', 'shot'=>'',
    'traits_icons'=>'', 'preferred_foot'=>'Right', 'weak_foot'=>1
];

$message = '';
$player_strengths = [];

// --- Φόρτωση υπάρχοντος παίκτη ---
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM players WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    if ($player) {
        foreach ($fields as $k => &$v) {
            $v = $player[$k] ?? $v;
        }

        // strengths
        $res = $conn->prepare("SELECT stat_name FROM player_strengths WHERE player_id=?");
        $res->bind_param("i", $id);
        $res->execute();
        $result = $res->get_result();
        while ($row = $result->fetch_assoc()) {
            $player_strengths[] = $row['stat_name'];
        }
    } else {
        $message = "Ο παίκτης δεν βρέθηκε.";
        $id = null;
    }
}

// --- POST handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $k => &$v) {
        if (isset($_POST[$k])) {
            if (in_array($k,['xgoals','save_percentage','pass_accuracy','shot_accuracy','xassists','top_speed'])) {
                $v = $_POST[$k]!=='' ? floatval($_POST[$k]) : null;
            } elseif (in_array($k,['age','matches','goals','assists','clean_sheets','saves','goals_conceded','punches',
                                   'tackles','interceptions','clearances','blocks','key_passes','duels_won',
                                   'shots','shots_on_target','dribbles','successful_dribbles','weak_foot'])) {
                $v = $_POST[$k]!=='' ? intval($_POST[$k]) : null;
            } else {
                $v = trim($_POST[$k]);
            }
        }
    }

    if ($fields['name']==='') {
        $message = "Το όνομα είναι υποχρεωτικό.";
    } else {
        if ($id) {
            $sql = "UPDATE players SET
                name=?, position=?, age=?, matches=?, goals=?, assists=?, xgoals=?, clean_sheets=?, saves=?,
                image=?, goals_conceded=?, save_percentage=?, punches=?, pass_accuracy=?, tackles=?, interceptions=?, clearances=?,
                blocks=?, key_passes=?, duels_won=?, shots=?, shots_on_target=?, shot_accuracy=?, xassists=?, dribbles=?, successful_dribbles=?,
                heatmap=?, country=?, country_flag=?, shot=?, preferred_foot=?, weak_foot=?, top_speed=?, traits_icons=? WHERE id=?";
        } else {
            $sql = "INSERT INTO players
                (name, position, age, matches, goals, assists, xgoals, clean_sheets, saves, image,
                 goals_conceded, save_percentage, punches, pass_accuracy, tackles, interceptions, clearances,
                 blocks, key_passes, duels_won, shots, shots_on_target, shot_accuracy, xassists, dribbles, successful_dribbles,
                 heatmap, country, country_flag, shot, preferred_foot, weak_foot, top_speed, traits_icons)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        }

        $stmt = $conn->prepare($sql);
        $types = "ssiiiidiisididiiiiiiiidddsssssi";
        $params = [
    $fields['name'],$fields['position'],$fields['age'],$fields['matches'],$fields['goals'],$fields['assists'],
    $fields['xgoals'],$fields['clean_sheets'],$fields['saves'],$fields['image'],
    $fields['goals_conceded'],$fields['save_percentage'],$fields['punches'],$fields['pass_accuracy'],
    $fields['tackles'],$fields['interceptions'],$fields['clearances'],
    $fields['blocks'],$fields['key_passes'],$fields['duels_won'],$fields['shots'],$fields['shots_on_target'],
    $fields['shot_accuracy'],$fields['xassists'],$fields['dribbles'],$fields['successful_dribbles'],
    $fields['heatmap'],$fields['country'],$fields['country_flag'],$fields['shot'],
    $fields['preferred_foot'],$fields['weak_foot'],$fields['top_speed'],$fields['traits_icons']
];

$types = '';
foreach($params as $p){
    if(is_int($p) || is_null($p)) $types .= 'i';
    elseif(is_float($p)) $types .= 'd';
    else $types .= 's';
}

if($id){ 
    $types .= 'i'; 
    $params[] = $id;
}

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        if (!$id) $id = $stmt->insert_id;

        $message = $id ? "✅ Ο παίκτης αποθηκεύτηκε επιτυχώς!" : "❌ Σφάλμα αποθήκευσης.";

        // --- strengths ---
        $selected_strengths = $_POST['strengths'] ?? [];
        $stmt_del = $conn->prepare("DELETE FROM player_strengths WHERE player_id=?");
        $stmt_del->bind_param("i",$id); $stmt_del->execute();

        if (!empty($selected_strengths)) {
            $stmt_ins = $conn->prepare("INSERT INTO player_strengths (player_id, stat_name) VALUES (?,?)");
            foreach ($selected_strengths as $s) {
                $stmt_ins->bind_param("is",$id,$s);
                $stmt_ins->execute();
            }
        }
        $player_strengths = $selected_strengths;
    }
}

// Shot map
$shot_data = json_decode($fields['shot'] ?: '[]', true);
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $id?"Επεξεργασία Παίκτη":"Προσθήκη Παίκτη" ?></title>
<style>
/* απλοποιημένο στυλ */
body { font-family:sans-serif; background:#1a1a1a; color:#fff; margin:0; padding:0;}
.container { max-width:1200px; margin:40px auto; background:#3e2f20; padding:30px; border-radius:15px;}
h1,h3{color:#ffd966;}
.form-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:15px;}
input, select{width:100%; padding:8px; border-radius:8px; background:#2c2c2c; color:#fff; border:1px solid #ccc;}
input[type="submit"],.back{padding:12px 20px; border:none; border-radius:8px; cursor:pointer; color:#fff; margin-top:15px; display:inline-block; text-decoration:none;}
input[type="submit"]{background:#007bff;}
.back{background:#6c757d;}
.goal-area{position:relative;width:100%;max-width:600px;aspect-ratio:2/1;background:url('images/goal.png') center/contain no-repeat;margin-top:30px;border:3px solid #ffd966;border-radius:12px;}
.shot-marker{position:absolute;width:22px;height:22px;border-radius:50%;cursor:pointer;}
.shot-marker.goal{background:#28a745;}
.shot-marker.miss{background:#dc3545;}
</style>
</head>
<body>
<div class="container">
<h1><?= $id ? "Επεξεργασία Παίκτη" : "Προσθήκη Παίκτη" ?></h1>
<?php if($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>

<form method="POST">
<div class="form-grid">
    <div>
        <label>Position</label>
        <select name="position">
            <?php foreach(['GK','DEF','MID','ATT'] as $pos): ?>
                <option value="<?= $pos ?>" <?= $fields['position']==$pos?'selected':'' ?>><?= $pos ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Preferred Foot</label>
        <select name="preferred_foot">
            <?php foreach(['Right','Left','Both'] as $foot): ?>
                <option value="<?= $foot ?>" <?= $fields['preferred_foot']==$foot?'selected':'' ?>><?= $foot ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Weak Foot (0-5)</label>
        <input type="number" name="weak_foot" value="<?= htmlspecialchars($fields['weak_foot']) ?>" min="0" max="5">
    </div>
    <div>
        <label>Traits Icons</label>
        <input type="text" name="traits_icons" value="<?= htmlspecialchars($fields['traits_icons']) ?>">
    </div>
    <?php foreach($fields as $key => $val):
        if(in_array($key,['position','preferred_foot','weak_foot','shot','traits_icons'])) continue;
    ?>
        <div>
            <label><?= ucfirst(str_replace('_',' ',$key)) ?></label>
            <input type="<?= in_array($key,['xgoals','save_percentage','pass_accuracy','shot_accuracy','xassists','dribbles','successful_dribbles','top_speed'])?'number':'text' ?>" 
                name="<?= $key ?>" value="<?= htmlspecialchars((string)$val) ?>" <?= in_array($key,['xgoals','save_percentage','pass_accuracy','shot_accuracy','xassists','dribbles','successful_dribbles','top_speed'])?'step="0.1"':'' ?>>
        </div>
    <?php endforeach; ?>
</div>

<h3>Δυνατά Σημεία (Strengths)</h3>
<div class="form-grid">
<?php foreach([
   "goals","assists","xgoals","clean_sheets","saves","image","goals_conceded","save_percentage","punches",
   "pass_accuracy","tackles","interceptions","clearances","blocks","key_passes","duels_won","shots","shots_on_target",
   "shot_accuracy","xassists","dribbles","successful_dribbles","shot","top_speed"] as $s): ?>
    <div>
        <label>
            <input type="checkbox" name="strengths[]" value="<?= $s ?>" <?= in_array($s,$player_strengths)?'checked':'' ?>>
            <?= ucfirst(str_replace('_',' ',$s)) ?>
        </label>
    </div>
<?php endforeach; ?>
</div>

<input type="hidden" name="shot" id="shotField">
<h3>📍 Shot Map</h3>
<p>🟢 Κλικ για Γκολ — 🔴 Shift+Κλικ για Άστοχο | Ctrl+Κλικ για διαγραφή</p>
<div class="goal-area" id="goalArea"></div>

<div class="controls">
    <input type="submit" value="<?= $id?"Ενημέρωση":"Προσθήκη" ?>">
    <a class="back" href="admin_panel.php?section=players">← Επιστροφή</a>
</div>
</form>
</div>

<script>
const goalArea = document.getElementById('goalArea');
const shotField = document.getElementById('shotField');
let shots = <?= json_encode($shot_data) ?>;

function renderShots(){
    goalArea.innerHTML = '';
    shots.forEach((s,i)=>{
        const marker = document.createElement('div');
        marker.className = 'shot-marker '+(s.goal?'goal':'miss');
        marker.style.left = s.x+'%';
        marker.style.top = s.y+'%';
        marker.addEventListener('click',(e)=>{
            if(e.ctrlKey){ shots.splice(i,1); updateField(); renderShots(); }
        });
        goalArea.appendChild(marker);
    });
}

function updateField(){ shotField.value = JSON.stringify(shots); }

goalArea.addEventListener('click',(e)=>{
    if(e.target!==goalArea) return;
    const rect = goalArea.getBoundingClientRect();
    const x = ((e.clientX - rect.left)/rect.width)*100;
    const y = ((e.clientY - rect.top)/rect.height)*100;
    const isGoal = !e.shiftKey;
    shots.push({x:x, y:y, goal:isGoal?1:0});
    updateField();
    renderShots();
});

renderShots();
updateField();
</script>
</body>
</html>
