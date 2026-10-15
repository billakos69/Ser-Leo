<?php
session_start();
include 'config.php';
$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;

$logoutMessage = '';
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $logoutMessage = "✅ Αποσυνδεθήκατε με επιτυχία!";
}

// Προσθήκη στο καλάθι
// Προσθήκη στο καλάθι
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $pid = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $size = $_POST['size'] ?? 'M';

    if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

    if(isset($_SESSION['cart'][$pid][$size])) {
        $_SESSION['cart'][$pid][$size] += $quantity;
    } else {
        $_SESSION['cart'][$pid][$size] = $quantity;
    }

    $_SESSION['addedMessage'] = "Προστέθηκε στο καλάθι!";

    // Κάνουμε redirect για να αποφύγουμε επανυποβολή του POST
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}


// Αφαίρεση προϊόντος (με μέγεθος)
if(isset($_GET['remove']) && isset($_GET['size'])) {
    $remove_id = (int)$_GET['remove'];
    $size = $_GET['size'];
    if(isset($_SESSION['cart'][$remove_id][$size])) {
        unset($_SESSION['cart'][$remove_id][$size]);
        if(empty($_SESSION['cart'][$remove_id])) {
            unset($_SESSION['cart'][$remove_id]);
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']."?id=".($_GET['id'] ?? 0));
    exit;
}

// Ανάκτηση προϊόντος
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "
SELECT p.*, 
  (CASE 
    WHEN pp.active=1 
         AND NOW() BETWEEN pp.start_date AND pp.end_date
         AND FIND_IN_SET(p.id, pp.products_id)
    THEN ROUND(p.price * (1 - pp.percentage/100), 2)
    ELSE p.price
  END) AS current_price
FROM products p
LEFT JOIN product_promotions pp
  ON FIND_IN_SET(p.id, pp.products_id)
WHERE p.id=$id
LIMIT 1
";
$result = $conn->query($sql);
$product = $result->fetch_assoc();
if(!$product) die("Προϊόν δεν βρέθηκε.");
$images = explode(',', $product['image']);
// ====== ΝΕΟ ΚΟΜΜΑΤΙ ΓΙΑ OPTION ======
$option_values = ['One Size'];
if(!empty($product['product_options_id'])) {
    $option_id = (int)$product['product_options_id'];
    // Διορθωμένο query: παίρνουμε το πεδίο 'size'
    $opt_res = $conn->query("SELECT size FROM product_options WHERE id=$option_id");
    if($opt = $opt_res->fetch_assoc()) {
        $size_str = $opt['size'];
        // Σπάμε είτε με κόμμα είτε με τελεία ανάλογα με το format
        if(strpos($size_str, '.') !== false){
            $option_values = array_map('trim', explode('.', $size_str));
        } else {
            $option_values = array_map('trim', explode(',', $size_str));
        }
    }
}




?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($product['name']) ?> - Ser Leo FC Shop</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">

<link rel="icon" href="images/logo.png" type="image/png">
<style>
    html, body {
    height: 100%;
    margin: 0;
}

body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

main {
    flex: 1; /* το main καταλαμβάνει όλο τον διαθέσιμο χώρο */
}

footer {
    background: #111;
    color: #fff;
    text-align: center;
    padding: 20px;
}

/* ====== SLIDER ====== */
.slider { position: relative; max-width: 500px; margin: 0 auto 25px; overflow: hidden; border-radius: 12px; }
.slider img { width: 100%; display: none; border-radius: 12px; transition: opacity 0.4s ease; }
.slider img.active { display: block; }
.slider .prev, .slider .next { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(186, 112, 0, 0.8); color: #000; font-weight: bold; font-size: 1.2rem; padding: 10px 14px; border-radius: 50%; cursor: pointer; user-select: none; transition: background 0.3s, transform 0.3s; }
.slider .prev:hover, .slider .next:hover { background: #ffa631ff; transform: translateY(-50%) scale(1.1); }
.slider .prev { left: 10px; }
.slider .next { right: 10px; }

/* ====== PRODUCT DETAIL ====== */
.product-detail-container {
    max-width: 900px;
    margin: 140px auto 60px;
    padding: 30px;
    background: #111;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(179, 135, 14, 0.6);
    display: flex;
    flex-direction: row;
    gap: 40px;
}
.product-detail-container h2 { font-size: 2rem; color: #bc8a31; margin-bottom: 25px; }
.price { font-size: 1.5rem; font-weight: 600; color: #fff; margin: 15px 0; }
form { margin-top: 20px; display: flex; flex-direction: column; gap: 15px; align-items: center; }
form label { font-size: 1rem; color: #ddd; }
form input[type="number"], form select { padding: 8px 12px; border-radius: 8px; border: none; background: #222; color: #fff; font-size: 1rem; width: 120px; text-align: center; }
form button { background: linear-gradient(135deg, #bc8a31, #ffa631ff); color: #000; font-weight: 600; padding: 10px 25px; border-radius: 25px; border: none; cursor: pointer; transition: 0.3s; }
form button:hover { background: linear-gradient(135deg, #724102ee, #ffa631ff); color: #000; transform: scale(1.05); }

/* ====== FULLSCREEN MODAL ====== */
.image-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.9);
    justify-content: center;
    align-items: center;
}
.image-modal img {
    max-width: 90%;
    max-height: 80%;
    border-radius: 12px;
    transition: transform 0.3s;
}
.image-modal .close {
    position: absolute;
    top: 20px; right: 30px;
    font-size: 2rem;
    font-weight: bold;
    color: #fff;
    cursor: pointer;
}
.image-modal .modal-prev,
.image-modal .modal-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    font-size: 2rem;
    color: #fff;
    background: rgba(0,0,0,0.5);
    padding: 10px 15px;
    border-radius: 50%;
    cursor: pointer;
    user-select: none;
}
.image-modal .modal-prev { left: 20px; }
.image-modal .modal-next { right: 20px; }

/* ====== CART DROPDOWN ====== */
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
 .price del {
            color: #888;
            font-size: 0.9rem;
            margin-right: 5px;
        }
        .price span {
            color: #ff0000;
            font-weight: bold;
        }
/* ====== Responsive adjustments ====== */
@media (max-width: 1024px) {
    .product-detail-container {
        flex-direction: column;
        margin: 120px 20px 40px;
        padding: 20px;
    }
    .slider {
        max-width: 100%;
    }
}

/* Κινητά */
@media (max-width: 768px) {
    .product-detail-container {
        margin: 100px 15px 30px;
        padding: 15px;
        gap: 20px;
    }

    form input[type="number"], form select {
        width: 100%;
        max-width: 150px;
    }

    .cart-container {
        right: 10px;
        top: 70px;
    }

    .cart-dropdown {
        width: 90vw;
        right: 5vw;
        top: 50px;
    }

    .slider .prev, .slider .next {
        font-size: 1.5rem;
        padding: 8px 12px;
    }

    
}

</style>
</head>
<body>
<header class="header">
<!-- MENU -->
<?php include 'nav.php'; ?>


    
</header>
<div class="cart-container">
<?php
$cartCount = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $id => $sizes) {
        foreach($sizes as $size => $qty) {
            $cartCount += $qty;
        }
    }
}
?>
<button class="cart-btn" onclick="toggleCart()">Καλάθι (<?= $cartCount ?>)</button>
        <div class="cart-dropdown" id="cartDropdown">
    <h3>Το Καλάθι Σου</h3>
    <?php
    $cart_items = $_SESSION['cart'] ?? [];
    if(!$cart_items): ?>
        <p style="text-align:center;color:#bbb;">Το καλάθι είναι άδειο</p>
    <?php else:
        $total = 0;
        foreach($cart_items as $id => $sizes):
            // Παίρνουμε το προϊόν μαζί με την τρέχουσα τιμή (promotion)
            $res = $conn->query("
                SELECT p.*, 
                  (CASE 
                    WHEN pp.active=1 
                         AND NOW() BETWEEN pp.start_date AND pp.end_date
                         AND FIND_IN_SET(p.id, pp.products_id)
                    THEN ROUND(p.price * (1 - pp.percentage/100), 2)
                    ELSE p.price
                  END) AS current_price
                FROM products p
                LEFT JOIN product_promotions pp
                  ON FIND_IN_SET(p.id, pp.products_id)
                WHERE p.id=$id
                LIMIT 1
            ");
            $prod = $res->fetch_assoc();

            foreach($sizes as $size => $quantity):
                $price = $prod['current_price'];
                $subtotal = $price * $quantity;
                $total += $subtotal;
    ?>
    <div class="cart-item">
        <img src="<?= htmlspecialchars(explode(',',$prod['image'])[0]) ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
        <div class="cart-item-info">
            <span><?= htmlspecialchars($prod['name']) ?></span>
            <span>Μέγεθος: <?= htmlspecialchars($size) ?></span>
            <span>Ποσότητα: <?= htmlspecialchars($quantity) ?></span>
            <span>Τιμή: <?= number_format($price,2) ?>€</span>
        </div>
        <div class="cart-item-actions">
            <a href="product.php?remove=<?= $id ?>&size=<?= urlencode($size) ?>&id=<?= $product['id'] ?>">×</a>
        </div>
    </div>
    <?php endforeach; endforeach; ?>
    <p style="color:#ffffff;font-weight:bold;margin-top:10px;">Σύνολο: <?= number_format($total,2) ?>€</p>
    <a href="checkout.php" class="checkout-btn">Ολοκλήρωση Αγοράς</a>
    <?php endif; ?>
</div>

    </div>
<main class="product-detail-container">
    <h2><?= htmlspecialchars($product['name']) ?></h2>
    <div class="slider">
        <?php foreach($images as $i => $img): ?>
            <img src="<?= htmlspecialchars($img) ?>" class="<?= $i===0 ? 'active' : '' ?>">
        <?php endforeach; ?>
        <div class="prev">&#10094;</div>
        <div class="next">&#10095;</div>
    </div>
    <!-- FULLSCREEN MODAL -->
<div class="image-modal" id="imageModal">
  <span class="close" onclick="closeModal()">&times;</span>
  <span class="modal-prev" onclick="modalPrev()">&#10094;</span>
  <img id="modalImage" src="" alt="">
  <span class="modal-next" onclick="modalNext()">&#10095;</span>
</div>

   <p class="price">
    <?php if($product['current_price'] < $product['price']): ?>
        <del><?= number_format($product['price'],2) ?>€</del>
        <span><?= number_format($product['current_price'],2) ?>€</span>
    <?php else: ?>
        <?= number_format($product['price'],2) ?>€
    <?php endif; ?>
</p>
    <form method="POST">
        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
        <label>Ποσότητα: <input type="number" name="quantity" value="1" min="1"></label>
        <label>Μέγεθος:
                    <select name="size">
            <?php foreach($option_values as $val): ?>
                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($val) ?></option>
            <?php endforeach; ?>
            </select>

        </label>
        <button type="submit">Προσθήκη στο Καλάθι</button>
    </form>
    <?php if(!empty($addedMessage)): ?>
        <p style="color:#0baa93; text-align:center; margin-top:10px;"><?= $addedMessage ?></p>
    <?php endif; ?>
</main>


<?php include 'footer.html'; ?>

<script src="navbar.js"></script>

<script>
let slides = document.querySelectorAll('.slider img');
let index = 0;
document.querySelector('.next').onclick = function() {
    slides[index].classList.remove('active');
    index = (index+1) % slides.length;
    slides[index].classList.add('active');
};
document.querySelector('.prev').onclick = function() {
    slides[index].classList.remove('active');
    index = (index-1+slides.length) % slides.length;
    slides[index].classList.add('active');
};

function toggleCart() {
    const cart = document.getElementById('cartDropdown');
    cart.style.display = cart.style.display === 'block' ? 'none' : 'block';
}

let modal = document.getElementById("imageModal");
let modalImg = document.getElementById("modalImage");
let modalIndex = 0;

// Άνοιγμα modal όταν πατάς εικόνα
slides.forEach((img, i) => {
    img.addEventListener("click", () => {
        modal.style.display = "flex";
        modalImg.src = img.src;
        modalIndex = i;
    });
});

function closeModal() {
    modal.style.display = "none";
}

function modalNext() {
    modalIndex = (modalIndex+1) % slides.length;
    modalImg.src = slides[modalIndex].src;
}

function modalPrev() {
    modalIndex = (modalIndex-1+slides.length) % slides.length;
    modalImg.src = slides[modalIndex].src;
}

// Κλείσιμο με ESC
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeModal();
    if (e.key === "ArrowRight") modalNext();
    if (e.key === "ArrowLeft") modalPrev();
});


document.addEventListener('DOMContentLoaded', () => {
    // Burger menu
    const menuToggle = document.getElementById('menu-toggle');
    const navLinks = document.getElementById('nav-links');

    if(menuToggle && navLinks){
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
        });
    }

    // Cart toggle
    const cartBtn = document.querySelector('.cart-btn');
    const cartDropdown = document.getElementById('cartDropdown');

    if(cartBtn && cartDropdown){
        cartBtn.addEventListener('click', () => {
            cartDropdown.classList.toggle('show');
        });
    }
});


</script>
</body>
</html>
