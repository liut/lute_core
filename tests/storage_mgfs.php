<?PHP



//define ('_PS_DEBUG', TRUE );
define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/init.php';

$config = [
	'images' => array(
		'type' => 'mgfs',
		'url_prefix' => SP_URL_STO,
		'return_id' => true,
		'db_ns' => 'mongo.storage', // 数据库节点配置名称
		'db_prefix' => 'img', 		// 存储图片的相关Collections前辍
		'max_size' => 1024 * 200, 	// 最大文件尺寸
		'max_width' => 800,
		'max_heigth' => 800,
		'max_quality' => 88, // 图片最大压缩率
		'strip_image' => true,
		'allowed_imagetypes' => array(IMAGETYPE_GIF,IMAGETYPE_JPEG,IMAGETYPE_PNG),
		'allowed_types' => array('image/gif', 'image/pjpeg', 'image/jpeg', 'image/png'),
		'allowed_extensions' => array('gif', 'jpg', 'png'),
	),
];

Loader::config('storage', $config);

$sto = Storage::farm('images');

print_r($sto);
