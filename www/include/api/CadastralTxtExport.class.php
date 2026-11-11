<?php

final class CadastralTxtExport {
    public static function execute(string $code, string $name, $sections, string $reportClass): bool {
        global $today;

        if (!is_array($sections) || $sections === []) {
            echoJSONResponse('請選擇要匯出的段碼。', STATUS_CODE::DEFAULT_FAIL);
            return false;
        }
        $normalized = [];
        foreach ($sections as $section) {
            if ((!is_int($section) && !is_string($section)) || !preg_match('/\A[0-9]{1,4}\z/', (string)$section)) {
                echoJSONResponse($code . ' 段碼格式錯誤。', STATUS_CODE::DEFAULT_FAIL);
                return false;
            }
            $normalized[] = str_pad((string)$section, 4, '0', STR_PAD_LEFT);
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_STRING);

        $system = System::getInstance();
        $site = $system->getSiteCode();
        $filename = $today . '_' . $code . '_' . $site . implode('_' . $site, $normalized) . '_' . $name . '.txt';
        $temporary = null;
        $output = null;
        try {
            $mock = $system->isMockMode();
            $db = null;
            if (!$mock) {
                $wrapper = new OraDBWrapper();
                if (!$wrapper->reachable()) {
                    throw new RuntimeException('無法連線至地籍資料庫。');
                }
                $db = $wrapper->getDB();
            }
            $temporary = tempnam(EXPORT_DIR, 'cadastral-');
            if ($temporary === false || ($output = fopen($temporary, 'wb')) === false) {
                throw new RuntimeException('無法開啟地籍匯出暫存檔。');
            }
            $count = 0;
            if ($db !== null) {
                foreach ($normalized as $section) {
                    $data = $reportClass::readSection($section, function ($sql) use ($db) {
                        $db->parse($sql);
                        $db->execute();
                        return $db->fetchAll(true);
                    });
                    $count += self::writeRecords($output, $reportClass::records($data));
                }
            }
            self::write($output, self::trailer($count));
            $closed = fclose($output);
            $output = null;
            if (!$closed || !rename($temporary, EXPORT_DIR . DIRECTORY_SEPARATOR . $filename)) {
                throw new RuntimeException('無法儲存地籍匯出檔。');
            }
        } catch (Throwable $error) {
            Logger::getInstance()->error(__METHOD__ . ': ' . $error->getMessage());
            echoJSONResponse($code . ' ' . $name . '產製失敗。', STATUS_CODE::FAIL_LOAD_ERROR);
            return false;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
            if ($temporary && is_file($temporary)) {
                unlink($temporary);
            }
        }
        $_SESSION[$code] = $filename;
        echoJSONResponse($filename . '已建立。', STATUS_CODE::SUCCESS_NORMAL, ['filename' => $filename]);
        return true;
    }

    public static function writeRecords($output, iterable $records): int {
        $count = 0;
        foreach ($records as $record) {
            self::write($output, $record . "\n");
            $count++;
        }
        return $count;
    }

    public static function field(string $value, int $width): string {
        $size = abs($width);
        $cut = mb_strcut($value, 0, $size, 'CP950');
        if (strlen($cut) < min($size, strlen($value))) {
            $cut .= '?';
        }
        return str_pad($cut, $size, ' ', $width < 0 ? STR_PAD_LEFT : STR_PAD_RIGHT);
    }

    public static function trailer(int $count): string {
        return mb_convert_encoding('##### TAG #####共產製 ' . $count . ' 筆資料', 'CP950', 'UTF-8');
    }

    private static function write($output, string $bytes): void {
        if (fwrite($output, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('無法寫入地籍匯出檔。');
        }
    }

}
