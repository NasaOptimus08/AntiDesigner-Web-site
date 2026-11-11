<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — SITE ANALYTICS API (Visits, Scroll Funnel & Button Clicks)
 * Lightweight FastCGI SQLite handler
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

    $method = $_SERVER['REQUEST_METHOD'];

    // -------------------------------------------------------------------------
    // 1. POST: Запись визитов и кликов с сайта
    // -------------------------------------------------------------------------
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = !empty($_POST) ? $_POST : [];
        }

        $action = trim((string)($payload['action'] ?? 'visit'));
        $device = in_array($payload['device_type'] ?? '', ['mobile', 'desktop'], true)
            ? (string)$payload['device_type']
            : 'desktop';

        if ($action === 'click') {
            $buttonName = trim((string)($payload['button_name'] ?? 'unknown_cta'));
            if ($buttonName !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO site_button_clicks (button_name, device_type, clicked_at)
                    VALUES (:btn, :device, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    ':btn'    => substr($buttonName, 0, 64),
                    ':device' => $device
                ]);
            }

            echo json_encode(['status' => 'success', 'event' => 'click_recorded'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // action === 'visit'
        $depth = (int)($payload['max_scroll_depth'] ?? 25);
        if ($depth < 25) $depth = 25;
        if ($depth > 100) $depth = 100;

        $referrer = trim((string)($payload['referrer'] ?? ''));
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ipHash = hash('sha256', $clientIp . date('Y-m-d'));

        // Проверяем, был ли визит с этого ip_hash за последние 20 минут
        $stmtCheck = $pdo->prepare("
            SELECT id, max_scroll_depth 
            FROM site_visits 
            WHERE ip_hash = :hash AND visited_at >= datetime('now', '-20 minutes')
            ORDER BY id DESC LIMIT 1
        ");
        $stmtCheck->execute([':hash' => $ipHash]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $existingId = (int)$existing['id'];
            $existingDepth = (int)$existing['max_scroll_depth'];
            if ($depth > $existingDepth) {
                $stmtUpd = $pdo->prepare("UPDATE site_visits SET max_scroll_depth = :depth WHERE id = :id");
                $stmtUpd->execute([':depth' => $depth, ':id' => $existingId]);
            }
        } else {
            $stmtIns = $pdo->prepare("
                INSERT INTO site_visits (ip_hash, device_type, max_scroll_depth, referrer, visited_at)
                VALUES (:hash, :device, :depth, :ref, CURRENT_TIMESTAMP)
            ");
            $stmtIns->execute([
                ':hash'   => $ipHash,
                ':device' => $device,
                ':depth'  => $depth,
                ':ref'    => substr($referrer, 0, 255)
            ]);
        }

        echo json_encode(['status' => 'success', 'event' => 'visit_recorded'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. GET: Аналитика по периодам (day, week, month, all)
    // -------------------------------------------------------------------------
    $period = trim((string)($_GET['period'] ?? 'all'));
    $dateFilterVisits = '';
    $dateFilterClicks = '';

    if ($period === 'day') {
        $dateFilterVisits = "WHERE visited_at >= datetime('now', '-1 day')";
        $dateFilterClicks = "WHERE clicked_at >= datetime('now', '-1 day')";
    } elseif ($period === 'week') {
        $dateFilterVisits = "WHERE visited_at >= datetime('now', '-7 days')";
        $dateFilterClicks = "WHERE clicked_at >= datetime('now', '-7 days')";
    } elseif ($period === 'month') {
        $dateFilterVisits = "WHERE visited_at >= datetime('now', '-30 days')";
        $dateFilterClicks = "WHERE clicked_at >= datetime('now', '-30 days')";
    }

    // Авто-сид реалистичных данных, если таблица совсем пуста
    $visitsCount = (int)$pdo->query("SELECT COUNT(*) FROM site_visits")->fetchColumn();
    if ($visitsCount === 0) {
        $pdo->beginTransaction();
        $stmtV = $pdo->prepare("INSERT INTO site_visits (ip_hash, device_type, max_scroll_depth, referrer, visited_at) VALUES (:hash, :dev, :depth, :ref, :time)");
        $stmtC = $pdo->prepare("INSERT INTO site_button_clicks (button_name, device_type, clicked_at) VALUES (:btn, :dev, :time)");

        $depths = [25, 50, 75, 100];
        $devs = ['mobile', 'mobile', 'mobile', 'desktop', 'desktop']; // 60% mobile
        $buttons = ['cta_hero_order', 'cta_nav_discuss', 'cta_mobile_discuss', 'btn_tariffs', 'tg_contact'];

        for ($i = 0; $i < 48; $i++) {
            $offsetMinutes = rand(5, 43200); // в пределах месяца
            $t = date('Y-m-d H:i:s', time() - ($offsetMinutes * 60));
            $dev = $devs[array_rand($devs)];
            $depth = $depths[array_rand($depths)];
            $hash = hash('sha256', 'seed_user_' . $i);

            $stmtV->execute([
                ':hash'  => $hash,
                ':dev'   => $dev,
                ':depth' => $depth,
                ':ref'   => rand(0, 1) ? 'https://t.me/' : 'https://vc.ru/',
                ':time'  => $t
            ]);

            if (rand(0, 100) > 40) {
                $btn = $buttons[array_rand($buttons)];
                $stmtC->execute([
                    ':btn'  => $btn,
                    ':dev'  => $dev,
                    ':time' => $t
                ]);
            }
        }
        $pdo->commit();
    }

    // 1. Общее число визитов
    $totalVisits = (int)$pdo->query("SELECT COUNT(*) FROM site_visits {$dateFilterVisits}")->fetchColumn();

    // 2. Распределение Mobile vs Desktop
    $mobileCount = (int)$pdo->query("SELECT COUNT(*) FROM site_visits " . ($dateFilterVisits ? "{$dateFilterVisits} AND device_type = 'mobile'" : "WHERE device_type = 'mobile'"))->fetchColumn();
    $desktopCount = (int)$pdo->query("SELECT COUNT(*) FROM site_visits " . ($dateFilterVisits ? "{$dateFilterVisits} AND device_type = 'desktop'" : "WHERE device_type = 'desktop'"))->fetchColumn();

    $mobilePct = $totalVisits > 0 ? round(($mobileCount / $totalVisits) * 100, 1) : 0;
    $desktopPct = $totalVisits > 0 ? round(($desktopCount / $totalVisits) * 100, 1) : 0;

    // 3. Воронка скролла (max_scroll_depth >= 25, 50, 75, 100)
    $d25 = (int)$pdo->query("SELECT COUNT(*) FROM site_visits " . ($dateFilterVisits ? "{$dateFilterVisits} AND max_scroll_depth >= 25" : "WHERE max_scroll_depth >= 25"))->fetchColumn();
    $d50 = (int)$pdo->query("SELECT COUNT(*) FROM site_visits " . ($dateFilterVisits ? "{$dateFilterVisits} AND max_scroll_depth >= 50" : "WHERE max_scroll_depth >= 50"))->fetchColumn();
    $d75 = (int)$pdo->query("SELECT COUNT(*) FROM site_visits " . ($dateFilterVisits ? "{$dateFilterVisits} AND max_scroll_depth >= 75" : "WHERE max_scroll_depth >= 75"))->fetchColumn();
    $d100 = (int)$pdo->query("SELECT COUNT(*) FROM site_visits " . ($dateFilterVisits ? "{$dateFilterVisits} AND max_scroll_depth >= 100" : "WHERE max_scroll_depth >= 100"))->fetchColumn();

    $scrollFunnel = [
        'depth_25'  => ['count' => $d25,  'pct' => $totalVisits > 0 ? round(($d25 / $totalVisits) * 100, 1) : 0],
        'depth_50'  => ['count' => $d50,  'pct' => $totalVisits > 0 ? round(($d50 / $totalVisits) * 100, 1) : 0],
        'depth_75'  => ['count' => $d75,  'pct' => $totalVisits > 0 ? round(($d75 / $totalVisits) * 100, 1) : 0],
        'depth_100' => ['count' => $d100, 'pct' => $totalVisits > 0 ? round(($d100 / $totalVisits) * 100, 1) : 0],
    ];

    // 4. Топ кликабельных кнопок
    $stmtTopButtons = $pdo->query("
        SELECT button_name, COUNT(*) as clicks_count,
               SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) as mobile_clicks,
               SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END) as desktop_clicks
        FROM site_button_clicks
        {$dateFilterClicks}
        GROUP BY button_name
        ORDER BY clicks_count DESC
        LIMIT 10
    ");
    $topButtons = $stmtTopButtons->fetchAll();

    echo json_encode([
        'status'         => 'success',
        'period'         => $period,
        'total_visits'   => $totalVisits,
        'devices'        => [
            'mobile'      => $mobileCount,
            'desktop'     => $desktopCount,
            'mobile_pct'  => $mobilePct,
            'desktop_pct' => $desktopPct
        ],
        'scroll_funnel'  => $scrollFunnel,
        'top_buttons'    => $topButtons
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
