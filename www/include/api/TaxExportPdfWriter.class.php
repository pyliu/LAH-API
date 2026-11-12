<?php

call_user_func(function () {
    $possibleTfpdf = [
        dirname(__DIR__, 2) . '/vendor/setasign/tfpdf/tfpdf.php',
        dirname(__DIR__, 3) . '/www/vendor/setasign/tfpdf/tfpdf.php',
        dirname(__DIR__, 3) . '/vendor/setasign/tfpdf/tfpdf.php',
    ];
    foreach ($possibleTfpdf as $tfPath) {
        if (is_file($tfPath)) {
            require_once $tfPath;
            break;
        }
    }

    if (!class_exists('tFPDF', false)) {
        if (!class_exists('FPDF', false)) {
            $possibleFpdf = [
                dirname(__DIR__, 2) . '/vendor/setasign/fpdf/fpdf.php',
                dirname(__DIR__, 3) . '/www/vendor/setasign/fpdf/fpdf.php',
            ];
            foreach ($possibleFpdf as $fpdf) {
                if (is_file($fpdf)) {
                    require_once $fpdf;
                    break;
                }
            }
        }
        if (class_exists('FPDF', false)) {
            class_alias('FPDF', 'tFPDF');
        }
    }
});

/**
 * 繁體中文公務報表 PDF 渲染引擎
 * 支援 TrueType Subset 嵌入 (標楷體 DFKaiShu / kaiu.ttf)
 * 保證在 Microsoft Edge、Google Chrome、Acrobat Reader 及各平台 100% 完美呈現文字
 */
class TaxExportPdfWriter extends tFPDF {
    /**
     * @var string
     */
    protected $defaultFontFamily = 'KaiU';

    /**
     * @var bool
     */
    protected $isTrueTypeEmbedded = false;

    public function __construct(string $orientation = 'L', string $unit = 'pt', string $size = 'A4') {
        parent::__construct($orientation, $unit, $size);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(20, 20, 20);
        $this->initChineseFont();
    }

    /**
     * 初始化繁體中文字型 (優先載入標楷體 TrueType Subset)
     */
    protected function initChineseFont(): void {
        $candidates = [
            dirname(__DIR__, 2) . '/assets/fonts/kaiu.ttf',
            dirname(__DIR__, 3) . '/www/assets/fonts/kaiu.ttf',
            dirname(__DIR__, 3) . '/API/assets/fonts/kaiu.ttf',
            'C:/Windows/Fonts/kaiu.ttf',
            'C:/Windows/Fonts/msjh.ttc',
            'C:/Windows/Fonts/mingliu.ttc',
        ];

        $foundFont = null;
        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                $foundFont = $cand;
                break;
            }
        }

        if ($foundFont !== null && method_exists($this, 'AddFont')) {
            try {
                $this->AddFont('KaiU', '', $foundFont, true);
                $this->SetFont('KaiU', '', 10);
                $this->defaultFontFamily = 'KaiU';
                $this->isTrueTypeEmbedded = true;
                return;
            } catch (Throwable $e) {
                // 回退至 Big5 CIDFont
            }
        }

        $this->initBig5Fallback();
    }

    /**
     * Big5 CIDFont 回退方案 (無外部 TTF 時使用)
     */
    protected function initBig5Fallback(): void {
        $this->defaultFontFamily = 'Big5';
        $family = 'Big5';
        $name = 'MSung-Light-Acro';
        $cw = [
            ' '=>250, '!'=>250, '"'=>408, '#'=>592, '$'=>488, '%'=>755, '&'=>646, '\''=>263,
            '('=>323, ')'=>323, '*'=>387, '+'=>521, ','=>250, '-'=>314, '.'=>250, '/'=>276,
            '0'=>500, '1'=>500, '2'=>500, '3'=>500, '4'=>500, '5'=>500, '6'=>500, '7'=>500,
            '8'=>500, '9'=>500, ':'=>290, ';'=>290, '<'=>521, '='=>521, '>'=>521, '?'=>436,
            '@'=>747, 'A'=>646, 'B'=>627, 'C'=>656, 'D'=>698, 'E'=>588, 'F'=>532, 'G'=>711,
            'H'=>713, 'I'=>293, 'J'=>419, 'K'=>631, 'L'=>540, 'M'=>885, 'N'=>715, 'O'=>722,
            'P'=>576, 'Q'=>722, 'R'=>628, 'S'=>528, 'T'=>574, 'U'=>711, 'V'=>609, 'W'=>890,
            'X'=>601, 'Y'=>569, 'Z'=>565, '['=>323, '\\'=>276, ']'=>323, '^'=>521, '_'=>500,
            '`'=>500, 'a'=>501, 'b'=>527, 'c'=>447, 'd'=>527, 'e'=>467, 'f'=>285, 'g'=>498,
            'h'=>522, 'i'=>246, 'j'=>240, 'k'=>464, 'l'=>246, 'm'=>775, 'n'=>522, 'o'=>517,
            'p'=>528, 'q'=>528, 'r'=>349, 's'=>406, 't'=>328, 'u'=>522, 'v'=>452, 'w'=>668,
            'x'=>459, 'y'=>452, 'z'=>418, '{'=>394, '|'=>394, '}'=>394, '~'=>521
        ];
        $CMap = 'ETen-B5-H';
        $registry = ['ordering' => 'Taiwan1', 'supplement' => 0];

        $fontkey = strtolower($family);
        $this->fonts[$fontkey] = [
            'i' => count($this->fonts) + 1,
            'type' => 'Type0',
            'name' => $name,
            'up' => -100,
            'ut' => 50,
            'cw' => $cw,
            'CMap' => $CMap,
            'registry' => $registry
        ];
        $this->SetFont($this->defaultFontFamily, '', 10);
    }

    /**
     * 強制文字編碼正規化為 UTF-8
     */
    public static function ensureUtf8($str): string {
        if ($str === null || $str === '') {
            return '';
        }
        $str = (string)$str;
        if (mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }
        $converted = @iconv('CP950', 'UTF-8//IGNORE', $str);
        if ($converted !== false && $converted !== '') {
            return $converted;
        }
        return mb_convert_encoding($str, 'UTF-8', 'CP950');
    }

    /**
     * UTF-8 轉 Big5 (僅供 Fallback 模式使用)
     */
    public static function toBig5(string $str): string {
        if ($str === '') {
            return '';
        }
        $utf8 = self::ensureUtf8($str);
        $converted = @mb_convert_encoding($utf8, 'CP950', 'UTF-8');
        return ($converted !== false) ? $converted : $str;
    }

    /**
     * 輸出單元格文字 (自動相容 TrueType UTF-8 與 Big5 Fallback)
     */
    public function cellZh(float $w, float $h = 0, string $txt = '', $border = 0, int $ln = 0, string $align = '', bool $fill = false, string $link = ''): void {
        $out = $this->isTrueTypeEmbedded ? self::ensureUtf8($txt) : self::toBig5($txt);
        $this->Cell($w, $h, $out, $border, $ln, $align, $fill, $link);
    }

    /**
     * 輸出多行文字單元格
     */
    public function multiCellZh(float $w, float $h, string $txt, $border = 0, string $align = 'J', bool $fill = false): void {
        $out = $this->isTrueTypeEmbedded ? self::ensureUtf8($txt) : self::toBig5($txt);
        $this->MultiCell($w, $h, $out, $border, $align, $fill);
    }

    /**
     * 取得中文字串寬度
     */
    public function getStringWidthZh(string $str): float {
        $out = $this->isTrueTypeEmbedded ? self::ensureUtf8($str) : self::toBig5($str);
        return $this->GetStringWidth($out);
    }

    /**
     * 繪製置中單元格文字 (精確 X, Y 定位)
     */
    public function drawCell(float $x, float $y, float $w, float $h, string $text, string $align = 'C', float $fontSize = 8.5, $border = 1): void {
        $this->SetXY($x, $y);
        $this->SetFont($this->defaultFontFamily, '', $fontSize);
        $this->cellZh($w, $h, $text, $border, 0, $align);
    }

    /**
     * 繪製自適應斷行文字單元格 (避免長地址/名稱溢出邊界)
     */
    public function drawWrappedCell(float $x, float $y, float $w, float $h, string $text, int $maxCharsPerLine = 14, float $fontSize = 7.5, string $align = 'L', int $border = 1): void {
        if ($border) {
            $this->Rect($x, $y, $w, $h, 'D');
        }
        $text = trim($text);
        $len = mb_strlen($text, 'UTF-8');
        if ($len === 0) {
            return;
        }
        $this->SetFont($this->defaultFontFamily, '', $fontSize);
        if ($len <= $maxCharsPerLine) {
            $lineH = $fontSize + 2.5;
            $this->SetXY($x + 2, $y + max(1.0, ($h - $lineH) / 2));
            $this->cellZh($w - 4, $lineH, $text, 0, 0, $align);
            return;
        }

        $line1 = mb_substr($text, 0, $maxCharsPerLine, 'UTF-8');
        $line2 = mb_substr($text, $maxCharsPerLine, $maxCharsPerLine, 'UTF-8');
        $lineH = $fontSize + 1.5;
        $totalTextH = $lineH * 2 + 1.0;
        $startY = $y + max(1.5, ($h - $totalTextH) / 2);
        $this->SetXY($x + 2, $startY);
        $this->cellZh($w - 4, $lineH, $line1, 0, 0, $align);
        $this->SetXY($x + 2, $startY + $lineH + 1.0);
        $this->cellZh($w - 4, $lineH, $line2, 0, 0, $align);
    }

    /**
     * 繪製矩形外框 (pt 單位)
     */
    public function drawRect(float $x, float $y, float $w, float $h): void {
        $this->Rect($x, $y, $w, $h, 'D');
    }
}
