<?php
// ═══════════════════════════════════════════════════════════════════════════
//  NAS 多媒體影音特區 - 目錄導航、全站最新快取與外掛字幕偵測模組 (video_explorer.php)
//  - 嚴格相容 PHP 5.6 (ASUSTOR AS-202TE ADM 3.5 運行環境)
//  - 支援任意深度多層級目錄動態瀏覽與多資料庫聯合前綴
//  - 內建 5 分鐘快取機制，大幅降低低功耗 NAS 硬碟與 CPU 負載
// ═══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/video_core.php';

/**
 * 取得影音檔案快取目錄（預設 __DIR__ . '/cache'，若不存在則自動建立，不可寫入則回退至系統暫存目錄）
 * 
 * @return string 快取目錄絕對路徑
 */
function get_video_explorer_cache_dir() {
    $dir = __DIR__ . '/cache';
    if (!@is_dir($dir)) {
        @mkdir($dir, 0777, true);
        @file_put_contents($dir . '/index.html', '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory listing denied</h1></body></html>');
    }
    if (@is_dir($dir) && @is_writable($dir)) {
        return $dir;
    }
    return sys_get_temp_dir();
}

/**
 * 取得全站最新影音檔案 (支援 NAS 高效查詢、跨目錄聯合掃描、5分鐘智慧快取與手動強制更新機制)
 * 
 * @param string|array $base_dir 根目錄路徑或目錄陣列
 * @param int $limit 取得筆數上限 (預設 40)
 * @param bool $force 是否強制忽略快取重新掃描 (預設 false)
 * @return array 包含影片清單與快取資訊的關聯陣列
 */
function get_recent_videos($base_dir, $limit = 40, $force = false) {
    global $allowed_extensions, $audio_extensions, $video_dirs;
    $dirs = is_array($base_dir) ? $base_dir : ((isset($video_dirs) && is_array($video_dirs)) ? $video_dirs : array($base_dir));

    // 過濾出真實存在的目錄
    $valid_dirs = array();
    foreach ($dirs as $d) {
        $real = safe_path_resolve_single($d, '');
        if ($real && @is_dir($real)) {
            $valid_dirs[] = $real;
        }
    }
    if (empty($valid_dirs)) {
        return array('success' => false, 'videos' => array(), 'count' => 0, 'from_cache' => false);
    }

    $is_multi = (count($valid_dirs) > 1);
    $ttl = 300; // 快取有效時間 5 分鐘 (300 秒)，極致保護 NAS 硬碟與低功耗 CPU
    $cache_dir = get_video_explorer_cache_dir();
    $cache_file = rtrim($cache_dir, '/\\') . '/nas_recent_videos_' . md5(implode('|', $valid_dirs)) . '.json';

    // 1. 若非強制更新且快取檔案仍在有效期限內，直接讀取快取（零 CPU / 硬碟開銷）
    if (!$force && @file_exists($cache_file)) {
        $file_age = time() - @filemtime($cache_file);
        if ($file_age < $ttl) {
            $cached_content = @file_get_contents($cache_file);
            $cached = @json_decode($cached_content, true);
            if ($cached && is_array($cached) && !empty($cached['videos'])) {
                $cached['from_cache'] = true;
                $cached['cache_age'] = $file_age;
                $cached['ttl'] = $ttl;
                return $cached;
            }
        }
    }

    $candidates = array();

    // 掃描各個有效目錄
    foreach ($valid_dirs as $real_base) {
        $prefix = $is_multi ? (basename($real_base) . '/') : '';
        $norm_base = rtrim(str_replace('\\', '/', $real_base), '/');
        $dir_candidates = array();

        // 方法 A：Linux/NAS 核心快速檢索（高效率、零 PHP 記憶體開銷）
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $name_conds = array();
            foreach ($allowed_extensions as $ext) {
                $name_conds[] = '-name "*.' . $ext . '"';
            }
            $name_str = implode(' -o ', $name_conds);

            // 嘗試 find -printf 快速獲取 mtime, size, path
            $cmd = 'find ' . escapeshellarg($real_base) . ' -type f \( ' . $name_str . ' \) -printf "%T@\t%s\t%p\n" 2>/dev/null | sort -nr | head -n ' . intval($limit);
            $lines = @shell_exec($cmd);

            if (!empty($lines)) {
                $raw_lines = explode("\n", trim($lines));
                foreach ($raw_lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $parts = explode("\t", $line, 3);
                    if (count($parts) === 3) {
                        $norm_fpath = str_replace('\\', '/', $parts[2]);
                        $rel_path = $prefix . ltrim(substr($norm_fpath, strlen($norm_base)), '/');
                        $dir_candidates[] = array(
                            'mtime'    => (int)$parts[0],
                            'size'     => (float)$parts[1],
                            'path'     => $parts[2],
                            'rel_path' => $rel_path
                        );
                    }
                }
            } else {
                // Busybox find fallback
                $cmd = 'find ' . escapeshellarg($real_base) . ' -type f \( ' . $name_str . ' \) 2>/dev/null';
                $file_list = @shell_exec($cmd);
                if (!empty($file_list)) {
                    $raw_files = explode("\n", trim($file_list));
                    foreach ($raw_files as $fpath) {
                        $fpath = trim($fpath);
                        if ($fpath === '') continue;
                        $mtime = @filemtime($fpath);
                        if ($mtime) {
                            $norm_fpath = str_replace('\\', '/', $fpath);
                            $rel_path = $prefix . ltrim(substr($norm_fpath, strlen($norm_base)), '/');
                            $dir_candidates[] = array(
                                'path'     => $fpath,
                                'mtime'    => $mtime,
                                'rel_path' => $rel_path
                            );
                        }
                    }
                }
            }
        }

        // 方法 B：若上述未取得，以 PHP 遞迴迭代器掃描回退
        if (empty($dir_candidates)) {
            try {
                $dir_iter = new RecursiveDirectoryIterator($real_base, RecursiveDirectoryIterator::SKIP_DOTS);
                $iter = new RecursiveIteratorIterator($dir_iter, RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($iter as $fileinfo) {
                    if ($fileinfo->isFile()) {
                        $fn = $fileinfo->getFilename();
                        $dot = strrpos($fn, '.');
                        if ($dot !== false) {
                            $ext = strtolower(substr($fn, $dot + 1));
                            if (in_array($ext, $allowed_extensions)) {
                                $norm_fpath = str_replace('\\', '/', $fileinfo->getPathname());
                                $rel_path = $prefix . ltrim(substr($norm_fpath, strlen($norm_base)), '/');
                                $dir_candidates[] = array(
                                    'path'     => $fileinfo->getPathname(),
                                    'mtime'    => $fileinfo->getMTime(),
                                    'size'     => $fileinfo->getSize(),
                                    'rel_path' => $rel_path
                                );
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
        }

        foreach ($dir_candidates as $dc) {
            $candidates[] = $dc;
        }
    }

    // 跨目錄總排序
    usort($candidates, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
    $candidates = array_slice($candidates, 0, $limit);

    // 格式化最終輸出
    $videos = array();
    $two_weeks_ago = time() - (86400 * 14);

    foreach ($candidates as $item) {
        $fpath = $item['path'];
        $rel_path = fs_to_utf8($item['rel_path']);
        $filename = fs_to_utf8(basename($fpath));
        $info = get_filename_parts($filename);
        $ext = $info['ext'];
        $size = isset($item['size']) && $item['size'] > 0 ? (float)$item['size'] : get_real_filesize($fpath);
        $mtime = isset($item['mtime']) ? (int)$item['mtime'] : (int)@filemtime($fpath);
        $is_audio = in_array($ext, $audio_extensions);

        // 解析上層子目錄
        $dir_parts = explode('/', $rel_path);
        array_pop($dir_parts);
        $folder_path = implode('/', $dir_parts);

        $videos[] = array(
            'id'          => md5($rel_path),
            'rel_path'    => $rel_path,
            'folder_path' => $folder_path,
            'filename'    => $filename,
            'ext'         => strtoupper($ext),
            'title'       => $info['title'],
            'size'        => $size,
            'size_str'    => format_bytes($size),
            'mtime'       => $mtime,
            'mtime_str'   => $mtime ? date('Y-m-d H:i', $mtime) : '-',
            'time_ago'    => get_time_ago($mtime),
            'is_audio'    => $is_audio,
            'is_new'      => ($mtime > $two_weeks_ago)
        );
    }

    $now = time();
    $result = array(
        'success'     => true,
        'count'       => count($videos),
        'from_cache'  => false,
        'cached_at'   => date('Y-m-d H:i:s', $now),
        'cached_time' => date('H:i', $now),
        'ttl'         => $ttl,
        'videos'      => $videos
    );

    // 寫入快取檔案
    @file_put_contents($cache_file, json_encode($result, JSON_UNESCAPED_UNICODE));
    return $result;
}

// ─── 遞迴收集資料夾底下的所有影片並隨機挑選（具備多目錄公平分散採樣與 24 小時智慧快取）───
/**
 * 均勻分散採樣收集資料夾底下的影片候選清單（防止單一影集霸佔所有預覽配額，確保跨劇集真正隨機多樣性）
 *
 * @param string $folder_abs 資料夾絕對路徑
 * @param string $sub_rel_prefix 相對當前根目錄的子路徑前綴 (例如 "@韓劇/")
 * @param int $max_depth 遞迴搜尋深度限制
 * @param int $max_limit 最大收集影片數
 * @return array 包含 sub_path, title, filename, ext 的陣列清單
 */
function scan_folder_preview_candidates_raw($folder_abs, $sub_rel_prefix, $max_depth, $max_limit) {
    global $video_extensions;
    $candidates = array();
    if (!@is_dir($folder_abs) || $max_depth < 1 || $max_limit <= 0) {
        return $candidates;
    }

    $entries = @scandir($folder_abs);
    if ($entries === false) {
        return $candidates;
    }

    $direct_files = array();
    $sub_dirs = array();

    // 1. 先檢索當前層級的所有可播放影片與子資料夾
    foreach ($entries as $e_raw) {
        if ($e_raw === '.' || $e_raw === '..' || substr($e_raw, 0, 1) === '.') continue;
        $sub_abs = $folder_abs . '/' . $e_raw;
        if (@is_file($sub_abs)) {
            $e_utf8 = fs_to_utf8($e_raw);
            $info = get_filename_parts($e_utf8);
            $ext = strtolower($info['ext']);
            if (in_array($ext, $video_extensions)) {
                $direct_files[] = array(
                    'sub_path' => ($sub_rel_prefix !== '' ? ($sub_rel_prefix . '/') : '') . $e_utf8,
                    'title'    => $info['title'],
                    'filename' => $e_utf8,
                    'ext'      => strtoupper($ext)
                );
            }
        } elseif ($max_depth > 1 && @is_dir($sub_abs)) {
            $sub_dirs[] = $e_raw;
        }
    }

    // 隨機打散當前層級檔案
    if (!empty($direct_files)) {
        shuffle($direct_files);
    }

    // 情況 A：若無子目錄（單一劇集/葉節點資料夾），直接回傳隨機挑選的檔案
    if (empty($sub_dirs) || $max_depth <= 1) {
        return array_slice($direct_files, 0, $max_limit);
    }

    // 情況 B：包含多個子目錄，實施公平分攤（Round-Robin Fair Distribution）機制
    shuffle($sub_dirs);
    $num_sub = count($sub_dirs);

    // 若有直屬檔案，先分配適度份額（例如平均比例，上限不超過 4 部），保留配額給子資料夾
    if (!empty($direct_files)) {
        $direct_take = min(count($direct_files), max(1, (int)floor($max_limit / ($num_sub + 1))));
        for ($i = 0; $i < $direct_take; $i++) {
            $candidates[] = $direct_files[$i];
        }
    }

    // 第一階段（Pass 1 - 公平配額採樣）：
    // 依剩餘所需數量與未探索子目錄數動態計算公平配額，防止單一巨型影集（如 73 集的冰與火之歌）吃光所有配額
    $remaining_sub = $num_sub;
    foreach ($sub_dirs as $sub_raw) {
        $remaining_quota = $max_limit - count($candidates);
        if ($remaining_quota <= 0) break;

        $fair_quota = (int)ceil($remaining_quota / $remaining_sub);
        $remaining_sub--;

        $sub_utf8 = fs_to_utf8($sub_raw);
        $next_prefix = ($sub_rel_prefix !== '' ? ($sub_rel_prefix . '/') : '') . $sub_utf8;

        $sub_cands = scan_folder_preview_candidates_raw($folder_abs . '/' . $sub_raw, $next_prefix, $max_depth - 1, $fair_quota);
        foreach ($sub_cands as $cand) {
            $candidates[] = $cand;
        }
    }

    // 第二階段（Pass 2 - 候選不足補充）：
    // 若某些短劇集或小目錄檔案數量較少，導致總數未達標，再依序由其餘有剩餘檔案的子目錄均勻補齊
    if (count($candidates) < $max_limit) {
        $still_needed = $max_limit - count($candidates);
        $chosen_map = array();
        foreach ($candidates as $c) {
            $chosen_map[$c['sub_path']] = true;
        }

        shuffle($sub_dirs);
        foreach ($sub_dirs as $sub_raw) {
            if ($still_needed <= 0) break;
            $sub_utf8 = fs_to_utf8($sub_raw);
            $next_prefix = ($sub_rel_prefix !== '' ? ($sub_rel_prefix . '/') : '') . $sub_utf8;

            $more_cands = scan_folder_preview_candidates_raw($folder_abs . '/' . $sub_raw, $next_prefix, $max_depth - 1, $still_needed);
            foreach ($more_cands as $c) {
                if (!isset($chosen_map[$c['sub_path']])) {
                    $chosen_map[$c['sub_path']] = true;
                    $candidates[] = $c;
                    $still_needed--;
                    if ($still_needed <= 0) break;
                }
            }
        }

        // 若仍未滿且有剩餘直接影片，以直接影片補足
        if ($still_needed > 0 && !empty($direct_files)) {
            foreach ($direct_files as $df) {
                if (!isset($chosen_map[$df['sub_path']])) {
                    $chosen_map[$df['sub_path']] = true;
                    $candidates[] = $df;
                    $still_needed--;
                    if ($still_needed <= 0) break;
                }
            }
        }
    }

    // 整體候選池進行徹底隨機洗牌，消除目錄探索前後順序
    shuffle($candidates);
    return array_slice($candidates, 0, $max_limit);
}

/**
 * 取得資料夾預覽候選影片（具備記憶體靜態快取與磁碟 JSON 快取機制，24小時有效或目錄變更自動失效）
 *
 * @param string $folder_abs 資料夾絕對路徑
 * @param int $max_depth 遞迴深度限制（預設 3）
 * @param int $max_limit 最大收集影片數（預設 60 部）
 * @param bool $force 是否強制略過快取重新掃描（預設 false）
 * @return array 包含 sub_path 等資訊的候選影片清單
 */
function get_folder_preview_candidates_cached($folder_abs, $max_depth = 3, $max_limit = 60, $force = false) {
    static $mem_cache = array();

    if (!@is_dir($folder_abs)) {
        return array();
    }

    $folder_norm = rtrim(str_replace('\\', '/', $folder_abs), '/');
    // 引入 v2 標記，自動讓過往因貪婪遍歷產生偏差的舊版快取無痛失效
    $cache_key = md5('v2|' . $folder_norm . '|' . $max_depth . '|' . $max_limit);

    // 1. 若處於同一請求且非強制更新，直接從記憶體靜態快取回傳（零磁碟 I/O）
    if (!$force && isset($mem_cache[$cache_key])) {
        return $mem_cache[$cache_key];
    }

    $cache_dir = get_video_explorer_cache_dir();
    $cache_file = rtrim($cache_dir, '/\\') . '/nas_folder_preview_v2_' . $cache_key . '.json';
    $ttl = 86400; // 快取有效時間 24 小時（NAS 影音檔案擺放後極少頻繁變動）

    // 2. 檢查磁碟快取檔案
    if (!$force && @file_exists($cache_file)) {
        $file_mtime = (int)@filemtime($cache_file);
        $file_age = time() - $file_mtime;
        if ($file_age < $ttl) {
            $dir_mtime = (int)@filemtime($folder_abs);
            // 若目錄修改時間未大於快取產生時間，表示該目錄無新增/刪除直接子項目，快取有效
            if ($dir_mtime <= $file_mtime) {
                $cached_content = @file_get_contents($cache_file);
                if ($cached_content !== false && $cached_content !== '') {
                    $cached = @json_decode($cached_content, true);
                    if ($cached && isset($cached['items']) && is_array($cached['items'])) {
                        $mem_cache[$cache_key] = $cached['items'];
                        return $cached['items'];
                    }
                }
            }
        }
    }

    // 3. 磁碟快取未命中或強制更新：執行公平分散實體掃描
    $items = scan_folder_preview_candidates_raw($folder_abs, '', $max_depth, $max_limit);

    // 寫入記憶體快取
    $mem_cache[$cache_key] = $items;

    // 寫入磁碟快取
    $cache_data = array(
        'folder_abs' => $folder_norm,
        'max_depth'  => $max_depth,
        'dir_mtime'  => (int)@filemtime($folder_abs),
        'cached_at'  => time(),
        'count'      => count($items),
        'items'      => $items
    );
    @file_put_contents($cache_file, json_encode($cache_data, JSON_UNESCAPED_UNICODE));

    // 4. 定期機率性清理超過 7 天的過期快取檔案（1% 機率觸發，自動包含清理舊版快取檔）
    if (mt_rand(1, 100) === 1) {
        $old_caches = @glob(rtrim($cache_dir, '/\\') . '/nas_folder_preview_*.json');
        if ($old_caches && is_array($old_caches)) {
            $expire_time = time() - (86400 * 7);
            foreach ($old_caches as $cf) {
                if (@filemtime($cf) < $expire_time) {
                    @unlink($cf);
                }
            }
        }
    }

    return $items;
}

/**
 * 遞迴收集資料夾底下的所有可播放影片（限制深度與數量上限並封裝快取機制以保護 NAS CPU）
 *
 * @param string $folder_abs 資料夾絕對路徑
 * @param string $folder_rel 資料夾相對路徑
 * @param int $max_depth 遞迴搜尋深度限制（預設 3，支援如「連續劇/@韓劇/XXXX」等兩層子目錄深度）
 * @param int $max_limit 最大收集影片數（預設 60 部，避免巨量檔案耗盡記憶體）
 * @param bool $force 是否強制略過快取重新掃描（預設 false）
 * @return array 影片候選陣列
 */
function collect_videos_in_folder($folder_abs, $folder_rel, $max_depth = 3, $max_limit = 60, $force = false) {
    $items = get_folder_preview_candidates_cached($folder_abs, $max_depth, $max_limit, $force);
    $candidates = array();
    $prefix = ($folder_rel !== '' ? ($folder_rel . '/') : '');
    foreach ($items as $it) {
        $candidates[] = array(
            'rel_path' => $prefix . $it['sub_path'],
            'title'    => $it['title'],
            'filename' => $it['filename'],
            'ext'      => $it['ext']
        );
    }
    return $candidates;
}

/**
 * 尋找資料夾底下的所有影片並從中亂數隨機挑選一部作為代表預覽影片（支援快取）
 *
 * @param string $folder_abs 資料夾絕對路徑
 * @param string $folder_rel 資料夾相對路徑
 * @param int $max_depth 遞迴搜尋深度限制（預設 3，支援兩層子目錄）
 * @param bool $force 是否強制略過快取重新掃描（預設 false）
 * @return array|null 成功回傳包含 rel_path, title, filename, pool 的陣列，失敗回傳 null
 */
function find_sample_video_in_folder($folder_abs, $folder_rel, $max_depth = 3, $force = false) {
    $candidates = collect_videos_in_folder($folder_abs, $folder_rel, $max_depth, 60, $force);
    if (empty($candidates)) {
        return null;
    }

    // 隨機打散或以 mt_rand 隨機挑選一部影片
    $rand_idx = mt_rand(0, count($candidates) - 1);
    $selected = $candidates[$rand_idx];

    // 同時附帶最多 20 部候選清單（供前端隨機預覽池使用）
    $pool_sample = $candidates;
    shuffle($pool_sample);
    $selected['pool'] = array_slice($pool_sample, 0, 20);
    $selected['total_found'] = count($candidates);

    return $selected;
}

// ─── 單層目錄動態瀏覽：僅讀取當前資料夾的子資料夾與影片檔案 ────────────────
function browse_directory($base_dir, $rel_path = '', $force = false) {
    global $allowed_extensions, $audio_extensions, $video_dirs;
    $dirs = is_array($base_dir) ? $base_dir : ((isset($video_dirs) && is_array($video_dirs)) ? $video_dirs : array($base_dir));

    $clean_rel = str_replace(array('../', '..\\', "\0"), '', $rel_path);
    $clean_rel = trim($clean_rel, '/\\');

    $is_multi_root = (count($dirs) > 1);

    // ── 情況一：多資料庫模式下瀏覽最頂層根目錄 (全部影音庫) ──
    if ($is_multi_root && $clean_rel === '') {
        $subfolders = array();
        $any_exists = false;

        foreach ($dirs as $d) {
            $exists = @is_dir($d);
            if ($exists) $any_exists = true;
            $d_name = basename($d);
            $sample_video = $exists ? find_sample_video_in_folder($d, $d_name, 3, $force) : null;
            $subfolders[] = array(
                'name'         => $d_name,
                'rel_path'     => $d_name,
                'sample_video' => $sample_video
            );
        }

        return array(
            'success'        => true,
            'dir_exists'     => $any_exists,
            'base_dir'       => implode(' · ', $dirs),
            'current_path'   => '',
            'parent_path'    => null,
            'breadcrumbs'    => array(array('name' => '全部影音庫', 'path' => '')),
            'subfolders'     => $subfolders,
            'videos'         => array(),
            'video_count'    => 0,
            'folder_count'   => count($subfolders),
            'total_size'     => 0,
            'total_size_str' => '0 B'
        );
    }

    // ── 情況二：確定具體的實體資料夾 ──
    $target_dir = '';
    $sub_rel = $clean_rel;

    if ($is_multi_root) {
        foreach ($dirs as $d) {
            $d_name = basename($d);
            if ($clean_rel === $d_name) {
                $target_dir = $d;
                $sub_rel = '';
                break;
            }
            if (strpos($clean_rel, $d_name . '/') === 0) {
                $target_dir = $d;
                $sub_rel = substr($clean_rel, strlen($d_name . '/'));
                break;
            }
        }
        if ($target_dir === '') {
            $target_dir = $dirs[0];
        }
    } else {
        $target_dir = $dirs[0];
    }

    $real_target = safe_path_resolve_single($target_dir, $sub_rel);

    $result = array(
        'success'        => false,
        'dir_exists'     => ($real_target && @is_dir($real_target)),
        'base_dir'       => $target_dir,
        'current_path'   => $clean_rel,
        'parent_path'    => null,
        'breadcrumbs'    => array(),
        'subfolders'     => array(),
        'videos'         => array(),
        'video_count'    => 0,
        'folder_count'   => 0,
        'total_size'     => 0,
        'total_size_str' => '0 B'
    );

    if (!$real_target || !@is_dir($real_target)) {
        return $result;
    }

    $result['success'] = true;

    // 建立麵包屑導航路徑
    if ($is_multi_root) {
        $breadcrumbs = array(
            array('name' => '全部影音庫', 'path' => '')
        );
        $parts = explode('/', $clean_rel);
        $acc_path = '';
        foreach ($parts as $p) {
            $acc_path = ($acc_path === '' ? $p : $acc_path . '/' . $p);
            $breadcrumbs[] = array(
                'name' => $p,
                'path' => $acc_path
            );
        }
        $parent_parts = array_slice($parts, 0, -1);
        $result['parent_path'] = implode('/', $parent_parts);
    } else {
        $breadcrumbs = array(
            array('name' => '全部影片', 'path' => '')
        );
        if ($clean_rel !== '') {
            $parts = explode('/', $clean_rel);
            $acc_path = '';
            foreach ($parts as $p) {
                $acc_path = ($acc_path === '' ? $p : $acc_path . '/' . $p);
                $breadcrumbs[] = array(
                    'name' => $p,
                    'path' => $acc_path
                );
            }
            $parent_parts = array_slice($parts, 0, -1);
            $result['parent_path'] = implode('/', $parent_parts);
        }
    }
    $result['breadcrumbs'] = $breadcrumbs;

    $items = @scandir($real_target);
    if ($items === false) return $result;

    $subfolders = array();
    $videos = array();
    $total_bytes = 0.0;

    foreach ($items as $item_raw) {
        if ($item_raw === '.' || $item_raw === '..' || substr($item_raw, 0, 1) === '.') continue;
        $item = fs_to_utf8($item_raw);
        $item_path = $real_target . '/' . $item_raw;
        $item_rel = ($clean_rel !== '' ? $clean_rel . '/' : '') . $item;

        if (@is_dir($item_path)) {
            $sample_video = find_sample_video_in_folder($item_path, $item_rel, 3, $force);
            $subfolders[] = array(
                'name'         => $item,
                'rel_path'     => $item_rel,
                'sample_video' => $sample_video
            );
        } elseif (@is_file($item_path)) {
            $info = get_filename_parts($item);
            $ext = $info['ext'];
            if (in_array($ext, $allowed_extensions)) {
                $size = get_real_filesize($item_path);
                $mtime = @filemtime($item_path);
                $is_audio = in_array($ext, $audio_extensions);

                $videos[] = array(
                    'id'          => md5($item_rel),
                    'rel_path'    => $item_rel,
                    'filename'    => $item,
                    'ext'         => strtoupper($ext),
                    'title'       => $info['title'],
                    'size'        => $size,
                    'size_str'    => format_bytes($size),
                    'mtime'       => $mtime,
                    'mtime_str'   => $mtime ? date('Y-m-d H:i', $mtime) : '-',
                    'time_ago'    => get_time_ago($mtime),
                    'is_audio'    => $is_audio,
                    'is_new'      => ($mtime && $mtime > (time() - 86400 * 14))
                );
                $total_bytes += $size;
            }
        }
    }

    // 自然語意排序資料夾與影片
    usort($subfolders, function($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });
    usort($videos, function($a, $b) {
        return strnatcasecmp($a['filename'], $b['filename']);
    });

    $result['subfolders']     = $subfolders;
    $result['videos']         = $videos;
    $result['folder_count']   = count($subfolders);
    $result['video_count']    = count($videos);
    $result['total_size']     = $total_bytes;
    $result['total_size_str'] = format_bytes($total_bytes);

    return $result;
}

// ─── 字幕檔案偵測：掃描與指定影片同目錄、同主檔名的字幕檔 ─────────────────
/**
 * 偵測影片旁的字幕檔，回傳可用字幕清單
 * 規則：主檔名相同（不含副檔名），副檔名為字幕格式
 * 支援語言標記：video.zh.srt / video.en.vtt / video.繁中.ass
 *
 * @param string $base_dir    影音根目錄
 * @param string $rel_path    影片的相對路徑 (含副檔名)
 * @return array              字幕清單，每項含 label, ext, rel_path, lang
 */
function detect_subtitles($base_dir, $rel_path) {
    global $subtitle_extensions;

    $real_video = safe_path_resolve($base_dir, $rel_path);
    if (!$real_video || !is_file($real_video)) {
        return array();
    }

    $video_dir_path = dirname($real_video);
    $video_filename = fs_to_utf8(basename($real_video));
    $video_info = get_filename_parts($video_filename);
    $video_stem  = $video_info['title'];

    $rel_dir = dirname($rel_path);
    if ($rel_dir === '.') {
        $rel_dir = '';
    }

    $sub_exts = (isset($subtitle_extensions) && is_array($subtitle_extensions)) ? $subtitle_extensions : array('vtt', 'srt', 'ass', 'ssa', 'sub');

    $subtitles = array();

    foreach ($sub_exts as $sub_ext) {
        // 1. 完全相符：video.vtt
        $exact_name = $video_stem . '.' . $sub_ext;
        $exact_path = safe_fs_path($video_dir_path . '/' . $exact_name);
        if (@is_file($exact_path)) {
            $sub_rel = ($rel_dir !== '' ? $rel_dir . '/' : '') . $exact_name;
            $subtitles[] = array(
                'label'    => strtoupper($sub_ext),
                'ext'      => $sub_ext,
                'lang'     => '',
                'rel_path' => $sub_rel
            );
        }

        // 2. 語言標記：video.zh.srt / video.en.vtt / video.繁中.ass
        $dir_items = @scandir($video_dir_path);
        if ($dir_items) {
            foreach ($dir_items as $f_raw) {
                if ($f_raw === '.' || $f_raw === '..') continue;
                $f = fs_to_utf8($f_raw);
                $prefix = $video_stem . '.';
                $suffix = '.' . $sub_ext;
                if (substr($f, 0, strlen($prefix)) !== $prefix) continue;
                if (strtolower(substr($f, -strlen($suffix))) !== $suffix) continue;
                if (strtolower($f) === strtolower($exact_name)) continue;
                $lang_part = substr($f, strlen($prefix), strlen($f) - strlen($prefix) - strlen($suffix));
                if ($lang_part === '') continue;
                $sub_rel = ($rel_dir !== '' ? $rel_dir . '/' : '') . $f;
                $already = false;
                foreach ($subtitles as $s) {
                    if ($s['rel_path'] === $sub_rel) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $subtitles[] = array(
                        'label'    => $lang_part . ' (' . strtoupper($sub_ext) . ')',
                        'ext'      => $sub_ext,
                        'lang'     => $lang_part,
                        'rel_path' => $sub_rel
                    );
                }
            }
        }
    }

    return $subtitles;
}

// ─── 資料夾隨機影片抽取：尋找指定資料夾底下任一支影片作為預覽標的 ───────────
/**
 * 隨機取得指定資料夾底下的影片檔案資訊
 * 優先檢查該目錄直屬檔案；若無直屬影片則受控探測子目錄（限制深度與數量以保護低功耗 NAS）
 *
 * @param string|array $base_dir 影音基準根目錄或目錄陣列
 * @param string $folder_rel     目標資料夾相對路徑
 * @return array 包含隨機抽取的影片資訊或失敗原因
 */
function get_random_video_in_folder($base_dir, $folder_rel, $force = false) {
    global $video_extensions, $video_dirs;

    $dirs = is_array($base_dir) ? $base_dir : ((isset($video_dirs) && is_array($video_dirs)) ? $video_dirs : array($base_dir));
    $clean_rel = str_replace(array('../', '..\\', "\0"), '', $folder_rel);
    $clean_rel = trim($clean_rel, '/\\');

    $real_folder = safe_path_resolve($dirs[0], $clean_rel);
    if (!$real_folder || !@is_dir($real_folder)) {
        return array('success' => false, 'error' => '資料夾不存在或無法存取', 'folder' => $clean_rel);
    }

    // 0. 優先使用資料夾預覽快取池隨機抽取（支援 2 層子目錄深度，享受快取零磁碟 I/O）
    $sample = find_sample_video_in_folder($real_folder, $clean_rel, 3, $force);
    if ($sample && !empty($sample['rel_path'])) {
        return array(
            'success' => true,
            'video'   => array(
                'rel_path' => $sample['rel_path'],
                'title'    => isset($sample['title']) ? $sample['title'] : '',
                'filename' => isset($sample['filename']) ? $sample['filename'] : basename($sample['rel_path']),
                'ext'      => isset($sample['ext']) ? $sample['ext'] : ''
            ),
            'folder'  => $clean_rel
        );
    }

    // 1. 優先階層：快速檢查該目錄直屬影片（耗時 < 1ms，零遞迴開銷）
    $direct_candidates = array();
    $has_subdirs = false;
    $items = @scandir($real_folder);

    if ($items !== false) {
        foreach ($items as $it) {
            if ($it === '.' || $it === '..' || substr($it, 0, 1) === '.') continue;
            $it_utf8 = fs_to_utf8($it);
            $full_p = $real_folder . '/' . $it;
            if (@is_file($full_p)) {
                $info = get_filename_parts($it_utf8);
                if (!empty($info['ext']) && in_array(strtolower($info['ext']), $video_extensions)) {
                    $direct_candidates[] = array(
                        'rel_path' => ($clean_rel !== '' ? $clean_rel . '/' : '') . $it_utf8,
                        'title'    => $info['title'],
                        'filename' => $it_utf8,
                        'ext'      => strtoupper($info['ext'])
                    );
                }
            } elseif (@is_dir($full_p)) {
                $has_subdirs = true;
            }
        }
    }

    if (!empty($direct_candidates)) {
        $pick = $direct_candidates[array_rand($direct_candidates)];
        return array(
            'success' => true,
            'video'   => $pick,
            'folder'  => $clean_rel
        );
    }

    // 2. 次要階層：若直屬目錄無影片但有子資料夾，進行受控抽樣搜尋
    if ($has_subdirs) {
        // Linux/NAS 環境下使用 find 快速截取前 30 個結果
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $name_conds = array();
            foreach ($video_extensions as $ext) {
                $name_conds[] = '-name "*.' . $ext . '"';
            }
            $name_str = implode(' -o ', $name_conds);
            $cmd = 'find ' . safe_escapeshellarg($real_folder) . ' -type f \( ' . $name_str . ' \) 2>/dev/null | head -n 30';
            $out = @shell_exec($cmd);

            if (!empty($out)) {
                $lines = explode("\n", trim($out));
                $candidates = array();
                $norm_base = rtrim(str_replace('\\', '/', $real_folder), '/');
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $norm_line = str_replace('\\', '/', $line);
                    $sub_rel = '';
                    if (strpos($norm_line, $norm_base . '/') === 0) {
                        $sub_rel = substr($norm_line, strlen($norm_base . '/'));
                    } else {
                        $sub_rel = basename($line);
                    }
                    $sub_rel_utf8 = fs_to_utf8($sub_rel);
                    $f_name = basename($sub_rel_utf8);
                    $info = get_filename_parts($f_name);
                    $candidates[] = array(
                        'rel_path' => ($clean_rel !== '' ? $clean_rel . '/' : '') . $sub_rel_utf8,
                        'title'    => $info['title'],
                        'filename' => $f_name,
                        'ext'      => strtoupper($info['ext'])
                    );
                }
                if (!empty($candidates)) {
                    $pick = $candidates[array_rand($candidates)];
                    return array(
                        'success' => true,
                        'video'   => $pick,
                        'folder'  => $clean_rel
                    );
                }
            }
        }

        // Windows 環境或 Linux find 未命中之 PHP 回退抽樣搜尋
        $recursive_candidates = array();
        find_random_video_fallback($real_folder, $real_folder, $clean_rel, $recursive_candidates, 30, 0);
        if (!empty($recursive_candidates)) {
            $pick = $recursive_candidates[array_rand($recursive_candidates)];
            return array(
                'success' => true,
                'video'   => $pick,
                'folder'  => $clean_rel
            );
        }
    }

    return array(
        'success' => false,
        'error'   => '此資料夾內暫無可預覽影片',
        'folder'  => $clean_rel
    );
}

/**
 * 跨平台遞迴隨機抽樣輔助函式（深度上限 5，候選上限 30，避免 NAS 效能損耗）
 */
function find_random_video_fallback($dir, $root_dir, $clean_rel, &$candidates, $max, $depth) {
    global $video_extensions;
    if ($depth > 5 || count($candidates) >= $max) return;

    $items = @scandir($dir);
    if ($items === false) return;

    shuffle($items);
    $norm_root = rtrim(str_replace('\\', '/', $root_dir), '/');

    foreach ($items as $it) {
        if ($it === '.' || $it === '..' || substr($it, 0, 1) === '.') continue;
        $full = $dir . '/' . $it;
        if (@is_file($full)) {
            $info = get_filename_parts(fs_to_utf8($it));
            if (!empty($info['ext']) && in_array(strtolower($info['ext']), $video_extensions)) {
                $norm_full = str_replace('\\', '/', $full);
                $inner = (strpos($norm_full, $norm_root . '/') === 0) ? substr($norm_full, strlen($norm_root . '/')) : $it;
                $inner_utf8 = fs_to_utf8($inner);
                $candidates[] = array(
                    'rel_path' => ($clean_rel !== '' ? $clean_rel . '/' : '') . ltrim($inner_utf8, '/'),
                    'title'    => $info['title'],
                    'filename' => fs_to_utf8($it),
                    'ext'      => strtoupper($info['ext'])
                );
                if (count($candidates) >= $max) return;
            }
        } elseif (@is_dir($full)) {
            find_random_video_fallback($full, $root_dir, $clean_rel, $candidates, $max, $depth + 1);
            if (count($candidates) >= $max) return;
        }
    }
}

