<?php
session_start();

if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

$file = "../data/nieuws.json";
$uploadDir = "../uploads/nieuws/";

/* Map aanmaken als deze niet bestaat */
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* JSON laden */
$data = [];

if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        $data = [];
    }
}

/* ===========================
   VERWIJDEREN
=========================== */

if (isset($_GET["delete"])) {

    $deleteId = (int)$_GET["delete"];

    foreach ($data as $index => $item) {

        if (($item["id"] ?? 0) == $deleteId) {

            if (!empty($item["image"])) {

                $img = $uploadDir . $item["image"];

                if (file_exists($img)) {
                    unlink($img);
                }

            }

            unset($data[$index]);
        }
    }

    $data = array_values($data);

    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    header("Location: nieuws.php");
    exit;
}

/* ===========================
   BEWERKEN LADEN
=========================== */

$editItem = null;

if (isset($_GET["edit"])) {

    $editId = (int)$_GET["edit"];

    foreach ($data as $item) {

        if (($item["id"] ?? 0) == $editId) {

            $editItem = $item;
            break;
        }
    }
}

/* ===========================
   OPSLAAN
=========================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id"] ?? "";

    $titel = trim($_POST["titel"] ?? "");
    $tekst = trim($_POST["tekst"] ?? "");

    $datum = date("Y-m-d");

    $imageName = "";

    /* Upload */



    /* ===========================
       UPDATE
    =========================== */

    if (!empty($id)) {

        foreach ($data as &$item) {

            if (($item["id"] ?? 0) == $id) {

                $item["titel"] = $titel;
                $item["tekst"] = $tekst;

                if ($imageName != "") {

                    if (!empty($item["image"])) {

                        $old = $uploadDir . $item["image"];

                        if (file_exists($old)) {
                            unlink($old);
                        }

                    }

                    $item["image"] = $imageName;
                }

                break;
            }
        }

        unset($item);

    } else {

        /* ===========================
           NIEUW BERICHT
        =========================== */

        $ids = array_column($data, "id");

        $newId = empty($ids)
            ? 1
            : max($ids) + 1;

        $data[] = [

            "id" => $newId,

            "titel" => $titel,

            "tekst" => $tekst,

            "datum" => $datum,

            "image" => $imageName

        ];
    }

    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    header("Location: nieuws.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>

    <meta charset="UTF-8">

    <title>Nieuws beheren</title>

    <link rel="stylesheet" href="css/admin.css">

</head>

<body>

<div class="wrapper">

    <?php include "includes/sidebar.php"; ?>

    <div class="content">

        <div class="topbar">

            <h1>📰 Nieuws beheren</h1>

            <p>Voeg nieuwsberichten toe, bewerk ze of verwijder ze.</p>

        </div>

        <!-- FORMULIER -->

        <div class="card">

            <h2 style="margin-bottom:20px;">

                <?= $editItem ? "Nieuwsbericht bewerken" : "Nieuw nieuwsbericht" ?>

            </h2>

            <form method="post" enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="id"
                    value="<?= $editItem["id"] ?? "" ?>">

                <label><strong>Titel</strong></label><br><br>

                <input
                    type="text"
                    name="titel"
                    required
                    value="<?= htmlspecialchars($editItem["titel"] ?? "") ?>"
                    style="width:100%;padding:12px;">

                <br><br>

                <label><strong>Tekst</strong></label><br><br>

                <textarea
                    name="tekst"
                    rows="8"
                    required
                    style="width:100%;padding:12px;"><?= htmlspecialchars($editItem["tekst"] ?? "") ?></textarea>

                <br><br>

                <label><strong>Afbeelding</strong></label><br><br>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.gif,.webp,.svg">

                <?php if (!empty($editItem["image"])): ?>

                    <br><br>

                    <img
                        src="../uploads/nieuws/<?= htmlspecialchars($editItem["image"]) ?>"
                        style="width:220px;border-radius:10px;">

                <?php endif; ?>

                <br><br>

                <button class="button">

                    <?= $editItem ? "💾 Opslaan" : "➕ Nieuws toevoegen" ?>

                </button>

            </form>

        </div>

        <br>

        <h2>Alle nieuwsberichten</h2>

        <br>



<div class="cards">

<?php if (empty($data)): ?>

    <div class="card">
        <p>Er zijn nog STEEDS geen nieuwsberichten.</p>
    </div>

<?php else: ?>

<?php
usort($data, function($a, $b) {
    return $b["id"] <=> $a["id"];
});

 foreach ($data as $item): ?>

<div class="card">

    <div class="nieuws-card">

        <?php if (!empty($item["image"])): ?>

            <div class="nieuws-foto">
                <img src="../uploads/nieuws/<?= htmlspecialchars($item["image"]) ?>" alt="">
            </div>

        <?php endif; ?>

        <div class="nieuws-info">

            <h3><?= htmlspecialchars($item["titel"] ?? "") ?></h3>

            <small>
                <?= htmlspecialchars($item["datum"] ?? "") ?>
            </small>

            <p>
                <?= nl2br(htmlspecialchars($item["tekst"] ?? "")) ?>
            </p>

            <div class="nieuws-buttons">

                <a class="button"
                   href="?edit=<?= $item["id"] ?>">
                    ✏️ Bewerken
                </a>

                <a class="button delete"
                   href="?delete=<?= $item["id"] ?>"
                   onclick="return confirm('Weet je zeker dat je dit nieuwsbericht wilt verwijderen?')">
                    🗑️ Verwijderen
                </a>

            </div>

        </div>

    </div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>
<script src="js/scroll.js"></script>
</body>

</html>