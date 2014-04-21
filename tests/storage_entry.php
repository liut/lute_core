<?PHP



//define ('_PS_DEBUG', TRUE );
define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/init.php';

$entry = new Storage_Entry(__DIR__ . '/init.php');

print_r($entry);
