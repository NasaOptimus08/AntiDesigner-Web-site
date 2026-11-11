<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — AI DIAGNOSTICS & PING TEST
 * Verifies Dual-Tier Gemini API keys and connectivity without crashing.
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    require_once dirname(__DIR__, 2) . '/core-data/ai_router.php';

    $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
    $config = file_exists($configFile) ? require $configFile : [];

    $router = new AIRouter($config);

    $fastModelName  = $router->getFastModelName();
    $heavyModelName = $router->getHeavyModelName();

    $report = [
        'status'      => 'success',
        'fast_model'  => [
            'model'          => $fastModelName,
            'key_configured' => false,
            'ping_response'  => 'SKIPPED (No key)'
        ],
        'heavy_model' => [
            'model'                    => $heavyModelName,
            'key_configured'           => false,
            'ping_response'            => 'SKIPPED (No key)',
            'reasoning_disabled_check' => true
        ]
    ];

    // -------------------------------------------------------------------------
    // 1. Тестирование Fast Model (Free Tier / Flash-Lite)
    // -------------------------------------------------------------------------
    if ($router->isFastKeyConfigured()) {
        $report['fast_model']['key_configured'] = true;

        $fastPayload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => 'Ответь одним словом: OK']
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 16,
                'temperature'     => 0.1
            ]
        ];

        try {
            $fastRes = $router->sendRequest($fastModelName, $fastPayload);
            $text = trim((string)($fastRes['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            $report['fast_model']['ping_response'] = $text !== '' ? $text : 'EMPTY_RESPONSE';
        } catch (Throwable $e) {
            $report['fast_model']['ping_response'] = 'ERROR: ' . $e->getMessage();
        }
    }

    // -------------------------------------------------------------------------
    // 2. Тестирование Heavy Model (Flex / Flash / Pro) с thinkingBudget = 0
    // -------------------------------------------------------------------------
    if ($router->isHeavyKeyConfigured()) {
        $report['heavy_model']['key_configured'] = true;

        $heavyPayload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => 'Ответь одним словом: OK']
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 16,
                'temperature'     => 0.1,
                'thinkingConfig'  => [
                    'thinkingBudget' => 0 // Принудительное отключение платного reasoning
                ]
            ]
        ];

        try {
            $heavyRes = $router->sendRequest($heavyModelName, $heavyPayload);
            $text = trim((string)($heavyRes['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            $report['heavy_model']['ping_response'] = $text !== '' ? $text : 'EMPTY_RESPONSE';
        } catch (Throwable $e) {
            $report['heavy_model']['ping_response'] = 'ERROR: ' . $e->getMessage();
        }
    }

    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $fatal) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Фатальная ошибка модуля диагностики: ' . $fatal->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
