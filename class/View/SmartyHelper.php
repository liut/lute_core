<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * View_SmartyHelper
 *
 * Smarty 模板引擎助手
 *
 * @package	Lib
 * @author	 liut
 * @copyright  2007 liut
 * @version	$Id$
 */


/**
 * View_SmartyHelper
 *
 */
class View_SmartyHelper
{
	/**
	 * 构造函数
	 *
	 * @param Smarty $tpl
	 */
	public function __construct($tpl) {
		$tpl->registerPlugin('function', '_t',             array(& $this, '_func_t'));

		$tpl->registerPlugin('modifier', 'parse_str',      array(& $this, '_mod_parse_str'));
		$tpl->registerPlugin('modifier', 'to_hashmap',     array(& $this, '_mod_to_hashmap'));
		$tpl->registerPlugin('modifier', 'col_values',     array(& $this, '_mod_col_values'));
		$tpl->registerPlugin('modifier', 'pretty_time',    array(& $this, '_mod_pretty_time'));
		$tpl->registerPlugin('modifier', 'to_bytes',       array(& $this, '_mod_to_bytes'));
        var_dump($tpl);exit;
	}

	/**
	 * 提供对  _T() 函数的支持
	 */
	public function _func_t($params)
	{
		return _T($params['key'], isset($params['lang']) ? $params['lang'] : null);
	}

	/**
	 * 将字符串分割为数组
	 */
	public function _mod_parse_str($string)
	{
		$arr = array();
		parse_str(str_replace('|', '&', $string), $arr);
		return $arr;
	}

	/**
	 * 将二维数组转换为 hashmap
	 */
	public function _mod_to_hashmap($data, $f_key, $f_value = '')
	{
		$arr = array();
		if (!is_array($data)) { return $arr; }
		if ($f_value != '') {
			foreach ($data as $row) {
				$arr[$row[$f_key]] = $row[$f_value];
			}
		} else {
			foreach ($data as $row) {
				$arr[$row[$f_key]] = $row;
			}
		}
		return $arr;
	}

	/**
	 * 获取二维数组中指定列的数据
	 */
	public function _mod_col_values($data, $f_value)
	{
		$arr = array();
		if (!is_array($data)) { return $arr; }
		foreach ($data as $row) {
			$arr[] = $row[$f_value];
		}
		return $arr;
	}
	
	public function _mod_pretty_time($switch_time) 
	{
		$now_time = time();
		$time_span = $now_time - $switch_time;
		if($time_span < 60)
			return '1分钟前';
		elseif($time_span < 3600)
			return floor($time_span/60).'分钟前';
		elseif($time_span < 86400)
			return floor($time_span/3600).'小时前';
		elseif($time_span < 2592000)
			return floor($time_span/86400).'天前';
		elseif($time_span < 31104000)
			return '约'.floor($time_span/2592000).'月前';
		else
			return date('Y-m-d', $switch_time);
		
	}
    
	public function _mod_to_bytes($val)
	{
		$val = trim($val);
		$last = strtolower($val[strlen($val)-1]);
		switch($last) {
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return $val;
	}
}
