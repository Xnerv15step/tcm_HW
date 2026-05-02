<?php

// ─────────────────────────────────────────────
// 📌 基本底圖設定
// ─────────────────────────────────────────────

// 底圖整體尺寸（用於理解設計稿比例）
const IMG_W = 1184;
const IMG_H = 3552;

// 姓名顯示區域（在底圖上的固定矩形框）
// 👉 所有名字都會被限制在這個區塊內做排版
const NAME_X1 = 355;   // 左上角 X
const NAME_Y1 = 1280;  // 左上角 Y
const NAME_W  = 450;   // 寬度
const NAME_H  = 1260;  // 高度

// ─────────────────────────────────────────────
// ✍️ 字體設定
// ─────────────────────────────────────────────
const FONT_SIZE = 80;            // 字體大小
const LETTER_SPACING = 10;       // 字與字之間的額外間距（直書用）
const NAME_SHIFT_X = -40;        // 整體水平微調（修正視覺偏移）

function normalizeNames(string $raw, int $max = 10): array
{
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $raw = str_replace(['，', '、', ','], "\n", $raw);
    $names = array_map('trim', explode("\n", $raw));
    $names = array_values(array_filter($names, static fn($v) => $v !== ''));
    if (count($names) > $max) {
        $names = array_slice($names, 0, $max);
    }
    return $names;
}


// ─────────────────────────────────────────────
// 📐 Layout 決策函式
// ─────────────────────────────────────────────
// 👉 功能：依照人數回傳「每一列要放幾個名字」
//
// 例如：
// [2,3] → 第一列 2 個名字，第二列 3 個名字
// ─────────────────────────────────────────────
function getLayout(int $n): array
{
    $patterns = [
        1 => [1],
        2 => [2],
        3 => [3],
        4 => [2, 2],
        5 => [2, 3],
        6 => [3, 3],
        7 => [3, 4],
        8 => [4, 4],
        9 => [3, 3, 3],
        10 => [3, 3, 4],
    ];

    return $patterns[$n] ?? [];
}


// ─────────────────────────────────────────────
// 📍 座標計算（Layout → 中心點模型）
// ─────────────────────────────────────────────
// 👉 核心概念：
// 不直接給 x/y 起點，而是先算「格子中心 cx/cy」
// 再交給 render 做精準字形對齊
//
// ✔ 好處：
// - 可換不同對齊策略
// - 可支援未來 auto layout
// - render 與 layout 解耦
// ─────────────────────────────────────────────
function calcPositions(array $names, array $layout): array
{
    $positions = [];

    $nameIndex = 0;                 // 目前處理到第幾個名字
    $rowCount  = count($layout);    // 總列數（row）

    foreach ($layout as $rowIndex => $colCount) {

        // 每一列平均分配高度
        $layerHeight = NAME_H / $rowCount;

        // 每一欄平均分配寬度
        $colWidth = NAME_W / $colCount;

        for ($col = 0; $col < $colCount; $col++) {

            // 若名字已經用完，避免越界
            if (!isset($names[$nameIndex])) break;

            // ─────────────────────────────
            // 🎯 計算格子中心點
            // ─────────────────────────────
            $centerX = NAME_X1 + $col * $colWidth + $colWidth / 2;
            $centerY = NAME_Y1 + $rowIndex * $layerHeight + $layerHeight / 2;

            $positions[] = [
                'name' => $names[$nameIndex],

                // cx/cy = 格子中心點（layout anchor）
                'cx' => $centerX,
                'cy' => $centerY,
            ];

            $nameIndex++;
        }
    }

    return $positions;
}


// ─────────────────────────────────────────────
// 🎨 Render（實際畫圖核心）
// ─────────────────────────────────────────────
// 👉 負責：
// - 載入底圖
// - 逐字拆解姓名
// - 計算真實字形 bbox
// - 做垂直直書排版
// - 輸出 PNG
// ─────────────────────────────────────────────
function renderTablet(array $positions, string $outputPath): void
{
    // 載入底圖
    $img = imagecreatefrompng(__DIR__ . '/blank.png');

    // 文字顏色（黑色）
    $black = imagecolorallocate($img, 0, 0, 0);

    // 字型（楷體，支援繁中）
    $font = 'C:/Windows/Fonts/kaiu.ttf';
    if (!is_file($font)) {
        throw new RuntimeException('找不到字型檔：' . $font);
    }

    // 每個字的垂直間距（字高 + 間距）
    $advance = FONT_SIZE + LETTER_SPACING;


    // ─────────────────────────────────────────────
    // 📏 字形中心校正（非常重要）
    // ─────────────────────────────────────────────
    // 👉 問題：
    // 不同中文字左右空白不同，會導致整串直書「歪掉」
    //
    // 👉 解法：
    // 用固定參考字（國）建立統一中心軸
    // ─────────────────────────────────────────────
    $refChar = '國';
    $refBox = imagettfbbox(FONT_SIZE, 0, $font, $refChar);

    $refMinX = min($refBox[0], $refBox[2], $refBox[4], $refBox[6]);
    $refMaxX = max($refBox[0], $refBox[2], $refBox[4], $refBox[6]);

    $refCenterOffsetX = ($refMinX + $refMaxX) / 2;


    // ─────────────────────────────────────────────
    // 🔁 逐一處理每個姓名
    // ─────────────────────────────────────────────
    foreach ($positions as $entry) {

        $chars = mb_str_split($entry['name']);

        // 用來計算整串文字的上下範圍（bounding box）
        $minTerm = null;
        $maxTerm = null;

        // ─────────────────────────────
        // 📐 計算整串字的垂直總高度
        // ─────────────────────────────
        foreach ($chars as $i => $char) {

            $bbox = imagettfbbox(FONT_SIZE, 0, $font, $char);

            $minY = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $maxY = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);

            // 每個字在直書中的相對位置
            $tMin = $i * $advance + $minY;
            $tMax = $i * $advance + $maxY;

            $minTerm = ($minTerm === null) ? $tMin : min($minTerm, $tMin);
            $maxTerm = ($maxTerm === null) ? $tMax : max($maxTerm, $tMax);
        }

        if ($minTerm === null || $maxTerm === null) {
            continue;
        }

        // ─────────────────────────────
        // 🎯 垂直置中（整串名字）
        // ─────────────────────────────
        $y0 = $entry['cy'] - ($minTerm + $maxTerm) / 2;

        // ─────────────────────────────
        // 🎯 水平置中 + 軸線修正
        // ─────────────────────────────
        $x0 = $entry['cx']
            + NAME_SHIFT_X
            - $refCenterOffsetX;


        // ─────────────────────────────
        // ✍️ 實際畫字（逐字直書）
        // ─────────────────────────────
        foreach ($chars as $i => $char) {
            $y = $y0 + $i * $advance;

            imagettftext(
                $img,
                FONT_SIZE,
                0,
                (int) round($x0),
                (int) round($y),
                $black,
                $font,
                $char
            );
        }
    }

    // 輸出 PNG
    imagepng($img, $outputPath);

    // PHP 8.x 已自動管理 GD 圖片資源釋放
}

function generateTabletImage(array $names, string $outputPath): void
{
    $names = array_values(array_filter(array_map('trim', $names), static fn($v) => $v !== ''));
    if ($names === []) {
        throw new InvalidArgumentException('沒有輸入名字');
    }

    $layout = getLayout(count($names));
    if ($layout === []) {
        throw new InvalidArgumentException('目前僅支援 1~10 個名字');
    }

    $positions = calcPositions($names, $layout);
    renderTablet($positions, $outputPath);
}


// ─────────────────────────────────────────────
// 🚀 CLI / 直接執行時的主流程（for debug）
// ─────────────────────────────────────────────
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $names = ['郭仰德', '方孝子', '王山水', '李月華', '陳大文'];
    generateTabletImage($names, 'output.png');
    echo "完成！";
}
