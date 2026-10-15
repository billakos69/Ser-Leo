<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendUsernameEmail($email, $username) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Ρυθμίσεις
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'basixas69@gmail.com'; // το Gmail σου
        $mail->Password   = 'wpeldzaugmvffmnu';    // κωδικός εφαρμογής
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('basixas69@gmail.com', 'Ser-Leo Support');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Ανάκτηση Ονόματος Χρήστη ';

        if (file_exists('images/logo.png')) {
            $mail->addEmbeddedImage('images/logo.png', 'logo_cid');
        }

        $mail->Body = "
        <div style='
            font-family: Arial, sans-serif;
            background-color: #f6f8fb;
            padding: 30px;
            color: #333;
        '>
            <div style='
                max-width: 600px;
                background: white;
                margin: auto;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            '>
                <div style='
                    background: linear-gradient(135deg, #000000ff, #000000ff);
                    color: white;
                    text-align: center;
                    padding: 25px 10px;
                '>
                    " . (file_exists('images/logo.png') ? "
                            <div style='text-align: center; margin-bottom: 10px;'>
                                <img src='cid:logo_cid' alt='Ser-Leo Logo' 
                                    style='max-width: 120px; height: auto; border-radius: 10px; display: inline-block;'>
                            </div>
                        " : "") . "

                    <h2 style='margin-top: 10px;'>Ανάκτηση Ονόματος Χρήστη</h2>
                </div>

                <div style='padding: 30px;'>
                    <p style='font-size: 16px;'>Γεια σου,</p>
                    <p style='font-size: 15px; line-height: 1.6;'>
                        Λάβαμε αίτημα για ανάκτηση του ονόματος χρήστη σου στο <strong>Ser-Leo</strong>.
                    </p>

                    <div style='text-align: center; margin: 25px 0;'>
                        <div style='
                            background-color: #f0f4f8;
                            display: inline-block;
                            padding: 12px 25px;
                            border-radius: 6px;
                            font-size: 18px;
                            font-weight: bold;
                            color: #7e550aff;
                        '>
                            👤 Το Username σου είναι: <span style='color:#222;'>$username</span>
                        </div>
                    </div>

                    <p style='font-size: 14px; color: #555;'>
                        Αν δεν έκανες εσύ αυτή την αίτηση, μπορείς να αγνοήσεις αυτό το μήνυμα.
                    </p>
                </div>

                <div style='
                    background: #f0f4f8;
                    padding: 15px;
                    text-align: center;
                    font-size: 13px;
                    color: #777;
                    border-top: 1px solid #e5e9f0;
                '>
                    © " . date('Y') . " Ser-Leo. Όλα τα δικαιώματα διατηρούνται.
                </div>
            </div>
        </div>";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Σφάλμα αποστολής email: ' . $mail->ErrorInfo);
        return false;
    }
}
?>
