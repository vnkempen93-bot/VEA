<?php
declare(strict_types=1);

function vea_read_json(string $path, $default)
{
    if (!is_file($path)) {
        return $default;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Kon bestand niet lezen: ' . basename($path));
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Ongeldige JSON in ' . basename($path) . ': ' . json_last_error_msg());
    }

    return $data;
}

function vea_write_json(string $path, $data): void
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Kon JSON niet schrijven.');
    }

    if (file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Kon bestand niet opslaan: ' . basename($path));
    }
}

function vea_ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Kon map niet aanmaken: ' . $dir);
    }
}

function vea_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function vea_email_headers(string $fromEmail, string $replyTo = ''): string
{
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    if ($fromEmail !== '') {
        $headers[] = 'From: Van Exel Automatisering <' . $fromEmail . '>';
    }
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    return implode("\r\n", $headers);
}

function vea_send_mail(string $to, string $subject, string $message, string $replyTo = ''): bool
{
    if ($to === '') {
        return false;
    }

    return mail($to, $subject, $message, vea_email_headers('noreply@vanexelautomatisering.nl', $replyTo));
}

function vea_find_account_by_login(array $accounts, string $login): ?array
{
    $needle = strtolower(trim($login));
    foreach ($accounts as $account) {
        $username = strtolower((string) ($account['username'] ?? ''));
        $email = strtolower((string) ($account['email'] ?? ''));
        if ($needle !== '' && ($needle === $username || $needle === $email)) {
            return $account;
        }
    }

    return null;
}

function vea_find_account_index_by_id(array $accounts, string $id): int
{
    foreach ($accounts as $index => $account) {
        if ((string) ($account['id'] ?? '') === $id) {
            return (int) $index;
        }
    }

    return -1;
}

function vea_ticket_sequence_from_number(string $ticketNumber): int
{
    if (preg_match('/-(\d{4})$/', $ticketNumber, $matches) !== 1) {
        return 0;
    }

    return (int) $matches[1];
}

function vea_next_ticket_number(array $tickets, array &$settings): string
{
    $prefix = trim((string) ($settings['ticket_prefix'] ?? 'VEA'));
    if ($prefix === '') {
        $prefix = 'VEA';
    }

    $currentYear = date('Y');
    $sequence = (int) ($settings['next_ticket_sequence'] ?? 1);
    $maxFound = 0;

    foreach ($tickets as $ticket) {
        $number = (string) ($ticket['ticket_number'] ?? '');
        if (strpos($number, $prefix . '-' . $currentYear . '-') !== 0) {
            continue;
        }
        $maxFound = max($maxFound, vea_ticket_sequence_from_number($number));
    }

    if ($maxFound >= $sequence) {
        $sequence = $maxFound + 1;
    }

    $settings['next_ticket_sequence'] = $sequence + 1;

    return $prefix . '-' . $currentYear . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
}

function vea_current_customer(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if (!isset($_SESSION['customer']) || !is_array($_SESSION['customer'])) {
        return null;
    }

    return $_SESSION['customer'];
}

function vea_find_ticket_by_number(array $tickets, string $ticketNumber): ?array
{
    foreach ($tickets as $ticket) {
        if ((string) ($ticket['ticket_number'] ?? '') === $ticketNumber) {
            return $ticket;
        }
    }

    return null;
}

function vea_ticket_mail_subject(string $ticketNumber, string $kind): string
{
    if ($kind === 'customer') {
        return 'Bevestiging ticket ' . $ticketNumber;
    }

    return 'Nieuw ticket ' . $ticketNumber;
}

function vea_ticket_mail_body(array $ticket, string $kind): string
{
    $ticketNumber = (string) ($ticket['ticket_number'] ?? '');
    $customerName = (string) ($ticket['customer_name'] ?? '');
    $customerEmail = (string) ($ticket['customer_email'] ?? '');
    $subject = (string) ($ticket['subject'] ?? '');
    $category = (string) ($ticket['category'] ?? '');
    $description = (string) ($ticket['description'] ?? '');
    $createdAt = (string) ($ticket['created_at'] ?? '');

    if ($kind === 'customer') {
        return
            "Beste " . $customerName . ",\n\n" .
            "Uw ticket is aangemaakt.\n\n" .
            "Ticketnummer: " . $ticketNumber . "\n" .
            "Onderwerp: " . $subject . "\n" .
            "Categorie: " . $category . "\n" .
            "Datum: " . $createdAt . "\n\n" .
            "Wij nemen zo snel mogelijk contact met u op.\n\n" .
            "Met vriendelijke groet,\nVan Exel Automatisering\n";
    }

    return
        "Nieuw support ticket\n\n" .
        "Ticketnummer: " . $ticketNumber . "\n" .
        "Klant: " . $customerName . "\n" .
        "E-mail: " . $customerEmail . "\n" .
        "Onderwerp: " . $subject . "\n" .
        "Categorie: " . $category . "\n" .
        "Datum: " . $createdAt . "\n\n" .
        "Omschrijving:\n" . $description . "\n";
}

function vea_ticket_message_path(string $baseDir): string
{
    return $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ticket_messages.json';
}

function vea_load_ticket_messages(string $path): array
{
    return vea_read_json($path, []);
}

function vea_save_ticket_messages(string $path, array $messages): void
{
    vea_write_json($path, array_values($messages));
}

function vea_add_ticket_message(array &$messages, string $ticketNumber, string $senderType, string $senderName, string $senderEmail, string $body): array
{
    $message = [
        'id' => uniqid('msg_', true),
        'ticket_number' => $ticketNumber,
        'sender_type' => $senderType,
        'sender_name' => $senderName,
        'sender_email' => $senderEmail,
        'body' => $body,
        'created_at' => date('Y-m-d H:i'),
    ];

    $messages[] = $message;

    return $message;
}

function vea_ticket_messages_for(array $messages, string $ticketNumber): array
{
    $filtered = [];
    foreach ($messages as $message) {
        if ((string) ($message['ticket_number'] ?? '') === $ticketNumber) {
            $filtered[] = $message;
        }
    }

    return $filtered;
}

function vea_ticket_reply_mail_subject(string $ticketNumber, string $senderType): string
{
    return $senderType === 'admin'
        ? 'Reactie op ticket ' . $ticketNumber
        : 'Nieuwe reactie op ticket ' . $ticketNumber;
}

function vea_ticket_reply_mail_body(array $ticket, array $message, string $recipientType): string
{
    $ticketNumber = (string) ($ticket['ticket_number'] ?? '');
    $subject = (string) ($ticket['subject'] ?? '');
    $category = (string) ($ticket['category'] ?? '');
    $senderName = (string) ($message['sender_name'] ?? '');
    $body = (string) ($message['body'] ?? '');
    $createdAt = (string) ($message['created_at'] ?? '');

    if ($recipientType === 'customer') {
        return
            "Beste " . (string) ($ticket['customer_name'] ?? '') . ",\n\n" .
            "Er is een nieuwe reactie op uw ticket.\n\n" .
            "Ticketnummer: " . $ticketNumber . "\n" .
            "Onderwerp: " . $subject . "\n" .
            "Categorie: " . $category . "\n\n" .
            $senderName . " schreef op " . $createdAt . ":\n" .
            $body . "\n\n" .
            "Met vriendelijke groet,\nVan Exel Automatisering\n";
    }

    return
        "Er is een nieuwe reactie op ticket " . $ticketNumber . "\n\n" .
        "Onderwerp: " . $subject . "\n" .
        "Categorie: " . $category . "\n" .
        "Klant: " . (string) ($ticket['customer_name'] ?? '') . "\n\n" .
        $senderName . " schreef op " . $createdAt . ":\n" .
        $body . "\n";
}

function vea_remove_ticket_messages(array &$messages, string $ticketNumber): int
{
    $before = count($messages);
    $messages = array_values(array_filter($messages, function ($message) use ($ticketNumber) {
        return (string) ($message['ticket_number'] ?? '') !== $ticketNumber;
    }));

    return $before - count($messages);
}

function vea_ticket_unread_count(array $ticket, array $messages, string $viewerType = 'admin'): int
{
    $lastSeenKey = $viewerType === 'customer' ? 'customer_last_seen_at' : 'admin_last_seen_at';
    $lastSeen = (string) ($ticket[$lastSeenKey] ?? '');
    $count = 0;
    $firstTicketMessageSkipped = false;

    foreach ($messages as $message) {
        if ((string) ($message['ticket_number'] ?? '') !== (string) ($ticket['ticket_number'] ?? '')) {
            continue;
        }

        if (!$firstTicketMessageSkipped) {
            $firstTicketMessageSkipped = true;
            continue;
        }

        if ($viewerType === 'admin' && (string) ($message['sender_type'] ?? '') !== 'customer') {
            continue;
        }

        if ($viewerType === 'customer' && (string) ($message['sender_type'] ?? '') === 'customer') {
            continue;
        }

        if ($lastSeen === '' || (string) ($message['created_at'] ?? '') > $lastSeen) {
            $count++;
        }
    }

    return $count;
}

function vea_mark_ticket_seen(array &$tickets, string $ticketNumber, string $viewerType = 'admin'): void
{
    $field = $viewerType === 'customer' ? 'customer_last_seen_at' : 'admin_last_seen_at';
    $now = date('Y-m-d H:i');

    foreach ($tickets as $index => $ticket) {
        if ((string) ($ticket['ticket_number'] ?? '') === $ticketNumber) {
            $tickets[$index][$field] = $now;
            break;
        }
    }
}
