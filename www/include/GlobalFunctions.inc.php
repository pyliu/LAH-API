<?php
require_once("Config.inc.php");

function GetDBUserMapping($refresh = false) {
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

function getCodeSelectHTML($element_id, $other_attrs = "") {
    return '<select id="'.$element_id.'" name="'.$element_id.'" '.$other_attrs.'>
    <optgroup label="本所">
      <option>HB04 壢登</option>
      <option>HB05 壢永</option>
      <option>HB06 壢速</option>
    </optgroup>
    <optgroup label="本所收件(跨所)">
      <option>HAB1 壢桃登跨</option>
      <option>HCB1 壢溪登跨</option>
      <option>HDB1 壢楊登跨</option>
      <option>HEB1 壢蘆登跨</option>
      <option>HFB1 壢德登跨</option>
      <option>HGB1 壢平登跨</option>
      <option>HHB1 壢山登跨</option>
    </optgroup>
    <optgroup label="他所收件(跨所)">
      <option>HBA1 桃壢登跨</option>
      <option>HBC1 溪壢登跨</option>
      <option>HBD1 楊壢登跨</option>
      <option>HBE1 蘆壢登跨</option>
      <option>HBF1 德壢登跨</option>
      <option>HBG1 平壢登跨</option>
      <option>HBH1 山壢登跨</option>
    </optgroup>
  </select>';
}

function zipLogs() {
    global $log;
    // Enter the name of directory
    $pathdir = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs";
    $dir = opendir($pathdir); 
    $today = date("Y-m-d");
    while($file = readdir($dir)) {
        // skip today
        if (stristr($file, $today)) {
            $log->info("Skipping today's log for compression.【${file}】");
            continue;
        }
        $full_filename = $pathdir.DIRECTORY_SEPARATOR.$file;
        if(is_file($full_filename)) {
            $pinfo = pathinfo($full_filename);
            if ($pinfo["extension"] != "log") {
                continue;
            }
            $zipcreated = $pinfo["dirname"].DIRECTORY_SEPARATOR.$pinfo["filename"].".zip";
            $zip = new ZipArchive();
            if($zip->open($zipcreated, ZipArchive::CREATE ) === TRUE) {
                $log->info("New zip file created.【${zipcreated}】");
                $zip->addFile($full_filename, $file);
                $zip ->close();
            }
            $log->info("remove log file.【".$pinfo["basename"]."】");
            @unlink($full_filename);
        }
    }
}
?>
