SELECT t.rm01, t.rm02, t.rm03, t.rm07_1, t.rm07_2, t.rm09, s.kcnt,
  (CASE
    WHEN t.RM30 = 'A' THEN '初審'
    WHEN t.RM30 = 'B' THEN '複審'
    WHEN t.RM30 = 'H' THEN '公告'
    WHEN t.RM30 = 'I' THEN '補正'
    WHEN t.RM30 = 'R' THEN '登錄'
    WHEN t.RM30 = 'C' THEN '校對'
    WHEN t.RM30 = 'U' THEN '異動完成'
    WHEN t.RM30 = 'F' THEN '結案'
    WHEN t.RM30 = 'X' THEN '補正初核'
    WHEN t.RM30 = 'Y' THEN '駁回初核'
    WHEN t.RM30 = 'J' THEN '撤回初核'
    WHEN t.RM30 = 'K' THEN '撤回'
    WHEN t.RM30 = 'Z' THEN '歸檔'
    WHEN t.RM30 = 'N' THEN '駁回'
    WHEN t.RM30 = 'L' THEN '公告初核'
    WHEN t.RM30 = 'E' THEN '請示'
    WHEN t.RM30 = 'D' THEN '展期'
    ELSE t.RM30
  END) AS "辦理情形"
FROM SCRSMS t
LEFT JOIN SRKEYN s
  ON t.rm09 = s.kcde_2
WHERE s.kcde_1 = '06'
  AND RM07_1 LIKE '10904%'
ORDER BY RM09