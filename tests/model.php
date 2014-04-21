<?PHP



//define ('_PS_DEBUG', TRUE );
define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/init.php';

/**
* Model_Test
* create table test(id serial, name varchar(20), status smallint, updated timestamp default current_timestamp, primary key(id)) without oids;
*/
class Model_Test extends Model
{
    protected static $_db_name = 'bc.postgres';
    protected static $_table_name = 'test';
    protected static $_primary_key = 'id';
	
	protected static $_editables = array('name','status','updated');
	
}

// load

$id = 32;
echo 'load object by pk: ', $id, PHP_EOL;
$mt = Model_Test::load($id);
if ($mt->isValid()) {
	echo 'id: ', $mt->id, ', name: ', $mt->name, ', updated: ', $mt->updated, PHP_EOL;
}
else {
	echo 'object is invalid', PHP_EOL;
}

// load by option

$mt = Model_Test::load($id, array('cachable' => 5*60)); // 允许5分钟缓存

echo 'load by pk: ', ($mt->isValid() ? 'valid' : 'invalid'), ', ', print_r($mt, TRUE), PHP_EOL;

// load by special pk

$mt = Model_Test::load('test01', array('pk' => 'name', 'cachable' => 50));

echo 'load by special: ', ($mt->isValid() ? 'valid' : 'invalid'), ', ', print_r($mt, TRUE), PHP_EOL;

// load fold
/*$data = Model_Test::findFold([
	'where' => ['id' => ['IN', 2, 3, 4, 5, 6, 7], 'status' => 1],
	'fold_key' => 'id',
	'limit' => 5
]);

echo 'find fold: ';*/
$data = Model_Test::findByPk([2,3,4,5,6,7]);
echo 'find by pk: ';
print_r($data);

// load paging

$total = -1; // 设置成 -1 会在分页加载时修改
$option = array(
	'where' => array(
		'status' => 1
	),
	'order_by' => 'updated DESC'
);

$limit = 5;
$offset = 0;
$data = Model_Test::findPage($option, $limit, $offset, $total);
echo 'page total records: ', $total, PHP_EOL;

echo 'page data: ', print_r($data, TRUE);

// count

$count = Model_Test::count();
echo 'count: ', $count, PHP_EOL;

//return;

// create new record

$mt = Model_Test::farm(array(
	'name' => 'test'.time(),
	'status' => 1
));

$new_id = $mt->save();

echo 'new id: ', $new_id, PHP_EOL;

echo 'new test object: ', print_r($mt, TRUE), PHP_EOL;

// update
$mt = Model_Test::load($new_id);
$mt->name = 'updated'.time();

$ret = $mt->save();

echo 'update ', $new_id, ' ', $ret, PHP_EOL;


