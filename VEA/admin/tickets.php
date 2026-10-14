<?php
declare(strict_types=1);

require __DIR__ . '/../includes/portal.php';

$baseDir = dirname(__DIR__);
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$ticketsFile = $dataDir . DIRECTORY_SEPARATOR . 'tickets.json';
$messagesFile = vea_ticket_message_path($baseDir);

vea_ensure_dir($dataDir);

$tickets = vea_read_json($ticketsFile, []);
$messages = vea_load_ticket_messages($messagesFile);

function tickets_admin_redirect(string $type, string $message): void
{
    header('Location: tickets.php?' . http_build_query([$type => $message]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'update_ticket') {
            $ticketNumber = trim((string) ($_POST['ticket_number'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'nieuw'));
            $allowed = ['nieuw', 'in_behandeling', 'gesloten'];
            if (!in_array($status, $allowed, true)) {
                throw new RuntimeException('Ongeldige ticketstatus.');
            }

            $updated = false;
            foreach ($tickets as $index => $ticket) {
                if ((string) ($ticket['ticket_number'] ?? '') === $ticketNumber) {
                    $tickets[$index]['status'] = $status;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                throw new RuntimeException('Ticket niet gevonden.');
            }

            vea_write_json($ticketsFile, array_values($tickets));
            tickets_admin_redirect('success', 'Ticketstatus bijgewerkt.');
        }

        if ($action === 'delete_ticket') {
            $ticketNumber = trim((string) ($_POST['ticket_number'] ?? ''));
            $tickets = array_values(array_filter($tickets, function ($ticket) use ($ticketNumber) {
                return (string) ($ticket['ticket_number'] ?? '') !== $ticketNumber;
            }));
            vea_remove_ticket_messages($messages, $ticketNumber);
            vea_write_json($ticketsFile, $tickets);
            vea_save_ticket_messages($messagesFile, $messages);
            tickets_admin_redirect('success', 'Ticket verwijderd.');
        }

        throw new RuntimeException('Onbekende actie.');
    } catch (Throwable $e) {
        tickets_admin_redirect('error', $e->getMessage());
    }
}

$success = (string) ($_GET['success'] ?? '');
$error = (string) ($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="../css/portal.css">
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <h2>CMS Admin</h2>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="accounts.php">Klanten & ticketinstellingen</a></li>
                <li><a href="tickets.php">Tickets</a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="topbar">
                <h1>Tickets</h1>
                <p>Bekijk, open en beheer alle supporttickets.</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="notice success"><?php echo vea_h($success); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="notice error"><?php echo vea_h($error); ?></div>
            <?php endif; ?>

            <section class="portal-card">
                <div class="ticket-grid">
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                        $status = (string) ($ticket['status'] ?? 'nieuw');
                        $badgeClass = $status === 'in_behandeling' ? 'progress' : ($status === 'gesloten' ? 'done' : 'new');
                        $unreadCount = vea_ticket_unread_count($ticket, $messages, 'admin');
                        $ticketThread = vea_ticket_messages_for($messages, (string) ($ticket['ticket_number'] ?? ''));
                        $lastMessage = count($ticketThread) ? $ticketThread[count($ticketThread) - 1] : null;
                        ?>
                        <div class="ticket-tile">
                            <?php if ($unreadCount > 0): ?>
                                <span class="reply-badge"><?php echo vea_h((string) $unreadCount); ?></span>
                            <?php endif; ?>
                            <div class="ticket-head">
                                <div>
                                    <strong><?php echo vea_h($ticket['ticket_number'] ?? ''); ?></strong><br>
                                    <span class="meta"><?php echo vea_h($ticket['customer_name'] ?? ''); ?></span>
                                </div>
                                <span class="badge <?php echo vea_h($badgeClass); ?>"><?php echo vea_h($status); ?></span>
                            </div>
                            <p><strong><?php echo vea_h($ticket['subject'] ?? ''); ?></strong></p>
                            <p class="meta"><?php echo vea_h($ticket['category'] ?? ''); ?> • <?php echo vea_h($ticket['created_at'] ?? ''); ?></p>
                            <?php if ($lastMessage): ?>
                                <p class="ticket-preview"><?php echo vea_h($lastMessage['sender_name'] ?? ''); ?>: <?php echo vea_h(mb_strimwidth((string) ($lastMessage['body'] ?? ''), 0, 90, '...')); ?></p>
                            <?php endif; ?>
                            <div class="ticket-tile-footer">
                                <a class="button" href="ticket.php?id=<?php echo urlencode((string) ($ticket['ticket_number'] ?? '')); ?>">Chat openen</a>
                                <span class="meta">Update of verwijder hieronder</span>
                            </div>

                            <form method="post" class="admin-form ticket-status-form">
                                <input type="hidden" name="action" value="update_ticket">
                                <input type="hidden" name="ticket_number" value="<?php echo vea_h($ticket['ticket_number'] ?? ''); ?>">
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
                                <input type="hidden" name="ticket_number" value="<?php echo vea_h($ticket['ticket_number'] ?? ''); ?>">
                                <button class="button danger" type="submit">Ticket verwijderen</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
