<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - TELEGRAM CHAT SYNCHRONIZATION API
 * Receives messages from local / VPS Python client_agent.py and logs to SQLite
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    /** @var PDO $pdo */

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $tgUserId = trim((string)($input['telegram_user_id'] ?? ''));
    $username = trim((string)($input['telegram_username'] ?? ''));
    $fullName = trim((string)($input['full_name'] ?? 'Клиент Telegram'));
    $sender = trim((string)($input['sender'] ?? 'user'));
    $text = trim((string)($input['text'] ?? ''));
    $utm = trim((string)($input['utm_source'] ?? 'tg_direct'));

    if ($text === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Текст сообщения не может быть пустым'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Ищем лида по telegram_user_id или username
    $leadId = 0;
    if ($tgUserId !== '') {
        $stmtFind = $pdo->prepare("SELECT id FROM leads WHERE telegram_user_id = :uid LIMIT 1");
        $stmtFind->execute([':uid' => $tgUserId]);
        $leadId = (int)$stmtFind->fetchColumn();
    }
    if ($leadId === 0 && $username !== '') {
        $cleanUser = ltrim($username, '@');
        $stmtFind = $pdo->prepare("SELECT id FROM leads WHERE telegram_username = :usr LIMIT 1");
        $stmtFind->execute([':usr' => $cleanUser]);
        $leadId = (int)$stmtFind->fetchColumn();
    }

    // Если лид не найден - создаем нового лида
    if ($leadId === 0) {
        $stmtNewLead = $pdo->prepare("
            INSERT INTO leads (telegram_user_id, telegram_username, full_name, utm_source, status, budget_range, created_at)
            VALUES (:uid, :usr, :name, :utm, 'new', '25к-35к', CURRENT_TIMESTAMP)
        ");
        $stmtNewLead->execute([
            ':uid'  => $tgUserId ?: (string)time(),
            ':usr'  => ltrim($username, '@'),
            ':name' => $fullName ?: 'Клиент Telegram',
            ':utm'  => $utm
        ]);
        $leadId = (int)$pdo->lastInsertId();
    }

    // Сохраняем сообщение в bot_messages
    $stmtMsg = $pdo->prepare("
        INSERT INTO bot_messages (lead_id, sender, text, created_at)
        VALUES (:lid, :sender, :text, CURRENT_TIMESTAMP)
    ");
    $stmtMsg->execute([
        ':lid'    => $leadId,
        ':sender' => in_array($sender, ['user', 'bot', 'operator'], true) ? $sender : 'user',
        ':text'   => $text
    ]);
    $msgId = (int)$pdo->lastInsertId();

    echo json_encode([
        'status'     => 'success',
        'message_id' => $msgId,
        'lead_id'    => $leadId
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

