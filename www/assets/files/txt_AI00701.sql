SELECT LPAD( DD45, 1, ' ' ) || LPAD( DD46, 2, '0' ) || LPAD( DD48, 4, '0' ) || LPAD(DD49, 8, ' ') || LPAD(DD05, 7, ' ') || LPAD(DD06, 2, ' ') || LPAD(DD08*100, 8, ' ' ) || RPAD(CASE
       WHEN DD09 IS NULL THEN ' '
       WHEN DD09 = '' THEN ' '
       ELSE DD09
END, 60, ' ' ) || LPAD(DD11, 1, ' ') || LPAD(DD12, 2, ' ') || LPAD(DD13, 3 , '0') || LPAD(DD16, 7, ' ') AS AI00701 FROM SRDBID
WHERE
--DD48 in ('0362', '0363')    -- A20
-- DD48 in ('0200', '0202', '0205', '0210') -- A21
-- DD48 in ('0255') -- 草漯
-- DD48 in ('0255', '0275', '0277', '0278', '0377') -- 草漯UNIT3
-- DD48 in ('0255', '0377', '0392') -- 草漯UNIT6
--(DD48 || DD49 between '031800000000' and '032299999999') -- 中壢運動公園
--DD48 in ('0213', '0222')  -- 中原
ORDER BY DD48, DD49