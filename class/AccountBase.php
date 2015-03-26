<?php
/**
 * AccountBase
 *
 *
 * @author liut
 * @version $Id$
 * @created 17:45 2012年05月24日
 */

/**
 * 	 账户基础类
 *
 *
 *
 *
 * 	示例:
 * 	<code class="php">
 * 	class Eb_Account extends AccountBase
 * 	{
 * 		const COOKIE_NAME = '_bSA';
 *
 *
 *
 *	}
 * 	</code>
 *
 */
abstract class AccountBase extends Model
{
	// 必须定义的常量
	//const COOKIE_NAME = '_bSA';
	//const COOKIE_LIFE = 604800;//	60*60*24*7;
	//const ENCRYPT_KEY = "Mc8p-HUu182KQWSN";	// must be 16 chars

	// 和 Authorization API 认证相关的常量
	const API_NAME = NULL;
	const API_ID = NULL;
	const API_LIFE = 1800;

	// 和 DB 相关的常量
	//const FIELD_LOGIN   = 'email';			// 登录名字段

	// ERROR code
	const ERR_NOT_FOUND 	= -1001;	// 没有这个用户
	const ERR_INCORRECT 	= -1002; 	// 密码不正确
	const ERR_DISABLED 		= -1003; 	// 用户被禁止访问

	// 必须定义的变量
	//protected static $_db_name = 'ad.test';
	//protected static $_table_name = 'users';
	protected static $_idle_time = 3600;
	//protected static $_stored_keys = array('id','login','name','lastHit');
	protected static $_stored_ver = 1; // TODO:: 实现结构版本

	public static function idleTime()
	{
		return static::$_idle_time;
	}

	protected static $_current;
	/**
	 * return current singleton self
	 *
	 * @return object
	 */
	public static function current()
	{
		if(static::$_current === null) static::$_current = static::retrieve();
		return static::$_current;
	}


	/**
	 * retrieve account from request
	 *
	 * @return Account
	 */
	protected static function retrieve()
	{
		if(!isset($_COOKIE[static::COOKIE_NAME]) || empty($_COOKIE[static::COOKIE_NAME])) {
			if (is_string(static::API_NAME) && is_int(static::API_ID)) {
				$auth_name = 'HTTP_'.strtoupper(static::API_NAME);
				$user = static::apiRetrieve($auth_name);
				if ($user) {
					return $user;
				}
			}

			return static::guest();
		}

		$encrypted_string = $_COOKIE[static::COOKIE_NAME];

		$decryption = static::decrypt($encrypted_string, static::ENCRYPT_KEY);

		if (!$decryption) {
			return static::guest();
		}

		$data = static::parseStored($decryption, static::$_stored_keys);
		$data['stateStored'] = true;
		$user = static::farm($data);

		return $user;
	}

	/**
	 * retrieve account from request, for api authorization
	 *
	 * @return Account
	 * @author liut
	 **/
	protected static function apiRetrieve($auth_name)
	{
		if (!isset($_SERVER[$auth_name])) {
			Log::info($auth_name, __METHOD__ . ' auth_name not found in header');
			return FALSE;
		}

		$dat = $_SERVER[$auth_name];
		Log::info($dat, 'Authorization header checked '.$auth_name);

		$ah = Api_Token::farm(static::API_ID);
		$ah->life(static::API_LIFE);

		$arr = Api_Token::parse($dat);
		Log::info($arr, 'token parsed');

		if (is_array($arr) && $ah->verify($arr)) {
			$api_access_key = base64_encode($arr['value']);
			Log::info('token ok '.$api_access_key);
			$data = static::findByPk((string)$api_access_key, 'access_key');
			if ($data) {
				// Log::info($data, __METHOD__.' found');
				$data['stateStored'] = true;
				return static::farm($data);
			}
			Log::warning($api_access_key, 'access key not found');
		}

		Log::warning($dat, 'Invalid Authorization');
		return FALSE;
	}

	/**
	 * 解析Coookie中存储的值为一个关联数组
	 *
	 * @param string $cookie_value
	 * @param array $keys
	 * @param string $delimiter
	 * @return array
	 */
	public static function parseStored($cookie_value, $keys, $delimiter = ',')
	{
		$k_len = count($keys);
		$u_vals = explode($delimiter, $cookie_value, $k_len + 2);
		if(count($u_vals) > $k_len) $u_vals = array_slice($u_vals, 0, $k_len);
		else $u_vals = array_pad($u_vals, $k_len, '');
		$data = array_combine($keys, $u_vals);

		return $data;
	}

	/**
	 * 用 base64 解码
	 *
	 * @param string $string
	 * @return string
	 */
	protected static function base64Decode($string)
	{
		$base64_string = strtr($string, '-_.', '+/=');
		return base64_decode($base64_string);
	}

	/**
	 * 用 base64 编码
	 *
	 * @param string $string
	 * @return string
	 */
	protected static function base64Encode($string)
	{
		$base64_string = base64_encode($string);
		return strtr($base64_string, '+/', '-_');
	}

	/**
	 * 解密
	 *
	 * @param string $text
	 * @param string $key
	 * @return string
	 */
	protected static function decrypt($text, $key = null)
	{
		$pos = strpos($text, '.');
		if (function_exists('xxtea_decrypt') && $pos === 0) {
			is_null($key) && $key = static::ENCRYPT_KEY;
			$encrypted_string = static::base64Decode(substr($text, 1));
			return xxtea_decrypt($encrypted_string, $key);
		}

		if ($pos === 0) {
			return FALSE; // error value
		}

		return static::base64Decode($text);
	}

	/**
	 * 加密
	 *
	 * @param string $text
	 * @param string $key
	 * @return string
	 */
	protected static function encrypt($text, $key = null)
	{
		if (function_exists('xxtea_encrypt')) {
			is_null($key) && $key = static::ENCRYPT_KEY;
			$encrypted_string = xxtea_encrypt($text, $key);
			return '.' . static::base64Encode($encrypted_string);
		}

		return static::base64Encode($text);
	}

	/**
	 * 计算 Hash 过的密码
	 *
	 * @param string $password
	 * @param string $salt
	 * @return string
	 */
	public static function hashPassword($password)
	{
		if (!function_exists('password_hash')) {
			include_once LIB_ROOT . 'function/password_compat/password.php';
		}

		return password_hash($password, PASSWORD_DEFAULT);
	}

	/**
	 * 返回一个未认证的匿名用户对象
	 *
	 * @return Account
	 */
	protected static function guest()
	{
		static $_guest;
		if($_guest === null)
		{
			$_guest = static::farm(array(
				'id' => 0,
				'name' => 'guest'
			));
		}
		return $_guest;
	}

	/**
	 * 根据登录名和密码，验证用户
	 *
	 * @param string $username
	 * @param string $password
	 * @param array $option = null
	 * @return mixed 成功返回 self instance，失败返回负数
	 */
	public static function authenticate($username, $password, $option = null)
	{
		if (is_string($option)) {
			$pk = $option;
		}
		if (!isset($pk) || empty($pk)) {
			$pk = static::FIELD_LOGIN;
		}
		$row = static::find([
			'where' => [$pk => $username],
			'columns' => 'id,'.$pk.',password,status',
			'fetch' => 'row',
			'limit' => 1
		]);
		if (!$row) {
			return static::ERR_NOT_FOUND;
		}

		if (!$row['password']) {
			Log::notice($username, __METHOD__.' password incorrect ');
			return static::ERR_INCORRECT;
		}

		if(!static::verifyPassword($row['password'], $password)) {
			$tlog = sprintf("password incorrect: %s %s***%s", $username, substr($password, 0, 1), substr($password, -1));
			Log::notice($tlog, __METHOD__);
			return static::ERR_INCORRECT;
		}

		if($row['status'] < 1) {
			return static::ERR_DISABLED;
		}

		return static::load($row['id']);
	}

	/**
	 * verify password
	 * @param string stored password
	 * @param string input password
	 * @return boolean
	 */
	public static function verifyPassword($password_stored, $password_input)
	{
		if (!function_exists('password_verify')) {
			include_once LIB_ROOT . 'function/password_compat/password.php';
		}

		return password_verify($password_input, $password_stored);
	}

	/**
	 * 验证是否登录
	 *
	 * @param
	 * @return void
	 */
	public function isLogin()
	{
		$valid = $this->isValid();
		$stateStored = $this->stateStored;
		return $valid && $stateStored && $this->lastHit > 0;
	}

	public function isActive()
	{
		$now = self::now();
		return $this->isLogin() && ($this->lastHit + static::idleTime() > $now);
	}

	/**
	 * 返回可用于存储在连线状态（如Cookie、Session等）中的值
	 *
	 * @return string
	 */
 	protected function getStoredValue()
	{
		$values = $this->toArray(static::$_stored_keys);
		return implode(',', $values);
	}

	/**
	 * set password
	 *
	 * @param string $password
	 * @return void
	 */
	public function setPassword($password)
	{
		$this->_innerSet('password', static::hashPassword($password));
	}

	/**
	 *
	 */
	public function save()
	{
		// 从Cookie获取的账户信息，在保存时需要判断是否已经存在
		if ($this->isNew()) {
			if ($this->isValid()) {
				$pk = static::primaryKey();
				$id = $this->id;
			} else {
				$pk = static::FIELD_LOGIN;
				$id = $this->__get($pk);
			}
			$row = static::exist($id, $pk);
			if ($row) {
				$this->isNew(FALSE);
			}
		}
		return parent::save();
	}

	/**
	 * 退出（清除cookie）
	 *
	 * @return void
	 */
	public function signout()
	{
		$this->_innerSet('id', 0);
		if (method_exists($this, 'clear')) {
			$this->clear();
		}
		return setcookie(static::COOKIE_NAME, FALSE, time()+315360000, '/', Request::genCookieDomain());
	}

	/**
	 * 最后命中时间
	 *
	 * @return void
	 */
	public function getLastHit()
	{
		$timestamp = self::now();
		return $timestamp;
	}

	/**
	 * 根据发呆时间，检查并更新登录状态
	 *
	 * @return void
	 */
	public function refresh($force = FALSE)
	{
		if($this->isLogin()) {
			$now = self::now();
			if ($force) {
				$this->lastHit = $now;
				static::setHttpCookie($this);
			}
			$last_hit = $this->lastHit; //var_dump($last_hit, $now, static::$_idle_time);
			if($last_hit + static::idleTime() < $now) { // timeout
				//$this->stateStored = false;
				return false;
			}
			$sub_time = $now - $last_hit;
			if($sub_time > static::idleTime()/2 && $sub_time < static::idleTime()) {
				// refresh timestamp and save to cookie
				$this->lastHit = $now;
				static::setHttpCookie($this);
			}
			return true;
		}
		return false;
	}

	/**
	 * 设置Cookie, 设定为登录
	 *
	 * @param object $user
	 * @return mixed
	 */
	protected static function setHttpCookie($user)
	{
		if(is_object($user)) {
			if(isset($_SERVER['HTTP_HOST'])) {	// 只有在HTTP请求下才写Cookie
				$user->stateStored = true;
				$str = $user->getStoredValue();
				Log::debug('stored value: ' . $str);
				$cookie = static::encrypt($str, static::ENCRYPT_KEY); //var_dump($cookie);
				setcookie(static::COOKIE_NAME, $cookie, time()+static::COOKIE_LIFE, '/', Request::genCookieDomain());
			}
			static::$_current = $user;
		}
		return $user;
	}

	protected static function now()
	{
		return isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
	}

	private static $_cached_exists = [];

	public static function exist($id, $pk = NULL)
	{
		$class = get_called_class();
		is_null($pk) && $pk = static::FIELD_LOGIN;
		$key = $class.'_'.$pk.'_'.$id;
		if (!isset(self::$_cached_exists[$key])) {
			self::$_cached_exists[$key] = static::findByPk($id, $pk);
			Log::debug($class.'::exist '.$id . ' ' . (isset(self::$_cached_exists[$key])?'yes':'no'));
		}
		return self::$_cached_exists[$key];
	}

}


