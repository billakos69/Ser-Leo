<?php
session_start();
include 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']!=='Admin'){
    header("Location: login.php"); exit;
}

$code = $_GET['code'] ?? '';
$editing = !empty($code);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $code_input = $conn->real_escape_string($_POST['code']);
    $amount = floatval($_POST['amount']) / 100; // μετατρέπουμε % σε 0-1

    if($editing){
        $conn->query("UPDATE discounts SET code='$code_input', amount=$amount WHERE code='$code'");
    } else {
        $conn->query("INSERT INTO discounts (code, amount) VALUES ('$code_input', $amount)");
    }
    header("Location: admin_panel.php?section=discounts"); exit;
}

if($editing){
    $res = $conn->query("SELECT * FROM discounts WHERE code='$code'");
    $d = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $editing ? 'Edit' : 'Add' ?> Discount</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {--bg:#111;--card:#222;--accent:#bc8a31;--hover:#5f4009;--text:#fff;}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);margin:0;padding:0;}
.container{width:90%;max-width:600px;margin:50px auto;padding:20px;background:var(--card);border-radius:8px;box-shadow:0 0 10px rgba(188,138,49,0.5);}
h1{text-align:center;color:var(--accent);}
form label{display:block;margin-bottom:15px;font-weight:500;}
form input, form select{width:100%;padding:10px;border-radius:4px;border:none;background:#111;color:#fff;}
button{padding:10px 20px;background:var(--accent);color:#fff;border:none;border-radius:4px;cursor:pointer;transition:0.3s;}
button:hover{background:var(--hover);}
</style>
</head>
<body>
<div class="container">
<h1><?= $editing ? 'Edit' : 'Add' ?> Discount</h1>
<form method="post">
    <label>Κωδικός:
        <input type="text" name="code" value="<?= htmlspecialchars($d['code'] ?? '') ?>" required>
    </label>
    <label>Έκπτωση (%):
        <input type="number" step="0.01" name="amount" value="<?= isset($d['amount']) ? $d['amount']*100 : '' ?>" required>
    </label>
    <button type="submit"><?= $editing ? 'Αποθήκευση' : 'Προσθήκη' ?></button>
</form>
</div>
</body>
</html>
