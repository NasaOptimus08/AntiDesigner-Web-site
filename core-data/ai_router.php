<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — AI ROUTER (Dual-Tier Model & Budget Orchestrator)
 * Routes requests to Fast or Heavy Gemini models with strict cost controls.
 * =============================================================================
 */

class AIRouter
{
    // Модели Gemini
    public const MODEL_FAST = 'gemini-3.5-flash-lite';
    public const MODEL_HEAVY = 'gemini-3.8-flash';

    // Типы контентных задач
    public const TASK_LONGREAD    = 'vc_ru';
    public const TASK_LEAD_MAGNET = 'lead_magnet';
    public const TASK_TELEGRAM    = 'tg_post';
    public const TASK_GEO         = 'site_geo';
    public const TASK_REELS       = 'reels';

    private string $fastApiKey;
    private string $heavyApiKey;
    private string $fallbackApiKey;

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
        } else {
            $this->fastApiKey     = trim($configOrFastKey);
            $this->heavyApiKey    = trim($heavyKey) ?: $this->fastApiKey;
            $this->fallbackApiKey = $this->fastApiKey;
        }
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

        // Если специфический ключ пуст, пробуем универсальный фоллбэк
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

        // 2. Формирование тела запроса (payload)
        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $fullSystemInstruction]
                ]
            ],
            'contents' => [
                [
                    'role' => 'user',
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

        // 3. Жесткая экономия для Heavy: отключаем платный reasoning и внешние tools
        if ($model === self::MODEL_HEAVY) {
            $payload['generationConfig']['thinkingConfig'] = [
                'thinkingBudget' => 0
            ];
            // Отключаем платные инструменты Google Search Grounding если не запрошены явно
            unset($payload['tools']);
        }

        // 4. Отправка запроса
        $response = $this->sendRequest($model, $payload);

        // 5. Извлечение сгенерированного текста
        $generatedText = '';
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $generatedText = (string)$response['candidates'][0]['content']['parts'][0]['text'];
        } elseif (isset($response['candidates'][0]['finishReason'])) {
            $reason = (string)$response['candidates'][0]['finishReason'];
            throw new RuntimeException("Генерация остановлена фильтром безопасности: finishReason = {$reason}");
        } else {
            throw new RuntimeException('Модель вернула пустой ответ или неожиданный формат данных');
        }

        // 6. Постобработка: гарантия отсутствия длинных тире в готовом контенте
        return str_replace(['—', '–'], '-', trim($generatedText));
    }

    /**
     * Низкоуровневая отправка HTTP cURL-запроса к Google AI Studio
     */
    public function sendRequest(string $model, array $payload): array
    {
        $apiKey = $this->getApiKeyForModel($model);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            throw new RuntimeException('Ошибка сериализации JSON для Gemini API: ' . json_last_error_msg());
        }

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
            throw new RuntimeException("Сетевая ошибка cURL при обращении к модели [{$model}]: {$curlError}");
        }

        if (!is_string($responseBody) || $responseBody === '') {
            throw new RuntimeException("Модель [{$model}] вернула пустой HTTP-ответ (HTTP {$httpCode})");
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Не удалось декодировать ответ Gemini API (HTTP {$httpCode}): " . substr($responseBody, 0, 300));
        }

        if ($httpCode !== 200 || isset($decoded['error'])) {
            $errorMessage = $decoded['error']['message'] ?? "HTTP ошибка {$httpCode}";
            $errorCode    = $decoded['error']['code'] ?? $httpCode;
            throw new RuntimeException("Gemini API Error [{$errorCode}] для модели [{$model}]: {$errorMessage}");
        }

        return $decoded;
    }

    /**
     * Геттеры для проверки состояния
     */
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
