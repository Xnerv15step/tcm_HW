<?php

declare(strict_types=1);

// 取得網址參數中的 ID 並轉為字串
$id = (string)($_GET['id'] ?? '');

// 安全檢查：驗證 ID 是否為 32 位元的十六進制字串（防止路徑穿越攻擊或惡意輸入）
if (!preg_match('/\A[a-f0-9]{32}\z/', $id)) {
    http_response_code(400); // 格式錯誤回傳 400 Bad Request
    echo 'Bad id';
    exit;
}

// 組合完整的圖片檔案路徑
$path = __DIR__ . '/generated/' . $id . '.png';

// 檢查該檔案是否真的存在於伺服器上
if (!is_file($path)) {
    http_response_code(404); // 檔案不存在回傳 404 Not Found
    echo 'Not found';
    exit;
}

// 判斷是否為預覽模式（由網址參數 inline=1 決定）
$inline = isset($_GET['inline']) && (string)$_GET['inline'] === '1';
// 設定客戶端儲存或顯示時的預設檔名
$filename = 'tablet.png';

// 設定 HTTP 回應標頭
header('Content-Type: image/png'); // 告知瀏覽器這是一張 PNG 圖片
header('Content-Length: ' . (string)filesize($path)); // 告知圖片大小
header('Cache-Control: no-store'); // 告知瀏覽器不要快取此檔案

// 設定下載行為：
// 當 inline 為真時會在瀏覽器直接顯示；為假時則會觸發下載下載視窗
// 注意：目前的程式碼固定寫死為 'inline'，若要支援下載功能可根據 $inline 變數動態調整
header('Content-Disposition: inline; filename="' . $filename . '"');

// 讀取檔案內容並輸出串流給瀏覽器
readfile($path);
