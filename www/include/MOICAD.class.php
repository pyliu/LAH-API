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
}
