<?php
require_once(dirname(dirname(__FILE__))."/include/FileAPICommandFactory.class.php");
$cmd = FileAPICommandFactory::getCommand($_POST["type"]);
echo $cmd->execute();
?>
