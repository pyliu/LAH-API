<?php

final class TaxExportAI001 {
    /**
     * @var callable|null
     */
    private $dbSelector;

    public function __construct($dbSelector = null) {
        $this->dbSelector = $dbSelector;
    }

    /**
     * 執行 AI001 異動資料匯出
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
     * 格式化 A 紀錄 (土地標示部, 169 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-8s%-7s%-2s%-7s%-2s%-2s%10.2f%-2s%-2s%10.1f%10.1f%-2s%-2s%-84s
     */
    public static function formatRecordA(array $row, string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'A';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? 'X'), 1);
        $aa48 = self::field($row['AA48'] ?? '', 4);
        $aa49 = self::field($row['AA49'] ?? '', 8);
        $rm33 = self::field($row['RM33'] ?? '', 7);
        $rm36 = self::field($row['RM36'] ?? '', 2);
        $aa05 = self::field($row['RM34'] ?? ($row['AA05'] ?? ''), 7);
        $aa06 = self::field($row['RM09'] ?? ($row['AA06'] ?? ''), 2);
        $aa08 = self::field($row['AA08'] ?? '', 2);
        $aa10 = self::numField($row['AA10'] ?? '', 10, 2);
        $aa11 = self::field($row['AA11'] ?? '', 2);
        $aa12 = self::field($row['AA12'] ?? '', 2);
        $aa16 = self::numField($row['AA16'] ?? '', 10, 1);
        $aa17 = self::numField($row['AA17'] ?? '', 10, 1);
        $aa46 = self::field($row['AA46'] ?? '', 2);
        $aa45 = self::field($row['AA45'] ?? '', 2);
        $aa09 = self::field($row['AA09'] ?? '', 84);

        return $rm01 . $rm02 . $rm03 . $dept . $t . $aa48 . $aa49 . $rm33 . $rm36 .
               $aa05 . $aa06 . $aa08 . $aa10 . $aa11 . $aa12 . $aa16 . $aa17 .
               $aa46 . $aa45 . $aa09;
    }

    /**
     * 格式化 B 紀錄 (土地所有權部, 227 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-4s%-10s%-60s%-1s%-90s%1s%8.0f%8.0f%10.1f%-7s%-7s%-2s
     */
    public static function formatRecordB(array $row, string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'B';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? ''), 1);
        $bb01 = self::field($row['BB01'] ?? '', 4);
        $bb05 = self::field($row['BB05'] ?? '', 4); // 截斷為 4 碼
        $bb09 = self::field($row['BB09'] ?? '', 10);
        $lnam = self::field($row['LNAM'] ?? '', 60);
        $lcde = self::field($row['LCDE'] ?? '', 1);
        $ladr = self::field($row['LADR'] ?? '', 90);
        $bb15_1 = self::field($row['BB15_1'] ?? '', -1);
        $bb15_3 = self::numField($row['BB15_3'] ?? '', 8, 0); // 注意：BB15_3 在前
        $bb15_2 = self::numField($row['BB15_2'] ?? '', 8, 0); // BB15_2 在後
        $bb21 = self::numField($row['BB21'] ?? '', 10, 1);
        $bb07 = self::field($row['RM34'] ?? ($row['BB07'] ?? ''), 7);
        $rm33 = self::field($row['RM33'] ?? '', 7);
        $bb06 = self::field($row['RM09'] ?? ($row['BB06'] ?? ''), 2);

        return $rm01 . $rm02 . $rm03 . $dept . $t . $bb01 . $bb05 . $bb09 . $lnam .
               $lcde . $ladr . $bb15_1 . $bb15_3 . $bb15_2 . $bb21 . $bb07 .
               $rm33 . $bb06;
    }

    /**
     * 格式化 G 紀錄 (標示部/所有權部其他登記事項, 1021 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-2s%-1000s
     */
    public static function formatRecordG(array $row, string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'G';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? ''), 1);
        $gg01 = self::field($row['GG01'] ?? ($row['BB01'] ?? ''), 4);
        $gg30_1 = self::field($row['GG30_1'] ?? '', 2);
        $gg30_2 = self::field($row['GG30_2'] ?? '', 1000);

        return $rm01 . $rm02 . $rm03 . $dept . $t . $gg01 . $gg30_1 . $gg30_2;
    }

    /**
     * 格式化 M 紀錄 (管理者資料, 180 bytes)
     * Format: %-3s%-4s%-6s%1s%1s%-4s%-10s%-60s%-1s%-90s
     */
    public static function formatRecordM(array $row, string $type = ''): string {
        $rm01 = self::field($row['RM01'] ?? '', 3);
        $rm02 = self::field($row['RM02'] ?? '', 4);
        $rm03 = self::field($row['RM03'] ?? '', 6);
        $dept = 'M';
        $t = self::field($type !== '' ? $type : ($row['TYPE'] ?? ''), 1);
        $mm01 = self::field($row['BB01'] ?? ($row['MM01'] ?? ''), 4);
        $mm13 = self::field($row['BB09'] ?? ($row['MM13'] ?? ''), 10);
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
     * MOCK 合成資料產製 (相容本所案件與他所案件)
     */
    private function exportMock(string $path, array $batch): array {
        $lines = [];

        // 本所案件 A 紀錄 (169 bytes)
        $a1 = self::formatRecordA([
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'AA48' => '1010',
            'AA49' => '02101940',
            'RM33' => '1151027',
            'RM36' => '01',
            'RM34' => '1151029',
            'RM09' => '01',
            'AA08' => '田',
            'AA10' => '4646.00',
            'AA11' => '01',
            'AA12' => '01',
            'AA16' => '12000.0',
            'AA17' => '3500.0',
            'AA46' => '01',
            'AA45' => 'HA',
            'AA09' => '01'
        ]);

        // 本所案件 B 紀錄 (227 bytes)
        $b1 = self::formatRecordB([
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'B',
            'BB01' => '0001',
            'BB05' => '1151',
            'BB09' => 'A123456789',
            'LNAM' => '本所測試所有權人',
            'LCDE' => '1',
            'LADR' => '桃園市桃園區復興路１號',
            'BB15_1' => '1',
            'BB15_3' => '1',
            'BB15_2' => '1',
            'BB21' => '3500.0',
            'RM34' => '1151027',
            'RM33' => '1151029',
            'RM09' => '01'
        ]);

        // 他所承辦本所案件 A 紀錄 (169 bytes)
        $a2 = self::formatRecordA([
            'RM01' => '115',
            'RM02' => 'U1HA',
            'RM03' => '000002',
            'TYPE' => 'A',
            'AA48' => '1010',
            'AA49' => '02101940',
            'RM33' => '1151027',
            'RM36' => '01',
            'RM34' => '1151029',
            'RM09' => '01',
            'AA08' => '田',
            'AA10' => '4646.00',
            'AA11' => '01',
            'AA12' => '01',
            'AA16' => '12000.0',
            'AA17' => '3500.0',
            'AA46' => '01',
            'AA45' => 'HA',
            'AA09' => '01'
        ]);

        // 他所承辦本所案件 B 紀錄 (227 bytes)
        $b2 = self::formatRecordB([
            'RM01' => '115',
            'RM02' => 'U1HA',
            'RM03' => '000002',
            'TYPE' => 'B',
            'BB01' => '0001',
            'BB05' => '1151',
            'BB09' => 'B987654321',
            'LNAM' => '他所跨所所有權人',
            'LCDE' => '1',
            'LADR' => '桃園市中壢區中央路２號',
            'BB15_1' => '1',
            'BB15_3' => '1',
            'BB15_2' => '1',
            'BB21' => '3500.0',
            'RM34' => '1151027',
            'RM33' => '1151029',
            'RM09' => '01'
        ]);

        $lines = [$a1, $b1, $a2, $b2];
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

        // 2. 查詢預設登記原因清單
        $sqlErm06 = "SELECT KCDE_2 FROM SRKEYN WHERE KCDE_1='06' AND KCDE_2 NOT LIKE '/*%' AND SUBSTR(KRMK,7,1)='1' ORDER BY KCDE_2";
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
            $hasA = false;
            $hasB = false;
            $hasL = false;
            $hasM = false;
            foreach ($trRows as $tr) {
                $ii00 = trim($tr['II00'] ?? '');
                $ipType = trim($tr['IP_TYPE'] ?? '');
                $cnt = (int)($tr['CNT'] ?? 0);
                if ($cnt <= 0) continue;
                if ($ii00 === 'A' && in_array($ipType, ['A', 'D', 'M'], true)) {
                    $hasA = true;
                } elseif ($ii00 === 'B' && in_array($ipType, ['A', 'D', 'M'], true)) {
                    $hasB = true;
                } elseif ($ii00 === 'L' && in_array($ipType, ['A', 'D', 'M'], true)) {
                    $hasL = true;
                } elseif ($ii00 === 'B' && $ipType === 'l') {
                    $hasL = true;
                } elseif ($ii00 === 'M' && in_array($ipType, ['A', 'D', 'M'], true)) {
                    $hasM = true;
                }
            }

            if ($hasA) {
                $count += $this->processBranch1($fp, $c);
            } elseif ($hasB) {
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
     * 分支 1：含土地標示部異動 (WRALID / WRGALL / WRBLOW / WRMNGR)
     */
    private function processBranch1($fp, array $c): int {
        $rm01 = trim($c['RM01'] ?? '');
        $rm02 = trim($c['RM02'] ?? '');
        $rm03 = trim($c['RM03'] ?? '');

        $sql = "SELECT 'A' AS DEPT, '0' AS SEQ, AS_TYPE AS TYPE, AA48, AA49, AA45, AA46, '0000' AS BB01, '' AS BB09, AA05 AS RM34, '' AS BB05, AA06 AS RM09, AA08, AA09, TO_CHAR(AA10) AS AA10, AA11, AA12, TO_CHAR(AA16) AS AA16, TO_CHAR(AA17) AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRALID
                WHERE (AS03='$rm01') AND (AS04_1='$rm02') AND (AS04_2='$rm03') AND (AS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'G' AS DEPT, '1' AS SEQ, GS_TYPE AS TYPE, GG48 AS AA48, GG49 AS AA49, '' AS AA45, '' AS AA46, GG01 AS BB01, '' AS BB09, '' AS RM34, '' AS BB05, '' AS RM09, '' AS AA08, '' AS AA09, '' AS AA10, '' AS AA11, '' AS AA12, '' AS AA16, '' AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, GG01, GG30_1, GG30_2, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRGALL
                WHERE (GS03='$rm01') AND (GS04_1='$rm02') AND (GS04_2='$rm03') AND (GG00='A') AND (GS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'B' AS DEPT, '2' AS SEQ, BS_TYPE AS TYPE, BA48 AS AA48, BA49 AS AA49, '' AS AA45, '' AS AA46, BB01, BB09, BB07 AS RM34, BB05, BB06 AS RM09, '' AS AA08, '' AS AA09, '' AS AA10, '' AS AA11, '' AS AA12, '' AS AA16, '' AS AA17, BB15_1, TO_CHAR(BB15_2) AS BB15_2, TO_CHAR(BB15_3) AS BB15_3, TO_CHAR(BB21) AS BB21, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRBLOW
                WHERE (BS03='$rm01') AND (BS04_1='$rm02') AND (BS04_2='$rm03') AND (BS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'G' AS DEPT, '3' AS SEQ, GS_TYPE AS TYPE, GG48 AS AA48, GG49 AS AA49, '' AS AA45, '' AS AA46, GG01 AS BB01, '' AS BB09, '' AS RM34, '' AS BB05, '' AS RM09, '' AS AA08, '' AS AA09, '' AS AA10, '' AS AA11, '' AS AA12, '' AS AA16, '' AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, GG01, GG30_1, GG30_2, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRGALL
                WHERE (GS03='$rm01') AND (GS04_1='$rm02') AND (GS04_2='$rm03') AND (GG00='B') AND (GS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'M' AS DEPT, '4' AS SEQ, MS_TYPE AS TYPE, MM48 AS AA48, MM49 AS AA49, '' AS AA45, '' AS AA46, MM01 AS BB01, MM13 AS BB09, '' AS RM34, '' AS BB05, '' AS RM09, '' AS AA08, '' AS AA09, '' AS AA10, '' AS AA11, '' AS AA12, '' AS AA16, '' AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, '' AS GG01, '' AS GG30_1, '' AS GG30_2, LNAM, LCDE, LADR
                FROM SRLNID, WRMNGR
                WHERE (MS03='$rm01') AND (MS04_1='$rm02') AND (MS04_2='$rm03') AND (MM00='B') AND (MS_TYPE IN ('A', 'D', 'M', 'N')) AND LIDN=MM13
                ORDER BY AA48, AA49, BB01, SEQ, DEPT";
        
        $rows = $this->query($sql);
        $count = 0;
        foreach ($rows as $r) {
            $r['RM01'] = $rm01;
            $r['RM02'] = $rm02;
            $r['RM03'] = $rm03;
            $r['RM33'] = $c['RM33'] ?? '';
            $r['RM36'] = $c['RM36'] ?? '';

            $dept = trim($r['DEPT'] ?? '');
            if ($dept === 'A') {
                $line = self::formatRecordA($r);
            } elseif ($dept === 'B') {
                $bb09 = trim($r['BB09'] ?? '');
                if ($bb09 !== '') {
                    $sqlL = "SELECT LIDN, LNAM, LCDE, LADR FROM SRLNID WHERE LIDN = '$bb09' AND 1=1";
                    $lRows = $this->query($sqlL);
                    if (!empty($lRows[0])) {
                        $r['LNAM'] = $lRows[0]['LNAM'] ?? '';
                        $r['LCDE'] = $lRows[0]['LCDE'] ?? '';
                        $r['LADR'] = $lRows[0]['LADR'] ?? '';
                    }
                }
                $line = self::formatRecordB($r);
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
     * 分支 2：無標示部異動，僅所有權部異動 (SRALID / WRBLOW / WRGALL / WRMNGR)
     */
    private function processBranch2($fp, array $c): int {
        $rm01 = trim($c['RM01'] ?? '');
        $rm02 = trim($c['RM02'] ?? '');
        $rm03 = trim($c['RM03'] ?? '');

        $sql = "SELECT 'A' AS DEPT, '0' AS SEQ, 'X' AS TYPE, AA48, AA49, AA45, AA46, '0000' AS BB01, '' AS BB09, AA05 AS RM34, '' AS BB05, AA06 AS RM09, AA08, AA09, TO_CHAR(AA10) AS AA10, AA11, AA12, TO_CHAR(AA16) AS AA16, TO_CHAR(AA17) AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM SRALID
                WHERE (AA48||AA49) IN (
                  SELECT DISTINCT SUBSTR(IIKEY,2,12) FROM TRINDX
                  WHERE (II03='$rm01') AND (II04_1='$rm02') AND (II04_2='$rm03') AND (II00='B') AND (IP_TYPE IN ('A', 'D', 'M', 'N'))
                )
                UNION
                SELECT 'B' AS DEPT, '2' AS SEQ, BS_TYPE AS TYPE, BA48 AS AA48, BA49 AS AA49, '' AS AA45, '' AS AA46, BB01, BB09, BB07 AS RM34, BB05, BB06 AS RM09, '' AS AA08, '' AS AA09, '' AS AA10, '' AS AA11, '' AS AA12, '' AS AA16, '' AS AA17, BB15_1, TO_CHAR(BB15_2) AS BB15_2, TO_CHAR(BB15_3) AS BB15_3, TO_CHAR(BB21) AS BB21, '' AS GG01, '' AS GG30_1, '' AS GG30_2, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRBLOW
                WHERE (BS03='$rm01') AND (BS04_1='$rm02') AND (BS04_2='$rm03') AND (BS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'G' AS DEPT, '3' AS SEQ, GS_TYPE AS TYPE, GG48 AS AA48, GG49 AS AA49, '' AS AA45, '' AS AA46, GG01 AS BB01, '' AS BB09, '' AS RM34, '' AS BB05, '' AS RM09, '' AS AA08, '' AS AA09, '' AS AA10, '' AS AA11, '' AS AA12, '' AS AA16, '' AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, GG01, GG30_1, GG30_2, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM WRGALL
                WHERE (GS03='$rm01') AND (GS04_1='$rm02') AND (GS04_2='$rm03') AND (GG00='B') AND (GS_TYPE IN ('A', 'D', 'M', 'N'))
                UNION
                SELECT 'M' AS DEPT, '4' AS SEQ, MS_TYPE AS TYPE, MM48 AS AA48, MM49 AS AA49, '' AS AA45, '' AS AA46, MM01 AS BB01, MM13 AS BB09, '' AS RM34, '' AS BB05, '' AS RM09, '' AS AA08, '' AS AA09, '' AS AA10, '' AS AA11, '' AS AA12, '' AS AA16, '' AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, '' AS GG01, '' AS GG30_1, '' AS GG30_2, LNAM, LCDE, LADR
                FROM SRLNID, WRMNGR
                WHERE (MS03='$rm01') AND (MS04_1='$rm02') AND (MS04_2='$rm03') AND (MM00='B') AND (MS_TYPE IN ('A', 'D', 'M', 'N')) AND LIDN=MM13
                ORDER BY AA48, AA49, BB01, SEQ, DEPT";
        
        $rows = $this->query($sql);
        $count = 0;
        foreach ($rows as $r) {
            $r['RM01'] = $rm01;
            $r['RM02'] = $rm02;
            $r['RM03'] = $rm03;
            $r['RM33'] = $c['RM33'] ?? '';
            $r['RM36'] = $c['RM36'] ?? '';

            $dept = trim($r['DEPT'] ?? '');
            if ($dept === 'A') {
                $line = self::formatRecordA($r, 'X');
            } elseif ($dept === 'B') {
                $bb09 = trim($r['BB09'] ?? '');
                if ($bb09 !== '') {
                    $sqlL = "SELECT LIDN, LNAM, LCDE, LADR FROM SRLNID WHERE LIDN = '$bb09' AND 1=1";
                    $lRows = $this->query($sqlL);
                    if (!empty($lRows[0])) {
                        $r['LNAM'] = $lRows[0]['LNAM'] ?? '';
                        $r['LCDE'] = $lRows[0]['LCDE'] ?? '';
                        $r['LADR'] = $lRows[0]['LADR'] ?? '';
                    }
                }
                $line = self::formatRecordB($r);
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
     * 分支 3：統編/住址更正異動 (SRALID / WRLNID)
     */
    private function processBranch3($fp, array $c): int {
        $rm01 = trim($c['RM01'] ?? '');
        $rm02 = trim($c['RM02'] ?? '');
        $rm03 = trim($c['RM03'] ?? '');
        $rm09 = trim($c['RM09'] ?? '');

        $sql = "SELECT 'A' AS DEPT, '0' AS SEQ, 'X' AS TYPE, AA48, AA49, AA45, AA46, '' AS BB01, '' AS BB09, AA05 AS RM34, '' AS BB05, AA06 AS RM09, AA08, AA09, TO_CHAR(AA10) AS AA10, AA11, AA12, TO_CHAR(AA16) AS AA16, TO_CHAR(AA17) AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, '' AS LNAM, '' AS LCDE, '' AS LADR
                FROM SRALID
                WHERE (AA48||AA49) IN (
                  SELECT DISTINCT SUBSTR(IIKEY,2,12) FROM TRINDX
                  WHERE (II03='$rm01') AND (II04_1='$rm02') AND (II04_2='$rm03') AND (II00='B') AND (IP_TYPE = 'l')
                )
                UNION
                SELECT 'B' AS DEPT, '1' AS SEQ, LS_TYPE AS TYPE, '' AS AA48, '' AS AA49, '' AS AA45, '' AS AA46, '' AS BB01, LIDN AS BB09, '' AS RM34, '' AS BB05, '' AS RM09, '' AS AA08, '' AS AA09, '' AS AA10, '' AS AA11, '' AS AA12, '' AS AA16, '' AS AA17, '' AS BB15_1, '' AS BB15_2, '' AS BB15_3, '' AS BB21, LNAM, LCDE, LADR
                FROM WRLNID
                WHERE (LS03='$rm01') AND (LS04_1='$rm02') AND (LS04_2='$rm03') AND (LS_TYPE IN ('A', 'D', 'M', 'N'))
                ORDER BY SEQ, AA48, AA49, BB09";
        
        $rows = $this->query($sql);
        $count = 0;
        foreach ($rows as $r) {
            $r['RM01'] = $rm01;
            $r['RM02'] = $rm02;
            $r['RM03'] = $rm03;
            $r['RM33'] = $c['RM33'] ?? '';
            $r['RM36'] = $c['RM36'] ?? '';

            $dept = trim($r['DEPT'] ?? '');
            if ($dept === 'A') {
                $line = self::formatRecordA($r, 'X');
            } elseif ($dept === 'B') {
                $r['RM34'] = ''; // 分支 3 之 BB07 固定為空字串
                $r['RM09'] = $rm09; // 分支 3 之原因取主案件 RM09
                $line = self::formatRecordB($r);
            } else {
                continue;
            }
            fwrite($fp, $line . "\n");
            $count++;
        }
        return $count;
    }

    /**
     * 分支 4：僅管理者異動 (TRINDX / SRALID / SRBLOW / WRMNGR)
     */
    private function processBranch4($fp, array $c): int {
        $rm01 = trim($c['RM01'] ?? '');
        $rm02 = trim($c['RM02'] ?? '');
        $rm03 = trim($c['RM03'] ?? '');

        $sqlIdx = "SELECT IIKEY, IP_TYPE FROM TRINDX
                   WHERE (II03='$rm01') AND (II04_1='$rm02') AND (II04_2='$rm03')
                     AND (II00='M') AND (SUBSTR(IIKEY,1,1)='C') AND 1=1";
        $indexes = $this->query($sqlIdx);
        $count = 0;

        foreach ($indexes as $idx) {
            $iikey = trim($idx['IIKEY'] ?? '');
            if (strlen($iikey) > 17) {
                continue;
            }
            $saa48 = substr($iikey, 1, 4);
            $saa49 = substr($iikey, 5, 8);
            $sbb01 = substr($iikey, 13, 4);

            // [qry10] SRALID
            $sqlA = "SELECT AA48, AA49, AA05, AA06, AA08, AA09, AA10, AA11, AA12, AA16, AA17, AA45, AA46
                     FROM SRALID
                     WHERE (AA48='$saa48') AND (AA49='$saa49') AND 1=1";
            $aRows = $this->query($sqlA);
            foreach ($aRows as $r) {
                $r['RM01'] = $rm01;
                $r['RM02'] = $rm02;
                $r['RM03'] = $rm03;
                $r['RM33'] = $c['RM33'] ?? '';
                $r['RM36'] = $c['RM36'] ?? '';
                $line = self::formatRecordA($r, 'X');
                fwrite($fp, $line . "\n");
                $count++;
            }

            // [qry5] SRBLOW
            $sqlB = "SELECT BA48, BA49, BB01, BB05, BB06, BB07, BB09, BB15_1, BB15_2, BB15_3, BB21
                     FROM SRBLOW
                     WHERE (BA48='$saa48') AND (BA49='$saa49') AND (BB01='$sbb01') AND 1=1";
            $bRows = $this->query($sqlB);
            foreach ($bRows as $r) {
                $r['RM01'] = $rm01;
                $r['RM02'] = $rm02;
                $r['RM03'] = $rm03;
                $r['RM33'] = $c['RM33'] ?? '';
                $r['RM36'] = $c['RM36'] ?? '';
                $r['LNAM'] = '';
                $r['LCDE'] = '';
                $r['LADR'] = '';
                $line = self::formatRecordB($r, 'X');
                fwrite($fp, $line . "\n");
                $count++;
            }

            // [qry7_BAD1] WRMNGR
            $sqlM = "SELECT MM00, MM48, MM49, MM01, MM13, MS_TYPE
                     FROM WRMNGR
                     WHERE (MS03='$rm01') AND (MS04_1='$rm02') AND (MS04_2='$rm03')
                       AND (MM00='B') AND (MM48='$saa48') AND (MM49='$saa49') AND (MM01='$sbb01')
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
