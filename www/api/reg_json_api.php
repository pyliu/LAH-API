<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."RegQuery.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegForeignerPDF.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegForeignerRestriction.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegAddressUndisclosed.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."RegCaseData.class.php");

$query = new RegQuery();
$cache = Cache::getInstance();
$system = System::getInstance();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
    case "foreigner_pdf_list":
        Logger::getInstance()->info("XHR [foreigner_pdf_list] get pdf list request.");
        $count = 0;
        $result = $query->getRegForeignerPDF($_POST['start_ts'], $_POST['end_ts'], $_POST['keyword']);
				// $result = $mock ? $cache->get('foreigner_pdf_list') : $query->getRegForeignerPDF($_POST['start_ts'], $_POST['end_ts'], $_POST['keyword']);
        // $cache->set('foreigner_pdf_list', $result);
				$count = count($result);
				$response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "取得 $count 筆外國人PDF資料" : "無法取得外國人PDF資料";
        Logger::getInstance()->info("XHR [foreigner_pdf_list] $message");
        echoJSONResponse($message, $response_code, array( "raw" => $result ));
        break;
    case "add_foreigner_pdf":
        Logger::getInstance()->info("XHR [add_foreigner_pdf] add foreigner pdf request.");
        $status = STATUS_CODE::DEFAULT_FAIL;
        $message = '未知的失敗';
        $filename = '';
        $tmp_file = '';
        $payload = array();
        if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
            $filename = $_FILES['file']['name'];
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (strtoupper($extension) === 'PDF') {
                $tmp_file = $_FILES['file']['tmp_name'];
        
                $payload['year'] = $year = $_POST['year'];
                $payload['number'] = $number = str_pad($_POST['number'], 6, '0', STR_PAD_LEFT);;
                $payload['fid'] = $fid = $_POST['fid'];
                $payload['fname'] = $fname = $_POST['fname'];
                $payload['note'] = $note = $_POST['note'];
        
                // make sure the parent dir has been created
                $parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.$year;
                if (!file_exists($parent_dir) || !is_dir($parent_dir)) {
                    Logger::getInstance()->info("建立 $parent_dir ...");
                    @mkdir($parent_dir, 0777, true);
                }
                
                $to_file = $parent_dir.DIRECTORY_SEPARATOR.$number."_".$fid."_".$fname.".".$extension;
                $moved = move_uploaded_file($tmp_file, $to_file);
                if ($moved) {
                    // cont. to add database record ...
                    $sqlite_pdf = new SQLiteRegForeignerPDF();
                    $row_id = $sqlite_pdf->add($_POST);
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
        Logger::getInstance()->info("XHR [add_foreigner_pdf] $message");
        echoJSONResponse($message, $status, array(
          'payload' => $payload
        ));
        break;
    
    case "edit_foreigner_pdf":
        Logger::getInstance()->info("XHR [edit_foreigner_pdf] edit foreigner pdf request.");
        
        $status = STATUS_CODE::DEFAULT_FAIL;
        $message = '未知的失敗';

        $payload = array();
        // primary key in DB
        $payload['id'] = $id = $_POST['id'];
        $payload['year'] = $year = $_POST['year'];
        $payload['old_year'] = $old_year = $_POST['old_year'];
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
                $old_parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.$old_year;
                
                $new_parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.$year;
                // in case new dir does not exist
                if (!is_dir($new_parent_dir)) {
                    // Create the directory with permissions 0777 (modify if needed)
                    if (mkdir($new_parent_dir, 0777, true)) {
                        Logger::getInstance()->info("$new_parent_dir directory created successfully.");
                    } else {
                        Logger::getInstance()->info("Failed to create $new_parent_dir directory.");
                    }
                }

                $orig_file = $old_parent_dir.DIRECTORY_SEPARATOR.$record['number']."_".$record['fid']."_".$record['fname'].".pdf";
                $new_file = $new_parent_dir.DIRECTORY_SEPARATOR.$number."_".$fid."_".$fname.".pdf";
                // rename orig file
                $rename_result = @rename($orig_file, $new_file);
                if ($rename_result) {
                    $orig_file = $new_file;
                } else {
                    $log = "更名 ".$orig_file." 至 ".$new_file." 失敗";
                    $message .= "-($log)";
                    Logger::getInstance()->error("⚠ $log");
                }

                // handle upload new pdf file
                if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
                    // remove orig pdf
                    $unlink_result = @unlink($orig_file);
                    if (!$unlink_result) {
                        $message .= "-(刪除 ".$orig_file." 檔案失敗)";
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
        Logger::getInstance()->info("XHR [edit_foreigner_pdf] $message");
        echoJSONResponse($message, $status, array(
          'payload' => $payload
        ));
        break;
    case "remove_foreigner_pdf":
        Logger::getInstance()->info("XHR [remove_foreigner_pdf] remove foreigner pdf request.");
        $id = $_POST['id'];
        $result = $query->removeRegForeignerPDF($id);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已刪除外國人PDF資料 ($id)" : "無法刪除外國人PDF資料 ($id)";
        Logger::getInstance()->info("XHR [remove_foreigner_pdf] $message");
        echoJSONResponse($message, $response_code);
        break;
    case "add_foreigner_data":
    case "edit_foreigner_data":
        Logger::getInstance()->info("XHR [edit_foreigner_data] edit foreigner data request.");
        $data = $_POST['data'];
        $pkey = $data['pkey'];
        $srfr = new SQLiteRegForeignerRestriction();
        $result = $srfr->add($data);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已更新外國人管制資料 ($pkey)" : "無法更新外國人管制資料 ($pkey)";
        Logger::getInstance()->info("XHR [edit_foreigner_data] $message");
        echoJSONResponse($message, $response_code);
        break;
    case "remove_foreigner_data":
        Logger::getInstance()->info("XHR [remove_foreigner_data] remove foreigner data request.");
        $pkey = $_POST['pkey'];
        $srfr = new SQLiteRegForeignerRestriction();
        $result = $srfr->delete($pkey);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已刪除外國人管制資料 ($pkey)" : "無法刪除外國人管制資料 ($pkey)";
        Logger::getInstance()->info("XHR [remove_foreigner_pdf] $message");
        echoJSONResponse($message, $response_code);
        break;
    case "address_undisclosed_list":
        Logger::getInstance()->info("XHR [address_undisclosed_list] get address undisclosed list request.");
        $result = $query->getRegAddressUndisclosed($_POST['start_ts'], $_POST['end_ts'], $_POST['keyword']);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已取得住址隱匿資料" : "無法取得住址隱匿資料";
        Logger::getInstance()->info("XHR [address_undisclosed_list] $message");
        echoJSONResponse($message, $response_code, array( "raw" => $result ));
        break;
    case "add_address_undisclosed":
        Logger::getInstance()->info("XHR [add_address_undisclosed] add address undisclosed request.");
        $data = $_POST['data'];
        $srau = new SQLiteRegAddressUndisclosed();
        $result = $srau->add($data);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已新增住址隱匿資料" : "無法新增住址隱匿資料";
        Logger::getInstance()->info("XHR [add_address_undisclosed] $message");
        echoJSONResponse($message, $response_code);
        break;
    case "edit_address_undisclosed":
        Logger::getInstance()->info("XHR [edit_address_undisclosed] edit address undisclosed request.");
        $status = STATUS_CODE::DEFAULT_FAIL;
        $message = '未知的失敗';
        $payload = array();
        $id = $_POST['id'];
        $payload['id'] = $id;
        $srau = new SQLiteRegAddressUndisclosed();
        $record = $srau->getOne($id);
        if ($record === false) {
            $status = STATUS_CODE::FAIL_NOT_FOUND;
            $message = "資料庫無法找到住址隱匿資料 ($id)";
        } else {
            $data = $_POST['data'];
            $data['id'] = $id;
            $result = $srau->update($data);
            if ($result === true) {
                $status = STATUS_CODE::SUCCESS_NORMAL;
                $message = "已更新住址隱匿資料 ($id)";
            } else {
                $status = STATUS_CODE::FAIL_DB_ERROR;
                $message = "更新住址隱匿資料失敗 ($id)";
            }
        }
        Logger::getInstance()->info("XHR [edit_address_undisclosed] $message");
        echoJSONResponse($message, $status, array( 'payload' => $payload ));
        break;
    case "remove_address_undisclosed":
        Logger::getInstance()->info("XHR [remove_address_undisclosed] remove address undisclosed request.");
        $id = $_POST['id'];
        $srau = new SQLiteRegAddressUndisclosed();
        $result = $srau->delete($id);
        $response_code = $result === false ? STATUS_CODE::FAIL_DB_ERROR : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已刪除住址隱匿資料 ($id)" : "無法刪除住址隱匿資料 ($id)";
        Logger::getInstance()->info("XHR [remove_address_undisclosed] $message");
        echoJSONResponse($message, $response_code);
        break;
    case "get_case_applicants":
        Logger::getInstance()->info("XHR [get_case_applicants] get case applicants request.");
        $caseno = isset($_POST['caseno']) ? trim($_POST['caseno']) : '';
        // 移除 - 分隔符號，轉為 13 碼純字串
        $caseno_raw = str_replace('-', '', $caseno);
        if (strlen($caseno_raw) !== 13) {
            $message = "案件號格式錯誤，應為 13 碼（去除 - 後）";
            Logger::getInstance()->warning("XHR [get_case_applicants] $message ($caseno)");
            echoJSONResponse($message, STATUS_CODE::FAIL_NOT_VALID_SERVER);
            break;
        }
        $case_data = new RegCaseData($caseno_raw);
        $baked = $case_data->getBakedData();
        $applicants = array();
        // 代理人
        if (!empty($baked['代理人姓名'])) {
            $applicants[] = array(
                'role'   => '代理人',
                'name'   => $baked['代理人姓名'],
                'id_no'  => $baked['代理人統編'] ?? ''
            );
        }
        // 權利人
        if (!empty($baked['權利人姓名'])) {
            $applicants[] = array(
                'role'   => '權利人',
                'name'   => $baked['權利人姓名'],
                'id_no'  => $baked['權利人統編'] ?? ''
            );
        }
        $response_code = empty($baked) ? STATUS_CODE::FAIL_NOT_FOUND : STATUS_CODE::SUCCESS_NORMAL;
        if (empty($applicants) && $response_code === STATUS_CODE::SUCCESS_NORMAL) {
            $response_code = STATUS_CODE::SUCCESS_WITH_NO_RECORD;
        }
        $message = $response_code > 0 ? "已取得案件 {$caseno} 相關人員" : "查無案件 {$caseno} 資料";
        Logger::getInstance()->info("XHR [get_case_applicants] $message");
        echoJSONResponse($message, $response_code, array('applicants' => $applicants));
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
