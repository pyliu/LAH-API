# AIX 伺服器內部郵件發送異常排除與靜態 ARP 永久配置標準作業手冊（SOP）

**文件版本**：v1.0  
**適用對象**：各地政事務所系統管理師、資料庫管理師（DBA）、系統維運工程師  
**適用環境**：IBM AIX 6.1 / 7.1 / 7.2 / 7.3、Oracle DataGuard 監控通報環境、內部 Sendmail 服務  

---

## 一、 故障情境與現象

在 AIX 資料庫伺服器上執行 DataGuard 監控腳本（如 `/home/oracle/ha/check-dataguard.sh`）時，發生站內通報信件無法發送的情況。

### 1.1 典型錯誤徵狀
1. **日誌逾時錯誤**：檢視 `/home/oracle/ha/nohup.out` 或 `/home/oracle/nohup.out`，顯示：
   ```text
   hamonitor@mail.ha.cenweb.land.moi... Connecting to mail.ha.cenweb.land.moi. via esmtp...
   hamonitor@mail.ha.cenweb.land.moi... Deferred: Connection timed out with mail.ha.cenweb.land.moi.
   ```
2. **Ping 封包遺失**：執行 `ping mail.ha.cenweb.land.moi` 出現 100% 封包遺失：
   ```text
   PING mail.ha.cenweb.land.moi: (220.1.34.50): 56 data bytes
   --- mail.ha.cenweb.land.moi ping statistics ---
   4 packets transmitted, 0 packets received, 100% packet loss
   ```
3. **郵件佇列積壓**：檢查 `/var/spool/mqueue`，累積大量未送出的 `qf*` 與 `df*` 檔案。

---

## 二、 根本原因分析（Root Cause）

此故障主要由以下兩項核心網路與系統配置問題共同導致：

### 2.1 二層 ARP 解析失敗（關鍵原因）
- 站內郵件伺服器 `220.1.34.50`（mail.ha.cenweb.land.moi）實體設備為 Synology NAS（MAC 位址為 `00:11:32:e5:4a:10`）。
- 該主機與 AIX 主機的業務網卡（如 `en0`：`220.1.34.114/24`）處於同一個二層網段。
- 由於交換器安全原則（Port Isolation）或設備本身不回應動態廣播 ARP Request，AIX 核心無法動態學到該 IP 的 MAC 位址，在 ARP 表中顯示為 `incomplete`：
  ```text
  ? (220.1.34.50) at (incomplete)
  ```
  AIX 找不到二層 MAC，TCP SYN 封包根本無法在實體層封裝發出，導致連線直接逾時。

### 2.2 常見排查誤區：為何加入閘道路由依然無效？
部分管理師嘗試手動下達：
```bash
route add -host 220.1.34.50 220.1.34.9
```
此做法**無法解決郵件發送問題**，原因如下：
1. **閘道端 ACL 限制**：所端閘道器（`220.1.34.9`）通常僅開放特定管理封包，其防護策略常阻絕非授權網段發往 25 埠（SMTP）之連線。
2. **非對稱路由（Asymmetric Routing）**：雖然 ICMP Ping 可能通暢，但 TCP 具狀態性（Stateful），發出的 SYN 與回程路徑不一致會導致三方交握失敗。
3. **正確路徑為本機直連**：郵件主機本身就是該網段之直連設備，不應繞道三層閘道轉發。

### 2.3 名稱解析配置延遲（次要原因）
AIX 的 `/etc/netsvc.conf` 若預設包含 `bind`（例如 `hosts=local, bind, nis`），而主機又未配置外部 DNS 伺服器（`/etc/resolv.conf` 不存在），Sendmail 在寄信前會強制等待 DNS 解析逾時（約 30～60 秒），進一步加劇郵件寄送停滯。

---

## 三、 5 分鐘快速診斷清單

請以 `root` 身分登入 AIX 終端機，執行以下檢測：

| 診斷項目 | 檢測指令 | 正常狀況 | 異常狀況與處置 |
| :--- | :--- | :--- | :--- |
| **1. ARP 狀態** | `arp -an \| grep 220.1.34.50` | 顯示 `permanent` 及 MAC 位址 | 顯示 `(incomplete)` 或無輸出 ➔ **需配置靜態 ARP** |
| **2. SMTP 埠連通** | `perl -e 'use IO::Socket::INET; print IO::Socket::INET->new(PeerAddr=>"220.1.34.50",PeerPort=>25,Timeout=>2)?"OPEN\n":"FAIL\n"'` | 輸出 `OPEN` | 輸出 `FAIL` ➔ **請優先確認靜態 ARP** |
| **3. 名稱解析** | `grep '^hosts=' /etc/netsvc.conf` | `hosts=local` | 含 `bind` 或 `nis` ➔ **需調整為 local** |
| **4. 殘留無效路由** | `netstat -rn \| grep 220.1.34.50` | 無輸出（走直連） | 顯示指向 `220.1.34.9` ➔ **需刪除** |

---

## 四、 逐步設定處置步驟

### 步驟一：移除無效的閘道轉發路由
若先前曾手動加入指向閘道器的路由，請先將其移除：
```bash
route delete -host 220.1.34.50 220.1.34.9
```

### 步驟二：手動綁定永久靜態 ARP（立即生效）
AIX 的 `arp` 指令需加上 `ether` 關鍵字指定乙太網路類型：
```bash
arp -s ether 220.1.34.50 00:11:32:e5:4a:10
```
> **檢查驗證**：
> 執行 `arp -an | grep 220.1.34.50`，若看到以下輸出即代表成功生效：
> `? (220.1.34.50) at 0:11:32:e5:4a:10 [ethernet] permanent stored in bucket 136`

### 步驟三：優化名稱解析順序
將 `/etc/netsvc.conf` 設定為僅查閱本地 `/etc/hosts`，避免無效的 DNS 查詢等待：
```bash
# 備份原始檔案
cp /etc/netsvc.conf /etc/netsvc.conf.bak

# 替換為本地優先
sed -e 's/hosts=local, bind, nis/hosts=local/' /etc/netsvc.conf.bak > /etc/netsvc.conf

# 確認設定
grep '^hosts=' /etc/netsvc.conf
```
預期輸出：
```text
hosts=local
```

### 步驟四：重啟 Sendmail 服務並清空積壓佇列
```bash
# 重啟 Sendmail 子系統
stopsrc -s sendmail
startsrc -s sendmail -a "-bd -q30m"

# 強制發送佇列中積壓的郵件
sendmail -q -v
```
執行後檢查 `/var/spool/mqueue` 目錄，檔案應全數成功寄出並清空。

---

## 五、 開機持久化配置（重開機自動生效）

AIX 核心的 `arp -s` 設定僅存於記憶體，系統重開機後會遺失。必須將指令寫入網路初始化腳本 `/etc/rc.net` 中。

### 5.1 寫入 `/etc/rc.net`
請以 `root` 執行以下指令追加至檔案末尾：
```bash
# 備份 rc.net
cp /etc/rc.net /etc/rc.net.orig

# 追加靜態 ARP 指令
cat << 'EOF' >> /etc/rc.net

# -------------------------------------------------------------
# 內部郵件主機（Synology）永久靜態 ARP 綁定
# -------------------------------------------------------------
if [ -x /usr/sbin/arp ]; then
    /usr/sbin/arp -s ether 220.1.34.50 00:11:32:e5:4a:10
fi
EOF
```

### 5.2 驗證持久化腳本語法
執行以下指令確認語法無誤：
```bash
tail -n 10 /etc/rc.net
```

---

## 六、 端到端功能驗證

### 6.1 測試 SMTP 埠號連線
執行 Perl 單行指令確認 TCP 25 埠暢通：
```bash
perl -e 'use IO::Socket::INET; my $s = IO::Socket::INET->new(PeerAddr=>"220.1.34.50", PeerPort=>25, Timeout=>3); print $s ? "SUCCESS: Port 25 is OPEN\n" : "FAILED: $!\n";'
```
預期輸出：
```text
SUCCESS: Port 25 is OPEN
```

### 6.2 實際執行 DataGuard 監控腳本
以 `oracle` 身份手動執行通報腳本：
```bash
su - oracle -c "/home/oracle/ha/check-dataguard.sh"
```

### 6.3 檢視發信日誌
檢視 `/home/oracle/nohup.out`，若出現類似以下 SMTP 交握與回傳訊息，即代表發信完全正常：
```text
Connecting to mail.ha.cenweb.land.moi. via esmtp...
220 mail.ha.cenweb.land.moi ESMTP Postfix
>>> EHLO DRORAHA01
250-mail.ha.cenweb.land.moi
...
>>> MAIL From:<oracle@DRORAHA01> SIZE=1710
250 2.1.0 Ok
>>> RCPT To:<hamonitor@mail.ha.cenweb.land.moi>
250 2.1.5 Ok
>>> DATA
354 End data with <CR><LF>.<CR><LF>
>>> .
250 2.0.0 Ok: queued as E69FE794C
hamonitor@mail.ha.cenweb.land.moi... Sent (Ok: queued as E69FE794C)
Closing connection to mail.ha.cenweb.land.moi.
>>> QUIT
221 2.0.0 Bye
```

---

## 七、 附錄：各所環境參數對照表

若其他地政事務所之主機 IP 或郵件伺服器有所差異，請參照下表進行參數微調：

| 參數名稱 | 本所參考設定值 | 其他所設定說明 |
| :--- | :--- | :--- |
| **AIX 業務網卡** | `en0`（或 `en3`） | 請透過 `ifconfig -a` 確認與郵件主機同網段之介面 |
| **目標郵件主機名稱** | `mail.ha.cenweb.land.moi` | 需於 `/etc/hosts` 中正向對應 |
| **目標郵件主機 IP** | `220.1.34.50` | 內部 Mail Server IP |
| **郵件主機 MAC 位址** | `00:11:32:e5:4a:10` | 若各所有獨立郵件設備，請以該設備實際網卡 MAC 為準 |
| **持久化配置檔案** | `/etc/rc.net` | AIX 網路設定完成後必經之系統初始化腳本 |
