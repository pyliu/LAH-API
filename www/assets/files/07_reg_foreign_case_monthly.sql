SELECT SQ.RM01   AS "收件年", -- 權利人&義務人為外國人案件
       SQ.RM02   AS "收件字",
       SQ.RM03   AS "收件號",
       SQ.RM09   AS "登記原因代碼",
       k.KCNT    AS "登記原因",
       SQ.RM07_1 AS "收件日期",
       SQ.RM58_1 AS "結案日期",
       SQ.RM18   AS "權利人統一編號",
       SQ.RM19   AS "權利人姓名",
       SQ.RM21   AS "義務人統一編號",
       SQ.RM22   AS "義務人姓名",
       (CASE
          WHEN SQ.RM30 = 'A' THEN '初審'
          WHEN SQ.RM30 = 'B' THEN '複審'
          WHEN SQ.RM30 = 'H' THEN '公告'
          WHEN SQ.RM30 = 'I' THEN '補正'
          WHEN SQ.RM30 = 'R' THEN '登錄'
          WHEN SQ.RM30 = 'C' THEN '校對'
          WHEN SQ.RM30 = 'U' THEN '異動完成'
          WHEN SQ.RM30 = 'F' THEN '結案'
          WHEN SQ.RM30 = 'X' THEN '補正初核'
          WHEN SQ.RM30 = 'Y' THEN '駁回初核'
          WHEN SQ.RM30 = 'J' THEN '撤回初核'
          WHEN SQ.RM30 = 'K' THEN '撤回'
          WHEN SQ.RM30 = 'Z' THEN '歸檔'
          WHEN SQ.RM30 = 'N' THEN '駁回'
          WHEN SQ.RM30 = 'L' THEN '公告初核'
          WHEN SQ.RM30 = 'E' THEN '請示'
          WHEN SQ.RM30 = 'D' THEN '展期'
          ELSE SQ.RM30
      END) AS "辦理情形",
       (CASE
          WHEN SQ.RM31 = 'A' THEN '結案'
          WHEN SQ.RM31 = 'B' THEN '撤回'
          WHEN SQ.RM31 = 'C' THEN '併案'
          WHEN SQ.RM31 = 'D' THEN '駁回'
          WHEN SQ.RM31 = 'E' THEN '請示'
          ELSE SQ.RM31
      END) AS "結案與否"
  FROM (SELECT *
    FROM MOICAD.RLNID p, MOICAS.CRSMS tt
    WHERE tt.RM07_1 LIKE '10807%'
      AND p.LCDE In ('2', '8', 'C', 'D') -- 代碼檔 09
      AND (tt.RM18 = p.LIDN OR tt.RM21 = p.LIDN)
  ) SQ
  LEFT JOIN MOICAD.RKEYN k ON k.KCDE_2 = SQ.RM09
 WHERE k.KCDE_1 = '06' AND SQ.RM09 in ('64', '65'); -- 買賣 64, 贈與 65