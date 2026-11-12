<?php

if (class_exists('PHPUnit\Framework\TestCase')) {
    abstract class BaseTaxExportTestCase extends \PHPUnit\Framework\TestCase {}
} else {
    abstract class BaseTaxExportTestCase {}
}

$incPath = dirname(__DIR__) . '/include/api/TaxExportAI002.class.php';
if (!file_exists($incPath)) {
    $incPath = dirname(__DIR__, 2) . '/include/api/TaxExportAI002.class.php';
}
if (!file_exists($incPath)) {
    $incPath = dirname(__DIR__, 3) . '/include/api/TaxExportAI002.class.php';
}
if (!file_exists($incPath)) {
    $incPath = dirname(__DIR__, 3) . '/www/include/api/TaxExportAI002.class.php';
}
if (!file_exists($incPath)) {
    $incPath = dirname(__DIR__, 4) . '/www/include/api/TaxExportAI002.class.php';
}
require_once $incPath;

class TaxExportAI002Test extends BaseTaxExportTestCase {
    private $tempDir;
    private $passed = 0;
    private $failed = 0;

    public function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/lah_ai002_test_' . bin2hex(random_bytes(6));
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0700, true);
        }
    }

    public function tearDown(): void {
        if ($this->tempDir && is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $f) {
                if (is_file($f)) unlink($f);
            }
            rmdir($this->tempDir);
        }
    }

    private function myAssert($condition, string $message): void {
        if (is_callable(['parent', 'assertTrue'])) {
            $this->assertTrue($condition, $message);
        }
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    private function myAssertSame($expected, $actual, string $message): void {
        if (is_callable(['parent', 'assertSame'])) {
            $this->assertSame($expected, $actual, $message);
        }
        $cond = ($expected === $actual);
        if (!$cond) {
            $expStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
            $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
            $this->myAssert(false, "{$message} (期望: '{$expStr}', 實際: '{$actStr}')");
        } else {
            $this->myAssert(true, $message);
        }
    }

    public function runAll(): bool {
        $this->setUp();
        echo "=======================================================\n";
        echo "  TaxExportAI002 (地價異動資料 TXT) 單元測試套件\n";
        echo "=======================================================\n";

        $this->testRecordLengthAndFieldLayout();
        $this->testMultiplicationRules();
        $this->testTrailerTagEncodingAndCount();
        $this->testMockModeDeliverSampleData();
        $this->testEmptyDataDeliverZeroTag();
        $this->testDeduplicationAndKa16FirstRowOnly();
        $this->testFlagAndLaTypeBranching();

        echo "\n-------------------------------------------------------\n";
        echo "測試結果: 通過 {$this->passed} 項, 失敗 {$this->failed} 項\n";
        echo "-------------------------------------------------------\n";
        $this->tearDown();
        return $this->failed === 0;
    }

    public function testRecordLengthAndFieldLayout(): void {
        echo "\n[*] 測試 AI002 單列定長 103 bytes 與欄位排列結構...\n";

        $row = [
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
        ];

        $record = TaxExportAI002::formatRecord($row);
        $this->myAssertSame(103, strlen($record), 'AI002 單列長度必須嚴格為 103 bytes');

        // 驗證各欄位位置 (0-based offset)
        $this->myAssertSame('115', substr($record, 0, 3), '第 1-3 碼 (offset 0-2) 為收件年 SS03');
        $this->myAssertSame('A1HA', substr($record, 3, 4), '第 4-7 碼 (offset 3-6) 為收件字 SS04_1');
        $this->myAssertSame('000001', substr($record, 7, 6), '第 8-13 碼 (offset 7-12) 為收件號 SS04_2');
        $this->myAssertSame('01', substr($record, 13, 2), '第 14-15 碼 (offset 13-14) 為鄉鎮市區代碼 KRMK');
        $this->myAssertSame('1010', substr($record, 15, 4), '第 16-19 碼 (offset 15-18) 為段小段代碼 HA48');
        $this->myAssertSame('02101940', substr($record, 19, 8), '第 20-27 碼 (offset 19-26) 為地號 HA49');
        $this->myAssertSame('A123456789', substr($record, 27, 10), '第 28-37 碼 (offset 27-36) 為所有權人統編 LA09');
        $this->myAssertSame('0001', substr($record, 37, 4), '第 38-41 碼 (offset 37-40) 為次序 MB22_2');
        $this->myAssertSame('2', substr($record, 41, 1), '第 42 碼 (offset 41) 為異動別代號 flag');
        $this->myAssertSame('01', substr($record, 42, 2), '第 43-44 碼 (offset 42-43) 為登記原因 SS06');
        $this->myAssertSame('1151025', substr($record, 44, 7), '第 45-51 碼 (offset 44-50) 為原因發生日期 SS07');
        $this->myAssertSame('         1', substr($record, 51, 10), '第 52-61 碼 (offset 51-60) 為持分分子 MB23_3 (%10.0f 在前)');
        $this->myAssertSame('         1', substr($record, 61, 10), '第 62-71 碼 (offset 61-70) 為持分分母 MB23_2 (%10.0f 在後)');
        $this->myAssertSame('11510', substr($record, 71, 5), '第 72-76 碼 (offset 71-75) 為前次移轉年月 MB22_1');
        $this->myAssertSame('   12000', substr($record, 76, 8), '第 77-84 碼 (offset 76-83) 為前次移轉現值 MB22_3 * 10 (%8.0f)');
        $this->myAssertSame('    3500', substr($record, 84, 8), '第 85-92 碼 (offset 84-91) 為申報地價 LA21 * 10 (%8s)');
        $this->myAssertSame('  12000', substr($record, 92, 7), '第 93-99 碼 (offset 92-98) 為公告現值 KA16 (%7s 不乘 10)');
        $this->myAssertSame('    ', substr($record, 99, 4), '第 100-103 碼 (offset 99-102) 為固定 4 個保留空白');
    }

    public function testMultiplicationRules(): void {
        echo "\n[*] 測試倍數計算規則（LA21*10, MB22_3*10, KA16 不乘 10）...\n";

        $row = [
            'SS03' => '115', 'SS04_1' => 'A1HA', 'SS04_2' => '000001',
            'KRMK' => '01', 'HA48' => '1010', 'HA49' => '00001111',
            'LA09' => 'A123456789', 'MB22_2' => '0001', 'FLAG' => '2',
            'SS06' => '01', 'SS07' => '1151025', 'MB23_3' => 1, 'MB23_2' => 1,
            'MB22_1' => '11510',
            'MB22_3' => '1234',   // 原始值 1234 -> * 10 應為 12340
            'LA21' => '567',      // 原始值 567 -> * 10 應為 5670
            'KA16' => '8900'      // 原始值 8900 -> 不乘 10 應仍為 8900
        ];

        $rec = TaxExportAI002::formatRecord($row);
        $this->myAssertSame('   12340', substr($rec, 76, 8), 'MB22_3 必須乘以 10 並以 %8.0f 格式化為 8 碼');
        $this->myAssertSame('    5670', substr($rec, 84, 8), 'LA21 必須乘以 10 並以 %8.0f 格式化為 8 碼');
        $this->myAssertSame('   8900', substr($rec, 92, 7), 'KA16 不得乘以 10，保持原始數值並格式化為 7 碼');

        // 支援傳入已乘 10 之 MB22_3_X10 與 LA21_X10
        $rowPre = $row;
        $rowPre['MB22_3_X10'] = 45000;
        $rowPre['LA21_X10'] = 9800;
        $recPre = TaxExportAI002::formatRecord($rowPre);
        $this->myAssertSame('   45000', substr($recPre, 76, 8), '支援 MB22_3_X10 預乘數值');
        $this->myAssertSame('    9800', substr($recPre, 84, 8), '支援 LA21_X10 預乘數值');
    }

    public function testTrailerTagEncodingAndCount(): void {
        echo "\n[*] 測試檔尾 TAG 二進位編碼與筆數格式...\n";

        // 0 筆 TAG
        $tag0 = TaxExportAI002::trailer(0);
        $expected0 = mb_convert_encoding('##### TAG #####共產製 0 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expected0, $tag0, '0 筆 TAG 格式與 CP950 二進位吻合');

        // 3 筆 TAG
        $tag3 = TaxExportAI002::trailer(3);
        $expected3 = mb_convert_encoding('##### TAG #####共產製 3 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expected3, $tag3, '3 筆 TAG 格式與 CP950 二進位吻合');
        $this->myAssertSame(30, strlen($tag3), '3 筆 TAG 總 byte 數為 30 bytes (無尾隨換行)');
    }

    public function testMockModeDeliverSampleData(): void {
        echo "\n[*] 測試 MOCK 模式產製合成資料 (本所案件與他所跨所案件)...\n";

        $tempFile = $this->tempDir . '/mock_ai002.txt';
        $exporter = new TaxExportAI002();
        $batch = ['mock' => true, 'begin' => '2026-10-16', 'end' => '2026-10-31'];
        $res = $exporter->export($tempFile, ['code' => 'AI002'], $batch);

        $this->myAssertSame('success', $res['state'], 'MOCK 模式回傳狀態為 success');
        $this->myAssert(file_exists($tempFile), '產出檔案必須存在');

        $content = file_get_contents($tempFile);
        $lines = explode("\n", $content);

        // 3 筆資料列 + 1 筆 TAG
        $this->myAssertSame(4, count($lines), '產出 3 筆資料列與 1 筆 TAG');
        $this->myAssertSame(103, strlen($lines[0]), '第 1 筆紀錄長度為 103 bytes');
        $this->myAssertSame(103, strlen($lines[1]), '第 2 筆紀錄長度為 103 bytes');
        $this->myAssertSame(103, strlen($lines[2]), '第 3 筆紀錄長度為 103 bytes');

        // 驗證首筆有 KA16，次筆 KA16 為 7 空白
        $this->myAssertSame('  12000', substr($lines[0], 92, 7), '第 1 筆（同地號首筆）印出公告現值 KA16');
        $this->myAssertSame('       ', substr($lines[1], 92, 7), '第 2 筆（同地號次筆）公告現值欄為 7 個半形空白');
        $this->myAssertSame('  15000', substr($lines[2], 92, 7), '第 3 筆（新地號首筆）印出新公告現值 KA16');

        // 驗證 flag
        $this->myAssertSame('2', substr($lines[0], 41, 1), '第 1 筆 flag 為 2 (LA_TYPE=D)');
        $this->myAssertSame('3', substr($lines[1], 41, 1), '第 2 筆 flag 為 3 (LA_TYPE=M)');
        $this->myAssertSame('2', substr($lines[2], 41, 1), '第 3 筆 flag 為 2 (LA_TYPE=D)');

        // 驗證 TAG
        $expectedTag = mb_convert_encoding('##### TAG #####共產製 3 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expectedTag, $lines[3], '檔尾 TAG 正確標記 3 筆資料');
    }

    public function testEmptyDataDeliverZeroTag(): void {
        echo "\n[*] 測試空資料產製 (0 筆案件輸出 0 筆 TAG 且無尾隨換行)...\n";

        $tempFile = $this->tempDir . '/empty_ai002.txt';
        $mockSelector = function (string $sql): array {
            if (strpos($sql, "KCDE_1='06'") !== false) {
                return [['KCDE_2' => '01']];
            }
            return []; // 主查詢無資料
        };

        $exporter = new TaxExportAI002($mockSelector);
        $batch = ['mock' => false, 'begin' => '2026-10-16', 'end' => '2026-10-31'];
        $res = $exporter->export($tempFile, ['code' => 'AI002'], $batch);

        $this->myAssertSame('empty', $res['state'], '查無資料時回傳 empty 狀態');
        $content = file_get_contents($tempFile);
        $expectedTag0 = mb_convert_encoding('##### TAG #####共產製 0 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expectedTag0, $content, '空資料時檔案內容恰好為 0 筆 TAG (無尾隨換行)');
    }

    public function testDeduplicationAndKa16FirstRowOnly(): void {
        echo "\n[*] 測試相鄰地號去重與首筆 KA16 保留/次筆空白行為 (Seam 注入測試)...\n";

        $tempFile = $this->tempDir . '/dedup_ai002.txt';
        $mockSelector = function (string $sql): array {
            if (strpos($sql, "KCDE_1='06'") !== false) {
                return [['KCDE_2' => '01']];
            }
            if (strpos($sql, 'FROM VPHIND, VPSCRN') !== false) {
                // 回傳 3 筆主案件紀錄：前 2 筆地號相同（應去重為 1 組），第 3 筆地號不同
                return [
                    ['SS03' => '115', 'SS04_1' => 'A1HA', 'SS04_2' => '000001', 'HA48' => '1010', 'HA49' => '00001111', 'SS06' => '01', 'SS07' => '1151025'],
                    ['SS03' => '115', 'SS04_1' => 'A1HA', 'SS04_2' => '000001', 'HA48' => '1010', 'HA49' => '00001111', 'SS06' => '01', 'SS07' => '1151025'], // 重複筆
                    ['SS03' => '115', 'SS04_1' => 'A1HA', 'SS04_2' => '000001', 'HA48' => '1010', 'HA49' => '00002222', 'SS06' => '01', 'SS07' => '1151025']  // 不同地號
                ];
            }
            if (strpos($sql, "KCDE_1='48'") !== false) {
                return [['KRMK' => '01']];
            }
            if (strpos($sql, 'FROM WPALID') !== false) {
                if (strpos($sql, "00001111") !== false) {
                    return [['KA16' => '12000', 'KA_TYPE' => 'D']];
                }
                return [['KA16' => '18000', 'KA_TYPE' => 'D']];
            }
            if (strpos($sql, 'FROM WPBLOW') !== false && strpos($sql, "LA_TYPE IN ('D', 'M')") !== false) {
                if (strpos($sql, "00001111") !== false) {
                    // 地號 00001111 有 2 筆 WPBLOW：第一筆 LA_TYPE='D'，第二筆 LA_TYPE='M'
                    return [
                        ['LA01' => '0001', 'LA09' => 'A111111111', 'LA_TYPE' => 'D', 'LA21' => '300'],
                        ['LA01' => '0002', 'LA09' => 'A222222222', 'LA_TYPE' => 'M', 'LA21' => '0']
                    ];
                }
                // 地號 00002222 有 1 筆 WPBLOW
                return [
                    ['LA01' => '0001', 'LA09' => 'B111111111', 'LA_TYPE' => 'D', 'LA21' => '400']
                ];
            }
            if (strpos($sql, 'FROM WPBLOW') !== false && strpos($sql, "LA_TYPE = 'N'") !== false) {
                // 查 LA_TYPE='N' 之 LA21
                return [['LA21' => '350']];
            }
            if (strpos($sql, 'FROM WPPRCE') !== false) {
                return [
                    ['MB22_1' => '11510', 'MB22_2' => '0001', 'MB22_3' => '1000', 'MB23_1' => '1', 'MB23_2' => '1', 'MB23_3' => '1', 'MA_TYPE' => 'D']
                ];
            }
            return [];
        };

        $exporter = new TaxExportAI002($mockSelector);
        $batch = ['mock' => false, 'begin' => '2026-10-16', 'end' => '2026-10-31'];
        $res = $exporter->export($tempFile, ['code' => 'AI002'], $batch);

        $this->myAssertSame('success', $res['state'], 'Seam 注入執行成功');
        $content = file_get_contents($tempFile);
        $lines = explode("\n", $content);

        // 應產出 3 筆資料列 (重複的那筆主查詢已被去重) + 1 筆 TAG
        $this->myAssertSame(4, count($lines), '去重後產出 3 筆資料列 + 1 筆 TAG');

        // 地號 00001111 之第 1 筆 WPBLOW
        $this->myAssertSame('00001111', substr($lines[0], 19, 8), '第 1 列為地號 00001111');
        $this->myAssertSame('2', substr($lines[0], 41, 1), '第 1 列 flag 為 2');
        $this->myAssertSame('  12000', substr($lines[0], 92, 7), '第 1 列為首筆 WPBLOW，印出公告現值 KA16 12000');

        // 地號 00001111 之第 2 筆 WPBLOW
        $this->myAssertSame('00001111', substr($lines[1], 19, 8), '第 2 列同為地號 00001111');
        $this->myAssertSame('3', substr($lines[1], 41, 1), '第 2 列 flag 為 3 (LA_TYPE=M)');
        $this->myAssertSame('    3500', substr($lines[1], 84, 8), '第 2 列 LA21*10 來自 LA_TYPE=N 之查詢結果 350*10=3500');
        $this->myAssertSame('       ', substr($lines[1], 92, 7), '第 2 列為次筆 WPBLOW，公告現值欄必須填 7 個空白');

        // 地號 00002222 之第 1 筆 WPBLOW
        $this->myAssertSame('00002222', substr($lines[2], 19, 8), '第 3 列為新地號 00002222');
        $this->myAssertSame('  18000', substr($lines[2], 92, 7), '第 3 列為新地號首筆 WPBLOW，重新印出該地號之 KA16 18000');

        $this->myAssertSame(103, strlen($lines[0]), '第 1 列 103 bytes');
        $this->myAssertSame(103, strlen($lines[1]), '第 2 列 103 bytes');
        $this->myAssertSame(103, strlen($lines[2]), '第 3 列 103 bytes');
    }

    public function testFlagAndLaTypeBranching(): void {
        echo "\n[*] 測試 LA_TYPE='D' 與 LA_TYPE='M' 之 flag 與 LA21 查詢分支...\n";

        $tempFile = $this->tempDir . '/flag_branch_ai002.txt';
        $nQueryCount = 0;
        $mockSelector = function (string $sql) use (&$nQueryCount): array {
            if (strpos($sql, "KCDE_1='06'") !== false) return [['KCDE_2' => '01']];
            if (strpos($sql, 'FROM VPHIND, VPSCRN') !== false) {
                return [['SS03' => '115', 'SS04_1' => 'A1HA', 'SS04_2' => '000001', 'HA48' => '1010', 'HA49' => '00003333', 'SS06' => '01', 'SS07' => '1151025']];
            }
            if (strpos($sql, "KCDE_1='48'") !== false) return [['KRMK' => '01']];
            if (strpos($sql, 'FROM WPALID') !== false) return [['KA16' => '10000', 'KA_TYPE' => 'D']];
            if (strpos($sql, 'FROM WPBLOW') !== false && strpos($sql, "LA_TYPE IN ('D', 'M')") !== false) {
                return [
                    ['LA01' => '0001', 'LA09' => 'C111111111', 'LA_TYPE' => 'D', 'LA21' => '150'],
                    ['LA01' => '0002', 'LA09' => 'C222222222', 'LA_TYPE' => 'M', 'LA21' => '0']
                ];
            }
            if (strpos($sql, 'FROM WPBLOW') !== false && strpos($sql, "LA_TYPE = 'N'") !== false) {
                $nQueryCount++;
                return [['LA21' => '250']];
            }
            if (strpos($sql, 'FROM WPPRCE') !== false) {
                return [['MB22_1' => '11510', 'MB22_2' => '0001', 'MB22_3' => '500', 'MB23_1' => '1', 'MB23_2' => '1', 'MB23_3' => '1', 'MA_TYPE' => 'D']];
            }
            return [];
        };

        $exporter = new TaxExportAI002($mockSelector);
        $batch = ['mock' => false, 'begin' => '2026-10-16', 'end' => '2026-10-31'];
        $res = $exporter->export($tempFile, ['code' => 'AI002'], $batch);

        $this->myAssertSame('success', $res['state'], '分支測試執行成功');
        $this->myAssertSame(1, $nQueryCount, '僅針對 LA_TYPE=M 執行 1 次 LA_TYPE=N 查詢');

        $content = file_get_contents($tempFile);
        $lines = explode("\n", $content);
        $this->myAssertSame('    1500', substr($lines[0], 84, 8), 'LA_TYPE=D 時 LA21*10 為 150*10=1500');
        $this->myAssertSame('    2500', substr($lines[1], 84, 8), 'LA_TYPE=M 時 LA21*10 為 250*10=2500');
    }
}

// 支援命令列直接執行: php www/test/TaxExportAI002Test.php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $tester = new TaxExportAI002Test();
    $ok = $tester->runAll();
    exit($ok ? 0 : 1);
}
