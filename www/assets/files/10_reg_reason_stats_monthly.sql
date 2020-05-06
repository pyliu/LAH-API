SELECT  (CASE
        WHEN RM101 = 'HA' THEN '桃園' 
        WHEN RM101 = 'HB' THEN '中壢' 
        WHEN RM101 = 'HC' THEN '大溪' 
        WHEN RM101 = 'HD' THEN '楊梅' 
        WHEN RM101 = 'HE' THEN '蘆竹' 
        WHEN RM101 = 'HF' THEN '八德' 
        WHEN RM101 = 'HG' THEN '平鎮' 
        WHEN RM101 = 'HH' THEN '龜山' 
 END) AS "收件所", KCNT AS "登記原因", COUNT(*) AS "件數"
  FROM MOICAS.CRSMS t
  LEFT JOIN MOIADM.RKEYN w
    ON t.RM09 = w.KCDE_2
   AND w.KCDE_1 = '06' -- 登記原因
 WHERE RM07_1 BETWEEN '1090401' and '1090431'
   AND RM101 <> 'HB'
   AND RM99 = 'Y'
 GROUP BY RM101, KCNT