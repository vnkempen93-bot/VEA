<?php
session_start();

if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

$file = "../data/merken.json";

$data = [];

if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);

    if (!is_array($data)) {
        $data = [];
    }
}

/* ------------------------
   Nieuw merk toevoegen
-------------------------*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $naam = trim($_POST["naam"]);

    if (!empty($_FILES["logo"]["name"])) {

        $ext = strtolower(pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION));

        $nieuwBestand = uniqid() . "." . $ext;

        move_uploaded_file(
            $_FILES["logo"]["tmp_name"],
            "../afbeeldingen/" . $nieuwBestand
        );

        $data[] = [
            "naam" => $naam,
            "bestand" => $nieuwBestand
        ];

        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    header("Location: merken.php");
    exit;
}

/* ------------------------
   Verwijderen
-------------------------*/

if (isset($_GET["delete"])) {

    $id = (int)$_GET["delete"];

    if (isset($data[$id])) {

        $bestand = "../afbeeldingen/" . $data[$id]["bestand"];

        if (file_exists($bestand)) {
            unlink($bestand);
        }

        unset($data[$id]);

        $data = array_values($data);

        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    header("Location: merken.php");
    exit;
}
?>

<!doctype html>

<html lang="nl">

<head>

<meta charset="utf-8">

<title>Merken beheren</title>

<link rel="stylesheet" href="css/admin.css">

</head>

<body>

<div class="wrapper">

<?php include "includes/sidebar.php"; ?>

<div class="content">

<div class="topbar">

<h1>Merken beheren</h1>

<p>Voeg logo's toe voor de slider.</p>

</div>

<div class="card">

<form method="post" enctype="multipart/form-data">

<label>Merknaam</label><br><br>

<input type="text" name="naam" required style="width:100%;padding:12px;">

<br><br>

<label>Logo</label><br><br>

<input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp" required>

<br><br>

<button class="button">Toevoegen</button>

</form>

</div>

<br>

<div class="cards">

<?php foreach($data as $index=>$logo): ?>

<div class="card" style="text-align:center;">

<img
src="../afbeeldingen/<?= htmlspecialchars($logo["bestand"]) ?>"
style="height:70px;max-width:180px;object-fit:contain;">

<h3>

<?= htmlspecialchars($logo["naam"]) ?>

</h3>

<a class="button"
href="?delete=<?= $index ?>"
onclick="return confirm('Logo verwijderen?')">

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