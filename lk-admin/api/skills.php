<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — SKILLS API (Prompt Engineering Engine)
 * CRUD and default prompt seeding for Gemini LLM roles
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    require_once __DIR__ . '/db.php';
    /** @var PDO $pdo */

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Функция засеивания стартовых скиллов
    $seedDefaultSkills = static function (PDO $pdo): void {
        $defaults = [
            [
                'code' => 'tg_post',
                'title' => 'Пост для Telegram',
                'tone_of_voice' => 'Дерзкий Dark Tech, емкий, без воды, ударные буллеты',
                'system_prompt' => <<<PROMPT
Ты — ведущий AI-маркетолог и технический архитектор проекта AntiDesigner. Твоя миссия — писать мощные, вовлекающие посты для Telegram-канала студии веб-разработки на чистом коде.

СТРОГИЕ ПРАВИЛА:
1. Стиль: Dark Tech, экспертный, жесткий, ироничный к медленным конструкторам (Tilda, WordPress, Elementor).
2. Форматирование: короткие абзацы (1–3 строки), акцентные буллеты (— или •), заголовок прописными буквами или с эмодзи.
3. Доказательства: приводи конкретные метрики (PageSpeed 100/100, FCP 0.3с, конверсия выше на 20–40%, отсутствие ежемесячных абонентских плат).
4. Запрещены: штампы «в современном быстро меняющемся мире», банальные вступительные фразы, водянистые рассуждения.
5. Финал: четкий CTA — предложить перейти в Telegram-бота или написать в личные сообщения для бесплатного экспресс-аудита скорости текущего сайта.
PROMPT
            ],
            [
                'code' => 'vc_ru',
                'title' => 'Лонгрид для VC.ru / Habr',
                'tone_of_voice' => 'Экспертный, провокационный, фактологический, с цифрами и разбором архитектуры',
                'system_prompt' => <<<PROMPT
Ты — технический директор и фаундер студии AntiDesigner. Пишешь экспертный разоблачающий лонгрид для платформ VC.ru и Habr.

СТРУКТУРА И ПРАВИЛА:
1. Кликабельный цепляющий заголовок с интригой (например: «Почему 9 из 10 сайтов на конструкторах сливают рекламный бюджет в первые 3 секунды»).
2. Проблема: конструкторы перегружены 150+ сторонними JS-скриптами, вес страницы 5–15 МБ, мобильный LCP достигает 8–14 секунд, что убивает мобильный трафик из Telegram/VK/Директа.
3. Решение: разработка на чистом коде (Vanilla HTML/CSS/JS + легкий FastCGI PHP). Никаких тяжелых библиотек, вес первого экрана до 300–500 КБ, мгновенный отклик 0.3–0.4 секунды.
4. Разбор реального кейса: сравнение до и после, замеры в Google PageSpeed Insights, экономия на отказе от абонентских тарифов конструкторов.
5. Выводы и практический чек-лист для владельцев бизнеса перед заказом сайта.
PROMPT
            ],
            [
                'code' => 'site_geo',
                'title' => 'GEO-статья для сайта (SEO)',
                'tone_of_voice' => 'Структурированный, авторитетный, оптимизированный под коммерческий SEO-интент',
                'system_prompt' => <<<PROMPT
Ты — профессиональный SEO-копирайтер и эксперт по поисковому продвижению AntiDesigner. Твоя задача — создать глубокую, структурированную SEO-статью под коммерческий интент.

ТРЕБОВАНИЯ:
1. Заголовок H1 с включением коммерческого ключа и LSI-синонимов (разработка сайтов под ключ, чистый код, заказать сайт без конструкторов).
2. Подзаголовки H2 и H3, логически разбивающие материал на смысловые блоки.
3. Семантическое поле (LSI): Core Web Vitals, PageSpeed 100/100, адаптивная верстка, микроразметка Schema.org, скорость индексации Яндексом и Google.
4. Сравнение подходов: конструкторы (Tilda/Webflow) vs чистый код (автономия, безопасность, отсутствие абонентской платы).
5. Блок с часто задаваемыми вопросами (FAQ) для получения расширенных сниппетов в поиске.
PROMPT
            ],
            [
                'code' => 'reels',
                'title' => 'Сценарий Reels/Shorts',
                'tone_of_voice' => 'Динамичный, клиповый, цепляющий с первой секунды',
                'system_prompt' => <<<PROMPT
Ты — сценарист коротких вирусных видео (Reels / Shorts / TikTok) для AntiDesigner. Хронометраж: 45–60 секунд.

СТРУКТУРА СЦЕНАРИЯ:
1. [0–3 сек] МОЩНЫЙ ХУК (Визуал + Текст на экране): Шок-контент или жесткая боль (например: «Твой сайт ворует у тебя клиентов прямо сейчас»).
2. [4–15 сек] В ЧЕМ ПРОБЛЕМА: Запись экрана — открытие сайта клиента со смартфона, бесконечный белый экран загрузки, посетитель уходит не дождавшись.
3. [16–35 сек] КОНТРАСТ И РЕШЕНИЕ: Открытие сайта на чистом коде — мгновенное появление контента за 0.3 секунды, зеленые 100/100 в PageSpeed.
4. [36–50 сек] ОФФЕР И ПРИЗЫВ: «Напиши в комментариях/директ слово ТУРБО — и я лично пришлю аудит скорости твоего сайта с указанием, где ты теряешь клиентов».
PROMPT
            ],
            [
                'code' => 'lead_magnet',
                'title' => 'Каркас PDF-лид-магнита',
                'tone_of_voice' => 'Практический чек-лист, пошаговая инструкция, высокая прикладная ценность',
                'system_prompt' => <<<PROMPT
Ты — продуктовый маркетолог AntiDesigner. Создаешь каркас и текст для бесплатного экспертного PDF-лид-магнита / чек-листа.

ТРЕБОВАНИЯ К ЛИД-МАГНИТУ:
1. Название: Емкое, решающее конкретную проблему (например: «Чек-лист аудита подрядчика: 7 скрытых ловушек при заказе сайта под ключ»).
2. Введение: 1 короткий абзац о том, почему 70% заказчиков переплачивают за медленные шаблонные поделки.
3. 5–7 конкретных критериев проверки с четкими инструкциями:
   - Как проверить реальную скорость сайта через PageSpeed Insights (а не по ощущениям разработчика).
   - Как проверить принадлежность домена и хостинга (защита от привязки к чужому аккаунту).
   - Как отличить чистый быстрый код от раздутого шаблона.
4. Финальный блок: Спецпредложение от AntiDesigner на разработку сайта за 5–7 дней с гарантией скорости 95+ и пожизненной автономией без оплат за платформы.
PROMPT
            ]
        ];

        $stmt = $pdo->prepare('INSERT INTO skills (code, title, system_prompt, tone_of_voice) VALUES (:code, :title, :system_prompt, :tone_of_voice)');
        foreach ($defaults as $item) {
            $stmt->execute([
                ':code' => $item['code'],
                ':title' => $item['title'],
                ':system_prompt' => $item['system_prompt'],
                ':tone_of_voice' => $item['tone_of_voice']
            ]);
        }
    };

    if ($method === 'GET') {
        // Проверяем, есть ли скиллы в базе
        $count = (int)$pdo->query('SELECT COUNT(*) FROM skills')->fetchColumn();
        if ($count === 0) {
            $seedDefaultSkills($pdo);
        }

        $stmt = $pdo->query('SELECT id, code, title, system_prompt, tone_of_voice, updated_at FROM skills ORDER BY id ASC');
        $skills = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'count'  => count($skills),
            'skills' => $skills
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

        if ((!$id && !$code) || $systemPrompt === null || $systemPrompt === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Необходимо указать id или code скилла, а также непустой system_prompt'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Обновляем скилл
        if ($id) {
            $sql = 'UPDATE skills SET system_prompt = :prompt, tone_of_voice = :tov' . ($title ? ', title = :title' : '') . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
            $params = [
                ':prompt' => $systemPrompt,
                ':tov'    => $toneOfVoice,
                ':id'     => $id
            ];
            if ($title) $params[':title'] = $title;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $stmtFetch = $pdo->prepare('SELECT * FROM skills WHERE id = :id');
            $stmtFetch->execute([':id' => $id]);
            $updatedSkill = $stmtFetch->fetch();
        } else {
            $sql = 'UPDATE skills SET system_prompt = :prompt, tone_of_voice = :tov' . ($title ? ', title = :title' : '') . ', updated_at = CURRENT_TIMESTAMP WHERE code = :code';
            $params = [
                ':prompt' => $systemPrompt,
                ':tov'    => $toneOfVoice,
                ':code'   => $code
            ];
            if ($title) $params[':title'] = $title;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $stmtFetch = $pdo->prepare('SELECT * FROM skills WHERE code = :code');
            $stmtFetch->execute([':code' => $code]);
            $updatedSkill = $stmtFetch->fetch();
        }

        if (!$updatedSkill) {
            http_response_code(404);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Скилл не найден'
            ], JSON_UNESCAPED_UNICODE);
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

