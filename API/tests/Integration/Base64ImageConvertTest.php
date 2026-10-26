<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class Base64ImageConvertTest extends BaseIntegrationTestCase {
    private string $endpoint = 'base64_image_convert.php';
    private array $tempFiles = [];

    protected function tearDown(): void {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    private function createTempImage(int $width, int $height, string $format = 'jpeg'): string {
        $ext = strtolower($format);
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'integ_test_img_' . uniqid() . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $im = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($im, 70, 130, 180);
        imagefill($im, 0, 0, $color);

        switch ($ext) {
            case 'png':
                imagepng($im, $path);
                break;
            case 'gif':
                imagegif($im, $path);
                break;
            default:
                imagejpeg($im, $path, 90);
                break;
        }

        unset($im);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * 路徑 1：未上傳任何檔案（失敗分支：無檔案）
     */
    public function testNoFileUploadedReturnsFail(): void {
        $res = $this->post($this->endpoint, [
            'width' => 100,
            'height' => 100
        ]);

        $this->assertResponseFail($res, STATUS_CODE::DEFAULT_FAIL);
        $this->assertSame('已上傳', $res['message']);
        $this->assertNull($res['uri']);
        $this->assertNull($res['encoded']);
    }

    /**
     * 路徑 2：上傳不支援之非圖片檔案（失敗分支：副檔名不支援）
     */
    public function testUnsupportedFileTypeReturnsFail(): void {
        $txtPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'integ_test_txt_' . uniqid() . '.txt';
        file_put_contents($txtPath, 'Not an image file content.');
        $this->tempFiles[] = $txtPath;

        $res = $this->post(
            $this->endpoint,
            ['width' => 100],
            ['file' => $txtPath]
        );

        $this->assertResponseFail($res, STATUS_CODE::DEFAULT_FAIL, '檔案不是支援的影像檔案');
        $this->assertNull($res['uri']);
        $this->assertNull($res['encoded']);
    }

    /**
     * 路徑 3：上傳並縮放 JPEG 圖片
     */
    public function testJpegImageConvertSuccess(): void {
        $jpgPath = $this->createTempImage(200, 150, 'jpeg');

        $res = $this->post(
            $this->endpoint,
            [
                'width' => 100,
                'height' => 100,
                'quality' => 85
            ],
            [
                'file' => $jpgPath
            ]
        );

        $this->assertResponseSuccess($res, '暫存影像已儲存到');
        $this->assertStringStartsWith('data:image/jpeg;base64,', $res['uri'] ?? '');
        $this->assertNotEmpty($res['encoded']);

        $decoded = base64_decode($res['encoded'], true);
        $this->assertNotFalse($decoded, '回傳的 Base64 必須可成功解碼');
    }

    /**
     * 路徑 4：上傳並縮放 PNG 圖片
     */
    public function testPngImageConvertSuccess(): void {
        $pngPath = $this->createTempImage(160, 120, 'png');

        $res = $this->post(
            $this->endpoint,
            [
                'width' => 80,
                'height' => 60
            ],
            [
                'file' => $pngPath
            ]
        );

        $this->assertResponseSuccess($res, '暫存影像已儲存到');
        $this->assertStringStartsWith('data:image/png;base64,', $res['uri'] ?? '');
        $this->assertNotEmpty($res['encoded']);

        $decoded = base64_decode($res['encoded'], true);
        $this->assertNotFalse($decoded);
    }

    /**
     * 路徑 5：上傳並縮放 GIF 圖片
     */
    public function testGifImageConvertSuccess(): void {
        $gifPath = $this->createTempImage(120, 120, 'gif');

        $res = $this->post(
            $this->endpoint,
            [
                'width' => 60,
                'height' => 60
            ],
            [
                'file' => $gifPath
            ]
        );

        $this->assertResponseSuccess($res, '暫存影像已儲存到');
        $this->assertStringStartsWith('data:image/gif;base64,', $res['uri'] ?? '');
        $this->assertNotEmpty($res['encoded']);

        $decoded = base64_decode($res['encoded'], true);
        $this->assertNotFalse($decoded);
    }

    /**
     * 路徑 6：驗證縮圖尺寸約束（大圖縮小至指定上限寬高）
     */
    public function testImageResizingDimensions(): void {
        $bigPath = $this->createTempImage(800, 600, 'jpeg');

        $res = $this->post(
            $this->endpoint,
            [
                'width' => 200,
                'height' => 150,
                'quality' => 75
            ],
            [
                'file' => $bigPath
            ]
        );

        $this->assertResponseSuccess($res);
        $this->assertNotEmpty($res['encoded']);

        $imageBinary = base64_decode($res['encoded']);
        $sizeInfo = getimagesizefromstring($imageBinary);
        $this->assertNotFalse($sizeInfo, '縮圖結果必須是合法影像串流');

        [$outWidth, $outHeight] = $sizeInfo;
        $this->assertLessThanOrEqual(200, $outWidth, '產出寬度不得大於指定上限 200');
        $this->assertLessThanOrEqual(150, $outHeight, '產出高度不得大於指定上限 150');
    }
}
