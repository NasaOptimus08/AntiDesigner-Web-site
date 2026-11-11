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

    // -------------------------------------------------------------------------
    // 5. action = get_config (Конфигурация Telegram-ассистента)
    // -------------------------------------------------------------------------
    if ($action === 'get_config') {
        $stmt = $pdo->query("SELECT * FROM tg_assistant_config ORDER BY id ASC LIMIT 1");
        $config = $stmt->fetch();
        if (!$config) {
            $config = [
                'system_prompt'     => 'Ты — автономный ассистент веб-студии AntiDesigner. Твоя задача — квалифицировать лида, выявить потребность в быстром сайте на чистом коде с гарантией скорости 95-100/100 (без медленных конструкторов вроде Tilda и Wordpress), предложить подходящий тариф (18к-25к, 25к-35к или 35к+) и предложить бесплатный экспресс-аудит текущего сайта.',
                'tone_of_voice'     => 'Деловой, прямой, экспертный, уверенный, без официоза и без шаблонных фраз. Пиши лаконично, задавай один четкий вопрос за раз.',
                'typing_delay_min'  => 3,
                'typing_delay_max'  => 7,
                'blacklist_phones'  => '+79990001122, +79161234567, Мама, Брат, Музыка',
                'api_id'            => '2847192',
                'api_hash'          => '8f7e6d5c4b3a2109fedcba9876543210'
            ];
        }

        echo json_encode([
            'status' => 'success',
            'config' => $config
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 6. action = save_config (Сохранение настроек ассистента)
    // -------------------------------------------------------------------------
    if ($action === 'save_config') {
        $sysPrompt = trim((string)($input['system_prompt'] ?? ''));
        $tone = trim((string)($input['tone_of_voice'] ?? ''));
        $delayMin = max(1, (int)($input['typing_delay_min'] ?? 3));
        $delayMax = max($delayMin, (int)($input['typing_delay_max'] ?? 7));
        $blacklist = trim((string)($input['blacklist_phones'] ?? ''));
        $apiId = trim((string)($input['api_id'] ?? ''));
        $apiHash = trim((string)($input['api_hash'] ?? ''));

        $stmtCheck = $pdo->query("SELECT id FROM tg_assistant_config LIMIT 1");
        $cfgId = $stmtCheck->fetchColumn();

        if ($cfgId) {
            $stmtUpd = $pdo->prepare("
                UPDATE tg_assistant_config SET
                    system_prompt = :prompt,
                    tone_of_voice = :tone,
                    typing_delay_min = :dmin,
                    typing_delay_max = :dmax,
                    blacklist_phones = :bl,
                    api_id = :api_id,
                    api_hash = :api_hash,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmtUpd->execute([
                ':prompt'   => $sysPrompt,
                ':tone'     => $tone,
                ':dmin'     => $delayMin,
                ':dmax'     => $delayMax,
                ':bl'       => $blacklist,
                ':api_id'   => $apiId,
                ':api_hash' => $apiHash,
                ':id'       => $cfgId
            ]);
        } else {
            $stmtIns = $pdo->prepare("
                INSERT INTO tg_assistant_config (system_prompt, tone_of_voice, typing_delay_min, typing_delay_max, blacklist_phones, api_id, api_hash, updated_at)
                VALUES (:prompt, :tone, :dmin, :dmax, :bl, :api_id, :api_hash, CURRENT_TIMESTAMP)
            ");
            $stmtIns->execute([
                ':prompt'   => $sysPrompt,
                ':tone'     => $tone,
                ':dmin'     => $delayMin,
                ':dmax'     => $delayMax,
                ':bl'       => $blacklist,
                ':api_id'   => $apiId,
                ':api_hash' => $apiHash
            ]);
        }

        echo json_encode([
            'status'  => 'success',
            'message' => 'Настройки Telegram-ассистента успешно сохранены'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 7. action = test_mock (Mock Sandbox симуляция диалога без затрат токенов)
    // -------------------------------------------------------------------------
    if ($action === 'test_mock') {
        $testMsg = trim((string)($input['test_message'] ?? 'Здравствуйте! Сколько стоит сайт на чистом коде? Сайт на Тильде грузится 5 секунд.'));
        $senderName = trim((string)($input['sender_name'] ?? 'Роман (Строительство)'));

        // Проверка черного списка
        $stmtCfg = $pdo->query("SELECT * FROM tg_assistant_config ORDER BY id ASC LIMIT 1");
        $cfg = $stmtCfg->fetch();
        $blacklistRaw = $cfg['blacklist_phones'] ?? '+79990001122, Мама, Брат, Музыка';
        $blacklist = array_filter(array_map('trim', explode(',', strtolower($blacklistRaw))));

        $isBlocked = false;
        foreach ($blacklist as $blItem) {
            if ($blItem !== '' && (str_contains(strtolower($senderName), $blItem) || str_contains(strtolower($testMsg), $blItem))) {
                $isBlocked = true;
                break;
            }
        }

        if ($isBlocked) {
            echo json_encode([
                'status'           => 'success',
                'decision'         => 'BLOCKED',
                'reason'           => 'Контакт или ключевое слово найдено в черном списке',
                'intent_detected'  => false,
                'simulated_delay'  => 0,
                'bot_response'     => '[СООБЩЕНИЕ ПРОИГНОРИРОВАНО АССИСТЕНТОМ]'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        // Проверка коммерческого интента
        $intentKeywords = ['сайт', 'лендинг', 'разработк', 'тильд', 'tilda', 'скорост', 'тариф', 'стоимост', 'цен', 'pagespeed', 'дизайн', 'верстк'];
        $hasIntent = false;
        $matchedKeyword = '';
        foreach ($intentKeywords as $kw) {
            if (mb_stripos($testMsg, $kw) !== false) {
                $hasIntent = true;
                $matchedKeyword = $kw;
                break;
            }
        }

        $delayMin = (int)($cfg['typing_delay_min'] ?? 3);
        $delayMax = (int)($cfg['typing_delay_max'] ?? 7);
        $simulatedDelay = round(lcg_value() * ($delayMax - $delayMin) + $delayMin, 1);

        $botReply = "Привет! Мы делаем сайты только на чистом коде (без Tilda и WP) со скоростью 100/100 в PageSpeed за 5-7 дней. Тарифы под ключ: базовый лендинг - от 18к до 25к, бизнес-сайт с анимациями и микро-админкой - 25к-35к руб. Какой у вас бизнес и есть ли текущий сайт для экспресс-аудита?";

        echo json_encode([
            'status'           => 'success',
            'decision'         => $hasIntent ? 'REPLIED' : 'IGNORED',
            'intent_detected'  => $hasIntent,
            'matched_keyword'  => $matchedKeyword,
            'blacklist_status' => 'CLEAN (Passed)',
            'simulated_delay'  => "{$simulatedDelay} сек (typing status имитирован)",
            'bot_response'     => $botReply,
            'log'              => "[SANDBOX] Лид '{$senderName}': интент подтвержден по ключу '{$matchedKeyword}', выдержана пауза {$simulatedDelay}с, сформирован ответ."
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 8. action = download_vps_package (Генерация .zip пакета для деплоя на VPS)
    // -------------------------------------------------------------------------
    if ($action === 'download_vps_package') {
        $zipFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'antidesigner-tg-agent-' . time() . '.zip';
        $packagerPy = dirname(__DIR__, 2) . '/tg-agent/package_vps.py';

        // 1. Пробуем сгенерировать через python package_vps.py
        if (file_exists($packagerPy)) {
            $cmd = 'python ' . escapeshellarg($packagerPy) . ' ' . escapeshellarg($zipFile) . ' 2>&1';
            exec($cmd, $out, $ret);
        }

        // 2. Если Python недоступен, пробуем ZipArchive
        if (!file_exists($zipFile) || filesize($zipFile) === 0) {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    $agentDir = dirname(__DIR__, 2) . '/tg-agent';
                    if (file_exists($agentDir . '/client_agent.py')) {
                        $zip->addFile($agentDir . '/client_agent.py', 'client_agent.py');
                    }
                    if (file_exists($agentDir . '/requirements.txt')) {
                        $zip->addFile($agentDir . '/requirements.txt', 'requirements.txt');
                    }
                    if (file_exists($agentDir . '/anti-tg-agent.service')) {
                        $zip->addFile($agentDir . '/anti-tg-agent.service', 'anti-tg-agent.service');
                    }
                    if (file_exists($agentDir . '/README.md')) {
                        $zip->addFile($agentDir . '/README.md', 'README.md');
                    }
                    $stmtCfg = $pdo->query("SELECT * FROM tg_assistant_config ORDER BY id ASC LIMIT 1");
                    $cfg = $stmtCfg->fetch();
                    $envContent = "TG_API_ID=" . ($cfg['api_id'] ?? '2847192') . "\n" .
                        "TG_API_HASH=" . ($cfg['api_hash'] ?? '8f7e6d5c4b3a2109fedcba9876543210') . "\n" .
                        "TG_PHONE=\n" .
                        "SYNC_ENDPOINT=http://localhost:8000/lk-admin/api/sync_chat.php\n" .
                        "DELAY_MIN=" . ($cfg['typing_delay_min'] ?? 3) . "\n" .
                        "DELAY_MAX=" . ($cfg['typing_delay_max'] ?? 7) . "\n" .
                        "BLACKLIST_PHONES=" . ($cfg['blacklist_phones'] ?? '+79990001122,Мама,Брат,Музыка') . "\n";
                    $zip->addFromString('.env', $envContent);
                    $zip->close();
                }
            }
        }

        if (file_exists($zipFile) && filesize($zipFile) > 0) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="antidesigner-tg-agent.zip"');
            header('Content-Length: ' . filesize($zipFile));
            header('Pragma: no-cache');
            readfile($zipFile);
            @unlink($zipFile);
            exit;
        }

        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Не удалось сформировать архив для VPS'], JSON_UNESCAPED_UNICODE);
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

