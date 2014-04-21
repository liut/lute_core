<?PHP


include_once __DIR__ . '/init.php';

isset($argv) || $argv = $_SERVER['argv'];
if(!isset($argv[1]) || empty($argv[1])) {
	echo "Usage: ", $argv[0], " text", PHP_EOL;
	return;
}

$text = $argv[1];
$_to = isset($argv[2]) ? $argv[2] : 'zh_CN';
$_from = isset($argv[3]) ? $argv[3] : 'auto';

$str = Util_GoogleTranslator::translate($text, $_from, $_to);

echo $text, PHP_EOL;
echo $str, PHP_EOL;
