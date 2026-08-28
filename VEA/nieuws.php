
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
    <title>ICT Nieuws en Updates | Van Exel Automatisering</title>
    <meta name="description" content="Lees het laatste ICT-nieuws en updates van Van Exel Automatisering over beheer, beveiliging, cloud en bedrijfscontinuïteit.">
    <link rel="canonical" href="https://www.vanexelautomatisering.nl/nieuws.php">
    <link href="https://fonts.googleapis.com" rel="stylesheet">
    <link rel="stylesheet" href="basis.css">
</head>
<body>

<?php include 'includes/header.php'; ?>



<header class="hero">
    <h1>Nieuws</h1>
    <p>Hier houden wij u op de hoogte van al het nieuws over onze diensten.</p>
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
