<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Ticket
 *
 * 长效会话跟踪器
 *
 * @author     liut
 * @version    $Id$
 * @created    22:17 2009-07-14
 */


/**
 * Ticket
 *
 */
class Ticket
{
	// const
	const COOKIE_TSID = '__tkid';
	const LIFTIME_TSID = 31536000;	// 3600 * 24 * 365 * 1

	// vars
	protected $_sid;

	// vars
	private $_new = false;
	private $_ts = 0;

	/**
	 * constructor
	 *
	 * @return void
	 */
	private function __construct()
	{
		$this->init();
	}

	protected function init()
	{
		$sid = static::lastSid();
		if(empty($sid) || strlen($sid) < 16) {
			$this->_new = true;
			$this->_sid = static::storedSid();
		} else {
			$this->_sid = $sid;
		}
		$this->_ts = static::makeTimed($this->_sid);
	}

	/**
	 * return current singleton static
	 *
	 * @return object
	 */
	public static function current()
	{
		static $_current;
		if($_current === null) $_current = new static();
		return $_current;
	}

	/**
	 * 从Cookie里取得sid或新建sid并设置cookie
	 *
	 * @param
	 * @return void
	 */
	public static function storedSid()
	{
		$sid = static::lastSid();
		if(empty($sid) || strlen($sid) < 16) {
			$sid = static::genSid();
			if(static::isHttp()) {
				setcookie(static::COOKIE_TSID, $sid, time() + static::LIFTIME_TSID, '/',Request::genCookieDomain());
			}
		}
		return $sid;
	}

	/**
	 * get LastSid
	 *
	 * @return string
	 */
	public static function lastSid()
	{
		return isset($_COOKIE[static::COOKIE_TSID]) ? $_COOKIE[static::COOKIE_TSID] : null;;
	}

	/**
	 * 生成 Tecket_id
	 *
	 * @return string
	 */
	protected static function genSid()
	{
		return substr(str_replace('.', '', uniqid('',true)), 0, 16);;
	}

	/**
	 * function description
	 *
	 * @param
	 * @return void
	 */
	public function __get($name)
	{
		if($name === 'id' || $name === 'sid') return $this->_sid;
		if($name === 'new' || $name === 'isNew') return $this->_new;
		if ($name === 'ts' ||  $name === 'timestamp') return $this->_ts;
		return null;
	}

	/**
	 * function description
	 *
	 * @param string $sid;
	 * @return void
	 */
	public static function makeTimed($sid)
	{
		$t = hexdec(substr($sid, 0,8));
		return $t;
	}

	public static function isHttp()
	{
		return isset($_SERVER['HTTP_HOST']);
	}

	/**
	 * 临时测试
	 *
	 * @return void
	 */
	public static function testGenSn()
	{
		$i = 0;
		do
		{
			$sid = static::genSid();
			echo $sid, "\t", time(), "\t", static::makeTimed($sid), PHP_EOL;
			$i ++;
		}
		while ($i < 100);

		return $i;
	}

	/**
	 * 根据访问者IP，猜猜是哪儿的地址
	 *
	 * '8.8.4.4' => array (
	 *   'continent_code' => 'NA',
	 *   'country_code' => 'US',
	 *   'country_code3' => 'USA',
	 *   'country_name' => 'United States',
	 *   'region' => 'CA',
	 *   'city' => 'Mountain View',
	 *   'postal_code' => '94043',
	 *   'latitude' => 37.419200897216797,
	 *   'longitude' => -122.05740356445312,
	 *   'dma_code' => 807,
	 *   'area_code' => 650,
	 * )
	 *
	 * @param mixed ip address
	 * @return array or FALSE
	 */
	public static function guessIp($ip)
	{
		if (empty($ip)) {
			return FALSE;
		}

		if ($ip == '127.0.0.1') {
			Log::info('geoip not support loopback ip', __METHOD__);
			return 'localhost';
		}

		if (extension_loaded('geoip')) {
			try {
				$country_code = @geoip_country_code_by_name($ip);
				if (!$country_code) {
					return FALSE;
				}
				$result = [
					'continent_code' => geoip_continent_code_by_name($ip),
					'country_code' => $country_code,
					'country_code3' => geoip_country_code3_by_name($ip),
					'country_name' => geoip_country_name_by_name($ip),
				];

				$record = @geoip_record_by_name($ip);
				if ($record) {
					Log::info($record, __METHOD__.' Guess hit: ' . $ip);
					$result = array_merge($result, $record);
				} else {
					Log::notice('Guess address: no record found: ' . $ip, __METHOD__);
				}

				// $org = @geoip_org_by_name($ip);
				// if ($org) {
				// 	$result['org'] = $org;
				// }

				return $result;
			} catch (Exception $e) {
				Log::warning($e, __METHOD__);
			}
		}

		return FALSE;
	}
}

