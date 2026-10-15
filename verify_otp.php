<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// --- Επαλήθευση OTP ---
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $otp = trim($_POST['otp']);

    $stmt = $conn->prepare("SELECT otp_code, otp_expires, pending_email FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && $otp === $res['otp_code'] && strtotime($res['otp_expires']) > time()) {
        // Ενημέρωση email
        $stmt = $conn->prepare("UPDATE users 
            SET email = pending_email, otp_code=NULL, otp_expires=NULL, pending_email=NULL 
            WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $message = "✅ Το email σου επιβεβαιώθηκε επιτυχώς!";
    } else {
        $message = "❌ Μη έγκυρος ή ληγμένος κωδικός OTP.";
    }
}

// --- Αποστολή ξανά OTP ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $stmt = $conn->prepare("SELECT pending_email FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $email = $res['pending_email'];

    if ($email) {
        $otp = random_int(100000, 999999);
        $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

        $stmt = $conn->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
        $stmt->bind_param("ssi", $otp, $expires, $userId);
        $stmt->execute();

        require 'send_otp_email.php'; // το αρχείο με τη sendOtpEmail()
        sendOtpEmail($email, $otp);

        $message = "📧 Νέος κωδικός OTP στάλθηκε στο $email!";
        $_SESSION['otp_last_sent'] = time(); // cooldown timer
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Επιβεβαίωση OTP</title>
<style>
body { background:#111; color:#fff; font-family:'Poppins',sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; }
.container { background:#222; padding:40px; border-radius:16px; box-shadow:0 0 20px rgba(255,200,100,0.2); text-align:center; width:350px; }
h1 { color:#ffb833; }
input[type='text'] { width:100%; padding:12px; border-radius:8px; border:none; text-align:center; font-size:18px; margin:10px 0; }
button { padding:10px 18px; border:none; border-radius:10px; cursor:pointer; font-weight:bold; background:#ffb833; color:#000; transition:0.3s; }
button:hover:not(:disabled) { background:#ffc94d; transform:scale(1.05); }
button:disabled { background:#666; color:#999; cursor:not-allowed; }
.msg { margin-top:15px; font-size:15px; }
.resend { margin-top:20px; }
.timer { color:#ffb833; font-weight:bold; margin-top:10px; }
</style>
</head>
<body>
<div class="container">
    <h1>Επιβεβαίωση OTP</h1>
    <?php if ($message): ?>
        <p class="msg"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="text" name="otp" placeholder="Εισήγαγε τον OTP" required maxlength="6">
        <button type="submit" name="verify">✅ Επιβεβαίωση</button>
    </form>

    <div class="resend">
        <form method="post">
            <button type="submit" name="resend" id="resendBtn">🔁 Αποστολή ξανά OTP</button>
        </form>
        <p class="timer" id="timer"></p>
    </div>
</div>

<script>
const resendBtn = document.getElementById('resendBtn');
const timerText = document.getElementById('timer');

let cooldown = 60; // seconds
<?php if (isset($_SESSION['otp_last_sent'])): ?>
    const lastSent = <?= time() - $_SESSION['otp_last_sent'] ?>;
    if (lastSent < cooldown) cooldown = cooldown - lastSent;
<?php endif; ?>

if (cooldown > 0) startCooldown();

function startCooldown() {
    resendBtn.disabled = true;
    timerText.textContent = `Μπορείς να ξαναστείλεις σε ${cooldown} δευτ.`;
    const interval = setInterval(() => {
        cooldown--;
        if (cooldown > 0) {
            timerText.textContent = `Μπορείς να ξαναστείλεις σε ${cooldown} δευτ.`;
        } else {
            clearInterval(interval);
            timerText.textContent = '';
            resendBtn.disabled = false;
        }
    }, 1000);
}
</script>
</body>
</html>
