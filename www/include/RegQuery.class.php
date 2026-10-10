<?php
require_once("init.php");
require_once("System.class.php");
require_once("RegCaseData.class.php");
require_once("SQLiteRegForeignerPDF.class.php");
require_once("SQLiteRegAddressUndisclosed.class.php");
require_once("SQLiteRegPropertyAlert.class.php");
require_once("MOISMS.class.php");

class RegQuery {

	function __construct() {}
	function __destruct() {}

	public function getRegForeignerPDF($st, $ed, $keyword = '') {
		$sqlite_rfpdf = new SQLiteRegForeignerPDF();
		$rows = $sqlite_rfpdf->search($st, $ed, $keyword);
		return $rows;
	}

	public function removeRegForeignerPDF($id) {
		$sqlite_rfpdf = new SQLiteRegForeignerPDF();
		$orig = $sqlite_rfpdf->getOne($id);
		$result = $sqlite_rfpdf->delete($id);
		if ($result) {
			Logger::getInstance()->info(__METHOD__.": ✅ foreigner record removed.");
			// continue to delete pdf file
			$parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.$orig['year'];
      $orig_file = $parent_dir.DIRECTORY_SEPARATOR.$orig['number']."_".$orig['fid']."_".$orig['fname'].".pdf";
      $unlink_result = @unlink($orig_file);
			if (!$unlink_result) {Logger::getInstance()->error("⚠ 刪除 $orig_file 檔案失敗!");
			}
			return true;
		} else {
			Logger::getInstance()->warning(__METHOD__.": ⚠️ foreigner record does not remove.");
		}
		return false;
	}

	public function getRegAddressUndisclosed($st, $ed, $keyword = '') {
		$sqlite_raud = new SQLiteRegAddressUndisclosed();
		$rows = $sqlite_raud->search($st, $ed, $keyword);
		return $rows;
	}

	public function getRegPropertyAlert($st, $ed, $keyword = '') {
		$sqlite_rpa = new SQLiteRegPropertyAlert();
		$rows = $sqlite_rpa->search($st, $ed, $keyword);
		return $rows;
	}

	/**
	 * 針對單一筆住址隱匿或地籍異動即時通收件案件，檢測收件日期（含當日）之後是否有簡訊發送紀錄並更新狀態
	 *
	 * @param string $biz_type 'undisclosed' | 'property_alert'
	 * @param int|string $id 該筆收件紀錄主鍵 ID
	 * @return array ['status' => int, 'message' => string, 'payload' => array]
	 */
	public function checkSingleRegSmsStatus($biz_type, $id) {
		if ($biz_type === 'undisclosed') {
			$db = new SQLiteRegAddressUndisclosed();
			$bizTitle = '住址隱匿';
		} elseif ($biz_type === 'property_alert') {
			$db = new SQLiteRegPropertyAlert();
			$bizTitle = '地籍異動即時通';
		} else {
			return array(
				'status' => STATUS_CODE::DEFAULT_FAIL,
				'message' => "不支援的業務類別 ({$biz_type})",
				'payload' => array()
			);
		}

		$record = $db->getOne($id);
		if ($record === false || empty($record)) {
			return array(
				'status' => STATUS_CODE::FAIL_NOT_FOUND,
				'message' => "資料庫無法找到{$bizTitle}資料 ({$id})",
				'payload' => array()
			);
		}

		$cleanCell = preg_replace('/[^0-9]/', '', $record['cellphone'] ?? '');
		if (empty($cleanCell)) {
			return array(
				'status' => STATUS_CODE::DEFAULT_FAIL,
				'message' => '該筆案件無填寫手機號碼，無法查詢簡訊紀錄',
				'payload' => array()
			);
		}

		$caseCreatetime = !empty($record['createtime']) ? (int)$record['createtime'] : time();
		$rocYearStart = (int)date('Y', $caseCreatetime) - 1911;
		$stDate = sprintf("%03d%s", $rocYearStart, date('md', $caseCreatetime));
		$intakeDateStr = sprintf("%03d/%s/%s", $rocYearStart, date('m', $caseCreatetime), date('d', $caseCreatetime));

		$moisms = new MOISMS();
		$smsLogs = $moisms->getSMSRecordsByCellAndStartDate($cleanCell, $stDate);
		if ($smsLogs === false) {
			return array(
				'status' => STATUS_CODE::FAIL_REMOTE_UNREACHABLE,
				'message' => '無法連線至資料庫查詢簡訊紀錄',
				'payload' => array()
			);
		}

		$latestSuccess = null;
		$latestFail = null;
		$totalCellCount = 0;
		$bizMatchedCount = 0;
		$successCount = 0;
		$failCount = 0;
		$smsRecords = array();

		foreach ($smsLogs as $sms) {
			$rawCell = preg_replace('/[^0-9]/', '', $sms['SMS_CELL'] ?? '');
			if ($rawCell !== $cleanCell) {
				continue;
			}

			$rawDateDigits = preg_replace('/[^0-9]/', '', $sms['SMS_DATE'] ?? '');
			if (empty($rawDateDigits)) {
				continue;
			}
			$rawDate = str_pad($rawDateDigits, 7, '0', STR_PAD_LEFT);
			// 僅比對收件日期（含當日）之後的簡訊紀錄
			if ($rawDate < $stDate) {
				continue;
			}

			$totalCellCount++;

			$type = $sms['SMS_TYPE'] ?? '';
			$content = $sms['SMS_CONTENT'] ?? '';
			$isBizMatch = false;

			if ($biz_type === 'property_alert') {
				$isBizMatch = (
					mb_strpos($type, '地籍異動') !== false ||
					mb_strpos($content, '異動即時通') !== false ||
					mb_strpos($content, '地籍異動') !== false
				);
			} elseif ($biz_type === 'undisclosed') {
				$isBizMatch = (
					mb_strpos($type, '住址隱匿') !== false ||
					mb_strpos($content, '隱匿') !== false
				);
			}

			$rawTimeDigits = preg_replace('/[^0-9]/', '', $sms['SMS_TIME'] ?? '');
			$rawTime = str_pad(substr($rawTimeDigits, 0, 6), 6, '0', STR_PAD_RIGHT);
			$sortKey = $rawDate . $rawTime;

			$fmtDate = substr($rawDate, 0, 3) . '/' . substr($rawDate, 3, 2) . '/' . substr($rawDate, 5, 2);
			$fmtTime = substr($rawTime, 0, 2) . ':' . substr($rawTime, 2, 2) . ':' . substr($rawTime, 4, 2);
			$timeStr = "{$fmtDate} {$fmtTime}";

			$smsResult = strtoupper(trim($sms['SMS_RESULT'] ?? ''));
			$isSuccess = ($smsResult === 'S' || $smsResult === 'OK' || strpos($smsResult, 'OK') !== false);

			$smsRecords[] = array(
				'sort_key' => $sortKey,
				'time_str' => $timeStr,
				'type' => $type ?: '一般簡訊',
				'result' => $smsResult ?: '-',
				'is_success' => $isSuccess,
				'is_biz_match' => $isBizMatch,
				'content' => $content
			);

			if (!$isBizMatch) {
				continue;
			}

			$bizMatchedCount++;

			if ($isSuccess) {
				$successCount++;
				if ($latestSuccess === null || $sortKey > $latestSuccess['sort_key']) {
					$latestSuccess = array(
						'sort_key' => $sortKey,
						'time_str' => $timeStr
					);
				}
			} else {
				$failCount++;
				if ($latestFail === null || $sortKey > $latestFail['sort_key']) {
					$latestFail = array(
						'sort_key' => $sortKey,
						'time_str' => $timeStr
					);
				}
			}
		}

		// 依時間由新到舊排序簡訊明細
		usort($smsRecords, function ($a, $b) {
			return strcmp($b['sort_key'], $a['sort_key']);
		});

		$applicant = $record['applicant'] ?? '';
		$basePayload = array(
			'id' => $id,
			'intake_date' => $intakeDateStr,
			'cellphone' => $cleanCell,
			'total_count' => $totalCellCount,
			'matched_count' => $bizMatchedCount,
			'success_count' => $successCount,
			'fail_count' => $failCount,
			'sms_records' => $smsRecords
		);

		if ($latestSuccess !== null) {
			$newStatus = 1;
			$db->updateSmsStatus($id, $newStatus);
			$modifytime = time();
			$message = "{$applicant} ({$cleanCell}) 比對成功！發送時間：{$latestSuccess['time_str']}，已更新為「發送成功」";
			Logger::getInstance()->info(__METHOD__.": [{$bizTitle}] ID: {$id} {$message}");
			return array(
				'status' => STATUS_CODE::SUCCESS_NORMAL,
				'message' => $message,
				'payload' => array_merge($basePayload, array(
					'sms_status' => $newStatus,
					'updated' => true,
					'modifytime' => $modifytime,
					'sms_time' => $latestSuccess['time_str']
				))
			);
		}

		if ($latestFail !== null) {
			$newStatus = 2;
			$db->updateSmsStatus($id, $newStatus);
			$modifytime = time();
			$message = "{$applicant} ({$cleanCell}) 僅查得發送失敗紀錄（時間：{$latestFail['time_str']}），已更新為「發送失敗」";
			Logger::getInstance()->info(__METHOD__.": [{$bizTitle}] ID: {$id} {$message}");
			return array(
				'status' => STATUS_CODE::SUCCESS_NORMAL,
				'message' => $message,
				'payload' => array_merge($basePayload, array(
					'sms_status' => $newStatus,
					'updated' => true,
					'modifytime' => $modifytime,
					'sms_time' => $latestFail['time_str']
				))
			);
		}

		$message = "查無 {$applicant} ({$cleanCell}) 於收件日 ({$intakeDateStr}) 後之{$bizTitle}簡訊紀錄";
		Logger::getInstance()->info(__METHOD__.": [{$bizTitle}] ID: {$id} {$message}");
		return array(
			'status' => STATUS_CODE::SUCCESS_WITH_NO_RECORD,
			'message' => $message,
			'payload' => array_merge($basePayload, array(
				'sms_status' => (int)($record['sms_status'] ?? 0),
				'updated' => false,
				'modifytime' => (int)($record['modifytime'] ?? 0),
				'sms_time' => ''
			))
		);
	}
}

