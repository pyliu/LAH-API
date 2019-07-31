SELECT -- 土地清冊
       LTRIM(SUBSTR(t.BA49, 0, 4), '0') || '-' || LTRIM(SUBSTR(t.BA49, 5, 4), '0') AS "地號",
       NVL(q.aa11, '住宅區') AS "使用分區",
       q.AA16 "公告現值",
       q.AA10 AS "面積(平方公尺)",
       q.AA10 * 3025 / 10000 AS "坪數",
       --t.bb09 AS "所有權人統編",
       w.lnam AS "所有權人",
       (CASE
          WHEN t.bb15_1 = 'A' THEN '全部'
          WHEN t.bb15_1 = 'B' THEN '共同共有'
          WHEN t.bb15_1 = 'C' THEN '見地價備註事項'
          WHEN t.bb15_1 = 'Z' THEN '見其他登記事項'
          ELSE t.bb15_1
        END) AS "權利範圍類別",
       t.bb15_3 || '/' || t.bb15_2 AS "權利範圍"
  FROM MOICAD.RBLOW t
  LEFT JOIN MOICAD.RALID q
    ON t.Ba48 = q.aa48
   and t.ba49 = q.aa49
  LEFT JOIN MOICAD.RLNID w
    ON t.bb09 = w.lidn
 WHERE t.ba48 = '0334' AND         -- BA48 段小段代碼
       (t.ba49 in (    -- BA49 地建號
       '00020001',
       '00020066',
       '07250005',
       '07270004'
    ) OR t.ba49 BETWEEN '00020019' AND '00020041')
 ORDER BY BA49