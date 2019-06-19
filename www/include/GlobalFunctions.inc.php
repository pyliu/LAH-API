<?php
require_once("Config.inc.php");

function GetDBUserMapping() {
    $tmp_path = sys_get_temp_dir();
    $file     = $tmp_path . "\\tyland_user.map";
    $time = filemtime($file);
    
    if ($refresh === true || $time === false || mktime() - $time > 86400) {
        $db = SYSTEM_CONFIG["ORA_DB_MAIN"];
        
        $conn = oci_connect(SYSTEM_CONFIG["ORA_DB_USER"], SYSTEM_CONFIG["ORA_DB_PASS"], $db, "US7ASCII");
        if (!$conn) {
            $e = oci_error();
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
        // Prepare the statement
        $stid = oci_parse($conn, "SELECT * FROM SSYSAUTH1");
        if (!$stid) {
            $e = oci_error($conn);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
        
        // Perform the logic of the query
        $r = oci_execute($stid);
        if (!$r) {
            $e = oci_error($stid);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
        
        $result = array();
        while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
            $result[$row["USER_ID"]] = iconv("big-5", "utf-8", $row["USER_NAME"]);
        }
        
        if ($stid) {
            oci_free_statement($stid);
        }
        if ($conn) {
            oci_close($conn);
        }
        
        // cache
        $content = serialize($result);
        file_put_contents($file, $content);
        
        return $result;
    }
    
    $content = @file_get_contents($file);
    return unserialize($content);
}
?>
