<?php
require_once("./include/init.php");
require_once("./include/Prefetch.class.php");

try {
    echo "now is ".milliseconds()." ms\n";
    echo "ms date is ".timestampToDate(milliseconds())."\n";
    echo "now is ".time()." s\n";
    echo "sec date is ".timestampToDate(time())."\n";
} catch(Exception $e) {
    die($e->getMessage());
}
