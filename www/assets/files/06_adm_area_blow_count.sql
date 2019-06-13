SELECT q.AA48 AS "段代碼", q.KCNT AS "段名稱", COUNT(*) AS "土地標示部筆數"
FROM (
     SELECT t.AA48, m.KCNT
     FROM MOICAD.RALID t
     LEFT JOIN MOIADM.RKEYN m on (m.KCDE_1 = '48' and m.KCDE_2 = t.AA48)
) q
GROUP BY q.AA48, q.KCNT