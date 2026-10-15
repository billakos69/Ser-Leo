<?php
session_start();
include 'config.php';

// Έλεγχος admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}

// Αρχικοποίηση
$product_optionsId = $_GET['id'] ?? null;
$name = '';
$size = '';
$message = '';

// Αν υπάρχει ID → φόρτωση δεδομένων
if ($product_optionsId) {
    $stmt = $conn->prepare("SELECT name, size FROM product_options WHERE id=?");
    $stmt->bind_param("i", $product_optionsId);
    $stmt->execute();
    $product_options = $stmt->get_result()->fetch_assoc();
    if ($product_options) {
        $name = $product_options['name'];
        $size = $product_options['size'];
    } else {
        $message = "Η επιλογή προϊόντος δεν βρέθηκε.";
        $product_optionsId = null;
    }
}

// Αν γίνει POST → αποθήκευση
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $size = trim($_POST['size'] ?? '');

    if ($name === '' || $size === '') {
        $message = "Το όνομα και το μέγεθος είναι υποχρεωτικά.";
    } else {
        if ($product_optionsId) {
            // Ενημέρωση
            $stmt = $conn->prepare("UPDATE product_options SET name=?, size=? WHERE id=?");
            $stmt->bind_param("ssi", $name, $size, $product_optionsId);
            $stmt->execute();
            $message = "Η επιλογή ενημερώθηκε!";
        } else {
            // Εισαγωγή
            $stmt = $conn->prepare("INSERT INTO product_options (name, size) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $size);
            $stmt->execute();
            $message = "Η επιλογή προστέθηκε!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $product_optionsId ? "Επεξεργασία Μεγέθους" : "Προσθήκη Μεγέθους" ?></title>
<link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<div class="container">
<h1><?= $product_optionsId ? "Επεξεργασία Μεγέθους" : "Προσθήκη Μεγέθους" ?></h1>

<?php if($message): ?>
<p class="msg"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="POST">
    <label>Όνομα</label>
    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>

    <label>Μέγεθος</label>
    <textarea name="size" required><?= htmlspecialchars($size) ?></textarea>

    <input type="submit" value="<?= $product_optionsId ? "Ενημέρωση" : "Προσθήκη" ?>">
</form>

<a class="back" href="admin_panel.php?section=product_options">← Επιστροφή στο Admin Panel</a>
</div>
</body>
</html>
