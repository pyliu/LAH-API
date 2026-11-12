<?php

if (class_exists('PHPUnit\Framework\TestCase')) {
    abstract class BaseTaxExportBatchTestCase extends \PHPUnit\Framework\TestCase {}
} else {
    abstract class BaseTaxExportBatchTestCase {}
}

$incCandidates = [
    dirname(__DIR__, 3) . '/include/api/TaxExportBatch.class.php',
    dirname(__DIR__, 3) . '/API/include/api/TaxExportBatch.class.php',
    dirname(__DIR__, 3) . '/www/include/api/TaxExportBatch.class.php',
    dirname(__DIR__, 4) . '/API/include/api/TaxExportBatch.class.php',
    dirname(__DIR__, 4) . '/www/include/api/TaxExportBatch.class.php'
];
$foundInc = false;
foreach ($incCandidates as $cand) {
    if (file_exists($cand)) {
        require_once $cand;
        $foundInc = true;
        break;
    }
}
if (!$foundInc) {
    throw new RuntimeException("Cannot find TaxExportBatch.class.php");
}

class TaxExportBatchIntegrationTest extends BaseTaxExportBatchTestCase {
    private $tempDir;
    private $passed = 0;
    private $failed = 0;

    public function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/lah_tax_batch_test_' . bin2hex(random_bytes(6));
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0700, true);
        }
    }

    public function tearDown(): void {
        if ($this->tempDir && is_dir($this->tempDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
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
            echo "  [PASS] $message\n";
        } else {
            $this->failed++;
            echo "  [FAIL] $message\n";
        }
    }

    private function myAssertSame($expected, $actual, string $message): void {
        if (is_callable(['parent', 'assertSame'])) {
            $this->assertSame($expected, $actual, $message);
        }
        if ($expected === $actual) {
            $this->passed++;
            echo "  [PASS] $message\n";
        } else {
            $this->failed++;
            $expStr = var_export($expected, true);
            $actStr = var_export($actual, true);
            echo "  [FAIL] $message (預期: $expStr, 實際: $actStr)\n";
        }
    }

    private function myAssertContains(string $needle, string $haystack, string $message): void {
        $found = strpos($haystack, $needle) !== false;
        if ($found) {
            $this->passed++;
            echo "  [PASS] $message\n";
        } else {
            $this->failed++;
            echo "  [FAIL] $message (未包含: $needle)\n";
        }
    }

    public function runAll(): bool {
        echo "=======================================================\n";
        echo "  TaxExportBatch (地方稅務局批次產製整合) 測試套件 (API 軌)\n";
        echo "=======================================================\n\n";

        $this->testReportDefinitions();
        $this->testValidationAndIsolation();
        $this->testFullBatchExecution7ItemsMock();
        $this->testZipPackagingAndManifestStructure();
        $this->testAllEmptyZipPackaging();
        $this->testPartialFailureAndRetry();

        echo "\n-------------------------------------------------------\n";
        echo "測試結果: 通過 {$this->passed} 項, 失敗 {$this->failed} 項\n";
        echo "-------------------------------------------------------\n";
        return $this->failed === 0;
    }

    public function testReportDefinitions(): void {
        echo "[*] 測試 7 項報表定義與支援完整性...\n";
        $reports = TaxExportBatch::reports();
        $this->myAssertSame(7, count($reports), '必須精確定義 7 項報表');

        $expectedCodes = ['RCD0101_LOCAL', 'RCD0101_REMOTE', 'RCD0102_LOCAL', 'RCD0102_REMOTE', 'AI001', 'AI002', 'AI037'];
        foreach ($expectedCodes as $code) {
            $this->myAssert(isset($reports[$code]), "包含報表代碼 $code");
            $this->myAssert(!empty($reports[$code]['supported']), "報表 $code 必須為 supported = true");
            $this->myAssert(!empty($reports[$code]['name']), "報表 $code 具有中文名稱");
            $this->myAssert(!empty($reports[$code]['source']), "報表 $code 具有案件來源說明");
            $this->myAssert(in_array($reports[$code]['extension'], ['pdf', 'txt'], true), "報表 $code 副檔名為 pdf 或 txt");
        }
    }

    public function testValidationAndIsolation(): void {
        echo "\n[*] 測試日期校驗與批次租戶隔離性...\n";
        $this->setUp();
        try {
            $service = new TaxExportBatch($this->tempDir, 'H01:operator', true);

            // 無效日期
            $caught = false;
            try {
                $service->start('2026-02-30', '2026-03-01');
            } catch (InvalidArgumentException $e) {
                $caught = true;
            }
            $this->myAssert($caught, '拒絕不存在之日期 2026-02-30');

            // 起日晚於迄日
            $caught = false;
            try {
                $service->start('2026-10-31', '2026-10-15');
            } catch (InvalidArgumentException $e) {
                $caught = true;
            }
            $this->myAssert($caught, '拒絕起日晚於迄日');

            // 空帳號
            $caught = false;
            try {
                new TaxExportBatch($this->tempDir, '', true);
            } catch (InvalidArgumentException $e) {
                $caught = true;
            }
            $this->myAssert($caught, '未登入或空帳號拒絕建立批次服務');

            // 批次隔離
            $batch = $service->start('2026-10-01', '2026-10-15');
            $this->myAssert(!isset($batch['owner']), '外部快照不得外洩內部 owner 雜湊');

            // 其他使用者不得存取
            $other = new TaxExportBatch($this->tempDir, 'H01:another_user', true);
            $caught = false;
            try {
                $other->status($batch['batch']);
            } catch (InvalidArgumentException $e) {
                $caught = true;
            }
            $this->myAssert($caught, '其他使用者無法存取他人批次');

            // 模式不同 (mock vs prod) 不得存取
            $prod = new TaxExportBatch($this->tempDir, 'H01:operator', false);
            $caught = false;
            try {
                $prod->status($batch['batch']);
            } catch (InvalidArgumentException $e) {
                $caught = true;
            }
            $this->myAssert($caught, '不同 mock 模式無法交叉存取');
        } finally {
            $this->tearDown();
        }
    }

    public function testFullBatchExecution7ItemsMock(): void {
        echo "\n[*] 測試 MOCK 模式下完整推進 7 項產製 (4 PDF + 3 TXT)...\n";
        $this->setUp();
        try {
            $service = new TaxExportBatch($this->tempDir, 'H01:tester', true);
            $batch = $service->start('2026-10-16', '2026-10-31');
            $id = $batch['batch'];
            $batchDir = $this->tempDir . DIRECTORY_SEPARATOR . $id;

            $items = $batch['items'];
            $this->myAssertSame(7, count($items), '初始批次包含 7 項');

            foreach ($items as $item) {
                $this->myAssertSame('queued', $item['state'], "初始項目 {$item['code']} 為 queued 狀態");
            }

            // 依序推進 7 項
            foreach ($items as $item) {
                $code = $item['code'];
                $res = $service->advance($id, $code);
                $curr = null;
                foreach ($res['items'] as $it) {
                    if ($it['code'] === $code) {
                        $curr = $it;
                        break;
                    }
                }
                $this->myAssert($curr !== null, "回傳資料包含項目 $code");
                $this->myAssertSame('success', $curr['state'], "項目 $code advance 後狀態必須為 success");
                $this->myAssert(!empty($curr['filename']), "項目 $code 必須具有成果檔名");

                $filePath = $batchDir . DIRECTORY_SEPARATOR . $curr['filename'];
                $this->myAssert(is_file($filePath), "成果檔案存在於磁碟: {$curr['filename']}");
                $this->myAssert(filesize($filePath) > 0, "成果檔案大小大於 0 bytes");

                // 檢查 PDF 或 TXT 格式
                if ($curr['extension'] === 'pdf') {
                    $header = file_get_contents($filePath, false, null, 0, 5);
                    $this->myAssertSame('%PDF-', $header, "PDF 項目 $code 標頭為 %PDF-");
                } elseif ($curr['extension'] === 'txt') {
                    $content = file_get_contents($filePath);
                    $this->myAssertContains("##### TAG #####", $content, "TXT 項目 $code 尾端包含 TAG 標記");
                }
            }

            // 單檔下載檢查
            foreach ($items as $item) {
                $code = $item['code'];
                $dl = $service->download($id, $code);
                $this->myAssert(is_file($dl['path']), "單檔下載路徑存在: $code");
                $this->myAssert(!empty($dl['filename']), "單檔下載具有檔案名稱: $code");
                $expectedMime = $item['extension'] === 'pdf' ? 'application/pdf' : 'application/octet-stream';
                $this->myAssertSame($expectedMime, $dl['content_type'], "單檔下載 MIME 類型正確: $code");
            }
        } finally {
            $this->tearDown();
        }
    }

    public function testZipPackagingAndManifestStructure(): void {
        echo "\n[*] 測試 ZIP 打包成果與「產製結果清單.txt」內容結構...\n";
        $this->setUp();
        try {
            $service = new TaxExportBatch($this->tempDir, 'H01:tester', true);
            $batch = $service->start('2026-10-16', '2026-10-31');
            $id = $batch['batch'];

            // 推進全部 7 項
            foreach ($batch['items'] as $it) {
                $service->advance($id, $it['code']);
            }

            $zipInfo = $service->download($id, 'zip');
            $this->myAssert(is_file($zipInfo['path']), 'ZIP 打包檔案存在');
            $this->myAssertSame('application/zip', $zipInfo['content_type'], 'ZIP Content-Type 為 application/zip');
            $this->myAssertContains('20261016-20261031_地方稅務局資料.zip', $zipInfo['filename'], 'ZIP 檔名符合規範');

            $zip = new ZipArchive();
            $openRes = $zip->open($zipInfo['path']);
            $this->myAssert($openRes === true, 'ZIP 可正常由 ZipArchive 開啟');
            $this->myAssertSame(8, $zip->numFiles, 'ZIP 內包含 7 份報表 + 1 份產製結果清單，共 8 個檔案');

            // 檢查「產製結果清單.txt」
            $manifestContent = $zip->getFromName('產製結果清單.txt');
            $this->myAssert($manifestContent !== false, 'ZIP 內必須包含「產製結果清單.txt」');

            // 驗證 UTF-8 BOM
            $bom = substr($manifestContent, 0, 3);
            $this->myAssertSame("\xEF\xBB\xBF", $bom, '產製結果清單必須帶有 UTF-8 BOM 開頭');

            // 驗證標題與日期
            $this->myAssertContains('地方稅務局資料產製結果', $manifestContent, '包含標題「地方稅務局資料產製結果」');
            $this->myAssertContains('日期：2026-10-16 ～ 2026-10-31', $manifestContent, '包含日期區間 2026-10-16 ～ 2026-10-31');
            $this->myAssertContains("批次：$id", $manifestContent, "包含批次代碼 $id");

            // 驗證 7 項代碼、名稱、來源、狀態與檔名
            $reports = TaxExportBatch::reports();
            foreach ($reports as $code => $rep) {
                $this->myAssertContains($code, $manifestContent, "清單包含報表代碼 $code");
                $this->myAssertContains($rep['name'], $manifestContent, "清單包含報表名稱 {$rep['name']}");
                $this->myAssertContains($rep['source'], $manifestContent, "清單包含案件來源 {$rep['source']}");
                $this->myAssertContains('成功 (success)', $manifestContent, "清單標示成功 (success)");
            }

            $zip->close();
            if (!empty($zipInfo['temporary']) && is_file($zipInfo['path'])) {
                unlink($zipInfo['path']);
            }
        } finally {
            $this->tearDown();
        }
    }

    public function testAllEmptyZipPackaging(): void {
        echo "\n[*] 測試全查無資料 (7 項皆 empty) 時之 ZIP 打包與清單...\n";
        $this->setUp();
        try {
            // 建立一個全部回傳 empty 的 provider
            $emptyProvider = new class {
                public function advance(array $item, array $batch, string $path, callable $remember): array {
                    return ['state' => 'empty', 'message' => '查無符合條件之資料'];
                }
            };

            $service = new TaxExportBatch($this->tempDir, 'H01:operator', false, $emptyProvider);
            $batch = $service->start('2026-10-01', '2026-10-15');
            $id = $batch['batch'];

            foreach ($batch['items'] as $item) {
                $res = $service->advance($id, $item['code']);
                $curr = null;
                foreach ($res['items'] as $it) {
                    if ($it['code'] === $item['code']) {
                        $curr = $it;
                        break;
                    }
                }
                $this->myAssertSame('empty', $curr['state'], "項目 {$item['code']} 狀態為 empty");
            }

            // 即使全為 empty，亦能成功下載 ZIP
            $zipInfo = $service->download($id, 'zip');
            $this->myAssert(is_file($zipInfo['path']), '全 empty 情況下仍能成功建立 ZIP 檔案');

            $zip = new ZipArchive();
            $this->myAssert($zip->open($zipInfo['path']) === true, '全 empty ZIP 能正常開啟');
            $this->myAssertSame(1, $zip->numFiles, '全 empty ZIP 內僅包含 1 個檔案（產製結果清單.txt）');

            $manifestContent = $zip->getFromName('產製結果清單.txt');
            $this->myAssert($manifestContent !== false, '全 empty ZIP 內包含產製結果清單.txt');
            $this->myAssertContains('無資料 (empty)', $manifestContent, '清單內標註狀態為 無資料 (empty)');
            $this->myAssertContains('查無符合條件之資料', $manifestContent, '清單內包含說明訊息 查無符合條件之資料');

            $reports = TaxExportBatch::reports();
            foreach ($reports as $code => $rep) {
                $this->myAssertContains($code, $manifestContent, "全 empty 清單包含項目代碼 $code");
                $this->myAssertContains('無檔案', $manifestContent, "全 empty 清單產出檔名顯示 無檔案");
            }

            $zip->close();
            if (!empty($zipInfo['temporary']) && is_file($zipInfo['path'])) {
                unlink($zipInfo['path']);
            }
        } finally {
            $this->tearDown();
        }
    }

    public function testPartialFailureAndRetry(): void {
        echo "\n[*] 測試單項失敗重試與已產出項目成果保留...\n";
        $this->setUp();
        try {
            $provider = new class {
                public $failCode = 'AI002';
                public function advance(array $item, array $batch, string $path, callable $remember): array {
                    if ($item['code'] === $this->failCode) {
                        throw new RuntimeException('模擬資料庫連線逾時');
                    }
                    file_put_contents($path, 'DUMMY-' . $item['code']);
                    return ['state' => 'success', 'message' => '產製成功'];
                }
            };

            $service = new TaxExportBatch($this->tempDir, 'H01:operator', false, $provider);
            $batch = $service->start('2026-10-01', '2026-10-15');
            $id = $batch['batch'];

            // 推進 AI001 (成功)
            $res1 = $service->advance($id, 'AI001');
            $itAi001 = array_values(array_filter($res1['items'], fn($i) => $i['code'] === 'AI001'))[0];
            $this->myAssertSame('success', $itAi001['state'], 'AI001 產製成功');
            $this->myAssert(!empty($itAi001['filename']), 'AI001 具有產出檔名');

            // 推進 AI002 (失敗)
            $res2 = $service->advance($id, 'AI002');
            $itAi002 = array_values(array_filter($res2['items'], fn($i) => $i['code'] === 'AI002'))[0];
            $this->myAssertSame('failed', $itAi002['state'], 'AI002 產製失敗');
            $this->myAssertContains('產製失敗', $itAi002['message'], 'AI002 標示友善錯誤訊息');

            // 驗證 AI001 成果未受影響
            $itAi001_check = array_values(array_filter($res2['items'], fn($i) => $i['code'] === 'AI001'))[0];
            $this->myAssertSame('success', $itAi001_check['state'], 'AI002 失敗時 AI001 狀態仍維持 success');
            $this->myAssertSame($itAi001['filename'], $itAi001_check['filename'], 'AI001 產出檔案完整保留');

            // 若不帶 retry 旗標，對 failed 項目 advance 應維持 failed，不自動重試
            $resNoRetry = $service->advance($id, 'AI002', false);
            $itAi002_noRetry = array_values(array_filter($resNoRetry['items'], fn($i) => $i['code'] === 'AI002'))[0];
            $this->myAssertSame('failed', $itAi002_noRetry['state'], '未帶 retry 時 advance 維持 failed 狀態');

            // 解除失敗條件，以 retry = true 重試
            $provider->failCode = '';
            $resRetry = $service->advance($id, 'AI002', true);
            $itAi002_retried = array_values(array_filter($resRetry['items'], fn($i) => $i['code'] === 'AI002'))[0];
            $this->myAssertSame('success', $itAi002_retried['state'], '帶 retry = true 時成功重新產製');
            $this->myAssert(!empty($itAi002_retried['filename']), '重試後成功取得產出檔名');

            // 再次確認 AI001 成果依然保留
            $itAi001_final = array_values(array_filter($resRetry['items'], fn($i) => $i['code'] === 'AI001'))[0];
            $this->myAssertSame('success', $itAi001_final['state'], '重試後 AI001 成果依然完好');
        } finally {
            $this->tearDown();
        }
    }
}

// 支援命令列直接執行: php API/tests/Unit/Export/TaxExportBatchIntegrationTest.php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $tester = new TaxExportBatchIntegrationTest();
    $ok = $tester->runAll();
    exit($ok ? 0 : 1);
}
