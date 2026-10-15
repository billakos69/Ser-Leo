$id = (int)$_GET['id'];
$res = $mysqli->query("SELECT * FROM teams WHERE id=$id");
$team = $res->fetch_assoc();
echo json_encode($team);
