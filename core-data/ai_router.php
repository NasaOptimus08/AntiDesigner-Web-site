<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - AI ROUTER (Dual-Tier Model & Budget Orchestrator)
 * Routes requests to Fast (Google AI Studio) or Heavy (Polza.ai / Gemini 3.8)
 * with strict cost controls and typography rules.
 * =============================================================================
 */

class AIRouter
{
    // Модели
    public const MODEL_FAST  = 'gemini-3.5-flash-lite';     // Google AI Studio
    public const MODEL_HEAVY = 'google/gemini-3.8-flash';    // Polza.ai

    // Константы задач
    public const TASK_LONGREAD    = 'vc_ru';
    public const TASK_LEAD_MAGNET = 'lead_magnet';
    public const TASK_TELEGRAM    = 'tg_post';
    public const TASK_GEO         = 'site_geo';
    public const TASK_REELS       = 'reels';

    private string $fastApiKey;
    private string $heavyApiKey;
    private string $fallbackApiKey;
    private string $polzaBaseUrl;

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
     * Проверка, использует ли ключ/модель провайдер Polza.ai
     */
    public function isPolzaModel(string $model): bool
    {
        if ($model === self::MODEL_FAST || str_contains($model, 'lite')) {
            return false;
        }
        return ($model === self::MODEL_HEAVY)
            || str_starts_with($this->heavyApiKey, 'pza_')
            || str_contains($model, 'google/gemini');
    }

    /**
     * Получить активный API-ключ для указанной модели
     */
    public function getApiKeyForModel(string $model): string
    {
        $isFast = ($model === self::MODEL_FAST)
            || str_contains($model, 'lite')
            || str_contains($model, 'flash-lite');

        $key = $isFast ? $this->fastApiKey : $this->heavyApiKey;

        if ($key === '') {
            $key = $this->fallbackApiKey;
        }

        if ($key === '') {
            throw new RuntimeException("API-ключ для модели [{$model}] не указан в core-data/config.php");
        }

        return $key;
    }

    /**
     * Определение модели по типу задачи
     */
    public function resolveModelForTask(string $taskType): string
    {
        return match ($taskType) {
            self::TASK_LONGREAD, 'longread', self::TASK_LEAD_MAGNET => self::MODEL_HEAVY,
            default => self::MODEL_FAST,
        };
    }

    /**
     * Выполнение генерации контента через соответствующую модель
     */
    public function execute(string $taskType, string $systemInstruction, string $userPrompt): string
    {
        $model = $this->resolveModelForTask($taskType);

        // 1. Принудительный запрет длинных тире и водянистых фраз в системном промпте
        $typographyRule = "\n\nСТРОГИЕ ПРАВИЛА ТЕКСТА И ТИПОГРАФИКИ: " .
            "Никогда не используй длинные тире (символ —). Заменяй их исключительно на стандартный дефис (-) или стрелки (->). " .
            "Никаких штампов «в современном мире», «динамично развивающийся» и клише.";

        $fullSystemInstruction = trim($systemInstruction) . $typographyRule;

        // 2. Отправка запроса в зависимости от провайдера
        if ($this->isPolzaModel($model)) {
            $generatedText = $this->sendPolzaRequest($model, $fullSystemInstruction, $userPrompt);
        } else {
            $generatedText = $this->sendGoogleRequest($model, $fullSystemInstruction, $userPrompt);
        }

        // 3. Постобработка: гарантия отсутствия длинных тире в готовом контенте
        return str_replace(['—', '–'], '-', trim($generatedText));
    }

    /**
     * Прямая генерация через конкретную модель (например, MODEL_FAST)
     */
    public function generateWithModel(string $model, string $systemInstruction, string $userPrompt): string
    {
        $typographyRule = "\n\nСТРОГИЕ ПРАВИЛА ТЕКСТА И ТИПОГРАФИКИ: " .
            "Никогда не используй длинные тире (символ —). Заменяй их исключительно на стандартный дефис (-) или стрелки (->). " .
            "Никаких штампов «в современном мире», «динамично развивающийся» и клише.";

        $fullSystemInstruction = trim($systemInstruction) . $typographyRule;

        if ($this->isPolzaModel($model)) {
            $generatedText = $this->sendPolzaRequest($model, $fullSystemInstruction, $userPrompt);
        } else {
            $generatedText = $this->sendGoogleRequest($model, $fullSystemInstruction, $userPrompt);
        }

        return str_replace(['—', '–'], '-', trim($generatedText));
    }

    /**
     * Запрос к Polza.ai (OpenAI-совместимый API)
     */
    public function sendPolzaRequest(string $model, string $systemInstruction, string $userPrompt): string
    {
        $apiKey = $this->getApiKeyForModel($model);
        $url = "{$this->polzaBaseUrl}/chat/completions";

        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemInstruction],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.7
        ];

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
    public function sendGoogleRequest(string $model, string $systemInstruction, string $userPrompt): string
    {
        $apiKey = $this->getApiKeyForModel($model);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

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
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 8192
            ]
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
