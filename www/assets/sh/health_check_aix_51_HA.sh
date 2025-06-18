#!/bin/ksh
# ==============================================================================
#
# Script Name: health_check_aix.sh
#
# Description: This script performs a comprehensive health check on an AIX
#              system, including PowerHA/HACMP, Oracle, and core OS resources.
#              The output is logged and sent via email.
#              It rotates the Oracle alert.log daily and cleans up old
#              log files from both the script log directory and Oracle dump
#              directory.
#
# Author: Gemini (AIX Automation Engineer)
# Version: 2.4 - Added Oracle alert.log rotation and extended cleanup.
# Last Modified: 2025-06-18
#
# ==============================================================================

# --- 全域變數設定 (Global Variables) ---
# 將常變動的路徑或名稱統一放在這裡，方便未來修改
LOG_DIR="/BACKUP/LOG"
HOSTNAME_TAG="p8_51"
MAIL_RECIPIENT="hamonitor@mail.ha.cenweb.land.moi"
MAIL_SUBJECT="[Health Check] - ${HOSTNAME_TAG} - $(date +'%Y-%m-%d %H:%M')"
CLEANUP_DAYS=7 # 清理幾天前的日誌

# !! 請根據不同地政事務所主機修改此處 !!
# 例如：桃園所為 HAWEB, 中壢所為 HBWEB, 原腳本為 L1HWEB
ORACLE_INSTANCE_NAME="HAWEB"

# --- 動態產生的變數 (Dynamically Generated Variables) ---
ORACLE_DUMP_DIR="/WEB/DB3/DBF/admin/${ORACLE_INSTANCE_NAME}/bdump"
ORACLE_ALERT_LOG="alert_${ORACLE_INSTANCE_NAME}.log"
TODAY=$(date +%Y%m%d)

# --- 日誌檔設定 (Log File Setup) ---
# 確保日誌目錄存在
mkdir -p "$LOG_DIR"
LOG_FILE="${LOG_DIR}/check_${HOSTNAME_TAG}.$(date +%Y-%m%d-%w-%H%M%S).log"

# ==============================================================================
# --- 函式定義 (Functions) ---
# ==============================================================================

# 寫入日誌的函式，方便加入分隔線
log_section_header() {
    echo ""
    echo "=============================================================================="
    echo "## $1"
    echo "=============================================================================="
}

# 檢查 HACMP/PowerHA 狀態
check_hacmp() {
    log_section_header "HACMP/PowerHA Cluster Status"
    
    if [ -x "/usr/es/sbin/cluster/utilities/clRGinfo" ]; then
        /usr/es/sbin/cluster/utilities/clRGinfo -v; echo ""
        /usr/es/sbin/cluster/sbin/cl_showfs2; echo ""
        /usr/es/sbin/cluster/utilities/clshowsrv -v
    else
        echo "HACMP/PowerHA commands not found. Skipping check."
    fi
}

# 檢查 Oracle 相關資訊
check_oracle() {
    log_section_header "Oracle Status"
    
    echo "--> Checking Oracle process count (estimated)..."
    ps -ef | grep '[o]ra_' | wc -l
    echo ""

    echo "--> Checking Oracle Alert Log for errors (ORA-)..."
    if [ -r "${ORACLE_DUMP_DIR}/${ORACLE_ALERT_LOG}" ]; then
        grep "ORA-" "${ORACLE_DUMP_DIR}/${ORACLE_ALERT_LOG}" || echo "No 'ORA-' errors found."
        echo ""
        echo "--> Total lines in alert.log:"
        wc -l < "${ORACLE_DUMP_DIR}/${ORACLE_ALERT_LOG}"
    else
        echo "ERROR: Oracle alert log not found or not readable at ${ORACLE_DUMP_DIR}/${ORACLE_ALERT_LOG}"
    fi
}

# 檢查核心系統資源
check_system_resources() {
    log_section_header "System Resources"
    echo "--> Filesystem usage (in GB)..."; df -g; echo ""
    echo "--> I/O statistics..."; iostat; echo ""
    echo "--> Virtual memory and CPU statistics (5 samples at 1-sec intervals)..."; vmstat 1 5
}

# 檢查 AIX 錯誤報告
check_errpt() {
    log_section_header "AIX Error Report (errpt)"
    echo "--> Error Summary:"; errpt; echo ""
    echo "--> Detailed Error Report (last 24 hours):"
    errpt -a -s "$(TZ=aaa24 date +%m%d%H%M%y)"
}

# **新功能**: 切換並備份 Oracle Alert Log
rotate_oracle_alert_log() {
    log_section_header "Rotating Oracle Alert Log"
    local alert_log_path="${ORACLE_DUMP_DIR}/${ORACLE_ALERT_LOG}"
    local backup_log_path="${ORACLE_DUMP_DIR}/alert_${ORACLE_INSTANCE_NAME}_${TODAY}.log"

    if [ -f "$alert_log_path" ]; then
        echo "Backing up current alert log to ${backup_log_path}..."
        # 複製一份當日備份
        cp "$alert_log_path" "$backup_log_path"
        
        echo "Truncating original alert log..."
        # 清空原始檔案，準備記錄新的日誌
        cp /dev/null "$alert_log_path"
        echo "Alert log rotation complete."
    else
        echo "WARNING: Original alert log not found at ${alert_log_path}. Skipping rotation."
    fi
}

# **功能擴展**: 清理過期的日誌檔案
cleanup_old_logs() {
    log_section_header "Cleaning Up Old Log Files (older than ${CLEANUP_DAYS} days)"
    
    # 1. 清理本腳本產生的日誌
    if [ -d "$LOG_DIR" ]; then
        echo "Searching for script logs in ${LOG_DIR}..."
        find "$LOG_DIR" -name "check_*.log" -mtime +${CLEANUP_DAYS} -exec rm -f {} \;
    else
        echo "WARNING: Script log directory ${LOG_DIR} not found. Skipping."
    fi

    # 2. 清理 Oracle dump 目錄下的日誌與追蹤檔
    if [ -d "$ORACLE_DUMP_DIR" ]; then
        echo "Searching for Oracle logs and trace files in ${ORACLE_DUMP_DIR}..."
        # 清理備份的 alert log
        find "$ORACLE_DUMP_DIR" -name "alert_*.log" -mtime +${CLEANUP_DAYS} -exec rm -f {} \;
        # 清理 Oracle trace files (*.trc)
        find "$ORACLE_DUMP_DIR" -name "*.trc" -mtime +${CLEANUP_DAYS} -exec rm -f {} \;
    else
        echo "WARNING: Oracle dump directory ${ORACLE_DUMP_DIR} not found. Skipping."
    fi
    echo "Log cleanup complete."
}

# 發送郵件
send_notification() {
    if [ -s "$LOG_FILE" ]; then
        nohup mail -s "$MAIL_SUBJECT" "$MAIL_RECIPIENT" < "$LOG_FILE" > /dev/null 2>&1 &
        echo "Log file sent to $MAIL_RECIPIENT"
    else
        echo "Log file is empty or does not exist. No email sent."
    fi
}

# ==============================================================================
# --- 主程式 (Main Logic) ---
# ==============================================================================

# 將所有輸出導入日誌檔案
exec >> "$LOG_FILE" 2>&1

echo "AIX Health Check for p8_51 Started at $(date)"

# 依序執行各項檢查
check_hacmp
check_system_resources
check_oracle
check_errpt

echo ""
log_section_header "Check Complete"
echo "AIX Health Check Finished at $(date)"

# 在主控台顯示腳本完成訊息（此訊息不會寫入日誌）
echo "Health check complete. Log saved to $LOG_FILE" >&2

# --- 後續維護作業 ---
# 在發送郵件後，執行日誌切檔與清理
send_notification
rotate_oracle_alert_log
cleanup_old_logs

exit 0
