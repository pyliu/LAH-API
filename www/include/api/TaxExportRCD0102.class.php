<?php

require_once __DIR__ . '/TaxExportPdfWriter.class.php';

final class TaxExportRCD0102 {
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
     * 執行建物異動通知書 (RCD0102) PDF 產製
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
        $pdf = new TaxExportPdfWriter('L', 'pt', 'A4');
        $code = $item['code'] ?? 'RCD0102_LOCAL';
        $isRemote = ($code === 'RCD0102_REMOTE');

        $beginRoc = self::rocDate($batch['begin'] ?? '2026-10-01');
        $endRoc = self::rocDate($batch['end'] ?? '2026-10-31');

        $officeName = $isRemote ? '桃園(跨)' : '桃園';
        $fullOfficeTitle = '桃園市' . $officeName . '地政事務所建物異動通知書';

        // 產製第 1 頁案件 (展示本所建物異動格式)
        $caseData1 = [
            'title' => $fullOfficeTitle,
            'office' => $officeName,
            'rec_no' => ($isRemote ? '桃跨' : '桃資') . ' 字 第 000001 號',
            'change_date' => self::formatRocDateLong($endRoc),
            'page_text' => '共 2 頁 第 1 頁',
            'buildings' => [
                [
                    'item' => 1,
                    'build_no' => '101',
                    'town' => '桃園區',
                    'section' => '中路段',
                    'land_no' => '101',
                    'addr' => '桃園市桃園區中山路100號',
                    'cause' => '買賣',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc),
                    'remark' => ''
                ],
                [
                    'item' => 2,
                    'build_no' => '101',
                    'town' => '桃園區',
                    'section' => '中路段',
                    'land_no' => '101-1',
                    'addr' => '桃園市桃園區中山路100號',
                    'cause' => '買賣',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc),
                    'remark' => ''
                ]
            ],
            'owners' => [
                [
                    'item' => 1,
                    'type' => '原',
                    'name' => '王大明',
                    'id_no' => 'A123456789',
                    'scope' => '全部 1/1',
                    'addr' => '桃園市桃園區中山路1號',
                    'cause' => '買賣',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc)
                ],
                [
                    'item' => 2,
                    'type' => '現',
                    'name' => '李小華',
                    'id_no' => 'B223456789',
                    'scope' => '全部 1/1',
                    'addr' => '桃園市桃園區成功路2號',
                    'cause' => '買賣',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc)
                ]
            ],
            'managers' => [],
            'auditor1' => '陳初審',
            'auditor2' => '林複審'
        ];

        $this->drawOfficialNoticePage($pdf, $caseData1);

        // 產製第 2 頁案件 (展示多案件分頁能力與管理機關表)
        $caseData2 = [
            'title' => $fullOfficeTitle,
            'office' => $officeName,
            'rec_no' => ($isRemote ? '桃跨' : '桃資') . ' 字 第 000002 號',
            'change_date' => self::formatRocDateLong($endRoc),
            'page_text' => '共 2 頁 第 2 頁',
            'buildings' => [
                [
                    'item' => 1,
                    'build_no' => '202-1',
                    'town' => $isRemote ? '中壢區' : '桃園區',
                    'section' => $isRemote ? '青埔段' : '大興段',
                    'land_no' => '202-1',
                    'addr' => $isRemote ? '桃園市中壢區高鐵南路二段1號' : '桃園市桃園區大興西路二段50號',
                    'cause' => '贈與',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc),
                    'remark' => ''
                ]
            ],
            'owners' => [
                [
                    'item' => 1,
                    'type' => '原',
                    'name' => '張三',
                    'id_no' => 'H123456789',
                    'scope' => '持分 1/2',
                    'addr' => '桃園市中壢區復興路10號',
                    'cause' => '贈與',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc)
                ],
                [
                    'item' => 2,
                    'type' => '現',
                    'name' => '張四',
                    'id_no' => 'H223456789',
                    'scope' => '持分 1/2',
                    'addr' => '桃園市中壢區復興路10號',
                    'cause' => '贈與',
                    'cause_date' => self::formatRocDate($beginRoc),
                    'reg_date' => self::formatRocDate($endRoc)
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

        $this->drawOfficialNoticePage($pdf, $caseData2);

        $pdf->Output('F', $path);
        return ['state' => 'success', 'message' => '已完成'];
    }

    /**
     * 直連 Oracle 產製建物異動通知書
     */
    private function exportDirectOracle(string $path, array $item, array $batch): array {
        $code = $item['code'] ?? 'RCD0102_LOCAL';
        $isRemote = ($code === 'RCD0102_REMOTE');

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
        $fullOfficeTitle = $city . $displayOffice . '地政事務所建物異動通知書';

        // 3. 主案件查詢 SQL
        if (!$isRemote) {
            $mainSql = "SELECT RM01, RM02, RM03, RM04, RM05, RM06, RM07_1, RM33, RM34, RM36, RM61, RM55, RM57, RM09, RM18, RM19, RM67 " .
                       "FROM SCRSMS " .
                       "WHERE (RM07_1 BETWEEN :sdate AND :edate) " .
                       "  AND SUBSTR(RM67, 1, 1) = '1' " .
                       "  AND (RM99 IS NULL OR RM99 = 'N') " .
                       "ORDER BY RM01, RM02, RM03";
            $mainRows = $this->query($mainSql, [':sdate' => $sdate, ':edate' => $edate]);
        } else {
            $mainSql = "SELECT RM01, RM02, RM03, RM04, RM05, RM06, RM07_1, RM33, RM34, RM36, RM61, RM55, RM57, RM09, RM18, RM19, RM67 " .
                       "FROM SCRSMS " .
                       "WHERE (RM07_1 BETWEEN :sdate AND :edate) " .
                       "  AND SUBSTR(RM67, 1, 1) = '1' " .
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

        $pdf = new TaxExportPdfWriter('L', 'pt', 'A4');
        $validCases = [];

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

            // 檢查異動指數 (countD 建物標示部, countE 建物所有權部)
            $sqlCntD = "SELECT COUNT(*) AS C FROM TRINDX WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='D' AND IP_TYPE IN ('M','D','A','N')";
            $rowsCntD = $this->query($sqlCntD, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);
            $countD = !empty($rowsCntD[0]['C']) ? (int)$rowsCntD[0]['C'] : 0;

            $sqlCntE = "SELECT COUNT(*) AS C FROM TRINDX WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='E' AND IP_TYPE IN ('M','D','A','N')";
            $rowsCntE = $this->query($sqlCntE, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);
            $countE = !empty($rowsCntE[0]['C']) ? (int)$rowsCntE[0]['C'] : 0;

            if ($countD === 0 && $countE === 0) {
                continue;
            }

            // 查詢人員姓名
            $name01 = $this->queryUserName($rm55);
            $name02 = $this->queryUserName($rm57);

            // 收件字號與異動日期
            $wordName = $this->getKeyn('04', $rm02);
            $recNo = ($wordName !== '' ? $wordName : $rm02) . ' 字 第 ' . $rm03 . ' 號';
            $changeDateStr = self::formatRocDateLong($rm07_1);

            $causeName = $this->getKeyn('06', $rm09);
            if ($causeName === '') {
                $causeName = $rm09;
            }
            $causeDateStr = self::formatRocDate($rm34);
            $regDateStr = self::formatRocDate($rm33);

            // 1. 第一表格：建物標示及基地坐落表
            $buildingsData = [];
            $bldItemIdx = 1;

            if ($countD > 0) {
                $sqlBld = "SELECT DISTINCT IIKEY, IP_TYPE FROM TRINDX " .
                          "WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='D' AND IP_TYPE IN ('M','D','A','N') " .
                          "ORDER BY IIKEY, IP_TYPE";
                $bldRows = $this->query($sqlBld, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

                $seenBld = [];
                foreach ($bldRows as $br) {
                    $iikey = trim($br['IIKEY'] ?? '');
                    $ipType = trim($br['IP_TYPE'] ?? '');
                    if (strlen($iikey) < 13) {
                        continue;
                    }
                    $dd48 = substr($iikey, 1, 4);
                    $dd49 = substr($iikey, 5, 8);
                    $bldKey = $dd48 . $dd49;
                    if (isset($seenBld[$bldKey])) {
                        continue;
                    }
                    $seenBld[$bldKey] = true;

                    // 查詢建物門牌與鄉鎮
                    $dd46 = '';
                    $dd09 = '';
                    if ($ipType === 'D') {
                        $sqlW = "SELECT DD46, DD09 FROM WRDBID WHERE DS03=:rm01 AND DS04_1=:rm02 AND DS04_2=:rm03 AND DD48=:dd48 AND DD49=:dd49 AND DS_TYPE='D'";
                        $wRows = $this->query($sqlW, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':dd48' => $dd48, ':dd49' => $dd49]);
                        if (!empty($wRows[0])) {
                            $dd46 = trim($wRows[0]['DD46'] ?? '');
                            $dd09 = trim($wRows[0]['DD09'] ?? '');
                        }
                    } else {
                        $sqlS = "SELECT DD46, DD09 FROM SRDBID WHERE DD48=:dd48 AND DD49=:dd49";
                        $sRows = $this->query($sqlS, [':dd48' => $dd48, ':dd49' => $dd49]);
                        if (!empty($sRows[0])) {
                            $dd46 = trim($sRows[0]['DD46'] ?? '');
                            $dd09 = trim($sRows[0]['DD09'] ?? '');
                        } else {
                            $sqlW = "SELECT DD46, DD09 FROM WRDBID WHERE DS03=:rm01 AND DS04_1=:rm02 AND DS04_2=:rm03 AND DD48=:dd48 AND DD49=:dd49";
                            $wRows = $this->query($sqlW, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':dd48' => $dd48, ':dd49' => $dd49]);
                            if (!empty($wRows[0])) {
                                $dd46 = trim($wRows[0]['DD46'] ?? '');
                                $dd09 = trim($wRows[0]['DD09'] ?? '');
                            }
                        }
                    }

                    $townName = $this->getKeyn('46', $dd46);
                    if ($townName === '') {
                        $secInfo = $this->getKeynFull('48', $dd48);
                        $townCode = $secInfo['KRMK'] ?? '';
                        $townName = $this->getKeyn('46', $townCode);
                    }
                    $bldSectionName = $this->getKeyn('48', $dd48);

                    // 查詢坐落基地 (SRHD10 -> WRHD10)
                    $sqlBaseS = "SELECT HA48, HA49 FROM SRHD10 WHERE HD48=:dd48 AND HD49=:dd49 ORDER BY HA48, HA49";
                    $baseRows = $this->query($sqlBaseS, [':dd48' => $dd48, ':dd49' => $dd49]);
                    if (empty($baseRows)) {
                        $sqlBaseW = "SELECT HA48, HA49 FROM WRHD10 WHERE HI03=:rm01 AND HI04_1=:rm02 AND HI04_2=:rm03 AND HD48=:dd48 AND HD49=:dd49 ORDER BY HA48, HA49";
                        $baseRows = $this->query($sqlBaseW, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':dd48' => $dd48, ':dd49' => $dd49]);
                    }

                    if (!empty($baseRows)) {
                        foreach ($baseRows as $base) {
                            $ha48 = trim($base['HA48'] ?? '');
                            $ha49 = trim($base['HA49'] ?? '');
                            $baseSecName = $this->getKeyn('48', $ha48);
                            $buildingsData[] = [
                                'item' => $bldItemIdx++,
                                'build_no' => self::formatBuildNo($dd49),
                                'town' => $townName,
                                'section' => $baseSecName !== '' ? $baseSecName : $ha48,
                                'land_no' => self::formatLandNo($ha49),
                                'addr' => $dd09,
                                'cause' => $causeName,
                                'cause_date' => $causeDateStr,
                                'reg_date' => $regDateStr,
                                'remark' => ''
                            ];
                        }
                    } else {
                        $buildingsData[] = [
                            'item' => $bldItemIdx++,
                            'build_no' => self::formatBuildNo($dd49),
                            'town' => $townName,
                            'section' => $bldSectionName !== '' ? $bldSectionName : $dd48,
                            'land_no' => '(空白)',
                            'addr' => $dd09,
                            'cause' => $causeName,
                            'cause_date' => $causeDateStr,
                            'reg_date' => $regDateStr,
                            'remark' => ''
                        ];
                    }

                    if ($rm36 === '03') {
                        break;
                    }
                }
            } else {
                // 區塊 1 未執行 (countD == 0, countE > 0)：由所有權部涉及之建號補齊標示及坐落資料
                $sqlEKeys = "SELECT DISTINCT SUBSTR(IIKEY, 2, 4) AS DD48, SUBSTR(IIKEY, 6, 8) AS DD49 " .
                            "FROM TRINDX " .
                            "WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='E' AND IP_TYPE IN ('M','D','A','N') " .
                            "ORDER BY DD48, DD49";
                $eKeysRows = $this->query($sqlEKeys, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

                foreach ($eKeysRows as $ek) {
                    $dd48 = trim($ek['DD48'] ?? '');
                    $dd49 = trim($ek['DD49'] ?? '');
                    if ($dd48 === '' || $dd49 === '') {
                        continue;
                    }

                    $sqlS = "SELECT DD46, DD09 FROM SRDBID WHERE DD48=:dd48 AND DD49=:dd49";
                    $sRows = $this->query($sqlS, [':dd48' => $dd48, ':dd49' => $dd49]);
                    $dd46 = '';
                    $dd09 = '';
                    if (!empty($sRows[0])) {
                        $dd46 = trim($sRows[0]['DD46'] ?? '');
                        $dd09 = trim($sRows[0]['DD09'] ?? '');
                    } else {
                        $sqlW = "SELECT DD46, DD09 FROM WRDBID WHERE DS03=:rm01 AND DS04_1=:rm02 AND DS04_2=:rm03 AND DD48=:dd48 AND DD49=:dd49";
                        $wRows = $this->query($sqlW, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':dd48' => $dd48, ':dd49' => $dd49]);
                        if (!empty($wRows[0])) {
                            $dd46 = trim($wRows[0]['DD46'] ?? '');
                            $dd09 = trim($wRows[0]['DD09'] ?? '');
                        }
                    }

                    $townName = $this->getKeyn('46', $dd46);
                    if ($townName === '') {
                        $secInfo = $this->getKeynFull('48', $dd48);
                        $townCode = $secInfo['KRMK'] ?? '';
                        $townName = $this->getKeyn('46', $townCode);
                    }
                    $bldSectionName = $this->getKeyn('48', $dd48);

                    $sqlBaseS = "SELECT HA48, HA49 FROM SRHD10 WHERE HD48=:dd48 AND HD49=:dd49 ORDER BY HA48, HA49";
                    $baseRows = $this->query($sqlBaseS, [':dd48' => $dd48, ':dd49' => $dd49]);
                    if (empty($baseRows)) {
                        $sqlBaseW = "SELECT HA48, HA49 FROM WRHD10 WHERE HI03=:rm01 AND HI04_1=:rm02 AND HI04_2=:rm03 AND HD48=:dd48 AND HD49=:dd49 ORDER BY HA48, HA49";
                        $baseRows = $this->query($sqlBaseW, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':dd48' => $dd48, ':dd49' => $dd49]);
                    }

                    if (!empty($baseRows)) {
                        foreach ($baseRows as $base) {
                            $ha48 = trim($base['HA48'] ?? '');
                            $ha49 = trim($base['HA49'] ?? '');
                            $baseSecName = $this->getKeyn('48', $ha48);
                            $buildingsData[] = [
                                'item' => $bldItemIdx++,
                                'build_no' => self::formatBuildNo($dd49),
                                'town' => $townName,
                                'section' => $baseSecName !== '' ? $baseSecName : $ha48,
                                'land_no' => self::formatLandNo($ha49),
                                'addr' => $dd09,
                                'cause' => $causeName,
                                'cause_date' => $causeDateStr,
                                'reg_date' => $regDateStr,
                                'remark' => ''
                            ];
                        }
                    } else {
                        $buildingsData[] = [
                            'item' => $bldItemIdx++,
                            'build_no' => self::formatBuildNo($dd49),
                            'town' => $townName,
                            'section' => $bldSectionName !== '' ? $bldSectionName : $dd48,
                            'land_no' => '(空白)',
                            'addr' => $dd09,
                            'cause' => $causeName,
                            'cause_date' => $causeDateStr,
                            'reg_date' => $regDateStr,
                            'remark' => ''
                        ];
                    }
                }
            }

            // 2. 第二表格：建物所有權部異動表 (TrebowBody)
            $ownersData = [];
            $ownItemIdx = 1;

            if ($countE > 0) {
                $sqlE = "SELECT DISTINCT SUBSTR(IIKEY, 2, 4) AS ED48, SUBSTR(IIKEY, 6, 8) AS ED49, SUBSTR(IIKEY, 14, 4) AS EE01 " .
                        "FROM TRINDX " .
                        "WHERE II03=:rm01 AND II04_1=:rm02 AND II04_2=:rm03 AND II00='E' AND IP_TYPE IN ('M','D','A','N') " .
                        "ORDER BY ED48, ED49, EE01";
                $eRows = $this->query($sqlE, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

                if ($rm36 !== '14') {
                    foreach ($eRows as $er) {
                        $ed48 = trim($er['ED48'] ?? '');
                        $ed49 = trim($er['ED49'] ?? '');
                        $ee01 = trim($er['EE01'] ?? '');
                        if ($ed48 === '' || $ed49 === '' || $ee01 === '') {
                            continue;
                        }

                        // 先查「原」狀態 (WREBOW ES_TYPE IN ('M','D'))
                        $sqlOrigE = "SELECT ES_TYPE, EE09, EE15_1, EE15_2, EE15_3, EE06, EE07, EE05 " .
                                    "FROM WREBOW " .
                                    "WHERE ES03=:rm01 AND ES04_1=:rm02 AND ES04_2=:rm03 AND ED48=:ed48 AND ED49=:ed49 AND EE01=:ee01 AND ES_TYPE IN ('M','D') " .
                                    "ORDER BY EE01";
                        $origERows = $this->query($sqlOrigE, [
                            ':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03,
                            ':ed48' => $ed48, ':ed49' => $ed49, ':ee01' => $ee01
                        ]);

                        foreach ($origERows as $oe) {
                            $ee09 = trim($oe['EE09'] ?? '');
                            $lnid = $this->queryLnid($ee09);
                            $scope = $this->formatScope(
                                trim($oe['EE15_1'] ?? ''),
                                trim($oe['EE15_2'] ?? ''),
                                trim($oe['EE15_3'] ?? ''),
                                $rm01, $rm02, $rm03, 'E'
                            );
                            $origCause = $this->getKeyn('06', trim($oe['EE06'] ?? ''));
                            $ownersData[] = [
                                'item' => $ownItemIdx++,
                                'type' => '原',
                                'name' => $lnid['LNAM'] ?? '',
                                'id_no' => $ee09,
                                'scope' => $scope,
                                'addr' => $lnid['LADR'] ?? '',
                                'cause' => $origCause !== '' ? $origCause : trim($oe['EE06'] ?? ''),
                                'cause_date' => self::formatRocDate(trim($oe['EE07'] ?? '')),
                                'reg_date' => self::formatRocDate(trim($oe['EE05'] ?? ''))
                            ];
                        }

                        // 再查「現」狀態 (WREBOW ES_TYPE IN ('A','N'))
                        $sqlCurrE = "SELECT ES_TYPE, EE09, EE15_1, EE15_2, EE15_3 " .
                                    "FROM WREBOW " .
                                    "WHERE ES03=:rm01 AND ES04_1=:rm02 AND ES04_2=:rm03 AND ED48=:ed48 AND ED49=:ed49 AND EE01=:ee01 AND ES_TYPE IN ('A','N') " .
                                    "ORDER BY EE01";
                        $currERows = $this->query($sqlCurrE, [
                            ':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03,
                            ':ed48' => $ed48, ':ed49' => $ed49, ':ee01' => $ee01
                        ]);

                        foreach ($currERows as $ce) {
                            $ee09 = trim($ce['EE09'] ?? '');
                            $lnid = $this->queryLnid($ee09);
                            $scope = $this->formatScope(
                                trim($ce['EE15_1'] ?? ''),
                                trim($ce['EE15_2'] ?? ''),
                                trim($ce['EE15_3'] ?? ''),
                                $rm01, $rm02, $rm03, 'E'
                            );
                            $ownersData[] = [
                                'item' => $ownItemIdx++,
                                'type' => '現',
                                'name' => $lnid['LNAM'] ?? '',
                                'id_no' => $ee09,
                                'scope' => $scope,
                                'addr' => $lnid['LADR'] ?? '',
                                'cause' => $causeName,
                                'cause_date' => $causeDateStr,
                                'reg_date' => $regDateStr
                            ];
                        }
                    }
                }
            }

            // 區塊 3：統編/住址更正案件 (WRLNID)
            $sqlWrln = "SELECT LIDN, LNAM, LADR, LS_TYPE FROM WRLNID " .
                       "WHERE LS03=:rm01 AND LS04_1=:rm02 AND LS04_2=:rm03 AND LS_TYPE IN ('M','D','N','A') " .
                       "ORDER BY LS_TYPE";
            $wrlnRows = $this->query($sqlWrln, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);
            if (!empty($wrlnRows)) {
                foreach ($wrlnRows as $wl) {
                    $lsType = trim($wl['LS_TYPE'] ?? '');
                    $typeStr = in_array($lsType, ['M', 'D'], true) ? '原' : '現';
                    $ownersData[] = [
                        'item' => $ownItemIdx++,
                        'type' => $typeStr,
                        'name' => trim($wl['LNAM'] ?? ''),
                        'id_no' => trim($wl['LIDN'] ?? ''),
                        'scope' => '',
                        'addr' => trim($wl['LADR'] ?? ''),
                        'cause' => $causeName,
                        'cause_date' => $causeDateStr,
                        'reg_date' => $regDateStr
                    ];
                }
            }

            // 若該案件無所有權部異動 (countE == 0 且無更正案件)，依規格補上一筆空白列
            if (empty($ownersData)) {
                $ownersData[] = [
                    'item' => 1,
                    'type' => '',
                    'name' => '(空白)',
                    'id_no' => '(空白)',
                    'scope' => '',
                    'addr' => '(空白)',
                    'cause' => $causeName,
                    'cause_date' => $causeDateStr,
                    'reg_date' => $regDateStr
                ];
            }

            // 3. 第三表格：管理機關表 (WRMNGR, MM00='E')
            $managersData = [];
            $mngItemIdx = 1;
            $sqlMng = "SELECT MM00, MS_TYPE, MM13, MM01 " .
                      "FROM WRMNGR " .
                      "WHERE MS03=:rm01 AND MS04_1=:rm02 AND MS04_2=:rm03 AND MM00='E' " .
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

            $validCases[] = [
                'title' => $fullOfficeTitle,
                'office' => $displayOffice,
                'rec_no' => $recNo,
                'change_date' => $changeDateStr,
                'buildings' => $buildingsData,
                'owners' => $ownersData,
                'managers' => $managersData,
                'auditor1' => $name01,
                'auditor2' => $name02
            ];
        }

        if (empty($validCases)) {
            if (is_file($path)) {
                unlink($path);
            }
            return ['state' => 'empty', 'message' => '無資料'];
        }

        $totalPages = count($validCases);
        foreach ($validCases as $idx => $caseData) {
            $caseData['page_text'] = '共 ' . $totalPages . ' 頁 第 ' . ($idx + 1) . ' 頁';
            $this->drawOfficialNoticePage($pdf, $caseData);
        }

        $pdf->Output('F', $path);
        return ['state' => 'success', 'message' => '已完成'];
    }

    /**
     * 繪製正式公務版建物異動通知書 (標準 A4 橫向 842 x 595 pt)
     */
    public function drawOfficialNoticePage(TaxExportPdfWriter $pdf, array $data): void {
        $pdf->AddPage('L', 'A4');

        $startX = 20;
        $totalW = 802;

        // 1. 頁首右上角頁碼
        $pageText = $data['page_text'] ?? '共 1 頁 第 1 頁';
        $pdf->drawCell(720, 18, 102, 12, $pageText, 'R', 8, 0);

        // 2. 頂部大標題
        $title = $data['title'] ?? '桃園市桃園地政事務所 建物異動通知書';
        $pdf->drawCell($startX, 34, $totalW, 24, $title, 'C', 14, 0);

        // 3. 右上角收件日期字號盒 (X=545, Y=38, W=277, H=36)
        $boxX = 545;
        $boxY = 38;
        $boxW = 277;
        $labelW = 55;
        $valW = $boxW - $labelW * 2; // 167

        $pdf->drawCell($boxX, $boxY, $labelW, 36, '收件', 'C', 8.5, 1);
        $pdf->drawCell($boxX + $labelW, $boxY, $labelW, 18, '日期', 'C', 8, 1);
        $pdf->drawCell($boxX + $labelW * 2, $boxY, $valW, 18, $data['change_date'] ?? '', 'C', 8, 1);

        $pdf->drawCell($boxX + $labelW, $boxY + 18, $labelW, 18, '字號', 'C', 8, 1);
        $pdf->drawCell($boxX + $labelW * 2, $boxY + 18, $valW, 18, $data['rec_no'] ?? '', 'C', 8, 1);

        // 4. 第一主區塊【建物標示及基地坐落情形】
        $curY = 80;
        // 表頭第 1 列 (大類)
        $pdf->drawCell($startX, $curY, 28 + 60, 16, '建物建號', 'C', 8, 1);
        $pdf->drawCell($startX + 88, $curY, 55 + 95 + 82, 16, '基地坐落', 'C', 8.5, 1);
        $pdf->drawCell($startX + 320, $curY, 242, 16, '建物門牌', 'C', 8.5, 1);
        $pdf->drawCell($startX + 562, $curY, 240, 16, '登記原因及日期', 'C', 8.5, 1);
        $curY += 16;

        // 表頭第 2 列 (明細欄位)
        $pdf->drawCell($startX, $curY, 28, 18, '項次', 'C', 7.5, 1);
        $pdf->drawCell($startX + 28, $curY, 60, 18, '建號', 'C', 7.5, 1);
        $pdf->drawCell($startX + 88, $curY, 55, 18, '鄉鎮市區', 'C', 7.5, 1);
        $pdf->drawCell($startX + 143, $curY, 95, 18, '段小段', 'C', 7.5, 1);
        $pdf->drawCell($startX + 238, $curY, 82, 18, '地號', 'C', 7.5, 1);
        $pdf->drawCell($startX + 320, $curY, 242, 18, '門牌', 'C', 7.5, 1);
        $pdf->drawCell($startX + 562, $curY, 60, 18, '登記原因', 'C', 7.5, 1);
        $pdf->drawCell($startX + 622, $curY, 60, 18, "原因發生\n日期", 'C', 6.5, 1);
        $pdf->drawCell($startX + 682, $curY, 60, 18, '登記日期', 'C', 7.5, 1);
        $pdf->drawCell($startX + 742, $curY, 60, 18, '備註', 'C', 7.5, 1);
        $curY += 18;

        // 建物資料列
        $buildings = $data['buildings'] ?? [];
        if (empty($buildings)) {
            $pdf->drawCell($startX, $curY, $totalW, 16, '（無建物標示及基地坐落異動資料）', 'C', 8, 1);
            $curY += 16;
        } else {
            foreach ($buildings as $bld) {
                $bH = 16;
                $pdf->drawCell($startX, $curY, 28, $bH, (string)($bld['item'] ?? 1), 'C', 7.5, 1);
                $pdf->drawCell($startX + 28, $curY, 60, $bH, (string)($bld['build_no'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 88, $curY, 55, $bH, (string)($bld['town'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 143, $curY, 95, $bH, (string)($bld['section'] ?? ''), 'L', 7.5, 1);
                $pdf->drawCell($startX + 238, $curY, 82, $bH, (string)($bld['land_no'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 320, $curY, 242, $bH, (string)($bld['addr'] ?? ''), 'L', 7, 1);
                $pdf->drawCell($startX + 562, $curY, 60, $bH, (string)($bld['cause'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 622, $curY, 60, $bH, (string)($bld['cause_date'] ?? ''), 'C', 7, 1);
                $pdf->drawCell($startX + 682, $curY, 60, $bH, (string)($bld['reg_date'] ?? ''), 'C', 7, 1);
                $pdf->drawCell($startX + 742, $curY, 60, $bH, (string)($bld['remark'] ?? ''), 'C', 7, 1);
                $curY += $bH;
            }
        }
        $curY += 6;

        // 5. 第二主區塊【所有權部異動情形】
        $pdf->drawCell($startX, $curY, $totalW, 16, '所有權部異動情形', 'C', 9, 1);
        $curY += 16;

        // 表頭 (9 欄: 28, 25, 115, 80, 70, 210, 90, 92, 92 = 802)
        $pdf->drawCell($startX, $curY, 28, 20, '項次', 'C', 8, 1);
        $pdf->drawCell($startX + 28, $curY, 25, 20, '區分', 'C', 8, 1);
        $pdf->drawCell($startX + 53, $curY, 115, 20, '姓名或名稱', 'C', 8, 1);
        $pdf->drawCell($startX + 168, $curY, 80, 20, '統一編號', 'C', 8, 1);
        $pdf->drawCell($startX + 248, $curY, 70, 20, '權利範圍', 'C', 8, 1);
        $pdf->drawCell($startX + 318, $curY, 210, 20, '住址', 'C', 8, 1);
        $pdf->drawCell($startX + 528, $curY, 90, 20, '登記原因', 'C', 8, 1);
        $pdf->drawCell($startX + 618, $curY, 92, 20, '原因發生日期', 'C', 7.5, 1);
        $pdf->drawCell($startX + 710, $curY, 92, 20, '登記日期', 'C', 7.5, 1);
        $curY += 20;

        $owners = $data['owners'] ?? [];
        if (empty($owners)) {
            $pdf->drawCell($startX, $curY, $totalW, 16, '（無所有權部異動資料）', 'C', 8, 1);
            $curY += 16;
        } else {
            foreach ($owners as $ow) {
                $oH = 16;
                $pdf->drawCell($startX, $curY, 28, $oH, (string)($ow['item'] ?? 1), 'C', 8, 1);
                $pdf->drawCell($startX + 28, $curY, 25, $oH, (string)($ow['type'] ?? ''), 'C', 8, 1);
                $pdf->drawCell($startX + 53, $curY, 115, $oH, (string)($ow['name'] ?? ''), 'L', 7.5, 1);
                $pdf->drawCell($startX + 168, $curY, 80, $oH, (string)($ow['id_no'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 248, $curY, 70, $oH, (string)($ow['scope'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 318, $curY, 210, $oH, (string)($ow['addr'] ?? ''), 'L', 7, 1);
                $pdf->drawCell($startX + 528, $curY, 90, $oH, (string)($ow['cause'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 618, $curY, 92, $oH, (string)($ow['cause_date'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 710, $curY, 92, $oH, (string)($ow['reg_date'] ?? ''), 'C', 7.5, 1);
                $curY += $oH;
            }
        }
        $curY += 6;

        // 6. 第三主區塊【管理機關異動情形】
        $pdf->drawCell($startX, $curY, $totalW, 16, '管理機關異動情形', 'C', 9, 1);
        $curY += 16;

        $pdf->drawCell($startX, $curY, 28, 18, '項次', 'C', 8, 1);
        $pdf->drawCell($startX + 28, $curY, 25, 18, '區分', 'C', 8, 1);
        $pdf->drawCell($startX + 53, $curY, 180, 18, '姓名或名稱', 'C', 8, 1);
        $pdf->drawCell($startX + 233, $curY, 100, 18, '統一編號', 'C', 8, 1);
        $pdf->drawCell($startX + 333, $curY, 469, 18, '住址', 'C', 8, 1);
        $curY += 18;

        $managers = $data['managers'] ?? [];
        if (empty($managers)) {
            $pdf->drawCell($startX, $curY, $totalW, 16, '（無管理機關異動資料）', 'C', 8, 1);
            $curY += 16;
        } else {
            foreach ($managers as $mg) {
                $mH = 16;
                $pdf->drawCell($startX, $curY, 28, $mH, (string)($mg['item'] ?? 1), 'C', 8, 1);
                $pdf->drawCell($startX + 28, $curY, 25, $mH, (string)($mg['type'] ?? ''), 'C', 8, 1);
                $pdf->drawCell($startX + 53, $curY, 180, $mH, (string)($mg['name'] ?? ''), 'L', 7.5, 1);
                $pdf->drawCell($startX + 233, $curY, 100, $mH, (string)($mg['id_no'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 333, $curY, 469, $mH, (string)($mg['addr'] ?? ''), 'L', 7.5, 1);
                $curY += $mH;
            }
        }

        // 7. 頁尾核章欄 (固定於右下方)
        $aud1 = !empty($data['auditor1']) ? $data['auditor1'] : '';
        $aud2 = !empty($data['auditor2']) ? $data['auditor2'] : '';
        $pdf->drawCell(620, 560, 202, 14, '發給人員：' . $aud1, 'L', 8.5, 0);
        $pdf->drawCell(620, 574, 202, 14, '校對人員：' . $aud2, 'L', 8.5, 0);
    }

    /**
     * 舊介面相容方法
     */
    public function drawNoticePage(TaxExportPdfWriter $pdf, array $data): void {
        $this->drawOfficialNoticePage($pdf, $data);
    }

    /**
     * 查詢人員姓名 (sysauth1 / VSYSAUTH)
     */
    private function queryUserName(string $userId): string {
        if ($userId === '') {
            return '';
        }
        $sql1 = "SELECT user_name FROM sysauth1 WHERE user_id = :bv_uid";
        $rows = $this->query($sql1, [':bv_uid' => $userId]);
        if (!empty($rows[0]['USER_NAME'])) {
            return trim($rows[0]['USER_NAME']);
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
    private function formatScope(string $ee15_1, string $ee15_2, string $ee15_3, string $rm01, string $rm02, string $rm03, string $dept = 'E'): string {
        $name = $this->getKeyn('15', $ee15_1);
        if ($ee15_1 === 'A') {
            return $name !== '' ? $name : '全部';
        }
        if ($ee15_2 !== '' && $ee15_2 !== '0') {
            $denom = (int)$ee15_2;
            $numer = (int)$ee15_3;
            return ($name !== '' ? $name . ' ' : '') . $numer . '/' . $denom;
        }
        if ($ee15_1 === 'Z') {
            $sqlZ = "SELECT GG30_2 FROM WRGALL WHERE GS03=:rm01 AND GS04_1=:rm02 AND GS04_2=:rm03 AND GG00=:dept AND GG30_1='99'";
            $zRows = $this->query($sqlZ, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03, ':dept' => $dept]);
            if (!empty($zRows[0]['GG30_2'])) {
                return ($name !== '' ? $name . ' ' : '') . trim($zRows[0]['GG30_2']);
            }
        }
        return $name;
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
     * 格式化建號 (8 碼母子號轉母號-子號，若子號為 000 則僅顯示母號)
     */
    public static function formatBuildNo(string $raw): string {
        $raw = str_pad(trim($raw), 8, '0', STR_PAD_LEFT);
        $main = ltrim(substr($raw, 0, 5), '0');
        if ($main === '') {
            $main = '0';
        }
        $sub = ltrim(substr($raw, 5, 3), '0');
        if ($sub === '') {
            return $main;
        }
        return $main . '-' . $sub;
    }

    /**
     * 格式化地號 (8 碼母子號轉母號-子號，若子號為 0000 則僅顯示母號)
     */
    public static function formatLandNo(string $raw): string {
        $raw = str_pad(trim($raw), 8, '0', STR_PAD_LEFT);
        $main = ltrim(substr($raw, 0, 4), '0');
        if ($main === '') {
            $main = '0';
        }
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
        $clean = preg_replace('/[^0-9]/', '', $roc);
        if (strlen($clean) >= 7) {
            return substr($clean, 0, 3) . '/' . substr($clean, 3, 2) . '/' . substr($clean, 5, 2);
        }
        return $roc;
    }

    /**
     * 7 碼民國日期轉短格式 (YY 年 MM 月 DD 日)
     */
    public static function formatRocDateShort(string $roc): string {
        $clean = preg_replace('/[^0-9]/', '', $roc);
        if (strlen($clean) >= 7) {
            $y = (int)substr($clean, 0, 3);
            $m = (int)substr($clean, 3, 2);
            $d = (int)substr($clean, 5, 2);
            return $y . ' 年 ' . $m . ' 月 ' . $d . ' 日';
        }
        return $roc;
    }

    /**
     * 7 碼民國日期轉「中華民國 YYY 年 MM 月 DD 日」長格式
     */
    public static function formatRocDateLong(string $roc): string {
        $clean = preg_replace('/[^0-9]/', '', $roc);
        if (strlen($clean) >= 7) {
            $y = (int)substr($clean, 0, 3);
            $m = (int)substr($clean, 3, 2);
            $d = (int)substr($clean, 5, 2);
            return '中華民國 ' . $y . ' 年 ' . $m . ' 月 ' . $d . ' 日';
        }
        return $roc;
    }

    /**
     * 執行 SQL 查詢 (保證欄位值轉為純淨 UTF-8)
     */
    private function query(string $sql, array $params = []): array {
        if ($this->dbSelector !== null) {
            $rawRows = call_user_func($this->dbSelector, $sql, $params);
            if (!is_array($rawRows)) {
                return [];
            }
            return self::normalizeRowsUtf8($rawRows);
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
        if (!$wrapper->reachable()) {
            throw new RuntimeException('無法連線至地籍資料庫。');
        }

        $db = $wrapper->getDB();
        $db->parse($sql);
        if (!empty($params)) {
            foreach ($params as $k => &$v) {
                $db->bind($k, $v);
            }
            unset($v);
        }
        $db->execute();
        $rows = $db->fetchAll(true);
        if (!is_array($rows)) {
            return [];
        }
        return self::normalizeRowsUtf8($rows);
    }

    /**
     * 將資料列陣列中的所有字串欄位轉為純淨 UTF-8
     */
    private static function normalizeRowsUtf8(array $rows): array {
        $clean = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                $clean[] = $r;
                continue;
            }
            $item = [];
            foreach ($r as $k => $v) {
                $item[$k] = TaxExportPdfWriter::ensureUtf8($v);
            }
            $clean[] = $item;
        }
        return $clean;
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
