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
--a.ED48 in ('0362', '0363') -- A20
-- a.ED48 in ('0200', '0202', '0205', '0210') -- A21
-- a.ED48 in ('0255') -- 草漯
-- a.ED48 in ('0255', '0275', '0277', '0278', '0377') -- 草漯UNIT3
-- a.ED48 in ('0255', '0377', '0392') -- 草漯UNIT6
--(a.ED48 || a.ED49 between '031800000000' and '032299999999') -- 中壢運動公園
--a.ED48 in ('0213', '0222')  -- 中原
   AND (b.LIDN = a.EE09)