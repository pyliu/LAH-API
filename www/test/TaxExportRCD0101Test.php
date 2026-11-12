<?php

if (class_exists('PHPUnit\Framework\TestCase')) {
    abstract class BaseTaxExportRCD0101TestCase extends \PHPUnit\Framework\TestCase {}
} else {
    abstract class BaseTaxExportRCD0101TestCase {}
}

$incPaths = [
    dirname(__DIR__) . '/include/api/TaxExportRCD0101.class.php',
    dirname(__DIR__, 2) . '/include/api/TaxExportRCD0101.class.php',
    dirname(__DIR__, 3) . '/www/include/api/TaxExportRCD0101.class.php',
    __DIR__ . '/../include/api/TaxExportRCD0101.class.php'
];
foreach ($incPaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        break;
    }
}

class TaxExportRCD0101Test extends BaseTaxExportRCD0101TestCase {
    private $tempDir;
    private $passed = 0;
    private $failed = 0;

    public function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/lah_rcd0101_test_' . bin2hex(random_bytes(6));
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0700, true);
        }
    }

    public function tearDown(): void {
        if ($this->tempDir && is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($this->tempDir);
        }
    }

    private function myAssert($condition, string $message): void {
        if (is_callable(['parent', 'assertTrue'])) {
            $this->assertTrue((bool)$condition, $message);
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
        echo "  TaxExportRCD0101 (土地地籍異動通知書 PDF) 單元測試套件\n";
        echo "=======================================================\n";

        $this->testHelperFunctions();
        $this->testPdfWriterEncodingAndFont();
        $this->testMockModeGenerationAndPages();
        $this->testEmptyDataReturnsEmptyAndNoFile();
        $this->testLocalVsRemoteOfficeTitleAndQuery();
        $this->testDirectOracleSeamInjectionWithAllSections();

        echo "\n-------------------------------------------------------\n";
        echo "測試結果: 通過 {$this->passed} 項, 失敗 {$this->failed} 項\n";
        echo "-------------------------------------------------------\n";
        $this->tearDown();
        return $this->failed === 0;
    }

    /**
     * 測試輔助轉換函式 (formatLandNo, rocDate, formatRocDate, formatRocDateLong)
     */
    public function testHelperFunctions(): void {
        echo "\n[*] 測試輔助轉換函式 (formatLandNo, rocDate, formatRocDateLong)...\n";

        // 1. 地號格式化
        $this->myAssertSame('101', TaxExportRCD0101::formatLandNo('01010000'), '地號 01010000 格式化為 101');
        $this->myAssertSame('101-1', TaxExportRCD0101::formatLandNo('01010001'), '地號 01010001 格式化為 101-1');
        $this->myAssertSame('202-35', TaxExportRCD0101::formatLandNo('02020035'), '地號 02020035 格式化為 202-35');
        $this->myAssertSame('0', TaxExportRCD0101::formatLandNo('00000000'), '全零地號 00000000 格式化為 0');

        // 2. 民國日期轉換
        $this->myAssertSame('1151029', TaxExportRCD0101::rocDate('2026-10-29'), '西元 2026-10-29 轉民國 1151029');
        $this->myAssertSame('115/10/29', TaxExportRCD0101::formatRocDate('1151029'), '1151029 轉 115/10/29');
        $this->myAssertSame('中華民國 115 年 10 月 29 日', TaxExportRCD0101::formatRocDateLong('1151029'), '1151029 轉長格式國字日期');
    }

    /**
     * 測試 TaxExportPdfWriter 中文字型與 Big5 轉碼
     */
    public function testPdfWriterEncodingAndFont(): void {
        echo "\n[*] 測試 TaxExportPdfWriter 繁體中文轉碼與 CID-0 字型定義...\n";

        $pdf = new TaxExportPdfWriter('L', 'mm', 'A4');
        $pdf->AddPage('L', 'A4');

        $zhText = '桃園市桃園地政事務所土地地籍異動通知書';
        $big5 = TaxExportPdfWriter::toBig5($zhText);
        $this->myAssert(strlen($big5) > 0, 'UTF-8 成功轉為 Big5');
        $this->myAssert($big5 !== $zhText, 'Big5 位元組與 UTF-8 不同');

        // 測試單元格與字串寬度
        $w = $pdf->getStringWidthZh($zhText);
        $this->myAssert($w > 50, '取得 Big5 字串寬度正常 (' . round($w, 1) . ' mm)');

        $out = $this->tempDir . '/test_writer.pdf';
        $pdf->cellZh(0, 10, $zhText, 1, 1, 'C');
        $pdf->Output('F', $out);

        $this->myAssert(is_file($out), 'PDF 檔案成功產出');
        $content = file_get_contents($out);
        $this->myAssert(substr($content, 0, 5) === '%PDF-', 'PDF 標頭以 %PDF- 起始');
        $this->myAssert(strpos($content, '/ETen-B5-H') !== false, 'PDF 包含 /ETen-B5-H CMap');
        $this->myAssert(strpos($content, '/MSung-Light-Acro') !== false, 'PDF 包含 /MSung-Light-Acro 字型');
    }

    /**
     * 測試 MOCK 模式產製與分頁驗證
     */
    public function testMockModeGenerationAndPages(): void {
        echo "\n[*] 測試 MOCK 模式產製通知書與分頁大小...\n";

        $exporter = new TaxExportRCD0101();
        $out = $this->tempDir . '/mock_notice.pdf';
        $item = ['code' => 'RCD0101_LOCAL'];
        $batch = [
            'begin' => '2026-10-01',
            'end' => '2026-10-31',
            'mock' => true
        ];

        $res = $exporter->export($out, $item, $batch);
        $this->myAssertSame('success', $res['state'], 'MOCK 模式回傳 state 為 success');
        $this->myAssert(is_file($out), '產出檔案存在');

        // 使用 FPDI 檢驗頁面
        if (class_exists(\setasign\Fpdi\Fpdi::class)) {
            $fpdi = new \setasign\Fpdi\Fpdi();
            $pages = $fpdi->setSourceFile($out);
            $this->myAssert($pages >= 2, 'MOCK 模式產出至少 2 頁通知書 (實際: ' . $pages . ' 頁)');

            for ($p = 1; $p <= $pages; $p++) {
                $tpl = $fpdi->importPage($p);
                $size = $fpdi->getTemplateSize($tpl);
                $this->myAssert($size['width'] > 290 && $size['width'] < 300, "第 {$p} 頁寬度約 297mm (Landscape A4)");
                $this->myAssert($size['height'] > 205 && $size['height'] < 215, "第 {$p} 頁高度約 210mm (Landscape A4)");
            }
        }
    }

    /**
     * 測試查無資料時回傳 empty 且不殘留檔案
     */
    public function testEmptyDataReturnsEmptyAndNoFile(): void {
        echo "\n[*] 測試查無案件時回傳 empty 且不留下檔案...\n";

        $mockDb = function(string $sql, array $params = []) {
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='55'") !== false) {
                return [['KCNT' => '桃園', 'KRMK' => 'HA']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='66'") !== false) {
                return [['KCNT' => '桃園市', 'KRMK' => 'H']];
            }
            // 主查詢 SCRSMS 查無資料
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return [];
            }
            return [];
        };

        $exporter = new TaxExportRCD0101($mockDb);
        $out = $this->tempDir . '/empty_test.pdf';
        $item = ['code' => 'RCD0101_LOCAL'];
        $batch = [
            'begin' => '2026-10-01',
            'end' => '2026-10-31',
            'mock' => false
        ];

        $res = $exporter->export($out, $item, $batch);
        $this->myAssertSame('empty', $res['state'], '主查詢無資料時回傳 empty');
        $this->myAssert(!is_file($out), '查無資料時不殘留 PDF 檔案');
    }

    /**
     * 測試本所 vs 他所跨所之機關名稱與 SQL 條件
     */
    public function testLocalVsRemoteOfficeTitleAndQuery(): void {
        echo "\n[*] 測試本所與他所條件過濾與表頭機關名稱...\n";

        $executedSqls = [];
        $mockDb = function(string $sql, array $params = []) use (&$executedSqls) {
            $executedSqls[] = ['sql' => $sql, 'params' => $params];

            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='55'") !== false) {
                return [['KCNT' => '桃園', 'KRMK' => 'HA']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='66'") !== false) {
                return [['KCNT' => '桃園市', 'KRMK' => 'H']];
            }
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return []; // 僅檢查 SQL 構建
            }
            return [];
        };

        // 1. 本所
        $executedSqls = [];
        $exporterLocal = new TaxExportRCD0101($mockDb);
        $outLocal = $this->tempDir . '/local_test.pdf';
        $exporterLocal->export($outLocal, ['code' => 'RCD0101_LOCAL'], ['begin' => '2026-10-01', 'end' => '2026-10-31', 'mock' => false]);

        $hasLocalCondition = false;
        foreach ($executedSqls as $entry) {
            if (strpos($entry['sql'], 'FROM SCRSMS') !== false) {
                if (strpos($entry['sql'], 'RM99 IS NULL OR RM99 = \'N\'') !== false) {
                    $hasLocalCondition = true;
                }
            }
        }
        $this->myAssert($hasLocalCondition, '本所查詢包含 (RM99 IS NULL OR RM99 = \'N\')');

        // 2. 他所
        $executedSqls = [];
        $exporterRemote = new TaxExportRCD0101($mockDb);
        $outRemote = $this->tempDir . '/remote_test.pdf';
        $exporterRemote->export($outRemote, ['code' => 'RCD0101_REMOTE'], ['begin' => '2026-10-01', 'end' => '2026-10-31', 'mock' => false]);

        $hasRemoteCondition = false;
        foreach ($executedSqls as $entry) {
            if (strpos($entry['sql'], 'FROM SCRSMS') !== false) {
                if (strpos($entry['sql'], 'RM99 = \'Y\'') !== false && strpos($entry['sql'], 'RM100') !== false) {
                    $hasRemoteCondition = true;
                }
            }
        }
        $this->myAssert($hasRemoteCondition, '他所查詢包含 RM99 = \'Y\' 且過濾跨所代碼 RM100');
    }

    /**
     * 測試 Seam 注入：真實模擬 Oracle 逐案組裝三子表並成功生成 PDF
     */
    public function testDirectOracleSeamInjectionWithAllSections(): void {
        echo "\n[*] 測試直連 Oracle Seam 注入 (標示部、所有權部、管理機關部完整流程)...\n";

        $mockDb = function(string $sql, array $params = []) {
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='55'") !== false) {
                return [['KCNT' => '桃園', 'KRMK' => 'HA']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='66'") !== false) {
                return [['KCNT' => '桃園市', 'KRMK' => 'H']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='04'") !== false) {
                return [['KCNT' => '桃資', 'KRMK' => '']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='06'") !== false) {
                return [['KCNT' => '買賣', 'KRMK' => '']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='48'") !== false) {
                return [['KCNT' => '中路段', 'KRMK' => '01']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='46'") !== false) {
                return [['KCNT' => '桃園區', 'KRMK' => '']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='08'") !== false) {
                return [['KCNT' => '田', 'KRMK' => '']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='11'") !== false) {
                return [['KCNT' => '農業區', 'KRMK' => '']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='12'") !== false) {
                return [['KCNT' => '農牧用地', 'KRMK' => '']];
            }
            if (strpos($sql, 'FROM SRKEYN') !== false && strpos($sql, "KCDE_1='15'") !== false) {
                return [['KCNT' => '全部', 'KRMK' => '']];
            }

            // 主案件
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return [
                    [
                        'RM01' => '115',
                        'RM02' => 'A1HA',
                        'RM03' => '000001',
                        'RM04' => '01',
                        'RM05' => '01',
                        'RM06' => '01',
                        'RM07_1' => '1151029',
                        'RM33' => '1151029',
                        'RM34' => '1151001',
                        'RM36' => '02', // 分割
                        'RM61' => '0000100',
                        'RM55' => 'U001',
                        'RM57' => 'U002',
                        'RM09' => '01'
                    ]
                ];
            }

            // 人員
            if (strpos($sql, 'FROM sysauth1') !== false) {
                return [['USER_NAME' => '審查員A']];
            }

            // TRINDX countA & countB
            if (strpos($sql, 'FROM TRINDX') !== false && strpos($sql, "II00='A'") !== false && strpos($sql, 'COUNT(*)') !== false) {
                return [['C' => 1]];
            }
            if (strpos($sql, 'FROM TRINDX') !== false && strpos($sql, "II00='B'") !== false && strpos($sql, 'COUNT(*)') !== false) {
                return [['C' => 1]];
            }

            // 地號清單
            if (strpos($sql, 'FROM TRINDX') !== false && strpos($sql, 'LAND_KEY') !== false) {
                return [['LAND_KEY' => '101001010000']];
            }

            // 分割記事
            if (strpos($sql, 'FROM TRINDX') !== false && strpos($sql, "SUBSTR(IIKEY,1,5)='A'||:aa48") !== false) {
                return [['NO' => '01010000']];
            }

            // 標示部 WRALID
            if (strpos($sql, 'FROM WRALID') !== false && strpos($sql, "IN ('M','D')") !== false) {
                return [
                    [
                        'AS_TYPE' => 'M', 'AA46' => '01', 'AA08' => '01', 'AA09' => '01',
                        'AA10' => '4646.00', 'AA11' => '01', 'AA12' => '01', 'AA16' => '12000.0',
                        'AA05' => '1151001', 'AA06' => '01'
                    ]
                ];
            }
            if (strpos($sql, 'FROM WRALID') !== false && strpos($sql, "IN ('N','A')") !== false) {
                return [
                    [
                        'AS_TYPE' => 'A', 'AA46' => '01', 'AA08' => '01', 'AA09' => '01',
                        'AA10' => '4646.00', 'AA11' => '01', 'AA12' => '01', 'AA16' => '13500.0',
                        'AA05' => '1151001', 'AA06' => '01'
                    ]
                ];
            }

            // 所有權部 TRINDX
            if (strpos($sql, 'FROM TRINDX') !== false && strpos($sql, 'OWN_KEY') !== false) {
                return [['OWN_KEY' => '1010010100000001']];
            }

            // WRBLOW
            if (strpos($sql, 'FROM WRBLOW') !== false && strpos($sql, "IN ('M','D')") !== false) {
                return [
                    [
                        'BS_TYPE' => 'M', 'BB09' => 'A123456789', 'BB15_1' => 'A',
                        'BB15_2' => '1', 'BB15_3' => '1', 'BB21' => '3500.0',
                        'BB06' => '01', 'BB07' => '1151001', 'BB05' => '1151029'
                    ]
                ];
            }
            if (strpos($sql, 'FROM WRBLOW') !== false && strpos($sql, "IN ('A','N')") !== false) {
                return [
                    [
                        'BS_TYPE' => 'A', 'BB09' => 'B223456789', 'BB15_1' => 'A',
                        'BB15_2' => '1', 'BB15_3' => '1', 'BB21' => '3800.0',
                        'BB06' => '01', 'BB07' => '1151001', 'BB05' => '1151029'
                    ]
                ];
            }

            // SRLNID
            if (strpos($sql, 'FROM SRLNID') !== false) {
                return [['LIDN' => 'A123456789', 'LNAM' => '王大明', 'LADR' => '桃園市桃園區中山路1號']];
            }

            // WPBLOW
            if (strpos($sql, 'FROM WPBLOW') !== false) {
                return [['LA21' => '3800.0']];
            }

            // WRMNGR
            if (strpos($sql, 'FROM WRMNGR') !== false) {
                return [
                    [
                        'MM00' => 'B', 'MS_TYPE' => 'A', 'MM13' => '03720803', 'MM01' => '0001'
                    ]
                ];
            }

            return [];
        };

        $exporter = new TaxExportRCD0101($mockDb);
        $out = $this->tempDir . '/oracle_seam_test.pdf';
        $item = ['code' => 'RCD0101_LOCAL'];
        $batch = [
            'begin' => '2026-10-01',
            'end' => '2026-10-31',
            'mock' => false
        ];

        $res = $exporter->export($out, $item, $batch);
        $this->myAssertSame('success', $res['state'], 'Oracle 直連產製回傳 success');
        $this->myAssert(is_file($out), '成功產出通知書 PDF 檔案');
        $this->myAssert(filesize($out) > 1000, '產出 PDF 檔案大小大於 1KB (' . filesize($out) . ' bytes)');

        if (class_exists(\setasign\Fpdi\Fpdi::class)) {
            $fpdi = new \setasign\Fpdi\Fpdi();
            $pages = $fpdi->setSourceFile($out);
            $this->myAssertSame(1, $pages, '成功解析恰好 1 頁通知書');
        }
    }
}

// 支援直接 CLI 執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    $test = new TaxExportRCD0101Test();
    $ok = $test->runAll();
    exit($ok ? 0 : 1);
}
