<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Scheduler.class.php");

$scheduler = new Scheduler();

switch ($_POST["type"]) {
    case "reqular":
        file_put_contents($ticket, strtotime('+5 mins', time()));
        $scheduler->do();
        echoJSONResponse('正常(regular)排程已執行完成。', STATUS_CODE::SUCCESS_NORMAL);
        break;
    case "15m":
        $result = $scheduler->do15minsJobs();
        $code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        $message = $result ? '15分鐘排程已執行完成。' : '15分鐘排程執行失敗。';
        echoJSONResponse($message, $code);
        break;
    case "30m":
        $result = $scheduler->do30minsJobs();
        $code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        $message = $result ? '30分鐘排程已執行完成。' : '30分鐘排程執行失敗。';
        echoJSONResponse($message, $code);
        break;
    case "1h":
        $result = $scheduler->do1HourJobs();
        $code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        $message = $result ? '每小時排程已執行完成。' : '每小時排程執行失敗。';
        echoJSONResponse($message, $code);
        break;
    case "4h":
        $result = $scheduler->do4HoursJobs();
        $code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        $message = $result ? '每4小時排程已執行完成。' : '每4小時排程執行失敗。';
        echoJSONResponse($message, $code);
        break;
    case "8h":
        $result = $scheduler->do8HoursJobs();
        $code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        $message = $result ? '每8小時排程已執行完成。' : '每8小時排程執行失敗。';
        echoJSONResponse($message, $code);
        break;
    case "12h":
        $result = $scheduler->doHalfDayJobs();
        $code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        $message = $result ? '每12小時排程已執行完成。' : '每12小時排程執行失敗。';
        echoJSONResponse($message, $code);
        break;
    case "24h":
        $result = $scheduler->doOneDayJobs();
        $code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        $message = $result ? '每24小時排程已執行完成。' : '每24小時排程執行失敗。';
        echoJSONResponse($message, $code);
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}

