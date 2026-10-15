<?php
session_start();
include 'config.php';

$id = (int)($_GET['id'] ?? 0);
$ticket = ['home_team'=>'','away_team'=>'','stadium'=>'','match_date'=>'','image_url'=>'','upcoming'=>0];

// Αν είναι edit, πάρε τα υπάρχοντα δεδομένα
if($id){
    $ticket = $conn->query("SELECT * FROM tick WHERE id=$id")->fetch_assoc();
}

// Αν έχει υποβληθεί φόρμα
if($_POST){
    $stadium = $_POST['stadium'];
    $date = $_POST['match_date'];
    $img = $_POST['image_url'];
    $available = (int)($_POST['available'] ?? 0); // 0 = NOW, 1 = UPCOMING

    if($id){
        $conn->query("UPDATE tick SET stadium='$stadium', match_date='$date', image_url='$img', upcoming=$available WHERE id=$id");
    }else{
        $home = $_POST['home_team'] ?? '';
        $away = $_POST['away_team'] ?? '';
        $conn->query("INSERT INTO tick(home_team,away_team,stadium,match_date,image_url,upcoming) 
                     VALUES('$home','$away','$stadium','$date','$img',$available)");
    }

    header("Location: admin_panel.php?section=tickets");
    exit;
}
?>

<style>
body {background:#111;color:#fff;font-family:'Poppins',sans-serif;margin:0;padding:0;}
form {background:#222;padding:30px;border-radius:10px;max-width:400px;margin:50px auto;box-shadow:0 4px 15px rgba(0,0,0,0.5);}
h2 {text-align:center;color:#bc8a31;margin-bottom:20px;}
p {display:flex;flex-direction:column;margin-bottom:15px;font-weight:500;}
input[type="text"], input[type="datetime-local"], select {padding:8px 10px;border-radius:5px;border:1px solid #444;background:#111;color:#fff;font-size:16px;margin-top:5px;}
input:focus, select:focus {border-color:#bc8a31;outline:none;}
button {display:block;width:100%;padding:10px;background:#bc8a31;color:#fff;font-size:16px;border:none;border-radius:8px;cursor:pointer;transition:0.3s;font-weight:bold;}
button:hover {
    background: #553801ff;;
    }
</style>

<form method="POST">
<h2><?= $id ? 'Edit' : 'New' ?> Ticket</h2>

<p>Stadium:<input type="text" name="stadium" value="<?=htmlspecialchars($ticket['stadium'] ?? '')?>" required></p>

<p>Match Date:<input type="datetime-local" name="match_date" value="<?=date('Y-m-d\TH:i', strtotime($ticket['match_date'] ?? date('Y-m-d H:i')))?>" required></p>

<p>Image URL:<input type="text" name="image_url" value="<?=htmlspecialchars($ticket['image_url'] ?? '')?>"></p>

<p>Available:
<select name="available">
    <option value="0" <?= ($ticket['upcoming'] ?? 0) == 0 ? 'selected' : '' ?>>NOW</option>
    <option value="1" <?= ($ticket['upcoming'] ?? 0) == 1 ? 'selected' : '' ?>>UPCOMING</option>
</select></p>

<p><button type="submit">Save</button></p>
</form>
