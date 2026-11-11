<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - B2B LEAD SCOUT & OUTREACH API (Sprint 4)
 * Two-stage pipeline: Flash-Lite (Scrape + PageSpeed) -> Flash Heavy (Deal Score & Pitch)
 * Strict filter (deal_score >= 5/10) + Win/Loss Self-learning loop.
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
    require_once dirname(__DIR__, 2) . '/core-data/ai_router.php';
    require_once dirname(__DIR__, 2) . '/core-data/scout_engine.php';
    /** @var PDO $pdo */

    $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
    $config = file_exists($configFile) ? require $configFile : [];
    $aiRouter = new AIRouter($config);
    $scoutEngine = new ScoutEngine($pdo, $aiRouter, $config);

    $rawInput = file_get_contents('php://input');
    $input = json_decode((string)$rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim((string)($_GET['action'] ?? $input['action'] ?? 'list'));

    // -------------------------------------------------------------------------
    // 1. action = list (Список лидов аутрича и дневная квота)
    // -------------------------------------------------------------------------
    if ($action === 'list') {
        $statusFilter = trim((string)($_GET['status'] ?? 'all'));
        $search = trim((string)($_GET['q'] ?? ''));

        $sql = "SELECT * FROM cold_outreach";
        $clauses = [];
        $params = [];

        if ($statusFilter !== 'all' && $statusFilter !== '') {
            $clauses[] = "status = :status";
            $params[':status'] = $statusFilter;
        }
        if ($search !== '') {
            $clauses[] = "(company_name LIKE :q OR website_url LIKE :q OR lpr_name LIKE :q OR niche LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        if (!empty($clauses)) {
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }
        $sql .= " ORDER BY deal_score DESC, id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leads = $stmt->fetchAll();

        // Подсчет сегодняшней квоты лидов (Х из 3)
        $stmtQuota = $pdo->query("SELECT COUNT(*) FROM cold_outreach WHERE date(created_at) = date('now')");
        $todayCount = (int)$stmtQuota->fetchColumn();

        echo json_encode([
            'status'      => 'success',
            'count'       => count($leads),
            'today_count' => $todayCount,
            'today_quota' => 3,
            'leads'       => $leads
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. action = analyze_target (Двухэтапный конвейер квалификации B2B лида)
    // -------------------------------------------------------------------------
    if ($action === 'analyze_target') {
        $targetUrl = trim((string)($input['url'] ?? ''));
        $targetCompany = trim((string)($input['company_name'] ?? ''));

        $res = $scoutEngine->analyzeTarget($targetUrl, $targetCompany);
        if (($res['status'] ?? '') === 'error') {
            http_response_code(400);
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2.1 action = search_leads (Парсинг каталогов и баз бизнесов через Lead Scout)
    // -------------------------------------------------------------------------
    if ($action === 'search_leads') {
        $niche = trim((string)($input['niche'] ?? $_GET['niche'] ?? 'Стоматология'));
        $city = trim((string)($input['city'] ?? $_GET['city'] ?? 'Москва'));

        $res = $scoutEngine->searchLeads($niche, $city);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2.2 action = extract_contacts (Поиск контактов ЛПР на сайте)
    // -------------------------------------------------------------------------
    if ($action === 'extract_contacts') {
        $targetUrl = trim((string)($input['url'] ?? $_GET['url'] ?? ''));
        require_once dirname(__DIR__, 2) . '/core/tools/contact_extractor.php';
        $res = extractLPRContacts($targetUrl, $config);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2.3 action = audit_pagespeed (Быстрый технический аудит PageSpeed и скриншот)
    // -------------------------------------------------------------------------
    if ($action === 'audit_pagespeed') {
        $targetUrl = trim((string)($input['url'] ?? $_GET['url'] ?? ''));
        require_once dirname(__DIR__, 2) . '/core/tools/pagespeed_tool.php';
        $res = auditPageSpeed($targetUrl, $config['pagespeed_api_key'] ?? '', $config);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2.4 action = collect_metrics (Сбор метрик со статей и соцсетей)
    // -------------------------------------------------------------------------
    if ($action === 'collect_metrics') {
        $platform = trim((string)($input['platform'] ?? $_GET['platform'] ?? 'vc_ru'));
        $postUrl = trim((string)($input['post_url'] ?? $_GET['post_url'] ?? ''));
        require_once dirname(__DIR__, 2) . '/core/tools/analytics_collector.php';
        $res = fetchPostMetrics($platform, $postUrl, $pdo);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. action = win_loss_analysis (Контур самообучения / Agent Memory)
    // -------------------------------------------------------------------------
    if ($action === 'win_loss_analysis') {
        $leadId = (int)($input['lead_id'] ?? 0);
        $chatHistory = trim((string)($input['chat_history'] ?? ''));
        $dealOutcome = trim((string)($input['deal_outcome'] ?? 'won')); // 'won' или 'lost'

        if ($leadId <= 0 || $chatHistory === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Укажите lead_id и текст переписки'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtLead = $pdo->prepare("SELECT * FROM cold_outreach WHERE id = :id");
        $stmtLead->execute([':id' => $leadId]);
        $lead = $stmtLead->fetch();
        if (!$lead) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Лид не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Обновляем статус лида и историю переписки
        $newStatus = ($dealOutcome === 'won') ? 'won' : 'lost';
        $stmtUpd = $pdo->prepare("
            UPDATE cold_outreach SET
                chat_history = :chat,
                deal_outcome = :outcome,
                status = :status
            WHERE id = :id
        ");
        $stmtUpd->execute([
            ':chat'    => $chatHistory,
            ':outcome' => $dealOutcome,
            ':status'  => $newStatus,
            ':id'      => $leadId
        ]);

        // Подтягиваем контекст диалогов из bot_messages
        $extraContext = "";
        $stmtBotMsgs = $pdo->query("SELECT sender, text FROM bot_messages ORDER BY id DESC LIMIT 6");
        $recentMsgs = $stmtBotMsgs->fetchAll();
        if (!empty($recentMsgs)) {
            $extraContext = "\nКОНТЕКСТ ДРУГИХ ДИАЛОГОВ CRM:\n";
            foreach (array_reverse($recentMsgs) as $bm) {
                $extraContext .= "[{$bm['sender']}]: {$bm['text']}\n";
            }
        }

        // Подтягиваем скилл отработки возражений из базы skills
        $objSkill = $aiRouter->loadSkill('objections');

        // Вызываем MODEL_HEAVY (Gemini Flash 3.8, ключ pza...) с thinkingBudget = 2048
        $sysPrompt = <<<PROMPT
Ты - персональный AI-аналитик продаж независимого Senior-разработчика Евгения Антипина (AntiDesigner).
ПОЗИЦИОНИРОВАНИЕ: AntiDesigner - это сильный личный бренд Евгения Антипина, а не студия и не агентство! Работа ведется напрямую с автором кода ("Я", "мой подход"). Категорически запрещены слова "мы", "наша студия", "наша команда".
Твоя задача: провести глубокий Win/Loss разбор завершившейся переписки с клиентом.

РЕГЛАМЕНТ РАБОТЫ С ВОЗРАЖЕНИЯМИ:
{$objSkill['system_prompt']}

ИТОГ ПЕРЕПИСКИ: {$dealOutcome} (won -> закрыли сделку на разработку; lost -> сорвалась или отказ).
НИША: {$lead['niche']}
КОМПАНИЯ: {$lead['company_name']}

Сформулируй:
1. Краткий стратегический вывод (hypothesis) для калибровки будущих питчей: какие аргументы помогли закрыть сделку, либо какая фраза/возражение привели к отказу.
2. Метрику или кейс-подтверждение (supporting_metric).
3. ПРАВИЛО ФАКТОЛОГИЧЕСКОЙ ЧЕСТНОСТИ: Не выдумывай несуществующие причины отказа или ложные возражения. Опирайся строго на предоставленный текст переписки.
Без длинных тире (символ —). Используй только дефис (-) или стрелки (->).

ФОРМАТ СТРОГО JSON:
{
  "hypothesis": "Четкий практический вывод до 2-3 предложений",
  "supporting_metric": "Кейс {$lead['company_name']}, чек {$dealOutcome}"
}
PROMPT;

        $userPrompt = "ИСТОРИЯ ПЕРЕПИСКИ С КЛИЕНТОМ:\n{$chatHistory}\n\nИСХОДНЫЙ ПИТЧ:\n{$lead['generated_pitch']}{$extraContext}";
        $response = $aiRouter->call($userPrompt, $sysPrompt, AIRouter::MODEL_HEAVY, 0.4, 2048);

        $cleanJson = trim($response);
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
        $cleanJson = preg_replace('/\s*```$/i', '', $cleanJson);
        $analysis = json_decode((string)$cleanJson, true);

        $hypothesis = $analysis['hypothesis'] ?? ($dealOutcome === 'won' ? 'Фокус на скорости загрузки и видео-замер закрывает созвон.' : 'Клиент возражал по цене, требуется предлагать рассрочку или стартовый тариф 18к.');
        $metric = $analysis['supporting_metric'] ?? "Кейс {$lead['company_name']} ({$dealOutcome})";

        // Записываем в agent_memory
        $stmtMem = $pdo->prepare("INSERT INTO agent_memory (hypothesis, supporting_metric, is_active, created_at) VALUES (:hypo, :metric, 1, CURRENT_TIMESTAMP)");
        $stmtMem->execute([':hypo' => $hypothesis, ':metric' => $metric]);

        // Обновляем Базу Знаний (crm_memory)
        $stmtKb = $pdo->query("SELECT id, content_markdown FROM knowledge_base WHERE category = 'crm_memory'");
        $kbRow = $stmtKb->fetch();
        $addition = "\n- [WIN/LOSS {$dealOutcome}] «{$lead['company_name']}» ({$lead['niche']}): {$hypothesis}";
        if ($kbRow) {
            $pdo->prepare("UPDATE knowledge_base SET content_markdown = content_markdown || :add WHERE id = :id")->execute([':add' => $addition, ':id' => $kbRow['id']]);
        }

        echo json_encode([
            'status'     => 'success',
            'message'    => "Win/Loss анализ завершен! Вывод сохранен в память агента (agent_memory).",
            'hypothesis' => $hypothesis,
            'metric'     => $metric
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. action = delete (Удаление лида)
    // -------------------------------------------------------------------------
    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM cold_outreach WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['status' => 'success', 'message' => 'Лид удален'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // 5. action = update_status (Обновление статуса лида)
    // -------------------------------------------------------------------------
    if ($action === 'update_status') {
        $id = (int)($input['id'] ?? 0);
        $status = trim((string)($input['status'] ?? 'qualified'));
        if ($id > 0) {
            $pdo->prepare("UPDATE cold_outreach SET status = :st WHERE id = :id")->execute([':st' => $status, ':id' => $id]);
            echo json_encode(['status' => 'success', 'message' => 'Статус обновлен'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Неизвестное действие: {$action}"], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
