archive log list
select DBID, NAME, OPEN_MODE, DATABASE_ROLE, SWITCHOVER_STATUS from v$database;
select * from v$archive_gap;
