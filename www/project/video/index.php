<?php
// ═══════════════════════════════════════════════════════════════════════════
//  NAS 影片播放   (Multimedia Video Player) - 前端入口與視圖渲染
//  - 運行目標：APACHE 2.4 / PHP 7.3 (嚴格相容 PHP 5.6)
//  - 100% 離線優先 (Offline-First) 單檔前端視圖架構
//  - 後端專用邏輯已模組化解耦至：
//      * video_core.php      (核心組態、路徑與編碼安全)
//      * video_explorer.php  (目錄瀏覽、快取、外掛字幕)
//      * video_subtitles.php (純 PHP 內嵌字幕解析引擎)
//      * video_stream.php    (HTTP 206 Range 分段串流)
//      * video_api.php       (API 請求分流處理器)
// ═══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/auth.php';
require_pin_auth();

require_once __DIR__ . '/video_core.php';
require_once __DIR__ . '/video_explorer.php';
require_once __DIR__ . '/video_subtitles.php';
require_once __DIR__ . '/video_stream.php';
require_once __DIR__ . '/video_api.php';

// 處理 API 請求（若包含 action 參數，將直接處理並 exit）
handle_video_api_request($video_dirs, $video_dir);

// 預載前端初始資料（預載多目錄結構與全站最新影片清單）
$initial_data = browse_directory($video_dirs, '');
$initial_json = json_encode($initial_data, JSON_UNESCAPED_UNICODE);

// 預載全站最新影音（優先從快取讀取，效能極高且省電）
$initial_recent = get_recent_videos($video_dirs, 40, false);
$initial_recent_json = json_encode($initial_recent, JSON_UNESCAPED_UNICODE);

// 取得當前會話認證簽章（供 Google TV / 串流播放帶入以穿透認證閘門）
$current_auth_token = function_exists('auth_get_current_token') ? auth_get_current_token() : '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  
  <!-- ════ 嚴格遵守 Offline Inclusion 規則 ════ -->
  <link rel="stylesheet" href="offline/bundle.css">
  <script src="offline/qrcode.min.js"></script>
  
  <title>🎬 NAS 影片播放 — 遠端串流播放軟體</title>

  <script>
    // 注入全域認證 Token
    window.AUTH_TOKEN = <?php echo json_encode($current_auth_token); ?>;

    // ── 初始化：預防畫面閃爍 (FOUC) 與同步儀表板字體設定 ──
    (function () {
      try {
        var t = localStorage.getItem('fin-lab-theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
        var f = localStorage.getItem('fin-lab-font-scale') || '1';
        document.documentElement.style.fontSize = (17.5 * parseFloat(f)) + 'px';
      } catch (e) {}
    })();

    // ── 主題切換管理器 (雙向同步儀表板 fin-lab-theme 設定) ──
    window.toggleTheme = function () {
      var cur = document.documentElement.getAttribute('data-theme') || 'dark';
      var next = (cur === 'light') ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', next);
      try {
        localStorage.setItem('fin-lab-theme', next);
      } catch (e) {}
      window.updateThemeButtonUI(next);
    };

    window.updateThemeButtonUI = function (theme) {
      var btn = document.getElementById('theme-toggle-btn');
      if (!btn) return;
      var t = theme || document.documentElement.getAttribute('data-theme') || 'dark';
      btn.textContent = (t === 'light') ? '☀️' : '🌙';
      btn.title = (t === 'light') ? '目前為明亮主題，點擊切換為深色主題' : '目前為深色主題，點擊切換為明亮主題';
    };

    // 跨視窗/分頁實時監聽：若在儀表板切換主題，影音中心即時同步
    window.addEventListener('storage', function (e) {
      if (e.key === 'fin-lab-theme' && e.newValue) {
        document.documentElement.setAttribute('data-theme', e.newValue);
        window.updateThemeButtonUI(e.newValue);
      }
      if (e.key === 'fin-lab-font-scale' && e.newValue) {
        document.documentElement.style.fontSize = (17.5 * parseFloat(e.newValue)) + 'px';
      }
    });

    document.addEventListener('DOMContentLoaded', function () {
      window.updateThemeButtonUI();
    });
  </script>

  <style>
    /* ════════════════════════════════════════════════════════════════
       設計風格：工業極簡監控終端 + 深色科技劇院 (Dark Cinema Aesthetic)
       符合 100% 離線優先與自給自足規範（使用系統自帶字體與 CSS 變數）
    ════════════════════════════════════════════════════════════════ */

    :root {
      --bg: #0a0c0f;
      --bg2: #12151c;
      --bg3: #181d26;
      --card-bg: #131720;
      --card-hover: #1c2230;
      --border: #252932;
      --border2: #2e3340;
      --border-light: #323846;
      --cyan: #00d4aa;
      --cyan-glow: rgba(0, 212, 170, 0.18);
      --amber: #f59e0b;
      --red: #ef4444;
      --blue: #38bdf8;
      --purple: #c084fc;
      --green: #22c55e;
      --text: #e2e8f0;
      --text2: #94a3b8;
      --text3: #64748b;
      --mono: 'JetBrains Mono', 'Courier New', monospace;
      --sans: 'Noto Sans TC', 'Microsoft JhengHei', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      --topbar-bg: rgba(10, 12, 15, 0.94);
    }

    /* ════════════════════════════════════════════════════════════════
       明亮主題色彩變數 (同步儀表板 fin-lab-theme 規範)
    ════════════════════════════════════════════════════════════════ */
    [data-theme="light"] {
      --bg: #f1f5f9;
      --bg2: #ffffff;
      --bg3: #f8fafc;
      --card-bg: #ffffff;
      --card-hover: #f1f5f9;
      --border: #e2e8f0;
      --border2: #cbd5e1;
      --border-light: #cbd5e1;
      --text: #0f172a;
      --text2: #475569;
      --text3: #94a3b8;
      --cyan: #00a882;
      --cyan-glow: rgba(0, 168, 130, 0.12);
      --topbar-bg: rgba(255, 255, 255, 0.94);
    }

    [data-theme="light"] body {
      background-image:
        radial-gradient(ellipse 80% 40% at 50% 0%, rgba(0, 168, 130, 0.04) 0%, transparent 70%),
        repeating-linear-gradient(90deg, transparent, transparent 79px, rgba(203, 213, 225, 0.3) 79px, rgba(203, 213, 225, 0.3) 80px);
    }

    [data-theme="light"] .video-title {
      color: var(--text);
    }

    [data-theme="light"] .playlist-item {
      border: 1px solid var(--border);
    }

    [data-theme="light"] .preview-popup {
      border: 1px solid var(--border2);
      box-shadow: 0 20px 50px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
    }

    [data-theme="light"] .kbd {
      background: var(--bg3);
      border-color: var(--border2);
      color: var(--text2);
    }

    [data-theme="light"] .btn {
      border-color: var(--border);
      background: var(--bg2);
      color: var(--text);
    }

    [data-theme="light"] .btn:hover {
      border-color: var(--cyan);
      background: var(--bg3);
    }

    [data-theme="light"] .speed-select option,
    [data-theme="light"] .loop-select option {
      background: #ffffff;
      color: #0f172a;
    }

    [data-theme="light"] .tv-cast-modal {
      background: #ffffff;
      border-color: var(--border2);
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2), 0 0 20px rgba(0, 168, 130, 0.1);
    }
    [data-theme="light"] .tv-cast-header {
      background: #f8fafc;
    }
    [data-theme="light"] .tv-cast-section {
      background: #f8fafc;
    }
    [data-theme="light"] .tv-cast-section.highlight {
      background: rgba(56, 189, 248, 0.06);
    }
    [data-theme="light"] .tv-app-btn {
      background: #ffffff;
    }
    [data-theme="light"] .tv-url-input {
      background: #ffffff;
    }
    [data-theme="light"] .tv-cast-guide {
      background: #f8fafc;
    }
    [data-theme="light"] .brand-guide-btn {
      color: #0f172a;
      background: rgba(0, 168, 130, 0.12);
      border-color: rgba(0, 168, 130, 0.35);
    }
    [data-theme="light"] .brand-guide-btn:hover {
      color: #007055;
      background: rgba(0, 168, 130, 0.22);
      border-color: #00a882;
      box-shadow: 0 0 10px rgba(0, 168, 130, 0.25);
    }

    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      width: 100%;
      max-width: 100vw;
      overflow-x: hidden;
      overflow-x: clip;
      box-sizing: border-box;
    }

    html {
      font-size: 17.5px;
      scroll-behavior: smooth;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: var(--sans);
      line-height: 1.5;
      min-height: 100vh;
      overflow-x: hidden;
      overflow-x: clip;
      background-image:
        radial-gradient(ellipse 80% 40% at 50% 0%, rgba(0, 212, 170, 0.04) 0%, transparent 70%),
        repeating-linear-gradient(90deg, transparent, transparent 79px, rgba(37, 41, 50, 0.4) 79px, rgba(37, 41, 50, 0.4) 80px);
    }

    /* 頂部導航列 */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: var(--topbar-bg);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
      padding: 10px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: nowrap;   /* 永不折行 */
      overflow-x: auto;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      white-space: nowrap; /* 全列所有文字永不折行 */
    }
    .topbar::-webkit-scrollbar {
      display: none;
    }

    .topbar * {
      white-space: nowrap; /* 確保所有子元素文字均不折行 */
    }

    .topbar-left {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: nowrap;
      flex-shrink: 0;
      min-width: 0;
    }

    .brand-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      background: linear-gradient(135deg, rgba(0, 212, 170, 0.15), rgba(192, 132, 252, 0.15));
      border: 1px solid var(--cyan);
      border-radius: 8px;
      font-size: 0.95rem;
      font-weight: 800;
      color: var(--cyan);
      box-shadow: 0 0 16px var(--cyan-glow);
      text-decoration: none;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .brand-guide-btn {
      display: inline-flex !important;
      align-items: center;
      justify-content: center;
      gap: 4px;
      color: var(--text);
      font-size: 0.78rem;
      font-weight: 600;
      padding: 3px 8px;
      margin-left: 4px;
      background: rgba(0, 212, 170, 0.14);
      border: 1px solid rgba(0, 212, 170, 0.45);
      border-radius: 6px;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.2s ease;
      font-family: var(--sans);
      outline: none;
      flex-shrink: 0;
      line-height: 1;
      vertical-align: middle;
    }

    .brand-guide-btn:hover {
      color: #ffffff;
      background: rgba(0, 212, 170, 0.3);
      border-color: var(--cyan);
      box-shadow: 0 0 10px rgba(0, 212, 170, 0.4);
      transform: translateY(-1px);
    }

    .brand-guide-btn:active {
      transform: translateY(0);
    }

    .guide-tag-icon {
      font-size: 0.88rem;
      line-height: 1;
      display: inline-flex !important;
      align-items: center;
      justify-content: center;
      filter: drop-shadow(0 0 4px rgba(0, 212, 170, 0.6));
    }

    .guide-tag-text {
      font-size: 0.78rem;
      color: var(--text);
      font-weight: 600;
      display: inline-block;
      line-height: 1;
      white-space: nowrap;
    }

    .path-badge {
      font-family: var(--mono);
      font-size: 0.75rem;
      background: var(--bg3);
      padding: 4px 10px;
      border-radius: 6px;
      border: 1px solid var(--border);
      color: var(--text2);
      display: inline-flex;
      align-items: center;
      gap: 6px;
      max-width: 360px;
      min-width: 60px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex-shrink: 1; /* 空間不足時優先壓縮路徑文字並顯示 ... */
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: nowrap;
      flex-shrink: 0; /* 右側按鈕群組不可被壓縮變形 */
      min-width: 0;
    }

    .topbar .btn {
      white-space: nowrap !important; /* 按鈕字體永不折行 */
      flex-shrink: 0 !important;      /* 避免被擠壓成兩行字 */
    }

    /* 頂部導航列專屬圖示按鈕 (主題切換與鎖定會話按鈕大小 100% 同步一致) */
    .topbar-icon-btn {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 32px !important;
      height: 32px !important;
      min-width: 32px !important;
      padding: 0 !important;
      font-size: 0.85rem !important;
      line-height: 1 !important;
      border-radius: 6px;
      color: var(--text2);
      text-decoration: none;
      box-sizing: border-box;
      flex-shrink: 0 !important;
      text-align: center;
    }
    .topbar-icon-btn.theme-btn:hover {
      border-color: var(--cyan);
      color: var(--cyan);
      background: var(--bg3);
    }
    .topbar-icon-btn.lock-btn:hover {
      border-color: var(--amber);
      color: var(--amber);
      background: var(--bg3);
    }

    /* 統計（檔案數與容量）與預覽設定：空間不足時自動隱藏 */
    .topbar-stat-box,
    .topbar-preview-box {
      white-space: nowrap !important;
      flex-shrink: 0;
    }

    /* 寬度不足時：優先隱藏「檔案數與容量」統計 */
    @media (max-width: 1380px) {
      .topbar-stat-box {
        display: none !important;
      }
    }

    /* 寬度更進一步不足時：隱藏預覽時長 slider */
    @media (max-width: 1180px) {
      .topbar-preview-box {
        display: none !important;
      }
    }

    /* 動態寬度適應 class (JS 智慧偵測：字體放大或縮小視窗時無縫生效) */
    .topbar.hide-stat .topbar-stat-box {
      display: none !important;
    }
    .topbar.hide-preview .topbar-preview-box {
      display: none !important;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      font-family: var(--mono);
      cursor: pointer;
      border: 1px solid var(--border);
      background: var(--bg2);
      color: var(--text);
      text-decoration: none;
      transition: all 0.2s ease;
      user-select: none;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .btn:hover {
      border-color: var(--cyan);
      color: var(--cyan);
      background: var(--bg3);
      transform: translateY(-1px);
    }

    .btn-primary {
      background: rgba(0, 212, 170, 0.12);
      border-color: var(--cyan);
      color: var(--cyan);
    }

    .btn-primary:hover {
      background: var(--cyan);
      color: #000;
      box-shadow: 0 0 12px var(--cyan-glow);
    }

    .badge-new {
      font-size: 0.62rem;
      padding: 1px 5px;
      border-radius: 3px;
      background: linear-gradient(135deg, #ef4444, #f59e0b);
      color: #fff;
      font-weight: 800;
      font-family: var(--mono);
      letter-spacing: 0.5px;
      box-shadow: 0 0 8px rgba(239, 68, 68, 0.4);
      animation: pulseNew 2s infinite;
      display: inline-block;
      vertical-align: middle;
    }

    @keyframes pulseNew {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.82; transform: scale(1.05); }
    }

    .item-folder-tag {
      font-size: 0.68rem;
      color: var(--cyan);
      background: rgba(0, 212, 170, 0.08);
      padding: 1px 6px;
      border-radius: 3px;
      border: 1px solid rgba(0, 212, 170, 0.2);
      max-width: 170px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 3px;
    }

    .btn-jump-dir {
      padding: 2px 6px;
      font-size: 0.68rem;
      border-radius: 4px;
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--text2);
      cursor: pointer;
      font-family: var(--mono);
      transition: all 0.15s;
      display: inline-flex;
      align-items: center;
      gap: 3px;
    }

    .btn-jump-dir:hover {
      border-color: var(--cyan);
      color: var(--cyan);
      background: var(--bg2);
    }

    .btn-icon {
      padding: 6px 10px;
    }

    /* 麵包屑導航列（支援任意深度） */
    .breadcrumb-bar {
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      padding: 8px 24px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.8rem;
      font-family: var(--mono);
      color: var(--text2);
      overflow-x: auto;
      white-space: nowrap;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
    }
    .breadcrumb-bar::-webkit-scrollbar {
      display: none;
    }

    .breadcrumb-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      color: var(--text2);
      transition: color 0.15s;
    }

    .breadcrumb-item:hover {
      color: var(--cyan);
    }

    .breadcrumb-item.active {
      color: var(--cyan);
      font-weight: 700;
      cursor: default;
    }

    .breadcrumb-sep {
      color: var(--border-light);
    }

    /* 主頁面版面 (CSS Grid) */
    .main-wrapper {
      max-width: 1760px;
      width: 100%;
      min-width: 0;
      margin: 0 auto;
      padding: 18px 20px;
      display: grid;
      grid-template-columns: minmax(0, 1.85fr) minmax(380px, 1.15fr);
      gap: 20px;
      align-items: start;
      box-sizing: border-box;
      overflow-x: hidden;
      overflow-x: clip;
    }

    @media (max-width: 1100px) {
      .main-wrapper {
        grid-template-columns: 1fr;
        padding: 12px;
        overflow-x: hidden;
        overflow-x: clip;
      }
    }

    /* ── 播放器專區 (Left Stage) ── */
    .player-stage {
      display: flex;
      flex-direction: column;
      gap: 14px;
      width: 100%;
      min-width: 0;
      max-width: 100%;
      box-sizing: border-box;
    }

    .video-viewport-card {
      background: #000;
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: visible;
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.7);
      position: relative;
      z-index: 80;
    }

    .video-container {
      width: 100%;
      background: #000;
      display: flex;
      align-items: center;
      justify-content: center;
      aspect-ratio: 16 / 9;
      position: relative;
      border-radius: 11px 11px 0 0;
      overflow: hidden;
      transform: translateZ(0);
      backface-visibility: hidden;
    }

    video {
      width: 100%;
      height: 100%;
      object-fit: contain;
      background: #000;
      outline: none;
      cursor: pointer;
      transform: translateZ(0);
      backface-visibility: hidden;
    }

    /* 播放 / 暫停中央浮動視覺反饋徽章 */
    .video-play-indicator {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.6);
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: rgba(11, 15, 23, 0.78);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      border: 1px solid rgba(0, 212, 170, 0.5);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
      pointer-events: none;
      opacity: 0;
      z-index: 25;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6), 0 0 16px rgba(0, 212, 170, 0.25);
      transition: transform 0.22s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.22s ease-out;
      user-select: none;
    }

    .video-play-indicator.show {
      opacity: 1;
      transform: translate(-50%, -50%) scale(1.1);
    }

    /* ⏩/⏪ 快轉與倒退畫面動態 HUD 提示卡片 (淡入/淡出) */
    .video-seek-indicator {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.85);
      background: rgba(11, 15, 23, 0.86);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(0, 212, 170, 0.55);
      border-radius: 12px;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      color: #fff;
      pointer-events: none;
      opacity: 0;
      z-index: 26;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.75), 0 0 20px rgba(0, 212, 170, 0.25);
      transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      user-select: none;
    }

    .video-seek-indicator.show {
      opacity: 1;
      transform: translate(-50%, -50%) scale(1);
    }

    .video-seek-indicator.forward {
      border-color: rgba(0, 212, 170, 0.65);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.75), 0 0 22px rgba(0, 212, 170, 0.35);
    }

    .video-seek-indicator.rewind {
      border-color: rgba(234, 179, 8, 0.65);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.75), 0 0 22px rgba(234, 179, 8, 0.35);
    }

    .seek-indicator-icon {
      font-size: 1.8rem;
      line-height: 1;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .seek-indicator-content {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 2px;
    }

    .seek-indicator-delta {
      font-size: 1.05rem;
      font-weight: 700;
      font-family: var(--mono);
      letter-spacing: 0.5px;
      line-height: 1.2;
    }

    .video-seek-indicator.forward .seek-indicator-delta {
      color: var(--cyan);
    }

    .video-seek-indicator.rewind .seek-indicator-delta {
      color: #fbbf24;
    }

    .seek-indicator-time {
      font-size: 0.75rem;
      color: var(--text2);
      font-family: var(--mono);
      line-height: 1.2;
      white-space: nowrap;
    }

    .video-container:fullscreen .video-seek-indicator,
    .video-container:-webkit-full-screen .video-seek-indicator,
    .video-container:-moz-full-screen .video-seek-indicator,
    .video-container.mobile-web-fullscreen .video-seek-indicator,
    .video-container:fullscreen .video-play-indicator,
    .video-container:-webkit-full-screen .video-play-indicator,
    .video-container:-moz-full-screen .video-play-indicator,
    .video-container.mobile-web-fullscreen .video-play-indicator {
      z-index: 2147483647 !important;
    }

    /* 🎨 原生 DVD 圖形字幕 (SPU Canvas) 懸浮渲染層 */
    .subtitle-overlay-canvas {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 9;
      display: none;
    }

    .video-container:fullscreen {
      width: 100vw !important;
      height: 100vh !important;
      margin: 0 !important;
      border-radius: 0 !important;
      border-bottom: none !important;
      aspect-ratio: auto !important;
    }
    .video-container:-webkit-full-screen {
      width: 100vw !important;
      height: 100vh !important;
      margin: 0 !important;
      border-radius: 0 !important;
      border-bottom: none !important;
      aspect-ratio: auto !important;
    }
    .video-container:-moz-full-screen {
      width: 100vw !important;
      height: 100vh !important;
      margin: 0 !important;
      border-radius: 0 !important;
      border-bottom: none !important;
      aspect-ratio: auto !important;
    }

    /* 📱 行動裝置橫置滿版全螢幕 (CSS Web Fullscreen Fallback) */
    .video-container.mobile-web-fullscreen {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      width: 100dvw !important;
      height: 100dvh !important;
      max-width: 100vw !important;
      max-height: 100vh !important;
      z-index: 2147483647 !important;
      background: #000 !important;
      margin: 0 !important;
      padding: 0 !important;
      border: none !important;
      border-radius: 0 !important;
      border-bottom: none !important;
      aspect-ratio: auto !important;
      box-shadow: none !important;
    }
    .video-container.mobile-web-fullscreen video {
      width: 100% !important;
      height: 100% !important;
      object-fit: contain !important;
    }
    body.has-mobile-web-fs {
      overflow: hidden !important;
    }

    .video-container:fullscreen .subtitle-overlay-canvas,
    .video-container:-webkit-full-screen .subtitle-overlay-canvas,
    .video-container:-moz-full-screen .subtitle-overlay-canvas,
    .video-container.mobile-web-fullscreen .subtitle-overlay-canvas {
      width: 100% !important;
      height: 100% !important;
      max-width: 100vw !important;
      max-height: 100vh !important;
      z-index: 2147483647 !important;
      pointer-events: none;
    }

    /* 隱藏原生控制列有缺陷之全螢幕按鈕（因原生按鈕強制將 <video> 送入全螢幕而孤立 Canvas 字幕並產生狀態死鎖） */
    video::-webkit-media-controls-fullscreen-button {
      display: none !important;
    }
    video::-moz-media-controls-fullscreen-button {
      display: none !important;
    }

    /* ════ 影片內全螢幕懸浮控制項 (In-Video Fullscreen Overlay Control) ════ */
    .video-overlay-fs-btn {
      position: absolute;
      right: 10px;
      bottom: 8px;
      z-index: 50;
      width: 38px;
      height: 38px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      background: rgba(18, 21, 28, 0.88);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      border-radius: 6px;
      color: #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1.15rem;
      line-height: 1;
      padding: 0;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.25s ease, background 0.2s ease, border-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.6);
      user-select: none;
      -webkit-user-select: none;
    }

    /* 影片操作 Overlay 顯現時同步浮現，無活動時完全淡出隱藏不遮蔽觀看 */
    .video-overlay-fs-btn.visible {
      opacity: 1;
      pointer-events: auto;
    }

    .video-overlay-fs-btn:hover {
      background: rgba(0, 212, 170, 0.25);
      border-color: var(--cyan);
      color: var(--cyan);
      transform: scale(1.08);
      box-shadow: 0 0 12px rgba(0, 212, 170, 0.45);
    }

    .video-overlay-fs-btn:active {
      transform: scale(0.95);
    }

    /* 全螢幕模式下的影片內控制項定位與尺寸（保障最高層級呈現） */
    .video-container:fullscreen .video-overlay-fs-btn,
    .video-container:-webkit-full-screen .video-overlay-fs-btn,
    .video-container:-moz-full-screen .video-overlay-fs-btn,
    .video-container.mobile-web-fullscreen .video-overlay-fs-btn {
      right: 18px;
      bottom: 16px;
      width: 44px;
      height: 44px;
      font-size: 1.3rem;
      z-index: 2147483647 !important;
    }

    /* 快捷操作工具列 */
    .player-toolbar {
      background: var(--bg2);
      border-top: 1px solid var(--border);
      padding: 10px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
      width: 100%;
      max-width: 100%;
      min-width: 0;
      box-sizing: border-box;
      border-radius: 0 0 11px 11px;
      position: relative;
      z-index: 10;
    }

    .toolbar-group {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      min-width: 0;
      max-width: 100%;
    }

    .toolbar-label {
      font-size: 0.75rem;
      color: var(--text3);
      font-family: var(--mono);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* ⚡ 倍速下拉選單 (精簡空間模式) */
    .speed-select {
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--cyan);
      border-radius: 4px;
      padding: 3px 6px;
      font-size: 0.72rem;
      font-family: var(--mono);
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s ease;
      outline: none;
      line-height: 1.2;
    }

    .speed-select:hover,
    .speed-select:focus {
      border-color: var(--cyan);
      box-shadow: 0 0 8px rgba(0, 212, 170, 0.25);
    }

    .speed-select option {
      background: var(--bg2, #161a22);
      color: var(--text);
    }

    /* 🔁 循環播放模式選單 (單片循環、目錄循環、隨機循環、播畢即停) */
    .loop-select {
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--cyan);
      border-radius: 4px;
      padding: 3px 6px;
      font-size: 0.72rem;
      font-family: inherit;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s ease;
      outline: none;
      line-height: 1.2;
    }

    .loop-select:hover,
    .loop-select:focus {
      border-color: var(--cyan);
      box-shadow: 0 0 8px rgba(0, 212, 170, 0.25);
    }

    .loop-select option {
      background: var(--bg2, #161a22);
      color: var(--text);
    }

    .loop-select.mode-list {
      color: var(--cyan);
      border-color: rgba(0, 212, 170, 0.45);
    }
    .loop-select.mode-single {
      color: var(--amber);
      border-color: rgba(245, 158, 11, 0.55);
    }
    .loop-select.mode-random {
      color: var(--purple2, #a78bfa);
      border-color: rgba(167, 139, 250, 0.55);
    }
    .loop-select.mode-off {
      color: var(--text3, #888);
      border-color: var(--border);
    }

    .player-toolbar .btn-icon {
      padding: 3px 8px;
      font-size: 0.82rem;
      line-height: 1.2;
    }

    .pill-btn {
      padding: 3px 8px;
      font-size: 0.72rem;
      border-radius: 4px;
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--text2);
      cursor: pointer;
      font-family: var(--mono);
      font-weight: 600;
      transition: all 0.15s;
    }

    .pill-btn:hover {
      border-color: var(--cyan);
      color: var(--cyan);
    }

    .pill-btn.active {
      background: rgba(0, 212, 170, 0.2);
      border-color: var(--cyan);
      color: var(--cyan);
      font-weight: 700;
    }

    /* ⏱️ 快進 / 倒退 連體微型控制項 (極致節省空間) */
    .seek-segmented-group {
      display: inline-flex;
      align-items: center;
      border: 1px solid var(--border);
      border-radius: 4px;
      overflow: hidden;
      background: var(--bg3);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
      flex-shrink: 0;
    }

    .seek-segmented-group .seek-btn {
      padding: 3px 6px;
      font-size: 0.72rem;
      font-family: var(--mono);
      font-weight: 600;
      color: var(--text2);
      background: transparent;
      border: none;
      border-right: 1px solid var(--border);
      cursor: pointer;
      transition: all 0.15s ease;
      line-height: 1.2;
      user-select: none;
      white-space: nowrap;
    }

    .seek-segmented-group .seek-btn:last-child {
      border-right: none;
    }

    .seek-segmented-group .seek-btn.rewind:hover {
      background: rgba(234, 179, 8, 0.2);
      color: #fbbf24;
    }

    .seek-segmented-group .seek-btn.forward:hover {
      background: rgba(0, 212, 170, 0.2);
      color: var(--cyan);
    }

    .seek-segmented-group .seek-btn:active {
      transform: scale(0.94);
    }

    /* 🔗 分享/複製直連按鈕專屬科技風格 */
    .pill-btn.share-btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: rgba(168, 85, 247, 0.1);
      border-color: rgba(168, 85, 247, 0.35);
      color: #c084fc;
      padding: 3px 10px;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .pill-btn.share-btn:hover {
      background: rgba(168, 85, 247, 0.25);
      border-color: #c084fc;
      color: #fff;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.35);
    }

    .pill-btn.share-btn.copied {
      background: rgba(34, 197, 94, 0.25) !important;
      border-color: #22c55e !important;
      color: #4ade80 !important;
      box-shadow: 0 0 12px rgba(34, 197, 94, 0.4) !important;
    }

    /* 資料夾分享按鈕 (清單、麵包屑與詳情路徑共用) */
    .folder-share-btn {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      padding: 1px 7px;
      font-size: 0.68rem;
      font-family: var(--mono);
      font-weight: 500;
      color: #c084fc;
      background: rgba(168, 85, 247, 0.1);
      border: 1px solid rgba(168, 85, 247, 0.35);
      border-radius: 4px;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      line-height: 1.3;
      user-select: none;
      -webkit-user-select: none;
      vertical-align: middle;
    }

    .folder-share-btn:hover {
      background: rgba(168, 85, 247, 0.25);
      border-color: #c084fc;
      color: #fff;
      box-shadow: 0 0 8px rgba(168, 85, 247, 0.35);
    }

    .folder-share-btn:active {
      transform: scale(0.95);
    }

    .folder-share-btn.copied {
      background: rgba(34, 197, 94, 0.25) !important;
      border-color: #22c55e !important;
      color: #4ade80 !important;
      box-shadow: 0 0 10px rgba(34, 197, 94, 0.4) !important;
    }

    .breadcrumb-copy-btn {
      margin-left: 8px;
      flex-shrink: 0;
      align-self: center;
    }

    /* 🌟 WebGL 劇院環境動態光暈 (Ambilight Canvas) */
    .ambilight-canvas {
      position: absolute;
      top: -24px;
      left: -24px;
      width: calc(100% + 48px);
      height: calc(100% + 48px);
      pointer-events: none;
      z-index: -1;
      border-radius: 24px;
      filter: blur(40px) saturate(1.8) brightness(1.18);
      -webkit-filter: blur(40px) saturate(1.8) brightness(1.18);
      opacity: 0;
      transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1);
      transform: translateZ(0);
      will-change: opacity;
    }

    .ambilight-canvas.active {
      opacity: 0.85;
    }

    /* 環境光切換按鈕 */
    .pill-btn.ambilight-btn {
      color: var(--amber);
      border-color: rgba(245, 158, 11, 0.35);
      background: rgba(245, 158, 11, 0.08);
      padding: 4px 10px;
      font-size: 0.75rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      user-select: none;
    }

    .pill-btn.ambilight-btn:hover {
      border-color: var(--amber);
      color: #fff;
      background: rgba(245, 158, 11, 0.22);
      box-shadow: 0 0 10px rgba(245, 158, 11, 0.35);
    }

    .pill-btn.ambilight-btn.active {
      color: #fef08a;
      border-color: rgba(250, 204, 21, 0.6);
      background: rgba(250, 204, 21, 0.16);
      box-shadow: 0 0 12px rgba(250, 204, 21, 0.3);
    }

    /* 📺 電視/投射按鈕樣式 (Google TV / Chromecast / AirPlay) */
    .pill-btn.cast-btn {
      color: var(--blue);
      border-color: rgba(56, 189, 248, 0.35);
      background: rgba(56, 189, 248, 0.08);
      padding: 4px 10px;
      font-size: 0.75rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      user-select: none;
    }

    .pill-btn.cast-btn:hover {
      border-color: var(--blue);
      color: #fff;
      background: rgba(56, 189, 248, 0.22);
      box-shadow: 0 0 10px rgba(56, 189, 248, 0.35);
    }

    .pill-btn.cast-btn.active,
    .pill-btn.cast-btn.connected {
      color: #7dd3fc;
      border-color: rgba(56, 189, 248, 0.7);
      background: rgba(56, 189, 248, 0.25);
      box-shadow: 0 0 12px rgba(56, 189, 248, 0.4);
    }

    .pill-btn.tv-cast-btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: rgba(56, 189, 248, 0.1);
      border-color: rgba(56, 189, 248, 0.35);
      color: #38bdf8;
      padding: 3px 10px;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .pill-btn.tv-cast-btn:hover {
      background: rgba(56, 189, 248, 0.25);
      border-color: #38bdf8;
      color: #fff;
      box-shadow: 0 0 10px rgba(56, 189, 248, 0.35);
    }

    /* ════════════════════════════════════════════════════════════════
       📺 智慧電視 / Google TV 播放助手彈窗樣式
       ════════════════════════════════════════════════════════════════ */
    .tv-cast-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.75);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      z-index: 9998;
      display: none;
      opacity: 0;
      transition: opacity 0.25s ease;
    }
    .tv-cast-backdrop.active {
      display: block;
      opacity: 1;
    }

    .tv-cast-modal {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.94);
      width: min(92vw, 560px);
      max-height: 88vh;
      background: var(--bg2);
      border: 1px solid var(--border2);
      border-radius: 12px;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.7), 0 0 20px rgba(56, 189, 248, 0.15);
      z-index: 9999;
      display: none;
      flex-direction: column;
      opacity: 0;
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
      overflow: hidden;
      color: var(--text);
      font-family: var(--sans);
      box-sizing: border-box;
    }
    .tv-cast-modal.active {
      display: flex;
      opacity: 1;
      transform: translate(-50%, -50%) scale(1);
    }

    .tv-cast-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 18px;
      border-bottom: 1px solid var(--border);
      background: var(--bg3);
    }
    .tv-cast-title-wrap {
      flex: 1;
      min-width: 0;
      margin-right: 12px;
    }
    .tv-cast-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--text);
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .tv-cast-subtitle {
      font-size: 0.76rem;
      color: var(--blue);
      font-family: var(--mono);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      margin-top: 3px;
    }
    .tv-cast-close-btn {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text2);
      width: 32px;
      height: 32px;
      border-radius: 6px;
      font-size: 1.25rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.15s ease;
      flex-shrink: 0;
    }
    .tv-cast-close-btn:hover {
      background: rgba(239, 68, 68, 0.2);
      border-color: var(--red);
      color: #fca5a5;
    }

    .tv-cast-body {
      padding: 16px 18px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 14px;
      max-height: calc(88vh - 70px);
    }

    .tv-cast-section {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 14px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .tv-cast-section.highlight {
      border-color: rgba(56, 189, 248, 0.4);
      background: rgba(56, 189, 248, 0.03);
    }

    .tv-cast-section-header {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .tv-cast-section-title {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--text);
      margin: 0;
    }
    .tv-cast-badge {
      font-size: 0.68rem;
      font-family: var(--mono);
      font-weight: 700;
      padding: 2px 6px;
      border-radius: 4px;
      text-transform: uppercase;
    }
    .tv-cast-badge.chrome {
      background: rgba(0, 212, 170, 0.15);
      color: var(--cyan);
      border: 1px solid rgba(0, 212, 170, 0.4);
    }
    .tv-cast-badge.recommend {
      background: rgba(245, 158, 11, 0.15);
      color: var(--amber);
      border: 1px solid rgba(245, 158, 11, 0.4);
    }

    .tv-cast-desc {
      font-size: 0.78rem;
      color: var(--text2);
      line-height: 1.45;
      margin: 0;
    }

    .tv-cast-action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 16px;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      font-family: var(--sans);
      cursor: pointer;
      transition: all 0.2s ease;
      border: 1px solid var(--border2);
      text-decoration: none;
      user-select: none;
    }
    .primary-cast-btn {
      background: linear-gradient(135deg, rgba(56, 189, 248, 0.18), rgba(0, 212, 170, 0.18));
      border-color: rgba(56, 189, 248, 0.45);
      color: #38bdf8;
    }
    .primary-cast-btn:hover {
      background: linear-gradient(135deg, rgba(56, 189, 248, 0.32), rgba(0, 212, 170, 0.32));
      border-color: #38bdf8;
      color: #fff;
      box-shadow: 0 0 14px rgba(56, 189, 248, 0.35);
    }

    .tv-cast-tip {
      font-size: 0.74rem;
      color: var(--text3);
      background: rgba(255, 255, 255, 0.03);
      border-left: 3px solid var(--cyan);
      padding: 6px 10px;
      border-radius: 0 4px 4px 0;
      line-height: 1.4;
    }

    .tv-cast-troubleshoot {
      background: rgba(0, 0, 0, 0.22);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 8px 12px;
      font-size: 0.74rem;
      color: var(--text2);
    }
    .tv-cast-troubleshoot summary {
      cursor: pointer;
      font-weight: 600;
      color: var(--cyan);
      user-select: none;
      outline: none;
    }
    .tv-cast-troubleshoot .troubleshoot-content {
      margin-top: 8px;
      line-height: 1.5;
    }
    .tv-cast-troubleshoot ul {
      margin: 4px 0 0 16px;
      padding: 0;
    }
    .tv-cast-troubleshoot li {
      margin-bottom: 4px;
    }

    .tv-cast-app-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 8px;
    }
    .tv-app-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 6px;
      text-decoration: none;
      color: var(--text);
      transition: all 0.2s ease;
    }
    .tv-app-btn:hover {
      border-color: var(--blue);
      background: var(--card-hover);
      color: #fff;
    }
    .tv-app-btn.vlc:hover {
      border-color: #f97316;
      box-shadow: 0 0 10px rgba(249, 115, 22, 0.25);
    }
    .tv-app-btn.wvc:hover {
      border-color: #10b981;
      box-shadow: 0 0 10px rgba(16, 185, 129, 0.25);
    }
    .tv-app-btn.nplayer:hover {
      border-color: #38bdf8;
      box-shadow: 0 0 10px rgba(56, 189, 248, 0.25);
    }
    .tv-app-btn .app-icon {
      font-size: 1.3rem;
      line-height: 1;
    }
    .tv-app-btn .app-info {
      display: flex;
      flex-direction: column;
      min-width: 0;
    }
    .tv-app-btn .app-name {
      font-size: 0.8rem;
      font-weight: 700;
    }
    .tv-app-btn .app-sub {
      font-size: 0.68rem;
      color: var(--text3);
    }

    .tv-cast-url-box {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .tv-url-label {
      font-size: 0.74rem;
      font-weight: 600;
      color: var(--text2);
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .tv-url-input-group {
      display: flex;
      gap: 6px;
      align-items: center;
      width: 100%;
      box-sizing: border-box;
    }
    .tv-url-input {
      flex: 1;
      min-width: 0;
      width: 100%;
      box-sizing: border-box;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 5px;
      padding: 6px 10px;
      font-size: 0.75rem;
      font-family: var(--mono);
      color: var(--cyan);
      outline: none;
      text-overflow: ellipsis;
    }
    .tv-url-input:focus {
      border-color: var(--cyan);
    }
    .tv-copy-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      gap: 5px;
      background: rgba(56, 189, 248, 0.12);
      border: 1px solid rgba(56, 189, 248, 0.35);
      color: var(--blue);
      padding: 6px 12px;
      border-radius: 5px;
      font-size: 0.75rem;
      font-weight: 600;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.2s ease;
      box-sizing: border-box;
    }
    .tv-copy-btn:hover {
      background: rgba(56, 189, 248, 0.25);
      color: #fff;
      border-color: var(--blue);
      box-shadow: 0 0 8px rgba(56, 189, 248, 0.3);
    }
    .tv-copy-btn.copied {
      background: rgba(34, 197, 94, 0.2) !important;
      border-color: var(--green) !important;
      color: #4ade80 !important;
    }
    .tv-copy-btn.tv-qr-btn {
      background: rgba(6, 182, 212, 0.15);
      border-color: rgba(6, 182, 212, 0.4);
      color: var(--cyan);
    }
    .tv-copy-btn.tv-qr-btn:hover {
      background: rgba(6, 182, 212, 0.28);
      color: #fff;
      border-color: var(--cyan);
      box-shadow: 0 0 8px rgba(6, 182, 212, 0.35);
    }

    .tv-cast-guide {
      background: var(--bg3);
      border: 1px dashed var(--border2);
      border-radius: 8px;
      padding: 12px 14px;
    }
    .tv-guide-title {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--amber);
      margin-bottom: 6px;
    }
    .tv-guide-list {
      margin: 0;
      padding-left: 18px;
      font-size: 0.74rem;
      color: var(--text2);
      line-height: 1.5;
    }
    .tv-guide-list li {
      margin-bottom: 4px;
    }
    .tv-guide-list code {
      background: rgba(255, 255, 255, 0.08);
      padding: 1px 4px;
      border-radius: 3px;
      font-family: var(--mono);
      color: var(--cyan);
    }


    .toggle-wrap {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.78rem;
      font-family: var(--mono);
      color: var(--text2);
      cursor: pointer;
      user-select: none;
    }

    .toggle-wrap input[type="checkbox"] {
      cursor: pointer;
      accent-color: var(--cyan);
      width: 16px;
      height: 16px;
    }

    /* 浮動於影片畫面上的接續播放提示膠囊 (Floating Resume Overlay) */
    .resume-banner {
      display: none;
      position: absolute;
      bottom: 58px;
      left: 16px;
      z-index: 30;
      background: rgba(11, 15, 23, 0.88);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(0, 212, 170, 0.5);
      border-radius: 8px;
      padding: 8px 14px;
      align-items: center;
      gap: 12px;
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.75), 0 0 16px rgba(0, 212, 170, 0.2);
      animation: resumeSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: auto;
      max-width: calc(100% - 32px);
    }

    @keyframes resumeSlideIn {
      from {
        opacity: 0;
        transform: translateY(10px) scale(0.96);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .resume-banner.show {
      display: flex;
    }

    .resume-info {
      font-size: 0.82rem;
      color: var(--text, #f1f5f9);
      display: flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      user-select: none;
    }

    .resume-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }

    .resume-actions .btn {
      padding: 4px 12px;
      font-size: 0.78rem;
      border-radius: 5px;
      font-weight: 600;
    }

    .resume-btn-dismiss {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid var(--border);
      color: var(--text2);
      transition: all 0.15s ease;
    }
    .resume-btn-dismiss:hover {
      background: rgba(255, 255, 255, 0.16);
      color: var(--text);
    }

    @media (max-width: 640px) {
      .resume-banner {
        bottom: 50px;
        left: 8px;
        right: 8px;
        max-width: calc(100% - 16px);
        padding: 6px 10px;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: space-between;
      }
      .resume-info {
        font-size: 0.75rem;
      }
      .resume-actions .btn {
        padding: 3px 8px;
        font-size: 0.72rem;
      }
    }

    /* 影片資訊卡片 */
    .video-info-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 16px 20px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      width: 100%;
      max-width: 100%;
      min-width: 0;
      box-sizing: border-box;
      overflow: hidden;
    }

    .video-title-row {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 8px 10px;
      flex-wrap: wrap;
      width: 100%;
      min-width: 0;
    }

    .video-title {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.4;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin: 0;
      max-width: 100%;
      min-width: 0;
      word-break: break-all;
      overflow-wrap: anywhere;
    }

    .format-badge {
      font-size: 0.68rem;
      padding: 2px 7px;
      border-radius: 4px;
      font-family: var(--mono);
      font-weight: 800;
      letter-spacing: 0.5px;
      display: inline-flex;
      align-items: center;
      line-height: 1.2;
      flex-shrink: 0;
    }

    .format-badge.MP4, .format-badge.M4V {
      background: rgba(56, 189, 248, 0.15);
      border: 1px solid var(--blue);
      color: var(--blue);
    }

    .format-badge.WEBM, .format-badge.WEBA {
      background: rgba(0, 212, 170, 0.15);
      border: 1px solid var(--cyan);
      color: var(--cyan);
    }

    .format-badge.MKV {
      background: rgba(192, 132, 252, 0.15);
      border: 1px solid var(--purple);
      color: var(--purple);
    }

    .format-badge.MOV {
      background: rgba(251, 113, 133, 0.15);
      border: 1px solid #fb7185;
      color: #fb7185;
    }

    .format-badge.FLAC {
      background: rgba(245, 158, 11, 0.15);
      border: 1px solid var(--amber);
      color: var(--amber);
    }

    .format-badge.MP3 {
      background: rgba(244, 63, 94, 0.15);
      border: 1px solid #f43f5e;
      color: #f43f5e;
    }

    .format-badge.M4A, .format-badge.AAC {
      background: rgba(129, 140, 248, 0.15);
      border: 1px solid #818cf8;
      color: #818cf8;
    }

    .format-badge.WAV {
      background: rgba(34, 211, 238, 0.15);
      border: 1px solid #22d3ee;
      color: #22d3ee;
    }

    .format-badge.OGG, .format-badge.OGV, .format-badge.OGA, .format-badge.OPUS {
      background: rgba(34, 197, 94, 0.15);
      border: 1px solid var(--green);
      color: var(--green);
    }

    .format-badge.TS, .format-badge.3GP, .format-badge.3GPP, .format-badge.AVI {
      background: rgba(148, 163, 184, 0.15);
      border: 1px solid var(--border-light);
      color: var(--text2);
    }

    /* ════════════════════════════════════════════════════════════════
       伺服器端設定說明與常用技巧面板 (Server Guide & Pro Tips)
       終端工業風 + 深色科技劇院美學 (相容淺色主題與 100% 離線)
    ════════════════════════════════════════════════════════════════ */
    .server-guide-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 18px;
      width: 100%;
      box-sizing: border-box;
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.6);
      position: relative;
      animation: fadeInGuide 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes fadeInGuide {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .guide-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--border);
      min-width: 0;
      max-width: 100%;
      box-sizing: border-box;
    }

    .guide-header-left {
      display: flex;
      align-items: center;
      gap: 14px;
      min-width: 0;
      flex: 1 1 auto;
    }

    .guide-header-icon {
      font-size: 2.2rem;
      line-height: 1;
      filter: drop-shadow(0 0 12px rgba(0, 212, 170, 0.35));
      flex-shrink: 0;
    }

    .guide-header-text {
      min-width: 0;
    }

    .guide-title {
      font-size: 1.22rem;
      font-weight: 700;
      color: var(--text);
      letter-spacing: 0.5px;
      margin: 0;
      word-break: break-word;
    }

    .guide-subtitle {
      font-size: 0.82rem;
      color: var(--text2);
      margin-top: 3px;
      font-family: var(--mono);
      word-break: break-word;
    }

    .guide-header-right {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      flex-shrink: 0;
      min-width: 0;
      max-width: 100%;
    }

    .guide-return-btn {
      font-size: 0.85rem;
      padding: 8px 14px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border-radius: 8px;
      box-shadow: 0 0 12px rgba(0, 212, 170, 0.2);
      min-width: 0;
      max-width: 100%;
      box-sizing: border-box;
      overflow: hidden;
      white-space: nowrap;
    }

    .guide-return-icon {
      flex-shrink: 0;
      line-height: 1;
    }

    .guide-return-label {
      flex-shrink: 0;
      white-space: nowrap;
    }

    .guide-title-wrap {
      display: inline-flex;
      align-items: center;
      min-width: 0;
      flex: 0 1 auto;
      max-width: 320px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      opacity: 0.9;
      font-size: 0.82rem;
    }

    .guide-playing-title {
      display: inline-block;
      min-width: 0;
      max-width: 300px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      vertical-align: bottom;
    }

    /* 狀態徽章晶片列 */
    .guide-badges-row {
      display: flex;
      align-items: center;
      gap: 8px 12px;
      flex-wrap: wrap;
    }

    .guide-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.76rem;
      font-family: var(--mono);
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 4px 10px;
      color: var(--text2);
    }

    .badge-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
      flex-shrink: 0;
    }
    .dot-cyan { background: var(--cyan); box-shadow: 0 0 6px var(--cyan); }
    .dot-blue { background: var(--blue); box-shadow: 0 0 6px var(--blue); }
    .dot-purple { background: var(--purple); box-shadow: 0 0 6px var(--purple); }
    .dot-amber { background: var(--amber); box-shadow: 0 0 6px var(--amber); }
    .dot-green { background: var(--green); box-shadow: 0 0 6px var(--green); }

    .badge-label {
      color: var(--text3);
    }
    .badge-value {
      color: var(--text);
      font-weight: 600;
    }

    /* 警示與診斷膠囊 */
    .guide-alert-box {
      border-radius: 8px;
      padding: 14px 18px;
      display: flex;
      align-items: flex-start;
      gap: 14px;
      border: 1px solid var(--border);
      background: var(--bg2);
    }

    .guide-alert-box.warning {
      border-color: rgba(245, 158, 11, 0.35);
      background: rgba(245, 158, 11, 0.06);
    }
    .guide-alert-box.info {
      border-color: rgba(0, 212, 170, 0.25);
      background: rgba(0, 212, 170, 0.04);
    }

    .guide-alert-icon {
      font-size: 1.4rem;
      line-height: 1.2;
      flex-shrink: 0;
    }

    .guide-alert-body {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .guide-alert-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text);
    }

    .guide-alert-desc {
      font-size: 0.82rem;
      color: var(--text2);
      line-height: 1.6;
    }

    .guide-code-inline {
      color: var(--cyan);
      background: var(--bg3);
      padding: 2px 6px;
      border-radius: 4px;
      font-family: var(--mono);
      font-size: 0.85em;
      border: 1px solid var(--border);
    }

    .guide-path-switcher {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 6px;
      flex-wrap: wrap;
    }

    .guide-path-input {
      flex: 1;
      min-width: 240px;
      background: var(--bg);
      border: 1px solid var(--border);
      color: var(--text);
      padding: 7px 12px;
      border-radius: 6px;
      font-family: var(--mono);
      font-size: 0.82rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .guide-path-input:focus {
      border-color: var(--cyan);
    }

    .guide-reset-btn {
      background: var(--card2);
      border: 1px solid var(--border2);
      color: var(--text2);
      padding: 7px 14px;
      border-radius: 6px;
      font-size: 0.84rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .guide-reset-btn:hover {
      background: var(--hover);
      color: var(--text);
      border-color: var(--cyan);
    }
    .guide-reset-btn.active-test {
      background: rgba(245, 158, 11, 0.15);
      border: 1px solid rgba(245, 158, 11, 0.5);
      color: #fbbf24;
    }
    .guide-reset-btn.active-test:hover {
      background: rgba(245, 158, 11, 0.25);
      border-color: #f59e0b;
      color: #fef08a;
      box-shadow: 0 0 10px rgba(245, 158, 11, 0.25);
    }

    .guide-test-badge {
      background: rgba(245, 158, 11, 0.18);
      border: 1px solid rgba(245, 158, 11, 0.45);
      color: #fbbf24;
      font-size: 0.76rem;
      padding: 4px 10px;
      border-radius: 20px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      letter-spacing: 0.5px;
      animation: pulse-guide-badge 2s infinite ease-in-out;
      white-space: nowrap;
    }
    @keyframes pulse-guide-badge {
      0%, 100% { opacity: 0.9; transform: scale(1); }
      50% { opacity: 1; transform: scale(1.03); }
    }

    .guide-orig-note {
      display: inline-block;
      margin-top: 4px;
      font-size: 0.82rem;
      color: var(--text3);
    }
    .guide-code-inline-sub {
      background: rgba(255, 255, 255, 0.05);
      padding: 1px 5px;
      border-radius: 3px;
      font-family: var(--mono);
      font-size: 0.82em;
      color: var(--text2);
      border: 1px dashed var(--border);
    }
    .guide-dirs-summary {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 10px 14px;
      margin-top: 12px;
      font-size: 0.82rem;
      line-height: 1.6;
    }
    .guide-dirs-summary code {
      font-family: var(--mono);
      color: var(--cyan);
      background: rgba(0, 212, 170, 0.08);
      padding: 1px 5px;
      border-radius: 3px;
    }
    .guide-badge-testing {
      border-color: rgba(245, 158, 11, 0.4) !important;
      background: rgba(245, 158, 11, 0.08) !important;
    }

    /* 分頁導航列 */
    .guide-nav-tabs {
      display: flex;
      gap: 8px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 2px;
      overflow-x: auto;
      scrollbar-width: none;
    }
    .guide-nav-tabs::-webkit-scrollbar { display: none; }

    .guide-tab-btn {
      background: transparent;
      border: none;
      border-bottom: 2px solid transparent;
      padding: 8px 14px;
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--text2);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      transition: all 0.2s ease;
      border-radius: 6px 6px 0 0;
    }

    .guide-tab-btn:hover {
      color: var(--text);
      background: var(--bg2);
    }

    .guide-tab-btn.active {
      color: var(--cyan);
      border-bottom-color: var(--cyan);
      background: rgba(0, 212, 170, 0.06);
    }

    .tab-icon {
      font-size: 1rem;
    }

    /* 分頁內容與卡片網格 */
    .guide-tab-content {
      width: 100%;
    }

    .guide-tab-pane {
      display: none;
      width: 100%;
      animation: fadeInGuide 0.2s ease-out;
    }
    .guide-tab-pane.active {
      display: block;
    }

    .guide-cards-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
    }

    @media (max-width: 900px) {
      .guide-cards-grid {
        grid-template-columns: 1fr;
      }
    }

    .guide-section-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 16px 18px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .guide-section-card.full-width {
      grid-column: 1 / -1;
    }

    .guide-sec-header {
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 8px;
    }

    .sec-icon {
      font-size: 1.15rem;
    }

    .sec-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text);
      margin: 0;
    }

    .guide-sec-content {
      font-size: 0.82rem;
      color: var(--text2);
      line-height: 1.6;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .guide-sec-content p {
      margin: 0;
    }

    .guide-code-block {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 10px 12px;
      overflow-x: auto;
      font-family: var(--mono);
      font-size: 0.78rem;
      color: var(--cyan);
      margin: 4px 0;
    }
    .guide-code-block code {
      white-space: pre-wrap;
      word-break: break-all;
    }

    .guide-list {
      padding-left: 18px;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .guide-list li strong {
      color: var(--text);
    }

    /* 表格樣式 */
    .guide-table-wrap {
      overflow-x: auto;
      border: 1px solid var(--border);
      border-radius: 6px;
    }

    .guide-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.78rem;
      text-align: left;
    }

    .guide-table th, .guide-table td {
      padding: 8px 12px;
      border-bottom: 1px solid var(--border);
    }

    .guide-table th {
      background: var(--bg3);
      color: var(--text);
      font-weight: 600;
    }

    .guide-table tr:last-child td {
      border-bottom: none;
    }

    .guide-table td code {
      background: var(--bg);
      padding: 2px 5px;
      border-radius: 3px;
      border: 1px solid var(--border);
      color: var(--cyan);
      font-family: var(--mono);
      font-size: 0.9em;
      margin-right: 2px;
    }

    /* 快速鍵網格 */
    .guide-kbd-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px 16px;
    }

    @media (max-width: 700px) {
      .guide-kbd-grid {
        grid-template-columns: 1fr;
      }
    }

    .kbd-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.8rem;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 6px 10px;
    }

    .kbd-desc {
      color: var(--text2);
    }

    .guide-btn {
      background: rgba(0, 212, 170, 0.12);
      border: 1px solid var(--cyan);
      color: var(--cyan);
    }
    .guide-btn:hover {
      background: rgba(0, 212, 170, 0.22);
      box-shadow: 0 0 10px rgba(0, 212, 170, 0.25);
    }

    /* 明亮主題相容 */
    [data-theme="light"] .server-guide-card {
      background: #ffffff;
      border-color: var(--border);
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.08);
    }
    [data-theme="light"] .guide-section-card {
      background: #f8fafc;
      border-color: var(--border);
    }
    [data-theme="light"] .guide-badge {
      background: #f1f5f9;
      border-color: var(--border);
    }
    [data-theme="light"] .guide-alert-box {
      background: #f8fafc;
    }
    [data-theme="light"] .guide-code-block {
      background: #f1f5f9;
      color: #008768;
    }
    [data-theme="light"] .guide-table th {
      background: #f1f5f9;
    }
    [data-theme="light"] .kbd-item {
      background: #f8fafc;
    }
    [data-theme="light"] .guide-btn {
      background: rgba(0, 168, 130, 0.1);
      border-color: var(--cyan);
      color: #008768;
    }
    [data-theme="light"] .guide-reset-btn {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #475569;
    }
    [data-theme="light"] .guide-reset-btn:hover {
      background: #e2e8f0;
      color: #0f172a;
      border-color: var(--cyan);
    }
    [data-theme="light"] .guide-reset-btn.active-test {
      background: #fef3c7;
      border-color: #f59e0b;
      color: #b45309;
    }
    [data-theme="light"] .guide-reset-btn.active-test:hover {
      background: #fde68a;
      border-color: #d97706;
      color: #92400e;
    }
    [data-theme="light"] .guide-test-badge {
      background: #fef3c7;
      border-color: #f59e0b;
      color: #b45309;
    }
    [data-theme="light"] .guide-code-inline-sub {
      background: #f1f5f9;
      color: #475569;
      border-color: #cbd5e1;
    }
    [data-theme="light"] .guide-dirs-summary {
      background: #f8fafc;
      border-color: #e2e8f0;
    }
    [data-theme="light"] .guide-dirs-summary code {
      color: #008768;
      background: rgba(0, 168, 130, 0.1);
    }
    [data-theme="light"] .guide-badge-testing {
      background: #fef3c7 !important;
      border-color: #f59e0b !important;
    }

    /* 純音訊播放專屬視覺效果 */
    .audio-visual-stage {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 14px;
      background: radial-gradient(circle at center, rgba(0, 212, 170, 0.09) 0%, #05070a 80%);
      z-index: 2;
      pointer-events: none;
    }

    .audio-disc-wrap {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      background: repeating-radial-gradient(#111318, #111318 4px, #181d26 5px, #181d26 6px);
      border: 3px solid rgba(0, 212, 170, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 0 30px rgba(0, 212, 170, 0.15), inset 0 0 20px rgba(0, 0, 0, 0.8);
      animation: rotateVinyl 12s linear infinite;
      animation-play-state: paused;
    }

    .audio-disc-wrap.playing {
      animation-play-state: running;
    }

    .audio-disc-center {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--cyan), var(--purple));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    }

    @keyframes rotateVinyl {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .audio-track-info {
      text-align: center;
      max-width: 80%;
    }

    .audio-track-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .audio-spectrum-bars {
      display: flex;
      align-items: flex-end;
      gap: 4px;
      height: 24px;
    }

    .audio-spectrum-bars span {
      width: 4px;
      background: var(--cyan);
      border-radius: 2px;
      height: 20%;
      transition: height 0.15s;
    }

    .audio-spectrum-bars.playing span {
      animation: specBounce 0.8s infinite alternate ease-in-out;
    }
    .audio-spectrum-bars.playing span:nth-child(1) { animation-delay: 0.05s; }
    .audio-spectrum-bars.playing span:nth-child(2) { animation-delay: 0.2s; }
    .audio-spectrum-bars.playing span:nth-child(3) { animation-delay: 0.35s; }
    .audio-spectrum-bars.playing span:nth-child(4) { animation-delay: 0.1s; }
    .audio-spectrum-bars.playing span:nth-child(5) { animation-delay: 0.4s; }
    .audio-spectrum-bars.playing span:nth-child(6) { animation-delay: 0.25s; }
    .audio-spectrum-bars.playing span:nth-child(7) { animation-delay: 0.15s; }
    .audio-spectrum-bars.playing span:nth-child(8) { animation-delay: 0.3s; }

    @keyframes specBounce {
      from { height: 15%; }
      to { height: 100%; }
    }

    .video-meta-pills {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 0.78rem;
      color: var(--text2);
      font-family: var(--mono);
      flex-wrap: wrap;
      width: 100%;
      min-width: 0;
    }

    .meta-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: var(--bg3);
      padding: 3px 8px;
      border-radius: 4px;
      border: 1px solid var(--border);
      max-width: 100%;
      min-width: 0;
      word-break: break-all;
      overflow-wrap: anywhere;
      white-space: normal;
    }

    .meta-pill span {
      min-width: 0;
      word-break: break-all;
      overflow-wrap: anywhere;
    }

    .shortcuts-box {
      margin-top: 4px;
      padding-top: 10px;
      border-top: 1px dashed var(--border);
      display: flex;
      align-items: center;
      gap: 14px;
      font-size: 0.72rem;
      color: var(--text3);
      font-family: var(--mono);
      flex-wrap: wrap;
    }

    .kbd {
      background: var(--bg2);
      border: 1px solid var(--border-light);
      border-radius: 4px;
      padding: 2px 5px;
      color: var(--cyan);
      font-weight: 700;
    }

    /* ── 右側播放清單 (Right Drawer) ── */
    .playlist-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      height: calc(100vh - 120px);
      min-height: 620px;
      position: sticky;
      top: 75px;
    }

    /* ── 手機版側邊滑出選單遮罩與樣式 (Mobile Slide-out Drawer) ── */
    .playlist-overlay {
      display: none;
    }

    .mobile-drawer-close,
    .mobile-drawer-fab {
      display: none;
    }

    @media (max-width: 1024px) {
      /* 頂部導航列在手機/平板隨手勢滾出視窗，使影片能緊密吸附置頂 */
      .topbar {
        position: static;
      }

      .main-wrapper {
        grid-template-columns: 1fr;
        padding: 12px;
        overflow-x: hidden;
        overflow-x: clip;
      }

      .player-stage {
        overflow: visible;
      }

      /* ── 手機/平板版面：攤平播放器容器，讓 .video-container 能相對於 player-stage 黏著置頂 ── */
      .video-viewport-card {
        display: contents;
      }

      /* 🌟 WebGL 劇院環境光暈在手機/平板版面隱藏 (全螢幕貼邊無邊框光暈需求，且節省電量並徹底避免版面溢出) */
      .ambilight-canvas {
        display: none !important;
      }

      #btn-ambilight-toggle {
        display: none !important;
      }

      #btn-fullscreen #btn-fs-text {
        display: none !important;
      }

      #btn-cast #btn-cast-text {
        display: none !important;
      }

      .video-container {
        position: -webkit-sticky;
        position: sticky;
        top: 0;
        z-index: 500;
        width: calc(100% + 24px);
        margin-left: -12px;
        margin-right: -12px;
        border-radius: 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.85);
        border-bottom: 1px solid rgba(56, 189, 248, 0.3);
      }

      /* 字幕列與輔助工具列攤平後各自獨立為質感深色小卡片 */
      .subtitle-bar {
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg2);
      }

      .player-toolbar {
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg2);
      }

      /* 手機/平板版面：點擊導航路徑與麵包屑觸發抽屜反饋 */
      .breadcrumb-bar {
        cursor: pointer;
        user-select: none;
        -webkit-user-select: none;
      }
      .breadcrumb-bar:active {
        background: rgba(0, 212, 170, 0.08);
      }
      .breadcrumb-item {
        cursor: pointer;
      }
      .breadcrumb-item.active {
        cursor: pointer;
      }
      #current-path-pill {
        cursor: pointer;
      }
      #current-path-pill:active {
        border-color: var(--cyan);
        background: rgba(0, 212, 170, 0.1);
      }

      /* 遮罩背景 (Backdrop Blur) */
      .playlist-overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 1050;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
      }

      .playlist-overlay.active {
        opacity: 1;
        pointer-events: auto;
      }

      /* 右側滑出抽屜 */
      .playlist-card {
        position: fixed !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 88vw;
        max-width: 440px;
        height: 100vh !important;
        max-height: 100vh !important;
        border-radius: 16px 0 0 16px;
        border-right: none;
        border-top: none;
        border-bottom: none;
        border-left: 1px solid var(--border);
        background: var(--bg);
        z-index: 1060;
        transform: translateX(105%);
        transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: -10px 0 40px rgba(0, 0, 0, 0.85), 0 0 0 1px rgba(255, 255, 255, 0.05);
        display: flex;
        flex-direction: column;
        overflow: hidden;
      }

      .playlist-card.drawer-open {
        transform: translateX(0) !important;
      }

      /* 抽屜頂部關閉按鈕 */
      .mobile-drawer-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 9px;
        font-size: 0.85rem;
        color: var(--text2);
        background: var(--bg3);
        border: 1px solid var(--border);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
      }

      .mobile-drawer-close:hover {
        color: #fff;
        border-color: var(--red);
        background: rgba(239, 68, 68, 0.15);
      }

      /* 右下角固定懸浮按鈕 (FAB) - 畫面無操作時縮小為半透明圓形圖示，有操作時平滑展開 */
      .mobile-drawer-fab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: fixed;
        bottom: 20px;
        right: 16px;
        z-index: 1040;
        background: rgba(17, 20, 26, 0.72);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        color: var(--cyan);
        border: 1px solid rgba(0, 212, 170, 0.38);
        border-radius: 50%;
        width: 38px;
        height: 38px;
        padding: 0;
        font-size: 0.85rem;
        font-weight: 700;
        font-family: var(--mono);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.45);
        cursor: pointer;
        opacity: 0.4;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        -webkit-tap-highlight-color: transparent;
        overflow: hidden;
      }

      .mobile-drawer-fab .fab-icon {
        font-size: 1.15rem;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }

      .mobile-drawer-fab .fab-text {
        max-width: 0;
        opacity: 0;
        overflow: hidden;
        white-space: nowrap;
        margin-left: 0;
        transition: max-width 0.3s ease, opacity 0.25s ease, margin 0.25s ease;
      }

      /* 活躍狀態：畫面有操作、觸碰滾動或懸停時展開為完整膠囊按鈕 */
      .mobile-drawer-fab.active,
      .mobile-drawer-fab:hover,
      .mobile-drawer-fab:focus-visible {
        opacity: 1;
        width: auto;
        border-radius: 999px;
        padding: 8px 15px;
        background: linear-gradient(135deg, #111318 0%, #1a1e28 100%);
        border-color: var(--cyan);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.6), 0 0 16px var(--cyan-glow);
      }

      .mobile-drawer-fab.active .fab-text,
      .mobile-drawer-fab:hover .fab-text,
      .mobile-drawer-fab:focus-visible .fab-text {
        max-width: 80px;
        opacity: 1;
        margin-left: 6px;
      }

      .mobile-drawer-fab:active {
        transform: scale(0.92);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
      }

      /* 側邊播放清單開啟時暫時隱藏 FAB，防範圖層重疊 */
      .mobile-drawer-fab.drawer-opened {
        opacity: 0 !important;
        pointer-events: none !important;
        transform: scale(0.8);
      }

      /* 手機/平板抽屜頂部標頭瘦身：隱藏被擠壓的標題文字 */
      #playlist-title-text {
        display: none !important;
      }
    }

    .playlist-header {
      padding: 14px 16px;
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 10px;
      flex-shrink: 0;
    }

    .playlist-header-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
    }

    .playlist-header-actions {
      display: flex;
      align-items: center;
      gap: 5px;
      flex-shrink: 0;
    }

    .playlist-header-actions .btn {
      padding: 3px 7px;
      font-size: 0.75rem;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .playlist-title {
      font-size: 0.92rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text);
    }

    .playlist-counter {
      font-family: var(--mono);
      font-size: 0.75rem;
      color: var(--cyan);
      background: rgba(0, 212, 170, 0.1);
      padding: 2px 8px;
      border-radius: 12px;
      border: 1px solid rgba(0, 212, 170, 0.3);
    }

    /* 搜尋列與導航工具列 */
    .search-row {
      display: flex;
      align-items: center;
      gap: 8px;
      width: 100%;
    }

    .search-box {
      position: relative;
      display: flex;
      align-items: center;
      flex: 1;
      min-width: 0;
    }

    .btn-nav-toggle,
    .btn-recent-sidebar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      padding: 7px 12px;
      font-size: 0.8rem;
      font-weight: 600;
      white-space: nowrap;
      flex-shrink: 0;
      border-radius: 6px;
      cursor: pointer;
      background: rgba(245, 158, 11, 0.08);
      border: 1px solid rgba(245, 158, 11, 0.35);
      color: var(--amber);
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      height: 34px;
      box-sizing: border-box;
      user-select: none;
      -webkit-user-select: none;
    }

    .btn-nav-toggle:hover,
    .btn-recent-sidebar:hover {
      border-color: var(--amber);
      color: #000;
      background: var(--amber);
      box-shadow: 0 0 12px rgba(245, 158, 11, 0.4);
    }

    /* 處於最新模式時，按鈕動態切換為「回到根目錄」青色科技樣式 */
    .btn-nav-toggle.is-recent,
    .btn-recent-sidebar.is-recent {
      border-color: var(--cyan);
      color: var(--cyan);
      background: rgba(0, 212, 170, 0.12);
      box-shadow: 0 0 10px var(--cyan-glow);
    }

    .btn-nav-toggle.is-recent:hover,
    .btn-recent-sidebar.is-recent:hover {
      border-color: var(--cyan);
      color: #000;
      background: var(--cyan);
      box-shadow: 0 0 14px var(--cyan-glow);
    }

    .btn-nav-toggle:active,
    .btn-recent-sidebar:active {
      transform: scale(0.95);
    }

    .btn-nav-toggle .btn-nav-icon,
    .btn-recent-sidebar .btn-nav-icon {
      font-size: 0.95rem;
      line-height: 1;
    }

    .btn-nav-toggle .btn-nav-text,
    .btn-recent-sidebar .btn-nav-text {
      font-size: 0.78rem;
      letter-spacing: 0.5px;
    }

    @media (max-width: 420px) {
      .btn-nav-toggle .btn-nav-text,
      .btn-recent-sidebar .btn-nav-text {
        display: none;
      }
      .btn-nav-toggle,
      .btn-recent-sidebar {
        padding: 7px 9px;
      }
    }

    .search-input {
      width: 100%;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 7px 32px 7px 10px;
      font-size: 0.8rem;
      color: var(--text);
      outline: none;
      transition: all 0.2s;
    }

    .search-input:focus {
      border-color: var(--cyan);
      box-shadow: 0 0 8px var(--cyan-glow);
    }

    .search-clear-btn {
      position: absolute;
      right: 8px;
      background: transparent;
      border: none;
      color: var(--text3);
      cursor: pointer;
      font-size: 0.9rem;
      display: none;
    }

    .playlist-item.item-parent {
      background: rgba(0, 212, 170, 0.05);
      border: 1px dashed rgba(0, 212, 170, 0.35);
    }

    .playlist-item.item-parent:hover {
      background: rgba(0, 212, 170, 0.15);
      border-color: var(--cyan);
      box-shadow: 0 0 10px rgba(0, 212, 170, 0.2);
    }

    .playlist-item.item-folder {
      background: var(--bg3);
      border: 1px solid var(--border);
    }

    .playlist-item.item-folder:hover {
      background: var(--card-hover);
      border-color: rgba(245, 158, 11, 0.5);
      box-shadow: 0 0 10px rgba(245, 158, 11, 0.15);
    }

    .item-icon {
      min-width: 28px;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.15rem;
      flex-shrink: 0;
    }

    .item-badge-folder {
      background: rgba(245, 158, 11, 0.12);
      border: 1px solid rgba(245, 158, 11, 0.35);
      color: var(--amber);
      padding: 1px 6px;
      border-radius: 3px;
      font-size: 0.65rem;
      font-family: var(--mono);
      font-weight: 600;
    }

    .item-action-hint {
      font-size: 0.72rem;
      color: var(--text3);
      font-family: var(--mono);
      margin-left: auto;
      flex-shrink: 0;
      transition: color 0.15s, transform 0.15s;
    }

    .playlist-item:hover .item-action-hint {
      color: var(--cyan);
      transform: translateX(2px);
    }

    /* 影片清單項目：嚴禁垂直壓縮、支援平滑滾動 */
    .playlist-items {
      flex: 1 1 0;
      min-height: 0;
      overflow-y: auto;
      overflow-x: hidden;
      padding: 10px 8px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      scrollbar-width: thin;
      scrollbar-color: var(--border-light) var(--bg);
    }

    .playlist-items::-webkit-scrollbar {
      width: 6px;
    }

    .playlist-items::-webkit-scrollbar-track {
      background: var(--bg);
    }

    .playlist-items::-webkit-scrollbar-thumb {
      background: var(--border-light);
      border-radius: 3px;
    }

    .playlist-items::-webkit-scrollbar-thumb:hover {
      background: var(--cyan);
    }

    .playlist-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      min-height: 54px;
      flex-shrink: 0;
      background: var(--bg2);
      border: 1px solid transparent;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.15s ease;
      position: relative;
      overflow: hidden;
    }

    .playlist-item:hover {
      background: var(--card-hover);
      border-color: var(--border-light);
      transform: translateX(2px);
    }

    .playlist-item.active {
      background: rgba(0, 212, 170, 0.08);
      border-color: var(--cyan);
      box-shadow: 0 0 12px rgba(0, 212, 170, 0.15);
    }

    .item-index {
      font-family: var(--mono);
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--text3);
      min-width: 24px;
      text-align: center;
    }

    .playlist-item.active .item-index {
      color: var(--cyan);
    }

    .item-body {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .item-title {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      line-height: 1.3;
    }

    .playlist-item.active .item-title {
      color: var(--cyan);
    }

    .item-meta {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.7rem;
      color: var(--text3);
      font-family: var(--mono);
      flex-wrap: wrap;
    }

    .item-progress-bar {
      position: absolute;
      bottom: 0;
      left: 0;
      height: 2.5px;
      background: var(--cyan);
      opacity: 0.8;
      transition: width 0.3s;
    }

    .equalizer-wave {
      display: none;
      align-items: flex-end;
      gap: 2px;
      height: 14px;
      width: 14px;
      flex-shrink: 0;
    }

    .playlist-item.active .equalizer-wave {
      display: flex;
    }

    .equalizer-bar {
      width: 2.5px;
      background: var(--cyan);
      border-radius: 1px;
      animation: bounce 1s infinite alternate ease-in-out;
    }

    .equalizer-bar:nth-child(1) { height: 40%; animation-delay: 0.1s; }
    .equalizer-bar:nth-child(2) { height: 100%; animation-delay: 0.3s; }
    .equalizer-bar:nth-child(3) { height: 60%; animation-delay: 0.2s; }

    @keyframes bounce {
      from { height: 20%; }
      to { height: 100%; }
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-4px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .loading-box {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      padding: 50px 20px;
      color: var(--text3);
      font-size: 0.82rem;
      font-family: var(--mono);
    }

    .spinner {
      width: 28px;
      height: 28px;
      border: 3px solid var(--border);
      border-top-color: var(--cyan);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* 空白狀態 */
    .empty-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 32px 24px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
      max-width: 680px;
      margin: 40px auto;
    }

    .empty-icon {
      font-size: 3rem;
      color: var(--amber);
      filter: drop-shadow(0 0 10px rgba(245, 158, 11, 0.4));
    }

    /* ═══════════════════════════════════════════════════════
       字幕控制列 (Subtitle Control Bar)
    ═══════════════════════════════════════════════════════ */
    .subtitle-bar {
      background: var(--bg2);
      border-top: 1px solid var(--border);
      padding: 9px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      animation: fadeIn 0.25s ease;
      position: relative;
      z-index: 60;
      overflow: visible;
    }

    /* ═══════════════════════════════════════════════════════
       字幕標籤按鈕化與下拉選單 (Subtitle Dropdown in Label)
    ═══════════════════════════════════════════════════════ */
    .subtitle-bar-label {
      font-size: 0.76rem;
      color: var(--text2);
      font-family: var(--mono);
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 4px 10px;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: var(--bg3);
      cursor: pointer;
      position: relative;
      z-index: 60;
      user-select: none;
      transition: all 0.15s ease;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .subtitle-bar-label:hover {
      border-color: var(--purple, #c084fc);
      color: var(--purple, #c084fc);
      background: rgba(192, 132, 252, 0.08);
    }

    .subtitle-bar-label.dropdown-open {
      border-color: var(--purple, #c084fc);
      color: var(--purple, #c084fc);
      background: rgba(192, 132, 252, 0.15);
      box-shadow: 0 0 10px rgba(192, 132, 252, 0.25);
      z-index: 85;
    }

    .sub-label-title {
      color: var(--text2);
      font-weight: 600;
    }

    .subtitle-active-tag {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--cyan);
      max-width: 150px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .sub-caret {
      font-size: 0.65rem;
      color: var(--text3);
      transition: transform 0.2s ease;
      margin-left: 2px;
    }

    .subtitle-bar-label.dropdown-open .sub-caret {
      transform: rotate(180deg);
      color: var(--purple, #c084fc);
    }

    .subtitle-badge {
      background: linear-gradient(135deg, rgba(192, 132, 252, 0.18), rgba(56, 189, 248, 0.12));
      border: 1px solid rgba(192, 132, 252, 0.4);
      color: var(--purple);
      font-size: 0.62rem;
      font-family: var(--mono);
      font-weight: 800;
      padding: 1px 5px;
      border-radius: 3px;
      letter-spacing: 0.5px;
      animation: pulseNew 2.5s infinite;
    }

    /* 下拉選單彈出盒 */
    .subtitle-dropdown-menu {
      display: none;
      position: absolute;
      top: calc(100% + 6px);
      left: 0;
      min-width: 220px;
      max-width: min(88vw, 320px);
      background: rgba(13, 17, 23, 0.98);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(192, 132, 252, 0.4);
      border-radius: 8px;
      box-shadow: 0 16px 48px rgba(0, 0, 0, 0.95), 0 0 16px rgba(192, 132, 252, 0.2);
      z-index: 1000;
      overflow: hidden;
      animation: fadeIn 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .subtitle-dropdown-menu.show {
      display: block;
    }

    .subtitle-dropdown-header {
      padding: 7px 12px;
      font-size: 0.68rem;
      color: var(--text3);
      border-bottom: 1px solid var(--border);
      background: rgba(0, 0, 0, 0.25);
      display: flex;
      align-items: center;
      justify-content: space-between;
      letter-spacing: 0.5px;
      font-family: var(--mono);
    }

    .subtitle-dropdown-count {
      color: var(--purple, #c084fc);
      font-weight: 600;
    }

    .subtitle-dropdown-menu .subtitle-track-list {
      display: flex;
      flex-direction: column;
      gap: 3px;
      padding: 6px;
      max-height: 240px;
      overflow-y: auto;
      flex: none;
      width: 100%;
      box-sizing: border-box;
    }

    .subtitle-dropdown-menu .subtitle-track-list::-webkit-scrollbar {
      width: 4px;
    }
    .subtitle-dropdown-menu .subtitle-track-list::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: 2px;
    }

    .subtitle-dropdown-menu .subtitle-btn {
      width: 100%;
      border-radius: 6px;
      padding: 6px 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      font-size: 0.75rem;
      border: 1px solid transparent;
      background: transparent;
      color: var(--text2);
      cursor: pointer;
      text-align: left;
      font-family: var(--mono);
      transition: all 0.12s ease;
      white-space: nowrap;
      box-sizing: border-box;
    }

    .subtitle-dropdown-menu .subtitle-btn span:first-child {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex: 1;
    }

    .subtitle-dropdown-menu .subtitle-btn:hover {
      background: rgba(192, 132, 252, 0.12);
      border-color: rgba(192, 132, 252, 0.3);
      color: #fff;
    }

    .subtitle-dropdown-menu .subtitle-btn.active {
      background: rgba(192, 132, 252, 0.2);
      border-color: var(--purple, #c084fc);
      color: var(--purple, #c084fc);
      font-weight: 700;
    }

    .subtitle-dropdown-menu .subtitle-btn .sub-item-check {
      font-size: 0.8rem;
      color: var(--purple, #c084fc);
      font-weight: 800;
      min-width: 14px;
      text-align: right;
    }

    .subtitle-dropdown-menu .subtitle-btn.off-btn {
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 6px 6px 4px 4px;
      margin-bottom: 2px;
    }

    .subtitle-dropdown-menu .subtitle-btn.off-btn.active {
      background: rgba(100, 116, 139, 0.15);
      border-color: var(--text3);
      color: var(--text2);
    }

    /* 內嵌字幕互動標籤按鈕 (卡片下方) */
    .emb-sub-tag {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      padding: 2px 9px;
      margin: 2px 2px;
      border-radius: 4px;
      font-size: 0.73rem;
      background: rgba(192, 132, 252, 0.1);
      border: 1px solid rgba(192, 132, 252, 0.3);
      color: var(--purple, #c084fc);
      cursor: pointer;
      font-family: var(--mono);
      font-weight: 500;
      transition: all 0.15s;
      line-height: 1.4;
    }
    .emb-sub-tag:hover {
      background: rgba(192, 132, 252, 0.25);
      border-color: var(--purple, #c084fc);
      color: #fff;
    }
    .emb-sub-tag.active {
      background: var(--purple, #c084fc);
      border-color: var(--purple, #c084fc);
      color: #0b0f17;
      font-weight: 700;
      box-shadow: 0 0 10px rgba(192, 132, 252, 0.4);
    }
    .emb-sub-tag.disabled {
      opacity: 0.55;
      cursor: default;
      border-style: dashed;
      background: rgba(100, 116, 139, 0.1);
      color: var(--text3);
      border-color: var(--border);
    }
    .emb-sub-tag.off-tag {
      background: rgba(100, 116, 139, 0.12);
      border-color: rgba(148, 163, 184, 0.28);
      color: var(--text3, #94a3b8);
    }
    .emb-sub-tag.off-tag:hover {
      background: rgba(239, 68, 68, 0.18);
      border-color: rgba(239, 68, 68, 0.45);
      color: #fca5a5;
    }
    .emb-sub-tag.off-tag.active {
      background: rgba(100, 116, 139, 0.35);
      border-color: var(--text2, #cbd5e1);
      color: #f1f5f9;
      font-weight: 700;
      box-shadow: none;
    }

    .subtitle-style-controls {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-left: auto;
      flex-shrink: 0;
      flex-wrap: wrap;
      position: relative;
      z-index: 60;
    }

    .subtitle-size-group,
    .subtitle-color-group,
    .subtitle-shadow-group {
      display: flex;
      align-items: center;
      gap: 4px;
      flex-shrink: 0;
      position: relative;
      z-index: 60;
    }

    .subtitle-size-label {
      font-size: 0.7rem;
      color: var(--text3);
      font-family: var(--mono);
      white-space: nowrap;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 3px;
      padding: 1px 5px;
      border-radius: 4px;
      border: 1px solid transparent;
      user-select: none;
      transition: all 0.15s ease;
    }
    .subtitle-size-label:hover {
      color: var(--text);
      background: rgba(255, 255, 255, 0.06);
      border-color: var(--border);
    }
    .subtitle-size-label .sub-size-val {
      font-weight: 600;
      color: var(--text2);
      font-variant-numeric: tabular-nums;
      transition: color 0.15s ease;
    }
    .subtitle-size-label.is-modified {
      border-color: rgba(0, 240, 255, 0.35);
      background: rgba(0, 240, 255, 0.08);
      color: var(--cyan, #00f0ff);
    }
    .subtitle-size-label.is-modified .sub-size-val {
      color: var(--cyan, #00f0ff);
      font-weight: 700;
    }
    .subtitle-size-label.is-modified:hover {
      border-color: var(--cyan, #00f0ff);
      background: rgba(0, 240, 255, 0.18);
      color: #fff;
    }
    .subtitle-size-label.is-modified:hover .sub-size-val {
      color: #fff;
    }

    .subtitle-size-btn,
    .subtitle-style-btn {
      padding: 2px 7px;
      font-size: 0.7rem;
      border-radius: 4px;
      border: 1px solid var(--border);
      background: var(--bg3);
      color: var(--text2);
      cursor: pointer;
      font-family: var(--mono);
      font-weight: 600;
      transition: all 0.12s;
      line-height: 1.5;
      white-space: nowrap;
    }

    .subtitle-size-btn:hover,
    .subtitle-style-btn:hover {
      border-color: var(--cyan);
      color: var(--cyan);
    }

    .subtitle-style-btn.active {
      background: rgba(0, 212, 170, 0.18);
      border-color: var(--cyan);
      color: var(--cyan);
      font-weight: 700;
      box-shadow: 0 0 8px rgba(0, 212, 170, 0.25);
    }

    /* 🎨 色彩下拉選單按鈕與彈出盒 */
    .sub-color-picker-btn {
      position: relative;
      z-index: 60;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      user-select: none;
      font-size: 0.7rem;
      padding: 2px 7px;
      border-radius: 4px;
      border: 1px solid var(--border);
      background: var(--bg3);
      color: var(--text2);
      cursor: pointer;
      font-family: var(--mono);
      font-weight: 600;
      transition: all 0.15s ease;
      white-space: nowrap;
      line-height: 1.5;
    }

    .sub-color-picker-btn:hover {
      border-color: var(--cyan);
      color: var(--cyan);
    }

    .sub-color-picker-btn.dropdown-open {
      border-color: var(--cyan);
      color: var(--cyan);
      background: rgba(0, 212, 170, 0.15);
      box-shadow: 0 0 8px rgba(0, 212, 170, 0.25);
      z-index: 85;
    }

    .sub-color-picker-btn.dropdown-open #sub-color-caret {
      transform: rotate(180deg);
      color: var(--cyan);
    }

    .subtitle-color-dropdown-menu {
      right: 0;
      left: auto;
      min-width: 170px;
      max-width: 220px;
      border-color: rgba(0, 212, 170, 0.45);
      box-shadow: 0 16px 48px rgba(0, 0, 0, 0.95), 0 0 16px rgba(0, 212, 170, 0.2);
      z-index: 1000;
    }

    .subtitle-color-options {
      display: flex;
      flex-direction: column;
      gap: 3px;
      padding: 6px;
      width: 100%;
      box-sizing: border-box;
    }

    .sub-color-opt {
      width: 100%;
      border-radius: 6px;
      padding: 6px 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      font-size: 0.74rem;
      border: 1px solid transparent;
      background: transparent;
      color: var(--text2);
      cursor: pointer;
      font-family: var(--mono);
      transition: all 0.12s ease;
      white-space: nowrap;
      box-sizing: border-box;
    }

    .sub-color-opt:hover {
      background: rgba(0, 212, 170, 0.12);
      border-color: rgba(0, 212, 170, 0.3);
      color: #fff;
    }

    .sub-color-opt.active {
      background: rgba(0, 212, 170, 0.2);
      border-color: var(--cyan);
      color: var(--cyan);
      font-weight: 700;
    }

    .sub-color-opt .sub-item-check {
      font-size: 0.8rem;
      color: var(--cyan);
      font-weight: 800;
      min-width: 14px;
      text-align: right;
    }

    /* video::cue 字幕樣式覆蓋（同步色彩與陰影，全螢幕與視窗模式通用） */
    video::cue,
    ::cue,
    video:fullscreen::cue,
    :fullscreen::cue,
    .video-container:fullscreen video::cue,
    .video-container:-webkit-full-screen video::cue,
    .video-container:-moz-full-screen video::cue {
      background: rgba(0, 0, 0, 0.72) !important;
      color: #ffffff;
      font-size: 1em;
      font-family: 'Noto Sans TC', 'Microsoft JhengHei', sans-serif;
      line-height: 1.4;
      text-shadow: 0 2px 6px #000, 0 0 4px #000;
      border-radius: 3px;
      padding: 2px 6px;
    }

    .sub-color-white video::cue,
    .sub-color-original video::cue,
    .sub-color-white ::cue,
    .sub-color-original ::cue,
    #video-box.sub-color-white video::cue,
    #video-box.sub-color-original video::cue,
    #video-box.sub-color-white ::cue,
    #video-box.sub-color-original ::cue {
      color: #ffffff !important;
    }
    .sub-color-yellow video::cue,
    .sub-color-yellow ::cue,
    #video-box.sub-color-yellow video::cue,
    #video-box.sub-color-yellow ::cue {
      color: #ffe628 !important;
    }
    .sub-color-cyan video::cue,
    .sub-color-cyan ::cue,
    #video-box.sub-color-cyan video::cue,
    #video-box.sub-color-cyan ::cue {
      color: #00f0ff !important;
    }
    .sub-shadow-on video::cue,
    .sub-shadow-on ::cue,
    #video-box.sub-shadow-on video::cue,
    #video-box.sub-shadow-on ::cue {
      text-shadow: 0 2px 6px #000, 0 0 4px #000, 1px 1px 2px #000 !important;
    }
    .sub-shadow-off video::cue,
    .sub-shadow-off ::cue,
    #video-box.sub-shadow-off video::cue,
    #video-box.sub-shadow-off ::cue {
      text-shadow: none !important;
    }

    /* ════════════════════════════════════════════════════════
       Hover 影片預覽彈窗 (Preview Popup)
    ════════════════════════════════════════════════════════ */
    .preview-popup {
      position: fixed;
      z-index: 9998;
      width: 300px;
      border-radius: 12px;
      overflow: hidden;
      background: var(--bg);
      border: 1px solid rgba(255,255,255,0.1);
      box-shadow: 0 24px 64px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04), inset 0 1px 0 rgba(255,255,255,0.06);
      opacity: 0;
      visibility: hidden;
      display: none;
      transform: scale(0.90) translateY(10px);
      pointer-events: none;
      transition: opacity 0.2s cubic-bezier(0.4,0,0.2,1), transform 0.2s cubic-bezier(0.4,0,0.2,1);
      will-change: transform, opacity;
    }

    .preview-popup::before {
      content: '';
      position: absolute;
      top: -30px;
      bottom: -30px;
      left: -42px;
      width: 48px;
      pointer-events: auto;
    }

    .preview-popup.pp-flip-left::before {
      left: auto;
      right: -42px;
    }

    .preview-popup.pp-visible {
      opacity: 1;
      visibility: visible;
      display: block;
      transform: scale(1) translateY(0);
      pointer-events: auto !important;
    }

    .preview-popup.pp-loading video {
      opacity: 0;
    }

    .pp-video-wrap {
      position: relative;
      cursor: pointer;
      overflow: hidden;
    }

    .preview-popup video {
      width: 100%;
      display: block;
      aspect-ratio: 16 / 9;
      background: #000;
      transition: opacity 0.2s ease;
    }

    .pp-hover-play-hint {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.45);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.18s ease;
      pointer-events: none;
      z-index: 2;
    }

    .preview-popup:hover .pp-hover-play-hint {
      opacity: 1;
    }

    .preview-popup.pp-loading .pp-hover-play-hint {
      display: none !important;
    }

    .pp-hover-play-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 20px;
      background: rgba(15, 23, 42, 0.88);
      border: 1px solid rgba(6, 182, 212, 0.55);
      color: #fff;
      font-size: 0.78rem;
      font-weight: 600;
      font-family: inherit;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.6), 0 0 12px rgba(6, 182, 212, 0.35);
      transform: translateY(4px);
      transition: transform 0.18s ease;
    }

    .preview-popup:hover .pp-hover-play-badge {
      transform: translateY(0);
    }

    .pp-loading-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.6);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
      opacity: 0;
      transition: opacity 0.15s ease;
      pointer-events: none;
    }

    .preview-popup.pp-loading .pp-loading-overlay {
      opacity: 1;
    }

    .pp-spinner {
      width: 28px;
      height: 28px;
      border: 3px solid rgba(255,255,255,0.15);
      border-top-color: var(--cyan);
      border-radius: 50%;
      animation: spinRotate 0.7s linear infinite;
    }

    @keyframes spinRotate {
      to { transform: rotate(360deg); }
    }

    .pp-loading-text {
      font-size: 0.7rem;
      color: var(--text3);
      font-family: var(--mono);
    }

    .pp-footer {
      padding: 8px 12px 10px;
      background: var(--bg2);
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .pp-title {
      flex: 1;
      font-size: 0.74rem;
      font-weight: 600;
      color: var(--text);
      font-family: inherit;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }

    .pp-folder-hint {
      font-size: 0.64rem;
      color: var(--cyan);
      font-family: var(--mono);
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      margin-top: 1px;
      margin-bottom: 3px;
    }

    .pp-play-btn {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: linear-gradient(135deg, #06b6d4, #2563eb);
      color: #ffffff !important;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 6px;
      padding: 4px 8px;
      font-size: 0.70rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      white-space: nowrap;
      flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(6, 182, 212, 0.35);
      transition: all 0.15s ease;
    }

    .pp-play-btn:hover {
      background: linear-gradient(135deg, #22d3ee, #3b82f6);
      box-shadow: 0 4px 14px rgba(6, 182, 212, 0.6);
      transform: translateY(-1px);
      filter: brightness(1.08);
    }

    .pp-play-btn:active {
      transform: translateY(0);
    }

    .pp-label {
      font-size: 0.65rem;
      color: var(--text3);
      font-family: var(--mono);
      flex-shrink: 0;
    }

    .pp-progress-track {
      width: 100%;
      height: 2px;
      background: rgba(255,255,255,0.08);
      border-radius: 1px;
      overflow: hidden;
      margin-top: 2px;
    }

    .pp-progress-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, var(--cyan), var(--purple));
      border-radius: 1px;
      transition: width 0.1s linear;
    }

    /* 彈窗置中模式（手機或點擊 👁 按鈕展開） */
    .preview-popup.pp-modal {
      width: min(88vw, 440px) !important;
      left: 50% !important;
      top: 50% !important;
      transform: translate(-50%, -50%) scale(0.92) !important;
      pointer-events: none !important; /* 只有加上 pp-visible 才能點擊 */
    }

    .preview-popup.pp-modal.pp-visible {
      transform: translate(-50%, -50%) scale(1) !important;
      pointer-events: auto !important;
    }

    .preview-popup.pp-modal .pp-close-btn {
      display: flex !important;
    }

    /* 行動觸控裝置若無滑鼠時預設置中 */
    @media (hover: none) {
      .preview-popup {
        width: min(88vw, 440px);
        left: 50% !important;
        top: 50% !important;
        transform: translate(-50%, -50%) scale(0.92);
        pointer-events: none !important; /* 嚴禁未顯示時阻擋下層點擊 */
      }
      .preview-popup.pp-visible {
        transform: translate(-50%, -50%) scale(1);
      }
      .preview-popup.pp-visible.pp-modal {
        pointer-events: auto !important;
      }
      .pp-close-btn { display: flex !important; }
      .item-preview-btn { display: flex !important; }
    }

    /* 全幕遮罩背景（彈窗模式關閉用） */
    .pp-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 9997;
      background: rgba(0,0,0,0.65);
      backdrop-filter: blur(3px);
      opacity: 0;
      pointer-events: none !important; /* 預設不接收任何事件，絕不阻擋點擊 */
      transition: opacity 0.2s ease;
    }
    .pp-backdrop.pp-visible {
      display: block;
      opacity: 1;
      pointer-events: auto !important;
    }

    /* 關閉按鈕 */
    .pp-close-btn {
      display: none;
      position: absolute;
      top: 8px;
      right: 8px;
      z-index: 10;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: rgba(0,0,0,0.65);
      border: 1px solid rgba(255,255,255,0.2);
      color: #fff;
      font-size: 1rem;
      line-height: 1;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      flex-shrink: 0;
      transition: background 0.15s, transform 0.12s;
    }
    .pp-close-btn:hover { background: rgba(255,50,50,0.8); transform: scale(1.08); }
    .pp-close-btn:active { background: rgba(255,50,50,0.9); transform: scale(0.92); }

    /* 列表項目預覽按鈕（行動裝置或觸控寬度下常駐，桌面懸停顯現） */
    .item-preview-btn {
      display: none;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 1px solid rgba(56,189,248,0.35);
      background: rgba(56,189,248,0.08);
      color: var(--cyan);
      font-size: 0.85rem;
      cursor: pointer;
      margin-right: 4px;
      transition: background 0.15s, transform 0.12s, border-color 0.15s;
      -webkit-tap-highlight-color: transparent;
    }
    .item-preview-btn:hover {
      background: rgba(56,189,248,0.22);
      border-color: var(--cyan);
      transform: scale(1.08);
    }
    .item-preview-btn:active {
      background: rgba(56,189,248,0.35);
      transform: scale(0.95);
    }

    @media (max-width: 1024px) {
      .item-preview-btn { display: flex !important; }
    }
    .playlist-item:hover .item-preview-btn {
      display: flex;
    }

    /* ════════════════════════════════════════════════════════
       手機版面修正 (Mobile Layout Fix) — ≤ 768px
    ════════════════════════════════════════════════════════ */
    @media (max-width: 768px) {
      html {
        font-size: 15px;
      }

      html, body {
        overflow-x: hidden;
        overflow-x: clip;
        max-width: 100vw;
      }

      /* ── 頂部導航列：單行緊湊呈現，永不折行 ── */
      .topbar {
        position: static;
        padding: 6px 10px;
        gap: 6px;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
      }
      .topbar::-webkit-scrollbar {
        display: none;
      }

      .topbar-left {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        flex: 0 0 auto;
      }

      /* 路徑標籤手機隱藏（省空間） */
      .path-badge {
        display: none !important;
      }

      .topbar-right {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        flex: 0 0 auto;
      }

      /* 統計與預覽：手機直接隱藏（避免推擠版面造成折行） */
      .topbar-stat-box,
      .topbar-preview-box {
        display: none !important;
      }

      .brand-badge {
        font-size: 0.8rem;
        padding: 4px 8px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 6px;
      }
      /* 確保切換說明按鈕在手機版面維持清晰可見，且 💡 圖示絕對不被隱藏 */
      .brand-badge .brand-guide-btn {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        padding: 2px 6px;
        margin-left: 2px;
        gap: 3px;
        line-height: 1;
        border-color: rgba(0, 212, 170, 0.45);
        background: rgba(0, 212, 170, 0.16);
      }
      .brand-badge .brand-guide-btn .guide-tag-icon {
        display: inline-flex !important;
        font-size: 0.82rem !important;
        line-height: 1;
      }
      .brand-badge .brand-guide-btn .guide-tag-text {
        display: inline-block;
      }

      .topbar .btn {
        font-size: 0.72rem;
        padding: 4px 8px;
        white-space: nowrap;
        flex-shrink: 0;
      }
      .topbar-icon-btn {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
        padding: 0 !important;
        font-size: 0.78rem !important;
        line-height: 1 !important;
      }

      /* ── 麵包屑縮小 ── */
      .breadcrumb-bar {
        padding: 6px 10px;
        font-size: 0.72rem;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
      }

      /* ── 主版面：單欄，無多餘間距 ── */
      .main-wrapper {
        padding: 8px 6px;
        gap: 10px;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        overflow-x: hidden;
        overflow-x: clip;
      }

      /* ── 手機版播放器吸附尺寸微調 (6px 邊距滿版置頂) ── */
      .video-container {
        width: calc(100% + 12px);
        margin-left: -6px;
        margin-right: -6px;
      }

      /* ── 播放器工具列：垂直堆疊，每列寬度 100% ── */
      .player-toolbar {
        padding: 8px 10px;
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        width: 100%;
        box-sizing: border-box;
        overflow-x: hidden;
      }

      .toolbar-group {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: center;
      }

      /* ⚡ 倍速與快轉按鈕群：手機版面下同一排不分行，隱藏 LABEL 節省空間 */
      .speed-seek-group {
        display: flex;
        flex-wrap: nowrap !important;
        align-items: center;
        gap: 6px;
        width: 100%;
        overflow-x: auto;
        scrollbar-width: none;
      }
      .speed-seek-group::-webkit-scrollbar {
        display: none;
      }
      .speed-seek-group .toolbar-label {
        display: none !important;
      }

      /* 🎬 集數切換與全螢幕按鈕群 */
      .episode-group {
        display: flex;
        flex-wrap: nowrap !important;
        align-items: center;
        gap: 6px;
        width: 100%;
        overflow-x: auto;
        scrollbar-width: none;
      }
      .episode-group::-webkit-scrollbar {
        display: none;
      }
      #btn-ambilight-toggle {
        display: none !important;
      }
      #btn-fullscreen #btn-fs-text {
        display: none !important;
      }
      #btn-cast #btn-cast-text {
        display: none !important;
      }
      #btn-cast {
        padding: 4px 8px;
        font-size: 0.75rem;
        flex-shrink: 0;
      }
      #btn-fullscreen {
        padding: 4px 8px;
        font-size: 0.75rem;
        flex-shrink: 0;
      }

      /* 倍速按鈕群 */
      .toolbar-group .pill-btn {
        padding: 4px 7px;
        font-size: 0.72rem;
        flex-shrink: 0;
      }

      .toolbar-label {
        font-size: 0.7rem;
        white-space: nowrap;
      }

      .speed-select,
      .loop-select {
        padding: 2px 4px;
        font-size: 0.68rem;
      }

      /* 快進倒退微型按鈕與直連分享按鈕 */
      .seek-segmented-group .seek-btn {
        padding: 3px 5px;
        font-size: 0.68rem;
      }

      .pill-btn.share-btn,
      .pill-btn.tv-cast-btn,
      .pill-btn.cast-btn,
      .folder-share-btn,
      .pill-btn.ambilight-btn {
        padding: 2px 7px;
        font-size: 0.68rem;
      }

      /* 📺 智慧電視彈窗手機排版：網址與複製按鈕垂直堆疊，100% 杜絕溢出破格 */
      .tv-cast-modal {
        width: min(94vw, 520px) !important;
        max-height: 90vh;
      }
      .tv-cast-header {
        padding: 10px 14px;
      }
      .tv-cast-title {
        font-size: 0.95rem;
      }
      .tv-cast-body {
        padding: 12px 12px;
        gap: 10px;
      }
      .tv-cast-section {
        padding: 10px 12px;
      }
      .tv-url-input-group {
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
      }
      .tv-url-input {
        width: 100%;
        font-size: 0.72rem;
        padding: 7px 9px;
      }
      .tv-copy-btn {
        width: 100%;
        justify-content: center;
        padding: 7px 10px;
        font-size: 0.74rem;
      }

      /* 自動連播那列 */
      .toolbar-group .toggle-wrap {
        font-size: 0.72rem;
        white-space: nowrap;
      }

      .toolbar-group .btn {
        padding: 4px 8px;
        font-size: 0.72rem;
      }

      /* ── 影片資訊卡片 ── */
      .video-info-card {
        padding: 10px 12px;
        gap: 8px;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
      }

      .video-title {
        font-size: 0.95rem;
        word-break: break-all;
        overflow-wrap: anywhere;
      }

      .video-meta-pills {
        gap: 6px;
        font-size: 0.72rem;
        width: 100%;
      }

      .meta-pill {
        max-width: 100%;
        word-break: break-all;
        overflow-wrap: anywhere;
        white-space: normal;
      }

      /* 鍵盤快捷鍵提示手機隱藏 */
      .shortcuts-box {
        display: none;
      }

      /* ── 字幕列 ── */
      .subtitle-bar {
        padding: 6px 10px;
        gap: 6px;
        width: 100%;
        box-sizing: border-box;
      }

      .subtitle-size-group {
        margin-left: 0;
      }

      /* ── 接續播放提示膠囊 ── */
      .resume-banner {
        bottom: 40px;
        left: 10px;
        right: 10px;
      }

      /* ── 快轉/倒退 HUD 提示卡片手機縮放 ── */
      .video-seek-indicator {
        padding: 8px 14px;
        gap: 8px;
        border-radius: 9px;
      }
      .seek-indicator-icon {
        font-size: 1.4rem;
      }
      .seek-indicator-delta {
        font-size: 0.95rem;
      }
      .seek-indicator-time {
        font-size: 0.7rem;
      }

      /* ── 伺服器設定說明卡片手機排版 ── */
      .server-guide-card {
        padding: 14px 10px;
        gap: 12px;
        border-radius: 8px;
        max-width: 100%;
        box-sizing: border-box;
      }
      .guide-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding-bottom: 12px;
      }
      .guide-header-left {
        gap: 10px;
        min-width: 0;
      }
      .guide-header-icon {
        font-size: 1.8rem;
      }
      .guide-title {
        font-size: 1.05rem;
      }
      .guide-subtitle {
        font-size: 0.75rem;
      }
      .guide-header-right {
        width: 100%;
        justify-content: stretch;
      }
      .guide-return-btn {
        width: 100%;
        justify-content: center;
        padding: 8px 10px;
        font-size: 0.8rem;
        box-sizing: border-box;
      }
      .guide-title-wrap {
        max-width: min(56vw, 220px);
      }
      .guide-playing-title {
        max-width: min(53vw, 200px);
      }
    }

    /* ── 手機窄螢幕 (≤ 560px) 頂部說明按鈕僅保留 💡 圖示，杜絕排版折行 ── */
    @media (max-width: 560px) {
      .brand-badge .brand-guide-btn {
        padding: 3px 6px !important;
        min-width: 26px;
        min-height: 24px;
        justify-content: center;
      }
      .brand-badge .brand-guide-btn .guide-tag-text {
        display: none !important;
      }
      .brand-badge .brand-guide-btn .guide-tag-icon {
        display: inline-flex !important;
        font-size: 0.92rem !important;
        line-height: 1;
      }
      #btn-show-guide #guide-btn-text {
        display: none;
      }
      .guide-return-btn {
        padding: 7px 8px;
        font-size: 0.76rem;
      }
      .guide-return-label {
        font-size: 0.76rem;
      }
      .guide-title-wrap {
        max-width: min(48vw, 160px);
        font-size: 0.74rem;
      }
      .guide-playing-title {
        max-width: min(45vw, 150px);
      }
    }

    /* ── 極小螢幕 (≤ 400px) 微調 ── */
    @media (max-width: 400px) {
      .video-seek-indicator {
        padding: 6px 10px;
        gap: 6px;
      }
      .seek-indicator-icon {
        font-size: 1.2rem;
      }
      .seek-indicator-delta {
        font-size: 0.85rem;
      }
      .topbar {
        padding: 5px 8px;
        gap: 4px;
      }
      .topbar .btn {
        padding: 3px 6px;
        font-size: 0.68rem;
      }
      .topbar-icon-btn {
        width: 26px !important;
        height: 26px !important;
        min-width: 26px !important;
        padding: 0 !important;
        font-size: 0.72rem !important;
        line-height: 1 !important;
      }
      .brand-badge {
        font-size: 0.75rem;
        padding: 3px 6px;
        gap: 4px;
      }
      .brand-badge .brand-guide-btn {
        padding: 2px 5px !important;
        font-size: 0.68rem;
        min-width: 24px;
        min-height: 22px;
      }
      .brand-badge .brand-guide-btn .guide-tag-icon {
        display: inline-flex !important;
        font-size: 0.85rem !important;
      }
      .main-wrapper {
        padding: 6px 4px;
      }
      .player-toolbar {
        padding: 6px 6px;
        gap: 6px;
      }
      .pill-btn {
        padding: 3px 6px;
        font-size: 0.68rem;
      }
      .server-guide-card {
        padding: 10px 8px;
        gap: 10px;
      }
      .guide-return-btn {
        padding: 6px 8px;
        font-size: 0.72rem;
        gap: 4px;
      }
      .guide-return-label {
        font-size: 0.72rem;
      }
      .guide-title-wrap {
        max-width: min(42vw, 120px);
        font-size: 0.7rem;
      }
      .guide-playing-title {
        max-width: min(40vw, 110px);
      }
    }

    /* ════ 智慧防卡頓緩衝守護 HUD (Anti-Stutter Buffer Guard) ════ */
    .buffer-guard-overlay {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      background: rgba(10, 14, 22, 0.78);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 45;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.25s ease;
      box-sizing: border-box;
      margin: 0 !important;
    }
    .buffer-guard-overlay.active {
      display: flex !important;
      opacity: 1 !important;
      pointer-events: auto !important;
    }
    .video-container:fullscreen .buffer-guard-overlay,
    .video-container:-webkit-full-screen .buffer-guard-overlay,
    .video-container:-moz-full-screen .buffer-guard-overlay,
    .video-container.mobile-web-fullscreen .buffer-guard-overlay {
      z-index: 2147483647 !important;
    }
    .buffer-guard-box {
      background: rgba(15, 23, 42, 0.94);
      border: 1px solid rgba(0, 212, 170, 0.5);
      border-radius: 12px;
      padding: 18px 24px;
      max-width: 90%;
      width: 320px;
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.85), 0 0 24px rgba(0, 212, 170, 0.25);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 10px;
      user-select: none;
    }
    .buffer-guard-spinner {
      width: 32px;
      height: 32px;
      border: 3px solid rgba(0, 212, 170, 0.2);
      border-top-color: var(--cyan);
      border-radius: 50%;
      animation: bg-spin 0.8s linear infinite;
    }
    @keyframes bg-spin {
      to { transform: rotate(360deg); }
    }
    .buffer-guard-title {
      font-size: 0.88rem;
      font-weight: 700;
      color: #f1f5f9;
      letter-spacing: 0.3px;
    }
    .buffer-guard-bar-track {
      width: 100%;
      height: 7px;
      background: rgba(255, 255, 255, 0.12);
      border-radius: 99px;
      overflow: hidden;
      position: relative;
    }
    .buffer-guard-bar-fill {
      height: 100%;
      background: linear-gradient(90deg, #06b6d4, #00d4aa);
      border-radius: 99px;
      width: 0%;
      transition: width 0.2s ease;
      box-shadow: 0 0 8px rgba(0, 212, 170, 0.6);
    }
    .buffer-guard-desc {
      font-size: 0.74rem;
      color: var(--text2);
      font-family: var(--mono);
    }
    .buffer-guard-skip-btn {
      background: transparent;
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: var(--text3);
      font-size: 0.72rem;
      padding: 4px 14px;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.15s ease;
      margin-top: 2px;
    }
    .buffer-guard-skip-btn:hover {
      background: rgba(255, 255, 255, 0.12);
      color: var(--text);
      border-color: rgba(255, 255, 255, 0.45);
    }

    /* 實時緩衝健康度指標 (Buffer Health Badge) */
    .buffer-health-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 3px 8px;
      border-radius: 4px;
      background: var(--bg3);
      border: 1px solid var(--border);
      font-size: 0.7rem;
      font-family: var(--mono);
      color: var(--text2);
      user-select: none;
      line-height: 1.2;
      transition: all 0.2s ease;
    }
    .buffer-health-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #64748b;
      display: inline-block;
      transition: background 0.3s ease, box-shadow 0.3s ease;
    }
    .buffer-health-dot.good {
      background: #10b981;
      box-shadow: 0 0 6px rgba(16, 185, 129, 0.7);
    }
    .buffer-health-dot.fair {
      background: #f59e0b;
      box-shadow: 0 0 6px rgba(245, 158, 11, 0.7);
    }
    .buffer-health-dot.poor {
      background: #ef4444;
      box-shadow: 0 0 6px rgba(239, 68, 68, 0.7);
      animation: dot-pulse 1s infinite alternate;
    }
    @keyframes dot-pulse {
      from { opacity: 0.4; }
      to { opacity: 1; }
    }

    /* ⚡ 預載快取按鈕樣式 */
    .precache-btn {
      background: rgba(14, 165, 233, 0.12);
      border-color: rgba(14, 165, 233, 0.35);
      color: #38bdf8;
    }
    .precache-btn:hover {
      background: rgba(14, 165, 233, 0.22);
      border-color: #38bdf8;
      box-shadow: 0 0 10px rgba(56, 189, 248, 0.35);
    }
    .precache-btn.cached {
      background: rgba(16, 185, 129, 0.16) !important;
      border-color: rgba(16, 185, 129, 0.55) !important;
      color: #34d399 !important;
      box-shadow: 0 0 10px rgba(16, 185, 129, 0.3) !important;
    }

    /* ══ QR Code 分享彈窗模組 ══ */
    .qr-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.75);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      z-index: 10001;
      display: none;
      opacity: 0;
      transition: opacity 0.22s ease;
    }
    .qr-modal-backdrop.active {
      display: block;
      opacity: 1;
    }

    .qr-modal {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.92);
      width: 90%;
      max-width: 380px;
      max-height: 92vh;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 14px;
      box-shadow: 0 16px 48px rgba(0, 0, 0, 0.75), 0 0 24px rgba(6, 182, 212, 0.25);
      z-index: 10002;
      display: none;
      flex-direction: column;
      opacity: 0;
      transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
      font-family: inherit;
    }
    .qr-modal.active {
      display: flex;
      opacity: 1;
      transform: translate(-50%, -50%) scale(1);
    }

    .qr-modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
      background: var(--bg3);
      border-bottom: 1px solid var(--border);
    }
    .qr-modal-title-wrap {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }
    .qr-modal-icon {
      font-size: 1.1rem;
    }
    .qr-modal-title {
      font-size: 0.90rem;
      font-weight: 700;
      color: var(--text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .qr-modal-close {
      background: transparent;
      border: none;
      color: var(--text3);
      font-size: 1.35rem;
      cursor: pointer;
      line-height: 1;
      padding: 2px 6px;
      border-radius: 4px;
      transition: color 0.15s, background 0.15s;
    }
    .qr-modal-close:hover {
      color: var(--red);
      background: rgba(239, 68, 68, 0.15);
    }

    .qr-modal-body {
      padding: 16px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      max-height: calc(92vh - 55px);
    }

    .qr-target-badge {
      width: 100%;
      box-sizing: border-box;
      padding: 6px 10px;
      border-radius: 8px;
      background: var(--bg);
      border: 1px solid var(--border);
      font-size: 0.76rem;
      color: var(--cyan);
      font-family: var(--mono);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: center;
    }

    .qr-code-wrapper {
      background: #ffffff;
      padding: 6px;
      border-radius: 12px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .qr-code-img {
      display: block;
      max-width: 220px;
      width: auto;
      height: auto;
      max-height: 260px;
      border-radius: 8px;
      user-select: none;
    }

    .qr-scan-hint {
      font-size: 0.72rem;
      color: var(--text3);
      text-align: center;
    }

    .qr-url-box {
      width: 100%;
    }
    .qr-url-input {
      width: 100%;
      box-sizing: border-box;
      padding: 7px 10px;
      font-size: 0.72rem;
      font-family: var(--mono);
      color: var(--text2);
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      text-overflow: ellipsis;
      cursor: pointer;
    }
    .qr-url-input:focus {
      border-color: var(--cyan);
      outline: none;
      color: var(--text);
    }

    .qr-action-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 8px;
      width: 100%;
    }
    .qr-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      padding: 8px 6px;
      border-radius: 7px;
      font-size: 0.74rem;
      font-weight: 600;
      border: 1px solid var(--border);
      background: var(--bg3);
      color: var(--text);
      cursor: pointer;
      transition: all 0.15s ease;
      white-space: nowrap;
    }
    .qr-btn:hover {
      background: var(--border);
      color: #fff;
    }
    .qr-btn.primary {
      background: linear-gradient(135deg, #06b6d4, #2563eb);
      border-color: rgba(6, 182, 212, 0.4);
      color: #fff;
    }
    .qr-btn.primary:hover {
      filter: brightness(1.1);
      box-shadow: 0 2px 10px rgba(6, 182, 212, 0.4);
    }
    .qr-btn.copied {
      background: var(--green) !important;
      color: #000 !important;
      border-color: var(--green) !important;
    }

    /* 社群快速傳送分享區塊 */
    .qr-share-divider {
      display: flex;
      align-items: center;
      width: 100%;
      margin: 2px 0;
      gap: 8px;
      font-size: 0.68rem;
      color: var(--text3);
      letter-spacing: 0.5px;
    }
    .qr-share-divider::before,
    .qr-share-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }
    .qr-social-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
      width: 100%;
    }
    .qr-social-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 9px 8px;
      border-radius: 8px;
      font-size: 0.78rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.18s ease;
      white-space: nowrap;
      border: 1px solid transparent;
      user-select: none;
      box-sizing: border-box;
    }
    .qr-social-btn .social-icon {
      flex-shrink: 0;
    }
    .qr-social-btn .social-icon-emoji {
      font-size: 0.95rem;
      line-height: 1;
    }
    /* LINE 綠色品牌樣式 */
    .qr-social-btn.line {
      background: rgba(6, 199, 85, 0.14);
      border-color: rgba(6, 199, 85, 0.45);
      color: #22c55e;
    }
    .qr-social-btn.line:hover,
    .qr-social-btn.line:active {
      background: #06c755;
      border-color: #06c755;
      color: #ffffff;
      box-shadow: 0 0 12px rgba(6, 199, 85, 0.4);
    }
    /* Facebook 藍色品牌樣式 */
    .qr-social-btn.fb {
      background: rgba(24, 119, 242, 0.14);
      border-color: rgba(24, 119, 242, 0.45);
      color: #3b82f6;
    }
    .qr-social-btn.fb:hover,
    .qr-social-btn.fb:active {
      background: #1877f2;
      border-color: #1877f2;
      color: #ffffff;
      box-shadow: 0 0 12px rgba(24, 119, 242, 0.4);
    }
    /* 系統原生分享 (科技紫/漸層) */
    .qr-social-btn.native {
      background: rgba(168, 85, 247, 0.14);
      border-color: rgba(168, 85, 247, 0.45);
      color: #c084fc;
    }
    .qr-social-btn.native:hover,
    .qr-social-btn.native:active {
      background: linear-gradient(135deg, #a855f7, #6366f1);
      border-color: #a855f7;
      color: #ffffff;
      box-shadow: 0 0 12px rgba(168, 85, 247, 0.4);
    }

    /* 手機版面專屬響應式優化 (Mobile Optimization) */
    @media (max-width: 640px) {
      .qr-modal {
        width: 93%;
        max-width: 350px;
        border-radius: 12px;
      }
      .qr-modal-body {
        padding: 12px 14px 16px;
        gap: 8px;
      }
      .qr-code-wrapper {
        padding: 4px;
        border-radius: 10px;
      }
      .qr-code-img {
        max-width: 175px;
        max-height: 215px;
      }
      .qr-scan-hint {
        font-size: 0.69rem;
      }
      .qr-action-grid {
        gap: 6px;
      }
      .qr-btn {
        padding: 7px 4px;
        font-size: 0.72rem;
      }
      .qr-social-grid {
        gap: 6px;
      }
      .qr-social-btn {
        padding: 8px 6px;
        font-size: 0.76rem;
      }
    }
  </style>
</head>
<body>

  <!-- ══ 頂部導航列 ══════════════════════════════════════════════════════════ -->
  <header class="topbar">
    <div class="topbar-left">
      <a href="index.php" class="btn btn-icon" title="返回 NAS 監控儀表板">← 儀表板</a>
      <div class="brand-badge">
        🎬 影音中心
        <button type="button" class="brand-guide-btn" id="btn-topbar-guide" onclick="toggleServerGuide('config')" title="點擊隨時查看伺服器端設定說明、全格式支援矩陣與操作技巧">
          <span class="guide-tag-icon">💡</span><span class="guide-tag-text">全格式支援</span>
        </button>
      </div>
<?php
$badge_title = '影音庫：' . implode(', ', $video_dirs);
$badge_display = (count($video_dirs) > 1)
  ? (dirname($video_dirs[0]) . '/{' . implode(',', array_map('basename', $video_dirs)) . '}')
  : $video_dir;
?>
      <div class="path-badge" title="<?php echo htmlspecialchars($badge_title); ?>">
        📁 <span><?php echo htmlspecialchars($badge_display); ?></span>
      </div>
    </div>
    <div class="topbar-right">
      <div class="btn topbar-stat-box" style="cursor: default;">
        📊 <span id="stat-video-count">0</span> 部影音
        <span style="color:var(--border-light)">|</span>
        <span id="stat-total-size">0 B</span>
      </div>
      <!-- 預覽時長設定 -->
      <div class="btn topbar-preview-box" style="cursor:default;gap:8px;padding:5px 10px;" title="Hover 影片與資料夾隨機影片預覽播放時長（5 ~ 30 秒）">
        <span style="font-size:0.72rem;color:var(--text3);font-family:var(--mono);white-space:nowrap;">👁 預覽</span>
        <input type="range" id="preview-sec-slider" min="5" max="30" step="1" value="10"
          style="width:72px;accent-color:var(--cyan);cursor:pointer;vertical-align:middle;"
          oninput="updatePreviewSec(this.value)">
        <span id="preview-sec-label" style="font-size:0.78rem;font-family:var(--mono);color:var(--cyan);min-width:2.2em;text-align:center;">10s</span>
      </div>
      <!-- 明/暗主題切換按鈕 (同步儀表板 fin-lab-theme) -->
      <button type="button" class="btn topbar-icon-btn theme-btn" id="theme-toggle-btn" onclick="toggleTheme()" title="切換明暗主題">🌙</button>
      <?php if (has_pin_protection()): ?>
      <a href="auth.php?action=logout&redirect=index.php" class="btn topbar-icon-btn lock-btn" id="lock-btn" title="鎖定安全會話">🔒</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- 手機版背景遮罩 (Backdrop) -->
  <div class="playlist-overlay" id="playlist-overlay" onclick="closeMobileDrawer()"></div>

  <!-- ══ 麵包屑導航列（支援任意深度點擊返回） ════════════════════════════════ -->
  <nav class="breadcrumb-bar" id="breadcrumb-bar">
    <!-- 由 JS 動態渲染 -->
  </nav>

  <!-- ══ 主播放器與播放清單版面 ══════════════════════════════════════════════ -->
  <main class="main-wrapper">

    <!-- ── 左側：主要播放區域 (Stage) ── -->
    <section class="player-stage">
      
      <!-- 播放器本體 -->
      <div class="video-viewport-card" id="video-viewport-card">
        <!-- 🌟 WebGL 劇院環境動態光暈 (Ambilight Canvas) -->
        <canvas id="ambilight-canvas" class="ambilight-canvas" aria-hidden="true"></canvas>

        <div class="video-container" id="video-box">
          <video id="video-player" controls controlslist="nofullscreen" preload="auto" playsinline x-webkit-airplay="allow">
            您的瀏覽器不支援 HTML5 Video 播放。
          </video>
          <!-- 🎨 原生 DVD 圖形字幕 (SPU Canvas) 覆蓋圖層 -->
          <canvas id="graphic-subtitle-canvas" class="subtitle-overlay-canvas"></canvas>

          <!-- 影片內全螢幕懸浮按鈕 (與外部工具列按鈕功能 100% 同步一致) -->
          <button type="button" class="video-overlay-fs-btn" id="video-overlay-fs-btn" onclick="toggleFullscreen()" title="切換全螢幕 (快速鍵 F 或 雙擊畫面)">
            <span class="fs-icon" id="video-overlay-fs-icon">⛶</span>
          </button>

          <!-- 浮動於影片畫面上的接續播放提示膠囊 (Floating Resume Overlay) -->
          <div class="resume-banner" id="resume-banner">
            <div class="resume-info">
              <span>📌 偵測到上次觀看到 <strong id="resume-time-str" style="color:var(--cyan)">00:00</strong></span>
            </div>
            <div class="resume-actions">
              <button class="btn btn-primary" onclick="confirmResume()">▶ 接續播放</button>
              <button class="btn resume-btn-dismiss" onclick="dismissResume()">從頭開始</button>
            </div>
          </div>

          <!-- ⏳ 智慧防卡頓緩衝守護 HUD (Anti-Stutter Buffer Guard HUD) -->
          <div class="buffer-guard-overlay" id="buffer-guard-overlay">
            <div class="buffer-guard-box">
              <div class="buffer-guard-spinner"></div>
              <div class="buffer-guard-title">網路壅塞緩衝中，正在智慧充沛緩衝區...</div>
              <div class="buffer-guard-bar-track">
                <div class="buffer-guard-bar-fill" id="buffer-guard-bar-fill" style="width:0%;"></div>
              </div>
              <div class="buffer-guard-desc">
                已蓄積 <span id="buffer-guard-cur-sec">0.0</span>s / 安全水位 <span id="buffer-guard-target-sec">4.0</span>s
              </div>
              <button type="button" class="buffer-guard-skip-btn" id="buffer-guard-skip-btn" onclick="skipBufferGuard()">立即播放</button>
            </div>
          </div>

          <!-- 純音訊播放專屬視覺舞台 -->
          <div class="audio-visual-stage" id="audio-visual-stage" style="display:none;">
            <div class="audio-disc-wrap" id="audio-disc-wrap">
              <div class="audio-disc-center">🎵</div>
            </div>
            <div class="audio-track-info">
              <div class="audio-track-title" id="audio-track-title">音樂/音訊播放中</div>
            </div>
            <div class="audio-spectrum-bars" id="audio-spectrum-bars">
              <span></span><span></span><span></span><span></span>
              <span></span><span></span><span></span><span></span>
            </div>
          </div>

          <!-- 播放/暫停中央狀態視覺徽章 -->
          <div class="video-play-indicator" id="video-play-indicator"></div>

          <!-- ⏩/⏪ 快轉/倒退畫面浮動資訊 HUD (淡入/淡出) -->
          <div class="video-seek-indicator" id="video-seek-indicator">
            <div class="seek-indicator-icon" id="seek-indicator-icon">⏩</div>
            <div class="seek-indicator-content">
              <div class="seek-indicator-delta" id="seek-indicator-delta">+5 秒</div>
              <div class="seek-indicator-time" id="seek-indicator-time">00:00 / 00:00</div>
            </div>
          </div>
        </div>

        <!-- 字幕選擇列（有字幕時顯示） -->
        <div class="subtitle-bar" id="subtitle-bar" style="display:none;">
          <div class="subtitle-bar-label" id="subtitle-bar-label" onclick="toggleSubtitleDropdown(event)" title="點擊切換字幕軌道">
            <span class="sub-label-icon">💬</span>
            <span class="sub-label-title">字幕:</span>
            <span class="subtitle-active-tag" id="subtitle-active-tag">關閉</span>
            <span class="subtitle-badge" id="subtitle-count-badge">0</span>
            <span class="sub-caret" id="sub-caret">▾</span>

            <!-- 下拉字幕清單選單 (平時隱藏，點擊 .subtitle-bar-label 展開) -->
            <div class="subtitle-dropdown-menu" id="subtitle-dropdown-menu" onclick="event.stopPropagation()">
              <div class="subtitle-dropdown-header">
                <span>選擇字幕軌道</span>
                <span class="subtitle-dropdown-count" id="sub-dropdown-count">共 0 個</span>
              </div>
              <div class="subtitle-track-list" id="subtitle-track-list">
                <!-- 由 JS 動態注入 -->
              </div>
            </div>
          </div>
          <!-- 字幕樣式調整群組（字級、色彩、陰影） -->
          <div class="subtitle-style-controls" id="subtitle-style-controls" style="display:none;">
            <!-- 字級縮放 -->
            <div class="subtitle-size-group" id="subtitle-size-group">
              <span class="subtitle-size-label" id="subtitle-size-label" onclick="resetSubtitleSize()" title="當前為預設字級 1.0x (點擊重設)" role="button" tabindex="0">
                <span>字級</span><span class="sub-size-val" id="sub-size-val">1.0x</span>
              </span>
              <button class="subtitle-size-btn" onclick="adjustSubtitleSize(-0.1)" title="縮小字幕">A−</button>
              <button class="subtitle-size-btn" onclick="adjustSubtitleSize(0.1)" title="放大字幕">A+</button>
            </div>
            <!-- 色彩切換 (改為與字幕相同的下拉選單邏輯，大幅節省空間) -->
            <div class="subtitle-color-group" id="subtitle-color-group">
              <div class="sub-color-picker-btn" id="sub-color-picker-btn" onclick="toggleSubtitleColorDropdown(event)" title="點擊選擇字幕色彩">
                <span id="sub-color-current-label">🎨 🎬 原色</span>
                <span class="sub-caret" id="sub-color-caret">▾</span>

                <!-- 下拉色彩清單 (平時隱藏，點擊展開) -->
                <div class="subtitle-dropdown-menu subtitle-color-dropdown-menu" id="subtitle-color-dropdown-menu" onclick="event.stopPropagation()">
                  <div class="subtitle-dropdown-header">選擇字幕色彩</div>
                  <div class="subtitle-color-options">
                    <button type="button" class="sub-color-opt active" id="sub-color-original" onclick="setSubtitleColorMode('original'); closeSubtitleColorDropdown();" title="DVD 原始母帶色彩 (預設)">
                      <span>🎬 原色 (預設)</span>
                      <span class="sub-item-check">✓</span>
                    </button>
                    <button type="button" class="sub-color-opt" id="sub-color-yellow" onclick="setSubtitleColorMode('yellow'); closeSubtitleColorDropdown();" title="高對比經典亮黃">
                      <span>🟡 亮黃</span>
                      <span class="sub-item-check"></span>
                    </button>
                    <button type="button" class="sub-color-opt" id="sub-color-white" onclick="setSubtitleColorMode('white'); closeSubtitleColorDropdown();" title="清晰純白字體">
                      <span>⚪ 純白</span>
                      <span class="sub-item-check"></span>
                    </button>
                    <button type="button" class="sub-color-opt" id="sub-color-cyan" onclick="setSubtitleColorMode('cyan'); closeSubtitleColorDropdown();" title="科技青綠字體">
                      <span>🟢 青綠</span>
                      <span class="sub-item-check"></span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 輔助工具列 -->
        <div class="player-toolbar">
          <div class="toolbar-group speed-seek-group">
            <span class="toolbar-label speed-label">倍速:</span>
            <select class="speed-select" id="speed-select" onchange="setSpeed(this.value)" title="調整播放倍速">
              <option value="0.5">0.5x</option>
              <option value="0.75">0.75x</option>
              <option value="1" selected>1.0x</option>
              <option value="1.25">1.25x</option>
              <option value="1.5">1.5x</option>
              <option value="1.75">1.75x</option>
              <option value="2">2.0x</option>
            </select>
            <span class="toolbar-label seek-label">快轉:</span>
            <div class="seek-segmented-group" title="快轉與倒退跳轉">
              <button class="seek-btn rewind" onclick="seekRelative(-600)" title="倒退 10 分鐘">-10m</button>
              <button class="seek-btn rewind" onclick="seekRelative(-300)" title="倒退 5 分鐘">-5m</button>
              <button class="seek-btn rewind" onclick="seekRelative(-60)" title="倒退 1 分鐘">-1m</button>
              <button class="seek-btn forward" onclick="seekRelative(60)" title="快進 1 分鐘">+1m</button>
              <button class="seek-btn forward" onclick="seekRelative(300)" title="快進 5 分鐘">+5m</button>
              <button class="seek-btn forward" onclick="seekRelative(600)" title="快進 10 分鐘">+10m</button>
            </div>
            <!-- 即時緩衝水位指示器 -->
            <div class="buffer-health-badge" id="buffer-health-badge" title="即時緩衝水位 (目前已超前預載秒數)">
              <span class="buffer-health-dot" id="buffer-health-dot"></span>
              <span class="buffer-health-text" id="buffer-health-text">緩衝: --</span>
            </div>
          </div>

          <div class="toolbar-group episode-group">
            <button class="btn btn-primary btn-icon" id="btn-prev-ep" onclick="playPrevEpisode()" title="播放上一集 (快速鍵 P)">
              ⏮
            </button>
            <button class="btn btn-primary btn-icon" id="btn-next-ep" onclick="playNextEpisode()" title="播放下一集 (快速鍵 N)">
              ⏭
            </button>
            <select class="loop-select mode-list" id="loop-mode-select" onchange="setLoopMode(this.value, true)" title="循環播放模式 (快速鍵 L 切換)">
              <option value="list" selected>🔁 目錄循環</option>
              <option value="single">🔂 單片循環</option>
              <option value="random">🔀 隨機循環</option>
              <option value="off">⏹️ 播畢即停</option>
            </select>
            <button type="button" class="pill-btn ambilight-btn active" id="btn-ambilight-toggle" onclick="toggleAmbilight()" title="切換劇院環境動態光暈 (WebGL Ambilight)">
              <span id="ambilight-btn-icon">💡</span> <span id="ambilight-btn-text">環境光</span>
            </button>
            <button type="button" class="pill-btn cast-btn" id="btn-cast" onclick="handleCastButtonClick()" title="投影至 Google TV / 電視 / AirPlay 或查看電視串流網址">
              <span id="btn-cast-icon">📺</span> <span id="btn-cast-text">投放</span>
            </button>
            <button class="pill-btn" id="btn-fullscreen" onclick="toggleFullscreen()" title="切換全螢幕 (快速鍵 F 或 雙擊畫面)">
              <span id="btn-fs-icon">⛶</span> <span id="btn-fs-text">全螢幕</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 影片詳情卡片 -->
      <div class="video-info-card" id="video-info-card">
        <div class="video-title-row">
          <h1 class="video-title" id="current-title">請由右側選取影片播放...</h1>
          <span class="format-badge MP4" id="current-format-badge" style="display:none;">MP4</span>
          <button class="pill-btn share-btn" id="btn-share-link" onclick="copyVideoShareLink()" title="複製此影片直接播放連結至剪貼簿，方便分享給家人觀看" style="display:none;">
            <span id="share-btn-icon">🔗</span> <span id="share-btn-text">複製連結</span>
          </button>
          <button class="pill-btn precache-btn" id="btn-precache" onclick="startPrecacheCurrentVideo()" title="將此影片/音訊預先載入瀏覽器快取，播放完全零網路延遲 (適合中小型檔案與音樂)" style="display:none;">
            <span id="precache-btn-icon">⚡</span> <span id="precache-btn-text">離線快取</span>
          </button>
          <button class="pill-btn tv-cast-btn" id="btn-tv-cast" onclick="openTvCastModal()" title="Google TV / 智慧電視播放助手 (支援 Chromecast、VLC、Nova Video Player、AirPlay)" style="display:none;">
            <span id="tv-cast-icon">📺</span> <span id="tv-cast-text">電視播放</span>
          </button>
          <button type="button" class="pill-btn guide-btn" id="btn-show-guide" onclick="toggleServerGuide()" title="查看伺服器端設定說明與實用技巧">
            <span id="guide-btn-icon">💡</span> <span id="guide-btn-text">設定與技巧</span>
          </button>
        </div>
        <div class="video-meta-pills">
          <div class="meta-pill" id="current-path-pill" onclick="handlePathPillClick()" title="點擊檢視目前目錄播放清單">📁 路徑: <span id="current-rel-path" style="color:var(--text);">-</span></div>
          <button type="button" class="folder-share-btn" id="btn-share-folder" onclick="copyCurrentFolderShareLink(this)" title="複製當前影片所屬資料夾直達連結至剪貼簿，方便分享給家人" style="display:none;">
            <span>📁🔗</span> <span>複製目錄連結</span>
          </button>
          <div class="meta-pill">💾 大小: <span id="current-size" style="color:var(--text);">-</span></div>
          <div class="meta-pill">🕒 更新: <span id="current-mtime" style="color:var(--text);">-</span></div>
        </div>
        <!-- 內嵌字幕資訊（由 fetchAndRenderSubtitles 動態更新）-->
        <div id="embedded-subs-row" style="display:none;margin-top:4px;">
          <div class="meta-pill" style="border-color:rgba(192,132,252,0.35);background:rgba(192,132,252,0.07);display:flex;align-items:flex-start;flex-wrap:wrap;gap:4px;">
            <span style="white-space:nowrap;padding-top:2px;">🔤 內嵌字幕:</span>
            <div id="embedded-subs-text" style="display:inline-flex;flex-wrap:wrap;gap:4px;"></div>
            <span id="embedded-image-note" style="display:none;color:var(--text3);font-size:0.7em;margin-left:4px;padding-top:2px;">（圖形字幕，無法在瀏覽器播放）</span>
          </div>
        </div>

        <div class="shortcuts-box">
          <span>操作提示：</span>
          <span><span class="kbd">空白鍵 / 點擊畫面</span> 播放/暫停</span>
          <span><span class="kbd">←</span> <span class="kbd">→</span> 快退/快進 5秒 (Shift 1分 / Ctrl 5分)</span>
          <span><span class="kbd">↑</span> <span class="kbd">↓</span> 音量</span>
          <span><span class="kbd">F</span> 全螢幕</span>
          <span><span class="kbd">M</span> 靜音</span>
          <span><span class="kbd">P</span> <span class="kbd">N</span> 上/下一集</span>
          <span><span class="kbd">L</span> 循環模式</span>
        </div>
      </div>

      <!-- ══ 伺服器端設定說明與常用技巧面板 (Server Guide & Pro Tips) ══ -->
      <div class="server-guide-card" id="server-guide-card">
        
        <!-- 頂部資訊與狀態列 -->
        <div class="guide-header">
          <div class="guide-header-left">
            <div class="guide-header-icon">🎬</div>
            <div class="guide-header-text">
              <h2 class="guide-title">NAS 影片播放 · 伺服器運作中心</h2>
              <p class="guide-subtitle">NAS Video Player — Server Configuration &amp; Pro Tips Guide</p>
            </div>
          </div>
          <div class="guide-header-right">
            <button type="button" class="btn btn-primary guide-return-btn" id="guide-back-to-player-btn" onclick="hideServerGuide()" style="display:none;" title="返回影片播放器">
              <span class="guide-return-icon">▶</span>
              <span class="guide-return-label">返回影片播放</span>
              <span class="guide-title-wrap">(<span class="guide-playing-title" id="guide-playing-title"></span>)</span>
            </button>
          </div>
        </div>

        <!-- 系統狀態指標晶片列 -->
        <div class="guide-badges-row">
          <div class="guide-badge">
            <span class="badge-dot dot-cyan"></span>
            <span class="badge-label">系統環境:</span>
            <span class="badge-value">Apache 2.4</span>
          </div>
          <div class="guide-badge">
            <span class="badge-dot dot-blue"></span>
            <span class="badge-label">後端核心:</span>
            <span class="badge-value">PHP <?php echo PHP_VERSION; ?></span>
          </div>
          <div class="guide-badge">
            <span class="badge-dot <?php echo has_pin_protection() ? 'dot-purple' : 'dot-amber'; ?>"></span>
            <span class="badge-label">安全保護:</span>
            <span class="badge-value"><?php echo has_pin_protection() ? 'PIN 碼閘門已啟用' : '免密碼直接放行'; ?></span>
          </div>
          <div class="guide-badge">
            <span class="badge-dot dot-green"></span>
            <span class="badge-label">快取防護:</span>
            <span class="badge-value">5 分鐘智慧快取 (Zero-IO)</span>
          </div>
          <div class="guide-badge">
            <span class="badge-dot dot-cyan"></span>
            <span class="badge-label">串流協定:</span>
            <span class="badge-value">HTTP 206 Partial Content</span>
          </div>
          <?php if (isset($_GET['dir']) && !empty($_GET['dir'])): ?>
          <div class="guide-badge guide-badge-testing" title="目前處於自訂路徑測試模式，點擊下方「回復原設定」可還原">
            <span class="badge-dot dot-amber"></span>
            <span class="badge-label">執行模式:</span>
            <span class="badge-value">自訂路徑測試中</span>
          </div>
          <?php endif; ?>
        </div>

        <!-- 當前未載入影片或目錄異常時的診斷提示 -->
        <div class="guide-alert-box <?php echo $initial_data['dir_exists'] ? 'info' : 'warning'; ?>" id="guide-status-alert">
          <div class="guide-alert-icon"><?php echo $initial_data['dir_exists'] ? '💡' : '⚠️'; ?></div>
          <div class="guide-alert-body">
            <div class="guide-alert-title">
              <?php if (!$initial_data['dir_exists']): ?>
                尚未偵測到影片資料夾或外接儲存裝置未掛載
              <?php else: ?>
                目前尚未選取或正在播放影片
              <?php endif; ?>
            </div>
            <div class="guide-alert-desc">
              系統目前生效之讀取路徑：<code class="guide-code-inline"><?php echo htmlspecialchars(implode(', ', $video_dirs)); ?></code>
              <?php if (isset($_GET['dir']) && !empty($_GET['dir'])): ?>
                <br><span class="guide-orig-note">（💡 系統原始預設路徑：<code class="guide-code-inline-sub"><?php echo htmlspecialchars(implode(', ', $default_video_dirs)); ?></code>）</span>
              <?php endif; ?>
              <br>
              <?php if (!$initial_data['dir_exists']): ?>
                請確認 NAS 磁碟或 USB 隨身碟已正確插入並掛載，或於下方直接輸入有效 NAS 絕對路徑進行即時測試：
              <?php else: ?>
                請從右側「影音清單」選取任何一部影片開始播放，或查閱下方伺服器端配置說明與常用技巧。
              <?php endif; ?>
            </div>
            <div class="guide-path-switcher">
              <input type="text" id="custom-dir-input" class="guide-path-input" value="<?php echo htmlspecialchars(isset($_GET['dir']) && !empty($_GET['dir']) ? $_GET['dir'] : implode(', ', $default_video_dirs)); ?>" placeholder="輸入 NAS 絕對路徑，多個路徑以逗點分隔，例如 /share/USB1/@影片..." onkeydown="if(event.key==='Enter'){changeCustomDir();}">
              <button type="button" class="btn btn-primary" onclick="changeCustomDir()" title="以輸入的路徑重新整理頁面進行測試">切換測試</button>
              <button type="button" class="btn guide-reset-btn <?php echo (isset($_GET['dir']) && !empty($_GET['dir'])) ? 'active-test' : ''; ?>" id="btn-reset-dir" onclick="resetCustomDir()" title="回復為系統原始設定路徑 (移除測試參數，還原 .env 設定)">↺ 回復原設定</button>
              <?php if (isset($_GET['dir']) && !empty($_GET['dir'])): ?>
                <span class="guide-test-badge" title="目前正以自訂 URL 參數 (?dir=...) 測試路徑">🧪 測試模式中</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- 分頁導航列 -->
        <div class="guide-nav-tabs">
          <button type="button" class="guide-tab-btn active" data-tab="config" onclick="switchGuideTab('config')">
            <span class="tab-icon">⚙️</span>
            <span>伺服器配置與掛載</span>
          </button>
          <button type="button" class="guide-tab-btn" data-tab="tips" onclick="switchGuideTab('tips')">
            <span class="tab-icon">🎯</span>
            <span>播放控制與快捷鍵</span>
          </button>
          <button type="button" class="guide-tab-btn" data-tab="streaming" onclick="switchGuideTab('streaming')">
            <span class="tab-icon">📺</span>
            <span>電視投屏與外部串流</span>
          </button>
          <button type="button" class="guide-tab-btn" data-tab="subtitles" onclick="switchGuideTab('subtitles')">
            <span class="tab-icon">💬</span>
            <span>字幕解碼與效能引擎</span>
          </button>
        </div>

        <!-- 分頁內容容器 -->
        <div class="guide-tab-content">
          
          <!-- ══ Tab 1: 伺服器端配置與掛載 ══ -->
          <div class="guide-tab-pane active" id="guide-tab-config">
            <div class="guide-cards-grid">
              
              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">📂</span>
                  <h3 class="sec-title">影片庫路徑配置 (.env)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>本系統支援單一或多個資料夾聯合索引。您可於專案根目錄之 <code>.env</code> 檔案中自訂 <code>VIDEO_DIRS</code> 參數：</p>
                  <pre class="guide-code-block"><code># 支援以逗號 (,)、分號 (;) 或直線 (|) 分隔多個媒體庫路徑
VIDEO_DIRS="/share/USB1/@影片,/share/USB1/@保存影片,/volume1/Media"</code></pre>
                  <ul class="guide-list">
                    <li><strong>多目錄聯合索引</strong>：若設定多個目錄，根目錄將自動整合為多資料庫聯合瀏覽視圖，且支援跨目錄最新影音掃描。</li>
                    <li><strong>外接 USB 裝置</strong>：ASUSTOR NAS 外接硬碟或隨身碟預設掛載於 <code>/share/USB1/</code> 或 <code>/share/USB2/</code>。</li>
                    <li><strong>內建硬碟卷冊</strong>：本機儲存空間通常位於 <code>/volume1/</code>、<code>/volume2/</code>。</li>
                  </ul>
                  <div class="guide-dirs-summary">
                    <div><strong>當前生效路徑：</strong><code><?php echo htmlspecialchars(implode(', ', $video_dirs)); ?></code></div>
                    <div><strong>原始系統路徑：</strong><code><?php echo htmlspecialchars(implode(', ', $default_video_dirs)); ?></code></div>
                    <?php if (isset($_GET['dir']) && !empty($_GET['dir'])): ?>
                      <div style="margin-top:8px;">
                        <span class="guide-test-badge" style="margin-right:8px;">🧪 目前處於自訂測試路徑</span>
                        <button type="button" class="btn guide-reset-btn active-test" onclick="resetCustomDir()" style="padding:3px 10px;font-size:0.78rem;">↺ 立即回復原始設定</button>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">🎬</span>
                  <h3 class="sec-title">支援之影音與字幕格式矩陣</h3>
                </div>
                <div class="guide-sec-content">
                  <div class="guide-table-wrap">
                    <table class="guide-table">
                      <thead>
                        <tr>
                          <th>類別</th>
                          <th>副檔名</th>
                          <th>特性說明</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td><strong>視訊格式</strong></td>
                          <td><code>.mp4</code> <code>.webm</code> <code>.mkv</code> <code>.m4v</code> <code>.mov</code> <code>.ts</code> <code>.avi</code></td>
                          <td>支援 H.264 / HEVC / VP9 原生分段串流，隨拖隨播。</td>
                        </tr>
                        <tr>
                          <td><strong>音訊格式</strong></td>
                          <td><code>.mp3</code> <code>.flac</code> <code>.m4a</code> <code>.wav</code> <code>.aac</code> <code>.ogg</code> <code>.opus</code></td>
                          <td>高傳真音質，內建專屬 CD 唱盤視覺舞台與動態頻譜。</td>
                        </tr>
                        <tr>
                          <td><strong>外掛字幕</strong></td>
                          <td><code>.vtt</code> <code>.srt</code> <code>.ass</code> <code>.ssa</code> <code>.sub</code></td>
                          <td>同目錄同檔名字幕自動辨識，SRT 即時轉換標準 WebVTT。</td>
                        </tr>
                        <tr>
                          <td><strong>內嵌字幕</strong></td>
                          <td>EBML (MKV) / ISOBMFF (MP4) / DVD SPU</td>
                          <td>純 PHP 零依賴二進位提取，DVD 圖形字幕前端即時渲染。</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">⚡</span>
                  <h3 class="sec-title">快取機制與權限需求 (Zero-IO Protection)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>針對低功耗 NAS 硬體（如 Intel Atom 雙核心），系統內建 <strong>5 分鐘智慧快取機制</strong>：</p>
                  <ul class="guide-list">
                    <li><strong>保護 NAS 硬碟</strong>：避免多次頻繁造訪觸發硬碟持續尋道 (Head Seek)，降低磨損與噪音。</li>
                    <li><strong>極速瞬間回應</strong>：全站最新 40 部影片與資料夾索引一經掃描即序列化為 JSON，再次讀取耗時 &lt; 5ms。</li>
                    <li><strong>權限配置</strong>：系統將快取儲存於 <code>cache/</code> 目錄，請確認具備寫入權限：<br>
                      <code class="guide-code-inline">chmod -R 777 cache/</code>
                    </li>
                    <li><strong>強制重整</strong>：點擊播放清單右上角「↻」按鈕可隨時強制穿透快取並即時重新掃描磁碟。</li>
                  </ul>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">🛡️</span>
                  <h3 class="sec-title">安全閘門與 PIN 碼認證 (auth.php)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>系統內建無狀態 HMAC-SHA256 簽章憑證認證閘門：</p>
                  <ul class="guide-list">
                    <li><strong>啟用密碼保護</strong>：於 <code>.env</code> 設定 <code>PIN=您的密碼</code>，未授權存取將被導向深色終端解鎖面板。</li>
                    <li><strong>家庭免密碼模式</strong>：若在封閉家庭區網使用且無需認證，只需將 <code>.env</code> 之 <code>PIN=</code> 設為空白即可全站直接暢通。</li>
                    <li><strong>電視穿透簽章</strong>：支援為 Google TV / Chromecast 產生短期 Token 帶入 URL，無需在電視端繁瑣輸入密碼。</li>
                  </ul>
                </div>
              </div>

            </div>
          </div>

          <!-- ══ Tab 2: 播放控制與常用操作技巧 ══ -->
          <div class="guide-tab-pane" id="guide-tab-tips">
            <div class="guide-cards-grid">
              
              <div class="guide-section-card full-width">
                <div class="guide-sec-header">
                  <span class="sec-icon">⌨️</span>
                  <h3 class="sec-title">鍵盤快速鍵全對照地圖 (Keyboard Shortcuts)</h3>
                </div>
                <div class="guide-sec-content">
                  <div class="guide-kbd-grid">
                    <div class="kbd-item"><span class="kbd">空白鍵</span> 或 <span class="kbd">K</span> <span class="kbd-desc">播放 / 暫停切換</span></div>
                    <div class="kbd-item"><span class="kbd">←</span> / <span class="kbd">→</span> <span class="kbd-desc">快退 / 快進 5 秒</span></div>
                    <div class="kbd-item"><span class="kbd">Shift</span> + <span class="kbd">←</span> / <span class="kbd">→</span> <span class="kbd-desc">快退 / 快進 1 分鐘</span></div>
                    <div class="kbd-item"><span class="kbd">Ctrl</span> + <span class="kbd">←</span> / <span class="kbd">→</span> <span class="kbd-desc">快退 / 快進 5 分鐘</span></div>
                    <div class="kbd-item"><span class="kbd">↑</span> / <span class="kbd">↓</span> <span class="kbd-desc">音量微調 (5% 步進)</span></div>
                    <div class="kbd-item"><span class="kbd">M</span> <span class="kbd-desc">靜音 / 恢復音量</span></div>
                    <div class="kbd-item"><span class="kbd">F</span> 或 雙擊畫面 <span class="kbd-desc">切換全螢幕劇院模式</span></div>
                    <div class="kbd-item"><span class="kbd">P</span> / <span class="kbd">N</span> <span class="kbd-desc">播放上一部 / 下一部</span></div>
                    <div class="kbd-item"><span class="kbd">L</span> <span class="kbd-desc">切換循環模式 (目錄/單片/隨機/即停)</span></div>
                    <div class="kbd-item"><span class="kbd">0</span> ~ <span class="kbd">9</span> <span class="kbd-desc">跳轉至影片 0% ~ 90% 位置</span></div>
                  </div>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">📌</span>
                  <h3 class="sec-title">智慧記憶與接續播放 (Smart Resume)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>觀看任何影片時，系統均會透過 HTML5 本機儲存 (localStorage) 即時記錄精確秒數：</p>
                  <ul class="guide-list">
                    <li>下次再次點擊同一部影片時，畫面上會自動浮現<strong>「📌 偵測到上次觀看到 XX:XX」</strong>接續播放提示膠囊。</li>
                    <li>點擊「▶ 接續播放」即可無縫銜接上次進度，省去手動拖曳進度條尋找片段的困擾。</li>
                    <li>觀看超過 95% 時系統會自動視為已播畢並重設進度。</li>
                  </ul>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">⚡</span>
                  <h3 class="sec-title">離線極速快取 (Browser Memory Precache)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>針對喜愛的音樂、動漫或中小型影片，可善用播放器下方的<strong>「⚡ 離線快取」</strong>功能：</p>
                  <ul class="guide-list">
                    <li>系統會以背景多執行緒串流將檔案完整緩存至瀏覽器 Blob 記憶體。</li>
                    <li>快取完成後，影片拖曳進度條完全<strong>零延遲、零緩衝</strong>，即便斷開區域網路亦能持續順暢播放。</li>
                    <li>特別適合在 Wi-Fi 訊號微弱或網路被其他人大量佔用時使用。</li>
                  </ul>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">⏳</span>
                  <h3 class="sec-title">智慧防卡頓緩衝守護 (Anti-Stutter Buffer Guard)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>當播放大碼率 4K/1080p 影片遭遇網路暫時性壅塞時：</p>
                  <ul class="guide-list">
                    <li>系統會自動喚醒<strong>「緩衝守護 HUD」</strong>，暫停畫面並全力蓄積資料。</li>
                    <li>預載秒數達到 4.0 秒安全水位時將自動恢復流暢播放，徹底消滅每隔數秒卡頓一次的劣化體驗。</li>
                  </ul>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">🔍</span>
                  <h3 class="sec-title">多目錄即時搜尋與排序切換</h3>
                </div>
                <div class="guide-sec-content">
                  <p>右側播放清單支援多維度即時篩選：</p>
                  <ul class="guide-list">
                    <li><strong>全域即時搜尋</strong>：輸入關鍵字（支援繁簡中文、拼音與副檔名）即時過濾當前資料夾內容。</li>
                    <li><strong>排序維度切換</strong>：支援點擊「⏱ 時間」或「🔤 名稱」切換排序基準，並可切換「▼ 遞減」與「▲ 遞增」。</li>
                  </ul>
                </div>
              </div>

            </div>
          </div>

          <!-- ══ Tab 3: 電視投屏與外部串流 ══ -->
          <div class="guide-tab-pane" id="guide-tab-streaming">
            <div class="guide-cards-grid">
              
              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">📺</span>
                  <h3 class="sec-title">大螢幕電視投放 (Google TV / Chromecast)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>點擊播放器標題旁的<strong>「📺 電視播放」</strong>即可呼叫大螢幕投放助手：</p>
                  <ul class="guide-list">
                    <li><strong>自簽穿透 Token</strong>：系統會自動為投屏網址附加安全簽章，免除在電視瀏覽器上輸入 PIN 碼的繁瑣操作。</li>
                    <li><strong>原生 Cast 協議</strong>：若使用 Chrome 或 Edge 瀏覽器，可直接透過瀏覽器內建 Cast 功能投放畫面至支援的智慧電視。</li>
                  </ul>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">🚀</span>
                  <h3 class="sec-title">外部專業播放器協議直連</h3>
                </div>
                <div class="guide-sec-content">
                  <p>投放面板支援一鍵喚醒本機或行動裝置上的專業播放應用程式：</p>
                  <ul class="guide-list">
                    <li><strong>VLC Media Player</strong>：支援點擊 <code>vlc://</code> 直連開啟，享受硬體解碼與音效直通，並可在 VLC 中一鍵投射。</li>
                    <li><strong>Web Video Caster (WVC)</strong>：強大的電視投屏遙控器 App，支援 DLNA、Roku、FireTV 與 LG/Samsung 智慧電視。</li>
                    <li><strong>nPlayer / Nova Video Player</strong>：行動端影音神器，支援硬解 DTS/AC3 音軌與即時外掛字幕載入。</li>
                  </ul>
                </div>
              </div>

              <div class="guide-section-card full-width">
                <div class="guide-sec-header">
                  <span class="sec-icon">📱</span>
                  <h3 class="sec-title">行動端掃碼接續觀看 (Dynamic QR Code)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>投放彈窗與播放器內建純本機離線繪製之高解析度 QR Code：</p>
                  <ul class="guide-list">
                    <li>拿出手機或 iPad 平板相機掃描畫面上之 QR Code，即可在手持裝置上立即接續播放當前影片。</li>
                    <li>免手動輸入複雜的 NAS 區域網路 IP 位址與長檔名路徑，家庭成員隨掃即看。</li>
                  </ul>
                </div>
              </div>

            </div>
          </div>

          <!-- ══ Tab 4: 字幕解碼與效能引擎 ══ -->
          <div class="guide-tab-pane" id="guide-tab-subtitles">
            <div class="guide-cards-grid">
              
              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">🔤</span>
                  <h3 class="sec-title">純 PHP 內嵌字幕解析 (EBML &amp; ISOBMFF)</h3>
                </div>
                <div class="guide-sec-content">
                  <p>本系統包含完全以純 PHP 5.6 原生撰寫的二進位多媒體容器剖析引擎 (<code>video_subtitles.php</code>)：</p>
                  <ul class="guide-list">
                    <li><strong>MKV / WebM EBML 解析</strong>：僅讀取前 2MB Header 即可提取內嵌 UTF-8 / SRT 字幕軌道。</li>
                    <li><strong>MP4 / MOV ISOBMFF 解析</strong>：遍歷 <code>moov.trak.mdia</code> 盒子結構，提取 <code>tx3g</code> 與標準文字字幕。</li>
                    <li><strong>零外部依賴</strong>：即使 NAS 上完全沒有安裝 FFmpeg，亦能順利顯示內嵌文字字幕。</li>
                  </ul>
                </div>
              </div>

              <div class="guide-section-card">
                <div class="guide-sec-header">
                  <span class="sec-icon">🎨</span>
                  <h3 class="sec-title">DVD SPU 圖形字幕前端解碼引擎</h3>
                </div>
                <div class="guide-sec-content">
                  <p>針對老舊經典 DVD 或日漫常見的二進位位元點陣圖形字幕 (DVD-Video Sub-picture)：</p>
                  <ul class="guide-list">
                    <li><strong>Canvas 即時像素解碼</strong>：後端提取 DCSQ 二進位數據包，前端使用原生 Canvas API 即時逐幀繪製。</li>
                    <li><strong>自訂色彩矩陣</strong>：支援切換「🎬 原色 (母帶)」、「🟡 高對比亮黃」、「⚪ 清晰純白」與「🟢 科技青綠」。</li>
                    <li><strong>字級縮放 (A- / A+)</strong>：可任意調整字幕顯示大小與位置，大幅改善觀影舒適度。</li>
                  </ul>
                </div>
              </div>

              <div class="guide-section-card full-width">
                <div class="guide-sec-header">
                  <span class="sec-icon">🌐</span>
                  <h3 class="sec-title">HTTP 206 Partial Content 分段串流技術</h3>
                </div>
                <div class="guide-sec-content">
                  <p>核心串流模組 (<code>video_stream.php</code>) 專為低功耗 NAS 量身打造：</p>
                  <ul class="guide-list">
                    <li><strong>64 位元大於 2GB 巨量影片相容</strong>：克服 32-bit PHP (Intel Atom) 中 <code>filesize()</code> 與 <code>fseek()</code> 整數溢位限制。</li>
                    <li><strong>256KB 循環緩衝 Chunk</strong>：按需傳輸，降低伺服器記憶體開銷，保證大檔串流期間 NAS CPU 負載維持在最低水位。</li>
                    <li><strong>完整 CORS 與 Range 標頭</strong>：全域回應 <code>206 Partial Content</code> 與 <code>Accept-Ranges: bytes</code>，讓瀏覽器與電視播放器能精確執行毫秒級 Seek 跳轉。</li>
                  </ul>
                </div>
              </div>

            </div>
          </div>

        </div> <!-- END .guide-tab-content -->

      </div> <!-- END #server-guide-card -->

    </section>

    <!-- ── 右側：資料夾導航與播放清單 (Drawer) ── -->
    <aside class="playlist-card">
      <div class="playlist-header">
        <div class="playlist-header-top">
          <div class="playlist-title">
            <span id="playlist-title-text">🔥 全站最新影音</span>
            <span class="playlist-counter" id="filter-counter">0 部</span>
          </div>
          <div class="playlist-header-actions">
            <button class="btn btn-icon" id="btn-sort-toggle" onclick="toggleSortOrder()" title="切換排序項目：時間 / 名稱">
              ⏱ 時間
            </button>
            <button class="btn btn-icon" id="btn-sort-dir" onclick="toggleSortDirection()" title="切換排序順序：遞減 / 遞增">
              ▼ 遞減
            </button>
            <button class="btn btn-icon" id="btn-refresh-folder" onclick="refreshCurrentFolder()" title="重新整理當前資料夾與清單" style="line-height:1.2;">
              ↻
            </button>
            <button class="mobile-drawer-close" onclick="closeMobileDrawer()" title="關閉選單">✕</button>
          </div>
        </div>

        <!-- 搜尋列與導航按鈕 (最新影音 / 根目錄 二合一動態切換) -->
        <div class="search-row">
          <div class="search-box">
            <input type="text" id="search-input" class="search-input" placeholder="🔍 搜尋當前資料夾或影片名稱..." oninput="handleSearch(this.value)">
            <button class="search-clear-btn" id="search-clear" onclick="clearSearch()">✕</button>
          </div>
          <button class="btn-nav-toggle" id="btn-recent-sidebar" onclick="toggleRecentMode()" title="檢視全站最新發布/更新的影音檔案">
            <span class="btn-nav-icon">🔥</span>
            <span class="btn-nav-text">最新</span>
          </button>
        </div>
      </div>

      <!-- 檔案總管式統一捲動清單（回到上一層、子資料夾與影片檔案整合） -->
      <div class="playlist-items" id="playlist-items">
        <!-- 由 JS 動態渲染檔案總管清單 -->
      </div>
    </aside>

    <!-- 手機版懸浮快速呼叫按鈕 (FAB) -->
    <button class="mobile-drawer-fab" id="mobile-drawer-fab" onclick="toggleMobileDrawer()" title="開啟播放清單">
      <span class="fab-icon">📋</span>
      <span class="fab-text">播放清單</span>
    </button>

  </main>

  <!-- ══ 前端 Vanilla JS 影音核心 ═══════════════════════════════════════════ -->
  <script>
    (function () {
      // 1. 初始化資料與快取
      const initialData = <?php echo $initial_json ? $initial_json : '{}'; ?>;
      const initialRecentData = <?php echo $initial_recent_json ? $initial_recent_json : '{}'; ?>;
      const baseDir = <?php echo json_encode($video_dir); ?>;
      window._videoBaseDir = baseDir; // 供 PreviewManager 外部腳本使用

      // ── 安全認證會話守衛：當 API 回傳 401 或 auth_required 時，轉址至 PIN 碼解鎖頁面 ──
      function handleAuthRequired() {
        var path = window.location.pathname.split('/').pop() || 'index.php';
        var search = window.location.search || '';
        if (search) {
          search = search.replace(/([?&])auth_token=[^&]*(&|$)/, '$1').replace(/[?&]$/, '');
        }
        var redirect = path + search + window.location.hash;
        window.location.replace('auth.php?redirect=' + encodeURIComponent(redirect));
      }

      // ── 安全 Fetch 包裝器：自動附加 X-Requested-With 並攔截 401 未認證狀態 ──
      async function authFetch(url, options) {
        options = options || {};
        options.headers = options.headers || {};
        if (!options.headers['X-Requested-With']) {
          options.headers['X-Requested-With'] = 'XMLHttpRequest';
        }
        var curParams = new URLSearchParams(window.location.search);
        if (curParams.has('dir')) {
          var dirVal = curParams.get('dir');
          if (url.indexOf('dir=') === -1) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'dir=' + encodeURIComponent(dirVal);
          }
        }
        var res = await fetch(url, options);
        if (res.status === 401) {
          handleAuthRequired();
          throw new Error('Authentication required');
        }
        return res;
      }
      window.authFetch = authFetch; // 供 PreviewManager 等外部獨立 <script> 區塊使用

      // ── 統一串流 URL 產生器（附加 Token 與轉換絕對網址，專供本機與 Google TV 投射）──
      window.buildStreamUrl = function (relPath, compat) {
        var u = 'index.php?action=stream&file=' + encodeURIComponent(relPath);
        var curParams = new URLSearchParams(window.location.search);
        if (curParams.has('dir')) {
          u += '&dir=' + encodeURIComponent(curParams.get('dir'));
        }
        if (window.AUTH_TOKEN) {
          u += '&auth_token=' + encodeURIComponent(window.AUTH_TOKEN);
        }
        if (compat) {
          u += '&compat=1';
        }
        return new URL(u, window.location.href).href;
      };

      // ── 產生直接播放分享網址（完整絕對路徑，方便複製分享給家人）──
      window.buildShareUrl = function (relPath) {
        var u = new URL('index.php', window.location.href);
        u.searchParams.set('play', relPath);
        var curParams = new URLSearchParams(window.location.search);
        if (curParams.has('dir')) {
          u.searchParams.set('dir', curParams.get('dir'));
        }
        // 注意：直連分享網址不附加個人 auth_token，確保新裝置依據安全規範提示輸入 PIN 碼
        u.searchParams.delete('auth_token');
        return u.href;
      };

      // ── 通用剪貼簿複製函式（支援現代 Clipboard API 與降級兼容）──
      function copyTextToClipboard(text, onSuccess, fallbackPromptMsg) {
        if (!text) return;
        var handled = false;
        function doSuccess() {
          if (handled) return;
          handled = true;
          if (typeof onSuccess === 'function') onSuccess();
        }
        function doFallback() {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.top = '0';
          ta.style.left = '-9999px';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.focus();
          ta.select();
          var ok = false;
          try { ok = document.execCommand('copy'); } catch(e) {}
          document.body.removeChild(ta);
          if (ok) {
            doSuccess();
          } else if (fallbackPromptMsg) {
            window.prompt(fallbackPromptMsg, text);
          }
        }

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(doSuccess).catch(doFallback);
        } else {
          doFallback();
        }
      }

      // ── QR Code 彈窗控制器 (100% 離線本地產製，零 CDN / 零外部依賴) ──
      var currentQrUrl = '';
      var currentQrTargetName = '';

      window.showQrModal = function (url, targetName) {
        if (!url) return;
        currentQrUrl = url;
        currentQrTargetName = targetName || '分享連結';

        var modal = document.getElementById('qr-modal');
        var backdrop = document.getElementById('qr-modal-backdrop');
        var badge = document.getElementById('qr-target-badge');
        var input = document.getElementById('qr-url-input');
        var qrImg = document.getElementById('qr-img');
        var qrCanvas = document.getElementById('qr-canvas');

        if (!modal || !backdrop) return;

        if (badge) badge.textContent = currentQrTargetName;
        if (input) input.value = currentQrUrl;

        // 離線生成卡片式 QR Code 畫布（自帶標題與掃描引導，社群傳送一目了然）
        try {
          if (typeof qrcode === 'function') {
            var qr = qrcode(0, 'M');
            qr.addData(currentQrUrl);
            qr.make();
            var count = qr.getModuleCount();
            var margin = 2;
            var totalModules = count + margin * 2;
            var targetQrSize = 250;
            var cellSize = Math.max(3, Math.floor(targetQrSize / totalModules));
            var actualQrSize = cellSize * totalModules;

            var cardPadding = 14;
            var cardWidth = actualQrSize + cardPadding * 2;
            var headerHeight = 42;
            var footerHeight = 28;
            var cardHeight = headerHeight + actualQrSize + footerHeight;

            if (qrCanvas) {
              qrCanvas.width = cardWidth;
              qrCanvas.height = cardHeight;
              var ctx = qrCanvas.getContext('2d');

              // 1. 純白底色
              ctx.fillStyle = '#ffffff';
              ctx.fillRect(0, 0, cardWidth, cardHeight);

              // 2. 頂部影片/目錄標題 (深色高對比字體，過長自動省略截斷)
              var cleanTitle = (currentQrTargetName || 'ASUSTOR 影音')
                .replace(/[\\/:*?"<>|]/g, ' ')
                .trim();
              ctx.fillStyle = '#0f172a';
              ctx.font = 'bold 13px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
              ctx.textAlign = 'center';
              ctx.textBaseline = 'middle';
              var maxTextWidth = cardWidth - 20;
              var displayTitle = cleanTitle;
              if (ctx.measureText(displayTitle).width > maxTextWidth) {
                while (displayTitle.length > 3 && ctx.measureText(displayTitle + '...').width > maxTextWidth) {
                  displayTitle = displayTitle.slice(0, -1);
                }
                displayTitle += '...';
              }
              ctx.fillText(displayTitle, cardWidth / 2, headerHeight / 2 + 4);

              // 3. 繪製中間黑白 QR Code 模組
              var qrOffsetY = headerHeight;
              ctx.fillStyle = '#ffffff';
              ctx.fillRect(cardPadding, qrOffsetY, actualQrSize, actualQrSize);
              ctx.fillStyle = '#000000';
              for (var r = 0; r < count; r++) {
                for (var c = 0; c < count; c++) {
                  if (qr.isDark(r, c)) {
                    ctx.fillRect(
                      cardPadding + (c + margin) * cellSize,
                      qrOffsetY + (r + margin) * cellSize,
                      cellSize,
                      cellSize
                    );
                  }
                }
              }

              // 4. 底部掃描引導提示
              ctx.fillStyle = '#64748b';
              ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
              ctx.textAlign = 'center';
              ctx.textBaseline = 'middle';
              ctx.fillText('📷 手機相機或 LINE 掃描直接觀看', cardWidth / 2, cardHeight - (footerHeight / 2));

              if (qrImg) {
                qrImg.src = qrCanvas.toDataURL('image/png');
              }
            }
          }
        } catch (err) {
          console.error('QR Code 離線產製失敗:', err);
        }

        backdrop.style.display = 'block';
        modal.style.display = 'flex';
        void backdrop.offsetHeight;
        void modal.offsetHeight;
        backdrop.classList.add('active');
        modal.classList.add('active');
      };

      window.closeQrModal = function () {
        var modal = document.getElementById('qr-modal');
        var backdrop = document.getElementById('qr-modal-backdrop');
        if (modal) modal.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
        setTimeout(function () {
          if (modal) modal.style.display = 'none';
          if (backdrop) backdrop.style.display = 'none';
        }, 220);
      };

      window.copyQrModalUrl = function () {
        if (!currentQrUrl) return;
        var btn = document.getElementById('btn-qr-copy-url');
        var txt = document.getElementById('qr-copy-url-text');
        copyTextToClipboard(currentQrUrl, function () {
          if (btn) btn.classList.add('copied');
          if (txt) txt.textContent = '已複製！';
          setTimeout(function () {
            if (btn) btn.classList.remove('copied');
            if (txt) txt.textContent = '複製網址';
          }, 2000);
          showToast('📋 已複製分享網址！');
        }, '請複製以下分享網址：');
      };

      window.downloadQrImage = function () {
        var qrCanvas = document.getElementById('qr-canvas');
        if (!qrCanvas) return;
        var cleanName = (currentQrTargetName || 'qrcode')
          .replace(/[\\/:*?"<>|]/g, '_')
          .replace(/^[🎬📁\s]+/, '')
          .trim();
        var filename = 'QR_' + (cleanName || 'link') + '.png';
        var a = document.createElement('a');
        a.download = filename;
        a.href = qrCanvas.toDataURL('image/png');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        showToast('💾 已下載 QR Code 圖片：' + filename);
      };

      window.copyQrImage = function () {
        var qrCanvas = document.getElementById('qr-canvas');
        if (!qrCanvas) return;
        if (qrCanvas.toBlob && navigator.clipboard && window.isSecureContext && typeof ClipboardItem !== 'undefined') {
          qrCanvas.toBlob(function (blob) {
            if (blob) {
              navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })])
                .then(function () {
                  var btn = document.getElementById('btn-qr-copy-img');
                  if (btn) {
                    btn.classList.add('copied');
                    setTimeout(function () { btn.classList.remove('copied'); }, 2000);
                  }
                  showToast('🖼️ 已將 QR Code 圖片複製到剪貼簿！可在通訊軟體直接貼上 (Ctrl+V)');
                })
                .catch(function () {
                  downloadQrImage();
                });
            }
          }, 'image/png');
        } else {
          downloadQrImage();
        }
      };

      // ── 取得 QR Code 圖片 Blob 與 File 物件的通用輔助函式 ──
      function getQrCodeImageFile(callback) {
        var qrCanvas = document.getElementById('qr-canvas');
        if (!qrCanvas || !qrCanvas.toBlob) {
          if (typeof callback === 'function') callback(null, null, 'QR_Code.png');
          return;
        }
        var cleanTitle = (currentQrTargetName || 'qrcode')
          .replace(/[\\/:*?"<>|]/g, '_')
          .replace(/^[🎬📁\s]+/, '')
          .trim();
        var filename = 'QR_' + (cleanTitle || 'video') + '.png';

        qrCanvas.toBlob(function (blob) {
          if (!blob) {
            if (typeof callback === 'function') callback(null, null, filename);
            return;
          }
          var file = null;
          try {
            file = new File([blob], filename, { type: 'image/png' });
          } catch (e) {
            file = null;
          }
          if (typeof callback === 'function') callback(file, blob, filename);
        }, 'image/png');
      }

      // ── 社群傳送：LINE 分享 (傳送 QR Code 圖片) ──
      window.shareToLine = function () {
        getQrCodeImageFile(function (file, blob, filename) {
          // 1. 若環境支援 Web Share API 傳送檔案 (行動端如 iOS Safari, Android Chrome)
          if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
            if (blob && navigator.clipboard && window.isSecureContext && typeof ClipboardItem !== 'undefined') {
              navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]).catch(function () {});
            }
            navigator.share({
              files: [file],
              title: (currentQrTargetName || '影音 QR Code')
            }).catch(function (err) {
              if (err && err.name !== 'AbortError') {
                fallbackLineCopy();
              }
            });
            return;
          }

          // 2. 降級備援：複製 QR Code 圖片至剪貼簿，提示直接在聊天室貼上，並開啟 LINE
          fallbackLineCopy();
        });

        function fallbackLineCopy() {
          copyQrImage();
          showToast('🖼️ 已複製 QR Code 圖片！可在 LINE 聊天室中長按「貼上」傳送！', 4000);
          setTimeout(function () {
            var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            var targetUrl = isMobile ? 'line://' : 'https://line.me/R/';
            window.open(targetUrl, '_blank', 'noopener,noreferrer');
          }, 400);
        }
      };

      // ── 社群傳送：Facebook 分享 (傳送 QR Code 圖片) ──
      window.shareToFacebook = function () {
        getQrCodeImageFile(function (file, blob, filename) {
          if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
            if (blob && navigator.clipboard && window.isSecureContext && typeof ClipboardItem !== 'undefined') {
              navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]).catch(function () {});
            }
            navigator.share({
              files: [file],
              title: (currentQrTargetName || '影音 QR Code')
            }).catch(function (err) {
              if (err && err.name !== 'AbortError') {
                fallbackFbCopy();
              }
            });
            return;
          }

          fallbackFbCopy();
        });

        function fallbackFbCopy() {
          copyQrImage();
          showToast('🖼️ 已複製 QR Code 圖片！可直接在 Facebook 貼文或 Messenger 訊息中「貼上 (Ctrl+V)」圖片發布！', 4000);
          setTimeout(function () {
            window.open('https://www.facebook.com/', '_blank', 'noopener,noreferrer');
          }, 400);
        }
      };

      // ── 社群傳送：手機系統原生分享 (傳送 QR Code 圖片) ──
      window.shareViaNative = function () {
        getQrCodeImageFile(function (file, blob, filename) {
          if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
            navigator.share({
              files: [file],
              title: (currentQrTargetName || '影音 QR Code')
            }).catch(function (err) {
              if (err && err.name !== 'AbortError') {
                copyQrImage();
              }
            });
            return;
          }

          copyQrImage();
        });
      };

      // 監聽 Esc 鍵關閉 QR Modal
      window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
          var modal = document.getElementById('qr-modal');
          if (modal && modal.classList.contains('active')) {
            closeQrModal();
          }
        }
      });

      // ── 複製影片直接播放網址至剪貼簿並彈出 QR Code ──
      window.copyVideoShareLink = function () {
        if (!currentVideo || !currentVideo.rel_path) {
          showToast('⚠️ 目前尚未播放任何影片，請先由清單點選影片');
          return;
        }

        var shareUrl = window.buildShareUrl(currentVideo.rel_path);
        var targetName = '🎬 ' + (currentVideo.title || currentVideo.filename || '影片');

        var btn = document.getElementById('btn-share-link');
        var iconSpan = document.getElementById('share-btn-icon');
        var textSpan = document.getElementById('share-btn-text');

        copyTextToClipboard(shareUrl, function () {
          if (btn) {
            btn.classList.add('copied');
            if (iconSpan) iconSpan.textContent = '✅';
            if (textSpan) textSpan.textContent = '已複製！';
            clearTimeout(btn._resetTimer);
            btn._resetTimer = setTimeout(function () {
              btn.classList.remove('copied');
              if (iconSpan) iconSpan.textContent = '🔗';
              if (textSpan) textSpan.textContent = '複製連結';
            }, 2500);
          }
          showToast('📋 已複製影片播放網址，並已產製 QR Code 方便掃描與傳送！');
        }, '請複製以下影片播放連結：');

        window.showQrModal(shareUrl, targetName);
      };

      // ── 產生資料夾目錄分享網址（完整絕對路徑，方便複製分享給家人）──
      window.buildFolderShareUrl = function (folderRelPath) {
        var u = new URL('index.php', window.location.href);
        var cleanPath = (folderRelPath || '').replace(/\\/g, '/').replace(/^\/+/, '').replace(/\/+$/, '');
        if (cleanPath) {
          u.searchParams.set('folder', cleanPath);
        } else {
          u.searchParams.set('folder', '');
        }
        var curParams = new URLSearchParams(window.location.search);
        if (curParams.has('dir')) {
          u.searchParams.set('dir', curParams.get('dir'));
        }
        // 注意：直連分享網址不附加個人 auth_token，確保新裝置依據安全規範提示輸入 PIN 碼
        u.searchParams.delete('auth_token');
        u.searchParams.delete('play');
        u.searchParams.delete('v');
        return u.href;
      };

      // ── 複製資料夾目錄直達網址至剪貼簿並彈出 QR Code ──
      window.copyFolderShareLink = function (event, folderRelPath, triggerEl) {
        if (event) {
          event.stopPropagation();
          event.preventDefault();
        }

        var shareUrl = window.buildFolderShareUrl(folderRelPath);
        var btn = triggerEl || (event ? event.currentTarget : null);
        var origHtml = btn ? btn.innerHTML : '';
        var folderName = folderRelPath ? ('📁 ' + folderRelPath) : '📁 根目錄';

        copyTextToClipboard(shareUrl, function () {
          if (btn) {
            btn.classList.add('copied');
            btn.innerHTML = '<span>✅</span> <span>已複製！</span>';
            clearTimeout(btn._resetTimer);
            btn._resetTimer = setTimeout(function () {
              btn.classList.remove('copied');
              btn.innerHTML = origHtml || '<span>🔗</span> <span>複製連結</span>';
            }, 2500);
          }
          showToast('📋 已複製資料夾直達連結，並已產製 QR Code 方便掃描與傳送！');
        }, '請複製以下資料夾目錄分享連結：');

        window.showQrModal(shareUrl, folderName);
      };

      // ── 複製當前影片所屬資料夾直達網址 ──
      window.copyCurrentFolderShareLink = function (triggerEl) {
        var targetFolder = '';
        if (currentVideo && currentVideo.rel_path) {
          var norm = currentVideo.rel_path.replace(/\\/g, '/');
          var slashIdx = norm.lastIndexOf('/');
          if (slashIdx !== -1) {
            targetFolder = norm.substring(0, slashIdx);
          }
        } else if (currentPath) {
          targetFolder = currentPath;
        }
        window.copyFolderShareLink(null, targetFolder, triggerEl);
      };

      // ── 深色科技風 Toast 輕量通知 ──
      function showToast(msg, duration) {
        duration = duration || 3200;
        var toast = document.getElementById('global-toast');
        if (!toast) {
          toast = document.createElement('div');
          toast.id = 'global-toast';
          toast.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(12px);background:rgba(15,23,42,0.92);color:#38bdf8;padding:10px 20px;border-radius:6px;font-size:0.84rem;font-family:var(--mono);border:1px solid rgba(56,189,248,0.35);box-shadow:0 10px 30px rgba(0,0,0,0.6);z-index:99999;pointer-events:none;transition:opacity 0.25s, transform 0.25s;opacity:0;';
          document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';
        clearTimeout(toast._timer);
        toast._timer = setTimeout(function () {
          toast.style.opacity = '0';
          toast.style.transform = 'translateX(-50%) translateY(12px)';
        }, duration);
      }

      // ════════════════════════════════════════════════════════
      //  📺 Google TV / 智慧電視 / 投射助手模組 (TV Cast Assistant)
      //  架構：
      //    • 軌道 1：W3C Remote Playback API + AirPlay 原生協定（免外部 CDN）
      //    • 軌道 2：電視端 APP 晶片硬解 (VLC / Nova Video Player / nPlayer)
      // ════════════════════════════════════════════════════════

      // ── 更新工具列投射按鈕狀態 ──
      window.updateCastButtonState = function (state) {
        var btn = document.getElementById('btn-cast');
        var icon = document.getElementById('btn-cast-icon');
        var text = document.getElementById('btn-cast-text');
        if (!btn) return;
        if (state === 'connected') {
          btn.classList.add('active', 'connected');
          if (icon) icon.textContent = '📶';
          if (text) text.textContent = '投放中';
          btn.title = '正在投放至 Google TV / 電視 (點擊以切換或關閉)';
        } else if (state === 'connecting') {
          btn.classList.add('active');
          btn.classList.remove('connected');
          if (icon) icon.textContent = '⏳';
          if (text) text.textContent = '連線中';
          btn.title = '正在連線至電視設備...';
        } else {
          btn.classList.remove('active', 'connected');
          if (icon) icon.textContent = '📺';
          if (text) text.textContent = '投放';
          btn.title = '投影至 Google TV / 電視 / AirPlay 或查看電視串流網址';
        }
      };

      // ── 觸發瀏覽器原生 Cast / AirPlay 投射選單 ──
      window.triggerNativeCastPrompt = function () {
        var player = document.getElementById('video-player');
        if (!player) return;
        if (!currentVideo || !currentVideo.rel_path) {
          showToast('⚠️ 請先選取影片播放');
          return;
        }

        if (player.remote && typeof player.remote.prompt === 'function') {
          player.remote.prompt().then(function () {
            // 成功叫出投影設備選單
          }).catch(function (err) {
            if (err && err.name === 'NotSupportedError') {
              showToast('⚠️ 目前瀏覽器環境不支援直接投影，請使用下方電視串流網址播放');
            } else if (err && err.name === 'NotFoundError') {
              showToast('🔍 未在區域網路發現可用的 Chromecast / Google TV 裝置');
            } else {
              console.log('Remote Playback Prompt info:', err);
            }
          });
        } else if (typeof player.webkitShowPlaybackTargetPicker === 'function') {
          try {
            player.webkitShowPlaybackTargetPicker();
          } catch (e) {
            showToast('⚠️ 啟動 AirPlay 失敗，請確認已啟用投影設定');
          }
        } else {
          showToast('💡 此瀏覽器不支援原生投射，請使用下方電視串流網址在電視 APP 開啟');
        }
      };

      // ── 播放工具列「📺 投放」按鈕點擊處理 ──
      window.handleCastButtonClick = function () {
        if (!currentVideo || !currentVideo.rel_path) {
          showToast('⚠️ 目前尚未播放任何影片，請先由清單點選影片');
          return;
        }
        var player = document.getElementById('video-player');
        // 若當前正處於連線投放狀態，點擊直接喚起控制選單（中斷或調整）
        if (player && player.remote && player.remote.state === 'connected' && typeof player.remote.prompt === 'function') {
          player.remote.prompt().catch(function () {});
          return;
        }
        // 否則開啟智慧電視播放助手，提供瀏覽器原生投放與客廳電視 APP 多重解決方案
        openTvCastModal();
      };

      // ── 開啟電視播放助手彈窗 ──
      window.openTvCastModal = function () {
        if (!currentVideo || !currentVideo.rel_path) {
          showToast('⚠️ 目前尚未播放任何影片，請先由清單點選影片');
          return;
        }
        var modal = document.getElementById('tv-cast-modal');
        var backdrop = document.getElementById('tv-cast-modal-backdrop');
        var nameEl = document.getElementById('tv-cast-video-name');
        var streamInput = document.getElementById('tv-stream-url-input');
        var webInput = document.getElementById('tv-web-url-input');
        var vlcLink = document.getElementById('cast-vlc-link');
        var wvcLink = document.getElementById('cast-wvc-link');
        var nplayerLink = document.getElementById('cast-nplayer-link');
        var warnHost = document.getElementById('tv-cast-localhost-warn');

        // 生成相容電視的串流網址 (帶 compat=1 及 auth_token)
        var streamUrl = window.buildStreamUrl(currentVideo.rel_path, true);
        var shareWebUrl = window.buildShareUrl(currentVideo.rel_path);

        if (nameEl) nameEl.textContent = currentVideo.title || currentVideo.rel_path;
        if (streamInput) streamInput.value = streamUrl;
        if (webInput) webInput.value = shareWebUrl;

        // 偵測是否使用 localhost 存取
        if (warnHost) {
          if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            warnHost.style.display = 'block';
          } else {
            warnHost.style.display = 'none';
          }
        }

        var isAndroid = /Android/i.test(navigator.userAgent);

        // 設定 VLC 協定連結（Android 環境優先採用 Intent 喚起）
        if (vlcLink) {
          if (isAndroid) {
            vlcLink.href = 'intent:' + streamUrl + '#Intent;package=org.videolan.vlc;type=video/*;end';
          } else {
            vlcLink.href = 'vlc://' + streamUrl;
          }
        }

        // 設定 Web Video Caster 協定連結
        if (wvcLink) {
          if (isAndroid) {
            wvcLink.href = 'intent:' + streamUrl + '#Intent;package=com.instantbits.cast.webvideo;type=video/*;end';
          } else {
            wvcLink.href = 'wvc-x-callback://open?url=' + encodeURIComponent(streamUrl);
          }
        }

        // 設定 nPlayer 協定連結
        if (nplayerLink) {
          nplayerLink.href = 'nplayer-' + streamUrl;
        }

        if (backdrop) backdrop.classList.add('active');
        if (modal) {
          modal.classList.add('active');
          modal.setAttribute('aria-hidden', 'false');
        }
      };

      // ── 關閉電視播放助手彈窗 ──
      window.closeTvCastModal = function () {
        var modal = document.getElementById('tv-cast-modal');
        var backdrop = document.getElementById('tv-cast-modal-backdrop');
        if (modal) {
          modal.classList.remove('active');
          modal.setAttribute('aria-hidden', 'true');
        }
        if (backdrop) backdrop.classList.remove('active');
      };

      // ── 複製電視串流網址 ──
      window.copyCastStreamUrl = function () {
        var input = document.getElementById('tv-stream-url-input');
        var btn = document.getElementById('btn-copy-tv-stream');
        if (!input || !input.value) return;
        var copyVal = input.value;

        function onSuccess() {
          if (btn) {
            btn.classList.add('copied');
            var txt = btn.querySelector('.copy-text');
            if (txt) txt.textContent = '已複製！';
            setTimeout(function () {
              btn.classList.remove('copied');
              if (txt) txt.textContent = '複製串流網址';
            }, 2500);
          }
          showToast('📋 已複製電視串流網址！可貼入 Google TV 的 Nova Video Player 或 VLC「網路串流」播放');
        }

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(copyVal).then(onSuccess).catch(function () {
            input.select();
            document.execCommand('copy');
            onSuccess();
          });
        } else {
          input.select();
          document.execCommand('copy');
          onSuccess();
        }
      };

      // ── 複製電視網頁直連網址 ──
      window.copyCastWebUrl = function () {
        var input = document.getElementById('tv-web-url-input');
        var btn = document.getElementById('btn-copy-tv-web');
        if (!input || !input.value) return;
        var copyVal = input.value;

        function onSuccess() {
          if (btn) {
            btn.classList.add('copied');
            var txt = btn.querySelector('.copy-text');
            if (txt) txt.textContent = '已複製！';
            setTimeout(function () {
              btn.classList.remove('copied');
              if (txt) txt.textContent = '複製網頁網址';
            }, 2500);
          }
          showToast('📋 已複製網頁網址！可在電視瀏覽器輸入直接觀看');
        }

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(copyVal).then(onSuccess).catch(function () {
            input.select();
            document.execCommand('copy');
            onSuccess();
          });
        } else {
          input.select();
          document.execCommand('copy');
          onSuccess();
        }
      };

      // ── 電視播放助手專用 QR Code 彈窗 ──
      window.openTvCastQr = function (type) {
        var inputId = type === 'stream' ? 'tv-stream-url-input' : 'tv-web-url-input';
        var input = document.getElementById(inputId);
        if (!input || !input.value) {
          showToast('⚠️ 尚未產生網址');
          return;
        }
        var targetName = (currentVideo ? (currentVideo.title || currentVideo.filename) : '電視播放') + (type === 'stream' ? ' (串流網址)' : ' (網頁網址)');
        showQrModal(input.value, targetName);
      };

      // 前端路徑快取：記錄各路徑已載入的目錄內容，避免重複請求
      const dirCache = {};
      if (initialData && initialData.success) {
        dirCache[''] = initialData;
      }
      window.getFolderCachedVideos = function (path) {
        if (dirCache && dirCache[path] && Array.isArray(dirCache[path].videos)) {
          return dirCache[path].videos;
        }
        return null;
      };

      let currentPath = '';
      let currentFolderData = initialData;
      let recentVideosData = (initialRecentData && initialRecentData.success) ? initialRecentData : null;
      let searchQuery = '';
      let currentVideos = (initialRecentData && initialRecentData.videos) ? [...initialRecentData.videos] : ((initialData && initialData.videos) ? [...initialData.videos] : []);
      let currentVideo = null;
      let currentIndex = -1;
      let resumeTargetTime = 0;

      // 最新影音模式與排序狀態（預設啟動全站最新模式）
      let isRecentMode = true;
      let sortOrder = 'time_desc'; // 支援: 'time_desc', 'time_asc', 'name_asc', 'name_desc'
      try {
        const savedSort = localStorage.getItem('vid_sort_order');
        if (['time_desc', 'time_asc', 'name_asc', 'name_desc'].indexOf(savedSort) !== -1) {
          sortOrder = savedSort;
        }
      } catch(e) {}

      function updateSortButtonUI() {
        const btn = document.getElementById('btn-sort-toggle');
        const dirBtn = document.getElementById('btn-sort-dir');
        const isTime = sortOrder.indexOf('time') === 0;
        const isDesc = sortOrder.indexOf('desc') !== -1;
        if (btn) {
          btn.textContent = isTime ? '⏱ 時間' : '🔤 名稱';
          btn.title = isTime ? '切換排序項目（目前：修改時間，點擊切換為名稱）' : '切換排序項目（目前：檔案名稱，點擊切換為時間）';
        }
        if (dirBtn) {
          dirBtn.textContent = isDesc ? '▼ 遞減' : '▲ 遞增';
          dirBtn.title = isDesc
            ? (isTime ? '切換排序順序（目前：最新在前，點擊切換為最舊在前）' : '切換排序順序（目前：Z 到 A 倒序，點擊切換為 A 到 Z 順序）')
            : (isTime ? '切換排序順序（目前：最舊在前，點擊切換為最新在前）' : '切換排序順序（目前：A 到 Z 順序，點擊切換為 Z 到 A 倒序）');
        }
      }
      updateSortButtonUI();

      // ── 字幕狀態管理 ────────────────────────────────────────────────────
      let currentSubtitles = [];      // 當前影片可播放的字幕清單 (包含外掛與內嵌純文字字幕)
      let rawEmbeddedList = [];       // 後端傳回的原始內嵌字幕清單
      let activeSubtitleIndex = -1;   // -1 = 關閉字幕
      let subtitleFontScale = 1.0;    // 字幕字級縮放比例
      try {
        var savedInitialScale = parseFloat(localStorage.getItem('vid_subtitle_font_scale') || '1.0');
        if (!isNaN(savedInitialScale) && savedInitialScale >= 0.5 && savedInitialScale <= 2.5) {
          subtitleFontScale = savedInitialScale;
        }
      } catch(e) {}

      const videoPlayer = document.getElementById('video-player');
      const resumeBanner = document.getElementById('resume-banner');
      const resumeTimeStr = document.getElementById('resume-time-str');
      const subtitleBar = document.getElementById('subtitle-bar');
      const subtitleTrackList = document.getElementById('subtitle-track-list');
      const subtitleCountBadge = document.getElementById('subtitle-count-badge');
      const subtitleSizeGroup = document.getElementById('subtitle-size-group');
      const subtitleStyleControls = document.getElementById('subtitle-style-controls');

      // ── 監聽 W3C Remote Playback API 事件 (Google TV / Chromecast / AirPlay) ──
      if (videoPlayer && videoPlayer.remote) {
        try {
          videoPlayer.remote.addEventListener('connecting', function () {
            if (typeof window.updateCastButtonState === 'function') {
              window.updateCastButtonState('connecting');
            }
          });
          videoPlayer.remote.addEventListener('connect', function () {
            if (typeof window.updateCastButtonState === 'function') {
              window.updateCastButtonState('connected');
            }
            if (typeof showToast === 'function') {
              showToast('📺 已成功投影至電視螢幕');
            }
          });
          videoPlayer.remote.addEventListener('disconnect', function () {
            if (typeof window.updateCastButtonState === 'function') {
              window.updateCastButtonState('disconnected');
            }
            if (typeof showToast === 'function') {
              showToast('📺 已結束電視投影');
            }
          });
        } catch (e) {
          console.log('Remote Playback listeners setup error:', e);
        }
      }

      // ── 循環播放模式 (list: 目錄循環 | single: 單片循環 | random: 隨機循環 | off: 播畢即停，預設為目錄循環) ──
      let loopMode = 'list';
      try {
        var savedLoopMode = localStorage.getItem('vid_loop_mode');
        if (savedLoopMode && ['list', 'single', 'random', 'off'].includes(savedLoopMode)) {
          loopMode = savedLoopMode;
        } else {
          loopMode = 'list';
        }
      } catch(e) {
        loopMode = 'list';
      }
      var initialLoopSelect = document.getElementById('loop-mode-select');
      if (initialLoopSelect) {
        initialLoopSelect.value = loopMode;
        initialLoopSelect.className = 'loop-select mode-' + loopMode;
      }

      // ════════════════════════════════════════════════════════
      //  🎨 原生 DVD 圖形字幕引擎 (Frontend SPU Canvas Renderer)
      //  架構：後端無損提取 SPU 封包 -> 前端 2-bit RLE 解碼 -> Canvas 即時懸浮渲染
      //  相容性：100% 脫機自給自足、零外部依賴、硬體加速、自適應黑邊與字級縮放、色彩與陰影自訂
      // ════════════════════════════════════════════════════════
      var GraphicSubtitleManager = (function () {
        var canvas = null;
        var ctx = null;
        var cues = [];
        var palette = null;
        var isVisible = false;
        var currentUrl = '';
        var lastRenderedIndex = -1;
        var subtitleColorMode = localStorage.getItem('vid_subtitle_color_mode') || 'original';
        var subtitleShadowEnabled = true; // 字幕陰影預設開啟
        var defaultPalette = [
          '#000000', '#ffffff', '#000000', '#606060',
          '#ff0000', '#00ff00', '#0000ff', '#ffff00',
          '#ff00ff', '#00ffff', '#808080', '#c0c0c0',
          '#404040', '#e0e0e0', '#202020', '#101010'
        ];

        function getCanvas() {
          if (!canvas) {
            canvas = document.getElementById('graphic-subtitle-canvas');
            if (canvas) ctx = canvas.getContext('2d');
          }
          return canvas;
        }

        function hexToRgb(hex) {
          if (!hex || hex.length < 6) return [255, 255, 255];
          if (hex.charAt(0) === '#') hex = hex.substring(1);
          var num = parseInt(hex, 16);
          return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
        }

        function decodeBase64ToBytes(b64) {
          var bin = atob(b64);
          var len = bin.length;
          var bytes = new Uint8Array(len);
          for (var i = 0; i < len; i++) {
            bytes[i] = bin.charCodeAt(i);
          }
          return bytes;
        }

        function decodeCue(cue) {
          if (cue._canvas && cue._canvasColorMode === subtitleColorMode) return cue._canvas;

          var pal = (palette && palette.length >= 4) ? palette : defaultPalette;
          var colors32 = new Uint32Array(4);

          // 尋找主字體筆畫色彩 (亮度最高且非完全透明之像素索引)
          var bodyPixelIdx = -1;
          var maxScore = -1;
          for (var p = 0; p < 4; p++) {
            var alpVal = (cue.alp && cue.alp[p] !== undefined) ? cue.alp[p] : (p === 0 ? 0 : 15);
            if (alpVal > 0) {
              var colIdx = (cue.col && cue.col[p] !== undefined) ? cue.col[p] : p;
              var rgbOrig = hexToRgb(pal[colIdx] || defaultPalette[p]);
              var lum = 0.299 * rgbOrig[0] + 0.587 * rgbOrig[1] + 0.114 * rgbOrig[2];
              var score = alpVal * 1000 + lum;
              if (score > maxScore) {
                maxScore = score;
                bodyPixelIdx = p;
              }
            }
          }

          for (var p = 0; p < 4; p++) {
            var alpVal = (cue.alp && cue.alp[p] !== undefined) ? cue.alp[p] : (p === 0 ? 0 : 15);
            if (alpVal === 0) {
              colors32[p] = 0;
              continue;
            }

            var colIdx = (cue.col && cue.col[p] !== undefined) ? cue.col[p] : p;
            var rgb = hexToRgb(pal[colIdx] || defaultPalette[p]);
            var alpha = Math.round((alpVal / 15) * 255);

            if (subtitleColorMode === 'original') {
              // 保持 DVD 原始母帶色彩
              colors32[p] = (alpha << 24) | (rgb[2] << 16) | (rgb[1] << 8) | rgb[0];
            } else {
              if (p === bodyPixelIdx) {
                // 字體主體色彩替換（支援亮黃、純白、青綠）
                if (subtitleColorMode === 'yellow') {
                  rgb = [255, 230, 40]; // 高對比經典亮黃
                } else if (subtitleColorMode === 'cyan') {
                  rgb = [0, 240, 255]; // 科技青綠
                } else {
                  rgb = [255, 255, 255]; // 清晰純白
                }
                alpha = 255;
                colors32[p] = (alpha << 24) | (rgb[2] << 16) | (rgb[1] << 8) | rgb[0];
              } else {
                // 外框 / 描邊 / 抗鋸齒邊緣：強制實心黑邊加深，強化對比度防止被背景吃色
                var outlineAlpha = Math.min(255, Math.round((alpVal / 15) * 255 * 1.5));
                if (outlineAlpha < 140) outlineAlpha = 140;
                colors32[p] = (outlineAlpha << 24);
              }
            }
          }

          var bytes = decodeBase64ToBytes(cue.rle);
          var w = cue.w;
          var h = cue.h;
          var imgData = new ImageData(w, h);
          var data32 = new Uint32Array(imgData.data.buffer);

          var fields = [
            { offset: cue.top, startY: 0 },
            { offset: cue.bot, startY: 1 }
          ];

          for (var f = 0; f < 2; f++) {
            var bitPos = fields[f].offset * 8;
            var bitLen = bytes.length * 8;

            function getBits(n) {
              var res = 0;
              for (var b = 0; b < n; b++) {
                if (bitPos >= bitLen) return 0;
                var byteIdx = bitPos >> 3;
                var bitOffset = 7 - (bitPos & 7);
                res = (res << 1) | ((bytes[byteIdx] >> bitOffset) & 1);
                bitPos++;
              }
              return res;
            }

            for (var y = fields[f].startY; y < h; y += 2) {
              var x = 0;
              while (x < w && bitPos < bitLen) {
                var code = getBits(4);
                var count = 0;
                var color = 0;

                if (code >= 0x4) {
                  count = code >> 2;
                  color = code & 3;
                } else {
                  code = (code << 4) | getBits(4);
                  if (code >= 0x10) {
                    count = (code >> 2) & 0x0F;
                    color = code & 3;
                  } else {
                    code = (code << 4) | getBits(4);
                    if (code >= 0x40) {
                      count = (code >> 2) & 0x3F;
                      color = code & 3;
                    } else {
                      code = (code << 4) | getBits(4);
                      count = (code >> 2) & 0xFF;
                      color = code & 3;
                      if (count === 0) {
                        count = w - x;
                      }
                    }
                  }
                }

                var fillLen = Math.min(count, w - x);
                var pixelVal = colors32[color];
                var lineStart = y * w + x;
                for (var i = 0; i < fillLen; i++) {
                  data32[lineStart + i] = pixelVal;
                }
                x += count;
              }
              if ((bitPos & 7) !== 0) {
                bitPos = (bitPos + 7) & ~7;
              }
            }
          }

          var offCanvas = document.createElement('canvas');
          offCanvas.width = w;
          offCanvas.height = h;
          var offCtx = offCanvas.getContext('2d');
          offCtx.putImageData(imgData, 0, 0);
          cue._canvas = offCanvas;
          cue._canvasColorMode = subtitleColorMode;
          return offCanvas;
        }

        function updateCanvasSize() {
          var c = getCanvas();
          if (!c || !videoPlayer) return;
          var rect = videoPlayer.getBoundingClientRect();
          var w = rect.width;
          var h = rect.height;
          var dpr = window.devicePixelRatio || 1;
          if (w <= 0 || h <= 0) return;

          var targetW = Math.round(w * dpr);
          var targetH = Math.round(h * dpr);
          if (c.width !== targetW || c.height !== targetH) {
            c.width = targetW;
            c.height = targetH;
            c.style.width = w + 'px';
            c.style.height = h + 'px';
            lastRenderedIndex = -1;
          }
        }

        function render(timeSec, forceRedraw) {
          if (!isVisible) return;
          var c = getCanvas();
          if (!c || !ctx || !videoPlayer) return;

          updateCanvasSize();
          var curMs = (timeSec !== undefined ? timeSec : videoPlayer.currentTime) * 1000;

          var matchIdx = -1;
          for (var i = 0; i < cues.length; i++) {
            if (curMs >= cues[i].s && curMs <= cues[i].e) {
              matchIdx = i;
              break;
            }
          }

          if (matchIdx === -1) {
            if (lastRenderedIndex !== -1) {
              ctx.clearRect(0, 0, c.width, c.height);
              lastRenderedIndex = -1;
            }
            return;
          }

          if (matchIdx === lastRenderedIndex && !forceRedraw) {
            return;
          }

          var cue = cues[matchIdx];
          var cueCanvas = decodeCue(cue);
          if (!cueCanvas) return;

          lastRenderedIndex = matchIdx;

          // 計算黑邊與長寬比對齊 (object-fit: contain / cover)
          var dpr = window.devicePixelRatio || 1;
          var cw = c.width / dpr;
          var ch = c.height / dpr;
          var vw = videoPlayer.videoWidth || 720;
          var vh = videoPlayer.videoHeight || 480;

          var containerRatio = cw / ch;
          var videoRatio = vw / vh;
          var fit = videoPlayer.style.objectFit || 'contain';

          var drawW = cw, drawH = ch, drawX = 0, drawY = 0;
          if (fit === 'cover') {
            if (containerRatio > videoRatio) {
              drawW = cw;
              drawH = cw / videoRatio;
              drawX = 0;
              drawY = (ch - drawH) / 2;
            } else {
              drawH = ch;
              drawW = ch * videoRatio;
              drawX = (cw - drawW) / 2;
              drawY = 0;
            }
          } else {
            // contain
            if (containerRatio > videoRatio) {
              drawH = ch;
              drawW = ch * videoRatio;
              drawX = (cw - drawW) / 2;
              drawY = 0;
            } else {
              drawW = cw;
              drawH = cw / videoRatio;
              drawX = 0;
              drawY = (ch - drawH) / 2;
            }
          }

          // 判斷原製作母帶解析度 (標準 DVD 720x480 或 720x576，或 1080p 等高畫質母帶)
          var authorW = 720;
          var authorH = 480;
          if (vw > 720 || vh > 576) {
            authorW = vw;
            authorH = vh;
          } else if (cue.x + cue.w > 720 || cue.y + cue.h > 576) {
            authorW = 1920;
            authorH = 1080;
          } else if (cue.y + cue.h > 480) {
            authorH = 576;
          }

          var scaleX = drawW / authorW;
          var scaleY = drawH / authorH;

          var destX = drawX + cue.x * scaleX;
          var destY = drawY + cue.y * scaleY;
          var destW = cue.w * scaleX;
          var destH = cue.h * scaleY;

          // 支援字級縮放 (A+ / A-)
          if (subtitleFontScale && subtitleFontScale !== 1.0) {
            var origW = destW;
            var origH = destH;
            destW = destW * subtitleFontScale;
            destH = destH * subtitleFontScale;
            destX = destX - (destW - origW) / 2;
            destY = destY - (destH - origH);
          }

          ctx.clearRect(0, 0, c.width, c.height);
          if (subtitleShadowEnabled) {
            ctx.save();
            ctx.shadowColor = 'rgba(0, 0, 0, 0.95)';
            ctx.shadowBlur = Math.round(5 * dpr);
            ctx.shadowOffsetX = Math.round(2 * dpr);
            ctx.shadowOffsetY = Math.round(2 * dpr);
            ctx.drawImage(cueCanvas, destX * dpr, destY * dpr, destW * dpr, destH * dpr);
            ctx.restore();
          } else {
            ctx.drawImage(cueCanvas, destX * dpr, destY * dpr, destW * dpr, destH * dpr);
          }
        }

        // 60fps 硬體加速同步迴圈
        var animRafId = null;
        function animTick() {
          if (isVisible && videoPlayer && !videoPlayer.paused) {
            render();
            animRafId = requestAnimationFrame(animTick);
          } else {
            animRafId = null;
          }
        }
        function startAnimLoop() {
          if (!animRafId && isVisible && videoPlayer && !videoPlayer.paused) {
            animRafId = requestAnimationFrame(animTick);
          }
        }
        function stopAnimLoop() {
          if (animRafId) {
            cancelAnimationFrame(animRafId);
            animRafId = null;
          }
        }

        function show() {
          var c = getCanvas();
          if (c) {
            c.style.display = 'block';
            updateCanvasSize();
          }
          isVisible = true;
          startAnimLoop();
        }

        function hide() {
          stopAnimLoop();
          var c = getCanvas();
          if (c) {
            c.style.display = 'none';
          }
          clear();
          isVisible = false;
          lastRenderedIndex = -1;
        }

        function clear() {
          var c = getCanvas();
          if (c && ctx) {
            ctx.clearRect(0, 0, c.width, c.height);
          }
        }

        // 監聽播放器各類更新事件以確保同步
        if (videoPlayer) {
          videoPlayer.addEventListener('timeupdate', function () {
            if (isVisible) render();
          });
          videoPlayer.addEventListener('seeking', function () {
            if (isVisible) render(undefined, true);
          });
          videoPlayer.addEventListener('seeked', function () {
            if (isVisible) render(undefined, true);
          });
          videoPlayer.addEventListener('play', function () {
            if (isVisible) {
              updateCanvasSize();
              render();
              startAnimLoop();
            }
          });
          videoPlayer.addEventListener('pause', function () {
            stopAnimLoop();
            if (isVisible) render();
          });
          videoPlayer.addEventListener('ended', function () {
            stopAnimLoop();
            clear();
          });
          videoPlayer.addEventListener('loadedmetadata', function () {
            if (isVisible) {
              updateCanvasSize();
              render();
            }
          });
        }

        function scheduleCanvasRefresh() {
          if (!isVisible) return;
          updateCanvasSize();
          render(undefined, true);
          var delays = [80, 250, 500];
          for (var di = 0; di < delays.length; di++) {
            setTimeout(function () {
              if (isVisible) {
                updateCanvasSize();
                render(undefined, true);
              }
            }, delays[di]);
          }
        }

        window.addEventListener('resize', function () {
          if (isVisible) {
            updateCanvasSize();
            render(undefined, true);
          }
        });

        document.addEventListener('fullscreenchange', scheduleCanvasRefresh);
        document.addEventListener('webkitfullscreenchange', scheduleCanvasRefresh);
        document.addEventListener('mozfullscreenchange', scheduleCanvasRefresh);
        document.addEventListener('MSFullscreenChange', scheduleCanvasRefresh);

        var graphicLoadSeq = 0;

        return {
          load: async function (url) {
            var thisSeq = ++graphicLoadSeq;
            if (currentUrl === url && cues.length > 0) {
              show();
              render();
              return;
            }
            currentUrl = url;
            cues = [];
            lastRenderedIndex = -1;
            clear();
            show();
            showToast('⏳ 正在解析 DVD 圖形字幕封包...');
            try {
              var res = await authFetch(url);
              var data = await res.json();
              if (thisSeq !== graphicLoadSeq) return; // 捨棄過期回應
              if (data && data.success && Array.isArray(data.cues)) {
                cues = data.cues;
                palette = data.palette || null;
                showToast('🎨 已啟用 DVD 圖形字幕 (共 ' + cues.length + ' 句)');
                updateCanvasSize();
                render();
              } else {
                showToast('⚠️ 圖形字幕封包解析失敗');
                hide();
              }
            } catch (err) {
              if (thisSeq !== graphicLoadSeq) return;
              console.error('載入圖形字幕失敗:', err);
              showToast('⚠️ 圖形字幕載入失敗');
              hide();
            }
          },
          show: show,
          hide: hide,
          clear: clear,
          reset: function () {
            graphicLoadSeq++;
            cues = [];
            currentUrl = '';
            lastRenderedIndex = -1;
            palette = null;
            hide();
          },
          rerender: function () {
            if (isVisible) {
              updateCanvasSize();
              render(undefined, true);
            }
          },
          setColorMode: function (mode) {
            if (subtitleColorMode === mode) return;
            subtitleColorMode = mode;
            try { localStorage.setItem('vid_subtitle_color_mode', mode); } catch(e) {}
            for (var i = 0; i < cues.length; i++) {
              cues[i]._canvas = null;
            }
            lastRenderedIndex = -1;
            if (isVisible) {
              render(undefined, true);
            }
          },
          setShadow: function (enabled) {
            subtitleShadowEnabled = !!enabled;
            try { localStorage.setItem('vid_subtitle_shadow', subtitleShadowEnabled ? 'true' : 'false'); } catch(e) {}
            if (isVisible) {
              render(undefined, true);
            }
          },
          getColorMode: function () {
            return subtitleColorMode;
          },
          getShadow: function () {
            return subtitleShadowEnabled;
          },
          isShowing: function () {
            return isVisible;
          }
        };
      })();

      // ════ 全螢幕相容整合引擎 (Container Fullscreen & Subtitle Sync) ════
      window.isFullscreenActive = function () {
        var box = document.getElementById('video-box');
        var isWebFs = box && box.classList.contains('mobile-web-fullscreen');
        return !!(
          document.fullscreenElement ||
          document.webkitFullscreenElement ||
          document.mozFullScreenElement ||
          document.msFullscreenElement ||
          (videoPlayer && videoPlayer.webkitDisplayingFullscreen) ||
          isWebFs
        );
      };

      window.enterFullscreen = function () {
        if (window.isFullscreenActive()) return;
        var box = document.getElementById('video-box');
        if (!box) return;

        function applyWebFullscreenFallback() {
          if (!window.isFullscreenActive()) {
            box.classList.add('mobile-web-fullscreen');
            document.body.classList.add('has-mobile-web-fs');
            if (typeof handleFullscreenChange === 'function') {
              handleFullscreenChange();
            }
          }
        }

        try {
          if (box.requestFullscreen) {
            var p = box.requestFullscreen();
            if (p && typeof p.then === 'function') {
              p.then(function () {
                box.classList.remove('mobile-web-fullscreen');
                document.body.classList.remove('has-mobile-web-fs');
              }).catch(function () {
                if (videoPlayer && videoPlayer.requestFullscreen) {
                  var p2 = videoPlayer.requestFullscreen();
                  if (p2 && typeof p2.then === 'function') {
                    p2.then(function () {
                      box.classList.remove('mobile-web-fullscreen');
                      document.body.classList.remove('has-mobile-web-fs');
                    }).catch(function () {
                      if (videoPlayer && videoPlayer.webkitEnterFullscreen) {
                        try { videoPlayer.webkitEnterFullscreen(); } catch (e3) { applyWebFullscreenFallback(); }
                      } else {
                        applyWebFullscreenFallback();
                      }
                    });
                  } else {
                    applyWebFullscreenFallback();
                  }
                } else if (videoPlayer && videoPlayer.webkitEnterFullscreen) {
                  try { videoPlayer.webkitEnterFullscreen(); } catch (e2) { applyWebFullscreenFallback(); }
                } else {
                  applyWebFullscreenFallback();
                }
              });
            }
          } else if (box.webkitRequestFullscreen) {
            box.webkitRequestFullscreen();
          } else if (box.mozRequestFullScreen) {
            box.mozRequestFullScreen();
          } else if (box.msRequestFullscreen) {
            box.msRequestFullscreen();
          } else if (videoPlayer && videoPlayer.requestFullscreen) {
            videoPlayer.requestFullscreen().catch(function () {
              applyWebFullscreenFallback();
            });
          } else if (videoPlayer && videoPlayer.webkitEnterFullscreen) {
            try { videoPlayer.webkitEnterFullscreen(); } catch (e) { applyWebFullscreenFallback(); }
          } else {
            applyWebFullscreenFallback();
          }
        } catch (e) {
          applyWebFullscreenFallback();
        }
      };

      window.exitFullscreen = function () {
        var box = document.getElementById('video-box');
        if (box && box.classList.contains('mobile-web-fullscreen')) {
          box.classList.remove('mobile-web-fullscreen');
          document.body.classList.remove('has-mobile-web-fs');
          if (typeof handleFullscreenChange === 'function') {
            handleFullscreenChange();
          }
        }
        if (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement || (videoPlayer && videoPlayer.webkitDisplayingFullscreen)) {
          if (document.exitFullscreen) {
            document.exitFullscreen().catch(function () {});
          } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
          } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
          } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
          } else if (videoPlayer && videoPlayer.webkitExitFullscreen) {
            videoPlayer.webkitExitFullscreen();
          }
        }
      };

      window.toggleFullscreen = function () {
        if (window.isFullscreenActive()) {
          window.exitFullscreen();
        } else {
          window.enterFullscreen();
        }
      };

      // ── 影片點擊與雙擊互動：單擊切換暫停/播放，雙擊切換全螢幕 ──
      var videoClickTimer = null;

      function showPlayPauseIndicator(isPaused) {
        var indicator = document.getElementById('video-play-indicator');
        if (!indicator) return;
        indicator.textContent = isPaused ? '⏸' : '▶';
        indicator.classList.remove('show');
        void indicator.offsetWidth;
        indicator.classList.add('show');
        clearTimeout(indicator._timer);
        indicator._timer = setTimeout(function () {
          indicator.classList.remove('show');
        }, 350);
      }

      window.togglePlayPause = function () {
        if (!videoPlayer || !videoPlayer.src || videoPlayer.src === window.location.href) {
          return;
        }
        if (videoPlayer.paused) {
          var p = videoPlayer.play();
          if (p && typeof p.catch === 'function') {
            p.catch(function () {});
          }
          showPlayPauseIndicator(false);
        } else {
          videoPlayer.pause();
          showPlayPauseIndicator(true);
        }
      };

      function handleVideoClick(e) {
        // 僅響應滑鼠主要按鍵 (左鍵)
        if (e.button !== 0) return;

        // 若為觸控手勢（手機/平板），交由行動瀏覽器原生手勢與控制項處理
        if (e.pointerType === 'touch') return;

        // 若尚未載入有效影片來源，忽略
        if (!videoPlayer || !videoPlayer.src || videoPlayer.src === window.location.href) {
          return;
        }

        // 排除懸浮全螢幕按鈕或接續播放橫幅內部點擊
        if (e.target && e.target.closest && e.target.closest('#video-overlay-fs-btn, #resume-banner, .resume-banner')) {
          return;
        }

        // 偵測點擊位置是否在底部原生控制列範圍內 (約 52px，避免干擾原生進度條拖曳或原生音量調整)
        if (videoPlayer && videoPlayer.controls) {
          var rect = videoPlayer.getBoundingClientRect();
          var clickY = e.clientY - rect.top;
          var fromBottom = rect.height - clickY;
          if (fromBottom <= 52 && fromBottom >= 0) {
            return;
          }
        }

        // 點擊後立即釋放焦點，防止 <video> 元素奪取鍵盤焦點導致後續空白鍵被瀏覽器原生重複處理
        if (videoPlayer) {
          try { videoPlayer.blur(); } catch (err) {}
        }
        if (document.activeElement && document.activeElement !== document.body) {
          try { document.activeElement.blur(); } catch (err) {}
        }

        // 協調單擊 (暫停/播放) 與雙擊 (全螢幕)：延遲 220ms 等待確認非雙擊
        if (videoClickTimer) {
          clearTimeout(videoClickTimer);
          videoClickTimer = null;
          return;
        }

        videoClickTimer = setTimeout(function () {
          videoClickTimer = null;
          window.togglePlayPause();
        }, 220);
      }

      function handleVideoDblClick(e) {
        // 若偵測到雙擊，立即取消單擊計時器，避免播放狀態反覆跳動
        if (videoClickTimer) {
          clearTimeout(videoClickTimer);
          videoClickTimer = null;
        }
        e.preventDefault();
        toggleFullscreen();
      }

      if (videoPlayer) {
        videoPlayer.addEventListener('click', handleVideoClick);
        videoPlayer.addEventListener('dblclick', handleVideoDblClick);
        // 在滑鼠按下非控制列區域時主動釋放焦點，杜絕原生控制項奪焦
        videoPlayer.addEventListener('pointerdown', function (e) {
          if (videoPlayer && videoPlayer.controls) {
            var rect = videoPlayer.getBoundingClientRect();
            var fromBottom = rect.height - (e.clientY - rect.top);
            if (fromBottom > 52) {
              if (document.activeElement && document.activeElement !== document.body) {
                try { document.activeElement.blur(); } catch (err) {}
              }
            }
          }
        });
      }
      var gCanvas = document.getElementById('graphic-subtitle-canvas');
      if (gCanvas) {
        gCanvas.addEventListener('click', handleVideoClick);
        gCanvas.addEventListener('dblclick', handleVideoDblClick);
      }
      var vBox = document.getElementById('video-box');
      if (vBox) {
        vBox.addEventListener('click', function (e) {
          if (e.target === vBox) {
            handleVideoClick(e);
          }
        });
        vBox.addEventListener('dblclick', function (e) {
          if (e.target === vBox) {
            handleVideoDblClick(e);
          }
        });
        // 若處於滿版網頁全螢幕，當使用者觸控畫面（具備真實 User Gesture）時嘗試升級為原生全螢幕
        vBox.addEventListener('touchend', function () {
          if (vBox.classList.contains('mobile-web-fullscreen')) {
            if (vBox.requestFullscreen) {
              vBox.requestFullscreen().then(function () {
                vBox.classList.remove('mobile-web-fullscreen');
                document.body.classList.remove('has-mobile-web-fs');
              }).catch(function () {});
            }
          }
        }, { passive: true });
      }

      // 攔截 <video> 原生全螢幕呼叫並導向 #video-box（涵蓋實例與原型鏈）
      var origReqFull = videoPlayer ? (videoPlayer.requestFullscreen || videoPlayer.webkitRequestFullscreen) : null;
      if (origReqFull && videoPlayer) {
        var box = document.getElementById('video-box');
        videoPlayer.requestFullscreen = function () {
          if (box && box.requestFullscreen) return box.requestFullscreen();
          if (box && box.webkitRequestFullscreen) return box.webkitRequestFullscreen();
          return origReqFull.call(videoPlayer);
        };
      }
      if (typeof HTMLVideoElement !== 'undefined' && HTMLVideoElement.prototype) {
        var origProtoReq = HTMLVideoElement.prototype.requestFullscreen;
        if (origProtoReq) {
          HTMLVideoElement.prototype.requestFullscreen = function () {
            if (this.id === 'video-player') {
              var b = document.getElementById('video-box');
              if (b && b.requestFullscreen) return b.requestFullscreen();
              if (b && b.webkitRequestFullscreen) return b.webkitRequestFullscreen();
            }
            return origProtoReq.apply(this, arguments);
          };
        }
        var origProtoWebkit = HTMLVideoElement.prototype.webkitRequestFullscreen;
        if (origProtoWebkit) {
          HTMLVideoElement.prototype.webkitRequestFullscreen = function () {
            if (this.id === 'video-player') {
              var b = document.getElementById('video-box');
              if (b && b.webkitRequestFullscreen) return b.webkitRequestFullscreen();
              if (b && b.requestFullscreen) return b.requestFullscreen();
            }
            return origProtoWebkit.apply(this, arguments);
          };
        }
      }

      // 全螢幕切換監聽器：維持全螢幕狀態下字幕軌道與樣式
      function handleFullscreenChange() {
        var box = document.getElementById('video-box');
        var isWebFs = box && box.classList.contains('mobile-web-fullscreen');
        var fsEl = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement || (videoPlayer && videoPlayer.webkitDisplayingFullscreen ? videoPlayer : null) || (isWebFs ? box : null);
        var isFs = !!fsEl;

        // 1. 同步更新外部工具列全螢幕按鈕之圖示與狀態文字
        var fsBtn = document.getElementById('btn-fullscreen');
        var fsIcon = document.getElementById('btn-fs-icon');
        var fsText = document.getElementById('btn-fs-text');
        if (fsBtn) {
          if (isFs) {
            fsBtn.classList.add('active');
            if (fsIcon) fsIcon.textContent = '🗗';
            if (fsText) fsText.textContent = '視窗';
            fsBtn.title = '離開全螢幕 (ESC / F / 雙擊畫面)';
          } else {
            fsBtn.classList.remove('active');
            if (fsIcon) fsIcon.textContent = '⛶';
            if (fsText) fsText.textContent = '全螢幕';
            fsBtn.title = '切換全螢幕 (快速鍵 F 或 雙擊畫面)';
          }
        }

        // 2. 同步更新影片內懸浮全螢幕按鈕之圖示與提示 (保證內外 100% 同步)
        var overlayFsBtn = document.getElementById('video-overlay-fs-btn');
        var overlayFsIcon = document.getElementById('video-overlay-fs-icon');
        if (overlayFsBtn) {
          if (isFs) {
            overlayFsBtn.classList.add('active');
            if (overlayFsIcon) overlayFsIcon.textContent = '🗗';
            overlayFsBtn.title = '離開全螢幕 (ESC / F / 雙擊畫面)';
          } else {
            overlayFsBtn.classList.remove('active');
            if (overlayFsIcon) overlayFsIcon.textContent = '⛶';
            overlayFsBtn.title = '切換全螢幕 (快速鍵 F 或 雙擊畫面)';
          }
        }

        // 3. 字幕狀態校驗與重新維持 (同步純文字軌道與圖形字幕)
        if (activeSubtitleIndex >= 0 && currentSubtitles[activeSubtitleIndex]) {
          var activeSub = currentSubtitles[activeSubtitleIndex];
          if (activeSub.type === 'graphic') {
            requestAnimationFrame(function () {
              GraphicSubtitleManager.rerender();
            });
            var delays = [150, 450];
            for (var k = 0; k < delays.length; k++) {
              setTimeout(function () {
                requestAnimationFrame(function () {
                  GraphicSubtitleManager.rerender();
                });
              }, delays[k]);
            }
          } else {
            // 純文字 WebVTT 軌道：防止瀏覽器在變更渲染層級時重設 mode
            if (videoPlayer && videoPlayer.textTracks) {
              for (var ti = 0; ti < videoPlayer.textTracks.length; ti++) {
                var tt = videoPlayer.textTracks[ti];
                if (tt.label === activeSub.label) {
                  try { tt.mode = 'showing'; } catch (eT) {}
                }
              }
            }
            var tEls = videoPlayer ? videoPlayer.querySelectorAll('track') : [];
            for (var tk = 0; tk < tEls.length; tk++) {
              if (tEls[tk].track && tEls[tk].label === activeSub.label) {
                try { tEls[tk].track.mode = 'showing'; } catch (eE) {}
              }
            }
          }
        }

        // 4. 刷新 cue 字幕自訂樣式（字級與陰影）
        updateSubtitleCueStyle();
      }

      document.addEventListener('fullscreenchange', handleFullscreenChange);
      document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
      document.addEventListener('mozfullscreenchange', handleFullscreenChange);
      document.addEventListener('MSFullscreenChange', handleFullscreenChange);
      if (videoPlayer) {
        videoPlayer.addEventListener('webkitbeginfullscreen', handleFullscreenChange);
        videoPlayer.addEventListener('webkitendfullscreen', handleFullscreenChange);
      }

      // ── 影片內全螢幕控制按鈕之滑鼠活動同步淡入淡出機制 ──
      var overlayHideTimer = null;
      var overlayFsBtn = document.getElementById('video-overlay-fs-btn');
      var vBoxEl = document.getElementById('video-box');

      function hideOverlayFsBtn() {
        if (overlayFsBtn && videoPlayer && !videoPlayer.paused) {
          overlayFsBtn.classList.remove('visible');
        }
      }

      function showOverlayFsBtn() {
        if (overlayFsBtn) overlayFsBtn.classList.add('visible');
        if (overlayHideTimer) {
          clearTimeout(overlayHideTimer);
          overlayHideTimer = null;
        }
        if (videoPlayer && !videoPlayer.paused) {
          overlayHideTimer = setTimeout(hideOverlayFsBtn, 2000);
        }
      }

      if (vBoxEl) {
        vBoxEl.addEventListener('mousemove', showOverlayFsBtn);
        vBoxEl.addEventListener('pointermove', showOverlayFsBtn);
        vBoxEl.addEventListener('touchstart', showOverlayFsBtn, { passive: true });
        vBoxEl.addEventListener('mouseleave', function () {
          if (overlayHideTimer) {
            clearTimeout(overlayHideTimer);
            overlayHideTimer = null;
          }
          hideOverlayFsBtn();
        });
      }

      if (overlayFsBtn) {
        overlayFsBtn.addEventListener('mouseenter', function () {
          if (overlayHideTimer) {
            clearTimeout(overlayHideTimer);
            overlayHideTimer = null;
          }
          overlayFsBtn.classList.add('visible');
        });
        overlayFsBtn.addEventListener('mouseleave', function () {
          if (videoPlayer && !videoPlayer.paused) {
            overlayHideTimer = setTimeout(hideOverlayFsBtn, 2000);
          }
        });
      }

      if (videoPlayer) {
        videoPlayer.addEventListener('play', showOverlayFsBtn);
        videoPlayer.addEventListener('playing', showOverlayFsBtn);
        videoPlayer.addEventListener('seeking', showOverlayFsBtn);
        videoPlayer.addEventListener('volumechange', showOverlayFsBtn);
        videoPlayer.addEventListener('pause', function () {
          if (overlayHideTimer) {
            clearTimeout(overlayHideTimer);
            overlayHideTimer = null;
          }
          if (overlayFsBtn) overlayFsBtn.classList.add('visible');
        });
        videoPlayer.addEventListener('ended', function () {
          if (overlayHideTimer) {
            clearTimeout(overlayHideTimer);
            overlayHideTimer = null;
          }
          if (overlayFsBtn) overlayFsBtn.classList.add('visible');
        });
      }

      // ════════════════════════════════════════════════════════════════
      //  📱 手機模式橫置自動進入全螢幕引擎 (Mobile Auto-Fullscreen on Landscape)
      //  - 嚴格限定僅手機版面 (Mobile Phone Only)，杜絕 PC/筆電/桌面螢幕誤觸發
      //  - 偵測手機從直立轉為橫置 (Portrait -> Landscape)：自動無縫進入全螢幕播放
      //  - 偵測手機從橫置轉回直立 (Landscape -> Portrait)：自動離開全螢幕還原為頁面檢視
      //  - 支援雙軌容錯：原生 requestFullscreen 受限時自動切換滿版網頁全螢幕，確保第2次及後續橫置 100% 成功
      //  - 播放或調整進度時絕不強制全螢幕；全螢幕需由使用者手動點擊或手機轉向觸發
      // ════════════════════════════════════════════════════════════════
      (function initMobileAutoFullscreen() {
        var orientationState = {
          lastOrientation: null,
          debounceTimer: null,
          userManuallyExitedFs: false
        };

        // 嚴格判定是否為真實「手機版面」(排除 PC、筆記型電腦、觸控螢幕桌機)
        function isMobilePhone() {
          var ua = navigator.userAgent || navigator.vendor || window.opera || '';

          // 1. 排除明確的桌面作業系統 (Windows, macOS, Linux x86/x64)
          var isDesktopOS = /Windows NT|Macintosh|X11|Linux x86_64/i.test(ua);
          var isMobileUA = /Android.*Mobile|iPhone|iPod|Mobile|BlackBerry|IEMobile|Opera Mini/i.test(ua);

          // 若為桌面 OS 且非明確手機 UA，絕對視為 PC，不啟用自動橫置全螢幕
          if (isDesktopOS && !isMobileUA) {
            return false;
          }

          // 2. 檢測是否具備桌面滑鼠精確指標與 Hover 能力 (PC / 筆電觸控板)
          if (window.matchMedia) {
            var hasFine = window.matchMedia('(pointer: fine)').matches;
            var canHover = window.matchMedia('(hover: hover)').matches;
            if (hasFine && canHover && !isMobileUA) {
              return false;
            }
          }

          // 3. 符合行動裝置 UA 或非桌面作業系統環境
          return (isMobileUA || !isDesktopOS);
        }

        function getOrientationType() {
          // 優先以視窗即時長寬比判定 (最直接、跨瀏覽器、DevTools 與真實手機均 100% 準確無延遲)
          if (typeof window.innerWidth === 'number' && typeof window.innerHeight === 'number' && window.innerWidth > 0 && window.innerHeight > 0) {
            return (window.innerWidth > window.innerHeight) ? 'landscape' : 'portrait';
          }
          if (window.screen && window.screen.orientation && window.screen.orientation.type) {
            return window.screen.orientation.type.indexOf('landscape') !== -1 ? 'landscape' : 'portrait';
          }
          if (typeof window.orientation !== 'undefined') {
            return (Math.abs(window.orientation) === 90 || Math.abs(window.orientation) === 270) ? 'landscape' : 'portrait';
          }
          if (window.matchMedia) {
            var mql = window.matchMedia('(orientation: landscape)');
            if (mql && typeof mql.matches === 'boolean') {
              return mql.matches ? 'landscape' : 'portrait';
            }
          }
          return 'portrait';
        }

        function hasActiveVideo() {
          if (!videoPlayer) return false;
          if (typeof currentVideo !== 'undefined' && currentVideo && currentVideo.is_audio) {
            return false;
          }
          var src = videoPlayer.currentSrc || videoPlayer.src || '';
          return !!(src && src !== window.location.href);
        }

        function checkAndApplyOrientation() {
          // 嚴格限制：僅在確認為手機版面時才執行自動橫置全螢幕
          if (!isMobilePhone()) return;

          var curOrient = getOrientationType();
          var prevOrient = orientationState.lastOrientation;
          var isFs = window.isFullscreenActive ? window.isFullscreenActive() : false;

          // 1. 直立轉橫置 (Portrait -> Landscape)：若正在播放影片且未全螢幕，自動切換全螢幕
          if (curOrient === 'landscape') {
            if (prevOrient === 'portrait') {
              // 重新由直立轉為橫置，重置手動退出全螢幕標記
              orientationState.userManuallyExitedFs = false;
            }
            if (hasActiveVideo() && !isFs && !orientationState.userManuallyExitedFs) {
              if (window.enterFullscreen) {
                window.enterFullscreen();
              }
            }
          }
          // 2. 橫置轉直立 (Landscape -> Portrait)：自動退出全螢幕還原為頁面檢視
          else if (curOrient === 'portrait') {
            orientationState.userManuallyExitedFs = false;
            if (isFs) {
              if (window.exitFullscreen) {
                window.exitFullscreen();
              }
            }
          }

          orientationState.lastOrientation = curOrient;
        }

        function triggerOrientationCheck() {
          if (!isMobilePhone()) return;
          if (orientationState.debounceTimer) {
            clearTimeout(orientationState.debounceTimer);
          }
          // 立即檢查並於 150ms 後補償檢查 (適應行動裝置旋轉動畫與重繪延遲)
          checkAndApplyOrientation();
          orientationState.debounceTimer = setTimeout(function () {
            checkAndApplyOrientation();
          }, 150);
        }

        orientationState.lastOrientation = getOrientationType();

        // 僅監聽螢幕旋轉事件 (手機物理轉向時觸發)
        if (window.screen && window.screen.orientation && window.screen.orientation.addEventListener) {
          window.screen.orientation.addEventListener('change', triggerOrientationCheck);
        }
        window.addEventListener('orientationchange', triggerOrientationCheck);

        if (window.matchMedia) {
          var mql = window.matchMedia('(orientation: landscape)');
          if (mql.addEventListener) {
            mql.addEventListener('change', triggerOrientationCheck);
          } else if (mql.addListener) {
            mql.addListener(triggerOrientationCheck);
          }
        }

        // 僅針對手機裝置監聽視窗 resize 作為旋轉備援 (PC 調整視窗大小絕不觸發全螢幕)
        window.addEventListener('resize', function () {
          if (isMobilePhone()) {
            triggerOrientationCheck();
          }
        });

        // 監聽全螢幕狀態變化，同步追蹤使用者手動退出行為
        function onFsChangeSync() {
          var isFs = window.isFullscreenActive ? window.isFullscreenActive() : false;
          var curOrient = getOrientationType();
          if (!isFs && curOrient === 'landscape') {
            // 使用者在手機橫置狀態下主動按下了退出全螢幕 (ESC / 介面按鈕)
            orientationState.userManuallyExitedFs = true;
          } else if (isFs) {
            orientationState.userManuallyExitedFs = false;
          }
          orientationState.lastOrientation = curOrient;
        }
        document.addEventListener('fullscreenchange', onFsChangeSync);
        document.addEventListener('webkitfullscreenchange', onFsChangeSync);
        document.addEventListener('mozfullscreenchange', onFsChangeSync);
        document.addEventListener('MSFullscreenChange', onFsChangeSync);
        if (videoPlayer) {
          videoPlayer.addEventListener('webkitbeginfullscreen', onFsChangeSync);
          videoPlayer.addEventListener('webkitendfullscreen', onFsChangeSync);
        }

        // 供向後相容之空函式，避免任何舊有外部呼叫引發未定義異常
        window.checkMobileLandscapeFullscreen = function () {};
      })();

      // ════════════════════════════════════════════════════════════════
      //  🛡️ 智慧防卡頓緩衝守護與快取引擎 (Anti-Stutter Buffer Guard & Pre-Cache Engine)
      //  - 監聽 waiting / stalled 事件：網路壅塞導致緩衝耗盡時，阻止微卡頓循環
      //  - 暫時鎖定並充沛累積安全水位（預設 4.0 秒）後平滑續播
      //  - 即時計算播放前向緩衝 (Buffered Health) 並呈現在工具列
      //  - 支援將中小型影片或純音訊一鍵預載至本機記憶體 Blob 快取，徹底零網路延遲
      // ════════════════════════════════════════════════════════════════
      window.bufferGuard = {
        active: false,
        targetSec: 4.0,
        checkTimer: null,
        safetyTimer: null,
        cachedBlobUrl: null,
        cachedVideoId: null
      };

      window.getForwardBufferSeconds = function () {
        if (!videoPlayer || !videoPlayer.buffered || videoPlayer.buffered.length === 0) return 0;
        var cur = videoPlayer.currentTime || 0;
        for (var i = 0; i < videoPlayer.buffered.length; i++) {
          var s = videoPlayer.buffered.start(i);
          var e = videoPlayer.buffered.end(i);
          if (cur >= (s - 0.6) && cur <= e) {
            return Math.max(0, e - cur);
          }
        }
        return 0;
      };

      window.updateBufferHealthDisplay = function () {
        var badge = document.getElementById('buffer-health-badge');
        var dot = document.getElementById('buffer-health-dot');
        var text = document.getElementById('buffer-health-text');
        if (!badge || !dot || !text) return;

        var src = videoPlayer ? (videoPlayer.currentSrc || videoPlayer.src || '') : '';
        if (!src || src === window.location.href) {
          text.textContent = '緩衝: --';
          dot.className = 'buffer-health-dot';
          badge.title = '尚未載入媒體';
          return;
        }

        if (window.bufferGuard && window.bufferGuard.cachedBlobUrl && src === window.bufferGuard.cachedBlobUrl) {
          text.textContent = '離線快取';
          dot.className = 'buffer-health-dot good';
          badge.title = '⚡ 本片已快取於本機記憶體，100% 脫機零延遲播放';
          return;
        }

        var sec = window.getForwardBufferSeconds();
        var dur = videoPlayer ? (videoPlayer.duration || 0) : 0;
        var cur = videoPlayer ? (videoPlayer.currentTime || 0) : 0;
        var remain = Math.max(0, dur - cur);

        var secStr = sec >= 60 ? (Math.floor(sec / 60) + 'm' + Math.round(sec % 60) + 's') : (sec.toFixed(1) + 's');
        text.textContent = '緩衝 ' + secStr;

        if (sec >= 20 || (remain > 0 && remain <= sec + 0.5)) {
          dot.className = 'buffer-health-dot good';
          badge.title = '緩衝充沛：超前預載 ' + sec.toFixed(1) + ' 秒，播放極為平穩流暢';
        } else if (sec >= 6) {
          dot.className = 'buffer-health-dot fair';
          badge.title = '緩衝穩定：超前預載 ' + sec.toFixed(1) + ' 秒';
        } else {
          dot.className = 'buffer-health-dot poor';
          badge.title = '緩衝偏低：僅餘 ' + sec.toFixed(1) + ' 秒，弱網時可能短暫蓄水充沛';
        }
      };

      window.showBufferGuardOverlay = function (curSec, targetSec) {
        var overlay = document.getElementById('buffer-guard-overlay');
        var fill = document.getElementById('buffer-guard-bar-fill');
        var curEl = document.getElementById('buffer-guard-cur-sec');
        var targetEl = document.getElementById('buffer-guard-target-sec');
        if (!overlay) return;

        var pct = Math.min(100, Math.max(0, (curSec / targetSec) * 100));
        if (fill) fill.style.width = pct.toFixed(1) + '%';
        if (curEl) curEl.textContent = curSec.toFixed(1);
        if (targetEl) targetEl.textContent = targetSec.toFixed(1);

        overlay.classList.add('active');
      };

      window.hideBufferGuardOverlay = function () {
        var overlay = document.getElementById('buffer-guard-overlay');
        if (!overlay) return;
        overlay.classList.remove('active');
      };

      window.triggerBufferGuard = function () {
        if (!videoPlayer || videoPlayer.paused || videoPlayer.ended) return;
        if (window.bufferGuard && window.bufferGuard.active) return;
        if (window.bufferGuard && window.bufferGuard.cachedBlobUrl && videoPlayer.src === window.bufferGuard.cachedBlobUrl) return;

        var duration = videoPlayer.duration || 0;
        var curTime = videoPlayer.currentTime || 0;
        var remainTime = Math.max(0, duration - curTime);
        var curBuffer = window.getForwardBufferSeconds();

        // 剩餘時間極短或已經全部緩衝完畢時不介入
        if (remainTime <= 1.2 || curBuffer >= remainTime) return;

        var target = Math.min(window.bufferGuard.targetSec, remainTime);
        if (curBuffer >= target) return;

        window.bufferGuard.active = true;
        window.showBufferGuardOverlay(curBuffer, target);

        if (window.bufferGuard.checkTimer) clearInterval(window.bufferGuard.checkTimer);
        if (window.bufferGuard.safetyTimer) clearTimeout(window.bufferGuard.safetyTimer);

        window.bufferGuard.checkTimer = setInterval(function () {
          if (!videoPlayer) return;
          var nowBuffer = window.getForwardBufferSeconds();
          var nowRemain = Math.max(0, (videoPlayer.duration || 0) - (videoPlayer.currentTime || 0));
          var curTarget = Math.min(window.bufferGuard.targetSec, nowRemain);

          window.showBufferGuardOverlay(nowBuffer, curTarget);

          if (nowBuffer >= curTarget || (nowRemain > 0 && nowBuffer >= nowRemain)) {
            window.releaseBufferGuard('🟢 緩衝蓄滿，恢復流暢播放');
          }
        }, 220);

        // 12 秒後保險解除，防止弱網極端卡頓導致卡在 HUD
        window.bufferGuard.safetyTimer = setTimeout(function () {
          if (window.bufferGuard && window.bufferGuard.active) {
            window.releaseBufferGuard('⚡ 已預載可用緩衝，嘗試恢復播放');
          }
        }, 12000);
      };

      window.releaseBufferGuard = function (toastMsg) {
        if (window.bufferGuard.checkTimer) {
          clearInterval(window.bufferGuard.checkTimer);
          window.bufferGuard.checkTimer = null;
        }
        if (window.bufferGuard.safetyTimer) {
          clearTimeout(window.bufferGuard.safetyTimer);
          window.bufferGuard.safetyTimer = null;
        }
        window.bufferGuard.active = false;
        window.hideBufferGuardOverlay();

        if (videoPlayer && !videoPlayer.ended) {
          videoPlayer.play().catch(function () {});
        }
        if (toastMsg) {
          showToast(toastMsg, 1600);
        }
        window.updateBufferHealthDisplay();
      };

      window.skipBufferGuard = function () {
        window.releaseBufferGuard('▶️ 已手動跳過緩衝等待，立即播放');
      };

      // ── ⚡ 瀏覽器本機記憶體快取 (In-Memory Pre-Cache) ──
      var precacheAbortController = null;

      window.startPrecacheCurrentVideo = function () {
        if (!currentVideo) {
          showToast('⚠️ 請先選取要快取的影片或音訊');
          return;
        }
        var btn = document.getElementById('btn-precache');
        var text = document.getElementById('precache-btn-text');

        if (precacheAbortController) {
          precacheAbortController.abort();
          precacheAbortController = null;
          if (text) text.textContent = '離線快取';
          if (btn) btn.classList.remove('cached');
          showToast('🛑 已中止快取下載');
          return;
        }

        if (window.bufferGuard && window.bufferGuard.cachedBlobUrl && window.bufferGuard.cachedVideoId === currentVideo.id) {
          showToast('⚡ 此影片已存在於瀏覽器記憶體快取中，目前 100% 離線流暢播放');
          return;
        }

        var rawSize = currentVideo.size || 0;
        if (rawSize > 250 * 1024 * 1024) {
          if (!confirm('提示：此影片檔案較大 (' + (currentVideo.size_str || '') + ')，下載至快取可能佔用較多記憶體與傳輸時間。確定要預載至快取嗎？')) {
            return;
          }
        }

        precacheAbortController = new AbortController();
        var signal = precacheAbortController.signal;

        if (text) text.textContent = '下載中 0%';
        showToast('🚀 開始將「' + currentVideo.title + '」預載至瀏覽器快取...');

        var streamUrl = window.buildStreamUrl(currentVideo.rel_path, false);

        fetch(streamUrl, { signal: signal })
          .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var contentLength = res.headers.get('content-length');
            var total = contentLength ? parseInt(contentLength, 10) : 0;
            var loaded = 0;

            if (!res.body || !res.body.getReader) {
              return res.blob();
            }

            var reader = res.body.getReader();
            var chunks = [];

            function pump() {
              return reader.read().then(function (result) {
                if (result.done) {
                  return new Blob(chunks, { type: res.headers.get('content-type') || 'video/mp4' });
                }
                chunks.push(result.value);
                loaded += result.value.length;
                if (total > 0 && text) {
                  var p = Math.min(99, Math.round((loaded / total) * 100));
                  text.textContent = '下載中 ' + p + '%';
                }
                return pump();
              });
            }
            return pump();
          })
          .then(function (blob) {
            precacheAbortController = null;

            if (window.bufferGuard && window.bufferGuard.cachedBlobUrl) {
              try { URL.revokeObjectURL(window.bufferGuard.cachedBlobUrl); } catch(e) {}
            }

            var blobUrl = URL.createObjectURL(blob);
            window.bufferGuard.cachedBlobUrl = blobUrl;
            window.bufferGuard.cachedVideoId = currentVideo.id;

            if (text) text.textContent = '已快取';
            if (btn) btn.classList.add('cached');

            // 平滑切換當前播放來源為 Blob URL
            if (videoPlayer) {
              var curPos = videoPlayer.currentTime || 0;
              var wasPaused = videoPlayer.paused;
              videoPlayer.src = blobUrl;
              videoPlayer.currentTime = curPos;
              if (!wasPaused) {
                videoPlayer.play().catch(function () {});
              }
            }

            showToast('🎉「' + currentVideo.title + '」已成功快取至本機！完全免除網路壅塞影響。', 3500);
            window.updateBufferHealthDisplay();
          })
          .catch(function (err) {
            precacheAbortController = null;
            if (err && err.name === 'AbortError') return;
            if (text) text.textContent = '離線快取';
            if (btn) btn.classList.remove('cached');
            console.error('快取失敗:', err);
            showToast('❌ 快取下載失敗：' + (err.message || '連線中斷'));
          });
      };

      // ════════════════════════════════════════════════════════════════
      //  🌟 WebGL 劇院環境動態光暈引擎 (Ambient Light Shader Engine)
      //  - 100% 原生 Vanilla WebGL / GLSL 片段著色器，零外部 CDN 依賴
      //  - 128x72 超輕量雙線性取樣 + 邊緣色彩向外拉伸滲透 (Edge Bleed)
      //  - 30 FPS 節能更新上限 + 暫停/背景分頁自動休眠，零伺服器/用戶端多餘負擔
      // ════════════════════════════════════════════════════════════════
      (function initAmbilightEngine() {
        var canvas = document.getElementById('ambilight-canvas');
        var video = document.getElementById('video-player');
        var toggleBtn = document.getElementById('btn-ambilight-toggle');
        if (!canvas || !video) return;

        var isEnabled = true;
        try {
          var saved = localStorage.getItem('vid_ambilight_enabled');
          if (saved !== null) {
            isEnabled = (saved === 'true');
          }
        } catch (e) {}

        var gl = null;
        var program = null;
        var texture = null;
        var positionBuffer = null;
        var isInitialized = false;
        var isRendering = false;
        var animFrameId = null;
        var lastRenderTime = 0;
        var FPS_INTERVAL = 1000 / 30; // 節能限制：最高 30 FPS
        var useFallback2D = false;
        var ctx2d = null;

        var VS_SOURCE = [
          'attribute vec2 a_position;',
          'varying vec2 v_uv;',
          'void main() {',
          '  v_uv = (a_position + 1.0) * 0.5;',
          '  gl_Position = vec4(a_position, 0.0, 1.0);',
          '}'
        ].join('\n');

        var FS_SOURCE = [
          'precision mediump float;',
          'uniform sampler2D u_video;',
          'uniform float u_saturation;',
          'uniform float u_brightness;',
          'varying vec2 v_uv;',
          'void main() {',
          '  // 內縮取樣以將四周邊界像素向外延展溢流 (Edge Bleed Effect)',
          '  vec2 uv = clamp((v_uv - 0.12) / 0.76, 0.0, 1.0);',
          '  uv.y = 1.0 - uv.y; // 翻轉 Y 軸配合 WebGL 座標系',
          '  vec4 col = texture2D(u_video, uv);',
          '  // 提升彩度與沉浸光影對比',
          '  float gray = dot(col.rgb, vec3(0.299, 0.587, 0.114));',
          '  vec3 satCol = mix(vec3(gray), col.rgb, u_saturation);',
          '  // 徑向柔和外邊界光暈衰減 (Vignette Falloff)',
          '  float dist = distance(v_uv, vec2(0.5));',
          '  float vignette = smoothstep(0.72, 0.28, dist);',
          '  gl_FragColor = vec4(satCol * u_brightness * vignette, 1.0);',
          '}'
        ].join('\n');

        function initGL() {
          canvas.width = 128;
          canvas.height = 72;

          try {
            gl = canvas.getContext('webgl', { alpha: false, depth: false, antialias: false, powerPreference: 'low-power' }) ||
                 canvas.getContext('experimental-webgl', { alpha: false, depth: false, antialias: false, powerPreference: 'low-power' });
          } catch (e) { gl = null; }

          if (!gl) {
            useFallback2D = true;
            ctx2d = canvas.getContext('2d');
            isInitialized = true;
            return true;
          }

          function compileShader(type, src) {
            var s = gl.createShader(type);
            gl.shaderSource(s, src);
            gl.compileShader(s);
            if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
              console.warn('Ambilight Shader Compile Error:', gl.getShaderInfoLog(s));
              gl.deleteShader(s);
              return null;
            }
            return s;
          }

          var vs = compileShader(gl.VERTEX_SHADER, VS_SOURCE);
          var fs = compileShader(gl.FRAGMENT_SHADER, FS_SOURCE);
          if (!vs || !fs) {
            useFallback2D = true;
            ctx2d = canvas.getContext('2d');
            isInitialized = true;
            return true;
          }

          program = gl.createProgram();
          gl.attachShader(program, vs);
          gl.attachShader(program, fs);
          gl.linkProgram(program);
          if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            useFallback2D = true;
            ctx2d = canvas.getContext('2d');
            isInitialized = true;
            return true;
          }
          gl.useProgram(program);

          positionBuffer = gl.createBuffer();
          gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
          var positions = new Float32Array([
            -1, -1,
             1, -1,
            -1,  1,
            -1,  1,
             1, -1,
             1,  1
          ]);
          gl.bufferData(gl.ARRAY_BUFFER, positions, gl.STATIC_DRAW);

          var aPosLoc = gl.getAttribLocation(program, 'a_position');
          gl.enableVertexAttribArray(aPosLoc);
          gl.vertexAttribPointer(aPosLoc, 2, gl.FLOAT, false, 0, 0);

          var uSatLoc = gl.getUniformLocation(program, 'u_saturation');
          var uBrightLoc = gl.getUniformLocation(program, 'u_brightness');
          gl.uniform1f(uSatLoc, 1.45);
          gl.uniform1f(uBrightLoc, 1.15);

          texture = gl.createTexture();
          gl.bindTexture(gl.TEXTURE_2D, texture);
          gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
          gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
          gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
          gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);

          isInitialized = true;
          return true;
        }

        function renderFrame() {
          if (!isEnabled || !isRendering) return;

          var now = performance.now();
          var elapsed = now - lastRenderTime;

          if (elapsed >= FPS_INTERVAL) {
            lastRenderTime = now - (elapsed % FPS_INTERVAL);

            if (video.readyState >= 2 && !video.paused && !video.ended && video.videoWidth > 0) {
              if (!useFallback2D && gl && program) {
                try {
                  gl.bindTexture(gl.TEXTURE_2D, texture);
                  gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, video);
                  gl.drawArrays(gl.TRIANGLES, 0, 6);
                } catch (eTex) {
                  useFallback2D = true;
                  ctx2d = canvas.getContext('2d');
                }
              } else if (useFallback2D && ctx2d) {
                try {
                  ctx2d.drawImage(video, 0, 0, canvas.width, canvas.height);
                } catch (e2d) {}
              }
            }
          }

          if (isRendering) {
            animFrameId = requestAnimationFrame(renderFrame);
          }
        }

        function startRendering() {
          if (!isEnabled || isRendering) return;
          if (window.innerWidth <= 1024) return; // 手機與平板端影片黏著置頂滿版，自動休眠避免耗電

          // 全螢幕檢測：全螢幕下播放器佔滿整張螢幕，背後環境光完全被遮蔽，強制休眠徹底解放 GPU 頻寬防掉幀
          var isFs = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement || (video && video.webkitDisplayingFullscreen));
          if (isFs) return;

          if (!isInitialized) {
            initGL();
          }
          isRendering = true;
          canvas.classList.add('active');
          lastRenderTime = performance.now();
          animFrameId = requestAnimationFrame(renderFrame);
        }

        function stopRendering() {
          isRendering = false;
          if (animFrameId) {
            cancelAnimationFrame(animFrameId);
            animFrameId = null;
          }
          canvas.classList.remove('active');
        }

        function updateButtonUI() {
          if (!toggleBtn) return;
          var icon = document.getElementById('ambilight-btn-icon');
          var text = document.getElementById('ambilight-btn-text');
          if (isEnabled) {
            toggleBtn.classList.add('active');
            toggleBtn.title = '劇院環境動態光暈：已開啟（點擊關閉）';
            if (icon) icon.textContent = '💡';
            if (text) text.textContent = '環境光';
          } else {
            toggleBtn.classList.remove('active');
            toggleBtn.title = '劇院環境動態光暈：已關閉（點擊開啟）';
            if (icon) icon.textContent = '🌑';
            if (text) text.textContent = '環境光';
          }
        }

        window.toggleAmbilight = function () {
          isEnabled = !isEnabled;
          try {
            localStorage.setItem('vid_ambilight_enabled', isEnabled ? 'true' : 'false');
          } catch (e) {}
          updateButtonUI();
          if (isEnabled) {
            showToast('💡 已開啟 WebGL 劇院環境光暈');
            if (video && !video.paused && !video.ended && video.videoWidth > 0) {
              startRendering();
            }
          } else {
            showToast('🌑 已關閉 WebGL 劇院環境光暈');
            stopRendering();
          }
        };

        // 事件掛載：播放、暫停、結束與分頁切換自動啟閉
        video.addEventListener('play', function () {
          if (isEnabled && !document.hidden && video.videoWidth > 0) {
            startRendering();
          }
        });

        video.addEventListener('playing', function () {
          if (isEnabled && !document.hidden && video.videoWidth > 0) {
            startRendering();
          }
        });

        video.addEventListener('pause', function () {
          stopRendering();
        });

        video.addEventListener('ended', function () {
          stopRendering();
        });

        video.addEventListener('emptied', function () {
          stopRendering();
        });

        document.addEventListener('visibilitychange', function () {
          if (document.hidden) {
            stopRendering();
          } else if (isEnabled && video && !video.paused && !video.ended && video.videoWidth > 0) {
            startRendering();
          }
        });

        window.addEventListener('resize', function () {
          if (window.innerWidth <= 1024) {
            if (isRendering) stopRendering();
          } else {
            if (isEnabled && video && !video.paused && !video.ended && !isRendering && video.videoWidth > 0) {
              startRendering();
            }
          }
        }, { passive: true });

        // 全螢幕切換監聽：進入全螢幕時休眠環境光暈，退出全螢幕時平滑恢復
        function handleFsForAmbilight() {
          var isFs = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement || (video && video.webkitDisplayingFullscreen));
          if (isFs) {
            stopRendering();
          } else if (isEnabled && video && !video.paused && !video.ended && video.videoWidth > 0 && window.innerWidth > 1024) {
            startRendering();
          }
        }
        document.addEventListener('fullscreenchange', handleFsForAmbilight);
        document.addEventListener('webkitfullscreenchange', handleFsForAmbilight);
        document.addEventListener('mozfullscreenchange', handleFsForAmbilight);
        document.addEventListener('MSFullscreenChange', handleFsForAmbilight);
        if (video) {
          video.addEventListener('webkitbeginfullscreen', handleFsForAmbilight);
          video.addEventListener('webkitendfullscreen', handleFsForAmbilight);
        }

        updateButtonUI();
        if (isEnabled && video && !video.paused && !video.ended && video.videoWidth > 0) {
          startRendering();
        }
      })();

      function clearVideoTracks() {
        if (!videoPlayer) return;
        // 1. 完全停用所有文字字幕軌道，強制瀏覽器清除渲染佇列與緩存 Cues
        if (videoPlayer.textTracks) {
          for (var i = 0; i < videoPlayer.textTracks.length; i++) {
            try {
              videoPlayer.textTracks[i].mode = 'disabled';
            } catch(e1) {}
          }
        }
        // 2. 移除 DOM 上的所有 <track> 元素
        var tracks = videoPlayer.querySelectorAll('track');
        tracks.forEach(function(t) {
          try {
            if (t.track) t.track.mode = 'disabled';
            t.parentNode.removeChild(t);
          } catch(e2) {}
        });
        // 3. 重設圖形字幕管理員
        GraphicSubtitleManager.reset();
        // 4. 重設字幕清單與介面元件狀態
        currentSubtitles = [];
        rawEmbeddedList = [];
        activeSubtitleIndex = -1;
        if (subtitleBar) subtitleBar.style.display = 'none';
        if (subtitleSizeGroup) subtitleSizeGroup.style.display = 'none';
        if (subtitleStyleControls) subtitleStyleControls.style.display = 'none';
        var embRow = document.getElementById('embedded-subs-row');
        if (embRow) embRow.style.display = 'none';
        var embText = document.getElementById('embedded-subs-text');
        if (embText) embText.innerHTML = '';
        closeSubtitleDropdown();
        closeSubtitleColorDropdown();
      }

      function activateSubtitle(index, quiet) {
        activeSubtitleIndex = index;
        var isGraphic = false;

        if (index >= 0 && currentSubtitles[index]) {
          var sub = currentSubtitles[index];
          if (sub.type === 'graphic') {
            isGraphic = true;
            // 隱藏所有純文字軌道 (以 'hidden' 保持 cues 緩存，避免被瀏覽器清空)
            if (videoPlayer && videoPlayer.textTracks) {
              var tracks = videoPlayer.textTracks;
              for (var i = 0; i < tracks.length; i++) {
                try { tracks[i].mode = 'hidden'; } catch(e) {}
              }
            }
            GraphicSubtitleManager.load(sub.streamUrl);
          } else {
            // 切換為純文字字幕時，關閉 Canvas 圖形字幕層
            GraphicSubtitleManager.hide();
            if (videoPlayer) {
              if (videoPlayer.textTracks) {
                var textTracks = videoPlayer.textTracks;
                for (var j = 0; j < textTracks.length; j++) {
                  try {
                    textTracks[j].mode = (textTracks[j].label === sub.label) ? 'showing' : 'hidden';
                  } catch(e) {}
                }
              }
              var trackEls = videoPlayer.querySelectorAll('track');
              for (var m = 0; m < trackEls.length; m++) {
                try {
                  if (trackEls[m].track) {
                    trackEls[m].track.mode = (trackEls[m].label === sub.label) ? 'showing' : 'hidden';
                  }
                } catch(e) {}
              }
            }
          }
        } else {
          // 關閉所有字幕
          GraphicSubtitleManager.hide();
          if (videoPlayer) {
            if (videoPlayer.textTracks) {
              var allTracks = videoPlayer.textTracks;
              for (var k = 0; k < allTracks.length; k++) {
                try { allTracks[k].mode = 'hidden'; } catch(e) {}
              }
            }
            var allTrackEls = videoPlayer.querySelectorAll('track');
            for (var n = 0; n < allTrackEls.length; n++) {
              try {
                if (allTrackEls[n].track) {
                  allTrackEls[n].track.mode = 'hidden';
                }
              } catch(e) {}
            }
          }
        }

        renderSubtitleButtons();
        renderEmbeddedSubtitleTags();
        if (subtitleSizeGroup) {
          subtitleSizeGroup.style.display = (index >= 0) ? 'flex' : 'none';
        }
        if (subtitleStyleControls) {
          subtitleStyleControls.style.display = (index >= 0) ? 'flex' : 'none';
        }
        try {
          if (index >= 0 && currentSubtitles[index]) {
            var s = currentSubtitles[index];
            localStorage.setItem('vid_subtitle_pref', s.prefKey || s.label || '__ON__');
            if (!quiet && !isGraphic) showToast('💬 已啟用字幕：' + s.label);
          } else {
            localStorage.setItem('vid_subtitle_pref', '__OFF__');
            if (!quiet) showToast('💬 已關閉字幕');
          }
        } catch(e) {}
      }

      function renderSubtitleButtons() {
        if (!subtitleTrackList) return;
        if (currentSubtitles.length === 0) {
          if (subtitleBar) subtitleBar.style.display = 'none';
          closeSubtitleDropdown();
          closeSubtitleColorDropdown();
          return;
        }
        if (subtitleBar) subtitleBar.style.display = 'flex';
        if (subtitleCountBadge) subtitleCountBadge.textContent = currentSubtitles.length;
        var countEl = document.getElementById('sub-dropdown-count');
        if (countEl) countEl.textContent = '共 ' + currentSubtitles.length + ' 個';

        var activeLabelText = '關閉';
        var html = '';
        var isOffActive = (activeSubtitleIndex === -1);
        html += '<button type="button" class="subtitle-btn off-btn' + (isOffActive ? ' active' : '') + '" onclick="selectSubtitle(-1); closeSubtitleDropdown();" title="關閉所有字幕">' +
                '<span>✕ 關閉字幕</span><span class="sub-item-check">' + (isOffActive ? '✓' : '') + '</span></button>';

        for (var i = 0; i < currentSubtitles.length; i++) {
          var sub = currentSubtitles[i];
          var isActive = (activeSubtitleIndex === i);
          if (isActive) activeLabelText = sub.label;
          var icon = (sub.type === 'graphic') ? '🎨 ' : ((sub.type === 'embedded') ? '🔤 ' : '💬 ');
          html += '<button type="button" class="subtitle-btn' + (isActive ? ' active' : '') + '" onclick="selectSubtitle(' + i + '); closeSubtitleDropdown();" title="' + escapeHtml(sub.streamUrl || sub.label) + '">' +
                  '<span>' + icon + escapeHtml(sub.label) + '</span><span class="sub-item-check">' + (isActive ? '✓' : '') + '</span></button>';
        }
        subtitleTrackList.innerHTML = html;

        var activeTagEl = document.getElementById('subtitle-active-tag');
        if (activeTagEl) {
          activeTagEl.textContent = activeLabelText;
          activeTagEl.style.color = (activeSubtitleIndex >= 0) ? 'var(--cyan)' : 'var(--text3)';
        }
      }

      function closeSubtitleDropdown() {
        var menu = document.getElementById('subtitle-dropdown-menu');
        var label = document.getElementById('subtitle-bar-label');
        if (menu) menu.classList.remove('show');
        if (label) label.classList.remove('dropdown-open');
      }
      window.closeSubtitleDropdown = closeSubtitleDropdown;

      function closeSubtitleColorDropdown() {
        var menu = document.getElementById('subtitle-color-dropdown-menu');
        var btn = document.getElementById('sub-color-picker-btn');
        if (menu) menu.classList.remove('show');
        if (btn) btn.classList.remove('dropdown-open');
      }
      window.closeSubtitleColorDropdown = closeSubtitleColorDropdown;

      function openSubtitleDropdown() {
        closeSubtitleColorDropdown();
        var menu = document.getElementById('subtitle-dropdown-menu');
        var label = document.getElementById('subtitle-bar-label');
        if (menu) menu.classList.add('show');
        if (label) label.classList.add('dropdown-open');
      }
      window.openSubtitleDropdown = openSubtitleDropdown;

      function openSubtitleColorDropdown() {
        closeSubtitleDropdown();
        var menu = document.getElementById('subtitle-color-dropdown-menu');
        var btn = document.getElementById('sub-color-picker-btn');
        if (menu) menu.classList.add('show');
        if (btn) btn.classList.add('dropdown-open');
      }
      window.openSubtitleColorDropdown = openSubtitleColorDropdown;

      function toggleSubtitleDropdown(event) {
        if (event) event.stopPropagation();
        var menu = document.getElementById('subtitle-dropdown-menu');
        if (menu && menu.classList.contains('show')) {
          closeSubtitleDropdown();
        } else {
          openSubtitleDropdown();
        }
      }
      window.toggleSubtitleDropdown = toggleSubtitleDropdown;

      function toggleSubtitleColorDropdown(event) {
        if (event) event.stopPropagation();
        var menu = document.getElementById('subtitle-color-dropdown-menu');
        if (menu && menu.classList.contains('show')) {
          closeSubtitleColorDropdown();
        } else {
          openSubtitleColorDropdown();
        }
      }
      window.toggleSubtitleColorDropdown = toggleSubtitleColorDropdown;

      document.addEventListener('click', function(e) {
        var label = document.getElementById('subtitle-bar-label');
        if (label && !label.contains(e.target)) {
          closeSubtitleDropdown();
        }
        var colorBtn = document.getElementById('sub-color-picker-btn');
        if (colorBtn && !colorBtn.contains(e.target)) {
          closeSubtitleColorDropdown();
        }
      });

      function renderEmbeddedSubtitleTags() {
        var embRow  = document.getElementById('embedded-subs-row');
        var embText = document.getElementById('embedded-subs-text');
        var embNote = document.getElementById('embedded-image-note');
        if (!embRow || !embText) return;

        if (rawEmbeddedList.length === 0) {
          embRow.style.display = 'none';
          return;
        }

        embRow.style.display = '';
        var hasUnsupportedImage = false;
        var html = '';

        // 關閉字幕選項
        var isOffActive = (activeSubtitleIndex === -1);
        html += '<button type="button" class="emb-sub-tag off-tag' + (isOffActive ? ' active' : '') + '" onclick="selectSubtitle(-1)" title="關閉字幕">' +
                (isOffActive ? '✓ ' : '✕ ') + '關閉字幕</button>';

        for (var k = 0; k < rawEmbeddedList.length; k++) {
          var e = rawEmbeddedList[k];
          var displayName = e.name ? e.name.trim() : '';
          var langName = e.lang_label ? e.lang_label.trim() : '';
          var mainLabel = displayName;
          if (!mainLabel) {
            mainLabel = langName || '字幕';
          } else if (langName && mainLabel.indexOf(langName) === -1 && langName.indexOf(mainLabel) === -1) {
            mainLabel = mainLabel + ' (' + langName + ')';
          }
          var tagLabel = mainLabel + (e.codec_label ? ' (' + e.codec_label + ')' : '');

          var isGraphicDvd = (e.is_graphic || e.can_render || e.codec === 'dvd_subtitle' || e.codec === 'vobsub' || e.codec === 'mp4s');
          if (e.is_image && !isGraphicDvd) {
            hasUnsupportedImage = true;
            html += '<span class="emb-sub-tag disabled" title="此為高解析點陣字幕 (' + escapeHtml(e.codec_label) + ')，瀏覽器無法直接播放" onclick="showToast(\'⚠️ 此為高解析點陣字幕 (' + escapeHtml(e.codec_label) + ')，瀏覽器無法直接播放\')">' +
                    escapeHtml(tagLabel) + ' 🖼️</span>';
          } else {
            var matchIdx = -1;
            for (var m = 0; m < currentSubtitles.length; m++) {
              if ((currentSubtitles[m].type === 'embedded' || currentSubtitles[m].type === 'graphic') && currentSubtitles[m].subIndex === e.sub_index) {
                matchIdx = m;
                break;
              }
            }

            var isActive = (matchIdx >= 0 && activeSubtitleIndex === matchIdx);
            var clickAction = isActive ? 'selectSubtitle(-1)' : (matchIdx >= 0 ? ('selectSubtitle(' + matchIdx + ')') : 'selectSubtitle(-1)');
            var clickTitle = isActive ? '點擊關閉此字幕' : '點擊切換播放此字幕';
            var iconBadge = isGraphicDvd ? ' 🎨' : '';
            html += '<button type="button" class="emb-sub-tag' + (isActive ? ' active' : '') + '" onclick="' + clickAction + '" title="' + clickTitle + '">' +
                    (isActive ? '✓ ' : '') + escapeHtml(tagLabel) + iconBadge + '</button>';
          }
        }

        embText.innerHTML = html;
        if (embNote) embNote.style.display = hasUnsupportedImage ? 'inline' : 'none';
      }

      window.selectSubtitle = function(index) { activateSubtitle(index, false); };

      function updateSubtitleSizeDisplay() {
        var labelEl = document.getElementById('subtitle-size-label');
        var valEl = document.getElementById('sub-size-val');
        if (!valEl) return;
        var scaleFixed = subtitleFontScale.toFixed(1);
        valEl.textContent = scaleFixed + 'x';
        var isModified = Math.abs(subtitleFontScale - 1.0) > 0.01;
        if (labelEl) {
          if (isModified) {
            labelEl.classList.add('is-modified');
            labelEl.setAttribute('title', '當前字級 ' + scaleFixed + 'x (點擊重設為 1.0x)');
          } else {
            labelEl.classList.remove('is-modified');
            labelEl.setAttribute('title', '當前為預設字級 1.0x (點擊重設)');
          }
        }
      }

      window.resetSubtitleSize = function() {
        if (Math.abs(subtitleFontScale - 1.0) <= 0.01) {
          showToast('💬 字級已是預設大小 (1.0x)');
          return;
        }
        subtitleFontScale = 1.0;
        applySubtitleFontScale();
        if (GraphicSubtitleManager.isShowing()) {
          GraphicSubtitleManager.rerender();
        }
        try { localStorage.setItem('vid_subtitle_font_scale', '1.0'); } catch(e) {}
        showToast('💬 字幕字級已重設為預設大小 (1.0x)');
      };

      window.adjustSubtitleSize = function(delta) {
        subtitleFontScale = Math.max(0.5, Math.min(2.5, subtitleFontScale + delta));
        subtitleFontScale = Math.round(subtitleFontScale * 10) / 10;
        applySubtitleFontScale();
        if (GraphicSubtitleManager.isShowing()) {
          GraphicSubtitleManager.rerender();
        }
        try { localStorage.setItem('vid_subtitle_font_scale', subtitleFontScale); } catch(e) {}
      };

      function updateSubtitleCueStyle() {
        var styleId = 'subtitle-cue-style';
        var existing = document.getElementById(styleId);
        if (!existing) {
          existing = document.createElement('style');
          existing.id = styleId;
          document.head.appendChild(existing);
        }

        var colorHex = '#ffffff';
        if (currentSubtitleColorMode === 'yellow') colorHex = '#ffe628';
        else if (currentSubtitleColorMode === 'cyan') colorHex = '#00f0ff';
        else if (currentSubtitleColorMode === 'white') colorHex = '#ffffff';
        else colorHex = '#ffffff';

        var shadowCss = currentSubtitleShadow
          ? '0 2px 6px #000, 0 0 4px #000, 1px 1px 2px #000 !important'
          : 'none !important';

        var fontSizePct = Math.round(subtitleFontScale * 100) + '%';

        existing.textContent =
          'video::cue, ::cue, video:fullscreen::cue, :fullscreen::cue, .video-container:fullscreen video::cue, .video-container:-webkit-full-screen video::cue, .video-container:-moz-full-screen video::cue {\n' +
          '  font-size: ' + fontSizePct + ' !important;\n' +
          '  color: ' + colorHex + ' !important;\n' +
          '  text-shadow: ' + shadowCss + ';\n' +
          '  background: rgba(0, 0, 0, 0.72) !important;\n' +
          '  font-family: "Noto Sans TC", "Microsoft JhengHei", sans-serif !important;\n' +
          '  line-height: 1.4 !important;\n' +
          '  border-radius: 3px !important;\n' +
          '  padding: 2px 6px !important;\n' +
          '}\n';
      }

      function applySubtitleFontScale() {
        updateSubtitleCueStyle();
        updateSubtitleSizeDisplay();
      }

      // 初始同步字級數值顯示
      updateSubtitleSizeDisplay();

      var currentSubtitleColorMode = localStorage.getItem('vid_subtitle_color_mode') || 'original';
      var currentSubtitleShadow = true; // 字幕陰影預設開啟

      window.setSubtitleColorMode = function (mode) {
        currentSubtitleColorMode = mode;
        try { localStorage.setItem('vid_subtitle_color_mode', mode); } catch(e) {}
        GraphicSubtitleManager.setColorMode(mode);
        applySubtitleStyle();
      };

      window.toggleSubtitleShadow = function () {
        currentSubtitleShadow = !currentSubtitleShadow;
        try { localStorage.setItem('vid_subtitle_shadow', currentSubtitleShadow ? 'true' : 'false'); } catch(e) {}
        GraphicSubtitleManager.setShadow(currentSubtitleShadow);
        applySubtitleStyle();
      };

      function applySubtitleStyle() {
        var box = document.getElementById('video-box');
        if (box) {
          box.classList.remove('sub-color-yellow', 'sub-color-white', 'sub-color-cyan', 'sub-color-original');
          box.classList.add('sub-color-' + currentSubtitleColorMode);

          box.classList.remove('sub-shadow-on', 'sub-shadow-off');
          box.classList.add(currentSubtitleShadow ? 'sub-shadow-on' : 'sub-shadow-off');
        }

        var colorLabels = {
          'yellow': '🎨 🟡 亮黃',
          'white': '🎨 ⚪ 純白',
          'cyan': '🎨 🟢 青綠',
          'original': '🎨 🎬 原色'
        };
        var currentLabelEl = document.getElementById('sub-color-current-label');
        if (currentLabelEl && colorLabels[currentSubtitleColorMode]) {
          currentLabelEl.textContent = colorLabels[currentSubtitleColorMode];
        }

        var colorBtns = ['yellow', 'white', 'cyan', 'original'];
        for (var i = 0; i < colorBtns.length; i++) {
          var btn = document.getElementById('sub-color-' + colorBtns[i]);
          if (btn) {
            var isCur = (colorBtns[i] === currentSubtitleColorMode);
            if (isCur) {
              btn.classList.add('active');
            } else {
              btn.classList.remove('active');
            }
            var checkSpan = btn.querySelector('.sub-item-check');
            if (checkSpan) {
              checkSpan.textContent = isCur ? '✓' : '';
            }
          }
        }

        var shadowBtn = document.getElementById('sub-shadow-btn');
        if (shadowBtn) {
          if (currentSubtitleShadow) {
            shadowBtn.classList.add('active');
            shadowBtn.innerHTML = '<span>⬛ 陰影: 開</span>';
          } else {
            shadowBtn.classList.remove('active');
            shadowBtn.innerHTML = '<span>⬜ 陰影: 關</span>';
          }
        }

        updateSubtitleCueStyle();
      }
      applySubtitleStyle();

      var subtitleFetchSeq = 0;
      async function fetchAndRenderSubtitles(relPath) {
        var thisFetchSeq = ++subtitleFetchSeq;
        clearVideoTracks();

        try {
          var url = 'index.php?action=subtitles&file=' + encodeURIComponent(relPath);
          if (window.AUTH_TOKEN) {
            url += '&auth_token=' + encodeURIComponent(window.AUTH_TOKEN);
          }
          var res = await authFetch(url);
          var data = await res.json();
          // 若在非同步請求期間已切換其他影片或被重置，直接捨棄此過期回應
          if (thisFetchSeq !== subtitleFetchSeq || !currentVideo || currentVideo.rel_path !== relPath) {
            return;
          }
          if (!data || !data.success) return;

          // 1. 整理外掛字幕
          var extSubs = data.subtitles || [];
          for (var i = 0; i < extSubs.length; i++) {
            var sub = extSubs[i];
            currentSubtitles.push({
              type: 'external',
              label: sub.label || sub.filename || ('字幕 ' + (i + 1)),
              lang: sub.lang || 'zh',
              prefKey: sub.label || sub.rel_path,
              streamUrl: window.buildStreamUrl(sub.rel_path),
              relPath: sub.rel_path,
              subIndex: -1,
              trackNum: 0
            });
          }

          // 2. 整理內嵌字幕 (MKV/MP4 內置軌道：支援純文字與 DVD 圖形字幕)
          rawEmbeddedList = data.embedded || [];
          for (var k = 0; k < rawEmbeddedList.length; k++) {
            var e = rawEmbeddedList[k];
            var isGraphicDvd = (e.is_graphic || e.can_render || e.codec === 'dvd_subtitle' || e.codec === 'vobsub' || e.codec === 'mp4s');
            var displayName = e.name ? e.name.trim() : '';
            var langName = e.lang_label ? e.lang_label.trim() : '';
            var mainLabel = displayName;
            if (!mainLabel) {
              mainLabel = langName || '字幕';
            } else if (langName && mainLabel.indexOf(langName) === -1 && langName.indexOf(mainLabel) === -1) {
              mainLabel = mainLabel + ' (' + langName + ')';
            }
            var eLabel = mainLabel + (e.codec_label ? ' (' + e.codec_label + ')' : '');

            var curParams = new URLSearchParams(window.location.search);
            if (isGraphicDvd) {
              var graphicUrl = 'index.php?action=subtitles_graphic&file=' + encodeURIComponent(relPath) + '&sub_index=' + e.sub_index + '&track_num=' + (e.track_num || 0) + '&_t=' + Date.now();
              if (curParams.has('dir')) {
                graphicUrl += '&dir=' + encodeURIComponent(curParams.get('dir'));
              }
              if (window.AUTH_TOKEN) {
                graphicUrl += '&auth_token=' + encodeURIComponent(window.AUTH_TOKEN);
              }
              var fullGraphicUrl = new URL(graphicUrl, window.location.href).href;

              currentSubtitles.push({
                type: 'graphic',
                codec: e.codec || 'dvd_subtitle',
                label: eLabel,
                lang: e.lang || 'zh',
                prefKey: (e.name ? e.name : '') + '_' + e.lang_label,
                streamUrl: fullGraphicUrl,
                relPath: relPath,
                subIndex: e.sub_index,
                trackNum: e.track_num || 0
              });
            } else if (!e.is_image) {
              var vttUrl = 'index.php?action=subtitles_vtt&file=' + encodeURIComponent(relPath) + '&sub_index=' + e.sub_index + '&track_num=' + (e.track_num || 0) + '&_t=' + Date.now();
              if (curParams.has('dir')) {
                vttUrl += '&dir=' + encodeURIComponent(curParams.get('dir'));
              }
              if (window.AUTH_TOKEN) {
                vttUrl += '&auth_token=' + encodeURIComponent(window.AUTH_TOKEN);
              }
              var fullVttUrl = new URL(vttUrl, window.location.href).href;

              currentSubtitles.push({
                type: 'embedded',
                label: eLabel,
                lang: e.lang || 'zh',
                prefKey: (e.name ? e.name : '') + '_' + e.lang_label,
                streamUrl: fullVttUrl,
                relPath: relPath,
                subIndex: e.sub_index,
                trackNum: e.track_num || 0
              });
            }
          }

          // 3. 若有任何可播放字幕，掛載 HTML5 <track> 元素至播放器（純文字字幕專用）
          if (currentSubtitles.length > 0) {
            for (var t = 0; t < currentSubtitles.length; t++) {
              var s = currentSubtitles[t];
              if (s.type !== 'graphic') {
                var track = document.createElement('track');
                track.kind = 'subtitles';
                track.label = s.label;
                track.srclang = s.lang || 'zh';
                track.src = s.streamUrl;
                track.default = false;
                videoPlayer.appendChild(track);
              }
            }

            // 4. 智慧優先自動選擇 (Auto Select)
            var savedPref = '';
            try { savedPref = localStorage.getItem('vid_subtitle_pref') || ''; } catch(eL) {}

            var autoIndex = -1;
            if (savedPref === '__OFF__') {
              autoIndex = -1; // 使用者上次手動關閉字幕
            } else if (savedPref) {
              // 優先尋找與上次記憶相符的語言/名稱
              for (var j = 0; j < currentSubtitles.length; j++) {
                var c = currentSubtitles[j];
                if (c.prefKey === savedPref || c.label === savedPref || (c.relPath && c.relPath === savedPref)) {
                  autoIndex = j;
                  break;
                }
              }
            }

            // 若尚未選定，實施中文優先智慧推薦
            if (autoIndex === -1 && savedPref !== '__OFF__') {
              // 優先級 1: 繁體中文 / Traditional Chinese / 繁中
              for (var c1 = 0; c1 < currentSubtitles.length; c1++) {
                var lbl1 = currentSubtitles[c1].label.toLowerCase();
                if (lbl1.indexOf('traditional') !== -1 || lbl1.indexOf('繁體') !== -1 || lbl1.indexOf('繁中') !== -1 || lbl1.indexOf('cht') !== -1) {
                  autoIndex = c1;
                  break;
                }
              }
              // 優先級 2: 任意中文 (Chinese, zh, chi, zho, 中文)
              if (autoIndex === -1) {
                for (var c2 = 0; c2 < currentSubtitles.length; c2++) {
                  var lbl2 = currentSubtitles[c2].label.toLowerCase();
                  var lng2 = (currentSubtitles[c2].lang || '').toLowerCase();
                  if (lbl2.indexOf('中文') !== -1 || lbl2.indexOf('chinese') !== -1 || lng2 === 'zh' || lng2 === 'chi' || lng2 === 'zho') {
                    autoIndex = c2;
                    break;
                  }
                }
              }
              // 優先級 3: 若只有 1~2 個字幕，預設啟用第 1 個
              if (autoIndex === -1 && currentSubtitles.length <= 2) {
                autoIndex = 0;
              }
            }

            renderSubtitleButtons();
            renderEmbeddedSubtitleTags();

            if (autoIndex >= 0) {
              setTimeout(function() {
                activateSubtitle(autoIndex, true);
              }, 150);
            }

            var savedScale = parseFloat(localStorage.getItem('vid_subtitle_font_scale') || '1.0');
            if (!isNaN(savedScale) && savedScale > 0) {
              subtitleFontScale = savedScale;
              applySubtitleFontScale();
            }
            applySubtitleStyle();
          } else {
            renderEmbeddedSubtitleTags();
          }

        } catch(e) {
          console.warn('字幕偵測/提取失敗:', e);
        }
      }

      // ════════════════════════════════════════════════════════
      //  以下為原有功能
      // ════════════════════════════════════════════════════════

      // 2. 渲染麵包屑導航列 (支援點擊任意上層)
      function renderBreadcrumbs(breadcrumbs) {
        const bar = document.getElementById('breadcrumb-bar');
        if (!bar) return;

        let html = '';
        (breadcrumbs || []).forEach((crumb, idx) => {
          const isLast = (idx === breadcrumbs.length - 1);
          if (idx > 0) {
            html += '<span class="breadcrumb-sep">/</span>';
          }
          if (isLast) {
            html += `<span class="breadcrumb-item active" onclick="handleBreadcrumbClick('${escapeHtml(crumb.path)}')">${escapeHtml(crumb.name)}</span>`;
          } else {
            html += `<span class="breadcrumb-item" onclick="handleBreadcrumbClick('${escapeHtml(crumb.path)}')">${escapeHtml(crumb.name)}</span>`;
          }
        });

        // 若當前在非根目錄之特定資料夾，於麵包屑末端加入複製目錄連結按鈕
        if (!isRecentMode && currentPath) {
          html += `
            <button type="button" class="folder-share-btn breadcrumb-copy-btn" onclick="copyFolderShareLink(event, '${escapeHtml(currentPath)}', this)" title="複製此目錄直達連結至剪貼簿，方便分享給家人">
              <span>🔗</span> <span>複製目錄連結</span>
            </button>
          `;
        }

        bar.innerHTML = html;
      }

      window.handleBreadcrumbClick = function(path) {
        if (isRecentMode) {
          isRecentMode = false;
          updateNavButtonsUI();
          const titleText = document.getElementById('playlist-title-text');
          if (titleText) titleText.textContent = '🎬 影音目錄瀏覽';
        }
        if (path !== '__RECENT__' && path !== currentPath) {
          navigateToPath(path);
        }
        // 手機版面時，點擊導航路徑自動帶出播放清單抽屜
        if (window.innerWidth <= 1024) {
          openMobileDrawer();
        }
      };

      // 3. 檔案總管式整合渲染清單（包含 [回到上一層]、子資料夾 與 影片檔案）
      function renderExplorerList() {
        const container = document.getElementById('playlist-items');
        const counter = document.getElementById('filter-counter');
        if (!container) return;

        const hasParent = (currentPath !== '') || isRecentMode;

        const subfolders = isRecentMode ? [] : (currentFolderData.subfolders || []);
        const videos = currentVideos || [];

        const filteredFolders = subfolders.filter(f =>
          !searchQuery || f.name.toLowerCase().includes(searchQuery.toLowerCase())
        );

        // 依據排序模式排序資料夾（名稱排序時套用升冪/降冪）
        if (sortOrder.indexOf('name') === 0) {
          filteredFolders.sort((a, b) => {
            return sortOrder === 'name_desc'
              ? b.name.localeCompare(a.name, 'zh-Hant')
              : a.name.localeCompare(b.name, 'zh-Hant');
          });
        }

        const filteredVideos = videos.filter(v =>
          !searchQuery ||
          v.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
          v.filename.toLowerCase().includes(searchQuery.toLowerCase()) ||
          v.ext.toLowerCase().includes(searchQuery.toLowerCase())
        );

        // 依據排序模式排序檔案（支援時間遞減/遞增、名稱遞增/遞減）
        filteredVideos.sort((a, b) => {
          if (sortOrder === 'time_desc') {
            return (b.mtime || 0) - (a.mtime || 0);
          } else if (sortOrder === 'time_asc') {
            return (a.mtime || 0) - (b.mtime || 0);
          } else if (sortOrder === 'name_desc') {
            return (b.filename || b.title || '').localeCompare(a.filename || a.title || '', 'zh-Hant');
          } else {
            return (a.filename || a.title || '').localeCompare(b.filename || b.title || '', 'zh-Hant');
          }
        });

        if (counter) {
          let countText = '';
          if (filteredFolders.length > 0) {
            countText += `${filteredFolders.length} 目錄 · `;
          }
          countText += `${filteredVideos.length} 部`;
          counter.textContent = countText;
        }

        if (filteredFolders.length === 0 && filteredVideos.length === 0 && !hasParent) {
          container.innerHTML = `
            <div style="padding:40px 16px;text-align:center;color:var(--text3);font-size:0.85rem;">
              未找到相符的資料夾或影音檔案
            </div>
          `;
          return;
        }

        let html = '';

        // (1) 若非根目錄或處於最新模式，第一列顯示 [回到上一層 / 回目錄瀏覽]
        if (hasParent) {
          const parentHint = isRecentMode ? '返回資料夾目錄瀏覽' : '返回上層目錄';
          const parentTitle = isRecentMode ? '.. [回目錄瀏覽]' : '.. [回到上一層]';
          const parentIcon = isRecentMode ? '📁' : '⬆️';
          html += `
            <div class="playlist-item item-parent" onclick="goToParentFolder()" title="${parentHint}">
              <div class="item-icon" style="color:var(--cyan);">${parentIcon}</div>
              <div class="item-body">
                <div class="item-title" style="color:var(--cyan);font-weight:700;">${parentTitle}</div>
                <div class="item-meta">
                  <span style="color:var(--text3);">${parentHint}</span>
                </div>
              </div>
              <div class="item-action-hint">返回 ↵</div>
            </div>
          `;
        }

        // (2) 子資料夾清單（點擊進入，最新模式不顯示；支援懸停 Hover 與點擊隨機預覽資料夾內影片）
        if (!isRecentMode) {
          filteredFolders.forEach(folder => {
            const hasSample = folder.sample_video && folder.sample_video.rel_path;
            const previewRel = hasSample ? folder.sample_video.rel_path : '';
            const sampleName = (hasSample && (folder.sample_video.title || folder.sample_video.filename)) || '';
            const previewTitle = hasSample ? `🎬 ${sampleName} (📁 ${folder.name} 隨機預覽)` : `📁 ${folder.name}`;
            const poolJson = (hasSample && Array.isArray(folder.sample_video.pool) && folder.sample_video.pool.length > 0)
              ? escapeHtml(JSON.stringify(folder.sample_video.pool))
              : '';
            const totalFound = (hasSample && folder.sample_video.total_found) ? folder.sample_video.total_found : 0;
            const badgeHint = totalFound > 1 ? `• 🎲 隨機預覽 (${totalFound}部)` : '• 🎬 含預覽';

            html += `
              <div class="playlist-item item-folder ${hasSample ? 'has-preview' : ''}" onclick="navigateToPath('${escapeHtml(folder.rel_path)}')" data-folder-path="${escapeHtml(folder.rel_path)}" data-folder-name="${escapeHtml(folder.name)}" ${poolJson ? `data-sample-pool="${poolJson}"` : ''} ${hasSample ? `data-rel-path="${escapeHtml(previewRel)}" data-title="${escapeHtml(previewTitle)}"` : ''}>
                <div class="item-icon">📁</div>
                <div class="item-body">
                  <div class="item-title" title="${escapeHtml(folder.name)}">${escapeHtml(folder.name)}</div>
                  <div class="item-meta">
                    <span class="item-badge-folder">資料夾</span>
                    ${hasSample ? `<span style="color:var(--text3);font-size:0.68rem;">${badgeHint}</span>` : ''}
                    <button type="button" class="folder-share-btn" onclick="copyFolderShareLink(event, '${escapeHtml(folder.rel_path)}', this)" title="複製此資料夾直達連結至剪貼簿，方便分享給家人直接瀏覽">
                      <span>🔗</span> <span>複製連結</span>
                    </button>
                  </div>
                </div>
                <button type="button" class="item-preview-btn" onclick="event.stopPropagation(); window._ppMobilePreview && window._ppMobilePreview(this.parentElement)" title="隨機預覽此資料夾影片">👁</button>
                <div class="item-action-hint">進入 ›</div>
              </div>
            `;
          });
        }

        // (3) 影片/音訊檔案清單（點擊播放）
        filteredVideos.forEach((item, index) => {
          const isPlaying = currentVideo && (currentVideo.id === item.id);
          const progressKey = 'vid_prog_' + item.id;
          const savedProgress = parseFloat(localStorage.getItem(progressKey) || '0');
          const durationKey = 'vid_dur_' + item.id;
          const savedDuration = parseFloat(localStorage.getItem(durationKey) || '0');

          let progressPct = 0;
          if (savedDuration > 0 && savedProgress > 0) {
            progressPct = Math.min(100, Math.round((savedProgress / savedDuration) * 100));
          }

          const fileIcon = item.is_audio ? '🎵' : '🎬';
          const isNewBadge = (isRecentMode || item.is_new) ? `<span class="badge-new">NEW</span>` : '';
          const folderTag = (isRecentMode && item.folder_path) ?
            `<span class="item-folder-tag" title="點擊跳轉至所屬目錄" onclick="event.stopPropagation(); jumpToFolder('${escapeHtml(item.folder_path)}');">📁 ${escapeHtml(item.folder_path)}</span><button type="button" class="folder-share-btn" onclick="copyFolderShareLink(event, '${escapeHtml(item.folder_path)}', this)" title="複製此影片所屬資料夾直達連結" style="margin-left:3px;padding:0 5px;font-size:0.65rem;"><span>🔗</span></button>` : '';
          const timeTag = item.time_ago ? `<span style="color:var(--text3);">• 🕒 ${escapeHtml(item.time_ago)}</span>` : '';

          html += `
            <div class="playlist-item item-video ${isPlaying ? 'active' : ''}" onclick="playVideoById('${item.id}')" id="item-${item.id}" data-rel-path="${escapeHtml(item.rel_path)}" data-is-audio="${item.is_audio ? '1' : '0'}" data-title="${escapeHtml(item.title)}">
              <div class="item-icon">${fileIcon}</div>
              <div class="item-body">
                <div class="item-title" title="${escapeHtml(item.title)}">
                  ${isNewBadge}
                  ${escapeHtml(item.title)}
                </div>
                <div class="item-meta">
                  <span class="format-badge ${item.ext}">${item.ext}</span>
                  <span>${item.size_str}</span>
                  ${timeTag}
                  ${folderTag}
                  ${progressPct > 0 ? `<span style="color:var(--cyan);">• 已看 ${progressPct}%</span>` : ''}
                </div>
              </div>
              ${item.is_audio ? '' : `<button class="item-preview-btn" onclick="event.stopPropagation(); window._ppMobilePreview && window._ppMobilePreview(this.parentElement)" title="預覽影片">👁</button>`}
              <div class="equalizer-wave">
                <div class="equalizer-bar"></div>
                <div class="equalizer-bar"></div>
                <div class="equalizer-bar"></div>
              </div>
              ${progressPct > 0 ? `<div class="item-progress-bar" style="width: ${progressPct}%"></div>` : ''}
            </div>
          `;
        });

        container.innerHTML = html;
      }

      // ════════════════════════════════════════════════════════════════
      // 伺服器端設定說明與常用技巧面板管理器 (Server Guide Manager)
      // 改用具名函式宣告 (function declaration) 以取得 Hoisting 提升特性，
      // 確保在 applyRecentData/applyFolderData 初始化階段呼叫時已完成定義。
      // ════════════════════════════════════════════════════════════════
      function showServerGuide(targetTab) {
        var guide = document.getElementById('server-guide-card');
        var viewport = document.getElementById('video-viewport-card');
        var info = document.getElementById('video-info-card');
        var returnBtn = document.getElementById('guide-back-to-player-btn');
        var playingTitle = document.getElementById('guide-playing-title');

        if (guide) guide.style.display = 'flex';
        if (viewport) viewport.style.display = 'none';
        if (info) info.style.display = 'none';

        if (targetTab && typeof switchGuideTab === 'function') {
          switchGuideTab(targetTab);
        }

        if (currentVideo && (currentVideo.title || currentVideo.filename)) {
          var titleText = currentVideo.title || currentVideo.filename;
          if (returnBtn) {
            returnBtn.style.display = 'inline-flex';
            returnBtn.title = '返回影片播放：' + titleText;
          }
          if (playingTitle) {
            playingTitle.textContent = titleText;
          }
        } else {
          if (returnBtn) returnBtn.style.display = 'none';
        }

        if (guide && typeof guide.scrollIntoView === 'function') {
          guide.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      function hideServerGuide() {
        var guide = document.getElementById('server-guide-card');
        var viewport = document.getElementById('video-viewport-card');
        var info = document.getElementById('video-info-card');

        if (guide) guide.style.display = 'none';
        if (viewport) viewport.style.display = '';
        if (info) info.style.display = '';
      }

      function toggleServerGuide(targetTab) {
        var guide = document.getElementById('server-guide-card');
        if (guide && guide.style.display !== 'none' && currentVideo) {
          hideServerGuide();
        } else {
          showServerGuide(targetTab || 'config');
        }
      }

      function switchGuideTab(tabId) {
        var tabBtns = document.querySelectorAll('.guide-tab-btn');
        var tabPanes = document.querySelectorAll('.guide-tab-pane');

        for (var i = 0; i < tabBtns.length; i++) {
          if (tabBtns[i].getAttribute('data-tab') === tabId) {
            tabBtns[i].classList.add('active');
          } else {
            tabBtns[i].classList.remove('active');
          }
        }

        for (var j = 0; j < tabPanes.length; j++) {
          if (tabPanes[j].id === 'guide-tab-' + tabId) {
            tabPanes[j].classList.add('active');
          } else {
            tabPanes[j].classList.remove('active');
          }
        }
      }

      // 維持全域掛載，確保 HTML 按鈕的 onclick 事件可正常觸發
      window.showServerGuide = showServerGuide;
      window.hideServerGuide = hideServerGuide;
      window.toggleServerGuide = toggleServerGuide;
      window.switchGuideTab = switchGuideTab;

      // 套用最新影音資料結構至介面
      function applyRecentData(data) {
        if (!data || !data.success) return;
        isRecentMode = true;
        sortOrder = 'time_desc'; // 切換到最新影音時，排序強制設為「最新」
        try { localStorage.setItem('vid_sort_order', 'time_desc'); } catch(e) {}
        updateSortButtonUI();
        recentVideosData = data;
        currentVideos = data.videos || [];

        updateNavButtonsUI();

        const titleText = document.getElementById('playlist-title-text');
        if (titleText) {
          const cacheHint = data.from_cache ? `快取: ${data.cached_time}` : '已更新';
          titleText.innerHTML = `
            🔥 全站最新影音
            <span style="font-size:0.7rem;font-weight:normal;color:var(--text3);font-family:var(--mono);margin-left:4px;">(${cacheHint})</span>
          `;
        }

        renderBreadcrumbs([
          { name: '全部影片', path: '' },
          { name: '🔥 全站最新發布 (最新 40 部)', path: '__RECENT__' }
        ]);

        renderExplorerList();

        const statCount = document.getElementById('stat-video-count');
        if (statCount) statCount.textContent = data.count || 0;

        // 檢查是否有等待直連播放的指定影片（來自 URL ?play= 或 ?v=）
        if (window._pendingPlayRelPath) {
          var pending = window._pendingPlayRelPath;
          var pendingNorm = pending.replace(/\\/g, '/').toLowerCase();
          var matched = currentVideos.find(function (v) {
            var vNorm = (v.rel_path || '').replace(/\\/g, '/').toLowerCase();
            return vNorm === pendingNorm || vNorm.endsWith('/' + pendingNorm);
          });
          if (matched) {
            window._pendingPlayRelPath = null;
            playVideoById(matched.id, true);
            showToast('▶ 已直接載入分享影片：' + matched.title);
            return;
          }
        }

        // 若當前尚未播放影片，自動載入最新第一部影片預覽；若無影片，顯示伺服器設定指南
        if (currentVideos.length > 0 && !currentVideo) {
          playVideoById(currentVideos[0].id, false);
        } else if (currentVideos.length === 0 && !currentVideo) {
          showServerGuide();
        }
      }

      // 4. 讀取全站最新影音 (支援 force=true 忽略快取重新掃描)
      window.fetchRecentVideos = async function (force = false) {
        isRecentMode = true;
        sortOrder = 'time_desc'; // 切換到最新影音時，排序強制設為「最新」
        try { localStorage.setItem('vid_sort_order', 'time_desc'); } catch(e) {}
        updateSortButtonUI();
        updateNavButtonsUI();

        const container = document.getElementById('playlist-items');
        if (container) {
          container.innerHTML = `
            <div class="loading-box">
              <div class="spinner"></div>
              <div>${force ? '正在強制重新掃描 NAS 全站最新檔案 (更新快取)...' : '正在讀取最新影音清單...'}</div>
            </div>
          `;
        }

        renderBreadcrumbs([
          { name: '全部影片', path: '' },
          { name: '🔥 全站最新發布 (最新 40 部)', path: '__RECENT__' }
        ]);

        try {
          const url = `index.php?action=recent&limit=40${force ? '&force=1' : ''}`;
          const res = await authFetch(url);
          const data = await res.json();
          if (data && data.success) {
            applyRecentData(data);
          } else {
            if (data && data.auth_required) {
              handleAuthRequired();
              return;
            }
            if (container) {
              container.innerHTML = `<div style="padding:40px 16px;text-align:center;color:var(--red);">無法取得最新影音</div>`;
            }
          }
        } catch (e) {
          console.error('取得最新影片失敗:', e);
        }
      };

      // 導航按鈕狀態同步管理器 (最新影音 / 根目錄 二合一動態切換按鈕)
      function updateNavButtonsUI() {
        const toggleBtn = document.getElementById('btn-recent-sidebar');
        if (!toggleBtn) return;

        const iconEl = toggleBtn.querySelector('.btn-nav-icon');
        const textEl = toggleBtn.querySelector('.btn-nav-text');

        if (isRecentMode) {
          toggleBtn.classList.add('active', 'is-recent');
          toggleBtn.title = '回到最上層根目錄 (Home / Root)';
          if (iconEl) iconEl.textContent = '🏠';
          if (textEl) textEl.textContent = '根目錄';
        } else {
          toggleBtn.classList.remove('active', 'is-recent');
          toggleBtn.title = '檢視全站最新發布/更新的影音檔案';
          if (iconEl) iconEl.textContent = '🔥';
          if (textEl) textEl.textContent = '最新';
        }
      }

      // 切換最新影音視圖 / 根目錄二合一
      window.toggleRecentMode = function () {
        if (isRecentMode) {
          // 當前已在最新模式，再按一次直接回到最上層根目錄
          goToRootDir();
          return;
        }

        // 進入最新模式 → 清除搜尋、排序設為最新並記憶狀態
        clearSearch();
        sortOrder = 'time_desc';
        try { localStorage.setItem('vid_sort_order', 'time_desc'); } catch(e) {}
        updateSortButtonUI();
        try { localStorage.setItem('vid_last_nav', '__RECENT__'); } catch(e) {}
        fetchRecentVideos(false);
      };

      // 5. 切換排序項目 (時間 / 名稱) 與 排序順序 (遞減 / 遞增)
      window.toggleSortOrder = function () {
        const isTime = sortOrder.indexOf('time') === 0;
        const isAsc = sortOrder.indexOf('asc') !== -1;
        sortOrder = (isTime ? 'name' : 'time') + (isAsc ? '_asc' : '_desc');
        try {
          localStorage.setItem('vid_sort_order', sortOrder);
        } catch(e) {}
        updateSortButtonUI();
        renderExplorerList();
        showToast(sortOrder.indexOf('time') === 0 ? '⏱ 已切換為時間排序' : '🔤 已切換為檔案名稱排序');
      };

      window.toggleSortDirection = function () {
        const isTime = sortOrder.indexOf('time') === 0;
        const isDesc = sortOrder.indexOf('desc') !== -1;
        const newDir = isDesc ? 'asc' : 'desc';
        sortOrder = (isTime ? 'time' : 'name') + '_' + newDir;
        try {
          localStorage.setItem('vid_sort_order', sortOrder);
        } catch(e) {}
        updateSortButtonUI();
        renderExplorerList();
        showToast(newDir === 'desc' ? '▼ 已切換為遞減排序' : '▲ 已切換為遞增排序');
      };

      // 6. 從最新影音一鍵跳轉至所屬目錄
      window.jumpToFolder = function (folderPath) {
        isRecentMode = false;
        updateNavButtonsUI();
        const titleText = document.getElementById('playlist-title-text');
        if (titleText) titleText.textContent = '🎬 影音目錄瀏覽';
        navigateToPath(folderPath);
      };

      // 7. 動態導航至任意路徑 (AJAX + 快取)
      window.navigateToPath = async function (relPath, force = false) {
        if (typeof window._ppStop === 'function') {
          window._ppStop();
        }
        if (isRecentMode) {
          isRecentMode = false;
          const titleText = document.getElementById('playlist-title-text');
          if (titleText) titleText.textContent = '🎬 影音目錄瀏覽';
        }

        currentPath = relPath || '';
        updateNavButtonsUI();

        // 記憶上次瀏覽路徑
        try { localStorage.setItem('vid_last_nav', currentPath); } catch(e) {}

        // 若快取中已有且非強制重新整理，直接渲染（零延遲）
        if (!force && dirCache[currentPath]) {
          applyFolderData(dirCache[currentPath]);
          return;
        }

        const container = document.getElementById('playlist-items');
        if (container) {
          container.innerHTML = `
            <div class="loading-box">
              <div class="spinner"></div>
              <div>正在讀取目錄內容...</div>
            </div>
          `;
        }

        try {
          var browseUrl = `index.php?action=browse&path=${encodeURIComponent(currentPath)}`;
          if (force) {
            browseUrl += '&force=1';
          }
          const res = await authFetch(browseUrl);
          const data = await res.json();
          if (data && data.success) {
            dirCache[currentPath] = data;
            applyFolderData(data);
          } else {
            if (data && data.auth_required) {
              handleAuthRequired();
              return;
            }
            if (container) {
              container.innerHTML = `<div style="padding:40px 16px;text-align:center;color:var(--red);">目錄讀取失敗</div>`;
            }
          }
        } catch (e) {
          console.error("導航錯誤:", e);
        }
      };

      function applyFolderData(data) {
        currentFolderData = data;
        currentVideos = data.videos || [];
        playbackHistory = [];

        // 進入目錄時排序一律重設為名稱排序、遞增以方便使用者依序尋找想看的影片
        sortOrder = 'name_asc';
        try { localStorage.setItem('vid_sort_order', 'name_asc'); } catch(e) {}
        updateSortButtonUI();

        renderBreadcrumbs(data.breadcrumbs);
        renderExplorerList();

        // 更新頂部統計
        const statCount = document.getElementById('stat-video-count');
        const statSize = document.getElementById('stat-total-size');
        if (statCount) statCount.textContent = data.video_count || 0;
        if (statSize) statSize.textContent = data.total_size_str || '0 B';

        updateNavButtonsUI();

        // 檢查是否有等待直連播放的指定影片（來自 URL 參數 ?play= 或 ?v=，或預覽點擊「前往播放」）
        if (window._pendingPlayRelPath) {
          var pending = window._pendingPlayRelPath;
          var pendingNorm = pending.replace(/\\/g, '/').toLowerCase();
          var matched = currentVideos.find(function (v) {
            var vNorm = (v.rel_path || '').replace(/\\/g, '/').toLowerCase();
            return vNorm === pendingNorm || vNorm.endsWith('/' + pendingNorm);
          });
          if (matched) {
            window._pendingPlayRelPath = null;
            playVideoById(matched.id, true);
            var isNavFromPreview = window._isNavigatingFromPreview;
            window._isNavigatingFromPreview = false;
            showToast(isNavFromPreview ? ('▶ 已前往目錄並開始播放：' + (matched.title || matched.filename)) : ('▶ 已直接載入分享影片：' + (matched.title || matched.filename)));
            setTimeout(function() {
              var activeItem = document.querySelector('.playlist-item.item-video.active');
              if (activeItem && typeof activeItem.scrollIntoView === 'function') {
                activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
              }
            }, 120);
            return;
          }
        }

        // 若當前資料夾有影音且尚未播放影片，自動載入第一部；若無影片且當前亦無影片播放，展示伺服器指南
        if (currentVideos.length > 0 && !currentVideo) {
          playVideoById(currentVideos[0].id, false);
        } else if (currentVideos.length === 0 && !currentVideo) {
          showServerGuide();
        }
      }

      // 回到上一層目錄
      window.goToParentFolder = function () {
        if (isRecentMode) {
          toggleRecentMode();
          return;
        }
        if (currentFolderData && currentFolderData.parent_path !== null && currentFolderData.parent_path !== undefined) {
          navigateToPath(currentFolderData.parent_path);
        } else {
          navigateToPath('');
        }
      };

      // 回到最上層根目錄
      window.goToRootDir = function () {
        clearSearch();
        navigateToPath('');
        const container = document.getElementById('playlist-items');
        if (container) container.scrollTop = 0;
        showToast('🏠 已回到最上層根目錄');
      };

      // 6. 播放指定影片/音訊
      window.playVideoById = function (id, autoPlay = true) {
        const video = currentVideos.find(v => v.id === id);
        if (!video) return;

        // 若有懸停預覽或預覽彈窗在進行中，立刻停止並關閉
        if (typeof window._ppStop === 'function') {
          window._ppStop();
        }

        // 自動關閉伺服器設定指南並切換至播放器視圖
        hideServerGuide();

        currentVideo = video;
        currentIndex = currentVideos.findIndex(v => v.id === id);

        // 更新左側詳情
        const titleEl = document.getElementById('current-title');
        const pathEl = document.getElementById('current-rel-path');
        const sizeEl = document.getElementById('current-size');
        const mtimeEl = document.getElementById('current-mtime');
        const badgeEl = document.getElementById('current-format-badge');

        if (titleEl) titleEl.textContent = video.title;
        if (pathEl) pathEl.textContent = video.rel_path;
        if (sizeEl) sizeEl.textContent = video.size_str;
        if (mtimeEl) mtimeEl.textContent = video.mtime_str;
        if (badgeEl) {
          badgeEl.className = 'format-badge ' + video.ext;
          badgeEl.textContent = video.ext;
          badgeEl.style.display = 'inline-flex';
        }
        const shareBtnEl = document.getElementById('btn-share-link');
        if (shareBtnEl) {
          shareBtnEl.style.display = 'inline-flex';
        }
        const shareFolderBtnEl = document.getElementById('btn-share-folder');
        if (shareFolderBtnEl) {
          shareFolderBtnEl.style.display = 'inline-flex';
        }
        const tvCastBtnEl = document.getElementById('btn-tv-cast');
        if (tvCastBtnEl) {
          tvCastBtnEl.style.display = 'inline-flex';
        }
        const precacheBtnEl = document.getElementById('btn-precache');
        if (precacheBtnEl) {
          precacheBtnEl.style.display = 'inline-flex';
          var isCurrentCached = (window.bufferGuard && window.bufferGuard.cachedBlobUrl && window.bufferGuard.cachedVideoId === video.id);
          var precacheText = document.getElementById('precache-btn-text');
          if (isCurrentCached) {
            precacheBtnEl.classList.add('cached');
            if (precacheText) precacheText.textContent = '已快取';
          } else {
            precacheBtnEl.classList.remove('cached');
            if (precacheText) precacheText.textContent = '離線快取';
          }
        }

        // 切換純音訊專屬視覺展示層
        const audioStage = document.getElementById('audio-visual-stage');
        const audioTitle = document.getElementById('audio-track-title');
        const audioDisc = document.getElementById('audio-disc-wrap');
        const audioBars = document.getElementById('audio-spectrum-bars');

        if (video.is_audio) {
          if (audioStage) audioStage.style.display = 'flex';
          if (audioTitle) audioTitle.textContent = video.title;
        } else {
          if (audioStage) audioStage.style.display = 'none';
        }

        renderExplorerList();

        // 同步更新網址列參數，保留當前播放影片路徑（支援重整與直接分享）
        try {
          var u = new URL(window.location.href);
          u.searchParams.set('play', video.rel_path);
          u.searchParams.delete('auth_token');
          window.history.replaceState(null, '', u.href);
        } catch (eH) {}

        var streamUrl = (window.bufferGuard && window.bufferGuard.cachedBlobUrl && window.bufferGuard.cachedVideoId === video.id)
          ? window.bufferGuard.cachedBlobUrl
          : window.buildStreamUrl(video.rel_path, true);

        const savedProgress = parseFloat(localStorage.getItem('vid_prog_' + video.id) || '0');
        const savedDuration = parseFloat(localStorage.getItem('vid_dur_' + video.id) || '0');

        // ── 徹底重設並淨化播放器（清理舊媒體解碼管線、音訊狀態與殘留字幕）──
        clearVideoTracks();

        if (videoPlayer) {
          try {
            videoPlayer.pause();
          } catch(eP) {}

          // 卸載前一個影片串流，重置硬體音訊與視訊解碼通道
          videoPlayer.removeAttribute('src');
          videoPlayer.load();

          // 重新掛載新影片來源
          videoPlayer.src = streamUrl;
          videoPlayer.currentTime = 0;

          // 確保音量健全 (防止音量卡在 0 或靜音異常)
          if (videoPlayer.volume <= 0 || isNaN(videoPlayer.volume)) {
            videoPlayer.volume = 1.0;
          }

          // 同步當前倍速選單設定
          var curSpdEl = document.getElementById('speed-select');
          if (curSpdEl && curSpdEl.value) {
            videoPlayer.playbackRate = parseFloat(curSpdEl.value) || 1.0;
          }

          videoPlayer.load();

          if (savedProgress > 10 && (!savedDuration || savedProgress < savedDuration - 30)) {
            resumeTargetTime = savedProgress;
            if (resumeTimeStr) {
              resumeTimeStr.textContent = formatTime(savedProgress);
            }
            if (resumeBanner) {
              resumeBanner.classList.add('show');
              clearTimeout(window._resumeTimer);
              window._resumeTimer = setTimeout(function () {
                if (resumeBanner) resumeBanner.classList.remove('show');
              }, 10000);
            }
          } else {
            if (resumeBanner) {
              resumeBanner.classList.remove('show');
            }
          }

          if (autoPlay) {
            const playPromise = videoPlayer.play();
            if (playPromise !== undefined) {
              playPromise.catch(function (err) {
                console.warn('自動播放受限或失敗:', err);
                // 若被瀏覽器限制有聲自動播放，退回靜音自動播放，並提示點擊恢復聲音
                if (err && (err.name === 'NotAllowedError' || err.name === 'NotSupportedError')) {
                  videoPlayer.muted = true;
                  videoPlayer.play().then(function () {
                    showToast('🔇 瀏覽器限制自動播放聲音，點擊畫面即可開啟聲音');
                    var restoreAudio = function () {
                      videoPlayer.muted = false;
                      document.removeEventListener('click', restoreAudio);
                      document.removeEventListener('keydown', restoreAudio);
                    };
                    document.addEventListener('click', restoreAudio, { once: true });
                    document.addEventListener('keydown', restoreAudio, { once: true });
                  }).catch(function () {});
                }
              });
            }
          }
        }

        // ── 字幕偵測：若為影片格式則非同步查詢字幕 ────────────
        if (!video.is_audio) {
          fetchAndRenderSubtitles(video.rel_path);
        }

        // 手機模式下點擊影片自動收合側邊抽屜，回歸播放畫面
        if (window.innerWidth <= 1024) {
          closeMobileDrawer();
        }

        setTimeout(() => {
          const activeEl = document.getElementById('item-' + video.id);
          if (activeEl) {
            activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
        }, 150);
      };

      // 7. 接續播放
      window.confirmResume = function () {
        clearTimeout(window._resumeTimer);
        if (videoPlayer && resumeTargetTime > 0) {
          videoPlayer.currentTime = resumeTargetTime;
          videoPlayer.play();
        }
        if (resumeBanner) resumeBanner.classList.remove('show');
      };

      window.dismissResume = function () {
        clearTimeout(window._resumeTimer);
        if (resumeBanner) resumeBanner.classList.remove('show');
      };

      // 8. 循環播放與連播控制（支援目錄循環依目前排序、單片循環、隨機循環、播畢即停）
      let playbackHistory = [];

      // 取得當前目錄依照即時排序方式（最新/檔名）排定之播放清單
      function getCurrentPlaylistVideos() {
        var videos = (currentVideos || []);
        if (searchQuery) {
          var q = searchQuery.toLowerCase();
          videos = videos.filter(function (v) {
            return (v.title && v.title.toLowerCase().includes(q)) ||
                   (v.filename && v.filename.toLowerCase().includes(q)) ||
                   (v.ext && v.ext.toLowerCase().includes(q));
          });
        }
        var list = videos.slice();
        if (sortOrder === 'time_desc') {
          list.sort(function (a, b) {
            return (b.mtime || 0) - (a.mtime || 0);
          });
        } else if (sortOrder === 'time_asc') {
          list.sort(function (a, b) {
            return (a.mtime || 0) - (b.mtime || 0);
          });
        } else if (sortOrder === 'name_desc') {
          list.sort(function (a, b) {
            return (b.filename || b.title || '').localeCompare(a.filename || a.title || '', 'zh-Hant');
          });
        } else {
          list.sort(function (a, b) {
            return (a.filename || a.title || '').localeCompare(b.filename || b.title || '', 'zh-Hant');
          });
        }
        return list.length > 0 ? list : (currentVideos || []);
      }

      window.setLoopMode = function (mode, notify) {
        if (!['list', 'single', 'random', 'off'].includes(mode)) mode = 'list';
        loopMode = mode;
        try {
          localStorage.setItem('vid_loop_mode', mode);
          localStorage.setItem('vid_autoplay', mode !== 'off' ? 'true' : 'false');
        } catch(e) {}
        updateLoopModeUI();
        if (notify) {
          var labels = {
            list: '🔁 已切換為：目錄循環（依目前排序連播）',
            single: '🔂 已切換為：單片循環（本片播畢自動重播）',
            random: '🔀 已切換為：隨機循環（隨機抽選目錄影片）',
            off: '⏹️ 已切換為：播畢即停（不自動連播）'
          };
          showToast(labels[mode] || mode, 2000);
        }
      };

      window.cycleLoopMode = function () {
        var modes = ['list', 'single', 'random', 'off'];
        var nextIdx = (modes.indexOf(loopMode) + 1) % modes.length;
        window.setLoopMode(modes[nextIdx], true);
      };

      function updateLoopModeUI() {
        var sel = document.getElementById('loop-mode-select');
        if (sel) {
          sel.value = loopMode;
          sel.className = 'loop-select mode-' + loopMode;
        }
      }
      updateLoopModeUI();

      window.toggleAutoplay = function (val) {
        window.setLoopMode(val ? 'list' : 'off', true);
      };

      // 隨機播放當前目錄內影片
      window.playRandomEpisode = function () {
        var list = getCurrentPlaylistVideos();
        if (!list || list.length === 0) return;

        var nextIdx = 0;
        if (list.length > 1) {
          var curIdx = list.findIndex(function (v) { return v.id === (currentVideo ? currentVideo.id : ''); });
          var attempts = 0;
          do {
            nextIdx = Math.floor(Math.random() * list.length);
            attempts++;
          } while (nextIdx === curIdx && attempts < 30);
        }

        if (currentVideo) {
          playbackHistory.push(currentVideo.id);
          if (playbackHistory.length > 50) playbackHistory.shift();
        }

        playVideoById(list[nextIdx].id, true);
        showToast('🔀 隨機播放：' + list[nextIdx].title, 1800);
      };

      window.playPrevEpisode = function () {
        var list = getCurrentPlaylistVideos();
        if (!list || list.length === 0) return;

        // 隨機模式下若有歷史記錄，優先返回上一首
        if (loopMode === 'random' && playbackHistory.length > 0) {
          var prevId = playbackHistory.pop();
          var prevVideo = list.find(function (v) { return v.id === prevId; });
          if (prevVideo) {
            playVideoById(prevVideo.id, true);
            showToast('⏮ 播放上一部隨機影片：' + prevVideo.title, 1800);
            return;
          }
        }

        var curIdx = list.findIndex(function (v) { return v.id === (currentVideo ? currentVideo.id : ''); });
        var prevIdx = (curIdx >= 0) ? (curIdx - 1) : 0;
        if (prevIdx < 0) {
          prevIdx = list.length - 1;
        }
        playVideoById(list[prevIdx].id, true);
        showToast('⏮ 播放上一集：' + list[prevIdx].title, 1800);
      };

      window.playNextEpisode = function (isManual) {
        if (isManual === undefined) isManual = true;
        var list = getCurrentPlaylistVideos();
        if (!list || list.length === 0) return;

        // 手動點擊下一集且為隨機模式時，隨機挑選
        if (isManual && loopMode === 'random') {
          window.playRandomEpisode();
          return;
        }

        var curIdx = list.findIndex(function (v) { return v.id === (currentVideo ? currentVideo.id : ''); });
        var nextIdx = (curIdx >= 0) ? (curIdx + 1) : 0;
        if (nextIdx >= list.length) {
          nextIdx = 0;
        }

        if (currentVideo) {
          playbackHistory.push(currentVideo.id);
          if (playbackHistory.length > 50) playbackHistory.shift();
        }

        playVideoById(list[nextIdx].id, true);
        showToast('⏭ 播放下一集：' + list[nextIdx].title, 1800);
      };

      // 9. 播放器事件
      if (videoPlayer) {
        videoPlayer.addEventListener('play', () => {
          const disc = document.getElementById('audio-disc-wrap');
          const bars = document.getElementById('audio-spectrum-bars');
          if (disc) disc.classList.add('playing');
          if (bars) bars.classList.add('playing');

          if (typeof window.updateBufferHealthDisplay === 'function') {
            window.updateBufferHealthDisplay();
          }
        });

        videoPlayer.addEventListener('pause', () => {
          const disc = document.getElementById('audio-disc-wrap');
          const bars = document.getElementById('audio-spectrum-bars');
          if (disc) disc.classList.remove('playing');
          if (bars) bars.classList.remove('playing');

          if (window.bufferGuard && window.bufferGuard.active && typeof window.releaseBufferGuard === 'function') {
            window.releaseBufferGuard();
          }
        });

        videoPlayer.addEventListener('timeupdate', () => {
          if (!currentVideo) return;
          const cur = videoPlayer.currentTime;
          const dur = videoPlayer.duration;
          if (cur > 3) {
            localStorage.setItem('vid_prog_' + currentVideo.id, cur);
            if (dur > 0) {
              localStorage.setItem('vid_dur_' + currentVideo.id, dur);
            }
          }
          if (typeof window.updateBufferHealthDisplay === 'function') {
            window.updateBufferHealthDisplay();
          }
        });

        videoPlayer.addEventListener('waiting', function () {
          if (typeof window.triggerBufferGuard === 'function') {
            window.triggerBufferGuard();
          }
        });

        videoPlayer.addEventListener('stalled', function () {
          if (typeof window.triggerBufferGuard === 'function') {
            window.triggerBufferGuard();
          }
        });

        videoPlayer.addEventListener('progress', function () {
          if (typeof window.updateBufferHealthDisplay === 'function') {
            window.updateBufferHealthDisplay();
          }
        });

        videoPlayer.addEventListener('seeking', function () {
          if (typeof window.updateBufferHealthDisplay === 'function') {
            window.updateBufferHealthDisplay();
          }
        });

        videoPlayer.addEventListener('seeked', function () {
          if (typeof window.updateBufferHealthDisplay === 'function') {
            window.updateBufferHealthDisplay();
          }
        });

        videoPlayer.addEventListener('ended', () => {
          if (currentVideo) {
            localStorage.setItem('vid_prog_' + currentVideo.id, videoPlayer.duration || '99999');
          }
          GraphicSubtitleManager.clear();

          if (loopMode === 'single') {
            videoPlayer.currentTime = 0;
            videoPlayer.play().catch(function () {});
            showToast('🔂 單片循環：重新播放本片', 1500);
          } else if (loopMode === 'random') {
            window.playRandomEpisode();
          } else if (loopMode === 'list') {
            window.playNextEpisode(false);
          } else {
            // off: 播畢即停
            showToast('⏹️ 影片播放完畢', 1500);
          }
        });
      }

      // 10. 控制功能 (倍速、快轉/倒退跳轉)
      window.setSpeed = function (speed) {
        if (!videoPlayer) return;
        var s = parseFloat(speed) || 1.0;
        videoPlayer.playbackRate = s;
        var sel = document.getElementById('speed-select');
        if (sel && sel.value !== String(s)) {
          sel.value = String(s);
        }
        try { localStorage.setItem('vid_playback_rate', String(s)); } catch(e) {}
      };

      // ── 快轉與倒退畫面動態 HUD 提示（支援連續跳轉累加與淡入/淡出動態效果） ──
      var seekAccumSecs = 0;
      var seekIndicatorTimer = null;

      function showSeekIndicator(deltaSeconds, targetTime, duration) {
        var indicator = document.getElementById('video-seek-indicator');
        var iconEl = document.getElementById('seek-indicator-icon');
        var deltaEl = document.getElementById('seek-indicator-delta');
        var timeEl = document.getElementById('seek-indicator-time');
        if (!indicator || !iconEl || !deltaEl || !timeEl) return;

        // 若前次提示仍在顯示中且為同向跳轉，累加顯示總跳轉秒數
        if (seekIndicatorTimer && ((seekAccumSecs > 0 && deltaSeconds > 0) || (seekAccumSecs < 0 && deltaSeconds < 0))) {
          seekAccumSecs += deltaSeconds;
        } else {
          seekAccumSecs = deltaSeconds;
        }

        var isForward = seekAccumSecs > 0;
        var absSecs = Math.abs(seekAccumSecs);
        var deltaText = '';
        if (absSecs >= 60) {
          var m = Math.floor(absSecs / 60);
          var remS = absSecs % 60;
          deltaText = (isForward ? '+ ' : '- ') + m + ' 分鐘' + (remS > 0 ? (' ' + remS + ' 秒') : '');
        } else {
          deltaText = (isForward ? '+ ' : '- ') + absSecs + ' 秒';
        }

        iconEl.textContent = isForward ? '⏩' : '⏪';
        deltaEl.textContent = deltaText;

        var durStr = (duration && !isNaN(duration) && duration > 0) ? formatTime(duration) : '--:--';
        timeEl.textContent = formatTime(targetTime) + ' / ' + durStr;

        indicator.classList.remove('forward', 'rewind');
        indicator.classList.add(isForward ? 'forward' : 'rewind');

        // 觸發淡入動畫（強制 reflow 重置 transition 以確保每次連續跳轉都有動態感）
        indicator.classList.remove('show');
        void indicator.offsetWidth;
        indicator.classList.add('show');

        // 重置淡出計時器
        clearTimeout(seekIndicatorTimer);
        seekIndicatorTimer = setTimeout(function () {
          indicator.classList.remove('show');
          seekAccumSecs = 0;
          seekIndicatorTimer = null;
        }, 850);
      }

      // 快轉 / 倒退跳轉函式 (支援 5s, 1m, 5m, 10m 等相對秒數，畫面中央即時淡入/淡出資訊)
      window.seekRelative = function (seconds) {
        if (!videoPlayer || !videoPlayer.src || videoPlayer.src === window.location.href) {
          showToast('⚠️ 目前尚未播放任何影片');
          return;
        }
        var cur = videoPlayer.currentTime || 0;
        var dur = videoPlayer.duration;
        var target = cur + seconds;
        if (target < 0) target = 0;
        if (dur && !isNaN(dur) && target > dur) target = dur;
        videoPlayer.currentTime = target;

        // 影片畫面中央顯示淡入/淡出快進或倒退提示
        showSeekIndicator(seconds, target, dur);
      };

      // 11. 鍵盤快捷鍵（使用 capture 捕獲階段，防止 <video> 獲得焦點時瀏覽器原生預設行為與自訂快捷鍵衝突）
      window.addEventListener('keydown', (e) => {
        // 若使用者正在輸入框（搜尋、表單等）打字，不攔截任何快捷鍵
        if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA')) {
          return;
        }
        if (!videoPlayer) return;

        var code = e.code || '';
        var key = e.key || '';

        // 針對空白鍵 (Space)：強制優先攔截並阻止原生重複觸發，確保任何時候按下空白鍵都能精準暫停/播放
        if (code === 'Space' || key === ' ' || e.keyCode === 32) {
          e.preventDefault();
          e.stopPropagation();
          if (videoPlayer) {
            try { videoPlayer.blur(); } catch (err) {}
          }
          if (document.activeElement && document.activeElement !== document.body) {
            try { document.activeElement.blur(); } catch (err) {}
          }
          window.togglePlayPause();
          return;
        }

        switch (code) {
          case 'ArrowLeft':
            e.preventDefault();
            e.stopPropagation();
            if (e.shiftKey) seekRelative(-60);
            else if (e.ctrlKey) seekRelative(-300);
            else if (e.altKey) seekRelative(-600);
            else seekRelative(-5);
            break;
          case 'ArrowRight':
            e.preventDefault();
            e.stopPropagation();
            if (e.shiftKey) seekRelative(60);
            else if (e.ctrlKey) seekRelative(300);
            else if (e.altKey) seekRelative(600);
            else seekRelative(5);
            break;
          case 'ArrowUp':
            e.preventDefault();
            e.stopPropagation();
            videoPlayer.volume = Math.min(1, videoPlayer.volume + 0.1);
            break;
          case 'ArrowDown':
            e.preventDefault();
            e.stopPropagation();
            videoPlayer.volume = Math.max(0, videoPlayer.volume - 0.1);
            break;
          case 'KeyF':
            e.preventDefault();
            e.stopPropagation();
            toggleFullscreen();
            break;
          case 'KeyM':
            e.preventDefault();
            e.stopPropagation();
            videoPlayer.muted = !videoPlayer.muted;
            break;
          case 'KeyP':
            e.preventDefault();
            e.stopPropagation();
            playPrevEpisode();
            break;
          case 'KeyN':
            e.preventDefault();
            e.stopPropagation();
            playNextEpisode();
            break;
          case 'KeyL':
            e.preventDefault();
            e.stopPropagation();
            cycleLoopMode();
            break;
        }
      }, true);

      // 12. 搜尋與工具
      window.handleSearch = function (val) {
        searchQuery = val.trim();
        const clearBtn = document.getElementById('search-clear');
        if (clearBtn) {
          clearBtn.style.display = searchQuery ? 'block' : 'none';
        }
        renderExplorerList();
      };

      window.clearSearch = function () {
        const input = document.getElementById('search-input');
        if (input) {
          input.value = '';
          handleSearch('');
        }
      };

      window.refreshCurrentFolder = function () {
        if (isRecentMode) {
          // 最新模式下點擊重新整理：強制略過快取、重新全站掃描並更新快取
          fetchRecentVideos(true);
        } else {
          delete dirCache[currentPath];
          navigateToPath(currentPath, true);
        }
      };

      window.changeCustomDir = function () {
        var input = document.getElementById('custom-dir-input');
        if (input && input.value && input.value.trim()) {
          var val = input.value.trim();
          var search = window.location.search ? window.location.search.substring(1) : '';
          var pairs = search ? search.split('&') : [];
          var newPairs = [];
          for (var i = 0; i < pairs.length; i++) {
            if (!pairs[i]) continue;
            var k = pairs[i].split('=')[0];
            // 切換新測試目錄時清除舊的 dir、play、folder、path，避免路徑不一致發生404
            if (k !== 'dir' && k !== 'play' && k !== 'folder' && k !== 'path') {
              newPairs.push(pairs[i]);
            }
          }
          newPairs.push('dir=' + encodeURIComponent(val));
          window.location.href = window.location.pathname + '?' + newPairs.join('&');
        } else {
          if (typeof showToast === 'function') {
            showToast('⚠️ 請輸入欲測試的 NAS 絕對路徑', 3000);
          }
        }
      };

      window.resetCustomDir = function () {
        var defaultDirs = <?php echo json_encode(implode(', ', $default_video_dirs)); ?>;
        var hasDirParam = (window.location.search.indexOf('dir=') !== -1);
        if (hasDirParam) {
          // 清除 URL 中的 dir 參數以及相關的 play/folder/path，還原為原始系統環境
          var search = window.location.search.substring(1);
          var pairs = search ? search.split('&') : [];
          var newPairs = [];
          for (var i = 0; i < pairs.length; i++) {
            if (!pairs[i]) continue;
            var k = pairs[i].split('=')[0];
            if (k !== 'dir' && k !== 'play' && k !== 'folder' && k !== 'path') {
              newPairs.push(pairs[i]);
            }
          }
          var newSearch = newPairs.length > 0 ? ('?' + newPairs.join('&')) : '';
          window.location.href = window.location.pathname + newSearch;
        } else {
          // 若當前 URL 沒有 ?dir=，但使用者在輸入框中修改了內容，還原輸入框為系統原始設定
          var input = document.getElementById('custom-dir-input');
          if (input) {
            input.value = defaultDirs;
            if (typeof showToast === 'function') {
              showToast('已回復輸入框為系統原始設定：' + defaultDirs, 3500);
            }
          }
        }
      };

      function formatTime(sec) {
        const s = Math.floor(sec);
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const remSec = s % 60;
        if (h > 0) {
          return `${h}:${String(m).padStart(2, '0')}:${String(remSec).padStart(2, '0')}`;
        }
        return `${String(m).padStart(2, '0')}:${String(remSec).padStart(2, '0')}`;
      }

      function escapeHtml(str) {
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      // 13. 手機版側邊滑出選單 (Mobile Drawer) 控制核心
      window.openMobileDrawer = function () {
        const drawer = document.querySelector('.playlist-card');
        const overlay = document.getElementById('playlist-overlay');
        const fab = document.getElementById('mobile-drawer-fab');
        if (drawer) drawer.classList.add('drawer-open');
        if (overlay) overlay.classList.add('active');
        if (fab) fab.classList.add('drawer-opened');
        document.body.style.overflow = 'hidden';
      };

      window.closeMobileDrawer = function () {
        const drawer = document.querySelector('.playlist-card');
        const overlay = document.getElementById('playlist-overlay');
        const fab = document.getElementById('mobile-drawer-fab');
        if (drawer) drawer.classList.remove('drawer-open');
        if (overlay) overlay.classList.remove('active');
        if (fab) fab.classList.remove('drawer-opened');
        document.body.style.overflow = '';
      };

      window.toggleMobileDrawer = function () {
        const drawer = document.querySelector('.playlist-card');
        if (drawer && drawer.classList.contains('drawer-open')) {
          closeMobileDrawer();
        } else {
          openMobileDrawer();
        }
      };

      // 監聽鍵盤 ESC 關閉抽屜與電視投射助手
      window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          closeMobileDrawer();
          if (typeof closeTvCastModal === 'function') {
            closeTvCastModal();
          }
        }
      });

      // ── 手機版點擊導航路徑（麵包屑列或路徑標籤）自動帶出播放清單 ──
      const breadcrumbBarEl = document.getElementById('breadcrumb-bar');
      if (breadcrumbBarEl) {
        breadcrumbBarEl.addEventListener('click', function (e) {
          if (window.innerWidth <= 1024) {
            openMobileDrawer();
          }
        });
      }

      window.handlePathPillClick = function () {
        if (window.innerWidth <= 1024) {
          openMobileDrawer();
        }
      };

      // ── 手機版播放清單懸浮按鈕 (FAB) 智慧感知活動機制 ──
      // 畫面有操作/觸控/滾動時展開顯示，無操作 3.5 秒後自動收合為半透明迷你圖示
      let fabIdleTimer = null;
      const mobileFab = document.getElementById('mobile-drawer-fab');

      function wakeMobileFab() {
        if (!mobileFab) return;
        mobileFab.classList.add('active');
        if (fabIdleTimer) {
          clearTimeout(fabIdleTimer);
          fabIdleTimer = null;
        }
        fabIdleTimer = setTimeout(function () {
          if (mobileFab) {
            mobileFab.classList.remove('active');
          }
        }, 3500);
      }

      // 監聽全域操作（滾動、點擊、觸碰、滑動），有動作時立即喚醒展開 FAB
      ['scroll', 'touchstart', 'touchmove', 'mousemove', 'wheel'].forEach(function (evt) {
        window.addEventListener(evt, wakeMobileFab, { passive: true });
      });

      // 初始化時先維持展開 3.5 秒提示使用者，隨後自動轉為半透明迷你圖示
      wakeMobileFab();

      // 14. 雙向觸控滑動手勢核心 (Bidirectional Touch Swipe Gestures)
      // (1) 由右往左滑 (Right-to-Left) 帶出播放清單
      // (2) 由左往右滑 (Left-to-Right) 收合關閉播放清單
      let touchStartX = 0;
      let touchStartY = 0;
      let touchCurrentX = 0;
      let touchCurrentY = 0;
      let touchStartTime = 0;
      let isEdgeSwipeOpen = false;

      // 判斷是否由螢幕右側區域啟動手勢 (右側 100px 或右半部 35% 區域)
      function isRightSwipeZone(x) {
        return x >= (window.innerWidth - 100) || x >= (window.innerWidth * 0.65);
      }

      document.addEventListener('touchstart', (e) => {
        if (window.innerWidth > 1024) return;
        if (!e.touches || e.touches.length !== 1) return;

        // 避免干擾文字輸入或進度條拖曳
        const targetTag = e.target.tagName;
        if (targetTag === 'INPUT' || targetTag === 'TEXTAREA' || targetTag === 'SELECT') return;

        const touch = e.touches[0];
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
        touchCurrentX = touchStartX;
        touchCurrentY = touchStartY;
        touchStartTime = Date.now();

        const drawer = document.querySelector('.playlist-card');
        const isOpen = drawer && drawer.classList.contains('drawer-open');

        // 當抽屜未打開，且觸控點位於右側感應區時，啟用向左滑出監聽
        if (!isOpen && isRightSwipeZone(touchStartX)) {
          isEdgeSwipeOpen = true;
        } else {
          isEdgeSwipeOpen = false;
        }
      }, { passive: true });

      document.addEventListener('touchmove', (e) => {
        if (window.innerWidth > 1024 || !e.touches || e.touches.length !== 1) return;

        const touch = e.touches[0];
        touchCurrentX = touch.clientX;
        touchCurrentY = touch.clientY;

        // 若垂直滾動幅度明顯大於水平位移，視為上下瀏覽頁面，取消帶出手勢
        if (isEdgeSwipeOpen) {
          const deltaX = touchStartX - touchCurrentX;
          const deltaY = Math.abs(touchCurrentY - touchStartY);
          if (deltaY > 40 && deltaY > deltaX) {
            isEdgeSwipeOpen = false;
          }
        }
      }, { passive: true });

      document.addEventListener('touchend', (e) => {
        if (window.innerWidth > 1024) return;

        const deltaX = touchStartX - touchCurrentX; // 正值表示向左滑動
        const deltaY = Math.abs(touchCurrentY - touchStartY);
        const elapsed = Date.now() - touchStartTime;

        const drawer = document.querySelector('.playlist-card');
        const isOpen = drawer && drawer.classList.contains('drawer-open');

        // 動作 A：由右往左滑動 -> 滑出播放清單
        if (!isOpen && isEdgeSwipeOpen) {
          // (a) 水平向左位移達 40px 且大於垂直位移，或者快速輕拂 (flick) 達 25px
          if ((deltaX > 40 && deltaX > deltaY) || (deltaX > 25 && elapsed < 280 && deltaX > deltaY)) {
            openMobileDrawer();
          }
        }

        // 動作 B：抽屜已開啟時，由左往右滑動 (-deltaX > 50) -> 收合播放清單
        if (isOpen) {
          const swipeRightDist = touchCurrentX - touchStartX;
          if (swipeRightDist > 50 && swipeRightDist > deltaY) {
            closeMobileDrawer();
          }
        }

        // 重設狀態
        touchStartX = 0;
        touchStartY = 0;
        touchCurrentX = 0;
        touchCurrentY = 0;
        isEdgeSwipeOpen = false;
      }, { passive: true });

      // 初次啟動渲染：優先處理直連分享播放 (URL ?play= 或 ?v=) 或目錄分享 (?folder=, ?dir=, ?path=)，其次還原上次瀏覽位置
      (function initPageNav() {
        var directPlay = '';
        var directFolder = '';
        try {
          var urlParams = new URLSearchParams(window.location.search);
          directPlay = urlParams.get('play') || urlParams.get('v') || '';
          // 注意：'dir' 為「自訂路徑測試」專用的伺服器端 override 參數（見 video_core.php），
          // 語意上與此處的資料夾深連結完全不同，不可混用，否則會被誤判為子路徑導致目錄讀取失敗。
          directFolder = urlParams.get('folder') || urlParams.get('path') || '';
        } catch(e) {}

        if (directPlay) {
          var cleanPlay = directPlay.replace(/\\/g, '/').replace(/^\/+/, '');
          var targetFolder = '';
          var lastSlash = cleanPlay.lastIndexOf('/');
          if (lastSlash !== -1) {
            targetFolder = cleanPlay.substring(0, lastSlash);
          } else {
            // 若只有檔名，檢查 initialRecentData 中是否有該影片以反查其資料夾
            if (initialRecentData && initialRecentData.videos) {
              var rMatch = initialRecentData.videos.find(function (v) {
                var vn = (v.rel_path || '').replace(/\\/g, '/');
                return vn.toLowerCase().endsWith('/' + cleanPlay.toLowerCase()) || vn.toLowerCase() === cleanPlay.toLowerCase();
              });
              if (rMatch && rMatch.rel_path) {
                var rNorm = rMatch.rel_path.replace(/\\/g, '/');
                var rSlash = rNorm.lastIndexOf('/');
                if (rSlash !== -1) {
                  targetFolder = rNorm.substring(0, rSlash);
                }
              }
            }
          }

          window._pendingPlayRelPath = cleanPlay;
          isRecentMode = false;
          updateNavButtonsUI();

          var titleText = document.getElementById('playlist-title-text');
          if (titleText) titleText.textContent = '🎬 影音目錄瀏覽';

          if (targetFolder === '' && initialData && initialData.success) {
            applyFolderData(initialData);
          } else {
            navigateToPath(targetFolder).catch(function() {
              navigateToPath('');
            });
          }
          return;
        }

        // 處理目錄分享連結 (?folder=, ?dir=, ?path=)，直接載入並展開該目錄
        if (directFolder !== null && directFolder !== undefined && directFolder !== '') {
          var cleanFolder = directFolder.replace(/\\/g, '/').replace(/^\/+/, '').replace(/\/+$/, '');
          isRecentMode = false;
          updateNavButtonsUI();

          var folderTitleText = document.getElementById('playlist-title-text');
          if (folderTitleText) folderTitleText.textContent = '🎬 影音目錄瀏覽';

          if (cleanFolder === '' && initialData && initialData.success) {
            applyFolderData(initialData);
            if (window.innerWidth <= 1024) {
              openMobileDrawer();
            }
            showToast('📁 已載入分享目錄：根目錄');
          } else {
            navigateToPath(cleanFolder).then(function() {
              if (window.innerWidth <= 1024) {
                openMobileDrawer();
              }
              showToast('📁 已載入分享目錄：' + cleanFolder);
            }).catch(function() {
              navigateToPath('');
            });
          }
          return;
        }

        let savedNav = '';
        try { savedNav = localStorage.getItem('vid_last_nav') || ''; } catch(e) {}

        if (savedNav === '__RECENT__' || savedNav === '') {
          // 還原「最新影音」模式（或無記錄時的預設行為）
          if (initialRecentData && initialRecentData.success && initialRecentData.videos && initialRecentData.videos.length > 0) {
            applyRecentData(initialRecentData);
          } else if (initialData && initialData.success) {
            isRecentMode = false;
            updateNavButtonsUI();
            applyFolderData(initialData);
          }
        } else {
          // 還原上次所在的資料夾
          isRecentMode = false;
          updateNavButtonsUI();
          const titleText = document.getElementById('playlist-title-text');
          if (titleText) titleText.textContent = '🎬 影音目錄瀏覽';

          // 若根目錄已在 initialData 快取中直接套用，否則 AJAX 載入
          if (savedNav === '' && initialData && initialData.success) {
            applyFolderData(initialData);
          } else {
            // 先渲染根目錄占位，再導航至目標（讓 breadcrumb 正確顯示）
            navigateToPath(savedNav).catch(function() {
              // 若路徑失效（資料夾已刪除），fallback 至根目錄
              try { localStorage.removeItem('vid_last_nav'); } catch(e2) {}
              navigateToPath('');
            });
          }
        }
      }());

      // 初始化還原上次選擇之播放倍速
      try {
        var savedRate = localStorage.getItem('vid_playback_rate');
        if (savedRate) {
          var selSpd = document.getElementById('speed-select');
          if (selSpd) selSpd.value = savedRate;
          if (videoPlayer) videoPlayer.playbackRate = parseFloat(savedRate) || 1.0;
        }
      } catch(e) {}


      // ── 頂部導航列動態排版守護者：空間不足時自動隱藏次要統計與預覽，永不折行 ──
      function checkTopbarSpace() {
        var topbar = document.querySelector('.topbar');
        if (!topbar) return;

        // 暫時移除動態隱藏 class 以測量原始所需自然寬度
        topbar.classList.remove('hide-stat', 'hide-preview');

        // 若發生水平溢出，優先隱藏「檔案數與容量」統計區塊
        if (topbar.scrollWidth > topbar.clientWidth + 1) {
          topbar.classList.add('hide-stat');
        }
        // 若隱藏統計後仍發生水平溢出，進一步隱藏預覽時長 slider
        if (topbar.scrollWidth > topbar.clientWidth + 1) {
          topbar.classList.add('hide-preview');
        }
      }

      window.addEventListener('resize', checkTopbarSpace);
      if (window.ResizeObserver) {
        try {
          var ro = new ResizeObserver(function () {
            checkTopbarSpace();
          });
          var tb = document.querySelector('.topbar');
          if (tb) ro.observe(tb);
        } catch (e) {}
      }
      setTimeout(checkTopbarSpace, 50);

      // 監聽播放器錯誤事件，提示使用者查看排查技巧
      if (videoPlayer) {
        videoPlayer.addEventListener('error', function () {
          showToast('⚠️ 影片載入失敗，可點擊「設定與技巧」檢視伺服器配置與排查指南。', 4500);
        });
      }

      // 初始化狀態：若當前無影片正在播放，自動展示伺服器設定說明與技巧指南
      if (!currentVideo) {
        showServerGuide();
      }

    })();
  </script>

  <!-- ════════════════════════════════════════════════════════
       📺 智慧電視 / Google TV 播放助手彈窗 (TV Cast Modal)
       ════════════════════════════════════════════════════════ -->
  <div id="tv-cast-modal-backdrop" class="tv-cast-backdrop" onclick="closeTvCastModal()"></div>
  <div id="tv-cast-modal" class="tv-cast-modal" role="dialog" aria-labelledby="tv-cast-modal-title" aria-hidden="true">
    <div class="tv-cast-header">
      <div class="tv-cast-title-wrap">
        <div class="tv-cast-title" id="tv-cast-modal-title">📺 智慧電視 / Google TV 播放助手</div>
        <div class="tv-cast-subtitle" id="tv-cast-video-name">未選擇影片</div>
      </div>
      <button type="button" class="tv-cast-close-btn" onclick="closeTvCastModal()" aria-label="關閉彈窗">×</button>
    </div>

    <div class="tv-cast-body">
      <!-- Localhost 警示提示 -->
      <div id="tv-cast-localhost-warn" style="display:none;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#fca5a5;padding:8px 12px;border-radius:6px;font-size:0.75rem;">
        ⚠️ <b>注意</b>：您目前使用 <code>localhost</code> 網址瀏覽。Google TV 設備無法解析本機 localhost，請使用<b>區域網路 IP</b> (例如 <code>http://192.168.X.X:...</code>) 連入本站以順暢投放！
      </div>

      <!-- 方案一：瀏覽器直接投影 (W3C Remote Playback / AirPlay) -->
      <div class="tv-cast-section">
        <div class="tv-cast-section-header">
          <span class="tv-cast-badge chrome">標準協定</span>
          <h3 class="tv-cast-section-title">方式一：直接透過瀏覽器投放 (Chromecast / AirPlay)</h3>
        </div>
        <p class="tv-cast-desc">
          使用裝置內建原生投影功能，將當前播放畫面同步投放至 Google TV、Chromecast 或 Apple TV。
        </p>
        <button type="button" class="tv-cast-action-btn primary-cast-btn" onclick="triggerNativeCastPrompt()">
          <span>📡</span> <span>尋找投影裝置 (開啟投射選單)</span>
        </button>
        <div class="tv-cast-tip">
          💡 <b>小提示</b>：Chromecast 原生解碼支援 MP4 (H.264/AAC)。若影片為 MKV、4K 或包含特殊音訊 (DTS/AC3) 導致無聲或黑畫面，強烈建議使用下方【方式二】以獲得最佳畫質與 100% 電視硬解！
        </div>

        <!-- 找不到裝置排查指引 (針對小米手機與 PC Edge) -->
        <details class="tv-cast-troubleshoot">
          <summary>🔍 小米手機 Chrome / PC Edge 找不到 Google TV 解決方法 (點此展開)</summary>
          <div class="troubleshoot-content">
            <p><b>📱 小米手機 Chrome (HyperOS / Android)：</b></p>
            <ul>
              <li><b>開啟「鄰近裝置」權限（最關鍵！）</b>：Android 13+ 與小米 HyperOS 預設封鎖 Chrome 掃描區域網路。請進入手機「設定」>「應用程式設定」>「應用程式管理」>「Chrome」>「權限管理」> 將<b>「鄰近裝置」</b>設為「允許」。</li>
              <li><b>同一 Wi-Fi 與頻段</b>：確認手機與 Google TV 連接同一台分享器（勿使用有訪客隔離的 Guest Wi-Fi）。</li>
              <li><b>直接使用 VLC 投影</b>：點擊下方<b>【以 VLC 開啟】</b>，在播放畫面右上角點「投影」圖示，VLC 自帶 Google Cast 引擎，突破瀏覽器權限限制！</li>
            </ul>
            <p style="margin-top:6px;"><b>💻 PC Edge / Chrome 瀏覽器：</b></p>
            <ul>
              <li><b>開啟全 IP 投影 Flag</b>：在 Edge 網址列貼上 <code>edge://flags/#media-router-cast-allow-all-ips</code>，將其設為 <b>Enabled</b> 並重啟 Edge。</li>
              <li><b>Windows 防火牆設定</b>：確認連線為「<b>私人網路</b>」，「公用網路」會阻擋 mDNS (UDP 5353) 設備搜尋。</li>
              <li><b>瀏覽器選單投放</b>：亦可點擊 Edge 右上角「…」>「更多工具」>「將媒體投放至裝置」。</li>
            </ul>
          </div>
        </details>
      </div>

      <!-- 方案二：電視 APP 晶片硬解 (Nova / VLC / 外部播放器) -->
      <div class="tv-cast-section highlight">
        <div class="tv-cast-section-header">
          <span class="tv-cast-badge recommend">推薦首選</span>
          <h3 class="tv-cast-section-title">方式二：電視 APP 播放 (100% 硬體解碼 / 零 NAS 負擔)</h3>
        </div>
        <p class="tv-cast-desc">
          在 Google TV 上以專業播放器開啟，支援 4K、HDR、多音軌切換 (DTS/TrueHD) 與內嵌/外掛字幕，完全不消耗 NAS CPU。
        </p>

        <!-- 快速啟動按鈕 (手機/平板/連動) -->
        <div class="tv-cast-app-grid">
          <a id="cast-vlc-link" href="#" class="tv-app-btn vlc" title="以 VLC 播放器開啟串流（支援在 VLC 內點擊投影圖示投射至 Google TV）">
            <span class="app-icon">🟠</span>
            <span class="app-info">
              <span class="app-name">以 VLC 開啟</span>
              <span class="app-sub">自帶 Chromecast 投射</span>
            </span>
          </a>
          <a id="cast-wvc-link" href="#" class="tv-app-btn wvc" title="以 Web Video Caster 開啟串流投放">
            <span class="app-icon">📺</span>
            <span class="app-info">
              <span class="app-name">以 Web Video Caster 開啟</span>
              <span class="app-sub">Android 投屏神器</span>
            </span>
          </a>
          <a id="cast-nplayer-link" href="#" class="tv-app-btn nplayer" title="以 nPlayer 播放器開啟串流">
            <span class="app-icon">🔵</span>
            <span class="app-info">
              <span class="app-name">以 nPlayer 開啟</span>
              <span class="app-sub">iOS / Android 專業播放</span>
            </span>
          </a>
        </div>

        <!-- 串流網址複製區塊 -->
        <div class="tv-cast-url-box">
          <label class="tv-url-label" for="tv-stream-url-input">
            <span>🔗 電視串流網址 (已自動附加免登入 Token 與相容標頭)</span>
          </label>
          <div class="tv-url-input-group">
            <input type="text" id="tv-stream-url-input" class="tv-url-input" readonly spellcheck="false" onclick="this.select()">
            <button type="button" class="tv-copy-btn" id="btn-copy-tv-stream" onclick="copyCastStreamUrl()">
              <span class="copy-icon">📋</span> <span class="copy-text">複製串流網址</span>
            </button>
            <button type="button" class="tv-copy-btn tv-qr-btn" onclick="openTvCastQr('stream')" title="產製 QR Code 方便手機/電視掃描">
              <span class="copy-icon">📱</span> <span class="copy-text">QR Code</span>
            </button>
          </div>
        </div>

        <!-- 網頁直連網址複製區塊 (供電視瀏覽器) -->
        <div class="tv-cast-url-box" style="margin-top: 6px;">
          <label class="tv-url-label" for="tv-web-url-input">
            <span>🌐 電視瀏覽器網址 (若電視安裝了 Chrome / Silk 瀏覽器)</span>
          </label>
          <div class="tv-url-input-group">
            <input type="text" id="tv-web-url-input" class="tv-url-input" readonly spellcheck="false" onclick="this.select()">
            <button type="button" class="tv-copy-btn" id="btn-copy-tv-web" onclick="copyCastWebUrl()">
              <span class="copy-icon">📋</span> <span class="copy-text">複製網頁網址</span>
            </button>
            <button type="button" class="tv-copy-btn tv-qr-btn" onclick="openTvCastQr('web')" title="產製 QR Code 方便手機/電視掃描">
              <span class="copy-icon">📱</span> <span class="copy-text">QR Code</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 方案三：Google TV 使用教學指南 -->
      <div class="tv-cast-guide">
        <div class="tv-guide-title">📖 Google TV 最佳使用體驗建議</div>
        <ol class="tv-guide-list">
          <li><b>安裝 Nova Video Player (最推薦)</b>：在 Google TV 內建 Google Play 搜尋並安裝免費的 <code>Nova Video Player</code>，在選單選擇「網路串流」，貼上上方串流網址即可流暢觀看。</li>
          <li><b>安裝 VLC for Android TV</b>：於 Google Play 搜尋安裝 <code>VLC</code>，點選側邊欄「串流 (Streams)」貼上網址即可。</li>
          <li><b>免安裝投影</b>：若為 MP4 格式，直接點選本頁上方「尋找投影裝置」或播放器工具列上的「📺 投放」按鈕即可無線投射。</li>
        </ol>
      </div>
    </div>
  </div>

  <!-- ══ QR Code 分享彈窗模組 ══ -->
  <div id="qr-modal-backdrop" class="qr-modal-backdrop" onclick="closeQrModal()"></div>
  <div id="qr-modal" class="qr-modal" role="dialog" aria-modal="true" aria-labelledby="qr-modal-title">
    <div class="qr-modal-header">
      <div class="qr-modal-title-wrap">
        <span class="qr-modal-icon">📱</span>
        <div class="qr-modal-title" id="qr-modal-title">分享 QR Code 與連結</div>
      </div>
      <button type="button" class="qr-modal-close" onclick="closeQrModal()" aria-label="關閉">×</button>
    </div>
    <div class="qr-modal-body">
      <div class="qr-target-badge" id="qr-target-badge">🎬 影片標題</div>
      <div class="qr-code-wrapper">
        <canvas id="qr-canvas" style="display:none;"></canvas>
        <img id="qr-img" class="qr-code-img" alt="QR Code" title="長按或右鍵可另存/複製圖片">
      </div>
      <div class="qr-scan-hint">📷 手機打開相機或 LINE 掃描即可直接開啟觀看</div>
      <div class="qr-url-box">
        <input type="text" id="qr-url-input" class="qr-url-input" readonly spellcheck="false" onclick="this.select()">
      </div>
      <div class="qr-action-grid">
        <button type="button" class="qr-btn primary" id="btn-qr-copy-url" onclick="copyQrModalUrl()">
          <span class="qr-btn-icon">📋</span> <span id="qr-copy-url-text">複製網址</span>
        </button>
        <button type="button" class="qr-btn" id="btn-qr-download-img" onclick="downloadQrImage()">
          <span class="qr-btn-icon">💾</span> <span>下載圖片</span>
        </button>
        <button type="button" class="qr-btn" id="btn-qr-copy-img" onclick="copyQrImage()">
          <span class="qr-btn-icon">🖼️</span> <span>複製圖片</span>
        </button>
      </div>

      <!-- 社群快速傳送分享 (LINE / FB / 系統原生分享 - 傳送 QR Code 圖片) -->
      <div class="qr-share-divider">
        <span>社群傳送 QR Code 圖片</span>
      </div>
      <div class="qr-social-grid">
        <button type="button" class="qr-social-btn line" onclick="shareToLine()" title="傳送 QR Code 圖片至 LINE (自動呼叫分享面板或複製圖片)">
          <svg class="social-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M21 11.25C21 6.141 16.97 2 12 2S3 6.141 3 11.25c0 4.568 3.23 8.375 7.594 9.102.333.072.785.22.9.504.103.258.067.66.033.92l-.146.877c-.044.267-.208 1.042.913.568 1.12-.473 6.044-3.56 8.246-6.096C20.69 15.42 21 13.435 21 11.25z" fill="#06C755"/>
            <path d="M9.07 9.3v3.9h1.95v.9H8.07V9.3h1zm2.34 0h1v4.8h-1V9.3zm4.5 4.8h-1l-1.84-2.73v2.73h-1V9.3h1l1.84 2.73V9.3h1v4.8zm2.39-3.9h1.85v.9h-1.85v1.05h1.95v.9H17.3V9.3h2.95v.9h-1.95V10.2z" fill="#FFFFFF"/>
          </svg>
          <span>LINE</span>
        </button>
        <button type="button" class="qr-social-btn fb" onclick="shareToFacebook()" title="傳送 QR Code 圖片至 Facebook">
          <svg class="social-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
          </svg>
          <span>FB</span>
        </button>
        <button type="button" class="qr-social-btn native" id="btn-qr-native-share" onclick="shareViaNative()" title="呼叫手機系統原生分享面板傳送 QR Code 圖片 (LINE / 訊息 / AirDrop 等)">
          <span class="social-icon-emoji">📤</span>
          <span>系統分享</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ══ Hover 懸停預覽 / 點擊彈窗 ══ -->
  <!-- 彈窗遮罩背景（點選背景即關閉預覽） -->
  <div id="pp-backdrop" class="pp-backdrop" onclick="event.stopPropagation(); window._ppStop && window._ppStop();"></div>
  <div id="preview-popup" class="preview-popup">
    <div class="pp-video-wrap" onclick="window._ppPlayCurrent && window._ppPlayCurrent();" title="點擊直接前往此目錄播放">
      <video id="preview-video" muted playsinline preload="metadata" style="aspect-ratio:16/9;"></video>
      <div class="pp-hover-play-hint">
        <span class="pp-hover-play-badge">▶ 前往目錄播放</span>
      </div>
      <div class="pp-loading-overlay">
        <div class="pp-spinner"></div>
        <span class="pp-loading-text">附近畫面載入中…</span>
      </div>
      <!-- 關閉按鈕（彈窗模式下顯示） -->
      <button class="pp-close-btn" onclick="event.stopPropagation(); window._ppStop && window._ppStop();" aria-label="關閉預覽">×</button>
    </div>
    <div class="pp-footer">
      <div style="flex:1;min-width:0;">
        <div class="pp-title" id="pp-title">-</div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin:2px 0 3px;">
          <div class="pp-folder-hint" id="pp-folder-hint" style="margin:0;"></div>
          <span class="pp-label" id="pp-duration-label" style="flex-shrink:0;">10秒預覽</span>
        </div>
        <div class="pp-progress-track">
          <div class="pp-progress-fill" id="pp-progress-fill"></div>
        </div>
      </div>
      <button type="button" class="pp-play-btn" id="pp-play-btn" onclick="event.stopPropagation(); window._ppPlayCurrent && window._ppPlayCurrent();" title="立即前往此影片所在的資料夾並開始播放">
        <span class="pp-play-icon">▶</span> 前往播放
      </button>
    </div>
  </div>


  <script>
  // ════════════════════════════════════════════════════════
  //  PreviewManager — Hover 影片預覽模組
  //  特性：
  //    • 桌面：滑鼠懸停 250ms 後浮現游標跟隨 16:9 預覽小視窗
  //    • 行動端 / 點擊：點擊 👁 按鈕展開置中彈窗（支援背景遮罩與關閉）
  //    • 隨機 seek 到影片長度 15%~75% 之間精彩片段
  //    • 預覽時長由頂端 UI 設定（5~30s），預設 10s
  //    • 具備載入逾時智慧 fallback 與子標籤游標穿透保護
  //    • 音訊檔案不觸發預覽
  // ════════════════════════════════════════════════════════
  (function() {
    var DEBOUNCE_MS   = 250;   // hover 等候時間（兼顧靈敏與防抖）
    var SEEK_TIMEOUT  = 2200;  // seek 超時 fallback (ms)
    var POPUP_W       = 320;   // 彈窗寬度 (px)

    // 從 localStorage 讀取預覽秒數，預設 10s
    var PREVIEW_SEC = 10;
    (function initPreviewSec() {
      var saved = parseInt(localStorage.getItem('vid_preview_sec') || '10', 10);
      if (!isNaN(saved) && saved >= 5 && saved <= 30) PREVIEW_SEC = saved;
      var slider = document.getElementById('preview-sec-slider');
      var label  = document.getElementById('preview-sec-label');
      var ppLabel = document.getElementById('pp-duration-label');
      if (slider) slider.value = PREVIEW_SEC;
      if (label)  label.textContent = PREVIEW_SEC + 's';
      if (ppLabel) ppLabel.textContent = PREVIEW_SEC + '秒預覽';
    }());

    // 供頂端 UI 呼叫
    window.updatePreviewSec = function(val) {
      var n = Math.max(5, Math.min(30, parseInt(val, 10)));
      if (isNaN(n)) return;
      PREVIEW_SEC = n;
      try { localStorage.setItem('vid_preview_sec', n); } catch(e) {}
      var label   = document.getElementById('preview-sec-label');
      var ppLabel = document.getElementById('pp-duration-label');
      if (label)   label.textContent = n + 's';
      if (ppLabel) ppLabel.textContent = n + '秒預覽';
    };

    var popup     = document.getElementById('preview-popup');
    var pvVideo   = document.getElementById('preview-video');
    var pvTitle   = document.getElementById('pp-title');
    var pvFolder  = document.getElementById('pp-folder-hint');
    var pvFill    = document.getElementById('pp-progress-fill');
    var pvPlayBtn = document.getElementById('pp-play-btn');
    var backdrop  = document.getElementById('pp-backdrop');

    if (!popup || !pvVideo) return;

    // 狀態變數
    var debounceTimer         = null;
    var seekTimer             = null;
    var progressRaf           = null;
    var leaveTimer            = null;      // 滑鼠離開清單項目至預覽彈窗的緩衝計時器
    var previewStart          = 0;
    var isActive              = false;
    var isHoveringPopup       = false;     // 滑鼠游標是否正懸停於預覽彈窗內部
    var isPosLocked           = false;     // 位置鎖定停頓旗標（提供約 1 秒鎖定讓使用者能滑入點擊）
    var posLockTimer          = null;      // 位置鎖定計時器
    var anchorX               = 0;         // 彈窗定位基準 X 座標
    var anchorY               = 0;         // 彈窗定位基準 Y 座標
    var currentMode           = 'hover';   // 'hover' | 'modal'
    var currentItem           = null;
    var currentRelPath        = '';
    var activePreviewVideoRel = '';        // 當前預覽影片相對路徑 (rel_path)
    var activePreviewVideoTitle = '';
    var previewSeq            = 0;         // 防止非同步請求競爭序號
    var mouseX                = 0;
    var mouseY                = 0;

    function lockPosition(ms) {
      isPosLocked = true;
      clearTimeout(posLockTimer);
      posLockTimer = setTimeout(function() {
        isPosLocked = false;
      }, ms || 1000);
    }

    // ── 游標位置跟隨計算 ──────────────────────────────────────────
    function positionPopup(force) {
      if (currentMode === 'modal') {
        popup.style.left = '';
        popup.style.top  = '';
        popup.classList.remove('pp-flip-left');
        return;
      }
      // 若滑鼠已經移入預覽彈窗本體，或位置正處於 1 秒停頓鎖定保護期（非強制更新），絕不移動位置
      if (!force && (isHoveringPopup || isPosLocked)) return;

      var ph = popup.offsetHeight || 220;
      var vw = window.innerWidth;
      var vh = window.innerHeight;
      var x = mouseX + 24;
      var y = mouseY - ph / 2;
      var flipped = false;
      if (x + POPUP_W + 16 > vw) {
        x = mouseX - POPUP_W - 24;
        flipped = true;
      }
      if (x < 10) x = 10;
      y = Math.max(12, Math.min(y, vh - ph - 12));
      popup.style.left = Math.round(x) + 'px';
      popup.style.top  = Math.round(y) + 'px';
      if (flipped) {
        popup.classList.add('pp-flip-left');
      } else {
        popup.classList.remove('pp-flip-left');
      }
      anchorX = mouseX;
      anchorY = mouseY;
    }

    // ── 播放進度條動畫 ──────────────────────────────────────────
    function tickProgress() {
      if (!isActive) return;
      var elapsed = (pvVideo.currentTime || 0) - previewStart;
      if (elapsed < 0) elapsed = 0;
      var pct = Math.min(100, (elapsed / PREVIEW_SEC) * 100);
      if (pvFill) pvFill.style.width = pct + '%';
      if (elapsed >= PREVIEW_SEC) {
        stopPreview();
        return;
      }
      progressRaf = requestAnimationFrame(tickProgress);
    }

    // ── 啟動播放管線 ──────────────────────────────────────────
    function startPlayback(thisSeq) {
      if (!isActive || thisSeq !== previewSeq) return;
      clearTimeout(seekTimer);
      seekTimer = null;
      popup.classList.remove('pp-loading');

      var playPromise = pvVideo.play();
      if (playPromise !== undefined && typeof playPromise.catch === 'function') {
        playPromise.catch(function(err) {
          // 靜音自動播放降級保護
          console.warn('預覽自動播放受限:', err);
        });
      }
      previewStart = pvVideo.currentTime || 0;
      cancelAnimationFrame(progressRaf);
      progressRaf = requestAnimationFrame(tickProgress);
    }

    // ── 顯示預覽視窗 ──────────────────────────────────────────
    function showPopup(title, isModal) {
      currentMode = isModal ? 'modal' : 'hover';
      isHoveringPopup = false;
      activePreviewVideoRel = '';
      if (pvTitle) {
        pvTitle.textContent = title || '';
        pvTitle.title = title || '';
      }
      if (pvFolder) {
        pvFolder.textContent = '';
        pvFolder.title = '';
      }
      if (pvFill)    pvFill.style.width = '0%';
      if (pvPlayBtn) {
        pvPlayBtn.disabled = true;
        pvPlayBtn.style.opacity = '0.5';
      }

      popup.style.display = 'block';

      if (currentMode === 'modal') {
        popup.classList.add('pp-modal');
        popup.style.pointerEvents = 'auto';
        if (backdrop) {
          backdrop.style.display = 'block';
          backdrop.style.pointerEvents = 'auto';
          void backdrop.offsetHeight;
          backdrop.classList.add('pp-visible');
        }
      } else {
        popup.classList.remove('pp-modal');
        popup.style.pointerEvents = 'auto';
        if (backdrop) {
          backdrop.classList.remove('pp-visible');
          backdrop.style.pointerEvents = 'none';
          backdrop.style.display = 'none';
        }
        // 強制定錨於當前游標位置，並立即啟動 1 秒位置停頓鎖定，讓使用者能從容滑入點擊
        positionPopup(true);
        lockPosition(1000);
      }

      popup.classList.add('pp-loading');
      popup.classList.add('pp-visible');
      isActive = true;
    }

    // ── 停止 / 清除 ─────────────────────────────────────────
    function stopPreview() {
      isActive = false;
      isHoveringPopup = false;
      isPosLocked = false;
      clearTimeout(posLockTimer);
      posLockTimer = null;
      currentItem = null;
      currentRelPath = '';
      activePreviewVideoRel = '';
      activePreviewVideoTitle = '';
      previewSeq++;

      clearTimeout(debounceTimer);
      debounceTimer = null;
      clearTimeout(seekTimer);
      seekTimer = null;
      clearTimeout(leaveTimer);
      leaveTimer = null;
      cancelAnimationFrame(progressRaf);

      // 立刻停用點擊阻擋並徹底自畫面渲染樹中隱藏，絕不影響下層清單項目點擊
      popup.classList.remove('pp-visible', 'pp-loading', 'pp-modal', 'pp-flip-left');
      popup.style.display = 'none';
      popup.style.pointerEvents = 'none';
      if (pvTitle) {
        pvTitle.textContent = '-';
        pvTitle.title = '';
      }
      if (pvFolder) {
        pvFolder.textContent = '';
        pvFolder.title = '';
      }

      if (backdrop) {
        backdrop.classList.remove('pp-visible');
        backdrop.style.display = 'none';
        backdrop.style.pointerEvents = 'none';
      }

      var thisSeq = previewSeq;
      setTimeout(function() {
        if (!isActive && thisSeq === previewSeq) {
          try {
            pvVideo.pause();
            pvVideo.removeAttribute('src');
            pvVideo.load();
          } catch(e) {}
        }
      }, 150);
    }

    window._ppStop = stopPreview;

    // ── 隨機 seek 點計算 ─────────────────────────────────────
    function randomSeek(duration) {
      if (!duration || isNaN(duration) || duration < 20) return 0;
      var pct = 0.15 + Math.random() * 0.60;
      return duration * pct;
    }

    // ── 播放影片來源管線 ───────────────────────────────────────
    function playVideoSource(relPath, title, thisSeq) {
      if (!isActive || thisSeq !== previewSeq) return;

      activePreviewVideoRel = relPath;
      activePreviewVideoTitle = title || '';

      if (pvTitle) {
        pvTitle.textContent = title || '';
        pvTitle.title = title || '';
      }

      // 解析並更新目錄提示 (Folder hint)
      if (pvFolder) {
        var cleanRel = (relPath || '').replace(/\\/g, '/');
        var slashIdx = cleanRel.lastIndexOf('/');
        var folderPart = slashIdx !== -1 ? cleanRel.substring(0, slashIdx) : '';
        pvFolder.textContent = '📁 ' + (folderPart || '根目錄');
        pvFolder.title = folderPart ? ('目錄：' + folderPart) : '根目錄';
      }

      if (pvPlayBtn) {
        pvPlayBtn.disabled = false;
        pvPlayBtn.style.opacity = '1';
      }

      var src = (typeof window.buildStreamUrl === 'function')
        ? window.buildStreamUrl(relPath)
        : ('index.php?action=stream&file=' + encodeURIComponent(relPath));

      pvVideo.muted = true;
      pvVideo.volume = 0;
      pvVideo.preload = 'metadata';

      var metaHandled = false;

      function onMeta() {
        if (!isActive || thisSeq !== previewSeq || metaHandled) return;
        metaHandled = true;
        var dur = pvVideo.duration;
        var seekTo = (!isNaN(dur) && isFinite(dur) && dur > 0) ? randomSeek(dur) : 0;
        previewStart = seekTo;

        // 設定逾時保護：若 seek / 載入超過 2.2 秒未完成，直接強行解除 loading 開始播放現有緩衝
        seekTimer = setTimeout(function() {
          if (isActive && thisSeq === previewSeq) {
            startPlayback(thisSeq);
          }
        }, SEEK_TIMEOUT);

        // 若不需要跳轉或當前已處於目標位置，直接啟動播放
        if (seekTo <= 0.1 || Math.abs(pvVideo.currentTime - seekTo) < 0.2) {
          startPlayback(thisSeq);
        } else {
          var onSeeked = function() {
            pvVideo.removeEventListener('seeked', onSeeked);
            startPlayback(thisSeq);
          };
          pvVideo.addEventListener('seeked', onSeeked, { once: true });
          try {
            pvVideo.currentTime = seekTo;
          } catch(e) {
            startPlayback(thisSeq);
          }
        }
      }

      pvVideo.addEventListener('loadedmetadata', onMeta, { once: true });
      pvVideo.addEventListener('canplay', function onCanPlay() {
        if (isActive && thisSeq === previewSeq && popup.classList.contains('pp-loading') && !metaHandled) {
          onMeta();
        }
      }, { once: true });

      pvVideo.src = src;
      pvVideo.load();
    }

    // ── 執行影片預覽 ───────────────────────────────────────────
    function doPreview(relPath, title, isModal) {
      if (!relPath) return;
      if (isActive && currentRelPath === relPath && ((isModal && currentMode === 'modal') || (!isModal && currentMode === 'hover'))) {
        return; // 已經在播放同一支影片且模式相同
      }

      previewSeq++;
      var thisSeq = previewSeq;
      currentRelPath = relPath;

      clearTimeout(seekTimer);
      cancelAnimationFrame(progressRaf);

      showPopup(title, isModal);
      playVideoSource(relPath, title, thisSeq);
    }

    // ── 執行資料夾隨機影片預覽 ─────────────────────────────────
    function doFolderPreview(folderRel, folderName, isModal) {
      if (!folderRel && folderRel !== '') return;
      if (isActive && currentRelPath === folderRel && ((isModal && currentMode === 'modal') || (!isModal && currentMode === 'hover'))) {
        return;
      }

      previewSeq++;
      var thisSeq = previewSeq;
      currentRelPath = folderRel;

      clearTimeout(seekTimer);
      cancelAnimationFrame(progressRaf);

      var folderLabel = folderName || '資料夾';
      showPopup('🎬 選取隨機影片中… (📁 ' + folderLabel + ')', isModal);

      // 1. 優先檢查前端快取中是否有該資料夾的直屬影片
      var cachedVideos = (typeof window.getFolderCachedVideos === 'function')
        ? window.getFolderCachedVideos(folderRel)
        : null;

      if (cachedVideos && cachedVideos.length > 0) {
        var vids = [];
        for (var i = 0; i < cachedVideos.length; i++) {
          if (!cachedVideos[i].is_audio) vids.push(cachedVideos[i]);
        }
        if (vids.length > 0) {
          var pick = vids[Math.floor(Math.random() * vids.length)];
          var pickTitle = pick.title || pick.filename;
          var dispTitle = '🎬 ' + pickTitle + ' (📁 ' + folderLabel + ' 隨機預覽)';
          playVideoSource(pick.rel_path, dispTitle, thisSeq);
          return;
        }
      }

      // 2. 呼叫後端 API 隨機挑選該資料夾下一支影片
      var apiUrl = 'index.php?action=random_video&folder=' + encodeURIComponent(folderRel);
      if (window.AUTH_TOKEN) {
        apiUrl += '&token=' + encodeURIComponent(window.AUTH_TOKEN);
      }

      fetch(apiUrl)
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (!isActive || thisSeq !== previewSeq) return;
          if (data && data.success && data.video && data.video.rel_path) {
            var vidTitle = data.video.title || data.video.filename;
            var fullTitle = '🎬 ' + vidTitle + ' (📁 ' + folderLabel + ' 隨機預覽)';
            playVideoSource(data.video.rel_path, fullTitle, thisSeq);
          } else {
            // 資料夾內無可預覽影片
            if (pvTitle) {
              pvTitle.textContent = '📁 ' + folderLabel + ' (無可預覽影片)';
              pvTitle.title = '📁 ' + folderLabel + ' (無可預覽影片)';
            }
            popup.classList.remove('pp-loading');
            if (!isModal) {
              setTimeout(function() {
                if (isActive && thisSeq === previewSeq && currentMode === 'hover') {
                  stopPreview();
                }
              }, 1800);
            }
          }
        })
        .catch(function() {
          if (!isActive || thisSeq !== previewSeq) return;
          if (pvTitle) {
            pvTitle.textContent = '📁 ' + folderLabel + ' (讀取失敗)';
            pvTitle.title = '📁 ' + folderLabel + ' (讀取失敗)';
          }
          popup.classList.remove('pp-loading');
        });
    }

    // ── 事件委任 (Event Delegation) ──────────────────────────
    function getPreviewItem(el) {
      if (!el) return null;
      var item = el.closest ? el.closest('.playlist-item.item-video, .playlist-item.item-folder') : null;
      if (!item) return null;
      if (item.dataset.isAudio === '1') return null;
      if (!item.dataset.relPath && !item.dataset.folderPath) return null;
      return item;
    }

    // ── 從項目的候選池隨機選取影片（資料夾亂數選片機制）──
    function getRandomSampleFromItem(item) {
      if (!item) return null;
      var poolJson = item.dataset.samplePool;
      if (poolJson) {
        try {
          var pool = JSON.parse(poolJson);
          if (Array.isArray(pool) && pool.length > 0) {
            var randIdx = Math.floor(Math.random() * pool.length);
            var cand = pool[randIdx];
            var candTitle = cand.title || cand.filename;
            var folderName = item.dataset.folderName || item.dataset.folderPath || '';
            var title = '🎬 ' + candTitle + (folderName ? (' (📁 ' + folderName + ' 隨機預覽)') : '');
            return {
              relPath: cand.rel_path,
              title: title
            };
          }
        } catch (e) {}
      }
      if (item.dataset.relPath) {
        return {
          relPath: item.dataset.relPath,
          title: item.dataset.title || ''
        };
      }
      return null;
    }

    // ── 動態解析資料夾代表性預覽影片（支援快取與後端延遲探測）──
    function resolveFolderSample(item, callback) {
      var sample = getRandomSampleFromItem(item);
      if (sample && sample.relPath) {
        callback(sample.relPath, sample.title);
        return;
      }
      var folderPath = item.dataset.folderPath;
      if (!folderPath) {
        callback(null, null);
        return;
      }
      var folderName = item.dataset.folderName || folderPath;
      var url = 'index.php?action=folder_sample&folder=' + encodeURIComponent(folderPath);
      authFetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data && data.success && data.sample && data.sample.rel_path) {
            var relPath = data.sample.rel_path;
            var sampleTitle = data.sample.title || data.sample.filename;
            var title = '🎬 ' + sampleTitle + (folderName ? (' (📁 ' + folderName + ' 隨機預覽)') : '');
            item.dataset.relPath = relPath;
            item.dataset.title = title;
            if (Array.isArray(data.sample.pool) && data.sample.pool.length > 0) {
              item.dataset.samplePool = JSON.stringify(data.sample.pool);
            }
            callback(relPath, title);
          } else {
            callback(null, null);
          }
        })
        .catch(function() {
          callback(null, null);
        });
    }

    // ── 點擊 👁 按鈕觸發彈窗預覽（支援影片與資料夾）─────────
    window._ppMobilePreview = function(itemEl) {
      var item = itemEl && itemEl.closest ? itemEl.closest('.playlist-item.item-video, .playlist-item.item-folder') : itemEl;
      if (!item) return;
      var sample = getRandomSampleFromItem(item);
      if (sample && sample.relPath) {
        currentItem = item;
        clearTimeout(debounceTimer);
        debounceTimer = null;
        doPreview(sample.relPath, sample.title.trim(), true);
      } else if (item.dataset.folderPath) {
        resolveFolderSample(item, function(relPath, title) {
          if (relPath) {
            currentItem = item;
            clearTimeout(debounceTimer);
            debounceTimer = null;
            doPreview(relPath, title.trim(), true);
          } else {
            if (typeof showToast === 'function') showToast('ℹ️ 此資料夾內暫無可播放影片');
          }
        });
      }
    };

    // ── 點擊預覽「前往目錄播放」核心函式 ───────────────────────
    window._ppPlayCurrent = function() {
      if (!activePreviewVideoRel) return;
      var targetVideoRel = activePreviewVideoRel;
      var cleanRel = targetVideoRel.replace(/\\/g, '/');
      var slashIdx = cleanRel.lastIndexOf('/');
      var targetFolder = slashIdx !== -1 ? cleanRel.substring(0, slashIdx) : '';

      // 關閉預覽視窗
      stopPreview();

      // 設定直連播放目標及標記
      window._pendingPlayRelPath = targetVideoRel;
      window._isNavigatingFromPreview = true;

      // 若目前已經在目標資料夾中，直接在當前清單中尋找並播放
      if (typeof currentPath !== 'undefined' && currentPath === targetFolder && typeof currentVideos !== 'undefined' && currentVideos.length > 0) {
        var pendingNorm = targetVideoRel.replace(/\\/g, '/').toLowerCase();
        var matched = currentVideos.find(function (v) {
          var vNorm = (v.rel_path || '').replace(/\\/g, '/').toLowerCase();
          return vNorm === pendingNorm || vNorm.endsWith('/' + pendingNorm);
        });
        if (matched) {
          window._pendingPlayRelPath = null;
          window._isNavigatingFromPreview = false;
          playVideoById(matched.id, true);
          if (typeof showToast === 'function') {
            showToast('▶ 開始播放：' + (matched.title || matched.filename));
          }
          setTimeout(function() {
            var activeItem = document.querySelector('.playlist-item.item-video.active');
            if (activeItem && typeof activeItem.scrollIntoView === 'function') {
              activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }, 120);
          return;
        }
      }

      // 若不同資料夾，導航至該目錄；載入後由 applyFolderData 自動比對並立即播放
      if (typeof navigateToPath === 'function') {
        navigateToPath(targetFolder);
      }
    };

    // ── 彈窗 Hover 懸停橋接監聽 ────────────────────────────────
    popup.addEventListener('mouseenter', function() {
      if (currentMode === 'hover') {
        isHoveringPopup = true;
        clearTimeout(leaveTimer);
      }
    });

    popup.addEventListener('mouseleave', function(e) {
      if (currentMode === 'hover') {
        isHoveringPopup = false;
        clearTimeout(leaveTimer);
        // 如果離開彈窗後，游標並非移回原本的清單項目，給予 400ms 緩衝關閉預覽
        var toItem = e.relatedTarget ? getPreviewItem(e.relatedTarget) : null;
        if (!toItem || toItem !== currentItem) {
          leaveTimer = setTimeout(function() {
            if (isActive && currentMode === 'hover' && !isHoveringPopup) {
              stopPreview();
            }
          }, 400);
        }
      }
    });

    // ── 追蹤觸控輸入狀態：純觸控點擊時不觸發 mouseover 懸停預覽 ──
    var isTouchActive = false;
    window.addEventListener('touchstart', function() {
      isTouchActive = true;
    }, { passive: true });
    window.addEventListener('mousemove', function(e) {
      if (e.movementX !== 0 || e.movementY !== 0) {
        isTouchActive = false;
      }
    }, { passive: true });

    // ── 滑鼠事件監聽（游標追蹤與 Hover 偵測） ──────────────────
    document.addEventListener('mousemove', function(e) {
      mouseX = e.clientX;
      mouseY = e.clientY;
      if (isActive && currentMode === 'hover') {
        // 若游標正懸停於預覽彈窗內部，或位置正處於 1 秒停頓鎖定保護期，絕不更新位置
        if (!isHoveringPopup && !isPosLocked) {
          // 若游標移動超過顯著距離 (例如 > 60px) 時，才重新定錨並再次鎖定 1 秒
          var dx = mouseX - anchorX;
          var dy = mouseY - anchorY;
          if (Math.sqrt(dx * dx + dy * dy) > 60) {
            positionPopup(true);
            lockPosition(1000);
          }
        }
      }
    });

    document.addEventListener('mouseover', function(e) {
      // 若為純觸控點擊模擬的 mouseover，或設備不支援懸停，直接跳過
      if (isTouchActive || (window.matchMedia && !window.matchMedia('(hover: hover)').matches)) {
        return;
      }

      var item = getPreviewItem(e.target);
      if (!item) return;
      mouseX = e.clientX;
      mouseY = e.clientY;

      // 如果從外部移入新的清單項目，取消之前的離開關閉計時器
      clearTimeout(leaveTimer);

      // 若滑鼠在同一個清單項目的內部子元素（標題、標籤等）移動，不重複觸發或重置計時器
      if (item === currentItem) {
        return;
      }

      currentItem = item;

      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function() {
        // 如果當前已經是在 modal 模式，不被 hover 覆蓋
        if (isActive && currentMode === 'modal') return;
        if (item !== currentItem) return;

        var sample = getRandomSampleFromItem(item);
        if (sample && sample.relPath) {
          doPreview(sample.relPath, sample.title.trim(), false);
        } else if (item.dataset.folderPath) {
          resolveFolderSample(item, function(relPath, title) {
            if (relPath && item === currentItem && (!isActive || currentMode !== 'modal')) {
              doPreview(relPath, title.trim(), false);
            }
          });
        }
      }, DEBOUNCE_MS);
    });

    document.addEventListener('mouseout', function(e) {
      var fromItem = getPreviewItem(e.target);
      if (!fromItem) return;
      var toItem = e.relatedTarget ? getPreviewItem(e.relatedTarget) : null;
      // 若仍處於同一個項目內部各標籤間移動，忽略 mouseout
      if (toItem === fromItem) return;

      // 若滑鼠直接移向預覽彈窗內部（含 ::before 橋接感應區），不觸發關閉
      if (e.relatedTarget && (popup === e.relatedTarget || popup.contains(e.relatedTarget))) {
        return;
      }

      clearTimeout(debounceTimer);
      debounceTimer = null;

      // 只有 hover 模式離開清單項目時才關閉預覽
      // 給予 1000ms (1秒) 充裕緩衝時間，讓使用者游標能從容滑入預覽彈窗本體點擊「前往播放」
      if (isActive && currentMode === 'hover') {
        clearTimeout(leaveTimer);
        leaveTimer = setTimeout(function() {
          if (isActive && currentMode === 'hover' && !isHoveringPopup) {
            stopPreview();
          }
        }, 1000);
      }
    });

    // ── 滾動清單或畫面時，立即關閉未聚焦的浮動預覽 ───────────
    document.addEventListener('wheel', function() {
      if (isActive && currentMode === 'hover' && !isHoveringPopup) {
        stopPreview();
      }
    }, { passive: true });

    // ── 點擊清單項目或進入資料夾時，立即關閉浮動 Hover 預覽 ──
    document.addEventListener('click', function(e) {
      if (e.target && e.target.closest && e.target.closest('.playlist-item')) {
        if (isActive && currentMode === 'hover') {
          stopPreview();
        }
      }
    });

  }()); // END PreviewManager
  </script>

</body>
</html>
 