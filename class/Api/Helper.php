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
	 * @param string $token
	 * @return string
	 */
	public function verifyToken($token)
	{
		$arr = static::parseToken($token);
		if (!$arr) {
			return FALSE;
		}

		//var_dump($token, $stamp, $value, $this->hashToken($value, $stamp));
		return $token == $this->hashToken($arr['value'], $arr['stamp']);
	}

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

} // END class Sp_Api
