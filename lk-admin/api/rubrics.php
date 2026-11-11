<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - RUBRICS CRUD API
 * Manage content strategy rubrics and example topics
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

    // Создаем таблицу, если еще не создана
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

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim((string)($_GET['action'] ?? $input['action'] ?? 'list'));

    // Авто-сид 5 базовых рубрик при пустой таблице
    $count = (int)$pdo->query("SELECT COUNT(*) FROM rubrics")->fetchColumn();
    if ($count === 0) {
        $seedRubrics = [
            [
                'title'       => '🏎 Скорость и чистый код vs Конструкторы',
                'description' => 'Разбор косяков Tilda/WordPress, потерянные миллионы из-за скорости загрузки',
                'examples'    => json_encode([
                    'Почему сайты на Tilda теряют до 40% трафика из рекламы на первом экране',
                    'Замер TTFB и LCP: сравниваем шаблонный сайт с сайтом на чистом коде'
                ], JSON_UNESCAPED_UNICODE)
            ],
            [
                'title'       => '🛠 Кейсы и До/После',
                'description' => 'Аудиты реальных сайтов, PageSpeed 98+, время загрузки 0.3 сек',
                'examples'    => json_encode([
                    'Кейс: как мы разогнали сайт клиники до 100/100 и подняли конверсию на 38%',
                    'Редизайн каталога запчастей: убрали 14 лишних скриптов и ускорили рендер'
                ], JSON_UNESCAPED_UNICODE)
            ],
            [
                'title'       => '💰 Бизнес и Конверсия',
                'description' => 'Как скорость поднимает продажи на 20-40%, честное SEO без накруток',
                'examples'    => json_encode([
                    'Калькулятор окупаемости сайта: считаем стоимость лида до и после оптимизации',
                    'Почему поисковики Яндекс и Google отдают приоритет сайтам с чистым кодом'
                ], JSON_UNESCAPED_UNICODE)
            ],
            [
                'title'       => '🎯 Тарифы и Условия',
                'description' => 'Прозрачный прайс 18к-35к+, никакой абонентской платы и скрытых платежей',
                'examples'    => json_encode([
                    'Что входит в тариф 25 000 рублей: сайт под ключ с гарантией скорости за 7 дней',
                    'Почему у AntiDesigner нет абонентской платы за платформу'
                ], JSON_UNESCAPED_UNICODE)
            ],
            [
                'title'       => '❓ FAQ и Отработка возражений',
                'description' => 'Почему чистый код надежнее конструктора и как клиент сам меняет тексты',
                'examples'    => json_encode([
                    '«А как я сам поменяю телефон на сайте?»: простая инструкция без программиста',
                    'Безопасность сайта: почему чистый HTML нельзя взломать через плагины'
                ], JSON_UNESCAPED_UNICODE)
            ]
        ];

        $stmtIns = $pdo->prepare("INSERT INTO rubrics (title, description, examples) VALUES (:title, :desc, :examples)");
        foreach ($seedRubrics as $r) {
            $stmtIns->execute([
                ':title'    => $r['title'],
                ':desc'     => $r['description'],
                ':examples' => $r['examples']
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // 1. action = list
    // -------------------------------------------------------------------------
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT * FROM rubrics ORDER BY id ASC");
        $rows = $stmt->fetchAll();

        $rubrics = [];
        foreach ($rows as $row) {
            $ex = $row['examples'] ?? '';
            $parsedExamples = [];
            if ($ex !== '') {
                $decoded = json_decode($ex, true);
                if (is_array($decoded)) {
                    $parsedExamples = $decoded;
                } else {
                    // Разделение по переносам строк
                    $lines = array_map('trim', explode("\n", $ex));
                    $parsedExamples = array_values(array_filter($lines));
                }
            }

            $rubrics[] = [
                'id'          => (int)$row['id'],
                'title'       => $row['title'],
                'name'        => $row['title'], // Алиас для совместимости
                'tag'         => 'RUBRIC_' . $row['id'],
                'description' => $row['description'] ?? '',
                'desc'        => $row['description'] ?? '', // Алиас для совместимости
                'examples'    => $parsedExamples,
                'created_at'  => $row['created_at']
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
    // 2. action = save
    // -------------------------------------------------------------------------
    if ($action === 'save') {
        $id = (int)($input['id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $rawExamples = $input['examples'] ?? '';

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Название рубрики обязательно'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Типографика: замена длинных тире на дефис
        $title = str_replace(['—', '–'], '-', $title);
        $description = str_replace(['—', '–'], '-', $description);

        // Обработка примеров в JSON-массив
        if (is_array($rawExamples)) {
            $cleanExamples = array_values(array_filter(array_map(function($v) {
                return str_replace(['—', '–'], '-', trim((string)$v));
            }, $rawExamples)));
        } else {
            $lines = array_map('trim', explode("\n", (string)$rawExamples));
            $cleanExamples = array_values(array_filter(array_map(function($v) {
                return str_replace(['—', '–'], '-', $v);
            }, $lines)));
        }
        $examplesJson = json_encode($cleanExamples, JSON_UNESCAPED_UNICODE);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE rubrics SET title = :title, description = :desc, examples = :examples WHERE id = :id");
            $stmt->execute([
                ':title'    => $title,
                ':desc'     => $description,
                ':examples' => $examplesJson,
                ':id'       => $id
            ]);
            $savedId = $id;
            $msg = 'Рубрика успешно обновлена';
        } else {
            $stmt = $pdo->prepare("INSERT INTO rubrics (title, description, examples) VALUES (:title, :desc, :examples)");
            $stmt->execute([
                ':title'    => $title,
                ':desc'     => $description,
                ':examples' => $examplesJson
            ]);
            $savedId = (int)$pdo->lastInsertId();
            $msg = 'Рубрика успешно создана';
        }

        echo json_encode([
            'status'  => 'success',
            'message' => $msg,
            'id'      => $savedId,
            'title'   => $title
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. action = delete
    // -------------------------------------------------------------------------
    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный ID рубрики'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM rubrics WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Рубрика успешно удалена',
            'id'      => $id
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
