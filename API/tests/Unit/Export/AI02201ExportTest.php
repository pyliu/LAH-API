<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use AI02201Export;
use CadastralTxtExport;
use PHPUnit\Framework\TestCase;

require_once ROOT_DIR . '/include/api/AI02201Export.class.php';

class AI02201ExportTest extends TestCase {
    public function testWritesBuildingRightsWithTheirRelatedRecords(): void {
        $name = mb_convert_encoding('建物測試', 'CP950', 'UTF-8');
        $data = [
            'rights' => [
                ['CC48' => '0001', 'CC49' => '00001000', 'CC01' => '0000001',
                 'CC29' => '001', 'CC15_4' => '999999999', 'CC16' => '123', 'LNAM' => $name],
                ['CC48' => '0001', 'CC49' => '00002000', 'CC01' => '0000001', 'CC29' => '002']
            ],
            'details' => [
                ['TT29' => '001', 'TC17_1' => 'Z', 'TC17_G' => $name],
                ['TT29' => '002', 'TC17_1' => 'A', 'TC17_G' => 'unused']
            ],
            'remarks' => [
                ['GG48' => '0001', 'GG49' => '00001000', 'GG01' => '0000001', 'GG30_1' => '00', 'GG30_2' => $name]
            ],
            'managers' => [
                ['MM48' => '0001', 'MM49' => '00001000', 'MM01' => '0000001', 'LNAM' => $name, 'LADR' => 'manager']
            ],
            'sequences' => [
                ['RR48' => '0001', 'RR49' => '00001000', 'RR01' => '0000001', 'RR28_2' => '1'],
                ['RR48' => '0001', 'RR49' => '00002000', 'RR01' => '0000001', 'RR28_2' => '2']
            ]
        ];
        $stream = fopen('php://memory', 'w+b');
        $count = CadastralTxtExport::writeRecords($stream, AI02201Export::records($data));
        fwrite($stream, CadastralTxtExport::trailer($count));
        rewind($stream);
        $lines = explode("\n", stream_get_contents($stream));
        fclose($stream);
        $this->assertSame(6, $count);
        $this->assertSame('##### TAG #####共產製 6 筆資料', mb_convert_encoding(array_pop($lines), 'UTF-8', 'CP950'));
        $this->assertSame('FGMRFR', implode('', array_map(function ($line) { return $line[0]; }, $lines)));
        $this->assertSame([6901, 262, 140, 27, 6901, 27], array_map('strlen', $lines));
        $this->assertSame('F0001000010000000001', substr($lines[0], 0, 20));
        $this->assertSame('       123', substr($lines[0], 64, 10));
        $this->assertSame($name, substr($lines[0], 166, strlen($name)));
        $this->assertSame($name, substr($lines[0], 4901, strlen($name)));
        $this->assertSame(str_repeat(' ', 2000), substr($lines[4], 4901, 2000));
        $this->assertSame('R0001000020000000001      2', $lines[5]);
    }

    public function testEmptySectionWritesNoRecords(): void {
        $data = AI02201Export::readSection('0000', function ($sql) { return []; });
        $this->assertSame([], iterator_to_array(AI02201Export::records($data)));
    }
}
