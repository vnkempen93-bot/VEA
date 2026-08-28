<?php
session_start();

if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

$uploadDir = "../afbeeldingen/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* Upload */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!empty($_FILES["image"]["name"])) {

        $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));

        $allowed = ["jpg","jpeg","png","gif","webp","svg"];

        if (in_array($ext, $allowed)) {

            $newName = uniqid() . "." . $ext;

            move_uploaded_file(
                $_FILES["image"]["tmp_name"],
                $uploadDir . $newName
            );
        }
    }

    header("Location: media.php");
    exit;
}

/* Verwijderen */

if (isset($_GET["delete"])) {

    $file = basename($_GET["delete"]);

    $path = $uploadDir . $file;

    if (file_exists($path)) {
        unlink($path);
    }

    header("Location: media.php");
    exit;
}

$images = array_diff(scandir($uploadDir), [".", ".."]);
?>

<!doctype html>
<html lang="nl">
<head>

<meta charset="utf-8">

<title>Media</title>

<link rel="stylesheet" href="css/admin.css">

</head>

<body>

<div class="wrapper">

<?php include "includes/sidebar.php"; ?>

<div class="content">

<div class="topbar">

<h1>Media Bibliotheek</h1>

<p>Upload afbeeldingen voor de website.</p>

</div>

<div class="card">

<form method="post" enctype="multipart/form-data">

<input type="file" name="image" required>

<button class="button">Uploaden</button>

</form>

</div>

<br>

<div class="cards">

<?php foreach($images as $image): ?>

<div class="card" style="text-align:center;">

<img
src="../afbeeldingen/<?= htmlspecialchars($image) ?>"
style="width:100%;height:180px;object-fit:contain;">

<br><br>

<strong><?= htmlspecialchars($image) ?></strong>

<br><br>

<a
class="button"
href="?delete=<?= urlencode($image) ?>"
onclick="return confirm('Afbeelding verwijderen?')">

Verwijderen

</a>

</div>

<?php endforeach; ?>

</div>

</div>

</div>
<script src="js/scroll.js"></script>
</body>
</html>