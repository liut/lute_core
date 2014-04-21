<?PHP


return array(
	'ad' => array(	// 测试
		'liutao' => array(
			'dsn' => 'mysql:host=localhost;dbname=liutao',
			'username' => 'liutao',
			'password' => 'christ'
		)
		,
		'landing' => [
			'type' => 'mongo',
			'servers' => 'mongodb://catalog.db.wp.net',
			'options' => array('timeout' => 3 * 1000, 'replicaSet' => 'wp'),
			'db' => 'catalog'
		]
	),
	'bc' => array(	// 测试
		'postgres' => array(
			'dsn' => 'pgsql:host=localhost;dbname=postgres',
			'username' => 'postgres',
			'password' => 'christ'
		)
	)

);
?>