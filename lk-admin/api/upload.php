<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - SECURE FILE UPLOAD HANDLER
 * Handles document parsing (.pdf, .docx, .md, .txt, scripts) for RAG & Skills
 * =============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
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
    /** @var PDO $pdo */

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Файл не был передан'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Ошибка загрузки файла на сервер (Код: ' . $file['error'] . ')'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Лимит размера: 10 МБ
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Размер файла превышает лимит 10 МБ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $origName = (string)($file['name'] ?? 'document.txt');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $allowedExts = ['pdf', 'docx', 'md', 'txt', 'json', 'py', 'js', 'html', 'css', 'sql'];
    if (!in_array($ext, $allowedExts, true)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Неподдерживаемый формат: .{$ext}. Разрешены: " . implode(', ', $allowedExts)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Изолированная директория uploads
    $uploadsDir = dirname(__DIR__, 2) . '/core-data/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Защита директории через .htaccess
    $htaccessFile = $uploadsDir . '/.htaccess';
    if (!file_exists($htaccessFile)) {
        file_put_contents($htaccessFile, "Require all denied\n");
    }

    $cleanBaseName = preg_replace('/[^a-zA-Z0-9_\.-]/u', '_', pathinfo($origName, PATHINFO_FILENAME));
    $storedFileName = uniqid('doc_', true) . '_' . $cleanBaseName . '.' . $ext;
    $targetPath = $uploadsDir . '/' . $storedFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Не удалось сохранить файл в директорию загрузок');
    }

    // -------------------------------------------------------------------------
    // ИЗВЛЕЧЕНИЕ ТЕКСТА ИЗ ФАЙЛА
    // -------------------------------------------------------------------------
    $extractedText = '';

    if (in_array($ext, ['txt', 'md', 'json', 'py', 'js', 'html', 'css', 'sql'], true)) {
        $extractedText = (string)file_get_contents($targetPath);
    } elseif ($ext === 'docx') {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($targetPath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if (is_string($xml)) {
                    $xml = preg_replace('/<\/w:p>/', "\n", $xml);
                    $xml = preg_replace('/<w:br\/>/', "\n", $xml);
                    $extractedText = trim(strip_tags((string)$xml));
                }
            }
        }
        if ($extractedText === '') {
            $extractedText = "[Документ DOCX: {$origName}. Не удалось извлечь структуру XML]";
        }
    } elseif ($ext === 'pdf') {
        $rawPdf = (string)file_get_contents($targetPath);
        // Извлечение текстовых блоков PDF
        preg_match_all('/\((.*?)\)\s*Tj/s', $rawPdf, $matches);
        if (!empty($matches[1])) {
            $extractedText = trim(implode(' ', $matches[1]));
        }
        if ($extractedText === '') {
            // Извлечение текстовых последовательностей
            preg_match_all('/\[(.*?)\]\s*TJ/s', $rawPdf, $tjMatches);
            if (!empty($tjMatches[1])) {
                $extractedText = trim(strip_tags(implode(' ', $tjMatches[1])));
            }
        }
        if ($extractedText === '') {
            $extractedText = "[Файл PDF: {$origName}. Текстовый слой сохранен в архив uploads/{$storedFileName}]";
        }
    }

    // Санитайзинг длинных тире
    $extractedText = str_replace(['—', '–'], '-', $extractedText);

    $target = trim((string)($_POST['target'] ?? 'knowledge'));

    // -------------------------------------------------------------------------
    // 1. ЦЕЛЕВОЙ МОДУЛЬ: БАЗА ЗНАНИЙ (target=knowledge)
    // -------------------------------------------------------------------------
    if ($target === 'knowledge') {
        $category = trim((string)($_POST['category'] ?? 'content_rules'));

        $stmt = $pdo->prepare('SELECT id, title, content_markdown FROM knowledge_base WHERE category = :cat LIMIT 1');
        $stmt->execute([':cat' => $category]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $annotation = "\n\n---\n### [Файл: {$origName}]\n" . trim($extractedText) . "\n";

        if ($row) {
            $updatedMarkdown = $row['content_markdown'] . $annotation;
            $stmtUpd = $pdo->prepare('UPDATE knowledge_base SET content_markdown = :content, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmtUpd->execute([
                ':content' => $updatedMarkdown,
                ':id'      => $row['id']
            ]);
            $newContent = $updatedMarkdown;
        } else {
            $stmtIns = $pdo->prepare('INSERT INTO knowledge_base (category, title, content_markdown) VALUES (:cat, :title, :content)');
            $stmtIns->execute([
                ':cat'     => $category,
                ':title'   => strtoupper($category),
                ':content' => $annotation
            ]);
            $newContent = $annotation;
        }

        echo json_encode([
            'status'         => 'success',
            'message'        => 'Файл успешно загружен и обработан',
            'filename'       => $origName,
            'category'       => $category,
            'extracted_text' => $extractedText,
            'new_content'    => $newContent
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. ЦЕЛЕВОЙ МОДУЛЬ: СКИЛЛЫ (target=skill)
    // -------------------------------------------------------------------------
    if ($target === 'skill') {
        $skillId = isset($_POST['skill_id']) ? (int)$_POST['skill_id'] : 0;
        $skillCode = isset($_POST['skill_code']) ? trim((string)$_POST['skill_code']) : '';

        if ($skillId > 0) {
            $stmtUpd = $pdo->prepare('UPDATE skills SET system_prompt = :prompt, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmtUpd->execute([':prompt' => $extractedText, ':id' => $skillId]);
        } elseif ($skillCode !== '') {
            $stmtUpd = $pdo->prepare('UPDATE skills SET system_prompt = :prompt, updated_at = CURRENT_TIMESTAMP WHERE code = :code');
            $stmtUpd->execute([':prompt' => $extractedText, ':code' => $skillCode]);
        }

        echo json_encode([
            'status'         => 'success',
            'message'        => 'Файл успешно загружен и обработан',
            'filename'       => $origName,
            'prompt_text'    => $extractedText,
            'skill_id'       => $skillId
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    echo json_encode([
        'status'         => 'success',
        'message'        => 'Файл успешно загружен и обработан',
        'filename'       => $origName,
        'extracted_text' => $extractedText
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Ошибка загрузки: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
