select LPAD(JD48, 4, '0') || JD49 || (CASE
    WHEN J1415C = 'M' THEN '1'
    WHEN J1415C = 'S' THEN '2'
    ELSE J1415C
 END) || LPAD(JD14_1, 3, ' ') || LPAD(JD14_2 * 100, 7, ' ')
  from SRJD14
 where
  JD48 in (##REPLACEMENT##)
 order by JD48, JD49
