SELECT DISTINCT
	t.RM01,
	t.RM02,
	t.RM03,
	t.RM07_1,
	t.RM09,
	r.kcnt AS "登記原因",
	t.RM99  AS "是否跨所",
	(CASE
		WHEN t.RM101 = 'HA' THEN '桃園' 
		WHEN t.RM101 = 'HB' THEN '中壢' 
		WHEN t.RM101 = 'HC' THEN '大溪' 
		WHEN t.RM101 = 'HD' THEN '楊梅' 
		WHEN t.RM101 = 'HE' THEN '蘆竹' 
		WHEN t.RM101 = 'HF' THEN '八德' 
		WHEN t.RM101 = 'HG' THEN '平鎮' 
		WHEN t.RM101 = 'HH' THEN '龜山' 
	END) AS "資料收件所",
	(CASE
        WHEN t.RM100 = 'HA' THEN '桃園' 
        WHEN t.RM100 = 'HB' THEN '中壢' 
        WHEN t.RM100 = 'HC' THEN '大溪' 
        WHEN t.RM100 = 'HD' THEN '楊梅' 
        WHEN t.RM100 = 'HE' THEN '蘆竹' 
        WHEN t.RM100 = 'HF' THEN '八德' 
        WHEN t.RM100 = 'HG' THEN '平鎮' 
        WHEN t.RM100 = 'HH' THEN '龜山' 
	END) AS "資料管轄所"
FROM MOICAS.CRSMS t
LEFT JOIN MOIADM.RKEYN r
	on (t.RM09 = r.KCDE_2 and r.KCDE_1 = '06')
WHERE (t.RM07_1 BETWEEN '1090416' AND '1090431')
	AND t.RM09 in ( 'CU', 'CW') -- 信託 CU、塗銷信託 CW、共有物分割 38
	--AND t.RM31 = 'A'	-- A: 結案
	--AND t.RM02 = 'HB04'
ORDER BY t.RM07_1, t.RM09