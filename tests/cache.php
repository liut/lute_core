<?php

define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/init.php';


$cache = Cache::farm('memcached');

function test_data() {
	return [
		'id' => mt_rand(1,9),
		'stamp' => time()
	];
}

$key = 'test_data';

$life = 9;

$data = $cache->invoke($key, $life, 'test_data');

var_dump($data);
