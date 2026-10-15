<?php
session_start();
include 'config.php';

// Μόνο admin
if(!isset($_SESSION['user']) || $_SESSION['user']!=='Admin'){
    die("Access denied");
}

// Φόρτωση προϊόντων
$products_res = $conn->query("
    SELECT p.id, p.name, p.image, p.price, 
      (CASE 
        WHEN pp.active=1 
             AND NOW() BETWEEN pp.start_date AND pp.end_date
             AND FIND_IN_SET(p.id, pp.products_id)
        THEN ROUND(p.price * (1 - pp.percentage/100),2)
        ELSE p.price
      END) AS current_price
    FROM products p
    LEFT JOIN product_promotions pp
      ON FIND_IN_SET(p.id, pp.products_id)
    ORDER BY p.id DESC
");
$products=[];
while($p=$products_res->fetch_assoc()) $products[]=$p;

// Φόρτωση αγώνων
// Φόρτωση αγώνων με ονόματα ομάδων
$matches_res = $conn->query("
    SELECT 
        m.id,
        th.name AS home_team,
        ta.name AS away_team,
        m.home_score,
        m.away_score,
        m.match_date,
        m.stadium,
        m.image
    FROM matches m
    LEFT JOIN teams th ON m.home_team = th.id
    LEFT JOIN teams ta ON m.away_team = ta.id
    ORDER BY m.match_date ASC
");
$matches = [];
while($m = $matches_res->fetch_assoc()) $matches[] = $m;

?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Δημιουργία Newsletter</title>
<style>
body{font-family:Arial,sans-serif;background:#111;color:#fff;margin:0;padding:0;}
.container{width:90%;max-width:1000px;margin:30px auto;background:#222;padding:20px;border-radius:10px;}
input, select, textarea{width:100%;padding:8px;margin:5px 0;border-radius:5px;border:none;}
button{margin-top:15px;padding:10px 20px;background:#bc8a31;color:#fff;border:none;border-radius:5px;cursor:pointer;}
#preview{margin-top:20px;background:#333;padding:15px;border-radius:8px;}
#preview img{max-width:150px;border-radius:8px;margin-bottom:5px;}
</style>
</head>
<body>
<div class="container">
<h1 style="text-align:center;color:#bc8a31;">Δημιουργία Newsletter</h1>

<form id="emailForm">
    <label>Τίτλος Email:</label>
    <input type="text" id="emailTitle" value="Νέα ενημέρωση από το Shop!">

    <label>Κείμενο Email:</label>
    <textarea id="emailText" rows="4">Γράψε εδώ το κείμενο του newsletter...</textarea>

    <label>Χρώμα Container:</label>
    <input type="color" id="containerColor" value="#222222">

    <label>Επιλογή Προϊόντων:</label>
    <select id="productSelect">
        <option value="">--Επιλέξτε προϊόν--</option>
        <?php foreach($products as $p): ?>
            <option value="<?= $p['id'] ?>" data-image="<?= $p['image'] ?>">
                <?= htmlspecialchars($p['name']) ?> (<?= number_format($p['current_price'],2) ?>€)
            </option>
        <?php endforeach; ?>
    </select>
    <button type="button" onclick="addProduct()">Προσθήκη Προϊόντος</button>

    <label>Επιλογή Αγώνα:</label>
    <select id="matchSelect">
        <option value="">--Επιλέξτε αγώνα--</option>
        <?php foreach($matches as $m): ?>
            <option value="<?= $m['id'] ?>">
                <?= htmlspecialchars($m['home_team']) ?> vs <?= htmlspecialchars($m['away_team']) ?> 
                (<?= date('d/m/Y H:i', strtotime($m['match_date'])) ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <button type="button" onclick="addMatch()">Προσθήκη Αγώνα</button>
    
    <label>Προσθήκη Δικής σου Εικόνας:</label>
    <input type="file" id="customImageInput" accept="image/*">
    <button type="button" onclick="addCustomImage()">Προσθήκη Εικόνας</button>


    <button type="button" onclick="sendEmail()">Αποστολή Email</button>

    <h3>Προεπισκόπηση Email:</h3>
    <div id="preview"></div>

    <div id="progressContainer" style="display:none;margin-top:15px;">
  <div style="background:#444;height:20px;border-radius:10px;overflow:hidden;">
    <div id="progressBar" style="background:#bc8a31;height:100%;width:0%;transition:width 0.3s;"></div>
  </div>
  <p id="progressText" style="text-align:center;margin-top:5px;">Αποστολή...</p>
</div>

</form>
</div>

<script>
const products = <?php echo json_encode($products); ?>;
const matches = <?php echo json_encode($matches); ?>;
let selectedProducts = [];
let selectedMatches = [];

const productSelect = document.getElementById('productSelect');
const matchSelect = document.getElementById('matchSelect');
const preview = document.getElementById('preview');
const emailTitle = document.getElementById('emailTitle');
const emailText = document.getElementById('emailText');
const containerColor = document.getElementById('containerColor');

// --- Προϊόν ---
function addProduct(){
    const id = productSelect.value;
    if(!id) return;

    const product = products.find(p => p.id == id);
    if(!product) return;

    selectedProducts.push({
        id: product.id,
        name: product.name,
        image: product.image,
        price: product.current_price,
        original_price: product.price
    });



    updatePreview();
}

// --- Αγώνας ---
function addMatch() {
    const id = matchSelect.value;
    if (!id) return;

    const match = matches.find(m => m.id == id);
    if(!match) return;

    selectedMatches.push(match);
    updatePreview();

}



// --- Προεπισκόπηση ---
let customImages = [];

function addCustomImage() {
    const fileInput = document.getElementById('customImageInput');
    const file = fileInput.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        customImages.push(e.target.result); // Base64 string
        updatePreview();
    };
    reader.readAsDataURL(file);
}


// --- Προεπισκόπηση ---
function updatePreview() {
    let html = `<div style="background:${containerColor.value};padding:20px;border-radius:12px;color:#fff;">`;
    html += `<div style="text-align:left;"><img src='https://yourdomain.com/images/logo.png' style='height:60px;'></div>`;
    html += `<h2>Γεια σου, <span style="color:#ffd700;">USERNAME</span> 👋</h2>`;
    html += `<h3>${emailTitle.value}</h3>`;
    html += `<p>${emailText.value}</p>`;

    // Προϊόντα
    if (selectedProducts.length > 0) {
        html += `<h3 style="color:#ff3131;">Προτεινόμενα προϊόντα</h3>`;
        html += `<div style="display:flex;flex-wrap:wrap;gap:10px;">`;
        selectedProducts.forEach(p => {
            html += `<div style="flex:0 0 48%;background:#333;padding:10px;border-radius:10px;text-align:center;">`;
            html += `<img src="${p.image}" style="width:100%;border-radius:8px;margin-bottom:5px;">`;
            if(p.price < p.original_price){
                html += `<div style="margin-top:5px;font-weight:bold;color:#ff3131;"><del style="color:#888">${p.original_price}€</del> ${p.price}€</div>`;
            } else {
                html += `<div style="margin-top:5px;font-weight:bold;color:#ff3131;">${p.price}€</div>`;
            }
            html += `</div>`;
        });
        html += `</div>`;
    }

    // Αγώνες
    if (selectedMatches.length > 0) {
        html += `<h3 style="color:#ffd700;margin-top:20px;">Επερχόμενοι Αγώνες</h3>`;
        selectedMatches.forEach(m => {
            html += `<div style="background:#333;padding:10px;border-radius:10px;margin-bottom:10px;">`;
            if (m.image) html += `<img src="${m.image}" style="width:100%;border-radius:8px;margin-bottom:5px;">`;
            html += `<strong>${m.home_team} vs ${m.away_team}</strong><br>`;
            html += `Ημερομηνία: ${m.match_date}<br>`;
            if (m.stadium) html += `Γήπεδο: ${m.stadium}<br>`;
            if (m.home_score !== null && m.away_score !== null) {
                html += `Σκορ: ${m.home_score} - ${m.away_score}<br>`;
            }
            html += `</div>`;
        });
    }

    // Custom εικόνες
    if (customImages.length > 0) {
        html += `<h3 style="color:#31ff5a;margin-top:20px;">Προσωπικές Εικόνες</h3>`;
        html += `<div style="display:flex;flex-direction:column;gap:10px;">`;
        customImages.forEach(img => {
            html += `<div style="background:#333;padding:10px;border-radius:10px;text-align:center;">`;
            html += `<img src="${img}" style="width:100%;max-width:600px;border-radius:8px;">`;
            html += `</div>`;
        });
        html += `</div>`;
    }

    html += `<div style="text-align:center;margin-top:20px;font-weight:bold;color:#ffd700;">Μην ξεχνάς! Ο κωδικός <span style="color:#ff3131;">LEO10</span> δίνει 10% έκπτωση!</div>`;
    html += `</div>`;
    preview.innerHTML = html;
}

// --- Αποστολή ---
function sendEmail() {
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');

    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.textContent = 'Ξεκίνησε η αποστολή...';

    let fakeProgress = 0;
    const interval = setInterval(() => {
        fakeProgress += Math.random() * 15;
        if (fakeProgress > 90) fakeProgress = 90;
        progressBar.style.width = fakeProgress + '%';
    }, 500);

    const data = {
        title: emailTitle.value,
        text: emailText.value,
        color: containerColor.value,
        products: selectedProducts,
        matches: selectedMatches,
        images: customImages
    };

    fetch('send_email.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.text())
    .then(resp => {
        clearInterval(interval);
        progressBar.style.width = '100%';
        progressText.textContent = '✅ Το email στάλθηκε με επιτυχία!';
        alert(resp);
    })
    .catch(err => {
        clearInterval(interval);
        progressText.textContent = '❌ Σφάλμα κατά την αποστολή!';
        alert('Σφάλμα αποστολής: ' + err);
    });
}
</script>
</body>
</html>
