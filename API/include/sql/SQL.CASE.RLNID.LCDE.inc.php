<?php
// 人別
return "(CASE
    WHEN LCDE = '1' THEN '本國人'
    WHEN LCDE = '2' THEN '外國人'
    WHEN LCDE = '3' THEN '國有（中央機關）'
    WHEN LCDE = '4' THEN '省市有（省市機關）'
    WHEN LCDE = '5' THEN '縣市有（縣市機關）'
    WHEN LCDE = '6' THEN '鄉鎮市有（鄉鎮市機關）'
    WHEN LCDE = '7' THEN '本國私法人'
    WHEN LCDE = '8' THEN '外國法人'
    WHEN LCDE = '9' THEN '祭祀公業'
    WHEN LCDE = 'A' THEN '其他'
    WHEN LCDE = 'B' THEN '銀行法人'
    WHEN LCDE = 'C' THEN '大陸地區自然人'
    WHEN LCDE = 'D' THEN '大陸地區法人'
    ELSE LCDE
 END)";
 