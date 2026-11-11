<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — TELEGRAM LIVE MESSENGER & BOT RLHF TRAINING API
 * Two-column Live Messenger + Operator Replies + Bot Error Correction
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

    $action = trim((string)($_GET['action'] ?? $input['action'] ?? 'leads_list'));

    // Авто-сид диалогов, если таблица bot_messages пуста
    $messagesCount = (int)$pdo->query("SELECT COUNT(*) FROM bot_messages")->fetchColumn();
    if ($messagesCount === 0) {
        $stmtLead = $pdo->query("SELECT id FROM leads ORDER BY id ASC LIMIT 3");
        $leadIds = $stmtLead->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($leadIds)) {
            $l1 = (int)$leadIds[0];
            $l2 = isset($leadIds[1]) ? (int)$leadIds[1] : $l1;

            $seedDialogs = [
                // Диалог с лидом 1 (СтройГрупп)
                ['lead_id' => $l1, 'sender' => 'user',     'text' => 'Здравствуйте! Увидел ваш пост на VC про скорость сайтов. У нас строительная компания, сайт на Тильде грузится секунд 6 с телефона. Реально сделать за неделю на чистом коде?'],
                ['lead_id' => $l1, 'sender' => 'bot',      'text' => 'Привет! Да, мы разрабатываем сайты на чистом коде под ключ ровно за 5-7 дней без шаблонов и конструкторов. Скорость загрузки будет 100/100 в PageSpeed (до 0.4 секунды на смартфоне). Какой у вас текущий домен для экспресс-аудита?'],
                ['lead_id' => $l1, 'sender' => 'user',     'text' => 'stroigrupp-msk.ru, посмотрите пожалуйста. И какой тариф подойдет?'],
                ['lead_id' => $l1, 'sender' => 'operator', 'text' => 'Александр, добрый день! Проверил ваш сайт: в коде висит 38 лишних скриптов Тильды и неоптимизированные PNG на 14 Мб. Для вашей задачи идеально подойдет тариф 25 000 руб (полный редизайн, верстка на чистом коде, подключение форм и хостинга).'],
                ['lead_id' => $l1, 'sender' => 'user',     'text' => 'Отлично, давайте начинать! Куда отправить реквизиты для договора?'],

                // Диалог с лидом 2 (Клиника)
                ['lead_id' => $l2, 'sender' => 'user',     'text' => 'Добрый вечер. Кодовое слово СКОРОСТЬ'],
                ['lead_id' => $l2, 'sender' => 'bot',      'text' => 'Держите ваш лид-магнит: «10 скрытых признаков того, что сайт на конструкторе сливает рекламный бюджет». Прикрепил PDF-файл. Если хотите, проверим ваш сайт бесплатно!'],
                ['lead_id' => $l2, 'sender' => 'user',     'text' => 'Спасибо! У нас медицинский центр, хотим обновить лендинг косметологии. Сколько стоит разработка?']
            ];

            $stmtInsMsg = $pdo->prepare("
                INSERT INTO bot_messages (lead_id, sender, text, created_at)
                VALUES (:lid, :sender, :text, CURRENT_TIMESTAMP)
            ");

            foreach ($seedDialogs as $msg) {
                $stmtInsMsg->execute([
                    ':lid'    => $msg['lead_id'],
                    ':sender' => $msg['sender'],
                    ':text'   => $msg['text']
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // 1. action = leads_list (Список чатов)
    // -------------------------------------------------------------------------
    if ($action === 'leads_list') {
        $stmt = $pdo->query("
            SELECT l.id, l.full_name, l.telegram_username, l.status, l.budget_range, l.utm_source,
                   (SELECT text FROM bot_messages WHERE lead_id = l.id ORDER BY id DESC LIMIT 1) as last_message,
                   (SELECT created_at FROM bot_messages WHERE lead_id = l.id ORDER BY id DESC LIMIT 1) as last_message_at,
                   (SELECT COUNT(*) FROM bot_messages WHERE lead_id = l.id AND sender = 'user') as user_msg_count
            FROM leads l
            ORDER BY last_message_at DESC NULLS LAST, l.id DESC
        ");
        $leads = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'count'  => count($leads),
            'leads'  => $leads
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. action = messages (История переписки по lead_id)
    // -------------------------------------------------------------------------
    if ($action === 'messages') {
        $leadId = (int)($_GET['lead_id'] ?? $input['lead_id'] ?? 0);
        if ($leadId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан lead_id'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Инфо о лиде
        $stmtLead = $pdo->prepare("SELECT * FROM leads WHERE id = :id");
        $stmtLead->execute([':id' => $leadId]);
        $lead = $stmtLead->fetch();

        // Сообщения
        $stmtMsgs = $pdo->prepare("
            SELECT id, lead_id, sender, text, created_at
            FROM bot_messages
            WHERE lead_id = :id
            ORDER BY id ASC
        ");
        $stmtMsgs->execute([':id' => $leadId]);
        $messages = $stmtMsgs->fetchAll();

        echo json_encode([
            'status'   => 'success',
            'lead'     => $lead,
            'count'    => count($messages),
            'messages' => $messages
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. action = send (Ответ оператора)
    // -------------------------------------------------------------------------
    if ($action === 'send') {
        $leadId = (int)($input['lead_id'] ?? 0);
        $text = trim((string)($input['text'] ?? ''));
        $sender = trim((string)($input['sender'] ?? 'operator'));

        if ($leadId <= 0 || $text === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан lead_id или пустой текст'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO bot_messages (lead_id, sender, text, created_at)
            VALUES (:lid, :sender, :text, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':lid'    => $leadId,
            ':sender' => in_array($sender, ['operator', 'bot'], true) ? $sender : 'operator',
            ':text'   => $text
        ]);

        $newMsgId = (int)$pdo->lastInsertId();

        // Если у лида был статус 'new', переводим в 'qualified'
        $pdo->prepare("UPDATE leads SET status = 'qualified' WHERE id = :id AND status = 'new'")->execute([':id' => $leadId]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Сообщение успешно отправлено в диалог',
            'id'      => $newMsgId
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. action = save_correction (RLHF обучение бота на ошибках)
    // -------------------------------------------------------------------------
    if ($action === 'save_correction') {
        $userPrompt = trim((string)($input['user_prompt'] ?? ''));
        $badResponse = trim((string)($input['bad_bot_response'] ?? ''));
        $correction = trim((string)($input['human_correction'] ?? ''));

        if ($correction === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Введите эталонный правильный ответ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO bot_training_examples (user_prompt, bad_bot_response, human_correction, created_at)
            VALUES (:prompt, :bad, :good, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':prompt' => $userPrompt,
            ':bad'    => $badResponse,
            ':good'   => $correction
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Эталонный ответ сохранен в базу знаний бота (RLHF)'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Неизвестное действие: {$action}"], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
