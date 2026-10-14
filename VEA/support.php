<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/includes/portal.php';

$currentCustomer = vea_current_customer();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Portaal | Van Exel Automatisering</title>
    <meta name="description" content="Log in om supporttickets aan te maken voor uw bedrijf.">
    <link rel="stylesheet" href="basis.css">
    <link rel="stylesheet" href="css/portal.css">
</head>
<body>
<div id="site-header"></div>

<main class="portal-wrap">
    <section class="portal-hero">
        <h1>Support portaal</h1>
        <?php if ($currentCustomer): ?>
            <p>Welkom terug, <?php echo vea_h($currentCustomer['name']); ?>. U bent al ingelogd.</p>
            <a class="button" href="tickets.php">Naar mijn tickets</a>
            <a class="button secondary" href="tickets.php?logout=1" style="margin-left:10px;">Uitloggen</a>
        <?php else: ?>
            <p>Log in om uw tickets te bekijken en direct een nieuw ticket aan te maken.</p>
            <a class="button" href="tickets.php">Inloggen</a>
        <?php endif; ?>
    </section>

    <section class="portal-grid">
        <div class="portal-card">
            <h2>Hoe werkt het?</h2>
            <p>Uw bestaande klanten krijgen een account. Na inloggen kunnen zij een ticket insturen en direct een ticketnummer ontvangen.</p>
        </div>
        <div class="portal-card">
            <h2>Ticketnummer</h2>
            <p>Voorbeeld: <strong>VEA-2026-0001</strong></p>
        </div>
    </section>
</main>

<div id="site-footer"></div>
<script src="js/site.js" defer></script>
</body>
</html>
