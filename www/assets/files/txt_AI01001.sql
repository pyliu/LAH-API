select A.OD48_S || A.OD49 || A.OD48 || A.OD49_S || NVL(A.OD31_1, ' ') || LPAD(A.OD31_2, 10, ' ') || LPAD(A.OD31_3, 10, ' ') AS AI01001
  from SROD31 A
 where
    A.OD48 in ('0362', '0363')   -- A20
    -- A.OD48 in ('0200', '0202', '0205', '0210') -- A21
    -- A.OD48 in ('0255') -- 草漯
    -- A.OD48 in ('0255', '0275', '0277', '0278', '0377') -- 草漯UNIT3
    -- A.OD48 in ('0255', '0377', '0392') -- 草漯UNIT6
    --(A.OD48 || A.OD49 between '031800000000' and '032299999999') -- 中壢運動公園
 order by A.OD48, A.OD49