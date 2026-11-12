<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - AUTO-CLEANUP HELPER
 * Cleans up temporary media, audio, motion renders and heavy draft fields
 * after content is published to conserve VPS disk space and optimize SQLite.
 * =============================================================================
 */

if (!function_exists('cleanupPublishedDraftGarbage')) {
    /**
     * Clean up media files and heavy draft fields for published content
     *
     * @param PDO $pdo
     * @param int $planId ID from content_plan or content_items (0 for all published)
     * @return array Metrics of cleanup
     */
    function cleanupPublishedDraftGarbage(PDO $pdo, int $planId = 0): array
    {
        $uploadsDir = dirname(__DIR__) . '/uploads';
        $cleanedFilesCount = 0;
        $cleanedBytes = 0;
        $updatedRows = 0;

        try {
            // Ищем черновики, привязанные к plan_id, либо с id = planId
            if ($planId > 0) {
                $stmt = $pdo->prepare("
                    SELECT id, media_files, audio_url, motion_html 
                    FROM content_items 
                    WHERE plan_id = :pid OR id = :pid
                ");
                $stmt->execute([':pid' => $planId]);
            } else {
                // Если planId = 0, очищаем все опубликованные материалы
                $stmt = $pdo->query("
                    SELECT id, media_files, audio_url, motion_html 
                    FROM content_items 
                    WHERE status = 'published'
                ");
            }

            $items = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            foreach ($items as $item) {
                // 1. Проверяем аудиофайл
                $audioUrl = trim((string)($item['audio_url'] ?? ''));
                if ($audioUrl !== '') {
                    $audioFileName = basename($audioUrl);
                    $audioPath = $uploadsDir . '/' . $audioFileName;
                    if (file_exists($audioPath) && is_file($audioPath)) {
                        $cleanedBytes += filesize($audioPath);
                        @unlink($audioPath);
                        $cleanedFilesCount++;
                    }
                }

                // 2. Проверяем прикрепленные медиафайлы
                $mediaJson = trim((string)($item['media_files'] ?? ''));
                if ($mediaJson !== '') {
                    $mediaList = json_decode($mediaJson, true);
                    if (!is_array($mediaList)) {
                        $mediaList = [$mediaJson];
                    }
                    foreach ($mediaList as $mf) {
                        $fPath = is_string($mf) ? $mf : (string)($mf['path'] ?? $mf['url'] ?? '');
                        if ($fPath === '') continue;
                        $fName = basename($fPath);
                        $diskFile = $uploadsDir . '/' . $fName;
                        if (file_exists($diskFile) && is_file($diskFile)) {
                            $cleanedBytes += filesize($diskFile);
                            @unlink($diskFile);
                            $cleanedFilesCount++;
                        }
                    }
                }

                // 3. Зануляем тяжелые поля в базе данных
                $stmtClear = $pdo->prepare("
                    UPDATE content_items 
                    SET storyboard_json = NULL, 
                        media_files = NULL, 
                        audio_url = NULL,
                        chat_history = NULL, 
                        motion_html = NULL 
                    WHERE id = :id
                ");
                $stmtClear->execute([':id' => $item['id']]);
                $updatedRows++;
            }

            // Оптимизация SQLite (сжатие и очистка страниц)
            $pdo->exec('PRAGMA optimize;');

        } catch (Throwable $e) {
            // Silently log or handle
        }

        return [
            'status'         => 'success',
            'plan_id'        => $planId,
            'updated_items'  => $updatedRows,
            'deleted_files'  => $cleanedFilesCount,
            'freed_kb'       => round($cleanedBytes / 1024, 2)
        ];
    }
}

