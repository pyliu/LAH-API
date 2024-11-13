<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteSurDestructionTracking.class.php");

$destructionTracking = new SQLiteSurDestructionTracking();

switch ($_POST["type"]) {
    case "destruction_tracking_number_list":
        Logger::getInstance()->info("XHR [destruction_tracking_number_list] get tracking number list request.");
        $rows = $destructionTracking->getAllNumbers();
        $count = count($rows);
        $response_code = $rows === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "取得 $count 筆建物滅失追蹤發文字號資料" : "無法取得建物滅失追蹤發文字號資料";
        
        $result = array();
        if ($rows !== false) {
            foreach ($rows as $row) {
                $result[] = $row['number'];
            }
        }

        Logger::getInstance()->info("XHR [destruction_tracking_number_list] $message");
        echoJSONResponse($message, $response_code, array( "raw" => $result ));
        break;
    case "destruction_tracking_number_exist":
        Logger::getInstance()->info("XHR [destruction_tracking_number_exist] get tracking number list request.");
        $existed = $destructionTracking->exists($_POST['number']);
        $response_code = $existed === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? $_POST['number'].'已建立' : $_POST['number'].'查無資料';
        Logger::getInstance()->info("XHR [destruction_tracking_number_exist] $message");
        echoJSONResponse($message, $response_code, array( "raw" => $existed ));
        break;
    case "destruction_tracking_list":
        Logger::getInstance()->info("XHR [destruction_tracking_list] get tracking list request.");
        $count = 0;
        $result = $destructionTracking->searchByApplyDate($_POST['tw_start'], $_POST['tw_end']);
		$count = count($result);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "取得 $count 筆建物滅失追蹤資料" : "無法取得建物滅失追蹤資料";
        Logger::getInstance()->info("XHR [destruction_tracking_list] $message");
        echoJSONResponse($message, $response_code, array( "raw" => $result ));
        break;
    case "add_destruction_tracking":
        Logger::getInstance()->info("XHR [add_destruction_tracking] add tracking data request.");
        $status = STATUS_CODE::DEFAULT_FAIL;
        $message = '未知的失敗';
        $filename = '';
        $tmp_file = '';
        $payload = $_POST;
        if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
            $filename = $_FILES['file']['name'];
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (strtoupper($extension) === 'PDF') {
                $tmp_file = $_FILES['file']['tmp_name'];
        
                $payload['filename'] = $_POST['apply_date'].'_'.$_POST['section_code'].'_'.$_POST['land_number'].'_'.$_POST['building_number'];
        
                // make sure the parent dir has been created
                $parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.'sur_destruction_tracking';
                if (!file_exists($parent_dir) || !is_dir($parent_dir)) {
                    Logger::getInstance()->info("建立 $parent_dir ...");
                    @mkdir($parent_dir, 0777, true);
                }
                
                $to_file = $parent_dir.DIRECTORY_SEPARATOR.$payload['filename'].".".$extension;
                $moved = move_uploaded_file($tmp_file, $to_file);
                if ($moved) {
                    // cont. to add database record ...
                    $row_id = $destructionTracking->add($_POST);
                    $status = $row_id !== false ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
                    $message = $status === STATUS_CODE::SUCCESS_NORMAL ? "已新增資料並儲存PDF完成" : "於資料庫新增資料失敗";
                    $payload['file'] = $to_file;
                    $payload['id'] = $row_id;
                } else {    
                    $message = "無法移動上傳檔案 $tmp_file → $to_file";
                    Logger::getInstance()->error(__FILE__.': '.$message);
                }
            } else {
                $message = "檔案不是PDF";
                Logger::getInstance()->error(__FILE__.': 檔案不是PDF。 '.print_r($_FILES, true));
            }
        }
        Logger::getInstance()->info("XHR [add_destruction_tracking] $message");
        echoJSONResponse($message, $status, array(
            'payload' => $payload
        ));
        break;
    
    case "edit_destruction_tracking":
        Logger::getInstance()->info("XHR [edit_destruction_tracking] edit tracking data request.");
        
        $status = STATUS_CODE::DEFAULT_FAIL;
        $message = '未知的失敗';

        $payload = array();
        // primary key in DB
        $id = $_POST['id'];

        $orig = $destructionTracking->getOne($id);

        if ($orig === false) {
            $status = STATUS_CODE::FAIL_NOT_FOUND;
            $message = "資料庫無法找到資料 ($id)";
        } else {
            $result = $destructionTracking->update($_POST);
            if ($result === true) {
                // 更新成功
                $status = STATUS_CODE::SUCCESS_NORMAL;
                $message = "資料庫資料已更新($id)";
                // handle upload new pdf file
                if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
                    $parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.'sur_destruction_tracking';
                    $current_pdf_file = $destructionTracking->getPDFFilename($id);
                    $target_file_path = $parent_dir.DIRECTORY_SEPARATOR.$current_pdf_file;
                    // remove old pdf
                    $unlink_result = @unlink($target_file_path);
                    if (!$unlink_result) {
                        $message .= "-(刪除 ".ltrim($target_file_path, $parent_dir.DIRECTORY_SEPARATOR)." 檔案失敗)";
                        Logger::getInstance()->error("⚠ 刪除 $target_file_path 檔案失敗!");
                    }
                    // move uploaded file
                    $filename = $_FILES['file']['name'];
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    if (strtoupper($extension) === 'PDF') {
                        $tmp_file = $_FILES['file']['tmp_name'];
                        $moved = move_uploaded_file($tmp_file, $target_file_path);
                        $message .= $moved ? '-(PDF檔案置換成功)' : '-(PDF檔置換失敗)';
                    }
                }
            } else {
                $status = STATUS_CODE::FAIL_DB_ERROR;
                $message = "更新資料庫失敗 ($id)";
            }
        }
        Logger::getInstance()->info("XHR [edit_destruction_tracking] $message");
        echoJSONResponse($message, $status);
        break;
    case "remove_destruction_tracking":
        Logger::getInstance()->info("XHR [remove_destruction_tracking] remove tracking data request.");
        $id = $_POST['id'];
        $result = $destructionTracking->removePDF($id);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已刪除建物滅失追蹤PDF資料 ($id)" : "無法刪除建物滅失追蹤PDF資料 ($id)";
        Logger::getInstance()->info("XHR [remove_destruction_tracking] $message");
        echoJSONResponse($message, $response_code);
        break;
    case "switch_done_destruction_tracking":
        Logger::getInstance()->info("XHR [switch_done_destruction_tracking] switch done tracking data request.");
        $id = $_POST['id'];
        $done = $_POST['done'];
        $result = $destructionTracking->setDone($id, $done);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已切換建物滅失追蹤資料辦畢屬性 ($id)" : "無法設定建物滅失追蹤資料辦畢屬性 ($id)";
        Logger::getInstance()->info("XHR [switch_done_destruction_tracking] $message");
        echoJSONResponse($message, $response_code);
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
