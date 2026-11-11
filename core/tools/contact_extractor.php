<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - LPR & CONTACT EXTRACTOR TOOL
 * Discovers and extracts Decision Maker (LPR) details:
 *  - Name / Title (CEO, Founder, Director, Sole Proprietor)
 *  - Direct communication (Personal Telegram, WhatsApp, Mobile phone +7 9...)
 *  - Social profiles (VK, Telegram, Instagram)
 *  - Strict Junk Filtering: eliminates toll-free 8-800/8-804 and generic info@ emails
 * =============================================================================
 */

if (!function_exists('extractLPRContacts')) {
    /**
     * Extract LPR contacts and clean social links from website
     *
     * @param string $websiteUrl Target website URL
     * @param array<string, mixed> $config Optional configuration
     * @return array
     */
    function extractLPRContacts(string $websiteUrl, array $config = []): array
    {
        $websiteUrl = trim($websiteUrl);
        if ($websiteUrl === '') {
            return [
                'status'  => 'error',
                'message' => 'URL сайта не может быть пустым'
            ];
        }

        if (!preg_match('#^https?://#i', $websiteUrl)) {
            $websiteUrl = 'https://' . $websiteUrl;
        }

        $parsedUrl = parse_url($websiteUrl);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';
        $baseUrl = $scheme . '://' . $host;

        $pagesToCheck = [$websiteUrl];
        $pagesChecked = [];
        $aggregatedHtml = '';

        // Хелпер загрузки страницы через cURL
        $fetchUrl = function(string $url): string {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 AntiDesignerBot/4.0'
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            return is_string($res) ? $res : '';
        };

        // 1. Загрузка главной страницы
        $mainHtml = $fetchUrl($websiteUrl);
        $aggregatedHtml .= ' ' . $mainHtml;
        $pagesChecked[] = $websiteUrl;

        // 2. Поиск внутренних страниц «О нас», «Контакты», «Реквизиты»
        if ($mainHtml !== '') {
            $patterns = [
                '#href=[\'"]([^\'"]*(?:contact|kontak|o-nas|about|kompan|team|rekvizit|requisite|legal)[^\'"]*)[\'"]#i'
            ];

            $discoveredUrls = [];
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $mainHtml, $matches)) {
                    foreach ($matches[1] as $href) {
                        $href = trim($href);
                        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                            continue;
                        }

                        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                            if (parse_url($href, PHP_URL_HOST) === $host) {
                                $discoveredUrls[] = $href;
                            }
                        } elseif (str_starts_with($href, '/')) {
                            $discoveredUrls[] = $baseUrl . $href;
                        } else {
                            $discoveredUrls[] = $baseUrl . '/' . $href;
                        }
                    }
                }
            }

            $discoveredUrls = array_values(array_unique($discoveredUrls));
            // Берем до 2 страниц для глубокого анализа без превышения лимитов
            $subPagesToFetch = array_slice($discoveredUrls, 0, 2);
            foreach ($subPagesToFetch as $subUrl) {
                $subHtml = $fetchUrl($subUrl);
                if ($subHtml !== '') {
                    $aggregatedHtml .= ' ' . $subHtml;
                    $pagesChecked[] = $subUrl;
                }
            }
        }

        // =====================================================================
        // ИЗВЛЕЧЕНИЕ ДАННЫХ И СТРОГАЯ ФИЛЬТРАЦИЯ МУСОРА
        // =====================================================================
        $lprName = '';
        $lprTitle = 'Руководитель';
        $personalTg = '';
        $personalWa = '';
        $mobilePhone = '';
        $socialLinks = [];
        $personalEmail = '';
        $tollFreeNumbers = [];
        $genericEmails = [];

        // 1. Извлечение имени ЛПР
        $namePatterns = [
            '#(?:генеральный\s+директор|директор|основатель|руководитель|фаундер|учредитель|главврач|управляющий)\s*[:–—\-]?\s*([А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+(?:\s+[А-ЯЁ][а-яё]+)?)#iu' => 'Руководитель',
            '#(?:ИП|Индивидуальный предприниматель)\s+([А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+(?:\s+[А-ЯЁ][а-яё]+)?)#u' => 'Индивидуальный предприниматель',
            '#(?:ИП|Индивидуальный предприниматель)\s+([А-ЯЁ][а-яё]+\s+[А-ЯЁ]\.\s*[А-ЯЁ]\.)#u' => 'Индивидуальный предприниматель'
        ];

        foreach ($namePatterns as $regex => $title) {
            if (preg_match($regex, $aggregatedHtml, $m)) {
                $candidate = trim(strip_tags($m[1]));
                // Исключаем стоп-слова
                $stopWords = ['компания', 'центр', 'клиника', 'группа', 'москва', 'россия', 'город', 'услуги', 'сайт', 'сервис'];
                $isStop = false;
                foreach ($stopWords as $sw) {
                    if (mb_stripos($candidate, $sw) !== false) {
                        $isStop = true;
                        break;
                    }
                }
                if (!$isStop && mb_strlen($candidate) >= 4) {
                    $lprName = $candidate;
                    $lprTitle = $title;
                    break;
                }
            }
        }

        // 2. Поиск ссылок на мессенджеры Telegram и WhatsApp
        if (preg_match_all('#https?://t\.me/([a-zA-Z0-9_]{4,32})#i', $aggregatedHtml, $mTr)) {
            $ignoreTg = ['share', 'joinchat', 'addstickers', 'telegram', 'bot'];
            foreach ($mTr[1] as $idx => $tgUser) {
                $tgUserLower = strtolower($tgUser);
                if (!in_array($tgUserLower, $ignoreTg, true) && !str_ends_with($tgUserLower, 'bot')) {
                    $personalTg = 'https://t.me/' . $tgUser;
                    $socialLinks[] = $personalTg;
                    break;
                }
            }
        }

        if (preg_match('#https?://(?:wa\.me|api\.whatsapp\.com/send\?phone=)/?([0-9]{10,12})#i', $aggregatedHtml, $mWa)) {
            $personalWa = 'https://wa.me/' . $mWa[1];
            $socialLinks[] = $personalWa;
        }

        // 3. Поиск соцсетей (VK, Telegram, Instagram)
        if (preg_match_all('#https?://(?:vk\.com|instagram\.com)/[a-zA-Z0-9_.\-]+#i', $aggregatedHtml, $mSoc)) {
            foreach ($mSoc[0] as $socUrl) {
                if (!in_array($socUrl, $socialLinks, true) && !str_contains($socUrl, 'share') && !str_contains($socUrl, 'intent')) {
                    $socialLinks[] = $socUrl;
                }
            }
        }

        // 4. Поиск номеров телефонов с фильтрацией 8-800 / 8-804
        $phoneRegex = '#(?:\+7|8)[\s\-\(]*(\d{3})[\s\-\)]*(\d{3})[\s\-]*(\d{2})[\s\-]*(\d{2})#';
        if (preg_match_all($phoneRegex, $aggregatedHtml, $mPhones, PREG_SET_ORDER)) {
            foreach ($mPhones as $ph) {
                $code = $ph[1];
                $fullDigits = '7' . $ph[1] . $ph[2] . $ph[3] . $ph[4];
                $formatted = "+7 ({$ph[1]}) {$ph[2]}-{$ph[3]}-{$ph[4]}";

                // СТРОГИЙ ФИЛЬТР: отсекаем 8-800 и 8-804 (горячие линии колл-центров)
                if (in_array($code, ['800', '804'], true)) {
                    $tollFreeNumbers[] = $formatted;
                    continue;
                }

                // Приоритет мобильным номерам (код 9xx)
                if (str_starts_with($code, '9') && empty($mobilePhone)) {
                    $mobilePhone = $formatted;
                }
            }
        }

        // 5. Поиск email с фильтрацией общих ящиков (info@, support@)
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $aggregatedHtml, $mEmails)) {
            $genericPrefixes = ['info', 'support', 'mail', 'admin', 'help', 'contact', 'sales', 'office', 'hello', 'zakaz', 'order'];
            foreach (array_unique($mEmails[0]) as $em) {
                $emLower = strtolower($em);
                $isGeneric = false;
                foreach ($genericPrefixes as $prefix) {
                    if (str_starts_with($emLower, $prefix . '@')) {
                        $isGeneric = true;
                        $genericEmails[] = $em;
                        break;
                    }
                }
                if (!$isGeneric && empty($personalEmail)) {
                    $personalEmail = $em;
                }
            }
        }

        // Определяем лучший контакт для ЛПР
        $bestLprContact = $personalTg ?: ($personalWa ?: ($mobilePhone ?: ($personalEmail ?: '')));

        // Чистка типографики
        $lprName = str_replace(['—', '–'], '-', $lprName ?: 'Руководитель проекта');
        $lprTitle = str_replace(['—', '–'], '-', $lprTitle);

        return [
            'status'          => 'success',
            'website_url'     => $websiteUrl,
            'lpr_name'        => $lprName,
            'lpr_title'       => $lprTitle,
            'lpr_contact'     => $bestLprContact,
            'personal_tg'     => $personalTg,
            'personal_wa'     => $personalWa,
            'mobile_phone'    => $mobilePhone,
            'personal_email'  => $personalEmail,
            'social_links'    => array_values(array_unique($socialLinks)),
            'filtered_out'    => [
                'toll_free_numbers' => array_values(array_unique($tollFreeNumbers)),
                'generic_emails'    => array_values(array_unique($genericEmails))
            ],
            'pages_checked'   => $pagesChecked
        ];
    }
}
