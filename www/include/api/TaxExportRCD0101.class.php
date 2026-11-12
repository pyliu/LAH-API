<?php

require_once __DIR__ . '/TaxExportPdfWriter.class.php';

final class TaxExportRCD0101 {
    /**
     * @var callable|null
     */
    private $dbSelector;

    /**
     * 代碼表快取 [kcde_1 => [kcde_2 => [KCNT, KRMK]]]
     * @var array
     */
    private $keynCache = [];

    public function __construct($dbSelector = null) {
        $this->dbSelector = $dbSelector;
    }

    /**
     * 執行地籍異動通知書 (RCD0101) PDF 產製
     *
     * @param string $path 暫存輸出路徑 (.part)
     * @param array $item 項目定義 (含 code 等)
     * @param array $batch 批次資訊 (含 begin, end, mock 等)
     * @return array ['state' => 'success'|'empty'|'failed', 'message' => string]
     */
    public function export(string $path, array $item, array $batch): array {
        $isMock = !empty($batch['mock']);
        if ($isMock || ($this->dbSelector === null && $this->shouldFallbackToMock())) {
            return $this->exportMock($path, $item, $batch);
        }

        return $this->exportDirectOracle($path, $item, $batch);
    }

    /**
     * MOCK 模式產製合成通知書 PDF
     */
    private function exportMock(string $path, array $item, array $batch): array {
        $pdf = new TaxExportPdfWriter('L', 'mm', 'A4');
        $code = $item['code'] ?? 'RCD0101_LOCAL';
        $isRemote = ($code === 'RCD0101_REMOTE');

        $beginRoc = self::rocDate($batch['begin'] ?? '2026-10-01');
        $endRoc = self::rocDate($batch['end'] ?? '2026-10-31');

        $officeName = $isRemote ? '桃園(跨)' : '桃園';
        $fullOfficeTitle = '桃園市' . $officeName . '地政事務所土地地籍異動通知書';

        // 產製第 1 頁案件
        $caseData1 = [
            'title' => $fullOfficeTitle,
            'office' => $officeName,
            'rec_no' => ($isRemote ? '桃跨' : '桃資') . ' 字 第 000001 號',
            'change_date' => self::formatRocDateLong($endRoc),
            'sections' => [
                [
                    'item' => 1,
                    'town' => '桃園區',
                    'section' => '中路段',
                    'land_no' => '0101-0000',
                    'cause' => '買賣',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc),
                    'note' => '分割自 0101-0000 地號',
                    'details' => [
                        [
                            'type' => '原',
                            'category' => '田',
                            'grade' => '01',
                            'area' => '4646.00',
                            'zone' => '農業區',
                            'usage' => '農牧用地',
                            'value' => '12000.0',
                            'remark' => '一般農業區'
                        ],
                        [
                            'type' => '現',
                            'category' => '建',
                            'grade' => '01',
                            'area' => '4646.00',
                            'zone' => '住宅區',
                            'usage' => '建築用地',
                            'value' => '13500.0',
                            'remark' => '辦理變更使用'
                        ]
                    ]
                ]
            ],
            'owners' => [
                [
                    'item' => 1,
                    'type' => '原',
                    'name' => '王大明',
                    'id_no' => 'A123456789',
                    'scope' => '全部 1/1',
                    'price' => '3500.0',
                    'addr' => '桃園市桃園區中山路1號'
                ],
                [
                    'item' => 2,
                    'type' => '現',
                    'name' => '李小華',
                    'id_no' => 'B223456789',
                    'scope' => '全部 1/1',
                    'price' => '3800.0',
                    'addr' => '桃園市桃園區成功路2號'
                ]
            ],
            'managers' => [],
            'auditor1' => '陳初審',
            'auditor2' => '林複審'
        ];

        $this->drawNoticePage($pdf, $caseData1);

        // 產製第 2 頁案件 (展示多案件分頁能力)
        $caseData2 = [
            'title' => $fullOfficeTitle,
            'office' => $officeName,
            'rec_no' => ($isRemote ? '桃跨' : '桃資') . ' 字 第 000002 號',
            'change_date' => self::formatRocDateLong($endRoc),
            'sections' => [
                [
                    'item' => 1,
                    'town' => $isRemote ? '中壢區' : '桃園區',
                    'section' => $isRemote ? '青埔段' : '大興段',
                    'land_no' => '0202-0001',
                    'cause' => '贈與',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc),
                    'note' => '',
                    'details' => [
                        [
                            'type' => '原',
                            'category' => '建',
                            'grade' => '02',
                            'area' => '120.50',
                            'zone' => '商業區',
                            'usage' => '建築用地',
                            'value' => '25000.0',
                            'remark' => ''
                        ],
                        [
                            'type' => '現',
                            'category' => '建',
                            'grade' => '02',
                            'area' => '120.50',
                            'zone' => '商業區',
                            'usage' => '建築用地',
                            'value' => '25000.0',
                            'remark' => ''
                        ]
                    ]
                ]
            ],
            'owners' => [
                [
                    'item' => 1,
                    'type' => '原',
                    'name' => '張三',
                    'id_no' => 'H123456789',
                    'scope' => '持分 1/2',
                    'price' => '5000.0',
                    'addr' => '桃園市中壢區復興路10號'
                ],
                [
                    'item' => 2,
                    'type' => '現',
                    'name' => '張四',
                    'id_no' => 'H223456789',
                    'scope' => '持分 1/2',
                    'price' => '5000.0',
                    'addr' => '桃園市中壢區復興路10號'
                ]
            ],
            'managers' => [
                [
                    'item' => 1,
                    'type' => '現',
                    'name' => '財政部國有財產署',
                    'id_no' => '03720803',
                    'addr' => '臺北市中正區愛國西路2號'
                ]
            ],
            'auditor1' => '黃初審',
            'auditor2' => '周複審'
        ];

        $this->drawNoticePage($pdf, $caseData2);

        $pdf->Output('F', $path);
        return ['state' => 'success', 'message' => '已完成'];
    }

    /**
     * 直連 Oracle 產製地籍異動通知書
     */
    private function exportDirectOracle(string $path, array $item, array $batch): array {
        $code = $item['code'] ?? 'RCD0101_LOCAL';
        $isRemote = ($code === 'RCD0101_REMOTE');

        $sdate = self::rocDate($batch['begin']);
        $edate = self::rocDate($batch['end']);

        // 1. 查詢本所代碼與名稱
        $sqlKrmk = "SELECT KCNT, KRMK FROM SRKEYN WHERE (KCDE_1='55') AND (KCDE_2='01') AND 1=1";
        $krmkRows = $this->query($sqlKrmk);
        $office = !empty($krmkRows[0]['KCNT']) ? trim($krmkRows[0]['KCNT']) : '桃園';
        $krmk = !empty($krmkRows[0]['KRMK']) ? trim($krmkRows[0]['KRMK']) : 'HA';
        $krmkSub1 = substr($krmk, 0, 1);

        // 2. 查詢縣市名稱
        $sqlCity = "SELECT KCNT FROM SRKEYN WHERE KCDE_1='66' AND KCDE_2='01'";
        $cityRows = $this->query($sqlCity);
        $city = !empty($cityRows[0]['KCNT']) ? trim($cityRows[0]['KCNT']) : '桃園市';

        $displayOffice = $isRemote ? ($office . '(跨)') : $office;
        $fullOfficeTitle = $city . $displayOffice . '地政事務所土地地籍異動通知書';

        // 3. 主案件查詢 SQL
        if (!$isRemote) {
            $mainSql = "SELECT RM01, RM02, RM03, RM04, RM05, RM06, RM07_1, RM33, RM34, RM36, RM61, RM55, RM57, RM09 " .
                       "FROM SCRSMS " .
                       "WHERE RM07_1 BETWEEN :sdate AND :edate " .
                       "  AND SUBSTR(RM61, 5, 1) = '1' " .
                       "  AND (RM99 IS NULL OR RM99 = 'N') " .
                       "ORDER BY RM01, RM02, RM03";
            $mainRows = $this->query($mainSql, [':sdate' => $sdate, ':edate' => $edate]);
        } else {
            $mainSql = "SELECT RM01, RM02, RM03, RM04, RM05, RM06, RM07_1, RM33, RM34, RM36, RM61, RM55, RM57, RM09 " .
                       "FROM SCRSMS " .
                       "WHERE RM07_1 BETWEEN :sdate AND :edate " .
                       "  AND SUBSTR(RM61, 5, 1) = '1' " .
                       "  AND (RM99 = 'Y' " .
                       "       AND (RM100 = :krmk OR RM100 IN (SELECT KCDE_2 FROM SRKEYN WHERE KCDE_1 = 'CB2' AND KRMK = :krmk2)) " .
                       "       AND (RM100_1 = :krmkSub1)) " .
                       "ORDER BY RM01, RM02, RM03";
            $mainRows = $this->query($mainSql, [
                ':sdate' => $sdate,
                ':edate' => $edate,
                ':krmk' => $krmk,
                ':krmk2' => $krmk,
                ':krmkSub1' => $krmkSub1
            ]);
        }

        if (empty($mainRows)) {
            if (is_file($path)) {
                unlink($path);
            }
            return ['state' => 'empty', 'message' => '無資料'];
        }

        $pdf = new TaxExportPdfWriter('L', 'mm', 'A4');
        $validCasesCount = 0;

        foreach ($mainRows as $case) {
            $rm01 = trim($case['RM01'] ?? '');
            $rm02 = trim($case['RM02'] ?? '');
            $rm03 = trim($case['RM03'] ?? '');
            $rm07_1 = trim($case['RM07_1'] ?? '');
            $rm33 = trim($case['RM33'] ?? '');
            $rm34 = trim($case['RM34'] ?? '');
            $rm36 = trim($case['RM36'] ?? '');
            $rm09 = trim($case['RM09'] ?? '');
            $rm55 = trim($case['RM55'] ?? '');
            $rm57 = trim($case['RM57'] ?? '');

            // 檢查異動指數總數 (countA 與 countB)
            $sqlCntA = "SELECT COUNT(*) AS C FROM TRINDX WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='A' AND IP_TYPE IN ('M','D','A','N')";
            $rowsCntA = $this->query($sqlCntA, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);
            $countA = !empty($rowsCntA[0]['C']) ? (int)$rowsCntA[0]['C'] : 0;

            $sqlCntB = "SELECT COUNT(*) AS C FROM TRINDX WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='B' AND IP_TYPE IN ('M','D','A','N','l')";
            $rowsCntB = $this->query($sqlCntB, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);
            $countB = !empty($rowsCntB[0]['C']) ? (int)$rowsCntB[0]['C'] : 0;

            if ($countA === 0 && $countB === 0) {
                continue;
            }

            // 查詢人員姓名
            $name01 = $this->queryUserName($rm55);
            $name02 = $this->queryUserName($rm57);

            // 收件字號與原因
            $wordName = $this->getKeyn('04', $rm02);
            $recNo = ($wordName !== '' ? $wordName : $rm02) . ' 字 第 ' . $rm03 . ' 號';
            $causeName = $this->getKeyn('06', $rm09);
            $changeDateStr = self::formatRocDateLong($rm07_1);

            // 掃描案件涉及之土地清單
            $sqlLand = "SELECT DISTINCT SUBSTR(IIKEY, 2, 12) AS LAND_KEY " .
                       "FROM TRINDX " .
                       "WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 " .
                       "  AND II00 IN ('A','B') " .
                       "  AND IP_TYPE IN ('M','D','A','N','l') " .
                       "ORDER BY SUBSTR(IIKEY, 2, 12)";
            $landRows = $this->query($sqlLand, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

            $sectionsData = [];
            $secItemIdx = 1;

            foreach ($landRows as $lr) {
                $lkey = trim($lr['LAND_KEY'] ?? '');
                if (strlen($lkey) < 12) {
                    continue;
                }
                $aa48 = substr($lkey, 0, 4);
                $aa49 = substr($lkey, 4, 8);

                $secInfo = $this->getKeynFull('48', $aa48);
                $sectionName = $secInfo['KCNT'] ?? $aa48;
                $townCode = $secInfo['KRMK'] ?? '';
                $townName = $this->getKeyn('46', $townCode);

                $landNoStr = self::formatLandNo($aa49);

                $note = '';
                if ($rm36 === '02') {
                    $note = $this->queryPartitionNote($rm01, $rm02, $rm03, $aa48);
                } elseif ($rm36 === '03') {
                    $note = $this->queryMergeNote($rm01, $rm02, $rm03, $aa48);
                }

                // 標示部原/現對照
                $details = [];
                if ($countA > 0) {
                    // 原
                    $sqlWralidOrig = "SELECT AS_TYPE, AA46, AA08, AA09, AA10, AA11, AA12, AA16, AA05, AA06 " .
                                     "FROM WRALID " .
                                     "WHERE AI03=:rm01 AND AI04_1=:rm02 AND AI04_2=:rm03 AND AA48=:aa48 AND AA49=:aa49 AND AS_TYPE IN ('M','D')";
                    $origRows = $this->query($sqlWralidOrig, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':aa48' => $aa48, ':aa49' => $aa49]);

                    // 現
                    $sqlWralidCurr = "SELECT AS_TYPE, AA46, AA08, AA09, AA10, AA11, AA12, AA16, AA05, AA06 " .
                                     "FROM WRALID " .
                                     "WHERE AI03=:rm01 AND AI04_1=:rm02 AND AI04_2=:rm03 AND AA48=:aa48 AND AA49=:aa49 AND AS_TYPE IN ('N','A')";
                    $currRows = $this->query($sqlWralidCurr, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':aa48' => $aa48, ':aa49' => $aa49]);

                    if (!empty($origRows[0])) {
                        $or = $origRows[0];
                        $details[] = [
                            'type' => '原',
                            'category' => $this->getKeyn('08', trim($or['AA08'] ?? '')),
                            'grade' => trim($or['AA09'] ?? ''),
                            'area' => trim($or['AA10'] ?? ''),
                            'zone' => $this->getKeyn('11', trim($or['AA11'] ?? '')),
                            'usage' => $this->getKeyn('12', trim($or['AA12'] ?? '')),
                            'value' => trim($or['AA16'] ?? ''),
                            'remark' => ''
                        ];
                    }

                    if (!empty($currRows[0])) {
                        $cr = $currRows[0];
                        $details[] = [
                            'type' => '現',
                            'category' => $this->getKeyn('08', trim($cr['AA08'] ?? '')),
                            'grade' => trim($cr['AA09'] ?? ''),
                            'area' => trim($cr['AA10'] ?? ''),
                            'zone' => $this->getKeyn('11', trim($cr['AA11'] ?? '')),
                            'usage' => $this->getKeyn('12', trim($cr['AA12'] ?? '')),
                            'value' => trim($cr['AA16'] ?? ''),
                            'remark' => ''
                        ];
                    }
                }

                $sectionsData[] = [
                    'item' => $secItemIdx++,
                    'town' => $townName,
                    'section' => $sectionName,
                    'land_no' => $landNoStr,
                    'cause' => $causeName,
                    'cause_date' => self::formatRocDate($rm34),
                    'reg_date' => self::formatRocDate($rm33),
                    'note' => $note,
                    'details' => $details
                ];
            }

            // 查詢所有權部異動資料 (子表 2)
            $ownersData = [];
            $ownItemIdx = 1;

            if ($countB > 0) {
                $sqlOwnKeys = "SELECT DISTINCT SUBSTR(IIKEY, 2, 16) AS OWN_KEY " .
                              "FROM TRINDX " .
                              "WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='B' " .
                              "  AND IP_TYPE IN ('M','D','A','N','l') " .
                              "ORDER BY SUBSTR(IIKEY, 2, 16)";
                $ownKeysRows = $this->query($sqlOwnKeys, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

                foreach ($ownKeysRows as $okr) {
                    $okey = trim($okr['OWN_KEY'] ?? '');
                    if (strlen($okey) < 16) {
                        continue;
                    }
                    $ba48 = substr($okey, 0, 4);
                    $ba49 = substr($okey, 4, 8);
                    $bb01 = substr($okey, 12, 4);

                    // 原
                    $sqlOrigB = "SELECT BS_TYPE, BB09, BB15_1, BB15_2, BB15_3, BB21, BB06, BB07, BB05 " .
                                "FROM WRBLOW " .
                                "WHERE BI03=:rm01 AND BI04_1=:rm02 AND BI04_2=:rm03 AND BA48=:ba48 AND BA49=:ba49 AND BB01=:bb01 AND BS_TYPE IN ('M','D')";
                    $origBRows = $this->query($sqlOrigB, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':ba48' => $ba48, ':ba49' => $ba49, ':bb01' => $bb01]);

                    // 現
                    $sqlCurrB = "SELECT BS_TYPE, BB09, BB15_1, BB15_2, BB15_3, BB21, BB06, BB07, BB05 " .
                                "FROM WRBLOW " .
                                "WHERE BI03=:rm01 AND BI04_1=:rm02 AND BI04_2=:rm03 AND BA48=:ba48 AND BA49=:ba49 AND BB01=:bb01 AND BS_TYPE IN ('A','N')";
                    $currBRows = $this->query($sqlCurrB, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':ba48' => $ba48, ':ba49' => $ba49, ':bb01' => $bb01]);

                    if (!empty($origBRows[0])) {
                        $ob = $origBRows[0];
                        $lnid = $this->queryLnid(trim($ob['BB09'] ?? ''));
                        $scope = $this->formatScope(trim($ob['BB15_1'] ?? ''), trim($ob['BB15_2'] ?? ''), trim($ob['BB15_3'] ?? ''), $rm01, $rm02, $rm03);
                        $ownersData[] = [
                            'item' => $ownItemIdx++,
                            'type' => '原',
                            'name' => $lnid['LNAM'] ?? '',
                            'id_no' => trim($ob['BB09'] ?? ''),
                            'scope' => $scope,
                            'price' => trim($ob['BB21'] ?? ''),
                            'addr' => $lnid['LADR'] ?? ''
                        ];
                    }

                    if (!empty($currBRows[0])) {
                        $cb = $currBRows[0];
                        $lnid = $this->queryLnid(trim($cb['BB09'] ?? ''));
                        $scope = $this->formatScope(trim($cb['BB15_1'] ?? ''), trim($cb['BB15_2'] ?? ''), trim($cb['BB15_3'] ?? ''), $rm01, $rm02, $rm03);
                        // 覆蓋申報地價 WPBLOW
                        $price = trim($cb['BB21'] ?? '');
                        $sqlWpblow = "SELECT LA21 FROM WPBLOW WHERE LA03=:rm01 AND LA04_1=:rm02 AND LA04_2=:rm03 AND LA48=:ba48 AND LA49=:ba49 AND LA01=:bb01 AND LA_TYPE IN ('A','N')";
                        $wpRows = $this->query($sqlWpblow, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':ba48' => $ba48, ':ba49' => $ba49, ':bb01' => $bb01]);
                        if (!empty($wpRows[0]['LA21'])) {
                            $price = trim($wpRows[0]['LA21']);
                        }
                        $ownersData[] = [
                            'item' => $ownItemIdx++,
                            'type' => '現',
                            'name' => $lnid['LNAM'] ?? '',
                            'id_no' => trim($cb['BB09'] ?? ''),
                            'scope' => $scope,
                            'price' => $price,
                            'addr' => $lnid['LADR'] ?? ''
                        ];
                    }
                }
            }

            // 查詢管理機關 (子表 3)
            $managersData = [];
            $mngItemIdx = 1;
            $sqlMng = "SELECT MM00, MS_TYPE, MM13, MM01 " .
                      "FROM WRMNGR " .
                      "WHERE MI03=:rm01 AND MI04_1=:rm02 AND MI04_2=:rm03 AND MM00='B' " .
                      "ORDER BY MM48, MM49, MM01, MS_TYPE";
            $mngRows = $this->query($sqlMng, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

            foreach ($mngRows as $mr) {
                $msType = trim($mr['MS_TYPE'] ?? '');
                $typeStr = in_array($msType, ['M', 'D'], true) ? '原' : '現';
                $mm13 = trim($mr['MM13'] ?? '');
                $lnid = $this->queryLnid($mm13);
                $managersData[] = [
                    'item' => $mngItemIdx++,
                    'type' => $typeStr,
                    'name' => $lnid['LNAM'] ?? '',
                    'id_no' => $mm13,
                    'addr' => $lnid['LADR'] ?? ''
                ];
            }

            // 組裝此案件資料並繪製整頁
            $caseData = [
                'title' => $fullOfficeTitle,
                'office' => $displayOffice,
                'rec_no' => $recNo,
                'change_date' => $changeDateStr,
                'sections' => $sectionsData,
                'owners' => $ownersData,
                'managers' => $managersData,
                'auditor1' => $name01,
                'auditor2' => $name02
            ];

            $this->drawNoticePage($pdf, $caseData);
            $validCasesCount++;
        }

        if ($validCasesCount === 0) {
            if (is_file($path)) {
                unlink($path);
            }
            return ['state' => 'empty', 'message' => '無資料'];
        }

        $pdf->Output('F', $path);
        return ['state' => 'success', 'message' => '已完成'];
    }

    /**
     * 繪製單一案件地籍異動通知書頁面 (A4 橫向 297mm x 210mm)
     */
    public function drawNoticePage(TaxExportPdfWriter $pdf, array $data): void {
        $pdf->AddPage('L', 'A4');

        $startX = 10;
        $totalWidth = 277;

        // 1. 頂部大標題
        $pdf->SetXY($startX, 10);
        $pdf->SetFont('Big5', 'B', 15);
        $pdf->cellZh($totalWidth, 8, $data['title'] ?? '地政事務所土地地籍異動通知書', 0, 1, 'C');

        // 2. 收件字號與異動日期列
        $pdf->SetFont('Big5', '', 9.5);
        $pdf->SetX($startX);
        $pdf->cellZh(140, 6, '收件字號：' . ($data['rec_no'] ?? ''), 0, 0, 'L');
        $pdf->cellZh(137, 6, '異動日期：' . ($data['change_date'] ?? ''), 0, 1, 'R');

        // 3. 第一區塊：土地標示部
        $pdf->SetFont('Big5', 'B', 10);
        $pdf->cellZh($totalWidth, 5.5, '【土地標示部】', 0, 1, 'L');

        // 標示部表頭
        $pdf->SetFont('Big5', 'B', 8.5);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->cellZh(12, 5.5, '項次', 1, 0, 'C', true);
        $pdf->cellZh(25, 5.5, '鄉鎮市區', 1, 0, 'C', true);
        $pdf->cellZh(35, 5.5, '段小段', 1, 0, 'C', true);
        $pdf->cellZh(30, 5.5, '地號', 1, 0, 'C', true);
        $pdf->cellZh(30, 5.5, '登記原因', 1, 0, 'C', true);
        $pdf->cellZh(30, 5.5, '原因發生日期', 1, 0, 'C', true);
        $pdf->cellZh(30, 5.5, '登記日期', 1, 0, 'C', true);
        $pdf->cellZh(85, 5.5, '異動記事', 1, 1, 'C', true);

        // 標示部資料列
        $pdf->SetFont('Big5', '', 8);
        $sections = $data['sections'] ?? [];
        if (empty($sections)) {
            $pdf->cellZh($totalWidth, 5, '（無土地標示部異動資料）', 1, 1, 'C');
        } else {
            foreach ($sections as $sec) {
                $pdf->cellZh(12, 5, (string)($sec['item'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(25, 5, (string)($sec['town'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(35, 5, (string)($sec['section'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(30, 5, (string)($sec['land_no'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(30, 5, (string)($sec['cause'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(30, 5, (string)($sec['cause_date'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(30, 5, (string)($sec['reg_date'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(85, 5, (string)($sec['note'] ?? ''), 1, 1, 'L');

                // 原/現明細子列 (若有)
                $details = $sec['details'] ?? [];
                if (!empty($details)) {
                    // 子表頭
                    $pdf->SetFont('Big5', 'B', 7.5);
                    $pdf->cellZh(12, 4.5, '', 'L', 0, 'C');
                    $pdf->cellZh(15, 4.5, '別', 1, 0, 'C', true);
                    $pdf->cellZh(20, 4.5, '地目', 1, 0, 'C', true);
                    $pdf->cellZh(20, 4.5, '等則', 1, 0, 'C', true);
                    $pdf->cellZh(35, 4.5, '面積(㎡)', 1, 0, 'C', true);
                    $pdf->cellZh(40, 4.5, '使用分區', 1, 0, 'C', true);
                    $pdf->cellZh(40, 4.5, '使用地類別', 1, 0, 'C', true);
                    $pdf->cellZh(40, 4.5, '公告現值(元/㎡)', 1, 0, 'C', true);
                    $pdf->cellZh(55, 4.5, '備註', 1, 1, 'C', true);

                    $pdf->SetFont('Big5', '', 7.5);
                    foreach ($details as $det) {
                        $pdf->cellZh(12, 4.5, '', 'L', 0, 'C');
                        $pdf->cellZh(15, 4.5, (string)($det['type'] ?? ''), 1, 0, 'C');
                        $pdf->cellZh(20, 4.5, (string)($det['category'] ?? ''), 1, 0, 'C');
                        $pdf->cellZh(20, 4.5, (string)($det['grade'] ?? ''), 1, 0, 'C');
                        $pdf->cellZh(35, 4.5, (string)($det['area'] ?? ''), 1, 0, 'R');
                        $pdf->cellZh(40, 4.5, (string)($det['zone'] ?? ''), 1, 0, 'C');
                        $pdf->cellZh(40, 4.5, (string)($det['usage'] ?? ''), 1, 0, 'C');
                        $pdf->cellZh(40, 4.5, (string)($det['value'] ?? ''), 1, 0, 'R');
                        $pdf->cellZh(55, 4.5, (string)($det['remark'] ?? ''), 1, 1, 'L');
                    }
                    $pdf->SetFont('Big5', '', 8);
                }
            }
        }

        // 4. 第二區塊：土地所有權部
        $pdf->Ln(2);
        $pdf->SetFont('Big5', 'B', 10);
        $pdf->cellZh($totalWidth, 5.5, '【土地所有權部】', 0, 1, 'L');

        // 所有權部表頭
        $pdf->SetFont('Big5', 'B', 8.5);
        $pdf->cellZh(12, 5.5, '項次', 1, 0, 'C', true);
        $pdf->cellZh(15, 5.5, '別', 1, 0, 'C', true);
        $pdf->cellZh(45, 5.5, '所有權人姓名', 1, 0, 'C', true);
        $pdf->cellZh(30, 5.5, '統一編號', 1, 0, 'C', true);
        $pdf->cellZh(40, 5.5, '權利範圍', 1, 0, 'C', true);
        $pdf->cellZh(35, 5.5, '申報地價(元/㎡)', 1, 0, 'C', true);
        $pdf->cellZh(100, 5.5, '住址', 1, 1, 'C', true);

        // 所有權部資料列
        $pdf->SetFont('Big5', '', 8);
        $owners = $data['owners'] ?? [];
        if (empty($owners)) {
            $pdf->cellZh($totalWidth, 5, '（無土地所有權部異動資料）', 1, 1, 'C');
        } else {
            foreach ($owners as $own) {
                $pdf->cellZh(12, 5, (string)($own['item'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(15, 5, (string)($own['type'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(45, 5, (string)($own['name'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(30, 5, (string)($own['id_no'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(40, 5, (string)($own['scope'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(35, 5, (string)($own['price'] ?? ''), 1, 0, 'R');
                $pdf->cellZh(100, 5, (string)($own['addr'] ?? ''), 1, 1, 'L');
            }
        }

        // 5. 第三區塊：管理機關
        $managers = $data['managers'] ?? [];
        if (!empty($managers)) {
            $pdf->Ln(2);
            $pdf->SetFont('Big5', 'B', 10);
            $pdf->cellZh($totalWidth, 5.5, '【管理機關】', 0, 1, 'L');

            $pdf->SetFont('Big5', 'B', 8.5);
            $pdf->cellZh(12, 5.5, '項次', 1, 0, 'C', true);
            $pdf->cellZh(15, 5.5, '別', 1, 0, 'C', true);
            $pdf->cellZh(65, 5.5, '管理者名稱', 1, 0, 'C', true);
            $pdf->cellZh(35, 5.5, '統一編號', 1, 0, 'C', true);
            $pdf->cellZh(150, 5.5, '住址', 1, 1, 'C', true);

            $pdf->SetFont('Big5', '', 8);
            foreach ($managers as $mng) {
                $pdf->cellZh(12, 5, (string)($mng['item'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(15, 5, (string)($mng['type'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(65, 5, (string)($mng['name'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(35, 5, (string)($mng['id_no'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(150, 5, (string)($mng['addr'] ?? ''), 1, 1, 'L');
            }
        }

        // 6. 底部簽章核章區 (固定於頁面底部)
        $pdf->SetY(192);
        $pdf->SetFont('Big5', '', 9);
        $officeLabel = '填發機關：' . ($data['office'] ?? '桃園') . '地政事務所';
        $aud1Label = '初審：' . ($data['auditor1'] ?? '');
        $aud2Label = '複審：' . ($data['auditor2'] ?? '');

        $pdf->cellZh(65, 8, $officeLabel, 1, 0, 'L');
        $pdf->cellZh(53, 8, $aud1Label, 1, 0, 'L');
        $pdf->cellZh(53, 8, $aud2Label, 1, 0, 'L');
        $pdf->cellZh(53, 8, '課長：', 1, 0, 'L');
        $pdf->cellZh(53, 8, '主任：', 1, 1, 'L');
    }

    /**
     * 查詢人員姓名 (sysauth1 / VSYSAUTH)
     */
    private function queryUserName(string $userId): string {
        if ($userId === '') {
            return '';
        }
        $sql1 = "SELECT user_name FROM sysauth1 WHERE user_id = :uid";
        $rows = $this->query($sql1, [':uid' => $userId]);
        if (!empty($rows[0]['USER_NAME'])) {
            return trim($rows[0]['USER_NAME']);
        }
        $sql2 = "SELECT user_name FROM VSYSAUTH WHERE user_id = :uid";
        $rows2 = $this->query($sql2, [':uid' => $userId]);
        if (!empty($rows2[0]['USER_NAME'])) {
            return trim($rows2[0]['USER_NAME']);
        }
        return $userId;
    }

    /**
     * 查詢所有權人/統編姓名與住址 (SRLNID)
     */
    private function queryLnid(string $lidn): array {
        if ($lidn === '') {
            return ['LNAM' => '', 'LADR' => ''];
        }
        $sql = "SELECT LIDN, LNAM, LADR FROM SRLNID WHERE LIDN = :lidn";
        $rows = $this->query($sql, [':lidn' => $lidn]);
        if (!empty($rows[0])) {
            return [
                'LNAM' => trim($rows[0]['LNAM'] ?? ''),
                'LADR' => trim($rows[0]['LADR'] ?? '')
            ];
        }
        return ['LNAM' => '', 'LADR' => ''];
    }

    /**
     * 格式化權利範圍文字
     */
    private function formatScope(string $bb15_1, string $bb15_2, string $bb15_3, string $rm01, string $rm02, string $rm03): string {
        $name = $this->getKeyn('15', $bb15_1);
        if ($bb15_1 === 'A') {
            return $name !== '' ? $name : '全部';
        }
        if ($bb15_2 !== '' && $bb15_2 !== '0') {
            $denom = (int)$bb15_2;
            $numer = (int)$bb15_3;
            return ($name !== '' ? $name . ' ' : '') . $numer . '/' . $denom;
        }
        if ($bb15_1 === 'Z') {
            $sqlZ = "SELECT GG30_2 FROM WRGALL WHERE GI03=:rm01 AND GI04_1=:rm02 AND GI04_2=:rm03 AND GG00='B' AND GG30_1='99'";
            $zRows = $this->query($sqlZ, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);
            if (!empty($zRows[0]['GG30_2'])) {
                return ($name !== '' ? $name . ' ' : '') . trim($zRows[0]['GG30_2']);
            }
        }
        return $name;
    }

    /**
     * 查詢分割記事 (TRINDX / WRGALL)
     */
    private function queryPartitionNote(string $rm01, string $rm02, string $rm03, string $aa48): string {
        $sql = "SELECT SUBSTR(IIKEY, 6, 8) AS NO FROM TRINDX WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='A' AND SUBSTR(IIKEY,1,5)='A'||:aa48 AND IP_TYPE IN ('A','N') ORDER BY IIKEY";
        $rows = $this->query($sql, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':aa48' => $aa48]);
        if (!empty($rows)) {
            $nos = [];
            foreach ($rows as $r) {
                if (!empty($r['NO'])) {
                    $nos[] = self::formatLandNo(trim($r['NO']));
                }
            }
            if (!empty($nos)) {
                return '分割自：' . implode(', ', array_unique($nos)) . ' 地號';
            }
        }
        return '';
    }

    /**
     * 查詢合併記事 (TRINDX / WRGALL)
     */
    private function queryMergeNote(string $rm01, string $rm02, string $rm03, string $aa48): string {
        $sql = "SELECT SUBSTR(IIKEY, 6, 8) AS NO FROM TRINDX WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='A' AND SUBSTR(IIKEY,1,5)='A'||:aa48 AND IP_TYPE='D' ORDER BY IIKEY";
        $rows = $this->query($sql, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':aa48' => $aa48]);
        if (!empty($rows)) {
            $nos = [];
            foreach ($rows as $r) {
                if (!empty($r['NO'])) {
                    $nos[] = self::formatLandNo(trim($r['NO']));
                }
            }
            if (!empty($nos)) {
                return '合併自：' . implode(', ', array_unique($nos)) . ' 地號';
            }
        }
        return '';
    }

    /**
     * 取得 SRKEYN 名稱 (帶快取)
     */
    private function getKeyn(string $kcde1, string $kcde2): string {
        $full = $this->getKeynFull($kcde1, $kcde2);
        return $full['KCNT'] ?? '';
    }

    private function getKeynFull(string $kcde1, string $kcde2): array {
        if ($kcde2 === '') {
            return ['KCNT' => '', 'KRMK' => ''];
        }
        if (isset($this->keynCache[$kcde1][$kcde2])) {
            return $this->keynCache[$kcde1][$kcde2];
        }

        $sql = "SELECT KCNT, KRMK FROM SRKEYN WHERE KCDE_1=:c1 AND KCDE_2=:c2";
        $rows = $this->query($sql, [':c1' => $kcde1, ':c2' => $kcde2]);
        if (!empty($rows[0])) {
            $res = [
                'KCNT' => trim($rows[0]['KCNT'] ?? ''),
                'KRMK' => trim($rows[0]['KRMK'] ?? '')
            ];
            $this->keynCache[$kcde1][$kcde2] = $res;
            return $res;
        }

        $res = ['KCNT' => $kcde2, 'KRMK' => ''];
        $this->keynCache[$kcde1][$kcde2] = $res;
        return $res;
    }

    /**
     * 格式化地號 (8 碼母子號轉母號-子號，若子號為 0000 則僅顯示母號)
     */
    public static function formatLandNo(string $raw): string {
        $raw = str_pad(trim($raw), 8, '0', STR_PAD_LEFT);
        $main = ltrim(substr($raw, 0, 4), '0');
        if ($main === '') $main = '0';
        $sub = ltrim(substr($raw, 4, 4), '0');
        if ($sub === '') {
            return $main;
        }
        return $main . '-' . $sub;
    }

    /**
     * 西元 ISO 日期轉 7 碼民國格式 (YYYMMDD)
     */
    public static function rocDate(string $isoDate): string {
        $clean = str_replace(['-', '/'], '', substr($isoDate, 0, 10));
        if (strlen($clean) === 8) {
            $year = (int)substr($clean, 0, 4) - 1911;
            return sprintf('%03d%s', $year, substr($clean, 4, 4));
        }
        return $clean;
    }

    /**
     * 7 碼民國日期轉斜線格式 (YYY/MM/DD)
     */
    public static function formatRocDate(string $roc): string {
        $roc = trim($roc);
        if (strlen($roc) === 7) {
            return substr($roc, 0, 3) . '/' . substr($roc, 3, 2) . '/' . substr($roc, 5, 2);
        }
        return $roc;
    }

    /**
     * 7 碼民國日期轉「中華民國 YYY 年 MM 月 DD 日」長格式
     */
    public static function formatRocDateLong(string $roc): string {
        $roc = trim($roc);
        if (strlen($roc) === 7) {
            $y = (int)substr($roc, 0, 3);
            $m = (int)substr($roc, 3, 2);
            $d = (int)substr($roc, 5, 2);
            return '中華民國 ' . $y . ' 年 ' . $m . ' 月 ' . $d . ' 日';
        }
        return $roc;
    }

    /**
     * 執行 SQL 查詢
     */
    private function query(string $sql, array $params = []): array {
        if ($this->dbSelector !== null) {
            return call_user_func($this->dbSelector, $sql, $params);
        }

        if (!class_exists('OraDBWrapper')) {
            $file = dirname(__DIR__) . '/OraDBWrapper.class.php';
            if (is_file($file)) {
                require_once $file;
            }
        }

        if (!class_exists('OraDBWrapper')) {
            throw new RuntimeException('找不到 OraDBWrapper 類別。');
        }

        $wrapper = new OraDBWrapper();
        return $wrapper->getRawRows($sql, $params);
    }

    /**
     * 判斷是否回退至 MOCK 模式
     */
    private function shouldFallbackToMock(): bool {
        if (class_exists('System', false)) {
            $system = System::getInstance();
            if ($system->isMockMode()) {
                return true;
            }
        }
        if (getenv('MOCK') === 'true' || getenv('MOCK') === '1') {
            return true;
        }
        if (!class_exists('OraDBWrapper')) {
            $file = dirname(__DIR__) . '/OraDBWrapper.class.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
        if (class_exists('OraDBWrapper')) {
            $wrapper = new OraDBWrapper();
            if (!$wrapper->reachable()) {
                return true;
            }
        }
        return false;
    }
}
