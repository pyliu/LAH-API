Log="/home/oracle/ha/log/check-2.`/bin/date +%Y-%m%d-%w-%H%M%S`.log"
date      >>$Log 2>&1
df -g     >>$Log 2>&1
errpt     >>$Log 2>&1
#check DataGuard
sqlplus "/ as sysdba"   </home/oracle/ha/check-2.sql    >>$Log 2>&1
date   >>$Log 2>&1
nohup mail -v -s "P8-2 DataGuard STATE" hamonitor@mail.ha.cenweb.land.moi < $Log
