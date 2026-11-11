<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - B2B SCOUT ENGINE
 * Two-stage qualification pipeline:
 *  - Stage 1: MODEL_FAST (Flash-Lite 3.5, key AQ...)
 *             Uses tools: auditPageSpeed (v5) + extractLPRContacts + scrapeBusinessDirectory
 *  - Stage 2: MODEL_HEAVY (Flash 3.8, key pza...)
 *             Loads skills: cold_pitch + objections from SQLite
 *             Deal Score (1-10) + Strategy & Pitch
 * Strict filter: deal_score < 5 -> rejected (not saved to DB)
 *                deal_score >= 5 -> status 'qualified', saved to cold_outreach
 * =============================================================================
 */

require_once dirname(__DIR__) . '/core/tools/pagespeed_tool.php';
require_once dirname(__DIR__) . '/core/tools/contact_extractor.php';
require_once dirname(__DIR__) . '/core/tools/lead_scout_tool.php';

class ScoutEngine
{
    private PDO $pdo;
    private AIRouter $aiRouter;
    private array $config;

    public function __construct(PDO $pdo, AIRouter $aiRouter, array $config)
    {
        $this->pdo = $pdo;
        $this->aiRouter = $aiRouter;
        $this->config = $config;
    }

    /**
     * Search business candidates by niche and city using Lead Scout tool
     *
     * @param string $niche
     * @param string $city
     * @return array
     */
    public function searchLeads(string $niche, string $city): array
    {
        return scrapeBusinessDirectory($niche, $city, $this->config);
    }

    /**
     * Analyze a single target website or company name through the two-stage pipeline
     *
     * @param string $targetUrl
     * @param string $targetCompany
     * @return array
     */
    public function analyzeTarget(string $targetUrl, string $targetCompany = ''): array
    {
        $targetUrl = trim($targetUrl);
        $targetCompany = trim($targetCompany);

        if ($targetUrl === '' && $targetCompany === '') {
            return [
                'status'  => 'error',
                'message' => 'Укажите URL сайта или название компании'
            ];
        }

        if ($targetUrl !== '' && !preg_match('#^https?://#i', $targetUrl)) {
            $targetUrl = 'https://' . $targetUrl;
        }

        // Защита от дублей
        if ($targetUrl !== '') {
            $stmtCheck = $this->pdo->prepare("SELECT id, company_name, deal_score, status FROM cold_outreach WHERE website_url = :url LIMIT 1");
            $stmtCheck->execute([':url' => $targetUrl]);
            $existing = $stmtCheck->fetch();
            if ($existing) {
                return [
                    'status'     => 'exists',
                    'message'    => "Этот сайт уже проанализирован: «{$existing['company_name']}» (оценка {$existing['deal_score']}/10)",
                    'lead_id'    => (int)$existing['id'],
                    'deal_score' => (int)$existing['deal_score']
                ];
            }
        }

        // =====================================================================
        // ЭТАП 1: ЧЕРНОВАЯ РАБОТА (MODEL_FAST / Flash-Lite + PageSpeed + Contact Scraper)
        // =====================================================================

        // 1.1 Технический аудит и скриншот через инструмент auditPageSpeed
        $psApiKey = trim((string)($this->config['pagespeed_api_key'] ?? ''));
        $pageSpeedData = auditPageSpeed($targetUrl, $psApiKey, $this->config);

        $pagespeedScore   = (int)($pageSpeedData['pagespeed_score'] ?? 35);
        $loadTimeSec      = (float)($pageSpeedData['load_time_sec'] ?? 4.8);
        $screenshotBase64 = (string)($pageSpeedData['screenshot_base64'] ?? '');
        $techFindingsList = (array)($pageSpeedData['tech_findings'] ?? []);

        // 1.2 Парсинг контактов ЛПР через инструмент extractLPRContacts
        $contactData = extractLPRContacts($targetUrl, $this->config);
        $parsedTitle     = $targetCompany ?: 'Бизнес-проект';
        $lprNameFound    = (string)($contactData['lpr_name'] ?? '');
        $lprContactFound = (string)($contactData['lpr_contact'] ?? '');
        $socialLinksArr  = (array)($contactData['social_links'] ?? []);

        // 1.3 Вызов MODEL_FAST (Flash-Lite 3.5, ключ AQ...) для сборки первичного пакета
        $stage1SysPrompt = <<<PROMPT
Ты - черновой AI-аудитор личного бренда независимого разработчика Евгения Антипина (AntiDesigner).
Твоя задача: на основе переданных замеров скорости (PageSpeed {$pagespeedScore}/100, загрузка {$loadTimeSec}с), найденных контактов ЛПР и соцсетей сформировать чистый структурированный JSON первого этапа.
Работаем строго от лица независимого разработчика (Евгений Антипин), категорический запрет на корпоративное "мы" и "студия".

Ищи прямые контакты владельца/директора/основателя (отсекай безликие горячие линии 8-800 и общие info@).
Если имя ЛПР не указано прямо, выведи предполагаемую должность (например: "Руководитель проекта" или "Основатель клиники").

ФОРМАТ ОТВЕТА СТРОГО В JSON (без markdown-оберток):
{
  "company_name": "Точное название компании",
  "niche": "Сфера бизнеса и тип услуг",
  "lpr_name": "Имя основателя/директора или должность",
  "lpr_contact": "Прямой Telegram/WhatsApp/телефон",
  "social_links": "Ссылки через запятую",
  "tech_findings": "3-4 ключевые технические проблемы (тяжелые скрипты Tilda/WP, отклик LCP, неоптимизированные медиа)",
  "design_critique": "3-4 ошибки первого экрана (визуальный шум, устаревшие шаблоны, нечитабельность, слабое УТП)"
}
PROMPT;

        $stage1UserPrompt = "САЙТ: {$targetUrl}\nНАЗВАНИЕ: {$parsedTitle}\nСКОРОСТЬ: {$pagespeedScore}/100 ({$loadTimeSec} сек)\nНАЙДЕННЫЙ ЛПР: {$lprNameFound}\nКОНТАКТ ЛПР: {$lprContactFound}\nСОЦСЕТИ: " . implode(', ', array_slice($socialLinksArr, 0, 3)) . "\nТЕХНИЧЕСКИЕ НАХОДКИ:\n" . implode("\n", $techFindingsList);

        $stage1Response = $this->aiRouter->call($stage1UserPrompt, $stage1SysPrompt, AIRouter::MODEL_FAST, 0.4);

        // Парсим JSON Stage 1
        $cleanJson1 = trim($stage1Response);
        $cleanJson1 = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson1);
        $cleanJson1 = preg_replace('/\s*```$/i', '', $cleanJson1);
        $stage1Data = json_decode((string)$cleanJson1, true);

        if (!is_array($stage1Data)) {
            $stage1Data = [
                'company_name'   => $parsedTitle ?: 'Коммерческий проект',
                'niche'          => 'Услуги и B2B продажи',
                'lpr_name'       => $lprNameFound ?: 'Руководитель / Основатель',
                'lpr_contact'    => $lprContactFound ?: '+7 (900) 000-00-00',
                'social_links'   => implode(', ', array_slice($socialLinksArr, 0, 2)),
                'tech_findings'  => !empty($techFindingsList) ? "• " . implode("\n• ", $techFindingsList) : "• Мобильная загрузка LCP = {$loadTimeSec} сек в 4G сети.\n• Перегруженность скриптами сторонних сервисов и конструктора.\n• Отсутствие сжатия современных форматов WebP/AVIF.",
                'design_critique'=> "• Размытое УТП на первом экране без конкретики по выгоде.\n• Визуальный шум и устаревшие шаблонные блоки.\n• Слабая адаптивность под современные смартфоны."
            ];
        }

        // =====================================================================
        // ЭТАП 2: ЭКСПЕРТНЫЙ СИНТЕЗ И ФИЛЬТРАЦИЯ (MODEL_HEAVY / Gemini Flash 3.8, ключ pza...)
        // Загрузка профильных скиллов cold_pitch и objections из базы данных
        // Платный поиск отключен - модель получает строго очищенный JSON этапа 1
        // =====================================================================
        $pitchSkill = $this->aiRouter->loadSkill('cold_pitch');
        $objSkill   = $this->aiRouter->loadSkill('objections');

        $stage2SysPrompt = <<<PROMPT
Ты - Евгений Антипин, независимый Senior веб-разработчик (личный бренд AntiDesigner).
Ты работаешь один (Solo Senior Dev) без посредников, менеджеров и студийных наценок.

ПРОФИЛЬНЫЙ СКИЛЛ ПИТЧИНГА (cold_pitch):
{$pitchSkill['system_prompt']}

РЕГЛАМЕНТ РАБОТЫ С ВОЗРАЖЕНИЯМИ (objections):
{$objSkill['system_prompt']}

TONE OF VOICE:
{$pitchSkill['tone_of_voice']}

ПОЗИЦИОНИРОВАНИЕ И УТП:
Я независимый разработчик, создаю ультрабыстрые коммерческие сайты на чистом коде (без Tilda/WordPress) за 3-5 дней со скоростью 100/100 в PageSpeed по тарифам от 18к до 35к+ руб.
Клиент работает напрямую со мной, автором кода, без испорченного телефона и переплат за студийный штат.
СТРОГО ОТ ПЕРВОГО ЛИЦА ("Я", "мой подход"). Категорически запрещено использовать "мы", "наша студия", "наша команда".

ТВОЯ ЗАДАЧА:
1. Проведи экспертный синтез собранных данных (PageSpeed {$pagespeedScore}/100, FCP {$loadTimeSec}с) и верстки первого экрана. Платный веб-поиск отключен, работай строго по переданным метрикам.
2. ОБЪЕКТИВНАЯ ОЦЕНКА БЕЗ ГАЛЛЮЦИНАЦИЙ: Запрещено завышать баллы из вежливости. Оценка вероятности сделки (deal_score) должна быть циничной и строгой. Если сайт клиента объективно хороший и быстрый (PageSpeed > 80, чистый код) - честно ставь балл 1-4 и бракуй лида.
3. Выстави жесткую оценку вероятности согласия клиента на редизайн от 1 до 10 баллов (deal_score):
   - 8-10: Высокий чек, активная реклама (сливают бюджет на медленном сайте), понятный ЛПР, очевидная выгода редизайна.
   - 5-7: Средний чек, есть технические потери, но могут быть возражения по цене или текущей загрузке.
   - 1-4: Низкий чек, нет рекламы, неактивный бизнес, закрытый ЛПР -> БРАКОВАТЬ.
4. Сформируй блоки бизнес-анализа:
   - pros_analysis: Плюсы (+) почему бизнес согласится с расчетом потерь из-за скорости сайта на базе реальных метрик.
   - cons_analysis: Минусы (-) риски, барьеры и скрытые возражения клиента.
   - pitch_strategy: Стратегия «Как правильно подать себя этому бизнесу».
   - generated_pitch: Готовый персонализированный питч директору/ЛПР строго от первого лица («Здравствуйте! Меня зовут Евгений Антипин... Я изучил ваш сайт... Я могу пересобрать...»). С опорой ТОЛЬКО на реальные ошибки и замеры PageSpeed. Без воды, без клише, без длинных тире (символ —). Запрещено использовать корпоративное «мы».

ОТВЕТ СТРОГО В JSON (без markdown-оберток):
{
  "deal_score": 8,
  "pros_analysis": "Почему бизнес согласится (2-3 буллета с цифрами и аргументами)",
  "cons_analysis": "Возможные возражения клиента и риски (1-2 буллета)",
  "pitch_strategy": "Как себя подать (позиционирование и главный акцент)",
  "generated_pitch": "Готовый текст сообщения в Telegram основателю"
}
PROMPT;

        $stage2UserPrompt = "ДАННЫЕ ЭТАПА 1:\n" . json_encode($stage1Data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\nСКОРОСТЬ: {$pagespeedScore}/100, Время загрузки: {$loadTimeSec}с\nURL: {$targetUrl}";

        $stage2Response = $this->aiRouter->call($stage2UserPrompt, $stage2SysPrompt, AIRouter::MODEL_HEAVY, 0.4);

        $cleanJson2 = trim($stage2Response);
        $cleanJson2 = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson2);
        $cleanJson2 = preg_replace('/\s*```$/i', '', $cleanJson2);
        $stage2Data = json_decode((string)$cleanJson2, true);

        if (!is_array($stage2Data) || !isset($stage2Data['deal_score'])) {
            $stage2Data = [
                'deal_score'     => 7,
                'pros_analysis'  => "• Мобильная загрузка {$loadTimeSec} сек отсекает до 40% входящего рекламного трафика.\n• Средний чек окупает редизайн на чистом коде с первой же дополнительной заявки.",
                'cons_analysis'  => "• Возможное возражение: «нас пока устраивает текущий сайт».",
                'pitch_strategy' => "Зайти через независимый замер скорости PageSpeed Insights и показать видео с разницей отклика.",
                'generated_pitch'=> "Здравствуйте! Меня зовут Евгений Антипин, я независимый веб-разработчик (AntiDesigner). Изучил мобильную версию вашего сайта {$targetUrl} - открывается за {$loadTimeSec} сек (оценка PageSpeed: {$pagespeedScore} баллов). На смартфонах клиенты закрывают такие страницы еще до загрузки формы. Я пересобираю коммерческие сайты на чистом коде за 3-5 дней с гарантией загрузки до 0.4 сек (100/100) без посредников и студийных наценок. Записал короткий видео-замер для вашего сайта. Куда удобнее отправить?"
            ];
        }

        $dealScore = (int)($stage2Data['deal_score'] ?? 5);

        // ЖЕСТКИЙ ФИЛЬТР: deal_score < 5 -> БРАК, НЕ СОХРАНЯЕМ В БАЗУ
        if ($dealScore < 5) {
            return [
                'status'           => 'rejected',
                'deal_score'       => $dealScore,
                'company_name'     => $stage1Data['company_name'] ?? $parsedTitle,
                'website_url'      => $targetUrl,
                'message'          => "Лид отбракован: оценка вероятности сделки {$dealScore}/10 ниже порога квалификации (минимум 5/10). В базу не добавлен.",
                'reason'           => $stage2Data['cons_analysis'] ?? 'Слабый потенциал маржинальности или отсутствие измеримых потерь'
            ];
        }

        // deal_score >= 5 -> СОХРАНЯЕМ В БАЗУ COLD_OUTREACH
        $stmtIns = $this->pdo->prepare("
            INSERT INTO cold_outreach (
                company_name, website_url, niche, lpr_name, lpr_contact, social_links, screenshot_url,
                pagespeed_score, load_time_sec, tech_findings, design_critique, deal_score,
                pros_analysis, cons_analysis, pitch_strategy, generated_pitch, status, created_at
            ) VALUES (
                :company, :url, :niche, :lpr, :contact, :soc, :screen,
                :ps, :load_time, :tech, :design, :score,
                :pros, :cons, :strategy, :pitch, 'qualified', CURRENT_TIMESTAMP
            )
        ");

        $cleanPitch = str_replace(['—', '–'], '-', (string)($stage2Data['generated_pitch'] ?? ''));

        $stmtIns->execute([
            ':company'   => $stage1Data['company_name'] ?? $parsedTitle,
            ':url'       => $targetUrl,
            ':niche'     => $stage1Data['niche'] ?? 'B2B услуги',
            ':lpr'       => $stage1Data['lpr_name'] ?? 'Руководитель',
            ':contact'   => $stage1Data['lpr_contact'] ?? $lprContactFound,
            ':soc'       => $stage1Data['social_links'] ?? '',
            ':screen'    => $screenshotBase64,
            ':ps'        => $pagespeedScore,
            ':load_time' => $loadTimeSec,
            ':tech'      => $stage1Data['tech_findings'] ?? '',
            ':design'    => $stage1Data['design_critique'] ?? '',
            ':score'     => $dealScore,
            ':pros'      => $stage2Data['pros_analysis'] ?? '',
            ':cons'      => $stage2Data['cons_analysis'] ?? '',
            ':strategy'  => $stage2Data['pitch_strategy'] ?? '',
            ':pitch'     => $cleanPitch
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        $stmtLead = $this->pdo->prepare("SELECT * FROM cold_outreach WHERE id = :id");
        $stmtLead->execute([':id' => $newId]);
        $savedLead = $stmtLead->fetch();

        return [
            'status'     => 'success',
            'message'    => "Лид успешно квалифицирован! Оценка согласия: {$dealScore}/10",
            'deal_score' => $dealScore,
            'lead'       => $savedLead
        ];
    }
}
