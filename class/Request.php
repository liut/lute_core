<?PHP

/* vim: set expandtab tabstop=4 shiftwidth=4: */

// $Id$

/**
 * Request: 处理 HTTP 请求相关
 *
 */
class Request
{

	/**
	 * Instance parameters
	 * @var array
	 */
	protected $_params = array();

	/**
	 * current user
	 * @var AccountBase instance
	 */
	protected $_user = NULL;

	protected $_user_call = NULL;

	/**
	 * is mobile
	 * @var boolean
	 */
	private $_is_mobile = NULL;

	/**
	 * is https
	 * @var boolean
	 */
	private $_is_https = NULL;

	/**
	 * is ajax or .js, .json
	 * @var boolean
	 */
	private $_is_ajax = NULL;

	/**
	 * EXT is .js, .json
	 * @var boolean
	 */
	private $_is_json = NULL;

	/**
	 * EXT is xml
	 * @var boolean
	 */
	private $_is_xml = NULL;

	/**
	 * constructor
	 *
	 * @param string $uri
	 * @return self
	 */
	protected function __construct($uri)
	{
		$this->_params['URI'] = $uri;
		$this->_params['METHOD'] = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
		$this->_params['TIME'] = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		$this->_params['REQUEST_TIME'] = $this->_params['TIME'];

		$arr = parse_url($uri);
		$path = $arr['path'];
		if (preg_match('#(.+)(\.[a-z]{2,5})$#i', $path, $match)) {
			$this->format = $this->_params['EXT'] = substr($match[2], 1);
			$path = $match[1];
		}

		$this->_params['PATH'] = $path;

		$this->_params['SCHEME'] = isset($arr['scheme']) ? $arr['scheme'] : NULL;
		$this->_params['HOST'] = isset($arr['host']) ? $arr['host'] : NULL;

		unset($arr);

		$this->_params['CLIENT_IP'] = $this->getClientIp();
		$this->_params['CLIENT_AGENT'] = $this->getAgent();

		$this->_params['HTTP_REFERER'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		$this->_params['HTTP_ORIGIN'] = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	}

	public static function farm($uri = '')
	{
		if (!is_string($uri)) {
			throw new InvalidArgumentException("uri must be a string");
		}

		if (empty($uri)) {
			return static::current();
		}

		static $instance = [];
		if (!isset($instance[$uri])) {
			$instance[$uri] = new static($uri);
		}

		return $instance[$uri];
	}
	/**
	 * return current singleton self
	 *
	 * @return object
	 */
	public static function current()
	{
		static $_current;
		if($_current === NULL) {
			if (isset($_SERVER['REQUEST_URI'])) {
				$uri = $_SERVER['REQUEST_URI'];
			} else {
				$uri = '';
			}

			$_current = new static($uri);
		}
		return $_current;
	}

	/**
	 * 返回相关参数
	 *
	 * @param string $key
	 * @return void
	 */
	public function __get($key)
	{
		switch (true) {
			case array_key_exists($key, $this->_params):
				return $this->_params[$key];
			case array_key_exists($key, $_GET):
				return $_GET[$key];
			case array_key_exists($key, $_POST):
				return $_POST[$key];
			case array_key_exists($key, $_COOKIE):
				return $_COOKIE[$key];
			case array_key_exists($key, $_SERVER):
				return $_SERVER[$key];
			// case array_key_exists($key, $_ENV):
			// 	return $_ENV[$key];
			default:
				return NULL;
		}
	}

	/**
	 * Alias to __get, but support filter sanitize
	 *
	 * @param string $key
	 * @param string $filter filter name: string,stripped,encoded,special_chars,email,url,number_int,number_float,magic_quotes...
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($key, $filter = NULL, $default = NULL)
	{
		if ($filter !== NULL) {
			$f_opt = filter_id($filter);//'FILTER_SANITIZE_'.strtoupper($filter);
			if ($f_opt != FALSE) {
				if (filter_has_var(INPUT_POST, $key)) {
					return filter_input(INPUT_POST, $key, $f_opt);
				}

				if (filter_has_var(INPUT_GET, $key)) {
					return filter_input(INPUT_GET, $key, $f_opt);
				}

				return $default;
			}
		}

		$ret = $this->__get($key);

		return is_null($ret) ? $default : $ret;
	}

	/**
	 * function description
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function __set($key, $value)
	{
		$key = (string) $key;

		if ((NULL === $value) && isset($this->_params[$key])) {
			unset($this->_params[$key]);
		} elseif (NULL !== $value) {
			$this->_params[$key] = $value;
		}

	}

	/**
	 * Alias to __set()
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return object
	 */
	public function set($key, $value)
	{
		$this->__set($key, $value);
		return $this;
	}

	/**
	 * 取得用户属性
	 *
	 * @return mixed
	 */
	public function getUser()
	{
		if (is_null($this->_user)) {
			if (is_null($this->_user_call) || !is_callable($this->_user_call)) {
				throw new Exception("property _user_call is unset, or it is not callable", 101);
			}
			$this->_user = call_user_func($this->_user_call);
		}
		return $this->_user;
	}

	/**
	 * 设置用户属性调用方法
	 *
	 * @param mixed $callable
	 * @return void
	 */
	public function setUserCall($callable)
	{
		$this->_user_call = $callable;
		return $this;
	}

	/**
	 * 是否是POST
	 *
	 * @return boolean
	 */
	public function isPost()
	{
		return 'POST' === $this->_params['METHOD'];
	}

	/**
	 * 是否是 Ajax 调用
	 *
	 * @return boolean
	 */
	public function isAjax()
	{
		if (is_null($this->_is_ajax)) {
			$this->_is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
		}
		return $this->_is_ajax;
	}

	public function isJson()
	{
		if (is_null($this->_is_json)) {
			$this->_is_json = ($this->EXT === 'json' || $this->hasAccept('application/json'));
		}

		return $this->_is_json;
	}

	public function isJs()
	{
		return $this->EXT === 'js';
	}

	public function isXml()
	{
		if (is_null($this->_is_xml)) {
			$this->_is_xml = $this->EXT === 'xml';
		}

		return $this->_is_xml;
	}

	public function isApiCall()
	{
		return $this->isAjax() || $this->isJson() || $this->isXml();
	}

	/**
	 * 返回浏览器信息
	 *
	 * @return boolean
	 */
	public function getAgent()
	{
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	}

	/**
	 * 判断是否是旧的浏览器，如IE6
	 *
	 * @return boolean
	 */
	public function isAncient()
	{
		$user_agent = $this->CLIENT_AGENT;
		if(!empty($user_agent) && preg_match("#(MSIE [1-9]\.|Firefox [1-3]\.)#i", $user_agent)) {
			return true;
		}
		return false;
	}

	/**
	 * 取得访问者IP
	 *
	 * @return string
	 */
	public function getClientIp()
	{
		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
		{
			$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}
		elseif (isset($_SERVER["HTTP_CLIENT_IP"]))
		{
			$ip = $_SERVER["HTTP_CLIENT_IP"];
		}
		elseif(isset($_SERVER["REMOTE_ADDR"]))
		{
			$ip = $_SERVER["REMOTE_ADDR"];
		}
		else {
			return '';
		}

		if (strpos($ip, ',') > 0) {
			$ips = explode(',', $ip, 2);
			return $ips[0];
		}

		return $ip;
	}

	/**
	 * 检测并返回可以支持多个域名的cookie domain
	 * 需要提交定义 COOKIE_DOMAIN_SUPPORT
	 * 如： define('COOKIE_DOMAIN_SUPPORT', '.aaa.cc .bbb.cc' );
	 *
	 *
	 * @return string
	 */
	public static function genCookieDomain()
	{
		if(defined('COOKIE_DOMAIN_SUPPORT') && isset($_SERVER['HTTP_HOST']))	// 可以支持多个域名
		{
			foreach(explode(' ', COOKIE_DOMAIN_SUPPORT) as $domain)
			{
				$len = strlen($domain);
				if(substr($_SERVER['HTTP_HOST'], strlen($_SERVER['HTTP_HOST']) - $len) == $domain)
				{
					return $domain;
					break;
				}
			}
		}

		return '';
	}

	/**
	 * 解码被js escape或 htmlentities 编码的字符
	 * 注意： js escape 编码的内容总是 UTF-8
	 *
	 * @param string $str
	 * @param string $charset
	 * @return string
	 */
	public static function decode($str, $charset = 'UTF-8', $from_url = false)
	{
		$from_url && $str = urldecode($str);
		$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", $str);
		return html_entity_decode($str,NULL,$charset);
	}

	/**
	 * 处理日期输入
	 *
	 * @param mixed $date
	 * @param int $stamp
	 * @return string
	 */
	public static function checkDate($date, & $stamp = 0)
	{
		if (is_string($date) && $stamp = strtotime($date)) {
			return $date;
		}
		$stamp = is_int($date) ? $date : time();
		return date("Y-m-d", $stamp);
	}

	/**
	 * 判断是否是App访问，暂时只检测 iOS
	 *
	 * @param string $custom_name
	 * @return boolean
	 **/
	public function isApp($custom_name = NULL)
	{
		$ua = $this->getAgent();
		$ret = (strpos($ua, 'Darwin/') !== FALSE && strpos($ua, 'CFNetwork/') !== FALSE);

		if (is_null($custom_name) && defined('APP_CODE_IOS')) {
			$custom_name = APP_CODE_IOS;
		}

		if ($ret && is_string($custom_name) && strlen($custom_name) > 2) {
			$ret = (strpos($ua, $custom_name . '/') !== FALSE);
		}

		return $ret;
	}

	/**
	 * check browser is mobile
	 *
	 * @return boolean
	 */
	public function isMobile()
	{
		if (!is_NULL($this->_is_mobile)) return $this->_is_mobile;

		//$ua = strtolower($this->getAgent());
		$ac = strtolower(isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '');
		$op = strtolower(isset($_SERVER['HTTP_X_OPERAMINI_PHONE']) ? $_SERVER['HTTP_X_OPERAMINI_PHONE'] : '');

		$this->_is_mobile = strpos($ac, 'application/vnd.wap.xhtml+xml') !== false
		|| $op != ''
		|| /*strpos($ua, 'iphone') !== false
		|| strpos($ua, 'ipod') !== false
		|| strpos($ua, 'blackberry') !== false
		|| strpos($ua, 'nokia') !== false
		|| strpos($ua, 'sony') !== false
		|| strpos($ua, 'symbian') !== false
		|| strpos($ua, 'samsung') !== false
		|| strpos($ua, 'mobile') !== false
		|| strpos($ua, 'windows ce') !== false
		|| strpos($ua, 'windows phone') !== false
		|| strpos($ua, 'epoc') !== false
		|| strpos($ua, 'opera mini') !== false
		|| strpos($ua, 'nitro') !== false
		|| strpos($ua, 'j2me') !== false
		|| strpos($ua, 'midp-') !== false
		|| strpos($ua, 'cldc-') !== false
		|| strpos($ua, 'netfront') !== false
		|| strpos($ua, 'mot') !== false
		|| strpos($ua, 'up.browser') !== false
		|| strpos($ua, 'up.link') !== false
		|| strpos($ua, 'audiovox') !== false
		|| strpos($ua, 'ericsson,') !== false
		|| strpos($ua, 'panasonic') !== false
		|| strpos($ua, 'philips') !== false
		|| strpos($ua, 'sanyo') !== false
		|| strpos($ua, 'sharp') !== false
		|| strpos($ua, 'sie-') !== false
		|| strpos($ua, 'portalmmm') !== false
		|| strpos($ua, 'blazer') !== false
		|| strpos($ua, 'avantgo') !== false
		|| strpos($ua, 'danger') !== false
		|| strpos($ua, 'palm') !== false
		|| strpos($ua, 'series60') !== false
		|| strpos($ua, 'palmsource') !== false
		|| strpos($ua, 'pocketpc') !== false
		|| strpos($ua, 'smartphone') !== false
		|| strpos($ua, 'rover') !== false
		|| strpos($ua, 'ipaq') !== false
		|| strpos($ua, 'au-mic,') !== false
		|| strpos($ua, 'alcatel') !== false
		|| strpos($ua, 'ericy') !== false
		|| strpos($ua, 'up.link') !== false
		|| strpos($ua, 'vodafone/') !== false
		|| strpos($ua, 'wap1.') !== false
		|| strpos($ua, 'wap2.') !== false;

		//*/ // use regex from http://detectmobilebrowsers.com/
		 preg_match('#(android|bb\d+|meego).+mobile|avantgo|bada/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino#i', $this->getAgent());

		return $this->_is_mobile;
	}

	/**
	 * check scheme is https
	 *
	 * @return boolean
	 */
	public function isHttps()
	{
		if (!is_NULL($this->_is_https)) return $this->_is_https;
		$this->_is_https = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on';
		return $this->_is_https;
	}

	/**
	 * 返回完整的Url
	 *
	 * @return string
	 */
	public function getFullUri()
	{
		if ($this->_params['SCHEME'] && $this->_params['HOST']) {
			return $this->_params['URI'];
		}

		if (!isset($_SERVER['HTTP_HOST'])) {
			return NULL;
		}

		$scheme = $this->_params['SCHEME'] ?: ($this->isHttps() ? 'https:' : 'http:');
		return $scheme . "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
	}

	public function __toString()
	{
		return sprintf("uri: %s, referer: %s, ip: %s, ua: %s",
			$this->URI, $this->HTTP_REFERER, $this->CLIENT_IP, $this->CLIENT_AGENT
		);
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	public function getContentType($format = NULL)
	{
		$accept = $this->HTTP_ACCEPT;
		if ($this->hasAccept('text/event-stream', $accept)) {
			return 'text/event-stream';
		}

		if (is_null($format)) {
			$format = $this->EXT;
		}

		if ($format == 'js' || $this->hasAccept('text/javascript', $accept)) {
			return 'text/javascript';
		}

		if ($format == 'json' || $this->hasAccept('application/json', $accept) || $this->isAjax()) {
			return 'application/json'; //'text/javascript';//'text/x-json';//
		}

		if ($format == 'xml') {
			return 'text/xml';
		}

		if ($format == 'htm' || $format == 'html') {
			return 'text/html';
		}

		if ($format == 'txt') {
			return 'text/plain';
		}

		return FALSE;//'text/html';
		// TODO: more type

	}

	public function hasAccept($mime, $accept = NULL)
	{
		is_null($accept) && $accept = $this->HTTP_ACCEPT;

		return strpos($accept, $mime) !== FALSE;
	}

	public static function parseAcceptLang($accept_lang)
	{
		if (is_string($accept_lang)) {
			// break up string into pieces (languages and q factors)
			if( !preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $accept_lang, $matches) ) { // >0, 0, FALSE
				Log::notice($accept_lang, __METHOD__);
				return FALSE;
			}

			if (count($matches[1])) {
				// create a list like "en" => 0.8
				$langs = array_combine($matches[1], $matches[4]);

				// set default to 1 for any without q factor
				foreach ($langs as $lang => $val) {
					if ($val === '') $langs[$lang] = 1;
				}

				// sort list based on value
				arsort($langs, SORT_NUMERIC);

				return $langs;
			}
		}

		return FALSE;
	}

	public function getLangs()
	{
		$langs = array();

		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && $langs = self::parseAcceptLang($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			return $langs;
		}

		return $langs;
	}

	/**
	 * parse Accept-Language to detect a user's language
	 */
	public function getLang($available = NULL)
	{
		if (is_null($available)) {
			$available = defined('LANG_AVAILABLE') ? explode(' ', LANG_AVAILABLE) : ['en'];
		}
		elseif (!is_array($available)) {
			$available = [$available];
		}

		$force_lang = $this->__get('lang');

		if (in_array($force_lang, $available)) {
			return $force_lang;
		}

		$langs = $this->getLangs();

		foreach ($langs as $lang => $w) {
			if (strpos($lang, '-') === 2) {
				$lang = substr($lang, 0, 2);
			}
			if (in_array($lang, $available)) {
				return $lang;
			}
		}

		// default
		return defined('LANG_FALLBACK') ? LANG_FALLBACK : NULL;

	}

	public static function allHttpHeaders()
	{
		$headers = array();
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}
