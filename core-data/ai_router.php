<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - AI ROUTER (Dual-Tier Model & Budget Orchestrator)
 * Routes requests to Fast (Google AI Studio: Gemini Flash-Lite 3.5, key AQ...)
 * or Heavy (Polza.ai: Gemini Flash 3.8, key pza...) with strict tier isolation,
 * dynamic skill loading from SQLite, and strict typography rules.
 * =============================================================================
 */

class AIRouter
{
    // Модели
    public const MODEL_FAST  = 'gemini-3.5-flash-lite';     // Google AI Studio (черновая работа, скаут, чат)
    public const MODEL_HEAVY = 'google/gemini-3.8-flash';    // Polza.ai (главред, аналитик, снайпер продаж)

    // Константы FAST задач (MODEL_FAST, ключ gemini_fast_api_key на AQ...)
    public const TASK_TG_CHAT          = 'tg_chat';
    public const TASK_RAW_PARSING      = 'raw_parsing';
    public const TASK_SITE_CODE_AUDIT  = 'site_code_audit';
    public const TASK_CONTENT_PLANNER  = 'content_planner';

    // Константы HEAVY задач (MODEL_HEAVY, ключ gemini_heavy_api_key на pza...)
    public const TASK_LONGREAD         = 'vc_ru';
    public const TASK_TG_POST          = 'tg_post';
    public const TASK_SETKA_POST       = 'setka_post';
    public const TASK_REELS            = 'reels_hook';
    public const TASK_CAROUSEL         = 'ig_carousel';
    public const TASK_GEO_ARTICLE      = 'site_geo';
    public const TASK_LEAD_MAGNET      = 'lead_magnet';
    public const TASK_COLD_PITCH       = 'cold_pitch';
    public const TASK_CRM_ANALYTICS    = 'crm_analytics';

    // Алиасы для обратной совместимости
    public const TASK_TELEGRAM         = self::TASK_TG_POST;
    public const TASK_TG_DIGEST        = 'tg_digest';
    public const TASK_GEO              = self::TASK_GEO_ARTICLE;
    public const TASK_SETKA            = self::TASK_SETKA_POST;
    public const TASK_WIN_LOSS         = self::TASK_CRM_ANALYTICS;
    public const TASK_AUTO_PLAN        = self::TASK_CONTENT_PLANNER;
    public const TASK_BOT_CHAT         = self::TASK_TG_CHAT;

    // Глобальный защитный протокол "Анти-галлюцинация, личный бренд и 100% честность"
    public const ZERO_HALLUCINATIONS_PROTOCOL = <<<PROMPT

ПОЗИЦИОНИРОВАНИЕ АВТОРА И ЛИЧНЫЙ БРЕНД (AntiDesigner):
1. ЛИЧНЫЙ БРЕНД НЕЗАВИСИМОГО РАЗРАБОТЧИКА:
   - AntiDesigner - это НЕ агентство и НЕ студия. Это сильный ЛИЧНЫЙ БРЕНД одного независимого разработчика. Зовут автора: Антипин Евгений (Solo Senior Dev).
   - Вся коммуникация строится строго от первого лица единственного числа: «Я», «мой подход», «я верстаю», «я разрабатываю», «в моих проектах», «моя гарантия».
   - КАТЕГОРИЧЕСКИЙ ЗАПРЕТ на корпоративное "МЫ": полностью запрещены слова «мы», «наша команда», «наше агентство», «наша студия», «мы сделаем», «у нас», «наши специалисты». Запрещено притворяться компанией.
   - В TELEGRAM-ДИАЛОГАХ (БОТ / МЕССЕНДЖЕР / КЛИЕНТСКИЙ АССИСТЕНТ):
     * Ты больше НЕ являешься «роботом» или «ассистентом». Ты общаешься НАПРЯМУЮ ОТ ЛИЦА АВТОРА - как сам Евгений Антипин.
     * Для клиента это полноценный личный диалог с Евгением в его личном Telegram.
     * КАТЕГОРИЧЕСКИЙ ЗАПРЕТ НА ТРЕТЬИ ЛИЦА: Запрещены фразы «Я ассистент Евгения», «Я бот компании», «Сейчас переведу диалог на Евгения Антипина», «Я передам ваш вопрос разработчику», «Евгений разрабатывает».
     * Вся речь строится строго от первого лица: «Я», «мой опыт», «я посмотрю ваш сайт», «я верстаю на чистом коде», «я сделаю за 4 дня».
2. В ЧЕМ СИЛА ОДИНОЧНОГО РАЗРАБОТЧИКА (ГЛАВНОЕ УТП):
   - Один опытный разработчик на чистом коде делает сайт быстрее, чище и дешевле, чем раздутая студия из 10 человек (где клиент переплачивает за аккаунт-менеджеров, созвоны и аренду офиса, а получает шаблон на Тильде). Клиент работает напрямую с автором кода без посредников и испорченного телефона.
3. ТАРИФЫ И ЛИЧНЫЕ ОФФЕРЫ (ОТ ЛИЦА ЕВГЕНИЯ):
   - «Я сделаю экспресс-аудит вашего сайта», «Я разрабатываю сайты на чистом коде без конструкторов за 3-5 дней», «Лендинг на чистом HTML/CSS/JS делаю за 18 000 - 25 000 руб со скоростью 0.3 секунды», «Сложные проекты - от 35 000 руб», «В моих проектах 100/100 в Google PageSpeed, без абонентских плат конструкторам».

ЖЕСТКИЙ ПРОТОКОЛ ФАКТОЛОГИЧЕСКОЙ ЧЕСТНОСТИ (ZERO HALLUCINATIONS):
1. СТРОГИЙ ЗАПРЕТ НА ВЫДУМЫВАНИЕ ЦИФР И СТАТИСТИКИ:
   - Запрещено придумывать фальшивые проценты ("99% сайтов теряют клиентов", "конверсия вырастет ровно в 10 раз").
   - Используй только реальные технические стандарты Google Core Web Vitals и фактические данные, переданные в контексте.
   - Если точная цифра неизвестна - пиши вилку или аргументируй логикой без выдуманных данных.

2. ЧЕСТНОСТЬ В ПЕРЕПИСКАХ С КЛИЕНТАМИ В TELEGRAM:
   - Ты общаешься в живой разговорной манере Senior-разработчика с телефона. Короткие естественные реплики (1-3 предложения), без канцелярщины и шаблонов «Здравствуйте, чем я могу вам помочь?».
   - Не давать пустых обещаний ("ваш сайт будет на 1 месте в Яндексе за 2 дня").
   - Если нужно время подумать или клиент задает сложный технический вопрос (нестандартный стек, Python/Django, сложные интеграции) - НЕ говори «передам Евгению», отвечай как сам Евгений:
     «Понял задачу. Сейчас как раз за компом, гляну исходники вашего сайта и через 10-15 минут напишу точный вердикт» или «Зафиксировал. Давайте созвонимся на 10 минут в Telegram, я прямо на экране покажу замеры и все посчитаю».
   - Открыто и прямо называть тарифы (18к-25к, 25к-35к, 35к+) без скрытых условий.
   - В конце диалога всегда предлагать следующий простой шаг:
     «Скиньте ссылку на текущий сайт (если есть) - я гляну скорость и ошибки первого экрана» или «Удобно на 10 минут в Zoom/TG созвониться завтра? Покажу экран и наглядно разберу проект».

3. ОБЪЕКТИВНОСТЬ В ХОЛОДНОМ АУДИТЕ И ОЦЕНКЕ ЛИДОВ (1-10):
   - Запрещено завышать баллы из вежливости. Оценка вероятности сделки (deal_score) должна быть циничной и строгой. Если сайт клиента объективно хороший и быстрый - честно ставь низкий балл (< 5) и отбраковывай.
   - В питче указывать ТОЛЬКО реальные ошибки, которые вернул PageSpeed API и показал скриншот. Если скорость 85/100 - нельзя врать, что сайт медленный.

4. СТИЛЬ И ТОН:
   - Полный отказ от инфоцыганских клише ("уникальное торговое предложение", "взрывной рост", "сайт под ключ нового поколения").
   - Тон: спокойный, уверенный, технически грамотный эксперт по чистому коду.
   - СТРОГИЙ ЗАПРЕТ на длинные тире (символ —). Заменяй их исключительно на стандартный дефис (-) или стрелки (->).
PROMPT;

    private string $fastApiKey;
    private string $heavyApiKey;
    private string $fallbackApiKey;
    private string $polzaBaseUrl;
    private string $tavilyApiKey = '';
    private string $tavilyMcpUrl = '';
    private ?PDO $pdo = null;

    /**
     * @param array<string, mixed>|string $configOrFastKey Массив конфигурации или ключ для Fast
     * @param string $heavyKey Опциональный ключ для Heavy
     */
    public function __construct(array|string $configOrFastKey = [], string $heavyKey = '')
    {
        if (is_array($configOrFastKey)) {
            $config = $configOrFastKey;
            if (empty($config)) {
                $configFile = __DIR__ . '/config.php';
                $config = file_exists($configFile) ? require $configFile : [];
            }

            $this->fallbackApiKey = trim((string)($config['gemini_api_key'] ?? ''));
            $this->fastApiKey     = trim((string)($config['gemini_fast_api_key'] ?? '')) ?: $this->fallbackApiKey;
            $this->heavyApiKey    = trim((string)($config['gemini_heavy_api_key'] ?? '')) ?: $this->fallbackApiKey;
            $this->polzaBaseUrl   = rtrim((string)($config['polza_api_url'] ?? 'https://polza.ai/api/v1'), '/');
            $this->tavilyApiKey   = trim((string)($config['tavily_api_key'] ?? ''));
            $this->tavilyMcpUrl   = trim((string)($config['tavily_mcp_url'] ?? ''));
        } else {
            $this->fastApiKey     = trim($configOrFastKey);
            $this->heavyApiKey    = trim($heavyKey) ?: $this->fastApiKey;
            $this->fallbackApiKey = $this->fastApiKey;
            $this->polzaBaseUrl   = 'https://polza.ai/api/v1';
        }
    }

    /**
     * Установка подключения к базе данных для загрузки скиллов
     */
    public function setPDO(PDO $pdo): self
    {
        $this->pdo = $pdo;
        return $this;
    }

    /**
     * Получение PDO коннектора к базе
     */
    public function getPDO(): PDO
    {
        if ($this->pdo === null) {
            $dbPath = __DIR__ . '/database.sqlite';
            $this->pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $this->pdo->exec('PRAGMA journal_mode = WAL;');
            $this->pdo->exec('PRAGMA busy_timeout = 5000;');
        }
        return $this->pdo;
    }

    /**
     * Загрузка скилла из таблицы skills по коду
     */
    public function loadSkill(string $skillCode): array
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->prepare("SELECT code, title, category, system_prompt, tone_of_voice FROM skills WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $skillCode]);
        $row = $stmt->fetch();

        if ($row && is_array($row)) {
            return $row;
        }

        // Альтернативные соответствия
        $aliases = [
            'reels'       => 'reels_hook',
            'telegram'    => 'tg_post',
            'article'     => 'vc_ru',
            'setka'       => 'setka_post',
            'instagram'   => 'reels_hook',
            'carousel'    => 'ig_carousel',
            'plan'        => 'content_planner',
            'auto_plan'   => 'content_planner'
        ];

        if (isset($aliases[$skillCode])) {
            $stmt->execute([':code' => $aliases[$skillCode]]);
            $row = $stmt->fetch();
            if ($row && is_array($row)) {
                return $row;
            }
        }

        // Фолбэк базовый промпт
        return [
            'code'          => $skillCode,
            'title'         => 'Базовый скилл AntiDesigner (Евгений Антипин)',
            'category'      => 'general',
            'system_prompt' => "Ты - Евгений Антипин, независимый Senior разработчик (личный бренд AntiDesigner). Я создаю сверхбыстрые сайты на чистом коде с гарантией 100/100 в PageSpeed, без тяжелых конструкторов Tilda/WordPress и студийных наценок. Пиши строго от первого лица («Я», «мой подход»), емко, дерзко, с цифрами и фактами. Без длинных тире (символ —). Используй только дефис (-) или стрелки (->).",
            'tone_of_voice' => 'Dark Tech, авторский, экспертный, фактологический, от первого лица (Я)'
        ];
    }

    /**
     * Проверка, использует ли ключ/модель провайдер Polza.ai
     */
    public function isPolzaModel(string $model): bool
    {
        if ($model === self::MODEL_FAST || str_contains($model, 'lite')) {
            return false;
        }
        return ($model === self::MODEL_HEAVY)
            || str_starts_with($this->heavyApiKey, 'pza')
            || str_contains($model, 'google/gemini')
            || str_contains($model, 'gemini-3.8');
    }

    /**
     * Получить активный API-ключ для указанной модели с проверкой префиксов
     */
    public function getApiKeyForModel(string $model): string
    {
        $isFast = ($model === self::MODEL_FAST)
            || str_contains($model, 'lite')
            || str_contains($model, 'flash-lite');

        if ($isFast) {
            $key = $this->fastApiKey;
            if ($key === '' && str_starts_with($this->fallbackApiKey, 'AQ')) {
                $key = $this->fallbackApiKey;
            }
            if (str_starts_with($key, 'pza')) {
                throw new RuntimeException("Для MODEL_FAST (Flash-Lite) передан ключ Polza.ai (начинается на pza...). Требуется ключ Google AI Studio (префикс AQ...)");
            }
            if ($key === '') {
                throw new RuntimeException("API-ключ для MODEL_FAST (Gemini Flash-Lite, префикс AQ...) не указан в core-data/config.php");
            }
            return $key;
        }

        // Для Heavy модели (Gemini Flash 3.8 / Polza.ai)
        $key = $this->heavyApiKey;
        if ($key === '' && str_starts_with($this->fallbackApiKey, 'pza')) {
            $key = $this->fallbackApiKey;
        }
        if (str_starts_with($key, 'AQ')) {
            throw new RuntimeException("Для MODEL_HEAVY (Flash 3.8) передан ключ Google AI Studio (начинается на AQ...). Требуется ключ Polza.ai (префикс pza...)");
        }
        if ($key === '') {
            throw new RuntimeException("API-ключ для MODEL_HEAVY (Gemini Flash 3.8, префикс pza...) не указан в core-data/config.php");
        }
        return $key;
    }

    /**
     * Определение модели по типу задачи
     * Разделение обязанностей:
     *  - MODEL_FAST (ключ gemini_fast_api_key на AQ...):
     *      * TASK_TG_CHAT (диалоги в Telegram)
     *      * TASK_RAW_PARSING (первичный парсинг трендов и сайтов)
     *      * TASK_SITE_CODE_AUDIT (первичный разбор кода/дизайна 1-го экрана)
     *      * TASK_CONTENT_PLANNER (быстрое проектирование сетки контент-плана на 1-2 дня)
     *
     *  - MODEL_HEAVY (ключ gemini_heavy_api_key на pza...):
     *      * TASK_LONGREAD (статьи VC.ru / Habr)
     *      * TASK_TG_POST (посты в Telegram, девлоги и новости)
     *      * TASK_SETKA_POST (посты для Сетки)
     *      * TASK_REELS (сценарии Reels / Shorts)
     *      * TASK_CAROUSEL (слайды для каруселей)
     *      * TASK_GEO_ARTICLE (SEO/GEO-статьи на сайт)
     *      * TASK_LEAD_MAGNET (структура и тексты PDF лид-магнитов)
     *      * TASK_COLD_PITCH (персонализированные холодные питчи)
     *      * TASK_CRM_ANALYTICS (аналитика и Win/Loss разбор сделок)
     */
    public function resolveModel(string $taskType): string
    {
        return match ($taskType) {
            // Строго FAST задачи
            self::TASK_TG_CHAT,
            'bot_dialogue',
            'bot_consultant',
            self::TASK_RAW_PARSING,
            'trends',
            'scout_phase1',
            self::TASK_SITE_CODE_AUDIT,
            self::TASK_CONTENT_PLANNER,
            'plan',
            'auto_plan' => self::MODEL_FAST,

            // ВСЕ задачи копирайтинга и глубокого анализа — строго HEAVY
            default => self::MODEL_HEAVY,
        };
    }

    public function resolveModelForTask(string $taskType): string
    {
        return $this->resolveModel($taskType);
    }

    /**
     * Сборка итогового системного промпта с обязательным сквозным протоколом Zero Hallucinations
     */
    public function buildFullSystemInstruction(string $customInstruction = ''): string
    {
        $base = trim($customInstruction);
        $full = ($base !== '' ? $base . "\n\n" : '') . self::ZERO_HALLUCINATIONS_PROTOCOL;
        return str_replace(['—', '–'], '-', trim($full));
    }

    /**
     * Универсальный метод вызова модели (для совместимости со скаутом и скриптами)
     */
    public function call(
        string $userPrompt,
        string $systemInstruction = '',
        string $model = self::MODEL_FAST,
        float $temperature = 0.7,
        ?int $thinkingBudget = null
    ): string {
        $fullSystemInstruction = $this->buildFullSystemInstruction($systemInstruction);

        if ($this->isPolzaModel($model)) {
            $generatedText = $this->sendPolzaRequest($model, $fullSystemInstruction, $userPrompt, $temperature, $thinkingBudget);
        } else {
            $generatedText = $this->sendGoogleRequest($model, $fullSystemInstruction, $userPrompt, $temperature, $thinkingBudget);
        }

        return str_replace(['—', '–'], '-', trim($generatedText));
    }

    /**
     * Выполнение генерации контента через соответствующую модель с загрузкой скилла
     */
    public function execute(string $taskType, string $systemInstruction, string $userPrompt, ?int $thinkingBudget = null): string
    {
        $model = $this->resolveModelForTask($taskType);

        // Для лонгридов и Win/Loss анализа по умолчанию задаем thinkingBudget = 2048
        if ($thinkingBudget === null && in_array($taskType, [self::TASK_LONGREAD, 'vc_ru', self::TASK_WIN_LOSS, 'win_loss'], true)) {
            $thinkingBudget = 2048;
        }

        return $this->call($userPrompt, $systemInstruction, $model, 0.7, $thinkingBudget);
    }

    /**
     * Прямая генерация через конкретную модель (например, MODEL_FAST)
     */
    public function generateWithModel(string $model, string $systemInstruction, string $userPrompt, ?int $thinkingBudget = null): string
    {
        return $this->call($userPrompt, $systemInstruction, $model, 0.7, $thinkingBudget);
    }

    /**
     * Запрос к Polza.ai (OpenAI-совместимый API)
     */
    public function sendPolzaRequest(
        string $model,
        string $systemInstruction,
        string $userPrompt,
        float $temperature = 0.7,
        ?int $thinkingBudget = null
    ): string {
        $apiKey = $this->getApiKeyForModel($model);
        $url = "{$this->polzaBaseUrl}/chat/completions";

        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemInstruction],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => $temperature
        ];

        // Точечное подключение thinkingBudget (2048) для глубокого анализа
        if ($thinkingBudget !== null && $thinkingBudget > 0) {
            $payload['extra_body'] = [
                'thinking_budget' => $thinkingBudget
            ];
        }

        $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonData,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$apiKey}",
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new RuntimeException("cURL ошибка Polza.ai [{$model}]: {$curlError}");
        }

        if (!is_string($responseBody) || $responseBody === '') {
            throw new RuntimeException("Polza.ai [{$model}] вернул пустой ответ (HTTP {$httpCode})");
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Ошибка декодирования ответа Polza.ai (HTTP {$httpCode})");
        }

        if ($httpCode !== 200 || isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("Polza.ai Error [{$httpCode}]: " . (is_string($msg) ? $msg : json_encode($msg)));
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            throw new RuntimeException("Polza.ai вернул пустой текст ответа");
        }

        return (string)$content;
    }

    /**
     * Запрос к Google AI Studio (Native REST API)
     */
    public function sendGoogleRequest(
        string $model,
        string $systemInstruction,
        string $userPrompt,
        float $temperature = 0.7,
        ?int $thinkingBudget = null
    ): string {
        $apiKey = $this->getApiKeyForModel($model);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $generationConfig = [
            'temperature'     => $temperature,
            'maxOutputTokens' => 8192
        ];

        if ($thinkingBudget !== null && $thinkingBudget > 0) {
            $generationConfig['thinkingConfig'] = [
                'thinkingBudget' => $thinkingBudget
            ];
        }

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $userPrompt]
                    ]
                ]
            ],
            'generationConfig' => $generationConfig
        ];

        $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonData,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new RuntimeException("cURL ошибка Google AI [{$model}]: {$curlError}");
        }

        if (!is_string($responseBody) || $responseBody === '') {
            throw new RuntimeException("Google AI [{$model}] вернул пустой ответ (HTTP {$httpCode})");
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Ошибка декодирования ответа Google AI (HTTP {$httpCode})");
        }

        if ($httpCode !== 200 || isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("Google AI Error [{$httpCode}]: {$msg}");
        }

        $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($content === '') {
            throw new RuntimeException("Google AI вернул пустой текст ответа");
        }

        return (string)$content;
    }

    /**
     * Микро-пинг для диагностики
     */
    public function ping(string $model): string
    {
        if ($this->isPolzaModel($model)) {
            return $this->sendPolzaRequest($model, 'Ты - помощник. Отвечай кратко.', 'Ответь одним словом: OK');
        }
        return $this->sendGoogleRequest($model, 'Ты - помощник. Отвечай кратко.', 'Ответь одним словом: OK');
    }

    public function isFastKeyConfigured(): bool
    {
        return $this->fastApiKey !== '';
    }

    public function isHeavyKeyConfigured(): bool
    {
        return $this->heavyApiKey !== '';
    }

    public function getFastModelName(): string
    {
        return self::MODEL_FAST;
    }

    public function getHeavyModelName(): string
    {
        return self::MODEL_HEAVY;
    }

    public function getTavilyApiKey(): string
    {
        return $this->tavilyApiKey;
    }

    public function getTavilyMcpUrl(): string
    {
        return $this->tavilyMcpUrl;
    }

    /**
     * Вызов инструментов Tavily MCP для модели MODEL_FAST
     */
    public function executeFastWithTavily(string $toolName, array $args = []): array
    {
        require_once __DIR__ . '/tools/search_tool.php';
        return callTavilyMCP($toolName, $args, [
            'tavily_api_key' => $this->tavilyApiKey,
            'tavily_mcp_url' => $this->tavilyMcpUrl
        ]);
    }
}
