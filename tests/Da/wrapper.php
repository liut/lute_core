<?PHP

// $Id$



define ('_PS_DEBUG', TRUE );
define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/../init.php';

define('DB_NS', 'ad.liutao');



$data = array(
	'name' => 'test' . time()
);
/*
$ret = Da_Wrapper::insert()
	->table('ad.liutao.test')
	->data($data)
	->execute();
var_dump($ret);


$ret = Da_Wrapper::update()
	->table('ad.liutao.test')
	->data($data)
	->where('id', '=', 8)
	->execute();
var_dump($ret);
*/
/*
$ret = Da_Wrapper::delete()
	->table('ad.liutao.test')
	->where('id', '=', 7)
	->execute();
var_dump($ret);
*/


$limit = 3;
$offset = 0;
$total = 0;
/*$data = Da_Wrapper::select()
	->table('ad.liutao.test')
	->columns('id','name','updated')
	->orderby('id DESC')
	->getPage($limit, $offset, $total);
print_r($data);
echo 'total: ', $total, PHP_EOL;*/

echo 'getRow: ', print_r(Da_Wrapper::getRow(DB_NS, 'SELECT id, name, updated FROM test ORDER BY id DESC limit 1'), TRUE), PHP_EOL;

echo 'getOne: ', Da_Wrapper::getOne(DB_NS, 'SELECT name FROM test WHERE id > ? ORDER BY id DESC LIMIT 1', array(3)), PHP_EOL;

echo 'getAll: ', print_r(Da_Wrapper::getAll(DB_NS, 'SELECT id, name, updated FROM test ORDER BY id DESC limit 3'), TRUE), PHP_EOL;

echo 'getFlat: ', print_r(Da_Wrapper::getFlat(DB_NS, 'SELECT name FROM test ORDER BY id DESC limit 3'), TRUE), PHP_EOL;


/*
foreach(Da_Wrapper::getDbos() as $key => $dbo) {
	echo 'key: ', $key, "\n";
	if(is_object($dbo)) {
		echo 'error: ', $dbo->errorCode(), "\n";
		print_r($dbo->errorInfo());
	}
}
*/