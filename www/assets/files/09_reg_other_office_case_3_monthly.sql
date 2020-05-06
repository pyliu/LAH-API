SELECT SQ.RM01   AS "收件年",
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
       SQ.RM30   AS "辦理情形",
       SQ.RM31   AS "結案代碼"
  FROM (SELECT *
          FROM MOICAS.CRSMS tt
         WHERE tt.rm07_1 LIKE '10904%'
           AND tt.rm02 LIKE 'H%B1' -- 本所處理跨所案件
           AND tt.RM03 NOT LIKE '%0' -- 子號案件
        ) SQ
  LEFT JOIN MOICAD.RKEYN k
    ON k.KCDE_2 = SQ.RM09
 WHERE k.KCDE_1 = '06';