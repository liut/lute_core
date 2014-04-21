<?PHP
/** $Id$ */

if(isset($g_debug_footer_loaded)) return;
$g_debug_footer_loaded = true;

if(!defined('_PS_DEBUG') || TRUE !== _PS_DEBUG) {
	return;
}

$is_http = isset($_SERVER['HTTP_HOST']);

if(!$is_http && !defined('_CLI_DEBUG')) {
	return;
}

$request = Request::current();

if ($request->EXT == 'txt' || $request->EXT == 'json') {
	return;
}

if(!defined('_PS_DEBUG_FORCE_OUPUT')
	&& (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) || isset($_SERVER['HTTP_ORIGIN']) && !empty($_SERVER['HTTP_ORIGIN']))
	&& (!isset($_SERVER['HTTP_STATUS']) || $_SERVER['HTTP_STATUS'] != 404)) {
	return;
}

if ($is_http) {
	$headers = headers_list();
	$is_xhtml = in_array('Content-Type: application/xhtml+xml; charset=utf-8', $headers);
} else {
	$is_xhtml = FALSE;
}

if($is_http && !$is_xhtml) echo "<div id=\"_bt_info\" style=\"text-align: justify; clear: both; margin: 5px; font: .7em Verdana, Arial, sans-serif; color: #999;\">\n";

if (isset($XHPROF_ON) && TRUE === $XHPROF_ON) {

	echo $is_http ? ($is_xhtml ? "<!--\n" : "---------------<br />\n") : "\n---------------\n", // <!--
	     "Assuming you have set up the http based UI for \n",
	     "XHProf at some address, you can view run at \n",
	     "<a href=\"http://xhprof.".L_DOMAIN."/?run=$run_id&source=$XHPROF_APP\">$run_id</a>\n",
	     $is_http ? ($is_xhtml ? "\n-->\n" : "<br />---------------\n") : "---------------\n"; // -->

}

echo "<!-- included:\n", print_r(get_included_files(), true), "\n-->\n";

if(class_exists('Cache', FALSE)) {
	echo "<!-- cache:\n", htmlspecialchars(print_r(Cache::farm('all_instances'), true)), "\n-->\n";
}

if (class_exists('Da_Wrapper', FALSE)) {
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

if(class_exists('Log', FALSE)) {
	echo "<!-- logs:\n", print_r(Log::getLogs(), true), "\n-->\n";
}

if (class_exists('Lang', FALSE)) {
	echo "<!-- Lang::loaded:\n", print_r(Lang::loaded(), TRUE), "\n-->\n";
}

if($is_http && !$is_xhtml) echo "</div>";
return;
$constants = get_defined_constants(true);
if (isset($constants['user'])) {
	$defines = $constants['user'];
	echo "<!-- defines:\n", print_r($defines, true), "\n-->\n";
	return;
	$dumpfile = tempnam('/tmp','hidef.');
	$fp = fopen($dumpfile, 'w');
	fwrite($fp, serialize($defines));
	fclose($fp);
	error_log("written constants to $dumpfile");
}



