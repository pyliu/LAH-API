<?php

final class TaxExportBatch {
    private $root;
    private $owner;
    private $mock;
    private $provider;
    private $worker = false;

    public function __construct(string $root, string $owner, bool $mock, $provider = null) {
        if ($owner === '') {
            throw new InvalidArgumentException('請先登入再產製稅務資料。');
        }
        $this->root = $root;
        $this->owner = hash('sha256', $owner);
        $this->mock = $mock;
        $this->provider = $provider;
    }

    public static function reports(): array {
        return [
            'RCD0101_LOCAL'  => ['name' => '地籍異動通知書（本所）', 'extension' => 'pdf', 'source' => '本所承辦案件', 'supported' => true, 'exporter' => 'TaxExportRCD0101'],
            'RCD0101_REMOTE' => ['name' => '地籍異動通知書（他所）', 'extension' => 'pdf', 'source' => '他所承辦本所案件', 'supported' => true, 'exporter' => 'TaxExportRCD0101'],
            'RCD0102_LOCAL'  => ['name' => '建物異動通知書（本所）', 'extension' => 'pdf', 'source' => '本所承辦案件', 'supported' => true, 'exporter' => 'TaxExportRCD0102'],
            'RCD0102_REMOTE' => ['name' => '建物異動通知書（他所）', 'extension' => 'pdf', 'source' => '他所承辦本所案件', 'supported' => true, 'exporter' => 'TaxExportRCD0102'],
            'AI001'          => ['name' => '地籍異動資料', 'extension' => 'txt', 'source' => '本所及他所承辦本所案件', 'supported' => true, 'exporter' => 'TaxExportAI001'],
            'AI002'          => ['name' => '地價異動資料', 'extension' => 'txt', 'source' => '本所及他所承辦本所案件', 'supported' => true, 'exporter' => 'TaxExportAI002'],
            'AI037'          => ['name' => '建物異動資料', 'extension' => 'txt', 'source' => '本所及他所承辦本所案件', 'supported' => true, 'exporter' => 'TaxExportAI037']
        ];
    }

    public function start(string $begin, string $end): array {
        foreach ([$begin, $end] as $date) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || (int)$parsed->format('Y') < 1912) {
                throw new InvalidArgumentException('請輸入有效的起訖日期。');
            }
        }
        if ($begin > $end) {
            throw new InvalidArgumentException('起日不可晚於迄日。');
        }
        $id = bin2hex(random_bytes(16));
        $path = $this->root . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('無法建立產製目錄。');
        }
        if (!mkdir($path, 0700)) {
            throw new RuntimeException('無法建立批次。');
        }
        $items = [];
        foreach (self::reports() as $code => $report) {
            $items[$code] = array_merge($report, [
                'code' => $code,
                'state' => 'queued',
                'filename' => null,
                'message' => empty($report['supported']) ? '本階段尚未支援此項目' : '',
                'source_job' => null
            ]);
        }
        $batch = [
            'batch' => $id,
            'owner' => $this->owner,
            'mock' => $this->mock,
            'begin' => $begin,
            'end' => $end,
            'created' => date(DATE_ATOM),
            'items' => $items
        ];
        $this->save($path, $batch);
        return $this->snapshot($batch);
    }

    public function status(string $id): array {
        return $this->snapshot($this->load($this->path($id)));
    }

    public function advance(string $id, string $code, bool $retry = false): array {
        $path = $this->path($id);
        $this->load($path);
        $lock = fopen($path . '/batch.lock', 'c');
        if (!$lock) {
            throw new RuntimeException('無法鎖定批次。');
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return $this->status($id);
            }
            $batch = $this->load($path);
            if (!isset($batch['items'][$code])) {
                throw new InvalidArgumentException('不支援的稅務產出種類。');
            }
            $item = &$batch['items'][$code];

            // 若本階段尚未支援此項目，維持排隊狀態，不偽造成功
            if (empty($item['supported'])) {
                $item['state'] = 'queued';
                $item['message'] = '本階段尚未支援此項目產製';
                $this->save($path, $batch);
                return $this->snapshot($batch);
            }

            if (in_array($item['state'], ['success', 'empty'], true) || ($item['state'] === 'failed' && !$retry)) {
                return $this->snapshot($batch);
            }

            $item['state'] = 'pending';
            $item['message'] = '產製中';
            $this->save($path, $batch);
            $temporary = $path . '/' . $code . '.part';
            $errorHandlerActive = false;

            try {
                // 將底層 OraDB 等觸發之 E_USER_ERROR 轉為 ErrorException，確保 PHP 7.4/8.5 皆可被捕捉記錄
                set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
                    if ($severity === E_USER_ERROR || $severity === E_RECOVERABLE_ERROR) {
                        throw new ErrorException($message, 0, $severity, $file, $line);
                    }
                    return false;
                });
                $errorHandlerActive = true;

                if ($this->provider !== null) {
                    if (method_exists($this->provider, 'advance')) {
                        $result = $this->provider->advance($item, $batch, $temporary, function ($source) use (&$item, &$batch, $path) {
                            $item['source_job'] = $source;
                            $this->save($path, $batch);
                        });
                    } elseif (method_exists($this->provider, 'export')) {
                        $result = $this->provider->export($temporary, $item, $batch);
                    } else {
                        throw new RuntimeException('無效的產製提供者。');
                    }
                } else {
                    $exporterClass = $item['exporter'] ?? (self::reports()[$code]['exporter'] ?? null);
                    if ($exporterClass) {
                        $exporterFile = __DIR__ . '/' . $exporterClass . '.class.php';
                        if (is_file($exporterFile)) {
                            require_once $exporterFile;
                        }
                    }
                    if ($exporterClass && class_exists($exporterClass)) {
                        $exporter = new $exporterClass();
                        $result = $exporter->export($temporary, $item, $batch);
                    } else {
                        $result = $this->mockFile($temporary, $item, $batch);
                    }
                }
                if (!in_array($result['state'], ['pending', 'success', 'empty'], true)) {
                    throw new RuntimeException('產製結果狀態錯誤。');
                }
                $item['state'] = $result['state'];
                $item['message'] = $result['message'] ?? ($item['state'] === 'empty' ? '無資料' : '已完成');
                if (is_file($temporary) && in_array($item['state'], ['success', 'empty'], true)) {
                    $prefix = str_replace('-', '', $batch['begin']) . '-' . str_replace('-', '', $batch['end']);
                    $filename = $prefix . '_' . $item['name'] . '.' . $item['extension'];
                    if (!rename($temporary, $path . '/' . $filename)) {
                        throw new RuntimeException('無法儲存產製成果。');
                    }
                    $item['filename'] = $filename;
                } elseif ($item['state'] === 'success') {
                    throw new RuntimeException('產製完成但未取得檔案。');
                }
            } catch (Throwable $error) {
                $item['state'] = 'failed';
                $item['message'] = '產製失敗，請檢查資料庫連線或重試。';
                if (class_exists('Logger', false)) {
                    Logger::getInstance()->error('Tax export ' . $id . ' ' . $code . ': ' . $error->getMessage());
                }
            } finally {
                if ($errorHandlerActive) {
                    restore_error_handler();
                }
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            $this->save($path, $batch);
            return $this->snapshot($batch);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function download(string $id, string $code): array {
        $path = $this->path($id);
        $lock = fopen($path . '/batch.lock', 'c');
        if (!$lock) {
            throw new RuntimeException('無法讀取批次。');
        }
        try {
            if (!flock($lock, LOCK_SH | LOCK_NB)) {
                throw new RuntimeException('批次仍在產製，請稍後再下載。');
            }
            $batch = $this->load($path);
            if ($code !== 'zip') {
                $item = $batch['items'][$code] ?? [];
                if (!in_array($item['state'] ?? '', ['success', 'empty'], true) || empty($item['filename']) || !is_file($path . '/' . $item['filename'])) {
                    throw new InvalidArgumentException('此項目尚無可下載檔案。');
                }
                return [
                    'path' => $path . '/' . $item['filename'],
                    'filename' => $item['filename'],
                    'content_type' => $item['extension'] === 'pdf' ? 'application/pdf' : 'application/octet-stream'
                ];
            }
            $name = str_replace('-', '', $batch['begin']) . '-' . str_replace('-', '', $batch['end']) . '_地方稅務局資料.zip';
            $zipPath = tempnam($path, 'zip-');
            $zip = new ZipArchive();
            try {
                if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('無法建立 ZIP。');
                }
                $lines = [
                    '地方稅務局資料產製結果',
                    '日期：' . $batch['begin'] . ' ～ ' . $batch['end'],
                    '批次：' . $id,
                    $batch['mock'] ? 'MOCK 模擬資料，非正式產製成果' : '資料庫直連產製成果',
                    ''
                ];
                $stateLabels = [
                    'success' => '成功',
                    'empty'   => '無資料',
                    'failed'  => '失敗',
                    'queued'  => '等待產製',
                    'pending' => '產製中'
                ];
                foreach ($batch['items'] as $item) {
                    $stateText = ($stateLabels[$item['state']] ?? $item['state']) . " ({$item['state']})";
                    $lines[] = $item['code'] . ' | ' . $item['name'] . ' | ' . $item['source'] . ' | ' . $stateText . ' | ' . ($item['message'] !== '' ? $item['message'] : '-') . ' | ' . ($item['filename'] ?? '無檔案');
                    if ($item['filename'] && in_array($item['state'], ['success', 'empty'], true)) {
                        $file = $path . '/' . $item['filename'];
                        if (!is_file($file) || !$zip->addFile($file, $item['filename'])) {
                            throw new RuntimeException('批次成果遺失，無法打包。');
                        }
                    }
                }
                if (!$zip->addFromString('產製結果清單.txt', "\xEF\xBB\xBF" . implode("\r\n", $lines)) || !$zip->close()) {
                    throw new RuntimeException('ZIP 打包失敗。');
                }
            } catch (Throwable $error) {
                if (is_file($zipPath)) {
                    unlink($zipPath);
                }
                throw $error;
            }
            return ['path' => $zipPath, 'filename' => $name, 'content_type' => 'application/zip', 'temporary' => true];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function path(string $id): string {
        if (!preg_match('/\A[a-f0-9]{32}\z/', $id)) {
            throw new InvalidArgumentException('批次代碼錯誤。');
        }
        return $this->root . DIRECTORY_SEPARATOR . $id;
    }

    private function load(string $path): array {
        $content = is_file($path . '/manifest.json') ? file_get_contents($path . '/manifest.json') : '';
        $batch = json_decode($content, true);
        if (!is_array($batch) || !hash_equals($batch['owner'] ?? '', $this->owner) || ($batch['mock'] ?? null) !== $this->mock) {
            throw new InvalidArgumentException('找不到此帳號的產製批次。');
        }
        return $batch;
    }

    private function save(string $path, array $batch): void {
        $temp = tempnam($path, 'state-');
        try {
            $json = json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (file_put_contents($temp, $json) === false || !rename($temp, $path . '/manifest.json')) {
                throw new RuntimeException('無法儲存批次狀態。');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private function snapshot(array $batch): array {
        unset($batch['owner']);
        foreach ($batch['items'] as &$item) {
            unset($item['source_job']);
        }
        unset($item);
        $batch['items'] = array_values($batch['items']);
        return $batch;
    }

    private function mockFile(string $path, array $item, array $batch): array {
        if ($item['extension'] === 'pdf') {
            if (!class_exists('FPDF')) {
                $autoloadCandidates = [
                    dirname(__DIR__, 2) . '/vendor/autoload.php',
                    dirname(__DIR__, 3) . '/www/vendor/autoload.php'
                ];
                foreach ($autoloadCandidates as $cand) {
                    if (is_file($cand)) {
                        require_once $cand;
                        break;
                    }
                }
            }
            if (class_exists('FPDF')) {
                $pdf = new FPDF();
                $pdf->AddPage();
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(0, 10, 'MOCK - Tax Bureau Notice', 0, 1);
                $pdf->Cell(0, 10, 'Item: ' . $item['code'] . ' (' . $item['name'] . ')', 0, 1);
                $pdf->Cell(0, 10, 'Date Range: ' . $batch['begin'] . ' - ' . $batch['end'], 0, 1);
                $pdf->Cell(0, 10, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1);
                $pdf->Output('F', $path);
            } else {
                // 回退自包含最小有效 PDF
                $minimalPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF\n";
                file_put_contents($path, $minimalPdf);
            }
            return ['state' => 'success', 'message' => '模擬資料'];
        }
        if ($item['code'] === 'AI001') {
            return $this->mockAi001($path, $batch);
        }
        if ($item['code'] === 'AI002') {
            return $this->mockAi002($path, $batch);
        }
        if ($item['code'] === 'AI037') {
            return $this->mockAi037($path, $batch);
        }
        return ['state' => 'queued', 'message' => '本階段尚未支援此項目產製'];
    }

    private function mockAi037(string $path, array $batch): array {
        $padBytes = function (string $s, int $targetLen): string {
            $len = strlen($s);
            return $len < $targetLen ? $s . str_repeat(' ', $targetLen - $len) : substr($s, 0, $targetLen);
        };
        $lines = [];
        // 本所承辦案件 (D 記錄 106 位元組, E 記錄 207 位元組)
        $d1 = $padBytes("115A3HA003720D1011403004170006711509231151030HA" . mb_convert_encoding('桃園市桃園區復興路１號', 'CP950', 'UTF-8'), 106);
        $name1 = mb_convert_encoding('本所建物所有權人', 'CP950', 'UTF-8');
        $addr1 = mb_convert_encoding('桃園市桃園區三民路一段１號', 'CP950', 'UTF-8');
        $e1 = $padBytes("115A1HA000681E0Y1202689551" . str_repeat(' ', 21) . $name1 . str_repeat(' ', 20) . $addr1, 207);

        // 他所承辦本所案件 (跨所字號 U1)
        $d2 = $padBytes("115U1HA000060D1011403004170006711509231151030HA" . mb_convert_encoding('桃園市中壢區中央路２號', 'CP950', 'UTF-8'), 106);
        $name2 = mb_convert_encoding('他所跨所建物所有權人', 'CP950', 'UTF-8');
        $addr2 = mb_convert_encoding('桃園市中壢區延平路二段２號', 'CP950', 'UTF-8');
        $e2 = $padBytes("115U1HA000060E0J1200967981" . str_repeat(' ', 21) . $name2 . str_repeat(' ', 20) . $addr2, 207);

        $lines[] = $d1;
        $lines[] = $e1;
        $lines[] = $d2;
        $lines[] = $e2;

        $count = count($lines);
        $tag = "##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 " . $count . " \xB5\xA7\xB8\xEA\xAE\xC6";
        $lines[] = $tag;

        file_put_contents($path, implode("\r\n", $lines) . "\r\n");
        return ['state' => 'success', 'message' => '已完成，共 ' . $count . ' 筆'];
    }

    private function mockAi002(string $path, array $batch): array {
        $padBytes = function (string $s, int $targetLen): string {
            $len = strlen($s);
            return $len < $targetLen ? $s . str_repeat(' ', $targetLen - $len) : substr($s, 0, $targetLen);
        };
        $lines = [];
        // 本所承辦案件 (103 位元組)
        $l1 = $padBytes("115HA8117815001002210840000L20270647200010681151027         1         806402    4833  162400", 103);
        $l2 = $padBytes("115HA8117815001002210840000A12345678900011681151029         1        3211508  995000  162400", 103);

        // 他所承辦本所案件 (跨所收件字，如 U1)
        $l3 = $padBytes("115HAU1100699001001017210004U12005415500011641151016        85     1000011510  827000  135200", 103);
        $l4 = $padBytes("115HAU1102979001017707740000B98765432100011641151031        51     1000011510  808510  132080", 103);

        $lines[] = $l1;
        $lines[] = $l2;
        $lines[] = $l3;
        $lines[] = $l4;

        $count = count($lines);
        $tag = "##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 " . $count . " \xB5\xA7\xB8\xEA\xAE\xC6";
        $lines[] = $tag;

        file_put_contents($path, implode("\r\n", $lines) . "\r\n");
        return ['state' => 'success', 'message' => '已完成，共 ' . $count . ' 筆'];
    }

    private function mockAi001(string $path, array $batch): array {
        $file = __DIR__ . '/TaxExportAI001.class.php';
        if (is_file($file)) {
            require_once $file;
        }
        if (class_exists('TaxExportAI001')) {
            $exporter = new TaxExportAI001();
            return $exporter->export($path, ['code' => 'AI001'], array_merge($batch, ['mock' => true]));
        }

        $padBytes = function (string $s, int $targetLen): string {
            $len = strlen($s);
            return $len < $targetLen ? $s . str_repeat(' ', $targetLen - $len) : substr($s, 0, $targetLen);
        };
        $lines = [];
        // 本所承辦案件 (A 記錄 169 位元組, B 記錄 227 位元組)
        $a1 = $padBytes("115A1HA000001A1010021019400004811510271151029HA        4646", 169);
        $name1 = mb_convert_encoding('本所測試者', 'CP950', 'UTF-8');
        $addr1 = mb_convert_encoding('桃園市桃園區復興路１號', 'CP950', 'UTF-8');
        $b1 = $padBytes("115A1HA000001B0A123456789               " . $name1 . str_repeat(' ', 20) . $addr1, 227);

        // 他所承辦本所案件
        $a2 = $padBytes("115U1HA000002A1010021019400004811510271151029HA        4646", 169);
        $name2 = mb_convert_encoding('他所測試者', 'CP950', 'UTF-8');
        $addr2 = mb_convert_encoding('桃園市中壢區中央路２號', 'CP950', 'UTF-8');
        $b2 = $padBytes("115U1HA000002B0B987654321               " . $name2 . str_repeat(' ', 20) . $addr2, 227);

        $lines = [$a1, $b1, $a2, $b2];
        $count = count($lines);
        $tag = "##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 " . $count . " \xB5\xA7\xB8\xEA\xAE\xC6";
        $lines[] = $tag;

        file_put_contents($path, implode("\n", $lines));
        return ['state' => 'success', 'message' => '已完成，共 ' . $count . ' 筆'];
    }
}
