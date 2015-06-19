<?PHP

/**
 * Api Token 助手
 *
 *
 * @package Core
 * @author liut
 **/
class Api_Token
{
	private $_salt = NULL;
	private $_life = 0;

	const HASH_LENGTH = 20;
	const STAMP_LENGTH = 6;
	const STAMP_FORMAT = '%012x';

	/**
	 * 返回 UTC timestamp
	 *
	 * @return float
	 **/
	public static function stamp($inc_ms = FALSE)
	{
		if ($inc_ms) {
			$stamp = $_SERVER['REQUEST_TIME_FLOAT'];
			return floatval(strtr($stamp, ' ', '.'));
		}

		return (int)$_SERVER['REQUEST_TIME'];
	}

	/**
	 * 解析 Token 中的内容
	 * @param string $token
	 * @return mixed
	 */
	public static function parse($token)
	{
		$pos = self::HASH_LENGTH + self::STAMP_LENGTH;
		if (strlen($token) < $pos + 1) {
			return FALSE;
		}

		$s = base64_decode($token);
		$format = sprintf('H%dhash/H%dstamp', self::HASH_LENGTH * 2, self::STAMP_LENGTH * 2);
		$arr = unpack($format, $s);
		if (!$arr) {
			Log::notice($token, __METHOD__.' error');
			return FALSE;
		}
		$arr['value'] = substr($s, $pos);
		return $arr;

		// return [
		// 	'hash' => substr($token, 0, self::HASH_LENGTH),
		// 	'stamp' => substr($token, self::HASH_LENGTH, self::STAMP_LENGTH),
		// 	'value' => substr($token, self::HASH_LENGTH + self::STAMP_LENGTH),
		// ];
	}

	public static function farm($id = 0)
	{
		$instances = [];

		is_int($id) || $id = intval($id);

		if (!isset($instances[$id])) {
			$const_name = 'API_'.$id.'_SALT';
			$salt = defined($const_name) ? constant($const_name) : '0123abcdefghijk';
			$instances[$id] = new static($salt);
		}

		return $instances[$id];
	}

	public function __construct($salt)
	{
		$this->_salt = $salt;
	}

	public function life($life = NULL)
	{
		if (is_null($life)) {
			return $this->_life;
		}

		if (is_int($life) && $life >= 0) {
			$this->_life = $life;
		}
	}

	/**
	 * stamp length must be 13 chars
	 * @param binary $value
	 * @param string $stamp
	 * @return string
	 */
	public function hash($value, $stamp = NULL)
	{
		if (!is_scalar($value)) {
			throw new InvalidArgumentException('invalid string or numeric value', -101);
		}

		if (is_null($stamp)) {
			$stamp = sprintf(self::STAMP_FORMAT, self::stamp() * 1000);
		}
		elseif (is_int($stamp) || is_float($stamp)) { // time() or microtime()
			$stamp = sprintf(self::STAMP_FORMAT, $stamp * 1000);
		}
		elseif (!preg_match('#^[0-9a-f]+$#', $stamp)) {
			throw new InvalidArgumentException('invalid stamp '.$stamp, -101);
		}

		$hstamp = pack('H*', $stamp);

		$hashed = sha1($value . $hstamp . $this->_salt, TRUE);

		// Log::info($hashed . ' ' . $value . ' ' . $stamp, __METHOD__);

		return base64_encode($hashed.$hstamp.$value);
	}

	/**
	 * 验证 Token
	 * @param mixed $token
	 * @return string
	 */
	public function verify($token)
	{
		if (is_string($token)) {
			$arr = static::parse($token);
		} else {
			$arr = $token;
		}

		if (!is_array($arr)) {
			return FALSE;
		}

		$hashed = $this->hash($arr['value'], $arr['stamp']);
		$t2 = self::parse($hashed);

		if (is_array($t2) && $arr['hash'] == $t2['hash'] ) {
			if ($this->_life == 0) {
				Log::notice('life is unlimited', __METHOD__);
				return TRUE;
			}

			$now = self::stamp();
			$time = intval(hexdec($arr['stamp'])/1000);
			$elapse = $now - ($time + $this->_life);

			// Log::info([$time, $this->_life, $now, $elapse], __METHOD__.' times');

			if ($this->_life > 0 && $elapse < 0) {
				return TRUE;
			}

			Log::notice([gmdate('d H:i ', $time).$time, $this->_life, gmdate('d H:i ', $now).$now, $elapse], __METHOD__.' timeout');

			return FALSE;
		}

		Log::notice('mismatch '.$arr['hash'].' and '.$t2['hash'], __METHOD__);

		return FALSE;
	}

} // END class Api_Token
