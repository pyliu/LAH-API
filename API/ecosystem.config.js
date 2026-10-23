module.exports = {
  apps: [
    {
      name: "lah-api-v85",
      script: "C:\\AppServ\\php85\\php-cgi.exe",
      args: "-b 127.0.0.1:9085 -c C:\\AppServ\\php85\\php.ini",
      cwd: __dirname,               // 自動取得目前目錄，無縫相容開發筆電與各所伺服器路徑
      interpreter: "none",          // 關鍵：告知 PM2 這是原生 .exe，不透過 node 執行
      exec_mode: "fork",            // 關鍵限制：PM2 cluster 模式僅支援 Node.js。原生 .exe 設為 cluster 會因同時綁定 Port 9085 造成 EADDRINUSE (Error 10048) 衝突崩潰，因此必須使用 fork 模式
      instances: 1,                 // PM2 僅需守護 1 個主父程序，內部多程序併發完全交由下方的 PHP_FCGI_CHILDREN 接管
      autorestart: true,            // 當達到記憶體上限或主程序異常退出時，PM2 立即於 100ms 內無縫重啟
      watch: false,
      max_memory_restart: "1G",
      env: {
        // 將 Oracle Instant Client 與 PHP 8.5 注入環境變數
        PATH: "C:\\Tools\\instantclient_11_2;C:\\AppServ\\php85;" + process.env.PATH,
        NLS_LANG: "American_America.US7ASCII",
        // Windows FastCGI 核心併發參數：
        // 1. PHP_FCGI_CHILDREN: 父程序會自動在 Windows 底層衍生 16 個 worker 子程序共享 Port 9085 處理併發連線（實測共 17 個 php-cgi.exe，約佔 400MB RAM）
        // 2. PHP_FCGI_MAX_REQUESTS: 每個 worker 子程序處理滿 10000 次請求後自動退出並由父程序平滑重啟，徹底消除 Windows FastCGI 記憶體洩漏
        PHP_FCGI_CHILDREN: "16",
        PHP_FCGI_MAX_REQUESTS: "10000"
      }
    }
  ]
};
