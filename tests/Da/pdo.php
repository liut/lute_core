<?PHP

// $Id$



//define ('_PS_DEBUG', TRUE );
//define ('_DB_DEBUG', TRUE );
define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/../init.php';

/**
 * -- MySQL
 * CREATE TABLE IF NOT EXISTS `test` (
 *   `id` int(11) NOT NULL AUTO_INCREMENT,
 *   `name` char(255) NOT NULL,
 *   `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   PRIMARY KEY (`id`),
 *   KEY `name` (`name`)
 * ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
 */

$data = array(
	'name' => 'test' . time()
);

$dbo = Da_Wrapper::dbo('ad.liutao');

$result = $dbo->select('test', array(
	//'id' => ['BETWEEN', 8, 20]
	//'id' => ['IN', 8, 9, 10, 17]
	'id' => ['>=', 8]
), 'id,name,updated', array(
	'fetch' => 'all',
	'order_by' => 'updated DESC',
	'limit' => 5
));

print_r($result);return;

$ret = $dbo->insert('test', $data);

echo 'insert ', $ret, PHP_EOL;

if ($ret) {
	$id = $dbo->lastInsertId();
	echo 'new id: ', $id, PHP_EOL;
	
	$new_data = array('name' => 'new_test_' . time());
	$ret = $dbo->update('test', $new_data, array('id'=>$id));
	
	echo 'update ', $ret, PHP_EOL;
	
	$ret = $dbo->delete('test', array('id'=>$id));
	
	echo 'delete ', $ret, PHP_EOL;
} else {
	echo 'insert error', PHP_EOL;
}


