<?php
session_start();
include 'config.php';

// Έλεγχος admin
if(!isset($_SESSION['user']) || $_SESSION['user']!=='Admin'){
    header("Location: login.php"); exit;
}

// Αν υπάρχει ID, φορτώνουμε το promotion
$id = $_GET['id'] ?? null;
$promotion = [
    'products_id'=>'',
    'percentage'=>0,
    'start_date'=>'',
    'end_date'=>'',
    'active'=>1
];

if($id){
    $res = $conn->query("SELECT * FROM product_promotions WHERE id=".(int)$id);
    if($res && $res->num_rows) $promotion = $res->fetch_assoc();
}

// Παίρνουμε όλα τα προϊόντα για τη φόρμα
$allProducts = $conn->query("SELECT id, name, image FROM products ORDER BY name ASC");

// Save form
if($_SERVER['REQUEST_METHOD']=='POST'){
    $products = $_POST['products'] ?? [];
    $percentage = floatval($_POST['percentage'] ?? 0);
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;

    // Μετατροπή των πολλαπλών προϊόντων σε CSV
    $products_list = implode(',', array_map('intval', $products));

    if($id){
        // UPDATE
        $stmt = $conn->prepare("UPDATE product_promotions SET products_id=?, percentage=?, start_date=?, end_date=? WHERE id=?");
        $stmt->bind_param("ssssi", $products_list, $percentage, $start_date, $end_date, $id);
        $stmt->execute();
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO product_promotions (products_id, percentage, start_date, end_date) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $products_list, $percentage, $start_date, $end_date);
        $stmt->execute();
    }

    // Μετά το save πάμε πίσω στο admin panel
    header("Location: admin_panel.php?section=promotions");
    exit;
}

// Προεπιλεγμένα επιλεγμένα προϊόντα
$selectedProducts = explode(',', $promotion['products_id']);

?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= $id ? "Επεξεργασία Promotion" : "Προσθήκη Promotion" ?></title>
<style>
body{background:#111;color:#fff;font-family:'Poppins',sans-serif;}
form{max-width:700px;margin:20px auto;background:#222;padding:20px;border-radius:12px;display:flex;flex-direction:column;gap:15px;}
form input, form select{padding:8px 12px;border-radius:8px;border:none;background:#111;color:#fff;}
form button{padding:10px 25px;border-radius:25px;border:none;background:linear-gradient(135deg,#bc8a31,#5b3d09);font-weight:600;cursor:pointer;transition:0.3s;}
form button:hover{transform:scale(1.05);background:linear-gradient(135deg,#b1802a,#805405);}
.products-gallery{display:flex;flex-wrap:wrap;gap:15px;margin-bottom:20px;justify-content:center;}
.product-item{display:flex;flex-direction:column;align-items:center;width:140px;cursor:pointer;border:2px solid transparent;border-radius:12px;padding:5px;transition:0.3s;}
.product-item img{width:120px;height:120px;object-fit:cover;border-radius:12px;margin-bottom:5px;}
.product-item input[type="checkbox"]{display:none;}
.product-item input[type="checkbox"]:checked + img{border:2px solid #bc8a31;}
.product-item input[type="checkbox"]:checked + img + span{font-weight:bold;color:#bc8a31;}
.product-item:hover{transform:scale(1.05);}
@media(max-width:768px){.product-item{width:45%;} .product-item img{width:100%;height:auto;}}
</style>
</head>
<body>
<form method="POST">
    <label>Επιλογή Προϊόντων:</label>
    <div class="products-gallery">
        <?php while($p = $allProducts->fetch_assoc()): 
            $img = explode(',', $p['image'])[0];
        ?>
        <label class="product-item">
            <input type="checkbox" name="products[]" value="<?= $p['id'] ?>"
                <?= in_array($p['id'], $selectedProducts) ? 'checked' : '' ?>>
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
            <span><?= htmlspecialchars($p['name']) ?></span>
        </label>
        <?php endwhile; ?>
    </div>

    <label>Ποσοστό Έκπτωσης (%):</label>
    <input type="number" name="percentage" min="0" max="100" value="<?= htmlspecialchars($promotion['percentage']) ?>">

    <label>Ημερομηνία Έναρξης:</label>
    <input type="date" name="start_date" value="<?= htmlspecialchars($promotion['start_date']) ?>">

    <label>Ημερομηνία Λήξης:</label>
    <input type="date" name="end_date" value="<?= htmlspecialchars($promotion['end_date']) ?>">

    <button type="submit"><?= $id ? "Ενημέρωση" : "Αποθήκευση" ?></button>
</form>
</body>
</html>


<script>
    // JS για να βάλει τις εικόνες στα options
document.querySelectorAll('#productsSelect option').forEach(opt => {
    const img = opt.dataset.img;
    if(img){
        opt.style.backgroundImage = `url(${img})`;
    }
});

</script>
