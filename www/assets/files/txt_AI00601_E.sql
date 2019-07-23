select a.MM00 || a.MM48 || a.MM49 || LPAD(a.MM01, 4, '0') ||
       LPAD(b.EE09, 10, ' ') || LPAD(a.MM13, 10, ' ') ||
       RPAD(c.LNAM, 60, ' ') || RPAD(c.LADR, 60, ' ') AS AI00601_E
  from SRMNGR a, SREBOW b, SRLNID c
 where
   a.MM48 in ('0362', '0363')
    -- a.MM48 in ('0200', '0202', '0205', '0210') -- A21
    -- a.MM48 in ('0255') -- 草漯
    -- a.MM48 in ('0255', '0275', '0277', '0278', '0377') -- 草漯UNIT3
    -- a.MM48 in ('0255', '0377', '0392') -- 草漯UNIT6
    --(a.MM48 || a.MM49 between '031800000000' and '032299999999') -- 中壢運動公園
   and (a.MM00 = 'E')
   and (b.ED48 = a.MM48)
   and (b.ED49 = a.MM49)
   and (b.EE01 = a.MM01)
   and (c.LIDN = a.MM13)
 order by a.MM48, a.MM49