<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use AI02101Export;
use CadastralTxtExport;
use PHPUnit\Framework\TestCase;

require_once ROOT_DIR . '/include/api/AI02101Export.class.php';
require_once ROOT_DIR . '/include/api/CadastralTxtExport.class.php';

class AI02101ExportTest extends TestCase {
    public function testWritesEachRightWithOnlyItsRelatedRecords(): void {
        $name = mb_convert_encoding('測 試', 'CP950', 'UTF-8');
        $data = [
            'rights' => [
                ['CC48' => '0001', 'CC49' => '00000001', 'CC01' => '0000001', 'CC29' => '001', 'LNAM' => $name],
                ['CC48' => '0001', 'CC49' => '00000001', 'CC01' => '0000002', 'CC29' => '002', 'LNAM' => $name]
            ],
            'details' => [
                ['TT29' => '001', 'TC17_1' => 'Z', 'TC17_G' => $name, 'TC19_1' => 'A', 'TC19_G' => 'unused']
            ],
            'remarks' => [
                ['GG48' => '0001', 'GG49' => '00000001', 'GG01' => '0000001', 'GG30_1' => '00', 'GG30_2' => $name]
            ],
            'managers' => [
                ['MM48' => '0001', 'MM49' => '00000001', 'MM01' => '0000001', 'LNAM' => $name, 'LADR' => 'manager']
            ],
            'sequences' => [
                ['RR48' => '0001', 'RR49' => '00000001', 'RR01' => '0000001', 'RR28_2' => '1'],
                ['RR48' => '0001', 'RR49' => '00000001', 'RR01' => '0000002', 'RR28_2' => '2']
            ]
        ];
        $stream = fopen('php://memory', 'w+b');
        $count = CadastralTxtExport::writeRecords($stream, AI02101Export::records($data));
        fwrite($stream, CadastralTxtExport::trailer($count));
        rewind($stream);
        $lines = explode("\n", stream_get_contents($stream));
        fclose($stream);
        $this->assertSame(6, $count);
        $this->assertSame('##### TAG #####共產製 6 筆資料', mb_convert_encoding(array_pop($lines), 'UTF-8', 'CP950'));
        $this->assertSame('CGMRCR', implode('', array_map(function ($line) { return $line[0]; }, $lines)));
        $this->assertSame([6910, 262, 140, 27, 6910, 27], array_map('strlen', $lines));
        $this->assertSame('C0001000000010000001', substr($lines[0], 0, 20));
        $this->assertSame($name, substr($lines[0], 175, strlen($name)));
        $this->assertSame($name, substr($lines[0], 4910, strlen($name)));
        $this->assertSame(str_repeat(' ', 400), substr($lines[0], 5310, 400));
        $this->assertSame(str_repeat(' ', 2000), substr($lines[4], 4910, 2000));
        $this->assertSame(str_repeat(' ', 9), substr($lines[0], 64, 9));
        $this->assertSame('      1', substr($lines[3], 20));
    }

    public function testEmptySectionWritesNoRecords(): void {
        $data = AI02101Export::readSection('0000', function ($sql) { return []; });
        $this->assertSame([], iterator_to_array(AI02101Export::records($data)));
    }

    public function testTruncatesAtByteWidthWithoutLeavingHalfAChineseCharacter(): void {
        $chinese = mb_convert_encoding('測試', 'CP950', 'UTF-8');
        $this->assertSame(substr($chinese, 0, 2) . '?', CadastralTxtExport::field($chinese, 3));
        $this->assertSame('12', CadastralTxtExport::field('123', -2));
        $this->assertSame('   1', CadastralTxtExport::field('1', -4));
        $this->assertSame('1   ', CadastralTxtExport::field('1', 4));
    }

    public function testRejectsMalformedSectionBeforeQuerying(): void {
        $this->expectException(\InvalidArgumentException::class);
        AI02101Export::readSection('0001 OR 1=1', function ($sql) {
            throw new \LogicException('Malformed section reached database callback');
        });
    }
}
