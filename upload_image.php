<?php
// upload_image.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = uniqid('img_') . '.' . $ext;
    $target = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        // Επιστρέφουμε πλήρες URL
        $protocol = (!empty($_SERVER['HTTPS'])) ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        echo "$protocol://$host$path/$target";
    } else {
        http_response_code(500);
        echo "Upload failed";
    }
} else {
    http_response_code(400);
    echo "No file uploaded";
}
