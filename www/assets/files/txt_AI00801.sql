select LPAD(A.HD48, 4, '0') || LPAD(A.HD49, 8, '0') || LPAD(A.HA48, 4, '0') ||
       LPAD(A.HA49, 8, '0') AS FORMATTED
  from SRHD10 A
where
-- (A.HD48||A.HD49 between '036200000000' and '036399999999')
A.HD48 in (LPAD('0362', 4, '0'), LPAD('0363', 4, '0'))
order by A.HD48, A.HD49