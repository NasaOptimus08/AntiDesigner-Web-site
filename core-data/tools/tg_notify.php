<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - TELEGRAM DISPATCHER & NOTIFICATION HELPER
 * Lightweight dispatcher for admin personal notifications and channel publishing.
 * Auto-fallback: sends raw plain text if Markdown/HTML parsing fails.
 * Chunking: splits texts longer than 4000 characters.
 * =============================================================================
 */

if (!function_exists('notifyAdminTG')) {
    /**
     * Send direct message to admin Telegram account
     *
     * @param string $text Message text (HTML or plain text)
     * @param array<string, mixed> $config Optional configuration
     * @return bool True if delivered, false otherwise
     */
    function notifyAdminTG(string $text, array $config = []): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        if (empty($config)) {
            $configFile = dirname(__DIR__) . '/config.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
            }
        }

        $botToken = trim((string)($config['telegram_bot_token'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: ''));
        $adminId  = trim((string)($config['admin_telegram_id'] ?? getenv('ADMIN_TELEGRAM_ID') ?: ''));

        if ($botToken === '' || $adminId === '') {
            return false;
        }

        $chunks = splitTelegramText($text, 4000);
        $allDelivered = true;

        foreach ($chunks as $chunk) {
            $res = sendTelegramMessageRaw($botToken, $adminId, $chunk);
            if (!($res['ok'] ?? false)) {
                $allDelivered = false;
            }
        }

        return $allDelivered;
    }
}

if (!function_exists('sendTelegramChannelPost')) {
    /**
     * Send post to Telegram Channel with chunking and format error fallback
     *
     * @param string $text Post content
     * @param string $channelId Channel username (e.g. '@AntidesignerAI') or numeric ID
     * @param array<string, mixed> $config Optional config
     * @return array Result array with status, message_id, published_url, errors
     */
    function sendTelegramChannelPost(string $text, string $channelId = '', array $config = []): array
    {
        $text = trim($text);
        if ($text === '') {
            return [
                'status'  => 'error',
                'message' => 'Текст публикации пуст'
            ];
        }

        if (empty($config)) {
            $configFile = dirname(__DIR__) . '/config.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
            }
        }

        $botToken = trim((string)($config['telegram_bot_token'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: ''));
        $targetChannel = trim($channelId) ?: trim((string)($config['telegram_channel_id'] ?? '@AntidesignerAI'));

        if ($targetChannel === '') {
            $targetChannel = '@AntidesignerAI';
        }

        // Если это публичный юзернейм канала и нет @ в начале - добавляем
        if (!str_starts_with($targetChannel, '@') && !str_starts_with($targetChannel, '-100') && !is_numeric($targetChannel)) {
            $targetChannel = '@' . $targetChannel;
        }

        if ($botToken === '') {
            return [
                'status'  => 'error',
                'message' => 'telegram_bot_token не настроен в config.local.php'
            ];
        }

        $chunks = splitTelegramText($text, 4000);
        $firstMessageId = 0;
        $sentCount = 0;
        $errors = [];

        foreach ($chunks as $idx => $chunk) {
            $res = sendTelegramMessageRaw($botToken, $targetChannel, $chunk);
            if ($res['ok'] ?? false) {
                $sentCount++;
                if ($firstMessageId === 0 && isset($res['result']['message_id'])) {
                    $firstMessageId = (int)$res['result']['message_id'];
                }
            } else {
                $errors[] = $res['description'] ?? 'Неизвестная ошибка Telegram API';
            }
        }

        $cleanChannel = ltrim($targetChannel, '@');
        $publishedUrl = $firstMessageId > 0 ? "https://t.me/{$cleanChannel}/{$firstMessageId}" : "https://t.me/{$cleanChannel}";

        if ($sentCount > 0) {
            return [
                'status'        => 'success',
                'message'       => 'Пост успешно опубликован в канале Telegram',
                'channel'       => $targetChannel,
                'message_id'    => $firstMessageId,
                'published_url' => $publishedUrl,
                'parts_sent'    => $sentCount,
                'errors'        => $errors
            ];
        }

        return [
            'status'  => 'error',
            'message' => 'Не удалось опубликовать пост в Telegram: ' . implode('; ', $errors),
            'channel' => $targetChannel,
            'errors'  => $errors
        ];
    }
}

if (!function_exists('sendTelegramMessageRaw')) {
    /**
     * Low-level helper: send message with parse_mode=HTML, fallback to plain text on parse error
     *
     * @param string $botToken
     * @param string $chatId
     * @param string $text
     * @return array
     */
    function sendTelegramMessageRaw(string $botToken, string $chatId, string $text): array
    {
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

        // 1. Попытка отправки с HTML разметкой
        $postData = [
            'chat_id'                  => $chatId,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => false
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$raw, true);

        if ($httpCode === 200 && ($decoded['ok'] ?? false)) {
            return $decoded;
        }

        // 2. Если ошибка парсинга разметки (can't parse entities / entity bounds)
        // делаем надежный фоллбэк: отправка чистым текстом без parse_mode
        $desc = (string)($decoded['description'] ?? '');
        $isParseError = (
            str_contains(strtolower($desc), "can't parse entities") ||
            str_contains(strtolower($desc), 'entity') ||
            str_contains(strtolower($desc), 'parse') ||
            str_contains(strtolower($desc), 'tag')
        );

        if ($isParseError || $httpCode !== 200) {
            $plainData = [
                'chat_id'                  => $chatId,
                'text'                     => strip_tags($text),
                'disable_web_page_preview' => false
            ];

            $ch2 = curl_init($apiUrl);
            curl_setopt_array($ch2, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($plainData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 12,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $raw2 = curl_exec($ch2);
            $httpCode2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            $decoded2 = json_decode((string)$raw2, true);
            if ($httpCode2 === 200 && ($decoded2['ok'] ?? false)) {
                return $decoded2;
            }

            return is_array($decoded2) ? $decoded2 : ['ok' => false, 'description' => "HTTP {$httpCode2}: {$raw2}"];
        }

        return is_array($decoded) ? $decoded : ['ok' => false, 'description' => "HTTP {$httpCode}: {$raw}"];
    }
}

if (!function_exists('splitTelegramText')) {
    /**
     * Split long text into parts smaller than max length, breaking by paragraphs
     *
     * @param string $text
     * @param int $maxLength
     * @return array<string>
     */
    function splitTelegramText(string $text, int $maxLength = 4000): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        $paragraphs = explode("\n\n", $text);
        $current = '';

        foreach ($paragraphs as $para) {
            $candidate = ($current === '') ? $para : $current . "\n\n" . $para;
            if (mb_strlen($candidate, 'UTF-8') <= $maxLength) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $chunks[] = trim($current);
                    $current = '';
                }

                // Если один абзац длиннее max length, режем по строкам
                if (mb_strlen($para, 'UTF-8') > $maxLength) {
                    $lines = explode("\n", $para);
                    foreach ($lines as $line) {
                        $candidateLine = ($current === '') ? $line : $current . "\n" . $line;
                        if (mb_strlen($candidateLine, 'UTF-8') <= $maxLength) {
                            $current = $candidateLine;
                        } else {
                            if ($current !== '') {
                                $chunks[] = trim($current);
                            }
                            // Если строка длиннее maxLength, режем по символам
                            if (mb_strlen($line, 'UTF-8') > $maxLength) {
                                $chunks[] = mb_substr($line, 0, $maxLength, 'UTF-8');
                                $current = mb_substr($line, $maxLength, null, 'UTF-8');
                            } else {
                                $current = $line;
                            }
                        }
                    }
                } else {
                    $current = $para;
                }
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}

