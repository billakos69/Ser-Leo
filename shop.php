<?php
session_start();
include 'config.php';

$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;
$user_id    = $isLoggedIn ? $_SESSION['user_id'] : null;

// -------- ΠΑΡΑΜΕΤΡΟΙ ΠΑΓΙΝΑΣΗΣ --------
$perPage = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// -------- Μέτρημα προϊόντων --------
$countRes = $conn->query("SELECT COUNT(*) as total FROM products");
$totalProducts = $countRes->fetch_assoc()['total'];
$totalPages = ceil($totalProducts / $perPage);

// --- Φόρτωση προϊόντων με LIMIT ---
$sql = "
SELECT 
    p.id, 
    p.name, 
    p.price,
    (CASE 
        WHEN pp.active=1 
             AND NOW() BETWEEN pp.start_date AND pp.end_date
             AND FIND_IN_SET(p.id, pp.products_id)
        THEN ROUND(p.price * (1 - pp.percentage/100), 2)
        ELSE p.price
    END) AS current_price,
    p.image
FROM products p
LEFT JOIN product_promotions pp
    ON FIND_IN_SET(p.id, pp.products_id)
ORDER BY p.name ASC
LIMIT $offset, $perPage
";
$result = $conn->query($sql);
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// --- Αγαπημένα ---
$fav_products = [];
if ($isLoggedIn) {
    $resFav = $conn->query("SELECT products_id FROM favorite WHERE user_id=$user_id");
    while ($f = $resFav->fetch_assoc()) {
        $fav_products[] = $f['products_id'];
    }
}

// --- Καλάθι ---
$cart_items = $_SESSION['cart'] ?? [];
if(isset($_GET['remove']) && isset($_GET['size'])) {
    $remove_id = (int)$_GET['remove'];
    $size = $_GET['size'];
    if(isset($_SESSION['cart'][$remove_id][$size])) {
        unset($_SESSION['cart'][$remove_id][$size]);
        if(empty($_SESSION['cart'][$remove_id])) {
            unset($_SESSION['cart'][$remove_id]);
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// --- Count στο cart button ---
$cartCount = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $id => $sizes) {
        foreach($sizes as $size => $qty) $cartCount += $qty;
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
  <title>SER LEO F.C</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
<link rel="stylesheet" href="style.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="images/logo.png" type="image/png">
<style>
.cart-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 100;
}
.cart-btn {
    background: #bc8a31;
    color: #fff;
    font-weight: 600;
    padding: 10px 20px;
    border-radius: 25px;
    border: none;
    cursor: pointer;
}
.cart-dropdown {
    display: none;
    position: absolute;
    top: 50px;
    right: 0;
    width: 300px;
    background: #222;
    color: #fff;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 6px 20px rgba(255, 145, 0, 0.3);
}
.cart-item {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}
.cart-item img { width: 50px; border-radius: 8px; }
.cart-item-info { flex: 1; font-size: 0.9rem; }
.cart-item-actions a { color: #ffb555ff; font-weight: bold; text-decoration: none; font-size: 1.2rem; }
.checkout-btn { display: block; text-align: center; background: linear-gradient(135deg,#bc8a31,#714903); color:#fff; font-weight:bold; padding:10px; border-radius:20px; margin-top:10px; text-decoration:none; transition:0.3s;}
.checkout-btn:hover { background: linear-gradient(135deg,#84652d,#5d3d04); transform:scale(1.05);}

/* Pagination */
/* Container pagination */
.pagination {
    text-align: center;
    margin: 20px 0;
}

/* Όλα τα links */
/* Pagination links κόκκινα πάντα */
.pagination a {
    background: black; /* μη-active */
    color: white;
    padding: 8px 12px;
    margin: 0 3px;
    text-decoration: none;
    border-radius: 5px;
    transition: background 0.3s;
}

.pagination a:hover {
    background: #333; /* λίγο πιο ανοιχτό στο hover */
}

.pagination a.active {
    background: #bc8a31 !important; /* κόκκινο για το active */
    color: #fff;
}


.product-card {
  position: relative;
  background: #111;
  border-radius: 12px;
  overflow: hidden;
  transition: transform 0.2s;
}

.product-card img {
  width: 100%;
  border-radius: 12px 12px 0 0;
}

/* ❤️ Κουμπί αγαπημένων */
.fav-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  background: rgba(0,0,0,0.5);
  border: none;
  cursor: pointer;
  border-radius: 50%;
  font-size: 1.4rem;
  color: #aaa;
  z-index: 10;
  padding: 6px 8px;
  transition: color 0.3s, transform 0.2s, background 0.3s;
}

/* Να φαίνονται ΠΑΝΤΑ για logged in */
.fav-btn {
  opacity: 1;
  visibility: visible;
}

/* Hover effect */
.fav-btn:hover {
  color: #ff6b6b;
  background: rgba(0,0,0,0.7);
  transform: scale(1.15);
}

/* Αν είναι ενεργή (αγαπημένο) */
.fav-btn.active i,
.fav-btn i.active {
  color: #e74c3c !important;
}

/* Αν έχεις CSS που κρύβει τις καρδιές στο hover, αφαίρεσέ το */
.product-card:hover .fav-btn {
  opacity: 1;
  visibility: visible;
}




</style>
</head>
<body>

<!-- MENU + CART -->
<?php include 'nav.php'; ?>


<!-- DROPDOWN ΚΑΛΑΘΙ -->
<div class="cart-container">
  <button class="cart-btn" onclick="toggleCart()">Καλάθι (<?= $cartCount ?>)</button>
  <div class="cart-dropdown" id="cartDropdown">
    <h3>Το Καλάθι Σου</h3>
    <?php if (!$cart_items): ?>
      <p style="text-align:center;color:#bbb;">Το καλάθι είναι άδειο</p>
    <?php else: 
    $total = 0;
    foreach ($cart_items as $id => $sizes):
        $res = $conn->query("SELECT * FROM products WHERE id=$id");
        $prod = $res->fetch_assoc();
        foreach ($sizes as $size => $quantity):
            $subtotal = $prod['price'] * $quantity;
            $total += $subtotal;
    ?>
    <div class="cart-item">
      <img src="<?= htmlspecialchars(explode(',',$prod['image'])[0]) ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
      <div class="cart-item-info">
        <span><?= htmlspecialchars($prod['name']) ?></span>
        <span>Μέγεθος: <?= htmlspecialchars($size) ?></span>
        <span>Ποσότητα: <?= htmlspecialchars($quantity) ?></span>
      </div>
      <div class="cart-item-actions">
        <a href="shop.php?remove=<?= $id ?>&size=<?= urlencode($size) ?>">×</a>
      </div>
    </div>
    <?php endforeach; endforeach; ?>
    <p style="color:#fff;font-weight:bold;margin-top:10px;">Σύνολο: <?= number_format($total,2) ?>€</p>
    <a href="checkout.php" class="checkout-btn">Ολοκλήρωση Αγοράς</a>
    <?php endif; ?>
  </div>
</div>

<!-- ΠΡΟΪΟΝΤΑ -->
<main class="shop-container">
<h2>Official Merchandise</h2>
<div class="products-grid">
<?php foreach ($products as $product): ?>
<div class="product-card">
<?php if ($isLoggedIn): ?>
  <button class="fav-btn <?= in_array($product['id'],$fav_products)?'active':'' ?>" data-id="<?= $product['id'] ?>">
    <i class="fas fa-heart <?= in_array($product['id'],$fav_products)?'active':'' ?>"></i>
  </button>
<?php endif; ?>

<a href="product.php?id=<?= $product['id'] ?>">
<img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
</a>
<h3><a href="product.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></a></h3>
<p class="price">
    <?php if($product['current_price'] < $product['price']): ?>
        <del><?= number_format($product['price'],2) ?>€</del>
        <span><?= number_format($product['current_price'],2) ?>€</span>
    <?php else: ?>
        <?= number_format($product['price'],2) ?>€
    <?php endif; ?>
</p>
</div>
<?php endforeach; ?>
</div>
</main>

<!-- PAGINATION -->
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>





<?php include 'footer.html'; ?>

<script>
function toggleCart() {
    const cart = document.getElementById('cartDropdown');
    cart.style.display = cart.style.display==='block'?'none':'block';
}

// AJAX favorites
document.querySelectorAll('.fav-btn').forEach(btn=>{
  btn.addEventListener('click', function(e){
    e.preventDefault();
    const productId = this.dataset.id;
    const heart = this.querySelector('i');

    fetch(`favorite_toggle.php?product_id=${productId}`)
      .then(res=>res.json())
      .then(data=>{
        if(data.success){
          this.classList.toggle('active');
          heart.classList.toggle('active');
        } else {
          alert(data.message || 'Πρέπει να συνδεθείς.');
        }
      })
      .catch(err=>{
        console.error('Σφάλμα:', err);
      });
  });
});


document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.getElementById('menu-toggle');
    const navLinks = document.getElementById('nav-links');

    menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
    });
});



</script>

</body>
</html>
