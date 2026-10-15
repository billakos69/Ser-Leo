<?php
session_start();
include 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']!=='Admin'){
    header("Location: login.php"); exit;
}

$id = $_GET['id'] ?? '';
$editing = !empty($id);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $user_id = intval($_POST['user_id']);
    $discount_code = $conn->real_escape_string($_POST['discount_code']);
    $used_at = $conn->real_escape_string($_POST['used_at']);

    if($editing){
        $conn->query("UPDATE discount_usage SET user_id=$user_id, discount_code='$discount_code', used_at='$used_at' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO discount_usage (user_id, discount_code, used_at) VALUES ($user_id, '$discount_code', '$used_at')");
    }
    header("Location: admin_panel.php?section=discount_usage"); exit;
}

if($editing){
    $res = $conn->query("SELECT * FROM discount_usage WHERE id=$id");
    $u = $res->fetch_assoc();
}

$users = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
$discounts = $conn->query("SELECT code FROM discounts ORDER BY code ASC");
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $editing ? 'Edit' : 'Add' ?> Discount Usage</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {--bg:#111;--card:#222;--accent:#bc8a31;--hover:#5f4009;--text:#fff;}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);margin:0;padding:0;}
.container{width:90%;max-width:600px;margin:50px auto;padding:20px;background:var(--card);border-radius:8px;box-shadow:0 0 10px rgba(255,0,0,0.5);}
h1{text-align:center;color:var(--accent);}
form label{display:block;margin-bottom:15px;font-weight:500;}
form input, form select{width:100%;padding:10px;border-radius:4px;border:none;background:#111;color:#fff;}
button{padding:10px 20px;background:var(--accent);color:#fff;border:none;border-radius:4px;cursor:pointer;transition:0.3s;}
button:hover{background:var(--hover);}
</style>
</head>
<body>
<div class="container">
<h1><?= $editing ? 'Edit' : 'Add' ?> Discount Usage</h1>
<form method="post">
    <label>Χρήστης:
        <select name="user_id" required>
            <?php while($user=$users->fetch_assoc()): ?>
            <option value="<?= $user['id'] ?>" <?= ($u['user_id']??'')==$user['id'] ? 'selected':'' ?>>
                <?= htmlspecialchars($user['username']) ?>
            </option>
            <?php endwhile; ?>
        </select>
    </label>
    <label>Κουπόνι:
        <select name="discount_code" required>
            <?php while($d=$discounts->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($d['code']) ?>" <?= ($u['discount_code']??'')==$d['code'] ? 'selected':'' ?>>
                <?= htmlspecialchars($d['code']) ?>
            </option>
            <?php endwhile; ?>
        </select>
    </label>
    <label>Ημερομηνία Χρήσης:
        <input type="datetime-local" name="used_at" value="<?= isset($u['used_at']) ? date('Y-m-d\TH:i', strtotime($u['used_at'])) : date('Y-m-d\TH:i') ?>" required>
    </label>
    <button type="submit"><?= $editing ? 'Αποθήκευση' : 'Προσθήκη' ?></button>
</form>
</div>
</body>
</html>
