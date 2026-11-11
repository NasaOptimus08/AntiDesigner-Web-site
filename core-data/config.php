<?php
declare(strict_types=1);

/**
 * AntiDesigner Core — Базовая конфигурация и ключи доступа к внешним сервисам.
 * Локальные боевые ключи автоматически подгружаются из core-data/config.local.php (в .gitignore).
 */
$config = [
    'gemini_api_key'     => getenv('GEMINI_API_KEY') ?: 'YOUR_GEMINI_API_KEY_HERE', // Google AI Studio (Gemini 1.5 Flash / Pro)
    'telegram_bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '', // Telegram Bot API Token
    'admin_telegram_id'  => getenv('ADMIN_TELEGRAM_ID') ?: '', // ID администратора в Telegram для 2FA и уведомлений
    'tavily_api_key'     => getenv('TAVILY_API_KEY') ?: '', // Tavily API для поискового радара трендов
    'pagespeed_api_key'  => getenv('PAGESPEED_API_KEY') ?: ''  // Google PageSpeed Insights REST API v5
];

$localConfigFile = __DIR__ . '/config.local.php';
if (file_exists($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

return $config;
