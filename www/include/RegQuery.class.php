<?php
require_once("init.php");
require_once("System.class.php");
require_once("RegCaseData.class.php");
require_once("SQLiteRegForeignerPDF.class.php");

class RegQuery {

	function __construct() { }
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
}
