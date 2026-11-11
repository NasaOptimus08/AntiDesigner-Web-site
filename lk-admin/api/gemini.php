<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — GEMINI API CLIENT (Google AI Studio REST cURL)
 * Zero heavy frameworks, native PHP FastCGI execution
 * =============================================================================
 */

/**
 * Отправить запрос к Google Gemini API (AI Studio)
 *
 * @param string $systemInstruction Системный промпт (роль, правила, ToV)
 * @param string $userPrompt Промпт пользователя (тема, задача, данные)
 * @param string $model Модель Gemini (по умолчанию gemini-1.5-flash)
 * @return string Сгенерированный текстовый ответ
 * @throws RuntimeException При сетевых ошибках, отсутствии ключа или сбое API
 */
function callGemini(string $systemInstruction, string $userPrompt, string $model = 'gemini-1.5-flash'): string
{
    $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
    if (!file_exists($configFile)) {
        throw new RuntimeException('Конфигурационный файл core-data/config.php не найден');
    }

    $config = require $configFile;
    $apiKey = trim((string)($config['gemini_api_key'] ?? ''));

    if ($apiKey === '' || $apiKey === 'YOUR_GEMINI_API_KEY') {
        throw new RuntimeException('Укажите Gemini API ключ в core-data/config.php');
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.75,
            'topP' => 0.95,
            'maxOutputTokens' => 4096
        ]
    ];

    if (trim($systemInstruction) !== '') {
        $payload['system_instruction'] = [
            'parts' => [
                ['text' => trim($systemInstruction)]
            ]
        ];
    }

    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonBody === false) {
        throw new RuntimeException('Ошибка сериализации JSON для запроса к Gemini: ' . json_last_error_msg());
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlErrno !== 0) {
        throw new RuntimeException("cURL ошибка при запросе к Gemini ({$curlErrno}): {$curlError}");
    }

    if ($rawResponse === false || trim((string)$rawResponse) === '') {
        throw new RuntimeException("Пустой ответ от Gemini API (HTTP {$httpCode})");
    }

    $decoded = json_decode((string)$rawResponse, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Не удалось распарсить JSON от Gemini (HTTP {$httpCode}): " . substr((string)$rawResponse, 0, 300));
    }

    if ($httpCode !== 200) {
        $errorMsg = $decoded['error']['message'] ?? ('HTTP код ошибки: ' . $httpCode);
        $errorStatus = $decoded['error']['status'] ?? 'UNKNOWN';
        throw new RuntimeException("Gemini API Error [{$errorStatus}]: {$errorMsg}");
    }

    $candidate = $decoded['candidates'][0] ?? null;
    if (!$candidate) {
        $blockReason = $decoded['promptFeedback']['blockReason'] ?? 'Без указания причины';
        throw new RuntimeException("Запрос заблокирован Gemini фильтрами безопасности: {$blockReason}");
    }

    $generatedText = $candidate['content']['parts'][0]['text'] ?? null;
    if ($generatedText === null) {
        $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';
        throw new RuntimeException("Gemini не вернул текстовое содержимое (finishReason: {$finishReason})");
    }

    return trim((string)$generatedText);
}

