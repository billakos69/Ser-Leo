<?php
// account.php - πλήρες αρχείο
include 'config.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
date_default_timezone_set("Europe/Athens");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$userId = (int) $_SESSION['user_id'];

/* --------- HELPERS --------- */
function sendOtpEmail($to, $otp) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'basixas69@gmail.com'; // <- προσαρμόστε
        $mail->Password   = 'wpeldzaugmvffmnu';    // <- προσαρμόστε (app password)
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('basixas69@gmail.com', 'Ser-Leo Support');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Κωδικός Επιβεβαίωσης (OTP) - Ser-Leo';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;padding:20px;'>
                <h2 style='color:#ffb833;'>Κωδικός Επιβεβαίωσης</h2>
                <p>Ο κωδικός OTP για επιβεβαίωση αλλαγής είναι:</p>
                <h1 style='letter-spacing:6px;background:#111;padding:12px 18px;border-radius:8px;color:#ffb833;display:inline-block;'>$otp</h1>
                <p style='color:#555;'>Ισχύει για 10 λεπτά. Αν δεν το ζήτησες εσύ, αγνόησέ το.</p>
            </div>";
        $mail->AltBody = "Ο κωδικός OTP είναι: $otp (ισχύει 10 λεπτά)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP MAIL ERROR: " . ($mail->ErrorInfo ?? $e->getMessage()));
        return false;
    }
}

/* --------- Φόρτωση χρήστη (συμπεριλαμβανομένου user_pfp) --------- */
$stmt = $conn->prepare("SELECT id, username, email, phone, highscore, user_pfp, otp_code, otp_expires, pending_email, pending_phone FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$highscore = $user['highscore'] ?? 0;
$user_pfp = $user['user_pfp'] ?? 'images/default_pfp.png'; // default

$message = '';
$message_type = ''; // success | error | info
$showOtpForm = false;

/* --------- BACKEND ACTIONS --------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Αποθήκευση avatar από modal
    if (isset($_POST['save_avatar'])) {
        $selected = $_POST['selected_avatar'] ?? null;
        if ($selected) {
            $u = $conn->prepare("UPDATE users SET user_pfp=? WHERE id=?");
            $u->bind_param("si", $selected, $userId);
            if ($u->execute()) {
                $_SESSION['message'] = "Η εικόνα προφίλ ενημερώθηκε!";
                header("Location: account.php");
                exit;
            } else {
                $message = "Σφάλμα αποθήκευσης εικόνας.";
                $message_type = "error";
            }
        }
    }

    // --- Ανανέωση στοιχείων (update) με OTP όταν χρειάζεται
if (isset($_POST['update'])) {
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');
    $newPassword = trim($_POST['password'] ?? '');

    if ($newUsername === '' || $newEmail === '' || $newPhone === '') {
        $message = "Όλα τα πεδία (εκτός κωδικού) είναι απαραίτητα.";
        $message_type = "error";
    } else {
        $needOtp = false;
        $pendingEmail = null;
        $pendingPhone = null;
        $pendingPassword = null;

        // Έλεγχος αλλαγών που απαιτούν OTP
        if ($newEmail !== $email) { $needOtp = true; $pendingEmail = $newEmail; }
        if ($newPhone !== $phone) { $needOtp = true; $pendingPhone = $newPhone; }
        if ($newPassword !== '') { $needOtp = true; $pendingPassword = password_hash($newPassword, PASSWORD_DEFAULT); }

        $query = "UPDATE users SET username=?";
        $params = [$newUsername];
        $types = "s";

        if (!$needOtp) {
            // Δεν χρειάζεται OTP, ενημέρωση απευθείας
            $query .= ", email=?, phone=?";
            $params[] = $newEmail;
            $params[] = $newPhone;
            $types .= "ss";

            if ($newPassword !== '') {
                $query .= ", password=?";
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
                $types .= "s";
            }
        } else {
            // Δημιουργία OTP
            $otp = random_int(100000, 999999);
            $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));
            $query .= ", otp_code=?, otp_expires=?, pending_email=?, pending_phone=?, pending_password=?";
            $params[] = (string)$otp;
            $params[] = $expires;
            $params[] = $pendingEmail;
            $params[] = $pendingPhone;
            $params[] = $pendingPassword;
            $types .= "sssss";
        }

        $query .= " WHERE id=?";
        $params[] = $userId;
        $types .= "i";

        $stmtUpd = $conn->prepare($query);
        $stmtUpd->bind_param($types, ...$params);
        if ($stmtUpd->execute()) {
            $_SESSION['user'] = $newUsername;
            $username = $newUsername;
            if (!$needOtp) {
                $email = $newEmail;
                $phone = $newPhone;
                $message = "Τα στοιχεία ενημερώθηκαν επιτυχώς.";
                $message_type = "success";
            } else {
                $toEmail = $pendingEmail ?? $email;
                if (sendOtpEmail($toEmail, $otp)) {
                    $message = "Έστειλα κωδικό OTP στο <b>" . htmlspecialchars($toEmail) . "</b>. Εισάγετε τον κωδικό για να επιβεβαιώσετε την αλλαγή.";
                    $message_type = "info";
                    $showOtpForm = true;
                    $_SESSION['otp_last_sent'] = time();
                } else {
                    $message = "Δεν ήταν δυνατή η αποστολή του OTP. Δοκίμασε ξανά.";
                    $message_type = "error";
                }
            }
        } else {
            $message = "Σφάλμα κατά την ενημέρωση.";
            $message_type = "error";
        }
    }
}


    // --- Resend OTP (backend guard 30s) ---
    if (isset($_POST['resend_otp'])) {
        $last = $_SESSION['otp_last_sent'] ?? 0;
        if (time() - $last < 30) {
            $message = "Περίμενε λίγο πριν ζητήσεις νέο OTP.";
            $message_type = "error";
            $showOtpForm = true;
        } else {
            // πάρτε pending_email αν υπάρχει, αλλιώς το email
            $q = $conn->prepare("SELECT pending_email, email FROM users WHERE id=?");
            $q->bind_param("i", $userId);
            $q->execute();
            $r = $q->get_result()->fetch_assoc();
            $emailToSend = $r['pending_email'] ?: $r['email'];

            $otp = random_int(100000, 999999);
            $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));
            $u = $conn->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
            $u->bind_param("ssi", $otp, $expires, $userId);
            $u->execute();

            if (sendOtpEmail($emailToSend, $otp)) {
                $message = "Νέος κωδικός OTP στάλθηκε στο <b>" . htmlspecialchars($emailToSend) . "</b>.";
                $message_type = "info";
                $showOtpForm = true;
                $_SESSION['otp_last_sent'] = time();
            } else {
                $message = "Αποτυχία αποστολής OTP.";
                $message_type = "error";
                $showOtpForm = true;
            }
        }
    }

    // --- Verify OTP ---
    if (isset($_POST['verify_otp'])) {
    $inputOtp = trim($_POST['otp'] ?? '');
    $q = $conn->prepare("SELECT otp_code, otp_expires, pending_email, pending_phone, pending_password FROM users WHERE id=?");
    $q->bind_param("i", $userId);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();

    if (!$row || empty($row['otp_code'])) {
        $message = "Δεν βρέθηκε ενεργός κωδικός OTP.";
        $message_type = "error";
    } else {
        if (date("Y-m-d H:i:s") > $row['otp_expires']) {
            $message = "Ο κωδικός OTP έχει λήξει.";
            $message_type = "error";
        } elseif ($inputOtp !== $row['otp_code']) {
            $message = "Λάθος κωδικός OTP.";
            $message_type = "error";
            $showOtpForm = true;
        } else {
            $u = $conn->prepare("
                UPDATE users SET 
                    email = COALESCE(?, email),
                    phone = COALESCE(?, phone),
                    password = COALESCE(?, password),
                    otp_code=NULL, otp_expires=NULL, pending_email=NULL, pending_phone=NULL, pending_password=NULL 
                WHERE id=?
            ");
            $u->bind_param("sssi", $row['pending_email'], $row['pending_phone'], $row['pending_password'], $userId);
            if ($u->execute()) {
                $message = "Οι αλλαγές επιβεβαιώθηκαν και αποθηκεύτηκαν.";
                $message_type = "success";
                if ($row['pending_email']) $email = $row['pending_email'];
                if ($row['pending_phone']) $phone = $row['pending_phone'];
            } else {
                $message = "Σφάλμα κατά την αποθήκευση.";
                $message_type = "error";
            }
        }
    }
}


    // --- Διαγραφή λογαριασμού ---
    if (isset($_POST['delete_confirm'])) {
        $d = $conn->prepare("DELETE FROM users WHERE id=?");
        $d->bind_param("i", $userId);
        $d->execute();
        session_destroy();
        header("Location: signup.php?msg=account_deleted");
        exit;
    }
}

/* --------- Φόρτωση favorites για την καρτέλα Αγαπημένα --------- */
$stmtFav = $conn->prepare("SELECT p.id, p.name, p.image, p.price FROM favorite f JOIN products p ON f.products_id = p.id WHERE f.user_id = ?");
$stmtFav->bind_param("i", $userId);
$stmtFav->execute();
$resFav = $stmtFav->get_result();
$fav_items = $resFav->fetch_all(MYSQLI_ASSOC);

/* --------- Φόρτωση διαθέσιμων pfp images --------- */
$resPfps = $conn->query("SELECT id, image_path, name FROM pfp_images ORDER BY id ASC");
$pfp_images = $resPfps ? $resPfps->fetch_all(MYSQLI_ASSOC) : [];

?>
<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ο Λογαριασμός μου • Ser-Leo</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="icon" href="images/logo.png">
<style>
/* Custom */
body{background:radial-gradient(1000px 600px at 10% 10%,#0b0b0b,#121212);}
.card{backdrop-filter:blur(6px);background:rgba(10,10,10,0.6);border:1px solid rgba(255,184,51,0.08);}
.tab{cursor:pointer;}
.avatar-option{border:2px solid transparent;border-radius:10px;cursor:pointer;transition:0.2s;overflow:hidden;}
.avatar-option img{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:8px;}
.avatar-option:hover{transform:scale(1.03);border-color:#bc8a31;}
.selected-avatar{border-color:#bc8a31;box-shadow:0 0 10px rgba(188,138,49,0.6);}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:50;align-items:center;justify-content:center;padding:20px;}
.modal.active{display:flex;}
</style>
</head>
<body class="min-h-screen flex items-center justify-center text-gray-100">
<div class="w-full max-w-5xl mx-4">
  <div class="card rounded-2xl p-6 shadow-2xl">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center space-x-4">
        <!-- Avatar preview (click opens modal) -->
        <div id="avatarTrigger" class="relative cursor-pointer group">
          <img id="avatarPreview" src="<?= htmlspecialchars($user_pfp) ?>" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-2 border-yellow-400 shadow-md">
          <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center text-yellow-300 opacity-0 group-hover:opacity-100 transition-opacity rounded-full">
            <span class="text-sm">Αλλαγή</span>
          </div>
        </div>

        <div>
          <h1 class="text-2xl font-semibold text-yellow-300"><?= htmlspecialchars($username) ?></h1>
          <p class="text-sm text-gray-300">Διαχείριση προφίλ & ασφάλειας</p>
        </div>
      </div>

      <div class="text-right">
       
        <a href="index.php" class="mt-2 inline-block text-xs text-gray-300 hover:text-yellow-300">← Επιστροφή</a>
      </div>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
      <div class="mb-4 p-3 rounded-lg bg-green-900 text-green-300">
        <?= $_SESSION['message']; unset($_SESSION['message']); ?>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="mb-4 p-3 rounded-lg <?= $message_type==='success'?'bg-green-900 text-green-300':($message_type==='error'?'bg-red-900 text-red-300':'bg-yellow-900 text-yellow-300') ?>">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Sidebar tabs -->
      <div class="space-y-3">
        <button data-tab="info" class="tab w-full px-4 py-3 rounded-lg bg-yellow-800 text-yellow-200">Στοιχεία</button>
        <button data-tab="security" class="tab w-full px-4 py-3 rounded-lg text-gray-200 hover:bg-gray-800">Ασφάλεια</button>
        <button data-tab="favorites" class="tab w-full px-4 py-3 rounded-lg text-gray-200 hover:bg-gray-800">Αγαπημένα</button>

        <form method="post" onsubmit="return confirm('Είσαι σίγουρος;');">
          <button type="submit" name="delete_confirm" class="mt-6 w-full px-4 py-3 rounded-lg bg-gradient-to-r from-yellow-600 to-yellow-400 text-black font-semibold">Διαγραφή Λογαριασμού</button>
        </form>
      </div>

      <!-- Main content -->
      <div class="md:col-span-2">
        <!-- INFO tab -->
        <div id="info" class="tab-content">
          <form method="post" class="space-y-4">
            <div>
              <label class="text-sm text-gray-300">Username</label>
              <input name="username" class="w-full mt-1 p-3 rounded-lg bg-gray-900 border border-gray-800" value="<?= htmlspecialchars($username) ?>" required>
            </div>

            <div>
              <label class="text-sm text-gray-300">Email</label>
              <input name="email" type="email" class="w-full mt-1 p-3 rounded-lg bg-gray-900 border border-gray-800" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div>
              <label class="text-sm text-gray-300">Κινητό Τηλέφωνο</label>
              <input name="phone" class="w-full mt-1 p-3 rounded-lg bg-gray-900 border border-gray-800" value="<?= htmlspecialchars($phone) ?>" required>
            </div>

            <div>
              <label class="text-sm text-gray-300">Νέος Κωδικός (προαιρετικά)</label>
              <input name="password" type="password" class="w-full mt-1 p-3 rounded-lg bg-gray-900 border border-gray-800" placeholder="••••••••">
            </div>

            <div class="flex items-center space-x-3">
              <button type="submit" name="update" class="px-5 py-3 rounded-lg bg-gradient-to-r from-yellow-600 to-yellow-400 text-black font-semibold">Αποθήκευση Αλλαγών</button>
              <button type="button" id="openAvatarModal" class="px-4 py-3 rounded-lg border border-gray-700 text-gray-300">Επιλογή Avatar</button>
            </div>

            <p class="text-sm text-gray-400">Αν αλλάξεις email/κινητό θα λάβεις κωδικό OTP για επιβεβαίωση (10 λεπτά).</p>
          </form>
        </div>

        <!-- SECURITY tab -->
        <div id="security" class="tab-content hidden">
          <h3 class="text-lg text-yellow-300 mb-3">Ασφάλεια</h3>
          <p class="text-sm text-gray-300 mb-4">Έλεγχος email / τηλέφωνο και ενεργοποίηση OTP.</p>
          <div class="bg-gray-900 p-4 rounded-lg border border-gray-800">
            <p><b>Email:</b> <?= htmlspecialchars($email) ?></p>
            <p><b>Τηλέφωνο:</b> <?= htmlspecialchars($phone) ?></p>
          </div>
        </div>

        <!-- FAVORITES tab -->
        <div id="favorites" class="tab-content hidden">
          <h3 class="text-lg text-yellow-300 mb-3">Αγαπημένα</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <?php if (empty($fav_items)): ?>
              <div class="col-span-full text-gray-400">Δεν έχετε αγαπημένα προϊόντα.</div>
            <?php else: ?>
              <?php foreach ($fav_items as $it): ?>
                <div class="p-3 rounded-lg bg-gray-900 border border-gray-800">
                  <img src="<?= htmlspecialchars($it['image']) ?>" class="w-full h-36 object-cover rounded-md mb-2">
                  <h4><?= htmlspecialchars($it['name']) ?></h4>
                  <div class="text-yellow-300 font-semibold"><?= number_format($it['price'],2) ?>€</div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- OTP FORM (εμφανίζεται αν χρειαστεί) -->
        <?php if ($showOtpForm): ?>
          <div class="mt-6 p-4 rounded-lg bg-gray-900 border border-gray-800">
            <h4 class="text-yellow-300">Εισάγετε κωδικό OTP</h4>
            <form method="post" class="mt-3 space-y-3">
              <input name="otp" required class="w-40 p-3 rounded-lg bg-black border border-gray-700 text-center text-lg" placeholder="123456">
              <div class="flex items-center space-x-3">
                <button type="submit" name="verify_otp" class="px-4 py-2 rounded-lg bg-gradient-to-r from-yellow-600 to-yellow-400 text-black font-semibold">Επιβεβαίωση OTP</button>
                <button type="submit" name="resend_otp" id="resendBtn" class="px-4 py-2 rounded-lg bg-gray-700 text-gray-200 hover:bg-gray-600">Αποστολή ξανά OTP</button>
              </div>
              <p id="cooldownMsg" class="text-sm text-gray-400 mt-2"></p>
            </form>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<!-- Avatar Modal (within same page, opened from avatar preview or button) -->
<div id="avatarModal" class="modal" aria-hidden="true">
  <div class="bg-gray-900 p-6 rounded-2xl border border-yellow-700 max-w-4xl w-full">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl text-yellow-300 font-semibold">Επίλεξε Εικόνα Προφίλ</h3>
      <button id="closeModal" class="px-3 py-2 bg-gray-700 rounded hover:bg-gray-600">Κλείσιμο</button>
    </div>

    <form method="post" id="avatarForm">
      <div class="grid grid-cols-4 gap-4">
        <?php foreach ($pfp_images as $img): 
            $isSel = ($user_pfp === $img['image_path']);
        ?>
          <label class="avatar-option <?= $isSel ? 'selected-avatar' : '' ?>">
            <input type="radio" name="selected_avatar" value="<?= htmlspecialchars($img['image_path']) ?>" hidden <?= $isSel ? 'checked' : '' ?>>
            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="<?= htmlspecialchars($img['name']) ?>">
          </label>
        <?php endforeach; ?>
      </div>

      <div class="flex justify-between items-center mt-5">
        <div class="text-sm text-gray-300">Επίλεξε και πάτησε <b>Αποθήκευση</b>.</div>
        <div class="space-x-3">
          <button type="button" id="modalCancel" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600">Ακύρωση</button>
          <button type="submit" name="save_avatar" class="px-4 py-2 bg-yellow-500 text-black rounded hover:bg-yellow-400 font-semibold">Αποθήκευση</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// Tabs
document.querySelectorAll('.tab').forEach(btn=>{
  btn.addEventListener('click', ()=> {
    const tab = btn.getAttribute('data-tab');
    document.querySelectorAll('.tab-content').forEach(tc=>tc.classList.add('hidden'));
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('bg-yellow-800','text-yellow-200'));
    const el = document.getElementById(tab);
    if(el) el.classList.remove('hidden');
    btn.classList.add('bg-yellow-800','text-yellow-200');
    // store active tab
    localStorage.setItem('account_active_tab', tab);
  });
});
// set default
const activeTab = localStorage.getItem('account_active_tab') || 'info';
document.querySelector(`[data-tab="${activeTab}"]`)?.click();

// Avatar modal open/close + preview selection
const avatarModal = document.getElementById('avatarModal');
const avatarTrigger = document.getElementById('avatarTrigger');
const openBtn = document.getElementById('openAvatarModal');
const closeBtn = document.getElementById('closeModal');
const modalCancel = document.getElementById('modalCancel');
const avatarPreview = document.getElementById('avatarPreview');

function openModal(){ avatarModal.classList.add('active'); avatarModal.setAttribute('aria-hidden','false'); }
function closeModal(){ avatarModal.classList.remove('active'); avatarModal.setAttribute('aria-hidden','true'); }

// open modal from preview or button
avatarTrigger?.addEventListener('click', openModal);
openBtn?.addEventListener('click', openModal);
closeBtn?.addEventListener('click', closeModal);
modalCancel?.addEventListener('click', closeModal);

// selecting avatar in modal updates preview live
document.querySelectorAll('.avatar-option').forEach(opt=>{
  opt.addEventListener('click', ()=>{
    document.querySelectorAll('.avatar-option').forEach(o=>o.classList.remove('selected-avatar'));
    opt.classList.add('selected-avatar');
    const img = opt.querySelector('img').src;
    avatarPreview.src = img;
    // mark radio checked
    const radio = opt.querySelector('input[type=radio]');
    if(radio) radio.checked = true;
  });
});

// Cooldown for resend OTP (client-side). Backend also guards (30s).
let cooldown = 30;
const resendBtn = document.getElementById('resendBtn');
const cooldownMsg = document.getElementById('cooldownMsg');
if (resendBtn) {
  resendBtn.addEventListener('click', () => {
    resendBtn.disabled = true;
    let t = cooldown;
    cooldownMsg.textContent = `Περίμενε ${t} δευτ. για νέα αποστολή.`;
    const interval = setInterval(() => {
      t--;
      if (t > 0) {
        cooldownMsg.textContent = `Περίμενε ${t} δευτ. για νέα αποστολή.`;
      } else {
        clearInterval(interval);
        cooldownMsg.textContent = '';
        resendBtn.disabled = false;
      }
    }, 1000);
  });
}
</script>
</body>
</html>
