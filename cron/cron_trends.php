<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - CRON TRENDS HUNTER (Sprint 4 / TestLab)
 * Daily background runner for Tavily / Radar search & AI topic filtration.
 * Stage: Search raw tech trends -> Filter via MODEL_FAST (Flash-Lite 3.5) ->
 * Save to SQLite table `trends`.
 * Uses flock to prevent overlapping runs.
 * =============================================================================
 */

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$startTime = microtime(true);
$coreDataDir = dirname(__DIR__) . '/core-data';
$lockFile = $coreDataDir . '/cron_trends.lock';

// Блокировка от наложения процессов
$lockFp = fopen($lockFile, 'c+');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Process already running. Exiting.\n";
    exit(0);
}

try {
    require_once dirname(__DIR__) . '/lk-admin/api/db.php';
    require_once $coreDataDir . '/tools/search_tool.php';
    require_once $coreDataDir . '/ai_router.php';
    /** @var PDO $pdo */

    $configFile = $coreDataDir . '/config.php';
    $config = file_exists($configFile) ? require $configFile : [];
    $aiRouter = new AIRouter($config);

    echo "[" . date('Y-m-d H:i:s') . "] === Запуск задачи cron_trends.php ===\n";

    // 1. Поиск сырых инфоповодов (Tavily Search API / Curated Radar)
    $t0 = microtime(true);
    $searchRes = searchWebTrends('', $config);
    $searchTimeMs = (int)round((microtime(true) - $t0) * 1000);

    $rawCount = (int)($searchRes['count'] ?? 0);
    $rawResults = (array)($searchRes['results'] ?? []);
    $sourceType = (string)($searchRes['source_type'] ?? 'tavily_api');

    echo "[" . date('Y-m-d H:i:s') . "] {$sourceType}: получено {$rawCount} сырых инфоповодов за {$searchTimeMs} мс.\n";

    if (empty($rawResults)) {
        echo "[" . date('Y-m-d H:i:s') . "] Нет доступных инфоповодов для обработки.\n";
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(0);
    }

    // 2. Фильтрация через MODEL_FAST (Flash-Lite 3.5, ключ AQ...)
    $t1 = microtime(true);
    $filteredTrends = [];

    if ($aiRouter->isFastKeyConfigured()) {
        $filterPrompt = "СЫРЫЕ ИНФОПОВОДЫ:\n" . json_encode($rawResults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $sysPrompt = <<<SYS
Ты - AI-редактор контент-плана независимого Senior-разработчика Евгения Антипина (AntiDesigner).
Моя ниша: скоростная веб-разработка на чистом коде против медленных конструкторов Tilda/WordPress и раздутых агентств.
Твоя задача: отобрать из сырых новостей 2-3 самых острых и коммерчески ценных инфоповода (боли медленной загрузки, LCP/INP, алгоритмы соцсетей Сетка/TG, выгода работы напрямую с разработчиком-одиночкой).
Строго соблюдай позиционирование: пиши от первого лица («Я»), запрещено использовать «мы», «наша студия».
Отсей нерелевантный мусор и спам.
Запрещено использовать длинные тире (символ —). Используй только дефис (-).
Верни СТРОГО JSON-массив объектов без markdown-тегов:
[
  {
    "title": "Острый заголовок новости",
    "summary": "Суть инфоповода и почему это важно клиенту (2-3 предложения)",
    "source_url": "URL источника",
    "is_approved": 1
  }
]
SYS;
        try {
            $aiResponse = $aiRouter->call($filterPrompt, $sysPrompt, AIRouter::MODEL_FAST, 0.4);
            $cleanJson = trim($aiResponse);
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
            $cleanJson = preg_replace('/\s*```$/i', '', $cleanJson);
            $parsed = json_decode((string)$cleanJson, true);

            if (is_array($parsed) && !empty($parsed)) {
                $filteredTrends = $parsed;
            }
        } catch (Throwable $aiErr) {
            echo "[" . date('Y-m-d H:i:s') . "] Внимание: AI фильтрация вернула ошибку (" . $aiErr->getMessage() . "). Используем прямой поток.\n";
        }
    }

    $aiFilterTimeMs = (int)round((microtime(true) - $t1) * 1000);

    // Если AI вернул пустой результат или офлайн - берем топ-3 из поиска
    if (empty($filteredTrends)) {
        $filteredTrends = array_slice($rawResults, 0, 3);
        foreach ($filteredTrends as &$item) {
            $item['is_approved'] = 1;
        }
        unset($item);
    }

    $filteredCount = count($filteredTrends);
    $rejectedCount = max(0, $rawCount - $filteredCount);
    echo "[" . date('Y-m-d H:i:s') . "] Gemini Flash-Lite 3.5 (AQ...): отобрано {$filteredCount} целевых тренда (отсеяно {$rejectedCount}) за {$aiFilterTimeMs} мс.\n";

    // 3. Сохранение в SQLite базу (таблица trends)
    $savedCount = saveTrendsToDatabase($filteredTrends, $pdo);
    echo "[" . date('Y-m-d H:i:s') . "] Сохранено в SQLite (таблица trends): {$savedCount} новых инфоповодов.\n";

    $totalTimeMs = (int)round((microtime(true) - $startTime) * 1000);
    $peakMemMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

    echo "[" . date('Y-m-d H:i:s') . "] Успешно завершено за {$totalTimeMs} мс. Память: {$peakMemMb} МБ. Статус SQLite: OK.\n";

} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
} finally {
    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

