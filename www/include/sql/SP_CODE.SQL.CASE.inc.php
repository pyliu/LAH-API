<?php
// 地價案件情況
return "(CASE
        WHEN SP_CODE = 'B' THEN '登記中' 
        WHEN SP_CODE = 'R' THEN '登錄完成' 
        WHEN SP_CODE = 'D' THEN '校對中' 
        WHEN SP_CODE = 'C' THEN '校對正確' 
        WHEN SP_CODE = 'E' THEN '校對正確' 
        WHEN SP_CODE = 'S' THEN '校對有誤' 
        WHEN SP_CODE = 'G' THEN '異動開始'
        WHEN SP_CODE = 'F' THEN '異動完成'
        ELSE SP_CODE
 END)";
 