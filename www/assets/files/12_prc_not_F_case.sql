SELECT
  t.ss03,
  t.ss04_1,
  t.ss04_2,
  t.ss05,
  t.ss06 || '：' || q.kcnt AS "登記原因",
  t.ss07,
  (CASE
      WHEN t.SP_CODE = 'B' THEN 'B：登記中' 
      WHEN t.SP_CODE = 'R' THEN 'R：登錄完成' 
      WHEN t.SP_CODE = 'D' THEN 'D：校對中' 
      WHEN t.SP_CODE = 'C' THEN 'C：校對正確' 
      WHEN t.SP_CODE = 'E' THEN 'E：校對有誤' 
      WHEN t.SP_CODE = 'S' THEN 'S：異動開始' 
      WHEN t.SP_CODE = 'G' THEN 'G：異動有誤' 
      WHEN t.SP_CODE = 'F' THEN 'F：異動完成' 
  END) AS "案件情況",
  t.sp_date || ' ' || t.sp_time AS "異動時間",
  t.ss09,
  (CASE
      WHEN t.SS100 = 'HA' THEN 'HA：桃園' 
      WHEN t.SS100 = 'HB' THEN 'HB：中壢' 
      WHEN t.SS100 = 'HC' THEN 'HC：大溪' 
      WHEN t.SS100 = 'HD' THEN 'HD：楊梅' 
      WHEN t.SS100 = 'HE' THEN 'HE：蘆竹' 
      WHEN t.SS100 = 'HF' THEN 'HF：八德' 
      WHEN t.SS100 = 'HG' THEN 'HG：平鎮' 
      WHEN t.SS100 = 'HH' THEN 'HH：龜山' 
  END) AS "資料管轄所",
  (CASE
      WHEN t.SS101 = 'HA' THEN 'HA：桃園' 
      WHEN t.SS101 = 'HB' THEN 'HB：中壢' 
      WHEN t.SS101 = 'HC' THEN 'HC：大溪' 
      WHEN t.SS101 = 'HD' THEN 'HD：楊梅' 
      WHEN t.SS101 = 'HE' THEN 'HE：蘆竹' 
      WHEN t.SS101 = 'HF' THEN 'HF：八德' 
      WHEN t.SS101 = 'HG' THEN 'HG：平鎮' 
      WHEN t.SS101 = 'HH' THEN 'HH：龜山' 
  END) AS "資料收件所"
FROM MOIPRC.PSCRN t
LEFT JOIN SRKEYN q ON t.SS06  = q.kcde_2 AND q.kcde_1 = '06'
WHERE
  SP_DATE LIKE '10904%' AND
  SP_CODE <> 'F'