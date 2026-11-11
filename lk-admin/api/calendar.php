<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - SMM MEMORY STONES CONTENT PLAN & RUBRICATOR API
 * Table structure: Day, Format, Rubric, Title, Comment, Status, Platforms (TG/VC/Habr/Inst/Site)
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

    $action = trim((string)($_GET['action'] ?? $input['action'] ?? 'list'));

    // -------------------------------------------------------------------------
    // 1. action = list
    // -------------------------------------------------------------------------
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT * FROM content_plan ORDER BY id ASC");
        $rows = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'count'  => count($rows),
            'items'  => $rows
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. action = create
    // -------------------------------------------------------------------------
    if ($action === 'create') {
        $dayLabel = trim((string)($input['day_label'] ?? 'Новый день'));
        $format = trim((string)($input['format'] ?? 'post_tg'));
        $rubric = trim((string)($input['rubric'] ?? '🏎 Скорость и чистый код'));
        $title = trim((string)($input['title'] ?? 'Новая тема'));
        $comment = trim((string)($input['comment'] ?? ''));
        $status = trim((string)($input['status'] ?? 'idea'));

        $ptg = !empty($input['platform_tg']) ? 1 : 0;
        $pvc = !empty($input['platform_vc']) ? 1 : 0;
        $phabr = !empty($input['platform_habr']) ? 1 : 0;
        $pinst = !empty($input['platform_inst']) ? 1 : 0;
        $psetka = !empty($input['platform_setka']) ? 1 : 0;
        $psite = !empty($input['platform_site']) ? 1 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO content_plan (day_label, format, rubric, title, comment, status, platform_tg, platform_vc, platform_habr, platform_inst, platform_setka, platform_site, created_at)
            VALUES (:day, :fmt, :rub, :title, :comment, :st, :ptg, :pvc, :phabr, :pinst, :psetka, :psite, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':day'     => $dayLabel,
            ':fmt'     => $format,
            ':rub'     => $rubric,
            ':title'   => str_replace(['—', '–'], '-', $title),
            ':comment' => $comment,
            ':st'      => $status,
            ':ptg'     => $ptg,
            ':pvc'     => $pvc,
            ':phabr'   => $phabr,
            ':pinst'   => $pinst,
            ':psetka'  => $psetka,
            ':psite'   => $psite
        ]);

        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'status'  => 'success',
            'message' => 'Строка успешно добавлена в контент-план',
            'id'      => $newId
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. action = update
    // -------------------------------------------------------------------------
    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный ID строки'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $fields = [];
        $params = [':id' => $id];

        $allowedKeys = [
            'day_label'     => 'string',
            'format'        => 'string',
            'rubric'        => 'string',
            'title'         => 'string',
            'comment'       => 'string',
            'status'        => 'string',
            'platform_tg'   => 'int',
            'platform_vc'   => 'int',
            'platform_habr' => 'int',
            'platform_inst' => 'int',
            'platform_setka'=> 'int',
            'platform_site' => 'int'
        ];

        foreach ($allowedKeys as $key => $type) {
            if (array_key_exists($key, $input)) {
                $fields[] = "{$key} = :{$key}";
                if ($type === 'int') {
                    $params[":{$key}"] = !empty($input[$key]) ? 1 : 0;
                } else {
                    $val = trim((string)$input[$key]);
                    if ($key === 'title') {
                        $val = str_replace(['—', '–'], '-', $val);
                    }
                    $params[":{$key}"] = $val;
                }
            }
        }

        if (empty($fields)) {
            echo json_encode(['status' => 'success', 'message' => 'Нет полей для обновления'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql = "UPDATE content_plan SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Строка контент-плана успешно сохранена'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. action = delete
    // -------------------------------------------------------------------------
    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный ID'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM content_plan WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Строка удалена из контент-плана'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 5. action = rubrics
    // -------------------------------------------------------------------------
    if ($action === 'rubrics') {
        // Убедимся, что таблица rubrics существует
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rubrics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                examples TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_rubrics_title ON rubrics(title);
        ");

        $stmtRub = $pdo->query("SELECT * FROM rubrics ORDER BY id ASC");
        $dbRubrics = $stmtRub->fetchAll();

        // Если таблица пуста, сидируем базовые
        if (empty($dbRubrics)) {
            require_once __DIR__ . '/rubrics.php';
            exit;
        }

        $rubrics = [];
        foreach ($dbRubrics as $r) {
            $ex = $r['examples'] ?? '';
            $parsedExamples = [];
            if ($ex !== '') {
                $decoded = json_decode($ex, true);
                if (is_array($decoded)) {
                    $parsedExamples = $decoded;
                } else {
                    $lines = array_map('trim', explode("\n", $ex));
                    $parsedExamples = array_values(array_filter($lines));
                }
            }
            $rubrics[] = [
                'id'          => (int)$r['id'],
                'name'        => $r['title'],
                'title'       => $r['title'],
                'tag'         => 'RUBRIC_' . $r['id'],
                'desc'        => $r['description'] ?? '',
                'description' => $r['description'] ?? '',
                'examples'    => $parsedExamples
            ];
        }

        echo json_encode([
            'status'  => 'success',
            'count'   => count($rubrics),
            'rubrics' => $rubrics
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 6. action = auto_plan (Проектирование плана на 1-2 дня через Flash-Lite)
    // -------------------------------------------------------------------------
    if ($action === 'auto_plan') {
        require_once dirname(__DIR__, 2) . '/core-data/ai_router.php';

        // 1. Получаем активные тренды
        $stmtTrends = $pdo->query("SELECT title, summary FROM trends WHERE is_approved = 1 ORDER BY id DESC LIMIT 5");
        $trends = $stmtTrends->fetchAll();
        if (empty($trends)) {
            $stmtTrends = $pdo->query("SELECT title, summary FROM trends ORDER BY id DESC LIMIT 5");
            $trends = $stmtTrends->fetchAll();
        }

        $trendsContext = "";
        if (!empty($trends)) {
            $trendsContext = "АКТУАЛЬНЫЕ ТРЕНДЫ И ИНФОПОВОДЫ:\n";
            foreach ($trends as $idx => $t) {
                $num = $idx + 1;
                $trendsContext .= "{$num}. «{$t['title']}» - {$t['summary']}\n";
            }
        }

        // 2. Получаем активные рубрики
        $stmtRub = $pdo->query("SELECT id, title, description FROM rubrics ORDER BY id ASC");
        $rubrics = $stmtRub->fetchAll();
        $rubricsList = [];
        $rubricsContext = "ДОСТУПНЫЕ РУБРИКИ:\n";
        foreach ($rubrics as $r) {
            $rubricsList[] = $r['title'];
            $rubricsContext .= "- {$r['title']}: {$r['description']}\n";
        }

        // 3. Подтягиваем профильный скилл контент-планировщика из базы skills
        $aiRouter = new AIRouter();
        $aiRouter->setPDO($pdo);
        $plannerSkill = $aiRouter->loadSkill('content_planner');

        $sysInstruction = <<<PROMPT
{$plannerSkill['system_prompt']}

TONE OF VOICE:
{$plannerSkill['tone_of_voice']}

{$rubricsContext}

{$trendsContext}

ФОРМАТ ОТВЕТА:
Верни СТРОГО валидный JSON-массив без какого-либо вступительного текста, без markdown-разметки (или строго внутри ```json ```), содержащий объекты со следующими ключами:
[
  {
    "day_label": "1 день (Сегодня)",
    "format": "post_tg",
    "rubric": "Название одной из доступных рубрик",
    "title": "Хлесткий, кликабельный заголовок темы без штампов и без длинных тире",
    "comment": "Краткое ТЗ / главная мысль публикации и призыв к действию",
    "status": "idea",
    "platform_tg": 1,
    "platform_vc": 0,
    "platform_habr": 0,
    "platform_inst": 0,
    "platform_setka": 0,
    "platform_site": 0
  }
]

ПРАВИЛА ТИПОГРАФИКИ:
Никогда не используй длинные тире (символ —). Используй только дефис (-) или стрелки (->).
PROMPT;

        $userPrompt = "Сформируй 3-4 публикации на 1-2 дня с распределением по доступным рубрикам и форматам.";
        $rawResult = $aiRouter->generateWithModel(AIRouter::MODEL_FAST, $sysInstruction, $userPrompt);

        // Извлекаем JSON из ответа
        $cleanJson = trim($rawResult);
        if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\])\s*```/u', $cleanJson, $matches)) {
            $cleanJson = trim($matches[1]);
        } elseif (preg_match('/(\[[\s\S]*?\])/u', $cleanJson, $matches)) {
            $cleanJson = trim($matches[1]);
        }

        $items = json_decode($cleanJson, true);
        if (!is_array($items) || empty($items)) {
            // Фолбэк на качественный набор, если ИИ вернул невалидный JSON
            $items = [
                [
                    'day_label'     => '1 день (Сегодня)',
                    'format'        => 'post_tg',
                    'rubric'        => $rubricsList[0] ?? '🏎 Скорость и чистый код vs Конструкторы',
                    'title'         => 'Куда уходят 40% бюджета с Директа: как Tilda режет мобильные переходы',
                    'comment'       => 'Разбор LCP 4.2 сек на конструкторах и замер отказов в Метрике',
                    'status'        => 'idea',
                    'platform_tg'   => 1,
                    'platform_vc'   => 0,
                    'platform_habr' => 0,
                    'platform_inst' => 0,
                    'platform_setka'=> 1,
                    'platform_site' => 0
                ],
                [
                    'day_label'     => '1 день (Сегодня)',
                    'format'        => 'reels',
                    'rubric'        => $rubricsList[1] ?? '🛠 Кейсы и До/После',
                    'title'         => 'Тест скорости 4G: чистый код 0.28 сек против шаблона 5.1 сек',
                    'comment'       => 'Замер на слабом интернете в движении, демонстрация мгновенного первого экрана',
                    'status'        => 'idea',
                    'platform_tg'   => 1,
                    'platform_vc'   => 0,
                    'platform_habr' => 0,
                    'platform_inst' => 1,
                    'platform_setka'=> 0,
                    'platform_site' => 0
                ],
                [
                    'day_label'     => '2 день (Завтра)',
                    'format'        => 'article',
                    'rubric'        => $rubricsList[2] ?? '💰 Бизнес и Конверсия',
                    'title'         => 'Математика скорости: как сокращение загрузки до 0.4 сек окупает сайт за 1 месяц',
                    'comment'       => 'Кейс B2B-компании с графиками конверсий и ростом заявок на 32%',
                    'status'        => 'idea',
                    'platform_tg'   => 1,
                    'platform_vc'   => 1,
                    'platform_habr' => 1,
                    'platform_inst' => 0,
                    'platform_setka'=> 1,
                    'platform_site' => 1
                ],
                [
                    'day_label'     => '2 день (Завтра)',
                    'format'        => 'post_setka',
                    'rubric'        => $rubricsList[4] ?? '❓ FAQ и Отработка возражений',
                    'title'         => '«А как я поменяю текст без конструктора?»: почему чистый код удобнее в 2026 году',
                    'comment'       => 'Наглядный видео-пример редактирования контента через панель AntiDesigner Core',
                    'status'        => 'idea',
                    'platform_tg'   => 1,
                    'platform_vc'   => 0,
                    'platform_habr' => 0,
                    'platform_inst' => 0,
                    'platform_setka'=> 1,
                    'platform_site' => 0
                ]
            ];
        }

        // Вставляем сгенерированные строки в content_plan со статусом idea
        $stmtIns = $pdo->prepare("
            INSERT INTO content_plan (day_label, format, rubric, title, comment, status, platform_tg, platform_vc, platform_habr, platform_inst, platform_setka, platform_site, created_at)
            VALUES (:day, :fmt, :rub, :title, :comment, 'idea', :ptg, :pvc, :phabr, :pinst, :psetka, :psite, CURRENT_TIMESTAMP)
        ");

        $inserted = [];
        foreach ($items as $item) {
            $dayLabel = trim((string)($item['day_label'] ?? '1 день'));
            $fmt      = trim((string)($item['format'] ?? 'post_tg'));
            $rub      = trim((string)($item['rubric'] ?? ($rubricsList[0] ?? '🏎 Скорость и чистый код')));
            $title    = str_replace(['—', '–'], '-', trim((string)($item['title'] ?? 'Новая публикация')));
            $comment  = str_replace(['—', '–'], '-', trim((string)($item['comment'] ?? '')));
            $ptg      = !empty($item['platform_tg']) ? 1 : 0;
            $pvc      = !empty($item['platform_vc']) ? 1 : 0;
            $phabr    = !empty($item['platform_habr']) ? 1 : 0;
            $pinst    = !empty($item['platform_inst']) ? 1 : 0;
            $psetka   = !empty($item['platform_setka']) ? 1 : 0;
            $psite    = !empty($item['platform_site']) ? 1 : 0;

            $stmtIns->execute([
                ':day'     => $dayLabel,
                ':fmt'     => $fmt,
                ':rub'     => $rub,
                ':title'   => $title,
                ':comment' => $comment,
                ':ptg'     => $ptg,
                ':pvc'     => $pvc,
                ':phabr'   => $phabr,
                ':pinst'   => $pinst,
                ':psetka'  => $psetka,
                ':psite'   => $psite
            ]);

            $inserted[] = [
                'id'        => (int)$pdo->lastInsertId(),
                'day_label' => $dayLabel,
                'format'    => $fmt,
                'rubric'    => $rub,
                'title'     => $title,
                'comment'   => $comment,
                'status'    => 'idea'
            ];
        }

        echo json_encode([
            'status'  => 'success',
            'message' => 'Спроектировано ' . count($inserted) . ' публикаций на 1-2 дня',
            'count'   => count($inserted),
            'items'   => $inserted
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

