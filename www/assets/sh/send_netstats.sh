#!/bin/bash
# add following to the jboss crontab
# * 7-21 * * 1-6 for i in 1 2 3 4; do /home/jboss/cron/send_netstats.sh > /dev/null 2>&1 & [ "$i" -ne 4 ] && sleep 15; done

# 1. 取得本機 IP (相容 inet addr: 與 inet，僅取第一組有效 IPv4，避免多網卡換行問題)
ip=$(netstat -ie 2>/dev/null | awk '/inet / && !/127\.0\.0\.1/ {
    val = $2
    sub(/^addr:/, "", val)
    if (val ~ /^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/) {
        print val
        exit
    }
}')

# 若抓不到 IP 則提早結束，避免送出空值造成資料庫異常
[ -z "$ip" ] && exit 1

api="http://220.1.34.75/api/stats_json_api.php"
log_time=$(date "+%Y%m%d%H%M%S")

# 2. cross AP needs to collect other XAP data even if it's zero.
DEF_RECORDS=''
if [ "$ip" = "220.1.34.161" ]; then
    for def_ip in \
        220.1.35.123 220.1.37.246 220.1.38.30 220.1.34.161 220.1.36.45 \
        220.1.39.57 220.1.40.33 220.1.41.20 220.1.33.71; do
        DEF_RECORDS+=" -d records[]=0,$def_ip"
    done
fi

# 3. 連線數統計：改由單一 awk 直接過濾、統計並輸出，取代原本 9 個 pipe 行程
POST_PARAMS=$(netstat -ntu | awk '
/ESTAB/ {
    addr = $5
    sub(/^::ffff:/, "", addr)
    sub(/:.*/, "", addr)
    if (addr != "" && addr != "*") {
        count[addr]++
    }
}
END {
    for (a in count) {
        print " -d records[]=" count[a] "," a
    }
}')

# 4. 監控 JBoss 伺服器 CPU 使用率 (mpstat 取樣 1 秒，若未安裝或取值失敗則安全預設為 0.0)
JBOSS_CPU_USAGE=$(mpstat -P ALL 1 1 2>/dev/null | awk '/^[0-9]/ {sum += 100-$NF; count++} END {if (count > 0) printf "%.1f\n", sum/count; else print "0.0"}')
[ -z "$JBOSS_CPU_USAGE" ] && JBOSS_CPU_USAGE="0.0"
JBOSS_PARAMS=" -d records[]=$JBOSS_CPU_USAGE,JBOSS_CPU_USAGE"

# 5. 發送請求：加上連線超時 5s、最長執行時間 10s，防止連線異常時背景行程持續堆積
curl -sS --connect-timeout 5 -m 10 -X POST \
     -d "type=stats_set_conn_count" \
     -d "log_time=$log_time" \
     -d "ap_ip=$ip" \
     -d "api_key=4a0494ad2055969f758260e8055dcb99" \
     $DEF_RECORDS \
     $POST_PARAMS \
     $JBOSS_PARAMS \
     "$api"
