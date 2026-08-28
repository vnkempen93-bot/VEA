<?php
session_start();

if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

$analyticsFile = "../data/analytics_visits.json";
$visits = [];

if (file_exists($analyticsFile)) {
    $json = file_get_contents($analyticsFile);
    $decoded = json_decode($json, true);

    if (is_array($decoded)) {
        $visits = $decoded;
    }
}

$totalPageviews = count($visits);
$totalVisits = 0;
$uniqueVisitors = [];
$todayDate = date("Y-m-d");
$todayVisits = 0;
$todayPageviews = 0;
$todayUniqueVisitors = [];
$pageCounts = [];
$sourceCounts = [];
$referrerCounts = [];

$dailyVisits = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date("Y-m-d", strtotime("-" . $i . " days"));
    $dailyVisits[$day] = 0;
}

foreach ($visits as $visit) {
    $page = $visit["page"] ?? "onbekend";
    $source = $visit["source"] ?? "Onbekend";
    $referrerHost = $visit["referrer_host"] ?? "Direct";
    $timestamp = $visit["timestamp"] ?? "";
    $visitorId = trim((string)($visit["visitor_id"] ?? ""));
    $legacyIp = trim((string)($visit["ip"] ?? "onbekend"));
    $isVisitStart = array_key_exists("is_visit_start", $visit)
        ? (bool)$visit["is_visit_start"]
        : true;

    if ($visitorId === "") {
        $visitorId = "legacy-ip:" . $legacyIp;
    }

    $uniqueVisitors[$visitorId] = true;

    if (!isset($pageCounts[$page])) {
        $pageCounts[$page] = 0;
    }
    $pageCounts[$page]++;

    if (!isset($sourceCounts[$source])) {
        $sourceCounts[$source] = 0;
    }
    $sourceCounts[$source]++;

    if (!isset($referrerCounts[$referrerHost])) {
        $referrerCounts[$referrerHost] = 0;
    }
    $referrerCounts[$referrerHost]++;

    $visitDate = substr($timestamp, 0, 10);
    if ($visitDate === $todayDate) {
        $todayPageviews++;
        $todayUniqueVisitors[$visitorId] = true;
    }

    if (isset($dailyVisits[$visitDate])) {
        if ($isVisitStart) {
            $dailyVisits[$visitDate]++;
        }
    }

    if ($isVisitStart) {
        $totalVisits++;

        if ($visitDate === $todayDate) {
            $todayVisits++;
        }
    }
}

$totalUniqueVisitors = count($uniqueVisitors);
$todayUniqueVisitorsCount = count($todayUniqueVisitors);

arsort($pageCounts);
arsort($sourceCounts);
arsort($referrerCounts);

$topPages = array_slice($pageCounts, 0, 10, true);
$topSources = array_slice($sourceCounts, 0, 10, true);
$topReferrers = array_slice($referrerCounts, 0, 10, true);
$recentVisits = array_reverse(array_slice($visits, -50));
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Rapporten</title>
<link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="wrapper">
<?php include "includes/sidebar.php"; ?>
<div class="content">
<div class="topbar">
<h1>📊 Website rapporten</h1>
<p>Overzicht van bezoekers, sessies (30 min) en populairste pagina's.</p>
</div>

<div class="stats-grid">
    <div class="card stat-card">
        <h3>Totaal bezoeken (sessies)</h3>
        <p class="stat-number"><?= number_format($totalVisits, 0, ",", ".") ?></p>
    </div>
    <div class="card stat-card">
        <h3>Bezoeken vandaag (sessies)</h3>
        <p class="stat-number"><?= number_format($todayVisits, 0, ",", ".") ?></p>
    </div>
    <div class="card stat-card">
        <h3>Unieke bezoekers totaal</h3>
        <p class="stat-number"><?= number_format($totalUniqueVisitors, 0, ",", ".") ?></p>
    </div>
    <div class="card stat-card">
        <h3>Unieke bezoekers vandaag</h3>
        <p class="stat-number"><?= number_format($todayUniqueVisitorsCount, 0, ",", ".") ?></p>
    </div>
    <div class="card stat-card">
        <h3>Paginaweergaven totaal</h3>
        <p class="stat-number"><?= number_format($totalPageviews, 0, ",", ".") ?></p>
    </div>
    <div class="card stat-card">
        <h3>Paginaweergaven vandaag</h3>
        <p class="stat-number"><?= number_format($todayPageviews, 0, ",", ".") ?></p>
    </div>
</div>

<div class="card">
    <h2>Bezoeken per dag (sessies, laatste 14 dagen)</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Aantal bezoeken</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dailyVisits as $date => $count): ?>
                <tr>
                    <td><?= htmlspecialchars($date) ?></td>
                    <td><?= number_format($count, 0, ",", ".") ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Top bronnen</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bron</th>
                    <th>Bezoeken</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($topSources)): ?>
                <tr><td colspan="2">Nog geen data beschikbaar.</td></tr>
            <?php else: ?>
                <?php foreach ($topSources as $source => $count): ?>
                <tr>
                    <td><?= htmlspecialchars($source) ?></td>
                    <td><?= number_format($count, 0, ",", ".") ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Top verwijzende websites</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Website</th>
                    <th>Bezoeken</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($topReferrers)): ?>
                <tr><td colspan="2">Nog geen data beschikbaar.</td></tr>
            <?php else: ?>
                <?php foreach ($topReferrers as $host => $count): ?>
                <tr>
                    <td><?= htmlspecialchars($host) ?></td>
                    <td><?= number_format($count, 0, ",", ".") ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Top bezochte pagina's</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pagina</th>
                    <th>Weergaven</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($topPages)): ?>
                <tr><td colspan="2">Nog geen data beschikbaar.</td></tr>
            <?php else: ?>
                <?php foreach ($topPages as $page => $count): ?>
                <tr>
                    <td><?= htmlspecialchars($page) ?></td>
                    <td><?= number_format($count, 0, ",", ".") ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Laatste 50 bezoeken</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tijdstip</th>
                    <th>Pagina</th>
                    <th>Nieuwe sessie</th>
                    <th>Bron</th>
                    <th>Verwijzer</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentVisits)): ?>
                <tr><td colspan="6">Nog geen bezoeken geregistreerd.</td></tr>
            <?php else: ?>
                <?php foreach ($recentVisits as $visit): ?>
                <tr>
                    <td><?= htmlspecialchars($visit["timestamp"] ?? "") ?></td>
                    <td><?= htmlspecialchars($visit["page"] ?? "") ?></td>
                    <td><?= !empty($visit["is_visit_start"]) ? "Ja" : "Nee" ?></td>
                    <td><?= htmlspecialchars($visit["source"] ?? "") ?></td>
                    <td><?= htmlspecialchars($visit["referrer_host"] ?? "") ?></td>
                    <td><?= htmlspecialchars($visit["ip"] ?? "") ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
<script src="js/scroll.js"></script>
</body>
</html>
