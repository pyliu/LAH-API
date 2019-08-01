SELECT DISTINCT
  t.RM01   AS "收件年",
  t.RM02   AS "收件字",
  t.RM03   AS "收件號",
  t.RM09   AS "登記原因代碼",
  k.KCNT    AS "登記原因",
  t.RM07_1 AS "收件日期",
  t.RM58_1 AS "結案日期",
  t.RM18   AS "權利人統一編號",
  t.RM19   AS "權利人姓名",
  t.RM21   AS "義務人統一編號",
  t.RM22   AS "義務人姓名",
  p.LBIR_1 AS "外國人類別",
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
END) AS "辦理情形",
  (CASE
    WHEN t.RM31 = 'A' THEN '結案'
    WHEN t.RM31 = 'B' THEN '撤回'
    WHEN t.RM31 = 'C' THEN '併案'
    WHEN t.RM31 = 'D' THEN '駁回'
    WHEN t.RM31 = 'E' THEN '請示'
    ELSE t.RM31
END) AS "結案與否"
FROM MOICAD.RLNID p, MOICAS.CRSMS t, MOICAD.RKEYN k 
WHERE
  t.RM07_1 LIKE '10807%'
--AND p.LCDE in ('2', '8', 'C', 'D')
  AND p.LCDE not in ('1', '3', '4', '5', '6', '7', '9', 'A', 'B')
  AND (t.RM18 = p.LIDN OR t.RM21 = p.LIDN)
  AND t.RM09 in ('64', '65') -- 買賣 64, 贈與 65
  AND k.KCDE_2 = t.RM09
  AND k.KCDE_1 = '06'