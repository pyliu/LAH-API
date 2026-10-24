<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Notification;

class NotificationTest extends TestCase {
    public function testInstantiation(): void {
        $notif = new Notification();
        $this->assertInstanceOf(Notification::class, $notif);
    }
}