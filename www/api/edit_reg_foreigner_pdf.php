<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegForeignerPDF.class.php");

Logger::getInstance()->info("收到編輯登記外國人PDF請求");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '未知的失敗';

$payload = array();
// primary key in DB
$payload['id'] = $id = $_POST['id'];
$payload['year'] = $year = $_POST['year'];
$payload['number'] = $number = str_pad($_POST['number'], 6, '0', STR_PAD_LEFT);;
$payload['fid'] = $fid = $_POST['fid'];
$payload['fname'] = $fname = $_POST['fname'];
$payload['note'] = $note = $_POST['note'];
$payload['modifytime'] = $modifytime = time();

$rfpdf = new SQLiteRegForeignerPDF();
$record = $rfpdf->getOne($id);

if ($record === false) {
    $status = STATUS_CODE::FAIL_NOT_FOUND;
    $message = "資料庫無法找到資料 ($id)";
} else {
    $result = $rfpdf->update($_POST);
    if ($result === true) {
        // 更新成功
        $status = STATUS_CODE::SUCCESS_NORMAL;
        $message = "資料庫資料已更新($id)";
        $parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.$year;
        $orig_file = $parent_dir.DIRECTORY_SEPARATOR.$record['number']."_".$record['fid']."_".$record['fname'].".pdf";
        $new_file = $parent_dir.DIRECTORY_SEPARATOR.$number."_".$fid."_".$fname.".pdf";
        // rename orig file
        $rename_result = @rename($orig_file, $new_file);
        if ($rename_result) {
            $orig_file = $new_file;
        } else {
            $log = "更名 ".ltrim($orig_file, $parent_dir.DIRECTORY_SEPARATOR)." 至 ".ltrim($new_file, $parent_dir.DIRECTORY_SEPARATOR)." 失敗";
            $message .= "-($log)";
            Logger::getInstance()->error("⚠ $log");
        }

        // handle upload new pdf file
        if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
            // remove orig pdf
            $unlink_result = @unlink($orig_file);
            if (!$unlink_result) {
                $message .= "-(刪除 ".ltrim($orig_file, $parent_dir.DIRECTORY_SEPARATOR)." 檔案失敗)";
                Logger::getInstance()->error("⚠ 刪除 $orig_file 檔案失敗!");
            }
            // move uploaded file
            $filename = $_FILES['file']['name'];
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (strtoupper($extension) === 'PDF') {
                $tmp_file = $_FILES['file']['tmp_name'];
                $to_file = $parent_dir.DIRECTORY_SEPARATOR.$number."_".$fid."_".$fname.".".$extension;
                $moved = move_uploaded_file($tmp_file, $to_file);
                $message .= $moved ? '-(PDF檔案置換成功)' : '-(PDF檔置換失敗)';
            }
        }
    } else {
        $status = STATUS_CODE::FAIL_DB_ERROR;
        $message = "更新資料庫失敗 ($id)";
    }
}

echo json_encode(array(
    'status' => $status,
    'message'  => $message,
    'payload' => $payload
));
