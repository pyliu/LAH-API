Log="/ha/log/check-hacmp-fs.`/bin/date +%Y-%m%d-%w-%H%M%S`.log"
date      >>$Log 2>&1
echo "check smitty hacmp fs *******"    >>$Log 2>&1
/usr/es/sbin/cluster/sbin/cl_showfs2    >>$Log 2>&1
echo "df -g **************"    >>$Log 2>&1
df -g     >>$Log 2>&1
echo "errpt **************"    >>$Log 2>&1
errpt     >>$Log 2>&1
date       >>$Log 2>&1
nohup mail -v -s "smitty hacmp fs check" hamonitor@mail.ha.cenweb.land.moi < $Log
