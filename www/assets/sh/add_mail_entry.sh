#!/bin/bash
# add mail entry to SQLite DB by API

apiIpi=$1
if [ -z "$apiIp" ]; then
     apiIp="220.1.34.75"
fi

subject=$2
if [ -z "$subject" ]; then
     subject="No SUBJECT"
fi

message=$3
if [ -f "$message" ]; then
     # expect a log path to read
     message=$(cat "$message")
elif [ -z "$message" ]; then
     message=`date "+%Y%m%d%H%M%S"`
     message="Finished timestamp: $message"
fi

ip=`netstat -ie | grep 'inet ' | grep -v '127.0.0.1' | cut -d: -f2 | awk '{print $2; exit}'`
api="http://$apiIp/api/monitor_json_api.php"
#log_time=`date "+%Y%m%d%H%M%S"`

curl -s -X POST \
     -d "type=add_mail_entry" \
     -d "FROM=$ip" \
     -d "SUBJECT=$subject" \
     -d "MESSAGE=$message" \
     -d "MAILBOX=INBOX" \
     -d "api_key=4a0494ad2055969f758260e8055dcb99" \
     $api
