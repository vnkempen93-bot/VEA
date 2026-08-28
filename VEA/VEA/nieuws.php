
<?php
$file = __DIR__ . "/data/nieuws.json";

$data = json_decode(file_get_contents($file), true);

if (!is_array($data)) {
    $data = [];
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Van Exel Automatisering | Uw IT-Partner</title>
    <link href="https://fonts.googleapis.com" rel="stylesheet">
    <link rel="stylesheet" href="basis.css">
</head>
<body>

<?php include 'includes/header.php'; ?>



<header class="hero">
    <h1>Uw partner in zorgeloze IT</h1>
    <p>Wij maken complexe techniek begrijpelijk en zorgen dat uw bedrijf altijd online is.</p>
</header>

<section class="news">
    <h2>Nieuws</h2>

    <?php if (!empty($data)): ?>
<?php foreach ($data as $item): ?>
   

<div class="service-card news-card">

    <?php if (!empty($item["image"])): ?>
        <img src="uploads/nieuws/<?= htmlspecialchars($item["image"]) ?>" alt="">
    <?php endif; ?>

    <div class="news-content">
        <h3><?= htmlspecialchars($item["titel"] ?? "") ?></h3>
        <small><?= htmlspecialchars($item["datum"] ?? "") ?></small>
        <p><?= nl2br(htmlspecialchars($item["tekst"] ?? "")) ?></p>
    </div>

</div>

    </div>
<?php endforeach; ?>

    <?php else: ?>
        <p>Er zijn nog geen nieuwsberichten.</p>
    <?php endif; ?>
</section>

<section id="diensten" class="services">
    <div class="service-card">
        <h3>Cloud Oplossingen</h3>
        <p>Altijd en overal veilig toegang tot uw bedrijfsgegevens en applicaties.</p>
    </div>
    <div class="service-card">
        <h3>Hosted Telefonie</h3>
        <p>Flexibele en professionele bereikbaarheid via de modernste VoIP techniek.</p>
    </div>
    <div class="service-card">
        <h3>Veilig Werken</h3>
        <p>Optimale bescherming van uw data en netwerk tegen moderne dreigingen.</p>
    </div>
    <div class="service-card">
        <h3>Business Continuïteit</h3>
        <p>Slimme back-up en herstelplannen zodat uw werk nooit stil komt te liggen.</p>
    </div>
</section>
 <?php include 'includes/logo-slider.php'; ?>
<footer><?php include 'includes/footer.php'; ?></footer>

</body>
</html>

