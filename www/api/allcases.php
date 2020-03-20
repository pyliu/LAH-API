<?php
require_once("../include/init.php");
require_once("../include/Query.class.php");
require_once("../include/RegCaseData.class.php");
require_once(ROOT_DIR."/include/Cache.class.php");

$qday = $_REQUEST["date"];
$qday = preg_replace("/\D+/", "", $qday);
if (empty($qday) || !preg_match("/^[0-9]{7}$/i", $qday)) {
  $qday = $today; // 今天
}

$query = new Query();
$cache = new Cache();
if (SYSTEM_CONFIG["MOCK_MODE"]) $log->warning("現在處於模擬模式(mock mode)，allcases API僅會回應之前已被快取之最新的資料！");
$all = SYSTEM_CONFIG["MOCK_MODE"] === true ? $cache->get('allcases') : $query->queryAllCasesByDate($qday);
$cache->set('allcases', $all);

// Fetch the results of the query
$str = "<table id='case_results' class='table-hover text-center col-lg-12' border='1'>\n";
$str .= "<thead id='case_table_header'><tr class='header'>".
    "<th id='fixed_th1' data-toggle='tooltip' title='依「收件字號」排序'>收件字號</th>\n".
    "<th id='fixed_th2' data-toggle='tooltip' title='依「收件時間」排序'>收件時間</th>\n".
    "<th id='fixed_th3' data-toggle='tooltip' title='依「登記原因」排序'>登記原因</th>\n".
    "<th id='fixed_th4' data-toggle='tooltip' title='依「辦理情形」排序'>狀態</th>\n".
    "<th id='fixed_th5' data-toggle='tooltip' title='依「收件人員」排序'>收件人員</th>\n".
    "<th id='fixed_th6' data-toggle='tooltip' title='依「作業人員」排序'>作業人員</th>\n".
    "<th id='fixed_th7' data-toggle='tooltip' title='依「初審人員」排序'>初審人員</th>\n".
    "<th id='fixed_th8' data-toggle='tooltip' title='依「複審人員」排序'>複審人員</th>\n".
    "<th id='fixed_th9' data-toggle='tooltip' title='依「准登人員」排序'>准登人員</th>\n".
    "<th id='fixed_th10' data-toggle='tooltip' title='依「登錄人員」排序'>登錄人員</th>\n".
    "<th id='fixed_th11' data-toggle='tooltip' title='依「校對人員」排序'>校對人員</th>\n".
    "<th id='fixed_th12' data-toggle='tooltip' title='依「結案人員」排序'>結案人員</th>\n".
    "</tr></thead>\n";
$str .= "<tbody>\n";
$count = 0;
foreach ($all as $row) {
    $count++;
    $data = new RegCaseData($row);
    $str .= "<tr class='".$data->getStatusCss()."' style='font-size: .95rem;'>\n";
    $str .= "<td class='text-right px-3'><a class='case ajax ".($data->isDanger() ? "text-danger" : "")."' href='#'>".$data->getReceiveSerial()."</a></td>\n".
        "<td data-toggle='tooltip' title='限辦期限：".$data->getDueDate()."'>".$data->getReceiveTime()."</td>\n".
//"<td data-toggle='tooltip' data-placement='right' title='限辦期限：".$data->getDueDate()."'>".$data->getDueHrs()."</td>\n".
"<td data-toggle='tooltip' data-placement='right' title='登記原因'>".$row["RM09"]."：".$data->getCaseReason()."</td>\n".
        "<td data-toggle='tooltip' title='辦理情形'>".$data->getStatus()."</td>\n".
        "<td ".$data->getReceptionistTooltipAttr().">".$data->getReceptionist()."</td>\n".
        "<td ".$data->getCurrentOperatorTooltipAttr().">".$data->getCurrentOperator()."</td>\n".
        "<td ".$data->getFirstReviewerTooltipAttr().">".$data->getFirstReviewer()."</td>\n".
        "<td ".$data->getSecondReviewerTooltipAttr().">".$data->getSecondReviewer()."</td>\n".
        "<td ".$data->getPreRegisterTooltipAttr().">".$data->getPreRegister()."</td>\n".
        "<td ".$data->getRegisterTooltipAttr().">".$data->getRegister()."</td>\n".
        "<td ".$data->getCheckerTooltipAttr().">".$data->getChecker()."</td>\n".
        "<td ".$data->getCloserTooltipAttr().">".$data->getCloser()."</td>\n";
    $str .= "</tr>\n";
}
$str .= "</tbody>\n";
$str .= "</table>\n";

$str = "<span>日期: ".$qday." 共 <span class='text-primary' id='record_count'>".$count."</span> 筆資料</span>\n" . $str;

echo $str;
?>
