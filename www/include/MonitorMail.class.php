<?php
require_once("init.php");
require_once("System.class.php");
require_once(ROOT_DIR.'/vendor/autoload.php');

use PhpImap\Mailbox;
use PhpImap\Exceptions\ConnectionException;

class MonitorMail {
    private $host;
    private $method;
    private $mailbox;

    private function getConnectionString(): string {
        $v = System::getInstance()->get('MONITOR_MAIL_SSL');
        $mail_ssl = $v=== 'true' || $v === true;
        if ($this->method === 'pop3') {
            if ($mail_ssl) {
                return "{".$this->host.":995/pop3/ssl/novalidate-cert}";
            }
            return "{".$this->host.":110/pop3/notls}";
        } else {
            if ($mail_ssl) {
                return "{".$this->host.":993/imap/ssl/novalidate-cert}";
            }
            return "{".$this->host.":143/notls}";
        }
    }

    private function getFullMailboxPath($folder): string {
        return $this->getConnectionString().$folder;
        // if ($mail_ssl) {
        //     return "{".$this->host.":995/pop3/ssl/novalidate-cert}".$folder;
        // }
        // return "{".$this->host.":110/pop3/notls}".$folder;
    }

    private function selectFolder($folder): void {
        $fullpath = $this->getFullMailboxPath($folder);
        $this->mailbox->switchMailbox($fullpath);
    }

    private function getUnseenMailIds($folder = "INBOX", $days_before = 0): array {
        // PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
        $tag = "UNSEEN";
        if ($days_before > 0) {
            // $tag .= " SINCE ".date('d-M-Y G\:i', time() - $days_before);
            $tag .= " SINCE ".date('d-M-Y', time() - $days_before * 24 * 60 * 60);
        }
        Logger::getInstance()->info(__METHOD__.": $tag");
        $this->selectFolder($folder);
        return $this->mailbox->searchMailbox($tag);
    }

    private function getAllMailIds($folder = "INBOX", $days_before = 1): array {
        // PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
        $tag = "All";
        if ($days_before > 0) {
            // $tag .= " SINCE ".date('d-M-Y G\:i', time() - $days_before);
            $tag .= " SINCE ".date('d-M-Y', time() - $days_before * 24 * 60 * 60);
        }
        Logger::getInstance()->info(__METHOD__.": $tag");
        $this->selectFolder($folder);
        return $this->mailbox->searchMailbox($tag);
    }

    private function getSubjectMailIds($keyword, $folder = "INBOX", $days_before = 0): array {
        // PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
        $tag = 'SUBJECT "'.$keyword.'"';
        if ($days_before > 0) {
            $tag .= " SINCE ".date('d-M-Y', time() - $days_before * 24 * 60 * 60);
        }
        Logger::getInstance()->info(__METHOD__.": $tag");
        $this->selectFolder($folder);
        return $this->mailbox->searchMailbox($tag);
    }

    function __construct() {
        $this->host = System::getInstance()->get("MONITOR_MAIL_HOST");
        $this->method = System::getInstance()->get("MONITOR_MAIL_METHOD") || 'imap';
        $account = System::getInstance()->get("MONITOR_MAIL_ACCOUNT");
        $password = System::getInstance()->get("MONITOR_MAIL_PASSWORD");
        try {
            $conn = $this->getFullMailboxPath("INBOX");
            // $conn = $this->getConnectionString();

            Logger::getInstance()->info("連線 $conn");

            $this->mailbox = new Mailbox(
                $conn, // IMAP server and mailbox folder
                $account, // Username for the before configured mailbox
                $password, // Password for the before configured username
                LOG_DIR ?? sys_get_temp_dir(), // Directory, where attachments will be saved (optional)
                'UTF-8', // Server encoding (optional)
                true, // Trim leading/ending whitespaces of IMAP path (optional)
                true // Attachment filename mode (optional; false = random filename; true = original filename)
            );
            // set some connection arguments (if appropriate)
            $this->mailbox->setConnectionArgs(
                CL_EXPUNGE // expunge deleted mails upon mailbox close
                // | OP_SECURE // don't do non-secure authentication
            );
            // If you don't need to grab attachments you can significantly increase performance of your application
            $this->mailbox->setAttachmentsIgnore(true);
        } catch (ConnectionException $ex) {
            Logger::getInstance()->error("IMAP 連線 $conn 失敗: " . $ex);
        }
    }

    function __destruct() {
        $this->mailbox->disconnect();
        unset($this->mailbox);
    }

    public function getCurrentServerTime(): string {
        // Call imap_check() - see http://php.net/manual/function.imap-check.php
        // $info = $this->mailbox->imap_check('check');
        // Show current time for the mailbox
        // return isset($info->Date) && $info->Date ? date('Y-m-d H:i:s', strtotime($info->Date)) : 'Unknown';
        return 'Unknown';
    }

    public function getMailboxes(): array {
        return $this->mailbox->getMailboxes('*');
    }

    public function getLatestMail($folder = "INBOX"): array {
        $mail = null;
        try {
            $mailsIds = $this->getAllMailIds();
            $total = count($mailsIds);
            Logger::getInstance()->info(__METHOD__.": 找到 $total 封郵件。");
            // If $mailsIds is empty, no emails could be found
            if (empty($mailsIds)) {
                Logger::getInstance()->warning(__METHOD__.": 找不到郵件。");
            } else {
                $mail = $this->mailbox->getMail(
                    $mailsIds[$total - 1],
                    false   // Do NOT mark emails as seen (optional)
                );
            }
        } catch(Exception $ex) {
            Logger::getInstance()->error("IMAP 取得 ${folder} 最新郵件失敗: " . $ex);
        } finally {
            return $this->extract([$mail]);
        }
    }

    public function getAllMails($folder = "INBOX", $days_before = 1): array {
        $mails = [];
        try {
            $mailsIds = $this->getAllMailIds($folder, $days_before);
            Logger::getInstance()->info(__METHOD__.": 找到 ".count($mailsIds)." 封郵件。");
            // If $mailsIds is empty, no emails could be found
            if (empty($mailsIds)) {
                Logger::getInstance()->warning(__METHOD__.": 找不到郵件。");
            } else {
                foreach ($mailsIds as $mailId) {
                    $mails[] = $this->mailbox->getMail(
                        $mailId,
                        false   // Do NOT mark emails as seen (optional)
                    );
                }
            }
        } catch(Exception $ex) {
            Logger::getInstance()->error("IMAP 取得 $folder 郵件失敗: " . $ex);
        } finally {
            return $this->extract($mails);
        }
    }

    public function getAllMailsCount($folder = "INBOX", $days_before = 1): int {
        return count($this->getAllMailIds($folder, $days_before));
    }
    
    public function getAllUnseenMails($folder = "INBOX", $days_before = 1): array {
        $mails = [];
        try {
            $mailsIds = $this->getUnseenMailIds($folder, $days_before);
            Logger::getInstance()->info(__METHOD__.": 找到 ".count($mailsIds)." 封郵件。");
            // If $mailsIds is empty, no emails could be found
            if (empty($mailsIds)) {
                Logger::getInstance()->warning(__METHOD__.": 找不到郵件。");
            } else {
                foreach ($mailsIds as $mailId) {
                    $mails[] = $this->mailbox->getMail(
                        $mailId,
                        false   // Do NOT mark emails as seen (optional)
                    );
                }
            }
        } catch(Exception $ex) {
            Logger::getInstance()->error("IMAP 取得 ${folder} 郵件失敗: " . $ex);
        } finally {
            return $this->extract($mails);
        }
    }
    
    public function getAllUnseenMailsCount($folder = "INBOX", $days_before = 1): int {
        return count($this->getUnseenMailIds($folder, $days_before));
    }

    public function getMailsBySubject($keyword, $folder = "INBOX", $days_before = 1): array {
        $mails = [];
        try {
            $mailsIds = $this->getSubjectMailIds($keyword, $folder, $days_before);
            Logger::getInstance()->info(__METHOD__.": 找到 ".count($mailsIds)." 封郵件。");
            // If $mailsIds is empty, no emails could be found
            if (empty($mailsIds)) {
                Logger::getInstance()->warning(__METHOD__.": 找不到郵件。");
            } else {
                foreach ($mailsIds as $mailId) {
                    $mails[] = $this->mailbox->getMail(
                        $mailId,
                        false   // Do NOT mark emails as seen (optional)
                    );
                }
            }
        } catch(Exception $ex) {
            Logger::getInstance()->error("IMAP 取得 ${folder} 郵件失敗: " . $ex);
        } finally {
            return $this->extract($mails);
        }
    }
    
    public function extract(&$mail_objs): array {
        $mails = array();
        if (!empty($mail_objs)) {
            foreach($mail_objs as $obj) {
                $mails[] = array(
                    "id" => intval($obj->id),
                    "from" => $obj->fromName ?? $obj->fromAddress,
                    "to" => $obj->toString,
                    "subject" => $obj->subject,
                    "message" => $obj->textPlain,
                    "timestamp" => strtotime($obj->date),  // timestamp
                    "mailbox" => $obj->mailboxFolder
                );
            }
        }
        return $mails;
    }
}
