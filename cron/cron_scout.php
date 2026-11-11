<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - CRON B2B LEAD SCOUT (Sprint 4)
 * Daily background runner for ISPmanager / Virtual Hosting Cron.
 * Reads candidate queue from core-data/targets_queue.txt,
 * runs two-stage qualification pipeline (Flash-Lite -> Flash Heavy),
 * selects up to 3 qualified leads (deal_score >= 5/10),
 * saves them to cold_outreach, and sends Telegram alert to admin.
 * Uses flock to prevent overlapping runs.
 * =============================================================================
 */

// Разрешаем выполнение в CLI или веб-вызовом Cron
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$coreDataDir = dirname(__DIR__) . '/core-data';
$lockFile = $coreDataDir . '/cron_scout.lock';
$queueFile = $coreDataDir . '/targets_queue.txt';

// Блокировка от наложения процессов (flock)
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

    echo "[" . date('Y-m-d H:i:s') . "] === Starting B2B Lead Scout Cron ===\n";

    // Проверяем текущее количество лидов за сегодня
    $stmtQuota = $pdo->query("SELECT COUNT(*) FROM cold_outreach WHERE date(created_at) = date('now')");
    $todayCount = (int)$stmtQuota->fetchColumn();
    $dailyQuota = 3;

    echo "[" . date('Y-m-d H:i:s') . "] Qualified leads today: {$todayCount} / {$dailyQuota}\n";

    if ($todayCount >= $dailyQuota) {
        echo "[" . date('Y-m-d H:i:s') . "] Daily quota already fulfilled ({$todayCount}/{$dailyQuota}). Nothing to do.\n";
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(0);
    }

    if (!file_exists($queueFile)) {
        echo "[" . date('Y-m-d H:i:s') . "] Queue file not found: {$queueFile}\n";
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(0);
    }

    $lines = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || empty($lines)) {
        echo "[" . date('Y-m-d H:i:s') . "] Targets queue is empty.\n";
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(0);
    }

    $newLeadsCount = 0;
    $remainingLines = [];

    foreach ($lines as $line) {
        $url = trim($line);
        if ($url === '' || str_starts_with($url, '#')) {
            continue;
        }

        if ($todayCount >= $dailyQuota) {
            $remainingLines[] = $url;
            continue;
        }

        echo "[" . date('Y-m-d H:i:s') . "] Processing target: {$url} ... ";
        $result = $scoutEngine->analyzeTarget($url);

        $status = $result['status'] ?? 'error';
        if ($status === 'success') {
            $newLeadsCount++;
            $todayCount++;
            $score = $result['deal_score'] ?? 5;
            $company = $result['lead']['company_name'] ?? 'Компания';
            echo "QUALIFIED! Score: {$score}/10 [{$company}]\n";
        } elseif ($status === 'rejected') {
            $score = $result['deal_score'] ?? 0;
            echo "REJECTED (Score: {$score}/10, threshold: 5)\n";
        } elseif ($status === 'exists') {
            echo "ALREADY PROCESSED\n";
        } else {
            $msg = $result['message'] ?? 'Unknown error';
            echo "ERROR: {$msg}\n";
            $remainingLines[] = $url;
        }

        // Небольшая пауза между запросами для щадящего режима CPU
        usleep(300000); // 0.3 сек
    }

    // Обновляем очередь: удаляем отработанные сайты
    if (!empty($remainingLines)) {
        file_put_contents($queueFile, implode("\n", $remainingLines) . "\n", LOCK_EX);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Cron finished. Added: {$newLeadsCount} new leads. Total today: {$todayCount}/{$dailyQuota}\n";

    // Отправка Telegram-уведомления администратору, если найдены новые лиды
    if ($newLeadsCount > 0) {
        $tgToken = trim((string)($config['telegram_bot_token'] ?? ''));
        $adminChatId = trim((string)($config['admin_telegram_id'] ?? ''));

        if ($tgToken !== '' && $adminChatId !== '') {
            $text = "🎯 *B2B-Скаут AntiDesigner*\n\n"
                  . "Отобрано целевых лидов на сегодня: *{$todayCount} из {$dailyQuota}* (добавлено: +{$newLeadsCount}).\n"
                  . "Перейдите в панель управления для проверки технических аудитов и отправки питчей в Telegram.";

            $tgUrl = "https://api.telegram.org/bot{$tgToken}/sendMessage";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $tgUrl,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'chat_id'    => $adminChatId,
                    'text'       => $text,
                    'parse_mode' => 'Markdown'
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            curl_exec($ch);
            curl_close($ch);
            echo "[" . date('Y-m-d H:i:s') . "] Telegram alert sent to admin.\n";
        }
    }

} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Cron Fatal Error: " . $e->getMessage() . "\n";
} finally {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
}
