<?php
session_start();
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

// Default τιμή για νέα θέση
$seat = [
    'seat_row' => '',
    'seat_number' => '',
    'Gate' => '',
    'price' => '0',
    'available' => 0, // 0 = available, 1 = booked
    'image_url' => ''
];

// Αν είναι edit, παίρνουμε τα υπάρχοντα στοιχεία από DB
if($id){
    $s = $conn->query("SELECT * FROM seats WHERE id=$id")->fetch_assoc();
    if($s){
        $seat = [
            'seat_row' => $s['seat_row'],
            'seat_number' => $s['seat_number'],
            'Gate' => $s['Gate'],
            'price' => $s['price'],
            'available' => $s['available'] ?? 0, // fallback σε 0
            'image_url' => $s['image_url'] ?? ''
        ];
    }
}

// Όταν πατηθεί Save
if($_POST){
    $row = $_POST['seat_row'];
    $num = (int)$_POST['seat_number'];
    $gate = $_POST['Gate'];
    $price = (float)$_POST['price'];
    $available = (int)($_POST['available']); // 0 ή 1
    $image_url = $_POST['image_url'] ?? '';

    if($id){
        // Update
        $conn->query("UPDATE seats SET 
            seat_row='$row',
            seat_number=$num,
            Gate='$gate',
            price=$price,
            available=$available,
            image_url='$image_url'
            WHERE id=$id
        ");
    }else{
        // Insert
        $conn->query("INSERT INTO seats(seat_row,seat_number,Gate,price,available,image_url) 
            VALUES('$row',$num,'$gate',$price,$available,'$image_url')
        ");
    }
    header("Location: admin_panel.php?section=seats");
    exit;
}

// Παίρνουμε όλα τα Gate για dropdown
$gates_result = $conn->query("SELECT DISTINCT Gate FROM seats ORDER BY Gate ASC");
$gates = [];
while($r = $gates_result->fetch_assoc()){
    $gates[] = $r['Gate'];
}
?>

<style>
body {background: #111;color: #fff;font-family: 'Poppins',sans-serif;margin:0;padding:0;}
form {background:#222;padding:30px;border-radius:10px;max-width:400px;margin:50px auto;box-shadow:0 4px 15px rgba(0,0,0,0.5);}
h2 {text-align:center;color:#bc8a31;margin-bottom:20px;}
p {display:flex;flex-direction:column;margin-bottom:15px;font-weight:500;}
input[type="text"], input[type="number"], select {padding:8px 10px;border-radius:5px;border:1px solid #444;background:#111;color:#fff;font-size:16px;margin-top:5px;}
input:focus, select:focus {border-color:#bc8a31;outline:none;}
button {display:block;width:100%;padding:10px;background:#bc8a31;color:#fff;font-size:16px;border:none;border-radius:8px;cursor:pointer;transition:0.3s;font-weight:bold;}
button:hover {background:#cc0000;}
</style>

<form method="POST">
<h2><?= $id ? 'Edit' : 'New' ?> Seat</h2>

<p>Gate:
<select name="Gate" required>
    <option value="">-- Επιλέξτε Gate --</option>
    <?php foreach($gates as $g): ?>
        <option value="<?= htmlspecialchars($g) ?>" <?= ($seat['Gate'] ?? '') == $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
    <?php endforeach; ?>
</select>
</p>

<p>Row:
<input type="text" name="seat_row" value="<?= htmlspecialchars($seat['seat_row']) ?>"></p>

<p>Seat Number:
<input type="number" name="seat_number" value="<?= htmlspecialchars($seat['seat_number']) ?>"></p>

<p>Price:
<input type="number" name="price" value="<?= htmlspecialchars($seat['price']) ?>" step="0.01"></p>

<p>Image URL:
<input type="text" name="image_url" value="<?= htmlspecialchars($seat['image_url']) ?>"></p>

<p>Available:
<select name="available">
    <option value="0" <?= $seat['available']==0 ? 'selected' : '' ?>>Available</option>
    <option value="1" <?= $seat['available']==1 ? 'selected' : '' ?>>Booked</option>
</select>
</p>

<p><button type="submit">Save</button></p>
</form>
