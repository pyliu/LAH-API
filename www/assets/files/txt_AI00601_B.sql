select A.MM00 || A.MM48 || A.MM49 || A.MM01 ||
    LPAD(B.BB09, 10, ' ') ||
    LPAD(A.MM13, 10, ' ') ||
    RPAD(NVL(C.LNAM, ' '), 60, ' ') ||
    RPAD(NVL(C.LADR, ' '), 60, ' ') AS AI00601_B
  from SRMNGR A, SRBLOW B, SRLNID C
 where --(A.MM48 || A.MM49 between '036200000000' and '036399999999')
--a.MM48 in ('0362', '0363') -- A20
-- a.MM48 in ('0200', '0202', '0205', '0210') -- A21
-- a.MM48 in ('0255') -- 草漯
-- a.MM48 in ('0255', '0275', '0277', '0278', '0377') -- 草漯UNIT3
-- a.MM48 in ('0255', '0377', '0392') -- 草漯UNIT6
--(a.MM48 || a.MM49 between '031800000000' and '032299999999') -- 中壢運動公園
--a.MM48 in ('0213', '0222')  -- 中原
   and (A.MM00 = 'B')
   and (B.BA48 = A.MM48)
   and (B.BA49 = A.MM49)
   and (B.BB01 = A.MM01)
   and (C.LIDN = A.MM13)
 order by A.MM48, A.MM49