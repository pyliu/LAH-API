<?php
// 案件辦理情形
return "(CASE
    WHEN RM30 = 'A' THEN '初審'
    WHEN RM30 = 'B' THEN '複審'
    WHEN RM30 = 'H' THEN '公告'
    WHEN RM30 = 'I' THEN '補正'
    WHEN RM30 = 'R' THEN '登錄'
    WHEN RM30 = 'C' THEN '校對'
    WHEN RM30 = 'U' THEN '異動完成'
    WHEN RM30 = 'F' THEN '結案'
    WHEN RM30 = 'X' THEN '補正初核'
    WHEN RM30 = 'Y' THEN '駁回初核'
    WHEN RM30 = 'J' THEN '撤回初核'
    WHEN RM30 = 'K' THEN '撤回'
    WHEN RM30 = 'Z' THEN '歸檔'
    WHEN RM30 = 'N' THEN '駁回'
    WHEN RM30 = 'L' THEN '公告初核'
    WHEN RM30 = 'E' THEN '請示'
    WHEN RM30 = 'D' THEN '展期'
    ELSE RM30
END)";
