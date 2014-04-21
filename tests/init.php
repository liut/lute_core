<?PHP

/**
 * 脚本初始化
 *
 * @version        1.0
 * @since           12:54 2009-02-09
 * @author          liut
 * @words           Init
 * @Revised Information
 * $Id$
 * 
 */
//

defined('DS') || define('DS', DIRECTORY_SEPARATOR);

define('CONF_ROOT', __DIR__ . DS );

if (!defined('APP_ROOT')):
define('APP_ROOT', __DIR__ . DS);
define('WEB_ROOT', __DIR__ . DS);
define('LIB_ROOT', dirname(APP_ROOT) . DS);
define('LOG_ROOT', APP_ROOT . '../logs' . DS);
define('SKIN_ROOT', APP_ROOT . 'templates' . DS);

// writable paths
define('CACHE_ROOT', APP_ROOT.'cache/' );	//
define('DATA_ROOT', APP_ROOT.'data/' );	//
define('TEMP_ROOT', APP_ROOT.'temp/' );	//


if('WINNT' == PHP_OS || 'Darwin' == PHP_OS) // 为 windows & macosx 下调试用，仅 beta 和 开发 环境
{
	defined('LOG_LEVEL') || define('LOG_LEVEL', 7 ); // 3=err,4=warn,5=notice,6=info,7=debug

	defined('_PS_DEBUG') || define('_PS_DEBUG', TRUE );	// DEBUG , beta only
	defined('_DB_DEBUG') || define('_DB_DEBUG', TRUE );	// DEBUG , beta only

}
else
{
	defined('LOG_LEVEL') || define('LOG_LEVEL', 4 ); // 3=err,4=warn,5=notice,6=info,7=debug
	
}

endif;

// Global Loader
include_once LIB_ROOT . 'class'.DS.'Loader.php';

if (PHP_SAPI === 'cli') { // command line
	isset($argc) || $argc = $_SERVER['argc'];
	isset($argv) || $argv = $_SERVER['argv'];
}
else { // http mod, cgi, cgi-fcgi
	die('please run test in console'.PHP_EOL);
}


register_shutdown_function('_shutdown');

/**
 * called by shutdown 
 *
 * @return void
 **/
function _shutdown()
{
	$is_http = isset($_SERVER['HTTP_HOST']);
	
	if(!defined('_PS_DEBUG') || TRUE !== _PS_DEBUG) {
		return;
	}

	if(!$is_http && !defined('_CLI_DEBUG')) {
		return;
	}

	if(!defined('_PS_DEBUG_FORCE_OUPUT') 
		&& isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) 
		&& (!isset($_SERVER['HTTP_STATUS']) || $_SERVER['HTTP_STATUS'] == 404)) {
		return;
	}
	
	if ($is_http) {
		$headers = headers_list();
		//var_dump($headers);
		$is_xhtml = in_array('Content-Type: application/xhtml+xml; charset=utf-8', $headers);
	} else {
		$is_xhtml = FALSE;
	}
	
	if($is_http && !$is_xhtml) echo "<div id=\"_bt_info\" style=\"text-align: justify; clear: both; margin: 5px; font: .7em Verdana, Arial, sans-serif; color: #999;\">\n";

	echo "<!-- included:\n", print_r(get_included_files(), true), "\n-->\n";

	if(class_exists('Cache', false)) {
		echo "<!-- cache:\n", htmlspecialchars(print_r(Cache::farm('all_instances'), true)), "\n-->\n";
	}

	if (class_exists('Da_Wrapper', false)) {
		echo "<!-- Da_Wrapper::dbos:\n";
		foreach(Da_Wrapper::getDbos() as $key => $dbo)
		{
			echo $key, ":\n";
			echo "\t", get_class($dbo), "::errorInfo:\n";
			print_r($dbo->errorInfo());
			if (method_exists($dbo, 'getLogs')) {
				echo 'logs:', PHP_EOL;
				print_r($dbo->getLogs());
			}

		}
		if (method_exists('Da_Wrapper', 'getLogs')) {
			echo 'Da_Wrapper::logs: ', PHP_EOL;
			print_r(Da_Wrapper::getLogs());
		}

		echo "\n-->\n";
	}

	if(class_exists('Log', false)) {
		echo "<!-- logs:\n", print_r(Log::getLogs(), true), "\n-->\n";
	}

	//var_dump(xdebug_get_declared_vars());
	if($is_http && !$is_xhtml) echo "</div>";
}


