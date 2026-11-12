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
        $pdf = new TaxExportPdfWriter('L', 'mm', 'A4');
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
            'buildings' => [
                [
                    'item' => 1,
                    'build_no' => '101',
                    'town' => '桃園區',
                    'section' => '中路段',
                    'land_no' => '101',
                    'addr' => '桃園市桃園區中山路100號'
                ],
                [
                    'item' => 2,
                    'build_no' => '101',
                    'town' => '桃園區',
                    'section' => '中路段',
                    'land_no' => '101-1',
                    'addr' => '桃園市桃園區中山路100號'
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

        $this->drawNoticePage($pdf, $caseData1);

        // 產製第 2 頁案件 (展示多案件分頁能力與管理機關表)
        $caseData2 = [
            'title' => $fullOfficeTitle,
            'office' => $officeName,
            'rec_no' => ($isRemote ? '桃跨' : '桃資') . ' 字 第 000002 號',
            'change_date' => self::formatRocDateLong($endRoc),
            'buildings' => [
                [
                    'item' => 1,
                    'build_no' => '202-1',
                    'town' => $isRemote ? '中壢區' : '桃園區',
                    'section' => $isRemote ? '青埔段' : '大興段',
                    'land_no' => '202-1',
                    'addr' => $isRemote ? '桃園市中壢區高鐵南路二段1號' : '桃園市桃園區大興西路二段50號'
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

        $this->drawNoticePage($pdf, $caseData2);

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

            // 1. 第一表格：建物標示及基地坐落表 (TrdbidBody)
            $buildingsData = [];
            $bldItemIdx = 1;

            if ($countD > 0) {
                // 區塊 1：建物標示部有異動
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
                        $sqlW = "SELECT DD46, DD09 FROM WRDBID WHERE DI03=:rm01 AND DI04_1=:rm02 AND DI04_2=:rm03 AND DD48=:dd48 AND DD49=:dd49 AND DS_TYPE='D'";
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
                            $sqlW = "SELECT DD46, DD09 FROM WRDBID WHERE DI03=:rm01 AND DI04_1=:rm02 AND DI04_2=:rm03 AND DD48=:dd48 AND DD49=:dd49";
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
                                'addr' => $dd09
                            ];
                        }
                    } else {
                        $buildingsData[] = [
                            'item' => $bldItemIdx++,
                            'build_no' => self::formatBuildNo($dd49),
                            'town' => $townName,
                            'section' => $bldSectionName !== '' ? $bldSectionName : $dd48,
                            'land_no' => '(空白)',
                            'addr' => $dd09
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
                        $sqlW = "SELECT DD46, DD09 FROM WRDBID WHERE DI03=:rm01 AND DI04_1=:rm02 AND DI04_2=:rm03 AND DD48=:dd48 AND DD49=:dd49";
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
                                'addr' => $dd09
                            ];
                        }
                    } else {
                        $buildingsData[] = [
                            'item' => $bldItemIdx++,
                            'build_no' => self::formatBuildNo($dd49),
                            'town' => $townName,
                            'section' => $bldSectionName !== '' ? $bldSectionName : $dd48,
                            'land_no' => '(空白)',
                            'addr' => $dd09
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
                                    "WHERE EI03=:rm01 AND EI04_1=:rm02 AND EI04_2=:rm03 AND ED48=:ed48 AND ED49=:ed49 AND EE01=:ee01 AND ES_TYPE IN ('M','D') " .
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
                                    "WHERE EI03=:rm01 AND EI04_1=:rm02 AND EI04_2=:rm03 AND ED48=:ed48 AND ED49=:ed49 AND EE01=:ee01 AND ES_TYPE IN ('A','N') " .
                                    "ORDER BY EE01";
                        $currERows = $this->query($sqlCurrE, [
                            ':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03,
                            ':ed48' => $ed48, ':ed49' => $ed49, ':ee01' => $ee01
                        ]);

                        $causeName = $this->getKeyn('06', $rm09);
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
                                'cause' => $causeName !== '' ? $causeName : $rm09,
                                'cause_date' => self::formatRocDate($rm34),
                                'reg_date' => self::formatRocDate($rm33)
                            ];
                        }
                    }
                }
            }

            // 區塊 3：統編/住址更正案件 (WRLNID)
            $sqlWrln = "SELECT LIDN, LNAM, LADR, LS_TYPE FROM WRLNID " .
                       "WHERE LI03=:rm01 AND LI04_1=:rm02 AND LI04_2=:rm03 AND LS_TYPE IN ('M','D','N','A') " .
                       "ORDER BY LS_TYPE";
            $wrlnRows = $this->query($sqlWrln, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);
            if (!empty($wrlnRows)) {
                $causeName = $this->getKeyn('06', $rm09);
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
                        'cause' => $causeName !== '' ? $causeName : $rm09,
                        'cause_date' => self::formatRocDate($rm34),
                        'reg_date' => self::formatRocDate($rm33)
                    ];
                }
            }

            // 若該案件無所有權部異動 (countE == 0 且無更正案件)，依規格補上一筆空白列
            if (empty($ownersData)) {
                $causeName = $this->getKeyn('06', $rm09);
                $ownersData[] = [
                    'item' => 1,
                    'type' => '',
                    'name' => '(空白)',
                    'id_no' => '(空白)',
                    'scope' => '',
                    'addr' => '(空白)',
                    'cause' => $causeName !== '' ? $causeName : $rm09,
                    'cause_date' => self::formatRocDate($rm34),
                    'reg_date' => self::formatRocDate($rm33)
                ];
            }

            // 3. 第三表格：管理機關表 (WRMNGR, MM00='E')
            $managersData = [];
            $mngItemIdx = 1;
            $sqlMng = "SELECT MM00, MS_TYPE, MM13, MM01 " .
                      "FROM WRMNGR " .
                      "WHERE MI03=:rm01 AND MI04_1=:rm02 AND MI04_2=:rm03 AND MM00='E' " .
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
                'buildings' => $buildingsData,
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
     * 繪製單一案件建物異動通知書頁面 (A4 橫向 297mm x 210mm)
     */
    public function drawNoticePage(TaxExportPdfWriter $pdf, array $data): void {
        $pdf->AddPage('L', 'A4');

        $startX = 10;
        $totalWidth = 277;

        // 1. 頂部大標題
        $pdf->SetXY($startX, 10);
        $pdf->SetFont('Big5', 'B', 15);
        $pdf->cellZh($totalWidth, 8, $data['title'] ?? '地政事務所建物異動通知書', 0, 1, 'C');

        // 2. 收件字號與異動日期列
        $pdf->SetFont('Big5', '', 9.5);
        $pdf->SetX($startX);
        $pdf->cellZh(140, 6, '收件字號：' . ($data['rec_no'] ?? ''), 0, 0, 'L');
        $pdf->cellZh(137, 6, '異動日期：' . ($data['change_date'] ?? ''), 0, 1, 'R');

        // 3. 第一表格：建物標示及基地坐落表
        $pdf->SetFont('Big5', 'B', 10);
        $pdf->cellZh($totalWidth, 5.5, '【建物標示及基地坐落表】', 0, 1, 'L');

        // 標示及坐落表頭 (6 欄，寬度 12, 28, 25, 35, 27, 150 = 277mm)
        $pdf->SetFont('Big5', 'B', 8.5);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->cellZh(12, 5.5, '項次', 1, 0, 'C', true);
        $pdf->cellZh(28, 5.5, '建號', 1, 0, 'C', true);
        $pdf->cellZh(25, 5.5, '鄉鎮市區', 1, 0, 'C', true);
        $pdf->cellZh(35, 5.5, '段小段', 1, 0, 'C', true);
        $pdf->cellZh(27, 5.5, '地號', 1, 0, 'C', true);
        $pdf->cellZh(150, 5.5, '建物門牌', 1, 1, 'C', true);

        // 標示及坐落資料列
        $pdf->SetFont('Big5', '', 8);
        $buildings = $data['buildings'] ?? [];
        if (empty($buildings)) {
            $pdf->cellZh($totalWidth, 5, '（無建物標示及基地坐落異動資料）', 1, 1, 'C');
        } else {
            foreach ($buildings as $bld) {
                $pdf->cellZh(12, 5, (string)($bld['item'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(28, 5, (string)($bld['build_no'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(25, 5, (string)($bld['town'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(35, 5, (string)($bld['section'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(27, 5, (string)($bld['land_no'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(150, 5, (string)($bld['addr'] ?? ''), 1, 1, 'L');
            }
        }

        // 4. 第二表格：建物所有權部異動表
        $pdf->Ln(2);
        $pdf->SetFont('Big5', 'B', 10);
        $pdf->cellZh($totalWidth, 5.5, '【建物所有權部異動表】', 0, 1, 'L');

        // 所有權部表頭 (9 欄，寬度 12, 12, 38, 28, 32, 75, 28, 26, 26 = 277mm)
        $pdf->SetFont('Big5', 'B', 8.5);
        $pdf->cellZh(12, 5.5, '項次', 1, 0, 'C', true);
        $pdf->cellZh(12, 5.5, '別', 1, 0, 'C', true);
        $pdf->cellZh(38, 5.5, '所有權人姓名', 1, 0, 'C', true);
        $pdf->cellZh(28, 5.5, '統一編號', 1, 0, 'C', true);
        $pdf->cellZh(32, 5.5, '權利範圍', 1, 0, 'C', true);
        $pdf->cellZh(75, 5.5, '住址', 1, 0, 'C', true);
        $pdf->cellZh(28, 5.5, '登記原因', 1, 0, 'C', true);
        $pdf->cellZh(26, 5.5, '原因發生日期', 1, 0, 'C', true);
        $pdf->cellZh(26, 5.5, '登記日期', 1, 1, 'C', true);

        // 所有權部資料列
        $pdf->SetFont('Big5', '', 8);
        $owners = $data['owners'] ?? [];
        if (empty($owners)) {
            $pdf->cellZh($totalWidth, 5, '（無建物所有權部異動資料）', 1, 1, 'C');
        } else {
            foreach ($owners as $own) {
                $pdf->cellZh(12, 5, (string)($own['item'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(12, 5, (string)($own['type'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(38, 5, (string)($own['name'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(28, 5, (string)($own['id_no'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(32, 5, (string)($own['scope'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(75, 5, (string)($own['addr'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(28, 5, (string)($own['cause'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(26, 5, (string)($own['cause_date'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(26, 5, (string)($own['reg_date'] ?? ''), 1, 1, 'C');
            }
        }

        // 5. 第三表格：管理機關表
        $managers = $data['managers'] ?? [];
        if (!empty($managers)) {
            $pdf->Ln(2);
            $pdf->SetFont('Big5', 'B', 10);
            $pdf->cellZh($totalWidth, 5.5, '【管理機關】', 0, 1, 'L');

            // 管理機關表頭 (5 欄，寬度 12, 12, 65, 38, 150 = 277mm)
            $pdf->SetFont('Big5', 'B', 8.5);
            $pdf->cellZh(12, 5.5, '項次', 1, 0, 'C', true);
            $pdf->cellZh(12, 5.5, '別', 1, 0, 'C', true);
            $pdf->cellZh(65, 5.5, '管理者名稱', 1, 0, 'C', true);
            $pdf->cellZh(38, 5.5, '統一編號', 1, 0, 'C', true);
            $pdf->cellZh(150, 5.5, '住址', 1, 1, 'C', true);

            $pdf->SetFont('Big5', '', 8);
            foreach ($managers as $mng) {
                $pdf->cellZh(12, 5, (string)($mng['item'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(12, 5, (string)($mng['type'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(65, 5, (string)($mng['name'] ?? ''), 1, 0, 'L');
                $pdf->cellZh(38, 5, (string)($mng['id_no'] ?? ''), 1, 0, 'C');
                $pdf->cellZh(150, 5, (string)($mng['addr'] ?? ''), 1, 1, 'L');
            }
        }

        // 6. 底部核章區 (固定於頁面底部 Y=192)
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
            $sqlZ = "SELECT GG30_2 FROM WRGALL WHERE GI03=:rm01 AND GI04_1=:rm02 AND GI04_2=:rm03 AND GG00=:dept AND GG30_1='99'";
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
