SELECT DISTINCT -- REGA 登記主檔－土地建物登記統計檔 (SREGA)
  RA03 AS "收件年",
  RA04_1 AS "收件字",
  RA04_2 AS "收件號",
  RA40 AS "註記日期",
  ITEM AS "項目代碼",
  RECA AS "土地筆數",
  RA10 AS "面積",
  RECD AS "建物筆數",
  RA08 AS "面積",
  (CASE
    WHEN t.RE46 = '03' THEN '中壢區'
    WHEN t.RE46 = '12' THEN '觀音區'
    ELSE t.RE46
END)  AS "鄉鎮市區",
  AA16T AS "土地公告現值總額"
FROM MOICAD.REGA t
WHERE t.RA40 LIKE '10904%' and t.RE46 = '03' -- 03 中壢, 12 觀音
ORDER BY t.RA40;