SELECT a.ED48 ||
       a.ED49 ||
       LPAD(a.EE01, 4, '0') ||
       LPAD(a.EE05, 7, '0') ||
       a.EE06 ||
       LPAD(NVL(a.EE07, ' '), 7, ' ') ||
       LPAD(a.EE09, 10, ' ') ||
       NVL(a.EE15_1, ' ') ||
       LPAD(NVL(TO_CHAR(a.EE15_2), ' '), 10, ' ') ||
       LPAD(NVL(TO_CHAR(a.EE15_3), ' '), 10, ' ') ||
       LPAD(NVL(a.EE16, ' '), 10, ' ') ||
       RPAD(NVL(b.LNAM, ' '), 60, ' ') ||
       RPAD (NVL(b.LADR, ' '), 60, ' ') AS AI01101
  FROM SREBOW a, SRLNID b
 WHERE
  a.ED48 in (##REPLACEMENT##)
   AND (b.LIDN = a.EE09)