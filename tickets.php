<?php
session_start();
include 'config.php';
require 'vendor/autoload.php';

// DB
$host="localhost"; $user="root"; $pass=""; $db="movies_db";
$conn=new mysqli($host,$user,$pass,$db);
if($conn->connect_error) die("DB Error: ".$conn->connect_error);

$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;

if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// --- HELPER: download image ---
function downloadImage($url){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data ?: false;
}

// --- Αφαίρεση από καλάθι ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove'])){
    $key = $_POST['remove'];
    unset($_SESSION['cart'][$key]);
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}

// --- Checkout ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['checkout_done'])){
    $emails = $_POST['email'];
    $first_names = $_POST['first_name'];
    $last_names = $_POST['last_name'];
    $amkas = $_POST['amka'];
    $paymentMethod = $_POST['payment_method'] ?? 'Γήπεδο';
    $irisCode      = $_POST['iris_code'] ?? '6971234567';
    $ibankId       = $_POST['ibank_id'] ?? 'GR1234567890123456789012345';

    $pdfFiles = [];
// Παίρνουμε το πρώτο εισιτήριο για να δείξουμε το όνομα χρήστη
$first_name_main = htmlspecialchars($first_names[0]);
$last_name_main  = htmlspecialchars($last_names[0]);

$mailBody = '
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; background:#f4f4f4; padding:20px; color:#333; }
  .container { background:#fff; border-radius:10px; overflow:hidden; max-width:600px; margin:auto; box-shadow:0 3px 10px rgba(0,0,0,0.2); }
  .header { background:#010101; padding:20px; text-align:center; color:#fff; }
  .header img { max-width:120px; }
  .content { padding:20px; }
  .ticket { border:1px solid #ddd; border-radius:8px; margin-bottom:15px; padding:15px; background:#fafafa; }
  .ticket h3 { margin:0 0 10px; color:#bc8a31; }
  .ticket p { margin:5px 0; }
  .footer { text-align:center; padding:15px; font-size:12px; color:#777; }
</style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="cid:logoimg" alt="Logo">
      <h2>Αποδείξεις Αγοράς Εισιτηρίων</h2>
    </div>
    <div class="content">
      <p>Αγαπητέ <strong>'.$first_name_main.' '.$last_name_main.'</strong>,</p>
      <p>Σας ευχαριστούμε για την αγορά εισιτηρίων από την <strong>SERLEO</strong>. Παρακάτω θα βρείτε τα στοιχεία των εισιτηρίων σας:</p>
';

// loop στα εισιτήρια
foreach($_SESSION['cart'] as $index=>$ticket){
    $mailBody .= "
    <div class='ticket'>
      <h3>Εισιτήριο ".($index+1)."</h3>
      <p><strong>Θύρα:</strong> {$ticket['Gate']} | <strong>Σειρά:</strong> {$ticket['seat_row']} | <strong>Θέση:</strong> {$ticket['seat_number']}</p>
      <p><strong>Τιμή:</strong> {$ticket['price']} €</p>
    </div>";
}

$mailBody .= '
      <p>Τα αντίστοιχα PDF αρχεία με τα εισιτήρια σας επισυνάπτονται στο παρόν email.</p>
    </div>
    <div class="footer">
      <p>© 2025 SERLEO FC - Όλα τα δικαιώματα διατηρούνται</p>
    </div>
  </div>
</body>
</html>';


    foreach($_SESSION['cart'] as $index=>$ticket){
        $first_name = htmlspecialchars($first_names[$index]);
        $last_name  = htmlspecialchars($last_names[$index]);
        $amka       = htmlspecialchars($amkas[$index]);

        // Σημείωση booked στη DB
        $conn->query("UPDATE seats SET available=1 WHERE Gate='{$ticket['Gate']}' AND seat_row='{$ticket['seat_row']}' AND seat_number='{$ticket['seat_number']}'");


        // Λήψη εικόνας εισιτηρίου από DB
        $imageFileCenter = null;
        if(!empty($ticket['image_url'])){
            $imgData = downloadImage($ticket['image_url']);
            if($imgData){
                $ext = pathinfo(parse_url($ticket['image_url'], PHP_URL_PATH), PATHINFO_EXTENSION);
                $imageFileCenter = sys_get_temp_dir()."/seat_{$ticket['Gate']}_{$ticket['seat_row']}_{$ticket['seat_number']}.$ext";
                file_put_contents($imageFileCenter, $imgData);
            }
        }

        // --- Δημιουργία PDF ---
        $pdf = new TCPDF('P','mm','A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('3D Stadium Tickets');
        $pdf->SetTitle("Εισιτήριο {$ticket['Gate']}-{$ticket['seat_row']}-{$ticket['seat_number']}");
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->AddPage();
        

     // --- Εικόνα εισιτηρίου ---
if($imageFileCenter && file_exists($imageFileCenter)){
    $pageWidth = $pdf->getPageWidth() - 20; // margins
    $imgY = 10; // απόσταση από πάνω
    $pdf->Image($imageFileCenter, 10, $imgY, $pageWidth, 0, '', '', '', false, 300);

    // Υπολογίζουμε νέο Y για τα στοιχεία
    $imgHeight = $pdf->getImageRBY() - $imgY; // getImageRBY() επιστρέφει το κάτω όριο της εικόνας
    $pdf->SetY($imgY + $imgHeight + 40); // αφήνουμε 5mm κενό
}

$paymentMethod = $_POST['payment_method'] ?? 'Γήπεδο';
$irisCode      = $_POST['iris_code'] ?? ($_SESSION['iris_code'] ?? '');
$ibankId       = $_POST['ibank_id'] ?? ($_SESSION['ibank_id'] ?? '');

$paymentText = '';
if($paymentMethod === 'Γήπεδο'){
    $paymentText = 'Εκδοτήριο';
} elseif($paymentMethod === 'IRIS') {
    $paymentText = "IRIS - $irisCode";
} elseif($paymentMethod === 'IBANK') {
    $paymentText = "IBANK - $ibankId";
}


// --- Στοιχεία εισιτηρίου ---
$html = "
<h2 style='color:#d32f2f; text-align:center;'>Εισιτήριο Αγώνα</h2>
<p><strong>ΟΝΟΜΑ:</strong> $first_name</p>
<p><strong>ΕΠΩΝΥΜΟ:</strong> $last_name</p>
<p><strong>AMKA:</strong> $amka</p>
<p><strong>Θύρα:</strong> {$ticket['Gate']} | Σειρά: {$ticket['seat_row']} | Θέση: {$ticket['seat_number']}</p>
<p><strong>Τιμή:</strong> {$ticket['price']} €</p>
<p><strong>Αποδείξη αγοράς:</strong> #".($index+1)."</p>
<p><strong>Τρόπος Πληρωμής:</strong> $paymentText</p>";
$pdf->writeHTML($html, true, false, true, false, '');

// --- Χορηγός στο κέντρο ---
$sponsorCenter = glob(__DIR__.'/images/sponsor2.*');
if(count($sponsorCenter) && file_exists($sponsorCenter[0])){
    $pdf->SetY($pdf->GetY() + 5); // λίγο κενό πριν τον κεντρικό χορηγό
    $pdf->Image($sponsorCenter[0], ($pdf->getPageWidth()-80)/2, $pdf->GetY(), 80, 0, '', '', '', false, 300);
    $pdf->SetY($pdf->GetY() + 30); // λίγο κενό μετά
}

// --- Χορηγός full-width κάτω ---
$sponsorFull = glob(__DIR__.'/images/sponsor3.*');
if(count($sponsorFull) && file_exists($sponsorFull[0])){
    $pdf->SetY($pdf->GetY() + 5); // λίγο κενό πριν full-width
    $pdf->Image($sponsorFull[0], 10, $pdf->GetY(), $pdf->getPageWidth()-20, 40, '', '', '', false, 300);
    $pdf->SetY($pdf->GetY() + 45); // αφήνουμε χώρο κάτω
}

// --- Όροι χρήσης ---
$pdf->SetFont('dejavusans', '', 8); // Μικρότερο μέγεθος για όλο το κείμενο
$pdf->SetTextColor(85, 85, 85);    // Γκρι χρώμα (RGB 85,85,85)

$htmlTerms = <<<HTML
<h4 style="font-size:9px; color:#777;">ΟΡΟΙ ΧΡΗΣΗΣ</h4>
<ul style="font-size:8px; color:#555;">
<li>Βάσει της ισχύουσας νομοθεσίας, κατά την άφιξή σας στο γήπεδο θα πρέπει απαραιτήτως να έχετε μαζί σας την αστυνομική σας ταυτότητα ή γενικότερα οποιοδήποτε
αποδεικτικό έγγραφο ταυτοποίησης.</li>
<li>Οι παρούσες οδηγίες δύναται να τροποποιηθούν σε περίπτωση αλλαγής της ισχύουσας νομοθεσίας.</li>
<li>Οι όροι χρήσης εισιτηρίων & εισόδου στο γήπεδο είναι αναρτημένοι στην επίσημη ιστοσελίδα της SERLEO στο Terms of Use .</li>
<li>Η συλλογή ή/και μετάδοση ή/και παραγωγή και/ή διάδοση οποιασδήποτε πληροφορίας ή δεδομένων σχετικά με την εξέλιξη του αγώνα, ή οποιουδήποτε είδους
ηχογράφηση οποιουδήποτε ήχου, βίντεο ή οπτικοακουστικού υλικού σε οποιοδήποτε αγώνα (είτε με χρήση ηλεκτρονικών συσκευών είτε με άλλο τρόπο) για σκοπούς
οποιασδήποτε μορφής στοιχήματος, τυχερών παιχνιδιών ή εμπορικών δραστηριοτήτων που δεν έχουν εγκριθεί εκ των προτέρων ή για άλλους σκοπούς που
παραβιάζουν τους όρους και προϋποθέσεις συμβατικών συνεργασιών, απαγορεύεται αυστηρά στα Στάδια/Γήπεδα του Πρωταθλήματος , εκτός από τις περιπτώσεις
όπου η άδεια χορηγείται ρητά από τη γηπεδούχο Π.Α.Ε. Τα κινητά τηλέφωνα μπορούν να χρησιμοποιηθούν μόνο για προσωπική, ιδιωτική χρήση.
Σε περίπτωση παραβίασης αυτών των όρων και προϋποθέσεων, οι παραβάτες φίλαθλοι θα πρέπει, στα όρια του εφικτού, να απομακρύνονται από το γήπεδο επιμέλεια
της γηπεδούχου Π.Α.Ε., εφόσον κάτι τέτοιο υποπίπτει στην αντίληψή της. .</li>
</ul>
HTML;
$pdf->writeHTML($htmlTerms, true, false, true, false, '');


        // --- Αποθήκευση PDF ---
        $pdfFile = __DIR__."/ticket_{$ticket['Gate']}_{$ticket['seat_row']}_{$ticket['seat_number']}.pdf";
        $pdf->Output($pdfFile,'F');
        $pdfFiles[] = $pdfFile;

        $mailBody .= "<p>Θύρα {$ticket['Gate']} Σειρά {$ticket['seat_row']} Θέση {$ticket['seat_number']} (€{$ticket['price']})</p>";
    }


    // --- Αποστολή email ---
    try{
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->addEmbeddedImage(__DIR__.'/images/logo.png', 'logoimg');
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'basixas69@gmail.com';
        $mail->Password = 'wpeldzaugmvffmnu';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        
        $mail->setFrom('basixas69@gmail.com','tickets@serleo.gr');
        $mail->addAddress($emails);
        $mail->addAddress('basixas69@gmail.com');

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Αποδείξεις Αγοράς Εισιτηρίων";
        $mail->Body = $mailBody;

        foreach($pdfFiles as $f) $mail->addAttachment($f);

        $mail->send();
        echo "<p style='color:green;'>Το email στάλθηκε με επιτυχία!</p>";
        $_SESSION['cart'] = [];

        // Διαγραφή temp PDFs
        foreach($pdfFiles as $f) if(file_exists($f)) unlink($f);

    } catch(\PHPMailer\PHPMailer\Exception $e){
        echo "<p style='color:red;'>Σφάλμα: {$mail->ErrorInfo}</p>";
    }
   
    if(isset($_POST['ajax']) && $_POST['ajax']==1){
        $_SESSION['cart'] = [];
        exit; // <--- ΣΗΜΑΝΤΙΚΟ: σταματά η εκτέλεση πριν ξαναγραφτεί το nav
    }
}

// --- Προσθήκη στο καλάθι ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_to_cart']) && !empty($_POST['selected_seats'])){
    foreach($_POST['selected_seats'] as $seatKey){
        [$gate, $row, $number] = explode('-', $seatKey);


        // Έλεγχος αν υπάρχει ήδη
        $exists = false;
        foreach($_SESSION['cart'] as $c){
            if($c['Gate']==$gate && $c['seat_row']==$row && $c['seat_number']==$number){
                $exists = true; break;
            }
        }
        if(!$exists){
            // Τιμή και image_url από DB
            $res = $conn->query("SELECT price,image_url FROM seats WHERE Gate='$gate' AND seat_row='$row' AND seat_number='$number'");
            $rowDB = $res->fetch_assoc();
            $isAjax = isset($_POST['ajax']) && $_POST['ajax']==1;
            $price = $rowDB['price'] ?? 10;
            $image_url = $rowDB['image_url'] ?? '';

            $_SESSION['cart'][] = [
                'Gate' => $gate,
                'seat_row' => $row,
                'seat_number' => $number,
                'price' => $price,
                'image_url' => $image_url
            ];
        }
    }
    header("Location: ?page=seats&match=".$_GET['match']."&gate=".$_GET['gate']);
    exit;
}

$page = $_GET['page'] ?? 'tick';

?>


<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
  <title>SER LEO F.C</title>
<link rel="stylesheet" href="style.css">
<link rel="icon" href="images/logo.png" type="image/png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
<style>
    
h1, h2 {
    margin: 80px;
    text-align: center;
    color: #bc8a31;
}

html, body {
    height: 100%;
    margin: 0;
    display: flex;
    flex-direction: column;
}

body > .seat-page {
    flex: 1; /* όλη η σελίδα με θέσεις γεμίζει τον διαθέσιμο χώρο */
}

footer {
    margin-top: auto; /* πάντα κάτω */
}



/* Μόνο για τη σελίδα θέσεων */
.seat-page {
    display: flex;
    flex-direction: column;
    min-height: 100vh; /* πλήρες ύψος viewport */
}

.seat-container {
    flex: 1; /* καταλαμβάνει όλο το διαθέσιμο ύψος */
    display: flex;
    flex-direction: column;
}


.cart-icon {
    position: fixed;
    top: 80px;
    right: 20px;
    background: #bc8a31;
    color: #fff;
    padding: 10px 15px;
    border-radius: 25px;
    font-weight: bold;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.5);
    transition: background 0.3s;
}
.cart-icon:hover {
    background: #a4762a;
}
.cart-count {
    background: #000;
    color: #fff;
    border-radius: 50%;
    padding: 3px 8px;
    font-size: 12px;
    margin-left: 6px;
}

.tick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin: 20px;
}

.match-card {
    background: #222;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.5);
    text-align: center;
    display: inline-block;
    padding: 0;
    transition: transform 0.3s, box-shadow 0.3s;
}
.match-card img {
    display: block;
    width: 100%;
    height: auto;
    max-height: 300px;
    object-fit: contain;
}
.match-card .info {
    padding: 10px;
}
.match-card.upcoming {
    background: #333;
    color: #aaa;
    pointer-events: none;
    opacity: 0.8;
}

.btn {
    display: inline-block;
    padding: 6px 10px;
    background: #bc8a31;
    color: #fff;
    border-radius: 4px;
    text-decoration: none;
    margin-top: 8px;
    transition: background 0.3s;
}
.btn:hover {
    background: #a4762a;
}

.cart-page {
    max-width: 600px;
    margin: 80px auto;
    background: #222;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.5);
    color: #fff;
}
.cart-page p {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #444;
    padding: 5px 0;
}
.remove-btn {
    background: #000;
    color: #bc8a31;
    border: none;
    padding: 3px 8px;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s;
}
.remove-btn:hover {
    background: #2b200a;
}

.gates-container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    justify-content: center;
    margin: 20px;
}
.gate-btn {
    padding: 20px;
    background: #bc8a31;
    color: #fff;
    border-radius: 10px;
    text-decoration: none;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
}
.gate-btn:hover {
    background: #a4762a;
}

.stadium-3d {
    position: relative;
    width: 90%;
    max-width: 1000px;
    margin: 40px auto;
    /* ολόκληρη εικόνα χωρίς περικοπή */
    background: url('https://i.imgur.com/lTaK20e.jpeg') no-repeat center center;
    background-size: contain;
    /* ύψος αναλογικό με το πλάτος για να φαίνεται όλο */
    padding-top: 56.25%; /* 16:9 αναλογία */
    border-radius: 15px;
    perspective: 1500px;
}

.stadium-3d {
    position: relative;
    width: 100%;
    max-width: 800px;
    margin: 30px auto;
    aspect-ratio: 16/9;
    background: url('https://i.imgur.com/lTaK20e.jpeg') no-repeat center center;
    background-size: cover;
    border-radius: 15px;
}

.stadium-3d .gate-btn {
    position: absolute;
    transform: translate(-50%, -50%);
    padding: 12px 16px;
    background: #ff0000ff;       /* Μαύρο κουμπί */
    color: #fff;            /* Λευκό κείμενο */
    border-radius: 50%;
    border: 2px solid #ffffffff; /* Απαλό περίγραμμα */
    font-weight: bold;
    text-align: center;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.5);
    white-space: nowrap;     /* Το κείμενο μένει μέσα στο κουμπί */
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s, background 0.2s;
}


.gate-btn {
    position: absolute;
    transform: translate(-50%, -50%);
    width: 50px;
    height: 50px;
    background: #bc8a31;
    color: #fff;
    font-weight: bold;
    text-align: center;
    line-height: 50px;
    border-radius: 50%;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.5);
    transition: transform 0.3s, background 0.3s;
}

.gate-btn:hover {
    transform: translate(-50%, -50%) scale(1.3);
    background: #a4762a;
}


.seat-container {
    width: 90%;
    margin: 40px auto;
}

.row {
    display: flex;
    justify-content: center;
    margin-bottom: 15px;
}

.seat {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    margin: 5px;
    border-radius: 12px;
    color: #fff;
    cursor: pointer;
    border: 2px solid #fff;
    background: #1a7226ff;
}

.seat.booked {
    background: #ff0000ff;
    cursor: not-allowed;
}

.seat.available:hover {
    transform: scale(1.2);
    background: #1eff00ff;
}

.seat input {
    display: none;
}



@keyframes showRow {
    to { opacity: 1; transform: rotateX(20deg) translateZ(0) translateY(0); }
}

.seat {
    display: inline-flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 60px;
    height: 60px;
    margin: 5px;
    border-radius: 12px;
    text-align: center;
    font-weight: bold;
    color: #fff;
    cursor: pointer;
    transition: transform 0.2s, background 0.2s;
    border: 2px solid #fff;
    position: relative;
    background: #1a7226ff; /* διαθέσιμη θέση */
}
.seat.booked {
    background: #ff0000ff;
    cursor: not-allowed;
}
.seat.available:hover {
    transform: scale(1.2);
    background: #1eff00ff;
}
.seat.selected {
    background: #fff;
    color: #000;
    transform: scale(1.2);
    border-color: #31bc4fff;
}
.seat span {
    font-size: 14px;
}
.seat input {
    display: none;
}

.add-cart-btn {
    display: block;
    margin: 20px auto;
    padding: 10px 20px;
    font-size: 18px;
    background: #bc8a31;
    color: #fff;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.3s;
}
.add-cart-btn:hover {
    background: #a4762a;
}

.footer .row {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 40px;
    margin: 10px 0;
}

</style>
</head>
<body>

<!-- MENU -->
<?php include 'nav.php'; ?>


<a class="cart-icon" href="?page=cart">🛒 Καλάθι 
  <span class="cart-count"><?=count($_SESSION['cart'])?></span>
</a>

<?php
// --- Σελίδα αγώνων ---
if($page==="tick"){
    $tick=$conn->query("SELECT * FROM tick ORDER BY match_date");
    echo "<h1>Αγώνες</h1><div class='tick-grid'>";
    while($m=$tick->fetch_assoc()){
        $cls=$m['upcoming']?"match-card upcoming":"match-card";
        echo "<div class='$cls'>";
        echo "<img src='{$m['image_url']}' alt='match'>";
        echo "<div class='info'><p>{$m['stadium']}<br>".date("d/m/Y H:i",strtotime($m['match_date']))."</p>";
        if(!$m['upcoming']) echo "<a class='btn' href='?page=seats&match={$m['id']}'>Δείτε Θέσεις</a>";
        echo "</div></div>";
    }
    echo "</div>";
}

// --- Σελίδα καθισμάτων ---
// --- Σελίδα Καθισμάτων ---
if($page==="seats" && isset($_GET['match'])){
    // Παράδειγμα: φόρτωση θυρών από DB
    $matchId = (int)$_GET['match'];
    $gates = []; // array από DB
    $res = $conn->query("SELECT * FROM seats ORDER BY Gate, seat_row, seat_number");
    while($row = $res->fetch_assoc()){
        $gates[$row['Gate']][] = $row;
    }
    $selectedGate = $_GET['gate'] ?? '';
// Πάρε το match ID από GET
$mid = isset($_GET['match']) ? (int)$_GET['match'] : 0;

// Φόρτωση καθισμάτων από DB για αυτόν τον αγώνα
$gates = [];
$res = $conn->query("SELECT * FROM seats ORDER BY Gate, seat_row, seat_number");
while($s = $res->fetch_assoc()){
    $gates[$s['Gate']][] = $s;
}
    include 'stadium.php';
}



// --- Σελίδα καλαθιού ---
// --- Checkout φόρμα ---
if($page === "cart") {
    $isAjax = isset($_POST['ajax']) && $_POST['ajax']==1;

    ob_start();

    echo "<div class='cart-page-inner'>";

    // --- PROGRESS BAR πάνω από τον τίτλο ---
    echo '<div id="progressContainer" style="display:none; margin-bottom: 15px;">
            <div style="background:#444; border-radius:8px; overflow:hidden; height:20px;">
                <div id="progressBar" style="width:0%; background:#bc8a31; height:100%; text-align:center; color:#fff; line-height:20px;">0%</div>
            </div>
            <div id="progressText" style="margin-top:5px; color:#fff;">Αποστολή παραγγελίας...</div>
          </div>';

    echo "<h1>Καλάθι</h1>";

    if(empty($_SESSION['cart'])){
        echo "<p>Το καλάθι είναι άδειο</p>";
    } else {
        $paymentMethod = $_POST['payment_method'] ?? 'Γήπεδο';
        $irisCode      = $_POST['iris_code'] ?? ($_SESSION['iris_code'] ?? '6971234567');
        $ibankId       = $_POST['ibank_id'] ?? ($_SESSION['ibank_id'] ?? 'GR1234567890123456789012345');

        echo "<form id='checkoutForm' method='POST'>";
        echo '<input type="hidden" name="checkout_done" value="1">';

        foreach($_SESSION['cart'] as $index=>$c){
            echo "<div style='border:1px solid #444; padding:10px; margin-bottom:10px;'>";
            echo "<h3>Εισιτήριο ".($index+1)." - Θύρα {$c['Gate']} Σειρά {$c['seat_row']} Θ{$c['seat_number']}</h3>";
            echo "<p>Τιμή: {$c['price']} € 
                    <button type='submit' name='remove' value='$index' class='remove-btn'>X</button>
                  </p>";
            echo "<p><input type='text' name='first_name[$index]' placeholder='Όνομα' required></p>";
            echo "<p><input type='text' name='last_name[$index]' placeholder='Επώνυμο' required></p>";
            echo "<p><input type='text' name='amka[$index]' placeholder='AMKA' required></p>";
            echo "</div>";
        }

        echo "<p><input type='email' name='email' placeholder='Email για αποστολή' required></p>";

        echo '<label>Τρόπος Πληρωμής</label>
        <select name="payment_method" id="payment" onchange="toggleExtra()">
            <option value="Γήπεδο" '.($paymentMethod==='Γήπεδο'?'selected':'').'>Εκδοτήριο</option>
            <option value="IRIS" '.($paymentMethod==='IRIS'?'selected':'').'>IRIS</option>
            <option value="IBANK" '.($paymentMethod==='IBANK'?'selected':'').'>IBANK</option>
        </select>

        <div id="extraIris" style="display:none; margin-top:5px;">
            <label>Κινητό IRIS</label>
            <input type="text" name="iris_code" value="'.htmlspecialchars($irisCode).'" readonly>
        </div>

        <div id="extraIBank" style="display:none; margin-top:5px;">
            <label>Τραπεζικός Λογαριασμός IBANK</label>
            <input type="text" name="ibank_id" value="'.htmlspecialchars($ibankId).'" readonly>
        </div>';

        echo '<button type="submit" class="btn">Ολοκλήρωση Αγοράς</button>';
        echo "</form>";
    }

    echo "</div>"; // .cart-page-inner

       $cartHTML = ob_get_clean();

    if($isAjax){
        echo $cartHTML; // μόνο το inner HTML
        exit; // <--- Σταματά όλη την υπόλοιπη εκτέλεση της σελίδας
    } else {
        echo "<div class='cart-page'>"; 
        echo $cartHTML;
        echo "</div>";
    }
}

?>



<script>
// Επιλογή θέσης
document.querySelectorAll('.seat input').forEach(cb=>{
    cb.addEventListener('change', e=>{
        cb.parentElement.classList.toggle('selected', cb.checked);
    });
});

// Τρόπος πληρωμής: εμφάνιση IRIS/IBANK
function toggleExtra(){
    const payment = document.getElementById('payment').value;
    document.getElementById('extraIris').style.display = (payment==='IRIS') ? 'block' : 'none';
    document.getElementById('extraIBank').style.display = (payment==='IBANK') ? 'block' : 'none';
}
toggleExtra();

// Burger menu
const menuToggle = document.getElementById('menu-toggle');
const navLinks = document.getElementById('nav-links');

menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
});

// --- Προσθήκη μπάρας προόδου ---
const checkoutForm = document.getElementById('checkoutForm'); // <--- ΣΗΜΑΝΤΙΚΟ
if(checkoutForm){
    checkoutForm.addEventListener('submit', function(e){
        if(!this.querySelector('input[name="checkout_done"]')) return;
        e.preventDefault();

        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');

        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        progressText.textContent = 'Ξεκίνησε η αποστολή...';

        let progress = 0;
        const interval = setInterval(()=>{
            if(progress < 95){
                progress += Math.random()*2;
                progressBar.style.width = Math.min(progress,95) + '%';
                progressBar.textContent = Math.floor(progress)+'%';
            }
        }, 100);

        const formData = new FormData(this);
        formData.append('ajax', 1); // σημαία ajax
        fetch(window.location.href, { method:'POST', body: formData })
        .then(res => res.text())
        .then(html => {
            clearInterval(interval);
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressText.textContent = '✅ Η παραγγελία ολοκληρώθηκε!';

            // Αντικαθιστούμε μόνο το inner
            const inner = document.querySelector('.cart-page-inner');
            if(inner) inner.innerHTML = html;
        });


    });
}
document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault(); // αποτρέπουμε το default submit
        
        const index = btn.value;
        const formData = new FormData();
        formData.append('remove', index);

        // Αποστολή POST
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(() => {
                // Όταν ολοκληρωθεί η αφαίρεση, κάνουμε reload τη σελίδα
                window.location.reload();
            });
    });
});


</script>

<?php include 'footer.html'; ?>
</body>
</html>
