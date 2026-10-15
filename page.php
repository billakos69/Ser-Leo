<?php
// page.php
$page = $_GET['page'] ?? 'home'; // default σε home αν δεν υπάρχει parameter

// Περιεχόμενο για κάθε σελίδα
$pagesContent = [
    'services' => '
<h2>Υπηρεσίες (Services)</h2>
<p>Η ιστοσελίδα του Μαγκαλιάκου προσφέρει ολοκληρωμένη ενημέρωση και υπηρεσίες για τους φίλους της ομάδας και του ποδοσφαίρου γενικότερα:</p>
<ol>
    <li><strong>Νέα της Ομάδας:</strong> Άμεση και έγκυρη ενημέρωση για αγώνες, αποτελέσματα, μεταγραφές και σημαντικά γεγονότα που αφορούν τον Μαγκαλιάκο.</li>
    <li><strong>Αναλύσεις Αγώνων και Στατιστικά:</strong> Λεπτομερείς αναφορές για κάθε αγώνα της ομάδας και στατιστικά παικτών για τους φιλάθλους και επαγγελματίες.</li>
    <li><strong>Shop:</strong> Η σελίδα παρέχει τη δυνατότητα αγοράς προϊόντων της ομάδας.</li>
    <li><strong>Newsletter και Ειδοποιήσεις:</strong> Εγγραφή για να λαμβάνετε ενημερώσεις σχετικά με τους αγώνες, τα νέα και τις εκδηλώσεις του Μαγκαλιάκου.</li>
</ol>
<p>Οι υπηρεσίες μας παρέχονται με γνώμονα την ακρίβεια, την αξιοπιστία και την ασφάλεια των χρηστών.</p>
',

    'privacy' => '
<h2>Πολιτική Απορρήτου (Privacy Policy)</h2>
<p>Η προστασία των προσωπικών δεδομένων των φιλάθλων του Μαγκαλιάκου αποτελεί προτεραιότητά μας.</p>
<ol>
    <li><strong>Συλλογή Δεδομένων:</strong> Συλλέγουμε δεδομένα όπως όνομα, email, προτιμήσεις για newsletter και στατιστικά χρήσης για τη βελτίωση της εμπειρίας σας στην ιστοσελίδα.</li>
    <li><strong>Χρήση Δεδομένων:</strong> Τα δεδομένα χρησιμοποιούνται για:
        <ul>
            <li>Αποστολή ενημερώσεων και newsletter.</li>
            <li>Ανάλυση επισκεψιμότητας και βελτίωση της ιστοσελίδας.</li>
            <li>Προσαρμογή περιεχομένου στις προτιμήσεις σας.</li>
        </ul>
    </li>
    <li><strong>Ασφάλεια Δεδομένων:</strong> Εφαρμόζουμε μέτρα ασφαλείας για την προστασία των πληροφοριών σας από μη εξουσιοδοτημένη πρόσβαση.</li>
    <li><strong>Δικαιώματα Χρηστών:</strong> Έχετε δικαίωμα πρόσβασης, διόρθωσης ή διαγραφής των προσωπικών σας δεδομένων ανά πάσα στιγμή.</li>
    <li><strong>Cookies:</strong> Χρησιμοποιούμε cookies για την καλύτερη λειτουργία της ιστοσελίδας και την εξατομίκευση περιεχομένου.</li>
</ol>
<p>Με την πλοήγησή σας στην ιστοσελίδα του Μαγκαλιάκου, συμφωνείτε με την Πολιτική Απορρήτου μας.</p>
',

    'terms' => '
<h2>Όροι Χρήσης (Terms of Service)</h2>
<p>Η χρήση της ιστοσελίδας του Μαγκαλιάκου υπόκειται στους παρακάτω όρους:</p>
<ol>
    <li><strong>Αποδοχή Όρων:</strong> Η πρόσβαση και χρήση της ιστοσελίδας σημαίνει αποδοχή των όρων χρήσης και της Πολιτικής Απορρήτου.</li>
    <li><strong>Περιεχόμενο:</strong> Όλο το περιεχόμενο παρέχεται για ενημερωτικούς σκοπούς. Απαγορεύεται η αναπαραγωγή χωρίς άδεια της ομάδας ή των διαχειριστών.</li>
    <li><strong>Αποποίηση Ευθυνών:</strong> Παρά τις προσπάθειες για ακριβείς πληροφορίες, η ομάδα δεν ευθύνεται για λάθη, καθυστερήσεις ή ζημιές που μπορεί να προκύψουν από τη χρήση της ιστοσελίδας.</li>
    <li><strong>Τροποποιήσεις Όρων:</strong> Η ομάδα διατηρεί το δικαίωμα να τροποποιεί τους όρους χρήσης οποιαδήποτε στιγμή, με ισχύ από τη δημοσίευσή τους.</li>
</ol>
<p>Η συνεχιζόμενη χρήση της ιστοσελίδας μετά από αλλαγές θεωρείται αποδοχή των νέων όρων.</p>
',
];


// Αν δεν υπάρχει το page, βάζουμε μήνυμα λάθους
$content = $pagesContent[$page] ?? 'Η σελίδα δεν βρέθηκε.';
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>SER LEO - <?php echo ucfirst($page); ?></title>
<link rel="stylesheet" href="style.css">
<link rel="icon" href="images/logo.png" type="image/png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>

<style>


.container {
  max-width: 800px;
  margin: 40px auto;
  background: #7e0000;
  padding: 30px;
  border-radius: 16px;
  box-shadow: 0 0 15px #ff0000;
  color: #ffffff;
  font-family: Arial, sans-serif;
  line-height: 1.6;
}
.container h1 {
  text-align: center;
  margin-bottom: 20px;
}
.container .content ol {
  padding-left: 20px;
}

</style>
</head>
<body>

<!-- MENU -->
<?php include 'nav.php'; ?>


<div class="container">
    <h1><?php echo ucfirst($page); ?></h1>
    <p><?php echo $content; ?></p>
</div>
<script src="navbar.js"></script>

<?php include 'footer.html'; ?>
<script>
   // Εμφάνιση / Απόκρυψη burger menu
  const menuToggle = document.getElementById('menu-toggle');
  const navLinks = document.getElementById('nav-links');

  menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
  });

  </script>
</body>
</html>
