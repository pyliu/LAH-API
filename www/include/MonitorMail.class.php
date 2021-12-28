<?php
require_once("init.php");
require_once("System.class.php");
require_once(ROOT_DIR.'/vendor/autoload.php');

use PhpImap\Mailbox;
use PhpImap\Exceptions\ConnectionException;

class MonitorMail {
    private $mailbox;

    function __construct() {
        $account = System::getInstance()->get("MONITOR_MAIL_ACCOUNT");
        $password = System::getInstance()->get("MONITOR_MAIL_PASSWORD");
        try {
            $this->mailbox = new Mailbox(
                '{mail.ha.cenweb.land.moi/novalidate-cert}INBOX', // IMAP server and mailbox folder
                $account, // Username for the before configured mailbox
                $password, // Password for the before configured username
                __DIR__, // Directory, where attachments will be saved (optional)
                'UTF-8', // Server encoding (optional)
                true, // Trim leading/ending whitespaces of IMAP path (optional)
                true // Attachment filename mode (optional; false = random filename; true = original filename)
            );
            // set some connection arguments (if appropriate)
            $this->mailbox->setConnectionArgs(
                CL_EXPUNGE // expunge deleted mails upon mailbox close
                // | OP_SECURE // don't do non-secure authentication
            );
        } catch (ConnectionException $ex) {
            Logger::getInstance()->error("IMAP connection failed: " . $ex);
        }
    }

    function __destruct() {
        unset($this->mailbox);
    }

    public function getCurrentServerTime() {
        // Call imap_check() - see http://php.net/manual/function.imap-check.php
        $info = $this->mailbox->imap('check');
        // Show current time for the mailbox
        return isset($info->Date) && $info->Date ? date('Y-m-d H:i:s', strtotime($info->Date)) : 'Unknown';
    }

    public function getLatestMail() {
        $mail = null;
        try {
            // Get all emails (messages)
            // PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
            $mailsIds = $this->mailbox->searchMailbox('ALL');
            $total = count($mailsIds);
            Logger::getInstance()->info(__METHOD__.": 找到 $total 封郵件。");
            // If $mailsIds is empty, no emails could be found
            if (empty($mailsIds)) {
                Logger::getInstance()->warning(__METHOD__.": 找不到郵件。");
            } else {

                $mail = $this->mailbox->getMail($mailsIds[$total - 1]);
            }
        } catch(Exception $ex) {
            Logger::getInstance()->error("IMAP connection failed: " . $ex);
        } finally {
            return $mail;
        }
    }

    public function getAllMails() {
        $mails = [];
        try {
            // Get all emails (messages)
            // PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
            $mailsIds = $this->mailbox->searchMailbox('ALL');
            Logger::getInstance()->info(__METHOD__.": 找到 ".count($mailsIds)." 封郵件。");
            // If $mailsIds is empty, no emails could be found
            if (empty($mailsIds)) {
                Logger::getInstance()->warning(__METHOD__.": 找不到郵件。");
            } else {
                // foreach ($mailsIds as $mailId) {
                //     $mails[] = $this->mailbox->getMail($mailId);
                //     break;
                // }
            }
        } catch(ConnectionException $ex) {
            Logger::getInstance()->error("IMAP connection failed: " . $ex);
        } finally {
            return $mails;
        }
    }
}
