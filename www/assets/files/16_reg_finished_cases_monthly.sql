SELECT DISTINCT 
      t.RM01 AS "收件年",
      t.RM02 AS "收件字",
      t.RM03 AS "收件號",
      t.RM07_1 AS "收件日期",
      t.RM09 AS "登記原因代碼",
      r.kcnt AS "登記原因",
      (CASE
        WHEN RM30 = 'A' THEN
         '初審'
        WHEN RM30 = 'B' THEN
         '複審'
        WHEN RM30 = 'H' THEN
         '公告'
        WHEN RM30 = 'I' THEN
         '補正'
        WHEN RM30 = 'R' THEN
         '登錄'
        WHEN RM30 = 'C' THEN
         '校對'
        WHEN RM30 = 'U' THEN
         '異動完成'
        WHEN RM30 = 'F' THEN
         '結案'
        WHEN RM30 = 'X' THEN
         '補正初核'
        WHEN RM30 = 'Y' THEN
         '駁回初核'
        WHEN RM30 = 'J' THEN
         '撤回初核'
        WHEN RM30 = 'K' THEN
         '撤回'
        WHEN RM30 = 'Z' THEN
         '歸檔'
        WHEN RM30 = 'N' THEN
         '駁回'
        WHEN RM30 = 'L' THEN
         '公告初核'
        WHEN RM30 = 'E' THEN
         '請示'
        WHEN RM30 = 'D' THEN
         '展期'
        ELSE
         RM30
      END) AS "辦理情形",
      (CASE
        WHEN RM31 = 'A' THEN
         '結案'
        WHEN RM31 = 'B' THEN
         '撤回'
        WHEN RM31 = 'C' THEN
         '併案'
        WHEN RM31 = 'D' THEN
         '駁回'
        WHEN RM31 = 'E' THEN
         '請示'
        ELSE
         RM31
      END) AS "結案與否",
      t.RM99 AS "是否跨所",
      t.RM101 AS "資料管轄所"
  FROM MOICAS.CRSMS t
  LEFT JOIN MOIADM.RKEYN r
    on (t.RM09 = r.KCDE_2 and r.KCDE_1 = '06')
 WHERE (t.RM07_1 BETWEEN '1080701' AND '1080731')
   AND (t.RM30 in ('C') OR RM31 = 'A')
 ORDER BY t.RM07_1