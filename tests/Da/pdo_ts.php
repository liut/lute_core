<?PHP

// $Id$



//define ('_PS_DEBUG', TRUE );
//define ('_DB_DEBUG', TRUE );
define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/../init.php';


$str = 'lace blue';

echo Da_PDO::toTsQuery($str), PHP_EOL;
