<?PHP

// $Id$



//define ('_PS_DEBUG', TRUE );
//define ('_DB_DEBUG', TRUE );
define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/../init.php';


$dao = Da_Wrapper::dbo('ad.landing');

//print_r($dao);

$ret = $dao->select('goods', [], '*', [
	'order_by' => ['id'=> 'DESC', 'status' => 'ASC'],
	'fetch' => 'all',
	'limit' => 5
]);

print_r($ret);
