<?php
declare(strict_types=1);

/**
 * AntiDesigner Core - Базовый коннектор к SQLite 3 в режиме FastCGI WAL
 */

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $coreDataDir = dirname(__DIR__, 2) . '/core-data';
    $dbFile = $coreDataDir . '/database.sqlite';
    $schemaFile = $coreDataDir . '/schema.sql';

    if (!is_dir($coreDataDir)) {
        if (!mkdir($coreDataDir, 0755, true) && !is_dir($coreDataDir)) {
            throw new RuntimeException("Не удалось создать директорию: {$coreDataDir}");
        }
    }

    $isNewDb = !file_exists($dbFile) || (file_exists($dbFile) && filesize($dbFile) === 0);

    $dsn = 'sqlite:' . $dbFile;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, null, null, $options);

    // Архитектурные PRAGMA для режима высокой нагрузки без взаимных блокировок
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA foreign_keys = ON;');

    // Автоинициализация схемы таблиц при первом старте
    if ($isNewDb && file_exists($schemaFile)) {
        $schemaSql = file_get_contents($schemaFile);
        if ($schemaSql !== false && trim($schemaSql) !== '') {
            $pdo->exec($schemaSql);
        }
    }

    // Автоматические миграции структуры для существующих БД
    try {
        $pdo->exec("
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
        ");

        // Проверяем и добавляем расширенные поля в content_items
        $contentCols = $pdo->query("PRAGMA table_info(content_items)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $neededCols = [
            'plan_id'         => 'INTEGER DEFAULT NULL',
            'storyboard_json' => 'TEXT DEFAULT NULL',
            'media_files'     => 'TEXT DEFAULT NULL',
            'chat_history'    => 'TEXT DEFAULT NULL',
            'audio_url'       => 'TEXT DEFAULT NULL',
            'motion_html'     => 'TEXT DEFAULT NULL'
        ];
        foreach ($neededCols as $colName => $colDef) {
            if (!in_array($colName, $contentCols, true)) {
                $pdo->exec("ALTER TABLE content_items ADD COLUMN {$colName} {$colDef}");
            }
        }

        // Автомиграция специализированных скиллов Reels/Shorts в SQLite
        $specialSkills = [
            [
                'code' => 'code_animator',
                'title' => 'Кодовый Motion-Аниматор сцен (HTML/Canvas)',
                'category' => 'instagram',
                'tov' => 'Инженерный, лаконичный, генерация строго чистого HTML5/CSS3/Canvas кода 60fps без внешних тяжелых библиотек',
                'prompt' => <<<PROMPT
Ты - Senior Motion-программист и Frontend-разработчик личного бренда AntiDesigner (Евгений Антипин).
Твоя задача: генерировать чистый, самодостаточный, высокопроизводительный HTML5/Canvas/CSS3 код для вертикального формата 9:16 (1080x1920) со скоростью 60 FPS (requestAnimationFrame).

ТРЕБОВАНИЯ К КОДУ:
1. НИКАКОГО AI-СЛОПА И ТЯЖЕЛЫХ БИБЛИОТЕК: Никакого Three.js, GSAP, PixiJS, Lottie или внешних CDN. Только нативный Vanilla JS + HTML5 Canvas API + чистый CSS.
2. ЭСТЕТИКА DARK TECH:
   - Фон: глубокий черный/графитовый (#08090c, #0d1117).
   - Акценты: ультра-изумруд (#00ff88, #10b981), кибер-индиго (#6366f1), титановый бордер (#1e222d, rgba(255,255,255,0.08)).
   - Сетка: тонкая сетка 90x90px (rgba(255,255,255,0.03)) с радиальным свечением.
   - Типографика: моноширинные акценты, системные шрифты (-apple-system, monospace, Inter), максимальная четкость.
3. ДИНАМИКА И АНИМАЦИЯ (60 FPS):
   - Кинетическое появление плашек и текста (lerp, easeOutCubic).
   - Анимированный спидометр / счетчик PageSpeed 100/100 с дуговым кругом прогресса.
   - Счетчик времени отклика (0.35s против 8.4s у конструкторов).
   - Аудио-реактивные полосы / волны или бегущий сканер кода.
   - Нижний прогресс-бар таймлайна с секундомером.
4. ФОРМАТ ВЫХОДА:
   - Полный, готовый к запуску HTML документ с тегом <canvas id="reel-canvas" width="1080" height="1920"> и функцией window.startReelAnimation().
   - Никаких длинных тире (—), только дефисы (-).
PROMPT
            ],
            [
                'code' => 'voiceover_director',
                'title' => 'Режиссер озвучки и TTS (Voiceover)',
                'category' => 'instagram',
                'tov' => 'Уверенный, живой, разговорный тембр Senior-разработчика (Евгений Антипин). Без дикторского пафоса, с четкими паузами под монтаж.',
                'prompt' => <<<PROMPT
Ты - звукорежиссер и сценарист озвучки коротких вертикальных видео (Reels / Shorts) для личного бренда независимого разработчика AntiDesigner (Евгений Антипин).
На вход ты получаешь тему ролика, раскадровку и тайминги сцен.

ТВОЯ ЗАДАЧА:
1. Адаптировать текст озвучки под жесткий хронометраж (25-30 секунд, ровно 60-90 слов) с расстановкой пауз под визуальную смену сцен.
2. Написать Voice Direction Prompt (инструкцию для TTS-модели): тембр речи, интонации, смысловые ударения на ключевых цифрах (0.3 секунды, 100/100, 18к) и динамику подачи.
3. Позиционирование: строго от первого лица («Я независимый разработчик», «мой чистый код»). Категорически запрещены слова «мы», «наша команда», «наша студия».
4. Вернуть результат СТРОГО в формате JSON:
{
  "voice_script": "чистый текст озвучки для TTS без служебных пометок",
  "voice_prompt": "инструкция по подаче: уверенный, спокойный, разговорный тембр разработчика без дикторского пафоса",
  "estimated_seconds": 28
}
Никаких длинных тире (—), только дефисы (-).
PROMPT
            ],
            [
                'code' => 'video_editor',
                'title' => 'ИИ-Монтажер Reels (Переходы, Титры, Склейка)',
                'category' => 'instagram',
                'tov' => 'Точный режиссер вертикального видеомонтажа. Синхронизация хука, динамических переходов, титров и аудиодорожки.',
                'prompt' => <<<PROMPT
Ты - ведущий видеомонтажер и режиссер сборки вертикальных роликов Reels / Shorts (1080x1920, 9:16) для личного бренда AntiDesigner.
Твоя задача: объединить сгенерированный 3-секундный видео-хук (Сцена 1, hook.mp4), кодовую графику сцен 2-6 (Canvas/HTML) и закадровую озвучку (.mp3) в цельный бесшовный ролик.

ПРАВИЛА МАСТЕР-МОНТАЖА:
1. Сцена 1 (0-3 сек): Воспроизведение видео-хука с наложением кинетического заголовка и звукового маркера.
2. Переход (3.0-3.3 сек): Бесшовный динамический переход (Quick Glitch, Flash Cut или Zoom Push) из видеофайла в Canvas-анимацию.
3. Сцены 2-6 (3.3 - 28 сек): 60 FPS рендеринг кодовой инфографики (счетчики миллисекунд, PageSpeed 100/100, бенчмарки, титры) с точной синхронизацией по звуковой дорожке.
4. Кинетические субтитры: крупный легкочитаемый текст по центру или в нижней трети экрана, контрастный титановый бейдж.
5. Финал и CTA (27-30 сек): Яркая плашка призыва к действию («Напиши СКОРОСТЬ в Директ») и прогресс-бар с зацикливанием.
6. Выход: единый рабочий HTML5 Canvas плеер со встроенным JS, синхронизирующий видео-элемент, холст и аудиодорожку.
PROMPT
            ]
        ];

        foreach ($specialSkills as $sk) {
            $checkSk = (int)$pdo->query("SELECT COUNT(*) FROM skills WHERE code = " . $pdo->quote($sk['code']))->fetchColumn();
            if ($checkSk === 0) {
                $stmtSk = $pdo->prepare("INSERT INTO skills (code, title, category, system_prompt, tone_of_voice) VALUES (:code, :title, :category, :prompt, :tov)");
                $stmtSk->execute([
                    ':code'     => $sk['code'],
                    ':title'    => $sk['title'],
                    ':category' => $sk['category'],
                    ':prompt'   => $sk['prompt'],
                    ':tov'      => $sk['tov']
                ]);
            } else {
                // Актуализируем название и тональность
                $stmtUpd = $pdo->prepare("UPDATE skills SET title = :title, category = :category, tone_of_voice = :tov WHERE code = :code");
                $stmtUpd->execute([
                    ':title'    => $sk['title'],
                    ':category' => $sk['category'],
                    ':tov'      => $sk['tov'],
                    ':code'     => $sk['code']
                ]);
            }
        }

        // Засеивание стилей по умолчанию для code_animator
        $countCodeStyles = (int)$pdo->query("SELECT COUNT(*) FROM skill_prompts WHERE skill_code = 'code_animator'")->fetchColumn();
        if ($countCodeStyles === 0) {
            $defaultStyles = [
                [
                    'title'   => 'Стиль: Dark Tech Minimalist (HTML5/Canvas 9:16)',
                    'type'    => 'style',
                    'order'   => 1,
                    'content' => "# СТИЛЬ: DARK TECH MINIMALIST (HTML5 / CANVAS 9:16)\n\nЭстетика: Deep Charcoal (#08090c), титановый бордер (#1e222d), акцентный изумруд (#10b981) и ультра-фиолет (#6366f1).\nТипографика: JetBrains Mono / Inter, чистые геометрические пропорции.\nАнимация: CSS transform + requestAnimationFrame, плавное появление карточек, счетчик миллисекунд загрузки сайта, индикатор PageSpeed 100/100 с круговым прогресс-баром."
                ],
                [
                    'title'   => 'Стиль: Kinetic Typography & Swiss Grid (HTML5 9:16)',
                    'type'    => 'style',
                    'order'   => 2,
                    'content' => "# СТИЛЬ: KINETIC TYPOGRAPHY & SWISS GRID (HTML5 9:16)\n\nЭстетика: Черно-белый контраст с одним акцентным маркером (Safety Orange / Electric Green), модульная сетка швейцарской типографики.\nАнимация: Крупные кинетические текстовые хуки, пословное появление субтитров (word-by-word reveal), диагональные сдвиги, перечеркивание медленных цифр (5.8 сек -> 0.35 сек)."
                ],
                [
                    'title'   => 'Стиль: Cyber Terminal & Code Stream (HTML5 9:16)',
                    'type'    => 'style',
                    'order'   => 3,
                    'content' => "# СТИЛЬ: CYBER TERMINAL & CODE STREAM (HTML5 9:16)\n\nЭстетика: Терминальный монохром с неоновым зеленым курсором (#00ff88), сетка пикселей, бегущий поток компилируемого чистого кода (FastCGI PHP + Vanilla JS).\nАнимация: Построчный рендеринг исходного кода, замер потребления памяти (300 КБ против 12 МБ у конструкторов), сканирующий луч."
                ]
            ];
            $stmtP = $pdo->prepare("INSERT INTO skill_prompts (skill_code, title, prompt_type, step_order, content_markdown, is_active, created_at) VALUES ('code_animator', :title, :type, :order, :content, 1, CURRENT_TIMESTAMP)");
            foreach ($defaultStyles as $ds) {
                $stmtP->execute([
                    ':title'   => $ds['title'],
                    ':type'    => $ds['type'],
                    ':order'   => $ds['order'],
                    ':content' => $ds['content']
                ]);
            }
        }
    } catch (Throwable $e) {
        // Фоллбэк логирования миграций без блокировки приложения
    }

    return $pdo;
}

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection or initialization failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

