<?php
session_start();

if(!isset($_SESSION["loggedin"])){
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>

<html lang="nl">

<head>

<meta charset="UTF-8">

<title>Dashboard</title>

<link rel="stylesheet" href="css/admin.css">

</head>

<body>

<div class="wrapper">

<?php include "includes/sidebar.php"; ?>

<div class="content">

<div class="topbar">

<h1>Welkom in het CMS</h1>

<p>Beheer hier de website van Van Exel Automatisering.</p>

</div>

<div class="cards">

<div class="card">

<h3>📰 Nieuws</h3>

<p>Nieuwsberichten toevoegen en bewerken.</p>

<a class="button" href="nieuws.php">Open</a>

</div>

<div class="card">

<h3>🖼️ Merken</h3>

<p>Logo's beheren voor de slider.</p>

<a class="button" href="merken.php">Open</a>

</div>

<div class="card">

<h3>📷 Media</h3>

<p>Alle afbeeldingen beheren.</p>

<a class="button" href="media.php">Open</a>

</div>

<div class="card">

<h3>📊 Rapporten</h3>

<p>Bezoekers, bronnen en paginaweergaven bekijken.</p>

<a class="button" href="rapporten.php">Open</a>

</div>

<div class="card">

<h3>🚨 Banner melding</h3>

<p>Toon of verberg een mededelingenbanner onder de header.</p>

<a class="button" href="banner.php">Open</a>

</div>

</div>

</div>

</div>
<script src="js/scroll.js"></script>
</body>

</html>