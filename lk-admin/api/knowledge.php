<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — KNOWLEDGE BASE API (RAG Context & Memory)
 * 4 Categories: leads_archive, crm_memory, scraping_logs, content_rules
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

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Функция засеивания 4 интерактивных категорий
    $seedCategories = static function (PDO $pdo): void {
        $defaults = [
            [
                'category' => 'leads_archive',
                'title' => 'База холодных лидов и аудитов',
                'content_markdown' => <<<MD
# БАЗА ХОЛОДНЫХ ЛИДОВ И ТЕХНИЧЕСКИХ АУДИТОВ

### Лид 01: «СпецТрансЛогистик» (Грузоперевозки / B2B)
- **URL:** spectrans-log.ru (Tilda Publishing)
- **Метрики PageSpeed:** Мобильный 28/100, LCP 8.9с, вес первого экрана 11.4 МБ (не сжатые PNG, 142 сторонних JS-запроса).
- **Слабые места:** Форма заявки блокируется на медленном 4G, мобильный пользователь ждет 9 секунд.
- **Готовый питч:** Персонализированное видео со сравнением: загрузка spectrans vs демо на чистом коде (0.3с). Предложение пересборки первого экрана за 5 дней с гарантией PageSpeed 95+.

### Лид 02: «КровляПроф» (Строительство / Услуги)
- **URL:** krovlya-prof.ru (WordPress + Elementor)
- **Метрики PageSpeed:** Мобильный 34/100, отклик сервера TTFB 2.1с, сбой верстки на iPhone 13.
- **Готовый питч:** Фокус на уязвимости плагинов и сливе рекламного бюджета в Яндекс.Директ. Оффер: чистый код без абонентской платы.

### Лид 03: «Клиника Доктора Зубова» (Медицина / Премиум)
- **URL:** zubov-clinic.ru (Tilda)
- **Метрики PageSpeed:** 41/100, видео в фоне грузится 12 секунд.
- **Готовый питч:** Пересборка видео-фона через сжатие AV1/WebM с мгновенным стартом и ростом конверсии в запись на 30%.
MD
            ],
            [
                'category' => 'crm_memory',
                'title' => 'CRM-память конверсий и гипотез',
                'content_markdown' => <<<MD
# CRM-ПАМЯТЬ КОНВЕРСИЙ И ЭФФЕКТИВНОСТИ ТЕМ

Данная сводка передается в системный промпт модели для генерации контента с максимальной отдачей.

### 1. Рейтинг конверсий по тематикам постов и статей:
1. **Тема «Скорость и слив рекламы» (PageSpeed < 50):** Конверсия в клик по боту — 4.8%. Конверсия бота в заявку на аудит — 38%. (ТОП-1 по выручке).
2. **Тема «Абонентская плата конструкторов (15 000 ₽/год за каждый сайт)»:** Конверсия в клик — 3.9%. Отлично привлекает малый бизнес с линейкой сайтов.
3. **Тема «Кейсы до/после (Байкал, Девдеев)»:** Конверсия в заявку на разработку под ключ — 2.9% (привлекает крупных клиентов с чеком 35 000+ ₽).
4. **Тема «Типографика и дизайн-тренды»:** Конверсия в заявку — 0.8% (дает охваты, но слабую продажу).

### 2. Рабочие гипотезы агента (Memory Loop):
- *Гипотеза #1:* Использование конкретных цифр (0.3с vs 9.4с) в первом предложении поста увеличивает дочитываемость на 42%.
- *Гипотеза #2:* В лонгридах для VC.ru упоминание конструктора Tilda в связке со скриншотом замеров PageSpeed дает в 3 раза больше комментариев и попадание в топ ленты.
MD
            ],
            [
                'category' => 'scraping_logs',
                'title' => 'Журнал парсингов по дням',
                'content_markdown' => <<<MD
# ЖУРНАЛ ПАРСИНГОВ И СБОРА ДАННЫХ

### Лог: 11 Ноября 2026
- **09:00 (Radar Tavily):** Собрано 12 инфоповодов. 3 перенесены в план (обновление Core Web Vitals, баг в плагине WP).
- **14:30 (PageSpeed API):** Проверено 15 сайтов из ниши загородного строительства. Средний мобильный балл — 36/100.
- **18:15 (Outbound):** Сгенерировано 5 персонализированных питчей.

### Лог: 10 Ноября 2026
- **09:00 (Radar Tavily):** 8 новостей про веб-перформанс.
- **16:00 (Vision Audit):** Анализ первого экрана 7 сайтов ювелирных бутиков. Выявлены тяжелые PNG и медленные слайдеры.
MD
            ],
            [
                'category' => 'content_rules',
                'title' => 'Правила и алгоритмы платформ',
                'content_markdown' => <<<MD
# РЕДПОЛИТИКА И АЛГОРИТМЫ ПЛАТФОРМ ANTIDESIGNER

### 1. Telegram-канал:
- Стиль: Dark Tech, экспертный цинизм к медленным шаблонам, короткие абзацы (1–3 строки).
- Обязательно: 1 четкая мысль на пост, призыв проверить свой сайт в боте.
- СТРОГОЕ ПРАВИЛО: Никаких длинных тире (—). Только дефис (-) или стрелки (->).

### 2. VC.ru / Habr:
- Структура: Провокационный заголовок с цифрой -> Разбор скрытой проблемы конструкторов -> Архитектурное доказательство на чистом коде -> Реальный кейс с цифрами -> Чек-лист.
- Тон: Профессиональный инженерный разбор без агрессии, с железобетонными замерами в DevTools.

### 3. Instagram Reels / Shorts:
- Хронометраж: 45–55 секунд.
- Первые 3 секунды: жесткий визуальный хук (белый зависший экран сайта конкурента).
- Оффер в конце: кодовое слово в директ для получения бесплатного аудита.
MD
            ]
        ];

        foreach ($defaults as $item) {
            $stmtCheck = $pdo->prepare('SELECT id FROM knowledge_base WHERE category = :cat');
            $stmtCheck->execute([':cat' => $item['category']]);
            $exists = $stmtCheck->fetchColumn();

            if (!$exists) {
                $stmtIns = $pdo->prepare('INSERT INTO knowledge_base (category, title, content_markdown) VALUES (:cat, :title, :content)');
                $stmtIns->execute([
                    ':cat'     => $item['category'],
                    ':title'   => $item['title'],
                    ':content' => $item['content_markdown']
                ]);
            }
        }
    };

    if ($method === 'GET') {
        $seedCategories($pdo);

        $category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
        if ($category !== '') {
            $stmt = $pdo->prepare('SELECT id, category, title, content_markdown, updated_at FROM knowledge_base WHERE category = :cat LIMIT 1');
            $stmt->execute([':cat' => $category]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'item'   => $item
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $stmt = $pdo->query('SELECT id, category, title, content_markdown, updated_at FROM knowledge_base ORDER BY id ASC');
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'count'  => count($items),
            'items'  => $items
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $id = isset($input['id']) ? (int)$input['id'] : null;
        $category = isset($input['category']) ? trim((string)$input['category']) : null;
        $contentMarkdown = isset($input['content_markdown']) ? trim((string)$input['content_markdown']) : null;
        $title = isset($input['title']) ? trim((string)$input['title']) : null;

        if ($contentMarkdown === null || (!$id && !$category)) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Необходимо указать category или id, а также content_markdown'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($id) {
            $sql = 'UPDATE knowledge_base SET content_markdown = :content' . ($title ? ', title = :title' : '') . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $params = [':content' => $contentMarkdown, ':id' => $id];
            if ($title) $params[':title'] = $title;
            $stmt->execute($params);

            $stmtFetch = $pdo->prepare('SELECT * FROM knowledge_base WHERE id = :id');
            $stmtFetch->execute([':id' => $id]);
            $updated = $stmtFetch->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmtCheck = $pdo->prepare('SELECT id FROM knowledge_base WHERE category = :cat LIMIT 1');
            $stmtCheck->execute([':cat' => $category]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                $sql = 'UPDATE knowledge_base SET content_markdown = :content' . ($title ? ', title = :title' : '') . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $params = [':content' => $contentMarkdown, ':id' => $existingId];
                if ($title) $params[':title'] = $title;
                $stmt->execute($params);
            } else {
                $sql = 'INSERT INTO knowledge_base (category, title, content_markdown) VALUES (:cat, :title, :content)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':cat'     => $category,
                    ':title'   => $title ?? strtoupper($category),
                    ':content' => $contentMarkdown
                ]);
            }

            $stmtFetch = $pdo->prepare('SELECT * FROM knowledge_base WHERE category = :cat');
            $stmtFetch->execute([':cat' => $category]);
            $updated = $stmtFetch->fetch(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'status'  => 'success',
            'message' => 'Контекст базы знаний успешно сохранен',
            'item'    => $updated
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Ошибка API Базы Знаний: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
