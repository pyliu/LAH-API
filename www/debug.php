<?php
require_once("./include/init.php");
require_once("./include/MonitorMail.class.php");
require_once("./include/SQLiteMonitorMail.class.php");
require_once("./include/Ping.class.php");
require 'vendor/autoload.php';

try {
    echo "now is ".milliseconds()." ms\n";
    echo "ms date is ".timestampToDate(milliseconds())."\n";
    echo "tw date is ".timestampToDate(milliseconds(), 'TW')."\n";
    echo "now is ".time()." s\n";
    echo "sec date is ".timestampToDate(time())."\n";
    echo "tw date is ".timestampToDate(time(), 'TW')."\n";

    echo "The current read timeout is " . imap_timeout(IMAP_READTIMEOUT) . "\n";
    echo "The current open timeout is " . imap_timeout(IMAP_OPENTIMEOUT) . "\n";
    echo "The current write timeout is " . imap_timeout(IMAP_WRITETIMEOUT) . "\n";
    echo "The current close timeout is " . imap_timeout(IMAP_CLOSETIMEOUT) . "\n";

    // Logger::getInstance()->info("XHR [ping] Ping ".$_POST["ip"]." request.");
    // for ($i = 1; $i < 255; $i++) {
    //     $ip = '192.168.13.'.$i;
    //     $ping = new Ping($ip, 1, 255);	// ip, timeout, ttl
    //     $latency = $ping->ping();
    //     echo $ip . ' ';
    //     echo empty($latency) ? 'timeout!' :  $latency . 'ms'."\n";
    // }

} catch(Exception $e) {
    die($e->getMessage());
} finally {
}
