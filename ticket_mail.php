<?php
session_start();
require 'vendor/autoload.php';
include 'config.php';

// DB connection
$host="localhost"; $user="root"; $pass=""; $db="movies_db";
$conn=new mysqli($host,$user,$pass,$db);
if($conn->connect_error) die("DB Error: ".$conn->connect_error);

// --- Διαβάζουμε το προσωρινό αρχείο checkout ---
$fname = sys_get_temp_dir()."/checkout_".session_id().".json";
if(!file_exists($fname)) exit; // τίποτα να μην κάνει αν δεν υπάρχει

$data = json_decode(file_get_contents($fname), true);
$cart = $data['cart'] ?? [];
$checkout = $data['checkout'] ?? [];
unlink($fname); // διαγράφουμε το προσωρινό αρχείο

if(empty($cart) || empty($checkout)) exit;

// --- HELPER ---
function downloadImage($url){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data ?: false;
}

// --- Email setup ---
$emails = $checkout['email'];
$first_names = $checkout['first_name'];
$last_names = $checkout['last_name'];
$amkas = $checkout['amka'];
$paymentMethod = $checkout['payment_method'] ?? 'Γήπεδο';
$irisCode      = $checkout['iris_code'] ?? '';
$ibankId       = $checkout['ibank_id'] ?? '';

$pdfFiles = [];
$first_name_main = htmlspecialchars($first_names[0]);
$last_name_main  = htmlspecialchars($last_names[0]);

// --- Δημιουργία body email ---
$mailBody = '
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; background:#f4f4f4; padding:20px; color:#333; }
.container { background:#fff; border-radius:10px; overflow:hidden; max-width:600px; margin:auto; box-shadow:0 3px 10px rgba(0,0,0,0.2); }
.header { background:#d32f2f; padding:20px; text-align:center; color:#fff; }
.header img { max-width:120px; }
.content { padding:20px; }
.ticket { border:1px solid #ddd; border-radius:8px; margin-bottom:15px; padding:15px; background:#fafafa; }
.ticket h3 { margin:0 0 10px; color:#d32f2f; }
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
    <p>Σας ευχαριστούμε για την αγορά εισιτηρίων από την <strong>MAGKALIAKOS</strong>. Παρακάτω θα βρείτε τα στοιχεία των εισιτηρίων σας:</p>
';

foreach($cart as $index=>$ticket){
    $mailBody .= "
    <div class='ticket'>
      <h3>Εισιτήριο ".($index+1)."</h3>
      <p><strong>Θύρα:</strong> {$ticket['Gate']} | <strong>Σειρά:</strong> {$ticket['seat_row']} | <strong>Θέση:</strong> {$ticket['seat_number']}</p>
      <p><strong>Τιμή:</strong> {$ticket['price']} €</p>
    </div>";
}

$mailBody .= '<p>Τα αντίστοιχα PDF αρχεία με τα εισιτήρια σας επισυνάπτονται στο παρόν email.</p>
</div>
<div class="footer">
<p>© 2025 Magkaliakos FC - Όλα τα δικαιώματα διατηρούνται</p>
</div>
</div>
</body>
</html>';

// --- Δημιουργία PDF εισιτηρίων ---
foreach($cart as $index=>$ticket){
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

    $pdf = new TCPDF('P','mm','A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('3D Stadium Tickets');
    $pdf->SetTitle("Εισιτήριο {$ticket['Gate']}-{$ticket['seat_row']}-{$ticket['seat_number']}");
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->AddPage();

    if($imageFileCenter && file_exists($imageFileCenter)){
        $pdf->Image($imageFileCenter, 10, 10, $pdf->getPageWidth()-20, 0, '', '', '', false, 300);
        $pdf->SetY($pdf->getImageRBY() + 10);
    }

    $paymentText = '';
    if($paymentMethod === 'Γήπεδο') $paymentText='Εκδοτήριο';
    elseif($paymentMethod==='IRIS') $paymentText="IRIS - $irisCode";
    elseif($paymentMethod==='IBANK') $paymentText="IBANK - $ibankId";

    $html = "
    <h2 style='color:#d32f2f; text-align:center;'>Εισιτήριο Αγώνα</h2>
    <p><strong>ΟΝΟΜΑ:</strong> $first_name</p>
    <p><strong>ΕΠΩΝΥΜΟ:</strong> $last_name</p>
    <p><strong>AMKA:</strong> $amka</p>
    <p><strong>Θύρα:</strong> {$ticket['Gate']} | Σειρά: {$ticket['seat_row']} | Θέση: {$ticket['seat_number']}</p>
    <p><strong>Τιμή:</strong> {$ticket['price']} €</p>
    <p><strong>Τρόπος Πληρωμής:</strong> $paymentText</p>
    ";

    $pdf->writeHTML($html,true,false,true,false,'');
    $pdfFile = sys_get_temp_dir()."/ticket_{$ticket['Gate']}_{$ticket['seat_row']}_{$ticket['seat_number']}.pdf";
    $pdf->Output($pdfFile,'F');
    $pdfFiles[] = $pdfFile;
}

// --- Στείλε email ---
try{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'basixas69@gmail.com';
    $mail->Password = 'wpeldzaugmvffmnu';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('basixas69@gmail.com','tickets@magkaliakos.gr');
    $mail->addAddress($emails); // ο χρήστης
    $mail->addAddress('basixas69@gmail.com'); // αντίγραφο

    $mail->addEmbeddedImage(__DIR__.'/images/logo.png','logoimg');
    $mail->isHTML(true);
    $mail->CharSet='UTF-8';
    $mail->Subject="Αποδείξεις Αγοράς Εισιτηρίων";
    $mail->Body = $mailBody;

    foreach($pdfFiles as $f) $mail->addAttachment($f);

    $mail->send();

} catch(\PHPMailer\PHPMailer\Exception $e){
    error_log("Email error: ".$mail->ErrorInfo);
}

// Διαγραφή temp PDFs
foreach($pdfFiles as $f) if(file_exists($f)) unlink($f);

?>
