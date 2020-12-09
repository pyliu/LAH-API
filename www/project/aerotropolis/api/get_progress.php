<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."init.php");
$_SESSTION['people_total'] = empty($_SESSTION['people_total']) ? 0 : $_SESSTION['people_total'];
$_SESSTION['people_processed'] = empty($_SESSTION['people_processed']) ? 0 : $_SESSTION['people_processed'];
$_SESSTION['data_total'] = empty($_SESSTION['data_total']) ? 0 : $_SESSTION['data_total'];
$_SESSTION['data_processed'] = empty($_SESSTION['data_processed']) ? 0 : $_SESSTION['data_processed'];
echo json_encode(array(
    'total' => $_POST['type'] === 'people' ? $_SESSTION['people_total'] : $_SESSTION['data_total'],
    'processed' => $_POST['type'] === 'people' ? $_SESSTION['people_processed'] : $_SESSTION['data_processed']
));
