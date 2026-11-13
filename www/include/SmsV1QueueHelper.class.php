<?php

/**
 * SmsV1QueueHelper - 移植 SmsMainFrame.TTSearchTimer 狀態判定與佇列搬移規則
 */
class SmsV1QueueHelper {
    /**
     * 依 MS_TYPE、MS30、sType 與 check_register_a/b/c/d 旗標決定對 SSMSWAIT 與 SSMSMAIN 的動作
     *
     * @param string $msType
     * @param string $ms30
     * @param string $sType
     * @param bool $checkRegisterA
     * @param bool $checkRegisterB
     * @param bool $checkRegisterC
     * @param bool $checkRegisterD
     * @return array{insert_wait: bool, wait_mode: string, wait_ms30: string, main_action: string, new_main_ms30: string}
     */
    public static function resolveAction(
        string $msType,
        string $ms30,
        string $sType,
        bool $checkRegisterA,
        bool $checkRegisterB,
        bool $checkRegisterC,
        bool $checkRegisterD
    ): array {
        $noop = array(
            'insert_wait' => false,
            'wait_mode' => 'none',
            'wait_ms30' => '',
            'main_action' => 'none',
            'new_main_ms30' => '',
        );

        if ($sType === 'I') {
            if ($ms30 === '0' && $checkRegisterA) {
                return array(
                    'insert_wait' => true,
                    'wait_mode' => 'legacy',
                    'wait_ms30' => 'I',
                    'main_action' => 'update_ms30',
                    'new_main_ms30' => 'I',
                );
            }
            return $noop;
        }

        if ($sType === 'C') {
            if (($ms30 === '0' || $ms30 === 'I') && $checkRegisterD) {
                return array(
                    'insert_wait' => true,
                    'wait_mode' => 'legacy',
                    'wait_ms30' => 'C',
                    'main_action' => 'update_ms30',
                    'new_main_ms30' => 'C',
                );
            }
            return $noop;
        }

        if ($sType === 'D') {
            return array(
                'insert_wait' => $checkRegisterB,
                'wait_mode' => $checkRegisterB ? 'legacy' : 'none',
                'wait_ms30' => 'D',
                'main_action' => 'delete_legacy',
                'new_main_ms30' => '',
            );
        }

        if ($sType === 'F') {
            return array(
                'insert_wait' => $checkRegisterC,
                'wait_mode' => $checkRegisterC ? 'legacy' : 'none',
                'wait_ms30' => 'F',
                'main_action' => 'delete_legacy',
                'new_main_ms30' => '',
            );
        }

        if ($sType === 'X') {
            return array(
                'insert_wait' => false,
                'wait_mode' => 'none',
                'wait_ms30' => '',
                'main_action' => 'delete_legacy',
                'new_main_ms30' => '',
            );
        }

        if ($msType === '2') {
            return array(
                'insert_wait' => true,
                'wait_mode' => 'type2',
                'wait_ms30' => '2',
                'main_action' => 'delete_legacy',
                'new_main_ms30' => '',
            );
        }

        if ($msType === 'M') {
            if ($ms30 === 'y' || $ms30 === 'z') {
                return array(
                    'insert_wait' => true,
                    'wait_mode' => 'm_gov',
                    'wait_ms30' => $ms30,
                    'main_action' => 'delete_gov',
                    'new_main_ms30' => '',
                );
            }
            return array(
                'insert_wait' => true,
                'wait_mode' => 'm_general',
                'wait_ms30' => $ms30,
                'main_action' => 'delete_m',
                'new_main_ms30' => '',
            );
        }

        return $noop;
    }

    /**
     * 依 SmsMainFrame.TTSendTimer 規則組裝最終 UTF-8 subject 與 msg
     *
     * @param string $ms03
     * @param string $ms04_1
     * @param string $ms04_2
     * @param string $ms30
     * @param string $msType
     * @param string $msNote 已轉為 UTF-8 且保留開頭空白的 MS_NOTE
     * @param string $rkeyn04 SRKEYN KCDE_1='04' 的 KCNT（空字串時退回 $ms04_1）
     * @param string $emailSub ssms_govinfo_m.email_sub（MS_TYPE='M' 且 MS30 為 y/z 時使用）
     * @param string $uName SSYSAUTH1.USER_NAME（MS_TYPE='S' 時使用）
     * @param string|null $md13_1 SCMSDS.MD13_1（MS_TYPE='S' 且 MS30='C' 時使用）
     * @param string|null $md13_2 SCMSDS.MD13_2（MS_TYPE='S' 且 MS30='C' 時使用）
     * @return array{subject: string, msg: string}
     */
    public static function assemblePendingMessage(
        string $ms03,
        string $ms04_1,
        string $ms04_2,
        string $ms30,
        string $msType,
        string $msNote,
        string $rkeyn04,
        string $emailSub = '',
        string $uName = '',
        ?string $md13_1 = null,
        ?string $md13_2 = null
    ): array {
        $regName = $rkeyn04 !== '' ? $rkeyn04 : $ms04_1;
        $subject = $ms03 . '年' . $regName . '字第' . $ms04_2 . '號一案案件回覆';
        $msg = '台端於地政事務所申請' . $ms03 . '年' . $regName . '字第' . $ms04_2 . '號一案';

        if ($ms30 === 'I') {
            $msg .= $msType === 'R'
                ? '經查尚需要補正,請於接到本通知後儘速洽該所審查人員補正,謝謝您,再見'
                : '經查尚需要補正,請於接到本通知後速洽測量員' . $uName . ',謝謝您,再見';
        } elseif ($ms30 === 'D') {
            $msg .= $msType === 'R'
                ? '已駁回,請至登記櫃台領件,謝謝您,再見'
                : '已駁回,如有問題或須領件請洽測量員' . $uName . ',謝謝您,再見';
        } elseif ($ms30 === 'F') {
            $msg .= $msType === 'R'
                ? '已辦畢,需要領件者請至登記櫃台領件,謝謝您,再見'
                : '測量業務已辦畢,如另需辦理登記業務者已移案處理中,謝謝您,再見';
        } elseif ($ms30 === 'C') {
            if (
                $md13_1 !== null && trim($md13_1) !== '' && strlen($md13_1) >= 7
                && $md13_2 !== null && trim($md13_2) !== '' && strlen($md13_2) >= 4
            ) {
                $msg .= '複丈延期至'
                    . substr($md13_1, 0, 3) . '年'
                    . substr($md13_1, 3, 2) . '月'
                    . substr($md13_1, 5) . '日'
                    . substr($md13_2, 0, 2) . '時'
                    . substr($md13_2, 2, 2) . '分辦理,謝謝您,再見';
            } else {
                $msg .= '複丈延期至098年05月25日13時辦理,謝謝您,再見';
            }
        }

        if ($msType === '2') {
            $msg = $msNote;
        }

        if ($msType === 'M') {
            if ($ms30 === 'X') {
                $subject = '有關您申請地籍異動即時通服務1事';
                $msg = $msNote;
            } elseif ($ms30 === 'Y' || $ms30 === 'Z') {
                $subject = '有關您所持有不動產地籍異動1事';
                $msg = $msNote;
            } elseif ($ms30 === 'y' || $ms30 === 'z') {
                $subject = $emailSub;
                $msg = $msNote;
            }
        }

        if ($msType === 'O') {
            if ($ms30 === '7' || $ms30 === '8' || $ms30 === '9') {
                $parts = explode('#', $msNote, 2);
                $subject = $parts[0] ?? '';
                $msg = isset($parts[1]) ? str_replace('<br>', "\n", $parts[1]) : '';
            }
        }

        if ($msType === 'W') {
            $subject = '指定送達處所建檔完成通知';
            $msg = $msNote;
        }

        return array(
            'subject' => $subject,
            'msg' => $msg,
        );
    }

    /**
     * 將正規化後的 SSMSWAIT 資料列與組裝後的 subject/msg 包裝為 §4.3 端點 4 單筆項目
     *
     * @param array<string, string> $row
     * @param string $subject
     * @param string $msg
     * @return array<string, mixed>
     */
    public static function buildPendingItem(array $row, string $subject, string $msg): array {
        $ms03 = trim((string)($row['ms03'] ?? ''));
        $ms04_1 = trim((string)($row['ms04_1'] ?? ''));
        $ms04_2 = trim((string)($row['ms04_2'] ?? ''));
        $ms30 = trim((string)($row['ms30'] ?? ''));
        $msType = trim((string)($row['ms_type'] ?? ''));
        $ms14 = trim((string)($row['ms14'] ?? ''));
        $msMail = trim((string)($row['ms_mail'] ?? ''));

        $govExt = null;
        if ($ms30 === 'y' || $ms30 === 'z') {
            $govExt = array(
                'app_seq' => trim((string)($row['app_seq'] ?? '')),
                'app_id' => trim((string)($row['app_id'] ?? '')),
                'gov_id' => trim((string)($row['gov_id'] ?? '')),
                'cc00' => trim((string)($row['cc00'] ?? '')),
                'cc48' => trim((string)($row['cc48'] ?? '')),
                'cc49' => trim((string)($row['cc49'] ?? '')),
                'gov_oid' => trim((string)($row['gov_oid'] ?? '')),
            );
        }

        $channel = $msMail === '' ? 'sms' : 'email';
        $recipient = $msMail === '' ? $ms14 : $msMail;
        $retryCount = (int)trim((string)($row['ms32'] ?? '0'));

        return array(
            'identity' => array(
                'ms03' => $ms03,
                'ms04_1' => $ms04_1,
                'ms04_2' => $ms04_2,
                'ms30' => $ms30,
                'ms_type' => $msType,
                'ms14' => $ms14,
                'ms_mail' => $msMail,
                'gov_ext' => $govExt,
            ),
            'retry_count' => $retryCount,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'msg' => $msg,
        );
    }

    /**
     * 將多筆已正規化為 UTF-8 的 SSMSWAIT 資料列組裝為待發送項目清單
     *
     * @param array<int, array<string, string>> $normalizedRows
     * @param callable $lookupRkeyn04 function(string $ms04_1): string
     * @param callable $lookupEmailSub function(string $appSeq): string
     * @param callable|null $lookupSurveyMeta function(string $ms03, string $ms04_1, string $ms04_2): array{u_name: string, md13_1: ?string, md13_2: ?string}
     * @return array<int, array<string, mixed>>
     */
    public static function assemblePendingItems(
        array $normalizedRows,
        callable $lookupRkeyn04,
        callable $lookupEmailSub,
        ?callable $lookupSurveyMeta = null
    ): array {
        $items = array();
        foreach ($normalizedRows as $row) {
            $ms03 = trim($row['ms03'] ?? '');
            $ms04_1 = trim($row['ms04_1'] ?? '');
            $ms04_2 = trim($row['ms04_2'] ?? '');
            $ms30 = trim($row['ms30'] ?? '');
            $msType = trim($row['ms_type'] ?? '');
            $msNote = $row['ms_note'] ?? '';

            $rkeyn04 = (string)$lookupRkeyn04($ms04_1);
            $emailSub = '';
            if ($msType === 'M' && ($ms30 === 'y' || $ms30 === 'z')) {
                $emailSub = (string)$lookupEmailSub(trim($row['app_seq'] ?? ''));
            }

            $uName = '';
            $md13_1 = null;
            $md13_2 = null;
            if ($msType === 'S' && $lookupSurveyMeta !== null) {
                $survey = $lookupSurveyMeta($ms03, $ms04_1, $ms04_2);
                $uName = (string)($survey['u_name'] ?? '');
                $md13_1 = $survey['md13_1'] ?? null;
                $md13_2 = $survey['md13_2'] ?? null;
            }

            $assembled = self::assemblePendingMessage(
                $ms03,
                $ms04_1,
                $ms04_2,
                $ms30,
                $msType,
                $msNote,
                $rkeyn04,
                $emailSub,
                $uName,
                $md13_1,
                $md13_2
            );

            $items[] = self::buildPendingItem(
                $row,
                $assembled['subject'],
                $assembled['msg']
            );
        }
        return $items;
    }

    /**
     * 驗證並正規化 POST /queue/report 請求酬載
     *
     * @param array<string, mixed> $payload
     * @return array{identity: array<string, mixed>, success: bool, msg: string, max_retries: int, error_desc: ?string}
     */
    public static function validateReportRequest(array $payload): array {
        if (!isset($payload['identity']) || !is_array($payload['identity']) || !self::isAssocArray($payload['identity'])) {
            throw new \InvalidArgumentException('Missing or invalid identity object.');
        }

        $identity = self::normalizeReportIdentity($payload['identity']);

        if (!array_key_exists('success', $payload) || !is_bool($payload['success'])) {
            throw new \InvalidArgumentException('Invalid success: expected boolean.');
        }
        $success = $payload['success'];

        $msg = '';
        if (array_key_exists('msg', $payload) && $payload['msg'] !== null) {
            if (!is_string($payload['msg'])) {
                throw new \InvalidArgumentException('Invalid msg: expected string or null.');
            }
            $msg = $payload['msg'];
        }

        $maxRetries = 0;
        if (array_key_exists('max_retries', $payload) && $payload['max_retries'] !== null) {
            if (!is_int($payload['max_retries']) || $payload['max_retries'] < 0) {
                throw new \InvalidArgumentException('Invalid max_retries: expected non-negative integer.');
            }
            $maxRetries = $payload['max_retries'];
        } elseif ($success === false) {
            throw new \InvalidArgumentException('Invalid max_retries: expected non-negative integer.');
        }

        $errorDesc = null;
        if (array_key_exists('error_desc', $payload) && $payload['error_desc'] !== null) {
            if (!is_string($payload['error_desc'])) {
                throw new \InvalidArgumentException('Invalid error_desc: expected string or null.');
            }
            $errorDesc = $payload['error_desc'];
        }

        return array(
            'identity' => $identity,
            'success' => $success,
            'msg' => $msg,
            'max_retries' => $maxRetries,
            'error_desc' => $errorDesc,
        );
    }

    /**
     * 驗證並正規化 RecordIdentity 複合主鍵
     *
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    public static function normalizeReportIdentity(array $identity): array {
        $requiredKeys = array('ms03', 'ms04_1', 'ms04_2', 'ms30', 'ms_type');
        $normalized = array();
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $identity) || !is_string($identity[$key])) {
                throw new \InvalidArgumentException("Invalid identity.{$key}: expected non-empty string.");
            }
            $val = trim($identity[$key]);
            if ($val === '') {
                throw new \InvalidArgumentException("Invalid identity.{$key}: expected non-empty string.");
            }
            $normalized[$key] = $val;
        }

        $ms14 = '';
        if (array_key_exists('ms14', $identity) && $identity['ms14'] !== null) {
            if (!is_string($identity['ms14'])) {
                throw new \InvalidArgumentException('Invalid identity.ms14: expected string or null.');
            }
            $ms14 = trim($identity['ms14']);
        }

        $msMail = '';
        if (array_key_exists('ms_mail', $identity) && $identity['ms_mail'] !== null) {
            if (!is_string($identity['ms_mail'])) {
                throw new \InvalidArgumentException('Invalid identity.ms_mail: expected string or null.');
            }
            $msMail = trim($identity['ms_mail']);
        }

        if ($ms14 === '' && $msMail === '') {
            throw new \InvalidArgumentException('Either identity.ms14 or identity.ms_mail must be non-empty.');
        }

        $ms30 = $normalized['ms30'];
        $isGov = ($ms30 === 'y' || $ms30 === 'z');
        $govExt = null;

        if ($isGov) {
            if (!isset($identity['gov_ext']) || !is_array($identity['gov_ext']) || !self::isAssocArray($identity['gov_ext'])) {
                throw new \InvalidArgumentException('Missing or invalid identity.gov_ext for government notification.');
            }
            $rawGov = $identity['gov_ext'];
            $govExt = array();
            foreach (array('app_seq', 'app_id', 'cc00', 'cc48', 'cc49') as $govKey) {
                if (!array_key_exists($govKey, $rawGov) || !is_string($rawGov[$govKey])) {
                    throw new \InvalidArgumentException("Invalid identity.gov_ext.{$govKey}: expected non-empty string.");
                }
                $govVal = trim($rawGov[$govKey]);
                if ($govVal === '') {
                    throw new \InvalidArgumentException("Invalid identity.gov_ext.{$govKey}: expected non-empty string.");
                }
                $govExt[$govKey] = $govVal;
            }
            if (preg_match('/^\d+$/', $govExt['app_seq']) !== 1) {
                throw new \InvalidArgumentException('Invalid identity.gov_ext.app_seq: expected numeric string.');
            }
            foreach (array('gov_id', 'gov_oid') as $optGovKey) {
                $optVal = '';
                if (array_key_exists($optGovKey, $rawGov) && $rawGov[$optGovKey] !== null) {
                    if (!is_string($rawGov[$optGovKey])) {
                        throw new \InvalidArgumentException("Invalid identity.gov_ext.{$optGovKey}: expected string or null.");
                    }
                    $optVal = trim($rawGov[$optGovKey]);
                }
                $govExt[$optGovKey] = $optVal;
            }
        } elseif (array_key_exists('gov_ext', $identity) && $identity['gov_ext'] !== null && !is_array($identity['gov_ext'])) {
            throw new \InvalidArgumentException('Invalid identity.gov_ext.');
        }

        return array(
            'ms03' => $normalized['ms03'],
            'ms04_1' => $normalized['ms04_1'],
            'ms04_2' => $normalized['ms04_2'],
            'ms30' => $ms30,
            'ms_type' => $normalized['ms_type'],
            'ms14' => $ms14,
            'ms_mail' => $msMail,
            'gov_ext' => $govExt,
        );
    }

    /**
     * PHP 7.3 相容關聯陣列判斷（排除空陣列與純數字索引 list）
     *
     * @param array<mixed, mixed> $arr
     * @return bool
     */
    private static function isAssocArray(array $arr): bool {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
