<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — CONFIGURATION STORE
 * Dual API Keys support (Fast & Heavy tiers) with backward compatibility.
 * Live keys are isolated in core-data/config.local.php (in .gitignore).
 * =============================================================================
 */

$config = [
    // API Ключи Gemini (раздельные для Free и Flex/Paid тарифов)
    'gemini_fast_api_key'  => getenv('GEMINI_FAST_API_KEY') ?: '', // Ключ для MODEL_FAST (Free Tier / Flash-Lite)
    'gemini_heavy_api_key' => getenv('GEMINI_HEAVY_API_KEY') ?: '', // Ключ для MODEL_HEAVY (Flex Tier / Flash / Pro)
    'gemini_api_key'       => getenv('GEMINI_API_KEY') ?: '', // Универсальный фоллбэк (если используется 1 общий ключ)

    // Telegram
    'telegram_bot_token'   => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'admin_telegram_id'    => getenv('ADMIN_TELEGRAM_ID') ?: '',

    // Внешние парсеры и поисковики
    'tavily_api_key'       => getenv('TAVILY_API_KEY') ?: '',
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
