<?php
session_start();
include 'config.php';
header('Content-Type: text/html; charset=UTF-8');

$user_id = $_SESSION['user_id'] ?? null;
$cart_items = $_SESSION['cart'] ?? [];

if(!$user_id || empty($cart_items)){
    die('Σφάλμα: Άδειο καλάθι ή κάντε σύνδεση.');
}

$code = trim($_POST['discount_code'] ?? '');
if(!$code){
    die("Βάλε κωδικό έκπτωσης.");
}

// Υπολογισμός τρέχουσας τιμής με promotions
$total = 0;
foreach($cart_items as $id => $sizes){
    $stmt = $conn->prepare("
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
        WHERE p.id=?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $prod = $res->fetch_assoc();

    foreach($sizes as $size => $qty){
        $total += $prod['current_price'] * intval($qty);
    }
}

// Έλεγχος discount
$stmt = $conn->prepare("SELECT * FROM discounts WHERE code=? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows){
    // Έλεγχος αν ο χρήστης τον έχει ήδη χρησιμοποιήσει
    $stmt2 = $conn->prepare("SELECT * FROM discount_usage WHERE discount_code=? AND user_id=?");
    $stmt2->bind_param("si", $code, $user_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    if($res2->num_rows){
        echo "<p style='color:orange;'>Έχετε ήδη χρησιμοποιήσει αυτόν τον κωδικό.</p>";
    } else {
        $row = $res->fetch_assoc();
        $discount = floatval($row['amount']); // π.χ. 0.10 = 10%
        $discount_amount = $total * $discount;
        $total_after_discount = $total - $discount_amount;

        echo "<p>Αρχικό ποσό: €".number_format($total,2)."<br>
              Έκπτωση: ".($discount*100)."%<br>
              Τελικό ποσό: €".number_format($total_after_discount,2)."</p>";

        // Αποθήκευση στο session
        $_SESSION['discount_code'] = $code;
        $_SESSION['discount_amount'] = $discount;
    }
} else {
    echo "<p style='color:red;'>Μη έγκυρος κωδικός έκπτωσης.</p>";
}
?>
