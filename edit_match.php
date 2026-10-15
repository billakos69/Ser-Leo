<?php
session_start();
include 'config.php';

// Έλεγχος admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}

// Αρχικοποίηση
$matchId = $_GET['id'] ?? null;
$home_team = $away_team = $home_score = $away_score = $match_date = $stadium = $image = '';
$message = '';

// Φόρτωσε όλες τις ομάδες για dropdown
$teams = $conn->query("SELECT id, name FROM teams ORDER BY name");

if ($matchId) {
    $stmt = $conn->prepare("SELECT * FROM matches WHERE id=?");
    $stmt->bind_param("i", $matchId);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    if ($match) {
        $home_team  = $match['home_team'];
        $away_team  = $match['away_team'];
        $home_score = $match['home_score'];
        $away_score = $match['away_score'];
        $stadium    = $match['stadium'];
        $match_date = $match['match_date'];
        $image      = $match['image'];
    } else {
        $message = "Ο αγώνας δεν βρέθηκε.";
        $matchId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $home_team  = $_POST['home_team'] ?? '';
    $away_team  = $_POST['away_team'] ?? '';
    $home_score = $_POST['home_score'] !== '' ? (int)$_POST['home_score'] : null;
    $away_score = $_POST['away_score'] !== '' ? (int)$_POST['away_score'] : null;
    $stadium    = $_POST['stadium'] ?? '';
    $match_date = $_POST['match_date'] ?? '';
    $image      = $_POST['image'] ?? '';

    if ($home_team === $away_team) {
        $message = "Η home και away ομάδα δεν μπορούν να είναι ίδιες.";
    } else {
        if ($matchId) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE matches 
                SET home_team=?, away_team=?, home_score=?, away_score=?, stadium=?, match_date=?, image=? 
                WHERE id=?");
            $stmt->bind_param("iiiisssi", $home_team, $away_team, $home_score, $away_score, $stadium, $match_date, $image, $matchId);
            $stmt->execute();
            $message = "Ο αγώνας ενημερώθηκε!";
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO matches 
                (home_team, away_team, home_score, away_score, stadium, match_date, image) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiisss", $home_team, $away_team, $home_score, $away_score, $stadium, $match_date, $image);
            $stmt->execute();
            $message = "Ο αγώνας προστέθηκε!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $matchId ? "Επεξεργασία Αγώνα" : "Προσθήκη Αγώνα" ?></title>
<link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<div class="container">
<h1><?= $matchId ? "Επεξεργασία Αγώνα" : "Προσθήκη Αγώνα" ?></h1>

<?php if($message): ?>
<p class="msg"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="POST">
    <label>Home Team</label>
    <select name="home_team" required>
        <option value="">Επιλέξτε ομάδα</option>
        <?php while($t=$teams->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>" <?= $t['id']==$home_team?'selected':'' ?>>
                <?= htmlspecialchars($t['name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Away Team</label>
    <select name="away_team" required>
        <option value="">Επιλέξτε ομάδα</option>
        <?php $teams->data_seek(0); // reset pointer ?>
        <?php while($t=$teams->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>" <?= $t['id']==$away_team?'selected':'' ?>>
                <?= htmlspecialchars($t['name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Home Score</label>
    <input type="number" name="home_score" value="<?= htmlspecialchars($home_score) ?>">

    <label>Away Score</label>
    <input type="number" name="away_score" value="<?= htmlspecialchars($away_score) ?>">

    <label>Stadium</label>
    <input type="text" name="stadium" value="<?= htmlspecialchars($stadium) ?>">

    <label>Ημερομηνία Αγώνα</label>
    <input type="date" name="match_date" value="<?= htmlspecialchars($match_date) ?>">

    <label>Εικόνα</label>
    <input type="text" name="image" value="<?= htmlspecialchars($image) ?>" placeholder="π.χ. match1.jpg">

    <input type="submit" value="<?= $matchId ? "Ενημέρωση" : "Προσθήκη" ?>">
</form>

<a class="back" href="admin_panel.php?section=league">← Επιστροφή στο Admin Panel</a>
</div>
</body>
</html>
