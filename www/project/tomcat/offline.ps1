<#
.SYNOPSIS
    Tomcat 戰情面板 - 離線資源自動下載腳本
.DESCRIPTION
    自動在當前目錄建立 offline 資料夾，並透過 Invoke-WebRequest 
    從 CDN 下載前端依賴檔案 (Tailwind, Vue3, Lucide)，
    讓戰情面板能在內網隔離環境 (Air-gapped) 中完美執行。
#>

# 取得腳本當下執行目錄
$scriptDir = if ($PSScriptRoot) { $PSScriptRoot } elseif ($MyInvocation.MyCommand.Path) { Split-Path $MyInvocation.MyCommand.Path } else { $PWD.Path }
$offlineDir = Join-Path $scriptDir "offline"

# 1. 確保 offline 資料夾存在
if (-not (Test-Path $offlineDir)) {
    Write-Host ">>> 正在建立 offline 資料夾..." -ForegroundColor Cyan
    New-Item -ItemType Directory -Path $offlineDir -Force | Out-Null
} else {
    Write-Host ">>> offline 資料夾已存在，準備檢查並更新檔案..." -ForegroundColor Cyan
}

# 2. 定義需要下載的資源庫
$resources = @(
    @{ 
        Name = "tailwindcss.js"
        Url  = "https://cdn.tailwindcss.com" 
    },
    @{ 
        Name = "vue.global.prod.js"
        Url  = "https://unpkg.com/vue@3/dist/vue.global.prod.js" 
    },
    @{ 
        Name = "lucide.min.js"
        Url  = "https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" 
    }
)

# 強制使用 TLS 1.2 通訊協定 (避免舊版 Windows Server 下載失敗)
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# 3. 執行下載
foreach ($res in $resources) {
    $destPath = Join-Path $offlineDir $res.Name
    Write-Host " -> 正在下載 $($res.Name) ... " -NoNewline
    
    try {
        # 使用 -UseBasicParsing 確保在未安裝 IE 引擎的 Server Core 上也能運作
        Invoke-WebRequest -Uri $res.Url -OutFile $destPath -UseBasicParsing -ErrorAction Stop
        Write-Host "[成功]" -ForegroundColor Green
    } catch {
        Write-Host "[失敗] $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "`n=================================================" -ForegroundColor Cyan
Write-Host "? 離線環境建置完成！" -ForegroundColor Green
Write-Host "現在您可以直接雙擊開啟 index.html 享受極速的離線監控體驗了。" -ForegroundColor Yellow
Write-Host "=================================================`n"
Start-Sleep -Seconds 5