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
	const COOKIE_REFS = '_refs';
	const LIFTIME_TSID = 31536000;	// 3600 * 24 * 365 * 1
	
	// vars
	protected $_sid;
	protected $_ref;
	
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
		$sid = self::lastSid();
		if(empty($sid) || strlen($sid) < 16) {
			$this->_new = true;
			$this->_sid = self::storedSid();
		} else {
			$this->_sid = $sid;
		}
		$this->_ts = self::makeTimed($this->_sid);
	}

	/**
	 * return current singleton self
	 * 
	 * @return object
	 */
	public static function current()
	{
		static $_current;
		if($_current === null) $_current = new self();
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
		$sid = self::lastSid();
		if(empty($sid) || strlen($sid) < 16) {
			$sid = self::genSid();
			if(self::isHttp()) {
				setcookie(self::COOKIE_TSID, $sid, time() + self::LIFTIME_TSID, '/',Request::genCookieDomain());
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
		return isset($_COOKIE[self::COOKIE_TSID]) ? $_COOKIE[self::COOKIE_TSID] : null;;
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
	
	/**
	 * 返回已经记录的 refs
	 * 
	 * @return array
	 */
	public static function referers()
	{
		if (isset($_COOKIE[self::COOKIE_REFS])) {
			$refs = explode('~~', $_COOKIE[self::COOKIE_REFS]);
			if (is_array($refs) && count($refs) > 3) {
				return $refs;
			}
		}

		return array('','','','');
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
			$sid = self::genSid();
			echo $sid, "\t", time(), "\t", self::makeTimed($sid), PHP_EOL;
			$i ++;
		}
		while ($i < 100);
		
		return $i;
	}

	/**
	 * 根据访问者IP，猜猜是哪儿的地址
	 * @param mixed ip address or Request instance
	 * @return array or FALSE
	 */
	public static function guessIp($request = NULL)
	{
		if (empty($request)) {
			$request = Request::current();
		}
		elseif (is_string($request)) {
			$ip = $request;
		}

		if ($request instanceof Request) {
			$ip = $request->CLIENT_IP;
		}

		if (!isset($ip)) {
			return FALSE;
		}

		if ($ip == '127.0.0.1') {
			Log::info('geoip not support loopback ip', __METHOD__);
			return FALSE;
		}

		if (function_exists('geoip_record_by_name')) {
			try {
				$record = geoip_record_by_name($ip);
				if ($record) {
					$row = [
						'country' => $record['country_code'],
						'province' => $record['region'],
						'city' => $record['city'],
						'postcode' => $record['postal_code']
					];
					Log::info('Guess hit: ' . $ip . ': ' . print_r($record, TRUE), __METHOD__);
					return $row;
				}
				Log::notice('Guess address: no record found: ' . $ip, __METHOD__);
			} catch (Exception $e) {
				Log::warning($e, __METHOD__);
			}
		}
		return FALSE;
	}
}

