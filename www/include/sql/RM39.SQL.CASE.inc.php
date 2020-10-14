<?php
// 登記處理註記
return "(CASE
    WHEN RM39 = 'B' THEN '登錄開始'
    WHEN RM39 = 'R' THEN '登錄完成'
    WHEN RM39 = 'C' THEN '校對結束'
    WHEN RM39 = 'E' THEN '校對有誤'
    WHEN RM39 = 'S' THEN '異動開始'
    WHEN RM39 = 'F' THEN '異動完成'
    WHEN RM39 = 'G' THEN '異動有誤'
    WHEN RM39 = 'P' THEN '競合暫停'
    ELSE RM39
END)";
