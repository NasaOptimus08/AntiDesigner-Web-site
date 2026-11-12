<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - CONTENT FACTORY API (Omnichannel Generation & Publishing)
 * Gemini AI generation + Telegram Bot API integration
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
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
    require_once __DIR__ . '/gemini.php';
    require_once dirname(__DIR__, 2) . '/core-data/ai_router.php';
    require_once dirname(__DIR__, 2) . '/core-data/tools/cleanup_tool.php';
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

        // Список форматов для генерации: поддержка как пакета, так и одиночных форматов
        $formatKey = $input['format'] ?? null;
        if ($formatKey === 'article') {
            $formats = ['vc_ru'];
        } elseif ($formatKey === 'telegram') {
            $formats = ['tg_post'];
        } elseif ($formatKey === 'instagram') {
            $formats = ['reels_hook'];
        } elseif ($formatKey === 'setka') {
            $formats = ['setka_post'];
        } elseif ($formatKey === 'lead_magnet') {
            $formats = ['lead_magnet'];
        } elseif ($formatKey === 'all' || !empty($input['formats'])) {
            $formats = $input['formats'] ?? ['vc_ru', 'tg_post', 'reels_hook', 'lead_magnet', 'site_geo', 'setka_post'];
            if (is_string($formats)) {
                $formats = array_filter(array_map('trim', explode(',', $formats)));
            }
        } else {
            $formats = ['vc_ru', 'tg_post', 'reels_hook', 'lead_magnet', 'setka_post'];
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

        // 3. Инициализируем AI Router с разделением ключей
        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $aiRouter = new AIRouter($config);
        $aiRouter->setPDO($pdo);

        $results = [];
        $errors = [];

        // Маппинг форматов Контент-фабрики на строгие типы задач AIRouter (ВСЕ идут на MODEL_HEAVY)
        $taskMap = [
            'tg_post'     => AIRouter::TASK_TG_POST,
            'telegram'    => AIRouter::TASK_TG_POST,
            'vc_ru'       => AIRouter::TASK_LONGREAD,
            'habr'        => AIRouter::TASK_LONGREAD,
            'article'     => AIRouter::TASK_LONGREAD,
            'setka_post'  => AIRouter::TASK_SETKA_POST,
            'setka'       => AIRouter::TASK_SETKA_POST,
            'reels_hook'  => AIRouter::TASK_REELS,
            'reels'       => AIRouter::TASK_REELS,
            'instagram'   => AIRouter::TASK_REELS,
            'ig_carousel' => AIRouter::TASK_CAROUSEL,
            'carousel'    => AIRouter::TASK_CAROUSEL,
            'lead_magnet' => AIRouter::TASK_LEAD_MAGNET,
            'site_geo'    => AIRouter::TASK_GEO_ARTICLE,
            'geo'         => AIRouter::TASK_GEO_ARTICLE,
        ];

        // 4. Для каждого выбранного формата вызываем Gemini Flash 3.8 (MODEL_HEAVY) с подтягиванием профильного скилла
        foreach ($formats as $fmtCode) {
            $taskType = $taskMap[$fmtCode] ?? AIRouter::TASK_LONGREAD;
            $resolvedModel = $aiRouter->resolveModel($taskType); // Строго MODEL_HEAVY

            // Обязательный точечный запрос скилла из базы данных skills
            $stmtSkill = $pdo->prepare('SELECT code, title, system_prompt, tone_of_voice FROM skills WHERE code = :code LIMIT 1');
            $stmtSkill->execute([':code' => $fmtCode]);
            $skill = $stmtSkill->fetch();

            if (!$skill || empty($skill['system_prompt'])) {
                // Если скилла нет по прямому коду, пробуем через loadSkill роутера
                $skill = $aiRouter->loadSkill($fmtCode);
            }

            // Подтягиваем активные модульные под-промпты из skill_prompts
            $subPromptsInstruction = '';
            $stmtSubPrompts = $pdo->prepare("SELECT title, prompt_type, step_order, content_markdown FROM skill_prompts WHERE skill_code = :code AND is_active = 1 ORDER BY step_order ASC, id ASC");
            $stmtSubPrompts->execute([':code' => $fmtCode]);
            $activeSubPrompts = $stmtSubPrompts->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($activeSubPrompts)) {
                $subPromptsInstruction = "\n\n### ЦЕПОЧКА МОДУЛЬНЫХ ПРОМПТОВ И СТИЛЕЙ (.MD):\n";
                foreach ($activeSubPrompts as $sp) {
                    $subPromptsInstruction .= "\n---\n#### {$sp['title']} (Тип: {$sp['prompt_type']}, Шаг {$sp['step_order']}):\n{$sp['content_markdown']}\n";
                }
            }

            $systemInstruction = $skill['system_prompt'] . "\n\n" .
                "TONE OF VOICE:\n" . ($skill['tone_of_voice'] ?: 'Dark Tech, лаконичный, экспертный, фактологический') . "\n\n" .
                $knowledgeContext .
                $subPromptsInstruction;

            if (in_array($fmtCode, ['reels_hook', 'reels', 'instagram'], true)) {
                $userPrompt = "ТЕМА ВИДЕО: {$topic}\n\n" .
                    "ЗАДАЧА: Сгенерируй профессиональный пакет для короткого видео (Reels / Shorts) строго по 6-фазной дуге удержания (5-6 сцен): Hook (0-3c) -> Problem (4-12c) -> Reveal (13-25c) -> Escalation (26-38c) -> Payoff (39-48c) -> CTA (49-60c).\n\n" .
                    "ДЛЯ КАЖДОЙ СЦЕНЫ СФОРМИРУЙ:\n" .
                    "1. Действие и визуал в кадре\n" .
                    "2. Текст субтитров (коротко, 4-8 слов)\n" .
                    "3. Роль в удержании аудитории (Retention Hook)\n" .
                    "4. Единый стиль, свет, ракурс камеры (Cinematic 35mm, Dark Tech)\n" .
                    "5. Image Prompt (Flux / Midjourney, 9:16)\n" .
                    "6. Video Prompt (Runway / Kling / Luma, 9:16)\n\n" .
                    "В КОНЦЕ ДОБАВЬ БЛОК ЗАКАДРОВОГО ГОЛОСА:\n" .
                    "• Дикторский скрипт озвучки (60-90 слов) с разметкой пауз [пауза 0.5с] и ударений (**слово**)\n" .
                    "• Voice Direction Prompt (тембр, темп, интонация для Gemini 3.8 Flash Audio / TTS).\n\n" .
                    "Отдай готовый материал в четко структурированном Markdown. Без длинных тире (—).";
            } else {
                $userPrompt = "ТЕМА МАТЕРИАЛА: {$topic}\n\n" .
                    "ЗАДАЧА: Сгенерируй полноценный, готовый к публикации материал для формата «{$skill['title']}» (код: {$fmtCode}). " .
                    "Опирайся на контекст компании AntiDesigner, приводи реальные цифры и преимущества чистого кода перед конструкторами. " .
                    "Отдай только готовый текст без лишних вступительных реплик. Без длинных тире (символ —).";
            }

            try {
                // Все чистовые материалы Контент-фабрики генерирует MODEL_HEAVY (Gemini Flash 3.8, ключ pza...)
                // Для лонгридов (vc_ru / habr) подключаем thinkingBudget = 2048
                $thinkingBudget = in_array($taskType, [AIRouter::TASK_LONGREAD, 'vc_ru'], true) ? 2048 : null;

                $generatedText = $aiRouter->execute(
                    $taskType,
                    $systemInstruction,
                    $userPrompt,
                    $thinkingBudget
                );

                // Дополнительная валидация типографики: исключение длинных тире
                $generatedText = str_replace(['—', '–'], '-', trim($generatedText));

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
                    'id'         => $newId,
                    'format'     => $fmtCode,
                    'task_type'  => $taskType,
                    'title'      => $skill['title'],
                    'content'    => $generatedText,
                    'model_used' => 'gemini-flash-3.8 (HEAVY)',
                    'status'     => 'draft'
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
            'status'     => 'success',
            'topic'      => $topic,
            'count'      => count($results),
            'model_used' => 'gemini-flash-3.8 (HEAVY)',
            'generated'  => $results,
            'errors'     => $errors
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
        $channelId = trim((string)($config['telegram_channel_id'] ?? '@AntidesignerAI'));
        if ($channelId === '') {
            $channelId = '@AntidesignerAI';
        }
        if (!str_starts_with($channelId, '@') && !str_starts_with($channelId, '-100') && !is_numeric($channelId)) {
            $channelId = '@' . $channelId;
        }

        if ($botToken === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Для публикации в Telegram укажите telegram_bot_token в core-data/config.local.php'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Подключаем инструмент tg_notify.php
        require_once dirname(__DIR__, 2) . '/core-data/tools/tg_notify.php';

        $tgRes = sendTelegramChannelPost($textToPublish, $channelId, $config);

        if ($tgRes['status'] !== 'success') {
            http_response_code(502);
            echo json_encode([
                'status'  => 'error',
                'message' => $tgRes['message'] ?? 'Ошибка отправки в канал Telegram',
                'details' => $tgRes
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $publishedUrl = $tgRes['published_url'] ?? ('https://t.me/' . ltrim($channelId, '@'));

        // Обновляем статус в БД
        if ($contentId > 0) {
            $stmtUpd = $pdo->prepare("UPDATE content_items SET status = 'published', published_url = :url WHERE id = :id");
            $stmtUpd->execute([
                ':url' => $publishedUrl,
                ':id'  => $contentId
            ]);

            // Автоматическая очистка временных медиа и тяжелых полей драфта после публикации
            cleanupPublishedDraftGarbage($pdo, $contentId);
        }

        echo json_encode([
            'status'            => 'success',
            'message'           => 'Материал успешно опубликован в Telegram-канале ' . $channelId,
            'channel'           => $channelId,
            'published_url'     => $publishedUrl,
            'message_id'        => $tgRes['message_id'] ?? null,
            'telegram_response' => $tgRes
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.1. ПРИНУДИТЕЛЬНАЯ ОЧИСТКА ОПУБЛИКОВАННЫХ ДРАФТОВ (action=cleanup_published_drafts)
    // -------------------------------------------------------------------------
    if ($action === 'cleanup_published_drafts') {
        $stats = cleanupPublishedDraftGarbage($pdo, 0);

        echo json_encode([
            'status'  => 'success',
            'message' => "Очистка завершена: освобождено {$stats['freed_kb']} КБ, удалено файлов: {$stats['deleted_files']}, очищено драфтов: {$stats['updated_items']}",
            'stats'   => $stats
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.2. ПОЛУЧЕНИЕ ПОЛНЫХ ДАННЫХ ДРАФТА (action=get_draft)
    // -------------------------------------------------------------------------
    if ($action === 'get_draft') {
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан ID драфта'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM content_items WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$draft) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Черновик не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'draft'  => $draft
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.3. ГЕНЕРАЦИЯ HTML5/CANVAS MOTION-АНИМАЦИИ (action=generate_motion)
    // -------------------------------------------------------------------------
    if ($action === 'generate_motion') {
        $contentId = (int)($input['content_id'] ?? 0);
        $styleCode = trim((string)($input['style_code'] ?? ''));
        $scriptText = trim((string)($input['script_text'] ?? ''));
        $topic = trim((string)($input['topic'] ?? ''));

        if ($contentId > 0 && $scriptText === '') {
            $stmtD = $pdo->prepare("SELECT topic, content_markdown FROM content_items WHERE id = :id");
            $stmtD->execute([':id' => $contentId]);
            $dRow = $stmtD->fetch();
            if ($dRow) {
                $scriptText = (string)$dRow['content_markdown'];
                $topic = $topic ?: (string)$dRow['topic'];
            }
        }

        // Подтягиваем описание стиля из skill_prompts
        $stylePrompt = '';
        if ($styleCode !== '') {
            $stmtSt = $pdo->prepare("SELECT content_markdown FROM skill_prompts WHERE skill_code = 'reels_hook' AND (title LIKE :t OR prompt_type = 'style') LIMIT 1");
            $stmtSt->execute([':t' => "%{$styleCode}%"]);
            $stylePrompt = (string)$stmtSt->fetchColumn();
        }
        if ($stylePrompt === '') {
            $stylePrompt = "Эстетика: Dark Tech Minimalist (HTML5/Canvas 9:16). Глубокий темный фон (#08090c), неоновый изумруд (#10b981), титановые рамки, плавная 60fps кинетическая типографика и динамический счетчик скорости.";
        }

        $sysPrompt = "Ты - Senior Creative Technologist и мастер генеративного моушн-дизайна (HTML5/CSS3/Canvas). "
            . "Твоя задача: сгенерировать самодостаточный, полностью рабочий вертикальный рекламный ролик (соотношение 9:16, 1080x1920 viewBox) на чистом HTML5, CSS3 и Canvas JS без внешних библиотек и CDN.\n\n"
            . "СТИЛЬ И ГРАФИКА:\n{$stylePrompt}\n\n"
            . "СТРОГИЕ ТРЕБОВАНИЯ К КОДУ:\n"
            . "1. Полный монолитный HTML-документ: <!DOCTYPE html><html><head><style>...</style></head><body><div class='viewport'><canvas id='c'></canvas><div class='overlay'>...</div></div><script>...</script></body></html>\n"
            . "2. Пропорции вертикальные 9:16, контейнер центрирован в окне браузера с черным фоном вокруг.\n"
            . "3. Плавная циклическая 60fps анимация (requestAnimationFrame): появление хука, поочередная смена 5-6 смысловых фраз сценария (по 3-4 секунды на слайд), анимированный счетчик миллисекунд и круговой прогресс-бар PageSpeed 100/100, нижний таймлайн ролика.\n"
            . "4. Запрещены любые внешние CDN (шрифты и иконки - системные sans-serif/monospace, SVG или рисуются на canvas).\n"
            . "5. Отдай СТРОГО чистый исходный код HTML без markdown-оберток.";

        $userPrompt = "ТЕМА РОЛИКА: {$topic}\nСЦЕНАРИЙ И ТЕЗИСЫ:\n{$scriptText}\n\nСгенерируй код самодостаточного вертикального 9:16 HTML5 моушн-ролика прямо сейчас.";

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $aiRouter = new AIRouter($config);
        $aiRouter->setPDO($pdo);

        $htmlMotion = $aiRouter->call($userPrompt, $sysPrompt, AIRouter::MODEL_HEAVY, 0.4);

        // Очистка от возможных markdown тегов
        $cleanHtml = trim($htmlMotion);
        $cleanHtml = preg_replace('/^```(?:html)?\s*/i', '', $cleanHtml);
        $cleanHtml = preg_replace('/\s*```$/i', '', $cleanHtml);

        if ($contentId > 0) {
            $stmtUpd = $pdo->prepare("UPDATE content_items SET motion_html = :html WHERE id = :id");
            $stmtUpd->execute([':html' => $cleanHtml, ':id' => $contentId]);
        }

        echo json_encode([
            'status'      => 'success',
            'motion_html' => $cleanHtml,
            'message'     => 'Моушн-анимация успешно сгенерирована'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.4. СИНТЕЗ ОЗВУЧКИ ЧЕРЕЗ POLZA.AI / GEMINI TTS (action=generate_tts)
    // -------------------------------------------------------------------------
    if ($action === 'generate_tts') {
        $contentId = (int)($input['content_id'] ?? 0);
        $text = trim((string)($input['text'] ?? ''));

        if ($text === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Текст для озвучки пуст'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];

        $polzaKey = trim((string)($config['polza_api_key'] ?? $config['gemini_heavy_api_key'] ?? ''));
        $ttsModel = trim((string)($config['polza_tts_model'] ?? 'openai/tts-1')) ?: 'openai/tts-1';

        if ($polzaKey === '') {
            echo json_encode([
                'status'  => 'error',
                'message' => 'polza_api_key не настроен в config.local.php. Укажите ключ или прикрепите готовый аудиофайл.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Очищаем текст от меток пауз и режиссуры для TTS движка
        $cleanVoiceText = preg_replace('/\[пауза\s*[\d\.\,]*\s*с(?:ек)?\]/iu', '', $text);
        $cleanVoiceText = preg_replace('/[#*`_~]/', '', $cleanVoiceText);
        $cleanVoiceText = trim($cleanVoiceText);

        $payload = json_encode([
            'model' => $ttsModel,
            'input' => $cleanVoiceText,
            'voice' => 'onyx'
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://polza.ai/api/v1/audio/speech');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $polzaKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $audioRaw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($audioRaw) && strlen($audioRaw) > 500) {
            $uploadsDir = dirname(__DIR__, 2) . '/core-data/uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $audioFileName = 'tts_' . ($contentId ?: rand(100, 999)) . '_' . time() . '.mp3';
            $audioFilePath = $uploadsDir . '/' . $audioFileName;
            file_put_contents($audioFilePath, $audioRaw);

            $relativeAudioUrl = '../core-data/uploads/' . $audioFileName;

            if ($contentId > 0) {
                $stmtUpd = $pdo->prepare("UPDATE content_items SET audio_url = :url WHERE id = :id");
                $stmtUpd->execute([':url' => $relativeAudioUrl, ':id' => $contentId]);
            }

            echo json_encode([
                'status'    => 'success',
                'audio_url' => $relativeAudioUrl,
                'message'   => 'Озвучка успешно сгенерирована через Polza.ai'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $errMsg = 'Ошибка синтеза речи через Polza.ai API (HTTP ' . $httpCode . ')';
        $errData = json_decode((string)$audioRaw, true);
        if (isset($errData['error']['message'])) {
            $errMsg .= ': ' . $errData['error']['message'];
        }

        echo json_encode([
            'status'  => 'error',
            'message' => $errMsg
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.5. ПРИКРЕПЛЕНИЕ АУДИОДОРОЖКИ К ДРАФТУ (action=attach_audio)
    // -------------------------------------------------------------------------
    if ($action === 'attach_audio') {
        $contentId = (int)($input['content_id'] ?? 0);
        $audioUrl = trim((string)($input['audio_url'] ?? ''));

        if ($contentId <= 0 || $audioUrl === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан content_id или audio_url'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE content_items SET audio_url = :url WHERE id = :id");
        $stmt->execute([':url' => $audioUrl, ':id' => $contentId]);

        echo json_encode([
            'status'    => 'success',
            'message'   => 'Аудиодорожка успешно прикреплена к драфту',
            'audio_url' => $audioUrl
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 5. ИНТЕРАКТИВНЫЙ КОНТЕНТ-ПЛАН (action=calendar_list)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_list') {
        // Проверяем наличие запланированных записей
        $count = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE scheduled_for IS NOT NULL")->fetchColumn();
        
        // Автоматический сид 30 дней СТРОГО один раз, если сетка абсолютно пуста
        if ($count === 0) {
            $planSeed = [
                ['format' => 'tg_post',     'status' => 'published', 'topic' => 'Почему конструкторы Tilda и Elementor режут конверсию на смартфонах: замер TTFB и LCP'],
                ['format' => 'reels_hook',   'status' => 'published', 'topic' => 'Тест скорости: Tilda 4.8 секунды против Чистого кода 0.35 секунды в сети 4G'],
                ['format' => 'vc_ru',        'status' => 'ready',     'topic' => 'Кейс ускорения клиники: как переход на чистый код поднял PageSpeed до 100/100 и дал +38% заявок'],
                ['format' => 'lead_magnet',  'status' => 'ready',     'topic' => 'Чек-лист: 10 скрытых признаков того, что ваш сайт на конструкторе сливает бюджет на рекламу'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Что внутри Tilda: почему одна простая страница весит 12 мегабайт и тянет 40 лишних js-библиотек'],
                ['format' => 'site_geo',     'status' => 'planned',   'topic' => 'Разработка сайта на чистом коде в Москве: реальные сроки, окупаемость и гарантия скорости'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => 'Клиент платил 180 000 руб в месяц за Яндекс Директ и не понимал, почему клиенты закрывают сайт'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Считаем математику владения: абонентская плата конструктора за 3 года против сайта в вечной собственности'],
                ['format' => 'vc_ru',        'status' => 'planned',   'topic' => 'Анатомия конверсионного первого экрана: почему слайдеры и сток-фото убивают продажи в B2B'],
                ['format' => 'lead_magnet',  'status' => 'planned',   'topic' => 'Гайд: Технический аудит скорости сайта своими руками за 5 минут'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Google Core Web Vitals в 2026 году: как поисковики штрафуют медленные сайты в ранжировании'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => '3 грубейшие ошибки в дизайне сайтов услуг, из-за которых посетитель уходит через 3 секунды'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Чистый код против шаблонов: почему мои сайты загружаются мгновенно даже в подземном паркинге'],
                ['format' => 'site_geo',     'status' => 'planned',   'topic' => 'Почему SEO-продвижение сайтов на Tilda обходится бизнесу дороже, чем на чистом HTML и CSS'],
                ['format' => 'vc_ru',        'status' => 'planned',   'topic' => 'Разбор 5 сайтов из ниши элитного ремонта: как тяжелая анимация превратила дизайн в пытку для клиентов'],
                ['format' => 'lead_magnet',  'status' => 'planned',   'topic' => 'Бриф на разработку продающего сайта: вопросы, которые экономят бизнесу до 200 000 рублей'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Мой личный рекорд: сайт с оценкой 100 из 100 на мобильных устройствах без потери качества'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => 'Как я переверстал лендинг на чистом коде за 48 часов и снизил цену лида с 2400 до 980 рублей'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Типографика, которая продает: 4 правила чистого шрифтового ритма для посадочных страниц'],
                ['format' => 'vc_ru',        'status' => 'planned',   'topic' => 'Почему бизнесу больше не нужны сайты на WordPress: уязвимости плагинов и тормоза базы данных'],
                ['format' => 'lead_magnet',  'status' => 'planned',   'topic' => 'Карта триггеров доверия: 15 элементов на сайте, которые закрывают возражения без звонка менеджеру'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Сколько весит идеальный сайт: почему лендинг AntiDesigner укладывается всего в 250 килобайт'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => 'Что видит клиент, когда заходит на сайт с телефона в лифте: сравнение конструктора и чистого кода'],
                ['format' => 'site_geo',     'status' => 'planned',   'topic' => 'Как подготовить сайт к запуску контекстной рекламы: технический чеклист готовности'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Микро-анимации на чистом CSS: делаем интерфейс премиальным без единой тяжелой JS-библиотеки'],
                ['format' => 'vc_ru',        'status' => 'planned',   'topic' => 'От идеи до первого лида за 3 дня: регламент быстрой разработки сайтов с гарантией скорости'],
                ['format' => 'lead_magnet',  'status' => 'planned',   'topic' => 'Аудит мобильной адаптивности: 12 неочевидных косяков верстки, отталкивающих покупателей'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Отзывы клиентов на сайте: как оформить блок доверия, чтобы ему реально верили, а не считали фейком'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => 'Главный секрет высокой конверсии: когда сайт отвечает на вопрос клиента быстрее, чем он успел моргнуть'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Итоги месяца и отчет по метрикам: сколько секунд и рублей я сэкономил своим клиентам']
            ];

            $baseTimestamp = strtotime('today');
            $stmtInsert = $pdo->prepare("
                INSERT INTO content_items (topic, format, status, scheduled_for, created_at)
                VALUES (:topic, :format, :status, :scheduled_for, CURRENT_TIMESTAMP)
            ");

            foreach ($planSeed as $idx => $seed) {
                $itemDate = date('Y-m-d', $baseTimestamp + ($idx * 86400));
                // Проверяем, нет ли уже такой темы
                $checkExists = $pdo->prepare("SELECT id FROM content_items WHERE topic = :topic LIMIT 1");
                $checkExists->execute([':topic' => $seed['topic']]);
                if (!$checkExists->fetchColumn()) {
                    $stmtInsert->execute([
                        ':topic'         => $seed['topic'],
                        ':format'        => $seed['format'],
                        ':status'       => $seed['status'],
                        ':scheduled_for' => $itemDate
                    ]);
                }
            }
        }

        $stmt = $pdo->query("
            SELECT id, topic, format, status, scheduled_for, views_count, likes_count, published_url, created_at,
                   CASE WHEN content_markdown IS NOT NULL AND LENGTH(content_markdown) > 0 THEN 1 ELSE 0 END as has_content
            FROM content_items 
            WHERE scheduled_for IS NOT NULL
            ORDER BY scheduled_for ASC, id ASC
            LIMIT 60
        ");
        $items = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'count'  => count($items),
            'items'  => $items
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 6. ОБНОВЛЕНИЕ ЭЛЕМЕНТА СЕТКИ (action=calendar_update)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_update') {
        $id = (int)($input['id'] ?? 0);
        $topic = trim((string)($input['topic'] ?? ''));
        $status = trim((string)($input['status'] ?? 'planned'));
        $scheduledFor = trim((string)($input['scheduled_for'] ?? ''));
        $format = trim((string)($input['format'] ?? 'tg_post'));

        if ($id <= 0 || $topic === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный ID или пустая тема'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $allowedStatuses = ['planned', 'ready', 'published', 'archived'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'planned';
        }

        $stmt = $pdo->prepare("
            UPDATE content_items 
            SET topic = :topic, status = :status, scheduled_for = :scheduled_for, format = :format
            WHERE id = :id
        ");
        $stmt->execute([
            ':topic'         => $topic,
            ':status'        => $status,
            ':scheduled_for' => $scheduledFor ?: date('Y-m-d'),
            ':format'        => $format,
            ':id'            => $id
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Публикация успешно обновлена'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 7. СОЗДАНИЕ НОВОЙ ПУБЛИКАЦИИ В СЕТКЕ (action=calendar_create)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_create') {
        $topic = trim((string)($input['topic'] ?? ''));
        $format = trim((string)($input['format'] ?? 'tg_post'));
        $scheduledFor = trim((string)($input['scheduled_for'] ?? date('Y-m-d')));
        $status = trim((string)($input['status'] ?? 'planned'));

        if ($topic === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Укажите тему публикации'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO content_items (topic, format, status, scheduled_for, created_at)
            VALUES (:topic, :format, :status, :scheduled_for, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':topic'         => $topic,
            ':format'        => $format,
            ':status'        => $status,
            ':scheduled_for' => $scheduledFor
        ]);

        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'status'  => 'success',
            'message' => 'Публикация добавлена в контент-план',
            'id'      => $newId
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 8. УДАЛЕНИЕ ПУБЛИКАЦИИ ИЗ СЕТКИ (action=calendar_delete)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный ID'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM content_items WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Публикация удалена из контент-плана'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 9. AI-ГЕНЕРАЦИЯ КОНТЕНТ-ПЛАНА НА 30 ДНЕЙ (action=calendar_generate_ai)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_generate_ai') {
        // Подтягиваем контекст из базы знаний
        $stmtKb = $pdo->query('SELECT category, title, content_markdown FROM knowledge_base ORDER BY id ASC');
        $kbRows = $stmtKb->fetchAll();
        $ragContext = '';
        foreach ($kbRows as $kb) {
            $ragContext .= "### {$kb['category']} - {$kb['title']}:\n{$kb['content_markdown']}\n\n";
        }

        $systemPrompt = "Ты - персональный AI-стратег и контент-редактор независимого разработчика Евгения Антипина (AntiDesigner, разработка сайтов на чистом коде с ультравысокой скоростью 100/100 в PageSpeed против медленных конструкторов Tilda/WordPress).\n"
            . "ПОЗИЦИОНИРОВАНИЕ: AntiDesigner - это ЛИЧНЫЙ БРЕНД Senior-разработчика Евгения Антипина, а не агентство и не студия! Категорически запрещены слова 'мы', 'наша команда', 'наша студия', 'наши специалисты'. Темы формулируются строго от 1-го лица ('Я', 'мой опыт', 'как я верстаю', 'в моих проектах') либо как объективные экспертные разборы с точки зрения автора кода.\n"
            . "Твоя задача - составить 30-дневный контент-план публикаций.\n"
            . "Каждый день должен иметь 1 тему, строго привязанную к болям клиентов (скорость загрузки, потеря заявок, экономия на рекламе, мобильная конверсия, владение кодом, работа напрямую с разработчиком без наценок агентств).\n"
            . "Форматы для чередования: tg_post, vc_ru, reels_hook, lead_magnet, site_geo.\n"
            . "ПРАВИЛО: Категорически ЗАПРЕЩЕНО использовать длинное тире (—) в текстах тем. Используй двоеточия, дефисы или скобки.\n"
            . "Верни ответ СТРОГО в виде валидного JSON массива объектов:\n"
            . '[{"day_offset": 0, "format": "tg_post", "topic": "..."}, {"day_offset": 1, "format": "reels_hook", "topic": "..."}]';

        $userPrompt = "Сформируй контент-план на 30 дней с учетом базы знаний независимого разработчика Евгения Антипина:\n\n{$ragContext}\nСгенерируй ровно 30 целевых тем в JSON.";

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $aiRouter = new AIRouter($config);
        $aiRouter->setPDO($pdo);
        $aiResponse = $aiRouter->call($userPrompt, $systemPrompt, AIRouter::MODEL_FAST);
        
        // Очищаем json
        $jsonStr = $aiResponse;
        if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\])\s*```/', $aiResponse, $matches)) {
            $jsonStr = $matches[1];
        } else {
            $start = strpos($aiResponse, '[');
            $end = strrpos($aiResponse, ']');
            if ($start !== false && $end !== false && $end > $start) {
                $jsonStr = substr($aiResponse, $start, $end - $start + 1);
            }
        }

        $items = json_decode($jsonStr, true);
        if (!is_array($items) || empty($items)) {
            throw new RuntimeException("Не удалось распарсить JSON план от нейросети: " . substr($aiResponse, 0, 300));
        }

        $baseTimestamp = strtotime('today');
        $stmtInsert = $pdo->prepare("
            INSERT INTO content_items (topic, format, status, scheduled_for, created_at)
            VALUES (:topic, :format, 'planned', :scheduled_for, CURRENT_TIMESTAMP)
        ");

        $insertedCount = 0;
        foreach ($items as $idx => $it) {
            $topic = trim((string)($it['topic'] ?? ''));
            $topic = str_replace(['—', '–'], '-', $topic);
            $fmt = trim((string)($it['format'] ?? 'tg_post'));
            $dayOffset = isset($it['day_offset']) ? (int)$it['day_offset'] : $idx;
            $itemDate = date('Y-m-d', $baseTimestamp + ($dayOffset * 86400));

            if ($topic !== '') {
                $stmtInsert->execute([
                    ':topic'         => $topic,
                    ':format'        => in_array($fmt, ['tg_post', 'vc_ru', 'reels_hook', 'lead_magnet', 'site_geo'], true) ? $fmt : 'tg_post',
                    ':scheduled_for' => $itemDate
                ]);
                $insertedCount++;
            }
        }

        echo json_encode([
            'status'  => 'success',
            'message' => "Сгенерировано и сохранено {$insertedCount} тем в контент-план",
            'count'   => $insertedCount
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

