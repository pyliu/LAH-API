#!/bin/bash
# add following to the jboss crontab
# for i in 1 2 3 4 ; do /home/jboss/cron/send_conn_stats_cron.sh 2>&1 > /dev/null & [[ i -ne 4 ]] && sleep 15; done

post_data()
{
    local log_time=`date "+%Y%m%d%H%M%S"`
    site=$1
    echo "type=stats_set_ap_conn&log_time=${log_time}&ip=${ip}&site=${site}&count=$2"
}

post_single()
{
    echo "$1: $2"
    data=$(post_data $1 $2)
    curl -X POST --data "${data}" $api
}

# non-root user may not find ifconfig ...
#ip=`ifconfig | grep 'inet addr:' | grep -v '127.0.0.1' | cut -d: -f2 | awk '{print $1}'`
ip="220.1.35.123"
api="http://220.1.35.84/api/stats_json_api.php"
netstat_est=`netstat -an | grep EST`
CURR=`date "+%Y-%m-%d %H:%M:%S"`

count()
{
    local stats=`echo $netstat_est | grep "$1" | wc -l`
    echo $stats	
}

post()
{
    local log_time=`date "+%Y%m%d%H%M%S"`
    curl -s -X POST \
    -d "type=stats_set_ap_conn" \
    -d "log_time=$log_time" \
    -d "ip=$ip" \
    -d "sites[]=$1" \
    -d "counts[]=$2" \
    -d "sites[]=$3" \
    -d "counts[]=$4" \
    -d "sites[]=$5" \
    -d "counts[]=$6" \
    -d "sites[]=$7" \
    -d "counts[]=$8" \
    -d "sites[]=$9" \
    -d "counts[]=${10}" \
    -d "sites[]=${11}" \
    -d "counts[]=${12}" \
    -d "sites[]=${13}" \
    -d "counts[]=${14}" \
    -d "sites[]=${15}" \
    -d "counts[]=${16}" \
    -d "sites[]=${17}" \
    -d "counts[]=${18}" \
    -d "sites[]=${19}" \
    -d "counts[]=${20}" \
    -d "sites[]=${21}" \
    -d "counts[]=${22}" \
    $api
}

echo -n "${CURR}: Send send post data to ${api} ... "

DB=`echo $netstat_est | grep -E ":1521" | wc -l`
TOTAL=`echo $netstat_est | wc -l`

post "H0" $(count "220.1.33.") \
"HA" $(count "220.1.34.") \
"HB" $(count ":9080") \
"HC" $(count "220.1.36.") \
"HD" $(count "220.1.37.") \
"HE" $(count "220.1.38.") \
"HF" $(count "220.1.39.") \
"HG" $(count "220.1.40.") \
"HH" $(count "220.1.41.") \
"DB" $DB \
"TOTAL" $TOTAL

echo "done."
