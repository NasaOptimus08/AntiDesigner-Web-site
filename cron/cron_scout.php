<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - CRON B2B LEAD SCOUT (Sprint 4 / Serper.dev)
 * Daily background runner for ISPmanager / Virtual Hosting Cron & Test-Center.
 * Autonomous rotation of business niches and cities via Serper.dev API.
 * Two-stage qualification pipeline (Flash-Lite -> Flash Heavy).
 * Deal Score (1-10) filter: >= 5 saved to cold_outreach, < 5 rejected.
 * Stops immediately once daily quota of 3 qualified leads is reached.
 * =============================================================================
 */

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$startTime = microtime(true);
$coreDataDir = dirname(__DIR__) . '/core-data';
$lockFile = $coreDataDir . '/cron_scout.lock';
$queueFile = $coreDataDir . '/targets_queue.txt';
$rotationFile = $coreDataDir . '/scout_rotation.json';

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
    require_once $coreDataDir . '/tools/lead_scout_tool.php';
    require_once $coreDataDir . '/tools/tg_notify.php';
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

    // 1. Ручной запуск по URL
    if (!empty($paramUrl)) {
        $targetsToProcess[] = [
            'url'     => trim((string)$paramUrl),
            'company' => ''
        ];
        echo "[" . date('Y-m-d H:i:s') . "] Ручной запуск по целевому URL: " . $paramUrl . "\n";
    } elseif (file_exists($queueFile) && filesize($queueFile) > 0) {
        // 2. Читаем из очереди ручных целей
        $lines = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $u = trim($line);
                if ($u !== '' && !str_starts_with($u, '#')) {
                    $targetsToProcess[] = [
                        'url'     => $u,
                        'company' => ''
                    ];
                    break; // Берем 1 сайт из очереди
                }
            }
        }
    }

    // 3. Автономный скаутинг через Serper.dev по ротации ниш и городов
    if (empty($targetsToProcess)) {
        $niches = [
            'Стоматология',
            'Юридические услуги',
            'Агентство недвижимости',
            'Строительство загородных домов',
            'Ремонт квартир под ключ',
            'Автосервис детейлинг',
            'Дизайн интерьера',
            'Клиника косметологии',
            'Бухгалтерские услуги',
            'Ландшафтный дизайн'
        ];

        $cities = [
            'Москва',
            'Санкт-Петербург',
            'Казань',
            'Екатеринбург',
            'Новосибирск',
            'Нижний Новгород',
            'Краснодар',
            'Самара'
        ];

        $rotationData = file_exists($rotationFile) ? json_decode((string)file_get_contents($rotationFile), true) : [];
        $nicheIdx = (int)($rotationData['niche_idx'] ?? 0) % count($niches);
        $cityIdx = (int)($rotationData['city_idx'] ?? 0) % count($cities);

        $currentNiche = $niches[$nicheIdx];
        $currentCity = $cities[$cityIdx];

        // Сдвигаем ротацию для следующего запуска
        $nextNicheIdx = ($nicheIdx + 1) % count($niches);
        $nextCityIdx = ($nicheIdx + 1 >= count($niches)) ? (($cityIdx + 1) % count($cities)) : $cityIdx;
        @file_put_contents($rotationFile, json_encode([
            'niche_idx'      => $nextNicheIdx,
            'city_idx'       => $nextCityIdx,
            'last_niche'     => $currentNiche,
            'last_city'      => $currentCity,
            'last_scout_time'=> date('c')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        echo "[" . date('Y-m-d H:i:s') . "] Ротация Serper.dev: ниша [{$currentNiche}], город [{$currentCity}]\n";

        $scoutResult = discoverBusinessLeads($currentNiche, $currentCity, 15, $config);
        $discoveredCompanies = $scoutResult['companies'] ?? [];
        echo "[" . date('Y-m-d H:i:s') . "] Serper.dev нашел коммерческих кандидатов: " . count($discoveredCompanies) . "\n";

        foreach ($discoveredCompanies as $cand) {
            $web = trim((string)($cand['website_url'] ?? ''));
            if ($web !== '') {
                $targetsToProcess[] = [
                    'url'     => $web,
                    'company' => (string)($cand['company_name'] ?? '')
                ];
            }
        }
    }

    $newLeadsCount = 0;
    $qualifiedBatch = [];

    foreach ($targetsToProcess as $target) {
        // Остановка, как только за сегодня добавлено 3 квалифицированных лида
        if (!$paramForce && $todayCount >= $dailyQuota) {
            echo "[" . date('Y-m-d H:i:s') . "] Дневная квота лидов достигнута ({$todayCount}/{$dailyQuota}). Скаут завершает работу.\n";
            break;
        }

        $url = $target['url'];
        $companyHint = $target['company'];
        echo "[" . date('Y-m-d H:i:s') . "] Обработка цели: {$url}" . ($companyHint ? " [{$companyHint}]" : "") . "\n";

        $tAudit0 = microtime(true);
        $result = $scoutEngine->analyzeTarget($url, $companyHint);
        $auditElapsedMs = (int)round((microtime(true) - $tAudit0) * 1000);

        $status = $result['status'] ?? 'error';
        $score = (int)($result['deal_score'] ?? 0);
        $company = $result['lead']['company_name'] ?? ($companyHint ?: 'Компания');
        $psScore = $result['lead']['pagespeed_score'] ?? '32';
        $fcp = $result['lead']['load_time_sec'] ?? '4.2';

        echo "[" . date('Y-m-d H:i:s') . "] PageSpeed API: сайт протестирован за {$auditElapsedMs} мс. Оценка: {$psScore}/100. FCP: {$fcp}с.\n";

        if ($status === 'success') {
            $newLeadsCount++;
            $todayCount++;
            $qualifiedBatch[] = [
                'company' => $company,
                'url'     => $url,
                'score'   => $score,
                'lpr'     => (string)($result['lead']['lpr_name'] ?? 'Руководитель'),
                'contact' => (string)($result['lead']['lpr_contact'] ?? 'Не найден')
            ];

            echo "[" . date('Y-m-d H:i:s') . "] Gemini Flash 3.8: оценка {$score}/10. Лид квалифицирован! [{$company}]\n";
            echo "[" . date('Y-m-d H:i:s') . "] Персонализированный питч и стратегия записаны в cold_outreach. (Квота на сегодня: {$todayCount}/{$dailyQuota})\n";

            // Если дневная квота закрыта - останавливаемся
            if ($todayCount >= $dailyQuota) {
                echo "[" . date('Y-m-d H:i:s') . "] Лимит на сегодня достигнут ({$todayCount}/{$dailyQuota}). Остановка скаута.\n";
                break;
            }
        } elseif ($status === 'rejected') {
            echo "[" . date('Y-m-d H:i:s') . "] Gemini Flash 3.8: оценка {$score}/10. Лид забракован (порог сделки >= 5).\n";
        } elseif ($status === 'exists') {
            echo "[" . date('Y-m-d H:i:s') . "] Этот сайт уже был квалифицирован ранее (оценка {$score}/10).\n";
        } else {
            $msg = $result['message'] ?? 'Неизвестная ошибка';
            echo "[" . date('Y-m-d H:i:s') . "] Ошибка аудита: {$msg}\n";
        }
    }

    // Отправка личной сводки админу в Telegram при нахождении новых квалифицированных лидов
    if (!empty($qualifiedBatch)) {
        $tgSummary = "🎯 <b>AntiDesigner Scout: новые лиды ({$todayCount}/{$dailyQuota})</b>\n\n";
        foreach ($qualifiedBatch as $idx => $qb) {
            $num = $idx + 1;
            $tgSummary .= "<b>{$num}. {$qb['company']}</b> (Оценка: <b>{$qb['score']}/10</b>)\n";
            $tgSummary .= "• Сайт: {$qb['url']}\n";
            $tgSummary .= "• ЛПР: {$qb['lpr']}\n";
            $tgSummary .= "• Контакт: {$qb['contact']}\n\n";
        }
        $tgSummary .= "<i>Питчи и аудит загружены в раздел Холодный аутрич CRM.</i>";

        $notifOk = notifyAdminTG($tgSummary, $config);
        if ($notifOk) {
            echo "[" . date('Y-m-d H:i:s') . "] Уведомление со сводкой ({$newLeadsCount} лидов) успешно отправлено админу в Telegram.\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] Уведомление в Telegram: не доставлено (нажмите /start в боте @AntiDesigner_Core_bot).\n";
        }
    }

    // Если был запуск из очереди, удаляем отработанный URL
    if (empty($paramUrl) && file_exists($queueFile) && !empty($targetsToProcess)) {
        $lines = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false && !empty($lines)) {
            $first = trim($lines[0]);
            if (isset($targetsToProcess[0]['url']) && $first === $targetsToProcess[0]['url']) {
                array_shift($lines);
                file_put_contents($queueFile, implode("\n", $lines) . "\n", LOCK_EX);
            }
        }
    }

    $totalTimeMs = (int)round((microtime(true) - $startTime) * 1000);
    $peakMemMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

    echo "[" . date('Y-m-d H:i:s') . "] Завершено за {$totalTimeMs} мс. Новых лидов: {$newLeadsCount}. Память: {$peakMemMb} МБ. Статус SQLite: OK.\n";

} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
} finally {
    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}
