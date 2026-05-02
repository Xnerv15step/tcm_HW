<?php

declare(strict_types=1);

// 取得網址參數中的 ID 並轉為字串
$id = (string)($_GET['id'] ?? '');

// 安全檢查：驗證 ID 是否為 32 位元的十六進制字串
if (!preg_match('/\A[a-f0-9]{32}\z/', $id)) {
    http_response_code(400);
    echo 'Bad id';
    exit;
}

// 組合完整的圖片檔案路徑
$path = __DIR__ . '/generated/' . $id . '.png';

// 檢查該檔案是否真的存在
if (!is_file($path)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

// --- 修改重點：動態判斷顯示模式 ---
// 只有當網址帶有 inline=1 時才直接顯示，否則一律視為下載
$isInline = isset($_GET['inline']) && (string)$_GET['inline'] === '1';
$disposition = $isInline ? 'inline' : 'attachment';

// 設定檔名
$filename = 'tablet.png';

// 設定 HTTP 回應標頭
header('Content-Type: image/png');
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: no-store');

// 根據模式決定是 inline (預覽) 還是 attachment (下載)
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');

// 輸出圖片
readfile($path);
exit;
