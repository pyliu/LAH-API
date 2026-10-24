<?php
require_once(dirname(dirname(__FILE__))."/include/api/FileAPICommandFactory.class.php");
$cmd = FileAPICommandFactory::getCommand($_POST["type"]);
die($cmd->execute());
