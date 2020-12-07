<?php
require_once("init.php");

// $log->info(print_r($_FILES, true));

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
    $section_code = substr($filename, 0, 4);
    $valid_extensions = array("txt", "TXT");
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $matched_count = 0;
    if (in_array($extension, $valid_extensions)) {
        $path = $_FILES['file']['tmp_name'];
        if (is_file($path)) {
            $message = '上傳成功';
            // $txt_data = file_get_contents($path);
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
                        $log->info("$section_code $now_section_name $now_land_number: ".strlen($now_content));
                        // TODO: WRITE CONTENT TO DB

                        // next start paragraph found, reset previous data
                        $now_section_name = '';
                        $now_land_number = '';
                        $now_found = false;
                        $now_content = '';
                    }
                } else {
                    // find the section name and land number as the beginning point
                    $pattern = mb_convert_encoding("[^#]{3}\s(?'section'[^#]+?段)\s(?'number'\d{4}-\d{4})\s地號\n", 'BIG5', 'UTF-8');
                    if(preg_match("/$pattern/m", $line, $matches)) {
                        $now_section_name = $matches['section'];
                        $now_land_number = $matches['number'];
                        // skip next two lines
                        $tmp_file->next();
                        $tmp_file->next();

                        $now_found = true;
                        $matched_count++;
                    }
                }
            }
            $log->info("$section_code processed $matched_count data.");
            // process the data txt into sqlite db
            /*
            $txt_data = mb_convert_encoding(file_get_contents($path), 'UTF-8', 'BIG5');
            // $log->info($txt_data);
            if (!empty($txt_data)) {
                $matches = array();
                // $pattern = mb_convert_encoding("\s\n桃園市蘆竹地政事務所 土地地籍整理清冊\n列印日期:\s民國\d{3}年\d{1,2}月\d{1,2}日\n蘆竹區\s(?'section'[^#]+?段)\s(?'number'\d{4}-\d{4})\s地號\n\n頁次:\s\d{6}\n(?'content'[^#]+?)\n\n\n", 'BIG5', 'UTF-8');
                $pattern = "\s\n桃園市蘆竹地政事務所 土地地籍整理清冊\n列印日期:\s民國\d{3}年\d{1,2}月\d{1,2}日\n蘆竹區\s(?'section'[^#]+?段)\s(?'number'\d{4}-\d{4})\s地號\n\n頁次:\s\d{6}\n(?'content'[^#]+?)\n\n\n";
                preg_match_all(
                    "/$pattern/m",
                    $txt_data,
                    $matches
                );
                $matched_count = count($matches[0]);
                $log->info($section_code.' matched count: '.$matched_count);
                // $log->info(print_r($matches['section'], true));
                // $log->info(print_r($matches['number'], true));
                // $log->info('content count: '.count($matches['content']));
                if ($matched_count > 0) {
                    $section_name = $matches['section'][0];
                    for ($i = 0; $i < $matched_count; $i++) {
                        $log->info("$section_code $section_name ".$matches['number'][$i].": ".strlen($matches['content'][$i]));
                    }
                }
            }
            */
        } else {
            $message = '上傳檔案失敗';
        }
    } else {
        $message = '只允許上傳 .txt, .TXT 檔案';
    }
} else {
    $message = '找不到檔案('.print_r($_FILES, true).')';
}

$output = array(
    'message'  => $message,
    'path'   => $path,
    'code' => $section_code,
    'name' => $section_name,
    'count' => $matched_count
);

echo json_encode($output);
