Log="/ha/log/check-hacmp-dnp.`/bin/date +%Y-%m%d-%w-%H%M%S`.log"
date      >>$Log 2>&1
echo "check smitty dnp  *******"    >>$Log 2>&1
/usr/es/sbin/cluster/utilities/clshowsrv -v >>$Log 2>&1
echo "df -g **************"    >>$Log 2>&1
df -g     >>$Log 2>&1
echo "errpt **************"    >>$Log 2>&1
errpt     >>$Log 2>&1
date       >>$Log 2>&1
nohup mail -v -s "smitty dnp check" hamonitor@mail.ha.cenweb.land.moi < $Log
