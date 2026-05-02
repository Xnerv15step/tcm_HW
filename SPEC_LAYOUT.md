# 祿位姓名排版規劃書

本文件說明專案目前的「分組（layout）策略」與「座標計算 / 對齊」邏輯，便於之後調整字型、字距、或擴充 10 人以上的排列規則。

對應程式碼：`C:\Users\DET\HW\print_table.php`

---

## 1. 名詞與座標系

- 影像座標系：左上角為 `(0,0)`，往右 `+x`、往下 `+y`（GD / PNG 的常見座標系）。
- **底圖尺寸**：
  - `IMG_W = 1184`
  - `IMG_H = 3552`
- **姓名可用區域（固定矩形框）**：
  - 左上角：`(NAME_X1, NAME_Y1) = (355, 1280)`
  - 尺寸：`NAME_W = 450`, `NAME_H = 1260`
- **格子（cell）**：姓名區域會被切成「列 × 欄」的格子，每個名字佔一格。
- **Anchor（錨點）**：本專案採用「每格中心點」作為排版 anchor：
  - `cx, cy` = 該格中心點
  - render 階段再依字形 bbox 做精準對齊

---

## 2. 分組 / 排列策略（1–10 人）

`getLayout(n)` 會回傳一個整數陣列 `layout[]`，每個元素代表該「列」要放幾個名字（欄數）。

例如 `layout = [2,3]` 表示：
- 第 0 列：2 欄（2 個名字）
- 第 1 列：3 欄（3 個名字）

目前支援的固定策略如下（`n -> layout`）：

| 人數 n | layout（每列欄數） | 列數 |
|---:|---|---:|
| 1 | `[1]` | 1 |
| 2 | `[2]` | 1 |
| 3 | `[3]` | 1 |
| 4 | `[2,2]` | 2 |
| 5 | `[2,3]` | 2 |
| 6 | `[3,3]` | 2 |
| 7 | `[3,4]` | 2 |
| 8 | `[4,4]` | 2 |
| 9 | `[3,3,3]` | 3 |
| 10 | `[3,3,4]` | 3 |

備註：
- 這是一套「顯式 mapping」的策略：可預測、可精修，但不會自動延伸到 10 以上。
- 若要擴充到 11+，建議另寫「自動分配」演算法（例如每列最多 4 欄、優先平均分配、避免最後一列落單等）。

---

## 3. 座標計算邏輯（layout → cell center）

對應函式：`calcPositions($names, $layout)`

### 3.1 列高與欄寬

令：
- `rowCount = count(layout)`（總列數）
- `colCount = layout[rowIndex]`（該列欄數）

則：
- 每列高度（平均分配）：`layerHeight = NAME_H / rowCount`
- 該列每欄寬度（平均分配）：`colWidth = NAME_W / colCount`

### 3.2 cell 中心點

對第 `rowIndex` 列、第 `col` 欄：

- `centerX = NAME_X1 + col * colWidth + colWidth / 2`
- `centerY = NAME_Y1 + rowIndex * layerHeight + layerHeight / 2`

輸出資料結構（每個名字一筆）：

```php
[
  'name' => '王山水',
  'cx'   => $centerX,
  'cy'   => $centerY,
]
```

重點：
- `calcPositions()` **只管分格子**，不管字型 bbox、不管 baseline。
- 這讓 layout（分格）與 render（字形對齊）解耦，方便日後替換對齊策略。

---

## 4. Render 對齊邏輯（bbox 精準置中 + 直書）

對應函式：`renderTablet($positions, $outputPath)`

### 4.1 直書步進（advance）

- `advance = FONT_SIZE + LETTER_SPACING`
- 第 `i` 個字（從 0 開始）會被畫在 `y = y0 + i * advance`

### 4.2 先算「整串字」的真實垂直外框 → 讓整串在 cell 內垂直置中

對名字拆字 `chars[]`，逐字取 bbox：
- bbox 的 y 會是相對於 baseline 的偏移（可能為負）
- 取該字 bbox 的 `minY / maxY`
- 把每個字放到直書第 `i` 格後，整體的垂直範圍為：
  - `tMin = i * advance + minY`
  - `tMax = i * advance + maxY`
- 全字串的範圍：
  - `minTerm = min(tMin)`
  - `maxTerm = max(tMax)`

令 cell 中心為 `cy`，則第一個字 baseline 的 y（`y0`）取：

- `y0 = cy - (minTerm + maxTerm) / 2`

這樣整串字的 bbox 外框會在 cell 中心上下對齊。

### 4.3 固定垂直中心線（避免「每字置中」造成整串彎曲）

問題：
- 如果「每個字」都各自算 bbox 的左右中心再置中，楷體字因左右留白不同，會造成同一串字的中心線左右飄移，視覺上變成「彎彎的」。

解法（目前採用）：
- 使用固定參考字（`refChar = '國'`）計算其 bbox 左右中心偏移：
  - `refCenterOffsetX = (refMinX + refMaxX) / 2`
- 對同一個名字，所有字共用同一條 baseline x：
  - `x0 = cx + NAME_SHIFT_X - refCenterOffsetX`

其中 `NAME_SHIFT_X` 是「整體左右微調」用常數（負數往左、正數往右），用來符合視覺中心。

---

## 5. 已知限制與建議擴充

### 5.1 限制

- 目前只支援 1–10 人（layout 為固定 mapping）。
- `advance` 仍採固定值（`FONT_SIZE + LETTER_SPACING`），沒有依每個字的 bbox 高度做動態行距。
- 參考字固定使用 `國`，若改字型或字重，可能需要重新挑選參考字或改成可設定。

### 5.2 建議擴充方向

- **11+ 人自動分配**：例如設定每列最大欄數 4，將 `n` 拆成多列，使每列欄數差距最小、避免最後一列只有 1 個。
- **動態行距**：用 bbox 的 `maxY-minY` 估字高，或以字串 bbox 估整體比例，動態調整 `advance` 以避免太擠或太鬆。
- **可設定參考字 / 中心線策略**：讓不同字型可快速校正視覺中心。

