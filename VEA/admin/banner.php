<?php
session_start();

if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

$file = "../data/banner.json";
$banner = [
    "enabled" => false,
    "text" => ""
];

if (file_exists($file)) {
    $loaded = json_decode(file_get_contents($file), true);

    if (is_array($loaded)) {
        $banner["enabled"] = !empty($loaded["enabled"]);
        $banner["text"] = trim((string)($loaded["text"] ?? ""));
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $text = trim((string)($_POST["banner_text"] ?? ""));
    $enabled = isset($_POST["banner_enabled"]);

    $banner = [
        "enabled" => $enabled && $text !== "",
        "text" => $text
    ];

    file_put_contents(
        $file,
        json_encode($banner, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    header("Location: banner.php?saved=1");
    exit;
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Banner melding beheren</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="wrapper">
    <?php include "includes/sidebar.php"; ?>
    <div class="content">
        <div class="topbar">
            <h1>🚨 Banner melding beheren</h1>
            <p>Stel een mededeling in die onder de website-header wordt getoond.</p>
        </div>

        <div class="card">
            <?php if (isset($_GET["saved"])): ?>
                <p style="margin-bottom:20px;color:#2f7d32;"><strong>Instellingen opgeslagen.</strong></p>
            <?php endif; ?>

            <form method="post">
                <label for="banner_text"><strong>Bannertekst</strong></label><br><br>
                <textarea
                    id="banner_text"
                    name="banner_text"
                    rows="5"
                    style="width:100%;padding:12px;resize:vertical;"><?= htmlspecialchars($banner["text"], ENT_QUOTES, 'UTF-8') ?></textarea>

                <br><br>

                <label style="display:flex;align-items:center;gap:10px;">
                    <input
                        type="checkbox"
                        name="banner_enabled"
                        value="1"
                        <?= !empty($banner["enabled"]) ? "checked" : "" ?>>
                    Banner inschakelen
                </label>

                <p style="margin-top:10px;color:#666;">
                    De banner wordt rood met witte tekst. Laat de tekst leeg om niets te tonen.
                </p>

                <button class="button">Opslaan</button>
            </form>
        </div>
    </div>
</div>
<script src="js/scroll.js"></script>
</body>
</html>
