<?php
require_once("./include/init.php");
require_once("./include/Prefetch.class.php");

try {
    echo "now is ".milliseconds()."ms";
} catch(Exception $e) {
    die($e->getMessage());
}
