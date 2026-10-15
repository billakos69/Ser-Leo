<?php
session_start();
include 'config.php';

// Έλεγχος admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
$name = $price = $stock = $image = '';
$message = '';
$id = $_GET['id'] ?? null;
$name = $price = $stock = $image = '';
$product_options_id = 0; // ✅ Προσθήκη εδώ για να μην βγάζει warning
$message = '';

// Fetch all categories
$categories = $conn->query("SELECT id, name FROM product_options ORDER BY name");

// Αν υπάρχει ID, φόρτωσε τα στοιχεία
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    if ($product) {
        $name = $product['name'] ?? '';
        $product_options_id = $product['product_options_id'] ?? 0;
        $price = $product['price'] ?? 0;
        $stock = $product['stock'] ?? 0;
        $image = $product['image'] ?? '';
    } else {
        $message = "Το προϊόν δεν βρέθηκε.";
        $id = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $product_options_id = intval($_POST['product_options_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $image = trim($_POST['image'] ?? '');

    if ($name === '') $message = "Το όνομα προϊόντος είναι υποχρεωτικό.";
    else {
        if ($id) {
            $stmt = $conn->prepare("UPDATE products SET name=?, product_options_id=?, price=?, stock=?, image=? WHERE id=?");
            $stmt->bind_param("sidssi", $name, $product_options_id, $price, $stock, $image, $id);
            $stmt->execute();
            $message = "Το προϊόν ενημερώθηκε!";
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, product_options_id, price, stock, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sidss", $name, $product_options_id, $price, $stock, $image);
            $stmt->execute();
            $message = "Το προϊόν προστέθηκε!";
        }
    }
}
?>

<!-- HTML Layout -->
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $id ? "Επεξεργασία Προϊόντος" : "Προσθήκη Προϊόντος" ?></title>
<link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<div class="container">
<h1><?= $id ? "Επεξεργασία Προϊόντος" : "Προσθήκη Προϊόντος" ?></h1>
<?php if($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="POST">
    <label>Όνομα</label>
    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
      <label>Κατηγορία:</label>
<select name="product_options_id" required>
    <option value="">-- Επιλογή Κατηγορίας --</option>
    <?php while($cat = $categories->fetch_assoc()): ?>
        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $product_options_id ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
        </option>
    <?php endwhile; ?>
</select>
    <label>Τιμή</label>
    <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($price) ?>" required>
    <label>Stock</label>
    <input type="number" name="stock" value="<?= htmlspecialchars($stock) ?>" required>
    <label>URL Εικόνας</label>
    <input type="text" name="image" value="<?= htmlspecialchars($image) ?>">
    <input type="submit" value="<?= $id ? "Ενημέρωση" : "Προσθήκη" ?>">
</form>
<a class="back" href="admin_panel.php?section=products">← Επιστροφή στα Προϊόντα</a>
</div>
</body>
</html>
