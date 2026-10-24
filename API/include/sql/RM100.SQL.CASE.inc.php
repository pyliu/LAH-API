<?php
// 所別
return "(CASE
        WHEN RM100 = 'HA' THEN '桃園'
        WHEN RM100 = 'HB' THEN '中壢'
        WHEN RM100 = 'HC' THEN '大溪'
        WHEN RM100 = 'HD' THEN '楊梅'
        WHEN RM100 = 'HE' THEN '蘆竹'
        WHEN RM100 = 'HF' THEN '八德'
        WHEN RM100 = 'HG' THEN '平鎮'
        WHEN RM100 = 'HH' THEN '龜山'
        ELSE RM100
 END)";
 