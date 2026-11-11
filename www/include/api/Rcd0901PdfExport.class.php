<?php
require_once __DIR__ . '/Rcd0901Portal.class.php';

final class Rcd0901PdfExport {
    public static function execute(string $code, $sections): bool {
        global $today;
        $reports = [
            'RCD0901_B' => ['C', '土地地籍整理清冊'],
            'RCD0901_E' => ['F', '建物地籍整理清冊']
        ];
        if (!isset($reports[$code]) || !is_array($sections) || !$sections) {
            echoJSONResponse('請選擇要產製地籍清冊的段碼。', STATUS_CODE::DEFAULT_FAIL);
            return false;
        }
        $normalized = [];
        foreach ($sections as $section) {
            if ((!is_string($section) && !is_int($section)) || !preg_match('/\A[0-9]{1,4}\z/', (string)$section)) {
                echoJSONResponse('地籍清冊段碼格式錯誤。', STATUS_CODE::DEFAULT_FAIL);
                return false;
            }
            $normalized[] = str_pad((string)$section, 4, '0', STR_PAD_LEFT);
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_STRING);
        list($part, $name) = $reports[$code];
        $system = System::getInstance();
        $site = $system->getSiteCode();
        $filename = $today . '_' . $code . '_' . $site . implode('_' . $site, $normalized) . '_' . $name . '.pdf';
        $temporary = [];
        $output = null;
        try {
            if (!class_exists(\setasign\Fpdi\Fpdi::class)) {
                throw new RuntimeException('請執行 Composer 安裝 FPDF 與 FPDI PDF 套件。');
            }
            $output = self::temporary();
            $temporary[] = $output;
            if ($system->isMockMode()) {
                self::mockPdf($output, $code, $normalized);
                $pageCount = count($normalized);
            } else {
                $portal = new Rcd0901Portal(
                    (string)Env::get('RCD0901_WEB_URL', ''),
                    (string)Env::get('RCD0901_WEB_USER', ''),
                    (string)Env::get('RCD0901_WEB_PASSWORD', '')
                );
                $sources = [];
                foreach ($normalized as $section) {
                    $source = self::temporary();
                    $temporary[] = $source;
                    if ($portal->fetch($section, $part, $source)) {
                        $sources[] = $source;
                    }
                }
                if (!$sources) {
                    echoJSONResponse($name . '查無資料。', STATUS_CODE::SUCCESS_WITH_NO_RECORD, ['data_count' => 0]);
                    return true;
                }
                $pageCount = self::merge($sources, $output);
            }
            if (!rename($output, EXPORT_DIR . DIRECTORY_SEPARATOR . $filename)) {
                throw new RuntimeException('無法儲存地籍清冊 PDF。');
            }
        } catch (Throwable $error) {
            Logger::getInstance()->error(__METHOD__ . ': ' . $error->getMessage());
            echoJSONResponse($code . ' 產製失敗。' . $error->getMessage(), STATUS_CODE::FAIL_LOAD_ERROR);
            return false;
        } finally {
            foreach ($temporary as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        $_SESSION[$code] = $filename;
        echoJSONResponse($filename . '已建立。', STATUS_CODE::SUCCESS_NORMAL, [
            'filename' => $filename, 'page_count' => $pageCount
        ]);
        return true;
    }

    public static function merge(array $sources, string $destination): int {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = 0;
        foreach ($sources as $source) {
            $pages = $pdf->setSourceFile($source);
            for ($page = 1; $page <= $pages; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
                $pageCount++;
            }
        }
        if ($pageCount === 0) {
            throw new RuntimeException('地籍清冊沒有可合併的頁面。');
        }
        $pdf->Output('F', $destination);
        return $pageCount;
    }

    public static function mockPdf(string $destination, string $code, array $sections): void {
        $pdf = new FPDF();
        foreach ($sections as $section) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 16);
            $pdf->Cell(0, 12, 'MOCK - Cadastral inventory', 0, 1);
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Cell(0, 10, $code . ' / Section ' . $section, 0, 1);
            $pdf->MultiCell(0, 8, 'Synthetic verification document. No cadastral records are included.');
        }
        $pdf->Output('F', $destination);
    }

    private static function temporary(): string {
        $path = tempnam(EXPORT_DIR, 'rcd0901-');
        if ($path === false) {
            throw new RuntimeException('無法建立地籍清冊暫存檔。');
        }
        return $path;
    }
}
