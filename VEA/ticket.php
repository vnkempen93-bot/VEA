<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Amsterdam');

require __DIR__ . '/includes/portal.php';

$baseDir = __DIR__;
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$ticketsFile = $dataDir . DIRECTORY_SEPARATOR . 'tickets.json';
$messagesFile = vea_ticket_message_path($baseDir);

$currentCustomer = vea_current_customer();
if (!$currentCustomer) {
    header('Location: tickets.php');
    exit;
}

$tickets = vea_read_json($ticketsFile, []);
$messages = vea_load_ticket_messages($messagesFile);
$ticketNumber = trim((string) ($_GET['id'] ?? ''));
$ticket = vea_find_ticket_by_number($tickets, $ticketNumber);

if (!$ticket || (string) ($ticket['customer_id'] ?? '') !== (string) $currentCustomer['id']) {
    http_response_code(404);
    echo 'Ticket niet gevonden.';
    exit;
}

$ticketStatus = (string) ($ticket['status'] ?? 'nieuw');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_reply') {
    if ($ticketStatus === 'gesloten') {
        header('Location: ticket.php?id=' . urlencode($ticketNumber) . '&msg=' . urlencode('Dit ticket is gesloten en kan niet meer worden beantwoord.'));
        exit;
    }

    $reply = trim((string) ($_POST['reply'] ?? ''));
    if ($reply === '') {
        header('Location: ticket.php?id=' . urlencode($ticketNumber) . '&msg=' . urlencode('Vul een reactie in.'));
        exit;
    }

    vea_add_ticket_message(
        $messages,
        $ticketNumber,
        'customer',
        $currentCustomer['name'],
        $currentCustomer['email'],
        $reply
    );

    $ticketIndex = -1;
    foreach ($tickets as $index => $item) {
        if ((string) ($item['ticket_number'] ?? '') === $ticketNumber) {
            $ticketIndex = $index;
            break;
        }
    }

    if ($ticketIndex >= 0) {
        $tickets[$ticketIndex]['status'] = 'in_behandeling';
        vea_write_json($ticketsFile, array_values($tickets));
    }

    vea_save_ticket_messages($messagesFile, $messages);

    $settings = vea_read_json($dataDir . DIRECTORY_SEPARATOR . 'ticket_settings.json', [
        'support_email' => '',
    ]);
    $supportEmail = trim((string) ($settings['support_email'] ?? ''));
    if ($supportEmail !== '') {
        vea_send_mail(
            $supportEmail,
            vea_ticket_reply_mail_subject($ticketNumber, 'customer'),
            vea_ticket_reply_mail_body($ticket, [
                'sender_name' => $currentCustomer['name'],
                'body' => $reply,
                'created_at' => date('Y-m-d H:i'),
            ], 'support'),
            $currentCustomer['email']
        );
    }

    header('Location: ticket.php?id=' . urlencode($ticketNumber) . '&msg=' . urlencode('Uw reactie is toegevoegd.'));
    exit;
}

$ticketMessages = vea_ticket_messages_for($messages, $ticketNumber);

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo vea_h($ticket['ticket_number'] ?? 'Ticket'); ?> | Van Exel Automatisering</title>
    <link rel="stylesheet" href="basis.css">
    <link rel="stylesheet" href="css/portal.css">
</head>
<body>
<div id="site-header"></div>

<main class="portal-wrap">
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="notice success"><?php echo vea_h((string) $_GET['msg']); ?></div>
    <?php endif; ?>
    <section class="portal-card">
        <p><a class="button secondary" href="tickets.php">Terug naar tickets</a></p>
        <h1><?php echo vea_h($ticket['ticket_number'] ?? ''); ?></h1>
        <p class="meta">Status: <strong><?php echo vea_h($ticket['status'] ?? ''); ?></strong></p>
        <p><strong>Onderwerp:</strong> <?php echo vea_h($ticket['subject'] ?? ''); ?></p>
        <p><strong>Categorie:</strong> <?php echo vea_h($ticket['category'] ?? ''); ?></p>
        <p><strong>Aangemaakt:</strong> <?php echo vea_h($ticket['created_at'] ?? ''); ?></p>
        <hr>
        <p><?php echo nl2br(vea_h($ticket['description'] ?? '')); ?></p>
    </section>

    <section class="portal-card">
        <h2>Reacties</h2>
        <div class="thread">
            <?php foreach ($ticketMessages as $message): ?>
                <div class="thread-item <?php echo vea_h((string) ($message['sender_type'] ?? '')); ?>">
                    <div class="thread-meta">
                        <strong><?php echo vea_h($message['sender_name'] ?? ''); ?></strong>
                        <span><?php echo vea_h($message['created_at'] ?? ''); ?></span>
                    </div>
                    <p><?php echo nl2br(vea_h($message['body'] ?? '')); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($ticketStatus !== 'gesloten'): ?>
        <section class="portal-card">
            <h2>Reactie toevoegen</h2>
            <form method="post" class="portal-form">
                <input type="hidden" name="action" value="add_reply">
                <div class="field">
                    <label>Uw reactie</label>
                    <textarea name="reply" rows="5" required></textarea>
                </div>
                <button class="button" type="submit">Versturen</button>
            </form>
        </section>
    <?php else: ?>
        <section class="portal-card">
            <h2>Reactie toevoegen</h2>
            <div class="notice info">Dit ticket is gesloten. U kunt geen reactie meer sturen.</div>
        </section>
    <?php endif; ?>
</main>

<div id="site-footer"></div>
<script src="js/site.js" defer></script>
</body>
</html>
