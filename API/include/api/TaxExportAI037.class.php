<?php

final class TaxExportAI037 {
    /**
     * @var callable|null
     */
    private $dbSelector;

    public function __construct($dbSelector = null) {
        $this->dbSelector = $dbSelector;
    }

    /**
     * 執行 AI037 建物異動資料匯出
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
     * 產製檔尾 TAG
     */
    public static function trailer(int $count): string {
        return mb_convert_encoding('##### TAG #####共產製 ' . $count . ' 筆資料', 'CP950', 'UTF-8');
    }

    /**
     * 格式化 D 紀錄 (建物標示部, 106 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-5s%-7s%-2s%-7s%-2s%10.2f%-2s%-2s%-50s
     *
     * @param array $row 資料列
     * @param string $mode 'branch1' | 'normal'
     * @param string $type 異動別 (若指定則覆蓋 $row['TYPE'])
     */
    public static function formatRecordD(array $row, string $mode = 'normal', string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'D';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? 'X'), 1);
        $dd48 = self::field($row['DD48'] ?? '', 4);
        $dd49Raw = (string)($row['DD49'] ?? '');
        $dd49 = self::field(substr($dd49Raw, 0, 5), 5); // 截斷為前 5 碼
        $rm33 = self::field($row['RM33'] ?? '', 7);
        $rm36 = self::field($row['RM36'] ?? '', 2);
        $dd05 = self::field($row['RM34'] ?? ($row['DD05'] ?? ''), 7);
        $dd06 = self::field($row['RM09'] ?? ($row['DD06'] ?? ''), 2);
        $dd08 = self::numField($row['DD08'] ?? '', 10, 2);

        if ($mode === 'branch1') {
            // 分支 1 歷史行為：
            // 1. 僅 TYPE 為 'M' 或 'D' 時印出 DD46，'A' 或 'N' 時為空白
            $rawType = trim($t);
            if ($rawType === 'M' || $rawType === 'D') {
                $dd46 = self::field($row['DD46'] ?? '', 2);
            } else {
                $dd46 = self::field('', 2);
            }
            // 2. DD45 與 DD09 變數誤傳恆補空白（共 52 空白）
            $dd45 = self::field('', 2);
            $dd09 = self::field('', 50);
        } else {
            $dd46 = self::field($row['DD46'] ?? '', 2);
            $dd45 = self::field($row['DD45'] ?? '', 2);
            $dd09 = self::field($row['DD09'] ?? '', 50);
        }

        return $rm01 . $rm02 . $rm03 . $dept . $t . $dd48 . $dd49 . $rm33 . $rm36 .
               $dd05 . $dd06 . $dd08 . $dd46 . $dd45 . $dd09;
    }

    /**
     * 格式化 E 紀錄 (建物所有權部, 227 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-4s%-10s%-60s%-1s%-90s%1s%8.0f%8.0f%10.1f%-7s%-7s%-2s
     */
    public static function formatRecordE(array $row, string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'E';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? ''), 1);
        $ee01 = self::field($row['EE01'] ?? '', 4);
        $ee05Raw = (string)($row['EE05'] ?? '');
        $ee05 = self::field(substr($ee05Raw, 0, 4), 4); // 截斷為前 4 碼
        $ee09 = self::field($row['EE09'] ?? '', 10);
        $lnam = self::field($row['LNAM'] ?? '', 60);
        $lcde = self::field($row['LCDE'] ?? '', 1);
        $ladr = self::field($row['LADR'] ?? '', 90);
        $ee15_1 = self::field($row['EE15_1'] ?? '', -1);
        $ee15_3 = self::numField($row['EE15_3'] ?? '', 8, 0); // EE15_3 在前
        $ee15_2 = self::numField($row['EE15_2'] ?? '', 8, 0); // EE15_2 在後
        $fixed0 = self::numField(0.0, 10, 1); // 第 15 欄固定為 "       0.0"
        $ee07 = self::field($row['RM34'] ?? ($row['EE07'] ?? ''), 7);
        $rm33 = self::field($row['RM33'] ?? '', 7);
        $ee06 = self::field($row['RM09'] ?? ($row['EE06'] ?? ''), 2);

        return $rm01 . $rm02 . $rm03 . $dept . $t . $ee01 . $ee05 . $ee09 . $lnam .
               $lcde . $ladr . $ee15_1 . $ee15_3 . $ee15_2 . $fixed0 . $ee07 .
               $rm33 . $ee06;
    }

    /**
     * 格式化 G 紀錄 (建物標示部/所有權部其他登記事項, 1021 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-2s%-1000s
     */
    public static function formatRecordG(array $row, string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'G';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? ''), 1);
        $gg01 = self::field($row['GG01'] ?? ($row['EE01'] ?? ''), 4);
        $gg30_1 = self::field($row['GG30_1'] ?? '', 2);
        $gg30_2 = self::field($row['GG30_2'] ?? '', 1000);

        return $rm01 . $rm02 . $rm03 . $dept . $t . $gg01 . $gg30_1 . $gg30_2;
    }

    /**
     * 格式化 H 紀錄 (建物基地坐落 WRHD10, 29 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-8s%-1s%1s
     */
    public static function formatRecordH(array $row, string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'H';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? ''), 1);
        $ha48 = self::field($row['HA48'] ?? '', 4);
        $ha49 = self::field($row['HA49'] ?? '', 8);
        $hd01Raw = (string)($row['HD01'] ?? '');
        $hd01 = self::field(substr($hd01Raw, 0, 1), 1);
        $hhFlag = self::field($row['HH_FLAG'] ?? '', -1);

        return $rm01 . $rm02 . $rm03 . $dept . $t . $ha48 . $ha49 . $hd01 . $hhFlag;
    }

    /**
     * 格式化 M 紀錄 (建物管理者資料, 180 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-10s%-60s%-1s%-90s
     */
    public static function formatRecordM(array $row, string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'M';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? ''), 1);
        $mm01 = self::field($row['EE01'] ?? ($row['MM01'] ?? ''), 4);
        $mm13 = self::field($row['EE09'] ?? ($row['MM13'] ?? ''), 10);
        $lnam = self::field($row['LNAM'] ?? '', 60);
        $lcde = self::field($row['LCDE'] ?? '', 1);
        $ladr = self::field($row['LADR'] ?? '', 90);

        return $rm01 . $rm02 . $rm03 . $dept . $t . $mm01 . $mm13 . $lnam . $lcde . $ladr;
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
     * MOCK 合成資料產製 (相容本所案件、他所案件與管理者異動案件)
     */
    private function exportMock(string $path, array $batch): array {
        // 本所案件 D 紀錄 (106 bytes, 分支 1 模式: 末尾 52 bytes 為空白)
        $d1 = self::formatRecordD([
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'DD48' => '1010',
            'DD49' => '00123000', // 截斷為前 5 碼 00123
            'RM33' => '1151027',
            'RM36' => '01',
            'RM34' => '1151029',
            'RM09' => '01',
            'DD08' => '150.25',
            'DD46' => '01',
            'DD45' => 'HA',
            'DD09' => '桃園市桃園區復興路１號'
        ], 'branch1');

        // 本所案件 H 紀錄 (29 bytes, 基地坐落)
        $h1 = self::formatRecordH([
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'HA48' => '1010',
            'HA49' => '02101940',
            'HD01' => '1',
            'HH_FLAG' => '1'
        ]);

        // 本所案件 E 紀錄 (227 bytes)
        $e1 = self::formatRecordE([
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'EE01' => '0001',
            'EE05' => '1151',
            'EE09' => 'A123456789',
            'LNAM' => '本所測試所有權人',
            'LCDE' => '1',
            'LADR' => '桃園市桃園區復興路１號',
            'EE15_1' => '1',
            'EE15_3' => '1',
            'EE15_2' => '1',
            'RM34' => '1151027',
            'RM33' => '1151029',
            'RM09' => '01'
        ]);

        // 本所案件 G 紀錄 (1021 bytes, 其他登記事項)
        $g1 = self::formatRecordG([
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'GG01' => '0001',
            'GG30_1' => '01',
            'GG30_2' => '本所建物測試登記事項內容'
        ]);

        // 他所承辦本所案件 D 紀錄 (106 bytes, 分支 2 模式: 正常輸出門牌 DD09 與縣市 DD45)
        $d2 = self::formatRecordD([
            'RM01' => '115',
            'RM02' => 'U1HA',
            'RM03' => '000002',
            'TYPE' => 'X',
            'DD48' => '1010',
            'DD49' => '00456000',
            'RM33' => '1151027',
            'RM36' => '01',
            'RM34' => '1151029',
            'RM09' => '01',
            'DD08' => '230.80',
            'DD46' => '01',
            'DD45' => 'HA',
            'DD09' => '桃園市中壢區中央路２號'
        ], 'normal', 'X');

        // 他所承辦本所案件 E 紀錄 (227 bytes)
        $e2 = self::formatRecordE([
            'RM01' => '115',
            'RM02' => 'U1HA',
            'RM03' => '000002',
            'TYPE' => 'A',
            'EE01' => '0001',
            'EE05' => '1151',
            'EE09' => 'B987654321',
            'LNAM' => '他所跨所所有權人',
            'LCDE' => '1',
            'LADR' => '桃園市中壢區中央路２號',
            'EE15_1' => '1',
            'EE15_3' => '1',
            'EE15_2' => '2',
            'RM34' => '1151027',
            'RM33' => '1151029',
            'RM09' => '01'
        ]);

        // 管理者異動案 M 紀錄 (180 bytes)
        $m1 = self::formatRecordM([
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000003',
            'TYPE' => 'M',
            'EE01' => '0001',
            'EE09' => 'C112233445',
            'LNAM' => '測試管理者',
            'LCDE' => '1',
            'LADR' => '桃園市八德區介壽路一段'
        ]);

        $lines = [$d1, $h1, $e1, $g1, $d2, $e2, $m1];
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
        $sdate = self::rocDate($batch['begin']);
        $edate = self::rocDate($batch['end']);

        // 1. 查詢本所代碼
        $sqlKrmk = "SELECT KCNT, KRMK FROM SRKEYN WHERE (KCDE_1='55') AND (KCDE_2='01') AND 1=1";
        $krmkRows = $this->query($sqlKrmk);
        $krmk = !empty($krmkRows[0]['KRMK']) ? trim($krmkRows[0]['KRMK']) : 'HA';
        $krmkSub1 = substr($krmk, 0, 1);

        // 2. 查詢建物預設登記原因清單 (SUBSTR(KRMK,14,1)='1')
        $sqlErm06 = "SELECT KCDE_2 FROM SRKEYN WHERE KCDE_1='06' AND KCDE_2 NOT LIKE '/*%' AND SUBSTR(KRMK,14,1)='1' ORDER BY KCDE_2";
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

        // 3. 查詢主案件
        $sqlMaster = "SELECT A.RM01, A.RM02, A.RM03, A.RM09, A.RM33, A.RM34, A.RM36
                      FROM SCRSMS A
                      WHERE (A.RM09 IN ($erm06In))
                        AND (A.RM40 >= '$sdate' AND A.RM40 <= '$edate')
                        AND (A.RM39 = 'F')
                        AND ((A.RM99 = 'N' OR A.RM99 IS NULL)
                             OR (A.RM99 = 'Y' AND (A.RM100 = '$krmk') AND (A.RM100_1 = '$krmkSub1')))
                      ORDER BY A.RM01, A.RM02, A.RM03";
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
        foreach ($cases as $c) {
            $rm01 = trim($c['RM01'] ?? '');
            $rm02 = trim($c['RM02'] ?? '');
            $rm03 = trim($c['RM03'] ?? '');

            // 查詢 TRINDX 統計
            $sqlTrindx = "SELECT II00, IP_TYPE, COUNT(*) AS CNT
                          FROM TRINDX
                          WHERE II03 = '$rm01' AND II04_1 = '$rm02' AND II04_2 = '$rm03'
                          GROUP BY II00, IP_TYPE";
            $trRows = $this->query($sqlTrindx);
            $hasD = false;
            $hasE = false;
            $hasL = false;
            $hasM = false;
            foreach ($trRows as $tr) {
                $ii00 = trim($tr['II00'] ?? '');
                $ipType = trim($tr['IP_TYPE'] ?? '');
                $cnt = (int)($tr['CNT'] ?? 0);
                if ($cnt <= 0) continue;
                if ($ii00 === 'D' && in_array($ipType, ['A', 'D', 'M'], true)) {
                    $hasD = true;
                } elseif ($ii00 === 'E' && in_array($ipType, ['A', 'D', 'M'], true)) {
                    $hasE = true;
                } elseif ($ii00 === 'L' && in_array($ipType, ['A', 'D', 'M'], true)) {
                    $hasL = true;
                } elseif ($ii00 === 'E' && $ipType === 'l') {
                    $hasL = true;
                } elseif ($ii00 === 'M' && in_array($ipType, ['A', 'D', 'M'], true)) {
                    $hasM = true;
                }
            }

            if ($hasD) {
                $count += $this->processBranch1($fp, $c);
            } elseif ($hasE) {
                $count += $this->processBranch2($fp, $c);
            } elseif ($hasL) {
                $count += $this->processBranch3($fp, $c);
            } elseif ($hasM) {
                $count += $this->processBranch4($fp, $c);
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
     * 分支 1：含建物標示部異動 (WRDBID / WRGALL / WRHD10 / WREBOW / WRMNGR)
     */
    private function processBranch1($fp, array $c): int {
        $rm01 = trim($c['RM01'] ?? '');
        $rm02 = trim($c['RM02'] ?? '');
        $rm03 = trim($c['RM03'] ?? '');

        $sql = "SELECT 'D' AS DEPT, '0' AS SEQ, DS_TYPE AS TYPE, DD48, DD49, DD45, DD46, '0000' AS EE01, '' AS EE09, DD05 AS RM34, '' AS EE05, DD06 AS RM09, TO_CHAR(DD08) AS DD08, DD09, '' AS EE15_1, '' AS EE15_2, '' AS EE15_3, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRDBID
                WHERE (DS03='$rm01') AND (DS04_1='$rm02') AND (DS04_2='$rm03') AND (DS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'G' AS DEPT, '1' AS SEQ, GS_TYPE AS TYPE, GG48 AS DD48, GG49 AS DD49, '' AS DD45, '' AS DD46, GG01 AS EE01, '' AS EE09, '' AS RM34, '' AS EE05, '' AS RM09, '' AS DD08, '' AS DD09, '' AS EE15_1, '' AS EE15_2, '' AS EE15_3, GG01, GG30_1, GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRGALL
                WHERE (GS03='$rm01') AND (GS04_1='$rm02') AND (GS04_2='$rm03') AND (GG00='D') AND (GS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'H' AS DEPT, '2' AS SEQ, HS_TYPE AS TYPE, HD48 AS DD48, HD49 AS DD49, '' AS DD45, '' AS DD46, '0000' AS EE01, '' AS EE09, '' AS RM34, '' AS EE05, '' AS RM09, '' AS DD08, '' AS DD09, '' AS EE15_1, '' AS EE15_2, '' AS EE15_3, '' AS GG01, '' AS GG30_1, '' AS GG30_2, HA48, HA49, '' AS HD01, HH_FLAG, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRHD10
                WHERE (HS03='$rm01') AND (HS04_1='$rm02') AND (HS04_2='$rm03') AND (HS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'E' AS DEPT, '3' AS SEQ, ES_TYPE AS TYPE, ED48 AS DD48, ED49 AS DD49, '' AS DD45, '' AS DD46, EE01, EE09, EE07 AS RM34, EE05, EE06 AS RM09, '' AS DD08, '' AS DD09, EE15_1, TO_CHAR(EE15_2) AS EE15_2, TO_CHAR(EE15_3) AS EE15_3, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WREBOW
                WHERE (ES03='$rm01') AND (ES04_1='$rm02') AND (ES04_2='$rm03') AND (ES_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'G' AS DEPT, '4' AS SEQ, GS_TYPE AS TYPE, GG48 AS DD48, GG49 AS DD49, '' AS DD45, '' AS DD46, GG01 AS EE01, '' AS EE09, '' AS RM34, '' AS EE05, '' AS RM09, '' AS DD08, '' AS DD09, '' AS EE15_1, '' AS EE15_2, '' AS EE15_3, GG01, GG30_1, GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRGALL
                WHERE (GS03='$rm01') AND (GS04_1='$rm02') AND (GS04_2='$rm03') AND (GG00='E') AND (GS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'M' AS DEPT, '5' AS SEQ, MS_TYPE AS TYPE, MM48 AS DD48, MM49 AS DD49, '' AS DD45, '' AS DD46, MM01 AS EE01, MM13 AS EE09, '' AS RM34, '' AS EE05, '' AS RM09, '' AS DD08, '' AS DD09, '' AS EE15_1, '' AS EE15_2, '' AS EE15_3, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, LNAM, LCDE, LADR
                FROM SRLNID, WRMNGR
                WHERE (MS03='$rm01') AND (MS04_1='$rm02') AND (MS04_2='$rm03') AND (MM00='E') AND (MS_TYPE IN ('A', 'D', 'M', 'N')) AND LIDN=MM13
                ORDER BY DD48, DD49, EE01, SEQ, DEPT";
        
        $rows = $this->query($sql);
        $count = 0;
        foreach ($rows as $r) {
            $r['RM01'] = $rm01;
            $r['RM02'] = $rm02;
            $r['RM03'] = $rm03;
            $r['RM33'] = $c['RM33'] ?? '';
            $r['RM36'] = $c['RM36'] ?? '';

            $dept = trim($r['DEPT'] ?? '');
            if ($dept === 'D') {
                $line = self::formatRecordD($r, 'branch1');
            } elseif ($dept === 'H') {
                $line = self::formatRecordH($r);
            } elseif ($dept === 'E') {
                $ee09 = trim($r['EE09'] ?? '');
                if ($ee09 !== '') {
                    $sqlL = "SELECT LIDN, LNAM, LCDE, LADR FROM SRLNID WHERE LIDN = '$ee09' AND 1=1";
                    $lRows = $this->query($sqlL);
                    if (!empty($lRows[0])) {
                        $r['LNAM'] = $lRows[0]['LNAM'] ?? '';
                        $r['LCDE'] = $lRows[0]['LCDE'] ?? '';
                        $r['LADR'] = $lRows[0]['LADR'] ?? '';
                    }
                }
                $line = self::formatRecordE($r);
            } elseif ($dept === 'G') {
                $line = self::formatRecordG($r);
            } elseif ($dept === 'M') {
                $line = self::formatRecordM($r);
            } else {
                continue;
            }
            fwrite($fp, $line . "\n");
            $count++;
        }
        return $count;
    }

    /**
     * 分支 2：無標示部異動，僅建物所有權部異動 (SRDBID / WREBOW / WRGALL / WRMNGR)
     */
    private function processBranch2($fp, array $c): int {
        $rm01 = trim($c['RM01'] ?? '');
        $rm02 = trim($c['RM02'] ?? '');
        $rm03 = trim($c['RM03'] ?? '');

        $sql = "SELECT 'D' AS DEPT, '0' AS SEQ, 'X' AS TYPE, DD48, DD49, DD45, DD46, '0000' AS EE01, '' AS EE09, DD05 AS RM34, '' AS EE05, DD06 AS RM09, TO_CHAR(DD08) AS DD08, DD09, '' AS EE15_1, '' AS EE15_2, '' AS EE15_3, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM SRDBID
                WHERE (DD48||DD49) IN (
                  SELECT DISTINCT SUBSTR(IIKEY,2,12) FROM TRINDX
                  WHERE (II03='$rm01') AND (II04_1='$rm02') AND (II04_2='$rm03') AND (II00='E') AND (IP_TYPE IN ('A', 'D', 'M', 'N'))
                )
                UNION
                SELECT 'E' AS DEPT, '3' AS SEQ, ES_TYPE AS TYPE, ED48 AS DD48, ED49 AS DD49, '' AS DD45, '' AS DD46, EE01, EE09, EE07 AS RM34, EE05, EE06 AS RM09, '' AS DD08, '' AS DD09, EE15_1, TO_CHAR(EE15_2) AS EE15_2, TO_CHAR(EE15_3) AS EE15_3, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WREBOW
                WHERE (ES03='$rm01') AND (ES04_1='$rm02') AND (ES04_2='$rm03') AND (ES_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'G' AS DEPT, '4' AS SEQ, GS_TYPE AS TYPE, GG48 AS DD48, GG49 AS DD49, '' AS DD45, '' AS DD46, GG01 AS EE01, '' AS EE09, '' AS RM34, '' AS EE05, '' AS RM09, '' AS DD08, '' AS DD09, '' AS EE15_1, '' AS EE15_2, '' AS EE15_3, GG01, GG30_1, GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRGALL
                WHERE (GS03='$rm01') AND (GS04_1='$rm02') AND (GS04_2='$rm03') AND (GG00='E') AND (GS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'M' AS DEPT, '5' AS SEQ, MS_TYPE AS TYPE, MM48 AS DD48, MM49 AS DD49, '' AS DD45, '' AS DD46, MM01 AS EE01, MM13 AS EE09, '' AS RM34, '' AS EE05, '' AS RM09, '' AS DD08, '' AS DD09, '' AS EE15_1, '' AS EE15_2, '' AS EE15_3, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS HA48, '' AS HA49, '' AS HD01, '' AS HH_FLAG, LNAM, LCDE, LADR
                FROM SRLNID, WRMNGR
                WHERE (MS03='$rm01') AND (MS04_1='$rm02') AND (MS04_2='$rm03') AND (MM00='E') AND (MS_TYPE IN ('A', 'D', 'M', 'N')) AND LIDN=MM13
                ORDER BY DD48, DD49, EE01, SEQ, DEPT";
        
        $rows = $this->query($sql);
        $count = 0;
        foreach ($rows as $r) {
            $r['RM01'] = $rm01;
            $r['RM02'] = $rm02;
            $r['RM03'] = $rm03;
            $r['RM33'] = $c['RM33'] ?? '';
            $r['RM36'] = $c['RM36'] ?? '';

            $dept = trim($r['DEPT'] ?? '');
            if ($dept === 'D') {
                $line = self::formatRecordD($r, 'normal', 'X');
            } elseif ($dept === 'E') {
                $ee09 = trim($r['EE09'] ?? '');
                if ($ee09 !== '') {
                    $sqlL = "SELECT LIDN, LNAM, LCDE, LADR FROM SRLNID WHERE LIDN = '$ee09' AND 1=1";
                    $lRows = $this->query($sqlL);
                    if (!empty($lRows[0])) {
                        $r['LNAM'] = $lRows[0]['LNAM'] ?? '';
                        $r['LCDE'] = $lRows[0]['LCDE'] ?? '';
                        $r['LADR'] = $lRows[0]['LADR'] ?? '';
                    }
                }
                $line = self::formatRecordE($r);
            } elseif ($dept === 'G') {
                $line = self::formatRecordG($r);
            } elseif ($dept === 'M') {
                $line = self::formatRecordM($r);
            } else {
                continue;
            }
            fwrite($fp, $line . "\n");
            $count++;
        }
        return $count;
    }

    /**
     * 分支 3：建物更正異動 (SRDBID / WRLNID)
     */
    private function processBranch3($fp, array $c): int {
        $rm01 = trim($c['RM01'] ?? '');
        $rm02 = trim($c['RM02'] ?? '');
        $rm03 = trim($c['RM03'] ?? '');
        $rm09 = trim($c['RM09'] ?? '');

        $sql = "SELECT 'D' AS DEPT, '0' AS SEQ, 'X' AS TYPE, DD48, DD49, DD45, DD46, '' AS EE09, DD05 AS RM34, DD06 AS RM09, TO_CHAR(DD08) AS DD08, DD09, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM SRDBID
                WHERE (DD48||DD49) IN (
                  SELECT DISTINCT SUBSTR(IIKEY,2,12) FROM TRINDX
                  WHERE (II03='$rm01') AND (II04_1='$rm02') AND (II04_2='$rm03') AND (II00='E') AND (IP_TYPE = 'l')
                )
                UNION
                SELECT 'E' AS DEPT, '1' AS SEQ, LS_TYPE AS TYPE, '' AS DD48, '' AS DD49, '' AS DD45, '' AS DD46, LIDN AS EE09, '' AS RM34, '' AS RM09, '' AS DD08, '' AS DD09, LNAM, LCDE, LADR
                FROM WRLNID
                WHERE (LS03='$rm01') AND (LS04_1='$rm02') AND (LS04_2='$rm03') AND (LS_TYPE IN ('A', 'D', 'M', 'N'))
                ORDER BY SEQ, DD48, DD49, EE09";
        
        $rows = $this->query($sql);
        $count = 0;
        foreach ($rows as $r) {
            $r['RM01'] = $rm01;
            $r['RM02'] = $rm02;
            $r['RM03'] = $rm03;
            $r['RM33'] = $c['RM33'] ?? '';
            $r['RM36'] = $c['RM36'] ?? '';

            $dept = trim($r['DEPT'] ?? '');
            if ($dept === 'D') {
                $line = self::formatRecordD($r, 'normal', 'X');
            } elseif ($dept === 'E') {
                $r['RM34'] = ''; // 分支 3 之 EE07 固定為空字串
                $r['RM09'] = $rm09; // 分支 3 之登記原因取主案件 RM09
                $line = self::formatRecordE($r);
            } else {
                continue;
            }
            fwrite($fp, $line . "\n");
            $count++;
        }
        return $count;
    }

    /**
     * 分支 4：僅建物管理者異動 (TRINDX / SRDBID / SREBOW / WRMNGR)
     */
    private function processBranch4($fp, array $c): int {
        $rm01 = trim($c['RM01'] ?? '');
        $rm02 = trim($c['RM02'] ?? '');
        $rm03 = trim($c['RM03'] ?? '');

        $sqlIdx = "SELECT IIKEY, IP_TYPE FROM TRINDX
                   WHERE (II03='$rm01') AND (II04_1='$rm02') AND (II04_2='$rm03')
                     AND (II00='M') AND (SUBSTR(IIKEY,1,1)='F') AND 1=1";
        $indexes = $this->query($sqlIdx);
        $count = 0;

        foreach ($indexes as $idx) {
            $iikey = trim($idx['IIKEY'] ?? '');
            if (strlen($iikey) < 17) {
                continue;
            }
            $sdd48 = substr($iikey, 1, 4);
            $sdd49 = substr($iikey, 5, 8);
            $see01 = substr($iikey, 13, 4);

            // [qry10] SRDBID
            $sqlD = "SELECT DD48, DD49, DD05, DD06, DD08, DD45, DD46, DD09
                     FROM SRDBID
                     WHERE (DD48='$sdd48') AND (DD49='$sdd49') AND 1=1";
            $dRows = $this->query($sqlD);
            foreach ($dRows as $r) {
                $r['RM01'] = $rm01;
                $r['RM02'] = $rm02;
                $r['RM03'] = $rm03;
                $r['RM33'] = $c['RM33'] ?? '';
                $r['RM36'] = $c['RM36'] ?? '';
                $line = self::formatRecordD($r, 'normal', 'X');
                fwrite($fp, $line . "\n");
                $count++;
            }

            // [qry5] SREBOW
            $sqlE = "SELECT ED48, ED49, EE01, EE05, EE06, EE07, EE09, EE15_1, EE15_2, EE15_3
                     FROM SREBOW
                     WHERE (ED48='$sdd48') AND (ED49='$sdd49') AND (EE01='$see01') AND 1=1";
            $eRows = $this->query($sqlE);
            foreach ($eRows as $r) {
                $r['RM01'] = $rm01;
                $r['RM02'] = $rm02;
                $r['RM03'] = $rm03;
                $r['RM33'] = $c['RM33'] ?? '';
                $r['RM36'] = $c['RM36'] ?? '';
                $r['LNAM'] = '';
                $r['LCDE'] = '';
                $r['LADR'] = '';
                $line = self::formatRecordE($r, 'X');
                fwrite($fp, $line . "\n");
                $count++;
            }

            // [qry7_BAD] WRMNGR
            $sqlM = "SELECT MM00, MM48, MM49, MM01, MM13, MS_TYPE
                     FROM WRMNGR
                     WHERE (MS03='$rm01') AND (MS04_1='$rm02') AND (MS04_2='$rm03')
                       AND (MM00='E') AND (MM48='$sdd48') AND (MM49='$sdd49') AND (MM01='$see01')
                       AND (MS_TYPE IN ('A','D','M','N'))";
            $mRows = $this->query($sqlM);
            foreach ($mRows as $r) {
                $r['RM01'] = $rm01;
                $r['RM02'] = $rm02;
                $r['RM03'] = $rm03;
                $mm13 = trim($r['MM13'] ?? '');
                if ($mm13 !== '') {
                    $sqlML = "SELECT LIDN, LNAM, LCDE, LADR FROM SRLNID WHERE LIDN='$mm13' AND 1=1";
                    $mlRows = $this->query($sqlML);
                    if (!empty($mlRows[0])) {
                        $r['LNAM'] = $mlRows[0]['LNAM'] ?? '';
                        $r['LCDE'] = $mlRows[0]['LCDE'] ?? '';
                        $r['LADR'] = $mlRows[0]['LADR'] ?? '';
                    }
                }
                $line = self::formatRecordM($r);
                fwrite($fp, $line . "\n");
                $count++;
            }
        }
        return $count;
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
