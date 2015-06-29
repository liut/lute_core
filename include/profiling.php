<?PHP

if (!defined('APP_ROOT')) {
	die('BAD REQUEST');
}

if (!isset($XHPROF_RAND_MAX) || !is_int($XHPROF_RAND_MAX)) {
	$XHPROF_RAND_MAX = 1000;
}
elseif ($XHPROF_RAND_MAX < 100) {
	$XHPROF_RAND_MAX = 100;
}

$XHPROF_ON = FALSE;
if ((defined('_PS_DEBUG') || mt_rand(1, $XHPROF_RAND_MAX) === 1) && extension_loaded('xhprof')) { // start profiling
	$XHPROF_ON = true;
	$XHPROF_APP = x_get_appname() . '_' . date('oW'); //.His
	$XHPROF_LIB_DIR = __DIR__ . '/xhprof_lib';
	#include_once $XHPROF_LIB_DIR . "/utils/xhprof_lib.php";
	#include_once $XHPROF_LIB_DIR . "/utils/xhprof_runs.php";
	xhprof_enable(XHPROF_FLAGS_NO_BUILTINS + XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY, array('ignored_functions' =>  array('call_user_func',
		'call_user_func_array')));
}

register_shutdown_function('_prof_shutdown');

/**
 * called by shutdown
 *
 * @return void
 **/
function _prof_shutdown()
{
	$XHPROF_ON = isset($GLOBALS['XHPROF_ON']) ? $GLOBALS['XHPROF_ON'] : FALSE;
	if (TRUE === $XHPROF_ON) {

		$XHPROF_APP = $GLOBALS['XHPROF_APP'] ?: 'testing';
		$XHPROF_LIB_DIR = $GLOBALS['XHPROF_LIB_DIR'] ?: __DIR__ . '/xhprof_lib';

		// stop profiler
		$xhprof_data = xhprof_disable();

		// display raw xhprof data for the profiler run
		//print_r($xhprof_data);
		//echo "<!-- xhprof_data:\n", print_r($xhprof_data, true), "\n-->\n";

		include_once $XHPROF_LIB_DIR . "/utils/xhprof_lib.php";
		include_once $XHPROF_LIB_DIR . "/utils/xhprof_runs.php";

		$dir = ini_get("xhprof.output_dir");
		if (empty($dir)) {
			$dir = LOG_ROOT . 'xhprof';
		}

		if (!is_dir($dir)) {
			mkdir($dir);
		}

		// save raw data for this profiler run using default
		// implementation of iXHProfRuns.
		$xhprof_runs = new XHProfRuns_Default($dir);

		// save the run under a namespace "$XHPROF_APP"
		$run_id = $xhprof_runs->save_run($xhprof_data, $XHPROF_APP);
	}

	if (defined('_PS_DEBUG')) {
		include __DIR__ . '/debug_footer.php';
	}
}

function x_get_appname()
{
	if (isset($_SERVER['SERVER_NAME'])) {
		return $_SERVER['SERVER_NAME'];
	}
	return 'cli_'.x_get_login();
}

function x_get_login()
{
	if (function_exists('posix_getlogin')) {
		return posix_getlogin();
	}

	if (isset($_SERVER['LOGNAME'])) {
		return $_SERVER['LOGNAME'];
	}

	if (isset($_SERVER['USER'])) {
		return $_SERVER['USER'];
	}

	return '';
}
