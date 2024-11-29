#!/bin/bash
# add following to the jboss crontab
# */1 7-21   *   *  1-6 for i in 1 2 3 4 ; do /home/jboss/cron/send_netstats.sh 2>&1 > /dev/null & [[ i -ne 4 ]] && sleep 15; done

ip=`netstat -ie | grep 'inet addr:' | grep -v '127.0.0.1' | cut -d: -f2 | awk '{print $1}'`
api="http://220.1.34.75/api/stats_json_api.php"
log_time=`date "+%Y%m%d%H%M%S"`

# cross AP needs to collect other XAP data even if it's zero.
DEF_RECORDS=''
if [ "$ip" == "220.1.34.161" ]; then
     DEF_RECORDS+=" -d records[]=0,220.1.35.123"
     DEF_RECORDS+=" -d records[]=0,220.1.37.246"
     DEF_RECORDS+=" -d records[]=0,220.1.38.30"
     DEF_RECORDS+=" -d records[]=0,220.1.34.161"
     DEF_RECORDS+=" -d records[]=0,220.1.36.45"
     DEF_RECORDS+=" -d records[]=0,220.1.39.57"
     DEF_RECORDS+=" -d records[]=0,220.1.40.33"
     DEF_RECORDS+=" -d records[]=0,220.1.41.20"
     DEF_RECORDS+=" -d records[]=0,220.1.33.71"
fi

POST_PARAMS=$(netstat -ntu | grep ESTAB | awk '{print $5}' | sed -e 's/^::ffff://'  | cut -d : -f 1 | sort | uniq -c | sort -nr | awk '{print " -d records[]="$1","$2}')
# 1110831 added to monitor jboss server cpu utilization
#JBOSS_CPU_USAGE=`top -bn 1 | grep -i jboss | awk '{sum+=$9} END {print sum}'`
# 1131129 use claude AI answer :D
JBOSS_CPU_USAGE=`mpstat -P ALL 1 1 | awk '/^[0-9]/ {sum += 100-$NF; count++} END {printf "%.1f\n", sum/count}'`
JBOSS_PARAMS=" -d records[]=$JBOSS_CPU_USAGE,JBOSS_CPU_USAGE"

curl -s -X POST \
     -d "type=stats_set_conn_count" \
     -d "log_time=$log_time" \
     -d "ap_ip=$ip" \
     -d "api_key=4a0494ad2055969f758260e8055dcb99" \
     $DEF_RECORDS \
     $POST_PARAMS \
     $JBOSS_PARAMS \
     $api
