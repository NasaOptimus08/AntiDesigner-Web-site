<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — SMM MEMORY STONES CONTENT PLAN & RUBRICATOR API
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

    // Авто-сид таблицы content_plan, если она пуста
    $planCount = (int)$pdo->query("SELECT COUNT(*) FROM content_plan")->fetchColumn();
    if ($planCount === 0) {
        $defaultSeed = [
            [
                'day_label'     => '1 день',
                'format'        => 'post_tg',
                'rubric'        => '🏎 Скорость и чистый код',
                'title'         => 'Почему конструкторы Tilda и Elementor режут конверсию на смартфонах: замер TTFB и LCP',
                'comment'       => 'Скриншот PageSpeed с 40 тяжелыми скриптами Tilda vs 1 файл CSS',
                'status'        => 'published',
                'platform_tg'   => 1,
                'platform_vc'   => 0,
                'platform_habr' => 0,
                'platform_inst' => 0,
                'platform_setka'=> 1,
                'platform_site' => 0
            ],
            [
                'day_label'     => '2 день',
                'format'        => 'reels',
                'rubric'        => '🛠 Кейсы и До/После',
                'title'         => 'Тест скорости: Tilda 4.8 секунды против Чистого кода 0.35 сек в сети 4G',
                'comment'       => 'Динамичный ролик: замер секундомером в метро на слабом интернете',
                'status'        => 'published',
                'platform_tg'   => 1,
                'platform_vc'   => 0,
                'platform_habr' => 0,
                'platform_inst' => 1,
                'platform_setka'=> 0,
                'platform_site' => 0
            ],
            [
                'day_label'     => '3 день',
                'format'        => 'article',
                'rubric'        => '💰 Бизнес и Конверсия',
                'title'         => 'Кейс ускорения клиники: как переход на чистый код поднял PageSpeed до 100/100 и дал +38% заявок',
                'comment'       => 'Большая аналитическая статья для VC с цифрами Яндекс Метрики',
                'status'        => 'ready',
                'platform_tg'   => 1,
                'platform_vc'   => 1,
                'platform_habr' => 1,
                'platform_inst' => 0,
                'platform_setka'=> 1,
                'platform_site' => 1
            ],
            [
                'day_label'     => '4 день',
                'format'        => 'lead_magnet',
                'rubric'        => '🎯 Тарифы и Условия',
                'title'         => 'Чек-лист: 10 скрытых признаков того, что ваш сайт на конструкторе сливает бюджет на рекламу',
                'comment'       => 'PDF-магнит для выдачи через ТГ-бота по кодовому слову СКОРОСТЬ',
                'status'        => 'ready',
                'platform_tg'   => 1,
                'platform_vc'   => 0,
                'platform_habr' => 0,
                'platform_inst' => 1,
                'platform_setka'=> 0,
                'platform_site' => 1
            ],
            [
                'day_label'     => '5 день',
                'format'        => 'carousel',
                'rubric'        => '❓ FAQ и Отработка возражений',
                'title'         => 'Считаем математику владения: абонентка конструктора за 3 года против сайта в вечной собственности',
                'comment'       => 'Инфографика из 6 слайдов с наглядным сравнением затрат',
                'status'        => 'in_progress',
                'platform_tg'   => 1,
                'platform_vc'   => 0,
                'platform_habr' => 0,
                'platform_inst' => 1,
                'platform_setka'=> 0,
                'platform_site' => 0
            ],
            [
                'day_label'     => '6 день',
                'format'        => 'post_setka',
                'rubric'        => '🏎 Скорость и чистый код',
                'title'         => 'Отказ от React на лендингах: почему мы пишем на Vanilla JS и отдаем 100/100 в PageSpeed',
                'comment'       => 'Экспертный пост для IT-сообщества Сетки с бенчмарками и вызовом к дискуссии',
                'status'        => 'ready',
                'platform_tg'   => 1,
                'platform_vc'   => 0,
                'platform_habr' => 1,
                'platform_inst' => 0,
                'platform_setka'=> 1,
                'platform_site' => 0
            ]
        ];

        $stmtIns = $pdo->prepare("
            INSERT INTO content_plan (day_label, format, rubric, title, comment, status, platform_tg, platform_vc, platform_habr, platform_inst, platform_setka, platform_site, created_at)
            VALUES (:day, :fmt, :rub, :title, :comment, :st, :ptg, :pvc, :phabr, :pinst, :psetka, :psite, CURRENT_TIMESTAMP)
        ");

        foreach ($defaultSeed as $row) {
            $stmtIns->execute([
                ':day'     => $row['day_label'],
                ':fmt'     => $row['format'],
                ':rub'     => $row['rubric'],
                ':title'   => $row['title'],
                ':comment' => $row['comment'],
                ':st'      => $row['status'],
                ':ptg'     => $row['platform_tg'],
                ':pvc'     => $row['platform_vc'],
                ':phabr'   => $row['platform_habr'],
                ':pinst'   => $row['platform_inst'],
                ':psetka'  => $row['platform_setka'],
                ':psite'   => $row['platform_site']
            ]);
        }
    }

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
        $rubrics = [
            [
                'name'     => '🏎 Скорость и чистый код vs Конструкторы',
                'tag'      => 'SPEED_VS_BUILDERS',
                'desc'     => 'Разбор косяков Tilda/WordPress, потерянные миллионы из-за скорости загрузки',
                'examples' => [
                    'Почему сайты на Tilda теряют до 40% трафика из рекламы на первом экране',
                    'Замер TTFB и LCP: сравниваем шаблонный сайт с сайтом на чистом коде'
                ]
            ],
            [
                'name'     => '🛠 Кейсы и До/После',
                'tag'      => 'CASE_STUDIES',
                'desc'     => 'Аудиты реальных сайтов, PageSpeed 98+, время загрузки 0.3 сек',
                'examples' => [
                    'Кейс: как мы разогнали сайт клиники до 100/100 и подняли конверсию на 38%',
                    'Редизайн каталога запчастей: убрали 14 лишних скриптов и ускорили рендер'
                ]
            ],
            [
                'name'     => '💰 Бизнес и Конверсия',
                'tag'      => 'CONVERSION_MATH',
                'desc'     => 'Как скорость поднимает продажи на 20-40%, честное SEO без накруток',
                'examples' => [
                    'Калькулятор окупаемости сайта: считаем стоимость лида до и после оптимизации',
                    'Почему поисковики Яндекс и Google отдают приоритет сайтам с чистым кодом'
                ]
            ],
            [
                'name'     => '🎯 Тарифы и Условия',
                'tag'      => 'TARIFFS_OFFER',
                'desc'     => 'Прозрачный прайс 18к–35к+, никакой абонентской платы и скрытых платежей',
                'examples' => [
                    'Что входит в тариф 25 000 рублей: сайт под ключ с гарантией скорости за 7 дней',
                    'Почему у AntiDesigner нет абонентской платы за платформу'
                ]
            ],
            [
                'name'     => '❓ FAQ и Отработка возражений',
                'tag'      => 'FAQ_OBJECTIONS',
                'desc'     => 'Почему чистый код надежнее конструктора и как клиент сам меняет тексты',
                'examples' => [
                    '«А как я сам поменяю телефон на сайте?»: простая инструкция без программиста',
                    'Безопасность сайта: почему чистый HTML нельзя взломать через плагины'
                ]
            ]
        ];

        echo json_encode([
            'status'  => 'success',
            'rubrics' => $rubrics
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

