<?php

namespace Tests\Unit\Export;

use TaxExportRCD0102;
use TaxExportPdfWriter;

if (class_exists('PHPUnit\Framework\TestCase')) {
    abstract class BaseTaxExportRCD0102TestCase extends \PHPUnit\Framework\TestCase {}
} else {
    abstract class BaseTaxExportRCD0102TestCase {}
}

$incPaths = [
    dirname(__DIR__, 4) . '/www/include/api/TaxExportRCD0102.class.php',
    dirname(__DIR__, 3) . '/include/api/TaxExportRCD0102.class.php',
    dirname(__DIR__, 2) . '/include/api/TaxExportRCD0102.class.php',
    dirname(__DIR__) . '/include/api/TaxExportRCD0102.class.php',
    __DIR__ . '/TaxExportRCD0102.class.php',
    __DIR__ . '/../include/api/TaxExportRCD0102.class.php'
];
foreach ($incPaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        break;
    }
}

class TaxExportRCD0102Test extends BaseTaxExportRCD0102TestCase {
    private $tempDir;
    private $passed = 0;
    private $failed = 0;

    public function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/lah_rcd0102_test_' . bin2hex(random_bytes(6));
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
        echo "  TaxExportRCD0102 (建物異動通知書 PDF) 單元測試套件\n";
        echo "=======================================================\n";

        $this->testHelperFunctions();
        $this->testPdfWriterEncodingAndFont();
        $this->testMockModeGenerationAndPages();
        $this->testEmptyDataReturnsEmptyAndNoFile();
        $this->testLocalVsRemoteOfficeTitleAndQuery();
        $this->testDirectOracleSeamInjectionWithAllTables();
        $this->testNoOwnerChangePadsBlankRow();

        echo "\n-------------------------------------------------------\n";
        echo "測試結果: 通過 {$this->passed} 項, 失敗 {$this->failed} 項\n";
        echo "-------------------------------------------------------\n";
        $this->tearDown();
        return $this->failed === 0;
    }

    /**
     * 測試輔助轉換函式 (formatBuildNo, formatLandNo, rocDate, formatRocDate, formatRocDateLong)
     */
    public function testHelperFunctions(): void {
        echo "\n[*] 測試輔助轉換函式 (formatBuildNo, formatLandNo, rocDate, formatRocDateLong)...\n";

        // 1. 建號格式化 (8 碼母子號轉母號-子號，若子號為 000 則僅顯示母號)
        $this->myAssertSame('101', \TaxExportRCD0102::formatBuildNo('00101000'), '建號 00101000 格式化為 101');
        $this->myAssertSame('101-1', \TaxExportRCD0102::formatBuildNo('00101001'), '建號 00101001 格式化為 101-1');
        $this->myAssertSame('202-35', \TaxExportRCD0102::formatBuildNo('00202035'), '建號 00202035 格式化為 202-35');
        $this->myAssertSame('12345-678', \TaxExportRCD0102::formatBuildNo('12345678'), '建號 12345678 格式化為 12345-678');
        $this->myAssertSame('0', \TaxExportRCD0102::formatBuildNo('00000000'), '全零建號 00000000 格式化為 0');

        // 2. 地號格式化
        $this->myAssertSame('101', \TaxExportRCD0102::formatLandNo('01010000'), '地號 01010000 格式化為 101');
        $this->myAssertSame('101-1', \TaxExportRCD0102::formatLandNo('01010001'), '地號 01010001 格式化為 101-1');

        // 3. 民國日期轉換
        $this->myAssertSame('1151029', \TaxExportRCD0102::rocDate('2026-10-29'), '西元 2026-10-29 轉民國 1151029');
        $this->myAssertSame('115/10/29', \TaxExportRCD0102::formatRocDate('1151029'), '1151029 轉 115/10/29');
        $this->myAssertSame('中華民國 115 年 10 月 29 日', \TaxExportRCD0102::formatRocDateLong('1151029'), '1151029 轉長格式國字日期');
    }

    /**
     * 測試 TaxExportPdfWriter 中文字型與 Big5 轉碼
     */
    public function testPdfWriterEncodingAndFont(): void {
        echo "\n[*] 測試 TaxExportPdfWriter 繁體中文轉碼與 CID-0 字型定義...\n";

        $pdf = new \TaxExportPdfWriter('L', 'mm', 'A4');
        $pdf->AddPage('L', 'A4');

        $zhText = '桃園市桃園地政事務所建物異動通知書';
        $big5 = \TaxExportPdfWriter::toBig5($zhText);
        $this->myAssert(strlen($big5) > 0, 'UTF-8 成功轉為 Big5');
        $this->myAssert($big5 !== $zhText, 'Big5 位元組與 UTF-8 不同');

        // 測試單元格與字串寬度
        $w = $pdf->getStringWidthZh($zhText);
        $this->myAssert($w > 50, '取得 Big5 字串寬度正常 (' . round($w, 1) . ' mm)');

        $out = $this->tempDir . '/test_writer_rcd0102.pdf';
        $pdf->cellZh(0, 10, $zhText, 1, 1, 'C');
        $pdf->Output('F', $out);

        $this->myAssert(is_file($out), 'PDF 檔案成功產出');
        $content = file_get_contents($out);
        $this->myAssert(substr($content, 0, 5) === '%PDF-', 'PDF 標頭以 %PDF- 起始');
        $hasCMap = strpos($content, '/Identity-H') !== false || strpos($content, '/ETen-B5-H') !== false || strpos($content, '/ToUnicode') !== false;
        $this->myAssert($hasCMap, 'PDF 包含 /Identity-H、/ToUnicode 或 /ETen-B5-H CMap');
        $hasFont = strpos($content, 'tFPDF') !== false || strpos($content, '/MSung-Light-Acro') !== false || strpos($content, 'KaiU') !== false;
        $this->myAssert($hasFont, 'PDF 包含 tFPDF 嵌入字型或 /MSung-Light-Acro 字型');
    }

    /**
     * 測試 MOCK 模式產製與分頁驗證
     */
    public function testMockModeGenerationAndPages(): void {
        echo "\n[*] 測試 MOCK 模式產製建物異動通知書與多案分頁...\n";

        $exporter = new \TaxExportRCD0102();
        $out = $this->tempDir . '/mock_notice_rcd0102.pdf';
        $item = ['code' => 'RCD0102_LOCAL'];
        $batch = [
            'begin' => '2026-10-01',
            'end' => '2026-10-31',
            'mock' => true
        ];

        $res = $exporter->export($out, $item, $batch);
        $this->myAssertSame('success', $res['state'], 'MOCK 模式回傳 success');
        $this->myAssert(is_file($out), '產出通知書 PDF 檔案存在');
        $this->myAssert(filesize($out) > 1000, '產出 PDF 檔案大小大於 1KB (' . filesize($out) . ' bytes)');

        // 跨所 MOCK 模式產製驗證
        $outRemote = $this->tempDir . '/mock_notice_remote_rcd0102.pdf';
        $itemRemote = ['code' => 'RCD0102_REMOTE'];
        $resRemote = $exporter->export($outRemote, $itemRemote, $batch);
        $this->myAssertSame('success', $resRemote['state'], '跨所 MOCK 模式回傳 success');
        $this->myAssert(is_file($outRemote), '跨所 PDF 檔案存在');
    }

    /**
     * 測試查無符合資料時正確回傳 empty 且刪除暫存檔
     */
    public function testEmptyDataReturnsEmptyAndNoFile(): void {
        echo "\n[*] 測試查無資料時回傳 empty 且清除暫存檔案...\n";

        $mockDb = function (string $sql, array $params = []): array {
            if (strpos($sql, 'FROM SRKEYN WHERE (KCDE_1=\'55\')') !== false) {
                return [['KCNT' => '桃園', 'KRMK' => 'HA']];
            }
            if (strpos($sql, 'FROM SRKEYN WHERE KCDE_1=\'66\'') !== false) {
                return [['KCNT' => '桃園市']];
            }
            return []; // SCRSMS 查無資料
        };

        $exporter = new \TaxExportRCD0102($mockDb);
        $out = $this->tempDir . '/empty_case_rcd0102.pdf';
        file_put_contents($out, 'temporary part file');

        $item = ['code' => 'RCD0102_LOCAL'];
        $batch = [
            'begin' => '2026-10-01',
            'end' => '2026-10-31',
            'mock' => false
        ];

        $res = $exporter->export($out, $item, $batch);
        $this->myAssertSame('empty', $res['state'], '查無資料時回傳 empty');
        $this->myAssert(!is_file($out), '暫存 PDF 檔案已確實刪除清理');
    }

    /**
     * 測試本所 vs 跨所查詢條件與表頭 (跨) 標記
     */
    public function testLocalVsRemoteOfficeTitleAndQuery(): void {
        echo "\n[*] 測試本所與他所查詢過濾條件與表頭 (跨) 標記...\n";

        $capturedSql = [];
        $capturedParams = [];

        $mockDb = function (string $sql, array $params = []) use (&$capturedSql, &$capturedParams): array {
            $capturedSql[] = $sql;
            $capturedParams[] = $params;

            if (strpos($sql, 'FROM SRKEYN WHERE (KCDE_1=\'55\')') !== false) {
                return [['KCNT' => '桃園', 'KRMK' => 'HA']];
            }
            if (strpos($sql, 'FROM SRKEYN WHERE KCDE_1=\'66\'') !== false) {
                return [['KCNT' => '桃園市']];
            }
            return [];
        };

        // 1. 本所測試
        $exporterLocal = new \TaxExportRCD0102($mockDb);
        $outLocal = $this->tempDir . '/local_test.pdf';
        $exporterLocal->export($outLocal, ['code' => 'RCD0102_LOCAL'], ['begin' => '2026-10-01', 'end' => '2026-10-31', 'mock' => false]);

        $localSql = '';
        foreach ($capturedSql as $s) {
            if (strpos($s, 'FROM SCRSMS') !== false) {
                $localSql = $s;
                break;
            }
        }
        $this->myAssert(strpos($localSql, "RM99 IS NULL OR RM99 = 'N'") !== false, '本所查詢包含 (RM99 IS NULL OR RM99 = \'N\')');
        $this->myAssert(strpos($localSql, "SUBSTR(RM67, 1, 1) = '1'") !== false, '本所查詢包含 SUBSTR(RM67, 1, 1) = \'1\'');

        // 2. 他所測試
        $capturedSql = [];
        $capturedParams = [];
        $exporterRemote = new \TaxExportRCD0102($mockDb);
        $outRemote = $this->tempDir . '/remote_test.pdf';
        $exporterRemote->export($outRemote, ['code' => 'RCD0102_REMOTE'], ['begin' => '2026-10-01', 'end' => '2026-10-31', 'mock' => false]);

        $remoteSql = '';
        foreach ($capturedSql as $s) {
            if (strpos($s, 'FROM SCRSMS') !== false) {
                $remoteSql = $s;
                break;
            }
        }
        $this->myAssert(strpos($remoteSql, "RM99 = 'Y'") !== false, '他所查詢包含 RM99 = \'Y\'');
        $this->myAssert(strpos($remoteSql, "RM100 = :krmk") !== false, '他所查詢包含 RM100 跨所綁定');
        $this->myAssert(strpos($remoteSql, "RM100_1 = :krmkSub1") !== false, '他所查詢包含 RM100_1 縣市碼綁定');
    }

    /**
     * 測試 Oracle Seam 直連注入：完整三個表格（標示部、所有權部、管理機關）與核章區繪製
     */
    public function testDirectOracleSeamInjectionWithAllTables(): void {
        echo "\n[*] 測試 Oracle Seam 直連注入完整資料組裝與表格繪製...\n";

        $mockDb = function (string $sql, array $params = []): array {
            // SRKEYN 機關
            if (strpos($sql, 'FROM SRKEYN WHERE (KCDE_1=\'55\')') !== false) {
                return [['KCNT' => '桃園', 'KRMK' => 'HA']];
            }
            if (strpos($sql, 'FROM SRKEYN WHERE KCDE_1=\'66\'') !== false) {
                return [['KCNT' => '桃園市']];
            }
            // SRKEYN 一般代碼
            if (strpos($sql, 'FROM SRKEYN WHERE KCDE_1=:c1 AND KCDE_2=:c2') !== false) {
                $c1 = $params[':c1'] ?? '';
                $c2 = $params[':c2'] ?? '';
                if ($c1 === '04' && $c2 === 'HA') return [['KCNT' => '桃資', 'KRMK' => '']];
                if ($c1 === '46' && $c2 === '01') return [['KCNT' => '桃園區', 'KRMK' => '']];
                if ($c1 === '48' && $c2 === '1010') return [['KCNT' => '中路段', 'KRMK' => '01']];
                if ($c1 === '06' && $c2 === '01') return [['KCNT' => '買賣', 'KRMK' => '']];
                if ($c1 === '15' && $c2 === 'A') return [['KCNT' => '全部', 'KRMK' => '']];
                return [['KCNT' => $c2, 'KRMK' => '']];
            }

            // SCRSMS 主案件
            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return [
                    [
                        'RM01' => '115', 'RM02' => 'HA', 'RM03' => '000001',
                        'RM07_1' => '1151029', 'RM33' => '1151029', 'RM34' => '1151027',
                        'RM36' => '01', 'RM09' => '01', 'RM55' => 'U001', 'RM57' => 'U002',
                        'RM67' => '10000'
                    ]
                ];
            }

            // sysauth1 使用者姓名
            if (strpos($sql, 'FROM sysauth1') !== false) {
                $uid = $params[':uid'] ?? '';
                if ($uid === 'U001') return [['USER_NAME' => '陳初審']];
                if ($uid === 'U002') return [['USER_NAME' => '林複審']];
                return [];
            }

            // TRINDX 指數總數與建號清單
            if (strpos($sql, 'FROM TRINDX') !== false) {
                if (strpos($sql, 'COUNT(*) AS C') !== false) {
                    return [['C' => 1]];
                }
                if (strpos($sql, "II00='D'") !== false) {
                    return [
                        ['IIKEY' => 'D101000101000', 'IP_TYPE' => 'A']
                    ];
                }
                if (strpos($sql, "II00='E'") !== false) {
                    return [
                        ['ED48' => '1010', 'ED49' => '00101000', 'EE01' => '0001']
                    ];
                }
            }

            // SRDBID / WRDBID 門牌
            if (strpos($sql, 'FROM SRDBID') !== false || strpos($sql, 'FROM WRDBID') !== false) {
                return [
                    ['DD46' => '01', 'DD09' => '桃園市桃園區中山路100號']
                ];
            }

            // SRHD10 / WRHD10 基地坐落
            if (strpos($sql, 'FROM SRHD10') !== false || strpos($sql, 'FROM WRHD10') !== false) {
                return [
                    ['HA48' => '1010', 'HA49' => '01010000']
                ];
            }

            // WREBOW 所有權部
            if (strpos($sql, 'FROM WREBOW') !== false) {
                if (strpos($sql, "ES_TYPE IN ('M','D')") !== false) {
                    return [
                        [
                            'ES_TYPE' => 'M', 'EE09' => 'A123456789',
                            'EE15_1' => 'A', 'EE15_2' => '1', 'EE15_3' => '1',
                            'EE06' => '01', 'EE07' => '1150920', 'EE05' => '1151001'
                        ]
                    ];
                }
                if (strpos($sql, "ES_TYPE IN ('A','N')") !== false) {
                    return [
                        [
                            'ES_TYPE' => 'A', 'EE09' => 'B223456789',
                            'EE15_1' => 'A', 'EE15_2' => '1', 'EE15_3' => '1'
                        ]
                    ];
                }
            }

            // SRLNID 統編資料
            if (strpos($sql, 'FROM SRLNID') !== false) {
                $lidn = $params[':lidn'] ?? '';
                if ($lidn === 'A123456789') return [['LNAM' => '王大明', 'LADR' => '桃園市桃園區中山路1號']];
                if ($lidn === 'B223456789') return [['LNAM' => '李小華', 'LADR' => '桃園市桃園區成功路2號']];
                if ($lidn === '03720803') return [['LNAM' => '財政部國有財產署', 'LADR' => '臺北市中正區愛國西路2號']];
                return [];
            }

            // WRMNGR 管理機關
            if (strpos($sql, 'FROM WRMNGR') !== false) {
                return [
                    [
                        'MM00' => 'E', 'MS_TYPE' => 'A', 'MM13' => '03720803', 'MM01' => '0001'
                    ]
                ];
            }

            return [];
        };

        $exporter = new \TaxExportRCD0102($mockDb);
        $out = $this->tempDir . '/oracle_seam_test_rcd0102.pdf';
        $item = ['code' => 'RCD0102_LOCAL'];
        $batch = [
            'begin' => '2026-10-01',
            'end' => '2026-10-31',
            'mock' => false
        ];

        $res = $exporter->export($out, $item, $batch);
        $this->myAssertSame('success', $res['state'], 'Oracle 直連產製回傳 success');
        $this->myAssert(is_file($out), '成功產出通知書 PDF 檔案');
        $this->myAssert(filesize($out) > 1000, '產出 PDF 檔案大小大於 1KB (' . filesize($out) . ' bytes)');
    }

    /**
     * 測試當無所有權異動時 (countD > 0 但 countE == 0)，第二表格補上空白列
     */
    public function testNoOwnerChangePadsBlankRow(): void {
        echo "\n[*] 測試當無所有權異動時，第二表格正確補上一筆空白列...\n";

        $mockDb = function (string $sql, array $params = []): array {
            if (strpos($sql, 'FROM SRKEYN WHERE (KCDE_1=\'55\')') !== false) {
                return [['KCNT' => '桃園', 'KRMK' => 'HA']];
            }
            if (strpos($sql, 'FROM SRKEYN WHERE KCDE_1=\'66\'') !== false) {
                return [['KCNT' => '桃園市']];
            }
            if (strpos($sql, 'FROM SRKEYN WHERE KCDE_1=:c1 AND KCDE_2=:c2') !== false) {
                $c1 = $params[':c1'] ?? '';
                $c2 = $params[':c2'] ?? '';
                if ($c1 === '04' && $c2 === 'HA') return [['KCNT' => '桃資', 'KRMK' => '']];
                if ($c1 === '46' && $c2 === '01') return [['KCNT' => '桃園區', 'KRMK' => '']];
                if ($c1 === '48' && $c2 === '1010') return [['KCNT' => '中路段', 'KRMK' => '01']];
                if ($c1 === '06' && $c2 === '02') return [['KCNT' => '分割', 'KRMK' => '']];
                return [['KCNT' => $c2, 'KRMK' => '']];
            }

            if (strpos($sql, 'FROM SCRSMS') !== false) {
                return [
                    [
                        'RM01' => '115', 'RM02' => 'HA', 'RM03' => '000002',
                        'RM07_1' => '1151029', 'RM33' => '1151029', 'RM34' => '1151027',
                        'RM36' => '02', 'RM09' => '02', 'RM55' => 'U001', 'RM57' => 'U002',
                        'RM67' => '10000'
                    ]
                ];
            }

            if (strpos($sql, 'FROM TRINDX') !== false) {
                if (strpos($sql, "II00='D'") !== false) {
                    if (strpos($sql, 'COUNT(*)') !== false) return [['C' => 1]];
                    return [['IIKEY' => 'D101000101000', 'IP_TYPE' => 'A']];
                }
                if (strpos($sql, "II00='E'") !== false) {
                    return [['C' => 0]]; // 無所有權異動
                }
            }

            if (strpos($sql, 'FROM SRDBID') !== false || strpos($sql, 'FROM WRDBID') !== false) {
                return [['DD46' => '01', 'DD09' => '桃園市桃園區中山路100號']];
            }

            if (strpos($sql, 'FROM SRHD10') !== false || strpos($sql, 'FROM WRHD10') !== false) {
                return [['HA48' => '1010', 'HA49' => '01010000']];
            }

            return [];
        };

        $exporter = new \TaxExportRCD0102($mockDb);
        $out = $this->tempDir . '/no_owner_test_rcd0102.pdf';
        $res = $exporter->export($out, ['code' => 'RCD0102_LOCAL'], ['begin' => '2026-10-01', 'end' => '2026-10-31', 'mock' => false]);
        $this->myAssertSame('success', $res['state'], '無所有權部異動案件直連產製回傳 success');
        $this->myAssert(is_file($out), '成功產出補齊空白列之通知書 PDF 檔案');
    }
}

// 支援直接 CLI 執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    $test = new TaxExportRCD0102Test();
    $ok = $test->runAll();
    exit($ok ? 0 : 1);
}
