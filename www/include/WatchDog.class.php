<?php
require_once('include/init.php');
require_once('include/Query.class.php');
require_once('include/Message.class.php');

class WatchDog {
    
    private $officeSchedule = [
        'Sun' => [],
        'Mon' => ['08:00 AM' => '05:00 PM'],
        'Tue' => ['08:00 AM' => '05:00 PM'],
        'Wed' => ['08:00 AM' => '05:00 PM'],
        'Thu' => ['08:00 AM' => '05:00 PM'],
        'Fri' => ['08:00 AM' => '05:00 PM'],
        'Sat' => []
    ];

    private function isOfficeHours() {
        global $log;
        
        // current or user supplied UNIX timestamp
        $timestamp = time();

        // default status
        $status = false;

        // get current time object
        $currentTime = (new DateTime())->setTimestamp($timestamp);

        // loop through time ranges for current day
        foreach ($this->officeSchedule[date('D', $timestamp)] as $startTime => $endTime) {

            // create time objects from start/end times
            $startTime = DateTime::createFromFormat('h:i A', $startTime);
            $endTime   = DateTime::createFromFormat('h:i A', $endTime);

            // check if current time is within a range
            if (($startTime < $currentTime) && ($currentTime < $endTime)) {
                $status = true;
                break;
            }
        }

        $log->info("現在是".($status ? "上班" : "下班")."時間");
        // TODO
        return $status;
    }

    private function checkCrossSiteData() {
        $query = new Query();
        // check reg case missing RM99~RM101 data
        $log->info('開始跨所註記遺失檢查 ... ');
        $rows = $query->getProblematicCrossCases();
        if (!empty($rows)) {
            $log->warning('找到'.count($rows).'件跨所註記遺失！');
            $case_ids = [];
            foreach ($rows as $row) {
                $case_ids[] = $row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'];
                $log->warning($row['RM01'].'-'.$row['RM02'].'-'.$row['RM03']);
            }
            
            $host_ip = getLocalhostIP();
            $msg = new Message();
            $content = "系統目前找到下列跨所註記遺失案件:\r\n\r\n".implode("\r\n", $case_ids)."\r\n\r\n請前往 http://$host_ip/watch_dog.php 修正。";
            foreach (SYSTEM_CONFIG['ADM_IPS'] as $adm_ip) {
                if ($adm_ip == '::1') {
                    continue;
                }
                $sn = $msg->send('跨所案件註記遺失通知', $content, $adm_ip, 840);   // 840 => +14 mins
                $log->info("訊息已送出(${sn})給 ${adm_ip}");
            }
        }
        $log->info('跨所註記遺失檢查結束。');
    }

    private function findDelayRegCases() {
        global $log;
        $query = new Query();
        // check reg case missing RM99~RM101 data
        $log->info('開始查詢15天內逾期登記案件 ... ');

        $rows = $query->queryOverdueCasesIn15Days();
        if (!empty($rows)) {
            $log->info('15天內找到'.count($rows).'件逾期登記案件。');
            $users = GetDBUserMapping();
            $case_records = [];
            foreach ($rows as $row) {
                $this_msg = $row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'].' '.REG_REASON[$row['RM09']].' '.($users[$row['RM45']] ?? $row['RM45']);
                $case_records[$row['RM45']][] = $this_msg;
                $case_records["ALL"][] = $this_msg;
                $log->info($this_msg);
            }
            
            // send to the reviewer
            foreach ($case_records as $ID => $records) {
                $this->sendOverdueMessage($ID, $records);
            }
        }
        $log->info('查詢近15天逾期登記案件完成。');
    }

    private function sendOverdueMessage($to_id, $case_records) {
        global $log;
        $chief_id = "HB0541";
        $host_ip = getLocalhostIP();
        $msg = new Message();
        $content = "目前有 ".count($case_records)." 件逾期案件(近15天，僅顯示前4筆):\r\n\r\n".implode("\r\n", array_slice($case_records, 0, 4))."\r\n...\r\n\r\n請前往 http://${host_ip}/overdue_reg_cases.html?reviewerID=".($to_id == "ALL" ? "" : $to_id)." 查看詳細列表。";
        $sn = $msg->sysSend('逾期案件通知', $content, $chief_id, 14399);  // 14399 => +3 hours 59 mins 59 secs
        $log->info("訊息已送出(${sn})給 ${chief_id}");
    }

    function __construct() { }

    function __destruct() { }

    public function do() {
        if ($this->isOfficeHours()) {
            $this->checkCrossSiteData();
            $this->findDelayRegCases();
        }
        return true;
    }
    
}
?>
