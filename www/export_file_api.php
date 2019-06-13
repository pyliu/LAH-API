<?php
require_once("include/FileAPICommandFactory.class.php");
$cmd = FileAPICommandFactory::getCommand($_POST["type"]);
echo $cmd->execute();
?>
