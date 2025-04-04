SELECT
  NVL(a.BA48, '    ') ||
  NVL(a.BA49, '        ') ||
  NVL(a.BB01, '    ') ||
  LPAD(NVL(a.BB05, ' '), 7, ' ') ||
  NVL(a.BB06, '  ') ||
  LPAD(NVL(a.BB07, ' '), 7, ' ') ||
  LPAD(a.BB09, 10, ' ') ||
  NVL(a.BB15_1, ' ') ||
  LPAD(NVL(TO_CHAR(a.BB15_2), ' '), 10, ' ') ||
  LPAD(NVL(TO_CHAR(a.BB15_3), ' '), 10, ' ') ||
  LPAD(NVL(a.BB16, ' '), 10, ' ') ||
  LPAD((CASE
    WHEN a.BB21 IS NULL THEN ' '
    ELSE TO_CHAR(a.BB21 * 10)
  END), 8, ' ') ||
  RPAD(NVL(b.LNAM, ' '), 60, ' ') ||
  RPAD(NVL(b.LADR, ' '), 60, ' ')
  AS AI00401
FROM SRBLOW a, SRLNID b
WHERE
a.BA48 in (##REPLACEMENT##)
   AND (b.LIDN = a.BB09)
 ORDER BY a.BA48, a.BA49