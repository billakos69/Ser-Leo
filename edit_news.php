<?php
session_start();
include 'config.php';

// Έλεγχος admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}

// Αρχικοποίηση
$newsId = $_GET['id'] ?? null;
$title = '';
$content = '';
$image = '';
$message = '';

if ($newsId) {
    $stmt = $conn->prepare("SELECT title, content, image FROM news WHERE id=?");
    $stmt->bind_param("i", $newsId);
    $stmt->execute();
    $news = $stmt->get_result()->fetch_assoc();
    if ($news) {
        $title = $news['title'];
        $content = $news['content'];
        $image = $news['image'];
    } else {
        $message = "Η είδηση δεν βρέθηκε.";
        $newsId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $image = trim($_POST['image'] ?? '');

    if ($title === '' || $content === '') {
        $message = "Ο τίτλος και το κείμενο είναι υποχρεωτικά.";
    } else {
        if ($newsId) {
            $stmt = $conn->prepare("UPDATE news SET title=?, content=?, image=? WHERE id=?");
            $stmt->bind_param("sssi", $title, $content, $image, $newsId);
            $stmt->execute();
            $message = "Η είδηση ενημερώθηκε!";
        } else {
            $stmt = $conn->prepare("INSERT INTO news (title, content, image) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $title, $content, $image);
            $stmt->execute();
            $message = "Η είδηση προστέθηκε!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $newsId ? "Επεξεργασία Ειδήσεων" : "Προσθήκη Ειδήσεων" ?></title>
<link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<div class="container">
<h1><?= $newsId ? "Επεξεργασία Ειδήσεων" : "Προσθήκη Ειδήσεων" ?></h1>

<?php if($message): ?>
<p class="msg"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="POST">
    <label>Τίτλος</label>
    <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" required>

    <label>Κείμενο</label>
    <textarea name="content" required><?= htmlspecialchars($content) ?></textarea>

    <label>URL Εικόνας</label>
    <input type="text" name="image" value="<?= htmlspecialchars($image) ?>" placeholder="π.χ. images/news1.jpg">

    <input type="submit" value="<?= $newsId ? "Ενημέρωση" : "Προσθήκη" ?>">
</form>

<a class="back" href="admin_panel.php?section=news">← Επιστροφή στο Admin Panel</a>
</div>
</body>
</html>
