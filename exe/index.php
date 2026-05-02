<?php

declare(strict_types=1);
// 引入處理邏輯的核心函式庫（包含名字正規化與圖片生成）
require_once __DIR__ . '/print_table.php';

// 設定初始預設顯示的名字清單
$default = "郭仰德\n方孝子\n王山水\n李月華\n陳大文";
// 優先取得 POST 提交的名字，若無則使用預設值
$raw = $_POST['names'] ?? $default;
$error = null;
$downloadId = null;
$previewUrl = 'blank.png'; // 預設的預覽圖片佔位符

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 正規化處理輸入的名字字串，限制最多 10 筆
        $names = normalizeNames((string) $raw, 10);
        if ($names === []) {
            throw new InvalidArgumentException('請輸入至少 1 個名字');
        }

        // 確保儲存生成的圖片目錄存在
        $dir = __DIR__ . '/generated';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // 生成唯一的檔案 ID 並設定存放路徑
        $downloadId = bin2hex(random_bytes(16));
        $path = $dir . '/' . $downloadId . '.png';

        // 呼叫繪圖函式生成祿位圖片
        generateTabletImage($names, $path);

        // 設定預覽 URL，指向負責下載/讀取的 PHP
        $previewUrl = 'download.php?id=' . rawurlencode($downloadId) . '&inline=1';
    } catch (Throwable $e) {
        // 捕捉任何執行中的錯誤並顯示給使用者
        $error = $e->getMessage();
    }
} ?>
<!doctype html>
<html lang="zh-Hant">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>祿位排版產生器</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            font-family: system-ui, -apple-system, "Segoe UI", Arial, "Noto Sans TC", sans-serif;
            margin: 24px;
        }

        /* 頁面大標題樣式 */
        .title {
            text-align: center;
            margin-bottom: 24px;
            font-size: 24px;
            color: #111827;
        }

        /* 主容器：使用 Grid 佈局，區分左側輸入區與右側預覽區 */
        .wrap {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: start;
            max-width: 1200px;
            margin: 0 auto;
            /* 水平居中 */
        }

        /* 左側輸入表單卡片 */
        .card {
            position: relative;
            left: 150px;
            /* 注意：此偏移可能會導致小螢幕版面偏移，建議依排版需求調整 */
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
        }

        /* 右側圖片預覽卡片 */
        .card-prieview {
            position: relative;
            left: 150px;
            border: 1px solid #e5e7eb;
            width: fit-content;
            border-radius: 12px;
            max-width: 30%;
            padding: 16px;
            margin: 0 auto;
        }

        /* 文字輸入框樣式 */
        textarea {
            width: 90%;
            min-height: 220px;
            resize: vertical;
            font-size: 16px;
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
        }

        /* 主要按鈕樣式 */
        button {
            background: #111827;
            color: #fff;
            border: 0;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #0b1220;
        }

        /* 輔助說明文字 */
        .muted {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.4;
        }

        /* 錯誤訊息提醒框 */
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 10px 12px;
            border-radius: 10px;
            margin: 12px 0 0;
        }

        /* 按鈕與連結的排列容器 */
        .actions {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .link {
            color: #111827;
            text-decoration: underline;
        }

        /* 圖片自適應縮放 */
        img {
            max-width: 100%;
            height: auto;
            background: #fff;
            display: block;
        }

        /* 手機版排版調整：改為垂直單欄 */
        @media (max-width: 900px) {
            .wrap {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <h1 style="margin:0 0 12px;" class="title">祿位排版產生器</h1>
    <div class="wrap">
        <!-- 左側：輸入區 -->
        <div class="card">
            <form method="post">
                <label for="names" style="font-weight:600;">名字（每行一個）</label>
                <div class="muted" style="margin:6px 0 10px;">支援最多 10 個名字；也可用逗號/頓號分隔。</div>
                <textarea id="names" name="names" placeholder="例：王小明&#10;李大華"><?php echo htmlspecialchars((string)$raw, ENT_QUOTES, 'UTF-8'); ?></textarea>

                <div class="actions">
                    <button type="submit">產生圖片</button>
                    <?php if ($downloadId): ?>
                        <!-- 只有在成功生成圖片後才顯示下載連結 -->
                        <a class="link" href="<?php echo 'download.php?id=' . htmlspecialchars($downloadId, ENT_QUOTES, 'UTF-8'); ?>">下載 PNG</a>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <!-- 顯示錯誤訊息 -->
                    <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                <?php endif; ?>
            </form>
        </div>

        <!-- 右側：預覽區 -->
        <div class="card-prieview">
            <div style="font-weight:600; margin-bottom:10px;">預覽</div>

            <?php if (!empty($previewUrl)): ?>
                <!-- 顯示生成的預覽圖或預設空白圖 -->
                <img alt="preview" src="<?php echo htmlspecialchars((string)$previewUrl, ENT_QUOTES, 'UTF-8'); ?>" />
            <?php else: ?>
                <!-- 當無預覽網址時的備用顯示（目前邏輯上 $previewUrl 初始即有值） -->
                <img alt="default-preview" src="path/to/your/blank-image.png" style="opacity: 0.3;" />
                <div class="muted">按「產生圖片」後會在這裡顯示預覽。</div>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>