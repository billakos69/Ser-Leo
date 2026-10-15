<?php
session_start();
include 'config.php';

// Έλεγχος admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}

// Αρχικοποίηση
$teamId = $_GET['id'] ?? null;
$name = $city = $coach = $founded = $stadium = $logo = '';
$message = '';

if ($teamId) {
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id=?");
    $stmt->bind_param("i", $teamId);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    if ($team) {
        $name = $team['name'];
        $city = $team['city'];
        $coach = $team['coach'];
        $founded = $team['founded'];
        $stadium = $team['stadium'];
        $logo = $team['logo'];
    } else {
        $message = "Η ομάδα δεν βρέθηκε.";
        $teamId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $coach = trim($_POST['coach'] ?? '');
    $founded = (int)$_POST['founded']; // μετατρέπει σε integer
    $stadium = trim($_POST['stadium'] ?? '');
    $logo = trim($_POST['logo'] ?? '');

    if ($name === '') {
        $message = "Το όνομα είναι υποχρεωτικό.";
    } else {
        if ($teamId) {
            $stmt = $conn->prepare("UPDATE teams SET name=?, city=?, coach=?, founded=?, stadium=?, logo=? WHERE id=?");
            $stmt->bind_param("ssssssi", $name, $city, $coach, $founded, $stadium, $logo, $teamId);
            $stmt->execute();
            $message = "Η ομάδα ενημερώθηκε!";
        } else {
            $stmt = $conn->prepare("INSERT INTO teams (name, city, coach, founded, stadium, logo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $city, $coach, $founded, $stadium, $logo);
            $stmt->execute();
            $message = "Η ομάδα προστέθηκε!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $teamId ? "Επεξεργασία Ομάδας" : "Προσθήκη Ομάδας" ?></title>
<link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<div class="container">
<h1><?= $teamId ? "Επεξεργασία Ομάδας" : "Προσθήκη Ομάδας" ?></h1>

<?php if($message): ?>
<p class="msg"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="POST">
    <label>Όνομα Ομάδας</label>
    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>

    <label>Πόλη</label>
    <input type="text" name="city" value="<?= htmlspecialchars($city) ?>">

    <label>Προπονητής</label>
    <input type="text" name="coach" value="<?= htmlspecialchars($coach) ?>">

    <label>Ίδρυση</label>
    <input type="text" name="founded" value="<?= htmlspecialchars($founded) ?>">

    <label>Γήπεδο</label>
    <input type="text" name="stadium" value="<?= htmlspecialchars($stadium) ?>">

    <label>Logo (URL)</label>
    <input type="text" name="logo" value="<?= htmlspecialchars($logo) ?>">

    <input type="submit" value="<?= $teamId ? "Ενημέρωση" : "Προσθήκη" ?>">
</form>

<a class="back" href="admin_panel.php?section=teams">← Επιστροφή στο Admin Panel</a>
</div>
</body>
</html>
