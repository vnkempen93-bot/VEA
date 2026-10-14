<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Amsterdam');

require __DIR__ . '/includes/portal.php';

$baseDir = __DIR__;
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$accountsFile = $dataDir . DIRECTORY_SEPARATOR . 'customer_accounts.json';
$ticketsFile = $dataDir . DIRECTORY_SEPARATOR . 'tickets.json';
$settingsFile = $dataDir . DIRECTORY_SEPARATOR . 'ticket_settings.json';

vea_ensure_dir($dataDir);

$accounts = vea_read_json($accountsFile, []);
$tickets = vea_read_json($ticketsFile, []);
$messagesFile = vea_ticket_message_path($baseDir);
$ticketMessages = vea_load_ticket_messages($messagesFile);
$settings = vea_read_json($settingsFile, [
    'support_email' => '',
    'ticket_prefix' => 'VEA',
    'next_ticket_sequence' => 1,
]);

$message = '';
$messageType = 'info';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: tickets.php?msg=Uitgelogd.');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $account = vea_find_account_by_login($accounts, $login);

    if (!$account || empty($account['active']) || !password_verify($password, (string) ($account['password_hash'] ?? ''))) {
        $message = 'Inloggen mislukt. Controleer uw gegevens.';
        $messageType = 'error';
    } else {
        $_SESSION['customer'] = [
            'id' => $account['id'],
            'name' => $account['name'],
            'username' => $account['username'],
            'email' => $account['email'],
        ];
        header('Location: tickets.php?msg=' . urlencode('Welkom terug, ' . $account['name'] . '.'));
        exit;
    }
}

$currentCustomer = vea_current_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_ticket') {
    if (!$currentCustomer) {
        $message = 'U moet eerst inloggen.';
        $messageType = 'error';
    } else {
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'Algemeen'));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($subject === '' || $description === '') {
            $message = 'Vul onderwerp en omschrijving in.';
            $messageType = 'error';
        } else {
            $ticketNumber = vea_next_ticket_number($tickets, $settings);
            $ticket = [
                'ticket_number' => $ticketNumber,
                'customer_id' => $currentCustomer['id'],
                'customer_name' => $currentCustomer['name'],
                'customer_email' => $currentCustomer['email'],
                'subject' => $subject,
                'category' => $category,
                'description' => $description,
                'status' => 'nieuw',
                'created_at' => date('Y-m-d H:i'),
            ];

            array_unshift($tickets, $ticket);
            vea_add_ticket_message(
                $ticketMessages,
                $ticketNumber,
                'customer',
                $currentCustomer['name'],
                $currentCustomer['email'],
                $description
            );
            vea_write_json($ticketsFile, array_values($tickets));
            vea_save_ticket_messages($messagesFile, $ticketMessages);
            vea_write_json($settingsFile, $settings);

            $supportEmail = trim((string) ($settings['support_email'] ?? ''));
            $supportMailOk = true;
            if ($supportEmail !== '') {
                $supportMailOk = vea_send_mail(
                    $supportEmail,
                    vea_ticket_mail_subject($ticketNumber, 'support'),
                    vea_ticket_mail_body($ticket, 'support'),
                    $currentCustomer['email']
                );
            }

            $customerMailOk = vea_send_mail(
                $currentCustomer['email'],
                vea_ticket_mail_subject($ticketNumber, 'customer'),
                vea_ticket_mail_body($ticket, 'customer')
            );

            $message = 'Ticket aangemaakt: ' . $ticketNumber . '.';
            if ($supportEmail === '') {
                $message .= ' Voeg een support e-mailadres toe in de admin.';
                $messageType = 'error';
            } elseif (!$supportMailOk || !$customerMailOk) {
                $message .= ' De e-mail kon niet volledig worden verstuurd.';
                $messageType = 'error';
            } else {
                $message .= ' Er is ook een bevestigingsmail verstuurd.';
                $messageType = 'success';
            }
        }
    }
}

$currentCustomer = vea_current_customer();
$customerTickets = [];
if ($currentCustomer) {
    foreach ($tickets as $ticket) {
        if ((string) ($ticket['customer_id'] ?? '') === (string) $currentCustomer['id']) {
            $customerTickets[] = $ticket;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = (string) $_GET['msg'];
    $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | Van Exel Automatisering</title>
    <meta name="description" content="Log in om supporttickets aan te maken en te volgen.">
    <link rel="stylesheet" href="basis.css">
    <link rel="stylesheet" href="css/portal.css">
</head>
<body>
<div id="site-header"></div>

<main class="portal-wrap">
    <?php if ($message !== ''): ?>
        <div class="notice <?php echo vea_h($messageType); ?>"><?php echo vea_h($message); ?></div>
    <?php endif; ?>

    <?php if (!$currentCustomer): ?>
        <section class="portal-grid">
            <div class="portal-card">
                <h1>Inloggen voor klanten</h1>
                <p>Gebruik uw account om tickets aan te maken.</p>

                <form method="post" class="portal-form">
                    <input type="hidden" name="action" value="login">
                    <div class="field">
                        <label>Gebruikersnaam of e-mail</label>
                        <input type="text" name="login" required>
                    </div>
                    <div class="field">
                        <label>Wachtwoord</label>
                        <input type="password" name="password" required>
                    </div>
                    <button class="button" type="submit">Inloggen</button>
                </form>
            </div>

            <div class="portal-card">
                <h2>Voor klanten</h2>
                <p>Na inloggen kunt u direct een ticketnummer ontvangen, uw ticket omschrijving invullen en de voortgang volgen.</p>
            </div>
        </section>
    <?php else: ?>
        <section class="portal-card">
            <h1>Welkom, <?php echo vea_h($currentCustomer['name']); ?></h1>
            <p class="meta">U bent ingelogd als <?php echo vea_h($currentCustomer['email']); ?>.</p>
            <p><a class="button secondary" href="tickets.php?logout=1">Uitloggen</a></p>
        </section>

        <section class="portal-grid">
            <div class="portal-card">
                <h2>Nieuw ticket aanmaken</h2>
                <form method="post" class="portal-form">
                    <input type="hidden" name="action" value="create_ticket">
                    <div class="field">
                        <label>Onderwerp</label>
                        <input type="text" name="subject" required>
                    </div>
                    <div class="field">
                        <label>Categorie</label>
                        <select name="category">
                            <option>Algemeen</option>
                            <option>Netwerk</option>
                            <option>Werkplek</option>
                            <option>E-mail</option>
                            <option>Factuur</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Omschrijving</label>
                        <textarea name="description" rows="6" required></textarea>
                    </div>
                    <button class="button" type="submit">Ticket aanmaken</button>
                </form>
            </div>

            <div class="portal-card">
                <h2>Uw tickets</h2>
                <div class="ticket-list">
                    <?php if (!$customerTickets): ?>
                        <p class="meta">U heeft nog geen tickets aangemaakt.</p>
                    <?php endif; ?>
                    <?php foreach ($customerTickets as $ticket): ?>
                        <div class="ticket-item">
                            <div class="ticket-head">
                                <div>
                                    <strong><a href="ticket.php?id=<?php echo urlencode((string) ($ticket['ticket_number'] ?? '')); ?>"><?php echo vea_h($ticket['ticket_number'] ?? ''); ?></a></strong><br>
                                    <span class="meta"><?php echo vea_h($ticket['subject'] ?? ''); ?></span>
                                </div>
                                <?php
                                $status = (string) ($ticket['status'] ?? 'nieuw');
                                $badgeClass = $status === 'in_behandeling' ? 'progress' : ($status === 'gesloten' ? 'done' : 'new');
                                ?>
                                <span class="badge <?php echo vea_h($badgeClass); ?>"><?php echo vea_h($status); ?></span>
                            </div>
                            <div class="meta"><?php echo vea_h($ticket['category'] ?? ''); ?> • <?php echo vea_h($ticket['created_at'] ?? ''); ?></div>
                            <p style="margin-top:10px;"><a class="button secondary" href="ticket.php?id=<?php echo urlencode((string) ($ticket['ticket_number'] ?? '')); ?>">Ticket bekijken</a></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<div id="site-footer"></div>
<script src="js/site.js" defer></script>
</body>
</html>
