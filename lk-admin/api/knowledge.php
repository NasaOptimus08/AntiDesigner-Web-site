<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — KNOWLEDGE BASE API (Persistent Agent Context)
 * Category context management and auto-seeding for RAG prompt injection
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    require_once __DIR__ . '/db.php';
    /** @var PDO $pdo */

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Функция засеивания базы знаний
    $seedDefaultKnowledge = static function (PDO $pdo): void {
        $defaults = [
            [
                'category' => 'tariffs',
                'title' => 'Тарифная сетка и форматы разработки',
                'content_markdown' => <<<MD
# ТАРИФНАЯ СЕТКА ANTIDESIGNER

### 1. Формат «Быстрый старт» (Лендинг под ключ)
- **Бюджет:** 18 000 — 25 000 ₽
- **Сроки:** 5–7 рабочих дней
- **Состав:** Продающая структура, копирайтинг смыслов, адаптивная верстка на чистом коде, интеграция с Telegram-ботом заявок, PageSpeed 95–100/100.
- **Для кого:** Быстрый тест ниши, запуск рекламы на одну услугу, упаковка эксперта.

### 2. Формат «Веб-система под ключ» (Многостраничник / Промо)
- **Бюджет:** 25 000 — 35 000 ₽
- **Сроки:** 7–10 рабочих дней
- **Состав:** Несколько страниц/экранов, калькулятор стоимости, интерактивные фильтры, 3D/AI-графика, SEO-микроразметка Schema.org, интеграция эквайринга.
- **Для кого:** Производства, строительные компании, сфера услуг с линейкой тарифов.

### 3. Формат «Корпоративный / White Label»
- **Бюджет:** от 35 000 ₽
- **Сроки:** 10–14 рабочих дней
- **Состав:** Сложная веб-система, собственная легкая мини-CRM на SQLite, полная автономия без сторонних плагинов, пожизненное владение без абонентской платы.
MD
            ],
            [
                'category' => 'usp',
                'title' => 'УТП чистого кода vs Конструкторы',
                'content_markdown' => <<<MD
# УТП И АРХИТЕКТУРА ЧИСТОГО КОДА

1. **Скорость и конверсия:**
   - Мгновенная первая отрисовка (FCP 0.3с, LCP < 1.0с).
   - В Google PageSpeed стабильные 95–100 баллов на мобильных устройствах.
   - Пользователь с мобильного интернета видит контент сразу, а не ждет 8–12 секунд белого экрана Tilda/WordPress. Это дает прирост конверсии в заявку на 20–40%.

2. **0 ₽ абонентской платы за конструкторы:**
   - Конструкторы требуют ежемесячно/ежегодно платить 6 000 — 15 000 ₽ за каждый сайт.
   - Наш чистый код работает на стандартном хостинге за 150 ₽/мес без привязки к тарифам платформ.

3. **100% владение и автономия:**
   - Исходный код принадлежит клиенту навсегда.
   - Защита от санкций, закрытия платформ, блокировок зарубежных библиотек.
   - Нет уязвимостей сторонних WordPress-плагинов.

4. **Формат работы 90/10 без посредников:**
   - Никаких аккаунт-менеджеров и испорченного телефона.
   - Прямой контакт в Telegram с ведущим разработчиком.
   - Готовый сайт за 5–7 дней вместо 2–3 месяцев согласований в студиях.
MD
            ],
            [
                'category' => 'target_audience',
                'title' => 'Целевая аудитория и боли клиентов',
                'content_markdown' => <<<MD
# ПОРТРЕТ ЦЕЛЕВОЙ АУДИТОРИИ

### Кто наш клиент:
- Владельцы малого и среднего бизнеса (услуги, B2B, производство, онлайн-школы).
- Эксперты, юристы, врачи, продюсеры с высоким чеком.
- Маркетологи и предприниматели, запускающие платный трафик (Яндекс.Директ, Telegram Ads, VK).

### Ключевые боли:
1. **Слив рекламы из-за тормозов:** Запустили рекламу, клики идут, а заявок мало — сайт на Tilda/WP грузится 7–10 секунд, пользователь уходит.
2. **Обожглись на студиях:** Заплатили 150к–300к, делали 3 месяца, в итоге получили шаблон на Elementor, а исходники не отдают.
3. **Устали от конструкторов:** Постоянно растут тарифы, платформы блокируют функции, сайты падают при нагрузке.
4. **Хотят «под ключ»:** Нет времени писать ТЗ на 50 страниц и искать отдельно копирайтера, дизайнера и верстальщика.
MD
            ],
            [
                'category' => 'cases',
                'title' => 'Эталонные кейсы и результаты',
                'content_markdown' => <<<MD
# ЭТАЛОННЫЕ КЕЙСЫ ANTIDESIGNER

1. **Кейс 01: «Поездка на Байкал» (Туризм / Премиум)**
   - *Задача:* Упаковка авторского зимнего тура и быстрый запуск рекламы.
   - *Реализация:* Чистый код, запуск за 5 дней, отклик 0.3с, видео-мокапы.
   - *Результат:* 21% конверсия из рекламы в бронирование с первой недели.

2. **Кейс 02: «Квазар» (EdTech / Онлайн-обучение)**
   - *Задача:* Флагманская веб-платформа онлайн-школы без конструкторов.
   - *Реализация:* Высокая скорость, адаптивная мобильная сетка, интеграция с CRM.
   - *Результат:* 100/100 PageSpeed, стабильная работа при наплыве трафика.

3. **Кейс 03: «Девдеев» (Ювелирный премиум-бренд)**
   - *Задача:* Минималистичный премиум-сайт с демонстрацией изделий.
   - *Реализация:* Dark Tech эстетика, WebP/AVIF графика без фотостудий.
   - *Результат:* Рост средней глубины просмотра страницы в 2.4 раза.
MD
            ]
        ];

        $stmt = $pdo->prepare('INSERT INTO knowledge_base (category, title, content_markdown) VALUES (:cat, :title, :content)');
        foreach ($defaults as $item) {
            $stmt->execute([
                ':cat'     => $item['category'],
                ':title'   => $item['title'],
                ':content' => $item['content_markdown']
            ]);
        }
    };

    if ($method === 'GET') {
        // Проверяем наличие записей
        $count = (int)$pdo->query('SELECT COUNT(*) FROM knowledge_base')->fetchColumn();
        if ($count === 0) {
            $seedDefaultKnowledge($pdo);
        }

        $stmt = $pdo->query('SELECT id, category, title, content_markdown, updated_at FROM knowledge_base ORDER BY id ASC');
        $items = $stmt->fetchAll();

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
                'message' => 'Необходимо указать id или category, а также content_markdown'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($id) {
            $sql = 'UPDATE knowledge_base SET content_markdown = :content' . ($title ? ', title = :title' : '') . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
            $params = [
                ':content' => $contentMarkdown,
                ':id'      => $id
            ];
            if ($title) $params[':title'] = $title;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $stmtFetch = $pdo->prepare('SELECT * FROM knowledge_base WHERE id = :id');
            $stmtFetch->execute([':id' => $id]);
            $updated = $stmtFetch->fetch();
        } else {
            // Ищем по категории
            $stmtCheck = $pdo->prepare('SELECT id FROM knowledge_base WHERE category = :cat LIMIT 1');
            $stmtCheck->execute([':cat' => $category]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                $sql = 'UPDATE knowledge_base SET content_markdown = :content' . ($title ? ', title = :title' : '') . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
                $params = [
                    ':content' => $contentMarkdown,
                    ':id'      => $existingId
                ];
                if ($title) $params[':title'] = $title;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = 'INSERT INTO knowledge_base (category, title, content_markdown) VALUES (:cat, :title, :content)';
                $params = [
                    ':cat'     => $category,
                    ':title'   => $title ?? strtoupper($category),
                    ':content' => $contentMarkdown
                ];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            $stmtFetch = $pdo->prepare('SELECT * FROM knowledge_base WHERE category = :cat');
            $stmtFetch->execute([':cat' => $category]);
            $updated = $stmtFetch->fetch();
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

