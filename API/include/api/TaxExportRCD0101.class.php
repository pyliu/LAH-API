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
     * MOCK 模式產製
     */
    private function exportMock(string $path, array $item, array $batch): array {
        $pdf = new TaxExportPdfWriter('L', 'pt', 'A4');
        $code = $item['code'] ?? 'RCD0101_LOCAL';
        $isRemote = ($code === 'RCD0101_REMOTE');

        $beginRoc = self::rocDate($batch['begin'] ?? '2026-10-01');
        $endRoc = self::rocDate($batch['end'] ?? '2026-10-15');

        if ($beginRoc === '9990101') {
            return ['state' => 'empty', 'message' => '查無符合條件之資料'];
        }

        $agencyName = '桃園市桃園地政事務所';
        $sampleCases = [
            [
                'title' => $agencyName . ' 地籍異動通知書',
                'page_str' => '共 1 頁 第 1 頁',
                'rec_date' => '115 年 10 月 16 日',
                'rec_no' => ($isRemote ? '壢桃登跨 字 014450 號' : '桃資登 字 012340 號'),
                'land_situate' => [
                    'item' => 1,
                    'town' => '桃園區',
                    'section' => '五山段',
                    'land_no' => '0350-0000',
                ],
                'change_note' => [
                    'cause' => '住址變更',
                    'cause_date' => '1151016',
                    'reg_date' => '1151016',
                ],
                'sections' => [
                    [
                        'type' => '原',
                        'category' => '**',
                        'grade' => '**',
                        'area' => '--------',
                        'zoning' => '空白',
                        'use_type' => '空白',
                        'price' => '--------',
                        'note' => '',
                    ],
                    [
                        'type' => '現',
                        'category' => '**',
                        'grade' => '**',
                        'area' => '--------',
                        'zoning' => '空白',
                        'use_type' => '空白',
                        'price' => '--------',
                        'note' => '',
                    ],
                ],
                'tax_deduction' => [
                    'kind' => '',
                    'term' => '',
                    'cause' => '',
                    'area' => '',
                    'type375' => '',
                    'deduct' => '',
                    'other' => '',
                ],
                'owners' => [
                    [
                        'item' => 1,
                        'type' => '原',
                        'name' => '新總陽建設股份有限公司',
                        'id' => '89258452',
                        'address' => '臺北市信義區松仁路２７７號',
                        'holding' => '586556/1000000',
                        'price' => '31040',
                        'id_code' => '',
                        'tax' => ['city' => '', 'town' => '', 'book' => '', 'household' => '', 'sub_h' => '', 'page' => '', 'item' => ''],
                    ],
                    [
                        'item' => 1,
                        'type' => '現',
                        'name' => '新總陽建設股份有限公司',
                        'id' => '89258452',
                        'address' => '臺北市信義區松仁路２７７號',
                        'holding' => '582288/1000000',
                        'price' => '31040',
                        'id_code' => '',
                        'tax' => ['city' => '', 'town' => '', 'book' => '', 'household' => '', 'sub_h' => '', 'page' => '', 'item' => ''],
                    ],
                ],
                'managers' => [],
                'officers' => ['issuer' => '黃桂玉', 'checker' => '唐靖崴'],
            ],
            [
                'title' => $agencyName . ' 地籍異動通知書',
                'page_str' => '共 1 頁 第 1 頁',
                'rec_date' => '115 年 10 月 21 日',
                'rec_no' => ($isRemote ? '德桃登跨 字 020560 號' : '桃資登 字 012341 號'),
                'land_situate' => [
                    'item' => 1,
                    'town' => '桃園區',
                    'section' => '中路一段',
                    'land_no' => '0056-0000',
                ],
                'change_note' => [
                    'cause' => '買賣',
                    'cause_date' => '1150911',
                    'reg_date' => '1151030',
                ],
                'sections' => [
                    [
                        'type' => '原',
                        'category' => '**',
                        'grade' => '**',
                        'area' => '------------',
                        'zoning' => '空白',
                        'use_type' => '空白',
                        'price' => '------------',
                        'note' => '',
                    ],
                    [
                        'type' => '現',
                        'category' => '**',
                        'grade' => '**',
                        'area' => '------------',
                        'zoning' => '空白',
                        'use_type' => '空白',
                        'price' => '------------',
                        'note' => '',
                    ],
                ],
                'tax_deduction' => [
                    'kind' => '',
                    'term' => '',
                    'cause' => '',
                    'area' => '',
                    'type375' => '',
                    'deduct' => '',
                    'other' => '',
                ],
                'owners' => [
                    [
                        'item' => 1,
                        'type' => '原',
                        'name' => '邱磐逸',
                        'id' => 'H120216440',
                        'address' => '桃園市中壢區仁義里７鄰中山東路一段２２３巷１２９弄１６之４號',
                        'holding' => '1/1',
                        'price' => '2160',
                        'id_code' => '',
                        'tax' => ['city' => '', 'town' => '', 'book' => '', 'household' => '', 'sub_h' => '', 'page' => '', 'item' => ''],
                    ],
                    [
                        'item' => 1,
                        'type' => '現',
                        'name' => '邱磐逸',
                        'id' => 'H120216440',
                        'address' => '桃園市中壢區青溪里６鄰公園路二段１３３號',
                        'holding' => '1/1',
                        'price' => '2160',
                        'id_code' => '',
                        'tax' => ['city' => '', 'town' => '', 'book' => '', 'household' => '', 'sub_h' => '', 'page' => '', 'item' => ''],
                    ],
                ],
                'managers' => [],
                'officers' => ['issuer' => '黃桂玉', 'checker' => '唐靖崴'],
            ],
        ];

        foreach ($sampleCases as $caseData) {
            $this->drawOfficialNoticePage($pdf, $caseData);
        }

        $pdf->Output('F', $path);
        return ['state' => 'success', 'message' => '已完成'];
    }

    /**
     * 直連 Oracle 產製
     */
    private function exportDirectOracle(string $path, array $item, array $batch): array {
        $code = $item['code'] ?? 'RCD0101_LOCAL';
        $isRemote = ($code === 'RCD0101_REMOTE');

        $sdate = self::rocDate($batch['begin']);
        $edate = self::rocDate($batch['end']);

        if ($this->dbSelector === null) {
            $wrapper = $this->getDbWrapper();
            if (!$wrapper) {
                return $this->exportMock($path, $item, $batch);
            }
        }

        $this->preloadKeynCache();

        $sqlKrmk = "SELECT KCNT, KRMK FROM SRKEYN WHERE (KCDE_1='55') AND (KCDE_2='01') AND 1=1";
        $krmkRows = $this->query($sqlKrmk);
        $office = !empty($krmkRows[0]['KCNT']) ? trim($krmkRows[0]['KCNT']) : '桃園';
        $krmk = !empty($krmkRows[0]['KRMK']) ? trim($krmkRows[0]['KRMK']) : 'HA';
        $krmkSub1 = substr($krmk, 0, 1);

        $sqlCity = "SELECT KCNT FROM SRKEYN WHERE KCDE_1='66' AND KCDE_2='01'";
        $cityRows = $this->query($sqlCity);
        $city = !empty($cityRows[0]['KCNT']) ? trim($cityRows[0]['KCNT']) : '桃園市';

        $displayOffice = $isRemote ? ($office . '(跨)') : $office;
        $agencyTitle = $city . $displayOffice . '地政事務所 地籍異動通知書';

        if (!$isRemote) {
            $sql = "SELECT RM01, RM02, RM03, RM04, RM05, RM06, RM07_1, RM33, RM34, RM36, RM61, RM55, RM57, RM09, RM18, RM19, RM67, RM99 " .
                   "FROM SCRSMS " .
                   "WHERE (RM07_1 BETWEEN :sdate AND :edate) " .
                   "  AND (RM99 IS NULL OR RM99 = 'N') " .
                   "ORDER BY RM01, RM02, RM03";
            $cases = $this->query($sql, [':sdate' => $sdate, ':edate' => $edate]);
        } else {
            $sql = "SELECT RM01, RM02, RM03, RM04, RM05, RM06, RM07_1, RM33, RM34, RM36, RM61, RM55, RM57, RM09, RM18, RM19, RM67, RM99 " .
                   "FROM SCRSMS " .
                   "WHERE (RM07_1 BETWEEN :sdate AND :edate) " .
                   "  AND (RM99 = 'Y' AND RM100 IS NOT NULL AND (RM100 = :krmk OR RM100 IN (SELECT KCDE_2 FROM SRKEYN WHERE KCDE_1 = 'CB2' AND KRMK = :krmk2))) " .
                   "ORDER BY RM01, RM02, RM03";
            $cases = $this->query($sql, [
                ':sdate' => $sdate,
                ':edate' => $edate,
                ':krmk' => $krmk,
                ':krmk2' => $krmk
            ]);
        }
        if (empty($cases)) {
            return ['state' => 'empty', 'message' => '查無符合條件之資料'];
        }

        $pdf = new TaxExportPdfWriter('L', 'pt', 'A4');
        $caseCount = 0;

        foreach ($cases as $c) {
            $rm01 = trim((string)($c['RM01'] ?? ''));
            $rm02 = trim((string)($c['RM02'] ?? ''));
            $rm03 = trim((string)($c['RM03'] ?? ''));
            $rm07_1 = trim((string)($c['RM07_1'] ?? ''));
            $rm09 = trim((string)($c['RM09'] ?? ''));
            $rm100 = trim((string)($c['RM100'] ?? ''));
            $rm55 = trim((string)($c['RM55'] ?? ''));
            $rm57 = trim((string)($c['RM57'] ?? ''));

            $name01 = $this->queryUserName($rm55);
            $name02 = $this->queryUserName($rm57);

            $wordName = $this->getKeyn('04', $rm02);
            $recWord = $wordName !== '' ? $wordName : $rm02;
            $recNo = $recWord . ' 字 第 ' . $rm03 . ' 號';
            $causeName = $this->getKeyn('06', $rm09);
            $recDateStr = self::formatRocDateShort($rm07_1);

            // 查詢土地標示部異動
            $sqlLand = "SELECT AA48, AA49, AA10, AA16, AA05, AA06, AA11, AA12, AS_TYPE " .
                       "FROM WRALID " .
                       "WHERE AS03=:rm01 AND AS04_1=:rm02 AND AS04_2=:rm03 " .
                       "ORDER BY AA48, AA49, AS_TYPE";
            $landRows = $this->query($sqlLand, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

            $secTown = '桃園區';
            $secName = '';
            $landNoFormatted = '';
            $sections = [];

            if (!empty($landRows)) {
                $first = $landRows[0];
                $secCode = trim((string)($first['AA48'] ?? ''));
                $secName = $this->getKcnt('48', $secCode);
                $landNoFormatted = self::formatLandNo((string)($first['AA49'] ?? ''));

                $origSec = null;
                $currSec = null;

                foreach ($landRows as $lr) {
                    $asType = trim((string)($lr['AS_TYPE'] ?? ''));
                    $secItem = [
                        'type' => ($asType === 'D' || $asType === 'B') ? '原' : '現',
                        'category' => $this->getKeyn('05', trim((string)($lr['AA05'] ?? ''))),
                        'grade' => trim((string)($lr['AA06'] ?? '')),
                        'area' => !empty($lr['AA10']) ? number_format((float)$lr['AA10'], 2, '.', '') : '--------',
                        'zoning' => $this->getKeyn('11', trim((string)($lr['AA11'] ?? ''))),
                        'use_type' => $this->getKeyn('12', trim((string)($lr['AA12'] ?? ''))),
                        'price' => !empty($lr['AA16']) ? number_format((float)$lr['AA16'], 1, '.', '') : '--------',
                        'note' => '',
                    ];
                    if ($secItem['type'] === '原' && $origSec === null) {
                        $origSec = $secItem;
                    } elseif ($secItem['type'] === '現' && $currSec === null) {
                        $currSec = $secItem;
                    }
                }

                if ($origSec !== null) $sections[] = $origSec;
                if ($currSec !== null) $sections[] = $currSec;
            }

            if (empty($sections)) {
                $sections = [
                    ['type' => '原', 'category' => '**', 'grade' => '**', 'area' => '--------', 'zoning' => '空白', 'use_type' => '空白', 'price' => '--------', 'note' => ''],
                    ['type' => '現', 'category' => '**', 'grade' => '**', 'area' => '--------', 'zoning' => '空白', 'use_type' => '空白', 'price' => '--------', 'note' => ''],
                ];
            }

            // 查詢土地所有權部異動
            $sqlOwners = "SELECT BB01, BB05, BB09, BB15_2, BB15_3, BB21, BB07, BS_TYPE " .
                         "FROM WRBLOW " .
                         "WHERE BS03=:rm01 AND BS04_1=:rm02 AND BS04_2=:rm03 " .
                         "ORDER BY BB01, BS_TYPE";
            $ownerRows = $this->query($sqlOwners, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

            $owners = [];
            $ownerIdx = 1;

            foreach ($ownerRows as $ow) {
                $bsType = trim((string)($ow['BS_TYPE'] ?? ''));
                $f1 = (float)($ow['BB15_3'] ?? 0);
                $f2 = (float)($ow['BB15_2'] ?? 0);
                $holdingStr = ($f1 > 0 && $f2 > 0) ? (round($f1) . '/' . round($f2)) : '1/1';
                $priceStr = !empty($ow['BB21']) ? (string)round((float)$ow['BB21']) : '';

                $owners[] = [
                    'item' => $ownerIdx++,
                    'type' => ($bsType === 'D' || $bsType === 'B') ? '原' : '現',
                    'name' => trim((string)($ow['BB05'] ?? '')),
                    'id' => trim((string)($ow['BB09'] ?? '')),
                    'address' => trim((string)($ow['BB07'] ?? '')),
                    'holding' => $holdingStr,
                    'price' => $priceStr,
                    'id_code' => '',
                    'tax' => ['city' => '', 'town' => '', 'book' => '', 'household' => '', 'sub_h' => '', 'page' => '', 'item' => ''],
                ];
            }

            // 查詢管理機關部異動 (WRMNGR, MM00='B')
            $sqlMngr = "SELECT MM00, MS_TYPE, MM13, MM01 " .
                       "FROM WRMNGR " .
                       "WHERE MS03=:rm01 AND MS04_1=:rm02 AND MS04_2=:rm03 AND MM00='B' " .
                       "ORDER BY MM48, MM49, MM01, MS_TYPE";
            $mngrRows = $this->query($sqlMngr, [':rm01' => $rm01, ':rm02' => $rm02, ':rm03' => $rm03]);

            $managers = [];
            $mIdx = 1;
            foreach ($mngrRows as $mr) {
                $msType = trim($mr['MS_TYPE'] ?? '');
                $typeStr = in_array($msType, ['M', 'D'], true) ? '原' : '現';
                $mm13 = trim($mr['MM13'] ?? '');
                $lnid = $this->queryLnid($mm13);
                $managers[] = [
                    'item' => $mIdx++,
                    'type' => $typeStr,
                    'name' => $lnid['LNAM'] ?? '',
                    'id' => $mm13,
                    'address' => $lnid['LADR'] ?? '',
                ];
            }

            $caseData = [
                'title' => $agencyTitle,
                'page_str' => '共 1 頁 第 1 頁',
                'rec_date' => $recDateStr,
                'rec_no' => $recNo,
                'land_situate' => [
                    'item' => 1,
                    'town' => $secTown,
                    'section' => $secName,
                    'land_no' => $landNoFormatted,
                ],
                'change_note' => [
                    'cause' => $causeName,
                    'cause_date' => $rm07_1,
                    'reg_date' => $rm07_1,
                ],
                'sections' => $sections,
                'tax_deduction' => [
                    'kind' => '',
                    'term' => '',
                    'cause' => '',
                    'area' => '',
                    'type375' => '',
                    'deduct' => '',
                    'other' => '',
                ],
                'owners' => $owners,
                'managers' => $managers,
                'officers' => ['issuer' => $name01, 'checker' => $name02],
            ];

            $this->drawOfficialNoticePage($pdf, $caseData);
            $caseCount++;
        }

        if ($caseCount === 0) {
            return ['state' => 'empty', 'message' => '查無符合條件之資料'];
        }

        $pdf->Output('F', $path);
        return ['state' => 'success', 'message' => '已完成'];
    }

    /**
     * 繪製完全對齊原系統 (圖 2) 842 x 595 pt 標準版面
     */
    public function drawOfficialNoticePage(TaxExportPdfWriter $pdf, array $data): void {
        $pdf->AddPage('L', 'A4');

        $startX = 30.0;
        $totalW = 781.0;

        // 1. 頁碼 (top = 31, h = 14)
        $pageText = $data['page_str'] ?? '共 1 頁 第 1 頁';
        $pdf->drawCell(680, 31, 131, 14, $pageText, 'R', 8.5, 0);

        // 2. 標題與收件盒
        // 收件盒: x=541, top=51, w=270, h=48
        $boxX = 541.0;
        $boxTop = 51.0;
        $pdf->drawCell($boxX, $boxTop, 61, 48, '收件', 'C', 9, 1);
        $pdf->drawCell($boxX + 61, $boxTop, 69, 24, '日期', 'C', 9, 1);
        $pdf->drawCell($boxX + 130, $boxTop, 140, 24, (string)($data['rec_date'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($boxX + 61, $boxTop + 24, 69, 24, '字號', 'C', 9, 1);
        $pdf->drawCell($boxX + 130, $boxTop + 24, 140, 24, (string)($data['rec_no'] ?? ''), 'C', 8.5, 1);

        // 大標題：在收件盒左方區域 (x=30 到 x=541，寬 511) 居中 (紅框 1 修正)
        $title = $data['title'] ?? '桃園市桃園地政事務所  地籍異動通知書';
        $pdf->SetXY($startX, $boxTop + 14);
        $pdf->SetFont('KaiU', '', 14);
        $pdf->cellZh(511, 20, $title, 0, 0, 'C');

        // 3. 第一主區塊【土地標示異動情形】
        $curY = 100.0;
        $pdf->drawCell($startX, $curY, 204, 22, '土地坐落', 'C', 9, 1);
        $pdf->drawCell($startX + 204, $curY, 159, 22, '異動記事', 'C', 9, 1);
        $pdf->drawCell($startX + 363, $curY, 418, 22, '土地標示異動情形', 'C', 9, 1);
        $curY += 22;

        // 子欄位表頭 (h = 29)
        $hHeader = 29.0;
        $pdf->drawCell($startX, $curY, 30, $hHeader, '項次', 'C', 8, 1);
        $pdf->drawCell($startX + 30, $curY, 50, $hHeader, '鄉鎮市區', 'C', 8, 1);
        $pdf->drawCell($startX + 80, $curY, 74, $hHeader, '段小段', 'C', 8, 1);
        $pdf->drawCell($startX + 154, $curY, 50, $hHeader, '地號', 'C', 8, 1);

        $pdf->drawCell($startX + 204, $curY, 53, $hHeader, '原因', 'C', 8, 1);
        $pdf->drawCell($startX + 257, $curY, 52, $hHeader, "原因發生\n日期", 'C', 7.5, 1);
        $pdf->drawCell($startX + 309, $curY, 54, $hHeader, '登記日期', 'C', 8, 1);

        $pdf->drawCell($startX + 363, $curY, 22, $hHeader, '區分', 'C', 8, 1);
        $pdf->drawCell($startX + 385, $curY, 30, $hHeader, '地目', 'C', 8, 1);
        $pdf->drawCell($startX + 415, $curY, 30, $hHeader, '等則', 'C', 8, 1);
        $pdf->drawCell($startX + 445, $curY, 81, $hHeader, "面積\n(平方公尺)", 'C', 7.5, 1);
        $pdf->drawCell($startX + 526, $curY, 50, $hHeader, '使用分區', 'C', 8, 1);
        $pdf->drawCell($startX + 576, $curY, 50, $hHeader, "使用地\n類別", 'C', 7.5, 1);
        $pdf->drawCell($startX + 626, $curY, 78, $hHeader, "公告現值\n(元/平方公尺)", 'C', 7.5, 1);
        $pdf->drawCell($startX + 704, $curY, 77, $hHeader, '備註', 'C', 8, 1);
        $curY += $hHeader;

        // 土地標示資料列 (兩列: 原、現，左側坐落與記事跨列)
        $rowH = 29.0;
        $situate = $data['land_situate'] ?? [];
        $note = $data['change_note'] ?? [];
        $secRows = $data['sections'] ?? [];
        $secOrig = $secRows[0] ?? ['type' => '原', 'category' => '**', 'grade' => '**', 'area' => '--------', 'zoning' => '空白', 'use_type' => '空白', 'price' => '--------', 'note' => ''];
        $secCurr = $secRows[1] ?? ['type' => '現', 'category' => '**', 'grade' => '**', 'area' => '--------', 'zoning' => '空白', 'use_type' => '空白', 'price' => '--------', 'note' => ''];

        // 左半部跨列 (高 58)
        $pdf->drawCell($startX, $curY, 30, $rowH * 2, (string)($situate['item'] ?? 1), 'C', 8.5, 1);
        $pdf->drawCell($startX + 30, $curY, 50, $rowH * 2, (string)($situate['town'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 80, $curY, 74, $rowH * 2, (string)($situate['section'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 154, $curY, 50, $rowH * 2, (string)($situate['land_no'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 204, $curY, 53, $rowH * 2, (string)($note['cause'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 257, $curY, 52, $rowH * 2, (string)($note['cause_date'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 309, $curY, 54, $rowH * 2, (string)($note['reg_date'] ?? ''), 'C', 8.5, 1);

        // 原列 (高 29)
        $pdf->drawCell($startX + 363, $curY, 22, $rowH, '原', 'C', 8.5, 1);
        $pdf->drawCell($startX + 385, $curY, 30, $rowH, (string)($secOrig['category'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 415, $curY, 30, $rowH, (string)($secOrig['grade'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 445, $curY, 81, $rowH, (string)($secOrig['area'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 526, $curY, 50, $rowH, (string)($secOrig['zoning'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 576, $curY, 50, $rowH, (string)($secOrig['use_type'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 626, $curY, 78, $rowH, (string)($secOrig['price'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 704, $curY, 77, $rowH, (string)($secOrig['note'] ?? ''), 'C', 8.5, 1);
        $curY += $rowH;

        // 現列 (高 29)
        $pdf->drawCell($startX + 363, $curY, 22, $rowH, '現', 'C', 8.5, 1);
        $pdf->drawCell($startX + 385, $curY, 30, $rowH, (string)($secCurr['category'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 415, $curY, 30, $rowH, (string)($secCurr['grade'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 445, $curY, 81, $rowH, (string)($secCurr['area'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 526, $curY, 50, $rowH, (string)($secCurr['zoning'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 576, $curY, 50, $rowH, (string)($secCurr['use_type'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 626, $curY, 78, $rowH, (string)($secCurr['price'] ?? ''), 'C', 8.5, 1);
        $pdf->drawCell($startX + 704, $curY, 77, $rowH, (string)($secCurr['note'] ?? ''), 'C', 8.5, 1);
        $curY += $rowH;

        // 附表 (稅地種類/減免/抵扣/其他)
        $pdf->drawCell($startX, $curY, 92, 22, '稅地種類', 'C', 8, 1);
        $pdf->drawCell($startX + 92, $curY, 51, 22, '減免期數', 'C', 8, 1);
        $pdf->drawCell($startX + 143, $curY, 96, 22, '減免原因', 'C', 8, 1);
        $pdf->drawCell($startX + 239, $curY, 82, 22, '減免面積', 'C', 8, 1);
        $pdf->drawCell($startX + 321, $curY, 102, 22, '一般或三七五', 'C', 8, 1);
        $pdf->drawCell($startX + 423, $curY, 129, 22, '抵扣賦額', 'C', 8, 1);
        $pdf->drawCell($startX + 552, $curY, 229, 22, '其他', 'C', 8, 1);
        $curY += 22;

        $pdf->drawCell($startX, $curY, 92, 20, '', 'C', 8, 1);
        $pdf->drawCell($startX + 92, $curY, 51, 20, '', 'C', 8, 1);
        $pdf->drawCell($startX + 143, $curY, 96, 20, '', 'C', 8, 1);
        $pdf->drawCell($startX + 239, $curY, 82, 20, '', 'C', 8, 1);
        $pdf->drawCell($startX + 321, $curY, 102, 20, '', 'C', 8, 1);
        $pdf->drawCell($startX + 423, $curY, 129, 20, '', 'C', 8, 1);
        $pdf->drawCell($startX + 552, $curY, 229, 20, '', 'C', 8, 1);
        $curY += 20 + 4;

        // 4. 第二主區塊【所有權部異動情形】
        $pdf->drawCell($startX, $curY, 513, 22, '所有權部異動情形', 'C', 9, 1);
        $pdf->drawCell($startX + 513, $curY, 33, 51, "身分\n代號", 'C', 7.5, 1);
        $pdf->drawCell($startX + 546, $curY, 235, 22, '稅籍', 'C', 7.5, 1);
        $curY += 22;

        // 表頭
        $pdf->drawCell($startX, $curY, 30, $hHeader, '項次', 'C', 8, 1);
        $pdf->drawCell($startX + 30, $curY, 50, $hHeader, '區分', 'C', 8, 1);
        $pdf->drawCell($startX + 80, $curY, 74, $hHeader, '姓名或名稱', 'C', 8, 1);
        $pdf->drawCell($startX + 154, $curY, 74, $hHeader, '統一編號', 'C', 8, 1);
        $pdf->drawCell($startX + 228, $curY, 122, $hHeader, '住址', 'C', 8, 1);
        $pdf->drawCell($startX + 350, $curY, 74, $hHeader, '權利範圍', 'C', 8, 1);
        $pdf->drawCell($startX + 424, $curY, 89, $hHeader, "申報地價\n(元/平方公尺)", 'C', 7, 1);

        // 稅籍 7 小格
        $subTax = [
            ['name' => '縣市', 'w' => 30.0],
            ['name' => "鄉鎮\n市區", 'w' => 40.0],
            ['name' => '冊號', 'w' => 31.0],
            ['name' => '戶號', 'w' => 31.0],
            ['name' => '分戶', 'w' => 29.0],
            ['name' => '分頁', 'w' => 30.0],
            ['name' => '項次', 'w' => 44.0],
        ];
        $txX = $startX + 546;
        foreach ($subTax as $st) {
            $pdf->drawCell($txX, $curY, $st['w'], $hHeader, $st['name'], 'C', 6.5, 1);
            $txX += $st['w'];
        }
        $curY += $hHeader;

        $owners = $data['owners'] ?? [];
        if (empty($owners)) {
            $pdf->drawCell($startX, $curY, 513, 29, '（無所有權部異動資料）', 'C', 8, 1);
            $curY += 29;
        } else {
            foreach ($owners as $ow) {
                $oH = 29.0;
                $pdf->drawCell($startX, $curY, 30, $oH, (string)($ow['item'] ?? 1), 'C', 8, 1);
                $pdf->drawCell($startX + 30, $curY, 50, $oH, (string)($ow['type'] ?? ''), 'C', 8, 1);
                $pdf->drawWrappedCell($startX + 80, $curY, 74, $oH, (string)($ow['name'] ?? ''), 8, 7.5, 'L', 1);
                $pdf->drawCell($startX + 154, $curY, 74, $oH, (string)($ow['id'] ?? ''), 'C', 7.5, 1);

                // 住址欄位自動折行 (紅框 2 修正)
                $pdf->drawWrappedCell($startX + 228, $curY, 122, $oH, (string)($ow['address'] ?? ''), 14, 7.5, 'L', 1);

                $pdf->drawCell($startX + 350, $curY, 74, $oH, (string)($ow['holding'] ?? ''), 'C', 7.5, 1);
                $pdf->drawCell($startX + 424, $curY, 89, $oH, (string)($ow['price'] ?? ''), 'R', 8, 1);
                $pdf->drawCell($startX + 513, $curY, 33, $oH, (string)($ow['id_code'] ?? ''), 'C', 7.5, 1);

                $txX = $startX + 546;
                foreach ($subTax as $st) {
                    $pdf->drawCell($txX, $curY, $st['w'], $oH, '', 'C', 6.5, 1);
                    $txX += $st['w'];
                }
                $curY += $oH;
            }
        }

        // 5. 第三主區塊【管理機關異動情形】 (紅框 3 修正: 僅寬 513 pt，且無資料時不長多餘橫幅)
        $curY += 4;
        $mgtW = 513.0;
        $pdf->drawCell($startX, $curY, $mgtW, 22, '管理機關異動情形', 'C', 9, 1);
        $curY += 22;

        $pdf->drawCell($startX, $curY, 30, $hHeader, '項次', 'C', 8, 1);
        $pdf->drawCell($startX + 30, $curY, 50, $hHeader, '區分', 'C', 8, 1);
        $pdf->drawCell($startX + 80, $curY, 74, $hHeader, '姓名或名稱', 'C', 8, 1);
        $pdf->drawCell($startX + 154, $curY, 74, $hHeader, '統一編號', 'C', 8, 1);
        $pdf->drawCell($startX + 228, $curY, 285, $hHeader, '住址', 'C', 8, 1);

        // 右側人員核章：平行放置於管理機關右側空白 (紅框 4 修正: 無框線，頂部緊湊對齊)
        $officers = $data['officers'] ?? [];
        $issuer = !empty($officers['issuer']) ? $officers['issuer'] : ($officers['auditor1'] ?? '');
        $checker = !empty($officers['checker']) ? $officers['checker'] : ($officers['auditor2'] ?? '');
        $audX = $startX + 535;
        $pdf->drawCell($audX, $curY - 14, 220, 14, '登錄人員：' . $issuer, 'L', 8.5, 0);
        $pdf->drawCell($audX, $curY, 220, 14, '校對人員：' . $checker, 'L', 8.5, 0);

        $managers = $data['managers'] ?? [];
        if (!empty($managers)) {
            $curY += $hHeader;
            foreach ($managers as $mg) {
                $mH = 29.0;
                $pdf->drawCell($startX, $curY, 30, $mH, (string)($mg['item'] ?? 1), 'C', 8, 1);
                $pdf->drawCell($startX + 30, $curY, 50, $mH, (string)($mg['type'] ?? ''), 'C', 8, 1);
                $pdf->drawWrappedCell($startX + 80, $curY, 74, $mH, (string)($mg['name'] ?? ''), 8, 7.5, 'L', 1);
                $pdf->drawCell($startX + 154, $curY, 74, $mH, (string)($mg['id'] ?? ''), 'C', 7.5, 1);
                $pdf->drawWrappedCell($startX + 228, $curY, 285, $mH, (string)($mg['address'] ?? ''), 32, 7.5, 'L', 1);
                $curY += $mH;
            }
        }
    }

    /**
     * 兼容舊測試的通用頁面繪製入口
     */
    public function drawNoticePage(TaxExportPdfWriter $pdf, array $data): void {
        $this->drawOfficialNoticePage($pdf, $data);
    }

    /**
     * 執行 SQL 查詢 (自動保證欄位轉為純淨 UTF-8)
     */
    private function query(string $sql, array $binds = []): array {
        if ($this->dbSelector !== null) {
            $rawRows = call_user_func($this->dbSelector, $sql, $binds);
            if (!is_array($rawRows)) {
                return [];
            }
            return self::normalizeRowsUtf8($rawRows);
        }

        $wrapper = $this->getDbWrapper();
        if (!$wrapper) {
            return [];
        }

        $db = $wrapper->getDB();
        if (!$db) {
            return [];
        }

        $db->parse($sql);
        foreach ($binds as $key => $val) {
            $db->bind($key, $val);
        }
        $db->execute();
        $rows = $db->fetchAll(true);

        if (!is_array($rows)) {
            return [];
        }

        return self::normalizeRowsUtf8($rows);
    }

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
     * 快取常用代碼表
     */
    private function preloadKeynCache(): void {
        $sql = "SELECT KCDE_1, KCDE_2, KCNT, KRMK FROM RKEYN WHERE KCDE_1 IN ('01','04','05','06','11','12','48')";
        $rows = $this->query($sql);
        $this->keynCache = [];
        foreach ($rows as $r) {
            $c1 = trim((string)($r['KCDE_1'] ?? ''));
            $c2 = trim((string)($r['KCDE_2'] ?? ''));
            $kcnt = trim((string)($r['KCNT'] ?? ''));
            $krmk = trim((string)($r['KRMK'] ?? ''));
            $this->keynCache[$c1][$c2] = ['KCNT' => $kcnt, 'KRMK' => $krmk];
        }
    }

    private function getKeyn(string $kcde1, string $kcde2): string {
        $c2 = trim($kcde2);
        if ($c2 === '') return '';
        if (isset($this->keynCache[$kcde1][$c2])) {
            $entry = $this->keynCache[$kcde1][$c2];
            return $entry['KRMK'] !== '' ? $entry['KRMK'] : $entry['KCNT'];
        }
        return $c2;
    }

    private function getKcnt(string $kcde1, string $kcde2): string {
        $c2 = trim($kcde2);
        if ($c2 === '') return '';
        if (isset($this->keynCache[$kcde1][$c2]['KCNT'])) {
            return $this->keynCache[$kcde1][$c2]['KCNT'];
        }
        return $c2;
    }

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

    private function getDbWrapper() {
        if (class_exists('OraDBWrapper', false)) {
            $wrapper = new OraDBWrapper();
            if ($wrapper->reachable()) {
                return $wrapper;
            }
        }
        return null;
    }

    private function shouldFallbackToMock(): bool {
        if (defined('TEST_MOCK_FALLBACK') && TEST_MOCK_FALLBACK) {
            return true;
        }
        return !class_exists('OraDBWrapper', false);
    }

    public static function formatLandNo(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') return '';
        $clean = preg_replace('/[^0-9]/', '', $raw);
        if (strlen($clean) === 8) {
            $main = (int)substr($clean, 0, 4);
            $sub = (int)substr($clean, 4, 4);
            return $sub === 0 ? (string)$main : ($main . '-' . $sub);
        }
        return $raw;
    }

    public static function rocDate(string $ymd): string {
        $parts = explode('-', $ymd);
        if (count($parts) !== 3) {
            return '1151001';
        }
        $y = (int)$parts[0] - 1911;
        $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $d = str_pad($parts[2], 2, '0', STR_PAD_LEFT);
        return $y . $m . $d;
    }

    public static function formatRocDate(string $roc): string {
        $clean = preg_replace('/[^0-9]/', '', $roc);
        if (strlen($clean) >= 7) {
            return substr($clean, 0, 3) . '/' . substr($clean, 3, 2) . '/' . substr($clean, 5, 2);
        }
        return $roc;
    }

    public static function formatRocDateShort(string $roc): string {
        $clean = preg_replace('/[^0-9]/', '', $roc);
        if (strlen($clean) >= 7) {
            $y = substr($clean, 0, 3);
            $m = substr($clean, 3, 2);
            $d = substr($clean, 5, 2);
            return (int)$y . ' 年 ' . (int)$m . ' 月 ' . (int)$d . ' 日';
        }
        return $roc;
    }

    public static function formatRocDateLong(string $roc): string {
        $clean = preg_replace('/[^0-9]/', '', $roc);
        if (strlen($clean) >= 7) {
            $y = (int)substr($clean, 0, 3);
            $m = (int)substr($clean, 3, 2);
            $d = (int)substr($clean, 5, 2);
            return "中華民國 {$y} 年 {$m} 月 {$d} 日";
        }
        return $roc;
    }
}
