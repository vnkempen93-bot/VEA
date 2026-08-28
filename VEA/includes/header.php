<?php
$analyticsFile = __DIR__ . '/../data/analytics_visits.json';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = (string)parse_url($requestUri, PHP_URL_PATH);
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$referrerHost = $referrer !== '' ? (string)parse_url($referrer, PHP_URL_HOST) : '';
$utmSource = trim((string)($_GET['utm_source'] ?? ''));
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'onbekend';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$cookieVisitorId = isset($_COOKIE['vea_visitor_id']) ? trim((string)$_COOKIE['vea_visitor_id']) : '';

if ($utmSource !== '') {
    $source = 'UTM: ' . $utmSource;
} elseif ($referrerHost === '') {
    $source = 'Direct';
} else {
    $referrerHostLower = strtolower($referrerHost);

    if (strpos($referrerHostLower, 'google.') !== false) {
        $source = 'Google';
    } elseif (strpos($referrerHostLower, 'bing.') !== false) {
        $source = 'Bing';
    } elseif (strpos($referrerHostLower, 'yahoo.') !== false) {
        $source = 'Yahoo';
    } elseif (strpos($referrerHostLower, 'duckduckgo.') !== false) {
        $source = 'DuckDuckGo';
    } elseif (strpos($referrerHostLower, 'facebook.') !== false) {
        $source = 'Facebook';
    } elseif (strpos($referrerHostLower, 'instagram.') !== false) {
        $source = 'Instagram';
    } elseif (strpos($referrerHostLower, 'linkedin.') !== false) {
        $source = 'LinkedIn';
    } else {
        $source = 'Referral: ' . $referrerHost;
    }
}

$visitorId = '';

if ($cookieVisitorId !== '') {
    $visitorId = $cookieVisitorId;
} elseif (!headers_sent()) {
    $visitorId = bin2hex(random_bytes(16));
    setcookie(
        'vea_visitor_id',
        $visitorId,
        time() + (86400 * 365),
        '/',
        '',
        false,
        true
    );
} else {
    $visitorId = hash('sha256', strtolower($ipAddress) . '|' . $userAgent);
}

$entry = [
    'timestamp' => date('c'),
    'page' => $requestPath !== '' ? $requestPath : 'onbekend',
    'source' => $source,
    'referrer' => $referrer,
    'referrer_host' => $referrerHost !== '' ? $referrerHost : 'Direct',
    'ip' => $ipAddress,
    'visitor_id' => $visitorId,
    'user_agent' => $userAgent
];

$visits = [];

if (file_exists($analyticsFile)) {
    $analyticsJson = file_get_contents($analyticsFile);
    $decodedVisits = json_decode($analyticsJson, true);

    if (is_array($decodedVisits)) {
        $visits = $decodedVisits;
    }
}

$lastVisitTimestamp = null;

for ($i = count($visits) - 1; $i >= 0; $i--) {
    $loggedVisitorId = $visits[$i]['visitor_id'] ?? '';

    if ($loggedVisitorId === $visitorId) {
        $lastVisitTimestamp = $visits[$i]['timestamp'] ?? null;
        break;
    }
}

$isVisitStart = true;

if ($lastVisitTimestamp !== null) {
    $lastVisitUnix = strtotime((string)$lastVisitTimestamp);
    $currentVisitUnix = time();

    if ($lastVisitUnix !== false && ($currentVisitUnix - $lastVisitUnix) <= 1800) {
        $isVisitStart = false;
    }
}

$entry['is_visit_start'] = $isVisitStart;
$visits[] = $entry;

if (count($visits) > 20000) {
    $visits = array_slice($visits, -20000);
}

file_put_contents(
    $analyticsFile,
    json_encode($visits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

$bannerFile = __DIR__ . '/../data/banner.json';
$bannerEnabled = false;
$bannerText = '';

if (file_exists($bannerFile)) {
    $bannerJson = file_get_contents($bannerFile);
    $bannerData = json_decode($bannerJson, true);

    if (is_array($bannerData)) {
        $bannerEnabled = !empty($bannerData['enabled']);
        $bannerText = trim((string)($bannerData['text'] ?? ''));
    }
}

if ($bannerText === '') {
    $bannerEnabled = false;
}
?>
<nav>
    <div class="logo">
        <img src="/VEA/VEA logo.jpg" height="60" alt="Van Exel Automatisering">
    </div>

    <ul>
        <li><a href="/VEA/homepage.php">Home</a></li>
        <li><a href="/VEA/diensten.php">Diensten</a></li>
        <li><a href="/VEA/support.php">Support</a></li>
        <li><a href="/VEA/nieuws.php">Nieuws</a></li>
        <li><a href="/VEA/contact.php">Contact</a></li>
    </ul>
</nav>
<?php if ($bannerEnabled): ?>
    <div
        class="site-banner"
        style="width:100%;background:#c62828;color:#ffffff;text-align:center;padding:12px 20px;font-weight:600;">
        <?= nl2br(htmlspecialchars($bannerText, ENT_QUOTES, 'UTF-8')) ?>
    </div>
<?php endif; ?>