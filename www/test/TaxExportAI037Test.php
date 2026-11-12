<?php

if (class_exists('PHPUnit\Framework\TestCase')) {
    abstract class BaseTaxExportTestCase extends \PHPUnit\Framework\TestCase {}
} else {
    abstract class BaseTaxExportTestCase {}
}

$candidates = [
    dirname(__DIR__) . '/include/api/TaxExportAI037.class.php',
    dirname(__DIR__, 2) . '/include/api/TaxExportAI037.class.php',
    dirname(__DIR__, 3) . '/include/api/TaxExportAI037.class.php',
    dirname(__DIR__, 3) . '/www/include/api/TaxExportAI037.class.php',
    dirname(__DIR__, 4) . '/www/include/api/TaxExportAI037.class.php',
];
$incPath = null;
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $incPath = $c;
        break;
    }
}
if (!$incPath) {
    throw new RuntimeException('找不到 TaxExportAI037.class.php');
}
require_once $incPath;

class TaxExportAI037Test extends BaseTaxExportTestCase {
    private $tempDir;
    private $passed = 0;
    private $failed = 0;

    public function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/lah_ai037_test_' . bin2hex(random_bytes(6));
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
        echo "  TaxExportAI037 (建物異動資料 TXT) 單元測試套件\n";
        echo "=======================================================\n";

        $this->testFieldPaddingAndTruncation();
        $this->testRecordDLengthAndFormat();
        $this->testRecordELengthAndFormat();
        $this->testRecordGLengthAndFormat();
        $this->testRecordHLengthAndFormat();
        $this->testRecordMLengthAndFormat();
        $this->testTrailerTagEncodingAndCount();
        $this->testMockModeDeliverBuildingsData();
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
        $res = TaxExportAI037::field('ABC', 6);
        $this->myAssertSame(6, strlen($res), 'field(\'ABC\', 6) 長度必須為 6');
        $this->myAssertSame('ABC   ', $res, 'field(\'ABC\', 6) 內容靠左補空白');

        // 2. 靠右補空白
        $resR = TaxExportAI037::field('123', -6);
        $this->myAssertSame(6, strlen($resR), 'field(\'123\', -6) 長度必須為 6');
        $this->myAssertSame('   123', $resR, 'field(\'123\', -6) 內容靠右補空白');

        // 3. 中文字剛好完整不截斷 (中文兩個字: 4 bytes)
        $zh = mb_convert_encoding('測試', 'CP950', 'UTF-8');
        $resZh = TaxExportAI037::field($zh, 4);
        $this->myAssertSame(4, strlen($resZh), '中文 4 bytes 恰好符合寬度 4');
        $this->myAssertSame($zh, $resZh, '中文完整保留');

        // 4. 中文字截斷遇半個字補 '?'
        $resCut = TaxExportAI037::field($zh, 3);
        $this->myAssertSame(3, strlen($resCut), '中文字截斷長度嚴格維持 3 bytes');
        $this->myAssertSame('?', substr($resCut, 2, 1), '半個中文字截斷邊界必須補半形問號 (?)');

        // 5. 數字欄位格式化 numField
        $num1 = TaxExportAI037::numField('123.456', 10, 2);
        $this->myAssertSame(10, strlen($num1), 'numField 10.2f 長度必須為 10');
        $this->myAssertSame('    123.46', $num1, 'numField 數值四捨五入至小數後 2 位且靠右對齊');

        $num0 = TaxExportAI037::numField('456.7', 8, 0);
        $this->myAssertSame(8, strlen($num0), 'numField 8.0f 長度必須為 8');
        $this->myAssertSame('     457', $num0, 'numField 數值四捨五入整數無小數點且靠右對齊');
    }

    public function testRecordDLengthAndFormat(): void {
        echo "\n[*] 測試 D 紀錄 (建物標示部) 定長 106 bytes 與欄位結構 (分支 1 與分支 2)...\n";

        $row1 = [
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'DD48' => '1010',
            'DD49' => '00123000', // 8 碼建號，必須被截斷為前 5 碼 '00123'
            'RM33' => '1151027',
            'RM36' => '01',
            'RM34' => '1151029',
            'RM09' => '01',
            'DD08' => '150.25',
            'DD46' => '01',
            'DD45' => 'HA',
            'DD09' => '桃園市桃園區復興路１號'
        ];

        // 1. 分支 1 格式化 (末尾 52 bytes 為空白)
        $recordD1 = TaxExportAI037::formatRecordD($row1, 'branch1');
        $this->myAssertSame(106, strlen($recordD1), 'D 紀錄 (分支 1) 總長度必須為 106 bytes');
        $this->myAssertSame('115A1HA000001D', substr($recordD1, 0, 14), 'D 紀錄前 14 碼必須包含年字號與部別 D');
        $this->myAssertSame('A', substr($recordD1, 14, 1), '第 15 碼為異動別 TYPE');
        $this->myAssertSame('1010', substr($recordD1, 15, 4), '第 16-19 碼為段小段 DD48');
        $this->myAssertSame('00123', substr($recordD1, 19, 5), '第 20-24 碼為建號 DD49 (必須截斷為 5 碼)');
        $this->myAssertSame('1151027', substr($recordD1, 24, 7), '第 25-31 碼為結案日期 RM33');
        $this->myAssertSame('01', substr($recordD1, 31, 2), '第 32-33 碼為異動性質 RM36');
        $this->myAssertSame('1151029', substr($recordD1, 33, 7), '第 34-40 碼為登記日期 DD05');
        $this->myAssertSame('01', substr($recordD1, 40, 2), '第 41-42 碼為登記原因 DD06');
        $this->myAssertSame('    150.25', substr($recordD1, 42, 10), '第 43-52 碼為總面積 DD08 (%10.2f)');
        $this->myAssertSame('  ', substr($recordD1, 52, 2), '分支 1 且 TYPE=\'A\' 時 DD46 必須為 2 個空白');
        $this->myAssertSame(str_repeat(' ', 52), substr($recordD1, 54, 52), '分支 1 末尾兩欄 DD45(2) 與 DD09(50) 共 52 bytes 恆為空白');

        // 2. 分支 1 且 TYPE='M' 時 DD46 有值
        $row1['TYPE'] = 'M';
        $recordD1_M = TaxExportAI037::formatRecordD($row1, 'branch1');
        $this->myAssertSame('01', substr($recordD1_M, 52, 2), '分支 1 且 TYPE=\'M\' 時 DD46 正常印出');
        $this->myAssertSame(str_repeat(' ', 52), substr($recordD1_M, 54, 52), '分支 1 且 TYPE=\'M\' 末尾 52 bytes 仍恆為空白');

        // 3. 分支 2/3/4 (normal 模式) 正常輸出 DD46, DD45, DD09
        $row2 = [
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
        ];
        $recordD2 = TaxExportAI037::formatRecordD($row2, 'normal', 'X');
        $this->myAssertSame(106, strlen($recordD2), 'D 紀錄 (normal 模式) 總長度必須為 106 bytes');
        $this->myAssertSame('01', substr($recordD2, 52, 2), 'normal 模式第 53-54 碼正常印出 DD46');
        $this->myAssertSame('HA', substr($recordD2, 54, 2), 'normal 模式第 55-56 碼正常印出 DD45');
        $addrBig5 = mb_convert_encoding('桃園市中壢區中央路２號', 'CP950', 'UTF-8');
        $this->myAssert(strpos(substr($recordD2, 56, 50), $addrBig5) === 0, 'normal 模式第 57-106 碼正常印出建物門牌 DD09');
    }

    public function testRecordELengthAndFormat(): void {
        echo "\n[*] 測試 E 紀錄 (建物所有權部) 定長 227 bytes 與欄位結構...\n";

        $row = [
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'EE01' => '0001',
            'EE05' => '1151027', // 7 碼日期，必須被截斷為前 4 碼
            'EE09' => 'A123456789',
            'LNAM' => '本所測試所有權人',
            'LCDE' => '1',
            'LADR' => '桃園市桃園區復興路１號',
            'EE15_1' => '1',
            'EE15_3' => '1', // 持分分子
            'EE15_2' => '2', // 持分分母
            'RM34' => '1151027',
            'RM33' => '1151029',
            'RM09' => '01'
        ];

        $recordE = TaxExportAI037::formatRecordE($row);
        $this->myAssertSame(227, strlen($recordE), 'E 紀錄總長度必須為 227 bytes');
        $this->myAssertSame('115A1HA000001E', substr($recordE, 0, 14), 'E 紀錄前 14 碼必須包含年字號與部別 E');
        $this->myAssertSame('A', substr($recordE, 14, 1), '第 15 碼為異動別 TYPE');
        $this->myAssertSame('0001', substr($recordE, 15, 4), '第 16-19 碼為登序 EE01');
        $this->myAssertSame('1151', substr($recordE, 19, 4), '第 20-23 碼 EE05 必須被截斷為 4 碼');
        $this->myAssertSame('A123456789', substr($recordE, 23, 10), '第 24-33 碼為所有權人統編 EE09');
        $this->myAssertSame('       1', substr($recordE, 185, 8), '持分 EE15_3 必須在前 (%8.0f)');
        $this->myAssertSame('       2', substr($recordE, 193, 8), '分母 EE15_2 必須在後 (%8.0f)');
        $this->myAssertSame('       0.0', substr($recordE, 201, 10), '第 15 欄固定為 10 碼 "       0.0" (%10.1f)');
        $this->myAssertSame('1151027', substr($recordE, 211, 7), '第 212-218 碼為原因發生日期 EE07');
        $this->myAssertSame('1151029', substr($recordE, 218, 7), '第 219-225 碼為結案日期 RM33');
        $this->myAssertSame('01', substr($recordE, 225, 2), '第 226-227 碼為登記原因 EE06');
    }

    public function testRecordGLengthAndFormat(): void {
        echo "\n[*] 測試 G 紀錄 (建物其他登記事項) 定長 1021 bytes...\n";

        $content1000 = str_repeat('B', 1000);
        $row = [
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'GG01' => '0001',
            'GG30_1' => '01',
            'GG30_2' => $content1000
        ];

        $recordG = TaxExportAI037::formatRecordG($row);
        $this->myAssertSame(1021, strlen($recordG), 'G 紀錄總長度必須為 1021 bytes');
        $this->myAssertSame('115A1HA000001G', substr($recordG, 0, 14), '前 14 碼包含部別 G');
        $this->myAssertSame('A', substr($recordG, 14, 1), '第 15 碼為異動別');
        $this->myAssertSame('0001', substr($recordG, 15, 4), '第 16-19 碼為登序 GG01');
        $this->myAssertSame('01', substr($recordG, 19, 2), '第 20-21 碼為事項代碼 GG30_1');
        $this->myAssertSame($content1000, substr($recordG, 21, 1000), '第 22-1021 碼為登記事項內容 (1000 bytes)');
    }

    public function testRecordHLengthAndFormat(): void {
        echo "\n[*] 測試 H 紀錄 (建物基地坐落 WRHD10) 定長 29 bytes...\n";

        $row = [
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'A',
            'HA48' => '1010',
            'HA49' => '02101940',
            'HD01' => '1',
            'HH_FLAG' => '1'
        ];

        $recordH = TaxExportAI037::formatRecordH($row);
        $this->myAssertSame(29, strlen($recordH), 'H 紀錄總長度必須為 29 bytes');
        $this->myAssertSame('115A1HA000001H', substr($recordH, 0, 14), '前 14 碼包含部別 H');
        $this->myAssertSame('A', substr($recordH, 14, 1), '第 15 碼為異動別 TYPE');
        $this->myAssertSame('1010', substr($recordH, 15, 4), '第 16-19 碼為基地段小段 HA48');
        $this->myAssertSame('02101940', substr($recordH, 19, 8), '第 20-27 碼為基地地號 HA49');
        $this->myAssertSame('1', substr($recordH, 27, 1), '第 28 碼為基地次序 HD01');
        $this->myAssertSame('1', substr($recordH, 28, 1), '第 29 碼為基地異動旗標 HH_FLAG');
    }

    public function testRecordMLengthAndFormat(): void {
        echo "\n[*] 測試 M 紀錄 (建物管理者資料) 定長 180 bytes...\n";

        $row = [
            'RM01' => '115',
            'RM02' => 'A1HA',
            'RM03' => '000001',
            'TYPE' => 'M',
            'EE01' => '0001',
            'EE09' => 'A123456789',
            'LNAM' => '測試建物管理者',
            'LCDE' => '1',
            'LADR' => '桃園市桃園區三民路一段'
        ];

        $recordM = TaxExportAI037::formatRecordM($row);
        $this->myAssertSame(180, strlen($recordM), 'M 紀錄總長度必須為 180 bytes');
        $this->myAssertSame('115A1HA000001M', substr($recordM, 0, 14), '前 14 碼包含部別 M');
        $this->myAssertSame('M', substr($recordM, 14, 1), '第 15 碼為異動別');
        $this->myAssertSame('0001', substr($recordM, 15, 4), '第 16-19 碼為登序');
        $this->myAssertSame('A123456789', substr($recordM, 19, 10), '第 20-29 碼為管理者統編');
    }

    public function testTrailerTagEncodingAndCount(): void {
        echo "\n[*] 測試檔尾 TAG 編碼與筆數格式...\n";

        $tag0 = TaxExportAI037::trailer(0);
        $expected0 = mb_convert_encoding('##### TAG #####共產製 0 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expected0, $tag0, '0 筆檔尾 TAG 正確性');

        $tag7 = TaxExportAI037::trailer(7);
        $expected7 = mb_convert_encoding('##### TAG #####共產製 7 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expected7, $tag7, '7 筆檔尾 TAG 正確性 (MS950 二進位吻合)');
    }

    public function testMockModeDeliverBuildingsData(): void {
        echo "\n[*] 測試 MOCK 模式產製 (涵蓋本所、他所與管理者，包含 D/E/G/H/M)...\n";

        $exporter = new TaxExportAI037();
        $outFile = $this->tempDir . '/AI037_mock.txt.part';

        $res = $exporter->export($outFile, ['code' => 'AI037', 'name' => '建物異動資料'], [
            'mock' => true,
            'begin' => '2026-10-27',
            'end' => '2026-10-29'
        ]);

        $this->myAssertSame('success', $res['state'], 'MOCK 模式必須回傳 success');
        $this->myAssert(file_exists($outFile), '產出檔案必須存在');

        $content = file_get_contents($outFile);
        $lines = explode("\n", $content);

        // 7 筆資料列 + 1 筆末尾 TAG (結尾無 \n)
        $this->myAssertSame(8, count($lines), '包含 7 筆資料與 1 筆 TAG');

        $d1 = $lines[0];
        $h1 = $lines[1];
        $e1 = $lines[2];
        $g1 = $lines[3];
        $d2 = $lines[4];
        $e2 = $lines[5];
        $m1 = $lines[6];
        $tag = $lines[7];

        $this->myAssertSame(106, strlen($d1), '第 1 筆為本所 D 紀錄 (106 bytes)');
        $this->myAssertSame(29, strlen($h1), '第 2 筆為本所 H 紀錄 (29 bytes)');
        $this->myAssertSame(227, strlen($e1), '第 3 筆為本所 E 紀錄 (227 bytes)');
        $this->myAssertSame(1021, strlen($g1), '第 4 筆為本所 G 紀錄 (1021 bytes)');
        $this->myAssertSame(106, strlen($d2), '第 5 筆為他所 D 紀錄 (106 bytes)');
        $this->myAssertSame(227, strlen($e2), '第 6 筆為他所 E 紀錄 (227 bytes)');
        $this->myAssertSame(180, strlen($m1), '第 7 筆為管理者 M 紀錄 (180 bytes)');

        // 驗證本所案件編號與他所案件編號
        $this->myAssert(strpos($d1, '115A1HA000001D') === 0, '包含本所案件 115A1HA000001D');
        $this->myAssert(strpos($d2, '115U1HA000002D') === 0, '包含他所案件 115U1HA000002D');
        $this->myAssert(strpos($m1, '115A1HA000003M') === 0, '包含管理者案件 115A1HA000003M');

        // 驗證 TAG
        $expectedTag = mb_convert_encoding('##### TAG #####共產製 7 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expectedTag, $tag, '結尾 TAG 筆數為 7 且格式正確');
    }

    public function testEmptyDataDeliverZeroTag(): void {
        echo "\n[*] 測試空資料產製 (0 筆案件輸出 0 筆 TAG 且無尾隨換行)...\n";

        $mockDb = function(string $sql): array {
            if (strpos($sql, 'KCDE_1=\'55\'') !== false) {
                return [['KRMK' => 'HA']];
            }
            if (strpos($sql, 'KCDE_1=\'06\'') !== false) {
                return [['KCDE_2' => '63'], ['KCDE_2' => '64']];
            }
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return []; // 查無主案件
            }
            return [];
        };

        $exporter = new TaxExportAI037($mockDb);
        $outFile = $this->tempDir . '/AI037_empty.txt.part';

        $res = $exporter->export($outFile, ['code' => 'AI037', 'name' => '建物異動資料'], [
            'mock' => false,
            'begin' => '2026-10-27',
            'end' => '2026-10-29'
        ]);

        $this->myAssertSame('empty', $res['state'], '查無資料時回傳 empty 狀態');
        $this->myAssert(file_exists($outFile), '產出檔案必須存在');

        $content = file_get_contents($outFile);
        $expected = mb_convert_encoding('##### TAG #####共產製 0 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expected, $content, '空資料時檔案內容恰好為 0 筆 TAG (無尾隨換行)');
    }

    public function testDirectOracleQueryBranches(): void {
        echo "\n[*] 測試直連 Oracle 四大互斥分支 (Seam 注入測試)...\n";

        // 1. 測試分支 1 (含建物標示部異動: D > 0)
        $dbBranch1 = function(string $sql): array {
            if (strpos($sql, 'KCDE_1=\'55\'') !== false) {
                return [['KRMK' => 'HA']];
            }
            if (strpos($sql, 'KCDE_1=\'06\'') !== false) {
                return [['KCDE_2' => '63']];
            }
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return [[
                    'RM01' => '115', 'RM02' => 'A1HA', 'RM03' => '000001',
                    'RM09' => '63', 'RM33' => '1151027', 'RM34' => '1151029', 'RM36' => '01'
                ]];
            }
            if (strpos($sql, 'FROM TRINDX') !== false && strpos($sql, 'GROUP BY') !== false) {
                return [
                    ['II00' => 'D', 'IP_TYPE' => 'A', 'CNT' => 1],
                    ['II00' => 'E', 'IP_TYPE' => 'A', 'CNT' => 1]
                ];
            }
            if (strpos($sql, 'FROM WRDBID') !== false) {
                // 分支 1 UNION 查詢: 回傳 1 筆 D, 1 筆 H, 1 筆 E
                return [
                    [
                        'DEPT' => 'D', 'SEQ' => '0', 'TYPE' => 'A',
                        'DD48' => '1010', 'DD49' => '00123000', 'DD45' => 'HA', 'DD46' => '01',
                        'EE01' => '0000', 'EE09' => '', 'RM34' => '1151029', 'EE05' => '', 'RM09' => '63',
                        'DD08' => '120.50', 'DD09' => '建物門牌',
                        'EE15_1' => '', 'EE15_2' => '', 'EE15_3' => '', 'GG01' => '', 'GG30_1' => '', 'GG30_2' => '',
                        'HA48' => '', 'HA49' => '', 'HD01' => '', 'HH_FLAG' => '', 'LNAM' => '', 'LCDE' => '', 'LADR' => ''
                    ],
                    [
                        'DEPT' => 'H', 'SEQ' => '2', 'TYPE' => 'A',
                        'DD48' => '1010', 'DD49' => '00123000', 'DD45' => '', 'DD46' => '',
                        'EE01' => '0000', 'EE09' => '', 'RM34' => '', 'EE05' => '', 'RM09' => '',
                        'DD08' => '', 'DD09' => '',
                        'EE15_1' => '', 'EE15_2' => '', 'EE15_3' => '', 'GG01' => '', 'GG30_1' => '', 'GG30_2' => '',
                        'HA48' => '1010', 'HA49' => '02101940', 'HD01' => '1', 'HH_FLAG' => '1',
                        'LNAM' => '', 'LCDE' => '', 'LADR' => ''
                    ],
                    [
                        'DEPT' => 'E', 'SEQ' => '3', 'TYPE' => 'A',
                        'DD48' => '1010', 'DD49' => '00123000', 'DD45' => '', 'DD46' => '',
                        'EE01' => '0001', 'EE09' => 'A123456789', 'RM34' => '1151027', 'EE05' => '1151', 'RM09' => '63',
                        'DD08' => '', 'DD09' => '',
                        'EE15_1' => '1', 'EE15_2' => '1', 'EE15_3' => '1', 'GG01' => '', 'GG30_1' => '', 'GG30_2' => '',
                        'HA48' => '', 'HA49' => '', 'HD01' => '', 'HH_FLAG' => '',
                        'LNAM' => '', 'LCDE' => '', 'LADR' => ''
                    ]
                ];
            }
            if (strpos($sql, 'FROM SRLNID') !== false) {
                return [['LIDN' => 'A123456789', 'LNAM' => '測試所有權人', 'LCDE' => '1', 'LADR' => '測試地址']];
            }
            return [];
        };

        $exporter1 = new TaxExportAI037($dbBranch1);
        $outFile1 = $this->tempDir . '/AI037_branch1.txt.part';
        $res1 = $exporter1->export($outFile1, ['code' => 'AI037', 'name' => '建物異動資料'], [
            'mock' => false,
            'begin' => '2026-10-27',
            'end' => '2026-10-29'
        ]);

        $this->myAssertSame('success', $res1['state'], '分支 1 執行成功');
        $lines1 = explode("\n", file_get_contents($outFile1));
        $this->myAssertSame(4, count($lines1), '產出 3 筆紀錄 + 1 筆 TAG');
        $this->myAssertSame(106, strlen($lines1[0]), '第 1 列為 106 bytes D 紀錄');
        $this->myAssertSame(29, strlen($lines1[1]), '第 2 列為 29 bytes H 紀錄');
        $this->myAssertSame(227, strlen($lines1[2]), '第 3 列為 227 bytes E 紀錄');
        $expectedTag3 = mb_convert_encoding('##### TAG #####共產製 3 筆資料', 'CP950', 'UTF-8');
        $this->myAssertSame($expectedTag3, $lines1[3], '結尾 TAG 為 3 筆');

        // 2. 測試分支 2 (無標示部異動，僅建物所有權部異動: E > 0)
        $dbBranch2 = function(string $sql): array {
            if (strpos($sql, 'KCDE_1=\'55\'') !== false) {
                return [['KRMK' => 'HA']];
            }
            if (strpos($sql, 'KCDE_1=\'06\'') !== false) {
                return [['KCDE_2' => '63']];
            }
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return [[
                    'RM01' => '115', 'RM02' => 'A1HA', 'RM03' => '000002',
                    'RM09' => '63', 'RM33' => '1151027', 'RM34' => '1151029', 'RM36' => '01'
                ]];
            }
            if (strpos($sql, 'FROM TRINDX') !== false && strpos($sql, 'GROUP BY') !== false) {
                return [
                    ['II00' => 'D', 'IP_TYPE' => 'A', 'CNT' => 0],
                    ['II00' => 'E', 'IP_TYPE' => 'A', 'CNT' => 1]
                ];
            }
            if (strpos($sql, 'FROM SRDBID') !== false) {
                return [
                    [
                        'DEPT' => 'D', 'SEQ' => '0', 'TYPE' => 'X',
                        'DD48' => '1010', 'DD49' => '00456000', 'DD45' => 'HA', 'DD46' => '01',
                        'EE01' => '0000', 'EE09' => '', 'RM34' => '1151029', 'EE05' => '', 'RM09' => '63',
                        'DD08' => '120.50', 'DD09' => '門牌二號',
                        'EE15_1' => '', 'EE15_2' => '', 'EE15_3' => '', 'GG01' => '', 'GG30_1' => '', 'GG30_2' => '',
                        'HA48' => '', 'HA49' => '', 'HD01' => '', 'HH_FLAG' => '', 'LNAM' => '', 'LCDE' => '', 'LADR' => ''
                    ],
                    [
                        'DEPT' => 'E', 'SEQ' => '3', 'TYPE' => 'A',
                        'DD48' => '1010', 'DD49' => '00456000', 'DD45' => '', 'DD46' => '',
                        'EE01' => '0001', 'EE09' => 'B987654321', 'RM34' => '1151027', 'EE05' => '1151', 'RM09' => '63',
                        'DD08' => '', 'DD09' => '',
                        'EE15_1' => '1', 'EE15_2' => '1', 'EE15_3' => '1', 'GG01' => '', 'GG30_1' => '', 'GG30_2' => '',
                        'HA48' => '', 'HA49' => '', 'HD01' => '', 'HH_FLAG' => '',
                        'LNAM' => '', 'LCDE' => '', 'LADR' => ''
                    ]
                ];
            }
            if (strpos($sql, 'FROM SRLNID') !== false) {
                return [['LIDN' => 'B987654321', 'LNAM' => '分支二所有權人', 'LCDE' => '1', 'LADR' => '測試地址二']];
            }
            return [];
        };

        $exporter2 = new TaxExportAI037($dbBranch2);
        $outFile2 = $this->tempDir . '/AI037_branch2.txt.part';
        $res2 = $exporter2->export($outFile2, ['code' => 'AI037', 'name' => '建物異動資料'], [
            'mock' => false,
            'begin' => '2026-10-27',
            'end' => '2026-10-29'
        ]);

        $this->myAssertSame('success', $res2['state'], '分支 2 執行成功');
        $lines2 = explode("\n", file_get_contents($outFile2));
        $this->myAssertSame(3, count($lines2), '分支 2 產出 2 筆紀錄 + 1 筆 TAG');
        $this->myAssertSame(106, strlen($lines2[0]), '分支 2 第 1 列為 106 bytes D 紀錄');
        $this->myAssertSame('HA', substr($lines2[0], 54, 2), '分支 2 正常輸出 DD45');
        $this->myAssertSame(227, strlen($lines2[1]), '分支 2 第 2 列為 227 bytes E 紀錄');
    }
}

// 支援命令列直接執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new TaxExportAI037Test();
    $ok = $test->runAll();
    exit($ok ? 0 : 1);
}
