<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - TRENDS API
 * Radar & Search Intelligence API:
 *  - list: get radar trends with filter (all, approved, pending)
 *  - search / fetch_new: live search via Tavily/Perplexity tool (searchWebTrends)
 *  - approve: mark trend as approved (is_approved = 1)
 *  - delete: remove trend
 *  - send_to_plan: 1-click transfer to interactive content-plan
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
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
    require_once dirname(__DIR__, 2) . '/core-data/tools/search_tool.php';
    require_once dirname(__DIR__, 2) . '/core-data/config.php';
    /** @var PDO $pdo */

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?: [];
    $action = $_GET['action'] ?? $payload['action'] ?? 'list';

    // -------------------------------------------------------------------------
    // 1. LIST: Получить список трендов
    // -------------------------------------------------------------------------
    if ($action === 'list') {
        $filter = trim((string)($_GET['filter'] ?? 'all'));
        $sql = "SELECT id, source_url, title, summary, is_approved, created_at FROM trends ";
        $params = [];

        if ($filter === 'approved') {
            $sql .= "WHERE is_approved = 1 ";
        } elseif ($filter === 'pending') {
            $sql .= "WHERE is_approved = 0 ";
        }

        $sql .= "ORDER BY id DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trends = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'count'  => count($trends),
            'filter' => $filter,
            'trends' => $trends
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. FETCH_NEW / SEARCH: Поиск новых трендов через Search Tool
    // -------------------------------------------------------------------------
    if ($action === 'fetch_new' || $action === 'search') {
        $query = trim((string)($payload['query'] ?? $_GET['query'] ?? ''));
        $config = require dirname(__DIR__, 2) . '/core-data/config.php';

        $searchResult = searchWebTrends($query, $config);
        $savedCount = 0;

        if (!empty($searchResult['results'])) {
            $savedCount = saveTrendsToDatabase($searchResult['results'], $pdo);
        }

        // Загружаем обновленный список
        $stmtAll = $pdo->query("SELECT id, source_url, title, summary, is_approved, created_at FROM trends ORDER BY id DESC LIMIT 20");
        $latestTrends = $stmtAll->fetchAll();

        echo json_encode([
            'status'        => 'success',
            'query'         => $searchResult['query'],
            'ai_summary'    => $searchResult['ai_summary'],
            'new_saved'     => $savedCount,
            'source_type'   => $searchResult['source_type'],
            'latest_trends' => $latestTrends
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. APPROVE: Утвердить инфоповод
    // -------------------------------------------------------------------------
    if ($action === 'approve') {
        $id = (int)($payload['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан ID тренда'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE trends SET is_approved = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Инфоповод утвержден для контент-плана',
            'id'      => $id
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. DELETE: Удалить тренд
    // -------------------------------------------------------------------------
    if ($action === 'delete') {
        $id = (int)($payload['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан ID тренда'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM trends WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Инфоповод удален',
            'id'      => $id
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 5. SEND_TO_PLAN: Перенести тему в Контент-план в 1 клик
    // -------------------------------------------------------------------------
    if ($action === 'send_to_plan') {
        $id = (int)($payload['id'] ?? 0);
        $trendTitle = trim((string)($payload['title'] ?? ''));
        $trendSummary = trim((string)($payload['summary'] ?? ''));

        if ($id > 0 && ($trendTitle === '' || $trendSummary === '')) {
            $stmt = $pdo->prepare("SELECT title, summary FROM trends WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $t = $stmt->fetch();
            if ($t) {
                $trendTitle = $t['title'];
                $trendSummary = $t['summary'];
            }
        }

        if ($trendTitle === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Заголовок тренда не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Помечаем тренд как одобренный
        if ($id > 0) {
            $pdo->prepare("UPDATE trends SET is_approved = 1 WHERE id = :id")->execute([':id' => $id]);
        }

        // Добавляем в content_plan
        $cleanTitle = str_replace(['—', '–'], '-', $trendTitle);
        $cleanComment = str_replace(['—', '–'], '-', $trendSummary);

        $stmtInsert = $pdo->prepare("
            INSERT INTO content_plan (day_label, format, rubric, title, comment, status, platform_tg, platform_vc, platform_setka)
            VALUES ('1 день', 'article', 'Скорость и чистый код', :title, :comment, 'idea', 1, 1, 1)
        ");
        $stmtInsert->execute([
            ':title'   => $cleanTitle,
            ':comment' => $cleanComment
        ]);
        $newPlanId = (int)$pdo->lastInsertId();

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Тема успешно перенесена в Контент-план (Банк идей)',
            'plan_id'  => $newPlanId,
            'title'    => $cleanTitle
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Неизвестный action: ' . htmlspecialchars($action)], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Trends API Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

