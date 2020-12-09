<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."init.php");
require_once(ROOT_DIR.DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."LandDataDB.class.php");

$owned_number = $_POST['owned_number'];
if (!empty($owned_number) && strlen($owned_number) == 12) {
    $db = new LandDataDB();
    $result = $db->queryData(substr($owned_number, 0, 4), substr($owned_number, 4));
    $count = count($result);
}

echo json_encode(array(
    'result' => $result ?? [],
    'count' => $count ?? 0
));
