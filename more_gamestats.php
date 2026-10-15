<?php
session_start();
include 'config.php';

if(!isset($_GET['match_id'])) die("No match ID specified");
$match_id = (int)$_GET['match_id'];

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

// Load match stats
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

// Load players & lineup
$lineup=[];
$res=$conn->query("
    SELECT l.player_id, l.side, l.pos_top, l.pos_left, p.name, p.image 
    FROM match_lineup l 
    JOIN players p ON l.player_id=p.id 
    WHERE l.match_id=$match_id
");
while($r=$res->fetch_assoc()){
    $lineup[$r['player_id']]=$r;
}

// Load match events
$events=[];
$res=$conn->query("SELECT * FROM match_events WHERE match_id=$match_id ORDER BY minute ASC, id ASC");
while($r=$res->fetch_assoc()){
    $events[]=$r;
}

// Rating color function
function rating_color($rating){
    if($rating>=9) return '#374df5';
    if($rating>=8) return '#05aec5';
    if($rating>=7) return '#adff2f';
    if($rating>=6) return '#ffff00';
    if($rating>=5) return '#ffa500';
    return '#ff4d4d';
}

// Handle POST
if($_SERVER['REQUEST_METHOD']=='POST'){
    // Save lineup
    $positions=json_decode($_POST['positions_json'],true);
    if($positions){
        $conn->query("DELETE FROM match_lineup WHERE match_id=$match_id");
        $stmt=$conn->prepare("INSERT INTO match_lineup (match_id,player_id,side,pos_top,pos_left) VALUES (?,?,?,?,?)");
        foreach($positions as $pid=>$pos){
            $stmt->bind_param("iisii",$match_id,$pid,$pos['side'],$pos['pos_top'],$pos['pos_left']);
            $stmt->execute();
        }
    }

    // Save stats
    $fields=['ball_possession','big_chances','total_shots','goalkeeper_saves','corner_kicks','fouls','passes','tackles','free_kicks','yellow_cards','red_cards'];
    $exists=$conn->query("SELECT match_id FROM match_stats WHERE match_id=$match_id")->num_rows;
    if($exists){
        $set=[]; $types=''; $vals=[];
        foreach($fields as $f){
            $set[]="$f"."_home=?"; $set[]="$f"."_away=?";
            $types.="ii";
            $vals[]=$_POST[$f.'_home']??0;
            $vals[]=$_POST[$f.'_away']??0;
        }
        $set_str=implode(',',$set);
        $update=$conn->prepare("UPDATE match_stats SET $set_str WHERE match_id=?");
        $types.="i"; $vals[]=$match_id;
        $update->bind_param($types,...$vals);
        $update->execute();
    } else {
        $cols=[]; $placeholders=[]; $types=''; $vals=[$match_id];
        foreach($fields as $f){
            $cols[]="$f"."_home"; $cols[]="$f"."_away";
            $placeholders[]='?'; $placeholders[]='?';
            $types.="ii";
            $vals[]=$_POST[$f.'_home']??0;
            $vals[]=$_POST[$f.'_away']??0;
        }
        $cols_str=implode(',',['match_id',...$cols]);
        $placeholders_str=implode(',',array_merge(['?'],$placeholders));
        $insert=$conn->prepare("INSERT INTO match_stats ($cols_str) VALUES ($placeholders_str)");
        $insert->bind_param("i".$types,...$vals);
        $insert->execute();
    }

    // Save events
    $events_post=json_decode($_POST['events_json'],true) ?: [];
    foreach($events_post as &$ev){
        if(!empty($ev['opponent_name'])) $ev['team_id']=$match['away_id'];
        elseif(!empty($ev['player_id'])){
            $side=$positions[$ev['player_id']]['side']??'home';
            $ev['team_id']=($side=='home')?$match['home_id']:$match['away_id'];
        }
    }
    $conn->query("DELETE FROM match_events WHERE match_id=$match_id");
    $stmt=$conn->prepare("INSERT INTO match_events (match_id,team_id,player_id,opponent_name,event_type,minute,sub_out_player_id,sub_in_player_id,rating) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach($events_post as $ev){
        $team_id = $ev['team_id'] ?? null;
        $player_id = $ev['player_id'] ?? null;
        $opponent_name = $ev['opponent_name'] ?? null;
        $minute = $ev['minute'] ?? 0;
        $sub_out = $ev['sub_out_player_id'] ?? null;
        $sub_in = $ev['sub_in_player_id'] ?? null;
        $rating = $ev['rating'] ?? null;
        $stmt->bind_param("iiissiiid",$match_id,$team_id,$player_id,$opponent_name,$ev['event_type'],$minute,$sub_out,$sub_in,$rating);
        $stmt->execute();
    }
    header("Location: edit_match_stats.php?match_id=$match_id");
    exit;
}


?>
<!DOCTYPE html>
<html lang="el">
<link rel="icon" href="images/logo.png" type="image/png">
<head>
<meta charset="UTF-8">
<title><?=$match['home_name']?> - <?=$match['away_name']?></title>
<style>
/* ---------------- Body & Container ---------------- */
body {background: #0b0b0b;color: #fff;font-family: "Inter", sans-serif;margin: 0;padding: 0;}
.extra-info {color: #353535ff;font-style: italic;font-weight: normal;}
.container {max-width: 1200px;margin: auto;padding: 20px;}
h1 {text-align: center;font-size: 28px;margin: 20px 0;color: #f0c14b;}
.pitch {position: relative;width: 900px;height: 550px;margin: 20px auto;border-radius: 15px;background: url('images/pitch.png') center/cover no-repeat;}
.player {position: absolute;background: #ffffffff;width: 65px;height: 65px;border-radius: 50%;border: 2px solid #ffffffff;text-align: center;font-weight: bold;line-height: 65px;box-shadow: 0 0 8px rgba(0,0,0,0.8);}
.player img {width: 100%;height: 100%;object-fit: cover;border-radius: 50%;}
.event-icon {width: 18px;height: 18px;vertical-align: middle;}
.rating {position: absolute;bottom: -10px;left: 50%;transform: translateX(-50%);padding: 1px 3px;border-radius: 3px;font-size: 12px;font-weight: bold;color: #111;background: #ffff00;box-shadow: 0 0 3px rgba(0,0,0,0.5);display: inline-block;line-height: 1;text-align: center;min-width: 0;}
.icon-wrap {position: absolute;display: flex;flex-wrap: wrap;gap: 2px;font-size: 14px;}
.multi-count {position: absolute;top: -4px;right: -12px;font-size: 11px;font-weight: bold;color: rgba(255,255,255,1);background: rgba(0,0,0,0.7);border: 1px solid #fff;border-radius: 50%;padding: 1px 4px;line-height: normal;}
.icon-wrap.top-left {top: -1px;left: -8px;flex-direction: row;}
.icon-wrap.top-right {top: -2px;right: -11px;flex-direction: column;}
.icon-wrap.bottom-left {bottom: -2px;left: -8px;flex-direction: column;}
.icon {position: relative;width: 20px;height: 20px;text-align: center;font-size: 16px;line-height: 20px;}
.stats {display: flex;justify-content: space-around;flex-wrap: wrap;margin: 30px 0;}
.stat {width: 45%;margin: 10px 0;}
.stat h3 {text-align: center;margin: 0 0 5px 0;}
.progress-bar {background: #222;height: 20px;border-radius: 10px;overflow: hidden;display: flex;}
.progress {height: 100%;text-align: center;line-height: 20px;font-weight: bold;color: #fff;font-size: 13px;}
.progress.home {background: #00b4d8;}
.progress.away {background: #ef233c;}
.timeline {max-width: 900px;margin: 30px auto;position: relative;}
.timeline-item {display: flex;align-items: center;margin: 8px 0;padding: 8px 12px;border-radius: 8px;background: #1b1b1b;}
.timeline-item.home {justify-content: flex-start;}
.timeline-item.away {justify-content: flex-end;}
.timeline-time {font-weight: bold;color: #f0c14b;margin: 0 10px;}
.timeline-icon {font-size: 18px;margin: 0 8px;}
.timeline-player {font-size: 15px;}
.parenthesi {font-size: 15px; color: #4e4e4eff;}

.away_logo {width: 22px;height: 22px;object-fit: contain;margin: 0 8px;}
</style>
</head>
<body>
<div class="container">

<h1>
    <img src="<?=$match['home_logo']?>" class="away_logo" alt="<?=$match['home_name']?>"> 
    <?=$match['home_name']?> <?=$match['home_score']?> - <?=$match['away_score']?> <?=$match['away_name']?> 
    <img src="<?=$match['away_logo']?>" class="away_logo" alt="<?=$match['away_name']?>"> 
</h1>

<!-- Stats -->
<div class="stats">
<?php
$stat_labels = [
    'ball_possession'=>'Ball Possession','big_chances'=>'Big Chances','total_shots'=>'Total Shots',
    'goalkeeper_saves'=>'Goalkeeper Saves','corner_kicks'=>'Corner Kicks','fouls'=>'Fouls',
    'passes'=>'Passes','tackles'=>'Tackles','free_kicks'=>'Free Kicks','yellow_cards'=>'Yellow Cards','red_cards'=>'Red Cards'
];
foreach($stat_labels as $key=>$label):
    $home=$stats[$key.'_home']??0;
    $away=$stats[$key.'_away']??0;
    $total=max($home+$away,1);
    $home_pct=round($home/$total*100);
    $away_pct=round($away/$total*100);
?>
<div class="stat">
<h3><?=$label?></h3>
<div class="progress-bar">
    <div class="progress home" style="width:<?=$home_pct?>%"><?=$home?></div>
    <div class="progress away" style="width:<?=$away_pct?>%"><?=$away?></div>
</div>
</div>
<?php endforeach;?>
</div>

<h2 style="text-align:center;margin-top:40px;">Lineup</h2>
<div class="pitch">
<?php foreach($lineup as $pid => $p): 
    $top = $p['pos_top'] ?? 80;
    $left = $p['pos_left'] ?? 150;

    $player_events = array_filter($events, function($e) use ($pid){
        return $e['player_id']==$pid || $e['sub_in_player_id']==$pid || $e['sub_out_player_id']==$pid;
    });

    $goal = $assist = $yellow = $red = $sub = $injury = $foul = 0;
    foreach($player_events as $ev){
        switch($ev['event_type']){
            case 'goal': $goal++; break;
            case 'assist': $assist++; break;
            case 'yellow_card': $yellow++; break;
            case 'red_card': $red++; break;
            case 'substitution': $sub++; break;
            case 'injury': $injury++; break;
            case 'foul': $foul++; break;
        }
    }

    $player_rating = null;
    foreach($events as $ev){
        if($ev['player_id']==$pid && $ev['event_type']=='rating'){
            $player_rating=$ev['rating'];
            break;
        }
    }
?>
<div class="player" style="top:<?=$top?>px; left:<?=$left?>px;" title="<?=htmlspecialchars($p['name'])?>">
    <img src="<?=$p['image'] ?: 'images/default-player.png'?>" alt="<?=htmlspecialchars($p['name'])?>">
    <?php if($player_rating!==null): ?>
        <div class="rating" style="background:<?=rating_color($player_rating)?>"><?=number_format($player_rating,1)?></div>
    <?php endif; ?>

    <div class="icon-wrap top-left">
        <?php if($yellow>0): ?><div class="icon">🟨<?php if($yellow>1) echo ' '.$yellow; ?></div><?php endif; ?>
        <?php if($red>0): ?><div class="icon">🟥<?php if($red>1) echo ' '.$red; ?></div><?php endif; ?>
    </div>

    <div class="icon-wrap top-right">
    <?php if($goal>0): ?>
        <div class="icon">⚽<?php if($goal>1): ?><span class="multi-count"><?=$goal?></span><?php endif; ?></div>
    <?php endif; ?>
    <?php if($assist>0): ?>
    <div class="icon"><img src="icons/assist.png" class="event-icon" alt="Assist"><?php if($assist>1): ?><span class="multi-count"><?=$assist?></span><?php endif; ?></div>
<?php endif; ?>
    </div>

   <div class="icon-wrap bottom-left">
    <?php if($sub>0): ?><div class="icon">🔄<?php if($sub>1) echo ' '.$sub; ?></div><?php endif; ?>
    <?php if($injury>0): ?><div class="icon"><img src="icons/injury.png" class="event-icon"><?php if($injury>1) echo '<span class="multi-count">'.$injury.'</span>'; ?></div><?php endif; ?>
    <?php if($foul>0): ?><div class="icon"><img src="icons/foul.png" class="event-icon"><?php if($foul>1) echo '<span class="multi-count">'.$foul.'</span>'; ?></div><?php endif; ?>
</div>

</div>
<?php endforeach; ?>
</div>

<h2 style="text-align:center;margin-top:40px;">Details</h2>
<div class="timeline">

<?php
$events_by_minute = [];
foreach($events as $e){
    if($e['event_type']=='rating') continue;
    $minute = $e['minute'] ?? 0;
    $events_by_minute[$minute][] = $e;
}
ksort($events_by_minute);

$half_time_inserted = false;
echo '<div class="timeline-item special" style="justify-content:center; background:#333; font-weight:bold;">Start</div>';

foreach($events_by_minute as $minute => $minute_events){
    if(!$half_time_inserted && $minute > 45){
        echo '<div class="timeline-item special" style="justify-content:center; background:#333; font-weight:bold;">Half Time</div>';
        $half_time_inserted = true;
    }

    $goal_events = $assist_events = $other_events = [];
    foreach($minute_events as $e){
        if($e['event_type']=='goal') $goal_events[] = $e;
        elseif($e['event_type']=='assist') $assist_events[] = $e;
        else $other_events[] = $e;
    }

    // --- Goals + Assists ---
    foreach($goal_events as $goal_event){
        $player = $lineup[$goal_event['player_id']]['name'] ?? ($goal_event['opponent_name'] ?? 'Unknown');
        $team_side = isset($lineup[$goal_event['player_id']]['side']) ? $lineup[$goal_event['player_id']]['side'] : 'away';
        $team_logo = ($team_side=='home')?$match['home_logo']:$match['away_logo'];
        $icon='⚽';

        // Βρίσκουμε assist για ίδιο λεπτό και ίδια ομάδα
        $assist_name = '';
        foreach($assist_events as $k => $assist){
            if($assist['minute']==$goal_event['minute'] && $assist['team_id']==$goal_event['team_id']){
                $assist_name = $lineup[$assist['player_id']]['name'] ?? ($assist['opponent_name'] ?? 'Unknown');
                unset($assist_events[$k]);
                break;
            }
        }
?>
<div class="timeline-item <?=$team_side?>">
<?php if($team_side=='home'): ?>
    <img src="<?=$team_logo?>" class="away_logo" alt="<?=$team_side?>">
    <span class="timeline-time">[<?=$goal_event['minute']?>']</span>
    <span class="timeline-icon"><?=$icon?></span>
    <span class="timeline-player"><?=htmlspecialchars($player)?><?php if($assist_name) echo " <span class='parenthesi'>(Assist: ".htmlspecialchars($assist_name).")</span>"; ?></span>
<?php else: ?>
    <span class="timeline-player"><?=htmlspecialchars($player)?><?php if($assist_name) echo " <span class='parenthesi'>(Assist: ".htmlspecialchars($assist_name).")</span>"; ?></span>
    <span class="timeline-icon"><?=$icon?></span>
    <span class="timeline-time">[<?=$goal_event['minute']?>']</span>
    <img src="<?=$team_logo?>" class="away_logo" alt="<?=$team_side?>">
<?php endif; ?>
</div>
<?php
    }

    // --- Υπόλοιπα events ---
    foreach($other_events as $e){
        if(!empty($e['player_id']) && isset($lineup[$e['player_id']])){
            $player = $lineup[$e['player_id']]['name'];
            $team_side = $lineup[$e['player_id']]['side'];
            $team_logo = ($team_side=='home')?$match['home_logo']:$match['away_logo'];
        } elseif(!empty($e['opponent_name'])) {
            $player = $e['opponent_name'];
            $team_side = 'away';
            $team_logo = $match['away_logo'];
        } else {
            $player = 'Unknown';
            $team_side = 'away';
            $team_logo = $match['away_logo'];
        }

        $icon=''; 
        $extra_player='';

        switch($e['event_type']){
            case 'yellow_card': $icon='🟨'; break;
            case 'red_card': $icon='🟥'; break;
            case 'injury': $icon='<img src="icons/injury.png" class="event-icon">'; break;
            case 'foul': $icon='<img src="icons/foul.png" class="event-icon">'; break;
            case 'substitution':
                $icon='🔄';
                if($team_side=='home'){ // δικοί μας
                    $player_in = $lineup[$e['sub_in_player_id']]['name'] ?? 'Unknown';  
                    $player_out = $lineup[$e['player_id']]['name'] ?? 'Unknown'; 
                } else { // αντίπαλοι
                    $player_in = $e['sub_in_name'] ?? 'Unknown';
                    $player_out = $e['sub_out_name'] ?? 'Unknown';
                }
                $player = $player_in;
                $extra_player = $player_out ? " (Out: $player_out)" : '';
                break;
            default: $icon='•'; break;
        }
?>
<div class="timeline-item <?=$team_side?>">
<?php if($team_side=='home'): ?>
    <img src="<?=$team_logo?>" class="away_logo" alt="<?=$team_side?>">
    <span class="timeline-time">[<?=$minute?>']</span>
    <?php if($icon) echo '<span class="timeline-icon">'.$icon.'</span>'; ?>
    <span class="timeline-player"><?=htmlspecialchars($player)?></span>
    <?php if($extra_player): ?>
        <span class="parenthesi" title="Extra"><?=$extra_player?></span>
    <?php endif; ?>
<?php else: ?>
    <span class="timeline-player"><?=htmlspecialchars($player)?></span>
    <?php if($extra_player): ?>
        <span class="parenthesi" title="Extra"><?=$extra_player?></span>
    <?php endif; ?>
    <?php if($icon) echo '<span class="timeline-icon">'.$icon.'</span>'; ?>
    <span class="timeline-time">[<?=$minute?>']</span>
    <img src="<?=$team_logo?>" class="away_logo" alt="<?=$team_side?>">
<?php endif; ?>
</div>

<?php
    }
}

echo '<div class="timeline-item special" style="justify-content:center; background:#333; font-weight:bold;">FT</div>';

?>
</div>
</div>
</body>
</html>
