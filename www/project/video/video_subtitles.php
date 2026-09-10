<?php
// ═══════════════════════════════════════════════════════════════════════════
//  NAS 多媒體影音特區 - 純 PHP 內嵌字幕解析與提取引擎 (video_subtitles.php)
//  - 嚴格相容 PHP 5.6 (ASUSTOR AS-202TE ADM 3.5 運行環境)
//  - 包含 EBML (MKV/WebM) 與 ISOBMFF (MP4/MOV) 純二進位文字字幕提取 (WebVTT)
//  - 包含 DVD SPU / DCSQ 二進位圖形字幕解碼器與 JSON Cues 產出
//  - 支援系統 FFmpeg / ffprobe 輔助探測回退機制
// ═══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/video_core.php';

// ─── 字幕快取核心管理員（嚴格相容 PHP 5.6，全快取集中於 cache 資料夾） ───────────
/**
 * 取得字幕專用快取資料夾 (預設為 __DIR__ . '/cache')
 * 若不存在則自動建立，若不可寫入則回退至系統暫存目錄
 */
function get_subtitles_cache_dir() {
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
 * 產生基於影片完整檔案特徵（路徑、修改時間、真實檔案大小）的唯一快取識別碼
 * 當影片來源檔案未變更時，識別碼保持一致；若檔案被替換或更新，則自動失效重整
 */
function get_video_subtitle_cache_key($filepath) {
    $mtime = (int)@filemtime($filepath);
    $fsize = (float)get_real_filesize($filepath);
    return md5($filepath) . '_' . $mtime . '_' . sprintf('%.0f', $fsize);
}

/**
 * 清除特定影片過期的舊版字幕快取檔案（當影片檔案被修改替換時自動觸發）
 */
function cleanup_old_video_subtitle_cache($filepath, $current_cache_key) {
    $cache_dir = get_subtitles_cache_dir();
    $file_hash = md5($filepath);
    $pattern = rtrim($cache_dir, '/\\') . '/nas_sub_*' . $file_hash . '*';
    $files = @glob($pattern);
    if ($files && is_array($files)) {
        foreach ($files as $f) {
            if (strpos($f, $current_cache_key) === false) {
                @unlink($f);
            }
        }
    }
}

// ─── 純 PHP EBML 解析器：偵測 MKV/WebM 內嵌字幕 ──────────────────────────
// 僅讀取最多 2MB，零外部依賴，相容 PHP 5.6 / 32-bit NAS (Intel Atom)

// 從檔案指標讀取 EBML Element ID (1~4 bytes)
function _ebml_read_id($fp) {
    $c = @fread($fp, 1);
    if (!$c || !strlen($c)) return false;
    $b = ord($c);
    if ($b & 0x80) return $b;
    if ($b & 0x40) { $r = @fread($fp, 1); return $r ? (($b << 8) | ord($r)) : false; }
    if ($b & 0x20) { $r = @fread($fp, 2); return (strlen($r) === 2) ? (($b << 16) | (ord($r[0]) << 8) | ord($r[1])) : false; }
    if ($b & 0x10) { $r = @fread($fp, 3); return (strlen($r) === 3) ? (($b << 24) | (ord($r[0]) << 16) | (ord($r[1]) << 8) | ord($r[2])) : false; }
    return false;
}

// 從檔案指標讀取 EBML 資料大小 (VINT 1~8 位元組)；-1=未知大小，-2=EOF/錯誤
function _ebml_read_size($fp) {
    $c = @fread($fp, 1);
    if (!$c || !strlen($c)) return -2;
    $b = ord($c);
    if (!$b) return -2;

    $num_extra = 0;
    $mask = 0;
    for ($i = 0; $i < 8; $i++) {
        if ($b & (0x80 >> $i)) {
            $num_extra = $i;
            $mask = (0x80 >> $i) - 1;
            break;
        }
    }

    $raw_bytes = array($b & $mask);
    if ($num_extra > 0) {
        $r = @fread($fp, $num_extra);
        if (strlen($r) < $num_extra) return -2;
        for ($j = 0; $j < $num_extra; $j++) {
            $raw_bytes[] = ord($r[$j]);
        }
    }

    $is_unknown = true;
    if ($raw_bytes[0] !== $mask) {
        $is_unknown = false;
    } else {
        for ($j = 1; $j <= $num_extra; $j++) {
            if ($raw_bytes[$j] !== 0xFF) {
                $is_unknown = false;
                break;
            }
        }
    }
    if ($is_unknown) return -1;

    $val = 0.0;
    $total = count($raw_bytes);
    for ($k = 0; $k < $total; $k++) {
        $val = ($val * 256.0) + (float)$raw_bytes[$k];
    }
    if ($val <= 2147483647.0) {
        return (int)$val;
    }
    return $val;
}

// 從字串緩衝讀取 EBML ID (pass-by-ref $pos)
function _ebml_buf_id($buf, &$pos) {
    $pos = (int)$pos;
    $len = strlen($buf);
    if ($pos >= $len) return false;
    $b = ord($buf[$pos]);
    if ($b & 0x80) { $pos++; return $b; }
    if ($b & 0x40) { if ($pos + 1 >= $len) return false; $id = ($b << 8) | ord($buf[$pos + 1]); $pos += 2; return $id; }
    if ($b & 0x20) { if ($pos + 2 >= $len) return false; $id = ($b << 16) | (ord($buf[$pos + 1]) << 8) | ord($buf[$pos + 2]); $pos += 3; return $id; }
    if ($b & 0x10) { if ($pos + 3 >= $len) return false; $id = ($b << 24) | (ord($buf[$pos + 1]) << 16) | (ord($buf[$pos + 2]) << 8) | ord($buf[$pos + 3]); $pos += 4; return $id; }
    return false;
}

// 從字串緩衝讀取 EBML 大小 (pass-by-ref $pos, 支援 1~8 位元組 VINT)
function _ebml_buf_size($buf, &$pos) {
    $pos = (int)$pos;
    $len = strlen($buf);
    if ($pos >= $len) return -2;
    $b = ord($buf[$pos++]);
    if (!$b) return -2;

    $num_extra = 0;
    $mask = 0;
    for ($i = 0; $i < 8; $i++) {
        if ($b & (0x80 >> $i)) {
            $num_extra = $i;
            $mask = (0x80 >> $i) - 1;
            break;
        }
    }

    if ($pos + $num_extra > $len) return -2;

    $raw_bytes = array($b & $mask);
    for ($j = 0; $j < $num_extra; $j++) {
        $raw_bytes[] = ord($buf[$pos++]);
    }

    $is_unknown = true;
    if ($raw_bytes[0] !== $mask) {
        $is_unknown = false;
    } else {
        for ($j = 1; $j <= $num_extra; $j++) {
            if ($raw_bytes[$j] !== 0xFF) {
                $is_unknown = false;
                break;
            }
        }
    }
    if ($is_unknown) return -1;

    $val = 0.0;
    $total = count($raw_bytes);
    for ($k = 0; $k < $total; $k++) {
        $val = ($val * 256.0) + (float)$raw_bytes[$k];
    }
    if ($val <= 2147483647.0) {
        return (int)$val;
    }
    return $val;
}

// 大端位元組串轉整數
function _ebml_uint($data, $len) {
    $v = 0;
    for ($i = 0; $i < $len && $i < strlen($data); $i++) { $v = ($v << 8) | ord($data[$i]); }
    return $v;
}

// CodecID → 友善名稱 + 是否為圖形字幕
function ebml_codec_label($codec_id) {
    $c = rtrim(trim($codec_id), "\0");
    $m = array(
        'S_TEXT/UTF8'   => array('SRT',    false),
        'S_TEXT/ASS'    => array('ASS',    false),
        'S_TEXT/SSA'    => array('SSA',    false),
        'S_SSA'         => array('SSA',    false),
        'S_ASS'         => array('ASS',    false),
        'S_TEXT/WEBVTT' => array('WebVTT', false),
        'S_TEXT/USF'    => array('USF',    false),
        'S_KATE'        => array('Kate',   false),
        'S_HDMV/TEXTST' => array('TextST', false),
        'S_VOBSUB'      => array('VobSub', true),
        'S_HDMV/PGS'    => array('PGS',   true),
        'S_DVBSUB'      => array('DVBSub', true),
        'S_ARIBSUB'     => array('ARIB',   true),
    );
    if (isset($m[$c])) return array('label' => $m[$c][0], 'is_image' => $m[$c][1]);
    $label = (strpos($c, 'S_') === 0) ? substr($c, 2) : ($c ?: '未知');
    return array('label' => $label, 'is_image' => false);
}

// 語言碼 → 繁中友善名稱
function ebml_lang_label($lang) {
    $l = strtolower(trim(rtrim($lang, "\0")));
    $m = array(
        'zh' => '中文', 'zho' => '中文', 'chi' => '中文',
        'zh-tw' => '繁中', 'zh-hant' => '繁中', 'zh-hk' => '繁中(港)',
        'zh-hans' => '簡中', 'zh-cn' => '簡中',
        'en' => '英文', 'eng' => '英文',
        'ja' => '日文', 'jpn' => '日文',
        'ko' => '韓文', 'kor' => '韓文',
        'fr' => '法文', 'fra' => '法文', 'fre' => '法文',
        'de' => '德文', 'deu' => '德文', 'ger' => '德文',
        'es' => '西語', 'spa' => '西語',
        'it' => '義語', 'ita' => '義語',
        'pt' => '葡語', 'por' => '葡語',
        'ru' => '俄文', 'rus' => '俄文',
        'ar' => '阿語', 'ara' => '阿語',
        'th' => '泰文', 'tha' => '泰文',
        'vi' => '越語', 'vie' => '越語',
        'id' => '印尼語', 'ind' => '印尼語',
        'ms' => '馬來語', 'msa' => '馬來語',
        'und' => '不明', 'mul' => '多語', '' => '不明',
    );
    return isset($m[$l]) ? $m[$l] : (strtoupper($l) ?: '不明');
}

/**
 * 純 PHP EBML 解析器：偵測 MKV / WebM 內嵌字幕軌道
 * 最多掃描前 2MB，零外部依賴，PHP 5.6 / 32-bit 相容
 *
 * EBML 關鍵 Element ID（皆 < 2^31，32-bit PHP 安全）:
 *   0x1A45DFA3 = EBML Header
 *   0x18538067 = Segment
 *   0x1654AE6B = Tracks
 *   0x1F43B675 = Cluster（出現即停止，代表已過 Tracks）
 *   0xAE       = TrackEntry
 *   0x83       = TrackType  (0x11=字幕)
 *   0x22B59C   = Language   (ISO 639-2)
 *   0x22B59D   = LanguageBCP47
 *   0x536E     = Name
 *   0x86       = CodecID
 */
function parse_mkv_subtitles($filepath) {
    $parts = get_filename_parts(basename($filepath));
    if (!in_array($parts['ext'], array('mkv', 'webm', 'mka', 'mk3d'))) return array();
    $fp = @fopen($filepath, 'rb');
    if (!$fp) return array();

    $MAX = 2 * 1024 * 1024;
    $subtitles = array();

    // 確認 EBML 魔術頭 (0x1A45DFA3)
    $magic = @fread($fp, 4);
    if (strlen($magic) < 4 || $magic !== "\x1A\x45\xDF\xA3") { @fclose($fp); return array(); }
    @fseek($fp, 0, SEEK_SET);

    $tracks_buf = null;
    $guard = 0;

    while (@ftell($fp) < $MAX && $guard++ < 8000) {
        $id   = _ebml_read_id($fp);   if ($id === false) break;
        $size = _ebml_read_size($fp); if ($size === -2)  break;

        // 0x1654AE6B = Tracks
        if ($id === 0x1654AE6B) {
            if ($size > 0 && $size <= $MAX) {
                $tracks_buf = @fread($fp, $size);
            } elseif ($size === -1) {
                $tracks_buf = @fread($fp, 131072); // 未知大小：讀 128KB
            }
            break;
        }
        // 0x1F43B675 = Cluster → Tracks 已過，停止
        if ($id === 0x1F43B675) break;
        // 0x18538067 = Segment：未知大小容器，直接潛入（繼續讀）
        if ($id === 0x18538067 || $size === -1) continue;
        // 其餘元素：跳過
        if ($size > 0) @fseek($fp, $size, SEEK_CUR);
    }
    @fclose($fp);

    if ($tracks_buf === null || strlen($tracks_buf) === 0) return array();

    // ── 解析 Tracks 緩衝區 ──
    $pos = 0; $blen = strlen($tracks_buf); $g2 = 0;
    while ($pos < $blen && $g2++ < 2000) {
        $id   = _ebml_buf_id($tracks_buf, $pos);   if ($id === false) break;
        $size = _ebml_buf_size($tracks_buf, $pos);  if ($size === -2)  break;

        if ($id !== 0xAE || $size <= 0) { // 非 TrackEntry
            if ($size > 0 && $size !== -1) $pos += $size; else break;
            continue;
        }

        // ── 解析 TrackEntry 子元素 ──
        $entry = substr($tracks_buf, $pos, $size); $pos += $size;
        $ep = 0; $elen = strlen($entry);
        $track_type = 0; $track_num = 0; $lang = ''; $name = ''; $codec_id = ''; $g3 = 0;

        while ($ep < $elen && $g3++ < 500) {
            $eid   = _ebml_buf_id($entry, $ep);    if ($eid === false) break;
            $esize = _ebml_buf_size($entry, $ep);  if ($esize === -2)  break;
            if ($esize === -1) break; // 不處理未知大小子元素

            if ($esize > 0 && $esize <= $elen) {
                $edata = substr($entry, $ep, $esize); $ep += $esize;
                if      ($eid === 0x83)                  { $track_type = _ebml_uint($edata, min($esize, 4)); }
                elseif  ($eid === 0xD7)                  { $track_num  = _ebml_uint($edata, min($esize, 4)); }
                elseif  ($eid === 0x22B59C || $eid === 0x22B59D) { $lang     = rtrim($edata, "\0"); }
                elseif  ($eid === 0x536E)                { $name     = rtrim($edata, "\0"); }
                elseif  ($eid === 0x86)                  { $codec_id = rtrim($edata, "\0"); }
                elseif  ($eid === 0x63A2)                { $codec_priv = $edata; }
            } elseif ($esize === 0) {
                // 空元素，跳過
            } else {
                $ep += $esize;
            }
        }

        // TrackType 0x11 = 字幕
        if ($track_type === 0x11 && $codec_id !== '') {
            $ci = ebml_codec_label($codec_id);
            $c_lower = strtolower($codec_id);
            $is_graphic_dvd = (strpos($c_lower, 'vobsub') !== false || strpos($c_lower, 'dvd') !== false);
            $subtitles[] = array(
                'sub_index'   => count($subtitles), // 字幕串流序號 (0:s:0, 0:s:1...)
                'track_num'   => $track_num,        // MKV TrackNumber (0xD7)
                'lang'        => $lang,
                'lang_label'  => ebml_lang_label($lang),
                'name'        => $name,
                'codec'       => $codec_id,
                'codec_label' => $ci['label'],
                'is_image'    => $ci['is_image'],
                'is_graphic'  => $is_graphic_dvd,
                'can_render'  => $is_graphic_dvd,
            );
        }
    }
    return $subtitles;
}

/**
 * 尋找系統中可用的 ffmpeg 執行檔路徑
 */
function find_ffmpeg_binary() {
    static $ffmpeg = null;
    if ($ffmpeg !== null) return $ffmpeg;

    $candidates = array(
        '/usr/builtin/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/usr/bin/ffmpeg',
        'ffmpeg',
        '/usr/local/AppCentral/ffmpeg/bin/ffmpeg',
        '/volume1/.@plugins/AppCentral/ffmpeg/bin/ffmpeg',
    );

    if (is_dir('/volume1/.@plugins/AppCentral')) {
        $candidates[] = '/volume1/.@plugins/AppCentral/ffmpeg/bin/ffmpeg';
    }

    foreach ($candidates as $c) {
        if (strpos($c, '/') === false) {
            if (DIRECTORY_SEPARATOR !== '\\') {
                $chk = @shell_exec('which ' . escapeshellarg($c) . ' 2>/dev/null');
                if (!empty($chk) && trim($chk) !== '') {
                    $ffmpeg = trim($chk);
                    return $ffmpeg;
                }
            } else {
                $chk = @shell_exec('where ' . escapeshellarg($c) . ' 2>nul');
                if (!empty($chk) && trim($chk) !== '') {
                    $lines = explode("\n", trim($chk));
                    $ffmpeg = trim($lines[0]);
                    return $ffmpeg;
                }
            }
        } else {
            if (@file_exists($c) && @is_executable($c)) {
                $ffmpeg = $c;
                return $ffmpeg;
            }
        }
    }

    $ffmpeg = false;
    return false;
}

/**
 * 毫秒轉 WebVTT 時間戳格式 (00:00:00.000)
 */
function _ms_to_vtt_time($ms) {
    $ms = (int)$ms;
    $hours = floor($ms / 3600000);
    $rem = $ms % 3600000;
    $mins = floor($rem / 60000);
    $rem2 = $rem % 60000;
    $secs = floor($rem2 / 1000);
    $milli = $rem2 % 1000;
    return sprintf('%02d:%02d:%02d.%03d', $hours, $mins, $secs, $milli);
}

/**
 * 純 PHP EBML 字幕提取回退引擎（零外部依賴）
 */
function extract_mkv_subtitles_pure_php($filepath, $target_track_num, $sub_index = 0) {
    if ($target_track_num <= 0) {
        $subs = parse_embedded_subtitles($filepath);
        if (isset($subs[$sub_index]['track_num'])) {
            $target_track_num = (int)$subs[$sub_index]['track_num'];
        }
    }
    if ($target_track_num <= 0) return false;

    $fp = @fopen($filepath, 'rb');
    if (!$fp) return false;

    $cues = array();
    $cluster_time = 0;
    $guard = 0;
    $max_scan = 300 * 1024 * 1024; // 最多掃描前 300MB

    while (!feof($fp) && @ftell($fp) < $max_scan && $guard++ < 25000) {
        $id = _ebml_read_id($fp);
        if ($id === false) break;
        $size = _ebml_read_size($fp);
        if ($size === -2) break;

        if ($id === 0x1F43B675 || $id === 0x18538067 || $size === -1) {
            continue; // Cluster 或 Segment 容器，深入讀取
        }
        if ($id === 0xE7) {
            $data = ($size > 0 && $size <= 8) ? @fread($fp, $size) : '';
            $cluster_time = _ebml_uint($data, strlen($data));
            continue;
        }
        if ($id === 0xA3 && $size > 4 && $size < 65536) {
            $block_data = @fread($fp, $size);
            $pos = 0;
            $b_len = strlen($block_data);
            $t_num = _ebml_buf_size($block_data, $pos);
            if ((int)$t_num === (int)$target_track_num && $pos + 3 <= $b_len) {
                $time_hi = ord($block_data[$pos++]);
                $time_lo = ord($block_data[$pos++]);
                $rel_time = ($time_hi << 8) | $time_lo;
                if ($rel_time & 0x8000) {
                    $rel_time = -((~$rel_time & 0xFFFF) + 1);
                }
                $pos++; // flags
                $payload = substr($block_data, $pos);
                $start_ms = $cluster_time + $rel_time;
                if ($start_ms < 0) $start_ms = 0;
                $text = trim(rtrim($payload, "\0"));

                if (strpos($text, ',,') !== false) {
                    $parts = explode(',,', $text, 2);
                    if (isset($parts[1])) $text = trim($parts[1]);
                }

                if ($text !== '') {
                    $cues[] = array('start' => $start_ms, 'text' => $text);
                }
            }
            continue;
        }

        if ($size > 0) {
            @fseek($fp, $size, SEEK_CUR);
        }
    }
    @fclose($fp);

    if (empty($cues)) return false;

    usort($cues, function($a, $b) { return $a['start'] - $b['start']; });

    $vtt = "WEBVTT\n\n";
    $count = count($cues);
    for ($i = 0; $i < $count; $i++) {
        $c = $cues[$i];
        $start = $c['start'];
        $end = ($i + 1 < $count && $cues[$i+1]['start'] > $start && ($cues[$i+1]['start'] - $start) <= 6000)
            ? $cues[$i+1]['start']
            : ($start + 3500);

        $vtt .= ($i + 1) . "\n";
        $vtt .= _ms_to_vtt_time($start) . " --> " . _ms_to_vtt_time($end) . "\n";
        $vtt .= $c['text'] . "\n\n";
    }

    return $vtt;
}

/**
 * 尋找系統中可用的 ffprobe 執行檔路徑
 */
function find_ffprobe_binary() {
    static $ffprobe = null;
    if ($ffprobe !== null) return $ffprobe;

    $ffmpeg = find_ffmpeg_binary();
    if ($ffmpeg) {
        $dir = dirname($ffmpeg);
        $candidate = $dir . '/ffprobe';
        if (@file_exists($candidate) && @is_executable($candidate)) {
            $ffprobe = $candidate;
            return $ffprobe;
        }
    }

    $candidates = array(
        '/usr/builtin/bin/ffprobe',
        '/usr/local/bin/ffprobe',
        '/usr/bin/ffprobe',
        'ffprobe',
        '/usr/local/AppCentral/ffmpeg/bin/ffprobe',
        '/volume1/.@plugins/AppCentral/ffmpeg/bin/ffprobe',
    );

    if (is_dir('/volume1/.@plugins/AppCentral')) {
        $candidates[] = '/volume1/.@plugins/AppCentral/ffmpeg/bin/ffprobe';
    }

    foreach ($candidates as $c) {
        if (strpos($c, '/') === false) {
            if (DIRECTORY_SEPARATOR !== '\\') {
                $chk = @shell_exec('which ' . escapeshellarg($c) . ' 2>/dev/null');
                if (!empty($chk) && trim($chk) !== '') {
                    $ffprobe = trim($chk);
                    return $ffprobe;
                }
            } else {
                $chk = @shell_exec('where ' . escapeshellarg($c) . ' 2>nul');
                if (!empty($chk) && trim($chk) !== '') {
                    $lines = explode("\n", trim($chk));
                    $ffprobe = trim($lines[0]);
                    return $ffprobe;
                }
            }
        } else {
            if (@file_exists($c) && @is_executable($c)) {
                $ffprobe = $c;
                return $ffprobe;
            }
        }
    }

    $ffprobe = false;
    return false;
}

/**
 * 利用系統 ffmpeg / ffprobe 解析所有格式影片（包含 MP4、MKV、MOV、TS 等）的內嵌字幕軌道
 */
function parse_subtitles_via_ffmpeg($filepath) {
    if (!@file_exists($filepath) || !@is_readable($filepath)) return array();

    // 1. 優先使用 ffprobe（結構化 JSON 輸出）
    $ffprobe = find_ffprobe_binary();
    if ($ffprobe) {
        $cmd = escapeshellcmd($ffprobe) . ' -v quiet -print_format json -show_streams -select_streams s ' . safe_escapeshellarg($filepath) . ' 2>/dev/null';
        $json_str = @shell_exec($cmd);
        if (!empty($json_str)) {
            $data = @json_decode($json_str, true);
            if (isset($data['streams']) && is_array($data['streams']) && !empty($data['streams'])) {
                $subtitles = array();
                $sub_idx = 0;
                foreach ($data['streams'] as $st) {
                    $codec = isset($st['codec_name']) ? strtolower($st['codec_name']) : '';
                    $tags = isset($st['tags']) && is_array($st['tags']) ? $st['tags'] : array();
                    $lang = isset($tags['language']) ? $tags['language'] : (isset($tags['LANG']) ? $tags['LANG'] : '');
                    $title = isset($tags['title']) ? $tags['title'] : (isset($tags['handler_name']) ? $tags['handler_name'] : '');
                    if (strtolower($title) === 'subtitlehandler') $title = '';

                    $is_graphic_dvd = in_array($codec, array('dvd_subtitle', 'dvdsub', 'vobsub', 's_vobsub'));
                    $is_image = in_array($codec, array('pgs', 'hdmv_pgs_subtitle', 'dvd_subtitle', 'dvdsub', 'vobsub', 'dvb_subtitle', 'xsub', 's_vobsub'));
                    $codec_lbl = $codec;
                    if ($codec === 'mov_text' || $codec === 'tx3g') $codec_lbl = 'Timed Text';
                    elseif ($codec === 'subrip' || $codec === 'srt') $codec_lbl = 'SRT';
                    elseif ($codec === 'ass' || $codec === 'ssa') $codec_lbl = 'ASS';
                    elseif ($codec === 'webvtt') $codec_lbl = 'WebVTT';
                    elseif ($is_graphic_dvd) $codec_lbl = 'DVD Subtitle';

                    $subtitles[] = array(
                        'sub_index'   => $sub_idx++,
                        'track_num'   => isset($st['index']) ? (int)$st['index'] : $sub_idx,
                        'lang'        => $lang,
                        'lang_label'  => ebml_lang_label($lang),
                        'name'        => $title,
                        'codec'       => $codec,
                        'codec_label' => $codec_lbl,
                        'is_image'    => $is_image,
                        'is_graphic'  => $is_graphic_dvd,
                        'can_render'  => $is_graphic_dvd,
                    );
                }
                if (!empty($subtitles)) return $subtitles;
            }
        }
    }

    // 2. 次選使用 ffmpeg -i 解析標準輸出
    $ffmpeg = find_ffmpeg_binary();
    if ($ffmpeg) {
        $cmd = escapeshellcmd($ffmpeg) . ' -i ' . safe_escapeshellarg($filepath) . ' 2>&1';
        $output = @shell_exec($cmd);
        if (!empty($output)) {
            $lines = explode("\n", $output);
            $subtitles = array();
            $sub_idx = 0;
            $current_sub = null;

            foreach ($lines as $line) {
                $line_trim = trim($line);
                if (preg_match('/Stream\s+#0:(\d+)(?:\[0x[0-9a-fA-F]+\])?(?:\(([^)]+)\))?:\s+Subtitle:\s+([^,\r\n]+)/i', $line_trim, $m)) {
                    if ($current_sub !== null) {
                        $subtitles[] = $current_sub;
                    }
                    $stream_id = (int)$m[1];
                    $lang = isset($m[2]) ? trim($m[2]) : '';
                    $codec_raw = trim($m[3]);
                    $codec_parts = explode(' ', $codec_raw);
                    $codec_clean = strtolower($codec_parts[0]);

                    $is_graphic_dvd = in_array($codec_clean, array('dvd_subtitle', 'dvdsub', 'vobsub', 's_vobsub'));
                    $is_image = in_array($codec_clean, array('pgs', 'hdmv_pgs_subtitle', 'dvd_subtitle', 'dvdsub', 'vobsub', 'dvb_subtitle', 'xsub', 's_vobsub'));
                    $codec_lbl = $codec_clean;
                    if ($codec_clean === 'mov_text' || $codec_clean === 'tx3g') $codec_lbl = 'Timed Text';
                    elseif ($codec_clean === 'subrip' || $codec_clean === 'srt') $codec_lbl = 'SRT';
                    elseif ($codec_clean === 'ass' || $codec_clean === 'ssa') $codec_lbl = 'ASS';
                    elseif ($codec_clean === 'webvtt') $codec_lbl = 'WebVTT';
                    elseif ($is_graphic_dvd) $codec_lbl = 'DVD Subtitle';

                    $current_sub = array(
                        'sub_index'   => $sub_idx++,
                        'track_num'   => $stream_id,
                        'lang'        => $lang,
                        'lang_label'  => ebml_lang_label($lang),
                        'name'        => '',
                        'codec'       => $codec_clean,
                        'codec_label' => $codec_lbl,
                        'is_image'    => $is_image,
                        'is_graphic'  => $is_graphic_dvd,
                        'can_render'  => $is_graphic_dvd,
                    );
                } elseif ($current_sub !== null) {
                    if (preg_match('/title\s*:\s*(.+)$/i', $line_trim, $tm)) {
                        $current_sub['name'] = trim($tm[1]);
                    } elseif (preg_match('/handler_name\s*:\s*(.+)$/i', $line_trim, $hm)) {
                        $hname = trim($hm[1]);
                        if (empty($current_sub['name']) && strtolower($hname) !== 'subtitlehandler') {
                            $current_sub['name'] = $hname;
                        }
                    } elseif (preg_match('/^Stream\s+#/i', $line_trim)) {
                        $subtitles[] = $current_sub;
                        $current_sub = null;
                    }
                }
            }
            if ($current_sub !== null) {
                $subtitles[] = $current_sub;
            }
            if (!empty($subtitles)) return $subtitles;
        }
    }

    return array();
}

/**
 * 輔助函數：讀取 32 位元大端整數 (相容 PHP 5.6)
 */
function _mp4_read_uint32($str, $offset = 0) {
    if ($offset + 4 > strlen($str)) return 0;
    $v = unpack('N', substr($str, $offset, 4));
    return (int)$v[1];
}

/**
 * 輔助函數：讀取 16 位元大端整數 (相容 PHP 5.6)
 */
function _mp4_read_uint16($str, $offset = 0) {
    if ($offset + 2 > strlen($str)) return 0;
    $v = unpack('n', substr($str, $offset, 2));
    return (int)$v[1];
}

/**
 * 尋找 MP4 的 moov box 內容（支援頭部與尾部 FastStart 探測）
 */
function _mp4_find_moov_box($fp, $filepath = '') {
    $filesize = 0.0;
    if (!empty($filepath)) {
        $filesize = (float)get_real_filesize($filepath);
    }
    if ($filesize <= 0.0) {
        @fseek($fp, 0, SEEK_END);
        $pos = @ftell($fp);
        if ($pos !== false && $pos > 0) {
            $filesize = (float)sprintf("%u", $pos);
        }
    }
    @rewind($fp);

    // 1. 先從頭部掃描 (適用 FastStart 最佳化的 MP4)
    $pos = 0.0;
    $guard = 0;
    while (($filesize <= 0.0 || $pos < $filesize) && $guard++ < 200) {
        if ($pos > 0) {
            if (safe_fseek($fp, $pos) !== 0) break;
        }
        $hdr = @fread($fp, 16);
        if (strlen($hdr) < 8) break;
        $size = (float)sprintf("%u", _mp4_read_uint32($hdr, 0));
        $type = substr($hdr, 4, 4);

        $hdr_len = 8;
        if ($size == 1.0) {
            if (strlen($hdr) < 16) break;
            $unp = unpack('N2', substr($hdr, 8, 8));
            $hi = (float)sprintf("%u", $unp[1]);
            $lo = (float)sprintf("%u", $unp[2]);
            $size = ($hi * 4294967296.0) + $lo;
            $hdr_len = 16;
        } elseif ($size == 0.0) {
            if ($filesize > $pos) {
                $size = $filesize - $pos;
            } else {
                break;
            }
        }

        if ($type === 'moov') {
            $payload_size = $size - $hdr_len;
            if ($payload_size > 0 && $payload_size < 35 * 1024 * 1024) {
                safe_fseek($fp, $pos + $hdr_len);
                return @fread($fp, (int)$payload_size);
            }
            break;
        }

        if ($size <= 0.0) break;
        $pos += $size;
    }

    // 2. 若 moov 在檔案末尾 (常見於未 FastStart 最佳化的 MP4 大檔)
    // 從末尾往前讀取最多 35MB (相容巨型電影的完整 moov 結構)
    if ($filesize > 16.0) {
        $tail_scan = min($filesize, 35 * 1024 * 1024);
        $tail_start = $filesize - $tail_scan;
        if (safe_fseek($fp, $tail_start) === 0) {
            $tail_data = @fread($fp, (int)$tail_scan);
            if ($tail_data !== false && strlen($tail_data) > 8) {
                $moov_pos = strpos($tail_data, 'moov');
                if ($moov_pos !== false && $moov_pos >= 4) {
                    $unp_len = unpack('N', substr($tail_data, $moov_pos - 4, 4));
                    $size = (float)sprintf("%u", $unp_len[1]);
                    if ($size > 8 && $size < 35 * 1024 * 1024) {
                        $payload_start = $tail_start + $moov_pos + 4;
                        if (safe_fseek($fp, $payload_start) === 0) {
                            return @fread($fp, (int)($size - 8));
                        }
                    }
                }
            }
        }
    }

    return null;
}

/**
 * 純 PHP MP4 (ISOBMFF) 字幕軌道偵測（零外部依賴）
 */
function parse_mp4_subtitles_pure_php($filepath) {
    if (!@file_exists($filepath) || !@is_readable($filepath)) return array();
    $fp = @fopen($filepath, 'rb');
    if (!$fp) return array();

    $moov_data = _mp4_find_moov_box($fp, $filepath);
    @fclose($fp);
    if ($moov_data === null || strlen($moov_data) === 0) return array();

    $subtitles = array();
    $pos = 0;
    $moov_len = strlen($moov_data);
    $guard = 0;

    while ($pos + 8 <= $moov_len && $guard++ < 500) {
        $box_size = _mp4_read_uint32($moov_data, $pos);
        $box_type = substr($moov_data, $pos + 4, 4);
        if ($box_size < 8) break;
        $box_end = min($pos + $box_size, $moov_len);
        $box_payload = substr($moov_data, $pos + 8, $box_size - 8);
        $pos = $box_end;

        if ($box_type !== 'trak') continue;

        $trak_pos = 0;
        $trak_len = strlen($box_payload);
        $track_id = 0;
        $is_subtitle = false;
        $lang = '';
        $name = '';
        $codec = 'mov_text';
        $codec_label = 'Timed Text';
        $g2 = 0;

        while ($trak_pos + 8 <= $trak_len && $g2++ < 100) {
            $sub_size = _mp4_read_uint32($box_payload, $trak_pos);
            $sub_type = substr($box_payload, $trak_pos + 4, 4);
            if ($sub_size < 8) break;
            $sub_payload = substr($box_payload, $trak_pos + 8, $sub_size - 8);
            $trak_pos += $sub_size;

            if ($sub_type === 'tkhd') {
                $ver = ord($sub_payload[0]);
                $track_id = ($ver === 1) ? _mp4_read_uint32($sub_payload, 20) : _mp4_read_uint32($sub_payload, 12);
            } elseif ($sub_type === 'mdia') {
                $mdia_pos = 0;
                $mdia_len = strlen($sub_payload);
                $g3 = 0;
                while ($mdia_pos + 8 <= $mdia_len && $g3++ < 100) {
                    $m_size = _mp4_read_uint32($sub_payload, $mdia_pos);
                    $m_type = substr($sub_payload, $mdia_pos + 4, 4);
                    if ($m_size < 8) break;
                    $m_payload = substr($sub_payload, $mdia_pos + 8, $m_size - 8);
                    $mdia_pos += $m_size;

                    if ($m_type === 'mdhd') {
                        $m_ver = ord($m_payload[0]);
                        $lang_offset = ($m_ver === 1) ? 36 : 24;
                        if (strlen($m_payload) >= $lang_offset + 2) {
                            $lang_val = _mp4_read_uint16($m_payload, $lang_offset);
                            $c1 = chr((($lang_val >> 10) & 0x1F) + 0x60);
                            $c2 = chr((($lang_val >> 5) & 0x1F) + 0x60);
                            $c3 = chr(($lang_val & 0x1F) + 0x60);
                            if ($c1 >= 'a' && $c1 <= 'z') {
                                $lang = $c1 . $c2 . $c3;
                            }
                        }
                    } elseif ($m_type === 'hdlr') {
                        if (strlen($m_payload) >= 12) {
                            $htype = substr($m_payload, 8, 4);
                            if (in_array($htype, array('sbtx', 'subt', 'text', 'clcp', 'sbmv', 'subp'))) {
                                $is_subtitle = true;
                            }
                            if (strlen($m_payload) >= 25) {
                                $hname = trim(substr($m_payload, 24));
                                $hname = rtrim($hname, "\0");
                                if ($hname !== '' && strtolower($hname) !== 'subtitlehandler') {
                                    $name = $hname;
                                }
                            }
                        }
                    } elseif ($m_type === 'minf') {
                        $stbl_pos = strpos($m_payload, 'stsd');
                        if ($stbl_pos !== false && $stbl_pos + 20 <= strlen($m_payload)) {
                            $entry_fourcc = substr($m_payload, $stbl_pos + 16, 4);
                            if (in_array($entry_fourcc, array('tx3g', 'text', 'wvtt', 'c608', 'c708', 'mp4s'))) {
                                $is_subtitle = true;
                                $codec = $entry_fourcc;
                                $codec_label = ($entry_fourcc === 'wvtt') ? 'WebVTT' : (($entry_fourcc === 'mp4s') ? 'DVD Subtitle' : 'Timed Text');
                            }
                        }
                    }
                }
            } elseif ($sub_type === 'udta') {
                $u_pos = strpos($sub_payload, 'name');
                if ($u_pos !== false && $u_pos >= 4) {
                    $n_size = _mp4_read_uint32($sub_payload, $u_pos - 4);
                    if ($n_size > 8 && $u_pos + $n_size - 4 <= strlen($sub_payload)) {
                        $raw_title = trim(substr($sub_payload, $u_pos + 4, $n_size - 8));
                        $raw_title = rtrim($raw_title, "\0");
                        if ($raw_title !== '') {
                            $name = $raw_title;
                        }
                    }
                }
            }
        }

        if ($name === '') {
            $u_pos = strpos($box_payload, 'name');
            if ($u_pos !== false && $u_pos >= 4) {
                $n_size = _mp4_read_uint32($box_payload, $u_pos - 4);
                if ($n_size > 8 && $n_size < 128 && $u_pos + $n_size - 4 <= strlen($box_payload)) {
                    $raw_title = trim(substr($box_payload, $u_pos + 4, $n_size - 8));
                    $raw_title = rtrim($raw_title, "\0");
                    if ($raw_title !== '' && strtolower($raw_title) !== 'subtitlehandler') {
                        $name = $raw_title;
                    }
                }
            }
        }

        if (($lang === '' || $lang === 'und') && $name !== '') {
            $n_lower = strtolower($name);
            if (strpos($n_lower, '繁體') !== false || strpos($n_lower, 'zh-hant') !== false || strpos($n_lower, 'cht') !== false) {
                $lang = 'chi';
            } elseif (strpos($n_lower, '簡體') !== false || strpos($n_lower, 'zh-hans') !== false || strpos($n_lower, 'chs') !== false) {
                $lang = 'chi';
            } elseif (strpos($n_lower, '中文') !== false || strpos($n_lower, 'chinese') !== false) {
                $lang = 'chi';
            } elseif (strpos($n_lower, '日') !== false || strpos($n_lower, 'jap') !== false) {
                $lang = 'jpn';
            } elseif (strpos($n_lower, '英') !== false || strpos($n_lower, 'eng') !== false) {
                $lang = 'eng';
            }
        }

        if ($is_subtitle) {
            $is_graphic_dvd = ($codec === 'mp4s' || $codec === 'dvd_subtitle');
            $subtitles[] = array(
                'sub_index'   => count($subtitles),
                'track_num'   => $track_id ?: (count($subtitles) + 1),
                'lang'        => $lang ?: 'und',
                'lang_label'  => ebml_lang_label($lang),
                'name'        => $name,
                'codec'       => $codec,
                'codec_label' => $codec_label,
                'is_image'    => $is_graphic_dvd,
                'is_graphic'  => $is_graphic_dvd,
                'can_render'  => $is_graphic_dvd,
            );
        }
    }

    return $subtitles;
}

/**
 * 純 PHP 提取 MP4 中的 mov_text / tx3g 內嵌字幕為標準 WebVTT
 */
function extract_mp4_subtitles_pure_php($filepath, $target_track_id = 0, $sub_index = 0) {
    if (!@file_exists($filepath) || !@is_readable($filepath)) return false;
    $fp = @fopen($filepath, 'rb');
    if (!$fp) return false;

    $moov_data = _mp4_find_moov_box($fp, $filepath);
    if ($moov_data === null || strlen($moov_data) === 0) {
        @fclose($fp);
        return false;
    }

    // 讀取全域 Movie Timescale
    $movie_timescale = 1000;
    $mvhd_pos = strpos($moov_data, 'mvhd');
    if ($mvhd_pos !== false && $mvhd_pos + 20 <= strlen($moov_data)) {
        $mv_ver = ord($moov_data[$mvhd_pos + 4]);
        $movie_timescale = ($mv_ver === 1) ? _mp4_read_uint32($moov_data, $mvhd_pos + 24) : _mp4_read_uint32($moov_data, $mvhd_pos + 16);
        if ($movie_timescale <= 0) $movie_timescale = 1000;
    }

    $moov_len = strlen($moov_data);
    $pos = 0;
    $guard = 0;
    $curr_sub_idx = 0;
    $target_trak_payload = null;

    while ($pos + 8 <= $moov_len && $guard++ < 200) {
        $box_size = _mp4_read_uint32($moov_data, $pos);
        $box_type = substr($moov_data, $pos + 4, 4);
        if ($box_size < 8) break;
        $box_payload = substr($moov_data, $pos + 8, $box_size - 8);
        $pos += $box_size;

        if ($box_type !== 'trak') continue;

        $is_sub = (strpos($box_payload, 'sbtx') !== false || strpos($box_payload, 'subt') !== false ||
                   strpos($box_payload, 'tx3g') !== false || strpos($box_payload, 'text') !== false);
        if (!$is_sub) continue;

        $tkhd_pos = strpos($box_payload, 'tkhd');
        $t_id = 0;
        if ($tkhd_pos !== false && $tkhd_pos + 20 <= strlen($box_payload)) {
            $ver = ord($box_payload[$tkhd_pos + 4]);
            $t_id = ($ver === 1) ? _mp4_read_uint32($box_payload, $tkhd_pos + 24) : _mp4_read_uint32($box_payload, $tkhd_pos + 16);
        }

        if ($target_track_id > 0 && $t_id === $target_track_id) {
            $target_trak_payload = $box_payload;
            break;
        } elseif ($curr_sub_idx === $sub_index) {
            $fallback_trak_payload = $box_payload;
        }
        if (!isset($first_sub_payload)) {
            $first_sub_payload = $box_payload;
        }
        $curr_sub_idx++;
    }

    if ($target_trak_payload === null) {
        if (isset($fallback_trak_payload)) {
            $target_trak_payload = $fallback_trak_payload;
        } elseif (isset($first_sub_payload)) {
            $target_trak_payload = $first_sub_payload;
        } else {
            @fclose($fp);
            return false;
        }
    }

    $timescale = 1000;
    $mdhd_pos = strpos($target_trak_payload, 'mdhd');
    if ($mdhd_pos !== false && $mdhd_pos + 20 <= strlen($target_trak_payload)) {
        $ver = ord($target_trak_payload[$mdhd_pos + 4]);
        $timescale = ($ver === 1) ? _mp4_read_uint32($target_trak_payload, $mdhd_pos + 24) : _mp4_read_uint32($target_trak_payload, $mdhd_pos + 16);
        if ($timescale <= 0) $timescale = 1000;
    }

    // 解析 Edit List (elst) 初始時間延遲
    $initial_delay_ms = 0;
    $edts_pos = strpos($target_trak_payload, 'edts');
    if ($edts_pos !== false) {
        $elst_pos = strpos($target_trak_payload, 'elst', $edts_pos);
        if ($elst_pos !== false && $elst_pos + 16 <= strlen($target_trak_payload)) {
            $elst_ver = ord($target_trak_payload[$elst_pos + 4]);
            $entry_count = _mp4_read_uint32($target_trak_payload, $elst_pos + 8);
            $e_offset = $elst_pos + 12;
            for ($e = 0; $e < $entry_count && $e_offset + 12 <= strlen($target_trak_payload); $e++) {
                if ($elst_ver === 1) {
                    if ($e_offset + 20 > strlen($target_trak_payload)) break;
                    $seg_dur = _mp4_read_uint32($target_trak_payload, $e_offset + 4);
                    $media_time_hi = _mp4_read_uint32($target_trak_payload, $e_offset + 8);
                    $media_time_lo = _mp4_read_uint32($target_trak_payload, $e_offset + 12);
                    $e_offset += 20;
                    if (($media_time_hi == -1 || $media_time_hi == 0xFFFFFFFF) && ($media_time_lo == -1 || $media_time_lo == 0xFFFFFFFF)) {
                        $initial_delay_ms += (int)(($seg_dur / $movie_timescale) * 1000);
                    }
                } else {
                    $seg_dur = _mp4_read_uint32($target_trak_payload, $e_offset);
                    $media_time = _mp4_read_uint32($target_trak_payload, $e_offset + 4);
                    $e_offset += 12;
                    if ($media_time == -1 || $media_time == 0xFFFFFFFF) {
                        $initial_delay_ms += (int)(($seg_dur / $movie_timescale) * 1000);
                    }
                }
            }
        }
    }

    $stts_pos = strpos($target_trak_payload, 'stts');
    if ($stts_pos === false) { @fclose($fp); return false; }
    $stts_len = _mp4_read_uint32($target_trak_payload, $stts_pos - 4);
    $stts_data = substr($target_trak_payload, $stts_pos + 4, $stts_len - 8);
    $stts_count = _mp4_read_uint32($stts_data, 4);
    $sample_durations = array();
    $stts_offset = 8;
    for ($i = 0; $i < $stts_count && $stts_offset + 8 <= strlen($stts_data); $i++) {
        $count = _mp4_read_uint32($stts_data, $stts_offset);
        $delta = _mp4_read_uint32($stts_data, $stts_offset + 4);
        $stts_offset += 8;
        for ($j = 0; $j < $count; $j++) {
            $sample_durations[] = (int)(($delta / $timescale) * 1000);
        }
    }

    $stsz_pos = strpos($target_trak_payload, 'stsz');
    if ($stsz_pos === false) { @fclose($fp); return false; }
    $stsz_data = substr($target_trak_payload, $stsz_pos + 4);
    $default_sample_size = _mp4_read_uint32($stsz_data, 4);
    $sample_count = _mp4_read_uint32($stsz_data, 8);
    $sample_sizes = array();
    if ($default_sample_size > 0) {
        $sample_sizes = array_fill(0, $sample_count, $default_sample_size);
    } else {
        $stsz_offset = 12;
        for ($i = 0; $i < $sample_count && $stsz_offset + 4 <= strlen($stsz_data); $i++) {
            $sample_sizes[] = _mp4_read_uint32($stsz_data, $stsz_offset);
            $stsz_offset += 4;
        }
    }

    $stsc_pos = strpos($target_trak_payload, 'stsc');
    if ($stsc_pos === false) { @fclose($fp); return false; }
    $stsc_data = substr($target_trak_payload, $stsc_pos + 4);
    $stsc_count = _mp4_read_uint32($stsc_data, 4);
    $stsc_entries = array();
    $stsc_offset = 8;
    for ($i = 0; $i < $stsc_count && $stsc_offset + 12 <= strlen($stsc_data); $i++) {
        $stsc_entries[] = array(
            'first_chunk'    => _mp4_read_uint32($stsc_data, $stsc_offset),
            'samples_chunk'  => _mp4_read_uint32($stsc_data, $stsc_offset + 4),
            'desc_idx'       => _mp4_read_uint32($stsc_data, $stsc_offset + 8),
        );
        $stsc_offset += 12;
    }

    $chunk_offsets = array();
    $stco_pos = strpos($target_trak_payload, 'stco');
    if ($stco_pos !== false) {
        $stco_data = substr($target_trak_payload, $stco_pos + 4);
        $chunk_count = _mp4_read_uint32($stco_data, 4);
        $stco_offset = 8;
        for ($i = 0; $i < $chunk_count && $stco_offset + 4 <= strlen($stco_data); $i++) {
            $chunk_offsets[] = _mp4_read_uint32($stco_data, $stco_offset);
            $stco_offset += 4;
        }
    } else {
        $co64_pos = strpos($target_trak_payload, 'co64');
        if ($co64_pos !== false) {
            $co64_data = substr($target_trak_payload, $co64_pos + 4);
            $chunk_count = _mp4_read_uint32($co64_data, 4);
            $co64_offset = 8;
            for ($i = 0; $i < $chunk_count && $co64_offset + 8 <= strlen($co64_data); $i++) {
                $hi = _mp4_read_uint32($co64_data, $co64_offset);
                $lo = _mp4_read_uint32($co64_data, $co64_offset + 4);
                $chunk_offsets[] = ($hi << 32) | $lo;
                $co64_offset += 8;
            }
        }
    }

    if (empty($chunk_offsets) || empty($sample_sizes) || empty($stsc_entries)) {
        @fclose($fp);
        return false;
    }

    $sample_offsets = array();
    $total_chunks = count($chunk_offsets);
    $sample_idx = 0;
    $num_stsc = count($stsc_entries);

    for ($c = 0; $c < $total_chunks; $c++) {
        $chunk_num = $c + 1;
        $samples_in_this_chunk = 1;
        for ($s_idx = 0; $s_idx < $num_stsc; $s_idx++) {
            if ($chunk_num >= $stsc_entries[$s_idx]['first_chunk']) {
                $samples_in_this_chunk = $stsc_entries[$s_idx]['samples_chunk'];
            } else {
                break;
            }
        }

        $current_offset = $chunk_offsets[$c];
        for ($s = 0; $s < $samples_in_this_chunk && $sample_idx < count($sample_sizes); $s++) {
            $sample_offsets[] = $current_offset;
            $current_offset += $sample_sizes[$sample_idx];
            $sample_idx++;
        }
    }

    $cues = array();
    $current_time_ms = $initial_delay_ms;
    $total_samples = count($sample_offsets);

    for ($i = 0; $i < $total_samples; $i++) {
        $dur_ms = isset($sample_durations[$i]) ? $sample_durations[$i] : 2000;
        $offset = $sample_offsets[$i];
        $size = isset($sample_sizes[$i]) ? $sample_sizes[$i] : 0;

        if ($size > 2) {
            @fseek($fp, $offset, SEEK_SET);
            $raw_sample = @fread($fp, $size);
            if (strlen($raw_sample) >= 2) {
                $text_len = _mp4_read_uint16($raw_sample, 0);
                if ($text_len > 0 && strlen($raw_sample) >= 2 + $text_len) {
                    $text = substr($raw_sample, 2, $text_len);
                    $text = trim(rtrim($text, "\0"));
                    if ($text !== '') {
                        $cues[] = array(
                            'start' => $current_time_ms,
                            'end'   => $current_time_ms + $dur_ms,
                            'text'  => $text
                        );
                    }
                }
            }
        }

        $current_time_ms += $dur_ms;
    }
    @fclose($fp);

    if (empty($cues)) return false;

    $vtt = "WEBVTT\n\n";
    foreach ($cues as $idx => $cue) {
        $vtt .= ($idx + 1) . "\n";
        $vtt .= _ms_to_vtt_time($cue['start']) . " --> " . _ms_to_vtt_time($cue['end']) . "\n";
        $vtt .= $cue['text'] . "\n\n";
    }

    return $vtt;
}

/**
 * 統一內嵌字幕偵測入口（支援 MKV、WebM、MP4、M4V、MOV、TS、AVI 等所有影音格式，具備 cache 資料夾持久快取）
 */
function parse_embedded_subtitles($filepath) {
    $filepath = safe_fs_path($filepath);
    if (!@file_exists($filepath) || !@is_readable($filepath)) return array();

    $cache_dir = get_subtitles_cache_dir();
    $cache_key = get_video_subtitle_cache_key($filepath);
    $mtime = (int)@filemtime($filepath);
    $cache_file = rtrim($cache_dir, '/\\') . '/nas_sub_info_' . $cache_key . '.json';
    $legacy_cache_file = rtrim($cache_dir, '/\\') . '/nas_sub_info_' . md5($filepath) . '_' . $mtime . '.json';

    // 1. 若來源檔案未變更且快取存在，直接返回快取解析結果（零 CPU 與零磁碟 I/O 開銷）
    if (@file_exists($cache_file) && @filesize($cache_file) >= 2) {
        $cached_str = @file_get_contents($cache_file);
        if ($cached_str !== false) {
            $cached_arr = @json_decode($cached_str, true);
            if (is_array($cached_arr)) {
                return $cached_arr;
            }
        }
    }
    if (@file_exists($legacy_cache_file) && @filesize($legacy_cache_file) >= 2) {
        $cached_str = @file_get_contents($legacy_cache_file);
        if ($cached_str !== false) {
            $cached_arr = @json_decode($cached_str, true);
            if (is_array($cached_arr)) {
                return $cached_arr;
            }
        }
    }

    $parts = get_filename_parts(basename($filepath));
    $ext = strtolower($parts['ext']);
    $subs = array();

    // 2. 若為 MKV / WebM，優先使用快速純 PHP EBML 解析器
    if (in_array($ext, array('mkv', 'webm', 'mka', 'mk3d'))) {
        $subs = parse_mkv_subtitles($filepath);
    }

    // 3. 嘗試 ffmpeg / ffprobe 探測字幕軌道（跨格式通用）
    if (empty($subs)) {
        $subs = parse_subtitles_via_ffmpeg($filepath);
    }

    // 4. 若為 MP4 / M4V / MOV / 3GP，使用純 PHP MP4 (ISOBMFF) 解析器回退
    if (empty($subs) && in_array($ext, array('mp4', 'm4v', 'mov', '3gp'))) {
        $subs = parse_mp4_subtitles_pure_php($filepath);
    }

    // 5. 其他格式回退
    if (empty($subs) && in_array($ext, array('mkv', 'webm', 'mka', 'mk3d'))) {
        $subs = parse_mkv_subtitles($filepath);
    }

    if (!is_array($subs)) {
        $subs = array();
    }

    // 儲存解析結果至 cache 資料夾供後續直接調用
    cleanup_old_video_subtitle_cache($filepath, $cache_key);
    @file_put_contents($cache_file, json_encode($subs));

    return $subs;
}

/**
 * 統一提取內嵌字幕為 WebVTT 字幕流（具備 cache 資料夾持久快取保護與跨格式多引擎支援）
 */
function extract_embedded_subtitle_vtt($filepath, $sub_index, $track_num = 0) {
    $filepath = safe_fs_path($filepath);
    if (!@file_exists($filepath) || !@is_readable($filepath)) return false;

    $cache_dir = get_subtitles_cache_dir();
    $cache_key = get_video_subtitle_cache_key($filepath);
    $mtime = (int)@filemtime($filepath);
    $cache_file = rtrim($cache_dir, '/\\') . '/nas_sub_' . $cache_key . '_' . intval($sub_index) . '.vtt';
    $legacy_cache_file = rtrim($cache_dir, '/\\') . '/nas_sub_' . md5($filepath) . '_' . $mtime . '_' . intval($sub_index) . '.vtt';

    // 1. 若來源檔案未變更且快取存在且有效，立即返回快取內容（零重複提取）
    if (@file_exists($cache_file) && @filesize($cache_file) > 10) {
        return @file_get_contents($cache_file);
    }
    if (@file_exists($legacy_cache_file) && @filesize($legacy_cache_file) > 10) {
        return @file_get_contents($legacy_cache_file);
    }

    // 2. 優先嘗試系統 ffmpeg 提取（支援 MP4、MKV、WebM、MOV 等所有格式）
    $ffmpeg = find_ffmpeg_binary();
    if ($ffmpeg) {
        $dev_null = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '2>nul' : '2>/dev/null';
        $cmd = escapeshellcmd($ffmpeg) . ' -y -loglevel error -i ' . safe_escapeshellarg($filepath) . ' -map 0:s:' . intval($sub_index) . ' -c:s webvtt -f webvtt ' . safe_escapeshellarg($cache_file) . ' ' . $dev_null;
        @shell_exec($cmd);
        if (@file_exists($cache_file) && @filesize($cache_file) > 10) {
            return @file_get_contents($cache_file);
        } else {
            // 若失敗或為空檔案，清理殘留
            if (@file_exists($cache_file)) {
                @unlink($cache_file);
            }
        }
    }

    // 3. 純 PHP 雙引擎回退機制
    $parts = get_filename_parts(basename($filepath));
    $ext = strtolower($parts['ext']);
    $vtt_content = false;

    if (in_array($ext, array('mkv', 'webm', 'mka', 'mk3d'))) {
        $vtt_content = extract_mkv_subtitles_pure_php($filepath, $track_num, $sub_index);
    } elseif (in_array($ext, array('mp4', 'm4v', 'mov', '3gp'))) {
        $vtt_content = extract_mp4_subtitles_pure_php($filepath, $track_num, $sub_index);
    }

    if ($vtt_content && strlen($vtt_content) > 10) {
        @file_put_contents($cache_file, $vtt_content);
        return $vtt_content;
    }

    return false;
}

// 保持與原舊名稱相容
function extract_mkv_subtitle_vtt($filepath, $sub_index, $track_num = 0) {
    return extract_embedded_subtitle_vtt($filepath, $sub_index, $track_num);
}

/**
 * 解析 DVD SPU (Sub-Picture Unit) 封包控制序列 (DCSQ)
 * 提取字幕顯示時間、座標位置 (DAREA)、RLE 點陣圖偏移量與調色盤資訊 (PHP 5.6 相容)
 */
function parse_spu_dcsq($spu_data, $start_ms, $dur_ms) {
    $len = strlen($spu_data);
    if ($len < 4) return null;

    $spu_size = (ord($spu_data[0]) << 8) | ord($spu_data[1]);
    $dcsq_offset = (ord($spu_data[2]) << 8) | ord($spu_data[3]);

    if ($dcsq_offset >= $len || $dcsq_offset < 4) {
        // 容錯機制 1：部分 MP4 封裝包含 2-byte 封包長度前綴
        if ($len >= 6) {
            $d2 = (ord($spu_data[4]) << 8) | ord($spu_data[5]);
            if ($d2 < $len - 2 && $d2 >= 4) {
                $spu_data = substr($spu_data, 2);
                $len -= 2;
                $spu_size = (ord($spu_data[0]) << 8) | ord($spu_data[1]);
                $dcsq_offset = $d2;
            }
        }
        // 容錯機制 2：部分 MP4 封裝包含 4-byte 樣本文本前綴
        if (($dcsq_offset >= $len || $dcsq_offset < 4) && $len >= 8) {
            $d4 = (ord($spu_data[6]) << 8) | ord($spu_data[7]);
            if ($d4 < $len - 4 && $d4 >= 4) {
                $spu_data = substr($spu_data, 4);
                $len -= 4;
                $spu_size = (ord($spu_data[0]) << 8) | ord($spu_data[1]);
                $dcsq_offset = $d4;
            }
        }
    }

    if ($dcsq_offset >= $len || $dcsq_offset < 4) return null;

    $cur_offset = $dcsq_offset;
    $guard = 0;

    $stop_ms = 0;
    $x1 = 0; $y1 = 0; $x2 = 0; $y2 = 0;
    $top_offset = 4;
    $bottom_offset = 4;
    $colors = array(0, 1, 2, 3);
    $alpha = array(0, 15, 15, 15);
    $has_darea = false;

    while ($cur_offset + 4 <= $len && $guard++ < 25) {
        $exec_date = (ord($spu_data[$cur_offset]) << 8) | ord($spu_data[$cur_offset + 1]);
        $next_offset = (ord($spu_data[$cur_offset + 2]) << 8) | ord($spu_data[$cur_offset + 3]);

        $p = $cur_offset + 4;
        $cmd_guard = 0;

        while ($p < $len && $cmd_guard++ < 60) {
            $cmd = ord($spu_data[$p++]);
            if ($cmd === 0xFF) {
                break;
            } elseif ($cmd === 0x00) {
                // FSTA_DSP
            } elseif ($cmd === 0x01) {
                // STA_DSP
            } elseif ($cmd === 0x02) {
                // STP_DSP (停止顯示指令)
                $stop_delay_ms = (int)round($exec_date * 1024 / 90);
                $stop_ms = $start_ms + $stop_delay_ms;
            } elseif ($cmd === 0x03) {
                // SET_COLOR (2 位元組，4 個 nibble 對應 pixel 3, 2, 1, 0)
                if ($p + 2 <= $len) {
                    $b0 = ord($spu_data[$p++]);
                    $b1 = ord($spu_data[$p++]);
                    $colors = array(
                        $b1 & 0x0F,
                        ($b1 >> 4) & 0x0F,
                        $b0 & 0x0F,
                        ($b0 >> 4) & 0x0F
                    );
                }
            } elseif ($cmd === 0x04) {
                // SET_CONTR (2 位元組，4 個 nibble 對應透明度 0=完全透明, 15=不透明)
                if ($p + 2 <= $len) {
                    $b0 = ord($spu_data[$p++]);
                    $b1 = ord($spu_data[$p++]);
                    $alpha = array(
                        $b1 & 0x0F,
                        ($b1 >> 4) & 0x0F,
                        $b0 & 0x0F,
                        ($b0 >> 4) & 0x0F
                    );
                }
            } elseif ($cmd === 0x05) {
                // SET_DAREA (6 位元組座標區域 x1, x2, y1, y2)
                if ($p + 6 <= $len) {
                    $b0 = ord($spu_data[$p]);
                    $b1 = ord($spu_data[$p + 1]);
                    $b2 = ord($spu_data[$p + 2]);
                    $b3 = ord($spu_data[$p + 3]);
                    $b4 = ord($spu_data[$p + 4]);
                    $b5 = ord($spu_data[$p + 5]);
                    $p += 6;

                    $x1 = ($b0 << 4) | (($b1 >> 4) & 0x0F);
                    $x2 = (($b1 & 0x0F) << 8) | $b2;
                    $y1 = ($b3 << 4) | (($b4 >> 4) & 0x0F);
                    $y2 = (($b4 & 0x0F) << 8) | $b5;
                    $has_darea = true;
                }
            } elseif ($cmd === 0x06) {
                // SET_DSPXA (4 位元組：Top 場與 Bottom 場的點陣數據偏移量)
                if ($p + 4 <= $len) {
                    $top_offset = (ord($spu_data[$p]) << 8) | ord($spu_data[$p + 1]);
                    $bottom_offset = (ord($spu_data[$p + 2]) << 8) | ord($spu_data[$p + 3]);
                    $p += 4;
                }
            } else {
                break;
            }
        }

        if ($next_offset <= $cur_offset || $next_offset >= $len) {
            break;
        }
        $cur_offset = $next_offset;
    }

    if (!$has_darea) return null;

    $width = $x2 - $x1 + 1;
    $height = $y2 - $y1 + 1;
    if ($width <= 0 || $height <= 0 || $width > 1920 || $height > 1080) {
        return null;
    }

    if ($stop_ms <= $start_ms) {
        $stop_ms = ($dur_ms > 0) ? ($start_ms + $dur_ms) : ($start_ms + 4000);
    }

    $pixel_data_len = $dcsq_offset;
    if ($pixel_data_len > $len) $pixel_data_len = $len;

    return array(
        's'   => (int)$start_ms,
        'e'   => (int)$stop_ms,
        'x'   => (int)$x1,
        'y'   => (int)$y1,
        'w'   => (int)$width,
        'h'   => (int)$height,
        'top' => (int)$top_offset,
        'bot' => (int)$bottom_offset,
        'col' => $colors,
        'alp' => $alpha,
        'rle' => base64_encode(substr($spu_data, 0, $pixel_data_len)),
    );
}

/**
 * 純 PHP 提取 MP4 中的 DVD Subtitle (mp4s) 封包為結構化圖形字幕 Cues
 */
function extract_mp4_spu_cues_pure_php($filepath, $target_track_id = 0, $sub_index = 0) {
    if (!@file_exists($filepath) || !@is_readable($filepath)) return false;
    $fp = @fopen($filepath, 'rb');
    if (!$fp) return false;

    $moov_data = _mp4_find_moov_box($fp, $filepath);
    if ($moov_data === null || strlen($moov_data) === 0) {
        @fclose($fp);
        return false;
    }

    // 讀取全域 Movie Timescale
    $movie_timescale = 1000;
    $mvhd_pos = strpos($moov_data, 'mvhd');
    if ($mvhd_pos !== false && $mvhd_pos + 20 <= strlen($moov_data)) {
        $mv_ver = ord($moov_data[$mvhd_pos + 4]);
        $movie_timescale = ($mv_ver === 1) ? _mp4_read_uint32($moov_data, $mvhd_pos + 24) : _mp4_read_uint32($moov_data, $mvhd_pos + 16);
        if ($movie_timescale <= 0) $movie_timescale = 1000;
    }

    $moov_len = strlen($moov_data);
    $pos = 0;
    $guard = 0;
    $curr_sub_idx = 0;
    $target_trak_payload = null;

    while ($pos + 8 <= $moov_len && $guard++ < 200) {
        $box_size = _mp4_read_uint32($moov_data, $pos);
        $box_type = substr($moov_data, $pos + 4, 4);
        if ($box_size < 8) break;
        $box_payload = substr($moov_data, $pos + 8, $box_size - 8);
        $pos += $box_size;

        if ($box_type !== 'trak') continue;

        $is_sub = (strpos($box_payload, 'sbtx') !== false || strpos($box_payload, 'subt') !== false ||
                   strpos($box_payload, 'sbmv') !== false || strpos($box_payload, 'mp4s') !== false ||
                   strpos($box_payload, 'tx3g') !== false || strpos($box_payload, 'text') !== false ||
                   strpos($box_payload, 'subp') !== false);
        if (!$is_sub) continue;

        $tkhd_pos = strpos($box_payload, 'tkhd');
        $t_id = 0;
        if ($tkhd_pos !== false && $tkhd_pos + 20 <= strlen($box_payload)) {
            $ver = ord($box_payload[$tkhd_pos + 4]);
            $t_id = ($ver === 1) ? _mp4_read_uint32($box_payload, $tkhd_pos + 24) : _mp4_read_uint32($box_payload, $tkhd_pos + 16);
        }

        if ($target_track_id > 0 && $t_id === $target_track_id) {
            $target_trak_payload = $box_payload;
            break;
        } elseif ($curr_sub_idx === $sub_index) {
            $fallback_trak_payload = $box_payload;
        }
        if (!isset($first_sub_payload)) {
            $first_sub_payload = $box_payload;
        }
        $curr_sub_idx++;
    }

    if ($target_trak_payload === null) {
        if (isset($fallback_trak_payload)) {
            $target_trak_payload = $fallback_trak_payload;
        } elseif (isset($first_sub_payload)) {
            $target_trak_payload = $first_sub_payload;
        } else {
            @fclose($fp);
            return false;
        }
    }

    $timescale = 1000;
    $mdhd_pos = strpos($target_trak_payload, 'mdhd');
    if ($mdhd_pos !== false && $mdhd_pos + 20 <= strlen($target_trak_payload)) {
        $ver = ord($target_trak_payload[$mdhd_pos + 4]);
        $timescale = ($ver === 1) ? _mp4_read_uint32($target_trak_payload, $mdhd_pos + 24) : _mp4_read_uint32($target_trak_payload, $mdhd_pos + 16);
        if ($timescale <= 0) $timescale = 1000;
    }

    // 解析 Edit List (elst) 初始時間延遲 (支援 empty edit dwell delay)
    $initial_delay_ms = 0;
    $edts_pos = strpos($target_trak_payload, 'edts');
    if ($edts_pos !== false) {
        $elst_pos = strpos($target_trak_payload, 'elst', $edts_pos);
        if ($elst_pos !== false && $elst_pos + 16 <= strlen($target_trak_payload)) {
            $elst_ver = ord($target_trak_payload[$elst_pos + 4]);
            $entry_count = _mp4_read_uint32($target_trak_payload, $elst_pos + 8);
            $e_offset = $elst_pos + 12;
            for ($e = 0; $e < $entry_count && $e_offset + 12 <= strlen($target_trak_payload); $e++) {
                if ($elst_ver === 1) {
                    if ($e_offset + 20 > strlen($target_trak_payload)) break;
                    $seg_dur = _mp4_read_uint32($target_trak_payload, $e_offset + 4);
                    $media_time_hi = _mp4_read_uint32($target_trak_payload, $e_offset + 8);
                    $media_time_lo = _mp4_read_uint32($target_trak_payload, $e_offset + 12);
                    $e_offset += 20;
                    if (($media_time_hi == -1 || $media_time_hi == 0xFFFFFFFF) && ($media_time_lo == -1 || $media_time_lo == 0xFFFFFFFF)) {
                        $initial_delay_ms += (int)(($seg_dur / $movie_timescale) * 1000);
                    }
                } else {
                    $seg_dur = _mp4_read_uint32($target_trak_payload, $e_offset);
                    $media_time = _mp4_read_uint32($target_trak_payload, $e_offset + 4);
                    $e_offset += 12;
                    if ($media_time == -1 || $media_time == 0xFFFFFFFF) {
                        $initial_delay_ms += (int)(($seg_dur / $movie_timescale) * 1000);
                    }
                }
            }
        }
    }

    // 從 esds 提取 16 色原廠 YCbCr 調色盤並轉為 RGB 十六進位格式
    $palette = array();
    $esds_pos = strpos($target_trak_payload, 'esds');
    if ($esds_pos !== false) {
        $esds_chunk = substr($target_trak_payload, $esds_pos, min(256, strlen($target_trak_payload) - $esds_pos));
        $tag5_pos = strpos($esds_chunk, "\x05");
        while ($tag5_pos !== false) {
            $p = $tag5_pos + 1;
            $len = 0;
            $len_guard = 0;
            while ($p < strlen($esds_chunk) && $len_guard++ < 4) {
                $b = ord($esds_chunk[$p++]);
                $len = ($len << 7) | ($b & 0x7F);
                if (!($b & 0x80)) break;
            }
            if ($len === 64 && $p + 64 <= strlen($esds_chunk)) {
                for ($pi = 0; $pi < 16; $pi++) {
                    $y  = ord($esds_chunk[$p + $pi * 4 + 1]);
                    $cb = ord($esds_chunk[$p + $pi * 4 + 2]);
                    $cr = ord($esds_chunk[$p + $pi * 4 + 3]);
                    $r = max(0, min(255, (int)round($y + 1.402 * ($cr - 128))));
                    $g = max(0, min(255, (int)round($y - 0.344136 * ($cb - 128) - 0.714136 * ($cr - 128))));
                    $b = max(0, min(255, (int)round($y + 1.772 * ($cb - 128))));
                    $palette[] = sprintf("#%02x%02x%02x", $r, $g, $b);
                }
                break;
            }
            $tag5_pos = strpos($esds_chunk, "\x05", $tag5_pos + 1);
        }
    }

    $stts_pos = strpos($target_trak_payload, 'stts');
    if ($stts_pos === false) { @fclose($fp); return false; }
    $stts_len = _mp4_read_uint32($target_trak_payload, $stts_pos - 4);
    $stts_data = substr($target_trak_payload, $stts_pos + 4, $stts_len - 8);
    $stts_count = _mp4_read_uint32($stts_data, 4);
    $sample_durations = array();
    $stts_offset = 8;
    for ($i = 0; $i < $stts_count && $stts_offset + 8 <= strlen($stts_data); $i++) {
        $count = _mp4_read_uint32($stts_data, $stts_offset);
        $delta = _mp4_read_uint32($stts_data, $stts_offset + 4);
        $stts_offset += 8;
        for ($j = 0; $j < $count; $j++) {
            $sample_durations[] = (int)(($delta / $timescale) * 1000);
        }
    }

    $stsz_pos = strpos($target_trak_payload, 'stsz');
    if ($stsz_pos === false) { @fclose($fp); return false; }
    $stsz_data = substr($target_trak_payload, $stsz_pos + 4);
    $default_sample_size = _mp4_read_uint32($stsz_data, 4);
    $sample_count = _mp4_read_uint32($stsz_data, 8);
    $sample_sizes = array();
    if ($default_sample_size > 0) {
        $sample_sizes = array_fill(0, $sample_count, $default_sample_size);
    } else {
        $stsz_offset = 12;
        for ($i = 0; $i < $sample_count && $stsz_offset + 4 <= strlen($stsz_data); $i++) {
            $sample_sizes[] = _mp4_read_uint32($stsz_data, $stsz_offset);
            $stsz_offset += 4;
        }
    }

    $stsc_pos = strpos($target_trak_payload, 'stsc');
    if ($stsc_pos === false) { @fclose($fp); return false; }
    $stsc_data = substr($target_trak_payload, $stsc_pos + 4);
    $stsc_count = _mp4_read_uint32($stsc_data, 4);
    $stsc_entries = array();
    $stsc_offset = 8;
    for ($i = 0; $i < $stsc_count && $stsc_offset + 12 <= strlen($stsc_data); $i++) {
        $stsc_entries[] = array(
            'first_chunk'    => _mp4_read_uint32($stsc_data, $stsc_offset),
            'samples_chunk'  => _mp4_read_uint32($stsc_data, $stsc_offset + 4),
            'desc_idx'       => _mp4_read_uint32($stsc_data, $stsc_offset + 8),
        );
        $stsc_offset += 12;
    }

    $chunk_offsets = array();
    $stco_pos = strpos($target_trak_payload, 'stco');
    if ($stco_pos !== false) {
        $stco_data = substr($target_trak_payload, $stco_pos + 4);
        $chunk_count = _mp4_read_uint32($stco_data, 4);
        $stco_offset = 8;
        for ($i = 0; $i < $chunk_count && $stco_offset + 4 <= strlen($stco_data); $i++) {
            $chunk_offsets[] = (float)sprintf("%u", _mp4_read_uint32($stco_data, $stco_offset));
            $stco_offset += 4;
        }
    } else {
        $co64_pos = strpos($target_trak_payload, 'co64');
        if ($co64_pos !== false) {
            $co64_data = substr($target_trak_payload, $co64_pos + 4);
            $chunk_count = _mp4_read_uint32($co64_data, 4);
            $co64_offset = 8;
            for ($i = 0; $i < $chunk_count && $co64_offset + 8 <= strlen($co64_data); $i++) {
                $hi = _mp4_read_uint32($co64_data, $co64_offset);
                $lo = _mp4_read_uint32($co64_data, $co64_offset + 4);
                $chunk_offsets[] = ((float)sprintf("%u", $hi) * 4294967296.0) + (float)sprintf("%u", $lo);
                $co64_offset += 8;
            }
        }
    }

    if (empty($chunk_offsets) || empty($sample_sizes) || empty($stsc_entries)) {
        @fclose($fp);
        return false;
    }

    $sample_offsets = array();
    $total_chunks = count($chunk_offsets);
    $sample_idx = 0;
    $num_stsc = count($stsc_entries);

    for ($c = 0; $c < $total_chunks; $c++) {
        $chunk_num = $c + 1;
        $samples_in_this_chunk = 1;
        for ($s_idx = 0; $s_idx < $num_stsc; $s_idx++) {
            if ($chunk_num >= $stsc_entries[$s_idx]['first_chunk']) {
                $samples_in_this_chunk = $stsc_entries[$s_idx]['samples_chunk'];
            } else {
                break;
            }
        }

        $current_offset = (float)$chunk_offsets[$c];
        for ($s = 0; $s < $samples_in_this_chunk && $sample_idx < count($sample_sizes); $s++) {
            $sample_offsets[] = $current_offset;
            $current_offset += (float)$sample_sizes[$sample_idx];
            $sample_idx++;
        }
    }

    $cues = array();
    $current_time_ms = $initial_delay_ms;
    $total_samples = count($sample_offsets);
    $cur_fp_pos = 0.0;

    for ($i = 0; $i < $total_samples; $i++) {
        $dur_ms = isset($sample_durations[$i]) ? $sample_durations[$i] : 2000;
        $offset = (float)$sample_offsets[$i];
        $size = isset($sample_sizes[$i]) ? $sample_sizes[$i] : 0;

        if ($size > 4) {
            $delta = $offset - $cur_fp_pos;
            if ($delta >= 0 && $delta < 2147483647 && $cur_fp_pos > 0) {
                @fseek($fp, (int)$delta, SEEK_CUR);
            } else {
                safe_fseek($fp, $offset);
            }
            $raw_sample = @fread($fp, $size);
            $cur_fp_pos = $offset + (float)strlen($raw_sample);

            if (strlen($raw_sample) >= 4) {
                $cue = parse_spu_dcsq($raw_sample, $current_time_ms, $dur_ms);
                if ($cue !== null) {
                    $cues[] = $cue;
                }
            }
        }
        $current_time_ms += $dur_ms;
    }
    @fclose($fp);

    if (empty($cues)) return false;

    // 驗證並修正異常時長
    $count = count($cues);
    for ($i = 0; $i < $count; $i++) {
        if ($cues[$i]['e'] <= $cues[$i]['s'] || $cues[$i]['e'] - $cues[$i]['s'] > 10000) {
            $cues[$i]['e'] = ($i + 1 < $count && $cues[$i+1]['s'] > $cues[$i]['s'] && ($cues[$i+1]['s'] - $cues[$i]['s']) <= 7000)
                ? $cues[$i+1]['s']
                : ($cues[$i]['s'] + 3500);
        }
    }

    return array(
        'success' => true,
        'type'    => 'graphic',
        'codec'   => 'dvd_subtitle',
        'palette' => (!empty($palette)) ? $palette : null,
        'cues'    => $cues,
    );
}

/**
 * 純 PHP 提取 MKV 中的 VobSub (S_VOBSUB) 封包與調色盤為結構化圖形字幕 Cues
 */
function extract_mkv_spu_cues_pure_php($filepath, $target_track_num = 0, $sub_index = 0) {
    if (!@file_exists($filepath) || !@is_readable($filepath)) return false;
    $fp = @fopen($filepath, 'rb');
    if (!$fp) return false;

    $magic = @fread($fp, 4);
    if (strlen($magic) < 4 || $magic !== "\x1A\x45\xDF\xA3") { @fclose($fp); return false; }
    @fseek($fp, 0, SEEK_SET);

    $MAX = 2 * 1024 * 1024;
    $tracks_buf = null;
    $guard = 0;

    while (@ftell($fp) < $MAX && $guard++ < 8000) {
        $id = _ebml_read_id($fp); if ($id === false) break;
        $size = _ebml_read_size($fp); if ($size === -2) break;
        if ($id === 0x1654AE6B) {
            if ($size > 0 && $size <= $MAX) {
                $tracks_buf = @fread($fp, $size);
            } elseif ($size === -1) {
                $tracks_buf = @fread($fp, 131072);
            }
            break;
        }
        if ($id === 0x1F43B675) break;
        if ($id === 0x18538067 || $size === -1) continue;
        if ($size > 0) @fseek($fp, $size, SEEK_CUR);
    }

    if ($tracks_buf === null || strlen($tracks_buf) === 0) {
        @fclose($fp);
        return false;
    }

    $pos = 0; $blen = strlen($tracks_buf); $g2 = 0;
    $matched_track_num = 0;
    $curr_sub_idx = 0;
    $palette = array();

    while ($pos < $blen && $g2++ < 2000) {
        $id = _ebml_buf_id($tracks_buf, $pos); if ($id === false) break;
        $size = _ebml_buf_size($tracks_buf, $pos); if ($size === -2) break;
        if ($id !== 0xAE || $size <= 0) {
            if ($size > 0 && $size !== -1) $pos += (int)$size; else break;
            continue;
        }

        $entry = substr($tracks_buf, $pos, (int)$size); $pos += (int)$size;
        $ep = 0; $elen = strlen($entry);
        $track_type = 0; $t_num = 0; $codec_id = ''; $codec_priv = ''; $g3 = 0;

        while ($ep < $elen && $g3++ < 500) {
            $eid = _ebml_buf_id($entry, $ep); if ($eid === false) break;
            $esize = _ebml_buf_size($entry, $ep); if ($esize === -2) break;
            if ($esize === -1) break;

            if ($esize > 0 && $esize <= $elen) {
                $edata = substr($entry, $ep, (int)$esize); $ep += (int)$esize;
                if ($eid === 0x83) { $track_type = _ebml_uint($edata, min((int)$esize, 4)); }
                elseif ($eid === 0xD7) { $t_num = _ebml_uint($edata, min((int)$esize, 4)); }
                elseif ($eid === 0x86) { $codec_id = rtrim($edata, "\0"); }
                elseif ($eid === 0x63A2) { $codec_priv = $edata; }
            } else {
                $ep += (int)$esize;
            }
        }

        if (!isset($first_any_track_num) && $t_num > 0) {
            $first_any_track_num = (int)$t_num;
        }

        if ($track_type === 0x11 || $track_type === 0) {
            $c_lower = strtolower($codec_id);
            if (strpos($c_lower, 'vobsub') !== false || strpos($c_lower, 'dvd') !== false || $codec_id === '') {
                if (!isset($first_vobsub_track)) {
                    $first_vobsub_track = (int)$t_num;
                }
                if (($target_track_num > 0 && (int)$t_num === (int)$target_track_num) || ($target_track_num <= 0 && $curr_sub_idx === $sub_index)) {
                    $matched_track_num = (int)$t_num;
                    if (!empty($codec_priv)) {
                        if (preg_match('/palette:\s*([0-9a-fA-F,\s]+)/i', $codec_priv, $pm)) {
                            $raw_p = explode(',', $pm[1]);
                            foreach ($raw_p as $rp) {
                                $rp = trim($rp);
                                if (strlen($rp) === 6) {
                                    $palette[] = '#' . strtolower($rp);
                                }
                            }
                        }
                    }
                    break;
                }
                $curr_sub_idx++;
            }
        }
    }

    if ($matched_track_num <= 0) {
        if (isset($first_vobsub_track)) {
            $matched_track_num = $first_vobsub_track;
        } elseif (isset($first_any_track_num)) {
            $matched_track_num = $first_any_track_num;
        } else {
            @fclose($fp);
            return false;
        }
    }

    @fseek($fp, 0, SEEK_SET);
    $cues = array();
    $cluster_time = 0;
    $g4 = 0;

    while (!@feof($fp) && $g4++ < 150000) {
        $id = _ebml_read_id($fp); if ($id === false) break;
        $size = _ebml_read_size($fp); if ($size === -2) break;

        if ($id === 0x1F43B675 || $id === 0x18538067 || $id === 0xA0 || $size === -1) {
            continue;
        }
        if ($id === 0xE7) {
            $data = ($size > 0 && $size <= 8) ? @fread($fp, $size) : '';
            $cluster_time = _ebml_uint($data, strlen($data));
            continue;
        }
        if (($id === 0xA3 || $id === 0xA1) && $size > 4 && $size < 131072) {
            $block_data = @fread($fp, $size);
            $pos = 0;
            $b_len = strlen($block_data);
            $t_num = _ebml_buf_size($block_data, $pos);
            if ((int)$t_num === (int)$matched_track_num && $pos + 3 <= $b_len) {
                $time_hi = ord($block_data[$pos++]);
                $time_lo = ord($block_data[$pos++]);
                $rel_time = ($time_hi << 8) | $time_lo;
                if ($rel_time & 0x8000) {
                    $rel_time = -((~$rel_time & 0xFFFF) + 1);
                }
                $pos++; // flags
                $payload = substr($block_data, $pos);
                $start_ms = $cluster_time + $rel_time;
                if ($start_ms < 0) $start_ms = 0;

                $cue = parse_spu_dcsq($payload, $start_ms, 0);
                if ($cue !== null) {
                    $cues[] = $cue;
                }
            }
            continue;
        }

        if ($size > 0) {
            @fseek($fp, (int)$size, SEEK_CUR);
        }
    }
    @fclose($fp);

    if (empty($cues)) return false;

    usort($cues, function($a, $b) { return $a['s'] - $b['s']; });

    $count = count($cues);
    for ($i = 0; $i < $count; $i++) {
        if ($cues[$i]['e'] <= $cues[$i]['s'] || $cues[$i]['e'] - $cues[$i]['s'] > 10000) {
            $cues[$i]['e'] = ($i + 1 < $count && $cues[$i+1]['s'] > $cues[$i]['s'] && ($cues[$i+1]['s'] - $cues[$i]['s']) <= 7000)
                ? $cues[$i+1]['s']
                : ($cues[$i]['s'] + 3500);
        }
    }

    return array(
        'success' => true,
        'type'    => 'graphic',
        'codec'   => 'dvd_subtitle',
        'palette' => (!empty($palette)) ? $palette : null,
        'cues'    => $cues,
    );
}

/**
 * 統一提取 DVD 圖形字幕 (SPU Cues) 引擎（具備 cache 資料夾持久快取保護與跨格式多引擎支援）
 */
function extract_dvd_subtitle_spu_cues($filepath, $sub_index, $track_num = 0) {
    $filepath = safe_fs_path($filepath);
    if (!@file_exists($filepath) || !@is_readable($filepath)) return false;
    @set_time_limit(120);

    $cache_dir = get_subtitles_cache_dir();
    $cache_key = get_video_subtitle_cache_key($filepath);
    $mtime = (int)@filemtime($filepath);
    $cache_file = rtrim($cache_dir, '/\\') . '/nas_sub_graphic_v3_' . $cache_key . '_' . intval($sub_index) . '.json';
    $legacy_cache_file = rtrim($cache_dir, '/\\') . '/nas_sub_graphic_v3_' . md5($filepath) . '_' . $mtime . '_' . intval($sub_index) . '.json';

    // 1. 若來源檔案未變更且快取存在且有效，立即返回快取內容（零重複解碼）
    if (@file_exists($cache_file) && @filesize($cache_file) > 50) {
        $cached_data = @file_get_contents($cache_file);
        if ($cached_data !== false && strpos($cached_data, '"success":true') !== false) {
            return $cached_data;
        }
    }
    if (@file_exists($legacy_cache_file) && @filesize($legacy_cache_file) > 50) {
        $cached_data = @file_get_contents($legacy_cache_file);
        if ($cached_data !== false && strpos($cached_data, '"success":true') !== false) {
            return $cached_data;
        }
    }

    $parts = get_filename_parts(fs_to_utf8(basename($filepath)));
    $ext = strtolower($parts['ext']);
    $data = false;

    // 2. 純 PHP 高速提取（僅在檔案 <= 2GB 時執行，避免 32-bit PHP fseek 溢位限制）
    $real_size = (float)get_real_filesize($filepath);
    $can_pure_php = ($real_size > 0.0 && $real_size <= 2000000000.0);

    if ($can_pure_php) {
        if (in_array($ext, array('mkv', 'webm', 'mka', 'mk3d'))) {
            $data = extract_mkv_spu_cues_pure_php($filepath, $track_num, $sub_index);
            if ($data === false || empty($data['cues'])) {
                $data = extract_mkv_spu_cues_pure_php($filepath, 0, $sub_index);
            }
        } elseif (in_array($ext, array('mp4', 'm4v', 'mov', '3gp'))) {
            $data = extract_mp4_spu_cues_pure_php($filepath, $track_num, $sub_index);
            if ($data === false || empty($data['cues'])) {
                $data = extract_mp4_spu_cues_pure_php($filepath, 0, $sub_index);
            }
        }
    }

    // 3. 系統 ffmpeg 快速複製回退（適用於大於 2GB 巨檔、32-bit PHP 尋標溢位或純 PHP 解析異常之檔案）
    if ($data === false || empty($data['cues'])) {
        $ffmpeg = find_ffmpeg_binary();
        if ($ffmpeg) {
            $tmp_mkv = rtrim($cache_dir, '/\\') . '/nas_tmp_spu_' . md5($filepath) . '_' . intval($sub_index) . '_' . time() . '.mkv';
            $dev_null = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '2>nul' : '2>/dev/null';

            // 優先嘗試使用 -c copy 僅複製字幕串流（不編碼影音），耗時極短且極省 CPU
            $cmd = escapeshellcmd($ffmpeg) . ' -y -loglevel error -i ' . safe_escapeshellarg($filepath) . ' -map 0:s:' . intval($sub_index) . ' -c copy -vn -an ' . safe_escapeshellarg($tmp_mkv) . ' ' . $dev_null;
            @shell_exec($cmd);

            // 若以 -map 0:s:X 複製失敗，嘗試以 track_num 索引
            if (!@file_exists($tmp_mkv) || @filesize($tmp_mkv) < 50) {
                if ($track_num > 0) {
                    $cmd = escapeshellcmd($ffmpeg) . ' -y -loglevel error -i ' . safe_escapeshellarg($filepath) . ' -map 0:' . intval($track_num) . ' -c copy -vn -an ' . safe_escapeshellarg($tmp_mkv) . ' ' . $dev_null;
                    @shell_exec($cmd);
                }
            }

            // 若 -c copy 未產生有效檔案，嘗試指定 -c:s copy
            if (!@file_exists($tmp_mkv) || @filesize($tmp_mkv) < 50) {
                $cmd = escapeshellcmd($ffmpeg) . ' -y -loglevel error -i ' . safe_escapeshellarg($filepath) . ' -map 0:s:' . intval($sub_index) . ' -c:s copy -vn -an ' . safe_escapeshellarg($tmp_mkv) . ' ' . $dev_null;
                @shell_exec($cmd);
            }

            // 若仍失敗，嘗試以 -c:s dvdsub
            if (!@file_exists($tmp_mkv) || @filesize($tmp_mkv) < 50) {
                $cmd = escapeshellcmd($ffmpeg) . ' -y -loglevel error -i ' . safe_escapeshellarg($filepath) . ' -map 0:s:' . intval($sub_index) . ' -c:s dvdsub -vn -an ' . safe_escapeshellarg($tmp_mkv) . ' ' . $dev_null;
                @shell_exec($cmd);
            }

            if (@file_exists($tmp_mkv) && @filesize($tmp_mkv) > 50) {
                $data = extract_mkv_spu_cues_pure_php($tmp_mkv, 0, 0);
            }
            if (@file_exists($tmp_mkv)) {
                @unlink($tmp_mkv);
            }
        }
    }

    if ($data && !empty($data['cues'])) {
        $json = json_encode($data);
        @file_put_contents($cache_file, $json);
        return $json;
    }

    return false;
}
