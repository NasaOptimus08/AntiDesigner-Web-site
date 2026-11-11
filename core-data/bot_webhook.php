<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - TELEGRAM BOT WEBHOOK HANDLER
 * Прямой диалог с клиентом от лица независимого разработчика Евгения Антипина
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    $dbPath = __DIR__ . '/database.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    require_once __DIR__ . '/ai_router.php';

    $configFile = __DIR__ . '/config.php';
    $config = file_exists($configFile) ? require $configFile : [];
    $aiRouter = new AIRouter($config);
    $aiRouter->setPDO($pdo);

    $rawInput = file_get_contents('php://input');
    if (!$rawInput) {
        echo json_encode(['status' => 'ok', 'message' => 'AntiDesigner Telegram Webhook active']);
        exit;
    }

    $update = json_decode($rawInput, true);
    if (!is_array($update) || !isset($update['message'])) {
        echo json_encode(['status' => 'ok', 'message' => 'No message in update']);
        exit;
    }

    $message = $update['message'];
    $chatId = $message['chat']['id'] ?? null;
    $from = $message['from'] ?? [];
    $text = trim((string)($message['text'] ?? ''));

    if (!$chatId || $text === '') {
        echo json_encode(['status' => 'ok', 'message' => 'Empty text or chat']);
        exit;
    }

    $tgUserId = (string)($from['id'] ?? $chatId);
    $username = (string)($from['username'] ?? '');
    $firstName = (string)($from['first_name'] ?? '');
    $lastName = (string)($from['last_name'] ?? '');
    $fullName = trim("{$firstName} {$lastName}");
    if ($fullName === '') {
        $fullName = $username ? "@{$username}" : "Клиент {$tgUserId}";
    }

    // Загрузка настроек ассистента
    $cfgStmt = $pdo->query("SELECT * FROM tg_assistant_config ORDER BY id ASC LIMIT 1");
    $cfg = $cfgStmt->fetch() ?: [];
    $blacklist = array_filter(array_map('trim', explode(',', strtolower((string)($cfg['blacklist_phones'] ?? '+79990001122,мама,брат,музыка')))));

    $senderIdentity = strtolower("{$fullName} {$username} {$tgUserId}");
    foreach ($blacklist as $bl) {
        if ($bl !== '' && str_contains($senderIdentity, $bl)) {
            echo json_encode(['status' => 'ignored', 'reason' => 'blacklist']);
            exit;
        }
    }

    // 1. Поиск или создание лида в CRM
    $stmtLead = $pdo->prepare("SELECT id FROM leads WHERE telegram_user_id = :uid LIMIT 1");
    $stmtLead->execute([':uid' => $tgUserId]);
    $leadId = $stmtLead->fetchColumn();

    if (!$leadId) {
        $stmtInsLead = $pdo->prepare("
            INSERT INTO leads (telegram_user_id, telegram_username, full_name, utm_source, status, created_at)
            VALUES (:uid, :uname, :fname, 'tg_bot_webhook', 'new', CURRENT_TIMESTAMP)
        ");
        $stmtInsLead->execute([
            ':uid'   => $tgUserId,
            ':uname' => $username,
            ':fname' => $fullName
        ]);
        $leadId = (int)$pdo->lastInsertId();
    }

    // 2. Запись входящего сообщения от клиента
    $stmtMsg = $pdo->prepare("
        INSERT INTO bot_messages (lead_id, sender, text, created_at)
        VALUES (:lid, 'user', :txt, CURRENT_TIMESTAMP)
    ");
    $stmtMsg->execute([':lid' => $leadId, ':txt' => $text]);

    // 3. Загрузка скилла bot_consultant
    $skill = $aiRouter->loadSkill('bot_consultant');
    $sysPrompt = $skill['system_prompt'] ?? '';

    // 4. Генерация ответа модели Flash-Lite 3.5 напрямую от лица Евгения Антипина
    $userPrompt = "СООБЩЕНИЕ КЛИЕНТА В TELEGRAM: \"{$text}\"\n"
        . "Ответь клиенту НАПРЯМУЮ ОТ СВОЕГО ЛИЦА (Ты - независимый Senior разработчик Евгений Антипин, AntiDesigner). "
        . "КАТЕГОРИЧЕСКИ ЗАПРЕЩЕНО говорить от третьего лица ('я ассистент', 'я бот', 'передам Евгению', 'Евгений разрабатывает'). "
        . "Ты - реальный разработчик. Формат: короткая естественная реплика (1-3 предложения), спокойный уверенный тон с телефона. "
        . "Лендинг на чистом коде делаю за 18 000 - 25 000 руб со скоростью 0.3с, сложные проекты - от 35 000 руб. Срок 3-5 дней. "
        . "Предложи скинуть ссылку на сайт для бесплатного аудита или созвониться на 10 минут. Без длинных тире (—).";

    $botReply = '';
    if ($aiRouter->isFastKeyConfigured()) {
        try {
            $rawReply = $aiRouter->call($userPrompt, $sysPrompt, AIRouter::MODEL_FAST, 0.4);
            $botReply = str_replace(['—', '–'], '-', trim($rawReply));
        } catch (Throwable $e) {
            // Фолбэк при ошибке
        }
    }

    // Проверка на запрещенные маркеры роботов и третьих лиц
    $lowerReply = mb_strtolower($botReply);
    $forbiddenMarkers = ['ассистент', 'я бот', 'передам евгению', 'переведу на евгения', 'евгений свяжется', 'евгений разрабатывает', 'наша команда', 'наша студия'];
    $hasForbidden = false;
    foreach ($forbiddenMarkers as $fm) {
        if (str_contains($lowerReply, $fm)) {
            $hasForbidden = true;
            break;
        }
    }

    if ($botReply === '' || $hasForbidden) {
        $msgLower = mb_strtolower($text);
        if (str_contains($msgLower, 'тариф') || str_contains($msgLower, 'цен') || str_contains($msgLower, 'стоим') || str_contains($msgLower, 'сколько')) {
            $botReply = "Привет! На связи Евгений Антипин (AntiDesigner). Я верстаю сайты на чистом коде без конструкторов со скоростью 100/100 в PageSpeed за 3-5 дней. Лендинг под ключ делаю за 18 000 - 25 000 ₽ со скоростью 0.3с, сложные проекты - от 35 000 ₽. Скиньте ссылку на текущий сайт (если есть) - я гляну скорость и ошибки первого экрана?";
        } elseif (str_contains($msgLower, 'python') || str_contains($msgLower, 'django')) {
            $botReply = "Понял задачу. Я специализируюсь на ультрабыстром фронтенде и легком бэкенде с гарантией 100/100 в PageSpeed. Сейчас как раз за компом - гляну требования к архитектуре и через 10-15 минут напишу точный вердикт, либо давайте созвонимся на 10 минут в TG.";
        } else {
            $botReply = "Привет! На связи Евгений Антипин (AntiDesigner). Я верстаю ультрабыстрые сайты на чистом коде за 3-5 дней напрямую без посредников и медленных конструкторов. Подскажите, вам нужен сайт с нуля или хотите ускорить и пересобрать существующий проект?";
        }
    }

    // 5. Сохранение ответа в переписку CRM
    $stmtBotMsg = $pdo->prepare("
        INSERT INTO bot_messages (lead_id, sender, text, created_at)
        VALUES (:lid, 'bot', :txt, CURRENT_TIMESTAMP)
    ");
    $stmtBotMsg->execute([':lid' => $leadId, ':txt' => $botReply]);

    // Обновляем статус лида
    $pdo->prepare("UPDATE leads SET status = 'qualified' WHERE id = :id AND status = 'new'")->execute([':id' => $leadId]);

    // 6. Отправка ответа в Telegram Bot API (если настроен бот-токен)
    $botToken = $config['telegram_bot_token'] ?? '';
    if ($botToken !== '') {
        $sendUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $sendData = json_encode([
            'chat_id' => $chatId,
            'text'    => $botReply
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $sendUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $sendData,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode([
        'status'  => 'success',
        'lead_id' => $leadId,
        'reply'   => $botReply
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Ошибка обработки вебхука: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
