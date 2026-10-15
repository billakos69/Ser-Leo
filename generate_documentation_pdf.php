<?php
// Generate a multi-page PDF documentation with TCPDF and embedded Unicode font (DejaVu Sans)
// Usage: php generate_documentation_pdf.php

require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Serleo Doc Generator');
$pdf->SetAuthor('___(Συγγραφέας)___');
$pdf->SetTitle('Serleo - Τεκμηρίωση Έργου');
$pdf->SetSubject('Τεκμηρίωση έργου Serleo');

// set margins
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(TRUE, 20);

// Use DejaVu Sans (built-in to TCPDF) for Unicode/Greek support
$pdf->SetFont('dejavusans', '', 12);

// ---- Page 1: Cover & TOC ----
$pdf->AddPage();
$html = '<h1 style="text-align:center;">Serleo — Τεκμηρίωση Έργου</h1>';
$html .= '<p style="text-align:center;">Συγγραφέας: ____________________<br>Ημερομηνία: ____________________</p>';
$html .= '<h2>Πίνακας Περιεχομένων</h2>';
$html .= '<ol>';
$html .= '<li>Εισαγωγή</li>';
$html .= '<li>��υνοπτική Περιγραφή Συστήματος</li>';
$html .= '<li>Τεχνολογίες & Αρχιτεκτονική</li>';
$html .= '<li>Ανάλυση Σελίδων</li>';
$html .= '<li>Ροή Πλοήγησης</li>';
$html .= '<li>Βάση Δεδομένων</li>';
$html .= '<li>Βασικές Λειτουργίες</li>';
$html .= '<li>Συμπεράσματα</li>';
$html .= '</ol>';
$pdf->writeHTML($html, true, false, true, false, '');

// ---- Page 2: Introduction & System Overview ----
$pdf->AddPage();
$html = '<h2>1. Εισαγωγή</h2>';
$html .= '<h3>Σκοπός της ιστοσελίδας</h3>';
$html .= '<p>Η εφαρμογή λειτουργεί ως μικτή πλατφόρμα διαχείρισης ποδοσφαιρικών/αθλητικών αγώνων, ενημερωτικού περιεχομένου, ηλεκτρονικού καταστήματος και πώλησης εισιτηρίων. Οι χρήστες μπορούν να εγγραφούν, να αγοράσουν προϊόντα και εισιτήρια, ενώ οι διαχειριστές έχουν εργαλεία CRUD για διαχείριση περιεχομένου.</p>';
$html .= '<h3>Γενική περιγραφή</h3>';
$html .= '<p>Το σύστημα είναι μονολιθικό PHP με MySQL. Περιλαμβάνει modules για authentication, admin panel, shop, tickets, και στατιστικά αγώνων. Το config.php παρέχει συνδέσεις προς τη βάση (mysqli και PDO).</p>';
$html .= '<h2>2. System Overview</h2>';
$html .= '<ul>'; 
$html .= '<li>Backend: PHP (mysqli, PDO)</li>';
$html .= '<li>Database: MySQL</li>';
$html .= '<li>Frontend: HTML, CSS, JavaScript</li>';
$html .= '<li>Libraries: PHPMailer, TCPDF</li>';
$html .= '</ul>';
$pdf->writeHTML($html, true, false, true, false, '');

// ---- Page 3..N: Page Analysis (grouped) ----
$pdf->AddPage();
$html = '<h2>3. Ανάλυση Σελίδων (Σύντομες περιγραφές ανά αρχείο)</h2>';

$pages = [
    ['file'=>'login.php','desc'=>'Σελίδα εισόδου χρηστών. Έλεγχος credentials, password_verify, session handling.'],
    ['file'=>'signup.php','desc'=>'Εγγραφή χρήστη. Έλεγχος ύπαρξης email, password_hash, INSERT στον πίνακα users.'],
    ['file'=>'account.php','desc'=>'Διαχείριση λογαριασμού: αλλαγές username/email με OTP, επιλογή avatar, προβολή favorites, διαγραφή λογαριασμού.'],
    ['file'=>'admin_panel.php','desc'=>'Κεντρικό πάνελ διαχείρισης: CRUD για προϊόντα, ειδήσεις, παίκτες, ομάδες, αγώνες, θέσεις, κουπόνια.'],
    ['file'=>'edit_product.php','desc'=>'Διαχείριση προϊόντων: name, options, price, stock, image.'],
    ['file'=>'shop.php','desc'=>'Λίστα προϊόντων με pagination και εφαρμογή promotions.'],
    ['file'=>'product.php','desc'=>'Σελίδα λεπτομερειών προϊόντος με επιλογές και υπολογισμό τρέχουσας τιμής.'],
    ['file'=>'cart.php','desc'=>'Καλάθι βασισμένο σε session. Προβολή/αλλαγή/αφαίρεση αντικειμένων.'],
    ['file'=>'checkout.php','desc'=>'Ολοκλήρωση παραγγελίας: εφαρμογή κουπονιών, δημιουργία PDF εισιτηρίου, αποστολή email.'],
    ['file'=>'tickets.php','desc'=>'Διαχείριση εισιτηρίων/θέσεων: προβολή αγώνων και θέσεων, κράτηση και ενημέρωση availability.'],
    ['file'=>'edit_match_stats.php','desc'=>'Διαχείριση στατιστικών αγώνα, lineup και γεγονότων (match_events).'],
    ['file'=>'news.php','desc'=>'Λίστα ειδήσεων με pagination.'],
    ['file'=>'news_detail.php','desc'=>'Λεπτομέρειες είδησης, δυνατότητα like/dislike με AJAX.'],
    ['file'=>'vote_ajax.php','desc'=>'Endpoint για καταγραφή ψήφων σε ειδήσεις.'],
    ['file'=>'favorite_toggle.php','desc'=>'Endpoint για προσθήκη/αφαίρεση προϊόντος στα αγαπημένα (AJAX).']
];

foreach($pages as $p){
    $html .= '<h4>' . htmlspecialchars($p['file']) . '</h4>';
    $html .= '<p>' . htmlspecialchars($p['desc']) . '</p>';
}

$html .= '<h3>Σημαντικά σημεία κώδικα / ασφάλεια</h3>';
$html .= '<ul><li>Πολλά prepared statements, αλλά και περιπτώσεις όπου μεταβλητές εισάγονται απευθείας σε SQL. Συνιστάται τυποποίηση σε prepared statements.</li>';
$html .= '<li>Προσθήκη CSRF tokens σε φόρμες και έλεγχος ρόλων για admin περιοχές.</li>';
$html .= '<li>Έλεγχος uploads (mime, μέγεθος) και escaping output για XSS.</li></ul>';

$pdf->writeHTML($html, true, false, true, false, '');

// ---- Page 4: Navigation Flow & DB ----
$pdf->AddPage();
$html = '<h2>4. Ροή Πλοήγησης</h2>';
$html .= '<p>Γενική ροή: index.php -> news.php / shop.php / league.php / team.php / tickets.php. Shop flow: shop.php -> product.php -> cart.php -> checkout.php -> ticket_mail.php.</p>';

$html .= '<h2>5. Βάση Δεδομένων</h2>';
$html .= '<p>Κύριοι πίνακες και σκοπός τους (σύντομη λίστα):</p>';
$html .= '<ul>';
$html .= '<li><b>users</b>: authentication, profile, otp/reset</li>';
$html .= '<li><b>products</b>, <b>product_options</b>, <b>product_promotions</b></li>';
$html .= '<li><b>discounts</b>, <b>discount_usage</b></li>';
$html .= '<li><b>favorite</b> (user favorites)</li>';
$html .= '<li><b>news</b>, <b>vote_usage</b></li>';
$html .= '<li><b>teams</b>, <b>players</b>, <b>player_strengths</b></li>';
$html .= '<li><b>matches</b>, <b>match_stats</b>, <b>match_lineup</b>, <b>match_events</b></li>';
$html .= '<li><b>seats</b>, <b>tick</b></li>';
$html .= '</ul>';

$pdf->writeHTML($html, true, false, true, false, '');

// ---- Page 5: Key Functionalities & Conclusion ----
$pdf->AddPage();
$html = '<h2>6. Βασικές Λειτουργίες</h2>';
$html .= '<h3>Authentication</h3>';
$html .= '<p>Χρήση password_hash/password_verify, sessions, και OAuth (Google/GitHub) callbacks.</p>';
$html .= '<h3>Cart & Checkout</h3>';
$html .= '<p>Session-based cart, έλεγχος κουπονιών μέσω check_discount.php, δημιουργία PDF εισιτηρίων με TCPDF και αποστολή μέσω PHPMailer.</p>';
$html .= '<h3>Admin CRUD</h3>';
$html .= '<p>Πλήρες σύστημα CRUD μέσω admin_panel.php και edit_* σελίδων.</p>';

$html .= '<h2>7. Συμπεράσματα & Προτάσεις Βελτίωσης</h2>';
$html .= '<ul>';
$html .= '<li>Πλήρης υιοθέτηση prepared statements όπου λείπουν.</li>';
$html .= '<li>Προσθήκη CSRF tokens και roles-based authorization.</li>';
$html .= '<li>Ενσωμάτωση validation και sanitization για όλα τα inputs και καλύτερος έλεγχος file uploads.</li>';
$html .= '<li>Προσθήκη SQL schema (DDL) για ευκολότερη εγκατάσταση.</li>';
$html .= '</ul>';

$html .= '<p>Το παρόν έγγραφο δημιουργήθηκε αυτόματα. Μπορείτε να προσαρμόσετε το κείμενο ή να προσθέσετε screenshots πριν την παράδοση.</p>';

$pdf->writeHTML($html, true, false, true, false, '');

// Output file to project root
$outFile = __DIR__ . '/Serleo_Documentation_Final.pdf';
$pdf->Output($outFile, 'F');

echo "Generated PDF: " . $outFile . PHP_EOL;
