select MU11 AS "收件人員代碼", COUNT(*) AS "總量"
  from MOICAS.CUSMM t
 where MU11 in ('HB0227', 'HB0506') -- HB0227 自強鍾嘉萍 HB0506 觀音劉淑慧
   and MU12 like '109%'
 group by MU11