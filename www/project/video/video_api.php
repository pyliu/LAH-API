<?php
// ═══════════════════════════════════════════════════════════════════════════
//  NAS 多媒體影音特區 - API 路由集中分發處理器 (video_api.php)
//  - 嚴格相容 PHP 5.6 (ASUSTOR AS-202TE ADM 3.5 運行環境)
//  - 集中分發串流 (stream)、目錄瀏覽 (browse)、全站最新 (recent)、字幕探測 (subtitles)
//  - 支援作為函式被 video.php 調度，亦支援獨立直連執行
// ═══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/video_core.php';
require_once __DIR__ . '/video_explorer.php';
require_once __DIR__ . '/video_subtitles.php';
require_once __DIR__ . '/video_stream.php';

/**
 * 處理影音特區 API 請求
 * 若有 action 參數且命中對應端點，則直接輸出並 exit；若無 action 參數則返回 false 交由前端渲染
 *
 * @param array $video_dirs 有效的影音目錄陣列
 * @param string $video_dir 主要基準目錄
 * @return bool
 */
function handle_video_api_request($video_dirs, $video_dir) {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if (empty($action)) {
        return false;
    }

    // 1. 串流播放 API
    if ($action === 'stream') {
        $req_file = isset($_GET['file']) ? $_GET['file'] : '';
        $real_target = safe_path_resolve($video_dir, $req_file);

        if ($real_target && is_file($real_target)) {
            stream_video_file($real_target);
        } else {
            header("HTTP/1.1 403 Forbidden");
            echo "403 Forbidden: Invalid file path.";
            exit;
        }
    }

    // 2. 動態讀取指定目錄內容 JSON API (支援任意深度與多資料庫聯合瀏覽，支援 force=1 強制更新快取)
    if ($action === 'browse') {
        $path = isset($_GET['path']) ? $_GET['path'] : '';
        $force = !empty($_GET['force']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(browse_directory($video_dirs, $path, $force), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2.5 隨機挑選指定資料夾內影片 API (供資料夾 Hover / 彈窗預覽使用，支援 force=1)
    if ($action === 'random_video') {
        $folder = isset($_GET['folder']) ? $_GET['folder'] : '';
        $force = !empty($_GET['force']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(get_random_video_in_folder($video_dirs, $folder, $force), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. 全站最新影音 API (支援取得最新新增/更新的影片與音訊，支援 force=1 強制更新)
    if ($action === 'recent') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 40;
        if ($limit <= 0 || $limit > 100) $limit = 40;
        $force = !empty($_GET['force']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(get_recent_videos($video_dirs, $limit, $force), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. 字幕偵測 API：傳回影片旁的可用字幕清單（外掛 + 全格式內嵌字幕）
    if ($action === 'subtitles') {
        $req_file = isset($_GET['file']) ? $_GET['file'] : '';
        header('Content-Type: application/json; charset=utf-8');
        if ($req_file === '') {
            echo json_encode(array('success' => false, 'subtitles' => array(), 'embedded' => array()));
            exit;
        }
        $subs = detect_subtitles($video_dir, $req_file);
        $real_file = safe_path_resolve($video_dir, $req_file);
        $embedded = ($real_file && @is_file($real_file)) ? parse_embedded_subtitles($real_file) : array();
        echo json_encode(array('success' => true, 'subtitles' => $subs, 'embedded' => $embedded), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4.5 內嵌字幕即時提取 API (輸出標準 WebVTT，供 HTML5 <track> 播放)
    if ($action === 'subtitles_vtt') {
        while (ob_get_level()) { @ob_end_clean(); }
        $req_file = isset($_GET['file']) ? $_GET['file'] : '';
        $sub_index = isset($_GET['sub_index']) ? intval($_GET['sub_index']) : 0;
        $track_num = isset($_GET['track_num']) ? intval($_GET['track_num']) : 0;

        $real_file = safe_path_resolve($video_dir, $req_file);
        if (!$real_file || !is_file($real_file)) {
            header("HTTP/1.1 404 Not Found");
            header('Access-Control-Allow-Origin: *');
            echo "404 Not Found: File is inaccessible.";
            exit;
        }

        $vtt = extract_embedded_subtitle_vtt($real_file, $sub_index, $track_num);
        if ($vtt === false || trim($vtt) === '') {
            header("HTTP/1.1 404 Not Found");
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: text/plain; charset=utf-8');
            echo "Subtitle extraction failed or track is not text-based.";
            exit;
        }

        header('Content-Type: text/vtt; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=86400');
        echo $vtt;
        exit;
    }

    // 4.6 內嵌 DVD 圖形字幕封包提取 API (輸出結構化 JSON Cues，供前端 Canvas 圖形字幕引擎即時解碼繪製)
    if ($action === 'subtitles_graphic') {
        while (ob_get_level()) { @ob_end_clean(); }
        $req_file = isset($_GET['file']) ? $_GET['file'] : '';
        $sub_index = isset($_GET['sub_index']) ? intval($_GET['sub_index']) : 0;
        $track_num = isset($_GET['track_num']) ? intval($_GET['track_num']) : 0;

        $real_file = safe_path_resolve($video_dir, $req_file);
        if (!$real_file || !is_file($real_file)) {
            header("HTTP/1.1 404 Not Found");
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => false, 'error' => '404 Not Found: File is inaccessible.'));
            exit;
        }

        $json_res = extract_dvd_subtitle_spu_cues($real_file, $sub_index, $track_num);
        if ($json_res === false || trim($json_res) === '') {
            header("HTTP/1.1 404 Not Found");
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => false, 'error' => 'Subtitle extraction failed or track contains no graphic cues.'));
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $json_res;
        exit;
    }

    // 5. 資料夾代表性預覽影片 API（供前端懸停動態探測，支援 force=1 強制更新快取）
    if ($action === 'folder_sample') {
        $folder = isset($_GET['folder']) ? $_GET['folder'] : '';
        $force = !empty($_GET['force']);
        header('Content-Type: application/json; charset=utf-8');
        if ($folder === '') {
            echo json_encode(array('success' => false, 'sample' => null), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $real_folder = safe_path_resolve($video_dir, $folder);
        $sample = ($real_folder && @is_dir($real_folder)) ? find_sample_video_in_folder($real_folder, $folder, 3, $force) : null;
        echo json_encode(array(
            'success' => ($sample !== null),
            'sample'  => $sample
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    return false;
}

// 支援直連 video_api.php 請求
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'video_api.php') {
    handle_video_api_request($video_dirs, $video_dir);
}
