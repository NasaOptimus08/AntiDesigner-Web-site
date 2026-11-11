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

    // Константы задач
    public const TASK_LONGREAD    = 'vc_ru';
    public const TASK_LEAD_MAGNET = 'lead_magnet';
    public const TASK_TELEGRAM    = 'tg_post';
    public const TASK_TG_DIGEST   = 'tg_digest';
    public const TASK_GEO         = 'site_geo';
    public const TASK_REELS       = 'reels_hook';
    public const TASK_CAROUSEL    = 'ig_carousel';
    public const TASK_SETKA       = 'setka_post';
    public const TASK_COLD_PITCH  = 'cold_pitch';
    public const TASK_WIN_LOSS    = 'win_loss';
    public const TASK_AUTO_PLAN   = 'auto_plan';
    public const TASK_BOT_CHAT    = 'bot_consultant';

    private string $fastApiKey;
    private string $heavyApiKey;
    private string $fallbackApiKey;
    private string $polzaBaseUrl;
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
            'title'         => 'Базовый скилл AntiDesigner',
            'category'      => 'general',
            'system_prompt' => "Ты - ведущий эксперт студии веб-разработки AntiDesigner. Мы делаем сверхбыстрые сайты на чистом коде с гарантией 100/100 в PageSpeed, без тяжелых конструкторов Tilda/WordPress. Пиши емко, дерзко, с цифрами и фактами. Без длинных тире (символ —). Используй только дефис (-) или стрелки (->).",
            'tone_of_voice' => 'Dark Tech, лаконичный, экспертный, фактологический'
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
        if ($key === '') {
            throw new RuntimeException("API-ключ для MODEL_HEAVY (Gemini Flash 3.8, префикс pza...) не указан в core-data/config.php");
        }
        return $key;
    }

    /**
     * Определение модели по типу задачи
     * Разделение обязанностей:
     *  - MODEL_FAST: авто-план, парсинг трендов, первичный аудит 1 экрана, телеграм-диалоги
     *  - MODEL_HEAVY: чистовой копирайтинг всех форматов контент-фабрики, оценка лидов 1-10, питчинг, Win/Loss разбор
     */
    public function resolveModelForTask(string $taskType): string
    {
        return match ($taskType) {
            'plan', 'auto_plan', 'content_planner', 'trends', 'scout_phase1', 'bot_dialogue' => self::MODEL_FAST,
            default => self::MODEL_HEAVY,
        };
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
        $typographyRule = "\n\nСТРОГИЕ ПРАВИЛА ТЕКСТА И ТИПОГРАФИКИ: " .
            "Никогда не используй длинные тире (символ —). Заменяй их исключительно на стандартный дефис (-) или стрелки (->). " .
            "Никаких штампов «в современном мире», «динамично развивающийся» и клише.";

        $fullSystemInstruction = trim($systemInstruction) . $typographyRule;

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
}
