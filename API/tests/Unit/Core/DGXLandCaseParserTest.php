<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use DGXLandCaseParser;

class DGXLandCaseParserTest extends TestCase {
    private DGXLandCaseParser $parser;

    protected function setUp(): void {
        parent::setUp();
        $this->parser = new DGXLandCaseParser();
    }

    public function testInstantiation(): void {
        $this->assertInstanceOf(DGXLandCaseParser::class, $this->parser);
    }

    public function testParseStandardCaseCodeWithRegex(): void {
        // Direct case word code like 110-HA81-012345
        $result = $this->parser->parse("110-HA81-012345");
        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false);
        $this->assertNotEmpty($result['results']);

        $first = $result['results'][0];
        $this->assertEquals(110, $first['year_miguo']);
        $this->assertEquals('HA81', $first['case_word']);
        $this->assertEquals('012345', $first['case_no']);
    }

    public function testParseInMockModeFallback(): void {
        // Arbitrary non-regex text should return mock result safely
        $result = $this->parser->parse("隨機文字輸入非標準地政案件");
        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false);
    }
}
