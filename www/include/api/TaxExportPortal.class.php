<?php
require_once __DIR__ . '/TaxExportTxt.trait.php';

final class TaxExportPortal {
    use TaxExportTxt;
    private $base;
    private $curl;
    private $directory;
    private $username;

    public function __construct(string $directory, ?string $base = null, ?string $username = null, ?string $password = null) {
        $base = $base ?? (string)Env::get('TAX_EXPORT_WEB_URL', Env::get('RCD0901_WEB_URL', ''));
        $username = $username ?? (string)Env::get('TAX_EXPORT_WEB_USER', Env::get('RCD0901_WEB_USER', ''));
        $password = $password ?? (string)Env::get('TAX_EXPORT_WEB_PASSWORD', Env::get('RCD0901_WEB_PASSWORD', ''));
        $url = parse_url($base);
        if (!$url || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host']) ||
            isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || $username === '' || $password === '') {
            throw new RuntimeException('請設定稅務匯出原系統連線資訊。');
        }
        $this->base = rtrim($base, '/') . '/';
        $this->username = $username;
        $this->directory = $directory;
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_HTTPAUTH => CURLAUTH_NTLM, CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_COOKIEFILE => $directory . '/cookies', CURLOPT_COOKIEJAR => $directory . '/cookies',
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_UNRESTRICTED_AUTH => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 600
        ]);
    }

    public function advance(array $item, array $batch, string $destination, callable $remember): array {
        if (strpos($item['code'], 'RCD0101_') === 0 || strpos($item['code'], 'RCD0102_') === 0) {
            return $this->notice($item['code'], $batch, $destination);
        }
        return $this->txt($item, $batch, $destination, $remember);
    }

    private function notice(string $code, array $batch, string $destination): array {
        $report = substr($code, 0, 7);
        $this->request($this->base);
        $html = $this->request($this->base . 'REG/RCD01/' . $report . '.jsp');
        $fields = self::fields($html, 'SheetFm');
        if (($fields['REQ'] ?? '') !== $report) {
            throw new RuntimeException('無法開啟異動通知書表單。');
        }
        $start = self::rocParts($batch['begin']);
        $end = self::rocParts($batch['end']);
        $local = substr($code, -5) === 'LOCAL';
        if ($report === 'RCD0101') {
            $fields = array_merge($fields, ['caseRadioGroup' => $local ? '1' : '2', 'radioGroup' => '2',
                'inputYear1' => $start[0], 'inputMonth1' => $start[1], 'inputDay1' => $start[2],
                'inputYear2' => $end[0], 'inputMonth2' => $end[1], 'inputDay2' => $end[2]]);
            $url = $this->base . 'REG/RCD01/RCD0101P.jsp';
        } else {
            $fields = array_merge($fields, ['CaseRadioGroup' => $local ? '1' : '2', 'queryType' => '2',
                'InputYear1' => $start[0], 'InputMonth1' => $start[1], 'InputDay1' => $start[2],
                'InputYear2' => $end[0], 'InputMonth2' => $end[1], 'InputDay2' => $end[2]]);
            $url = $this->base . 'Dispatcher';
        }
        $this->request($url, $fields, $destination);
        $handle = fopen($destination, 'rb');
        $prefix = fread($handle, 5);
        fclose($handle);
        if ($prefix === '%PDF-') {
            return ['state' => 'success'];
        }
        $message = file_get_contents($destination, false, null, 0, 65536);
        if (!mb_check_encoding($message, 'UTF-8')) {
            $message = mb_convert_encoding($message, 'UTF-8', 'CP950');
        }
        if (preg_match('/\A\s*查無資料[!！。]?\s*\z/u', strip_tags($message)) ||
            preg_match('/alert\([\x27"](?:查無|找不到)資料[!！。]?[\x27"]\)/u', $message)) {
            unlink($destination);
            return ['state' => 'empty', 'message' => '無資料'];
        }
        throw new RuntimeException('原系統未回傳有效通知書 PDF。');
    }

    private static function rocParts(string $date): array {
        $parts = explode('-', $date);
        return [str_pad((string)((int)$parts[0] - 1911), 3, '0', STR_PAD_LEFT), $parts[1], $parts[2]];
    }

    private static function fields(string $html, string $name): array {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $fields = [];
        foreach ($xpath->query('//form[@name="' . $name . '"]//input[@type="hidden"]') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        return $fields;
    }

    private function request(string $url, ?array $fields = null, ?string $destination = null): string {
        $base = parse_url($this->base);
        $target = parse_url($url);
        if (($base['scheme'] ?? '') !== ($target['scheme'] ?? '') || ($base['host'] ?? '') !== ($target['host'] ?? '') || ($base['port'] ?? null) !== ($target['port'] ?? null)) {
            throw new RuntimeException('拒絕跨主機的原系統下載。');
        }
        $stream = null;
        try {
            curl_setopt($this->curl, CURLOPT_URL, $url);
            curl_setopt($this->curl, CURLOPT_POST, $fields !== null);
            if ($fields !== null) {
                $pairs = [];
                foreach ($fields as $name => $values) {
                    foreach (is_array($values) ? $values : [$values] as $value) {
                        $pairs[] = rawurlencode($name) . '=' . rawurlencode((string)$value);
                    }
                }
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, implode('&', $pairs));
            }
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, $destination === null);
            if ($destination !== null) {
                $stream = fopen($destination, 'wb');
                if (!$stream) {
                    throw new RuntimeException('無法建立產製暫存檔。');
                }
                curl_setopt($this->curl, CURLOPT_FILE, $stream);
            }
            $body = curl_exec($this->curl);
            $status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            if ($body === false || $status !== 200) {
                throw new RuntimeException('原系統連線或產製失敗（HTTP ' . $status . '）。');
            }
            return $destination === null ? (string)$body : '';
        } finally {
            curl_setopt($this->curl, CURLOPT_COOKIELIST, 'FLUSH');
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
