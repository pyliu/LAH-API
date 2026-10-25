const path = require('path');
const fs = require('fs');

// 自動探測並動態解析安裝根目錄（例如 C:\AppServ 或自訂目錄）
function resolveRootDir() {
  if (process.env.LAH_ROOT && fs.existsSync(process.env.LAH_ROOT)) {
    return path.normalize(process.env.LAH_ROOT);
  }
  const candidates = [
    path.resolve(__dirname, '..'),       // 若部署於 <ROOT>/LAH-API
    path.resolve(__dirname, '..', '..'), // 若置於原始碼工作區 <ROOT>/LAH-API/API
    'C:\\AppServ'
  ];
  return candidates.find(c => fs.existsSync(path.join(c, 'node')) || fs.existsSync(path.join(c, 'php85'))) || 'C:\\AppServ';
}

const rootDir = resolveRootDir();

// 自動探測並動態解析 php85 目錄（優先自環境變數、相對目錄推導，最後回退至預設值）
function resolvePhp85Dir(root) {
  if (process.env.LAH_PHP85_DIR && fs.existsSync(process.env.LAH_PHP85_DIR)) {
    return process.env.LAH_PHP85_DIR;
  }
  const candidates = [
    path.join(root, 'php85'),
    path.resolve(__dirname, '..', 'php85'),
    path.resolve(__dirname, '..', '..', 'php85'),
    path.resolve(__dirname, 'php85'),
    'C:\\AppServ\\php85'
  ];
  return candidates.find(c => fs.existsSync(path.join(c, 'php-cgi.exe'))) || path.join(root, 'php85');
}

// 自動探測並動態解析 Oracle Instant Client 目錄（優先跟隨根目錄，並向下相容 C:\Tools\instantclient_11_2）
function resolveInstantClientDir(root) {
  if (process.env.OCI_DIR && fs.existsSync(process.env.OCI_DIR)) {
    return process.env.OCI_DIR;
  }
  const candidates = [
    path.join(root, 'instantclient_11_2'),
    path.resolve(__dirname, '..', 'instantclient_11_2'),
    'C:\\Tools\\instantclient_11_2',
    'C:\\AppServ\\instantclient_11_2'
  ];
  return candidates.find(c => fs.existsSync(c)) || path.join(root, 'instantclient_11_2');
}

const php85Dir = resolvePhp85Dir(rootDir);
const phpCgi = path.join(php85Dir, 'php-cgi.exe');
const phpIni = path.join(php85Dir, 'php.ini');
const ociDir = resolveInstantClientDir(rootDir);

module.exports = {
  apps: [
    {
      name: "lah-api-v85",
      script: phpCgi,
      args: `-b 127.0.0.1:8085 -c "${phpIni}"`,
      cwd: __dirname,               // 自動取得目前目錄，無縫相容開發筆電與各所伺服器路徑
      interpreter: "none",          // 關鍵：告知 PM2 這是原生 .exe，不透過 node 執行
      exec_mode: "fork",            // 關鍵限制：PM2 cluster 模式僅支援 Node.js。原生 .exe 設為 cluster 會因同時綁定 Port 8085 造成 EADDRINUSE (Error 10048) 衝突崩潰，因此必須使用 fork 模式
      instances: 1,                 // PM2 僅需守護 1 個主父程序，內部多程序併發完全交由下方的 PHP_FCGI_CHILDREN 接管
      autorestart: true,            // 當達到記憶體上限或主程序異常退出時，PM2 立即於 100ms 內無縫重啟
      watch: false,
      max_memory_restart: "1G",
      env: {
        // 將動態探測之 Oracle Instant Client 與 PHP 8.5 注入環境變數
        PATH: ociDir + ";" + php85Dir + ";" + (process.env.PATH || ""),
        NLS_LANG: "American_America.US7ASCII",
        // Windows FastCGI 核心併發參數：
        // 1. PHP_FCGI_CHILDREN: 父程序會自動在 Windows 底層衍生 16 個 worker 子程序共享 Port 8085 處理併發連線（實測共 17 個 php-cgi.exe，約佔 400MB RAM）
        // 2. PHP_FCGI_MAX_REQUESTS: 每個 worker 子程序處理滿 10000 次請求後自動退出並由父程序平滑重啟，徹底消除 Windows FastCGI 記憶體洩漏
        PHP_FCGI_CHILDREN: "16",
        PHP_FCGI_MAX_REQUESTS: "10000"
      }
    }
  ]
};
