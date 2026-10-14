<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Amsterdam');

require __DIR__ . '/../includes/portal.php';

$baseDir = dirname(__DIR__);
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$accountsFile = $dataDir . DIRECTORY_SEPARATOR . 'customer_accounts.json';
$ticketsFile = $dataDir . DIRECTORY_SEPARATOR . 'tickets.json';
$settingsFile = $dataDir . DIRECTORY_SEPARATOR . 'ticket_settings.json';
$messagesFile = vea_ticket_message_path($baseDir);

vea_ensure_dir($dataDir);

$tickets = vea_read_json($ticketsFile, []);
$messages = vea_load_ticket_messages($messagesFile);
$settings = vea_read_json($settingsFile, [
    'support_email' => '',
    'ticket_prefix' => 'VEA',
    'next_ticket_sequence' => 1,
]);

$ticketNumber = trim((string) ($_GET['id'] ?? ''));
$ticket = vea_find_ticket_by_number($tickets, $ticketNumber);

if (!$ticket) {
    http_response_code(404);
    echo 'Ticket niet gevonden.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'update_ticket') {
            $status = trim((string) ($_POST['status'] ?? 'nieuw'));
            $allowed = ['nieuw', 'in_behandeling', 'gesloten'];
            if (!in_array($status, $allowed, true)) {
                throw new RuntimeException('Ongeldige ticketstatus.');
            }

            foreach ($tickets as $index => $item) {
                if ((string) ($item['ticket_number'] ?? '') === $ticketNumber) {
                    $tickets[$index]['status'] = $status;
                    break;
                }
            }

            vea_write_json($ticketsFile, array_values($tickets));
            header('Location: ticket.php?id=' . urlencode($ticketNumber) . '&msg=' . urlencode('Ticket bijgewerkt.'));
            exit;
        }

        if ($action === 'add_reply') {
            $reply = trim((string) ($_POST['reply'] ?? ''));
            if ($reply === '') {
                header('Location: ticket.php?id=' . urlencode($ticketNumber) . '&msg=' . urlencode('Vul een reactie in.'));
                exit;
            }

            $ticket = vea_find_ticket_by_number($tickets, $ticketNumber);
            if (!$ticket) {
                throw new RuntimeException('Ticket niet gevonden.');
            }

            vea_add_ticket_message(
                $messages,
                $ticketNumber,
                'admin',
                'Van Exel Automatisering',
                (string) ($settings['support_email'] ?? ''),
                $reply
            );

            foreach ($tickets as $index => $item) {
                if ((string) ($item['ticket_number'] ?? '') === $ticketNumber) {
                    $tickets[$index]['status'] = 'in_behandeling';
                    break;
                }
            }

            vea_write_json($ticketsFile, array_values($tickets));
            vea_save_ticket_messages($messagesFile, $messages);

            $customerEmail = (string) ($ticket['customer_email'] ?? '');
            if ($customerEmail !== '') {
                vea_send_mail(
                    $customerEmail,
                    vea_ticket_reply_mail_subject($ticketNumber, 'admin'),
                    vea_ticket_reply_mail_body($ticket, [
                        'sender_name' => 'Van Exel Automatisering',
                        'body' => $reply,
                        'created_at' => date('Y-m-d H:i'),
                    ], 'customer')
                );
            }

            header('Location: ticket.php?id=' . urlencode($ticketNumber) . '&msg=' . urlencode('Reactie opgeslagen.'));
            exit;
        }

        if ($action === 'delete_ticket') {
            $tickets = array_values(array_filter($tickets, function ($item) use ($ticketNumber) {
                return (string) ($item['ticket_number'] ?? '') !== $ticketNumber;
            }));
            vea_remove_ticket_messages($messages, $ticketNumber);
            vea_write_json($ticketsFile, $tickets);
            vea_save_ticket_messages($messagesFile, $messages);
            header('Location: tickets.php?success=' . urlencode('Ticket verwijderd.'));
            exit;
        }
    } catch (Throwable $e) {
        header('Location: ticket.php?id=' . urlencode($ticketNumber) . '&msg=' . urlencode($e->getMessage()));
        exit;
    }
}

vea_mark_ticket_seen($tickets, $ticketNumber, 'admin');
vea_write_json($ticketsFile, array_values($tickets));
$ticket = vea_find_ticket_by_number($tickets, $ticketNumber);
$ticketMessages = vea_ticket_messages_for($messages, $ticketNumber);
$unreadCount = vea_ticket_unread_count($ticket, $messages, 'admin');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo vea_h($ticket['ticket_number'] ?? 'Ticket'); ?> | Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/portal.css">
</head>
<body>
<div class="wrapper">
    <aside class="sidebar">
        <h2>CMS Admin</h2>
    </aside>

    <main class="content">
        <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
            <div class="notice success"><?php echo vea_h((string) $_GET['msg']); ?></div>
        <?php endif; ?>

        <nav class="ticket-nav" aria-label="Navigatie">
            <a class="button secondary" href="tickets.php">Terug naar tickets</a>
            <a class="button secondary" href="index.php">Dashboard</a>
        </nav>

        <section class="portal-card ticket-chat-card">
            <div class="ticket-chat-head">
                <div>
                    <h1><?php echo vea_h($ticket['ticket_number'] ?? ''); ?></h1>
                    <p class="meta"><?php echo vea_h($ticket['customer_name'] ?? ''); ?> • <?php echo vea_h($ticket['customer_email'] ?? ''); ?></p>
                </div>
                <?php
                $status = (string) ($ticket['status'] ?? 'nieuw');
                $badgeClass = $status === 'in_behandeling' ? 'progress' : ($status === 'gesloten' ? 'done' : 'new');
                ?>
                <span class="badge <?php echo vea_h($badgeClass); ?>"><?php echo vea_h($status); ?></span>
            </div>

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

            <form method="post" class="portal-form ticket-reply-form">
                <input type="hidden" name="action" value="add_reply">
                <div class="field">
                    <label>Reactie toevoegen</label>
                    <textarea name="reply" rows="5" required></textarea>
                </div>
                <button class="button" type="submit">Versturen</button>
            </form>
        </section>

        <section class="portal-card ticket-details-card">
            <h2>Ticketinformatie</h2>
            <p><strong>Onderwerp:</strong> <?php echo vea_h($ticket['subject'] ?? ''); ?></p>
            <p><strong>Categorie:</strong> <?php echo vea_h($ticket['category'] ?? ''); ?></p>
            <p><strong>Aangemaakt:</strong> <?php echo vea_h($ticket['created_at'] ?? ''); ?></p>
            <p><strong>Nieuwe reacties:</strong> <?php echo vea_h((string) $unreadCount); ?></p>
            <p><?php echo nl2br(vea_h($ticket['description'] ?? '')); ?></p>

            <form method="post" class="admin-form ticket-status-form">
                <input type="hidden" name="action" value="update_ticket">
                <label class="field">
                    <span>Status</span>
                    <select name="status">
                        <option value="nieuw"<?php echo $status === 'nieuw' ? ' selected' : ''; ?>>Nieuw</option>
                        <option value="in_behandeling"<?php echo $status === 'in_behandeling' ? ' selected' : ''; ?>>In behandeling</option>
                        <option value="gesloten"<?php echo $status === 'gesloten' ? ' selected' : ''; ?>>Gesloten</option>
                    </select>
                </label>
                <button class="button" type="submit">Opslaan</button>
            </form>

            <form method="post" class="admin-form" onsubmit="return confirm('Dit ticket verwijderen?');">
                <input type="hidden" name="action" value="delete_ticket">
                <button class="button danger" type="submit">Ticket verwijderen</button>
            </form>
        </section>
    </main>
</div>
</body>
</html>
