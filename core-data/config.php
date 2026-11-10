<?php
declare(strict_types=1);

/**
 * AntiDesigner Core — Базовая конфигурация и ключи доступа к внешним сервисам
 */
return [
    'gemini_api_key'     => '', // Google AI Studio (Gemini 1.5 Flash / Pro)
    'telegram_bot_token' => '', // Telegram Bot API Token
    'admin_telegram_id'  => '', // ID администратора в Telegram для 2FA и уведомлений
    'tavily_api_key'     => '', // Tavily API для поискового радара трендов
    'pagespeed_api_key'  => ''  // Google PageSpeed Insights REST API v5
];
