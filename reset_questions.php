<?php
require "config.php";

$userId = $_SESSION['user_id'] ?? 1;

if (!isset($_POST['category_id'])) die("Λείπει κατηγορία");

$categoryId = (int)$_POST['category_id'];

// Διαγραφή ιστορικού ερωτήσεων για την κατηγορία
$stmt = $pdo->prepare("
    DELETE uq FROM user_questions uq
    INNER JOIN questions q ON uq.question_id = q.id
    WHERE uq.user_id = :uid AND q.category_id = :cid
");
$stmt->execute([':uid'=>$userId, ':cid'=>$categoryId]);

header("Location: quiz.php?cat=".$categoryId);
exit;
?>
