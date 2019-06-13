SELECT DISTINCT t.RM01   AS "收件年",
                t.RM02   AS "收件字",
                t.RM03   AS "收件號",
                t.RM07_1 AS "收件日期",
                w.KCNT   AS "收件原因",
                t.RM99   AS "是否跨所?",
                t.RM100  AS "跨所-資料管轄所所別",
                t.RM101  AS "跨所-收件所所別"
  FROM MOICAS.CRSMS t
  LEFT JOIN MOIADM.RKEYN w
    ON t.RM09 = w.KCDE_2
   AND w.KCDE_1 = '06' -- 登記原因
 WHERE RM07_1 BETWEEN '1080501' and '1080531'
   AND RM101 <> 'HB'
   AND RM99 = 'Y'