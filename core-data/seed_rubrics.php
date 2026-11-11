<?php
declare(strict_types=1);

require_once __DIR__ . '/../lk-admin/api/db.php';
/** @var PDO $pdo */

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

    $stmt = $pdo->prepare("INSERT INTO rubrics (title, description, examples) VALUES (:title, :desc, :examples)");
    foreach ($seedRubrics as $r) {
        $stmt->execute([
            ':title'    => $r['title'],
            ':desc'     => $r['description'],
            ':examples' => $r['examples']
        ]);
    }
    echo "Seeded " . count($seedRubrics) . " rubrics successfully.\n";
} else {
    echo "Rubrics table already has {$count} records.\n";
}
