<?php
session_start();
include 'config.php';

// Προσθήκη προϊόντος στο καλάθι
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    $size = $_POST['size'] ?? '';

    // Αν υπάρχει ήδη στο καλάθι, αυξάνουμε την ποσότητα
    if(isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = [
            'quantity' => $quantity,
            'size' => $size
        ];
    }
    header('Location: cart.php');
    exit;
}

// Διαγραφή προϊόντος
if(isset($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$remove_id]);
    header('Location: cart.php');
    exit;
}

// Αλλαγή ποσότητας
if(isset($_POST['update_cart'])) {
    foreach($_POST['quantities'] as $id => $qty) {
        if(isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['quantity'] = max(1, (int)$qty);
        }
    }
    header('Location: cart.php');
    exit;
}

$cart_items = $_SESSION['cart'] ?? [];
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Καλάθι - Ligdikos FC Shop</title>
<link rel="stylesheet" href="style.css">
<style>
.cart-dropdown {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #111;
    border: 2px solid cyan;
    border-radius: 12px;
    width: 300px;
    max-height: 400px;
    overflow-y: auto;
    padding: 15px;
    display: none;
    z-index: 2000;
    box-shadow: 0 6px 20px rgba(0,255,255,0.5);
}

.cart-dropdown h3 {
    text-align: center;
    color: #0baa93;
    margin-bottom: 15px;
}

.cart-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #222;
}

.cart-item img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    margin-right: 10px;
}

.cart-item-info {
    flex: 1;
    text-align: left;
}

.cart-item-info span {
    display: block;
    font-size: 0.9rem;
    color: #fff;
}

.cart-item-info .size {
    color: #0baa93;
}

.cart-item-actions {
    text-align: right;
}

.cart-item-actions a {
    color: #ff3333;
    text-decoration: none;
    font-weight: bold;
    margin-left: 5px;
}

.cart-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg,#0baa93,#ff8431);
    color: #000;
    padding: 12px 18px;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    z-index: 3000;
    transition: 0.3s;
}

.cart-btn:hover {
    background: linear-gradient(135deg,#ff8431,#ffbd69);
}
.checkout-btn {
    display: block;
    margin: 15px auto 0;
    padding: 10px 20px;
    background: linear-gradient(135deg,#0baa93,#00ffff);
    color: #000;
    text-align: center;
    font-weight: 600;
    border-radius: 25px;
    text-decoration: none;
}
.checkout-btn:hover {
    background: linear-gradient(135deg,#ff8431,#ffbd69);
}
</style>
</head>
<body>

<button class="cart-btn" onclick="toggleCart()">Καλάθι (<?= count($cart_items) ?>)</button>

<div class="cart-dropdown" id="cartDropdown">
    <h3>Το Καλάθι Σου</h3>
    <?php if(!$cart_items): ?>
        <p style="text-align:center;color:#bbb;">Το καλάθι είναι άδειο</p>
    <?php else: ?>
        <form method="POST">
        <?php 
        $total = 0;
        foreach($cart_items as $id => $item):
            $res = $conn->query("SELECT * FROM products WHERE id=$id");
            $prod = $res->fetch_assoc();
            $subtotal = $prod['price'] * $item['quantity'];
            $total += $subtotal;
        ?>
        <div class="cart-item">
            <img src="<?= htmlspecialchars(explode(',',$prod['image'])[0]) ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
            <div class="cart-item-info">
                <span><?= htmlspecialchars($prod['name']) ?></span>
                <span class="size">Μέγεθος: <?= $item['size'] ?></span>
                <span>Ποσότητα: <input type="number" name="quantities[<?= $id ?>]" value="<?= $item['quantity'] ?>" min="1" style="width:50px;"></span>
            </div>
            <div class="cart-item-actions">
                <a href="?remove=<?= $id ?>">×</a>
            </div>
        </div>
        <?php endforeach; ?>
        <p style="color:#0baa93;font-weight:bold;margin-top:10px;">Σύνολο: <?= number_format($total,2) ?>€</p>
        <button type="submit" name="update_cart" style="display:none;"></button>
        </form>
        <a href="checkout.php" class="checkout-btn">Ολοκλήρωση Αγοράς</a>
    <?php endif; ?>
</div>

<script>
function toggleCart() {
    const cart = document.getElementById('cartDropdown');
    cart.style.display = cart.style.display === 'block' ? 'none' : 'block';
}
</script>

</body>
</html>
