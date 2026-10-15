<?php
session_start();
include 'config.php';

// Ασφάλεια: μόνο admin
if(!isset($_SESSION['user']) || $_SESSION['user']!=='Admin'){
    header("Location: login.php"); exit;
}

// Section
$section = $_GET['section'] ?? 'products';

// --- Διαγραφή αντικειμένων ---
if(isset($_GET['delete_product'])){
    $id=(int)$_GET['delete_product'];
    $conn->query("DELETE FROM products WHERE id=$id");
    header("Location: admin_panel.php?section=products"); exit;
}
if(isset($_GET['delete_news'])){
    $id=(int)$_GET['delete_news'];
    $conn->query("DELETE FROM news WHERE id=$id");
    header("Location: admin_panel.php?section=news"); exit;
}
if(isset($_GET['delete_player'])){
    $id=(int)$_GET['delete_player'];
    $conn->query("DELETE FROM players WHERE id=$id");
    header("Location: admin_panel.php?section=players"); exit;
}
if(isset($_GET['delete_team'])){
    $id=(int)$_GET['delete_team'];
    $conn->query("DELETE FROM teams WHERE id=$id");
    header("Location: admin_panel.php?section=teams"); exit;
}
if(isset($_GET['delete_match'])){
    $id=(int)$_GET['delete_match'];
    $conn->query("DELETE FROM matches WHERE id=$id");
    header("Location: admin_panel.php?section=league"); exit;
}
if(isset($_GET['delete_ticket'])){
    $id=(int)$_GET['delete_ticket'];
    $conn->query("DELETE FROM tick WHERE id=$id");
    header("Location: admin_panel.php?section=tickets"); exit;
}
if(isset($_GET['delete_seat'])){
    $id = (int)$_GET['delete_seat'];
    $conn->query("DELETE FROM seats WHERE id=$id");
    header("Location: admin_panel.php?section=seats"); exit;
}
if(isset($_GET['delete_discount'])){
    $code = $conn->real_escape_string($_GET['delete_discount']);
    $conn->query("DELETE FROM discounts WHERE code='$code'");
    $conn->query("DELETE FROM discount_usage WHERE discount_code='$code'");
    header("Location: admin_panel.php?section=discounts"); exit;
}

if(isset($_GET['delete_promotion_option'])){
    $code = $conn->real_escape_string($_GET['delete_promotion_option']);
    $conn->query("DELETE FROM product_options WHERE code='$code'");
    header("Location: admin_panel.php?section=product_options"); exit;
}

if(isset($_GET['delete_match_stats'])){
    $code = $conn->real_escape_string($_GET['delete_match_stats']);
    $conn->query("DELETE FROM match_stats WHERE match_id='$code'");
    header("Location: admin_panel.php?section=match_stats"); exit;
}

// Διαγραφή χρήσης κουπονιού
if(isset($_GET['delete_discount_usage'])){
    $id = (int)$_GET['delete_discount_usage'];
    $conn->query("DELETE FROM discount_usage WHERE id=$id");
    header("Location: admin_panel.php?section=discount_usage"); exit;
}
if(isset($_GET['delete_promotion'])){
    $id=(int)$_GET['delete_promotion'];
    $conn->query("DELETE FROM product_promotions WHERE id=$id");
    header("Location: admin_panel.php?section=promotions"); exit;
}

// Μαζική αλλαγή όλων των θέσεων σε Available
if(isset($_GET['set_all_available'])){
    $conn->query("UPDATE seats SET available=0"); // 0 = Available
    header("Location: admin_panel.php?section=seats"); exit;
}

// Μαζική αλλαγή όλων των θέσεων σε Booked
if(isset($_GET['set_all_booked'])){
    $conn->query("UPDATE seats SET available=1"); // 1 = Booked
    header("Location: admin_panel.php?section=seats"); exit;
}


// --- Load data per section ---
switch($section){
    case 'products':
        $items = $conn->query("SELECT * FROM products ORDER BY id DESC");
        break;
    case 'players':
        $items = $conn->query("SELECT * FROM players ORDER BY id DESC");
        break;
    case 'news':
        $items = $conn->query("SELECT * FROM news ORDER BY id DESC");
        break;
    case 'teams':
        $items = $conn->query("SELECT * FROM teams ORDER BY id DESC");
        break;
    case 'league':
        $items = $conn->query("
            SELECT m.*, th.name AS home_team_name, ta.name AS away_team_name
            FROM matches m
            JOIN teams th ON m.home_team=th.id
            JOIN teams ta ON m.away_team=ta.id
            ORDER BY match_date DESC
        ");
        break;
    case 'tickets':
        $items = $conn->query("SELECT * FROM tick ORDER BY match_date DESC");
        break;
    case 'seats':
        $items = $conn->query("SELECT * FROM seats ORDER BY Gate, seat_row, seat_number");
        break;
    default:
        $items = null;

    case 'promotions':
    $items = $conn->query("SELECT * FROM product_promotions ORDER BY start_date DESC");
    break;

    case 'discounts':
    $items = $conn->query("SELECT * FROM discounts ORDER BY code ASC");
    break;

    case 'product_options':
    $items = $conn->query("SELECT * FROM product_options ORDER BY name ASC");
    break;
    
    case 'match_stats':
    $items = $conn->query("SELECT * FROM match_stats ORDER BY match_id ASC");
    break;

    case 'discount_usage':
    $code = $_GET['code'] ?? null;
    if($code){
        $code_safe = $conn->real_escape_string($code);
        $items = $conn->query("
            SELECT du.*, u.username, d.amount
            FROM discount_usage du
            JOIN users u ON du.user_id = u.id
            JOIN discounts d ON du.discount_code = d.code
            WHERE du.discount_code='$code_safe'
            ORDER BY du.used_at DESC
        ");
    } else {
        $items = $conn->query("
            SELECT du.*, u.username, d.amount
            FROM discount_usage du
            JOIN users u ON du.user_id = u.id
            JOIN discounts d ON du.discount_code = d.code
            ORDER BY du.used_at DESC
        ");
    }
    break;


    break;

}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {--bg:#111;--card:#222;--accent:#bc8a31;--hover:#5f4009;--text:#fff;}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);margin:0;padding:0;}
.container{width:90%;max-width:1200px;margin:30px auto;}
h1,h2{text-align:center;color:var(--accent);margin-bottom:20px;}
.top-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;justify-content:center;}
.top-bar a{padding:8px 15px;background:var(--accent);color:#fff;text-decoration:none;border-radius:4px;transition:0.3s;}
.top-bar a:hover{background:var(--hover);}
table{width:100%;border-collapse:collapse;margin-bottom:30px;background:var(--card);}
th,td{padding:10px;text-align:left;color:#fff;}
th{background:var(--accent);}
tr:nth-child(even){background:#222;}
tr:hover{background:#333;}
a.button{padding:4px 8px;background:var(--accent);color:#fff;text-decoration:none;border-radius:4px;transition:0.3s;}
a.button:hover{background:var(--hover);}
img{max-width:50px;height:auto;}
</style>
</head>
<body>
<div class="container">
<h1>Admin Panel</h1>
<div class="top-bar">
    <a href="admin_panel.php?section=products">Προϊόντα</a>
    <a href="admin_panel.php?section=players">Παίκτες</a>
    <a href="admin_panel.php?section=news">Ειδήσεις</a>
    <a href="admin_panel.php?section=teams">Ομάδες</a>
    <a href="admin_panel.php?section=league">Πρωτάθλημα</a>
    <a href="admin_panel.php?section=match_stats">Στατιστικά Αγώνων</a>
    <a href="admin_panel.php?section=tickets">Εισητήρια</a>
    <a href="admin_panel.php?section=seats">Θέσεις</a>
    <a href="admin_panel.php?section=discounts">Κουπόνια</a>
    <a href="admin_panel.php?section=promotions">Promotions</a>
    <a href="admin_panel.php?section=discount_usage">Χρήση κουπονιών</a>
    <a href="admin_panel.php?section=product_options">Επιλογές Μεγέθους</a>
    <a href="edit_emails.php">Ειδοποιήσεις Email</a>
</div>

<?php if($section=='products'): ?>
<h2>Προϊόντα <a class="button" href="edit_product.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr><th>ID</th><th>Όνομα</th><th>Τιμή</th><th>Stock</th><th>Ενέργειες</th></tr>
<?php while($p=$items->fetch_assoc()): ?>
<tr>
<td><?=$p['id']?></td>
<td><?=htmlspecialchars($p['name'])?></td>
<td><?=$p['price']?></td>
<td><?=$p['stock']?></td>
<td>
<a class="button" href="edit_product.php?id=<?=$p['id']?>">Edit</a>
<a class="button" href="?delete_product=<?=$p['id']?>" onclick="return confirm('Σίγουρα;')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν προϊόντα.</p><?php endif; ?>

<?php elseif($section=='players'): ?>
<h2>Παίκτες <a class="button" href="edit_player.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr>
    <th>ID</th>
    <th>Όνομα</th>
    <th>Θέση</th>
    <th>Number</th>
    <th>Matches</th>
    <th>Goals</th>
    <th>Assists</th>
    <th>xGoals</th>
    <th>Clean Sheets</th>
    <th>Saves</th>
    <th>Ενέργειες</th>
</tr>
<?php while($p=$items->fetch_assoc()): ?>
<tr>
    <td><?= $p['id'] ?></td>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['position'] ?? '-') ?></td>
    <td style="text-align:center;"><?= $p['number'] ?? '-' ?></td>
    <td style="text-align:center;"><?= $p['matches'] ?></td>
    <td style="text-align:center; color:#bc8a31; font-weight:bold;"><?= $p['goals'] ?></td>
    <td style="text-align:center; color:#4caf50; font-weight:bold;"><?= $p['assists'] ?></td>
    <td style="text-align:center; color:#ff9800; font-weight:bold;"><?= $p['xgoals'] ?></td>
    <td style="text-align:center; color:#2196f3; font-weight:bold;"><?= $p['clean_sheets'] ?></td>
    <td style="text-align:center; color:#f44336; font-weight:bold;"><?= $p['saves'] ?></td>
    <td>
        <a class="button" href="edit_player.php?id=<?= $p['id'] ?>">Edit</a>
        <a class="button" href="?delete_player=<?= $p['id'] ?>" onclick="return confirm('Σίγουρα;')">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν παίκτες.</p><?php endif; ?>



<?php elseif($section=='news'): ?>
<h2>Ειδήσεις <a class="button" href="edit_news.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr><th>ID</th><th>Τίτλος</th><th>Κείμενο</th><th>Εικόνα</th><th>Ενέργειες</th></tr>
<?php while($n=$items->fetch_assoc()): ?>
<tr>
<td><?=$n['id']?></td>
<td><?=htmlspecialchars($n['title'])?></td>
<td><?=htmlspecialchars(substr($n['content'],0,50))?>...</td>
<td><?php if($n['image']): ?><img src="<?=htmlspecialchars($n['image'])?>"><?php endif;?></td>
<td>
<a class="button" href="edit_news.php?id=<?=$n['id']?>">Edit</a>
<a class="button" href="?delete_news=<?=$n['id']?>" onclick="return confirm('Σίγουρα;')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν ειδήσεις.</p><?php endif; ?>

<?php elseif($section=='teams'): ?>
<h2>Ομάδες <a class="button" href="edit_team.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr><th>ID</th><th>Όνομα</th><th>Logo</th><th>Πόλη</th><th>Coach</th><th>Γήπεδο</th><th>Ενέργειες</th></tr>
<?php while($t=$items->fetch_assoc()): ?>
<tr>
<td><?=$t['id']?></td>
<td><?=htmlspecialchars($t['name'])?></td>
<td><?php if($t['logo']): ?><img src="<?=htmlspecialchars($t['logo'])?>"><?php endif;?></td>
<td><?=htmlspecialchars($t['city'])?></td>
<td><?=htmlspecialchars($t['coach'])?></td>
<td><?=htmlspecialchars($t['stadium'])?></td>
<td>
<a class="button" href="edit_team.php?id=<?=$t['id']?>">Edit</a>
<a class="button" href="?delete_team=<?=$t['id']?>" onclick="return confirm('Σίγουρα;')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν ομάδες.</p><?php endif; ?>

<?php elseif($section=='league'): ?>
<h2>Πρωτάθλημα / Αγώνες <a class="button" href="edit_match.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr><th>ID</th><th>Home</th><th>Away</th><th>Score</th><th>Date</th><th>Ενέργειες</th></tr>
<?php while($m=$items->fetch_assoc()): ?>
<tr>
<td><?=$m['id']?></td>
<td><?=htmlspecialchars($m['home_team_name'])?></td>
<td><?=htmlspecialchars($m['away_team_name'])?></td>
<td><?=$m['home_score']??'-'?> - <?=$m['away_score']??'-'?></td>
<td><?=$m['match_date']?></td>
<td>
<a class="button" href="edit_match.php?id=<?=$m['id']?>">Edit</a>
<a class="button" href="?delete_match=<?=$m['id']?>" onclick="return confirm('Σίγουρα;')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν αγώνες.</p><?php endif; ?>

<?php elseif($section=='tickets'): ?>
<h2>Εισητήρια <a class="button" href="edit_ticket.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr><th>ID</th><th>Stadium</th><th>Date</th><th>UPCOMING</th><th>IMAGE</th><th>Ενέργειες</th></tr>
<?php while($t=$items->fetch_assoc()): ?>
<tr>
<td><?=$t['id']?></td>
<td><?=htmlspecialchars($t['stadium'])?></td>
<td><?=$t['match_date']?></td>
<td><?=htmlspecialchars($t['upcoming'] ?? '-')?></td>
<td><?=htmlspecialchars($t['image_url'] ?? '-')?></td>
<td>
<a class="button" href="edit_ticket.php?id=<?=$t['id']?>">Edit</a>
<a class="button" href="?delete_ticket=<?=$t['id']?>" onclick="return confirm('Σίγουρα;')">Delete</a>
<a class="button" href="admin_panel.php?section=seats&match=<?=$t['id']?>">Seats</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν tickets.</p><?php endif; ?>

<?php elseif($section=='seats'): ?>
<h2>Όλες οι Θέσεις 
    <a class="button" href="edit_seat.php">+ Προσθήκη</a>
    <a class="button" href="?set_all_available=1" 
    style="background:green; color:white; border-radius:4px; padding:8px 15px;  text-decoration:none;" 
    onclick="return confirm('Να γίνουν όλες οι θέσεις Available;')">
    Όλες Available
    </a>
    <a class="button" href="?set_all_booked=1" onclick="return confirm('Να γίνουν όλες οι θέσεις Booked;')">Όλες Booked</a>
</h2>


<?php 
// Παίρνουμε όλες τις θέσεις, ταξινομημένες κατά Gate, Row, Number
$items = $conn->query("SELECT * FROM seats ORDER BY ID,Gate, seat_row, seat_number,image_url");
?>

<?php if($items && $items->num_rows > 0): ?>
<table>
    <tr>
        <th>ID</th>
        <th>Gate</th>
        <th>Row</th>
        <th>Number</th>
        <th>Price</th>
        <th>Status</th>
        <th>Image URL</th>
        <th>Ενέργειες</th>
    </tr>
    <?php while($s = $items->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($s['id'] ?? '-') ?></td>
        <td><?= htmlspecialchars($s['Gate'] ?? '-') ?></td>
        <td><?= htmlspecialchars($s['seat_row'] ?? '-') ?></td>
        <td><?= htmlspecialchars($s['seat_number'] ?? '-') ?></td>
        <td><?= htmlspecialchars($s['price'] ?? '-') ?></td>
        <td>
            <?php 
                if($s['available'] == 1){
                    echo '<span style="color:red;font-weight:bold;">Booked</span>';
                } else {
                    echo '<span style="color:green;font-weight:bold;">Available</span>';
                }
            ?>
        </td>
         <td><?= htmlspecialchars($s['image_url'] ?? '-') ?></td>
        <td>
            <a class="button" href="edit_seat.php?id=<?= htmlspecialchars($s['id'] ?? 0) ?>">Edit</a>
            <a class="button" href="?delete_seat=<?= htmlspecialchars($s['id'] ?? 0) ?>" onclick="return confirm('Σίγουρα;')">Delete</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<?php else: ?>
<p>Δεν βρέθηκαν θέσεις.</p>
<?php endif; ?>

<?php elseif($section=='discounts'): ?>
<h2>Κουπόνια <a class="button" href="edit_discount.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr><th>Κωδικός</th><th>Έκπτωση (%)</th><th>Χρησιμοποιημένο από χρήστες</th><th>Ενέργειες</th></tr>
<?php while($d=$items->fetch_assoc()): ?>
<tr>
<td><?=htmlspecialchars($d['code'])?></td>
<td><?=($d['amount']*100)?>%</td>
<td>
<?php
$usage_res = $conn->query("SELECT COUNT(*) AS cnt FROM discount_usage WHERE discount_code='". $conn->real_escape_string($d['code']) ."'");
$usage = $usage_res->fetch_assoc();
echo $usage['cnt'];
?>
</td>
<td>
<a class="button" href="edit_discount.php?code=<?=urlencode($d['code'])?>">Edit</a>
<a class="button" href="?delete_discount=<?=urlencode($d['code'])?>" onclick="return confirm('Σίγουρα;')">Delete</a>
<a class="button" href="admin_panel.php?section=discount_usage&code=<?=urlencode($d['code'])?>">Usage</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν κουπόνια.</p><?php endif; ?>


<?php elseif($section=='discount_usage'): ?>
<h2>Χρήση Κουπονιών <?php if($code) echo "(Κωδικός: ".htmlspecialchars($code).")"; ?></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr>
<th>ID</th>
<th>Χρήστης</th>
<th>Κουπόνι</th>
<th>Έκπτωση (%)</th>
<th>Ημερομηνία Χρήσης</th>
<th>Ενέργειες</th>
</tr>
<?php while($u=$items->fetch_assoc()): ?>
<tr>
<td><?= $u['id'] ?></td>
<td><?= htmlspecialchars($u['username']) ?></td>
<td><?= htmlspecialchars($u['discount_code']) ?></td>
<td><?= ($u['amount']*100) ?>%</td>
<td><?= $u['used_at'] ?></td>
<td>
<a class="button" href="?delete_discount_usage=<?= $u['id'] ?>" onclick="return confirm('Σίγουρα;')">Διαγραφή</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>Δεν υπάρχουν χρήσεις κουπονιών.</p>
<?php endif; ?>

<?php elseif($section=='promotions'): ?>
<h2>Promotions <a class="button" href="edit_promotion.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr>
<th>ID</th>
<th>Προϊόντα (IDs)</th>
<th>% Έκπτωση</th>
<th>Ημ/νία Έναρξης</th>
<th>Ημ/νία Λήξης</th>
<th>Active</th>
<th>Ενέργειες</th>
</tr>
<?php while($p=$items->fetch_assoc()): ?>
<tr>
<td><?=$p['id']?></td>
<td><?=htmlspecialchars($p['products_id'])?></td>
<td><?=$p['percentage']?>%</td>
<td><?=$p['start_date']?></td>
<td><?=$p['end_date']?></td>
<td><?=$p['active'] ? 'Yes' : 'No'?></td>
<td>
<a class="button" href="edit_promotion.php?id=<?=$p['id']?>">Edit</a>
<a class="button" href="?delete_promotion=<?=$p['id']?>" onclick="return confirm('Σίγουρα;')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν promotions.</p><?php endif; ?>

<?php elseif($section=='product_options'): ?>
<h2>Promotion Options <a class="button" href="edit_product_options.php">+ Προσθήκη</a></h2>
<?php if($items && $items->num_rows>0): ?>
<table>
<tr>
<th>ID</th>
<th>NAME</th>
<th>SIZE</th>

</tr>
<?php while($p=$items->fetch_assoc()): ?>
<tr>
<td><?=$p['id']?></td>
<td><?=htmlspecialchars($p['name'])?></td>
<td><?=$p['size']?></td>
<td>
<a class="button" href="edit_product_options.php?id=<?=$p['id']?>">Edit</a>
<a class="button" href="?delete_promotion=<?=$p['id']?>" onclick="return confirm('Σίγουρα;')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?><p>Δεν βρέθηκαν promotions.</p><?php endif; ?>

<?php elseif($section=='match_stats'): ?>
<h2>Στατιστικά Αγώνων <a class="button" href="edit_match_stats.php">+ Προσθήκη</a></h2>

<?php if($items && $items->num_rows>0): ?>
<table>
<tr>
    <th>ID</th>
    <th>Match</th>
    <th>Possession (%)</th>
    <th>Total Shots</th>
    <th>Shots on Target</th>
    <th>Corners</th>
    <th>Fouls</th>
    <th>Yellow Cards</th>
    <th>Red Cards</th>
    <th>Passes</th>
    <th>Ενέργειες</th>
</tr>
<?php while($s=$items->fetch_assoc()):
    $possession = (($s['ball_possession_home'] ?? 0) + ($s['ball_possession_away'] ?? 0));
    $total_shots = (($s['total_shots_home'] ?? 0) + ($s['total_shots_away'] ?? 0));
    $corners = (($s['corner_kicks_home'] ?? 0) + ($s['corner_kicks_away'] ?? 0));
    $fouls = (($s['fouls_home'] ?? 0) + ($s['fouls_away'] ?? 0));
    $yellow = (($s['yellow_cards_home'] ?? 0) + ($s['yellow_cards_away'] ?? 0));
    $red = (($s['red_cards_home'] ?? 0) + ($s['red_cards_away'] ?? 0));
    $passes = (($s['passes_home'] ?? 0) + ($s['passes_away'] ?? 0));
?>
<tr>
    <td><?= $s['id'] ?></td>
    <td><?= $s['match_id'] ?></td>
    <td><?= $possession ?></td>
    <td><?= $total_shots ?></td>
    <td><?= '-' ?></td>
    <td><?= $corners ?></td>
    <td><?= $fouls ?></td>
    <td><?= $yellow ?></td>
    <td><?= $red ?></td>
    <td><?= $passes ?></td>
    <td>
        <!-- Στέλνουμε το match_id στο edit -->
        <a class="button" href="edit_match_stats.php?match_id=<?= $s['match_id'] ?>">Edit</a>
        <a class="button" href="?delete_match_stats=<?= $s['id'] ?>" onclick="return confirm('Σίγουρα;')">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>Δεν βρέθηκαν στατιστικά αγώνων.</p>
<?php endif; ?>




   
<?php endif; ?>

</div>
</body>
</html>
