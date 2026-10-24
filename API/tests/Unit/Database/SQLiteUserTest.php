<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteUser;
use AUTHORITY;

class SQLiteUserTest extends TestCase {
    private SQLiteUser $userDao;

    protected function setUp(): void {
        parent::setUp();
        $this->userDao = new SQLiteUser();
    }

    public function testNonExistentUserReturnsEmptyArray(): void {
        $result = $this->userDao->getUser('NON_EXISTENT_ID_' . uniqid());
        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Querying non-existent user should return empty array');
    }

    public function testAddAndGetUser(): void {
        $testId = 'U_' . uniqid();
        $userData = [
            'id' => $testId,
            'name' => '測試人員',
            'sex' => 1,
            'title' => '測試職稱',
            'work' => '自動化測試',
            'ext' => '123',
            'birthday' => '080/01/01',
            'unit' => '資訊課',
            'ip' => '192.168.99.99',
            'education' => '大學',
            'exam' => '高考',
            'cell' => '0912345678',
            'onboard_date' => '113/01/01',
            'authority' => AUTHORITY::NORMAL
        ];

        // Add user
        $added = $this->userDao->addUser($userData);
        $this->assertTrue($added, 'User should be added successfully');

        // Retrieve user
        $rows = $this->userDao->getUser($testId);
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);

        $user = $rows[0];
        $this->assertEquals($testId, $user['id']);
        $this->assertEquals('測試人員', $user['name']);
        $this->assertEquals('資訊課', $user['unit']);
    }

    public function testOnboardAndOffboardUser(): void {
        $testId = 'U_OB_' . uniqid();
        // Ensure user exists first
        $added = $this->userDao->addUser([
            'id' => $testId,
            'name' => '離職員工',
            'authority' => AUTHORITY::NORMAL
        ]);
        $this->assertTrue($added);

        // Offboard user
        $offboarded = $this->userDao->offboardUser($testId);
        $this->assertTrue($offboarded);

        $rows = $this->userDao->getUser($testId);
        $this->assertNotEmpty($rows);
        $user = $rows[0];
        $this->assertGreaterThan(0, (int)$user['authority'] & AUTHORITY::DISABLED, 'Offboarded user must have DISABLED bit set');

        // Onboard user
        $onboarded = $this->userDao->onboardUser($testId);
        $this->assertTrue($onboarded);

        $rows = $this->userDao->getUser($testId);
        $this->assertNotEmpty($rows);
        $user = $rows[0];
        $this->assertEquals(0, (int)$user['authority'] & AUTHORITY::DISABLED, 'Onboarded user must have DISABLED bit cleared');
    }
}
