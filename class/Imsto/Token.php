<?PHP

/**
 * Imsto Token
 *
 * @package core
 * @author liut
 **/
class Imsto_Token
{

	const VER_LENGTH = 1;
	const APP_LENGTH = 1;
	const VCATE_LENGTH = 1;
	const HASH_LENGTH = 20;
	const STAMP_LENGTH = 8;

	const LIFE_TIME = 1500;

	/**
	 * 解析 Token 中的内容
	 *
	 *  di_ver   = 0
	 *  di_app   = di_ver + 1
	 *  di_vc    = di_app + 1
	 *  di_hash  = di_vc + 1
	 *  di_stamp = di_hash + 20
	 *  di_value = di_stamp + 8
	 *
	 * @param string $token
	 * @return mixed
	 */
	public static function parse($token)
	{
		$pos = self::VER_LENGTH + self::APP_LENGTH + self::VCATE_LENGTH + self::HASH_LENGTH + self::STAMP_LENGTH;
		if (strlen($token) < $pos + 1) {
			return FALSE;
		}

		$s = base64_decode(strtr($token, '-_', '+/'));

		$format = sprintf('H%dver/H%dapp/H%dcate/H%dhash/H%dstamp/H*value'
			, self::VER_LENGTH * 2
			, self::APP_LENGTH * 2
			, self::VCATE_LENGTH * 2
			, self::HASH_LENGTH * 2
			, self::STAMP_LENGTH * 2);

		$arr = unpack($format, $s);
		if (!$arr) {
			Log::notice($token, __METHOD__.' error');
			return FALSE;
		}
		// $arr['value'] = substr($s, $pos);
		return $arr;

	}

	public static function verify($token, $time = NULL)
	{
		$arr = static::parse($token);
		if (!$arr) {
			return FALSE;
		}

		if (is_null($time)) {
			$time = time();
		}

		$token_time = intval(hexdec($arr['stamp'])/1000000000);

		return ($token_time + static::LIFE_TIME) > $time;
	}

	private $_parts;
	private $_ver;
	private $_app;
	private $_cate;
	private $_hash;
	private $_stamp;
	private $_value;

	function __construct($token) {
		if (!is_string($token) || empty($token)) {
			throw new InvalidArgumentException('invalid token');
		}

		$arr = static::parse($token);

		if (!$arr) {
			throw new Exception('token parse failed');
		}

		$this->_parts = $arr;
	}

} // END class Imsto_Token
