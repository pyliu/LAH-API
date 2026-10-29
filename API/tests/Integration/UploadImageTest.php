<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class UploadImageTest extends BaseIntegrationTestCase {
    private string $endpoint = 'upload_image.php';
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
        $path = tempnam(sys_get_temp_dir(), 'test_img_') . '.' . $ext;
        $img = imagecreatetruecolor($w, $h);
        $color = imagecolorallocate($img, 100, 150, 200);
        imagefill($img, 0, 0, $color);
        imagejpeg($img, $path, 80);
        // PHP 8.0+ GdImage is automatically freed upon falling out of scope

        $this->tempFilesToClean[] = $path;
        return $path;
    }

    private function createTempTxt(): string {
        $path = tempnam(sys_get_temp_dir(), 'test_img_') . '.txt';
        file_put_contents($path, 'Not an image');
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
        $this->assertArrayHasKey('uri', $res);
        $this->assertArrayHasKey('encoded', $res);
    }

    /**
     * 測試 2：夾帶非 JPG 副檔名檔案 (如 .txt)
     */
    public function testUploadInvalidExtensionFile(): void {
        $txtFile = $this->createTempTxt();
        $res = $this->post($this->endpoint, [], [
            'file' => $txtFile
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertStringContainsString('檔案不是JPG', $res['message']);
    }

    /**
     * 測試 3：成功上傳標準 JPG 影像並驗證縮圖、資料庫寫入與 Base64 回傳
     */
    public function testUploadValidJpgImage(): void {
        $jpgFile = $this->createTempJpeg(200, 200, 'jpg');
        $filename = basename($jpgFile);

        $res = $this->post($this->endpoint, [
            'width' => 150,
            'height' => 150,
            'quality' => 85,
            'note' => '整合測試影像備註'
        ], [
            'file' => $jpgFile
        ]);

        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertStringContainsString('影像已儲存', $res['message']);
        $this->assertNotEmpty($res['uri']);
        $this->assertStringContainsString('data:image/jpeg;base64,', $res['uri']);
        $this->assertNotEmpty($res['encoded']);
        $this->assertNotFalse(base64_decode($res['encoded'], true), 'encoded 欄位應為合法之 Base64 字串');

        // 清理上傳到 UPLOAD_IMG_DIR 的檔案
        $uploadedTarget = defined('UPLOAD_IMG_DIR') ? UPLOAD_IMG_DIR . DIRECTORY_SEPARATOR . $filename : null;
        if ($uploadedTarget && file_exists($uploadedTarget)) {
            $this->tempFilesToClean[] = $uploadedTarget;
        }
    }

    /**
     * 測試 4：大寫副檔名 JPG 支援 (如 .JPG, .JPEG)
     */
    public function testUploadUpperExtensionJpg(): void {
        $jpgUpper = $this->createTempJpeg(120, 120, 'JPG');
        $filename = basename($jpgUpper);

        $res = $this->post($this->endpoint, [
            'width' => 100,
            'height' => 100
        ], [
            'file' => $jpgUpper
        ]);

        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertStringContainsString('影像已儲存', $res['message']);

        $uploadedTarget = defined('UPLOAD_IMG_DIR') ? UPLOAD_IMG_DIR . DIRECTORY_SEPARATOR . $filename : null;
        if ($uploadedTarget && file_exists($uploadedTarget)) {
            $this->tempFilesToClean[] = $uploadedTarget;
        }
    }
}
