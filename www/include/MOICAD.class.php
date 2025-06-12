<?php
require_once("init.php");

class MOICAD
{
	private function calcDeadlineByGG30_2(&$records) {
		$len = count($records);
		if ($len > 0) {
			for ($i = 0; $i < $len; $i++) {
				$gg30_2 = $records[$i]['GG30_2'];
				$begin = mb_strpos($gg30_2, '本筆土地應於');
				$end = mb_strpos($gg30_2, '日前移轉與本國人');
				$date = mb_substr($gg30_2, $begin + 6, $end - $begin - 5);
				$y_begin = mb_strpos($date, '年');
				$m_begin = mb_strpos($date, '月');
				$d_begin = mb_strpos($date, '日');
				// TW year
				$y = convertMBNumberString(mb_substr($date, 0, $y_begin));
				$m = str_pad(
					convertMBNumberString(mb_substr($date, $y_begin + 1, $m_begin - $y_begin - 1)),
					2,
					'0',
					STR_PAD_LEFT
				);
				$d = str_pad(
					convertMBNumberString(mb_substr($date, $m_begin + 1, $d_begin - $m_begin - 1)),
					2,
					'0',
					STR_PAD_LEFT
				);
				$records[$i]['deadline_tw'] = $y.str_pad($m, 2, '0').$d;
				$records[$i]['deadline_ts'] = strtotime(($y + 1911).'-'.$m.'-'.$d);
				$records[$i]['deadline'] = ($y + 1911).$m.$d;
				$records[$i]['deadline_raw'] = array(
					'ad_y' => $y + 1911,
					'y' => $y,
					'm' => $m,
					'd' => $d
				);
			}
		}
		return $records;
	}

	private $db_wrapper = null;
	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
	}

	function __destruct() {
		$this->db_wrapper = null;
	}
	/**
	 * Find foreigner inheritance restriction records
	 */
	public function getInheritanceRestrictionRecords()
	{
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			-- 合併 GG30_1 為 00、GP 的 GG30_2 字串
			SELECT
					RGALL_AGG.*,
					t.*,
					r.*,
					a.kcnt as aa46_cht,
					z.kcnt as aa11_cht,
					y.kcnt as bb15_1_cht,
					x.kcnt as bb06_cht,
					s.lnam as bb09_cht,
					w.kname as ba48_cht,
					v.kname as rm10_cht,
					s.*,
					u.*,
					v.*
			FROM
					(
							-- 步驟 4: 從包含 max_rn 的結果中篩選出 rn = max_rn 的記錄 (即葉節點)
							SELECT
									GG00,
									GG48,
									GG49,
									GG01,
									'00+GP' AS GG30_1,
									LTRIM(path, '　') as GG30_2 -- 移除開頭的分隔符
							FROM (
									-- 步驟 3: 計算每個分組的最大行號 max_rn
									SELECT
											GG00,
											GG48,
											GG49,
											GG01,
											rn,
											path,
											MAX(rn) OVER (PARTITION BY GG00, GG48, GG49, GG01) as max_rn -- 計算組內最大 rn
									FROM (
											-- 步驟 2: 執行階層查詢，獲取路徑和行號 rn
											SELECT
													GG00,
													GG48,
													GG49,
													GG01,
													rn, -- 需要將 rn 傳遞出來
													SYS_CONNECT_BY_PATH(TRIM(GG30_2), '　') as path -- 分隔符仍為 '　'
											FROM (
													-- 步驟 1: 篩選數據並產生組內行號 rn (與之前相同)
													SELECT
															GG00,
															GG48,
															GG49,
															GG01,
															GG02,
															GG30_2,
															ROW_NUMBER() OVER (PARTITION BY GG00, GG48, GG49, GG01 ORDER BY GG02) as rn
													FROM MOICAD.RGALL
													WHERE 1=1
														AND gg00 = 'B'
														AND gg30_1 IN ('00', 'GP')
														AND (gg30_2 LIKE '%' || :bv_key1 || '%' OR gg30_2 LIKE '%' || :bv_key3 || '%' OR gg30_2 LIKE '%' || :bv_key4 || '%')
											) RGALL_RN
											CONNECT BY PRIOR rn = rn - 1
													AND PRIOR GG00 = GG00
													AND PRIOR GG48 = GG48
													AND PRIOR GG49 = GG49
													AND PRIOR GG01 = GG01
											START WITH rn = 1
									) RGALL_CONNECT
							) RGALL_MAX_RN -- 包含 max_rn 的中間結果
							WHERE rn = max_rn -- 關鍵替代: 篩選 rn 等於組內最大 rn 的行
					) RGALL_AGG -- 最終聚合結果
			-- 後續的 LEFT JOIN 與原查詢相同
			LEFT JOIN MOICAD.RBLOW t ON
					t.ba48 = RGALL_AGG.gg48
					AND t.ba49 = RGALL_AGG.gg49
					AND t.bb01 = RGALL_AGG.gg01
			LEFT JOIN MOICAD.RALID r ON
					r.aa48 = RGALL_AGG.gg48
					AND r.aa49 = RGALL_AGG.gg49
			LEFT JOIN MOICAD.RLNID s ON t.bb09 = s.lidn
			LEFT JOIN MOICAS.CRSMS u ON u.rm01 = t.bb03 AND u.rm02 = t.bb04_1 AND u.rm03 = t.bb04_2
			LEFT JOIN MOIADM.RKEYN x ON x.kcde_1 = '06' AND t.bb06 = x.kcde_2
			LEFT JOIN MOIADM.RKEYN y ON y.kcde_1 = '15' AND t.bb15_1 = y.kcde_2
			LEFT JOIN MOIADM.RKEYN z ON z.kcde_1 = '11' AND r.aa11 = z.kcde_2
			LEFT JOIN MOIADM.RKEYN a ON a.kcde_1 = '46' AND r.aa46 = a.kcde_2
			LEFT JOIN MOIADM.RKEYN_ALL v ON v.kcde_1 = '46' AND v.kcde_2 = 'H' AND u.rm10 = v.kcde_3
			LEFT JOIN MOIADM.RKEYN_ALL w ON w.kcde_1 = '48' AND w.kcde_2 = 'H' AND t.ba48 = w.kcde_4
			WHERE
					s.lcde IN ('2', '8')
			ORDER BY
					t.bb16
		");
		// GG30_1: '00' => 搜尋其他登記事項 ' ... 移轉與本國人 .... ' 字樣
		$this->db_wrapper->getDB()->bind(":bv_key1", mb_convert_encoding('移轉與', "big5"));
		// GG30_1: 'GP' => 同時合併 ' ... 移請財政部國有財產屬公開標售。' 字樣
		$this->db_wrapper->getDB()->bind(":bv_key2", mb_convert_encoding('移請', "big5"));
		// GG30_1: '00' => 搜尋其他登記事項 ' ... 移轉於本國人 .... ' 字樣
		$this->db_wrapper->getDB()->bind(":bv_key3", mb_convert_encoding('移轉於', "big5"));
		// GG30_1: '00' => 同時合併 ' ... 移請財政部國有財產屬公開標售。' 字樣
		$this->db_wrapper->getDB()->bind(":bv_key4", mb_convert_encoding('移請', "big5"));
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find foreigner inheritance restriction TODO records
	 */
	public function getInheritanceRestrictionTODORecords()
	{
		$records = $this->getInheritanceRestrictionRecords();
		$filtered = [];
		if (!empty($records)) {
			foreach($records as $record) {
				if (strpos($record["GG30_2"], "移轉與") > 0 && strpos($record["GG30_2"], "移請") === false) {
					$filtered[] = $record;
				}
			}
		}
		return $filtered;
	}
	/**
	 * Find foreigner inheritance restriction records that retrive the deadline from GG30_2
	 */
	public function getInheritanceRestrictionTODORecordsAdvanced()
	{
		$raw = $this->getInheritanceRestrictionTODORecords();
		return $this->calcDeadlineByGG30_2($raw);
	}

	public function getInheritanceRestrictionRecordsAdvanced()
	{
		$raw = $this->getInheritanceRestrictionRecords();
		return $this->calcDeadlineByGG30_2($raw);
	}
	/**
	 * 登記原因代碼檔統計項目代碼資料 給 REGA 用
	 */
	public function getRCODA() {
		
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			select * from MOICAD.RCODA t
			left join MOIADM.RKEYN s on s.kcde_1 = '06' and s.kcde_2 = t.cod06
			order by t.item
		");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * 統計資料 BY 日期區間
	 */
	public function getREGA($st, $ed) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			select * from MOICAD.REGA t
			where ra40 between :bv_st and :bv_ed
			order by ITEM, ra40
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
}
