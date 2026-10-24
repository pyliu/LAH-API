<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use JsonAPICommandFactory;

class JsonAPICommandFactoryTest extends TestCase {
    public function testGetCommandDefaultReturnsNull(): void {
        $_POST['type'] = 'unknown_type_' . uniqid();
        $res = JsonAPICommandFactory::getCommand($_POST['type']);
        $this->assertNull($res);
    }
}
