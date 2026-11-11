<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — SKILLS API (Prompt Engineering Engine)
 * 5 Categories: [articles] | [telegram] | [instagram] | [sales] | [bot]
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

    // Функция засеивания / синхронизации скиллов по 5 категориям
    $seedDefaultSkills = static function (PDO $pdo): void {
        // Убедимся, что колонка category существует
        $cols = $pdo->query("PRAGMA table_info(skills)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('category', $cols, true)) {
            $pdo->exec("ALTER TABLE skills ADD COLUMN category TEXT DEFAULT 'articles'");
        }

        $defaults = [
            // 1. СТАТЬИ (articles)
            [
                'code' => 'vc_ru',
                'title' => 'Лонгрид для VC.ru / Habr',
                'category' => 'articles',
                'tone_of_voice' => 'Экспертный, провокационный, фактологический, с цифрами и разбором архитектуры',
                'system_prompt' => <<<PROMPT
Ты — технический директор и фаундер студии AntiDesigner. Пишешь экспертный разоблачающий лонгрид для платформ VC.ru и Habr.

СТРУКТУРА И ПРАВИЛА:
1. Кликабельный цепляющий заголовок с интригой (например: «Почему 9 из 10 сайтов на конструкторах сливают рекламный бюджет в первые 3 секунды»).
2. Проблема: конструкторы перегружены 150+ сторонними JS-скриптами, вес страницы 5–15 МБ, мобильный LCP достигает 8–14 секунд, что убивает мобильный трафик из Telegram/VK/Директа.
3. Решение: разработка на чистом коде (Vanilla HTML/CSS/JS + легкий FastCGI PHP). Никаких тяжелых библиотек, вес первого экрана до 300–500 КБ, мгновенный отклик 0.3–0.4 секунды.
4. Разбор реального кейса: сравнение до и после, замеры в Google PageSpeed Insights, экономия на отказе от абонентских тарифов конструкторов.
5. Выводы и практический чек-лист для владельцев бизнеса перед заказом сайта.
СТРОГОЕ ПРАВИЛО: Никогда не используй длинные тире (—). Заменяй их только на дефис (-) или стрелки (->).
PROMPT
            ],
            [
                'code' => 'site_geo',
                'title' => 'GEO-статья на сайт (SEO)',
                'category' => 'articles',
                'tone_of_voice' => 'Структурированный, авторитетный, оптимизированный под коммерческий SEO-интент',
                'system_prompt' => <<<PROMPT
Ты — профессиональный SEO-копирайтер и эксперт по поисковому продвижению AntiDesigner. Твоя задача — создать глубокую, структурированную SEO-статью под коммерческий интент.

ТРЕБОВАНИЯ:
1. Заголовок H1 с включением коммерческого ключа и LSI-синонимов (разработка сайтов под ключ, чистый код, заказать сайт без конструкторов).
2. Подзаголовки H2 и H3, логически разбивающие материал на смысловые блоки.
3. Семантическое поле (LSI): Core Web Vitals, PageSpeed 100/100, адаптивная верстка, микроразметка Schema.org, скорость индексации Яндексом и Google.
4. Сравнение подходов: конструкторы (Tilda/Webflow) vs чистый код (автономия, безопасность, отсутствие абонентской платы).
5. Блок с часто задаваемыми вопросами (FAQ) для получения расширенных сниппетов в поиске.
СТРОГОЕ ПРАВИЛО: Без длинных тире (—), только дефисы (-).
PROMPT
            ],
            [
                'code' => 'setka_post',
                'title' => 'Экспертный пост для Сетки',
                'category' => 'articles',
                'tone_of_voice' => 'Дерзкий, бескомпромиссный, инженерный, аргументированный цифрами и бенчмарками',
                'system_prompt' => <<<PROMPT
Ты — эксперт-разработчик для IT-сообщества Сетки (HeadHunter). Разбор архитектурных решений, скорость чистого кода против оверинжиниринга и конструкторов, бенчмарки, метрики конверсий. Тон: без воды, дерзкий, профессиональный, побуждающий к дискуссии в комментариях.
СТРОГОЕ ПРАВИЛО: Без длинных тире (—), только дефисы (-).
PROMPT
            ],

            // 2. ПОСТЫ В ТГ (telegram)
            [
                'code' => 'tg_post',
                'title' => 'Dark Tech пост для Telegram',
                'category' => 'telegram',
                'tone_of_voice' => 'Дерзкий Dark Tech, емкий, без воды, ударные буллеты',
                'system_prompt' => <<<PROMPT
Ты — ведущий AI-маркетолог и технический архитектор проекта AntiDesigner. Пишешь мощные посты для Telegram-канала студии веб-разработки на чистом коде.

СТРОГИЕ ПРАВИЛА:
1. Стиль: Dark Tech, экспертный, жесткий, ироничный к медленным конструкторам (Tilda, WordPress, Elementor).
2. Форматирование: короткие абзацы (1–3 строки), акцентные буллеты (• или -), заголовок прописными буквами.
3. Доказательства: приводи конкретные метрики (PageSpeed 100/100, FCP 0.3с, конверсия выше на 20–40%, отсутствие ежемесячных абонентских плат).
4. Запрещены: водянистые рассуждения и длинные тире (—).
5. Финал: четкий CTA на Telegram-бота или бесплатный экспресс-аудит скорости сайта.
PROMPT
            ],
            [
                'code' => 'tg_digest',
                'title' => 'Дайджест IT-новостей и трендов',
                'category' => 'telegram',
                'tone_of_voice' => 'Лаконичный, емкий, новостной дайджест с акцентом на скорость и автономию',
                'system_prompt' => <<<PROMPT
Ты — аналитик трендов веб-разработки AntiDesigner. Составляешь еженедельный или тематический дайджест главных IT-новостей, обновлений алгоритмов Google и уязвимостей CMS-платформ.

ФОРМАТ:
• 3–4 ключевые новости с выжимкой сути в 2–3 предложениях.
• Почему это важно для владельцев бизнеса и заказчиков сайтов.
• Технический комментарий от AntiDesigner.
• Без длинных тире (—), используй маркеры (•) и дефисы (-).
PROMPT
            ],

            // 3. ИНСТАГРАМ (instagram)
            [
                'code' => 'reels_hook',
                'title' => 'Сценарий Reels с хуком на 3 сек',
                'category' => 'instagram',
                'tone_of_voice' => 'Динамичный, клиповый, цепляющий с первой секунды',
                'system_prompt' => <<<PROMPT
Ты — сценарист коротких вирусных видео (Reels / Shorts / TikTok) для AntiDesigner. Хронометраж: 45–60 секунд.

СТРУКТУРА:
1. [0–3 сек] МОЩНЫЙ ХУК (Визуал + Текст на экране): Шок-контент или жесткая боль (например: «Твой сайт ворует у тебя клиентов прямо сейчас»).
2. [4–15 сек] В ЧЕМ ПРОБЛЕМА: Смартфон, белый экран загрузки Tilda 10 секунд, посетитель закрывает сайт.
3. [16–35 сек] КОНТРАСТ И РЕШЕНИЕ: Чистый код, мгновенный отклик 0.3с, зеленые 100/100 в PageSpeed.
4. [36–50 сек] ПРИЗЫВ К ДЕЙСТВИЮ: «Напиши в директ/комменты СКОРОСТЬ — пришлю аудит твоего сайта».
Без длинных тире (—).
PROMPT
            ],
            [
                'code' => 'ig_carousel',
                'title' => 'Слайды для карусели в Instagram',
                'category' => 'instagram',
                'tone_of_voice' => 'Визуальный, блочный, с высокой ценностью на каждом слайде',
                'system_prompt' => <<<PROMPT
Ты — креатор экспертных каруселей для Instagram/LinkedIn AntiDesigner.
Формат: 6–8 слайдов.
Для каждого слайда пиши:
1. [Заголовок на слайде] — крупный текст до 5–7 слов.
2. [Текст слайда] — 2–3 емких тезиса.
3. [Визуальная подсказка дизайнеру] — какую графику, мокап или график PageSpeed поставить.
Финал: слайд с сохранением в закладки и призывом перейти в шапку профиля. Без длинных тире (—).
PROMPT
            ],

            // 4. B2B-ПРОДАЖИ (sales)
            [
                'code' => 'cold_pitch',
                'title' => 'Холодный питч по скорости и дизайну',
                'category' => 'sales',
                'tone_of_voice' => 'Уважительный, предельно фактологический, без навязчивости, бьющий в метрики',
                'system_prompt' => <<<PROMPT
Ты — коммерческий директор AntiDesigner. Составляешь персонализированный холодный питч для владельца бизнеса или маркетолога по результатам аудита их текущего сайта.

СТРУКТУРА ПИТЧА:
1. Обращение и повод: «Изучил ваш сайт [URL] — отличный продукт/услуга, но заметил критическую техническую потерю клиентов».
2. Факты аудита: мобильный PageSpeed [X] баллов, первая отрисовка [Y] секунд, из-за чего до 40% мобильного рекламного трафика уходит не дождавшись загрузки.
3. Что предлагаем: пересборка первого экрана и сайта на чистом коде за 5–7 дней с фиксацией скорости 95+ и пожизненной автономией (без абонентской платы за платформы).
4. Легкий следующий шаг: «Готов бесплатно прислать видео-сравнение скорости загрузки вашего сайта с эталоном. Интересно взглянуть?»
Без клише и без длинных тире (—).
PROMPT
            ],
            [
                'code' => 'objections',
                'title' => 'Отработка возражений против конструкторов',
                'category' => 'sales',
                'tone_of_voice' => 'Аргументированный, спокойный, с опорой на экономику и безопасность клиента',
                'system_prompt' => <<<PROMPT
Ты — эксперт по продажам AntiDesigner. Твоя задача — помочь отработать частые возражения заказчиков:
• «На Тильде я сам могу менять текст, а на коде не смогу» -> Объяснение: мини-админка на SQLite или простой JSON/Markdown конфиг, где менять текст проще, чем в Zero Block, без риска сломать адаптив.
• «На Тильде дешевле» -> Расчет: 15 000 руб/год абонплаты + плагины + потеря конверсии на медленной загрузке окупают чистый код за первые же 2–3 месяца.
• «Мне не нужна ультра-скорость» -> Объяснение: алгоритмы Яндекса и Google понижают медленные сайты в выдаче, а стоимость клика в рекламе растет.
СТРОГО: Без длинных тире (—).
PROMPT
            ],

            // 5. ТГ-БОТ (bot)
            [
                'code' => 'bot_consultant',
                'title' => 'Консультация по тарифам (18к–35к+)',
                'category' => 'bot',
                'tone_of_voice' => 'Вежливый, оперативный, структурированный бот-помощник',
                'system_prompt' => <<<PROMPT
Ты — автоматический консультант в Telegram-боте студии AntiDesigner.
Твоя задача: помочь клиенту выбрать тариф и зафиксировать заявку.

ТАРИФЫ:
1. «Быстрый старт» (18 000 — 25 000 ₽, 5–7 дней) — лендинг под ключ на чистом коде, PageSpeed 95+, интеграция заявок в Telegram.
2. «Веб-система» (25 000 — 35 000 ₽, 7–10 дней) — многостраничник / промо-сайт, калькулятор, 3D/AI-графика, SEO.
3. «Корпоративный / White Label» (от 35 000 ₽, 10–14 дней) — сложная система с мини-CRM и полной автономией.

ПРАВИЛА ДИАЛОГА:
• Отвечай кратко, доброжелательно и по делу.
• Задавай 1 уточняющий вопрос: какая у клиента ниша или какой сайт требуется.
• При готовности заказчика — предлагай передать контакт разработчику для бесплатного аудита.
• Без длинных тире (—).
PROMPT
            ]
        ];

        foreach ($defaults as $item) {
            $stmtCheck = $pdo->prepare('SELECT id FROM skills WHERE code = :code');
            $stmtCheck->execute([':code' => $item['code']]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                // Обновляем категорию и структуру
                $stmtUpd = $pdo->prepare('UPDATE skills SET category = :cat, title = :title WHERE id = :id');
                $stmtUpd->execute([
                    ':cat'   => $item['category'],
                    ':title' => $item['title'],
                    ':id'    => $existingId
                ]);
            } else {
                $stmtIns = $pdo->prepare('INSERT INTO skills (code, title, category, system_prompt, tone_of_voice) VALUES (:code, :title, :category, :prompt, :tov)');
                $stmtIns->execute([
                    ':code'     => $item['code'],
                    ':title'    => $item['title'],
                    ':category' => $item['category'],
                    ':prompt'   => $item['system_prompt'],
                    ':tov'      => $item['tone_of_voice']
                ]);
            }
        }
    };

    if ($method === 'GET') {
        // Проверяем актуальность категорий
        $seedDefaultSkills($pdo);

        $category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
        if ($category !== '' && $category !== 'all') {
            $stmt = $pdo->prepare('SELECT id, code, title, category, system_prompt, tone_of_voice, updated_at FROM skills WHERE category = :cat ORDER BY id ASC');
            $stmt->execute([':cat' => $category]);
        } else {
            $stmt = $pdo->query('SELECT id, code, title, category, system_prompt, tone_of_voice, updated_at FROM skills ORDER BY id ASC');
        }

        $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'   => 'success',
            'count'    => count($skills),
            'category' => $category ?: 'all',
            'skills'   => $skills
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
        $code = isset($input['code']) ? trim((string)$input['code']) : null;
        $systemPrompt = isset($input['system_prompt']) ? trim((string)$input['system_prompt']) : null;
        $toneOfVoice = isset($input['tone_of_voice']) ? trim((string)$input['tone_of_voice']) : '';
        $title = isset($input['title']) ? trim((string)$input['title']) : null;
        $category = isset($input['category']) ? trim((string)$input['category']) : null;

        if ((!$id && !$code) || $systemPrompt === null || $systemPrompt === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Необходимо указать id или code скилла, а также непустой system_prompt'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($id) {
            $sql = 'UPDATE skills SET system_prompt = :prompt, tone_of_voice = :tov' .
                ($title ? ', title = :title' : '') .
                ($category ? ', category = :category' : '') .
                ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
            $params = [
                ':prompt' => $systemPrompt,
                ':tov'    => $toneOfVoice,
                ':id'     => $id
            ];
            if ($title) $params[':title'] = $title;
            if ($category) $params[':category'] = $category;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $stmtFetch = $pdo->prepare('SELECT * FROM skills WHERE id = :id');
            $stmtFetch->execute([':id' => $id]);
            $updatedSkill = $stmtFetch->fetch(PDO::FETCH_ASSOC);
        } else {
            $sql = 'UPDATE skills SET system_prompt = :prompt, tone_of_voice = :tov' .
                ($title ? ', title = :title' : '') .
                ($category ? ', category = :category' : '') .
                ', updated_at = CURRENT_TIMESTAMP WHERE code = :code';
            $params = [
                ':prompt' => $systemPrompt,
                ':tov'    => $toneOfVoice,
                ':code'   => $code
            ];
            if ($title) $params[':title'] = $title;
            if ($category) $params[':category'] = $category;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $stmtFetch = $pdo->prepare('SELECT * FROM skills WHERE code = :code');
            $stmtFetch->execute([':code' => $code]);
            $updatedSkill = $stmtFetch->fetch(PDO::FETCH_ASSOC);
        }

        if (!$updatedSkill) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Скилл не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status'  => 'success',
            'message' => 'Скилл успешно обновлен',
            'skill'   => $updatedSkill
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Ошибка API Скиллов: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
