<?php

if (class_exists('PHPUnit\Framework\TestCase')) {
    abstract class BaseTaxExportTestCase extends \PHPUnit\Framework\TestCase {}
} else {
    abstract class BaseTaxExportTestCase {}
}

$incPath = dirname(__DIR__) . '/include/api/TaxExportAI001.class.php';
if (!file_exists($incPath)) {
    $incPath = dirname(__DIR__, 2) . '/include/api/TaxExportAI001.class.php';
}
if (!file_exists($incPath)) {
    $incPath = dirname(__DIR__, 3) . '/include/api/TaxExportAI001.class.php';
}
if (!file_exists($incPath)) {
    $incPath = dirname(__DIR__, 3) . '/www/include/api/TaxExportAI001.class.php';
}
if (!file_exists($incPath)) {
    $incPath = dirname(__DIR__, 4) . '/www/include/api/TaxExportAI001.class.php';
}
require_once $incPath;

class TaxExportAI001Test extends BaseTaxExportTestCase {
    private $tempDir;
    private $passed = 0;
    private $failed = 0;

    public function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/lah_ai001_test_' . bin2hex(random_bytes(6));
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
        echo "  TaxExportAI001 (地籍異動資料 TXT) 單元測試套件\n";
        echo "=======================================================\n";

        $this->testFieldPaddingAndTruncation();
        $this->testRecordALengthAndFormat();
        $this->testRecordBLengthAndFormat();
        $this->testRecordGLengthAndFormat();
        $this->testRecordMLengthAndFormat();
        $this->testTrailerTagEncodingAndCount();
        $this->testMockModeDeliverBothOfficesData();
        $this->testEmptyDataDeliverZeroTag();
        $this->testDirectOracleQueryBranches();

        echo "\n-------------------------------------------------------\n";
        echo "測試結果: 通過 {$this->passed} 項, 失敗 {$this->failed} 項\n";
        echo "-------------------------------------------------------\n";
        $this->tearDown();
        return $this->failed === 0;
    }

    public function testFieldPaddingAndTruncation(): void {
        echo "\n[*] 測試欄位填補與 MS950 截斷補問號規則 (field)...\n";
        
        // 1. 一般英文靠左補空白
        $res = TaxExportAI001::field('ABC', 6);
        $this->myAssertSame(6, strlen($res), 'field(\'ABC\', 6) 長度必須為 6');
        $this->myAssertSame('ABC   ', $res, 'field(\'ABC\', 6) 內容靠左補空白');

        // 2. 靠右補空白
        $resR = TaxExportAI001::field('123', -6);
        $this->myAssertSame(6, strlen($resR), 'field(\'123\', -6) 長度必須為 6');
        $this->myAssertSame('   123', $resR, 'field(\'123\', -6) 內容靠右補空白');

        // 3. 中文字剛好完整不截斷 (中文兩個字: 4 bytes)
        $zh = mb_convert_encoding('測試', 'CP950', 'UTF-8');
        $resZh = TaxExportAI001::field($zh, 4);
        $this->myAssertSame(4, strlen($resZh), '中文 4 bytes 恰好符合寬度 4');
        $this->myAssertSame($zh, $resZh, '中文完整保留');

        // 4. 中文字截斷遇半個字補 '?'
        $resCut = TaxExportAI001::field($zh, 3);
        $this->myAssertSame(3, strlen($resCut), '中文字截斷長度嚴格維持 3 bytes');
        $this->myAssertSame('?', substr($resCut, 2, 1), '半個中文字截斷邊界必須補半形問號 (?)');

        // 5. 數字欄位格式化 numField
        $num1 = TaxExportAI001::numField('123.456', 10, 2);
        $this->myAssertSame(10, strlen($num1), 'numField 10.2f 長度必須為 10');
        $this->myAssertSame('    123.46', $num1, 'numField 數值四捨五入至小數後 2 位且靠右對齊');

        $num0 = TaxExportAI001::numField('456.7', 8, 0);
        $this->myAssertSame(8, strlen($num0), 'numField 8.0f 長度必須為 8');
        $this->myAssertSame('     457', $num0, 'numField 數值四捨五入整數無小數點且靠右對齊');
    }

    public function testRecordALengthAndFormat(): void {
        echo "\n[*] 測試 A 紀錄 (土地標示部) 定長 169 bytes 與欄位結構...\n";

        $row = [
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
        ];

        $recordA = TaxExportAI001::formatRecordA($row);
        $this->myAssertSame(169, strlen($recordA), 'A 紀錄總長度必須為 169 bytes');
        $this->myAssertSame('115A1HA000001A', substr($recordA, 0, 14), 'A 紀錄前 14 碼必須包含年字號與部別 A');
        $this->myAssertSame('A', substr($recordA, 14, 1), '第 15 碼為異動別 TYPE');
        $this->myAssertSame('1010', substr($recordA, 15, 4), '第 16-19 碼為段小段 AA48');
        $this->myAssertSame('02101940', substr($recordA, 19, 8), '第 20-27 碼為地號 AA49');
        $this->myAssertSame('   4646.00', substr($recordA, 47, 10), '第 48-57 碼為面積 AA10 (%10.2f)');
        $this->myAssertSame('   12000.0', substr($recordA, 61, 10), '第 62-71 碼為公告現值 AA16 (%10.1f)');
        $this->myAssertSame('    3500.0', substr($recordA, 71, 10), '第 72-81 碼為公告地價 AA17 (%10.1f)');
        $this->myAssertSame('01', substr($recordA, 81, 2), '第 82-83 碼為鄉鎮市區代碼 AA46 (在 AA45 之前)');
        $this->myAssertSame('HA', substr($recordA, 83, 2), '第 84-85 碼為縣市代碼 AA45');
    }

    public function testRecordBLengthAndFormat(): void {
        echo "\n[*] 測試 B 紀錄 (土地所有權部) 定長 227 bytes 與欄位結構...\n";

        $row = [
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'B',
            'BB01' => '0001',
            'BB05' => '1151027', // 7 碼日期，必須被截斷為前 4 碼
            'BB09' => 'A123456789',
            'LNAM' => '本所測試所有權人',
            'LCDE' => '1',
            'LADR' => '桃園市桃園區復興路１號',
            'BB15_1' => '1',
            'BB15_3' => '1', // 持分分子
            'BB15_2' => '2', // 持分分母
            'BB21' => '3500.0',
            'RM34' => '1151027',
            'RM33' => '1151029',
            'RM09' => '01'
        ];

        $recordB = TaxExportAI001::formatRecordB($row);
        $this->myAssertSame(227, strlen($recordB), 'B 紀錄總長度必須為 227 bytes');
        $this->myAssertSame('115A1HA000001B', substr($recordB, 0, 14), 'B 紀錄前 14 碼必須包含年字號與部別 B');
        $this->myAssertSame('B', substr($recordB, 14, 1), '第 15 碼為異動別 TYPE');
        $this->myAssertSame('0001', substr($recordB, 15, 4), '第 16-19 碼為登序 BB01');
        $this->myAssertSame('1151', substr($recordB, 19, 4), '第 20-23 碼 BB05 必須被截斷為 4 碼');
        $this->myAssertSame('A123456789', substr($recordB, 23, 10), '第 24-33 碼為所有權人統編 BB09');
        $this->myAssertSame('       1', substr($recordB, 185, 8), '持分 BB15_3 必須在前 (%8.0f)');
        $this->myAssertSame('       2', substr($recordB, 193, 8), '分母 BB15_2 必須在後 (%8.0f)');
        $this->myAssertSame('    3500.0', substr($recordB, 201, 10), '申報地價 BB21 為 10 碼 (%10.1f)');
    }

    public function testRecordGLengthAndFormat(): void {
        echo "\n[*] 測試 G 紀錄 (其他登記事項) 定長 1021 bytes...\n";

        $content1000 = str_repeat('A', 1000);
        $row = [
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'GG01' => '0001',
            'GG30_1' => '01',
            'GG30_2' => $content1000
        ];

        $recordG = TaxExportAI001::formatRecordG($row);
        $this->myAssertSame(1021, strlen($recordG), 'G 紀錄總長度必須為 1021 bytes');
        $this->myAssertSame('115A1HA000001G', substr($recordG, 0, 14), '前 14 碼包含部別 G');
        $this->myAssertSame('A', substr($recordG, 14, 1), '第 15 碼為異動別');
        $this->myAssertSame('0001', substr($recordG, 15, 4), '第 16-19 碼為登序 GG01');
        $this->myAssertSame('01', substr($recordG, 19, 2), '第 20-21 碼為事項代碼 GG30_1');
        $this->myAssertSame($content1000, substr($recordG, 21, 1000), '第 22-1021 碼為登記事項內容 (1000 bytes)');
    }

    public function testRecordMLengthAndFormat(): void {
        echo "\n[*] 測試 M 紀錄 (管理者資料) 定長 180 bytes...\n";

        $row = [
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'M',
            'BB01' => '0001',
            'BB09' => 'TESTMNGR01',
            'LNAM' => '測試管理者',
            'LCDE' => 'A',
            'LADR' => '桃園市復興路１號'
        ];

        $recordM = TaxExportAI001::formatRecordM($row);
        $this->myAssertSame(180, strlen($recordM), 'M 紀錄總長度必須為 180 bytes');
        $this->myAssertSame('115A1HA000001M', substr($recordM, 0, 14), '前 14 碼包含部別 M');
        $this->myAssertSame('M', substr($recordM, 14, 1), '第 15 碼為異動別');
        $this->myAssertSame('0001', substr($recordM, 15, 4), '第 16-19 碼為登序');
        $this->myAssertSame('TESTMNGR01', substr($recordM, 19, 10), '第 20-29 碼為管理者統編');
    }

    public function testTrailerTagEncodingAndCount(): void {
        echo "\n[*] 測試檔尾 TAG 編碼與筆數格式...\n";

        $tag0 = TaxExportAI001::trailer(0);
        $expected0 = mb_convert_encoding('##### TAG #####共產製 0 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expected0, $tag0, '0 筆檔尾 TAG 正確性');

        $tag4 = TaxExportAI001::trailer(4);
        $expected4 = "##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 4 \xB5\xA7\xB8\xEA\xAE\xC6";
        $this->myAssertSame($expected4, $tag4, '4 筆檔尾 TAG 正確性 (MS950 二進位吻合)');
    }

    public function testMockModeDeliverBothOfficesData(): void {
        echo "\n[*] 測試 MOCK 模式產製 (本所與他所跨所案件)...\n";

        $tempFile = $this->tempDir . '/mock_ai001.txt';
        $exporter = new TaxExportAI001();
        $batch = ['mock' => true, 'begin' => '2026-10-16', 'end' => '2026-10-31'];
        $res = $exporter->export($tempFile, ['code' => 'AI001'], $batch);

        $this->myAssertSame('success', $res['state'], 'MOCK 模式必須回傳 success');
        $this->myAssert(file_exists($tempFile), '產出檔案必須存在');

        $content = file_get_contents($tempFile);
        $lines = explode("\n", $content);

        // 4 筆資料列 + 1 筆 TAG (TAG 尾端無換行)
        $this->myAssertSame(5, count($lines), '包含 4 筆資料與 1 筆 TAG');

        $this->myAssertSame(169, strlen($lines[0]), '第 1 筆為本所 A 紀錄 (169 bytes)');
        $this->myAssertSame(227, strlen($lines[1]), '第 2 筆為本所 B 紀錄 (227 bytes)');
        $this->myAssertSame(169, strlen($lines[2]), '第 3 筆為他所 A 紀錄 (169 bytes)');
        $this->myAssertSame(227, strlen($lines[3]), '第 4 筆為他所 B 紀錄 (227 bytes)');

        // 驗證字串標記
        $this->myAssert(strpos($content, '115A1HA000001A') !== false, '包含本所案件 115A1HA000001A');
        $this->myAssert(strpos($content, '115U1HA000002A') !== false, '包含他所案件 115U1HA000002A');

        // 驗證 TAG
        $expectedTag = "##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 4 \xB5\xA7\xB8\xEA\xAE\xC6";
        $this->myAssertSame($expectedTag, $lines[4], '結尾 TAG 筆數為 4 且格式正確');
    }

    public function testEmptyDataDeliverZeroTag(): void {
        echo "\n[*] 測試空資料產製 (0 筆案件輸出 0 筆 TAG 且無尾隨換行)...\n";

        $tempFile = $this->tempDir . '/empty_ai001.txt';
        $mockSelector = function (string $sql): array {
            if (strpos($sql, "KCDE_1='55'") !== false) {
                return [['KCNT' => '桃園所', 'KRMK' => 'HA']];
            }
            if (strpos($sql, "KCDE_1='06'") !== false) {
                return [['KCDE_2' => '01'], ['KCDE_2' => '02']];
            }
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return []; // 查無任何案件
            }
            return [];
        };

        $exporter = new TaxExportAI001($mockSelector);
        $batch = ['mock' => false, 'begin' => '2026-10-16', 'end' => '2026-10-31'];
        $res = $exporter->export($tempFile, ['code' => 'AI001'], $batch);

        $this->myAssertSame('empty', $res['state'], '查無資料時回傳 empty 狀態');
        $content = file_get_contents($tempFile);
        $expectedTag0 = mb_convert_encoding('##### TAG #####共產製 0 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expectedTag0, $content, '空資料時檔案內容恰好為 0 筆 TAG (無尾隨換行)');
    }

    public function testDirectOracleQueryBranches(): void {
        echo "\n[*] 測試直連 Oracle 四大互斥分支 (Seam 注入測試)...\n";

        $tempFile = $this->tempDir . '/branch_ai001.txt';
        $mockSelector = function (string $sql): array {
            if (strpos($sql, "KCDE_1='55'") !== false) {
                return [['KCNT' => '桃園所', 'KRMK' => 'HA']];
            }
            if (strpos($sql, "KCDE_1='06'") !== false) {
                return [['KCDE_2' => '01']];
            }
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return [
                    ['RM01' => '115', 'RM02' => 'A1HA', 'RM03' => '000001', 'RM09' => '01', 'RM33' => '1151027', 'RM34' => '1151029', 'RM36' => '01']
                ];
            }
            if (strpos($sql, 'FROM TRINDX') !== false && strpos($sql, 'GROUP BY II00') !== false) {
                // 模擬命中分支 1 (II00 = 'A')
                return [['II00' => 'A', 'IP_TYPE' => 'A', 'CNT' => 1]];
            }
            if (strpos($sql, 'FROM WRALID') !== false) {
                return [
                    ['DEPT' => 'A', 'SEQ' => '0', 'TYPE' => 'A', 'AA48' => '1010', 'AA49' => '00001234', 'AA45' => 'HA', 'AA46' => '01', 'BB01' => '0000', 'BB09' => '', 'RM34' => '1151029', 'BB05' => '', 'RM09' => '01', 'AA08' => '田', 'AA09' => '01', 'AA10' => '100.5', 'AA11' => '01', 'AA12' => '01', 'AA16' => '1000', 'AA17' => '500'],
                    ['DEPT' => 'B', 'SEQ' => '2', 'TYPE' => 'A', 'AA48' => '1010', 'AA49' => '00001234', 'AA45' => '', 'AA46' => '', 'BB01' => '0001', 'BB09' => 'H123456789', 'RM34' => '1151029', 'BB05' => '1151', 'RM09' => '01', 'BB15_1' => '1', 'BB15_2' => '1', 'BB15_3' => '1', 'BB21' => '500']
                ];
            }
            if (strpos($sql, 'FROM SRLNID') !== false) {
                return [['LIDN' => 'H123456789', 'LNAM' => '王大明', 'LCDE' => '1', 'LADR' => '桃園市成功路三段１號']];
            }
            return [];
        };

        $exporter = new TaxExportAI001($mockSelector);
        $batch = ['mock' => false, 'begin' => '2026-10-16', 'end' => '2026-10-31'];
        $res = $exporter->export($tempFile, ['code' => 'AI001'], $batch);

        $this->myAssertSame('success', $res['state'], '分支 1 執行成功');
        $content = file_get_contents($tempFile);
        $lines = explode("\n", $content);
        $this->myAssertSame(3, count($lines), '產出 2 筆紀錄 + 1 筆 TAG');
        $this->myAssertSame(169, strlen($lines[0]), '第 1 列為 169 bytes A 紀錄');
        $this->myAssertSame(227, strlen($lines[1]), '第 2 列為 227 bytes B 紀錄');
        $expectedTag = "##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 2 \xB5\xA7\xB8\xEA\xAE\xC6";
        $this->myAssertSame($expectedTag, $lines[2], '結尾 TAG 為 2 筆');
    }
}

// 支援命令列直接執行: php www/test/TaxExportAI001Test.php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $tester = new TaxExportAI001Test();
    $ok = $tester->runAll();
    exit($ok ? 0 : 1);
}
