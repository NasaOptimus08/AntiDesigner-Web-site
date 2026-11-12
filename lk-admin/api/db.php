<?php
declare(strict_types=1);

/**
 * AntiDesigner Core - Базовый коннектор к SQLite 3 в режиме FastCGI WAL
 */

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $coreDataDir = dirname(__DIR__, 2) . '/core-data';
    $dbFile = $coreDataDir . '/database.sqlite';
    $schemaFile = $coreDataDir . '/schema.sql';

    if (!is_dir($coreDataDir)) {
        if (!mkdir($coreDataDir, 0755, true) && !is_dir($coreDataDir)) {
            throw new RuntimeException("Не удалось создать директорию: {$coreDataDir}");
        }
    }

    $isNewDb = !file_exists($dbFile) || (file_exists($dbFile) && filesize($dbFile) === 0);

    $dsn = 'sqlite:' . $dbFile;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, null, null, $options);

    // Архитектурные PRAGMA для режима высокой нагрузки без взаимных блокировок
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA foreign_keys = ON;');

    // Автоинициализация схемы таблиц при первом старте
    if ($isNewDb && file_exists($schemaFile)) {
        $schemaSql = file_get_contents($schemaFile);
        if ($schemaSql !== false && trim($schemaSql) !== '') {
            $pdo->exec($schemaSql);
        }
    }

    // Автоматические миграции структуры для существующих БД
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS skill_prompts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                skill_code TEXT NOT NULL,
                title TEXT NOT NULL,
                prompt_type TEXT DEFAULT 'step',
                step_order INTEGER DEFAULT 1,
                content_markdown TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_skill_prompts_code ON skill_prompts(skill_code);
            CREATE INDEX IF NOT EXISTS idx_skill_prompts_active ON skill_prompts(is_active);
        ");

        // Проверяем и добавляем расширенные поля в content_items
        $contentCols = $pdo->query("PRAGMA table_info(content_items)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $neededCols = [
            'plan_id'         => 'INTEGER DEFAULT NULL',
            'storyboard_json' => 'TEXT DEFAULT NULL',
            'media_files'     => 'TEXT DEFAULT NULL',
            'chat_history'    => 'TEXT DEFAULT NULL',
            'audio_url'       => 'TEXT DEFAULT NULL',
            'motion_html'     => 'TEXT DEFAULT NULL'
        ];
        foreach ($neededCols as $colName => $colDef) {
            if (!in_array($colName, $contentCols, true)) {
                $pdo->exec("ALTER TABLE content_items ADD COLUMN {$colName} {$colDef}");
            }
        }
    } catch (Throwable $e) {
        // Фоллбэк логирования миграций без блокировки приложения
    }

    return $pdo;
}

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection or initialization failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

