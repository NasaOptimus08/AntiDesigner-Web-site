-- =============================================================================
-- ANTIDESIGNER CORE - DDL SCHEMA (SQLite 3)
-- Sprint 1: Unified Architecture for Marketing, CRM & AI Agent Engine
-- =============================================================================

-- 1. Административная авторизация и сессии (Telegram 2FA)
CREATE TABLE IF NOT EXISTS admin_auth (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_token TEXT UNIQUE,
    telegram_auth_code TEXT,
    code_expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Скиллы (Промпт-инжиниринг и системные роли LLM по 5 категориям)
CREATE TABLE IF NOT EXISTS skills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    category TEXT DEFAULT 'articles',
    system_prompt TEXT NOT NULL,
    tone_of_voice TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. База знаний (Контекст для агента: ЦА, тарифы, УТП, кейсы)
CREATE TABLE IF NOT EXISTS knowledge_base (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category TEXT NOT NULL,
    title TEXT NOT NULL,
    content_markdown TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 4. Радар трендов (Парсинг IT/Web-новостей через Tavily / Perplexity)
CREATE TABLE IF NOT EXISTS trends (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_url TEXT,
    title TEXT NOT NULL,
    summary TEXT NOT NULL,
    is_approved INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 5. Контент-фабрика и Контент-план (Омничеканальные материалы)
CREATE TABLE IF NOT EXISTS content_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_id INTEGER DEFAULT NULL,
    topic TEXT NOT NULL,
    status TEXT DEFAULT 'planned',
    scheduled_for DATE,
    format TEXT NOT NULL,
    content_markdown TEXT,
    storyboard_json TEXT DEFAULT NULL,
    media_files TEXT DEFAULT NULL,
    chat_history TEXT DEFAULT NULL,
    audio_url TEXT DEFAULT NULL,
    motion_html TEXT DEFAULT NULL,
    views_count INTEGER DEFAULT 0,
    likes_count INTEGER DEFAULT 0,
    published_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 5.1. Модульные под-промпты для скиллов (шаги цепочки, стили анимации, правила)
CREATE TABLE IF NOT EXISTS skill_prompts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    skill_code TEXT NOT NULL,
    title TEXT NOT NULL,
    prompt_type TEXT DEFAULT 'step',
    step_order INTEGER DEFAULT 1,
    content_markdown TEXT NOT NULL,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_skill_prompts_code ON skill_prompts(skill_code);
CREATE INDEX IF NOT EXISTS idx_skill_prompts_active ON skill_prompts(is_active);

-- 6. CRM и Воронка Лидов (Атрибуция, статусы, тарифы, контакты)
CREATE TABLE IF NOT EXISTS leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    telegram_user_id TEXT,
    telegram_username TEXT,
    full_name TEXT,
    utm_source TEXT,
    status TEXT DEFAULT 'new',
    budget_range TEXT,
    lead_magnet_claimed TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 7. Холодный аутрич и B2B-скаут лидов (PageSpeed + Vision-аналитика + Flash 2.0 Deal Score)
CREATE TABLE IF NOT EXISTS cold_outreach (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT NOT NULL,
    website_url TEXT NOT NULL,
    niche TEXT,                      -- Ниша/сфера бизнеса
    lpr_name TEXT,                   -- Имя директора/владельца (ЛПР)
    lpr_contact TEXT,                -- Прямой контакт ЛПР (TG, WhatsApp, личный телефон)
    social_links TEXT,               -- Ссылки на соцсети (VK, TG, Inst)
    screenshot_url TEXT,             -- Скриншот первого экрана (base64)
    pagespeed_score INTEGER,         -- Скорость сайта (0-100)
    load_time_sec REAL,              -- Время загрузки в секундах
    tech_findings TEXT,              -- Технические ошибки сайта
    design_critique TEXT,            -- Разбор визуала и ошибок 1 экрана
    deal_score INTEGER NOT NULL,     -- Оценка вероятности сделки от 1 до 10
    pros_analysis TEXT,              -- Плюсы (+) почему лид согласится
    cons_analysis TEXT,              -- Минусы (-) риски и барьеры
    pitch_strategy TEXT,             -- Как правильно подать себя этому клиенту
    generated_pitch TEXT,            -- Готовый персонализированный текст сообщения
    chat_history TEXT,               -- История переписки с лидом для анализа
    deal_outcome TEXT,               -- 'won' (сделка), 'lost' (отказ), 'in_progress'
    status TEXT DEFAULT 'qualified', -- 'qualified', 'pitched', 'responded', 'won', 'lost'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 8. Контур памяти и самообучения (Feedback Loop / Agent Memory)
CREATE TABLE IF NOT EXISTS agent_memory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hypothesis TEXT NOT NULL,
    supporting_metric TEXT,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- СПРИНТ 3: Воронка конверсий, CRM, Аналитика, Контент-план SMM и ТГ-Бот
-- =============================================================================

-- 9. Посещения сайта и глубина скролла
CREATE TABLE IF NOT EXISTS site_visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_hash TEXT,
    device_type TEXT,          -- 'mobile', 'desktop'
    max_scroll_depth INTEGER,  -- 25, 50, 75, 100
    referrer TEXT,
    visited_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 10. Клики по целевым кнопкам сайта
CREATE TABLE IF NOT EXISTS site_button_clicks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    button_name TEXT NOT NULL, -- 'cta_order', 'btn_tariffs', 'tg_contact'
    device_type TEXT,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 11. Расширенный контент-план (структура таблицы SMM Memory Stones)
CREATE TABLE IF NOT EXISTS content_plan (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    day_label TEXT,            -- '1 день', '2 день' или календарная дата
    format TEXT NOT NULL,      -- 'article', 'post_tg', 'reels', 'carousel', 'lead_magnet', 'poll'
    rubric TEXT NOT NULL,      -- Название рубрики из Рубрикатора
    title TEXT NOT NULL,       -- Название темы
    comment TEXT,              -- Пояснение / ТЗ
    status TEXT DEFAULT 'idea',-- 'idea' (Банк идей), 'in_progress', 'ready', 'published'
    platform_tg INTEGER DEFAULT 0,
    platform_vc INTEGER DEFAULT 0,
    platform_habr INTEGER DEFAULT 0,
    platform_inst INTEGER DEFAULT 0,
    platform_setka INTEGER DEFAULT 0,
    platform_site INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 12. Переписка для Telegram-мессенджера
CREATE TABLE IF NOT EXISTS bot_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    sender TEXT NOT NULL,      -- 'user', 'bot', 'operator'
    text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(lead_id) REFERENCES leads(id)
);

-- 13. База обучения бота на ошибках (RLHF)
CREATE TABLE IF NOT EXISTS bot_training_examples (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_prompt TEXT NOT NULL,
    bad_bot_response TEXT,
    human_correction TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 14. Настройки локального Python-скрипта (Telegram-ассистента)
CREATE TABLE IF NOT EXISTS tg_assistant_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    system_prompt TEXT NOT NULL,
    tone_of_voice TEXT,
    typing_delay_min INTEGER DEFAULT 3,
    typing_delay_max INTEGER DEFAULT 7,
    blacklist_phones TEXT,     -- Черный список контактов (семья, музыка)
    api_id TEXT,
    api_hash TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 15. Платформенная аналитика публикаций (TG, VC, Habr, IG, Setka)
CREATE TABLE IF NOT EXISTS publication_analytics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    platform TEXT NOT NULL,    -- 'telegram', 'vc_ru', 'habr', 'instagram', 'setka'
    title TEXT NOT NULL,
    post_url TEXT,
    views_count INTEGER DEFAULT 0,
    likes_count INTEGER DEFAULT 0,
    comments_count INTEGER DEFAULT 0,
    video_plays INTEGER DEFAULT 0,
    published_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 16. Трекер кликов по коротким ссылкам
CREATE TABLE IF NOT EXISTS link_clicks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL,
    target_url TEXT NOT NULL,
    referrer TEXT,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 17. Динамический рубрикатор контента
CREATE TABLE IF NOT EXISTS rubrics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    examples TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 18. Модульные под-промпты и стили для скиллов (.md файлы)
CREATE TABLE IF NOT EXISTS skill_prompts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    skill_code TEXT NOT NULL,          -- Привязка к основному скиллу ('reels_hook', 'tg_post', 'vc_ru' и т.д.)
    title TEXT NOT NULL,               -- Название промпта/стиля ('Шаг 1: Усиление идеи', 'Стиль: Bauhaus Poster')
    prompt_type TEXT DEFAULT 'step',   -- 'step' (этап цепочки), 'style' (стиль анимации), 'rule' (доп. правило)
    step_order INTEGER DEFAULT 1,      -- Порядок выполнения в цепочке
    content_markdown TEXT NOT NULL,    -- Полный текст .md файла
    is_active INTEGER DEFAULT 1,       -- Включен по умолчанию или выбирается под задачу
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Индексы для оптимизации поиска и фильтрации
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE INDEX IF NOT EXISTS idx_content_status ON content_items(status);
CREATE INDEX IF NOT EXISTS idx_trends_approved ON trends(is_approved);
CREATE INDEX IF NOT EXISTS idx_site_visits_date ON site_visits(visited_at);
CREATE INDEX IF NOT EXISTS idx_site_clicks_btn ON site_button_clicks(button_name);
CREATE INDEX IF NOT EXISTS idx_content_plan_status ON content_plan(status);
CREATE INDEX IF NOT EXISTS idx_bot_messages_lead ON bot_messages(lead_id);
CREATE INDEX IF NOT EXISTS idx_publication_platform ON publication_analytics(platform);
CREATE INDEX IF NOT EXISTS idx_link_clicks_slug ON link_clicks(slug);
CREATE INDEX IF NOT EXISTS idx_rubrics_title ON rubrics(title);
CREATE INDEX IF NOT EXISTS idx_skill_prompts_code ON skill_prompts(skill_code);
CREATE INDEX IF NOT EXISTS idx_skill_prompts_active ON skill_prompts(is_active);


