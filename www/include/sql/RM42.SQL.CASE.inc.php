<?php
// 地價處理註記
return "(CASE
    WHEN RM42 = '0' THEN '登記移案'
    WHEN RM42 = 'B' THEN '登錄中'
    WHEN RM42 = 'R' THEN '登錄完成'
    WHEN RM42 = 'D' THEN '校對完成'
    WHEN RM42 = 'C' THEN '校對中'
    WHEN RM42 = 'E' THEN '校對有誤'
    WHEN RM42 = 'S' THEN '異動開始'
    WHEN RM42 = 'F' THEN '異動完成'
    WHEN RM42 = 'G' THEN '異動有誤'
    ELSE RM42
END)";
