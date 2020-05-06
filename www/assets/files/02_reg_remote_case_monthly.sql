SELECT
      t.RM01,
      t.RM02,
      t.RM03,
      t.RM09,
      w.KCNT,
      t.RM07_1,
      u.LIDN,
      u.LNAM,
      u.LADR,
      v.AB01,
      v.AB02,
      v.AB03,
      t.RM13,
      t.RM16
FROM MOICAS.CRSMS t
    LEFT JOIN MOICAD.RLNID u ON t.RM18 = u.LIDN
    LEFT JOIN MOICAS.CABRP v ON t.RM24 = v.AB01
    LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'
WHERE t.RM07_1 LIKE '10904%'
      AND (u.LADR NOT LIKE '%桃園市%' AND u.LADR NOT LIKE '%桃園縣%')
      AND (v.AB03 NOT LIKE '%桃園市%' AND v.AB03 NOT LIKE '%桃園縣%')