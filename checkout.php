<?php
session_start();
include 'config.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

$user_id = $_SESSION['user_id'] ?? null;
$cart_items = $_SESSION['cart'] ?? [];

// --- Υπολογισμός συνολικού με promotions ---
$total = 0;
$cart_detail = [];

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

    foreach($sizes as $size => $quantity){
        $subtotal = $prod['current_price'] * $quantity;
        $total += $subtotal;

        $cart_detail[] = [
            'id' => $prod['id'],
            'name' => $prod['name'],
            'image' => $prod['image'] ?? '',
            'size' => $size,
            'quantity' => $quantity,
            'price' => $prod['current_price'],
            'subtotal' => $subtotal
        ];
    }
}

// --- Έλεγχος discount ---
$discount_code = $_POST['discount_code'] ?? $_SESSION['discount_code'] ?? '';
$discount_percent = 0;
$discount_amount = 0;

if($discount_code){
    $stmt = $conn->prepare("SELECT * FROM discounts WHERE code=? LIMIT 1");
    $stmt->bind_param("s", $discount_code);
    $stmt->execute();
    $res = $stmt->get_result();
    if($row = $res->fetch_assoc()){
        $stmt2 = $conn->prepare("SELECT * FROM discount_usage WHERE discount_code=? AND user_id=?");
        $stmt2->bind_param("si", $discount_code, $user_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if($res2->num_rows == 0){
            $discount_percent = floatval($row['amount']);
            $discount_amount = $total * $discount_percent;
            $total -= $discount_amount;

            $_SESSION['discount_code'] = $discount_code;
            $_SESSION['discount_amount'] = $discount_percent;
        }
    }
}

// --- Υποβολή φόρμας ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['name'])){
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $address = htmlspecialchars($_POST['street'])." ".htmlspecialchars($_POST['street_number']);
    $floor = htmlspecialchars($_POST['floor']);
    $postcode = htmlspecialchars($_POST['postcode']);
    $city = htmlspecialchars($_POST['city']);
    $payment_method = $_POST['payment'] ?? 'cash';

    $fullAddress = "$address, Όροφος: $floor, $postcode $city";

    // --- Τρόπος πληρωμής ---
    $payment_info = '';
    if($payment_method === 'iris'){
        $payment_info = 'IRIS (Κινητό: 6971234567)';
    } elseif($payment_method === 'ibank'){
        $payment_info = 'IBANK (Λογαριασμός: GR1234567890123456789012345)';
    } else {
        $payment_info = 'Αντικαταβολή';
    }

    // --- Δημιουργία HTML πίνακα ---
    $orderHtml = "<h2>Λεπτομέρειες Παραγγελίας</h2>
                  <p><strong>Ονοματεπώνυμο:</strong> $name</p>
                  <p><strong>Email:</strong> $email</p>
                  <p><strong>Διεύθυνση:</strong> $fullAddress</p>
                  <p><strong>Τρόπος Πληρωμής:</strong> $payment_info</p>
                  <table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse;width:100%;'>
                  <tr style='background-color:#b3811e;color:#fff;'>
                    <th>Προϊόν</th><th>Εικόνα</th><th>Μέγεθος</th><th>Ποσότητα</th><th>Τιμή</th>
                  </tr>";

    $pdfHtml = "<h2>Λεπτομέρειες Παραγγελίας</h2>
                <p><strong>Ονοματεπώνυμο:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Διεύθυνση:</strong> $fullAddress</p>
                <p><strong>Τρόπος Πληρωμής:</strong> $payment_info</p>
                <table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse;width:100%;'>
                <tr style='background-color:#b3811e;color:#fff;'>
                    <th>Προϊόν</th><th>Εικόνα</th><th>Μέγεθος</th><th>Ποσότητα</th><th>Τιμή</th>
                </tr>";

    $embeddedImages = [];

    foreach($cart_detail as $item){
        $imagePath = null;
        $exts = ['png','jpg','jpeg','webp'];
        foreach($exts as $ext){
            $tmp = __DIR__ . "/images/{$item['id']}.$ext";
            if(file_exists($tmp)){
                $imagePath = $tmp;
                break;
            }
        }

        if($imagePath){
            $cid = "prod{$item['id']}";
            $embeddedImages[$cid] = $imagePath;
            $imgTagEmail = "<img src='cid:$cid' width='60'>";
            $imgTagPDF = "<img src='$imagePath' width='60'>";
        } else {
            $imgTagEmail = $imgTagPDF = "Δεν υπάρχει εικόνα";
        }

        $orderHtml .= "<tr>
                        <td>{$item['name']}</td>
                        <td>$imgTagEmail</td>
                        <td>{$item['size']}</td>
                        <td>{$item['quantity']}</td>
                        <td>€".number_format($item['subtotal'],2)."</td>
                       </tr>";

        $pdfHtml .= "<tr>
                        <td>{$item['name']}</td>
                        <td>$imgTagPDF</td>
                        <td>{$item['size']}</td>
                        <td>{$item['quantity']}</td>
                        <td>€".number_format($item['subtotal'],2)."</td>
                     </tr>";
    }

    if($discount_percent>0){
        $orderHtml .= "<tr>
                        <td colspan='4'>Έκπτωση ($discount_code)</td>
                        <td>-€".number_format($discount_amount,2)."</td>
                       </tr>";
        $pdfHtml .= "<tr>
                        <td colspan='4'>Έκπτωση ($discount_code)</td>
                        <td>-€".number_format($discount_amount,2)."</td>
                     </tr>";
    }

    $orderHtml .= "<tr>
                    <td colspan='4'><strong>Σύνολο</strong></td>
                    <td><strong>€".number_format($total,2)."</strong></td>
                   </tr></table>";

    $pdfHtml .= "<tr>
                    <td colspan='4'><strong>Σύνολο</strong></td>
                    <td><strong>€".number_format($total,2)."</strong></td>
                 </tr></table>";

    // --- Δημιουργία PDF ---
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SERLEO Shop');
    $pdf->SetTitle('Παραγγελία');
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    $pdf->setFontSubsetting(true);
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->AddPage();

   $logoFile = __DIR__ . '/images/logo.png';
if(file_exists($logoFile)){
    // Πλάτος 30mm αντί 40mm
    $pdf->Image($logoFile, 150, 10, 30);
}


    $pdf->SetY(25);
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 10, 'Λεπτομέρειες Παραγγελίας', 0, 1, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('dejavusans', '', 12);
    $pdf->MultiCell(0, 5, "Ονοματεπώνυμο: $name\nEmail: $email\nΔιεύθυνση: $fullAddress\nΤρόπος Πληρωμής: $payment_info", 0, 'L', 0, 1, '', '', true);
    $pdf->Ln(5);

    $tbl = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%">';
    $tbl .= '<tr style="background-color:#b3811e; color:#ffffff;">
                <th>Προϊόν</th>
                <th>Εικόνα</th>
                <th>Μέγεθος</th>
                <th>Ποσότητα</th>
                <th>Τιμή</th>
             </tr>';

    foreach($cart_detail as $item){
        $imgTag = "Δεν υπάρχει εικόνα";
        $imgPath = null;
        foreach($exts as $ext){
            $tmp = __DIR__ . "/images/{$item['id']}.$ext";
            if(file_exists($tmp)){
                $imgPath = $tmp;
                break;
            }
        }
        if($imgPath){
            $imgTag = '<img src="'.$imgPath.'" width="50" />';
        }

        $tbl .= '<tr>
                    <td>'.$item['name'].'</td>
                    <td>'.$imgTag.'</td>
                    <td>'.$item['size'].'</td>
                    <td>'.$item['quantity'].'</td>
                    <td>€'.number_format($item['subtotal'],2).'</td>
                 </tr>';
    }

    if($discount_percent>0){
        $tbl .= '<tr>
                    <td colspan="4" style="text-align:right;">Έκπτωση ('.$discount_code.')</td>
                    <td>-€'.number_format($discount_amount,2).'</td>
                 </tr>';
    }

    $tbl .= '<tr>
                <td colspan="4" style="text-align:right;"><strong>Σύνολο</strong></td>
                <td><strong>€'.number_format($total,2).'</strong></td>
             </tr>';

    $tbl .= '</table>';
    $pdf->writeHTML($tbl, true, false, true, false, '');
    $pdf->Ln(5);

    $extraText = "Σημαντική σημείωση: Τα προϊόντα αποστέλλονται εντός 3-5 εργάσιμων ημερών αφού γίνει η αποστολή των χρημάτων. Θα ενημερωθείτε με mail για την αποστολή.\nΜε το παρόν έγγραφο μπορεί να γίνει επιστροφή του προϊόντος εντός 14 ημερών.";
    $pdf->MultiCell(0, 6, $extraText, 0, 'L', 0, 1, '', '', true);

    $pdf_file = __DIR__.'/order_'.$user_id.'_'.time().'.pdf';
    $pdf->Output($pdf_file, 'F');

    // --- Αποστολή email ---
    try{
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'basixas69@gmail.com';
        $mail->Password = 'wpeldzaugmvffmnu';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('basixas69@gmail.com','SERLEO Shop');
        $mail->addAddress($email);
        $mail->addAddress('basixas69@gmail.com');

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Νέα Παραγγελία από $name";

        foreach($embeddedImages as $cid => $path){
            $mail->addEmbeddedImage($path, $cid);
        }

        $mail->Body = $orderHtml."<p>Σας ευχαριστούμε για την παραγγελία σας!</p>";
        $mail->addAttachment($pdf_file, "Παραγγελία_$name.pdf");

        $mail->send();
        unlink($pdf_file);
        $_SESSION['cart'] = [];
        unset($_SESSION['discount_code'], $_SESSION['discount_amount']);

        echo "<p style='color:green;text-align:center;'>Η παραγγελία στάλθηκε με επιτυχία! Έλεγξε το email σου.</p>";
    } catch(Exception $e){
        echo "<p style='color:red;text-align:center;'>Σφάλμα κατά την αποστολή: {$mail->ErrorInfo}</p>";
    }
}
?>





<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Checkout - SER LEO</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="images/logo.png" type="image/png">
<link rel="stylesheet" href="style.css">
<style>
.checkout-container {
    max-width: 600px;
    margin: 50px auto;
    padding: 20px;
    background: #111;
    color: #fff;
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(255, 187, 0, 0.3);
}
.checkout-container h2 { text-align: center; color: #b3811e; margin-bottom: 20px; }
.checkout-container label { display:block; margin:10px 0 5px; }
.checkout-container input,
.checkout-container select,
.checkout-container button { width:100%; padding:10px; border-radius:8px; border:none; margin-bottom:15px; }
.checkout-container button { background: linear-gradient(135deg, #b3811e, #ff8431); font-weight:bold; cursor:pointer; transition:0.3s; }
.checkout-container button:hover { background: linear-gradient(135deg, #ff8431, #ffbd69); }
#extraIris,#extraIBank {display:none;margin-top:10px;}
#extraIris input,#extraIBank input {background:#333;color:#ccc;}
</style>
</head>
<body>
<div class="checkout-container">
<div id="progressContainer" style="display:none;margin-top:15px;">
  <div style="background:#444;height:20px;border-radius:10px;overflow:hidden;">
    <div id="progressBar" style="background:#b3811e;height:100%;width:0%;transition:width 0.3s;"></div>
  </div>
  <p id="progressText" style="text-align:center;margin-top:5px;">Αποστολή παραγγελίας...</p>
</div>

<h2>Ολοκλήρωση Παραγγελίας</h2>
<form method="POST" id="checkoutForm" onsubmit="return sendOrder(event)">

<label>Όνομα</label>
<input type="text" name="name" required value="<?=htmlspecialchars($_POST['name'] ?? '')?>">
<label>Email</label>
<input type="email" name="email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
<label>Διεύθυνση</label>
<input type="text" name="street" placeholder="Οδός" required value="<?=htmlspecialchars($_POST['street'] ?? '')?>">
<label>Αριθμός</label>
<input type="text" name="street_number" placeholder="Αριθμός" required value="<?=htmlspecialchars($_POST['street_number'] ?? '')?>">
<label>Όροφος</label>
<input type="text" name="floor" placeholder="Π.χ. 2ος" value="<?=htmlspecialchars($_POST['floor'] ?? '')?>">
<label>Τ.Κ.</label>
<input type="text" id="postcode" name="postcode" placeholder="π.χ. 62100" required oninput="fetchCities()">
<label>Πόλη</label>
<select id="city" name="city"><option value="">Αυτόματη συμπλήρωση</option></select>
<div id="postcodeMessage" style="color:#b3811e;font-weight:bold;margin-bottom:15px;"></div>

<label>Κωδικός Έκπτωσης</label>
<div style="display:flex; gap:10px;">
<input type="text" id="discount_code" name="discount_code" value="<?=htmlspecialchars($_POST['discount_code'] ?? '')?>">
<button type="button" onclick="applyDiscount()">Εφαρμογή Κωδικού</button>
</div>
<div id="discountMessage" style="color:#b3811e;font-weight:bold;margin-bottom:15px;"></div>

<label>Τρόπος Πληρωμής</label>
<select name="payment" id="payment" onchange="toggleExtra()">
<option value="cash" <?= ($_POST['payment']??'')==='cash'?'selected':'' ?>>Αντικαταβολή</option>
<option value="iris" <?= ($_POST['payment']??'')==='iris'?'selected':'' ?>>IRIS</option>
<option value="ibank" <?= ($_POST['payment']??'')==='ibank'?'selected':'' ?>>IBANK</option>
</select>

<div id="extraIris">
<label>Κινητό IRIS</label>
<input type="text" value="6971234567" readonly>
</div>

<div id="extraIBank">
<label>Τραπεζικός Λογαριασμός IBANK</label>
<input type="text" value="GR1234567890123456789012345" readonly>
</div>

<button type="submit">Αποστολή Παραγγελίας</button>
</form>
</div>

<script>
function toggleExtra(){
    const payment = document.getElementById('payment').value;
    document.getElementById('extraIris').style.display = (payment==='iris')?'block':'none';
    document.getElementById('extraIBank').style.display = (payment==='ibank')?'block':'none';
}
toggleExtra();

function applyDiscount(){
    const code = document.getElementById('discount_code').value.trim();
    if(!code){
        document.getElementById('discountMessage').innerHTML = "Βάλε κωδικό έκπτωσης.";
        return;
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST','check_discount.php',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.onload = function(){
        document.getElementById('discountMessage').innerHTML = this.responseText;
    };
    xhr.send('discount_code=' + encodeURIComponent(code));
}


let fetchTimeout;
async function fetchCities(){
    const postcode = document.getElementById('postcode').value.trim();
    const citySelect = document.getElementById('city');
    const msg = document.getElementById('postcodeMessage');
    if(!postcode){ citySelect.innerHTML='<option value="">Αυτόματη συμπλήρωση</option>'; msg.innerHTML=""; return; }
    clearTimeout(fetchTimeout);
    fetchTimeout=setTimeout(async ()=>{
        msg.innerHTML="Αναζήτηση πόλεων...";
        citySelect.innerHTML='<option value="">Αναζήτηση...</option>';
        const url=`https://nominatim.openstreetmap.org/search?postalcode=${postcode}&country=Greece&format=json&addressdetails=1&accept-language=el`;
        try{
            const response = await fetch(url,{headers:{"User-Agent":"MyCheckoutApp/1.0"}});
            const data = await response.json();
            if(data.length>0){
                citySelect.innerHTML='<option value="">Επιλέξτε πόλη</option>';
                data.forEach(place=>{
                    let addr=place.address;
                    let locality = addr.city || addr.town || addr.village || addr.municipality || addr.county || "";
                    if(locality){
                        let opt = document.createElement('option');
                        opt.value=locality;
                        opt.textContent=locality+' ('+(addr.county||'')+')';
                        citySelect.appendChild(opt);
                    }
                });
                msg.innerHTML="Βρέθηκαν επιλογές. Διάλεξε την πόλη σου.";
            } else { citySelect.innerHTML='<option value="">Δεν βρέθηκε πόλη</option>'; msg.innerHTML="Δεν βρέθηκε πόλη για αυτό το Τ.Κ."; }
        } catch(e){ console.error(e); citySelect.innerHTML='<option value="">Σφάλμα</option>'; msg.innerHTML="Σφάλμα σύνδεσης"; }
    },500);
}


function sendOrder(e){
    e.preventDefault(); // σταματάμε το default submit

    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');

    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.textContent = 'Ξεκίνησε η αποστολή...';

    const form = document.getElementById('checkoutForm');
    const formData = new FormData(form);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);

    xhr.upload.onprogress = function(e){
        if(e.lengthComputable){
            let percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressText.textContent = `Αποστολή παραγγελίας... ${percent}%`;
        }
    };

    xhr.onload = function(){
        progressBar.style.width = '100%';
        progressText.textContent = '✅ Η παραγγελία στάλθηκε!';
        document.querySelector('.checkout-container').innerHTML = this.responseText;
    };

    xhr.onerror = function(){
        progressText.textContent = '❌ Σφάλμα κατά την αποστολή!';
    };

    xhr.send(formData);
    return false;
}


function sendOrder(e){
    e.preventDefault(); // Σταματάμε το default submit

    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');

    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.textContent = 'Ξεκίνησε η αποστολή...';

    let progress = 0;

    // Fake smooth animation
    const interval = setInterval(() => {
        if(progress < 95){ // φτάνει μέχρι 95% πριν το server response
            progress += Math.random() * 2; // τυχαία μικρά βήματα
            progressBar.style.width = Math.min(progress,95) + '%';
            progressText.textContent = `Αποστολή παραγγελίας... ${Math.floor(progress)}%`;
        }
    }, 100);

    const form = document.getElementById('checkoutForm');
    const formData = new FormData(form);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);

    xhr.onload = function(){
        clearInterval(interval);
        progressBar.style.width = '100%';
        progressText.textContent = '✅ Η παραγγελία στάλθηκε!';
        document.querySelector('.checkout-container').innerHTML = this.responseText;
    };

    xhr.onerror = function(){
        clearInterval(interval);
        progressBar.style.width = '100%';
        progressText.textContent = '❌ Σφάλμα κατά την αποστολή!';
    };

    xhr.send(formData);
    return false;
}

</script>
</body>
</html>
