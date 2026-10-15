<?php
session_start();
include 'config.php';
$isLoggedIn = isset($_SESSION['user']);
$username   = $isLoggedIn ? $_SESSION['user'] : null;

$logoutMessage = '';
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $logoutMessage = "✅ Αποσυνδεθήκατε με επιτυχία!";
}

// --- Ταξινόμηση ---
$order = isset($_GET['order']) ? $_GET['order'] : 'newest';
switch ($order) {
    case 'oldest':
        $orderBy = "created_at ASC";
        break;
    case 'popular':
        $orderBy = "likes DESC";
        break;
    case 'unpopular':
        $orderBy = "likes ASC";
        break;
    default:
        $orderBy = "created_at DESC";
}

// --- Pagination ---
$limit = 6; // πόσα νέα ανά σελίδα
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM news ORDER BY $orderBy LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$totalRows = $conn->query("SELECT FOUND_ROWS() as total")->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>SER LEO F.C</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
    <link rel="icon" href="images/logo.png" type="image/png">   
    <style>
        .news-container {
            max-width: 1200px;
            margin: 140px auto 60px;
            padding: 0 20px;
        }
        .news-container h2 {
            font-size: 2.2rem;
            font-weight: 600;
            text-align: center;
            color: #bc8a31;
            margin-bottom: 20px;
        }
        /* Φίλτρο */
          .filter-bar {
              display: flex;
              justify-content: flex-end; /* τερμα δεξιά */
              margin-bottom: 25px;
          }

          .filter-bar select {
              padding: 8px 14px;
              border-radius: 6px;
              border: 2px solid #643500ff; /* σκούρο κόκκινο */
              background: #000; /* μαύρο background */
              color: #bc8a31; /* κόκκινο κείμενο */
              font-weight: bold;
              cursor: pointer;
              transition: 0.3s;
          }

          /* Hover effect */
          .filter-bar select:hover {
              background: #643900ff; /* κόκκινο */
              color: #fff; /* λευκό */
              border-color: #bc8a31;
          }

          /* Option styling */
          .filter-bar select option {
              background: #181313ff; /* μαύρο */
              color: #bc8a31;   /* κόκκινο */
              font-weight: bold;
          }

        /* Grid */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
        }
        .news-card {
            background: #111;
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(255, 136, 0, 0.15);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .news-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 25px rgba(255, 136, 0, 0.15);
        }
        .news-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .news-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .news-content h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 10px;
        }
        .news-content .date {
            font-size: 0.9rem;
            color: #bbb;
            margin-bottom: 15px;
        }
        .read-more {
            margin-top: auto;
            align-self: flex-start;
            padding: 8px 18px;
            background: linear-gradient(135deg, #221600ff, #643e0dff);
            color: #fff;
            font-weight: 600;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .read-more:hover {
            background: linear-gradient(135deg, #634401ff, #b47c02ff);
            transform: scale(1.05);
             color: #fff;

        }
        /* Pagination */
        .pagination {
            margin-top: 40px;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            margin: 0 5px;
            padding: 8px 14px;
            background: black; /* μη-active */
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }

        .pagination a:hover {
            background: #333; /* hover για μη-active */
        }

        .pagination a.active {
            background: #bc8a31; /* κόκκινο για το active */
            color: #fff;
        }


        @media (max-width: 768px) {

    .news-container {
        margin: 100px 10px 30px;
        padding: 0 10px;
    }

    .news-container h2 {
        font-size: 1.8rem;
        margin-bottom: 15px;
    }

    /* Φίλτρο */
    .filter-bar {
        justify-content: center; /* στο κέντρο */
        margin-bottom: 20px;
    }
    .filter-bar select {
        padding: 6px 10px;
        font-size: 0.9rem;
        border-radius: 6px;
    }

    /* Grid */
    .news-grid {
        grid-template-columns: 1fr; /* μία στήλη */
        gap: 20px;
    }

    .news-card img {
        height: 180px;
    }

    .news-content h3 {
        font-size: 1.1rem;
        margin-bottom: 8px;
    }

    .news-content .date {
        font-size: 0.8rem;
        margin-bottom: 10px;
    }

    .read-more {
        padding: 6px 12px;
        font-size: 0.9rem;
        border-radius: 18px;
    }

    /* Pagination */
    .pagination a {
        padding: 6px 10px;
        font-size: 0.85rem;
        margin: 0 3px;
    }
    .pagination a.active {
        font-size: 0.9rem;
    }
}

    </style>
</head>
<body>

<!-- MENU -->
<?php include 'nav.php'; ?>



<main class="news-container">
    <h2>Τελευταία Νέα</h2>

    <!-- Φίλτρο -->
    <div class="filter-bar">
        <form method="get" action="">
            <select name="order" onchange="this.form.submit()">
                <option value="newest" <?= $order == 'newest' ? 'selected' : '' ?>>Νεότερα</option>
                <option value="oldest" <?= $order == 'oldest' ? 'selected' : '' ?>>Παλιότερα</option>
                <option value="popular" <?= $order == 'popular' ? 'selected' : '' ?>>Πιο Δημοφιλή</option>
                <option value="unpopular" <?= $order == 'unpopular' ? 'selected' : '' ?>>Λιγότερο Δημοφιλή</option>
            </select>
        </form>
    </div>

    <!-- Grid -->
    <div class="news-grid">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="news-card">
                    <?php if (!empty($row['image'])): ?>
                        <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                    <?php endif; ?>
                    <div class="news-content">
                        <h3><?= htmlspecialchars($row['title']) ?></h3>
                        <span class="date"><?= date("d/m/Y", strtotime($row['created_at'])) ?></span>
                        <a href="news_detail.php?id=<?= $row['id'] ?>" class="read-more">Διαβάστε περισσότερα</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align:center;">Δεν υπάρχουν νέα αυτή τη στιγμή.</p>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?order=<?= $order ?>&page=<?= $page-1 ?>">« Προηγούμενα</a>
        <?php endif; ?>
        
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <a href="?order=<?= $order ?>&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?order=<?= $order ?>&page=<?= $page+1 ?>">Επόμενα »</a>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.html'; ?>
<script src="navbar.js"></script>
<script>
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
