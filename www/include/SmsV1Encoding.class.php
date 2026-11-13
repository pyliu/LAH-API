<?php

/**
 * SmsV1Encoding - Oracle CP950 / ISO-8859-1 / UTF-8 編碼轉換與民國日期時間工具
 */
class SmsV1Encoding {
    /**
     * 取得 Oracle 資料庫目標編碼（預設 CP950）
     *
     * @return string
     */
    public static function getOracleEncoding(): string {
        return defined('ORACLE_ENCODING') ? (string)ORACLE_ENCODING : 'CP950';
    }

    /**
     * 將 Oracle 資料庫原始字串（CP950 / BIG5 位元組）轉換為 UTF-8
     *
     * @param string|null $value
     * @return string
     */
    public static function fromOracleEncoding(?string $value): string {
        if ($value === null || $value === '') {
            return '';
        }
        $converted = @mb_convert_encoding($value, 'UTF-8', self::getOracleEncoding());
        return is_string($converted) ? $converted : $value;
    }

    /**
     * 將 UTF-8 字串轉換為 Oracle 資料庫原始字串（CP950 / BIG5 位元組）
     *
     * @param string|null $value
     * @return string
     */
    public static function toOracleEncoding(?string $value): string {
        if ($value === null || $value === '') {
            return '';
        }
        $converted = @mb_convert_encoding($value, self::getOracleEncoding(), 'UTF-8');
        return is_string($converted) ? $converted : $value;
    }

    /**
     * 將 ISO-8859-1 封裝的 Big5 字串（如舊版 JDBC US7ASCII 讀取格式）轉換為 UTF-8
     * ISO-8859-1 -> Big5/CP950 bytes -> UTF-8
     *
     * @param string|null $value
     * @return string
     */
    public static function iso88591ToUtf8(?string $value): string {
        if ($value === null || $value === '') {
            return '';
        }
        $rawBytes = @mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
        if (!is_string($rawBytes)) {
            return '';
        }
        $utf8 = @mb_convert_encoding($rawBytes, 'UTF-8', self::getOracleEncoding());
        return is_string($utf8) ? $utf8 : '';
    }

    /**
     * 將 UTF-8 字串轉換為 ISO-8859-1 封裝的 Big5 字串（對應舊版 Common.big5ToIso）
     * UTF-8 -> Big5/CP950 bytes -> ISO-8859-1
     *
     * @param string|null $value
     * @return string
     */
    public static function utf8ToIso88591(?string $value): string {
        if ($value === null || $value === '') {
            return '';
        }
        $rawBytes = @mb_convert_encoding($value, self::getOracleEncoding(), 'UTF-8');
        if (!is_string($rawBytes)) {
            return '';
        }
        $iso = @mb_convert_encoding($rawBytes, 'UTF-8', 'ISO-8859-1');
        return is_string($iso) ? $iso : '';
    }

    /**
     * 取得 7 碼民國日期字串（YYYMMDD）
     *
     * @param \DateTimeInterface|null $dt
     * @return string
     */
    public static function getRocDate(?\DateTimeInterface $dt = null): string {
        $target = $dt !== null ? $dt : new \DateTimeImmutable('now');
        $rocYear = (int)$target->format('Y') - 1911;
        return sprintf('%03d%s', $rocYear, $target->format('md'));
    }

    /**
     * 取得 3 碼民國年份字串（YYY）
     *
     * @param \DateTimeInterface|null $dt
     * @return string
     */
    public static function getRocYear(?\DateTimeInterface $dt = null): string {
        return substr(self::getRocDate($dt), 0, 3);
    }

    /**
     * 取得 6 碼時間字串（HHMMSS）
     *
     * @param \DateTimeInterface|null $dt
     * @return string
     */
    public static function getTimeHhmmss(?\DateTimeInterface $dt = null): string {
        $target = $dt !== null ? $dt : new \DateTimeImmutable('now');
        return $target->format('His');
    }
}
