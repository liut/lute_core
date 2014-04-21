<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * 缓存操作类
 *
 * @author	  liut
 * @package	 Core
 * @version	 $Id$
 * @lastupdate $Date$
 */


/**
 * Cache
 *
 *
 *
 * Example:
 * 	required: CONF_ROOT . cache.conf.php:
 *
 * 	$cache = Cache::farm('section_name'); // section_name defined in config
 * 	$key = 'key1';
 * 	$data = 'abc';
 * 	$ret = $cache->set($key, $data, 60);	// set
 * 	$c_data = $cache->get($key);		// get
 */
abstract class Cache
{

	/**
	 * 加载配置信息
	 *
	 * @param string $section
	 * @return array
	 */
	public static function config($section)
	{
		static $_settings = null;
		if($_settings === null) $_settings = Loader::config('cache');
		if(!isset($_settings[$section])) {
			return null;
		}

		return $_settings[$section];
	}

	/**
	 * 工厂加载方法
	 *
	 * @param string $section
	 * @return object
	 * @deprecated by ::farm
	 */
	public static function factory($section = 'default')
	{
		return static::farm($section);
	}

	/**
	 * 工厂加载方法: 按指定配置名称产生一个实例
	 * @param string $section
	 * @return object
	 */
	public static function farm($section = 'default')
	{
		static $instances = array();
		if ($section === 'all_instances') {
			return $instances;
		}
		$config = static::config($section);
		if (is_string($config)) {
			$section = $config;
			$config = static::config($section);
		}
		if (!is_array($config)) {
			throw new InvalidArgumentException('cache node ['. $section . '] not found', 101);
		}
		if(!isset($instances[$section]) || $instances[$section] === null) {
			if (isset($config['className'])) $class = $config['className'];
			else {
				$class = 'Cache_' . ucfirst($section);
			}
			$cob = new $class();
			if (method_exists($cob, 'init')) {
				$option = isset($config['option']) ? $config['option'] : array();
				$cob->init($option);
			}
			if (isset($config['servers']) && method_exists($cob, 'addServers')) {
				$cob->addServers($config['servers']);
			}

			$instances[$section] = $cob;
		}
		return $instances[$section];
	}

	/**
	 * constructor
	 *
	 * @return object
	 */
	protected function __construct()
	{
	}

	protected static $availableOptions = array('lifeTime', 'debug', 'group', 'args_min', 'args_max', 'no_index');
	/** vars */
	protected $_inited 	= FALSE;
	/** vars */
	protected $_lifeTime = 300; //默认的过期时间
	/** vars */
	protected $_group = '';
	// for debug
	protected $_debug	 = FALSE;
	/**
	 * 允许 controller 中的参数传入
	 */
	protected $_args_min = 0;
	protected $_args_max = 0;

	protected $_no_index = FALSE;


	/**
	 * 按 Key 取缓存条目
	 *
	 * @param string $key
	 * @return mixed
	 */
	abstract public function get($key);

	/**
	 * 存缓存数据
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $expire
	 * @return void
	 */
	abstract public function set($key, $value, $expire);

	/**
	 * 按 Key 删除缓存条目
	 *
	 * @param string $key
	 * @return void
	 */
	abstract public function delete($key, $expire = NULL);

	/**
	 * 取出多个条目
	 *
	 * @param array $keys
	 * @param array $keys
	 * @return array
	 * @author liut
	 **/
	public function getMulti(array $keys, array &$cas_tokens = NULL, $flags = NULL)
	{
		throw new BadMethodCallException('unimplemented method');
	}

	/**
	 * 存储多个条目
	 *
	 * @param array $items
	 * @param int $expire
	 * @return void
	 * @author liut
	 **/
	public function setMulti(array $items, $expire)
	{
		throw new BadMethodCallException('unimplemented method');
	}

	public function getLifeTime()
	{
		return $this->_lifeTime;
	}

	/**
	 * init
	 *
	 *
	 */
	public function init($params = NULL)
	{
		if ($this->_inited) return $this->_inited;
		$this->_inited = TRUE;

		if ( is_array($params) ) foreach($params as $key => $value) {
			$this->setOption($key, $value);
		}

	}

	public function getOption($name)
	{
		$_name = '_'.$name;
		if (in_array($name, static::$availableOptions) && property_exists($this, $_name)) {
			return $this->$_name;
		}

		return NULL;
	}

	/**
	 * 设置选项
	 *
	 *
	 */
	public function setOption($name, $value)
	{
		$_name = '_'.$name;
		if (in_array($name, static::$availableOptions) && property_exists($this, $_name)) {
			$this->$_name = $value;
		}
	}

	protected $_logs	  = NULL;
	protected function log($message)
	{
		if (!$this->_debug) return;
		if (is_null($this->_logs))
		{
			$this->_logs = array();
		}
		if (defined('_PS_DEBUG') && TRUE === _PS_DEBUG) {
			Log::info($message, 'Cache log:');
		}
		$this->_logs[] = $message;
	}

	public function getLogs()
	{
		return $this->_logs;
	}

	protected $_curr_key = null;

	/**
	 * 开始一个页面输出缓存
	 *
	 * @param string $key
	 * @return void
	 * @access public
	 */
	public function start($key, $life = NULL)
	{
		$this->_curr_key = $key;
		is_null($life) || $this->_lifeTime = $life;
		$data = $this->get($key);
		if ($data !== false) {
			echo($data);
			return true;
		}
		ob_start();
		ob_implicit_flush(false);
		return false;
	}

	/**
	 * 结束并保存输出到缓存
	 *
	 * @access public
	 */
	public function end($abort = false)
	{
		$data = ob_get_contents();
		ob_end_clean();
		$abort || $this->set($this->_curr_key, $data, $this->_lifeTime);
		echo($data);
	}

	/**
	 * 执行一个带返回数据的方法调用
	 * @param string $key
	 * @param int $lifeTime
	 * @param callable $callback
	 * @param array $params
	 * @return mixed
	 */
	public function invoke($key, $lifeTime, callable $callback , array $params = [] )
	{
		$lifeTime < 0 && $lifeTime = 0;

		if (0 == $lifeTime) {
			return call_user_func_array($callback, $params);
		}

		$result = $this->get($key);

		if ($result === FALSE) {
			$result = call_user_func_array($callback, $params);
			if (is_null($result) || $result === FALSE /*|| $result instanceof Model && !$result->isValid()*/) {
				return $result;
			}
			$this->set($key, $result, $lifeTime);
		}

		return $result;
	}

	public function __invoke($key, $lifeTime, callable $callback , array $params = [])
	{
		return $this->invoke($key, $lifeTime, $callback, $params);
	}

	/**
	 * 将  URI 中的 controller action 和参数合并成一个完整的用于缓存的 ID, 以用于缓存使用
	 * see docs: architecture.md: uri format: /controller/action/arg1/arg2/
	 * see also: cache.conf.php
	 */
	public function makeId($key, $args = [], $glue = '/')
	{
		if ($this->_args_max < $this->_args_min) {
			$this->_args_max = $this->_args_min;
		}

		if ($this->_args_max == 0 || empty($args) || empty($args[0])) {
			return $key;
		}

		if (count($args) > $this->_args_max) {
			$args = array_slice($args, 0, $this->_args_max);
		}

		return array_reduce($args, function($id, $arg) use($glue) {
			if (empty($arg)) {
				return $id;
			}
			$id .= $glue . $arg;
			return $id;
		}, $key);
	}

}




