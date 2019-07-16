select A.OD48_S || A.OD49 || A.OD48 || A.OD49_S || NVL(A.OD31_1, ' ') || LPAD(A.OD31_2, 10, ' ') || LPAD(A.OD31_3, 10, ' ') AS AI01001
  from SROD31 A
 where
    -- (A.OD48 || A.OD49 between '036200000000' and '036399999999')
    A.OD48 in ('0362', '0363')
 order by A.OD48, A.OD49