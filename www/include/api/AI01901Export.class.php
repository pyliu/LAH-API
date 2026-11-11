<?php
require_once __DIR__ . '/CadastralTxtExport.class.php';

final class AI01901Export {
    private const LAND = [
        'AA45' => 1, 'AA46' => 2, 'AA48' => 4, 'AA49' => 8, 'AA05' => 7,
        'AA06' => 2, 'AA08' => 1, 'AA09' => 2, 'AA10' => -9, 'AA11' => 2,
        'AA12' => 2, 'AA16' => 7, 'AA17' => 7
    ];
    private const OWNER = [
        'BA48' => 4, 'BA49' => 8, 'BB01' => 4, 'BB05' => 7, 'BB06' => 2,
        'BB07' => 7, 'BB15_1' => 1, 'BB15_2' => 10, 'BB15_3' => 10,
        'BB16' => -10, 'KRMK' => -5, 'BB21' => -8, 'BB09' => 10,
        'LNAM' => 60, 'LADR' => 60, 'LCDE' => -1
    ];
    private const RIGHT = [
        'CC48' => 4, 'CC49' => 8, 'CC01' => 7, 'CC05' => 7, 'CC06' => 2,
        'CC03' => 3, 'CC04_1' => 4, 'CC04_2' => 6, 'CC27' => 1,
        'CC15_1' => 1, 'CC15_2' => 10, 'CC15_3' => 10, 'CC15_4' => -9,
        'CC16' => 10, 'CC18_1' => 1, 'CC18_2' => 4, 'CC18_3' => 4,
        'CC24' => 60, 'CC29' => 13, 'CC09' => 10, 'LNAM' => 60, 'LADR' => 60,
        'TC17_1' => 1, 'TC17_2' => 20, 'TC19_1' => 1, 'TC19_2' => 7,
        'TC19_3' => 7, 'TC20_1' => 1, 'TC20_2' => 7, 'TC21_1' => 1,
        'TC21_2' => 40, 'TC22_1' => 1, 'TC22_2' => 40, 'TC23_1' => 1,
        'TC23_2' => 40, 'TC25' => 60, 'CC19_1' => 2, 'CC19_2' => 400,
        'CC20_1' => 2, 'CC20_2' => 400, 'TC26_1' => 2, 'TC26_2' => 400,
        'TC27_1' => 2, 'TC27_3' => 7, 'TC27_2' => 400, 'TC28_1' => 2,
        'TC28_2' => 400, 'CC21_1' => 2, 'CC21_2' => 400, 'TC30_1' => 2,
        'TC30_2' => 400, 'TC31_1' => 2, 'TC31_2' => 400, 'TC32_1' => 2,
        'TC32_2' => 400, 'TC33_1' => 2, 'TC33_2' => 400, 'TC34_1' => 2,
        'TC34_2' => 400, 'TC17_G' => 400, 'TC19_G' => 400, 'TC20_G' => 400,
        'TC21_G' => 400, 'TC22_G' => 400
    ];
    private const PRICE = [
        'PA48' => 4, 'PA49' => 8, 'PB01' => 4, 'PB22_1' => 5, 'PB22_2' => 4,
        'PB22_3' => -8, 'PB23_1' => 1, 'PB23_2' => 10, 'PB23_3' => 10
    ];

    public static function readSection(string $section, callable $select): array {
        if (!preg_match('/\A[0-9]{4}\z/', $section)) {
            throw new InvalidArgumentException('AI01901 段碼格式錯誤。');
        }
        $queries = [
            'land' => "SELECT AA45, AA46, AA48, AA49, AA05, AA06, AA08, AA09, AA10,
                              AA11, AA12, AA16, AA17
                       FROM SRALID WHERE AA48 = '$section' ORDER BY AA49, AA05",
            'owners' => "SELECT b.BA48, b.BA49, b.BB01, b.BB05, b.BB06, b.BB07,
                                b.BB15_1, b.BB15_2, b.BB15_3, b.BB16, k.KRMK,
                                b.BB21 * 10 AS BB21, b.BB09, n.LNAM, n.LADR, n.LCDE
                         FROM SRBLOW b, SRLNID n, SRKEYN k
                         WHERE b.BA48 = '$section' AND n.LIDN = b.BB09
                           AND k.KCDE_1 = '00' AND k.KCDE_2 = 'V1'
                         ORDER BY b.BA49, b.BB01",
            'rights' => self::rightQuery($section),
            'managers' => "SELECT m.MM00, m.MM48, m.MM49, m.MM01, m.MM13, n.LNAM, n.LADR
                           FROM SRMNGR m, SRLNID n
                           WHERE m.MM48 = '$section' AND m.MM00 IN ('B', 'C')
                             AND n.LIDN = m.MM13
                           ORDER BY m.MM49, m.MM00, m.MM01, m.MM13",
            'prices' => "SELECT PA48, PA49, PB01, PB22_1, PB22_2, PB22_3,
                                PB23_1, PB23_2, PB23_3
                         FROM SRPRCE WHERE PA48 = '$section'
                         ORDER BY PA49, PB01, PB22_1, PB22_2",
            'remarks' => "SELECT g.GG00, g.GG48, g.GG49, g.GG01, g.GG30_1, g.GG30_2,
                                 CASE WHEN EXISTS (SELECT 1 FROM SRKEYN k
                                                   WHERE k.KCDE_1 = '30' AND k.KCDE_2 = g.GG30_1)
                                      THEN 1 ELSE 0 END AS SELECTED_CODE
                          FROM SRGALL g WHERE g.GG48 = '$section' AND g.GG00 IN ('A', 'B', 'C')
                          ORDER BY g.GG49, g.GG00, g.GG01, g.GG02"
        ];
        $data = [];
        foreach ($queries as $name => $sql) {
            $data[$name] = $select($sql);
            if (!is_array($data[$name])) {
                throw new RuntimeException('AI01901 查詢失敗。');
            }
        }
        return $data;
    }

    private static function rightQuery(string $section): string {
        $fields = [];
        foreach (array_keys(self::RIGHT) as $column) {
            $prefix = substr($column, 0, 2);
            $alias = $prefix === 'CC' ? 'c' : ($prefix === 'TC' ? 't' : 'n');
            $fields[] = $alias . '.' . $column;
        }
        return 'SELECT ' . implode(', ', $fields) . "
                FROM SRCLOR c, SRTOGH t, SRLNID n
                WHERE c.CC00 = 'C' AND c.CC48 = '$section'
                  AND t.TT29 = c.CC29 AND n.LIDN = c.CC09
                ORDER BY c.CC49, c.CC01";
    }

    public static function records(array $data): Generator {
        $owners = self::group($data['owners'], 'BA48', 'BA49');
        $rights = self::group($data['rights'], 'CC48', 'CC49');
        $managers = self::group($data['managers'], 'MM00', 'MM48', 'MM49', 'MM01');
        $prices = self::group($data['prices'], 'PA48', 'PA49', 'PB01');
        $remarks = [];
        $landWithRemarks = [];
        foreach ($data['remarks'] as $remark) {
            $part = $remark['GG00'];
            $parcel = self::key($remark, 'GG48', 'GG49');
            $sequence = $part === 'A' ? '' : self::key($remark, 'GG01');
            $remarks[$part . $parcel . $sequence][] = $remark;
            if ($part === 'A' && !empty($remark['SELECTED_CODE'])) {
                $landWithRemarks[$parcel] = true;
            }
        }
        foreach ($data['land'] as $land) {
            $parcel = self::key($land, 'AA48', 'AA49');
            if (!isset($landWithRemarks[$parcel])) {
                continue;
            }
            yield self::record('A', $land, self::LAND);
            yield from self::remarks($remarks['A' . $parcel] ?? [], 0);
            foreach ($owners[$parcel] ?? [] as $owner) {
                $key = $parcel . self::key($owner, 'BB01');
                yield self::record('B', $owner, self::OWNER);
                yield from self::remarks($remarks['B' . $key] ?? [], 4);
                yield from self::managers($managers['B' . $key] ?? [], $owner['BB09'], 4);
                foreach ($prices[$key] ?? [] as $price) {
                    $price['PB22_3'] = self::scaledPrice($price['PB22_3']);
                    yield self::record('P', $price, self::PRICE);
                }
            }
            foreach ($rights[$parcel] ?? [] as $right) {
                $key = $parcel . self::key($right, 'CC01');
                yield self::record('C', self::rightFields($right), self::RIGHT, false);
                yield from self::remarks($remarks['C' . $key] ?? [], 8);
                yield from self::managers($managers['C' . $key] ?? [], $right['CC09'], 7);
            }
        }
    }

    private static function key(array $row, string ...$columns): string {
        $key = [];
        foreach ($columns as $column) {
            $key[] = trim((string)$row[$column]);
        }
        return implode('', $key);
    }

    private static function group(array $rows, string ...$columns): array {
        $groups = [];
        foreach ($rows as $row) {
            $groups[self::key($row, ...$columns)][] = $row;
        }
        return $groups;
    }

    private static function remarks(array $rows, int $sequenceWidth): Generator {
        $fields = ['GG48' => 4, 'GG49' => 8];
        if ($sequenceWidth > 0) {
            $fields['GG01'] = $sequenceWidth;
        }
        $fields += ['GG30_1' => 2, 'GG30_2' => 2000];
        foreach ($rows as $row) {
            yield self::record('G', $row, $fields, false);
        }
    }

    private static function managers(array $rows, string $holder, int $sequenceWidth): Generator {
        $fields = [
            'MM48' => 4, 'MM49' => 8, 'MM01' => $sequenceWidth,
            'HOLDER' => 10, 'MM13' => 10, 'LNAM' => 60, 'LADR' => 60
        ];
        foreach ($rows as $row) {
            $row['HOLDER'] = $holder;
            yield self::record('M', $row, $fields);
        }
    }

    private static function rightFields(array $right): array {
        $right['TC23_2'] = '';
        foreach (['TC17' => 'Z', 'TC19' => 'Z', 'TC20' => 'Z', 'TC21' => 'Q', 'TC22' => 'Q'] as $field => $code) {
            if (($right[$field . '_1'] ?? '') !== $code) {
                $right[$field . '_G'] = '';
            }
        }
        if ((string)($right['CC15_4'] ?? '') === '') {
            $right['CC15_4'] = '0';
        }
        return $right;
    }

    private static function scaledPrice($value): string {
        if (!is_numeric($value)) {
            throw new RuntimeException('AI01901 地價欄位格式錯誤。');
        }
        $price = (string)((float)$value * 10);
        return strpos($price, '.') === false && stripos($price, 'e') === false ? $price . '.0' : $price;
    }

    private static function record(string $type, array $row, array $fields, bool $removeSpaces = true): string {
        $line = $type;
        foreach ($fields as $field => $width) {
            $value = (string)($row[$field] ?? '');
            if ($removeSpaces) {
                $value = str_replace(' ', '', $value);
            }
            $line .= CadastralTxtExport::field($value, $width);
        }
        return $line;
    }
}
