<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class MOICAD
{
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
				t.*,
				y.kcnt as bb15_1_cht,
				x.kcnt as bb06_cht,
				s.lnam as bb09_cht,
				w.kname as ba48_cht,
				v.kname as rm10_cht,
				s.*,
				r.*,
				u.*,
				v.*
			from MOICAD.RBLOW t
			left join MOICAD.RLNID s on t.bb09 = s.lidn
			left join MOICAD.RGALL r on t.ba48 = r.gg48 and t.ba49 = r.gg49 and r.gg00 = 'B'
			left join MOICAS.CRSMS u on u.rm01 = t.bb03 and u.rm02 = t.bb04_1 and u.rm03 = t.bb04_2
			left join MOIADM.RKEYN x ON x.kcde_1 = '06' and t.bb06 = x.kcde_2
			left join MOIADM.RKEYN y ON y.kcde_1 = '15' and t.bb15_1 = y.kcde_2
			left join MOIADM.RKEYN_ALL v ON v.kcde_1 = '46' and v.kcde_2 = 'H' and u.rm10 = v.kcde_3
			left join MOIADM.RKEYN_ALL w ON w.kcde_1 = '48' and w.kcde_2 = 'H' and t.ba48 = w.kcde_4
			where s.lcde in ('2', '8')
			--and gg30_1 = '00'
			and r.gg30_2 like '%' || :bv_key1 || '%'
			and r.gg30_2 not like '%' || :bv_key2 || '%'
			order by t.bb06
		");
		// 搜尋其他登記事項 ' ... 移轉與本國人 .... ' 字樣
		$this->db_wrapper->getDB()->bind(":bv_key1", mb_convert_encoding('移轉與', "big5"));
		// 同時排除已 ' ... 移請財政部國有財產屬公開標售。' 字樣
		$this->db_wrapper->getDB()->bind(":bv_key2", mb_convert_encoding('移請', "big5"));
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find foreigner inheritance restriction records that retrive the deadline from GG30_2
	 */
	public function getInheritanceRestrictionRecordsAdvanced()
	{
		$raw = $this->getInheritanceRestrictionRecords();
		$len = count($raw);
		if ($len > 0) {
			for ($i = 0; $i < $len; $i++) {
				$gg30_2 = $raw[$i]['GG30_2'];
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
				$raw[$i]['deadline_tw'] = $y.str_pad($m, 2, '0').$d;
				$raw[$i]['deadline_ts'] = strtotime(($y + 1911).'-'.$m.'-'.$d);
				$raw[$i]['deadline'] = ($y + 1911).$m.$d;
				$raw[$i]['deadline_raw'] = array(
					'ad_y' => $y + 1911,
					'y' => $y,
					'm' => $m,
					'd' => $d
				);
			}
		}
		return $raw;
	}
}
