<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."init.php");
require_once(ROOT_DIR.DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."LandDataDB.class.php");

$keyword = $_POST['keyword'];
$db = new LandDataDB();

$householdData = $db->searchPeopleByHouseholdCode($keyword);
$pidData = $db->searchPeopleByPid($keyword);
$nameData = $db->searchPeopleByPname($keyword);
$ownedNumberData = $db->searchPeopleByOwnedNumber($keyword);

$merged = $householdData + $pidData + $nameData + $ownedNumberData;
$count = count($merged);

echo json_encode(array(
    'keyword' => $keyword,
    'results' => $merged,
    'count' => $count
));
