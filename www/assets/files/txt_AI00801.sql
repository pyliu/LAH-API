select LPAD(A.HD48, 4, '0') || LPAD(A.HD49, 8, '0') || LPAD(A.HA48, 4, '0') ||
       LPAD(A.HA49, 8, '0') AS FORMATTED
  from SRHD10 A
where
--A.HD48 in ('0362', '0363')  -- A20
-- A.HD48 in ('0200', '0202', '0205', '0210') -- A21
-- A.HD48 in ('0255') -- 草漯
-- A.HD48 in ('0255', '0275', '0277', '0278', '0377') -- 草漯UNIT3
-- A.HD48 in ('0255', '0377', '0392') -- 草漯UNIT6
--(A.HD48 || A.HD49 between '031800000000' and '032299999999') -- 中壢運動公園
--A.HD48 in ('0213', '0222')  -- 中原
order by A.HD48, A.HD49