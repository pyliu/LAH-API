select AA48 AS "段代碼", m.KCNT AS "段名稱", SUM(AA10) AS "面積", COUNT(AA10) AS "筆數"
  from SRALID t
  LEFT JOIN MOIADM.RKEYN m
            on (m.KCDE_1 = '48' and m.KCDE_2 = t.AA48)
--where aa48 = '0313' or aa48 = '0315' or aa48 = '0316'
 group by t.AA48, m.KCNT