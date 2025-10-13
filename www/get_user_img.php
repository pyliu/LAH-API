<?php
require_once("./include/init.php");

$default_path = ROOT_DIR . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "users" . DIRECTORY_SEPARATOR;

$is_image_found = true; // 預設為 true，如果找不到檔案則設為 false

$key = array_key_exists('id', $_REQUEST) ? $_REQUEST['id'] : '';
$full_path = $default_path . $key . '.jpg';

if (!file_exists($full_path)) {
    $key = $_REQUEST["name"] ?? ''; // Use null coalescing operator for safety
    $full_path = $default_path . $key . '.jpg';
    if (!file_exists($full_path)) {
        $is_image_found = false; // 找不到指定圖片，設定為 false
        $full_path = $default_path . (strpos($key, '_avatar') ? 'not_found_avatar' . rand(1, 12) . '.jpg' : 'not_found.jpg');
        // rollback to default if still not found
        if (!file_exists($full_path) && strpos($key, '_avatar')) {
            $full_path = $default_path . 'not_found_avatar.jpg';
        }
        $key = 'not_found';
    }
}

// 只有在找到真實的圖片檔案時，才設定快取 headers
if ($is_image_found) {
    // --- 快取設定開始 ---

    // 設定快取時間為一週 (7 * 24 * 60 * 60 = 604800 秒)
    $cache_duration = 604800;
    $expires_time = time() + $cache_duration;

    // Cache-Control Header:
    // public: 表示回應可以被任何快取裝置（例如：瀏覽器、代理伺服器）快取。
    // max-age: 告訴瀏覽器這個資源可以快取多久（單位：秒）。
    header('Cache-Control: public, max-age=' . $cache_duration);

    // Expires Header:
    // 這是比較舊的快取機制，提供一個明確的過期時間點。
    // 為了相容舊版瀏覽器或代理伺服器，建議與 Cache-Control 一起使用。
    // 時間必須是 GMT 格式。
    header('Expires: ' . gmdate('D, d M Y H:i:s', $expires_time) . ' GMT');

    // Last-Modified Header:
    // 告知瀏覽器檔案的最後修改時間。
    // 當快取過期後，瀏覽器會用 If-Modified-Since header 帶上這個時間去詢問伺服器，
    // 如果伺服器發現檔案沒有變更，就可以回傳 304 Not Modified，節省傳輸流量。
    if (file_exists($full_path)) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($full_path)) . ' GMT');
    }

    // --- 快取設定結束 ---
}


// 取得並設定檔案的 MIME 類型
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$contentType = finfo_file($finfo, $full_path);
finfo_close($finfo);
header('Content-Type: ' . $contentType);

// 設定檔案大小
header('Content-Length: ' . filesize($full_path));

// 清除輸出緩衝區並輸出檔案內容
ob_clean();
flush();
readfile($full_path);
