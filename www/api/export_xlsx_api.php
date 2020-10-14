<?php
// ini_set("display_errors", 0);
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(ROOT_DIR."/include/api/FileAPICommandFactory.class.php");
$cmd = FileAPICommandFactory::getCommand("file_xlsx");
$cmd->execute();
