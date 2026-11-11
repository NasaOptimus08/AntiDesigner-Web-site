<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — AI DIAGNOSTICS & PING TEST
 * Tests both Fast (Google AI Studio) and Heavy (Polza.ai / Gemini 3.8) models.
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
            'provider'       => 'Google AI Studio',
            'key_configured' => false,
            'ping_response'  => 'SKIPPED (No key)'
        ],
        'heavy_model' => [
            'model'                    => $heavyModelName,
            'provider'                 => 'Polza.ai (OpenAI-compatible)',
            'key_configured'           => false,
            'ping_response'            => 'SKIPPED (No key)',
            'reasoning_disabled_check' => true
        ]
    ];

    // -------------------------------------------------------------------------
    // 1. Тестирование Fast Model (Google AI Studio: gemini-3.5-flash-lite)
    // -------------------------------------------------------------------------
    if ($router->isFastKeyConfigured()) {
        $report['fast_model']['key_configured'] = true;
        try {
            $fastText = trim($router->ping($fastModelName));
            $report['fast_model']['ping_response'] = $fastText !== '' ? $fastText : 'EMPTY_RESPONSE';
        } catch (Throwable $e) {
            $report['fast_model']['ping_response'] = 'ERROR: ' . $e->getMessage();
        }
    }

    // -------------------------------------------------------------------------
    // 2. Тестирование Heavy Model (Polza.ai: google/gemini-3.8-flash)
    // -------------------------------------------------------------------------
    if ($router->isHeavyKeyConfigured()) {
        $report['heavy_model']['key_configured'] = true;
        try {
            $heavyText = trim($router->ping($heavyModelName));
            $report['heavy_model']['ping_response'] = $heavyText !== '' ? $heavyText : 'EMPTY_RESPONSE';
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
