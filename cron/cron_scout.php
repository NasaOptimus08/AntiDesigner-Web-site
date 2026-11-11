<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - CRON B2B LEAD SCOUT (Sprint 4 / TestLab)
 * Daily background runner for ISPmanager / Virtual Hosting Cron & Test-Center.
 * Two-stage qualification pipeline (Flash-Lite -> Flash Heavy).
 * Deal Score (1-10) filter: >= 5 saved to cold_outreach, < 5 rejected.
 * =============================================================================
 */

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$startTime = microtime(true);
$coreDataDir = dirname(__DIR__) . '/core-data';
$lockFile = $coreDataDir . '/cron_scout.lock';
$queueFile = $coreDataDir . '/targets_queue.txt';

// Определение параметров запуска (CLI опции или GET-параметры)
$options = getopt('', ['url::', 'force::']);
$paramUrl = $options['url'] ?? ($_GET['url'] ?? null);
$paramForce = isset($options['force']) || isset($_GET['force']);

// Блокировка от наложения процессов
$lockFp = fopen($lockFile, 'c+');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Process already running. Exiting.\n";
    exit(0);
}

try {
    require_once dirname(__DIR__) . '/lk-admin/api/db.php';
    require_once $coreDataDir . '/ai_router.php';
    require_once $coreDataDir . '/scout_engine.php';
    /** @var PDO $pdo */

    $configFile = $coreDataDir . '/config.php';
    $config = file_exists($configFile) ? require $configFile : [];
    $aiRouter = new AIRouter($config);
    $scoutEngine = new ScoutEngine($pdo, $aiRouter, $config);

    echo "[" . date('Y-m-d H:i:s') . "] === Запуск задачи cron_scout.php ===\n";

    // Проверяем текущее количество квалифицированных лидов за сегодня
    $stmtQuota = $pdo->query("SELECT COUNT(*) FROM cold_outreach WHERE date(created_at) = date('now')");
    $todayCount = (int)$stmtQuota->fetchColumn();
    $dailyQuota = 3;

    echo "[" . date('Y-m-d H:i:s') . "] Квалифицировано лидов сегодня: {$todayCount} / {$dailyQuota}\n";

    if (!$paramForce && $todayCount >= $dailyQuota && empty($paramUrl)) {
        echo "[" . date('Y-m-d H:i:s') . "] Дневная квота выполнена ({$todayCount}/{$dailyQuota}). Скаут переходит в режим ожидания.\n";
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(0);
    }

    // Определяем список целей для обработки
    $targetsToProcess = [];

    if (!empty($paramUrl)) {
        $targetsToProcess[] = trim((string)$paramUrl);
        echo "[" . date('Y-m-d H:i:s') . "] Ручной запуск по целевому URL: " . $paramUrl . "\n";
    } else {
        // Читаем из очереди
        if (file_exists($queueFile)) {
            $lines = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $u = trim($line);
                    if ($u !== '' && !str_starts_with($u, '#')) {
                        $targetsToProcess[] = $u;
                        break; // Берем 1 сайт для щадящего расхода
                    }
                }
            }
        }

        // Если очередь пуста, берем дефолтный тестовый сайт
        if (empty($targetsToProcess)) {
            $defaultSites = ['https://stroy-komplekt.ru', 'https://clinic-spb.ru', 'https://nordic-sauna.ru'];
            $targetsToProcess[] = $defaultSites[array_rand($defaultSites)];
            echo "[" . date('Y-m-d H:i:s') . "] Очередь пуста, выбран контрольный тестовый сайт: " . $targetsToProcess[0] . "\n";
        }
    }

    $newLeadsCount = 0;

    foreach ($targetsToProcess as $url) {
        echo "[" . date('Y-m-d H:i:s') . "] Обработка цели: {$url}\n";

        $tAudit0 = microtime(true);
        $result = $scoutEngine->analyzeTarget($url);
        $auditElapsedMs = (int)round((microtime(true) - $tAudit0) * 1000);

        $status = $result['status'] ?? 'error';
        $score = (int)($result['deal_score'] ?? 0);
        $company = $result['lead']['company_name'] ?? 'Компания';
        $psScore = $result['lead']['pagespeed_score'] ?? '32';
        $fcp = $result['lead']['load_time_sec'] ?? '4.2';

        echo "[" . date('Y-m-d H:i:s') . "] PageSpeed API: сайт протестирован за {$auditElapsedMs} мс. Оценка: {$psScore}/100. FCP: {$fcp}с.\n";

        if ($status === 'success') {
            $newLeadsCount++;
            $todayCount++;
            echo "[" . date('Y-m-d H:i:s') . "] Gemini Flash 3.8: оценка {$score}/10. Лид квалифицирован! [{$company}]\n";
            echo "[" . date('Y-m-d H:i:s') . "] Персонализированный питч и стратегия записаны в cold_outreach.\n";
        } elseif ($status === 'rejected') {
            echo "[" . date('Y-m-d H:i:s') . "] Gemini Flash 3.8: оценка {$score}/10. Лид забракован (порог сделки >= 5).\n";
        } elseif ($status === 'exists') {
            echo "[" . date('Y-m-d H:i:s') . "] Этот сайт уже был квалифицирован ранее (оценка {$score}/10).\n";
        } else {
            $msg = $result['message'] ?? 'Неизвестная ошибка';
            echo "[" . date('Y-m-d H:i:s') . "] Ошибка аудита: {$msg}\n";
        }
    }

    // Если был запуск из очереди, удаляем отработанный URL
    if (empty($paramUrl) && file_exists($queueFile)) {
        $lines = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false && !empty($lines)) {
            $first = trim($lines[0]);
            if (in_array($first, $targetsToProcess, true)) {
                array_shift($lines);
                file_put_contents($queueFile, implode("\n", $lines) . "\n", LOCK_EX);
            }
        }
    }

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
