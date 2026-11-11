<?php

trait TaxExportTxt {
    private function txt(array $item, array $batch, string $destination, callable $remember): array {
        $fid = $item['code'];
        if (!in_array($fid, ['AI001', 'AI002', 'AI037'], true)) {
            throw new InvalidArgumentException('不支援的異動資料種類。');
        }
        $key = hash('sha256', strtolower($this->base . "\0" . $this->username) . "\0" . $fid);
        $leasePath = dirname($this->directory) . '/' . $key . '.lease';
        $lock = fopen(dirname($this->directory) . '/' . $key . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) { fclose($lock); }
            throw new RuntimeException('原系統同類挑檔忙碌，請稍後重試。', 409);
        }
        $job = $item['source_job'] ?? null;
        $owned = false;
        $lease = null;
        try {
            if (is_file($leasePath)) {
                $lease = json_decode(file_get_contents($leasePath), true);
                if (!is_array($lease) || !isset($lease['owner'], $lease['complete']) || !is_bool($lease['complete']) || !array_key_exists('job', $lease)) {
                    throw new RuntimeException('來源工作租約損壞，拒絕另啟挑檔。');
                }
                if ($lease['owner'] !== $batch['batch'] && !$lease['complete']) {
                    throw new RuntimeException('其他批次仍持有原系統挑檔租約，請待其完成。', 409);
                }
                if ($job && $lease['owner'] === $batch['batch'] && $lease['complete'] &&
                    ($lease['terminal_failure'] ?? false) && $lease['job'] === null && ($lease['last_file'] ?? '') === $job['filename']) {
                    $remember(null);
                    $job = null;
                }
                if ($job && ($lease['owner'] !== $batch['batch'] || $lease['job'] !== $job)) {
                    throw new RuntimeException('來源工作已不屬於此批次，拒絕重新送出。', 409);
                }
            } elseif ($job) {
                throw new RuntimeException('已送出工作的來源租約遺失，拒絕重新送出。');
            }
            $owned = true;
            if ($lease && $lease['owner'] === $batch['batch'] && $lease['job']) {
                $job = $lease['job'];
                if (empty($item['source_job'])) { $remember($job); }
            }
            if (!$job) {
                $lease = ['owner' => $batch['batch'], 'complete' => false, 'job' => null,
                    'last_file' => $lease['job']['filename'] ?? ($lease['last_file'] ?? '')];
                self::txtLease($leasePath, $lease);
            }
            // Disk session cookies from the constructor must not reintroduce another item's JSESSIONID.
            curl_setopt($this->curl, CURLOPT_COOKIELIST, 'FLUSH');
            curl_setopt($this->curl, CURLOPT_COOKIELIST, 'ALL');
            $cookie = $this->directory . '/cookies-' . $fid;
            curl_setopt_array($this->curl, [CURLOPT_COOKIESESSION => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);
            if ($job && is_file($cookie)) {
                foreach (file($cookie, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if ($line[0] !== '#' || strpos($line, '#HttpOnly_') === 0) {
                        curl_setopt($this->curl, CURLOPT_COOKIELIST, $line);
                    }
                }
            }
            if (!$job) {
                $this->request($this->base);
                $apply = $this->base . 'ADM/AI/AI03301.jsp?FID=' . $fid . '&FDS=MOIADM&OFDS=MOIADM';
                $fields = self::txtForm($this->request($apply));
                if (!array_key_exists('ED_OBJECT', $fields) || !array_key_exists('ED_UNIT', $fields)) {
                    throw new RuntimeException('無法開啟挑檔申請表單。');
                }
                $fields = self::txtForm($this->request($apply, array_merge($fields,
                    ['hdnOp' => 'savelogin', 'ED_OBJECT' => '稅務局資料', 'ED_UNIT' => '稅務局', 'REMARK' => ''])));
                $filename = $fields['ED_FILENAME'] ?? '';
                if (!preg_match('/\A' . $fid . '_' . preg_quote($fields['user_id'] ?? '', '/') . '_([0-9]{7})([0-9]{6})\.txt\z/', $filename, $match) ||
                    strcasecmp($fields['user_id'] ?? '', $this->username) !== 0 || empty($fields['ERM06']) || !is_array($fields['ERM06'])) {
                    throw new RuntimeException('挑檔表單、操作員或預設登記原因不符。');
                }
                if ($filename === $lease['last_file']) {
                    throw new RuntimeException('原系統同秒挑檔識別重複，請稍後重試。', 409);
                }
                $start = self::rocParts($batch['begin']);
                $end = self::rocParts($batch['end']);
                foreach (['YEAR', 'MONTH', 'DAY'] as $index => $part) {
                    $fields['ED_S' . $part] = $start[$index];
                    $fields['ED_E' . $part] = $end[$index];
                }
                foreach (['c45', 'c46', 'c48', 'c06', 'c08', 'c11', 'c12', 'c30'] as $name) {
                    if (array_key_exists($name, $fields)) { $fields[$name] = '0'; }
                }
                foreach (['EAA48S', 'EAA48E', 'EAA49S', 'EAA49E', 'HEAA49S', 'HEAA49E', 'SLANDNO', 'ELANDNO'] as $name) {
                    if (array_key_exists($name, $fields)) { $fields[$name] = ''; }
                }
                $fields = array_merge($fields, ['hdnOp' => 'save', 'c01' => '1', 'c02' => '1', 'RD_CNTYPE' => 'big5', 'EBB09' => 'T']);
                if ($fid !== 'AI002') {
                    $fields['ELIND'] = 'T';
                    $fields[$fid === 'AI001' ? 'CtOrCfdValue' : 'StOrCfdValue'] = 'CFD';
                }
                $job = ['fid' => $fid, 'user' => $fields['user_id'], 'date' => $match[1], 'time' => $match[2], 'filename' => $filename];
                $lease['job'] = $job;
                self::txtLease($leasePath, $lease);
                $remember($job); // After this durable boundary even a failed save response must never trigger another save.
                $this->request($this->base . 'ADM/AI/' . $fid . '01.jsp?FID=' . $fid . '&FDS=MOIADM', $fields);
                return ['state' => 'pending', 'message' => '已送出原系統挑檔，等待結果'];
            }
            if (($job['fid'] ?? '') !== $fid || strcasecmp($job['user'] ?? '', $this->username) !== 0 ||
                !preg_match('/\A[0-9]{7}\z/', $job['date'] ?? '') || !preg_match('/\A[0-9]{6}\z/', $job['time'] ?? '') ||
                $job['filename'] !== $fid . '_' . $job['user'] . '_' . $job['date'] . $job['time'] . '.txt') {
                throw new RuntimeException('持久化挑檔識別無效。');
            }
            $query = ['GRP' => 'ADM', 'REQ' => '', 'state' => 'queryAll', 'edit_s' => $job['date'], 'edit_e' => $job['date'],
                'user_id_sel' => $job['user'], 'exec_id' => $fid, 'q_status' => ''];
            foreach (['s', 'e'] as $suffix) {
                $query['edit_yyy_' . $suffix] = substr($job['date'], 0, 3);
                $query['edit_mm_' . $suffix] = substr($job['date'], 3, 2);
                $query['edit_dd_' . $suffix] = substr($job['date'], 5, 2);
            }
            $url = $this->base . 'ADM/AI/AI03901.jsp';
            $rows = self::txtDocument($this->request($url, $query));
            $names = ['AI001' => '地籍異動資料', 'AI002' => '地價異動資料', 'AI037' => '建物異動資料'];
            $matches = [];
            foreach ($rows->query('//tr') as $row) {
                $cells = $rows->query('./td', $row);
                if ($cells->length === 7 && preg_replace('/[^0-9]/', '', $cells->item(0)->textContent) === $job['date'] . $job['time'] &&
                    trim($cells->item(1)->textContent) === $names[$fid] && strcasecmp(trim($cells->item(2)->textContent), $job['user']) === 0) {
                    $matches[] = $cells->item(4);
                }
            }
            if (!$matches) { return ['state' => 'pending', 'message' => '尚未查到原系統工作，僅續查、不重送']; }
            if (count($matches) !== 1) { throw new RuntimeException('原系統挑檔識別不唯一。'); }
            $status = trim($matches[0]->textContent);
            if ($status === '產製中') { return ['state' => 'pending', 'message' => '原系統產製中']; }
            if (in_array($status, ['無符合條件資料', '產製失敗'], true)) {
                $lease['complete'] = true;
                if ($status === '產製失敗') {
                    $lease['last_file'] = $job['filename'];
                    $lease['job'] = null;
                    $lease['terminal_failure'] = true;
                }
                self::txtLease($leasePath, $lease);
                if ($status === '產製失敗') {
                    $remember(null);
                    $job = null;
                    throw new RuntimeException('原系統挑檔失敗，可重新申請重試。');
                }
                return ['state' => 'empty', 'message' => '無資料'];
            }
            if (!in_array($status, ['產製完成可下載', '已下載'], true)) { throw new RuntimeException('原系統挑檔狀態無法辨識。'); }
            $links = $rows->query('.//a[@href]', $matches[0]);
            if ($links->length !== 1) { throw new RuntimeException('找不到唯一的挑檔下載連結。'); }
            $url = $this->txtURL($links->item(0)->getAttribute('href'), $url, 'ADM/AI/AI03902.jsp');
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $keys);
            $expectedKeys = ['QODATE' => $job['date'], 'QOTIME' => $job['time'], 'EXEC_ID' => $fid, 'USER_ID' => $job['user'], 'fileNameStr' => $job['filename']];
            ksort($keys);
            ksort($expectedKeys);
            if ($keys !== $expectedKeys) {
                throw new RuntimeException('挑檔下載連結不屬於本次工作。');
            }
            foreach (['ADM/FileDownloadDisplay.jsp', 'ADM/FileDownload.jsp'] as $path) {
                $document = self::txtDocument($this->request($url));
                $metas = $document->query('//meta[translate(@http-equiv,"REFSH","refsh")="refresh"]');
                if ($metas->length !== 1 || !preg_match('/\A\s*0\s*;\s*url\s*=\s*(.+)\z/i', $metas->item(0)->getAttribute('content'), $next)) {
                    throw new RuntimeException('原系統未回傳預期下載轉址。');
                }
                $url = $this->txtURL(trim($next[1], " \t\r\n\"'"), $url, $path);
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parameters);
                $download = $parameters['downloadname'] ?? '';
                if (($parameters['defaultname'] ?? '') !== $job['filename'] || !is_string($download) ||
                    !preg_match('~(?:\A|/)work/report/adm/' . preg_quote($job['filename'], '~') . '\z~', $download) ||
                    preg_match('~[\\\\%]|(?:\A|/)\.\.(?:/|\z)~', $download)) {
                    throw new RuntimeException('下載轉址的檔名或來源路徑不符。');
                }
                if ($path === 'ADM/FileDownloadDisplay.jsp') {
                    $this->txtURL($parameters['TOURL'] ?? '', $url, 'ADM/FileDownload.jsp');
                }
            }
            try {
                $this->request($url, null, $destination);
                $count = self::txtCount($destination, $fid);
            } catch (Throwable $error) {
                if (is_file($destination)) { unlink($destination); }
                throw $error;
            }
            $lease['complete'] = true;
            self::txtLease($leasePath, $lease);
            return ['state' => $count ? 'success' : 'empty', 'message' => $count ? '已完成，共 ' . $count . ' 筆' : '無資料'];
        } finally {
            try {
                if ($owned && !$job && is_array($lease)) {
                    $lease['complete'] = true;
                    self::txtLease($leasePath, $lease);
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private static function txtDocument(string $html): DOMXPath {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        return new DOMXPath($document);
    }

    private static function txtForm(string $html): array {
        $xpath = self::txtDocument($html);
        $forms = $xpath->query('//form[@name="frmMain"]');
        if ($forms->length !== 1) { throw new RuntimeException('原系統未回傳唯一申請或產製表單。'); }
        $fields = [];
        foreach ($xpath->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $forms->item(0)) as $control) {
            if ($control->hasAttribute('disabled')) { continue; }
            $name = $control->getAttribute('name');
            $type = strtolower($control->getAttribute('type'));
            if (in_array($type, ['button', 'submit', 'reset', 'file'], true) ||
                (in_array($type, ['radio', 'checkbox'], true) && !$control->hasAttribute('checked'))) { continue; }
            if ($control->nodeName === 'select') {
                $values = [];
                foreach ($xpath->query('./option[@selected and not(@disabled)] | ./optgroup[not(@disabled)]/option[@selected and not(@disabled)]', $control) as $option) {
                    $values[] = $option->getAttribute('value');
                }
                $fields[$name] = $control->hasAttribute('multiple') ? $values : ($values[0] ?? '');
            } else {
                $fields[$name] = $control->nodeName === 'textarea' ? $control->textContent : $control->getAttribute('value');
            }
        }
        return $fields;
    }

    private function txtURL(string $reference, string $parent, string $path): string {
        if ($reference === '' || preg_match('/[\x00-\x20\\\\]/', $reference)) { throw new RuntimeException('無效的挑檔下載網址。'); }
        $base = parse_url($this->base);
        $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
        $url = strpos($reference, '/') === 0 ? $origin . $reference :
            (preg_match('~\Ahttps?://~i', $reference) ? $reference : substr($parent, 0, strrpos($parent, '/') + 1) . $reference);
        $target = parse_url($url);
        if (!$target || isset($target['user']) || isset($target['pass']) || isset($target['fragment']) ||
            ($target['scheme'] ?? '') !== $base['scheme'] || strcasecmp($target['host'] ?? '', $base['host']) !== 0 ||
            ($target['port'] ?? null) !== ($base['port'] ?? null) || ($target['path'] ?? '') !== $base['path'] . $path) {
            throw new RuntimeException('拒絕非預期路徑或跨主機的挑檔下載。');
        }
        return $url;
    }

    private static function txtCount(string $path, string $fid): int {
        $stream = fopen($path, 'rb');
        if (!$stream) { throw new RuntimeException('無法讀取挑檔結果。'); }
        $lengths = ['AI001' => ['A' => 167, 'M' => 167, 'B' => 207, 'G' => 1021],
            'AI037' => ['D' => 106, 'E' => 207, 'G' => 1021, 'H' => 29]];
        $count = 0;
        $trailer = false;
        try {
            while (($line = fgets($stream, 4096)) !== false) {
                if ($trailer) { throw new RuntimeException('TXT 筆數尾碼之後仍有資料。'); }
                $line = rtrim($line, "\r\n");
                if (preg_match('/\A##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 ([0-9]+) \xB5\xA7\xB8\xEA\xAE\xC6\z/', $line, $match)) {
                    if ((ltrim($match[1], '0') ?: '0') !== (string)$count) { throw new RuntimeException('TXT 資料列數與尾碼不符。'); }
                    $trailer = true;
                } else {
                    $expected = $fid === 'AI002' ? 103 : ($lengths[$fid][$line[13] ?? ''] ?? 0);
                    if (!$expected || strlen($line) !== $expected) { throw new RuntimeException('TXT 固定欄位格式不符。'); }
                    ++$count;
                }
            }
            if (!feof($stream) || !$trailer) { throw new RuntimeException('TXT 內容不完整或缺少筆數尾碼。'); }
            return $count;
        } finally { fclose($stream); }
    }

    private static function txtLease(string $path, array $lease): void {
        $temporary = tempnam(dirname($path), 'lease-');
        if ($temporary === false) { throw new RuntimeException('無法建立來源工作租約。'); }
        try {
            $json = json_encode($lease, JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $path)) {
                throw new RuntimeException('無法持久化來源工作租約。');
            }
        } finally { if (is_file($temporary)) { unlink($temporary); } }
    }
}
