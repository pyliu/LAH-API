SELECT DISTINCT
  t.RM01   AS "收件年",
  t.RM02   AS "收件字",
  t.RM03   AS "收件號",
  t.RM09   AS "登記原因代碼",
  r.KCNT    AS "登記原因",
  t.RM07_1 AS "收件日期",
  t.RM58_1 AS "結案日期",
  t.RM56_1 AS "校對日期",
  t.RM18   AS "權利人統一編號",
  t.RM19   AS "權利人姓名",
  t.RM21   AS "義務人統一編號",
  t.RM22   AS "義務人姓名",
  (CASE
    WHEN q.LCDE = '1' THEN '本國人'
    WHEN q.LCDE = '2' THEN '外國人'
    WHEN q.LCDE = '3' THEN '國有（中央機關）'
    WHEN q.LCDE = '4' THEN '省市有（省市機關）'
    WHEN q.LCDE = '5' THEN '縣市有（縣市機關）'
    WHEN q.LCDE = '6' THEN '鄉鎮市有（鄉鎮市機關）'
    WHEN q.LCDE = '7' THEN '本國私法人'
    WHEN q.LCDE = '8' THEN '外國法人'
    WHEN q.LCDE = '9' THEN '祭祀公業'
    WHEN q.LCDE = 'A' THEN '其他'
    WHEN q.LCDE = 'B' THEN '銀行法人'
    WHEN q.LCDE = 'C' THEN '大陸地區自然人'
    WHEN q.LCDE = 'D' THEN '大陸地區法人'
    ELSE q.LCDE
  END) AS "權利/義務人別",
  (CASE
    WHEN t.RM30 = 'A' THEN '初審'
    WHEN t.RM30 = 'B' THEN '複審'
    WHEN t.RM30 = 'H' THEN '公告'
    WHEN t.RM30 = 'I' THEN '補正'
    WHEN t.RM30 = 'R' THEN '登錄'
    WHEN t.RM30 = 'C' THEN '校對'
    WHEN t.RM30 = 'U' THEN '異動完成'
    WHEN t.RM30 = 'F' THEN '結案'
    WHEN t.RM30 = 'X' THEN '補正初核'
    WHEN t.RM30 = 'Y' THEN '駁回初核'
    WHEN t.RM30 = 'J' THEN '撤回初核'
    WHEN t.RM30 = 'K' THEN '撤回'
    WHEN t.RM30 = 'Z' THEN '歸檔'
    WHEN t.RM30 = 'N' THEN '駁回'
    WHEN t.RM30 = 'L' THEN '公告初核'
    WHEN t.RM30 = 'E' THEN '請示'
    WHEN t.RM30 = 'D' THEN '展期'
    ELSE t.RM30
  END) AS "辦理情形",
  (CASE
    WHEN t.RM31 = 'A' THEN '結案'
    WHEN t.RM31 = 'B' THEN '撤回'
    WHEN t.RM31 = 'C' THEN '併案'
    WHEN t.RM31 = 'D' THEN '駁回'
    WHEN t.RM31 = 'E' THEN '請示'
    ELSE t.RM31
  END) AS "結案與否"
FROM
  -- CRSMS 只找相同年份的買賣、贈與(校對時間)之案件以加快查詢速度
  (select * from MOICAS.CRSMS where RM07_1 LIKE '109%' AND RM09 in ('64', '65') AND RM56_1 LIKE '10904%') t,
  (select * from MOICAD.RLNID p where p.LCDE in ('2', '8', 'C', 'D') ) q, -- 代碼檔 09, 外國 人/法人
  (select * from MOICAD.RKEYN k where k.KCDE_1 = '06') r  -- 代碼檔 06，登記原因
WHERE ( t.RM18 = q.LIDN OR t.RM21 = q.LIDN ) AND r.KCDE_2 = t.RM09