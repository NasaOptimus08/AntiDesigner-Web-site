<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE — MICRO LINK REDIRECTOR & ATTRIBUTION TRACKER
 * Ultra-fast redirect with click attribution into SQLite link_clicks table
 * =============================================================================
 */

$coreDataDir = dirname(__DIR__) . '/core-data';
$dbFile = $coreDataDir . '/database.sqlite';

$slug = trim((string)($_GET['slug'] ?? ''));
$targetUrl = trim((string)($_GET['to'] ?? ''));
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

// Если slug передан в PATH_INFO (например /r/speed)
if ($slug === '' && !empty($_SERVER['PATH_INFO'])) {
    $slug = trim($_SERVER['PATH_INFO'], '/');
}

// Предопределенные шорткаты студии для постов и рекламы
$predefinedSlugs = [
    'speed'     => 'https://t.me/AntiDesigner01?start=speed_audit',
    'checklist' => 'https://t.me/AntiDesigner01?start=checklist',
    'audit'     => 'https://t.me/AntiDesigner01?start=free_audit',
    'tariffs'   => 'https://t.me/AntiDesigner01?start=tariffs',
    'case-vc'   => 'https://vc.ru/',
    'tg'        => 'https://t.me/AntiDesigner01'
];

if ($targetUrl === '') {
    if ($slug !== '' && isset($predefinedSlugs[$slug])) {
        $targetUrl = $predefinedSlugs[$slug];
    } else {
        $targetUrl = 'https://antidesigner.tech/';
    }
}

if ($slug === '') {
    $slug = 'direct';
}

// Защита от опасных протоколов
if (!preg_match('#^(https?://|/)#i', $targetUrl)) {
    $targetUrl = 'https://antidesigner.tech/';
}

// Быстрая запись клика в базу данных
if (file_exists($dbFile)) {
    try {
        $dsn = 'sqlite:' . $dbFile;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 2000;');

        $stmt = $pdo->prepare("
            INSERT INTO link_clicks (slug, target_url, referrer, clicked_at)
            VALUES (:slug, :url, :ref, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':slug' => substr($slug, 0, 64),
            ':url'  => substr($targetUrl, 0, 500),
            ':ref'  => substr($referrer, 0, 255)
        ]);
    } catch (Throwable $e) {
        // Редирект не должен падать даже при сбое БД
    }
}

header("Location: " . $targetUrl, true, 302);
exit;
