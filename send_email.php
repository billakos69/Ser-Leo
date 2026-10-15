<?php
session_start();
include 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Μόνο admin
if(!isset($_SESSION['user']) || $_SESSION['user']!=='Admin'){
    die("Access denied");
}

// Λαμβάνουμε JSON POST
$input = json_decode(file_get_contents('php://input'), true);
if(!$input) die("Δεν ελήφθησαν δεδομένα.");

$subject = $input['title'] ?? 'Νέα ενημέρωση από το Shop!';
$emailText = $input['text'] ?? '';
$containerColor = $input['color'] ?? '#222222';
$selectedProducts = $input['products'] ?? [];
$selectedMatches = $input['matches'] ?? []; // προσθήκη για αγώνες

if(empty($emailText) && empty($selectedProducts) && empty($selectedMatches)){
    die("Το μήνυμα είναι άδειο!");
}

// Φορτώνουμε χρήστες
$users = $conn->query("SELECT username,email FROM users");
if($users->num_rows == 0) die("Δεν υπάρχουν παραλήπτες.");

// PHPMailer setup
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'basixas69@gmail.com';
    $mail->Password = 'wpeldzaugmvffmnu';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->setFrom('basixas69@gmail.com','Ser Leo Shop');
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->addEmbeddedImage('images/logo.png', 'logo_cid');
    $mail->Subject = $subject;

    while($row = $users->fetch_assoc()){
        $username = htmlspecialchars($row['username']);
        $email = $row['email'];

        // HTML περιεχόμενο
        $html = "<div style='max-width:650px;margin:20px auto;background:{$containerColor};border-radius:12px;color:#fff;padding:20px;font-family:Arial,sans-serif;'>";
        $html .= '<img src="cid:logo_cid" style="height:60px;">';
        $html .= "<h2>Γεια σου, <span style='color:#ffd700;'>{$username}</span> 👋</h2>";
        $html .= "<p>{$emailText}</p>";

        // Custom images από τον υπολογιστή
if(!empty($input['images'])){
    foreach($input['images'] as $i => $imgBase64){
        // Δημιουργούμε ένα CID για κάθε εικόνα
        $cid = "customimg$i";
        $mail->addStringEmbeddedImage(
            base64_decode(preg_replace('#^data:image/\w+;base64,#i','',$imgBase64)),
            $cid,
            "custom_$i.png"
        );
        $html .= "<div style='text-align:center;margin:10px 0;'>
                    <img src='cid:$cid' style='width:100%;max-width:600px;border-radius:8px;'>
                  </div>";
    }
}


        // Προϊόντα
        if(!empty($selectedProducts)){
            $html .= "<h3 style='color:#ff3131;'>Προτεινόμενα προϊόντα</h3>";
            $html .= "<div style='display:flex;flex-wrap:wrap;gap:10px;'>";
            foreach($selectedProducts as $p){
                $html .= "<div style='flex:0 0 48%;background:#333;padding:10px;border-radius:10px;text-align:center;'>";
                $html .= "<img src='{$p['image']}' style='width:100%;border-radius:8px;margin-bottom:5px;'>";
                if($p['price'] < $p['original_price']){
                    $html .= "<div style='margin-top:5px;font-weight:bold;color:#ff3131;'>
                                <del style='color:#888'>{$p['original_price']}€</del> {$p['price']}€
                              </div>";
                } else {
                    $html .= "<div style='margin-top:5px;font-weight:bold;color:#ff3131;'>{$p['price']}€</div>";
                }
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        // Αγώνες
        if(!empty($selectedMatches)){
            $html .= "<h3 style='color:#ffd700;margin-top:20px;'>Επερχόμενοι Αγώνες</h3>";
            foreach($selectedMatches as $m){
                $html .= "<div style='background:#333;padding:10px;border-radius:10px;margin-bottom:10px;'>";
                if(!empty($m['image'])) $html .= "<img src='{$m['image']}' style='width:100%;border-radius:8px;margin-bottom:5px;'>";
                $html .= "<strong>{$m['home_team']} vs {$m['away_team']}</strong><br>";
                $html .= "Ημερομηνία: {$m['match_date']}<br>";
                if(!empty($m['stadium'])) $html .= "Γήπεδο: {$m['stadium']}<br>";
                $html .= "</div>";
            }
        }

        // Κωδικός έκπτωσης
        $html .= "<div style='text-align:center;margin-top:20px;font-weight:bold;color:#ffd700;'>
                    Μην ξεχνάς! Ο κωδικός <span style='color:#ff3131;'>LEO10</span> δίνει 10% έκπτωση!
                  </div>";
        $html .= "</div>";

        // Στείλε email
        $mail->clearAddresses();
        $mail->addAddress($email);
        $mail->Body = $html;
        $mail->send();
    }

    echo "Τα email στάλθηκαν κανονικά! 📧";

} catch (Exception $e){
    echo "Το email δεν στάλθηκε. Σφάλμα: {$mail->ErrorInfo}";
}
