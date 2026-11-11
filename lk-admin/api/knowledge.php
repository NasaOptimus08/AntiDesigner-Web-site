<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - KNOWLEDGE BASE API (RAG Context & Memory)
 * 4 Categories: leads_archive, crm_memory, scraping_logs, content_rules
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
    /** @var PDO $pdo */

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Функция засеивания категорий Базы Знаний
    $seedCategories = static function (PDO $pdo): void {
        $defaults = [
            [
                'category' => 'usp',
                'title' => 'УТП чистого кода vs Конструкторы',
                'content_markdown' => <<<MD
# УТП И АРХИТЕКТУРА ЧИСТОГО КОДА (ЕВГЕНИЙ АНТИПИН // ANTIDESIGNER)

AntiDesigner - это не агентство и не студия. Это мой сильный ЛИЧНЫЙ БРЕНД независимого Senior веб-разработчика (Solo Dev). Меня зовут Антипин Евгений.

### В чем моя сила перед веб-студиями (Главное УТП):
1. **Без посредников и испорченного телефона:** В раздутой студии из 10 человек клиент переплачивает за аккаунт-менеджеров, созвоны и аренду офиса, а верстку отдают стажеру на Тильде. Со мной вы работаете напрямую с автором кода - Senior-инженером.
2. **Скорость работы:** Я собираю чистый проект за 3-5 дней, пока студия только составляет бриф и согласует смету.
3. **Чистый код против медленных конструкторов:**
   - Мгновенная первая отрисовка: FCP 0.3с, LCP < 0.8с.
   - 95-100 баллов в Google PageSpeed на реальных смартфонах.
   - Никаких 140 сторонних тяжелых скриптов Tilda и WordPress. Весь сайт весит 25-50 КБ вместо 12 МБ мусора.
4. **Пожизненная собственность без абонентской платы:** Клиент не платит 15 000+ руб/год за каждый домен конструкторам. Код принадлежит заказчику навсегда.
MD
            ],
            [
                'category' => 'tariffs',
                'title' => 'Тарифная сетка и форматы разработки',
                'content_markdown' => <<<MD
# ТАРИФНАЯ СЕТКА ЕВГЕНИЯ АНТИПИНА (ANTIDESIGNER)

Я работаю по прозрачной фиксированной сетке без скрытых доплат. Клиент платит за чистый результат и прямое внимание Senior-разработчика.

### 1. Тариф «Быстрый старт» (Лендинг под ключ)
- **Стоимость:** 18 000 - 25 000 руб
- **Сроки:** 3-5 рабочих дней
- **Что я делаю:** Продающая структура, копирайтинг смыслов, адаптивная верстка на чистом HTML/CSS/Vanilla JS, интеграция с моим автономным Telegram-ботом заявок, скорость 100/100 в PageSpeed.
- **Для кого:** Быстрый тест ниши, запуск контекстной рекламы, эксперты и услуги.

### 2. Тариф «Бизнес-монолит» (Многостраничник или сервис)
- **Стоимость:** 25 000 - 35 000 руб
- **Сроки:** 5-7 рабочих дней
- **Что я делаю:** Полный редизайн, интерактивные калькуляторы, микро-админка на SQLite для самостоятельного редактирования текстов без программиста, веб-трекер конверсий, интеграция CRM и платежей.
- **Для кого:** Производство, клиники, премиум-услуги, B2B-компании.

### 3. Тариф «Индивидуальный стек» (Кастомные сервисы)
- **Стоимость:** от 35 000 руб
- **Сроки:** от 7-10 рабочих дней
- **Что я делаю:** Нестандартная архитектура, интеграции по API, сложные клиентские кабинеты, высокая нагрузка.
MD
            ],
            [
                'category' => 'target_audience',
                'title' => 'Целевая аудитория и боли клиентов',
                'content_markdown' => <<<MD
# ПОРТРЕТ ЦЕЛЕВОЙ АУДИТОРИИ (КЛИЕНТЫ ЕВГЕНИЯ АНТИПИНА)

### Кто мой клиент:
- Владельцы малого и среднего бизнеса (услуги, B2B, производство, онлайн-школы), уставшие от медленных шаблонных сайтов на Tilda/WordPress.
- Предприниматели, которые обожглись на агентствах: заплатили 150 000+ руб студии, ждали 2 месяца и получили тормозящий шаблон от стажера.
- Маркетологи и владельцы бизнеса, запускающие платный трафик (Яндекс.Директ, TG Ads) и видящие, что 40% кликов сгорает из-за 6-секундной загрузки мобильной Тильды.

### Мои ключевые аргументы в диалоге:
1. «Вам не нужно оплачивать офис агентства - вы платите только за чистый код Senior-разработчика».
2. «Я сделаю сайт за 3-5 дней с подтвержденным баллом 100/100 в PageSpeed».
3. «Вы общаетесь со мной напрямую в Telegram без менеджеров-переводчиков».
MD
            ],
            [
                'category' => 'cases',
                'title' => 'Эталонные кейсы и результаты',
                'content_markdown' => <<<MD
# ЭТАЛОННЫЕ КЕЙСЫ ЕВГЕНИЯ АНТИПИНА (ANTIDESIGNER)

1. **Кейс 01: «Поездка на Байкал» (Туризм / Премиум)**
   - *Задача:* Упаковка авторского зимнего тура и быстрый запуск рекламы.
   - *Мое решение:* Чистый код на HTML/CSS/JS, запуск за 4 дня, отклик 0.3с, видео-мокапы.
   - *Результат:* 21% конверсия из рекламы в бронирование с первого экрана.

2. **Кейс 02: «Девдеев.рф» (B2B Промышленность)**
   - *Задача:* Редизайн тяжелого сайта завода металлоконструкций, где страница каталога весила 14 МБ.
   - *Мое решение:* Пересборка на чистом стеке с микро-админкой SQLite, сжатие медиа в WebP, PageSpeed вырос с 24 до 98 баллов.
   - *Результат:* Рост мобильных заявок в 2.4 раза при неизменном рекламном бюджете.
MD
            ],
            [
                'category' => 'leads_archive',
                'title' => 'База холодных лидов и аудитов',
                'content_markdown' => <<<MD
# БАЗА ХОЛОДНЫХ ЛИДОВ И ТЕХНИЧЕСКИХ АУДИТОВ

### Лид 01: «СпецТрансЛогистик» (Грузоперевозки / B2B)
- **URL:** spectrans-log.ru (Tilda Publishing)
- **Метрики PageSpeed:** Мобильный 28/100, LCP 8.9с, вес первого экрана 11.4 МБ (не сжатые PNG, 142 сторонних JS-запроса).
- **Слабые места:** Форма заявки блокируется на медленном 4G, мобильный пользователь ждет 9 секунд.
- **Мой питч:** Персонализированное видео со сравнением: загрузка spectrans vs мое демо на чистом коде (0.3с). Предложение пересборки первого экрана за 3-5 дней с гарантией PageSpeed 95+.

### Лид 02: «КровляПроф» (Строительство / Услуги)
- **URL:** krovlya-prof.ru (WordPress + Elementor)
- **Метрики PageSpeed:** Мобильный 34/100, отклик сервера TTFB 2.1с, сбой верстки на iPhone 13.
- **Мой питч:** Фокус на уязвимости плагинов и сливе рекламного бюджета в Яндекс.Директ. Оффер: мой чистый код без абонентской платы.
MD
            ],
            [
                'category' => 'crm_memory',
                'title' => 'CRM-память конверсий и гипотез',
                'content_markdown' => <<<MD
# CRM-ПАМЯТЬ КОНВЕРСИЙ И ЭФФЕКТИВНОСТИ ТЕМ (ЕВГЕНИЙ АНТИПИН)

Сводка передается в системный промпт модели для генерации авторского контента с максимальной отдачей.

### 1. Рейтинг конверсий по тематикам постов и статей:
1. **Тема «Скорость и слив рекламы» (PageSpeed < 50):** Конверсия в клик по боту - 4.8%. Конверсия бота в заявку на мой экспресс-аудит - 38%. (ТОП-1 по выручке).
2. **Тема «Абонентская плата конструкторов (15 000 руб/год за каждый сайт)»:** Конверсия в клик - 3.9%. Отлично привлекает малый бизнес.
3. **Тема «Кейсы до/после (Байкал, Девдеев)»:** Конверсия в заявку на разработку под ключ - 2.9% (привлекает клиентов с чеком 35 000+ руб).
4. **Тема «Разработчик-одиночка против студии»:** Конверсия в заявку - 3.4% (сильный отклик у предпринимателей, уставших от бюрократии агентств).

### 2. Рабочие гипотезы агента (Memory Loop):
- *Гипотеза #1:* Использование конкретных цифр (0.3с vs 9.4с) в первом предложении поста увеличивает дочитываемость на 42%.
- *Гипотеза #2:* Позиционирование от первого лица («Я», «мой код», «без менеджеров») дает в 2.5 раза больше доверия, чем обезличенное агентство.
MD
            ],
            [
                'category' => 'scraping_logs',
                'title' => 'Журнал парсингов по дням',
                'content_markdown' => <<<MD
# ЖУРНАЛ ПАРСИНГОВ И СБОРА ДАННЫХ

### Лог: 11 Ноября 2026
- **09:00 (Radar Tavily):** Собрано 12 инфоповодов. 3 перенесены в план (обновление Core Web Vitals, баг в плагине WP).
- **14:30 (PageSpeed API):** Проверено 15 сайтов из ниши загородного строительства. Средний мобильный балл - 36/100.
- **18:15 (Outbound):** Сгенерировано 5 персонализированных питчей от имени Евгения Антипина.
MD
            ],
            [
                'category' => 'content_rules',
                'title' => 'Правила и алгоритмы платформ',
                'content_markdown' => <<<MD
# РЕДПОЛИТИКА ЛИЧНОГО БРЕНДА ANTIDESIGNER (ЕВГЕНИЙ АНТИПИН)

### Главный закон коммуникации:
ТОЛЬКО ОТ ПЕРВОГО ЛИЦА («Я», «мой подход», «в моем коде»).
Категорически запрещено использовать корпоративное «мы», «наша команда», «наша студия». AntiDesigner - это сильный личный бренд одного независимого Senior разработчика.

### 1. Telegram-канал:
- Стиль: Dark Tech, живой авторский девлог разработчика-одиночки, честный бэкстейдж, короткие абзацы (1-3 строки).
- Обязательно: 1 четкая мысль на пост, призыв проверить скорость своего сайта у меня в боте.
- СТРОГОЕ ПРАВИЛО: Никаких длинных тире (—). Только дефис (-) или стрелки (->).

### 2. VC.ru / Habr:
- Формат: Авторская экспертная колонка инженера-практика.
- Структура: Провокационный заголовок -> Разбор скрытого мусора конструкторов -> Мое доказательство на чистом коде -> Реальный кейс с цифрами -> Чек-лист.
- Тон: Профессиональный инженерный разбор без агрессии, с железобетонными замерами в DevTools.

### 3. Instagram Reels / Shorts:
- Хронометраж: 45-55 секунд.
- Первые 3 секунды: жесткий визуальный хук (белый зависший экран сайта конкурента).
- Оффер в конце: напишите мне в директ или перейдите в бот для бесплатного замера скорости.
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

    // Авто-сидинг дефолтных категорий выполняется СТРОГО один раз, если таблица абсолютно пуста
    $kbCount = (int)$pdo->query('SELECT COUNT(*) FROM knowledge_base')->fetchColumn();
    if ($kbCount === 0) {
        $seedCategories($pdo);
    }

    if ($method === 'GET') {

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
