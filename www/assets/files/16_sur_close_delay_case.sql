select t.*, s.*, u.KCNT
from MOICAS.CMSMS t
left join MOICAS.CMSDS s
  on t.mm01 = s.md01
  and t.mm02 = s.md02
  and t.mm03 = s.md03
left join MOIADM.RKEYN u
  on t.mm06 = u.kcde_2
  and u.kcde_1 = 'M3'
where
  t.mm22 = 'C' 
  and t.mm23 in ('A', 'B', 'C', 'F')
order by t.mm01, t.mm02, t.mm03