SELECT DISTINCT t.RM01   AS "收件年",
                t.RM02   AS "收件字",
                t.RM03   AS "收件號",
                t.RM07_1 AS "收件日期",
                w.KCNT   AS "收件原因",
                t.RM99   AS "是否跨所?",
                (CASE
                  WHEN t.RM100 = 'HA' THEN '桃園'
                  WHEN t.RM100 = 'HB' THEN '中壢'
                  WHEN t.RM100 = 'HC' THEN '大溪'
                  WHEN t.RM100 = 'HD' THEN '楊梅'
                  WHEN t.RM100 = 'HE' THEN '蘆竹'
                  WHEN t.RM100 = 'HF' THEN '八德'
                  WHEN t.RM100 = 'HG' THEN '平鎮'
                  WHEN t.RM100 = 'HH' THEN '龜山'
                  ELSE t.RM100
                END) AS "跨所-資料管轄所所別",
                (CASE
                  WHEN t.RM101 = 'HA' THEN '桃園'
                  WHEN t.RM101 = 'HB' THEN '中壢'
                  WHEN t.RM101 = 'HC' THEN '大溪'
                  WHEN t.RM101 = 'HD' THEN '楊梅'
                  WHEN t.RM101 = 'HE' THEN '蘆竹'
                  WHEN t.RM101 = 'HF' THEN '八德'
                  WHEN t.RM101 = 'HG' THEN '平鎮'
                  WHEN t.RM101 = 'HH' THEN '龜山'
                  ELSE t.RM101
                END) AS "跨所-收件所所別"
  FROM MOICAS.CRSMS t
  LEFT JOIN MOIADM.RKEYN w
    ON t.RM09 = w.KCDE_2
   AND w.KCDE_1 = '06' -- 登記原因
 WHERE RM07_1 BETWEEN '1090401' and '1090431'
   AND t.RM101 <> 'HB'
   AND t.RM99 = 'Y'