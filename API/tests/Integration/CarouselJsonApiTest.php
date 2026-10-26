<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class CarouselJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'carousel_json_api.php';
    private array $tempPosterFiles = [];

    protected function tearDown(): void {
        foreach ($this->tempPosterFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempPosterFiles = [];
        parent::tearDown();
    }

    /**
     * 路徑 1：不支援的查詢型態（default 分支）
     */
    public function testUnsupportedTypeReturnsError(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'unsupported_carousel_action'
        ]);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_carousel_action】', $res['message']);
    }

    /**
     * 路徑 2：空 type 參數（default 分支防護）
     */
    public function testEmptyTypeReturnsError(): void {
        $res = $this->post($this->endpoint, []);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態', $res['message']);
    }

    /**
     * 路徑 3：查詢輪播海報清單（list 分支）
     */
    public function testListPostersSuccess(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'list'
        ]);

        $this->assertResponseSuccess($res, '個 JPEG 檔案');
        $this->assertArrayHasKey('files', $res);
        $this->assertIsArray($res['files']);

        foreach ($res['files'] as $file) {
            $this->assertMatchesRegularExpression('/\.(jpe?g)$/i', $file, "檔案 {$file} 必須為 JPEG/JPG 格式");
        }
    }

    /**
     * 路徑 4：驗證動態加入之 JPG 海報能被正確列出
     */
    public function testListPostersIncludesNewJpg(): void {
        $posterDir = POSTER_IMG_DIR;
        if (!is_dir($posterDir)) {
            @mkdir($posterDir, 0777, true);
        }

        $testJpgName = 'integ_test_' . uniqid() . '.jpg';
        $testJpgPath = $posterDir . DIRECTORY_SEPARATOR . $testJpgName;
        file_put_contents($testJpgPath, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00\xFF\xDB");
        $this->tempPosterFiles[] = $testJpgPath;

        $res = $this->post($this->endpoint, [
            'type' => 'list'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertContains($testJpgName, $res['files'], "新加入的 JPG 海報 {$testJpgName} 應包含在清單中");
    }

    /**
     * 路徑 5：驗證海報資料夾中的非 JPG 檔案被正確過濾忽略
     */
    public function testListPostersIgnoresNonJpgFiles(): void {
        $posterDir = POSTER_IMG_DIR;
        if (!is_dir($posterDir)) {
            @mkdir($posterDir, 0777, true);
        }

        $testTxtName = 'integ_test_' . uniqid() . '.txt';
        $testTxtPath = $posterDir . DIRECTORY_SEPARATOR . $testTxtName;
        file_put_contents($testTxtPath, 'Dummy text file in poster dir');
        $this->tempPosterFiles[] = $testTxtPath;

        $testPngName = 'integ_test_' . uniqid() . '.png';
        $testPngPath = $posterDir . DIRECTORY_SEPARATOR . $testPngName;
        file_put_contents($testPngPath, "\x89PNG\r\n\x1a\n");
        $this->tempPosterFiles[] = $testPngPath;

        $res = $this->post($this->endpoint, [
            'type' => 'list'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertNotContains($testTxtName, $res['files'], "TXT 檔案不應出現在輪播海報清單中");
        $this->assertNotContains($testPngName, $res['files'], "PNG 檔案依現行邏輯不應出現在 JPEG 海報清單中");
    }
}
