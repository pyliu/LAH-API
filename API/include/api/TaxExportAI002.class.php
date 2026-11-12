<?php

final class TaxExportAI002 {
    /**
     * @var callable|null
     */
    private $dbSelector;

    public function __construct($dbSelector = null) {
        $this->dbSelector = $dbSelector;
    }

    /**
     * 執行 AI002 地價異動資料匯出
     * 
     * @param string $path 輸出之暫存檔案路徑 (.part)
     * @param array $item 項目定義 (含 code, name 等)
     * @param array $batch 批次資訊 (含 begin, end, mock 等)
     * @return array ['state' => 'success'|'empty'|'failed', 'message' => string]
     */
    public function export(string $path, array $item, array $batch): array {
        $isMock = !empty($batch['mock']);
        if ($isMock || ($this->dbSelector === null && $this->shouldFallbackToMock())) {
            return $this->exportMock($path, $batch);
        }

        return $this->exportDirectOracle($path, $batch);
    }

    /**
     * 格式化固定長度欄位 (MS950)
     * $width > 0: 左對齊右補空白 (%-Ns)
     * $width < 0: 右對齊左補空白 (%Ns)
     */
    public static function field($value, int $width): string {
        $str = (string)($value !== null ? $value : '');
        // 若為 UTF-8 且含有多位元組字元，轉為 CP950
        if (mb_check_encoding($str, 'UTF-8') && preg_match('/[\x80-\xFF]/', $str)) {
            $ms950 = @mb_convert_encoding($str, 'CP950', 'UTF-8');
            if ($ms950 !== false) {
                $str = $ms950;
            }
        }
        $size = abs($width);
        $len = strlen($str);
        if ($len > $size) {
            $cut = mb_strcut($str, 0, $size, 'CP950');
            if (strlen($cut) < $size) {
                $cut .= '?';
            }
            $str = $cut;
        }
        $padType = $width < 0 ? STR_PAD_LEFT : STR_PAD_RIGHT;
        return str_pad($str, $size, ' ', $padType);
    }

    /**
     * 格式化數字欄位 (右對齊左補空白，%W.Pf)
     */
    public static function numField($value, int $width, int $decimals = 0): string {
        if ($value === null || $value === '') {
            $val = 0.0;
        } else {
            $val = (float)$value;
        }
        if ($decimals > 0) {
            $formatted = sprintf('%.' . $decimals . 'f', $val);
        } else {
            $formatted = sprintf('%.0f', round($val));
        }
        return str_pad($formatted, $width, ' ', STR_PAD_LEFT);
    }

    /**
     * 產製檔尾 TAG (MS950)
     */
    public static function trailer(int $count): string {
        return mb_convert_encoding('##### TAG #####共產製 ' . $count . ' 筆資料', 'CP950', 'UTF-8');
    }

    /**
     * 民國 7 碼日期轉換 (YYYMMDD)
     */
    public static function rocDate(string $date): string {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dt) {
            return '';
        }
        $year = (int)$dt->format('Y') - 1911;
        return sprintf('%03d%s', $year, $dt->format('md'));
    }

    /**
     * 格式化 AI002 單列定長紀錄 (嚴格為 103 bytes)
     * Format: %-3s%-4s%-6s%-2s%-4s%-8s%-10s%-4s%-1s%-2s%-7s%10.0f%10.0f%-5s%8.0f%8s%7s    
     *
     * @param array $row 欄位資料陣列
     * @param string $ka16Str 公告現值字串（第一筆展開列傳入已格式化之 7 碼數字，其餘筆傳入 7 個半形空白）
     * @return string 103 bytes 之 MS950 字串
     */
    public static function formatRecord(array $row, string $ka16Str = ''): string {
        $ss03 = self::field($row['SS03'] ?? '', 3);
        $ss04_1 = self::field($row['SS04_1'] ?? '', 4);
        $ss04_2 = self::field($row['SS04_2'] ?? '', 6);
        $krmk = self::field($row['KRMK'] ?? '', 2);
        $ha48 = self::field($row['HA48'] ?? '', 4);
        $ha49 = self::field($row['HA49'] ?? '', 8);
        $la09 = self::field($row['LA09'] ?? '', 10);
        $mb22_2 = self::field($row['MB22_2'] ?? '', 4);
        $flag = self::field($row['FLAG'] ?? ($row['flag'] ?? ''), 1);
        $ss06 = self::field($row['SS06'] ?? '', 2);
        $ss07 = self::field($row['SS07'] ?? '', 7);
        $mb23_3 = self::numField($row['MB23_3'] ?? 0, 10, 0); // 持分分子在前
        $mb23_2 = self::numField($row['MB23_2'] ?? 0, 10, 0); // 持分分母在後
        $mb22_1 = self::field($row['MB22_1'] ?? '', 5);

        // 前次移轉現值 MB22_3 * 10
        if (isset($row['MB22_3_X10'])) {
            $mb22_3 = (float)$row['MB22_3_X10'];
        } elseif (isset($row['MB22_3']) && $row['MB22_3'] !== '') {
            $mb22_3 = ((float)$row['MB22_3']) * 10.0;
        } else {
            $mb22_3 = 0.0;
        }
        $mb22_3Formatted = self::numField($mb22_3, 8, 0);

        // 申報地價 LA21 * 10
        if (isset($row['LA21_X10'])) {
            $la21 = (float)$row['LA21_X10'];
        } elseif (isset($row['LA21']) && $row['LA21'] !== '') {
            $la21 = ((float)$row['LA21']) * 10.0;
        } else {
            $la21 = 0.0;
        }
        $la21Formatted = self::numField($la21, 8, 0);

        // 公告現值 KA16 (不乘 10，首筆格式化為 %7.0f，次筆起 7 個空白)
        if ($ka16Str !== '') {
            $ka16 = self::field($ka16Str, 7);
        } elseif (isset($row['KA16']) && $row['KA16'] !== '') {
            $ka16 = self::numField($row['KA16'], 7, 0);
        } else {
            $ka16 = '       ';
        }

        $tail = '    ';

        return $ss03 . $ss04_1 . $ss04_2 . $krmk . $ha48 . $ha49 . $la09 .
               $mb22_2 . $flag . $ss06 . $ss07 . $mb23_3 . $mb23_2 .
               $mb22_1 . $mb22_3Formatted . $la21Formatted . $ka16 . $tail;
    }

    /**
     * MOCK 模式產製合成資料
     */
    private function exportMock(string $path, array $batch): array {
        $lines = [];

        // 第 1 筆：本所案件地價異動，LA_TYPE='D' (flag='2')，首筆 WPBLOW 印公告現值 KA16
        $lines[] = self::formatRecord([
            'SS03' => '115',
            'SS04_1' => 'A1HA',
            'SS04_2' => '000001',
            'KRMK' => '01',
            'HA48' => '1010',
            'HA49' => '02101940',
            'LA09' => 'A123456789',
            'MB22_2' => '0001',
            'FLAG' => '2',
            'SS06' => '01',
            'SS07' => '1151025',
            'MB23_3' => 1,
            'MB23_2' => 1,
            'MB22_1' => '11510',
            'MB22_3' => 1200,   // x10 = 12000
            'LA21' => 350,      // x10 = 3500
            'KA16' => 12000     // 不乘 10 -> '  12000'
        ]);

        // 第 2 筆：同地號第二筆異動，LA_TYPE='M' (flag='3')，次筆 KA16 填 7 個半形空白
        $lines[] = self::formatRecord([
            'SS03' => '115',
            'SS04_1' => 'A1HA',
            'SS04_2' => '000001',
            'KRMK' => '01',
            'HA48' => '1010',
            'HA49' => '02101940',
            'LA09' => 'A123456789',
            'MB22_2' => '0002',
            'FLAG' => '3',
            'SS06' => '01',
            'SS07' => '1151025',
            'MB23_3' => 1,
            'MB23_2' => 2,
            'MB22_1' => '11510',
            'MB22_3' => 1350,   // x10 = 13500
            'LA21' => 360,      // x10 = 3600
        ], '       ');

        // 第 3 筆：跨所案件地價異動，LA_TYPE='D' (flag='2')
        $lines[] = self::formatRecord([
            'SS03' => '115',
            'SS04_1' => 'U1HA',
            'SS04_2' => '000002',
            'KRMK' => '02',
            'HA48' => '2020',
            'HA49' => '00010000',
            'LA09' => 'B987654321',
            'MB22_2' => '0001',
            'FLAG' => '2',
            'SS06' => '06',
            'SS07' => '1151027',
            'MB23_3' => 1,
            'MB23_2' => 1,
            'MB22_1' => '11510',
            'MB22_3' => 2100,   // x10 = 21000
            'LA21' => 420,      // x10 = 4200
            'KA16' => 15000     // 不乘 10 -> '  15000'
        ]);

        $count = count($lines);

        $fp = fopen($path, 'wb');
        if (!$fp) {
            throw new RuntimeException("無法開啟檔案: {$path}");
        }
        foreach ($lines as $line) {
            fwrite($fp, $line . "\n");
        }
        fwrite($fp, self::trailer($count));
        fclose($fp);

        return ['state' => 'success', 'message' => '已完成，共 ' . $count . ' 筆'];
    }

    /**
     * 直連 Oracle 查詢與產製
     */
    private function exportDirectOracle(string $path, array $batch): array {
        $sdate = self::rocDate($batch['begin'] ?? '');
        $edate = self::rocDate($batch['end'] ?? '');

        // 1. 查詢預設登記原因清單
        $sqlErm06 = "SELECT KCDE_2 FROM SRKEYN WHERE KCDE_1='06' AND KCDE_2 NOT LIKE '/*%' ORDER BY KCDE_2";
        $erm06Rows = $this->query($sqlErm06);
        if (empty($erm06Rows)) {
            file_put_contents($path, self::trailer(0));
            return ['state' => 'empty', 'message' => '無資料'];
        }

        $codes = [];
        foreach ($erm06Rows as $r) {
            if (!empty($r['KCDE_2'])) {
                $codes[] = "'" . addslashes(trim($r['KCDE_2'])) . "'";
            }
        }
        if (empty($codes)) {
            file_put_contents($path, self::trailer(0));
            return ['state' => 'empty', 'message' => '無資料'];
        }
        $erm06In = implode(',', $codes);

        // 2. 主查詢 VPSCRN / VPHIND
        $sqlMaster = "SELECT SS03, SS04_1, SS04_2, SS06, SS07, SP_DATE, HH00, HA48, HA49, HB01, HB09, HH07, HH_TYPE
                      FROM VPHIND, VPSCRN
                      WHERE (SP_DATE BETWEEN '$sdate' AND '$edate')
                        AND SS06 IN ($erm06In)
                        AND SP_CODE = 'F'
                        AND HI03 = SS03
                        AND HI04_1 = SS04_1
                        AND HI04_2 = SS04_2
                        AND HH00 = 'P'
                      ORDER BY SP_DATE, SS03, SS04_1, SS04_2, HA48, HA49, HB01";
        $cases = $this->query($sqlMaster);
        if (empty($cases)) {
            file_put_contents($path, self::trailer(0));
            return ['state' => 'empty', 'message' => '無資料'];
        }

        $fp = fopen($path, 'wb');
        if (!$fp) {
            throw new RuntimeException("無法建立檔案: {$path}");
        }

        $count = 0;
        $prevKey = null;
        $krmkCache = [];

        foreach ($cases as $c) {
            $ss03 = trim($c['SS03'] ?? '');
            $ss04_1 = trim($c['SS04_1'] ?? '');
            $ss04_2 = trim($c['SS04_2'] ?? '');
            $ha48 = trim($c['HA48'] ?? '');
            $ha49 = trim($c['HA49'] ?? '');
            $ss06 = trim($c['SS06'] ?? '');
            $ss07 = trim($c['SS07'] ?? '');

            // 相鄰地號去重
            $key = "{$ss03}|{$ss04_1}|{$ss04_2}|{$ha48}|{$ha49}";
            if ($key === $prevKey) {
                continue;
            }
            $prevKey = $key;

            // 查鄉鎮市區代碼
            if (!isset($krmkCache[$ha48])) {
                $sqlKrmk = "SELECT KRMK FROM SRKEYN WHERE (KCDE_1='48') AND (KCDE_2='$ha48') AND 1=1";
                $kRows = $this->query($sqlKrmk);
                $krmkCache[$ha48] = !empty($kRows[0]['KRMK']) ? trim($kRows[0]['KRMK']) : '';
            }
            $krmk = $krmkCache[$ha48];

            // 查公告現值 (WPALID)
            $sqlPalid = "SELECT KA16, KA_TYPE FROM WPALID
                         WHERE KA03 = '$ss03'
                           AND KA04_1 = '$ss04_1'
                           AND KA04_2 = '$ss04_2'
                           AND KA48 = '$ha48'
                           AND KA49 = '$ha49'
                           AND KA_TYPE IN ('D', 'N')
                         ORDER BY KA_TYPE";
            $palidRows = $this->query($sqlPalid);
            $firstKa16 = !empty($palidRows[0]['KA16']) ? trim($palidRows[0]['KA16']) : '';
            $ka16Formatted = $firstKa16 !== '' ? self::numField($firstKa16, 7, 0) : '       ';

            // 查地價所有權部異動 (WPBLOW)
            $sqlBlow = "SELECT LA01, LA09, LA_TYPE, LA21 FROM WPBLOW
                        WHERE LA03 = '$ss03'
                          AND LA04_1 = '$ss04_1'
                          AND LA04_2 = '$ss04_2'
                          AND LA48 = '$ha48'
                          AND LA49 = '$ha49'
                          AND LA_TYPE IN ('D', 'M')
                        ORDER BY LA01, LA_TYPE";
            $blowRows = $this->query($sqlBlow);

            foreach ($blowRows as $b) {
                $la01 = trim($b['LA01'] ?? '');
                $la09 = trim($b['LA09'] ?? '');
                $laType = trim($b['LA_TYPE'] ?? '');

                if ($laType === 'M') {
                    $flag = '3';
                    $sqlN = "SELECT LA21 FROM WPBLOW
                             WHERE LA03 = '$ss03'
                               AND LA04_1 = '$ss04_1'
                               AND LA04_2 = '$ss04_2'
                               AND LA48 = '$ha48'
                               AND LA49 = '$ha49'
                               AND LA01 = '$la01'
                               AND LA_TYPE = 'N'
                               AND 1=1";
                    $nRows = $this->query($sqlN);
                    $la21Val = !empty($nRows[0]['LA21']) ? (float)$nRows[0]['LA21'] : 0.0;
                } else {
                    $flag = '2';
                    $la21Val = !empty($b['LA21']) ? (float)$b['LA21'] : 0.0;
                }
                $la21X10 = $la21Val * 10.0;

                // 查歷次規定地價/前次移轉現值 (WPPRCE)
                $sqlPrce = "SELECT MB22_1, MB22_2, MB22_3, MB23_1, MB23_2, MB23_3, MA_TYPE
                            FROM WPPRCE
                            WHERE MA03 = '$ss03'
                              AND MA04_1 = '$ss04_1'
                              AND MA04_2 = '$ss04_2'
                              AND MA48 = '$ha48'
                              AND MA49 = '$ha49'
                              AND MA01 = '$la01'
                              AND MA_TYPE = '$laType'
                              AND 1=1
                            ORDER BY MB22_1, MB22_2";
                $prceRows = $this->query($sqlPrce);

                foreach ($prceRows as $p) {
                    $mb22_3Val = !empty($p['MB22_3']) ? (float)$p['MB22_3'] : 0.0;
                    $row = [
                        'SS03' => $ss03,
                        'SS04_1' => $ss04_1,
                        'SS04_2' => $ss04_2,
                        'KRMK' => $krmk,
                        'HA48' => $ha48,
                        'HA49' => $ha49,
                        'LA09' => $la09,
                        'MB22_2' => trim($p['MB22_2'] ?? ''),
                        'FLAG' => $flag,
                        'SS06' => $ss06,
                        'SS07' => $ss07,
                        'MB23_3' => $p['MB23_3'] ?? 0,
                        'MB23_2' => $p['MB23_2'] ?? 0,
                        'MB22_1' => trim($p['MB22_1'] ?? ''),
                        'MB22_3_X10' => $mb22_3Val * 10.0,
                        'LA21_X10' => $la21X10
                    ];
                    $line = self::formatRecord($row, $ka16Formatted);
                    fwrite($fp, $line . "\n");
                    $count++;
                }

                // 僅印在該地號第一筆 WPBLOW 展開列，其餘筆填 7 個半形空白
                $ka16Formatted = '       ';
            }
        }

        fwrite($fp, self::trailer($count));
        fclose($fp);

        return [
            'state' => $count > 0 ? 'success' : 'empty',
            'message' => $count > 0 ? "已完成，共 {$count} 筆" : '無資料'
        ];
    }

    /**
     * 執行 SQL 查詢
     */
    private function query(string $sql): array {
        if ($this->dbSelector !== null) {
            return call_user_func($this->dbSelector, $sql);
        }
        if (!class_exists('OraDBWrapper')) {
            require_once dirname(__DIR__) . '/OraDBWrapper.class.php';
        }
        $wrapper = new OraDBWrapper();
        if (!$wrapper->reachable()) {
            throw new RuntimeException('無法連線至地籍資料庫。');
        }
        $db = $wrapper->getDB();
        $db->parse($sql);
        $db->execute();
        return $db->fetchAll(true);
    }

    /**
     * 判斷是否應降級至 MOCK 模式
     */
    private function shouldFallbackToMock(): bool {
        if (class_exists('System', false)) {
            $system = System::getInstance();
            if ($system->isMockMode()) {
                return true;
            }
        }
        if (getenv('MOCK') === 'true' || getenv('MOCK') === '1') {
            return true;
        }
        if (!class_exists('OraDBWrapper')) {
            $file = dirname(__DIR__) . '/OraDBWrapper.class.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
        if (class_exists('OraDBWrapper')) {
            $wrapper = new OraDBWrapper();
            if (!$wrapper->reachable()) {
                return true;
            }
        }
        return false;
    }
}
