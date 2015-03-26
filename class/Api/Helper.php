<?PHP

/**
 * Api 接口助手
 *
 *
 * @package Sp
 * @author liut
 **/
class Api_Helper
{
	private $_salt = NULL;
	private $_life = 0;

	/**
	 * 解析 Token 中的内容
	 * @param string $token
	 * @return mixed
	 */
	public static function parseToken($token)
	{
		if (strlen($token) < 40 + 13 + 2) {
			return FALSE;
		}

		return [
			'hash' => substr($token, 0, 40),
			'stamp' => substr($token, 40, 13),
			'value' => substr($token, 40+13),
		];
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
	 * @param string $value
	 * @param string $stamp
	 * @return string
	 */
	public function hashToken($value = NULL, $stamp = NULL)
	{
		if (is_null($value)) {
			$value = sprintf("%x", mt_rand(1000, 999999));
		}
		is_null($stamp) && $stamp = uniqid();
		return sha1($value . $stamp . $this->_salt).$stamp.$value;
	}

	/**
	 * 验证 Token
	 * @param mixed $token
	 * @return string
	 */
	public function verifyToken($token)
	{
		if (is_string($token)) {
			$arr = static::parseToken($token);
		} else {
			$arr = $token;
		}

		if (!is_array($arr)) {
			return FALSE;
		}

		//var_dump($token, $stamp, $value, $this->hashToken($value, $stamp));
		if ($token == $this->hashToken($arr['value'], $arr['stamp'])) {
			if ($this->_life == 0) {
				return TRUE;
			}

			$time = hexdec(substr($arr['stamp']));
		 	if ($this->_life > 0 && $time + $this->_life > time()) {
		 		return TRUE;
		 	}
		}

		return FALSE;
	}

} // END class Sp_Api
