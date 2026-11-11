<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use Rcd0901PdfExport;
use Rcd0901Portal;
use setasign\Fpdi\Fpdi;

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/include/api/Rcd0901PdfExport.class.php';

class Rcd0901PdfExportTest extends TestCase {
    public function testMergesAllPagesInSourceOrder(): void {
        $first = tempnam(sys_get_temp_dir(), 'rcd-test-');
        $second = tempnam(sys_get_temp_dir(), 'rcd-test-');
        $output = tempnam(sys_get_temp_dir(), 'rcd-test-');
        try {
            Rcd0901PdfExport::mockPdf($first, 'RCD0901_B', ['0001']);
            Rcd0901PdfExport::mockPdf($second, 'RCD0901_E', ['0001', '0002']);
            $this->assertSame(3, Rcd0901PdfExport::merge([$first, $second], $output));
            $pdf = new Fpdi();
            $this->assertSame(3, $pdf->setSourceFile($output));
            for ($page = 1; $page <= 3; $page++) {
                $size = $pdf->getTemplateSize($pdf->importPage($page));
                $this->assertEqualsWithDelta(210, $size['width'], 0.02);
                $this->assertEqualsWithDelta(297, $size['height'], 0.02);
            }
        } finally {
            foreach ([$first, $second, $output] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testReadsOnlyTheReportFormsHiddenFields(): void {
        $fields = Rcd0901Portal::formFields('<form id="other"><input type="hidden" name="bad" value="value"></form>
            <form id="form1"><input type="hidden" name="PATH" value="/app/">
            <input type="hidden" name="xml" value="tw.gov.moiland.REG.RCD09.RCD0901">
            <input type="text" name="START_NO" value="wrong">
            <input type="hidden" name="GRP" value="REG"></form>');
        $this->assertSame([
            'PATH' => '/app/', 'xml' => 'tw.gov.moiland.REG.RCD09.RCD0901', 'GRP' => 'REG'
        ], $fields);
    }

    public function testRejectsLoginPagesAsReportForms(): void {
        $this->expectException(\RuntimeException::class);
        Rcd0901Portal::formFields('<html><form id="login"></form></html>');
    }

    public function testRejectsInvalidSourcesBeforeMakingRequests(): void {
        $this->expectException(\InvalidArgumentException::class);
        new Rcd0901Portal('file:///local/report', 'test', 'test');
    }
}
