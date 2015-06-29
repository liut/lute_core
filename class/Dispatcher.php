<?PHP


/**
 * Dispatcher Web 调度器
 *
 * example:
 *
 * Dispatcher::farm([
 * 	//'before_dispatch' => function(){},
 * 	'default_controller' => 'home',
 * 	'default_action' => 'index'
 * ])->run();
 *
 * public properties:
 * 		request
 * 		lang
 * 		controlName()
 * 		actionName()
 * 		getParams()
 *
 */
class Dispatcher
{
	protected static $_config = [
		'action_prefix' => 'action_',
		'before_dispatch' => NULL,
		'controller_prefix' => 'Controller_',
		'default_controller' => 'home',
		'default_action' => 'index',
		'lang' => NULL,
		'account_call' => NULL, // 加载 account 类，内容为 callable
		'namespace' => '',
		'request' => NULL,
		'view_call' => NULL, // A callable as return View_Engine_Interface instance
		'view_ext' => '.htm'
	];

	private $_control_name = '';
	private $_action_name = '';

	private $_params = [];

	private $_is_api_call = FALSE;
	/**
	 * @var jsonp callback name
	 */
	private $_callback = NULL;
	private $_wraped = FALSE;
	private $_cached = FALSE;

	/**
	 * 初始化一个调度实例
	 *
	 * example:
	 *
	 * $dispatcher = Dispatcher::farm([
	 * 	//'before_dispatch' => function(){},
	 * 	'default_controller' => 'home',
	 * 	'default_action' => 'index'
	 * ]);
	 *
	 * // $dispathcer->run();
	 *
	 * @param array $config
	 * @return self instance
	 */
	public static function farm(array $config = [])
	{
		static $_instance = NULL;
		is_null($_instance) && $_instance = new static($config);
		return $_instance;
	}

	/**
	 * 直接执行一个Web调度
	 *
	 * example:
	 *
	 * Dispatcher::start([
	 * 	//'before_dispatch' => function(){},
	 * 	'default_controller' => 'home',
	 * 	'default_action' => 'index'
	 * ]);
	 *
	 * @param array $config
	 * @deprecated by ::farm()->run()
	 */
	public static function start(array $config = [])
	{
		static $_started = FALSE;
		// 避免重复调用
		if ($_started) { return TRUE;}
		$_started = TRUE;

		self::farm($config)->run();
	}

	/**
	 * constructor
	 *
	 * @param array config
	 */
	protected function __construct(array $config)
	{
		// override self default config
		foreach (self::$_config as $key => $value) {
			if (array_key_exists($key, $config)) {
				self::$_config[$key] = $value = $config[$key];
			}
			$this->$key = $value;
		}

		$this->request || $this->request = Request::current();
		$this->lang || $this->lang = $this->request->getLang();
		$this->lang && $this->lang = strtolower($this->lang);
		$this->lang && self::$_config['lang'] = $this->lang;

		$this->_is_api_call = $this->request->isApiCall();

		$this->_callback = $this->request->jsoncallback;
		if(empty($this->_callback)) {
			$this->_callback = $this->request->callback;
		}

		$uri = explode('/', trim($this->request->PATH, '/.'));

		if(is_array($uri) and count($uri) > 0){
			//controller
			$ctl = array_shift($uri);
			// add version support
			if ( defined('SITE_ROOT') && preg_match("#^v\d{1,4}$#i", $ctl)) {
				$_dir = SITE_ROOT . '_class_' . $ctl;
				if (is_dir($_dir)) {
					Loader::import($_dir);
					unset($_dir);
					$ctl = array_shift($uri);
				}
			}

			if(!empty($ctl)) {
				if (preg_match("#^[a-z0-9_\.\-]+$#i", $ctl)){ // verify controller name
					$this->_control_name = $ctl;
				}
				else {
					// try to fix invalid ctl
					$_ctl = urldecode($ctl);
					if (strpos($_ctl, ' ') !== FALSE) {
						$_ctl = strtr($_ctl, [' '=> '']);
						if ($_ctl != $ctl) {
							header("Location: ".str_replace($ctl, $_ctl, $this->request->URI));
							exit;
						}
					}

					// try to fix invalid link
					$pos = strpos($_ctl, '>');
					if ($pos !== FALSE) {
						$_ctl = substr($_ctl, 0, $pos);
						header("Location: /$_ctl");
						exit;
					}

					$this->out(400, 'invalid controller name');
					exit;
				}
			}
			//action 对于php关键词进行特殊处理
			$act = array_shift($uri); // act: NULL, '', '0', strings
			if(is_string($act) && $act !== ''){ // empty('0') == true in PHP
				if (ctype_graph($act)){ // verify action name
					$this->_action_name = $act;
				}
				else {
					$this->out(400, 'invalid action name');
					exit;
				}
			}
			//整理uri得到的参数
			// TODO: 待优化
			foreach($uri as $k => $v){
				$this->_params[] = $v;
			}
		}

		if ($this->account_call && is_callable($this->account_call)) {
			$this->request->setUserCall($this->account_call);
		}

	}

	/**
	 * @return string
	 */
	public function controllerName()
	{
		return $this->_control_name;
	}

	/**
	 * @return string
	 */
	public function actionName()
	{
		return $this->_action_name;
	}

	/**
	 * @return array
	 */
	public function getParams()
	{
		return $this->_params;
	}

	private function _run()
	{

		empty($this->_control_name) && $this->_control_name = $this->default_controller;
		empty($this->_action_name) && $this->_action_name = $this->default_action;

		$control_name = $this->_fixCtlName($this->_control_name);
		$class_control = $this->_getCtlClass($control_name);

		// load controller class
		if (!class_exists($class_control)) {
			$error_ctrl = $this->controller_prefix . 'Error404';
			if ($error_ctrl != $class_control && class_exists($error_ctrl)) {
				$obj_ctl = new $error_ctrl($this->request);
				$obj_ctl->control_name = $this->_control_name;
				$obj_ctl->dispatcher($this);
				if (method_exists($obj_ctl, '__call')) {
					$response = $obj_ctl->__call($this->_action_name, $this->_params);
					return $this->send($response, $this->_action_name);
				}
			}
			return $this->out(404, 'controller not found');
		}

		if (is_callable($this->before_dispatch)) {
			$this->before_dispatch();
		}

		return $this->forward($control_name, $this->_action_name, $this->_params);
	}

	private function _fixCtlName($control_name)
	{
		return strtr($control_name, ['-' => '', '_' => '']);
	}

	private function _getCtlClass($control_name)
	{
		$class_control = $this->controller_prefix . ucfirst($control_name);

		if (!empty($this->namespace)) {
			$class_control = $this->namespace . "\\" . $class_control;
		}

		return $class_control;
	}

	/**
	 *
	 * @param string $control_name
	 * @param string $action_name
	 * @param array $params
	 * @return mixed
	 */
	public function forward($control_name, $action_name, array $params)
	{
		$class_control = $this->_getCtlClass($control_name);

		try {
			$obj_ctl = new $class_control($this->request);
			if (!$obj_ctl instanceof Controller) {
				return $this->out(400, 'Invalid Controller');
			}

			if (defined('HAS_MULTIDOMAIN') && TRUE === HAS_MULTIDOMAIN) { // 多服务域专用
				$properties = get_class_vars($class_control);
				$bo_prefix = static::loadBoNs($control_name);
				foreach($properties as $property => $value) {
					if (strncmp($property, 'bo', 2) === 0 && is_null($value)) {
						$bo_name = static::loadBoClass($bo_prefix, substr($property, 2));
						$obj_ctl->$property = new Eb_BoRefer($bo_name);
					}
				}
			}

			$obj_ctl->dispatcher($this);

			if (empty($action_name)) {
				$action_name = $this->default_action;
			}

			$obj_ctl->actionName($action_name);

			// before action hook
			if (method_exists($obj_ctl, 'beforeAction')) {
				$ret = $obj_ctl->beforeAction($action_name, $this->_params);
				if (is_array($ret) || is_int($ret) || is_string($ret)) { // NULL will be continue
					return $this->send($ret, $action_name);
				}
			}

			$req_method = strtolower($this->request->REQUEST_METHOD);

			$calls = [];

			if (empty($action_name)) {
				$calls[] = $this->action_prefix . '_' .$req_method;
			} else {
				$calls[] = $this->action_prefix . $action_name . '_' .$req_method;
				$calls[] = $this->action_prefix . $action_name;
			}

			$calls[] = 'fancy_call' . '_' .$req_method;
			$calls[] = 'fancy_call';
			$calls[] = '__call';

			//调用执行方法
			foreach ($calls as $m_name) {
				if (method_exists($obj_ctl, $m_name)) {
					Log::info('hit '.$control_name.'/'.$action_name.' -> '.$class_control.':'.$m_name, __METHOD__);
					if ($m_name == '__call') {
						$response = $obj_ctl->__call($action_name, $params);
					}
					else {
						if (strpos($m_name, 'fancy_call') === 0 ) {
							array_unshift($params, $action_name);
						}
						$response = call_user_func_array(array($obj_ctl, $m_name), $params);
					}

					// after action hook
					if (method_exists($obj_ctl, 'afterAction')) {
						$obj_ctl->afterAction($response);
					}

					return $this->send($response, $action_name);

				}
			}

			return $this->out(404, 'action `'.$action_name.'` not found');

		}
		catch(Exception $e) {
			$label = __METHOD__ . ' ' . $control_name . '/' . $action_name;
			if (count($params) > 0) {
				$label .= '/' . implode('/', $params);
			}
			if ($this->request->isPost()) {
				Log::notice($_POST, $label.' _POST');
			}

			Log::warning($e, $label);

			if (defined('_PS_DEBUG') && TRUE === _PS_DEBUG) {

				if (!$this->_is_api_call) {
					throw $e;
				}
				return $this->send([FALSE, 'data' => NULL, 'error' => [
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
					'file' => Loader::safePath($e->getFile()),
					'line' => $e->getLine(),
					'trace' => $e->getTrace(),
					'trace_string' => Loader::printException($e, TRUE),
				]]);
			}

			if ($e instanceof PDOException) {
				$e = new Exception('An internal error has occurred and we are working on it. Please try later.', $e->getCode());
			}

			if (!$this->_is_api_call) {
				return $this->out(404, $e->getMessage());
			}
			return $this->send([FALSE, 'data' => NULL, 'error' => [
				'code' => $e->getCode(),
				'message' => $e->getMessage(),
				// 'trace_string' => Loader::printException($e, TRUE),
			]]);
		}
	}

	/**
	 * 执行入口
	 */
	public function run($cached = FALSE)
	{
		$this->preheat();

		$this->_cached = $cached;

		if ($cached === FALSE) {
			$this->_run();
			return;
		}

		$section = 'controller_' . strtolower($this->_control_name);
		$config = Cache::config($section);
		if (!is_array($config)) {
			Log::info($config, __METHOD__.' cache instance load error : ' . $section);
			$this->_run();
			$this->jsonpWrapEnd();
			return;
		}

		$cache = Cache::farm($section);
		$_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 's' : '';
		$cache->setOption('subgroup', $this->lang . $_https);

		$key = $this->_action_name;
		$no_index = $cache->getOption('no_index');

		if ($key == $this->default_action && $no_index) {
			$this->_run();
			return;
		}

		if ($this->_is_api_call) { // 让 Api 调用生成不同的缓存
			$key .= '_api';
		}

		$cache->setOption('beforeOutput', [$this, 'jsonpWrapBegin']);
		//$cache->setOption('checkModified', TRUE);
		$fileSuffix = $cache->getOption('fileSuffix');

		if (!$fileSuffix) {
			$format = $this->request->format;
			if ($key == $this->default_action) {
				$format = 'html';
			}
			$fileSuffix = '.'.$format;
		}

		if ($fileSuffix) {
			$cache->setOption('fileSuffix', $fileSuffix);
		}

		$id = $cache->makeId(strtolower($key), $this->_params);

		Log::debug($id, __METHOD__);

		if (!$cache->start($id)) {

			$ret = $this->_run();

			if (is_int($ret) && $ret > 300) {
				if ($ret === 301 || $ret === 302 ) {
					return $ret;
				}
				$this->jsonpWrapEnd();
				return $ret;
			}

			$cache->end();
			//echo "<!-- $id -->", PHP_EOL;
		}
		$this->jsonpWrapEnd();
	}

	public function cacheRun()
	{
		$this->run(true);
	}

	/**
	 * send response
	 * @param mixed $response
	 * @param string $method
	 * @return void
	 */
	public function send($response, $method = NULL)
	{
		if (is_null($response) || is_bool($response)) {
			Log::info('dispatcher::send(): ctl: ' . $this->_control_name . ', method: '.$method.', response is null or boolean');
			//
			return;
		}
		if (is_int($response)) {
			return $this->out($response);
		}
		if (is_string($response)) {
			if (preg_match("#^[a-z0-9_/\.]+$#i", $response)) {
				$response = [$response];
			} else {
				$response = ['content' => $response];
			}
		}

		if (!is_array($response)) {
			//throw new Exception("Controller action return NULL or None output", 1);
			$response = [$response];
		}

		return $this->_send($response, $method);
	}

	private function _send(array $response, $method = NULL)
	{
		extract($response, EXTR_PREFIX_INVALID, 'res');

		if (isset($res_0)) {
			if (is_int($res_0)) {
				if ($res_0 == 301 && is_string($res_1)) {
					if (!headers_sent()) {
						if (strncmp($res_1, 'http', 4) !== 0) {
							$res_1 = 'http://'.$_SERVER['HTTP_HOST'] . '/' . ltrim($res_1, '/');
						}
						header("Location: $res_1", TRUE, 301);
						return 301;
					}
					$res_0 = 302;
				}
				if(is_string($res_1)){
					if ($res_0 == 302) { //302 && '302'
						Loader::redirect($res_1);
					} elseif ($res_0 >= 400) { // 404
						return $this->out($res_0, $res_1);
					} elseif ($res_0 == 200) { // 200
						return $this->tips($res_1);
					}
					// TODO: other status
				}
			} elseif (is_string($res_0)) {
				if (isset($res_1) && is_array($res_1)) {
					$context = $res_1;
					unset($res_1);
				}
				$template = $res_0;
				unset($res_0);
			} elseif (is_bool($res_0)) {
				$api_status = $res_0;
				if (isset($res_1)) {
					$data = $res_1;
				}
			}

		}

		if (isset($cookies)) {
			// TODO: setcookie
		}

		if (isset($location) && !$this->_is_api_call) { // redirect
			Loader::redirect($location);
			return 302;
		}

		if (!headers_sent()) {
			if (isset($content_type)) { // custom content type
				//header("Content-Type: $content_type");
				isset($charset) or $charset = 'UTF-8';
				header('Content-Type: '.$content_type.'; charset='.$charset);
			}
			if (isset($no_cached)) Loader::nocache();

			if (isset($headers)) {
				foreach($headers as $h) {
					if (is_string($h))
						header($h);
				}
			}
		}

		if ($this->_is_api_call) {
			if (strncmp($this->request->format, 'htm', 3) === 0) {
				return; //html output
			}
			if (!isset($data)) {
				$data = NULL;
			}

			if (!isset($api_status) && isset($status)) {
				$api_status = $status;
			}

			if (empty($data) && !isset($api_status)) {
				$api_status = FALSE;
			}

			isset($api_status) || $api_status = TRUE;

			if (!$this->_cached) {
				$this->jsonpWrapBegin();
			}

			if (!isset($meta) || !is_array($meta)) {
				$meta = [];
			}
			if (!isset($meta['ok'])) {
				$meta['ok'] = $api_status;
			}

			if (isset($error) && $error) {
				$meta['ok'] = $api_status = FALSE;
			}

			if (isset($id)) {
				$meta['id'] = $id;
			}

			if (isset($event)) {
				$meta['event'] = $event;
			}

			if (isset($retry)) {
				$meta['retry'] = $retry;
			}

			if (isset($context) && is_array($context) && isset($api_keys) && is_array($api_keys)) {
				foreach ($context as $key => $value) {
					if (in_array($key, $api_keys)) {
						$meta[$key] = $value;
					}
				}
			}

			if (isset($location) && !isset($meta['location'])) {
				$meta['location'] = $location;
			}

			echo $this->sendJson($meta, $data, isset($error) ? $error : NULL);

			if (!$this->_cached) {
				$this->jsonpWrapEnd();
			}
			return;
		}

		if (isset($content)) {
			echo $content;
			return;
		}

		if (isset($error) && $error && !isset($template)) {
			$template = 'error';

		}

		if (isset($template)) {
			if (!isset($lang)) {
				$lang = $this->lang;
			}

			if (!isset($context)) {
				$context = [];
			}
			if (isset($error) && !isset($context['error'])) {
				$context['error'] = $error;
			}

			$this->render($template . $this->view_ext, $context);
		}
	}

	/**
	 * @param $meta array
	 * @param $data array
	 * @param $error array
	 */
	protected function sendJson(array $meta, $data = NULL, $error = NULL)
	{
		$options = 0;
		if (defined('JSON_UNESCAPED_UNICODE') && defined('JSON_UNESCAPED_SLASHES')) {
			$options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		}

		if ($this->request->hasAccept('text/event-stream')) {
			if (isset($meta['event'])) {
				echo 'event: ', $meta['event'], PHP_EOL;
			}

			if (isset($meta['retry'])) {
				echo 'retry: ', $meta['retry'], PHP_EOL;
			}

			if (isset($meta['id'])) {
				echo 'id: ', $meta['id'], PHP_EOL;
			}

			echo 'data: ', json_encode($data, $options), PHP_EOL;

			echo PHP_EOL;

			flush();

			exit;
		}

		$result = [
			'meta' => $meta,
			'data' => $data
		];
		// if (is_bool($meta['ok'])) {
		// 	$result['status'] = $meta['ok'] ? 'ok' : 'fail'; // for compatibility
		// }

		if (isset($error)) {
			$result['error'] = $error;
		}

		// unset($meta['status'], $meta['meta'], $meta['data']);
		// $result = array_merge($result, $meta);

		return json_encode($result, $options);
		//exit;
	}

	protected function jsonpWrapBegin()
	{
		if($this->request->isJson() && !empty($this->_callback)) {
			echo $this->_callback . '(';
			$this->_wraped = TRUE;
		}
	}

	protected function jsonpWrapEnd()
	{
		if($this->request->isJson() && !empty($this->_callback) && $this->_wraped) {
			echo ');';
		}
	}

	public function render($name, array $context)
	{
		$context['request'] = $this->request;

		if ($this->account_call && is_callable($this->account_call)) {
			$context['current_user'] = call_user_func($this->account_call);
		}

		if (is_null($this->view_call)) {
			$view_class = defined('VIEW_CLASS') ? VIEW_CLASS : 'View_Simple';
			$this->view_call = function($dispatcher) use ($view_class) {
				return new $view_class($dispatcher->lang);
			};
		}

		$view = call_user_func($this->view_call, $this);
		echo $view->render($name, $context);
	}

	/**
	 * load Bo prefix (namespace supported)
	 * @deprecated
	 * @param string $cn controller name
	 */
	public static function loadBoNs($cn)
	{
		$bons = Loader::config('bons');
		if (isset($bons['controllers']) && isset($bons['controllers'][$cn])) {
			return $bons['controllers'][$cn];
		}
		return isset($bons['default']) ? $bons['default'] : NULL;
	}


	/**
	 * @deprecated
	 */
	public static function loadBoClass($ns, $class)
	{
		if (empty($ns)) {
			return 'Bo_' . ucfirst($class);
		}
		return $ns . '_' . ucfirst($class);
	}

	public function out($code, $message = null)
	{
		static $status = array(
			400 => '400 Bad Request',
			401 => '401 Unauthorized',
			402 => '402 Payment Required',
			403 => '403 Forbidden',
			404 => '404 Not Found',
			405 => '405 Method Not Allowed',
			406 => '406 Not Acceptable',
			501 => '501 Not Implemented',
		);
		$code = (int)$code;
		if (!isset($status[$code])) {
			Log::notice($message, __METHOD__. ' unprocessed code '.$code);
			$code = 404;
		}

		$msg = "dispatcher::out() -> status: $code, message: $message, request: $this->request";
		self::log($msg, $code);

		header("HTTP/1.1 ".$status[$code]);
		$_SERVER['HTTP_STATUS'] = $code;
		if ($this->request->isApiCall()) {
			$result = [
			'meta' => ['ok' => FALSE],
			'data' => NULL,
			'error' => ['code' => $code, 'message' => $message],
			];
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			return $code;
		}
		$context = ['status_code' => $code, 'status_label' => $status[$code], 'message' => $message];
		$this->render('error'.$this->view_ext, $context);

		return $code;
	}

	private static function log($msg, $code = 404)
	{
		$log_name = defined('LOG_NAME') ? strtolower(LOG_NAME) . '_' : '';
		$log_root = defined('LOG_ROOT') ? LOG_ROOT : '/tmp/';
		$log_file = $log_root . $log_name . 'dispatcher_'.$code.'.log';
		$line = date("Y-m-d H:i:s") . ' ' . $msg . "\n";
		@error_log($line, 3, $log_file);
	}

	/**
	 * 统一的消息提示页面
	 * usage: <code> return [200, 'message']; </code>
	 *
	 * @param string $message
	 * @return void
	 */
	public function tips($message)
	{
		$this->render('tips'.$this->view_ext,['message' => $message, 'referer' => $this->request->HTTP_REFERER]);
	}

	/**
	 * preheat: 预热，设置 ContentType 和 相关的头
	 *
	 * @param string $charset NULL UTF-8
	 * @return void
	 * @author liutao
	 **/
	public function preheat($charset = NULL)
	{
		if(is_null($charset)) {
			$charset = defined('RESPONSE_CHARSET') ? RESPONSE_CHARSET : 'UTF-8';
		}

		$ctype = $this->request->getContentType();

		if ($ctype) {
			header('Content-Type: '.$ctype.'; charset='.$charset);
			//defined('CONTENT_TYPE') || define('CONTENT_TYPE', $ctype );
		}
		unset($ctype);

		// CORS, 跨域 Ajax 调用支持
		$http_origin = $this->request->HTTP_ORIGIN;
		if (!empty($http_origin)) {
			$domain = Request::genCookieDomain();
			if ( !empty($domain) && ($pos = strrpos($http_origin, $domain)) !== FALSE ) {
				$origin = $http_origin;
			} else {
				$origin = 'http://www.'.L_DOMAIN; // ? $orgin = '*' is not work
			}

			$headers = [
				'Access-Control-Allow-Credentials' => 'true',
				'Access-Control-Allow-Origin' => $origin,
				'Access-Control-Allow-Headers' => 'X-Requested-With',
				'Access-Control-Max-Age' => '60'
			];
			foreach ($headers as $key => $value) {
				header($key . ': ' . $value);
			}
			unset($headers, $origin);
		}
		unset($http_origin);

		if ($this->request->REQUEST_METHOD == 'OPTIONS') {
			header('Allow: GET,HEAD,POST,OPTIONS');
			exit;
		}

	}

}

