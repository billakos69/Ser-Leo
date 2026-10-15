<?php
session_start();
include 'config.php';

if(!isset($_GET['match_id'])) die("No match ID specified");
$match_id = (int)$_GET['match_id'];

// Admin check
if(!isset($_SESSION['user']) || $_SESSION['user']!=='Admin'){
    header("Location: login.php"); exit;
}


// Load match info
$match = $conn->query("
    SELECT m.*, 
           th.name as home_name, th.logo as home_logo, th.id as home_id,
           ta.name as away_name, ta.logo as away_logo, ta.id as away_id
    FROM matches m
    JOIN teams th ON m.home_team = th.id
    JOIN teams ta ON m.away_team = ta.id
    WHERE m.id = $match_id
")->fetch_assoc();
if(!$match) die("Match not found");

// Load stats
$stats = $conn->query("SELECT * FROM match_stats WHERE match_id=$match_id")->fetch_assoc();
$stats = $stats ?: [
    'ball_possession_home'=>0,'ball_possession_away'=>0,
    'big_chances_home'=>0,'big_chances_away'=>0,
    'total_shots_home'=>0,'total_shots_away'=>0,
    'goalkeeper_saves_home'=>0,'goalkeeper_saves_away'=>0,
    'corner_kicks_home'=>0,'corner_kicks_away'=>0,
    'fouls_home'=>0,'fouls_away'=>0,
    'passes_home'=>0,'passes_away'=>0,
    'tackles_home'=>0,'tackles_away'=>0,
    'free_kicks_home'=>0,'free_kicks_away'=>0,
    'yellow_cards_home'=>0,'yellow_cards_away'=>0,
    'red_cards_home'=>0,'red_cards_away'=>0
];

// Load players
$players=[];
$res=$conn->query("SELECT id,name,image FROM players ORDER BY name ASC");
while($p=$res->fetch_assoc()){
    $players[$p['id']] = [
        'name'=>$p['name'],
        'image'=>$p['image'] ?: 'images/default-player.png'
    ];
}

// Load lineup
$lineupPositions=[];
$res=$conn->query("SELECT player_id, side, pos_top, pos_left FROM match_lineup WHERE match_id=$match_id");
while($r=$res->fetch_assoc()){
    $lineupPositions[$r['player_id']] = $r;
}

// Load events
$events=[];
$res=$conn->query("SELECT * FROM match_events WHERE match_id=$match_id ORDER BY minute ASC");
while($r=$res->fetch_assoc()){
    $events[]=$r;
}

// Handle POST
if($_SERVER['REQUEST_METHOD']=='POST'){
    // Save lineup positions
    $positions = json_decode($_POST['positions_json'],true);
    if($positions){
        $conn->query("DELETE FROM match_lineup WHERE match_id=$match_id");
        $stmt=$conn->prepare("INSERT INTO match_lineup (match_id, player_id, side, pos_top, pos_left) VALUES (?,?,?,?,?)");
        foreach($positions as $pid=>$pos){
            $stmt->bind_param("iisii",$match_id,$pid,$pos['side'],$pos['pos_top'],$pos['pos_left']);
            $stmt->execute();
        }
    }

    // Save stats
    $fields=['ball_possession','big_chances','total_shots','goalkeeper_saves','corner_kicks','fouls','passes','tackles','free_kicks','yellow_cards','red_cards'];
    $exists = $conn->query("SELECT match_id FROM match_stats WHERE match_id=$match_id")->num_rows;
    if($exists){
        $set=[]; $types=''; $vals=[];
        foreach($fields as $f){
            $set[]="$f"."_home=?"; $set[]="$f"."_away=?";
            $types.="ii";
            $vals[]=$_POST[$f.'_home']??0; $vals[]=$_POST[$f.'_away']??0;
        }
        $set_str=implode(',',$set);
        $update=$conn->prepare("UPDATE match_stats SET $set_str WHERE match_id=?");
        $types.="i"; $vals[]=$match_id;
        $update->bind_param($types,...$vals);
        $update->execute();
    }else{
        $cols=[]; $placeholders=[]; $types=''; $vals=[$match_id];
        foreach($fields as $f){
            $cols[]="$f"."_home"; $cols[]="$f"."_away";
            $placeholders[]='?'; $placeholders[]='?';
            $types.="ii";
            $vals[]=$_POST[$f.'_home']??0; $vals[]=$_POST[$f.'_away']??0;
        }
        $cols_str=implode(',',['match_id',...$cols]);
        $placeholders_str=implode(',',array_merge(['?'],$placeholders));
        $insert=$conn->prepare("INSERT INTO match_stats ($cols_str) VALUES ($placeholders_str)");
        $insert->bind_param("i".$types,...$vals);
        $insert->execute();
    }

    // Save events
    $events_post = json_decode($_POST['events_json'],true) ?: [];

    if($events_post){
        $conn->query("DELETE FROM match_events WHERE match_id=$match_id");
        $stmt=$conn->prepare("INSERT INTO match_events (match_id, team_id, player_id, opponent_name, event_type, minute, sub_out_player_id, sub_in_player_id, sub_out_name, sub_in_name, rating) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        foreach($events_post as $ev){
            $team_id = $ev['team_id'] ?? ($ev['opponent_name'] ? $match['away_id'] : null);
            $player_id = $ev['player_id'] ?? null;
            $opponent_name = $ev['opponent_name'] ?? null;
            $minute = isset($ev['minute']) ? (int)$ev['minute'] : 0;
            $sub_out = $ev['sub_out_player_id'] ?? null;
            $sub_in = $ev['sub_in_player_id'] ?? null;
            $sub_out_name = $ev['sub_out_name'] ?? null;
            $sub_in_name = $ev['sub_in_name'] ?? null;
            $rating = $ev['rating'] ?? null;

            // Βεβαιωνόμαστε ότι το event_type είναι έγκυρο
            $event_type = in_array($ev['event_type'], ['goal','assist','yellow_card','red_card','substitution','injury','rating']) ? $ev['event_type'] : 'goal';

$stmt->bind_param(
    "iiissiiissd",
    $match_id,
    $team_id,
    $player_id,
    $opponent_name,
    $event_type,
    $minute,
    $sub_out,
    $sub_in,
    $sub_out_name,
    $sub_in_name,
    $rating
);

            $stmt->execute();
        }
    }

    header("Location: edit_match_stats.php?match_id=$match_id");
    exit;
}


?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Edit Match Stats</title>
<style>
body{font-family:sans-serif;background:#111;color:#fff;padding:20px;}
form{max-width:1200px;margin:auto;background:#222;padding:20px;border-radius:10px;}
.pitch{position:relative;width:900px;height:550px;margin:auto;background:url('images/pitch.png') no-repeat center;background-size:cover;border:1px solid #333;}
.player-icon{position:absolute;width:60px;height:60px;border-radius:50%;overflow:hidden;border:2px solid #0af;text-align:center;font-weight:bold;cursor:grab;}
.player-icon img{width:100%;height:100%;object-fit:cover;}
.icon{position:absolute;width:20px;height:20px;text-align:center;font-size:16px;}
.icon-goal::after{content:'⚽';}
.icon-assist::after{content:'🅰️';}
.icon-yellow::after{content:'🟨';}
.icon-red::after{content:'🟥';}
.icon-sub::after{content:'🔄';}
.stats-table{width:100%;margin-top:20px;border-collapse:collapse;}
.stats-table th, .stats-table td{border:1px solid #444;padding:6px;text-align:center;}
.stats-table th{background:#222;color:#bc8a31;}
button{padding:5px 10px;margin-top:10px;border:none;border-radius:4px;background:#bc8a31;color:#111;cursor:pointer;}
button:hover{opacity:0.8;}
input, select{padding:4px;border-radius:4px;border:none;background:#333;color:#fff;}
fieldset{margin-top:20px;border:1px solid #444;padding:10px;border-radius:6px;}
legend{padding:0 5px;}
</style>
</head>
<body>
<h2>Edit Match Stats & Lineup</h2>
<form method="POST">

<!-- Statistics -->
<fieldset>
<legend>Statistics</legend>
<table class="stats-table">
<tr><th>Stat</th><th>Home</th><th>Away</th></tr>
<?php
$stat_fields = [
    'ball_possession'=>'Ball Possession %','big_chances'=>'Big Chances','total_shots'=>'Total Shots',
    'goalkeeper_saves'=>'Goalkeeper Saves','corner_kicks'=>'Corner Kicks','fouls'=>'Fouls',
    'passes'=>'Passes','tackles'=>'Tackles','free_kicks'=>'Free Kicks',
    'yellow_cards'=>'Yellow Cards','red_cards'=>'Red Cards'
];
foreach($stat_fields as $f=>$label): ?>
<tr>
<td><?=$label?></td>
<td><input type="number" name="<?=$f?>_home" value="<?=$stats[$f.'_home']?>" min="0"></td>
<td><input type="number" name="<?=$f?>_away" value="<?=$stats[$f.'_away']?>" min="0"></td>
</tr>
<?php endforeach;?>
</table>
</fieldset>

<!-- Lineup -->
<fieldset>
<legend>Line-up & Pitch</legend>
<select id="lineup-select">
<option value="">Select player</option>
<?php foreach($players as $pid=>$pdata): ?>
<option value="<?=$pid?>"><?=htmlspecialchars($pdata['name'])?></option>
<?php endforeach; ?>
</select>
<div id="lineup-chosen"></div>
<div class="pitch" id="pitch"></div>
<input type="hidden" id="positions_json" name="positions_json">
</fieldset>

<!-- Player Events -->
<fieldset>
<legend>Player Events (Own Team)</legend>
<div id="events-container"></div>
<button type="button" id="add-event">+ Add Event</button>
<input type="hidden" id="events_json" name="events_json">
</fieldset>

<!-- Ratings -->
<fieldset>
<legend>Player Ratings (Own Team)</legend>
<select id="rating-player">
<option value="">Select player</option>
<?php foreach($players as $pid=>$pdata): ?>
<option value="<?=$pid?>"><?=htmlspecialchars($pdata['name'])?></option>
<?php endforeach; ?>
</select>
<input type="number" id="rating-value" placeholder="0-10" min="0" max="10" step="0.1">
<button type="button" id="add-rating">+ Add Rating</button>
<div id="ratings-list"></div>
</fieldset>

<!-- Opponent Events -->
<fieldset>
<legend>Opponent Events (Timeline)</legend>
<div id="opponent-events-container"></div>
<button type="button" id="add-opponent-event">+ Add Opponent Event</button>
</fieldset>

<button type="submit">Save</button>
</form>

<script>
const pitch = document.getElementById('pitch');
const lineupPositions = <?=json_encode($lineupPositions)?>;
const players = <?=json_encode($players)?>;
const matchTeams = {home:<?=$match['home_id']?>, away:<?=$match['away_id']?>};
let eventsData = <?=json_encode($events)?>;

// ---------- Lineup ----------
const lineupSelect = document.getElementById('lineup-select');
const lineupChosen = document.getElementById('lineup-chosen');
const lineupOrder = [];

lineupSelect.addEventListener('change', () => {
    const pid = parseInt(lineupSelect.value);
    if (!pid || lineupOrder.includes(pid)) return;
    lineupOrder.push(pid);
    if (!lineupPositions[pid]) {
        lineupPositions[pid] = {pos_top: 50 + lineupOrder.indexOf(pid) * 30, pos_left: 100, side: 'home'};
    }
    renderLineup();
});

function renderLineup() {
    lineupChosen.innerHTML = '';
    lineupOrder.forEach(pid => {
        const div = document.createElement('div');
        div.textContent = players[pid].name + ' ';
        const btn = document.createElement('button');
        btn.type='button'; btn.textContent='Remove';
        btn.addEventListener('click', ()=>{
            const idx = lineupOrder.indexOf(pid);
            if(idx!==-1) lineupOrder.splice(idx,1);
            delete lineupPositions[pid];
            renderLineup(); renderPitch();
        });
        div.appendChild(btn);
        lineupChosen.appendChild(div);
    });
    renderPitch();
}

// ---------- Render Pitch ----------
function renderPitch() {
    pitch.innerHTML = '';
    for(const pid in lineupPositions){
        const pos = lineupPositions[pid];
        const div = document.createElement('div');
        div.className='player-icon';
        div.style.top = pos.pos_top + 'px';
        div.style.left = pos.pos_left + 'px';
        div.dataset.id=pid;

        const img = document.createElement('img'); img.src=players[pid].image;
        div.appendChild(img);

        const playerEvents = eventsData.filter(ev=>ev.player_id==pid && ev.event_type!=='rating');
        playerEvents.forEach((ev,idx)=>{
            const icon = document.createElement('div'); icon.className='icon';
            icon.style.left = `${idx*22}px`;
            switch(ev.event_type){
                case 'goal': icon.classList.add('icon-goal'); break;
                case 'assist': icon.classList.add('icon-assist'); break;
                case 'yellow_card': icon.classList.add('icon-yellow'); break;
                case 'red_card': icon.classList.add('icon-red'); break;
                case 'substitution': icon.classList.add('icon-sub'); break;
            }
            div.appendChild(icon);
        });

        pitch.appendChild(div);
    }
}

// ---------- Drag & Drop ----------
let dragged=null;
pitch.addEventListener('mousedown',e=>{
    if(e.target.closest('.player-icon')) dragged=e.target.closest('.player-icon');
});
pitch.addEventListener('mousemove',e=>{
    if(!dragged) return;
    const rect=pitch.getBoundingClientRect();
    let top=e.clientY-rect.top-30;
    let left=e.clientX-rect.left-30;
    dragged.style.top=top+'px';
    dragged.style.left=left+'px';
});
pitch.addEventListener('mouseup',()=>{
    if(!dragged) return;
    const pid=parseInt(dragged.dataset.id);
    lineupPositions[pid].pos_top=parseInt(dragged.style.top);
    lineupPositions[pid].pos_left=parseInt(dragged.style.left);
    dragged=null;
});

// ---------- Player Events ----------
const eventsContainer = document.getElementById('events-container');
function renderEvents(){
    eventsContainer.innerHTML='';
    eventsData.forEach((ev,index)=>{
        if(ev.event_type==='rating' || ev.player_id===null) return;
        const div = document.createElement('div'); div.style.marginBottom='5px';
        const playerSelect = document.createElement('select');
        for(const pid in players){
            const o=document.createElement('option'); o.value=pid; o.textContent=players[pid].name;
            if(ev.player_id==pid) o.selected=true;
            playerSelect.appendChild(o);
        }
        playerSelect.addEventListener('change',()=>{ ev.player_id=parseInt(playerSelect.value); renderPitch(); });
        div.appendChild(playerSelect);

        const types=['goal','assist','yellow_card','red_card','substitution','injury','foul'];
        const ts = document.createElement('select');
        types.forEach(t=>{ const o=document.createElement('option'); o.value=t; o.textContent=t.replace('_',' '); if(ev.event_type==t) o.selected=true; ts.appendChild(o); });
        ts.addEventListener('change',()=>{
            ev.event_type=ts.value;
            renderEvents(); renderPitch();
        });
        div.appendChild(ts);

        const mi = document.createElement('input'); mi.type='number'; mi.min=0; mi.value=ev.minute||0;
        mi.addEventListener('change',()=>{ ev.minute=parseInt(mi.value); });
        div.appendChild(mi);

        if(ev.event_type==='substitution'){
            const subInSelect = document.createElement('select');
            subInSelect.innerHTML='<option value="">Select Sub In</option>';
            for(const pid in lineupPositions){
                if(lineupPositions[pid].side==='home' && pid!=ev.player_id){
                    const o=document.createElement('option');
                    o.value=pid;
                    o.textContent=players[pid].name;
                    if(ev.sub_in_player_id==pid) o.selected=true;
                    subInSelect.appendChild(o);
                }
            }
            subInSelect.addEventListener('change',()=>{
                ev.sub_in_player_id=parseInt(subInSelect.value)||null;
                ev.sub_in_name=subInSelect.selectedOptions[0].textContent;
            });
            div.appendChild(subInSelect);
        }


        const rb=document.createElement('button'); rb.type='button'; rb.textContent='X';
        rb.addEventListener('click',()=>{ eventsData.splice(index,1); renderEvents(); renderPitch(); });
        div.appendChild(rb);

        eventsContainer.appendChild(div);
    });
}

// ---------- Ratings ----------
const ratingPlayer=document.getElementById('rating-player');
const ratingValue=document.getElementById('rating-value');
const ratingList=document.getElementById('ratings-list');

document.getElementById('add-rating').addEventListener('click',()=>{
    const pid=parseInt(ratingPlayer.value);
    const val=parseFloat(ratingValue.value);
    if(!pid||isNaN(val)) return alert('Select player & rating');
    for(let i=eventsData.length-1;i>=0;i--){
        if(eventsData[i].player_id===pid && eventsData[i].event_type==='rating') eventsData.splice(i,1);
    }
    eventsData.push({player_id:pid,team_id:lineupPositions[pid].side==='away'?matchTeams.away:matchTeams.home,event_type:'rating',minute:0,rating:val});
    renderRatings();
});

function renderRatings(){
    ratingList.innerHTML='';
    const ratings=eventsData.filter(ev=>ev.event_type==='rating');
    ratings.forEach(ev=>{
        const div=document.createElement('div');
        div.textContent=`${players[ev.player_id].name}: ${ev.rating}`;
        const btn=document.createElement('button'); btn.type='button'; btn.textContent='X';
        btn.addEventListener('click',()=>{ const idx=eventsData.indexOf(ev); if(idx!==-1) eventsData.splice(idx,1); renderRatings(); });
        div.appendChild(btn);
        ratingList.appendChild(div);
    });
}

// ---------- Add Event ----------
document.getElementById('add-event').addEventListener('click',()=>{
    if(lineupOrder.length===0) return alert('Add players first');
    eventsData.push({player_id:lineupOrder[0],team_id:matchTeams.home,event_type:'goal',minute:0});
    renderEvents(); renderPitch();
});

// ---------- Opponent Events ----------
// ---------- Opponent Events ----------
const opponentContainer = document.getElementById('opponent-events-container');
const addOpponentBtn = document.getElementById('add-opponent-event');
let opponentEvents = eventsData.filter(ev => ev.player_id === null);

function renderOpponentEvents() {
    opponentContainer.innerHTML = '';
    opponentEvents.forEach((ev, idx) => {
        const div = document.createElement('div');
        div.style.marginBottom = '5px';
        div.style.background = '#333';
        div.style.padding = '5px';
        div.style.borderRadius = '4px';
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.gap = '5px';

        // Επιλογή τύπου event
        const typeSelect = document.createElement('select');
        ['goal','assist','yellow_card','red_card','substitution','injury','foul'].forEach(t => {
            const o = document.createElement('option'); 
            o.value = t; 
            o.textContent = t.replace('_',' '); 
            if(ev.event_type === t) o.selected = true;
            typeSelect.appendChild(o);
        });
        typeSelect.addEventListener('change', () => {
            ev.event_type = typeSelect.value;
            renderOpponentEvents(); // rerender για να εμφανίσει σωστά sub fields
        });
        div.appendChild(typeSelect);

        // Λεπτά
        const minuteInput = document.createElement('input');
        minuteInput.type = 'number';
        minuteInput.min = 0;
        minuteInput.value = ev.minute || 0;
        minuteInput.style.width = '60px';
        minuteInput.addEventListener('input', () => { ev.minute = parseInt(minuteInput.value) || 0; });
        div.appendChild(minuteInput);

        if(ev.event_type === 'substitution'){
            // Sub Out
            const subOut = document.createElement('input');
            subOut.type = 'text';
            subOut.placeholder = 'Sub Out';
            subOut.value = ev.sub_out_name || '';
            subOut.addEventListener('input', () => { ev.sub_out_name = subOut.value || 'Unknown'; });
            div.appendChild(subOut);

            // Sub In
            const subIn = document.createElement('input');
            subIn.type = 'text';
            subIn.placeholder = 'Sub In';
            subIn.value = ev.sub_in_name || '';
            subIn.addEventListener('input', () => { ev.sub_in_name = subIn.value || 'Unknown'; });
            div.appendChild(subIn);
        } else {
            // Όνομα παίκτη αντιπάλου
            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.placeholder = 'Opponent player name';
            nameInput.value = ev.opponent_name || '';
            nameInput.addEventListener('input', () => { ev.opponent_name = nameInput.value || 'Unknown'; });
            div.appendChild(nameInput);
        }

        // Remove button
        const rmBtn = document.createElement('button');
        rmBtn.type = 'button';
        rmBtn.textContent = 'X';
        rmBtn.addEventListener('click', () => { 
            opponentEvents.splice(idx, 1); 
            renderOpponentEvents(); 
        });
        div.appendChild(rmBtn);

        opponentContainer.appendChild(div);
    });
}

addOpponentBtn.addEventListener('click', () => {
    opponentEvents.push({event_type:'goal', minute:0, opponent_name:'', sub_out_name:'', sub_in_name:''});
    renderOpponentEvents();
});

// ---------- Form submit ----------
document.querySelector('form').addEventListener('submit', () => {
    eventsData = eventsData.filter(ev => ev.player_id !== null);
    
    // Βεβαιωνόμαστε ότι κάθε opponent event έχει σωστά ονόματα
    opponentEvents.forEach(ev => {
        if(ev.event_type === 'substitution'){
            ev.sub_out_name = ev.sub_out_name || 'Unknown';
            ev.sub_in_name = ev.sub_in_name || 'Unknown';
        } else {
            ev.opponent_name = ev.opponent_name || 'Unknown';
        }
        eventsData.push(ev);
    });

    document.getElementById('events_json').value = JSON.stringify(eventsData);
});


// ---------- Init ----------
(function init(){
    Object.keys(lineupPositions).forEach(k=>{ const num=parseInt(k); if(!lineupOrder.includes(num)) lineupOrder.push(num); });
    renderLineup(); renderEvents(); renderRatings(); renderOpponentEvents();
})();
</script>

</body>
</html>
