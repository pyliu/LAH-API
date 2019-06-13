SELECT SQ.RM01,
       SQ.RM02,
       SQ.RM03,
       SQ.RM09,
       k.KCNT,
       SQ.RM07_1,
       SQ.RM58_1,
       SQ.RM18,
       SQ.RM19,
       SQ.RM21,
       SQ.RM22,
       SQ.RM30,
       SQ.RM31
  FROM (SELECT *
          FROM MOICAD.RLNID p, MOICAS.CRSMS tt
         WHERE tt.RM07_1 LIKE '10805%'
           AND p.LCDE In ('2', '8', 'C', 'D')
           AND (tt.RM18 = p.LIDN OR tt.RM21 = p.LIDN)) SQ
  LEFT JOIN MOICAD.RKEYN k
    ON k.KCDE_2 = SQ.RM09
 WHERE k.KCDE_1 = '06'