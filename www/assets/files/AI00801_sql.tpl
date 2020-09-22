select LPAD(A.HD48, 4, '0') || LPAD(A.HD49, 8, '0') || LPAD(A.HA48, 4, '0') ||
       LPAD(A.HA49, 8, '0') AS FORMATTED
  from SRHD10 A
where
  A.HD48 in (##REPLACEMENT##)
order by A.HD48, A.HD49