<?PHP
/**
 * 框架装载器
 * 用来初始化和封装基本操作
 *
 * @package	Lib
 * @author	 liut
 * @copyright  2007-2012 liut
 * @version	$Id$
 */

/** 简短的目录分隔符 */
defined('DS') || define('DS', DIRECTORY_SEPARATOR);


// 初始化加载环境
Loader::init();

/**
 * Loader
 *
 * 装载器
 */
final class Loader
{

	/**
	 * 类搜索路径
	 *
	 * @var array
	 */
	private static $_paths = array();

	/**
	 * 导入文件搜索路径
	 *
	 * 在使用 loadClass() 时，会通过 import() 指定的搜索路径查找类定义文件。
	 *
	 * 当 loadClass('Service_Products') 时，由于类名称映射出来的类定义文件已经包含了目录名
	 * （Service_Products 映射为 Service/Products.php）。
	 * 所以只能将 Service 子目录所在目录添加到搜索路径，而不是直接将 Service 目录添加到搜索路径。
	 *
	 * example:
	 * <code>
	 * // 假设要载入的文件完整路径为 /wroot/class/Service/Products.php
	 * Loader::import('/wroot/class');
	 * Loader::loadClass('Service_Products');
	 * </code>
	 *
	 * @param string $dir
	 * @param boolean $check_dir
	 */
	public static function import($dir, $check_dir = FALSE)
	{
		if ($check_dir && !is_dir($dir)) {
			Log::error('dir ' . $dir . ' not found', 'import error');
			return;
		}
		if (!in_array($dir, self::$_paths)) {
			array_unshift(self::$_paths, $dir);
		}
	}

	/**
	 * 载入指定类的定义文件，如果载入失败抛出异常
	 *
	 * example:
	 * <code>
	 * Loader::loadClass('Service_Products');
	 * </code>
	 *
	 * 在查找类定义文件时，类名称中的“_”会被替换为目录分隔符，
	 * 从而确定类名称和类定义文件的映射关系（例如： Service_Products 的定义文件为
	 * Service/Products.php）。
	 *
	 * loadClass() 会首先尝试从开发者指定的搜索路径中查找类的定义文件。
	 * 搜索路径可以用 Loader::import() 添加，或者通过 $dirs 参数提供。
	 *
	 * 如果没有指定 $dirs 参数和搜索路径，那么 loadClass() 会通过 PHP 的
	 * include_path 设置来查找文件。
	 *
	 * @param string $className 要载入的类名字
	 * @param string|array $dirs 可选的搜索路径
	 */
	public static function loadClass($className, $dirs = null)
	{
		if (class_exists($className, false) || interface_exists($className, false) || trait_exists($className, false)) {
			return $className;
		}

		if (null === $dirs) {
			$dirs = self::$_paths;
		} else {
			if (!is_array($dirs)) {
				$dirs = explode(PATH_SEPARATOR, $dirs);
			}
			$dirs = array_merge($dirs, self::$_paths);
		}

		$filename = strtr($className, '_\\', '//') . '.php';//str_replace('_', DIRECTORY_SEPARATOR, $className);
		/*if ($filename != $className) { 
			$dirname = dirname($filename);
			foreach ($dirs as $offset => $dir) {
				if ($dir == '.') {
					$dirs[$offset] = $dirname;
				} else {
					$dir = rtrim($dir, '\\/');
					$dirs[$offset] = $dir . DS . $dirname;
				}
			}
			$filename = basename($filename) . '.php';
		} else {
			$filename .= '.php';
		}*/

		self::_loadFile($filename, false, $dirs);

		if ( !(class_exists($className, false) || interface_exists($className, false) || trait_exists($className, false)) ) {
			//throw new Exception(sprintf("Class %s Not Found: %s", $className, $filename));	// 是否抛出异常有争议
			return false;
		}
		
		return $className;
	}

	/**
	 * 载入指定的文件
	 *
	 * $filename 参数必须是一个包含扩展名的完整文件名。
	 * loadFile() 会首先从 $dirs 参数指定的路径中查找文件，
	 * 找不到时再从 PHP 的 include_path 搜索路径中查找文件。
	 *
	 * $once 参数指示同一个文件是否只载入一次。
	 *
	 * example:
	 * <code>
	 * Loader::loadFile('Table/Products.php');
	 * </code>
	 *
	 * @param string $filename 要载入的文件名
	 * @param boolean $once 同一个文件是否只载入一次
	 * @param array $dirs 搜索目录
	 *
	 * @return mixed
	 */
	public static function loadFile($filename, $once = false, $dirs = null)
	{
		if (preg_match('/[^a-z0-9\-_.]/i', $filename)) {
			throw new Exception(sprintf('Security check: Illegal character in filename: %s.', $filename));
		}

		if (is_null($dirs)) {
			$dirs = self::$_paths;
		} elseif (is_string($dirs)) {
			$dirs = explode(PATH_SEPARATOR, $dirs);
		}

		return self::_loadFile($filename, $once, $dirs);
	}

	private static function _loadFile($filename, $once, array $dirs)
	{
		foreach ($dirs as $dir) {
			$path = rtrim($dir, '\\/') . DS . $filename;
			if (is_file($path)) {
				return $once ? include_once $path : include $path;
			}
		}

		// 在 include_path 中寻找文件
		foreach (explode(PATH_SEPARATOR, get_include_path()) as $dir) {
			if (!$dir) { // empty dir
				continue;
			}

			$path = rtrim($dir, '\\/') . DS . $filename;
			if (is_file($path)) {
				return $once ? include_once $path : include $path;
			}
		}
	}

	/**
	 * 检查指定文件是否可读
	 *
	 * 这个方法会在 PHP 的搜索路径中查找文件。
	 *
	 * 该方法来自 Zend Framework 中的 Zend_Loader::isReadable()。
	 *
	 * @param string $filename
	 *
	 * @return boolean
	 */
	public static function isReadable($filename)
	{
		return @is_readable($filename);
		if (!$fh = @fopen($filename, 'r', true)) {
			return false;
		}
		@fclose($fh);
		return true;
	}

	/**
	 * 准备运行环境
	 */
	public static function init()
	{
		if (version_compare(PHP_VERSION, '5.4.0', '<')) {
			throw new Exception("Error: PHP_VERSION < 5.4", 1);
		}
		static $_inited = false;
		// 避免重复调用 self::init()
		if ($_inited) { return true;}
		$_inited = true;

		// 将此文件所在位置加入搜索路径
		self::import(__DIR__);

		/**
		 * 自动加载对象
		 */
		spl_autoload_register(array('Loader', 'loadClass'));
		/**
		 * 设置异常处理例程
		 */
		set_exception_handler(array('Loader', 'printException'));
		
		return true;
	}

	/**
	 * 打印异常的详细信息
	 *
	 * @param Exception $ex
	 * @param boolean $return 为 true 时返回输出信息，而不是直接显示
	 */
	public static function printException(Exception $ex, $return = false)
	{
		$out = "exception '" . get_class($ex) . "'";
		if ($ex->getMessage() != '') {
			$out .= " with code " . $ex->getCode() . " message '" . $ex->getMessage() . "'";
		}

		$out .= ' in ' . self::safePath($ex->getFile()) . ':' . $ex->getLine() . "\n\n";
		// $out .= $ex->getTraceAsString();
		$trace = $ex->getTrace();
		$out .= self::formatTrace($trace);

		//Log::warning($out);

		if ($return) { return $out; }

		if (ini_get('html_errors')) {
			echo nl2br(htmlspecialchars($out));
		} else {
			echo $out;
		}

		return '';
	}

	/**
	 * 打印错误的详细信息
	 * 
	 * @param int $errno
	 * @param string $errstr
	 * @return void
	 */
	public static function printError($errno, $errstr, $errfile = null, $errline = null)
	{
		$error_reporting = error_reporting();
		if ($error_reporting == 0) {
			return;
		}
		
		if ($error_reporting & $errno) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		}
		$out = "error: $errno, $errstr" . PHP_EOL;
		//print_r(get_included_files());
		//echo "\n";
		$trace = debug_backtrace();
		//print_r($trace);
		$out .= '[BEGIN BACKTRACE]'.PHP_EOL;
		$out .= self::formatTrace($trace);
		$out .= '[END BACKTRACE]' . PHP_EOL;
		Log::warning($out);
		//echo $out;
	}

	public static function formatTrace($trace)
	{
		$out = '';
		foreach ($trace as $T) {
			!isset($T['file']) && $T['file'] = 'internal function';
			!isset($T['line']) && $T['line'] = '';
			//echo $T['function'], PHP_EOL;
			if($T['function'] != 'include' && $T['function'] != 'require' && $T['function'] != 'include_once' && $T['function'] != 'require_once' && $T['function'] != 'printError') {
				$out .= "\t" . '<'. self::safePath($T['file']) . ($T['line'] ? '> on line ' . $T['line'] : '');
				if(isset($T['class']))
					$out .= ' in method ' . $T['class'] . $T['type'];
				else
					$out .= ' in function ';
				$out .= $T['function'] . '(';
				if(isset($T['args']) && $T['args']) {
					// TODO: nice format args
					$out .= ' ' . self::_stupidArray($T['args']);
				}
				$out .= ")" . PHP_EOL;
			}
		}

		return $out;
	}

	private static function _stupidArray(array $arr)
	{
		$ret = [];
		foreach ($arr as $key => $value) {
			if (is_scalar($value)) {
				$ret[] = ''.$key.'=>'.(string)$value;
			}
			else {
				$ret[] = ''.$key.'=>'.gettype($value).(is_array($value)?'('.count($value).')':'');
			}
		}
		return '[' . implode(',', $ret) . ']';
	}

	/**
	 * 重定向浏览器到指定的 URL
	 *
	 * @param string $url 要重定向的 url
	 * @param int $delay 等待多少秒以后跳转
	 * @param bool $js 指示是否返回用于跳转的 JavaScript 代码
	 * @param bool $jsWrapped 指示返回 JavaScript 代码时是否使用 <script> 标签进行包装
	 * @param bool $return 指示是否返回生成的 JavaScript 代码
	 */
	public static function redirect($url, $delay = 0, $js = false, $jsWrapped = true, $return = false)
	{
		$delay = (int)$delay;
		if (!$js) {
			if (headers_sent() || $delay > 0) {
				echo <<<EOT
<html>
	<head>
	<meta http-equiv="refresh" content="{$delay};URL={$url}" />
	</head>
</html>
EOT;
				exit;
			} else {
				header("Location: {$url}");
				exit;
			}
		}

		$out = '';
		if ($jsWrapped) {
			$out .= '<script language="JavaScript" type="text/javascript">';
		}
		if ($delay > 0) {
			$out .= "window.setTimeout(function () { document.location='{$url}'; }, {$delay});";
		} else {
			$out .= "document.location='{$url}';";
		}
		if ($jsWrapped) {
			$out .= '</script>';
		}

		if ($return) {
			return $out;
		}

		echo $out;
		exit;
	}

	/**
	 * 输出错误信息并中止
	 * 
	 * @param string $msg
	 * @param int $code
	 * @param boolean $end
	 * @return void
	 */
	public static function out($code = 404, $msg = '', $end = true)
	{
		if ('cli' === PHP_SAPI) {
			echo 'code: ', $code, ', msg: ', print_r($msg, TRUE), PHP_EOL;
			if($end) exit();
			return;
		}
		static $status = array(
			400 => '400 Bad Request',
			401 => '401 Unauthorized',
			402 => '402 Payment Required',
			403 => '403 Forbidden',
			404 => '404 Not Found',
			405 => '405 Method Not Allowed',
			406 => '406 Not Acceptable',
		);
		$code = (int)$code;
		if (!isset($status[$code])) {
			$code = 404;
		}
		header("HTTP/1.1 ".$status[$code]);
		
		if (!empty($msg)) echo $msg;
		if($end) exit();
	}

	/**
	 * 加载配置
	 * 
	 * @param string $name
	 * @param array $inject
	 * @return mixed
	 */
	public static function & config($name, $inject = NULL, $dir = NULL)
	{
		static $settings = array();

		if(!isset($settings[$name])) {
			if (!preg_match("#^[a-z][a-z0-9_\.]{1,24}$#i", $name)) {
				throw new InvalidArgumentException('invalid config name');
			}

			if (is_null($dir) && defined('CONF_ROOT')) {
				$dir = CONF_ROOT;
			}

			$settings[$name] = self::_loadConfig($name, $dir);

			if (!isset($settings[$name]) && is_null($inject)) {
				throw new InvalidArgumentException('config file '.$name.'.* not found');
			}
			// TODO: Multi-Domain support

			if (is_array($inject) && $inject) { // TODO: here or move out of here, that's a question
				$settings[$name] = is_array($settings[$name]) ? array_merge($settings[$name], $inject) : $inject;
			}
		}

		return $settings[$name];
	}

	/**
	 * 加载配置, 按 name.conf.php -> name.ini -> name.yml 的顺序
	 * 
	 * @param string $name
	 * @param string $dir
	 * @return mixed array or NULL
	 */
	private static function _loadConfig($name, $dir)
	{
		$dir = is_null($dir) ? 'config' . DS : rtrim($dir, "\\/") . DS;

		$cfg = NULL;

		$name = strtr($name, '.', DS);

		$file = $name .'.conf.php';

		if (is_file($dir . $file)) {
			$cfg = include $dir . $file;
		}
		elseif (is_file($dir . $name . '.php')) {
			$cfg = include $dir . $name . '.php';
		}

		if (!is_array($cfg)) {
			$file = $dir . $name . '.ini';
			if (is_file($file)) {
				$cfg = parse_ini_file($file, TRUE);
			}
			if (is_null($cfg) || $cfg === FALSE) {
				$file = $dir . $name . '.yml';
				if (is_file($file) && function_exists('yaml_parse_file') ) {
					$cfg = yaml_parse_file($file);
				}
			}
		}

		if (empty($cfg)) {
			Log::info('config file '.self::safePath($dir).$name.'.* not found', __METHOD__);
		}

		return $cfg;
	}

	/**
	 * 设置 Http 无缓存输出
	 * 
	 * @return void
	 */
	public static function nocache()
	{
		if (!headers_sent()) {
			header('Expires: Fri, 02 Oct 98 20:00:00 GMT');
			header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
			header('Pragma: no-cache');
		}
	}

	/**
	 * Takes a value and checks if it is a Closure or not, if it is it
	 * will return the result of the closure, if not, it will simply return the
	 * value.
	 *
	 * @param   mixed  $var  The value to get
	 * @return  mixed
	 */
	public static function value($var)
	{
		return ($var instanceof Closure) ? $var() : $var;
	}

	public static function safePath($dir)
	{
		static $tr = [
			APP_ROOT => 'APP/',
			CONF_ROOT => 'CONF/',
			LIB_ROOT => 'LIB/',
			WEB_ROOT => 'WEB/',
		];

		return strtr($dir, $tr);
	}

}






