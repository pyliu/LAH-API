<?php
// 所別
return "(CASE
        WHEN RM101 = 'HA' THEN '桃園'
        WHEN RM101 = 'HB' THEN '中壢'
        WHEN RM101 = 'HC' THEN '大溪'
        WHEN RM101 = 'HD' THEN '楊梅'
        WHEN RM101 = 'HE' THEN '蘆竹'
        WHEN RM101 = 'HF' THEN '八德'
        WHEN RM101 = 'HG' THEN '平鎮'
        WHEN RM101 = 'HH' THEN '龜山'
        ELSE RM101
 END)";
 