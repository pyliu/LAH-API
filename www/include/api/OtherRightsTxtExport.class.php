<?php
require_once __DIR__ . '/CadastralTxtExport.class.php';

final class OtherRightsTxtExport {
    private const RIGHT = [
        'CC48' => -4, 'CC49' => -8, 'CC01' => -7, 'CC05' => -7, 'CC06' => -2,
        'CC03' => -3, 'CC04_1' => -4, 'CC04_2' => -6, 'CC27' => -1,
        'CC15_1' => -1, 'CC15_2' => -10, 'CC15_3' => -10, 'CC15_4' => -9,
        'CC16' => -10, 'CC18_1' => -1, 'CC18_2' => -4, 'CC18_3' => -4,
        'CC24' => 60, 'CC29' => -13, 'CC09' => -10, 'LNAM' => 60, 'LADR' => 60,
        'TC17_1' => -1, 'TC17_2' => 20, 'TC19_1' => -1, 'TC19_2' => -7,
        'TC19_3' => -7, 'TC20_1' => -1, 'TC20_2' => -7, 'TC21_1' => -1,
        'TC21_2' => 40, 'TC22_1' => -1, 'TC22_2' => 40, 'TC25' => 60,
        'CC19_1' => 2, 'CC19_2' => 400, 'CC20_1' => 2, 'CC20_2' => 400,
        'TC26_1' => 2, 'TC26_2' => 400, 'TC27_1' => 2, 'TC27_3' => 7,
        'TC27_2' => 400, 'TC28_1' => 2, 'TC28_2' => 400,
        'CC21_1' => 2, 'CC21_2' => 400, 'TC30_1' => 2, 'TC30_2' => 400,
        'TC31_1' => 2, 'TC31_2' => 400, 'TC32_1' => 2, 'TC32_2' => 400,
        'TC33_1' => 2, 'TC33_2' => 400, 'TC34_1' => 2, 'TC34_2' => 400,
        'TC17_G' => 400, 'TC19_G' => 400, 'TC20_G' => 400, 'TC21_G' => 400, 'TC22_G' => 400
    ];

    public static function readSection(string $section, callable $select, string $part): array {
        if (!preg_match('/\A[0-9]{4}\z/', $section)) {
            throw new InvalidArgumentException('他項權利報表段碼格式錯誤。');
        }
        $rightFields = [];
        $detailFields = [];
        foreach (array_keys(self::fields($part)) as $field) {
            if (substr($field, 0, 2) === 'TC') {
                $detailFields[] = $field;
            } else {
                $rightFields[] = (substr($field, 0, 2) === 'CC' ? 'c.' : 'n.') . $field;
            }
        }
        $queries = [
            'rights' => 'SELECT ' . implode(', ', $rightFields) . "
                         FROM SRCLOR c, SRLNID n
                         WHERE c.CC00 = '$part' AND c.CC48 = '$section' AND n.LIDN = c.CC09
                         ORDER BY c.CC49, c.CC01",
            'details' => 'SELECT TT29, ' . implode(', ', $detailFields) . "
                          FROM SRTOGH WHERE TT29 IN
                            (SELECT CC29 FROM SRCLOR WHERE CC00 = '$part' AND CC48 = '$section')",
            'remarks' => "SELECT GG48, GG49, GG01, GG30_1, GG30_2
                          FROM SRGALL WHERE GG00 = '$part' AND GG48 = '$section'
                          ORDER BY GG49, GG01, GG02",
            'managers' => "SELECT m.MM48, m.MM49, m.MM01, n.LNAM, n.LADR
                           FROM SRMNGR m, SRLNID n
                           WHERE m.MM00 = '$part' AND m.MM48 = '$section' AND n.LIDN = m.MM13
                           ORDER BY m.MM49, m.MM01, m.MM13",
            'sequences' => "SELECT RR48, RR49, RR01, RR28_2
                            FROM SRRLSQ WHERE RR00 = '$part' AND RR48 = '$section'
                            ORDER BY RR49, RR01, RR28_2"
        ];
        $data = [];
        foreach ($queries as $name => $sql) {
            $data[$name] = $select($sql);
            if (!is_array($data[$name])) {
                throw new RuntimeException('他項權利報表查詢失敗。');
            }
        }
        return $data;
    }

    public static function records(array $data, string $part): Generator {
        $details = self::group($data['details'], 'TT29');
        $remarks = self::group($data['remarks'], 'GG48', 'GG49', 'GG01');
        $managers = self::group($data['managers'], 'MM48', 'MM49', 'MM01');
        $sequences = self::group($data['sequences'], 'RR48', 'RR49', 'RR01');
        foreach ($data['rights'] as $right) {
            $key = self::key($right, 'CC48', 'CC49', 'CC01');
            $row = $right + ($details[trim((string)$right['CC29'])][0] ?? []);
            foreach (['TC17' => 'Z', 'TC19' => 'Z', 'TC20' => 'Z', 'TC21' => 'Q', 'TC22' => 'Q'] as $field => $code) {
                if (($row[$field . '_1'] ?? '') !== $code) {
                    $row[$field . '_G'] = '';
                }
            }
            yield self::record($part, $row, self::fields($part));
            $prefix = [
                'CC48' => -4, 'CC49' => -8, 'CC01' => -7
            ];
            foreach ($remarks[$key] ?? [] as $remark) {
                yield self::record('G', $right + $remark, $prefix + ['GG30_1' => -2, 'GG30_2' => 240]);
            }
            foreach ($managers[$key] ?? [] as $manager) {
                yield self::record('M', [
                    'CC48' => $right['CC48'], 'CC49' => $right['CC49'], 'CC01' => $right['CC01'],
                    'LNAM' => $manager['LNAM'], 'LADR' => $manager['LADR']
                ], $prefix + ['LNAM' => 60, 'LADR' => 60]);
            }
            foreach ($sequences[$key] ?? [] as $sequence) {
                yield self::record('R', $right + $sequence, $prefix + ['RR28_2' => -7]);
            }
        }
    }

    private static function fields(string $part): array {
        $fields = self::RIGHT;
        if ($part === 'F') {
            unset($fields['CC15_4']);
        }
        return $fields;
    }

    private static function group(array $rows, string ...$fields): array {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[self::key($row, ...$fields)][] = $row;
        }
        return $grouped;
    }

    private static function key(array $row, string ...$fields): string {
        $key = '';
        foreach ($fields as $field) {
            $key .= trim((string)$row[$field]);
        }
        return $key;
    }

    private static function record(string $type, array $row, array $fields): string {
        $line = $type;
        foreach ($fields as $field => $width) {
            $line .= CadastralTxtExport::field((string)($row[$field] ?? ''), $width);
        }
        return $line;
    }
}
