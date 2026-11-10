# ------------------------------------------------------------------------------
# 伺服器識別設定（供系統管理師依部署主機設定）
# 可選值: P8-2、P7-102、hb-114
# （注意：此字串與前端 LAH-FE DataGuard 監控看板主旨連動，請勿修改為其他字串）
# ------------------------------------------------------------------------------
SERVER_ID="${1:-P8-2}"

# 相關工作目錄與檔案設定
BASE_DIR="/home/oracle/ha"
LOG_DIR="${BASE_DIR}/log"
SQL_FILE="${BASE_DIR}/check-dataguard.sql"

# 監控通報收件人信箱
MAIL_TO="hamonitor@mail.ha.cenweb.land.moi"

Log="${LOG_DIR}/check-${SERVER_ID}.`/bin/date +%Y-%m%d-%w-%H%M%S`.log"
date      >>$Log 2>&1
df -g     >>$Log 2>&1
errpt     >>$Log 2>&1
#check DataGuard
sqlplus "/ as sysdba"   <"${SQL_FILE}"    >>$Log 2>&1
date   >>$Log 2>&1
nohup mail -v -s "${SERVER_ID} DataGuard STATE" "${MAIL_TO}" < $Log
find "${LOG_DIR}" -type f -name "check-*.log" -mtime +7 -exec rm -f {} \;
