<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — CONTENT FACTORY API (Omnichannel Generation & Publishing)
 * Gemini AI generation + Telegram Bot API integration
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/gemini.php';
    /** @var PDO $pdo */

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = $_GET['action'] ?? $input['action'] ?? 'list';

    // -------------------------------------------------------------------------
    // 1. СПИСОК МАТЕРИАЛОВ (action=list)
    // -------------------------------------------------------------------------
    if ($action === 'list') {
        $stmt = $pdo->query('SELECT * FROM content_items ORDER BY id DESC LIMIT 30');
        $items = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'count'  => count($items),
            'items'  => $items
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. ОБНОВЛЕНИЕ ЧЕРНОВИКА (action=update)
    // -------------------------------------------------------------------------
    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $contentMarkdown = trim((string)($input['content_markdown'] ?? ''));

        if ($id <= 0 || $contentMarkdown === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный id или пустой контент'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE content_items SET content_markdown = :content WHERE id = :id');
        $stmt->execute([':content' => $contentMarkdown, ':id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Материал успешно обновлен'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. ГЕНЕРАЦИЯ ПАКЕТА МАТЕРИАЛОВ ЧЕРЕЗ GEMINI (action=generate)
    // -------------------------------------------------------------------------
    if ($action === 'generate') {
        $topic = trim((string)($input['topic'] ?? ''));
        if ($topic === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Укажите тему или инфоповод для генерации'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Список форматов для генерации (по умолчанию 4 формата)
        $formats = $input['formats'] ?? ['tg_post', 'vc_ru', 'reels', 'lead_magnet'];
        if (is_string($formats)) {
            $formats = array_filter(array_map('trim', explode(',', $formats)));
        }
        if (empty($formats)) {
            $formats = ['tg_post', 'vc_ru', 'reels', 'lead_magnet'];
        }

        // 1. Подтягиваем всю актуальную базу знаний для RAG-контекста
        $stmtKb = $pdo->query('SELECT category, title, content_markdown FROM knowledge_base ORDER BY id ASC');
        $kbRows = $stmtKb->fetchAll();
        $knowledgeContext = "АКТУАЛЬНЫЙ КОНТЕКСТ КОМПАНИИ ANTIDESIGNER:\n";
        foreach ($kbRows as $kb) {
            $knowledgeContext .= "--- {$kb['title']} ({$kb['category']}) ---\n{$kb['content_markdown']}\n\n";
        }

        // 2. Подтягиваем системные промпты скиллов
        $stmtSkills = $pdo->query('SELECT code, title, system_prompt, tone_of_voice FROM skills');
        $allSkills = [];
        while ($row = $stmtSkills->fetch()) {
            $allSkills[$row['code']] = $row;
        }

        $results = [];
        $errors = [];

        // 3. Для каждого выбранного формата вызываем Gemini
        foreach ($formats as $fmtCode) {
            $skill = $allSkills[$fmtCode] ?? null;
            if (!$skill) {
                // Если скилл отсутствует, пропускаем
                continue;
            }

            $systemInstruction = $skill['system_prompt'] . "\n\n" .
                "TONE OF VOICE:\n" . ($skill['tone_of_voice'] ?: 'Dark Tech, лаконичный, экспертный') . "\n\n" .
                $knowledgeContext;

            $userPrompt = "ТЕМА МАТЕРИАЛА: {$topic}\n\n" .
                "ЗАДАЧА: Сгенерируй полноценный, готовый к публикации материал для формата «{$skill['title']}» (код: {$fmtCode}). " .
                "Опирайся на контекст компании, приводи реальные цифры и преимущества чистого кода перед конструкторами. " .
                "Отдай только готовый текст без лишних вступительных реплик.";

            try {
                $generatedText = callGemini($systemInstruction, $userPrompt, 'gemini-1.5-flash');

                // Сохраняем в базу данных в статусе draft
                $stmtInsert = $pdo->prepare('INSERT INTO content_items (topic, format, content_markdown, status) VALUES (:topic, :format, :content, :status)');
                $stmtInsert->execute([
                    ':topic'   => $topic,
                    ':format'  => $fmtCode,
                    ':content' => $generatedText,
                    ':status'  => 'draft'
                ]);
                $newId = (int)$pdo->lastInsertId();

                $results[] = [
                    'id'       => $newId,
                    'format'   => $fmtCode,
                    'title'    => $skill['title'],
                    'content'  => $generatedText,
                    'status'   => 'draft'
                ];
            } catch (Throwable $genErr) {
                $errors[] = "Ошибка при генерации {$skill['title']}: " . $genErr->getMessage();
            }
        }

        if (empty($results) && !empty($errors)) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => implode('; ', $errors)
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        echo json_encode([
            'status'    => 'success',
            'topic'     => $topic,
            'count'     => count($results),
            'generated' => $results,
            'errors'    => $errors
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ / ЧАТ (action=publish_tg)
    // -------------------------------------------------------------------------
    if ($action === 'publish_tg') {
        $contentId = isset($input['content_id']) ? (int)$input['content_id'] : 0;
        $textToPublish = trim((string)($input['text'] ?? ''));

        if ($contentId > 0 && $textToPublish === '') {
            $stmtGet = $pdo->prepare('SELECT content_markdown FROM content_items WHERE id = :id');
            $stmtGet->execute([':id' => $contentId]);
            $textToPublish = (string)$stmtGet->fetchColumn();
        }

        if ($textToPublish === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Текст публикации пуст'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Читаем конфигурацию Telegram
        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];

        $botToken = trim((string)($config['telegram_bot_token'] ?? ''));
        $chatId = trim((string)($config['admin_telegram_id'] ?? ''));

        if ($botToken === '' || $chatId === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Для публикации в Telegram укажите telegram_bot_token и admin_telegram_id в файле core-data/config.php'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Отправка в Telegram Bot API
        $tgUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $tgPayload = [
            'chat_id'                  => $chatId,
            'text'                     => $textToPublish,
            'disable_web_page_preview' => false
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $tgUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($tgPayload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $tgRes = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new RuntimeException("Сетевая ошибка при отправке в Telegram: {$curlErr}");
        }

        $tgDecoded = json_decode((string)$tgRes, true);
        if ($httpCode !== 200 || !($tgDecoded['ok'] ?? false)) {
            $desc = $tgDecoded['description'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("Ошибка Telegram API: {$desc}");
        }

        // Обновляем статус в БД
        if ($contentId > 0) {
            $stmtUpd = $pdo->prepare("UPDATE content_items SET status = 'published', published_url = :url WHERE id = :id");
            $stmtUpd->execute([
                ':url' => 'https://t.me/' . ltrim($chatId, '@'),
                ':id'  => $contentId
            ]);
        }

        echo json_encode([
            'status'  => 'success',
            'message' => 'Материал успешно опубликован в Telegram',
            'telegram_response' => $tgDecoded['result'] ?? null
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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

