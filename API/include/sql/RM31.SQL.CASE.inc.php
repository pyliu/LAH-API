<?php
return "(CASE
    WHEN RM31 = 'A' THEN '結案'
    WHEN RM31 = 'B' THEN '撤回'
    WHEN RM31 = 'C' THEN '併案'
    WHEN RM31 = 'D' THEN '駁回'
    WHEN RM31 = 'E' THEN '請示'
    ELSE RM31
END)";
