module.exports = {
  apps: [
    {
      name: "lah-api-v85",
      script: "C:\\Tools\\php85\\php-cgi.exe",
      args: "-b 127.0.0.1:9085 -c C:\\Tools\\php85\\php.ini",
      cwd: "C:\\Temp\\DEV\\LAH-Workspace\\LAH-API\\v85",
      interpreter: "none",          // 關鍵：告知 PM2 這是原生 .exe，不透過 node 執行
      exec_mode: "fork",
      instances: 1,                 // Windows 下由 PHP_FCGI_CHILDREN 控制內部子程序數
      autorestart: true,            // 當滿 10000 次請求自動退出或崩潰時，PM2 立即無縫重啟
      watch: false,
      max_memory_restart: "1G",
      env: {
        // 將 Oracle Instant Client 與 PHP 8.5 注入環境變數
        PATH: "C:\\Tools\\instantclient_11_2;C:\\Tools\\php85;" + process.env.PATH,
        NLS_LANG: "American_America.US7ASCII",
        // Windows PHP-CGI 穩定度關鍵參數：允許併發子程序與拉高回收門檻
        PHP_FCGI_CHILDREN: "16",
        PHP_FCGI_MAX_REQUESTS: "10000"
      }
    }
  ]
};
