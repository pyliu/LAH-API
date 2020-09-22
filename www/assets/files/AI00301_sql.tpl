SELECT ( A.AA45 || A.AA46 || A.AA48 || A.AA49 ||
  NVL(A.AA05, '       ') ||
  NVL(A.AA06, '  ') ||
  NVL(A.AA08, ' ') ||
  LPAD(NVL(A.AA09, ' '), 2, ' ') ||
  LPAD(A.AA10 * 100, 9, ' ') ||
  LPAD(NVL(A.AA11, ' '), 2, ' ') ||
  LPAD(NVL(A.AA12, ' '), 2, ' ') ||
  CASE
    WHEN LPAD(NVL(A.AA16, 0), 7, ' ') = '      0' THEN '       '
    ELSE LPAD(NVL(A.AA16, 0), 7, ' ')
  END || -- AA16 土地公告現值
  CASE
    WHEN LPAD(NVL(A.AA17, 0), 7, ' ') = '      0' THEN '       '
    ELSE LPAD(NVL(A.AA17, 0), 7, ' ')
  END || -- AA17 公告地價
  LPAD(A.AA21 * 1000, 10, ' ') ||
  LPAD(A.AA22 * 1000, 10, ' ') ||
  CASE
    WHEN A.AA23 IS NULL THEN '   '
    WHEN A.AA23 = '' THEN '   '
    ELSE LPAD(A.AA23, 3, ' ')
  END
) AS AI03001
  from SRALID A
 where
 A.AA48 in (##REPLACEMENT##)
 order BY A.AA48, A.AA49