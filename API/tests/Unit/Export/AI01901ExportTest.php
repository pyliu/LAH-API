<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use AI01901Export;
use CadastralTxtExport;
use PHPUnit\Framework\TestCase;

require_once ROOT_DIR . '/include/api/AI01901Export.class.php';
require_once ROOT_DIR . '/include/api/CadastralTxtExport.class.php';

class AI01901ExportTest extends TestCase {
    public function testWritesRelatedRecordsInReportOrder(): void {
        $name = mb_convert_encoding('測試', 'CP950', 'UTF-8');
        $data = [
            'land' => [
                ['AA45' => 'H', 'AA46' => 'A1', 'AA48' => '0001', 'AA49' => '00000001', 'AA10' => '123'],
                ['AA48' => '0001', 'AA49' => '00000002']
            ],
            'owners' => [
                ['BA48' => '0001', 'BA49' => '00000001', 'BB01' => '0001', 'BB09' => 'TEST000001', 'LNAM' => $name]
            ],
            'rights' => [
                ['CC48' => '0001', 'CC49' => '00000001', 'CC01' => '0000001', 'CC09' => 'TEST000002', 'LNAM' => $name]
            ],
            'managers' => [
                ['MM00' => 'B', 'MM48' => '0001', 'MM49' => '00000001', 'MM01' => '0001', 'MM13' => 'TEST000003', 'LNAM' => $name],
                ['MM00' => 'C', 'MM48' => '0001', 'MM49' => '00000001', 'MM01' => '0000001', 'MM13' => 'TEST000004', 'LNAM' => $name]
            ],
            'prices' => [
                ['PA48' => '0001', 'PA49' => '00000001', 'PB01' => '0001', 'PB22_3' => '12.3']
            ],
            'remarks' => [
                ['GG00' => 'A', 'GG48' => '0001', 'GG49' => '00000001', 'GG30_1' => '00', 'GG30_2' => $name . ' a', 'SELECTED_CODE' => 1],
                ['GG00' => 'B', 'GG48' => '0001', 'GG49' => '00000001', 'GG01' => '0001', 'GG30_1' => '00'],
                ['GG00' => 'C', 'GG48' => '0001', 'GG49' => '00000001', 'GG01' => '0000001', 'GG30_1' => '00']
            ]
        ];

        $output = fopen('php://memory', 'w+b');
        $count = CadastralTxtExport::writeRecords($output, AI01901Export::records($data));
        fwrite($output, CadastralTxtExport::trailer($count));
        rewind($output);
        $lines = explode("\n", stream_get_contents($output));
        fclose($output);

        $this->assertSame(9, $count);
        $this->assertSame('##### TAG #####共產製 9 筆資料', mb_convert_encoding(array_pop($lines), 'UTF-8', 'CP950'));
        $this->assertSame('AGBGMPCGM', implode('', array_map(function ($line) {
            return $line[0];
        }, $lines)));
        $this->assertSame([55, 2015, 208, 2019, 157, 55, 6951, 2023, 160], array_map('strlen', $lines));
        $this->assertSame('G00010000000100' . $name . ' a', substr($lines[1], 0, 15 + strlen($name) + 2));
        $this->assertSame('   123.0', substr($lines[5], 26, 8));
    }
}
