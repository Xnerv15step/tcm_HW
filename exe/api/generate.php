<?php
declare(strict_types=1);

require_once __DIR__ . '/../print_table.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $rawBody = file_get_contents('php://input') ?: '';
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');

    $rawNames = '';
    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $rawNames = (string)($json['names'] ?? '');
    } else {
        $rawNames = (string)($_POST['names'] ?? '');
    }

    $names = normalizeNames($rawNames, 10);
    if ($names === []) {
        throw new InvalidArgumentException('請輸入至少 1 個名字');
    }

    $dir = __DIR__ . '/../generated';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $id = bin2hex(random_bytes(16));
    $path = $dir . '/' . $id . '.png';
    generateTabletImage($names, $path);

    echo json_encode([
        'id' => $id,
        'previewUrl' => 'download.php?id=' . $id . '&inline=1',
        'downloadUrl' => 'download.php?id=' . $id,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

