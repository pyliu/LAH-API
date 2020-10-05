#!/bin/bash
# add following to the jboss crontab
# */1 7-21   *   *  1-6 for i in 1 2 3 4 ; do /home/jboss/cron/send_netstats.sh 2>&1 > /dev/null & [[ i -ne 4 ]] && sleep 15; done

# non-root user may not find ifconfig ...
#ip=`ifconfig | grep 'inet addr:' | grep -v '127.0.0.1' | cut -d: -f2 | awk '{print $1}'`
ip="220.1.35.123"
api="http://220.1.35.84/api/stats_json_api.php"
CURR=`date "+%Y-%m-%d %H:%M:%S"`
log_time=`date "+%Y%m%d%H%M%S"`
POST_PARAMS=$(netstat -ntu | grep ESTAB | awk '{print $5}' | sed -e 's/^::ffff://'  | cut -d : -f 1 | sort | uniq -c | sort -nr | awk '{print " -d records[]="$1","$2}')
curl -s -X POST \
     -d "type=stats_set_conn_count" \
     -d "log_time=$log_time" \
     -d "ap_ip=$ip" \
     -d "api_key=4a0494ad2055969f758260e8055dcb99" \
     $POST_PARAMS \
     $api
