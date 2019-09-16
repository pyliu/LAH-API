SELECT t.MD01             AS "收件年",
       t.MD02             AS "收件字",
       t.MD03             AS "收件號",
       t.MD06             AS "段小段",
       t.MD08             AS "地建號",
       r.SR_TYPE          AS "申請類別",
       r.SR09             AS "申請人統編",
       r.SR10             AS "申請人姓名",
       r.SR_AGENT_ID      AS "代理人統編",
       r.SR_AGENT_NAME    AS "代理人姓名",
       r.SR_SUBAGENT_ID   AS "複代理人統編",
       r.SR_SUBAGENT_NAME AS "複代理人姓名",
       r.SR08             AS "LOG時間",
       r.SR_METHOD        AS "申請方式",
       t.MD04             AS "謄本種類",
       t.MD05             AS "謄本項目"
  FROM MOICAS.CUSMD2 t
 INNER JOIN MOICAS.RSCNRL r
    ON (t.MD03 = r.SR03)
   AND (t.MD02 = r.SR02)
   AND (t.MD01 = r.SR01)
-- WHERE r.SR09 ='J000000000';
-- WHERE r.SR10 LIKE '星展%' AND t.MD06 = '0223'
 WHERE t.MD06 in ( '0223' )
   AND t.MD08 in ('08250000', '06941000')