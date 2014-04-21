<?PHP

// $Id$
$_debug = (defined('_PS_DEBUG') && TRUE === _PS_DEBUG);
return array(
	// default nodes
	'memcache' => array(
		'className' => 'Cache_Memcache',
		'option' => array(
			'debug' => $_debug
		)
	),
	'memcached' => array(
		'className' => 'Cache_Memcached',
		'option' => array(
			'debug' => $_debug
		),
		'servers' => array(
			array('mc.wp.net', 11211, 10)
		)
	),
	'default' => 'memcache',
	'lute' => array(
		'className' => 'Cache_Lute',
		'option' => array(
			'cacheDir' => CACHE_ROOT,
			'lifeTime' => 300,
			'debug' => $_debug
		),
	),
	'file' => 'lute',
	
	// apps nodes
	'item' => array(
		'className' => 'Cache_Lute',
		'option' => array(
			'cacheDir' => CACHE_ROOT . 'page/',
			'lifeTime' => $_debug ? 5 : 60 * 55,
			'group' => 'item_sto',
			'idPattern' => "#/?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2,36})(_images|)/?#",
			'idReplace' => create_function('$match', 'return $match[1]."/".$match[2]."/".$match[3].$match[4];'),// "'\\1/\\2/\\3\\4'", // "'\\1/\\2/\\3'.str_replace('/','_','\\4')"
			'fileSuffix' => '.htm',
			'debug' => $_debug
		),
	),
	'cate' => array(
		'className' => 'Cache_Lute',
		'option' => array(
			'cacheDir' => CACHE_ROOT . 'page/',
			'lifeTime' => $_debug ? 5 : 60 * 60 * 2,
			'group' => 'cate_sto',
			'idPattern' => "#/?(0[1-5][0-9])(0[0-5][0-9]\d{3})([a-z0-9_\-]+|)(\.html?|)$#",
			'idReplace' => create_function('$match', 'return $match[1]."/".$match[1].$match[2].$match[3];'),//"'\\1/\\1\\2\\3'",
			'fileSuffix' => '.html',
			'debug' => $_debug
		),
	),
	'brand' => array(
		'className' => 'Cache_Lute',
		'option' => array(
			'cacheDir' => CACHE_ROOT . 'page/',
			'lifeTime' => $_debug ? 5 : 60 * 60 * 2,
			'group' => 'brand_sto',
			'idPattern' => "#/?([A-Z][A-Z0-9]\d{2,6})([a-z0-9_\-/]+|)(\.html?|)?$#",
			'idReplace' => create_function('$match', 'return $match[1].str_replace("/","_", $match[2]);'),//"'\\1\\2'",
			'fileSuffix' => '.html',
			'debug' => $_debug
		),
	),
	'channel' => array(
		'className' => 'Cache_Lute',
		'option' => array(
			'cacheDir' => CACHE_ROOT . 'page/',
			'lifeTime' => $_debug ? 5 : 60 * 60 * 2,
			'group' => 'home_sto',
			'fileSuffix' => '.htm',
			'debug' => $_debug
		),
	),
	
	'api' => array(
		'className' => 'Cache_Lute',
		'option' => array(
			'cacheDir' => CACHE_ROOT . 'api/',
			'lifeTime' => $_debug ? 5 : 60 * 60 * 2,
			//'group' => 'data',
			//'fileSuffix' => '.htm',
			'debug' => $_debug
		),
	),

);
