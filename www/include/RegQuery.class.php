<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("System.class.php");
require_once("RegCaseData.class.php");
require_once("SQLiteRegForeignerPDF.class.php");

class RegQuery {
	private $site = 'HA';
	private $site_code = 'A';
	private $site_number = 1;

	function __construct() {
		$this->site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
		if (!empty($this->site)) {
			$this->site_code = $this->site[1];
			$this->site_number = ord($this->site_code) - ord('A');
		}
	}

	function __destruct() {}

	public function getRegForeignerPDF($st, $ed, $keyword = '') {
		$sqlite_rfpdf = new SQLiteRegForeignerPDF();
		$rows = $sqlite_rfpdf->get($st, $ed, $keyword);
		return $rows;
	}
}
