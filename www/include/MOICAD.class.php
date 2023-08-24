<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

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
			select
				RGALL_00_GP_FOREIGNER.*,
				t.*,
				r.*,											-- 土地標示部
				a.kcnt as aa46_cht,				-- 土標部鄉鎮市區
				z.kcnt as aa11_cht,				-- 土地使用分區
				y.kcnt as bb15_1_cht,
				x.kcnt as bb06_cht,
				s.lnam as bb09_cht,
				w.kname as ba48_cht,			-- 土所部段名
				v.kname as rm10_cht,			-- 收件資料鄉鎮市區
				s.*,
				u.*,
				v.*
			from 
				(select
					a.GG00,
					a.GG48,
					a.GG49,
					a.GG01,
					a.GG02,
					'00+GP' as \"GG30_1\",
					a.gg30_2 || chr(10) || b.gg30_2 as \"GG30_2\"
				from (
					select *
					from MOICAD.RGALL
					where 1=1
						and gg00 = 'B'
						and gg30_1 in ('00')
						and (gg30_2 like '%' || :bv_key1 || '%' or gg30_2 like '%' || :bv_key3 || '%' or gg30_2 like '%' || :bv_key4 || '%')
				) a
				left join (
					select *
					from MOICAD.RGALL
					where 1=1
						and gg00 = 'B'
						and gg30_1 in ('GP')
						and gg30_2 like '%' || :bv_key2 || '%'
				) b on a.GG00 = b.GG00
					and a.GG48 = b.GG48
					and a.GG49 = b.GG49
					and a.GG01 = b.GG01
				) RGALL_00_GP_FOREIGNER
			left join MOICAD.RBLOW t on
				t.ba48 = RGALL_00_GP_FOREIGNER.gg48
				and t.ba49 = RGALL_00_GP_FOREIGNER.gg49
				and t.bb01 = RGALL_00_GP_FOREIGNER.gg01
			left join MOICAD.RALID r on
				r.aa48 = RGALL_00_GP_FOREIGNER.gg48
				and r.aa49 = RGALL_00_GP_FOREIGNER.gg49
				--and t.bb01 = RGALL_00_GP_FOREIGNER.gg01
			left join MOICAD.RLNID s on t.bb09 = s.lidn
			left join MOICAS.CRSMS u on u.rm01 = t.bb03 and u.rm02 = t.bb04_1 and u.rm03 = t.bb04_2
			left join MOIADM.RKEYN x ON x.kcde_1 = '06' and t.bb06 = x.kcde_2
			left join MOIADM.RKEYN y ON y.kcde_1 = '15' and t.bb15_1 = y.kcde_2
			left join MOIADM.RKEYN z ON z.kcde_1 = '11' and r.aa11 = z.kcde_2	-- 使用分區代碼
			left join MOIADM.RKEYN a ON a.kcde_1 = '46' and r.aa46 = a.kcde_2 -- 土標部鄉鎮市區
			left join MOIADM.RKEYN_ALL v ON v.kcde_1 = '46' and v.kcde_2 = 'H' and u.rm10 = v.kcde_3
			left join MOIADM.RKEYN_ALL w ON w.kcde_1 = '48' and w.kcde_2 = 'H' and t.ba48 = w.kcde_4
			where s.lcde in ('2', '8')
			order by t.bb16
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
}
