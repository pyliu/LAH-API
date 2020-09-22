select a.MM00 || a.MM48 || a.MM49 ||
    LPAD(a.MM01, 4, '0') ||
    LPAD(b.EE09, 10, ' ') ||
    LPAD(a.MM13, 10, ' ') ||
    RPAD(NVL(c.LNAM, ' '), 60, ' ') ||
    RPAD(NVL(c.LADR, ' '), 60, ' ') AS AI00601_E
  from SRMNGR a, SREBOW b, SRLNID c
 where
  a.MM48 in (##REPLACEMENT##)
   and (a.MM00 = 'E')
   and (b.ED48 = a.MM48)
   and (b.ED49 = a.MM49)
   and (b.EE01 = a.MM01)
   and (c.LIDN = a.MM13)
 order by a.MM48, a.MM49