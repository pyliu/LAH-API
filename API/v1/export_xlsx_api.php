<?php
// ini_set("display_errors", 0);
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/api/FileAPICommandFactory.class.php");
$cmd = FileAPICommandFactory::getCommand($_REQUEST['type'] ?? "file_xlsx");
$cmd->execute();
