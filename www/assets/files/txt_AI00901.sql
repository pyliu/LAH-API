select LPAD(JD48, 4, '0') || JD49 || (CASE
    WHEN J1415C = 'M' THEN '1'
    WHEN J1415C = 'S' THEN '2'
    ELSE J1415C
 END) || LPAD(JD14_1, 3, ' ') || LPAD(JD14_2 * 100, 7, ' ')
  from SRJD14
 where
--JD48 in ('0362', '0363')  -- A20
-- JD48 in ('0200', '0202', '0205', '0210') -- A21
-- JD48 in ('0255') -- 草漯
-- JD48 in ('0255', '0275', '0277', '0278', '0377') -- 草漯UNIT3
-- JD48 in ('0255', '0377', '0392') -- 草漯UNIT6
--(JD48 || JD49 between '031800000000' and '032299999999') -- 中壢運動公園
--JD48 in ('0213', '0222')  -- 中原
 order by JD48, JD49
