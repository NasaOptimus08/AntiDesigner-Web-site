-- =============================================================================
-- ANTIDESIGNER CORE — DDL SCHEMA (SQLite 3)
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
    topic TEXT NOT NULL,
    status TEXT DEFAULT 'planned',
    scheduled_for DATE,
    format TEXT NOT NULL,
    content_markdown TEXT,
    views_count INTEGER DEFAULT 0,
    likes_count INTEGER DEFAULT 0,
    published_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

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

-- 7. Холодный аутрич и Аудит сайтов (PageSpeed + Vision-аналитика)
CREATE TABLE IF NOT EXISTS cold_outreach (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT NOT NULL,
    website_url TEXT NOT NULL,
    contact_info TEXT,
    screenshot_url TEXT,
    pagespeed_score INTEGER,
    tech_findings TEXT,
    design_critique TEXT,
    generated_pitch TEXT,
    status TEXT DEFAULT 'found',
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

-- Индексы для оптимизации поиска и фильтрации
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE INDEX IF NOT EXISTS idx_content_status ON content_items(status);
CREATE INDEX IF NOT EXISTS idx_trends_approved ON trends(is_approved);

