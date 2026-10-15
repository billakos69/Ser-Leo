<?php
session_start();
include 'config.php';
if(!isset($_SESSION['user_id'])) {
  echo json_encode(['success'=>false,'message'=>'not_logged_in']);
  exit;
}

$user_id = $_SESSION['user_id'];
$product_id = (int)$_GET['product_id'];

$q = $conn->prepare("SELECT * FROM favorite WHERE user_id=? AND products_id=?");
$q->bind_param("ii",$user_id,$product_id);
$q->execute();
$res = $q->get_result();

if($res->num_rows>0){
  $conn->query("DELETE FROM favorite WHERE user_id=$user_id AND products_id=$product_id");
  echo json_encode(['success'=>true,'action'=>'removed']);
} else {
  $conn->query("INSERT INTO favorite (user_id, products_id) VALUES ($user_id, $product_id)");
  echo json_encode(['success'=>true,'action'=>'added']);
}
?>
