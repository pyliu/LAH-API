--碰到有罕字會有問題，請複製下面SQL去PL/SQL Developer匯出
select a.BA48 || a.BA49 || a.BB01 ||
       LPAD(NVL(a.BB05, ' '), 7, ' ') ||
       a.BB06 ||
       LPAD(NVL(a.BB07, ' '), 7, ' ') ||
       LPAD(a.BB09, 10, ' ') ||
       NVL(a.BB15_1, ' ') ||
       LPAD(a.BB15_2, 10, ' ') ||
       LPAD(a.BB15_3, 10, ' ') ||
       LPAD(NVL(a.BB16, ' '), 10, ' ') ||
       LPAD(a.BB21 * 10, 8, ' ') ||
       RPAD(b.LNAM, 60, ' ') ||
       RPAD(b.LADR, 60, ' ')
       AS AI00401
  FROM SRBLOW a, SRLNID b
 WHERE (a.BA48 || a.BA49 BETWEEN '036200000000' AND '036399999999')
   AND (b.LIDN = a.BB09)
 ORDER BY a.BA48, a.BA49