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
            if ($md13_1 !== null && strlen($md13_1) >= 7 && $md13_2 !== null && strlen($md13_2) >= 4) {
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
                $parts = explode('#', $msNote);
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
}
