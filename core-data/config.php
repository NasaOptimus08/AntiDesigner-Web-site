<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - CONFIGURATION STORE
 * Dual API Keys support (Fast & Heavy tiers) with strict tier isolation.
 * Live keys are isolated in core-data/config.local.php (in .gitignore).
 * =============================================================================
 */

$config = [
    // Конфигурация AI моделей и разделения обязанностей
    'models' => [
        'fast' => [
            'name'        => 'gemini-3.5-flash-lite',
            'provider'    => 'google_ai_studio',
            'key_field'   => 'gemini_fast_api_key',
            'key_prefix'  => 'AQ',
            'description' => 'Черновая работа, парсинг, диспетчеризация, первичный аудит и мгновенные ответы в чате'
        ],
        'heavy' => [
            'name'        => 'google/gemini-3.8-flash',
            'provider'    => 'polza_ai',
            'key_field'   => 'gemini_heavy_api_key',
            'key_prefix'  => 'pza',
            'description' => 'Глубокая аналитика, оценка лидов (1-10), финальный экспертный копирайтинг, Win/Loss разбор'
        ]
    ],

    // API Ключи Gemini (строго разделены по тирам)
    // Ключ AQ... -> используется ТОЛЬКО при обращении к Gemini Flash-Lite 3.5 (MODEL_FAST)
    'gemini_fast_api_key'  => getenv('GEMINI_FAST_API_KEY') ?: '',

    // Ключ pza... -> используется ТОЛЬКО при обращении к Gemini Flash 3.8 (MODEL_HEAVY)
    'gemini_heavy_api_key' => getenv('GEMINI_HEAVY_API_KEY') ?: '',

    // Универсальный фоллбэк
    'gemini_api_key'       => getenv('GEMINI_API_KEY') ?: '',

    // Провайдер Polza.ai (для Heavy модели)
    'polza_api_url'        => getenv('POLZA_API_URL') ?: 'https://polza.ai/api/v1',

    // Telegram
    'telegram_bot_token'   => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'admin_telegram_id'    => getenv('ADMIN_TELEGRAM_ID') ?: '',
    'telegram_channel_id'  => getenv('TELEGRAM_CHANNEL_ID') ?: '@AntidesignerAI',

    // Внешние парсеры и поисковики
    'tavily_api_key'       => getenv('TAVILY_API_KEY') ?: '',
    'tavily_mcp_url'       => getenv('TAVILY_MCP_URL') ?: 'https://mcp.tavily.com/mcp/?tavilyApiKey=tvly-1x4fsB-nkx8vXKPd2fwHXxdm4638zmsExakAUBuB7lTrGi5G7',
    'serper_api_key'       => getenv('SERPER_API_KEY') ?: '',
    'pagespeed_api_key'    => getenv('PAGESPEED_API_KEY') ?: ''
];

// Автоматическое подключение локальных секретных ключей (не коммитятся в git)
$localConfigFile = __DIR__ . '/config.local.php';
if (file_exists($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

return $config;
