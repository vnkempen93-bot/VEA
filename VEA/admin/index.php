<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Amsterdam');

$baseDir = dirname(__DIR__);
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$newsDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'nieuws';
$brandDir = $baseDir . DIRECTORY_SEPARATOR . 'afbeeldingen';

$bannerFile = $dataDir . DIRECTORY_SEPARATOR . 'banner.json';
$newsFile = $dataDir . DIRECTORY_SEPARATOR . 'nieuws.json';
$brandsFile = $dataDir . DIRECTORY_SEPARATOR . 'merken.json';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function load_json(string $path, $default)
{
    if (!is_file($path)) {
        return $default;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Kon JSON-bestand niet lezen: ' . basename($path));
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Ongeldige JSON in ' . basename($path) . ': ' . json_last_error_msg());
    }

    return $decoded;
}

function save_json(string $path, $data): void
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Kon JSON niet serialiseren.');
    }

    if (file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Kon bestand niet opslaan: ' . basename($path));
    }
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Kon map niet aanmaken: ' . $dir);
    }
}

function slug_filename(string $prefix, string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        $ext = '';
    }

    $name = $prefix . bin2hex(random_bytes(8));
    return $ext === '' ? $name : $name . '.' . $ext;
}

function upload_file(string $field, string $targetDir, string $prefix): string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload mislukt voor ' . $field . '.');
    }

    $originalName = (string) $_FILES[$field]['name'];
    $targetName = slug_filename($prefix, $originalName);
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $targetPath)) {
        throw new RuntimeException('Kon upload niet opslaan.');
    }

    return $targetName;
}

function remove_file_if_exists(string $dir, string $filename): void
{
    if ($filename === '') {
        return;
    }

    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path)) {
        unlink($path);
    }
}

function redirect_with_notice(string $type, string $message): void
{
    header('Location: index.php?' . http_build_query([
        $type => $message,
    ]));
    exit;
}

ensure_dir($newsDir);
ensure_dir($brandDir);

$banner = load_json($bannerFile, ['enabled' => false, 'text' => '']);
$newsItems = load_json($newsFile, []);
$brandItems = load_json($brandsFile, []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_banner') {
            $text = trim((string) ($_POST['text'] ?? ''));
            $enabled = isset($_POST['enabled']) && $text !== '';

            save_json($bannerFile, [
                'enabled' => $enabled,
                'text' => $text,
            ]);

            redirect_with_notice('success', 'Banner opgeslagen.');
        }

        if ($action === 'save_news') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $title = trim((string) ($_POST['titel'] ?? ''));
            $date = trim((string) ($_POST['datum'] ?? ''));
            $text = trim((string) ($_POST['tekst'] ?? ''));
            $existingImage = trim((string) ($_POST['existing_image'] ?? ''));

            if ($title === '' || $date === '' || $text === '') {
                throw new RuntimeException('Vul titel, datum en tekst in.');
            }

            $uploadedImage = upload_file('image', $newsDir, 'nieuws_');
            $image = $uploadedImage !== '' ? $uploadedImage : $existingImage;

            if ($id === '') {
                $id = (string) (time() . random_int(100, 999));
                array_unshift($newsItems, [
                    'id' => $id,
                    'titel' => $title,
                    'tekst' => $text,
                    'datum' => $date,
                    'image' => $image,
                ]);
            } else {
                foreach ($newsItems as $index => $item) {
                    if ((string) ($item['id'] ?? '') === $id) {
                        if ($uploadedImage !== '' && !empty($item['image'])) {
                            remove_file_if_exists($newsDir, (string) $item['image']);
                        }

                        $newsItems[$index] = [
                            'id' => $id,
                            'titel' => $title,
                            'tekst' => $text,
                            'datum' => $date,
                            'image' => $image,
                        ];
                        break;
                    }
                }
            }

            save_json($newsFile, array_values($newsItems));
            redirect_with_notice('success', 'Nieuws opgeslagen.');
        }

        if ($action === 'delete_news') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $newItems = [];

            foreach ($newsItems as $item) {
                if ((string) ($item['id'] ?? '') === $id) {
                    remove_file_if_exists($newsDir, (string) ($item['image'] ?? ''));
                    continue;
                }
                $newItems[] = $item;
            }

            save_json($newsFile, array_values($newItems));
            redirect_with_notice('success', 'Nieuws verwijderd.');
        }

        if ($action === 'save_brand') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $name = trim((string) ($_POST['naam'] ?? ''));
            $existingFile = trim((string) ($_POST['existing_file'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('Vul een merknaam in.');
            }

            $uploadedFile = upload_file('bestand', $brandDir, 'merk_');
            $file = $uploadedFile !== '' ? $uploadedFile : $existingFile;

            if ($file === '') {
                throw new RuntimeException('Kies een logo-bestand.');
            }

            if ($id === '') {
                $id = (string) (time() . random_int(100, 999));
                array_unshift($brandItems, [
                    'id' => $id,
                    'naam' => $name,
                    'bestand' => $file,
                ]);
            } else {
                foreach ($brandItems as $index => $item) {
                    if ((string) ($item['id'] ?? '') === $id) {
                        if ($uploadedFile !== '' && !empty($item['bestand'])) {
                            remove_file_if_exists($brandDir, (string) $item['bestand']);
                        }

                        $brandItems[$index] = [
                            'id' => $id,
                            'naam' => $name,
                            'bestand' => $file,
                        ];
                        break;
                    }
                }
            }

            save_json($brandsFile, array_values($brandItems));
            redirect_with_notice('success', 'Merk opgeslagen.');
        }

        if ($action === 'delete_brand') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $newItems = [];

            foreach ($brandItems as $item) {
                if ((string) ($item['id'] ?? '') === $id) {
                    remove_file_if_exists($brandDir, (string) ($item['bestand'] ?? ''));
                    continue;
                }
                $newItems[] = $item;
            }

            save_json($brandsFile, array_values($newItems));
            redirect_with_notice('success', 'Merk verwijderd.');
        }

        throw new RuntimeException('Onbekende actie.');
    } catch (Throwable $e) {
        redirect_with_notice('error', $e->getMessage());
    }
}

$bannerEnabled = !empty($banner['enabled']);
$bannerText = (string) ($banner['text'] ?? '');
$newsCount = is_array($newsItems) ? count($newsItems) : 0;
$brandCount = is_array($brandItems) ? count($brandItems) : 0;
$success = (string) ($_GET['success'] ?? '');
$error = (string) ($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Van Exel Automatisering</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <h2>CMS Admin</h2>
            <ul>
                <li><a href="#dashboard">Dashboard</a></li>
                <li><a href="#banner">Banner beheren</a></li>
                <li><a href="#nieuws">Nieuws beheren</a></li>
                <li><a href="#merken">Merken beheren</a></li>
                <li><a href="accounts.php">Klanten & ticketinstellingen</a></li>
                <li><a href="tickets.php">Tickets</a></li>
                <li><a href="../homepage.html">Website bekijken</a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="topbar" id="dashboard">
                <h1>Beheeromgeving</h1>
                <p>Hier beheer je de banner, nieuwsberichten en logo's van de website.</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="notice success"><?php echo h($success); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="notice error"><?php echo h($error); ?></div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="card stat-card">
                    <h3>Banner</h3>
                    <div class="stat-number"><?php echo $bannerEnabled ? 'Aan' : 'Uit'; ?></div>
                    <p><?php echo $bannerText !== '' ? 'Tekst staat klaar om te tonen.' : 'Geen bannertekst ingesteld.'; ?></p>
                </div>
                <div class="card stat-card">
                    <h3>Nieuwsberichten</h3>
                    <div class="stat-number"><?php echo h((string) $newsCount); ?></div>
                    <p>Aantal items in <code>data/nieuws.json</code>.</p>
                </div>
                <div class="card stat-card">
                    <h3>Merken</h3>
                    <div class="stat-number"><?php echo h((string) $brandCount); ?></div>
                    <p>Aantal logo's in de slider.</p>
                </div>
            </section>

            <section class="card admin-section" id="banner">
                <h3>Banner beheren</h3>
                <p>Deze melding wordt bovenaan de website getoond.</p>

                <form method="post" class="admin-form">
                    <input type="hidden" name="action" value="save_banner">

                    <label class="field checkbox-field">
                        <input type="checkbox" name="enabled" value="1"<?php echo $bannerEnabled ? ' checked' : ''; ?>>
                        Banner inschakelen
                    </label>

                    <label class="field">
                        <span>Bannertekst</span>
                        <textarea name="text" rows="4"><?php echo h($bannerText); ?></textarea>
                    </label>

                    <button class="button" type="submit">Opslaan</button>
                </form>
            </section>

            <section class="card admin-section" id="nieuws">
                <h3>Nieuws beheren</h3>
                <p>Voeg nieuws toe, bewerk bestaande berichten of verwijder ze.</p>

                <div class="item-list">
                    <details class="item-create" open>
                        <summary>Nieuw bericht toevoegen</summary>
                        <form method="post" enctype="multipart/form-data" class="admin-form">
                            <input type="hidden" name="action" value="save_news">
                            <input type="hidden" name="id" value="">
                            <input type="hidden" name="existing_image" value="">

                            <label class="field">
                                <span>Titel</span>
                                <input type="text" name="titel" required>
                            </label>

                            <label class="field">
                                <span>Datum</span>
                                <input type="date" name="datum" value="<?php echo h(date('Y-m-d')); ?>" required>
                            </label>

                            <label class="field">
                                <span>Tekst</span>
                                <textarea name="tekst" rows="5" required></textarea>
                            </label>

                            <label class="field">
                                <span>Afbeelding (optioneel)</span>
                                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif">
                            </label>

                            <button class="button" type="submit">Toevoegen</button>
                        </form>
                    </details>

                    <?php foreach ($newsItems as $item): ?>
                        <?php
                        $itemId = (string) ($item['id'] ?? '');
                        $image = (string) ($item['image'] ?? '');
                        ?>
                        <div class="item-card">
                            <div class="item-head">
                                <div>
                                    <h4><?php echo h($item['titel'] ?? ''); ?></h4>
                                    <small><?php echo h($item['datum'] ?? ''); ?></small>
                                </div>
                                <?php if ($image !== ''): ?>
                                    <img class="thumbnail" src="../uploads/nieuws/<?php echo h($image); ?>" alt="">
                                <?php endif; ?>
                            </div>

                            <form method="post" enctype="multipart/form-data" class="admin-form">
                                <input type="hidden" name="action" value="save_news">
                                <input type="hidden" name="id" value="<?php echo h($itemId); ?>">
                                <input type="hidden" name="existing_image" value="<?php echo h($image); ?>">

                                <label class="field">
                                    <span>Titel</span>
                                    <input type="text" name="titel" value="<?php echo h($item['titel'] ?? ''); ?>" required>
                                </label>

                                <label class="field">
                                    <span>Datum</span>
                                    <input type="date" name="datum" value="<?php echo h($item['datum'] ?? ''); ?>" required>
                                </label>

                                <label class="field">
                                    <span>Tekst</span>
                                    <textarea name="tekst" rows="5" required><?php echo h($item['tekst'] ?? ''); ?></textarea>
                                </label>

                                <label class="field">
                                    <span>Nieuwe afbeelding (optioneel)</span>
                                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif">
                                </label>

                                <div class="action-row">
                                    <button class="button" type="submit">Opslaan</button>
                                </div>
                            </form>

                            <form method="post" class="admin-form">
                                <input type="hidden" name="action" value="delete_news">
                                <input type="hidden" name="id" value="<?php echo h($itemId); ?>">
                                <button class="button delete" type="submit" onclick="return confirm('Weet je zeker dat je dit nieuwsbericht wilt verwijderen?');">Verwijderen</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card admin-section" id="merken">
                <h3>Merken beheren</h3>
                <p>Upload hier de logo's die in de slider op de website verschijnen.</p>

                <details class="item-create" open>
                    <summary>Nieuw merk toevoegen</summary>
                    <form method="post" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="action" value="save_brand">
                        <input type="hidden" name="id" value="">
                        <input type="hidden" name="existing_file" value="">

                        <label class="field">
                            <span>Merknaam</span>
                            <input type="text" name="naam" required>
                        </label>

                        <label class="field">
                            <span>Logo bestand</span>
                            <input type="file" name="bestand" accept=".jpg,.jpeg,.png,.webp,.gif" required>
                        </label>

                        <button class="button" type="submit">Uploaden</button>
                    </form>
                </details>

                <div class="item-list">
                    <?php foreach ($brandItems as $item): ?>
                        <?php
                        $itemId = (string) ($item['id'] ?? '');
                        $file = (string) ($item['bestand'] ?? '');
                        ?>
                        <div class="item-card">
                            <div class="item-head">
                                <div>
                                    <h4><?php echo h($item['naam'] ?? ''); ?></h4>
                                    <small><?php echo h($file); ?></small>
                                </div>
                                <?php if ($file !== ''): ?>
                                    <img class="thumbnail thumbnail-brand" src="../afbeeldingen/<?php echo h($file); ?>" alt="">
                                <?php endif; ?>
                            </div>

                            <form method="post" enctype="multipart/form-data" class="admin-form">
                                <input type="hidden" name="action" value="save_brand">
                                <input type="hidden" name="id" value="<?php echo h($itemId); ?>">
                                <input type="hidden" name="existing_file" value="<?php echo h($file); ?>">

                                <label class="field">
                                    <span>Merknaam</span>
                                    <input type="text" name="naam" value="<?php echo h($item['naam'] ?? ''); ?>" required>
                                </label>

                                <label class="field">
                                    <span>Nieuw logo (optioneel)</span>
                                    <input type="file" name="bestand" accept=".jpg,.jpeg,.png,.webp,.gif">
                                </label>

                                <div class="action-row">
                                    <button class="button" type="submit">Opslaan</button>
                                </div>
                            </form>

                            <form method="post" class="admin-form">
                                <input type="hidden" name="action" value="delete_brand">
                                <input type="hidden" name="id" value="<?php echo h($itemId); ?>">
                                <button class="button delete" type="submit" onclick="return confirm('Weet je zeker dat je dit merk wilt verwijderen?');">Verwijderen</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
