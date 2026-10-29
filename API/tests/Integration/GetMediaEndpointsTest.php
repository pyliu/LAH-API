<?php
declare(strict_types=1);

namespace Tests\Integration;

class GetMediaEndpointsTest extends BaseIntegrationTestCase {

    /**
     * 路徑 1：get_adm_reserve_pdf.php 缺少參數回傳 400，查無資料回傳 404
     */
    public function testGetAdmReservePdf(): void {
        $rawMissing = $this->getRaw('get_adm_reserve_pdf.php');
        $this->assertSame(400, $rawMissing['http_code']);
        $this->assertStringContainsString('缺少公文號碼參數', $rawMissing['body']);

        $rawNotFound = $this->getRaw('get_adm_reserve_pdf.php', ['number' => 'NON_EXIST_99999']);
        $this->assertSame(404, $rawNotFound['http_code']);
        $this->assertStringContainsString('無法取得', $rawNotFound['body']);
    }

    /**
     * 路徑 2：get_reg_foreigner_pdf.php 缺少參數回傳 400，查無資料回傳 404
     */
    public function testGetRegForeignerPdf(): void {
        $rawMissing = $this->getRaw('get_reg_foreigner_pdf.php');
        $this->assertSame(400, $rawMissing['http_code']);
        $this->assertStringContainsString('缺少 ID 參數', $rawMissing['body']);

        $rawNotFound = $this->getRaw('get_reg_foreigner_pdf.php', ['id' => 'NON_EXIST_99999']);
        $this->assertSame(404, $rawNotFound['http_code']);
        $this->assertStringContainsString('無法取得', $rawNotFound['body']);
    }

    /**
     * 路徑 3：get_sur_destruction_pdf.php 缺少參數回傳 400，查無資料回傳 404
     */
    public function testGetSurDestructionPdf(): void {
        $rawMissing = $this->getRaw('get_sur_destruction_pdf.php');
        $this->assertSame(400, $rawMissing['http_code']);
        $this->assertStringContainsString('缺少 ID 參數', $rawMissing['body']);

        $rawNotFound = $this->getRaw('get_sur_destruction_pdf.php', ['id' => 'NON_EXIST_99999']);
        $this->assertSame(404, $rawNotFound['http_code']);
        $this->assertStringContainsString('無法取得', $rawNotFound['body']);
    }

    /**
     * 路徑 4：get_blob_img.php 查無影像回傳 200 (not_found.jpg) 或 404
     */
    public function testGetBlobImg(): void {
        $raw = $this->getRaw('get_blob_img.php', ['path' => 'non_exist_blob_path']);
        $this->assertContains($raw['http_code'], [200, 404]);
        if ($raw['http_code'] === 200) {
            $this->assertStringContainsString('image/jpeg', $raw['content_type']);
        }
    }

    /**
     * 路徑 5：get_carousel_img.php 查無影像回傳 not_found.jpg 預設圖
     */
    public function testGetCarouselImg(): void {
        $raw = $this->getRaw('get_carousel_img.php', ['file' => 'non_exist_carousel_img.jpg']);
        $this->assertContains($raw['http_code'], [200, 404]);
        if ($raw['http_code'] === 200) {
            $this->assertStringContainsString('image/jpeg', $raw['content_type']);
        }
    }

    /**
     * 路徑 6：get_user_img.php 查無使用者圖檔回傳 not_found 預設頭像
     */
    public function testGetUserImg(): void {
        $rawUser = $this->getRaw('get_user_img.php', ['id' => 'NON_EXIST_USER_99999']);
        $this->assertContains($rawUser['http_code'], [200, 404]);
        if ($rawUser['http_code'] === 200) {
            $this->assertStringContainsString('image/jpeg', $rawUser['content_type']);
        }

        $rawAvatar = $this->getRaw('get_user_img.php', ['id' => 'NON_EXIST_USER_99999_avatar']);
        $this->assertContains($rawAvatar['http_code'], [200, 404]);
        if ($rawAvatar['http_code'] === 200) {
            $this->assertStringContainsString('image/jpeg', $rawAvatar['content_type']);
        }
    }
}
