select COUNT(*) AS "因雨延期測量案件數" from SCMSMS t
left join SCMSDS q on MM01 = MD01 and MM02 = MD02 and MM03 = MD03
where (t.MM04_1 between '1090401' and '1090430')
and MD12 = '1'