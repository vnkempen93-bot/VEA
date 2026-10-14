<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Amsterdam');

require __DIR__ . '/../includes/portal.php';

$baseDir = dirname(__DIR__);
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$accountsFile = $dataDir . DIRECTORY_SEPARATOR . 'customer_accounts.json';
$settingsFile = $dataDir . DIRECTORY_SEPARATOR . 'ticket_settings.json';

vea_ensure_dir($dataDir);

$accounts = vea_read_json($accountsFile, []);
$settings = vea_read_json($settingsFile, [
    'support_email' => '',
    'ticket_prefix' => 'VEA',
    'next_ticket_sequence' => 1,
]);

$success = '';
$error = '';

function admin_redirect(string $type, string $message): void
{
    header('Location: accounts.php?' . http_build_query([$type => $message]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_settings') {
            $settings['support_email'] = trim((string) ($_POST['support_email'] ?? ''));
            $settings['ticket_prefix'] = trim((string) ($_POST['ticket_prefix'] ?? 'VEA')) ?: 'VEA';
            vea_write_json($settingsFile, $settings);
            admin_redirect('success', 'Ticketinstellingen opgeslagen.');
        }

        if ($action === 'save_account') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $active = isset($_POST['active']);

            if ($name === '' || $username === '' || $email === '') {
                throw new RuntimeException('Vul naam, gebruikersnaam en e-mail in.');
            }

            if ($id === '' && $password === '') {
                throw new RuntimeException('Kies een wachtwoord voor een nieuw account.');
            }

            foreach ($accounts as $existing) {
                if ((string) ($existing['id'] ?? '') === $id) {
                    continue;
                }
                if (strtolower((string) ($existing['username'] ?? '')) === strtolower($username)) {
                    throw new RuntimeException('Deze gebruikersnaam bestaat al.');
                }
                if (strtolower((string) ($existing['email'] ?? '')) === strtolower($email)) {
                    throw new RuntimeException('Dit e-mailadres bestaat al.');
                }
            }

            if ($id === '') {
                $accounts[] = [
                    'id' => uniqid('cust_', true),
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'active' => $active,
                    'created_at' => date('Y-m-d H:i'),
                ];
            } else {
                $index = vea_find_account_index_by_id($accounts, $id);
                if ($index < 0) {
                    throw new RuntimeException('Account niet gevonden.');
                }

                $account = $accounts[$index];
                $accounts[$index] = [
                    'id' => $id,
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : (string) ($account['password_hash'] ?? ''),
                    'active' => $active,
                    'created_at' => (string) ($account['created_at'] ?? date('Y-m-d H:i')),
                ];
            }

            vea_write_json($accountsFile, array_values($accounts));
            admin_redirect('success', 'Klantaccount opgeslagen.');
        }

        if ($action === 'delete_account') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $accounts = array_values(array_filter($accounts, function ($account) use ($id) {
                return (string) ($account['id'] ?? '') !== $id;
            }));
            vea_write_json($accountsFile, $accounts);
            admin_redirect('success', 'Klantaccount verwijderd.');
        }

        throw new RuntimeException('Onbekende actie.');
    } catch (Throwable $e) {
        admin_redirect('error', $e->getMessage());
    }
}

$success = (string) ($_GET['success'] ?? '');
$error = (string) ($_GET['error'] ?? '');
$supportEmail = (string) ($settings['support_email'] ?? '');
$ticketPrefix = (string) ($settings['ticket_prefix'] ?? 'VEA');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klanten & tickets | Admin</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <h2>CMS Admin</h2>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="index.php#banner">Banner beheren</a></li>
                <li><a href="index.php#nieuws">Nieuws beheren</a></li>
                <li><a href="index.php#merken">Merken beheren</a></li>
                <li><a href="accounts.php">Klanten & ticketinstellingen</a></li>
                <li><a href="tickets.php">Tickets</a></li>
                <li><a href="../homepage.html">Website bekijken</a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="topbar">
                <h1>Klanten & ticketinstellingen</h1>
                <p>Beheer de support-instellingen en klantaccounts.</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="notice success"><?php echo vea_h($success); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="notice error"><?php echo vea_h($error); ?></div>
            <?php endif; ?>

            <section class="portal-card">
                <h2>Ticket instellingen</h2>
                <form method="post" class="admin-form">
                    <input type="hidden" name="action" value="save_settings">
                    <label class="field">
                        <span>Support e-mailadres</span>
                        <input type="email" name="support_email" value="<?php echo vea_h($supportEmail); ?>" placeholder="support@bedrijf.nl">
                    </label>
                    <label class="field">
                        <span>Ticket prefix</span>
                        <input type="text" name="ticket_prefix" value="<?php echo vea_h($ticketPrefix); ?>" placeholder="VEA">
                    </label>
                    <button class="button" type="submit">Opslaan</button>
                </form>
            </section>

            <section class="portal-card">
                <h2>Nieuw klantaccount</h2>
                <form method="post" class="admin-form">
                    <input type="hidden" name="action" value="save_account">
                    <input type="hidden" name="id" value="">
                    <label class="field"><span>Naam</span><input type="text" name="name" required></label>
                    <label class="field"><span>Gebruikersnaam</span><input type="text" name="username" required></label>
                    <label class="field"><span>E-mail</span><input type="email" name="email" required></label>
                    <label class="field"><span>Wachtwoord</span><input type="password" name="password" required></label>
                    <label class="field checkbox-field"><input type="checkbox" name="active" value="1" checked>Actief</label>
                    <button class="button" type="submit">Account aanmaken</button>
                </form>
            </section>

            <section class="portal-card">
                <h2>Bestaande klantaccounts</h2>
                <div class="account-list">
                    <?php foreach ($accounts as $account): ?>
                        <div class="account-item">
                            <form method="post" class="admin-form">
                                <input type="hidden" name="action" value="save_account">
                                <input type="hidden" name="id" value="<?php echo vea_h($account['id'] ?? ''); ?>">
                                <label class="field"><span>Naam</span><input type="text" name="name" value="<?php echo vea_h($account['name'] ?? ''); ?>" required></label>
                                <label class="field"><span>Gebruikersnaam</span><input type="text" name="username" value="<?php echo vea_h($account['username'] ?? ''); ?>" required></label>
                                <label class="field"><span>E-mail</span><input type="email" name="email" value="<?php echo vea_h($account['email'] ?? ''); ?>" required></label>
                                <label class="field"><span>Nieuw wachtwoord (leeg laten om niet te wijzigen)</span><input type="password" name="password"></label>
                                <label class="field checkbox-field"><input type="checkbox" name="active" value="1"<?php echo !empty($account['active']) ? ' checked' : ''; ?>>Actief</label>
                                <button class="button" type="submit">Opslaan</button>
                            </form>
                            <form method="post" class="admin-form" onsubmit="return confirm('Dit account verwijderen?');">
                                <input type="hidden" name="action" value="delete_account">
                                <input type="hidden" name="id" value="<?php echo vea_h($account['id'] ?? ''); ?>">
                                <button class="button danger" type="submit">Verwijderen</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

        </main>
    </div>
</body>
</html>
