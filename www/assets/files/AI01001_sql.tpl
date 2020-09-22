select A.OD48_S || A.OD49 || A.OD48 || A.OD49_S || NVL(A.OD31_1, ' ') || LPAD(A.OD31_2, 10, ' ') || LPAD(A.OD31_3, 10, ' ') AS AI01001
  from SROD31 A
 where
  A.OD48 in (##REPLACEMENT##)
 order by A.OD48, A.OD49