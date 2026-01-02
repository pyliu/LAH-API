<?php
require_once("./include/init.php");

try {
    $ad = new AdService();
    $users = $ad->getValidUsers();
    echo "AD valid users:\n";
    foreach ($users as $user) {
        echo "- " . print_r($user, true) . "\n\n";
    }
    // @$ad->saveInvalidUsers();
} catch (Exception $ex) {
    echo 'Caught exception: ', $e->getMessage(), "\n";
} finally {
    echo "\n\nThis is the finally block.\n\n";
}