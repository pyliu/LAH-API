<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."init.php");
require_once(ROOT_DIR.DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR."LandDataDB.class.php");

// Function to check the string is ends 
// with given substring or not 
function reachEnd($string, $endString) { 
	$len = strlen($endString); 
	if ($len == 0) { 
		return true; 
	} 
	return (substr($string, -$len) === $endString); 
} 

if (isset($_FILES['file']['name'])) {
    $filename = $_FILES['file']['name'];
    $found_section_code = substr($filename, 0, 4);
    $found_section_name = '';
    $valid_extensions = array("txt", "TXT");
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $matched_count = 0;
    $processed_count = 0;
    if (in_array($extension, $valid_extensions)) {
        $path = $_FILES['file']['tmp_name'];
        if (is_file($path)) {
            $message = '上傳成功';
            // save txt to sub section code folder
            $saved_dir = FILE_PATH.DIRECTORY_SEPARATOR.$found_section_code;
            if (!is_dir($saved_dir)) {
                mkdir($saved_dir);
            }
            // remove old files first
            $exist_files = glob($saved_dir.DIRECTORY_SEPARATOR."*.txt");
            array_map('unlink', $exist_files);

            $db = new LandDataDB();
            $db->removeLandData($found_section_code);
            
            $tmp_file = new SplFileObject($path);
            $now_section_name = '';
            $now_land_number = '';
            $now_found = false;
            $now_content = '';
            while ($tmp_file->valid()) {
                $matches = array();
                $line = $tmp_file->current();
                $tmp_file->next();
                if ($now_found) {
                    $now_content .= $line;
                    if (reachEnd($now_content, "\n\n\n")) {
                        $now_land_number = str_replace('-', '', $now_land_number);
                        $saved_path = $saved_dir.DIRECTORY_SEPARATOR.$now_land_number.".txt";
                        $prev_content = @file_get_contents($saved_path);
                        $current_content = mb_convert_encoding($now_content, 'UTF-8', 'BIG5');
                        if ($prev_content === false) {
                            file_put_contents($saved_path, $current_content);
                            $processed_count++;
                        } else {
                            file_put_contents($saved_path, $prev_content.$current_content);
                        }
                        $db->addLandData($found_section_code, mb_convert_encoding($found_section_name, 'UTF-8', 'BIG5'), $now_land_number, ($prev_content ?? '').$current_content);
                        // reset previous data, startover again
                        $now_section_name = '';
                        $now_land_number = '';
                        $now_found = false;
                        $now_content = '';
                    }
                } else {
                    // find the section name and land number as the beginning point
                    $pattern = mb_convert_encoding("[^#]{3}\s(?'section'[^#]+?段)\s(?'number'\d{4}-\d{4})\s地號\n", 'BIG5', 'UTF-8');
                    if(preg_match("/$pattern/m", $line, $matches)) {
                        $found_section_name = $now_section_name = $matches['section'];
                        $now_land_number = $matches['number'];
                        // skip next two lines
                        $line = $tmp_file->current();   // "\n"
                        $tmp_file->next();
                        $line = $tmp_file->current();   // 頁次:000001
                        $tmp_file->next();

                        $now_found = true;
                        $matched_count++;
                    }
                }
            }
            $message .= "，$found_section_code 總共處理 $matched_count 筆，儲存 $processed_count 個檔案。(".FILE_PATH.")";
            $log->info($message);
        } else {
            $message = '上傳檔案失敗';
        }
    } else {
        $message = '只允許上傳 .txt, .TXT 檔案';
    }
} else {
    $message = '找不到檔案('.print_r($_FILES, true).')';
}

echo json_encode(array(
    'message'  => $message,
    'path'   => $path,
    'code' => $found_section_code,
    'name' => $found_section_name,
    'count' => $matched_count
));
