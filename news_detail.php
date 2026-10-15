<?php
session_start();
include 'config.php';
$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;

$logoutMessage = '';
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $logoutMessage = "✅ Αποσυνδεθήκατε με επιτυχία!";
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Δεν βρέθηκε το άρθρο.");
}

$id = (int)$_GET['id'];
$sql = "SELECT * FROM news WHERE id = $id LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Το άρθρο δεν υπάρχει.");
}

$news = $result->fetch_assoc();

$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : null;

// Έλεγχος αν έχει ήδη ψηφίσει
$userVote = null;
if ($isLoggedIn) {
    $checkVote = $conn->prepare("SELECT vote FROM vote_usage WHERE user_id = ? AND news_id = ?");
    $checkVote->bind_param("ii", $user_id, $id);
    $checkVote->execute();
    $resultVote = $checkVote->get_result();
    if ($resultVote->num_rows > 0) {
        $userVote = $resultVote->fetch_assoc()['vote'];
    }
}
?>


<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($news['title']) ?> - SER LEO F.C</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="images/logo.png" type="image/png">
    <link rel="stylesheet" href="style.css">
<style>
/* Γενικές ρυθμίσεις container */
.news-detail-container {
    max-width: 900px;
    margin: 120px auto 60px;
    padding: 20px;
    font-family: 'Poppins', sans-serif;
    color: #ffffff; /* Όλα τα κείμενα λευκά */
}

/* Κάρτα άρθρου */
.news-card {
    background: #111;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 6px 20px #bc8a31;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

/* Εικόνα άρθρου */
.news-card img {
    width: 100%;
    max-height: 500px;
    object-fit: cover;
    display: block;
    margin-top: 10px;
    border-bottom: 1px solid #333;
}

/* Περιεχόμενο άρθρου */
.news-content {
    padding: 25px;
}

/* Τίτλος άρθρου */
.news-content h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 15px;
    color: inherit; /* Κληρονομεί λευκό από container */
}

/* Ημερομηνία άρθρου */
.news-content .date {
    font-size: 0.85rem;
    margin: 15px 0;
    display: block;
    color: inherit;
}

/* Κείμενο άρθρου */
.news-content p {
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 20px;
    color: inherit;
}

/* Κουμπί πίσω */
.back-btn {
    display: inline-block;
    margin-top: 10px;
    background: #643500ff;
    color: #ffffff;
    font-weight: 500;
    padding: 6px 15px;
    border-radius: 4px;
    text-decoration: none;
    transition: 0.3s;
}

.back-btn:hover {
    background: #bc8a31;
    color: #ffffff;
}

/* Share links */
.share-links {
    margin-top: 20px;
    font-size: 0.95rem;
    color: inherit; /* κληρονομεί λευκό */
}

.share-links a {
    text-decoration: none;
    color: #00aaff;
    margin: 0 5px;
}

.share-links a:hover {
    text-decoration: underline;
}

/* Like / Dislike κουμπιά */
.like-dislike {
    margin-top: 25px;
    display: flex;
    gap: 10px;
}

.like-btn, .dislike-btn {
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.3s;
    color: #ffffff;
}

.like-btn {
    background: #008000;
}

.like-btn:hover {
    background: #00b300;
}

.like-btn.active {
    background: #00cc00;
}

.dislike-btn {
    background: #800000;
}

.dislike-btn:hover {
    background: #b30000;
}

.dislike-btn.active {
    background: #cc0000;
}

/* Responsive */
@media (max-width: 768px) {
    .news-detail-container {
        margin: 100px 10px 30px;
        padding: 15px;
    }

    .news-content h1 {
        font-size: 1.8rem;
        margin-bottom: 10px;
    }

    .news-card img {
        max-height: 250px;
    }

    .news-content p {
        font-size: 1rem;
        line-height: 1.4;
    }

    .news-content .date {
        font-size: 0.75rem;
        margin: 10px 0;
    }

    .back-btn {
        padding: 5px 12px;
        font-size: 0.9rem;
        border-radius: 16px;
    }

    .share-links {
        font-size: 0.85rem;
    }

    .share-links a {
        display: inline-block;
        margin: 0 3px;
    }

    .like-dislike {
        flex-direction: column;
        gap: 8px;
    }

    .like-btn, .dislike-btn {
        width: 100%;
        padding: 8px 0;
        font-size: 0.9rem;
    }
}
</style>

</head>
<body>

<!-- MENU -->
<?php include 'nav.php'; ?>


<main class="news-detail-container">
    <div class="news-card">
        <div class="news-content">
            <!-- Τίτλος πάνω από την εικόνα -->
            <h1><?= htmlspecialchars($news['title']) ?></h1>
             <!-- Like / Dislike -->
            <div class="like-dislike">
            <?php if (!$isLoggedIn): ?>
                <p>⚠️ Πρέπει να <a href="login.php">συνδεθείς</a> για να ψηφίσεις.</p>
            <?php else: ?>
                <button class="like-btn <?= $userVote === 'like' ? 'active' : '' ?>" onclick="vote(<?= $id ?>, 'like')">
                👍 Μου αρέσει (<span id="like-count"><?= $news['likes'] ?></span>)
            </button>
            <button class="dislike-btn <?= $userVote === 'dislike' ? 'active' : '' ?>" onclick="vote(<?= $id ?>, 'dislike')">
                👎 Δεν μου αρέσει (<span id="dislike-count"><?= $news['dislikes'] ?></span>)
            </button>

                <?php if ($userVote): ?>
                <?php endif; ?>
            <?php endif; ?>
    </div>
            
            <?php if (!empty($news['image'])): ?>
                <img src="<?= htmlspecialchars($news['image']) ?>" alt="<?= htmlspecialchars($news['title']) ?>">
            <?php endif; ?>
            
            <!-- Ημερομηνία -->
            <span class="date"><?= date("d/m/Y", strtotime($news['created_at'])) ?></span>
            
            <!-- Κείμενο άρθρου -->
            <p><?= nl2br(htmlspecialchars($news['content'])) ?></p>
            
            <!-- Κουμπί πίσω -->
            <a href="news.php" class="back-btn">&larr; Πίσω στις ειδήσεις</a>

            <!-- Share links -->
            <div class="share-links">
                <span>Μοιράσου το άρθρο: </span>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://yourdomain.com/news_detail.php?id='.$id) ?>" target="_blank">Facebook</a> |
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://yourdomain.com/news_detail.php?id='.$id) ?>&text=<?= urlencode($news['title']) ?>" target="_blank">Twitter</a> |
                <a href="mailto:?subject=<?= urlencode($news['title']) ?>&body=<?= urlencode('Δες αυτό το άρθρο: https://yourdomain.com/news_detail.php?id='.$id) ?>">Email</a>
            </div>
           

            

        </div>
            </div>




<script src="navbar.js"></script>

<script>
function vote(id, type) {
    fetch("vote_ajax.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + id + "&vote=" + type
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById("like-count").innerText = data.likes;
            document.getElementById("dislike-count").innerText = data.dislikes;

            let likeBtn = document.querySelector(".like-btn");
            let dislikeBtn = document.querySelector(".dislike-btn");

            if (data.action === "removed") {
                // Έσβησε το vote
                likeBtn.classList.remove("active");
                dislikeBtn.classList.remove("active");
            } else if (data.action === "added" || data.action === "changed") {
                if (data.userVote === "like") {
                    likeBtn.classList.add("active");
                    dislikeBtn.classList.remove("active");
                } else {
                    dislikeBtn.classList.add("active");
                    likeBtn.classList.remove("active");
                }
            }
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error("Error:", error));
}

 // Εμφάνιση / Απόκρυψη burger menu
 document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.getElementById('menu-toggle');
    const navLinks = document.getElementById('nav-links');

    menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
    });
});


</script>




</body>
</html>
