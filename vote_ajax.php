<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Πρέπει να συνδεθείς για να ψηφίσεις."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $vote = $_POST['vote'];
    $user_id = $_SESSION['user_id'];

    // Έλεγχος αν έχει ήδη ψηφίσει
    $stmt = $conn->prepare("SELECT vote FROM vote_usage WHERE user_id = ? AND news_id = ?");
    $stmt->bind_param("ii", $user_id, $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $currentVote = $row['vote'];

        if ($currentVote === $vote) {
            // Αν πάτησε το ίδιο => ακυρώνεται
            $conn->query("DELETE FROM vote_usage WHERE user_id = $user_id AND news_id = $id");

            if ($vote === "like") {
                $conn->query("UPDATE news SET likes = likes - 1 WHERE id = $id AND likes > 0");
            } else {
                $conn->query("UPDATE news SET dislikes = dislikes - 1 WHERE id = $id AND dislikes > 0");
            }

            $res2 = $conn->query("SELECT likes, dislikes FROM news WHERE id = $id");
            $row2 = $res2->fetch_assoc();

            echo json_encode([
                "success" => true,
                "action" => "removed",
                "likes" => $row2['likes'],
                "dislikes" => $row2['dislikes']
            ]);
            exit;
        } else {
            // Αν πάτησε διαφορετικό => αλλαγή ψήφου
            $conn->query("UPDATE vote_usage SET vote = '$vote' WHERE user_id = $user_id AND news_id = $id");

            if ($vote === "like") {
                $conn->query("UPDATE news SET likes = likes + 1, dislikes = dislikes - 1 WHERE id = $id AND dislikes > 0");
            } else {
                $conn->query("UPDATE news SET dislikes = dislikes + 1, likes = likes - 1 WHERE id = $id AND likes > 0");
            }

            $res2 = $conn->query("SELECT likes, dislikes FROM news WHERE id = $id");
            $row2 = $res2->fetch_assoc();

            echo json_encode([
                "success" => true,
                "action" => "changed",
                "likes" => $row2['likes'],
                "dislikes" => $row2['dislikes'],
                "userVote" => $vote
            ]);
            exit;
        }
    } else {
        // Αν δεν έχει κάνει ψήφο => νέα ψήφος
        $stmt = $conn->prepare("INSERT INTO vote_usage (user_id, news_id, vote) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $id, $vote);
        $stmt->execute();

        if ($vote === "like") {
            $conn->query("UPDATE news SET likes = likes + 1 WHERE id = $id");
        } else {
            $conn->query("UPDATE news SET dislikes = dislikes + 1 WHERE id = $id");
        }

        $res2 = $conn->query("SELECT likes, dislikes FROM news WHERE id = $id");
        $row2 = $res2->fetch_assoc();

        echo json_encode([
            "success" => true,
            "action" => "added",
            "likes" => $row2['likes'],
            "dislikes" => $row2['dislikes'],
            "userVote" => $vote
        ]);
        exit;
    }
}

echo json_encode(["success" => false]);
