<?php

if (!class_exists('FPDF', false)) {
    $possibleAutoloads = [
        dirname(__DIR__, 2) . '/vendor/autoload.php',
        dirname(__DIR__, 3) . '/www/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];
    foreach ($possibleAutoloads as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            break;
        }
    }
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
}

/**
 * 繁體中文 PDF 渲染引擎 (A4 橫向 / CID-0 MSung-Light-Acro / ETen-B5-H)
 * 遵循 Adobe PDF 標準 CJK CIDFont 規範，現代閱讀器免安裝外部字型
 */
class TaxExportPdfWriter extends FPDF {
    /**
     * Big5 半形字元寬度定義
     */
    protected static $big5Widths = [
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

    /**
     * @var string
     */
    protected $defaultFontFamily = 'Big5';

    public function __construct(string $orientation = 'L', string $unit = 'mm', string $size = 'A4') {
        parent::__construct($orientation, $unit, $size);
        $this->SetAutoPageBreak(true, 10);
        $this->SetMargins(10, 10, 10);
        $this->initBig5Font();
        $this->SetFont($this->defaultFontFamily, '', 10);
    }

    /**
     * 初始化 Big5 / ETen-B5-H 字型定義
     */
    protected function initBig5Font(): void {
        $family = 'Big5';
        $name = 'MSung-Light-Acro';
        $cw = self::$big5Widths;
        $CMap = 'ETen-B5-H';
        $registry = ['ordering' => 'Taiwan1', 'supplement' => 0];

        $this->addCIDFont($family, '', $name, $cw, $CMap, $registry);
        $this->addCIDFont($family, 'B', $name . ',Bold', $cw, $CMap, $registry);
        $this->addCIDFont($family, 'I', $name . ',Italic', $cw, $CMap, $registry);
        $this->addCIDFont($family, 'BI', $name . ',BoldItalic', $cw, $CMap, $registry);
    }

    protected function addCIDFont(string $family, string $style, string $name, array $cw, string $CMap, array $registry): void {
        $fontkey = strtolower($family) . strtoupper($style);
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
    }

    /**
     * UTF-8 轉 Big5 (CP950)
     */
    public static function toBig5(string $str): string {
        if ($str === '') {
            return '';
        }
        if (mb_check_encoding($str, 'UTF-8') && preg_match('/[\x80-\xFF]/', $str)) {
            $converted = @mb_convert_encoding($str, 'CP950', 'UTF-8');
            if ($converted !== false) {
                return $converted;
            }
        }
        return $str;
    }

    /**
     * 支援繁體中文的單元格輸出 (自動由 UTF-8 轉碼)
     */
    public function cellZh(float $w, float $h = 0, string $txt = '', $border = 0, int $ln = 0, string $align = '', bool $fill = false, string $link = ''): void {
        $this->Cell($w, $h, self::toBig5($txt), $border, $ln, $align, $fill, $link);
    }

    /**
     * 支援繁體中文的自動斷行文字單元格 (自動由 UTF-8 轉碼)
     */
    public function multiCellZh(float $w, float $h, string $txt, $border = 0, string $align = 'J', bool $fill = false): void {
        $this->MultiCell($w, $h, self::toBig5($txt), $border, $align, $fill);
    }

    /**
     * 取得繁體中文字串寬度
     */
    public function getStringWidthZh(string $txt): float {
        return $this->GetStringWidth(self::toBig5($txt));
    }

    /**
     * 覆寫 GetStringWidth
     */
    public function GetStringWidth($s) {
        if (isset($this->CurrentFont['type']) && $this->CurrentFont['type'] === 'Type0') {
            return $this->getBig5StringWidth((string)$s);
        }
        return parent::GetStringWidth($s);
    }

    /**
     * 計算 Big5 字串寬度
     */
    protected function getBig5StringWidth(string $s): float {
        $cw = &$this->CurrentFont['cw'];
        $w = 0;
        $l = strlen($s);
        $i = 0;
        while ($i < $l) {
            $c = $s[$i];
            if (ord($c) >= 128) {
                $w += 1000;
                $i += 2;
            } else {
                $w += isset($cw[$c]) ? $cw[$c] : 500;
                $i++;
            }
        }
        return $w * $this->FontSize / 1000;
    }

    /**
     * 覆寫 MultiCell 支援 Big5 無空格自動換行
     */
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false) {
        if (!isset($this->CurrentFont['type']) || $this->CurrentFont['type'] !== 'Type0') {
            parent::MultiCell($w, $h, $txt, $border, $align, $fill);
            return;
        }

        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }

        $b = 0;
        $b2 = '';
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos($border, 'L') !== false) $b2 .= 'L';
                if (strpos($border, 'R') !== false) $b2 .= 'R';
                $b = (strpos($border, 'T') !== false) ? $b2 . 'T' : $b2;
            }
        }

        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;

        while ($i < $nb) {
            $c = $s[$i];
            $ascii = ord($c);

            if ($c === "\n") {
                $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                if ($border && $nl === 2) {
                    $b = $b2;
                }
                continue;
            }

            if ($c === ' ') {
                $sep = $i;
            }

            if ($ascii >= 128) {
                $charLen = 2;
                $charWidth = 1000;
                $sep = $i;
            } else {
                $charLen = 1;
                $charWidth = isset($cw[$c]) ? $cw[$c] : 500;
            }

            if ($l + $charWidth > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i += $charLen;
                    }
                    $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                } else {
                    $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
                    $i = $sep;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                if ($border && $nl === 2) {
                    $b = $b2;
                }
            } else {
                $i += $charLen;
                $l += $charWidth;
            }
        }

        if ($border && strpos($border, 'B') !== false) {
            $b .= 'B';
        }
        $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    /**
     * 覆寫 _putfonts，輸出 CID-0 / Type0 中文字型定義
     */
    protected function _putfonts() {
        foreach ($this->FontFiles as $file => $info) {
            $this->_newobj();
            $this->FontFiles[$file]['n'] = $this->n;
            $font = file_get_contents($file);
            if (!$font) {
                $this->Error('Font file not found: ' . $file);
            }
            $compressed = (substr($file, -2) === '.z');
            if (!$compressed && isset($info['length2'])) {
                $font = substr($font, 6, $info['length1']) . substr($font, 6 + $info['length1'] + 6, $info['length2']);
            }
            $this->_put('<</Length ' . strlen($font));
            if ($compressed) {
                $this->_put('/Filter /FlateDecode');
            }
            $this->_put('/Length1 ' . $info['length1']);
            if (isset($info['length2'])) {
                $this->_put('/Length2 ' . $info['length2'] . ' /Length3 0');
            }
            $this->_put('>>');
            $this->_putstream($font);
            $this->_put('endobj');
        }

        foreach ($this->fonts as $k => $font) {
            if ($font['type'] === 'Type0') {
                $this->_putType0($font);
                $this->fonts[$k]['n'] = $this->n - 2;
            } else {
                $this->fonts[$k]['n'] = $this->n + 1;
                $name = $font['name'];
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/BaseFont /' . $name);
                $this->_put('/Subtype /Type1');
                if ($name !== 'Symbol' && $name !== 'ZapfDingbats') {
                    $this->_put('/Encoding /WinAnsiEncoding');
                }
                $this->_put('>>');
                $this->_put('endobj');
            }
        }
    }

    /**
     * 輸出 Type0 物件、CIDFontType0 與 FontDescriptor
     */
    protected function _putType0(array $font): void {
        // 1. Type0 Font
        $this->_newobj();
        $this->_put('<</Type /Font');
        $this->_put('/Subtype /Type0');
        $this->_put('/BaseFont /' . $font['name'] . '-' . $font['CMap']);
        $this->_put('/Encoding /' . $font['CMap']);
        $this->_put('/DescendantFonts [' . ($this->n + 1) . ' 0 R]');
        $this->_put('>>');
        $this->_put('endobj');

        // 2. CIDFontType0
        $this->_newobj();
        $this->_put('<</Type /Font');
        $this->_put('/Subtype /CIDFontType0');
        $this->_put('/BaseFont /' . $font['name']);
        $this->_put('/CIDSystemInfo <</Registry ' . $this->_textstring('Adobe') . ' /Ordering ' . $this->_textstring($font['registry']['ordering']) . ' /Supplement ' . $font['registry']['supplement'] . '>>');
        $this->_put('/FontDescriptor ' . ($this->n + 1) . ' 0 R');
        if ($font['CMap'] === 'ETen-B5-H') {
            $this->_put('/DW 1000');
        }
        $this->_put('/W [1 94 500]');
        $this->_put('>>');
        $this->_put('endobj');

        // 3. FontDescriptor
        $this->_newobj();
        $this->_put('<</Type /FontDescriptor');
        $this->_put('/FontName /' . $font['name']);
        $this->_put('/Flags 6');
        $this->_put('/FontBBox [-250 -140 1200 860]');
        $this->_put('/ItalicAngle 0');
        $this->_put('/Ascent 860');
        $this->_put('/Descent -140');
        $this->_put('/CapHeight 860');
        $this->_put('/StemV 80');
        $this->_put('>>');
        $this->_put('endobj');
    }
}
