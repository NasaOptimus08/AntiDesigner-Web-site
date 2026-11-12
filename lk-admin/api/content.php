<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - CONTENT FACTORY API (Omnichannel Generation & Publishing)
 * Gemini AI generation + Telegram Bot API integration
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/gemini.php';
    require_once dirname(__DIR__, 2) . '/core-data/ai_router.php';
    require_once dirname(__DIR__, 2) . '/core-data/tools/cleanup_tool.php';
    /** @var PDO $pdo */

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = $_GET['action'] ?? $input['action'] ?? 'list';

    // -------------------------------------------------------------------------
    // ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ СТУДИИ REELS (РАСКАДРОВКА И ВОЙСОВЕР)
    // -------------------------------------------------------------------------
    if (!function_exists('parseStoryboardScenes')) {
        function parseStoryboardScenes(string $text): array {
            $scenes = [];
            preg_match_all('/(?:^|\n)###?\s*(?:Сцена|Scene|\d+\.)\s*(\d*)[:\s\.\-–—]*([^\n]*)(.*?)(?=(?:\n###?\s*(?:Сцена|Scene|\d+\.)|\n##|\z))/us', $text, $matches, PREG_SET_ORDER);

            if (!empty($matches)) {
                $idx = 1;
                foreach ($matches as $m) {
                    $title = trim($m[2]) ?: ('Сцена ' . $idx);
                    $body = trim($m[3]);

                    $timeRange = '0-5 сек';
                    if (preg_match('/(?:\[|\()?\s*(\d+[\s–—\-]+\d+\s*с(?:ек)?)\s*(?:\]|\))?/ui', $title . ' ' . $body, $tm)) {
                        $timeRange = trim($tm[1]);
                    }

                    $subtitles = '';
                    if (preg_match('/(?:Субтитры|Текст на экране|Титры)[:\s–—\-]*([^\n]+)/ui', $body, $subM)) {
                        $subtitles = trim($subM[1], " \t\n\r\0\x0B\"'«»");
                    }

                    $action = '';
                    if (preg_match('/(?:Действие|Визуал|Кадр)[:\s–—\-]*([^\n]+)/ui', $body, $actM)) {
                        $action = trim($actM[1]);
                    }

                    $retention = 'Pattern Interrupt';
                    if (preg_match('/(?:Роль|Удержание|Retention)[:\s–—\-]*([^\n]+)/ui', $body, $retM)) {
                        $retention = trim($retM[1]);
                    }

                    $imagePrompt = '';
                    if (preg_match('/Image\s*Prompt[^:]*:\s*([^\n]+(?:\n[^\n#]+)*)/ui', $body, $ipM)) {
                        $imagePrompt = trim($ipM[1]);
                    }

                    $videoPrompt = '';
                    if (preg_match('/Video\s*Prompt[^:]*:\s*([^\n]+(?:\n[^\n#]+)*)/ui', $body, $vpM)) {
                        $videoPrompt = trim($vpM[1]);
                    }

                    $scenes[] = [
                        'scene_num'    => $idx,
                        'title'        => $title,
                        'time_range'   => $timeRange,
                        'subtitles'    => $subtitles ?: $title,
                        'action'       => $action ?: 'Демонстрация интерфейса и замера скорости',
                        'retention'    => $retention,
                        'image_prompt' => $imagePrompt ?: 'Photorealistic cinematic vertical 9:16 shot, anamorphic lens, shallow depth of field, 8k resolution, raw style, Dark Tech aesthetic, emerald green and dark titanium accents',
                        'video_prompt' => $videoPrompt ?: 'Slow subtle camera push-in, micro motion, volumetric lighting, 60fps cinematic movement, vertical 9:16'
                    ];
                    $idx++;
                    if ($idx > 6) break;
                }
            }

            if (count($scenes) < 4) {
                $defaultRoles = [
                    1 => ['Hook (0-3 сек)', '0-3 сек', 'Твой сайт на Тильде ворует клиентов', 'Pattern Interrupt'],
                    2 => ['Problem (4-10 сек)', '4-10 сек', 'Конструктор грузится 5 секунд в сети 4G', 'Pain Agitation'],
                    3 => ['Reveal (11-18 сек)', '11-18 сек', 'Чистый код открывается мгновенно за 0.35с', 'Pattern Shift'],
                    4 => ['Escalation (19-24 сек)', '19-24 сек', 'Зеленые 100/100 в PageSpeed без абонентки', 'Proof Benchmark'],
                    5 => ['Payoff (25-28 сек)', '25-28 сек', 'Напрямую с Senior-разработчиком без студий', 'Direct Value'],
                    6 => ['CTA (29-30 сек)', '29-30 сек', 'Напиши СКОРОСТЬ в Директ для аудита', 'Call to Action']
                ];
                $scenes = [];
                foreach ($defaultRoles as $num => $def) {
                    $scenes[] = [
                        'scene_num'    => $num,
                        'title'        => $def[0],
                        'time_range'   => $def[1],
                        'subtitles'    => $def[2],
                        'action'       => "Динамичный кадр: {$def[0]}, кинетическая типографика",
                        'retention'    => $def[3],
                        'image_prompt' => "Photorealistic cinematic vertical 9:16 shot, anamorphic lens, 8k, Dark Tech aesthetic, emerald green neon, scene {$num}",
                        'video_prompt' => "Slow subtle camera push-in, micro motion, 60fps cinematic vertical 9:16"
                    ];
                }
            }

            return $scenes;
        }
    }

    if (!function_exists('extractFallbackVoiceScript')) {
        function extractFallbackVoiceScript(string $text): string {
            if (preg_match('/(?:Дикторский текст|Текст озвучки|Voiceover|voice_script)[:\s–—\-]*\n*(.*?)(?=\n###|\n##|\z)/uis', $text, $m)) {
                return trim(str_replace(['—', '–'], '-', $m[1]));
            }
            $words = preg_split('/\s+/u', trim(strip_tags($text)));
            $slice = array_slice($words, 0, 75);
            return implode(' ', $slice);
        }
    }

    if (!function_exists('generateDefaultReelsCanvasHtml')) {
        function generateDefaultReelsCanvasHtml(string $topic, array $scenes, int $durationSeconds = 28): string {
            $durationMs = max(5000, min(30000, $durationSeconds * 1000));
            $cleanScenes = array_values($scenes);
            $scenesJson = json_encode($cleanScenes, JSON_UNESCAPED_UNICODE);
            $escapedTopic = htmlspecialchars($topic, ENT_QUOTES, 'UTF-8');

            return '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>AntiDesigner Reels Studio</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body {
    width: 100%; height: 100%;
    background: #08090c;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }
  #reel-canvas {
    width: 100%; height: 100%;
    max-height: 100%;
    object-fit: contain;
    background: #08090c;
    display: block;
  }
</style>
</head>
<body>
<canvas id="reel-canvas" width="1080" height="1920"></canvas>
<script>
window.REEL_DURATION_MS = ' . $durationMs . ';
const canvas = document.getElementById("reel-canvas");
const ctx = canvas.getContext("2d");
const scenes = ' . $scenesJson . ';
const topicText = ' . json_encode($topic, JSON_UNESCAPED_UNICODE) . ';

let startTime = null;
let animId = null;

window.startReelAnimation = function() {
  if (animId) cancelAnimationFrame(animId);
  startTime = performance.now();
  render(performance.now());
};

function wrapText(context, text, x, y, maxWidth, lineHeight) {
  const words = (text || "").split(" ");
  let line = "";
  for (let n = 0; n < words.length; n++) {
    const testLine = line + words[n] + " ";
    const metrics = context.measureText(testLine);
    if (metrics.width > maxWidth && n > 0) {
      context.fillText(line, x, y);
      line = words[n] + " ";
      y += lineHeight;
    } else {
      line = testLine;
    }
  }
  context.fillText(line, x, y);
}

function render(now) {
  if (!startTime) startTime = now;
  let elapsed = now - startTime;
  if (elapsed > window.REEL_DURATION_MS) elapsed = window.REEL_DURATION_MS;
  const progress = elapsed / window.REEL_DURATION_MS;

  // 1. Очистка и глубокий темный фон
  ctx.fillStyle = "#08090c";
  ctx.fillRect(0, 0, 1080, 1920);

  // 2. Сетка Dark Tech
  ctx.strokeStyle = "rgba(255, 255, 255, 0.03)";
  ctx.lineWidth = 1;
  for (let x = 0; x < 1080; x += 90) {
    ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, 1920); ctx.stroke();
  }
  for (let y = 0; y < 1920; y += 90) {
    ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(1080, y); ctx.stroke();
  }

  // Неоновое свечение в центре
  const radial = ctx.createRadialGradient(540, 720, 20, 540, 720, 600);
  radial.addColorStop(0, "rgba(0, 255, 136, 0.07)");
  radial.addColorStop(1, "rgba(8, 9, 12, 0)");
  ctx.fillStyle = radial;
  ctx.fillRect(0, 0, 1080, 1920);

  // 3. Верхний бейдж
  ctx.save();
  ctx.fillStyle = "rgba(0, 255, 136, 0.08)";
  ctx.strokeStyle = "rgba(0, 255, 136, 0.4)";
  ctx.lineWidth = 2;
  ctx.beginPath();
  if (ctx.roundRect) {
    ctx.roundRect(140, 140, 800, 96, 48);
  } else {
    ctx.rect(140, 140, 800, 96);
  }
  ctx.fill(); ctx.stroke();

  ctx.fillStyle = "#00ff88";
  ctx.font = "bold 36px -apple-system, BlinkMacSystemFont, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText("ANTIDESIGNER · ЧИСТЫЙ КОД", 540, 188);
  ctx.restore();

  // 4. Спидометр скорости PageSpeed 100/100
  ctx.save();
  const gaugeAngle = Math.min(progress * 1.6, 1) * Math.PI * 2;
  ctx.lineWidth = 20;
  ctx.strokeStyle = "rgba(255, 255, 255, 0.08)";
  ctx.beginPath();
  ctx.arc(540, 540, 190, 0, Math.PI * 2);
  ctx.stroke();

  ctx.strokeStyle = "#00ff88";
  ctx.shadowColor = "#00ff88";
  ctx.shadowBlur = 15;
  ctx.beginPath();
  ctx.arc(540, 540, 190, -Math.PI / 2, -Math.PI / 2 + gaugeAngle);
  ctx.stroke();
  ctx.shadowBlur = 0;

  ctx.fillStyle = "#ffffff";
  ctx.font = "900 96px -apple-system, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  const currentSpeedScore = Math.min(100, Math.floor(progress * 140));
  ctx.fillText(currentSpeedScore >= 100 ? "100" : currentSpeedScore + "", 540, 520);

  ctx.fillStyle = "#00ff88";
  ctx.font = "bold 30px -apple-system, sans-serif";
  ctx.fillText("GOOGLE PAGESPEED", 540, 590);
  ctx.restore();

  // 5. Тема ролика
  ctx.save();
  ctx.fillStyle = "rgba(255, 255, 255, 0.7)";
  ctx.font = "500 36px -apple-system, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "top";
  wrapText(ctx, topicText, 540, 800, 920, 48);
  ctx.restore();

  // 6. Активная сцена и субтитры
  const sceneCount = scenes.length || 1;
  const sceneDurationMs = window.REEL_DURATION_MS / sceneCount;
  const currentSceneIndex = Math.min(sceneCount - 1, Math.floor(elapsed / sceneDurationMs));
  const curScene = scenes[currentSceneIndex] || {};

  ctx.save();
  // Карточка сцены
  ctx.fillStyle = "rgba(18, 22, 31, 0.85)";
  ctx.strokeStyle = "rgba(0, 255, 136, 0.3)";
  ctx.lineWidth = 2;
  ctx.beginPath();
  if (ctx.roundRect) {
    ctx.roundRect(80, 1140, 920, 440, 32);
  } else {
    ctx.rect(80, 1140, 920, 440);
  }
  ctx.fill(); ctx.stroke();

  // Заголовок сцены
  ctx.fillStyle = "#00ff88";
  ctx.font = "bold 32px -apple-system, sans-serif";
  ctx.textAlign = "left";
  ctx.fillText((curScene.title || ("Сцена " + (currentSceneIndex + 1))) + " · " + (curScene.time_range || ""), 120, 1210);

  // Субтитры крупно
  ctx.fillStyle = "#ffffff";
  ctx.font = "bold 52px -apple-system, sans-serif";
  const subText = curScene.subtitles || curScene.action || topicText;
  wrapText(ctx, subText, 120, 1270, 840, 68);

  // Retention tag
  if (curScene.retention) {
    ctx.fillStyle = "rgba(0, 255, 136, 0.15)";
    ctx.strokeStyle = "#00ff88";
    ctx.lineWidth = 1;
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(120, 1490, 360, 50, 25);
    ctx.fill(); ctx.stroke();
    ctx.fillStyle = "#00ff88";
    ctx.font = "bold 24px -apple-system, sans-serif";
    ctx.fillText("⚡ " + curScene.retention, 145, 1524);
  }
  ctx.restore();

  // 7. Нижний прогресс-бар таймлайна (0-30 сек)
  ctx.save();
  ctx.fillStyle = "rgba(255, 255, 255, 0.1)";
  ctx.fillRect(80, 1800, 920, 16);
  ctx.fillStyle = "#00ff88";
  ctx.shadowColor = "#00ff88";
  ctx.shadowBlur = 8;
  ctx.fillRect(80, 1800, 920 * progress, 16);
  ctx.shadowBlur = 0;

  // Секундомер
  const curSec = (elapsed / 1000).toFixed(1);
  const totalSec = (window.REEL_DURATION_MS / 1000).toFixed(0);
  ctx.fillStyle = "#8b949e";
  ctx.font = "30px monospace";
  ctx.textAlign = "right";
  ctx.fillText(curSec + "s / " + totalSec + "s", 1000, 1780);
  ctx.restore();

  if (elapsed < window.REEL_DURATION_MS) {
    animId = requestAnimationFrame(render);
  }
}

window.addEventListener("load", () => {
  window.startReelAnimation();
});
</script>
</body>
</html>';
        }
    }

    // -------------------------------------------------------------------------
    // 1. СПИСОК МАТЕРИАЛОВ (action=list)
    // -------------------------------------------------------------------------
    if ($action === 'list') {
        $stmt = $pdo->query('SELECT * FROM content_items ORDER BY id DESC LIMIT 30');
        $items = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'count'  => count($items),
            'items'  => $items
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. ОБНОВЛЕНИЕ ЧЕРНОВИКА (action=update)
    // -------------------------------------------------------------------------
    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $contentMarkdown = isset($input['content_markdown']) ? trim((string)$input['content_markdown']) : null;
        $motionHtml = isset($input['motion_html']) ? (string)$input['motion_html'] : null;
        $storyboardJson = isset($input['storyboard_json']) ? (string)$input['storyboard_json'] : null;
        $audioUrl = isset($input['audio_url']) ? (string)$input['audio_url'] : null;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный id'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($contentMarkdown !== null && $contentMarkdown !== '') {
            $stmt = $pdo->prepare('UPDATE content_items SET content_markdown = :content WHERE id = :id');
            $stmt->execute([':content' => $contentMarkdown, ':id' => $id]);
        }
        if ($motionHtml !== null && $motionHtml !== '') {
            $pdo->prepare('UPDATE content_items SET motion_html = :mh WHERE id = :id')->execute([':mh' => $motionHtml, ':id' => $id]);
        }
        if ($storyboardJson !== null && $storyboardJson !== '') {
            $pdo->prepare('UPDATE content_items SET storyboard_json = :sb WHERE id = :id')->execute([':sb' => $storyboardJson, ':id' => $id]);
        }
        $voiceScript = isset($input['voice_script']) ? trim((string)$input['voice_script']) : null;
        if ($voiceScript !== null && $voiceScript !== '') {
            $stmtSb = $pdo->prepare("SELECT storyboard_json FROM content_items WHERE id = :id");
            $stmtSb->execute([':id' => $id]);
            $sbRaw = $stmtSb->fetchColumn();
            $sb = $sbRaw ? json_decode((string)$sbRaw, true) : [];
            if (!is_array($sb)) $sb = [];
            $sb['voice_script'] = $voiceScript;
            $pdo->prepare("UPDATE content_items SET storyboard_json = :sb WHERE id = :id")
                ->execute([':sb' => json_encode($sb, JSON_UNESCAPED_UNICODE), ':id' => $id]);
        }
        if ($audioUrl !== null && $audioUrl !== '') {
            $pdo->prepare('UPDATE content_items SET audio_url = :au, media_files = :au WHERE id = :id')->execute([':au' => $audioUrl, ':id' => $id]);
        }

        echo json_encode([
            'status'  => 'success',
            'message' => 'Материал успешно обновлен'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. ГЕНЕРАЦИЯ ПАКЕТА МАТЕРИАЛОВ ЧЕРЕЗ GEMINI (action=generate)
    // -------------------------------------------------------------------------
    if ($action === 'generate') {
        $topic = trim((string)($input['topic'] ?? ''));
        if ($topic === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Укажите тему или инфоповод для генерации'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Список форматов для генерации: поддержка как пакета, так и одиночных форматов
        $formatKey = $input['format'] ?? null;
        if ($formatKey === 'article') {
            $formats = ['vc_ru'];
        } elseif ($formatKey === 'telegram') {
            $formats = ['tg_post'];
        } elseif ($formatKey === 'instagram') {
            $formats = ['reels_hook'];
        } elseif ($formatKey === 'setka') {
            $formats = ['setka_post'];
        } elseif ($formatKey === 'lead_magnet') {
            $formats = ['lead_magnet'];
        } elseif ($formatKey === 'all' || !empty($input['formats'])) {
            $formats = $input['formats'] ?? ['vc_ru', 'tg_post', 'reels_hook', 'lead_magnet', 'site_geo', 'setka_post'];
            if (is_string($formats)) {
                $formats = array_filter(array_map('trim', explode(',', $formats)));
            }
        } else {
            $formats = ['vc_ru', 'tg_post', 'reels_hook', 'lead_magnet', 'setka_post'];
        }

        // 1. Подтягиваем всю актуальную базу знаний для RAG-контекста
        $stmtKb = $pdo->query('SELECT category, title, content_markdown FROM knowledge_base ORDER BY id ASC');
        $kbRows = $stmtKb->fetchAll();
        $knowledgeContext = "АКТУАЛЬНЫЙ КОНТЕКСТ КОМПАНИИ ANTIDESIGNER:\n";
        foreach ($kbRows as $kb) {
            $knowledgeContext .= "--- {$kb['title']} ({$kb['category']}) ---\n{$kb['content_markdown']}\n\n";
        }

        // 2. Подтягиваем системные промпты скиллов
        $stmtSkills = $pdo->query('SELECT code, title, system_prompt, tone_of_voice FROM skills');
        $allSkills = [];
        while ($row = $stmtSkills->fetch()) {
            $allSkills[$row['code']] = $row;
        }

        // 3. Инициализируем AI Router с разделением ключей
        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $aiRouter = new AIRouter($config);
        $aiRouter->setPDO($pdo);

        $results = [];
        $errors = [];

        // Маппинг форматов Контент-фабрики на строгие типы задач AIRouter (ВСЕ идут на MODEL_HEAVY)
        $taskMap = [
            'tg_post'     => AIRouter::TASK_TG_POST,
            'telegram'    => AIRouter::TASK_TG_POST,
            'vc_ru'       => AIRouter::TASK_LONGREAD,
            'habr'        => AIRouter::TASK_LONGREAD,
            'article'     => AIRouter::TASK_LONGREAD,
            'setka_post'  => AIRouter::TASK_SETKA_POST,
            'setka'       => AIRouter::TASK_SETKA_POST,
            'reels_hook'  => AIRouter::TASK_REELS,
            'reels'       => AIRouter::TASK_REELS,
            'instagram'   => AIRouter::TASK_REELS,
            'ig_carousel' => AIRouter::TASK_CAROUSEL,
            'carousel'    => AIRouter::TASK_CAROUSEL,
            'lead_magnet' => AIRouter::TASK_LEAD_MAGNET,
            'site_geo'    => AIRouter::TASK_GEO_ARTICLE,
            'geo'         => AIRouter::TASK_GEO_ARTICLE,
        ];

        // 4. Для каждого выбранного формата вызываем Gemini Flash 3.8 (MODEL_HEAVY) с подтягиванием профильного скилла
        foreach ($formats as $fmtCode) {
            $taskType = $taskMap[$fmtCode] ?? AIRouter::TASK_LONGREAD;
            $resolvedModel = $aiRouter->resolveModel($taskType); // Строго MODEL_HEAVY

            // Обязательный точечный запрос скилла из базы данных skills
            $stmtSkill = $pdo->prepare('SELECT code, title, system_prompt, tone_of_voice FROM skills WHERE code = :code LIMIT 1');
            $stmtSkill->execute([':code' => $fmtCode]);
            $skill = $stmtSkill->fetch();

            if (!$skill || empty($skill['system_prompt'])) {
                // Если скилла нет по прямому коду, пробуем через loadSkill роутера
                $skill = $aiRouter->loadSkill($fmtCode);
            }

            // Подтягиваем активные модульные под-промпты из skill_prompts
            $subPromptsInstruction = '';
            $stmtSubPrompts = $pdo->prepare("SELECT title, prompt_type, step_order, content_markdown FROM skill_prompts WHERE skill_code = :code AND is_active = 1 ORDER BY step_order ASC, id ASC");
            $stmtSubPrompts->execute([':code' => $fmtCode]);
            $activeSubPrompts = $stmtSubPrompts->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($activeSubPrompts)) {
                $subPromptsInstruction = "\n\n### ЦЕПОЧКА МОДУЛЬНЫХ ПРОМПТОВ И СТИЛЕЙ (.MD):\n";
                foreach ($activeSubPrompts as $sp) {
                    $subPromptsInstruction .= "\n---\n#### {$sp['title']} (Тип: {$sp['prompt_type']}, Шаг {$sp['step_order']}):\n{$sp['content_markdown']}\n";
                }
            }

            $systemInstruction = $skill['system_prompt'] . "\n\n" .
                "TONE OF VOICE:\n" . ($skill['tone_of_voice'] ?: 'Dark Tech, лаконичный, экспертный, фактологический') . "\n\n" .
                $knowledgeContext .
                $subPromptsInstruction;

            if (in_array($fmtCode, ['reels_hook', 'reels', 'instagram'], true)) {
                $userPrompt = "ТЕМА РОЛИКА: {$topic}\n\n" .
                    "ЗАДАЧА: Сгенерируй профессиональный пакет для вертикального видео (Reels / Shorts, 9:16) строго по 6-фазной дуге удержания (хронометраж до 30 секунд):\n" .
                    "1. СЦЕНАРИЙ (ровно 60-90 слов, без канцелярщины, от лица Senior-разработчика Евгения Антипина).\n" .
                    "2. РАСКАДРОВКА ИЗ 5-6 СЦЕН:\n" .
                    "   - Сцена 1: Hook (0-3 сек)\n" .
                    "   - Сцена 2: Problem (4-10 сек)\n" .
                    "   - Сцена 3: Reveal (11-18 сек)\n" .
                    "   - Сцена 4: Escalation (19-24 сек)\n" .
                    "   - Сцена 5: Payoff (25-28 сек)\n" .
                    "   - Сцена 6: CTA (29-30 сек)\n" .
                    "   Для каждой сцены укажи: [Время], [Действие], [Субтитры (4-8 слов)], [Роль удержания], [Image Prompt (9:16)], [Video Prompt (9:16)].\n\n" .
                    "3. САМОДОСТАТОЧНЫЙ HTML5/CSS3/CANVAS MOTION-КОД РОЛИКА (9:16, 1080x1920 viewBox, 60fps, темная тема Dark Tech, анимированный круговой счетчик PageSpeed 100/100, поочередная смена титров, нижний таймлайн 0-30 сек). Оберни код в блок ```html <!DOCTYPE html>...</html> ```.\n\n" .
                    "Отдай готовый материал в четко структурированном виде. Без длинных тире (-).";
            } else {
                $userPrompt = "ТЕМА МАТЕРИАЛА: {$topic}\n\n" .
                    "ЗАДАЧА: Сгенерируй полноценный, готовый к публикации материал для формата «{$skill['title']}» (код: {$fmtCode}). " .
                    "Опирайся на контекст компании AntiDesigner, приводи реальные цифры и преимущества чистого кода перед конструкторами. " .
                    "Отдай только готовый текст без лишних вступительных реплик. Без длинных тире (символ -).";
            }

            try {
                // Все чистовые материалы Контент-фабрики генерирует MODEL_HEAVY (Gemini Flash 3.8, ключ pza...)
                // Для лонгридов (vc_ru / habr) подключаем thinkingBudget = 2048
                $thinkingBudget = in_array($taskType, [AIRouter::TASK_LONGREAD, 'vc_ru'], true) ? 2048 : null;

                $generatedText = $aiRouter->execute(
                    $taskType,
                    $systemInstruction,
                    $userPrompt,
                    $thinkingBudget
                );

                // Дополнительная валидация типографики: исключение длинных тире
                $generatedText = str_replace(['—', '–'], '-', trim($generatedText));

                // Специальная обработка для формата Reels: 2-шаговый пайплайн (Motion + Voiceover)
                $motionHtml = '';
                $storyboardData = null;

                if (in_array($fmtCode, ['reels_hook', 'reels', 'instagram'], true)) {
                    // Извлекаем HTML5 Motion-код
                    if (preg_match('/```html\s*(.*?)\s*```/is', $generatedText, $mHtml)) {
                        $motionHtml = trim($mHtml[1]);
                    } elseif (preg_match('/<!DOCTYPE html>.*?<\/html>/is', $generatedText, $mDoc)) {
                        $motionHtml = trim($mDoc[0]);
                    }
                    if ($motionHtml === '') {
                        $motionHtml = "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><style>body{margin:0;background:#08090c;color:#e6edf3;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;overflow:hidden;}.phone{aspect-ratio:9/16;height:95vh;background:#0d1117;border:1px solid #1e222d;border-radius:16px;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:20px;text-align:center;box-sizing:border-box;}h2{color:#00ff88;font-size:24px;margin-bottom:12px;}</style></head><body><div class='phone'><h2>AntiDesigner</h2><p>" . htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') . "</p><div style='margin-top:20px;padding:8px 16px;border:1px solid #00ff88;border-radius:20px;color:#00ff88;'>PageSpeed 100/100 · 0.35s</div></div></body></html>";
                    }

                    // ШАГ 2: Режиссер закадрового голоса (voiceover_director)
                    $stmtVo = $pdo->prepare("SELECT system_prompt, tone_of_voice FROM skills WHERE code = 'voiceover_director' LIMIT 1");
                    $stmtVo->execute();
                    $voSkill = $stmtVo->fetch();
                    $voSysPrompt = ($voSkill && !empty($voSkill['system_prompt']))
                        ? $voSkill['system_prompt']
                        : 'Ты - звукорежиссер коротких видео. Адаптируй текст под хронометраж до 30 секунд. Верни строго JSON: {"voice_script": "...", "voice_prompt": "...", "estimated_seconds": 28}.';

                    $voUserPrompt = "ТЕМА РОЛИКА: {$topic}\nСЦЕНАРИЙ И ТАЙМИНГИ:\n" . substr($generatedText, 0, 3000) . "\n\n"
                        . "Адаптируй дикторский текст под точный тайминг до 30 секунд и верни СТРОГО JSON: {\"voice_script\": \"...\", \"voice_prompt\": \"...\", \"estimated_seconds\": 28}. Запрещены длинные тире (-).";

                    $step2Output = $aiRouter->execute(AIRouter::TASK_REELS, $voSysPrompt, $voUserPrompt);
                    $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim((string)$step2Output));
                    $cleanJson = preg_replace('/\s*```$/i', '', $cleanJson);
                    $voData = json_decode(trim($cleanJson), true);

                    $voiceScript = is_array($voData) && !empty($voData['voice_script'])
                        ? trim((string)$voData['voice_script'])
                        : extractFallbackVoiceScript($generatedText);

                    $voicePrompt = is_array($voData) && !empty($voData['voice_prompt'])
                        ? trim((string)$voData['voice_prompt'])
                        : 'Уверенный тембр Senior-разработчика (Евгений Антипин). Без дикторского пафоса, четкие паузы под монтаж, акцент на цифрах скорости (0.35с, 100/100).';

                    $estimatedSeconds = is_array($voData) && !empty($voData['estimated_seconds'])
                        ? (int)$voData['estimated_seconds']
                        : 28;

                    $scenes = parseStoryboardScenes($generatedText);

                    $storyboardData = [
                        'topic'             => $topic,
                        'format'            => $fmtCode,
                        'scenario'          => $generatedText,
                        'scenes'            => $scenes,
                        'voice_script'      => $voiceScript,
                        'voice_prompt'      => $voicePrompt,
                        'estimated_seconds' => $estimatedSeconds,
                        'motion_html'       => $motionHtml,
                        'audio_url'         => null
                    ];

                    $stmtInsert = $pdo->prepare('INSERT INTO content_items (topic, format, content_markdown, motion_html, storyboard_json, status) VALUES (:topic, :format, :content, :motion, :sb, :status)');
                    $stmtInsert->execute([
                        ':topic'   => $topic,
                        ':format'  => $fmtCode,
                        ':content' => $generatedText,
                        ':motion'  => $motionHtml,
                        ':sb'      => json_encode($storyboardData, JSON_UNESCAPED_UNICODE),
                        ':status'  => 'draft'
                    ]);
                    $newId = (int)$pdo->lastInsertId();

                    $results[] = [
                        'id'                => $newId,
                        'format'            => $fmtCode,
                        'task_type'         => $taskType,
                        'title'             => $skill['title'],
                        'content'           => $generatedText,
                        'motion_html'       => $motionHtml,
                        'voice_script'      => $voiceScript,
                        'voice_prompt'      => $voicePrompt,
                        'estimated_seconds' => $estimatedSeconds,
                        'scenes'            => $scenes,
                        'storyboard'        => $storyboardData,
                        'model_used'        => 'gemini-flash-3.8 (HEAVY)',
                        'status'            => 'draft'
                    ];
                } else {
                    // Сохраняем в базу данных в статусе draft для обычных текстовых форматов
                    $stmtInsert = $pdo->prepare('INSERT INTO content_items (topic, format, content_markdown, status) VALUES (:topic, :format, :content, :status)');
                    $stmtInsert->execute([
                        ':topic'   => $topic,
                        ':format'  => $fmtCode,
                        ':content' => $generatedText,
                        ':status'  => 'draft'
                    ]);
                    $newId = (int)$pdo->lastInsertId();

                    $results[] = [
                        'id'         => $newId,
                        'format'     => $fmtCode,
                        'task_type'  => $taskType,
                        'title'      => $skill['title'],
                        'content'    => $generatedText,
                        'model_used' => 'gemini-flash-3.8 (HEAVY)',
                        'status'     => 'draft'
                    ];
                }
            } catch (Throwable $genErr) {
                $errors[] = "Ошибка при генерации {$skill['title']}: " . $genErr->getMessage();
            }
        }

        if (empty($results) && !empty($errors)) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => implode('; ', $errors)
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        echo json_encode([
            'status'     => 'success',
            'topic'      => $topic,
            'count'      => count($results),
            'model_used' => 'gemini-flash-3.8 (HEAVY)',
            'generated'  => $results,
            'errors'     => $errors
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ / ЧАТ (action=publish_tg)
    // -------------------------------------------------------------------------
    if ($action === 'publish_tg') {
        $contentId = isset($input['content_id']) ? (int)$input['content_id'] : 0;
        $textToPublish = trim((string)($input['text'] ?? ''));

        if ($contentId > 0 && $textToPublish === '') {
            $stmtGet = $pdo->prepare('SELECT content_markdown FROM content_items WHERE id = :id');
            $stmtGet->execute([':id' => $contentId]);
            $textToPublish = (string)$stmtGet->fetchColumn();
        }

        if ($textToPublish === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Текст публикации пуст'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Читаем конфигурацию Telegram
        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];

        $botToken = trim((string)($config['telegram_bot_token'] ?? ''));
        $channelId = trim((string)($config['telegram_channel_id'] ?? '@AntidesignerAI'));
        if ($channelId === '') {
            $channelId = '@AntidesignerAI';
        }
        if (!str_starts_with($channelId, '@') && !str_starts_with($channelId, '-100') && !is_numeric($channelId)) {
            $channelId = '@' . $channelId;
        }

        if ($botToken === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Для публикации в Telegram укажите telegram_bot_token в core-data/config.local.php'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Подключаем инструмент tg_notify.php
        require_once dirname(__DIR__, 2) . '/core-data/tools/tg_notify.php';

        $tgRes = sendTelegramChannelPost($textToPublish, $channelId, $config);

        if ($tgRes['status'] !== 'success') {
            http_response_code(502);
            echo json_encode([
                'status'  => 'error',
                'message' => $tgRes['message'] ?? 'Ошибка отправки в канал Telegram',
                'details' => $tgRes
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $publishedUrl = $tgRes['published_url'] ?? ('https://t.me/' . ltrim($channelId, '@'));

        // Обновляем статус в БД
        if ($contentId > 0) {
            $stmtUpd = $pdo->prepare("UPDATE content_items SET status = 'published', published_url = :url WHERE id = :id");
            $stmtUpd->execute([
                ':url' => $publishedUrl,
                ':id'  => $contentId
            ]);

            // Автоматическая очистка временных медиа и тяжелых полей драфта после публикации
            cleanupPublishedDraftGarbage($pdo, $contentId);
        }

        echo json_encode([
            'status'            => 'success',
            'message'           => 'Материал успешно опубликован в Telegram-канале ' . $channelId,
            'channel'           => $channelId,
            'published_url'     => $publishedUrl,
            'message_id'        => $tgRes['message_id'] ?? null,
            'telegram_response' => $tgRes
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.1. ПРИНУДИТЕЛЬНАЯ ОЧИСТКА ОПУБЛИКОВАННЫХ ДРАФТОВ (action=cleanup_published_drafts)
    // -------------------------------------------------------------------------
    if ($action === 'cleanup_published_drafts') {
        $stats = cleanupPublishedDraftGarbage($pdo, 0);

        echo json_encode([
            'status'  => 'success',
            'message' => "Очистка завершена: освобождено {$stats['freed_kb']} КБ, удалено файлов: {$stats['deleted_files']}, очищено драфтов: {$stats['updated_items']}",
            'stats'   => $stats
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.2. ПОЛУЧЕНИЕ ПОЛНЫХ ДАННЫХ ДРАФТА (action=get_draft)
    // -------------------------------------------------------------------------
    if ($action === 'get_draft') {
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан ID драфта'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM content_items WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$draft) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Черновик не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $draft['storyboard'] = !empty($draft['storyboard_json']) ? json_decode((string)$draft['storyboard_json'], true) : null;
        $draft['chat_history_list'] = !empty($draft['chat_history']) ? json_decode((string)$draft['chat_history'], true) : [];

        echo json_encode([
            'status' => 'success',
            'draft'  => $draft
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.3. ГЕНЕРАЦИЯ HTML5/CANVAS MOTION-АНИМАЦИИ (action=generate_motion)
    // -------------------------------------------------------------------------
    if ($action === 'generate_motion') {
        $contentId = (int)($input['content_id'] ?? 0);
        $styleCode = trim((string)($input['style_code'] ?? ''));
        $scriptText = trim((string)($input['script_text'] ?? ''));
        $topic = trim((string)($input['topic'] ?? ''));

        if ($contentId > 0 && $scriptText === '') {
            $stmtD = $pdo->prepare("SELECT topic, content_markdown FROM content_items WHERE id = :id");
            $stmtD->execute([':id' => $contentId]);
            $dRow = $stmtD->fetch();
            if ($dRow) {
                $scriptText = (string)$dRow['content_markdown'];
                $topic = $topic ?: (string)$dRow['topic'];
            }
        }

        // Подтягиваем описание стиля из skill_prompts
        $stylePrompt = '';
        if ($styleCode !== '') {
            $stmtSt = $pdo->prepare("SELECT content_markdown FROM skill_prompts WHERE skill_code = 'reels_hook' AND (title LIKE :t OR prompt_type = 'style') LIMIT 1");
            $stmtSt->execute([':t' => "%{$styleCode}%"]);
            $stylePrompt = (string)$stmtSt->fetchColumn();
        }
        if ($stylePrompt === '') {
            $stylePrompt = "Эстетика: Dark Tech Minimalist (HTML5/Canvas 9:16). Глубокий темный фон (#08090c), неоновый изумруд (#10b981), титановые рамки, плавная 60fps кинетическая типографика и динамический счетчик скорости.";
        }

        $sysPrompt = "Ты - Senior Creative Technologist и мастер генеративного моушн-дизайна (HTML5/CSS3/Canvas). "
            . "Твоя задача: сгенерировать самодостаточный, полностью рабочий вертикальный рекламный ролик (соотношение 9:16, 1080x1920 viewBox) на чистом HTML5, CSS3 и Canvas JS без внешних библиотек и CDN.\n\n"
            . "СТИЛЬ И ГРАФИКА:\n{$stylePrompt}\n\n"
            . "СТРОГИЕ ТРЕБОВАНИЯ К КОДУ:\n"
            . "1. Полный монолитный HTML-документ: <!DOCTYPE html><html><head><style>...</style></head><body><div class='viewport'><canvas id='c'></canvas><div class='overlay'>...</div></div><script>...</script></body></html>\n"
            . "2. Пропорции вертикальные 9:16, контейнер центрирован в окне браузера с черным фоном вокруг.\n"
            . "3. Плавная циклическая 60fps анимация (requestAnimationFrame): появление хука, поочередная смена 5-6 смысловых фраз сценария (по 3-4 секунды на слайд), анимированный счетчик миллисекунд и круговой прогресс-бар PageSpeed 100/100, нижний таймлайн ролика.\n"
            . "4. Запрещены любые внешние CDN (шрифты и иконки - системные sans-serif/monospace, SVG или рисуются на canvas).\n"
            . "5. Отдай СТРОГО чистый исходный код HTML без markdown-оберток.";

        $userPrompt = "ТЕМА РОЛИКА: {$topic}\nСЦЕНАРИЙ И ТЕЗИСЫ:\n{$scriptText}\n\nСгенерируй код самодостаточного вертикального 9:16 HTML5 моушн-ролика прямо сейчас.";

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $aiRouter = new AIRouter($config);
        $aiRouter->setPDO($pdo);

        $htmlMotion = $aiRouter->call($userPrompt, $sysPrompt, AIRouter::MODEL_HEAVY, 0.4);

        // Очистка от возможных markdown тегов
        $cleanHtml = trim($htmlMotion);
        $cleanHtml = preg_replace('/^```(?:html)?\s*/i', '', $cleanHtml);
        $cleanHtml = preg_replace('/\s*```$/i', '', $cleanHtml);

        if ($contentId > 0) {
            $stmtUpd = $pdo->prepare("UPDATE content_items SET motion_html = :html WHERE id = :id");
            $stmtUpd->execute([':html' => $cleanHtml, ':id' => $contentId]);
        }

        echo json_encode([
            'status'      => 'success',
            'motion_html' => $cleanHtml,
            'message'     => 'Моушн-анимация успешно сгенерирована'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.4. СИНТЕЗ ОЗВУЧКИ ЧЕРЕЗ POLZA.AI / GEMINI TTS (action=generate_audio / action=generate_tts)
    // -------------------------------------------------------------------------
    if ($action === 'generate_audio' || $action === 'generate_tts') {
        $contentId = (int)($input['content_id'] ?? 0);
        $text = trim((string)($input['text'] ?? ''));

        if ($text === '' && $contentId > 0) {
            $stmtC = $pdo->prepare("SELECT storyboard_json, content_markdown FROM content_items WHERE id = :id LIMIT 1");
            $stmtC->execute([':id' => $contentId]);
            $cRow = $stmtC->fetch(PDO::FETCH_ASSOC);
            if ($cRow) {
                if (!empty($cRow['storyboard_json'])) {
                    $sb = json_decode((string)$cRow['storyboard_json'], true);
                    if (!empty($sb['voice_script'])) {
                        $text = trim((string)$sb['voice_script']);
                    }
                }
                if ($text === '') {
                    $text = extractFallbackVoiceScript((string)$cRow['content_markdown']);
                }
            }
        }

        if ($text === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Текст для озвучки пуст'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];

        $polzaKey = trim((string)($config['polza_api_key'] ?? $config['gemini_heavy_api_key'] ?? ''));
        $ttsModel = trim((string)($config['polza_tts_model'] ?? 'google/gemini-3.8-flash-lite-tts')) ?: 'google/gemini-3.8-flash-lite-tts';

        if ($polzaKey === '') {
            echo json_encode([
                'status'  => 'error',
                'message' => 'polza_api_key не настроен в config.local.php. Укажите ключ или прикрепите готовый аудиофайл.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Очищаем текст от меток пауз и режиссуры для TTS движка
        $cleanVoiceText = preg_replace('/\[пауза\s*[\d\.\,]*\s*с(?:ек)?\]/iu', '', $text);
        $cleanVoiceText = preg_replace('/[#*`_~]/', '', $cleanVoiceText);
        $cleanVoiceText = trim($cleanVoiceText);

        $payload = json_encode([
            'model' => $ttsModel,
            'input' => $cleanVoiceText,
            'voice' => 'onyx'
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://polza.ai/api/v1/audio/speech');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $polzaKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $audioRaw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($audioRaw) && strlen($audioRaw) > 500) {
            $uploadsDir = dirname(__DIR__, 2) . '/core-data/uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $audioFileName = 'voice_' . ($contentId ?: rand(100, 999)) . '_' . time() . '.mp3';
            $audioFilePath = $uploadsDir . '/' . $audioFileName;
            file_put_contents($audioFilePath, $audioRaw);

            $relativeAudioUrl = '../core-data/uploads/' . $audioFileName;

            if ($contentId > 0) {
                $stmtUpd = $pdo->prepare("UPDATE content_items SET audio_url = :url, media_files = :url WHERE id = :id");
                $stmtUpd->execute([':url' => $relativeAudioUrl, ':id' => $contentId]);

                $stmtGetSb = $pdo->prepare("SELECT storyboard_json FROM content_items WHERE id = :id");
                $stmtGetSb->execute([':id' => $contentId]);
                $sbRaw = $stmtGetSb->fetchColumn();
                if ($sbRaw) {
                    $sb = json_decode((string)$sbRaw, true);
                    if (is_array($sb)) {
                        $sb['audio_url'] = $relativeAudioUrl;
                        $pdo->prepare("UPDATE content_items SET storyboard_json = :sb WHERE id = :id")
                            ->execute([':sb' => json_encode($sb, JSON_UNESCAPED_UNICODE), ':id' => $contentId]);
                    }
                }
            }

            echo json_encode([
                'status'    => 'success',
                'audio_url' => $relativeAudioUrl,
                'model'     => $ttsModel,
                'message'   => 'Озвучка успешно сгенерирована через Polza.ai'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $errMsg = 'Ошибка синтеза речи через Polza.ai API (HTTP ' . $httpCode . ')';
        $errData = json_decode((string)$audioRaw, true);
        if (isset($errData['error']['message'])) {
            $errMsg .= ': ' . $errData['error']['message'];
        }

        echo json_encode([
            'status'  => 'error',
            'message' => $errMsg
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.5. ПРИКРЕПЛЕНИЕ АУДИОДОРОЖКИ К ДРАФТУ (action=attach_audio)
    // -------------------------------------------------------------------------
    if ($action === 'attach_audio') {
        $contentId = (int)($input['content_id'] ?? 0);
        $audioUrl = trim((string)($input['audio_url'] ?? ''));

        if ($contentId <= 0 || $audioUrl === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан content_id или audio_url'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE content_items SET audio_url = :url WHERE id = :id");
        $stmt->execute([':url' => $audioUrl, ':id' => $contentId]);

        echo json_encode([
            'status'    => 'success',
            'message'   => 'Аудиодорожка успешно прикреплена к драфту',
            'audio_url' => $audioUrl
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.6. ГЕНЕРАЦИЯ ОДИНОЧНОГО ДРАФТА / REELS СТУДИИ (action=generate_single)
    // -------------------------------------------------------------------------
    if ($action === 'generate_single') {
        $contentId = (int)($input['content_id'] ?? 0);
        $topic = trim((string)($input['topic'] ?? ''));
        $fmtCode = trim((string)($input['format'] ?? 'reels_hook'));

        if ($topic === '' && $contentId > 0) {
            $stmtEx = $pdo->prepare("SELECT topic, format FROM content_items WHERE id = :id");
            $stmtEx->execute([':id' => $contentId]);
            $rowEx = $stmtEx->fetch(PDO::FETCH_ASSOC);
            if ($rowEx) {
                $topic = (string)$rowEx['topic'];
                if (empty($input['format'])) {
                    $fmtCode = (string)$rowEx['format'];
                }
            }
        }

        if ($topic === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Укажите тему для генерации'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $aiRouter = new AIRouter($config);
        $aiRouter->setPDO($pdo);

        // Контекст базы знаний
        $stmtKb = $pdo->query('SELECT category, title, content_markdown FROM knowledge_base ORDER BY id ASC');
        $kbRows = $stmtKb->fetchAll();
        $knowledgeContext = "АКТУАЛЬНЫЙ КОНТЕКСТ КОМПАНИИ ANTIDESIGNER:\n";
        foreach ($kbRows as $kb) {
            $knowledgeContext .= "--- {$kb['title']} ({$kb['category']}) ---\n{$kb['content_markdown']}\n\n";
        }

        // Подтягиваем скилл
        $stmtSkill = $pdo->prepare('SELECT code, title, system_prompt, tone_of_voice FROM skills WHERE code = :code LIMIT 1');
        $stmtSkill->execute([':code' => $fmtCode]);
        $skill = $stmtSkill->fetch();
        if (!$skill || empty($skill['system_prompt'])) {
            $skill = $aiRouter->loadSkill($fmtCode);
        }

        // Под-промпты
        $subPromptsInstruction = '';
        $stmtSub = $pdo->prepare("SELECT title, prompt_type, step_order, content_markdown FROM skill_prompts WHERE skill_code = :code AND is_active = 1 ORDER BY step_order ASC, id ASC");
        $stmtSub->execute([':code' => $fmtCode]);
        $activeSub = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($activeSub)) {
            $subPromptsInstruction = "\n\n### ЦЕПОЧКА МОДУЛЬНЫХ ПРОМПТОВ И СТИЛЕЙ (.MD):\n";
            foreach ($activeSub as $sp) {
                $subPromptsInstruction .= "\n---\n#### {$sp['title']}:\n{$sp['content_markdown']}\n";
            }
        }

        $systemInstruction = $skill['system_prompt'] . "\n\nTONE OF VOICE:\n" . ($skill['tone_of_voice'] ?: 'Dark Tech, лаконичный') . "\n\n" . $knowledgeContext . $subPromptsInstruction;

        if (in_array($fmtCode, ['reels_hook', 'reels', 'instagram'], true)) {
            // ШАГ 1: Сценарий, 5-6 сцен раскадровки и HTML5 Canvas код
            $userPrompt = "ТЕМА РОЛИКА: {$topic}\n\n" .
                "ЗАДАЧА: Сгенерируй профессиональный пакет для вертикального видео (Reels / Shorts, 9:16) строго по 6-фазной дуге удержания (до 30 сек):\n" .
                "1. СЦЕНАРИЙ (ровно 60-90 слов, от лица Senior-разработчика Евгения Антипина).\n" .
                "2. РАСКАДРОВКА ИЗ 5-6 СЦЕН (Сцена 1: Hook, Сцена 2: Problem, Сцена 3: Reveal, Сцена 4: Escalation, Сцена 5: Payoff, Сцена 6: CTA) с [Субтитры], [Действие], [Retention], [Image Prompt (9:16)], [Video Prompt (9:16)].\n" .
                "3. САМОДОСТАТОЧНЫЙ HTML5 CANVAS MOTION-КОД РОЛИКА: Оберни в блок ```html <!DOCTYPE html>...</html> ```. ОБЯЗАТЕЛЬНО холст <canvas id=\"reel-canvas\" width=\"1080\" height=\"1920\"></canvas> и скрипт с window.startReelAnimation() и window.REEL_DURATION_MS (до 30000). Без внешних CDN. Без длинных тире (-).";

            $generatedText = $aiRouter->execute(AIRouter::TASK_REELS, $systemInstruction, $userPrompt);
            $generatedText = str_replace(['—', '–'], '-', trim($generatedText));

            $motionHtml = '';
            if (preg_match('/```html\s*(.*?)\s*```/is', $generatedText, $mHtml)) {
                $motionHtml = trim($mHtml[1]);
            } elseif (preg_match('/<!DOCTYPE html>.*?<\/html>/is', $generatedText, $mDoc)) {
                $motionHtml = trim($mDoc[0]);
            }

            // ШАГ 2: Режиссер закадрового голоса (voiceover_director)
            $stmtVo = $pdo->prepare("SELECT system_prompt, tone_of_voice FROM skills WHERE code = 'voiceover_director' LIMIT 1");
            $stmtVo->execute();
            $voSkill = $stmtVo->fetch();
            $voSysPrompt = ($voSkill && !empty($voSkill['system_prompt']))
                ? $voSkill['system_prompt']
                : 'Ты - звукорежиссер коротких видео. Адаптируй текст под хронометраж до 30 секунд. Верни строго JSON: {"voice_script": "...", "voice_prompt": "...", "estimated_seconds": 28}.';

            $voUserPrompt = "ТЕМА РОЛИКА: {$topic}\nСЦЕНАРИЙ И ТАЙМИНГИ:\n" . substr($generatedText, 0, 3000) . "\n\n"
                . "Адаптируй дикторский текст под точный тайминг до 30 секунд и верни СТРОГО JSON: {\"voice_script\": \"...\", \"voice_prompt\": \"...\", \"estimated_seconds\": 28}. Запрещены длинные тире (-).";

            $step2Output = $aiRouter->execute(AIRouter::TASK_REELS, $voSysPrompt, $voUserPrompt);
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim((string)$step2Output));
            $cleanJson = preg_replace('/\s*```$/i', '', $cleanJson);
            $voData = json_decode(trim($cleanJson), true);

            $voiceScript = is_array($voData) && !empty($voData['voice_script'])
                ? trim((string)$voData['voice_script'])
                : extractFallbackVoiceScript($generatedText);

            $voicePrompt = is_array($voData) && !empty($voData['voice_prompt'])
                ? trim((string)$voData['voice_prompt'])
                : 'Уверенный тембр Senior-разработчика (Евгений Антипин). Без дикторского пафоса, четкие паузы под монтаж, акцент на цифрах скорости (0.35с, 100/100).';

            $estimatedSeconds = is_array($voData) && !empty($voData['estimated_seconds'])
                ? (int)$voData['estimated_seconds']
                : 28;

            $scenes = parseStoryboardScenes($generatedText);

            if ($motionHtml === '' || stripos($motionHtml, 'reel-canvas') === false || stripos($motionHtml, 'startReelAnimation') === false) {
                $motionHtml = generateDefaultReelsCanvasHtml($topic, $scenes, $estimatedSeconds);
            }

            $storyboardData = [
                'topic'             => $topic,
                'format'            => $fmtCode,
                'scenario'          => $generatedText,
                'scenes'            => $scenes,
                'voice_script'      => $voiceScript,
                'voice_prompt'      => $voicePrompt,
                'estimated_seconds' => $estimatedSeconds,
                'motion_html'       => $motionHtml,
                'audio_url'         => null
            ];

            if ($contentId > 0) {
                $stmtSave = $pdo->prepare("UPDATE content_items SET topic = :topic, format = :format, content_markdown = :c, motion_html = :m, storyboard_json = :sb WHERE id = :id");
                $stmtSave->execute([
                    ':topic'  => $topic,
                    ':format' => $fmtCode,
                    ':c'      => $generatedText,
                    ':m'      => $motionHtml,
                    ':sb'     => json_encode($storyboardData, JSON_UNESCAPED_UNICODE),
                    ':id'     => $contentId
                ]);
            } else {
                $stmtSave = $pdo->prepare("INSERT INTO content_items (topic, format, content_markdown, motion_html, storyboard_json, status) VALUES (:topic, :format, :c, :m, :sb, 'draft')");
                $stmtSave->execute([
                    ':topic'  => $topic,
                    ':format' => $fmtCode,
                    ':c'      => $generatedText,
                    ':m'      => $motionHtml,
                    ':sb'     => json_encode($storyboardData, JSON_UNESCAPED_UNICODE)
                ]);
                $contentId = (int)$pdo->lastInsertId();
            }

            $stmtGet = $pdo->prepare("SELECT * FROM content_items WHERE id = :id");
            $stmtGet->execute([':id' => $contentId]);
            $savedDraft = $stmtGet->fetch(PDO::FETCH_ASSOC);
            $savedDraft['storyboard'] = $storyboardData;

            echo json_encode([
                'status'     => 'success',
                'id'         => $contentId,
                'draft'      => $savedDraft,
                'storyboard' => $storyboardData,
                'message'    => 'Ролик успешно сгенерирован (Шаг 1: Canvas Motion + Шаг 2: Voiceover)'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        } else {
            // Обычный текстовый формат
            $userPrompt = "ТЕМА МАТЕРИАЛА: {$topic}\n\nСгенерируй полноценный материал для «{$skill['title']}» (код: {$fmtCode}). Без длинных тире (-).";
            $generatedText = $aiRouter->execute(AIRouter::TASK_LONGREAD, $systemInstruction, $userPrompt);
            $generatedText = str_replace(['—', '–'], '-', trim($generatedText));

            if ($contentId > 0) {
                $stmtSave = $pdo->prepare("UPDATE content_items SET topic = :topic, format = :format, content_markdown = :c WHERE id = :id");
                $stmtSave->execute([':topic' => $topic, ':format' => $fmtCode, ':c' => $generatedText, ':id' => $contentId]);
            } else {
                $stmtSave = $pdo->prepare("INSERT INTO content_items (topic, format, content_markdown, status) VALUES (:topic, :format, :c, 'draft')");
                $stmtSave->execute([':topic' => $topic, ':format' => $fmtCode, ':c' => $generatedText]);
                $contentId = (int)$pdo->lastInsertId();
            }

            $stmtGet = $pdo->prepare("SELECT * FROM content_items WHERE id = :id");
            $stmtGet->execute([':id' => $contentId]);
            $savedDraft = $stmtGet->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status'  => 'success',
                'id'      => $contentId,
                'draft'   => $savedDraft,
                'message' => 'Материал успешно сгенерирован'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // 4.7. ГЕНЕРАЦИЯ B-ROLL МЕДИА ДЛЯ СЦЕНЫ (action=generate_scene_media)
    // -------------------------------------------------------------------------
    if ($action === 'generate_scene_media') {
        $contentId = (int)($input['content_id'] ?? 0);
        $sceneNum = (int)($input['scene_num'] ?? 1);
        $prompt = trim((string)($input['prompt'] ?? ''));
        $mediaType = trim((string)($input['media_type'] ?? 'image'));
        $altModel = !empty($input['alt_model']);

        if ($prompt === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Промпт для генерации медиа пуст'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $polzaKey = trim((string)($config['polza_api_key'] ?? $config['gemini_heavy_api_key'] ?? ''));

        if ($polzaKey === '') {
            echo json_encode(['status' => 'error', 'message' => 'polza_api_key не настроен в config.local.php'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($mediaType === 'video') {
            $model = trim((string)($config['polza_video_model'] ?? 'bytedance/seedance-2-mini')) ?: 'bytedance/seedance-2-mini';
            $payload = [
                'model'    => $model,
                'prompt'   => $prompt,
                'size'     => '1080x1920',
                'provider' => ['only' => ['mie']]
            ];
        } else {
            if ($altModel) {
                $model = trim((string)($config['polza_image_alt_model'] ?? 'bytedance/seedream-4')) ?: 'bytedance/seedream-4';
                $payload = [
                    'model'    => $model,
                    'prompt'   => $prompt,
                    'size'     => '1080x1920',
                    'provider' => ['only' => ['mie']]
                ];
            } else {
                $model = trim((string)($config['polza_image_model'] ?? 'google/gemini-nano-banana-2.1')) ?: 'google/gemini-nano-banana-2.1';
                $payload = [
                    'model'    => $model,
                    'prompt'   => $prompt,
                    'size'     => '1080x1920',
                    'provider' => [
                        'order'           => ['mie', 'img-a', 'bananalab'],
                        'allow_fallbacks' => false
                    ]
                ];
            }
        }

        $ch = curl_init('https://polza.ai/api/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $polzaKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $responseRaw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $respData = json_decode((string)$responseRaw, true);
        $mediaUrl = null;
        if ($httpCode === 200 && is_array($respData) && !empty($respData['data'][0])) {
            $first = $respData['data'][0];
            $ext = ($mediaType === 'video') ? 'mp4' : 'webp';
            $uploadsDir = dirname(__DIR__, 2) . '/core-data/uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $fileName = "scene_{$contentId}_{$sceneNum}_" . time() . ".{$ext}";
            $filePath = $uploadsDir . '/' . $fileName;

            if (!empty($first['b64_json'])) {
                file_put_contents($filePath, base64_decode($first['b64_json']));
                $mediaUrl = '../core-data/uploads/' . $fileName;
            } elseif (!empty($first['url'])) {
                $remoteMedia = @file_get_contents($first['url']);
                if ($remoteMedia !== false && strlen($remoteMedia) > 500) {
                    file_put_contents($filePath, $remoteMedia);
                    $mediaUrl = '../core-data/uploads/' . $fileName;
                } else {
                    $mediaUrl = $first['url'];
                }
            }
        }

        if ($mediaUrl !== null && $contentId > 0) {
            $stmtSb = $pdo->prepare("SELECT storyboard_json FROM content_items WHERE id = :id");
            $stmtSb->execute([':id' => $contentId]);
            $sbRaw = $stmtSb->fetchColumn();
            if ($sbRaw) {
                $sb = json_decode((string)$sbRaw, true);
                if (isset($sb['scenes']) && is_array($sb['scenes'])) {
                    foreach ($sb['scenes'] as &$sc) {
                        if ((int)($sc['scene_num'] ?? 0) === $sceneNum) {
                            $sc['media_url'] = $mediaUrl;
                            $sc['media_type'] = $mediaType;
                            break;
                        }
                    }
                    unset($sc);
                    $pdo->prepare("UPDATE content_items SET storyboard_json = :sb WHERE id = :id")
                        ->execute([':sb' => json_encode($sb, JSON_UNESCAPED_UNICODE), ':id' => $contentId]);
                }
            }
        }

        if ($mediaUrl !== null) {
            echo json_encode([
                'status'    => 'success',
                'scene_num' => $sceneNum,
                'media_url' => $mediaUrl,
                'model'     => $model,
                'message'   => 'B-roll медиа успешно сгенерировано'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        http_response_code(502);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Не удалось получить медиа от Polza.ai (HTTP ' . $httpCode . ')',
            'details' => $respData
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.8. AI-ЧАТ ДОРАБОТКИ РОЛИКА (action=chat_refine)
    // -------------------------------------------------------------------------
    if ($action === 'chat_refine') {
        $contentId = (int)($input['content_id'] ?? 0);
        $message = trim((string)($input['message'] ?? ''));

        if ($contentId <= 0 || $message === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан content_id или пустое сообщение'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtD = $pdo->prepare("SELECT * FROM content_items WHERE id = :id");
        $stmtD->execute([':id' => $contentId]);
        $draft = $stmtD->fetch(PDO::FETCH_ASSOC);
        if (!$draft) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Черновик не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $storyboard = !empty($draft['storyboard_json']) ? json_decode((string)$draft['storyboard_json'], true) : [];
        $chatHistory = !empty($draft['chat_history']) ? json_decode((string)$draft['chat_history'], true) : [];

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $aiRouter = new AIRouter($config);
        $aiRouter->setPDO($pdo);

        $sysPrompt = "Ты - Senior моушн-дизайнер и редактор AntiDesigner. Пользователь дает правку к вертикальному видео (Reels / Shorts). "
            . "Твоя задача: применить правку и вернуть СТРОГО JSON: {\"reply\": \"что изменено\", \"voice_script\": \"...\", \"motion_html\": \"... (если менялся)\"}. Без длинных тире (-).";

        $userPrompt = "ТЕКУЩИЙ СЦЕНАРИЙ И ОЗВУЧКА:\n" . ($storyboard['voice_script'] ?? $draft['content_markdown']) . "\n\n"
            . "ПРАВКА ПОЛЬЗОВАТЕЛЯ:\n{$message}\n\n"
            . "Примени изменения и верни СТРОГО JSON.";

        $aiRes = $aiRouter->execute(AIRouter::TASK_REELS, $sysPrompt, $userPrompt);
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim((string)$aiRes));
        $cleanJson = preg_replace('/\s*```$/i', '', $cleanJson);
        $resData = json_decode($cleanJson, true);

        $reply = is_array($resData) && !empty($resData['reply']) ? (string)$resData['reply'] : 'Правки успешно учтены и сохранены в драфт.';
        $newVoiceScript = is_array($resData) && !empty($resData['voice_script']) ? trim((string)$resData['voice_script']) : null;
        $newMotionHtml = is_array($resData) && !empty($resData['motion_html']) ? trim((string)$resData['motion_html']) : null;

        if ($newVoiceScript !== null) {
            $storyboard['voice_script'] = $newVoiceScript;
        }
        if ($newMotionHtml !== null && stripos($newMotionHtml, 'reel-canvas') !== false) {
            $draft['motion_html'] = $newMotionHtml;
            $storyboard['motion_html'] = $newMotionHtml;
        }

        $chatHistory[] = ['role' => 'user', 'text' => $message, 'time' => date('H:i')];
        $chatHistory[] = ['role' => 'assistant', 'text' => $reply, 'time' => date('H:i')];

        $stmtUpd = $pdo->prepare("UPDATE content_items SET storyboard_json = :sb, motion_html = :mh, chat_history = :ch WHERE id = :id");
        $stmtUpd->execute([
            ':sb' => json_encode($storyboard, JSON_UNESCAPED_UNICODE),
            ':mh' => $draft['motion_html'],
            ':ch' => json_encode($chatHistory, JSON_UNESCAPED_UNICODE),
            ':id' => $contentId
        ]);

        $draft['storyboard'] = $storyboard;
        $draft['chat_history_list'] = $chatHistory;

        echo json_encode([
            'status'     => 'success',
            'reply'      => $reply,
            'draft'      => $draft,
            'storyboard' => $storyboard
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4.9. ОТПРАВКА ЧЕРНОВИКА РОЛИКА В TELEGRAM-БОТ (action=send_to_telegram_bot)
    // -------------------------------------------------------------------------
    if ($action === 'send_to_telegram_bot') {
        $contentId = (int)($input['content_id'] ?? 0);
        if ($contentId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Не указан content_id'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtD = $pdo->prepare("SELECT * FROM content_items WHERE id = :id");
        $stmtD->execute([':id' => $contentId]);
        $draft = $stmtD->fetch(PDO::FETCH_ASSOC);
        if (!$draft) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Черновик не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $botToken = trim((string)($config['telegram_bot_token'] ?? ''));
        $adminId = trim((string)($config['admin_telegram_id'] ?? ''));

        if ($botToken === '' || $adminId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'telegram_bot_token или admin_telegram_id не настроены в config.local.php'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sb = !empty($draft['storyboard_json']) ? json_decode((string)$draft['storyboard_json'], true) : [];
        $voiceScript = $sb['voice_script'] ?? extractFallbackVoiceScript((string)$draft['content_markdown']);
        $estSec = $sb['estimated_seconds'] ?? 28;

        $tgText = "🎬 *AntiDesigner Reels Studio // Черновик ролика*\n\n"
            . "📌 *Тема:* " . ($draft['topic'] ?? 'Без темы') . "\n"
            . "⏱ *Хронометраж:* до {$estSec} сек\n\n"
            . "🎙 *Дикторский текст:*\n" . $voiceScript . "\n\n"
            . "⚡ Открыть и экспортировать ролик можно в админ-панели AntiDesigner Core.";

        // Отправка текстового сообщения
        $sendUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $ch = curl_init($sendUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'chat_id'    => $adminId,
                'text'       => $tgText,
                'parse_mode' => 'Markdown'
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        curl_exec($ch);
        curl_close($ch);

        // Если есть локальный аудиофайл, отправляем его через sendAudio
        if (!empty($draft['audio_url'])) {
            $localAudioPath = dirname(__DIR__, 2) . '/' . ltrim(str_replace('../', '', $draft['audio_url']), '/');
            if (file_exists($localAudioPath)) {
                $chAudio = curl_init("https://api.telegram.org/bot{$botToken}/sendAudio");
                $cfile = new CURLFile($localAudioPath, 'audio/mpeg', basename($localAudioPath));
                curl_setopt_array($chAudio, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => [
                        'chat_id' => $adminId,
                        'audio'   => $cfile,
                        'caption' => '🎙 Озвучка ролика: ' . ($draft['topic'] ?? '')
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                curl_exec($chAudio);
                curl_close($chAudio);
            }
        }

        echo json_encode([
            'status'  => 'success',
            'message' => 'Черновик ролика успешно отправлен в Telegram администратору'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 5. ИНТЕРАКТИВНЫЙ КОНТЕНТ-ПЛАН (action=calendar_list)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_list') {
        // Проверяем наличие запланированных записей
        $count = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE scheduled_for IS NOT NULL")->fetchColumn();
        
        // Автоматический сид 30 дней СТРОГО один раз, если сетка абсолютно пуста
        if ($count === 0) {
            $planSeed = [
                ['format' => 'tg_post',     'status' => 'published', 'topic' => 'Почему конструкторы Tilda и Elementor режут конверсию на смартфонах: замер TTFB и LCP'],
                ['format' => 'reels_hook',   'status' => 'published', 'topic' => 'Тест скорости: Tilda 4.8 секунды против Чистого кода 0.35 секунды в сети 4G'],
                ['format' => 'vc_ru',        'status' => 'ready',     'topic' => 'Кейс ускорения клиники: как переход на чистый код поднял PageSpeed до 100/100 и дал +38% заявок'],
                ['format' => 'lead_magnet',  'status' => 'ready',     'topic' => 'Чек-лист: 10 скрытых признаков того, что ваш сайт на конструкторе сливает бюджет на рекламу'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Что внутри Tilda: почему одна простая страница весит 12 мегабайт и тянет 40 лишних js-библиотек'],
                ['format' => 'site_geo',     'status' => 'planned',   'topic' => 'Разработка сайта на чистом коде в Москве: реальные сроки, окупаемость и гарантия скорости'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => 'Клиент платил 180 000 руб в месяц за Яндекс Директ и не понимал, почему клиенты закрывают сайт'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Считаем математику владения: абонентская плата конструктора за 3 года против сайта в вечной собственности'],
                ['format' => 'vc_ru',        'status' => 'planned',   'topic' => 'Анатомия конверсионного первого экрана: почему слайдеры и сток-фото убивают продажи в B2B'],
                ['format' => 'lead_magnet',  'status' => 'planned',   'topic' => 'Гайд: Технический аудит скорости сайта своими руками за 5 минут'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Google Core Web Vitals в 2026 году: как поисковики штрафуют медленные сайты в ранжировании'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => '3 грубейшие ошибки в дизайне сайтов услуг, из-за которых посетитель уходит через 3 секунды'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Чистый код против шаблонов: почему мои сайты загружаются мгновенно даже в подземном паркинге'],
                ['format' => 'site_geo',     'status' => 'planned',   'topic' => 'Почему SEO-продвижение сайтов на Tilda обходится бизнесу дороже, чем на чистом HTML и CSS'],
                ['format' => 'vc_ru',        'status' => 'planned',   'topic' => 'Разбор 5 сайтов из ниши элитного ремонта: как тяжелая анимация превратила дизайн в пытку для клиентов'],
                ['format' => 'lead_magnet',  'status' => 'planned',   'topic' => 'Бриф на разработку продающего сайта: вопросы, которые экономят бизнесу до 200 000 рублей'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Мой личный рекорд: сайт с оценкой 100 из 100 на мобильных устройствах без потери качества'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => 'Как я переверстал лендинг на чистом коде за 48 часов и снизил цену лида с 2400 до 980 рублей'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Типографика, которая продает: 4 правила чистого шрифтового ритма для посадочных страниц'],
                ['format' => 'vc_ru',        'status' => 'planned',   'topic' => 'Почему бизнесу больше не нужны сайты на WordPress: уязвимости плагинов и тормоза базы данных'],
                ['format' => 'lead_magnet',  'status' => 'planned',   'topic' => 'Карта триггеров доверия: 15 элементов на сайте, которые закрывают возражения без звонка менеджеру'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Сколько весит идеальный сайт: почему лендинг AntiDesigner укладывается всего в 250 килобайт'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => 'Что видит клиент, когда заходит на сайт с телефона в лифте: сравнение конструктора и чистого кода'],
                ['format' => 'site_geo',     'status' => 'planned',   'topic' => 'Как подготовить сайт к запуску контекстной рекламы: технический чеклист готовности'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Микро-анимации на чистом CSS: делаем интерфейс премиальным без единой тяжелой JS-библиотеки'],
                ['format' => 'vc_ru',        'status' => 'planned',   'topic' => 'От идеи до первого лида за 3 дня: регламент быстрой разработки сайтов с гарантией скорости'],
                ['format' => 'lead_magnet',  'status' => 'planned',   'topic' => 'Аудит мобильной адаптивности: 12 неочевидных косяков верстки, отталкивающих покупателей'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Отзывы клиентов на сайте: как оформить блок доверия, чтобы ему реально верили, а не считали фейком'],
                ['format' => 'reels_hook',   'status' => 'planned',   'topic' => 'Главный секрет высокой конверсии: когда сайт отвечает на вопрос клиента быстрее, чем он успел моргнуть'],
                ['format' => 'tg_post',     'status' => 'planned',   'topic' => 'Итоги месяца и отчет по метрикам: сколько секунд и рублей я сэкономил своим клиентам']
            ];

            $baseTimestamp = strtotime('today');
            $stmtInsert = $pdo->prepare("
                INSERT INTO content_items (topic, format, status, scheduled_for, created_at)
                VALUES (:topic, :format, :status, :scheduled_for, CURRENT_TIMESTAMP)
            ");

            foreach ($planSeed as $idx => $seed) {
                $itemDate = date('Y-m-d', $baseTimestamp + ($idx * 86400));
                // Проверяем, нет ли уже такой темы
                $checkExists = $pdo->prepare("SELECT id FROM content_items WHERE topic = :topic LIMIT 1");
                $checkExists->execute([':topic' => $seed['topic']]);
                if (!$checkExists->fetchColumn()) {
                    $stmtInsert->execute([
                        ':topic'         => $seed['topic'],
                        ':format'        => $seed['format'],
                        ':status'       => $seed['status'],
                        ':scheduled_for' => $itemDate
                    ]);
                }
            }
        }

        $stmt = $pdo->query("
            SELECT id, topic, format, status, scheduled_for, views_count, likes_count, published_url, created_at,
                   CASE WHEN content_markdown IS NOT NULL AND LENGTH(content_markdown) > 0 THEN 1 ELSE 0 END as has_content
            FROM content_items 
            WHERE scheduled_for IS NOT NULL
            ORDER BY scheduled_for ASC, id ASC
            LIMIT 60
        ");
        $items = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'count'  => count($items),
            'items'  => $items
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 6. ОБНОВЛЕНИЕ ЭЛЕМЕНТА СЕТКИ (action=calendar_update)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_update') {
        $id = (int)($input['id'] ?? 0);
        $topic = trim((string)($input['topic'] ?? ''));
        $status = trim((string)($input['status'] ?? 'planned'));
        $scheduledFor = trim((string)($input['scheduled_for'] ?? ''));
        $format = trim((string)($input['format'] ?? 'tg_post'));

        if ($id <= 0 || $topic === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный ID или пустая тема'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $allowedStatuses = ['planned', 'ready', 'published', 'archived'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'planned';
        }

        $stmt = $pdo->prepare("
            UPDATE content_items 
            SET topic = :topic, status = :status, scheduled_for = :scheduled_for, format = :format
            WHERE id = :id
        ");
        $stmt->execute([
            ':topic'         => $topic,
            ':status'        => $status,
            ':scheduled_for' => $scheduledFor ?: date('Y-m-d'),
            ':format'        => $format,
            ':id'            => $id
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Публикация успешно обновлена'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 7. СОЗДАНИЕ НОВОЙ ПУБЛИКАЦИИ В СЕТКЕ (action=calendar_create)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_create') {
        $topic = trim((string)($input['topic'] ?? ''));
        $format = trim((string)($input['format'] ?? 'tg_post'));
        $scheduledFor = trim((string)($input['scheduled_for'] ?? date('Y-m-d')));
        $status = trim((string)($input['status'] ?? 'planned'));

        if ($topic === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Укажите тему публикации'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO content_items (topic, format, status, scheduled_for, created_at)
            VALUES (:topic, :format, :status, :scheduled_for, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':topic'         => $topic,
            ':format'        => $format,
            ':status'        => $status,
            ':scheduled_for' => $scheduledFor
        ]);

        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'status'  => 'success',
            'message' => 'Публикация добавлена в контент-план',
            'id'      => $newId
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 8. УДАЛЕНИЕ ПУБЛИКАЦИИ ИЗ СЕТКИ (action=calendar_delete)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Некорректный ID'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM content_items WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Публикация удалена из контент-плана'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // 9. AI-ГЕНЕРАЦИЯ КОНТЕНТ-ПЛАНА НА 30 ДНЕЙ (action=calendar_generate_ai)
    // -------------------------------------------------------------------------
    if ($action === 'calendar_generate_ai') {
        // Подтягиваем контекст из базы знаний
        $stmtKb = $pdo->query('SELECT category, title, content_markdown FROM knowledge_base ORDER BY id ASC');
        $kbRows = $stmtKb->fetchAll();
        $ragContext = '';
        foreach ($kbRows as $kb) {
            $ragContext .= "### {$kb['category']} - {$kb['title']}:\n{$kb['content_markdown']}\n\n";
        }

        $systemPrompt = "Ты - персональный AI-стратег и контент-редактор независимого разработчика Евгения Антипина (AntiDesigner, разработка сайтов на чистом коде с ультравысокой скоростью 100/100 в PageSpeed против медленных конструкторов Tilda/WordPress).\n"
            . "ПОЗИЦИОНИРОВАНИЕ: AntiDesigner - это ЛИЧНЫЙ БРЕНД Senior-разработчика Евгения Антипина, а не агентство и не студия! Категорически запрещены слова 'мы', 'наша команда', 'наша студия', 'наши специалисты'. Темы формулируются строго от 1-го лица ('Я', 'мой опыт', 'как я верстаю', 'в моих проектах') либо как объективные экспертные разборы с точки зрения автора кода.\n"
            . "Твоя задача - составить 30-дневный контент-план публикаций.\n"
            . "Каждый день должен иметь 1 тему, строго привязанную к болям клиентов (скорость загрузки, потеря заявок, экономия на рекламе, мобильная конверсия, владение кодом, работа напрямую с разработчиком без наценок агентств).\n"
            . "Форматы для чередования: tg_post, vc_ru, reels_hook, lead_magnet, site_geo.\n"
            . "ПРАВИЛО: Категорически ЗАПРЕЩЕНО использовать длинное тире (—) в текстах тем. Используй двоеточия, дефисы или скобки.\n"
            . "Верни ответ СТРОГО в виде валидного JSON массива объектов:\n"
            . '[{"day_offset": 0, "format": "tg_post", "topic": "..."}, {"day_offset": 1, "format": "reels_hook", "topic": "..."}]';

        $userPrompt = "Сформируй контент-план на 30 дней с учетом базы знаний независимого разработчика Евгения Антипина:\n\n{$ragContext}\nСгенерируй ровно 30 целевых тем в JSON.";

        $configFile = dirname(__DIR__, 2) . '/core-data/config.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $aiRouter = new AIRouter($config);
        $aiRouter->setPDO($pdo);
        $aiResponse = $aiRouter->call($userPrompt, $systemPrompt, AIRouter::MODEL_FAST);
        
        // Очищаем json
        $jsonStr = $aiResponse;
        if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\])\s*```/', $aiResponse, $matches)) {
            $jsonStr = $matches[1];
        } else {
            $start = strpos($aiResponse, '[');
            $end = strrpos($aiResponse, ']');
            if ($start !== false && $end !== false && $end > $start) {
                $jsonStr = substr($aiResponse, $start, $end - $start + 1);
            }
        }

        $items = json_decode($jsonStr, true);
        if (!is_array($items) || empty($items)) {
            throw new RuntimeException("Не удалось распарсить JSON план от нейросети: " . substr($aiResponse, 0, 300));
        }

        $baseTimestamp = strtotime('today');
        $stmtInsert = $pdo->prepare("
            INSERT INTO content_items (topic, format, status, scheduled_for, created_at)
            VALUES (:topic, :format, 'planned', :scheduled_for, CURRENT_TIMESTAMP)
        ");

        $insertedCount = 0;
        foreach ($items as $idx => $it) {
            $topic = trim((string)($it['topic'] ?? ''));
            $topic = str_replace(['—', '–'], '-', $topic);
            $fmt = trim((string)($it['format'] ?? 'tg_post'));
            $dayOffset = isset($it['day_offset']) ? (int)$it['day_offset'] : $idx;
            $itemDate = date('Y-m-d', $baseTimestamp + ($dayOffset * 86400));

            if ($topic !== '') {
                $stmtInsert->execute([
                    ':topic'         => $topic,
                    ':format'        => in_array($fmt, ['tg_post', 'vc_ru', 'reels_hook', 'lead_magnet', 'site_geo'], true) ? $fmt : 'tg_post',
                    ':scheduled_for' => $itemDate
                ]);
                $insertedCount++;
            }
        }

        echo json_encode([
            'status'  => 'success',
            'message' => "Сгенерировано и сохранено {$insertedCount} тем в контент-план",
            'count'   => $insertedCount
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Неизвестное действие: {$action}"], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

