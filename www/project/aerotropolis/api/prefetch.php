<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."init.php");
require_once(ROOT_DIR.DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."LandDataDB.class.php");

$keyword = $_POST['keyword'];
$db = new LandDataDB();

$householdData = $db->preftechPeopleByColumn('household', $keyword);
$pidData = $db->preftechPeopleByColumn('pid', $keyword);
$nameData = $db->preftechPeopleByColumn('pname', $keyword);
$ownedNumberData = $db->preftechPeopleByColumn('owned_number', $keyword);

$merged = $householdData + $pidData + $nameData + $ownedNumberData;

$set = array();
$keyword = str_replace('*', '\*', $keyword);
foreach($merged as $item) {
    // if(!preg_match("/^[A-Z]{1}[12ABCD]{1}[[:digit:]]{8}$/i", $id))
    if (preg_match("/$keyword/i", $item['household']) && !in_array($item['household'], $set)) {
        $set[] = $item['household'];
    }
    if (preg_match("/$keyword/i", $item['pid']) && !in_array($item['pid'], $set)) {
        $set[] = $item['pid'];
    }
    if (preg_match("/$keyword/i", $item['pname']) && !in_array($item['pname'], $set)) {
        $set[] = $item['pname'];
    }
    if (preg_match("/$keyword/i", $item['owned_number']) && !in_array($item['owned_number'], $set)) {
        $set[] = $item['owned_number'];
    }
    if (count($set) > 9) {
        break;
    }
}

$count = count($set);

echo json_encode(array(
    'keyword' => $keyword,
    'items' => $set,
    'count' => $count
));
