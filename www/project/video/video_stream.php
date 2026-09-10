<?php
// ═══════════════════════════════════════════════════════════════════════════
//  NAS 多媒體影音特區 - HTTP 206 Range 分段串流引擎 (video_stream.php)
//  - 嚴格相容 PHP 5.6 (ASUSTOR AS-202TE ADM 3.5 運行環境)
//  - 支援 OPTIONS 預檢、全域 CORS 標頭（讓 Chromecast / Google TV 順暢 Seek）
//  - 支援即時外掛 SRT 轉 VTT 串流
//  - 256KB Chunk 循環緩衝分段輸出
// ═══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/video_core.php';

// ─── HTTP 206 Partial Content (Range) 多格式串流引擎 ───────────────────────
function stream_video_file($filepath) {
    $filepath = safe_fs_path($filepath);
    // 提前清空所有現存輸出緩衝，確保 HTTP 標頭在最前面發送
    while (ob_get_level()) {
        @ob_end_clean();
    }
    @set_time_limit(0);

    // 1. 若為 OPTIONS 預檢請求，立即回應 204 與 CORS 標頭
    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Range, Accept-Encoding, If-Range, Cache-Control');
        header('Access-Control-Max-Age: 86400');
        header('HTTP/1.1 204 No Content');
        exit;
    }

    if (!@file_exists($filepath) || !@is_readable($filepath)) {
        header("HTTP/1.1 404 Not Found");
        header('Access-Control-Allow-Origin: *');
        echo "404 Not Found: Video file is inaccessible.";
        exit;
    }

    $parts = get_filename_parts(fs_to_utf8(basename($filepath)));
    $ext = $parts['ext'];
    $mime_map = array(
        // 影片格式 (Video)
        'mp4'  => 'video/mp4',
        'm4v'  => 'video/mp4',
        'webm' => 'video/webm',
        'mkv'  => 'video/x-matroska',
        'mov'  => 'video/quicktime',
        'ogv'  => 'video/ogg',
        '3gp'  => 'video/3gpp',
        '3gpp' => 'video/3gpp',
        'ts'   => 'video/mp2t',
        'avi'  => 'video/x-msvideo',
        // 音訊格式 (Audio)
        'mp3'  => 'audio/mpeg',
        'flac' => 'audio/flac',
        'm4a'  => 'audio/mp4',
        'wav'  => 'audio/wav',
        'aac'  => 'audio/aac',
        'ogg'  => 'audio/ogg',
        'oga'  => 'audio/ogg',
        'opus' => 'audio/opus',
        'weba' => 'audio/webm',
        // 字幕格式 (Subtitle) - 直接以文字串流提供
        'vtt'  => 'text/vtt; charset=utf-8',
        'srt'  => 'text/plain; charset=utf-8',
        'ass'  => 'text/plain; charset=utf-8',
        'ssa'  => 'text/plain; charset=utf-8',
        'sub'  => 'text/plain; charset=utf-8',
    );
    $content_type = isset($mime_map[$ext]) ? $mime_map[$ext] : 'video/mp4';

    // ── 相容模式：若請求指示 compat=1，將 MKV / TS 等偽裝為 video/mp4 突破電視接收器限制 ──
    $is_compat = isset($_GET['compat']) && $_GET['compat'] === '1';
    if ($is_compat && in_array($ext, array('mkv', 'm4v', 'ts', 'avi'))) {
        $content_type = 'video/mp4';
    }
    // ── 外掛 SRT 自動轉換為標準 WebVTT，確保 HTML5 <track> 零報錯載入 ──
    if ($ext === 'srt') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Content-Type: text/vtt; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'HEAD') {
            exit;
        }
        $srt_raw = @file_get_contents($filepath);
        if ($srt_raw === false) $srt_raw = '';
        $srt_raw = preg_replace('/^\xEF\xBB\xBF/', '', $srt_raw);
        $vtt_body = preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $srt_raw);
        $out = "WEBVTT\n\n" . ltrim($vtt_body);
        header('Content-Length: ' . strlen($out));
        echo $out;
        exit;
    }

    $filesize = (float)get_real_filesize($filepath);
    $offset = 0.0;
    $end = ($filesize > 0) ? ($filesize - 1.0) : 0.0;
    $is_range = false;

    // 支援完整 HTTP Range 規格 (bytes=X-Y, bytes=X-, bytes=-Y)
    if ($filesize > 0 && isset($_SERVER['HTTP_RANGE'])) {
        $range_header = trim($_SERVER['HTTP_RANGE']);
        if (preg_match('/bytes=\s*(\d*)\s*-\s*(\d*)/i', $range_header, $matches)) {
            $r_start = $matches[1];
            $r_end   = isset($matches[2]) ? $matches[2] : '';

            if ($r_start === '' && $r_end !== '') {
                // suffix range: bytes=-500 (檔案最後 500 位元組)
                $suffix = (float)$r_end;
                if ($suffix > $filesize) $suffix = $filesize;
                $offset = $filesize - $suffix;
                $end = $filesize - 1.0;
                $is_range = true;
            } elseif ($r_start !== '') {
                $offset = (float)$r_start;
                if ($r_end !== '') {
                    $end = min((float)$r_end, $filesize - 1.0);
                } else {
                    $end = $filesize - 1.0;
                }
                if ($offset <= $end && $offset < $filesize) {
                    $is_range = true;
                } else {
                    header('HTTP/1.1 416 Requested Range Not Satisfiable');
                    header(sprintf("Content-Range: bytes */%.0f", $filesize));
                    exit;
                }
            }
        }
    }

    $length = ($filesize > 0) ? ($end - $offset + 1.0) : 0.0;

    // ── 輸出全站跨來源標頭（Chromecast / Google TV 必要） ──
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Range, Accept-Encoding, If-Range, Cache-Control');
    header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, Content-Type');

    header('Content-Type: ' . $content_type);
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');
    header('Connection: keep-alive');

    if ($is_range) {
        header('HTTP/1.1 206 Partial Content');
        header(sprintf("Content-Range: bytes %.0f-%.0f/%.0f", $offset, $end, $filesize));
    } else {
        header('HTTP/1.1 200 OK');
    }

    if ($length > 0) {
        header(sprintf('Content-Length: %.0f', $length));
    }

    // ── 關鍵：若客戶端僅發起 HEAD 探測請求，立即結束，絕不輸出 body ──
    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'HEAD') {
        exit;
    }
    @set_time_limit(0);

    $fp = @fopen($filepath, 'rb');
    if (!$fp) {
        header("HTTP/1.1 500 Internal Server Error");
        exit;
    }

    if ($offset > 0) {
        safe_fseek($fp, $offset);
    }

    // ── 智慧自適應傳輸緩衝區（512 KB ~ 1 MB）──
    // 針對網路壅塞與高碼率影音，加大傳輸區塊以充分填滿 TCP 視窗並顯著降低 NAS Atom CPU 核心排程負擔
    $buffer_size = 512 * 1024; // 預設 512 KB
    if ($filesize > 500 * 1024 * 1024) {
        $buffer_size = 1024 * 1024; // 大於 500MB 之高畫質長片採用 1 MB 區塊傳輸
    }
    $bytes_left = $length;

    while ($bytes_left > 0 && !feof($fp)) {
        if (connection_aborted() || connection_status() != CONNECTION_NORMAL) {
            break;
        }
        $read_bytes = ($bytes_left > $buffer_size) ? $buffer_size : (int)$bytes_left;
        $data = fread($fp, $read_bytes);
        if ($data === false || $data === '') break;
        echo $data;
        @ob_flush();
        @flush();
        $bytes_left -= strlen($data);
    }

    @fclose($fp);
    exit;
}
