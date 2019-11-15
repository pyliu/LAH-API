SELECT t.MD01             AS "收件年",
       t.MD02             AS "收件字",
       t.MD03             AS "收件號",
       t.MD06             AS "段小段",
	   t.MD07             AS "地建別",
       t.MD08             AS "地建號",
       r.SR_TYPE          AS "申請類別",
       r.SR09             AS "申請人統編",
       r.SR10             AS "申請人姓名",
       r.SR_AGENT_ID      AS "代理人統編",
       r.SR_AGENT_NAME    AS "代理人姓名",
       r.SR_SUBAGENT_ID   AS "複代理人統編",
       r.SR_SUBAGENT_NAME AS "複代理人姓名",
       r.SR08             AS "LOG時間",
       r.SR_METHOD        AS "申請方式",
       (CASE
		WHEN t.MD04 = 'A' THEN '登記電子資料謄本'
		WHEN t.MD04 = 'B' THEN '地價電子資料謄本'
		WHEN t.MD04 = 'C' THEN '地籍圖謄本'
		WHEN t.MD04 = 'D' THEN '建物平面圖謄本'
		WHEN t.MD04 = 'E' THEN '人工登記簿謄本'
		WHEN t.MD04 = 'F' THEN '閱覽'
		WHEN t.MD04 = 'G' THEN '列印電子資料'
		WHEN t.MD04 = 'H' THEN '申請'
		WHEN t.MD04 = 'I' THEN '測量'
		WHEN t.MD04 = 'J' THEN '異動索引'
		WHEN t.MD04 = 'K' THEN '土地建物異動清冊'
		WHEN t.MD04 = 'L' THEN '代理人申請登記案件明細表'
		WHEN t.MD04 = 'M' THEN '土地參考資訊'
                ELSE t.MD04
       END) AS "謄本種類",
       (CASE
		WHEN t.MD05 = 'A' THEN '全部'
		WHEN t.MD05 = 'B' THEN '所有權個人全部'
		WHEN t.MD05 = 'C' THEN '標示部'
		WHEN t.MD05 = 'D' THEN '所有權部'
		WHEN t.MD05 = 'E' THEN '他項權利部'
		WHEN t.MD05 = 'F' THEN '標示部及所有權部'
		WHEN t.MD05 = 'G' THEN '標示部及他項權利部'
		WHEN t.MD05 = 'H' THEN '他項權利部之個人'
		WHEN t.MD05 = 'I' THEN '他項權利個人全部'
		WHEN t.MD05 = 'J' THEN '標示部'
		WHEN t.MD05 = 'K' THEN '所有權部'
		WHEN t.MD05 = 'KA' THEN '收件年期字號'
		WHEN t.MD05 = 'KB' THEN '收件年字號+登序'
		WHEN t.MD05 = 'KC' THEN '收件年字號+統編'
		WHEN t.MD05 = 'KD' THEN '收件年字號+姓名'
		WHEN t.MD05 = 'L' THEN '標示部及所有權部'
		WHEN t.MD05 = 'LA' THEN '統計起始日期+統計終止日期+代理人統一編號'
		WHEN t.MD05 = 'LB' THEN '統計起始日期+統計終止日期'
		WHEN t.MD05 = 'LC' THEN '代理人統一編號'
		WHEN t.MD05 = 'M' THEN '標示部(標示部不存在)'
		WHEN t.MD05 = 'N' THEN '所有權部(標示部不存在)'
		WHEN t.MD05 = 'O' THEN '標示部及所有權部(標示部不存在)'
		WHEN t.MD05 = 'P' THEN '資料庫不存在'
		WHEN t.MD05 = 'Q' THEN '地籍圖謄本'
		WHEN t.MD05 = 'R' THEN '建物平面圖謄本'
		WHEN t.MD05 = 'S' THEN '登記簿謄本'
		WHEN t.MD05 = 'T' THEN '閱覽'
		WHEN t.MD05 = 'U' THEN '列印電子資料'
		WHEN t.MD05 = 'V' THEN '申請'
		WHEN t.MD05 = 'W' THEN '測量 '
		WHEN t.MD05 = 'X' THEN '異動索引'
		WHEN t.MD05 = 'XA' THEN '收件年期字號'
		WHEN t.MD05 = 'XB' THEN '地建號'
		WHEN t.MD05 = 'XC' THEN '地建號+登序'
		WHEN t.MD05 = 'XD' THEN '地建號+部別'
		WHEN t.MD05 = 'XE' THEN '地建號+統編'
		ELSE t.MD05
       END) AS "謄本項目"
  FROM MOICAS.CUSMD2 t
 INNER JOIN MOICAS.RSCNRL r
    ON (t.MD03 = r.SR03)
   AND (t.MD02 = r.SR02)
   AND (t.MD01 = r.SR01)
-- WHERE r.SR09 ='J000000000';
-- WHERE r.SR10 LIKE '星展%' AND t.MD06 = '0223'
 WHERE t.MD06 in ( '0226' )
   AND t.MD08 in ('00151000');