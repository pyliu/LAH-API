<?php

final class Rcd0901Portal {
    private $baseUrl;
    private $curl;
    private $form;

    public function __construct(string $baseUrl, string $username, string $password) {
        $url = parse_url($baseUrl);
        if (!$url || !in_array($url['scheme'] ?? '', ['http', 'https'], true) ||
            empty($url['host']) || isset($url['user']) || isset($url['pass']) ||
            isset($url['query']) || isset($url['fragment'])) {
            throw new InvalidArgumentException('RCD0901_WEB_URL 須為地政系統的 HTTP 或 HTTPS 基底網址。');
        }
        if ($username === '' || $password === '') {
            throw new RuntimeException('請設定 RCD0901_WEB_USER 與 RCD0901_WEB_PASSWORD。');
        }
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_HTTPAUTH => CURLAUTH_NTLM,
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_UNRESTRICTED_AUTH => false,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 600
        ]);
    }

    public function fetch(string $section, string $part, string $destination, string $startNo = '00000000', string $endNo = '99999999'): bool {
        if (!preg_match('/\A[0-9]{4}\z/', $section) || !in_array($part, ['C', 'F'], true) ||
            !preg_match('/\A[0-9]{8}\z/', $startNo) || !preg_match('/\A[0-9]{8}\z/', $endNo) ||
            strcmp($startNo, $endNo) > 0) {
            throw new InvalidArgumentException('清冊段碼或地建別錯誤。');
        }
        if ($this->form === null) {
            $this->request($this->baseUrl);
            $html = $this->request($this->baseUrl . 'REG/RCD09/RCD0901.jsp');
            $this->form = self::formFields($html);
        }
        $fields = array_replace($this->form, [
            'TYPE' => $part, '_TYPE' => $part,
            'START_LSEC' => $section, 'END_LSEC' => $section,
            'START_NO' => substr($startNo, 0, $part === 'C' ? 4 : 5) . '-' . substr($startNo, $part === 'C' ? 4 : 5),
            'END_NO' => substr($endNo, 0, $part === 'C' ? 4 : 5) . '-' . substr($endNo, $part === 'C' ? 4 : 5),
            'fileType' => 'pdf', 'GRP' => 'REG',
            'REQ' => $part === 'C' ? 'RCD0901-1' : 'RCD0902-1',
            'xsl' => $part === 'C' ? 'RCD0901' : 'RCD0902'
        ]);
        $this->request($this->baseUrl . 'Dispatcher', $fields, $destination);
        $stream = fopen($destination, 'rb');
        if ($stream === false) {
            throw new RuntimeException('無法讀取地政清冊回應。');
        }
        $prefix = fread($stream, 5);
        fclose($stream);
        if ($prefix === '%PDF-') {
            return true;
        }
        $message = file_get_contents($destination, false, null, 0, 65536);
        if (!mb_check_encoding($message, 'UTF-8')) {
            $message = mb_convert_encoding($message, 'UTF-8', 'CP950');
        }
        if (preg_match('/alert\([\x27"]找不到資料[!！]?[\x27"]\)/u', $message)) {
            return false;
        }
        throw new RuntimeException('地政系統未回傳有效 PDF，請確認報表帳號權限與產製狀態。');
    }

    public static function formFields(string $html): array {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $fields = [];
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//form[@id="form1"]//input[@type="hidden"]') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        if (empty($fields['PATH']) || ($fields['xml'] ?? '') !== 'tw.gov.moiland.REG.RCD09.RCD0901') {
            throw new RuntimeException('無法開啟 RCD0901 地籍整理清冊表單，請確認登入與權限。');
        }
        return $fields;
    }

    private function request(string $url, ?array $fields = null, ?string $destination = null): string {
        $stream = null;
        try {
            curl_setopt($this->curl, CURLOPT_URL, $url);
            curl_setopt($this->curl, CURLOPT_POST, $fields !== null);
            if ($fields !== null) {
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($fields));
            }
            if ($destination !== null) {
                $stream = fopen($destination, 'wb');
                if ($stream === false) {
                    throw new RuntimeException('無法建立地政清冊暫存檔。');
                }
                curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($this->curl, CURLOPT_FILE, $stream);
            } else {
                curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            }
            $body = curl_exec($this->curl);
            $status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            if ($body === false || $status !== 200) {
                throw new RuntimeException('地政清冊服務連線失敗（HTTP ' . $status . '）。');
            }
            return $destination === null ? (string)$body : '';
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
