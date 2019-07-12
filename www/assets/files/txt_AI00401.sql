select a.BA48 || a.BA49 || a.BB01 ||
       LPAD(CASE
            WHEN a.BB05 IS NULL THEN ' '
             WHEN a.BB05 = '' THEN ' '
             ELSE a.BB05
        END, 7, ' ') ||
       a.BB06 ||
       LPAD((CASE
             WHEN a.BB07 IS NULL THEN ' '
             WHEN a.BB07 = '' THEN ' '
             ELSE a.BB07
        END), 7, ' ') ||
       LPAD(a.BB09, 10, ' ') ||
       (CASE
             WHEN a.BB15_1 IS NULL THEN ' '
             WHEN a.BB15_1 = '' THEN ' '
             ELSE a.BB15_1
        END) ||
       LPAD(a.BB15_2, 10, ' ') ||
       LPAD(a.BB15_3, 10, ' ') ||
       LPAD((CASE
             WHEN a.BB16 IS NULL THEN ' '
             WHEN a.BB16 = '' THEN ' '
             ELSE a.BB16
        END), 10, ' ') ||
       LPAD(a.BB21 * 10, 8, ' ') ||
       RPAD(b.LNAM, 60, ' ') ||
       RPAD(b.LADR, 60, ' ')
       AS AI00401
  FROM SRBLOW a, SRLNID b
 WHERE --(a.BA48 || a.BA49 BETWEEN '036200000000' AND '036399999999')
   a.BA48 in ('0362', '0363') 
   AND (b.LIDN = a.BB09)
 ORDER BY a.BA48, a.BA49