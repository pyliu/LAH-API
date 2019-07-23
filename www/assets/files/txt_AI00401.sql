select a.BA48 || a.BA49 || a.BB01 ||
--碰到有罕字會有問題，請 Ctrl+a 複製SQL去PL/SQL Developer匯出
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
 WHERE
   a.BA48 in ('0362', '0363')
    -- a.BA48 in ('0200', '0202', '0205', '0210') -- A21
    -- a.BA48 in ('0255') -- 草漯
    -- a.BA48 in ('0255', '0275', '0277', '0278', '0377') -- 草漯UNIT3
    -- a.BA48 in ('0255', '0377', '0392') -- 草漯UNIT6
    --(a.BA48 || a.BA49 between '031800000000' and '032299999999') -- 中壢運動公園
   AND (b.LIDN = a.BB09)
 ORDER BY a.BA48, a.BA49