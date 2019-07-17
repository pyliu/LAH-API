SELECT t.rm01, t.rm02, t.rm03, t.rm07_1, t.rm07_2, t.rm09, s.kcnt, t.rm30
FROM SCRSMS t
LEFT JOIN SRKEYN s
  ON t.rm09 = s.kcde_2
WHERE s.kcde_1 = '06'
  AND RM07_1 LIKE '10807%'
ORDER BY RM09