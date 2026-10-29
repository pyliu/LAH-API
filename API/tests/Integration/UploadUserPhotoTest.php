<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class UploadUserPhotoTest extends BaseIntegrationTestCase {
    private string $endpoint = 'upload_user_photo.php';
    private array $tempFilesToClean = [];

    protected function tearDown(): void {
        foreach ($this->tempFilesToClean as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFilesToClean = [];
        parent::tearDown();
    }

    private function createTempJpeg(int $w = 100, int $h = 100, string $ext = 'jpg'): string {
        $path = tempnam(sys_get_temp_dir(), 'test_photo_') . '.' . $ext;
        $img = imagecreatetruecolor($w, $h);
        $color = imagecolorallocate($img, 120, 180, 240);
        imagefill($img, 0, 0, $color);
        imagejpeg($img, $path, 85);
        // PHP 8.0+ GdImage is automatically freed when falling out of scope

        $this->tempFilesToClean[] = $path;
        return $path;
    }

    private function createTempTxt(): string {
        $path = tempnam(sys_get_temp_dir(), 'test_photo_') . '.txt';
        file_put_contents($path, 'Not a photo');
        $this->tempFilesToClean[] = $path;
        return $path;
    }

    /**
     * 測試 1：未夾帶檔案之上傳請求
     */
    public function testUploadWithoutFile(): void {
        $res = $this->post($this->endpoint, []);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertArrayHasKey('message', $res);
    }

    /**
     * 測試 2：夾帶非 JPG 副檔名檔案 (如 .txt)
     */
    public function testUploadInvalidExtensionFile(): void {
        $txtFile = $this->createTempTxt();
        $res = $this->post($this->endpoint, [
            'id' => 'TEST_USER_99',
            'name' => '測試同仁'
        ], [
            'file' => $txtFile
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertStringContainsString('檔案不是JPG', $res['message']);
    }

    /**
     * 測試 3：成功上傳標準使用者大頭照 (一般相片，同時產生 ID 與 Name 檔名)
     */
    public function testUploadStandardUserPhoto(): void {
        $jpgFile = $this->createTempJpeg(200, 200, 'jpg');
        $userId = 'TEST_U' . random_int(1000, 9999);
        $userName = '測試員_' . random_int(100, 999);

        $res = $this->post($this->endpoint, [
            'id' => $userId,
            'name' => $userName,
            'avatar' => 'false'
        ], [
            'file' => $jpgFile
        ]);

        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertStringContainsString('已儲存', $res['message']);

        // 驗證磁碟上已建立 ID 與姓名檔名
        $targetDir = defined('USER_IMG_DIR') ? USER_IMG_DIR : '';
        if (!empty($targetDir)) {
            $idFile = $targetDir . DIRECTORY_SEPARATOR . $userId . '.jpg';
            $nameFile = $targetDir . DIRECTORY_SEPARATOR . $userName . '.jpg';

            $this->assertFileExists($idFile, "應產生以 ID 命名之相片檔案: {$idFile}");
            $this->assertFileExists($nameFile, "應產生以姓名命名之相片檔案: {$nameFile}");

            $this->tempFilesToClean[] = $idFile;
            $this->tempFilesToClean[] = $nameFile;
        }
    }

    /**
     * 測試 4：成功上傳頭像相片 (avatar=true，檔名帶有 _avatar)
     */
    public function testUploadUserAvatarPhoto(): void {
        $jpgFile = $this->createTempJpeg(300, 300, 'jpg');
        $userId = 'TEST_AVATAR_' . random_int(1000, 9999);
        $userName = '頭像員_' . random_int(100, 999);

        $res = $this->post($this->endpoint, [
            'id' => $userId,
            'name' => $userName,
            'avatar' => 'true'
        ], [
            'file' => $jpgFile
        ]);

        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertStringContainsString('已儲存', $res['message']);

        $targetDir = defined('USER_IMG_DIR') ? USER_IMG_DIR : '';
        if (!empty($targetDir)) {
            $idAvatarFile = $targetDir . DIRECTORY_SEPARATOR . $userId . '_avatar.jpg';
            $nameAvatarFile = $targetDir . DIRECTORY_SEPARATOR . $userName . '_avatar.jpg';

            $this->assertFileExists($idAvatarFile, "應產生以 ID 命名之頭像檔案: {$idAvatarFile}");
            $this->assertFileExists($nameAvatarFile, "應產生以姓名命名之頭像檔案: {$nameAvatarFile}");

            $this->tempFilesToClean[] = $idAvatarFile;
            $this->tempFilesToClean[] = $nameAvatarFile;
        }
    }

    /**
     * 測試 5：大寫副檔名支援 (如 .JPG)
     */
    public function testUploadUpperExtensionJpg(): void {
        $jpgUpper = $this->createTempJpeg(150, 150, 'JPG');
        $userId = 'TEST_UPPER_' . random_int(1000, 9999);

        $res = $this->post($this->endpoint, [
            'id' => $userId,
            'avatar' => 'false'
        ], [
            'file' => $jpgUpper
        ]);

        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertStringContainsString('已儲存', $res['message']);

        $targetDir = defined('USER_IMG_DIR') ? USER_IMG_DIR : '';
        if (!empty($targetDir)) {
            $idFile = $targetDir . DIRECTORY_SEPARATOR . $userId . '.JPG';
            if (file_exists($idFile)) {
                $this->tempFilesToClean[] = $idFile;
            }
        }
    }
}
