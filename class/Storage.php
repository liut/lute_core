<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Storage
 * 
 * 文件存储的约束：
 * 1. 所有文件按hash（md5+size）作为ID，参见 ::makeId() 方法
 * 2. 不允许有内容完全一样的文件, ID 和 path 一致对应
 * 3. 所有的存储对象在读取时使用统一的规则，即 path 访问; path 的正则大致是 ([a-z0-9]{2})/([a-z0-9]{2})/([a-z0-9]{19,36})\.(jpg|png)
 * 
 *
 * @package	Sp
 * @author	 liut
 * @version	$Id$
 */



/**
 * Storage
 * 文件存储工厂类和基类
 *
 * 文件条目 Meta 结构: {id:,path:,filename:,size:,created:,hash:}
 * 存储时依赖 Storage_Entry 类
 *
 */
abstract class Storage
{
	protected $_ini;

	// const
	const DFT_MAX_QUALITY = 80;

	/**
	 * forbid construct
	 *
	 */
	protected function __construct($option)
	{
		$this->_ini = $option;
	}

	/**
	 * 加载配置节点
	 *
	 * @param string $section
	 * @return array
	 */
	public static function config($section)
	{
		static $_settings = null;
		if($_settings === null) $_settings = Loader::config('storage');
		if(!isset($_settings[$section])) {
			throw new Exception('storage node ['. $section . '] not found');
			//return null;
		}
		return $_settings[$section];
	}

	/**
	 * 工厂加载方法加载子对象
	 *
	 * @param string $section
	 * @return mixed
	 */
	public static function farm($section)
	{
		static $instances = array();
		if(!isset($instances[$section])) {
			$opt = self::config($section);
			if (isset($opt['class'])) {
				$class = $opt['class'];
				unset($opt['class']);
			} else {
				$type = $opt['type'];
				$class = __CLASS__ . '_' . ucfirst($type);
				unset($opt['type']);
			}

			$obj = new $class($opt);

			if (!($obj instanceof Storage)) {
				throw new Exception('class: '.$class.' must inherited Storage');
			}

			foreach (['db_ns', 'db_prefix'] as $v) {
				$_v = '_'.$v;
				if (isset($opt[$v]) && $opt[$v]) {
					$obj->$_v = $opt[$v];
				}
			}

			$instances[$section] = $obj;
		}
		return $instances[$section];
	}

	public static function hashFile($file, $size = 0)
	{
		if (!is_file($file)) {
			throw new Exception("Read file error: " . $file, 1);
		}

		$md5 = md5_file($file);
		$size > 0 || $size = filesize($file);

		return sprintf("%s%4x", $md5, $size);
	}

	public static function hashContent($value, $size = 0)
	{
		$md5 = md5($value);
		$size > 0 || $size = strlen($value);

		return sprintf("%s%04x", $md5, $size);
	}

	/**
	 * 按 hash 值计算出 ID
	 * 
	 * @param string $hashed
	 * @return string
	 */
	public static function makeId($hashed)
	{
		return static::stringBaseConvert($hashed, 16, 36);
	}

	/**
	 * 
	 * @param string $id
	 * @param string $ext
	 * @return string
	 */
	public static function makePath($id, $type = NULL)
	{
		if (empty($id) or strlen($id) < 5) {
			throw new InvalidArgumentException("id: '$id' is empty or too short.");
		}

		$name = substr($id,0,2)."/".substr($id,2,2)."/".substr($id,4);
		if (empty($type)) {
			return $name;
		}

		if (is_int($type)) {
			$ext = static::imageTypeToExtension($type, true);
		} else {
			$ext = '.' . $type;
		}
		return $name . $ext;
	}

	/**
	 * 返回完整URL
	 *
	 * @param string $section
	 * @return string
	 */
	public static function makeUrl($section, $path)
	{
		$option = self::config($section);
		if($option['url_prefix']) return $option['url_prefix'] . $path;
		return $path;
	}

	/**
	 * 改良的 base_convert
	 * @param string $str
	 * @param int $frombase
	 * @param int $tobase
	 * @return string
	 */
	public static function stringBaseConvert($str, $frombase = 10, $tobase = 36)
	{
		$str = trim($str);
		if (intval($frombase) != 10) {
			$len = strlen($str);
			$q = 0;
			for ($i=0; $i<$len; $i++) {
				$r = base_convert($str[$i], $frombase, 10);
				$q = bcadd(bcmul($q, $frombase), $r);
			}
		}
		else $q = $str;

		if (intval($tobase) != 10) {
			$s = '';
			while (bccomp($q, '0', 0) > 0) {
				$r = intval(bcmod($q, $tobase));
				$s = base_convert($r, 10, $tobase) . $s;
				$q = bcdiv($q, $tobase, 0);
			}
		}
		else $s = $q;

		return $s;
	}

	/**
	 * @param array $post_file = _FILES['input_name']
	 * @return array
	 */
	public static function fixPostFile( &$post_file )
	{
		if( empty( $post_file ) ) {
			return $post_file;
		}

		if( 'array' !== gettype($post_file['name']) ) {
			return $post_file;
		}

		$keys = array_keys($post_file['name']);
		$ret = array();
		foreach ($keys as $idx) {
			$ret[$idx] = [];
			foreach ($post_file as $res=>$item) {
				$ret[$idx][$res] = $item[$idx];
			}
		}

		return $ret;
	}

	/**
	 * 通过文件 hash 值 计算条目的 ID
	 *
	 * @param string $hashed
	 * @return string
	 */
	public function getId($hashed)
	{
		return static::makeId($hashed);
	}

	/**
	 * 存储文件
	 *
	 * @param string $file
	 * @param string $new_name
	 * @param array $option option or other meta
	 * @return string
	 */
	abstract public function put(Storage_Entry $entry, $option = NULL);

	/**
	 * 根据id取出文件
	 *
	 * @param mixed $id or filename or file path
	 * @return mixed
	 */
	abstract public function get($id);

	/**
	 * 根据 path 值（和ID对应的唯一值）判断文件是否存在
	 *
	 * @param string $path
	 * @return mixed id or FALSE
	 */
	abstract public function exists($path);

	/**
	 * 根据id删除文件
	 *
	 * @param mixed $id
	 * @return boolean
	 */
	abstract public function delete($id);

	/**
	 * 分页浏览，可以添加查询条件
	 *
	 * @param array $condition = array()
	 * @param int $limit = 20
	 * @param int $offset = 0
	 * @param int $total = null
	 * @return array | iterator
	 */
	public function browse($condition = array(), $limit = 20, $offset = 0, & $total = 0)
	{
		return $this->browseMeta($condition, $limit, $offset, $total);
	}

	/**
	 * setOption
	 *
	 * @param string $key
	 * @return void
	 */
	public function setOption($key, $value = null)
	{
		if (is_array($key)) {
			foreach($key as $k => $v) {
				$this->_ini[$k] = $v;
			}
		} elseif (is_string($key)) {
			$this->_ini[$key] = $value;
		}
		return $this;
	}

	/**
	 * getOption
	 *
	 * @param string $key
	 * @return void
	 */
	public function getOption($key, $dft = null)
	{
		if(isset($this->_ini[$key])) return $this->_ini[$key];
		return $dft;
	}

	/**
	 * 对上传进行包装
	 *
	 * @param string $section storage.conf里的配置节点
	 * @param string | mixed $field 上传文件的input字段名 or object
	 * @param string $new_name 上传文件的文件重命名
	 * @param string $max_size 允许上传的最大文件字节
	 * @param string $width 宽度，可省略
	 * @param string $height 高度，可省略
	 * @return mixed 返回负数为失败，返回不为空的字符为正确
	 */
	public static function upload($section, $field, $opt = NULL)
	{
		$sto = self::farm($section);
		if (is_array($opt)) {
			$sto->setOption($opt);
		}
		
		if ($field instanceof Storage_Entry) {
			return $sto->store($field);
		} elseif (is_string($field) && isset($_FILES[$field])) {
			$files = self::fixPostFile($_FILES[$field]);
			if (isset($files[0])) {
				Log::debug('multiple upload', __METHOD__);
				$ret = [];
				foreach ($files as $file) {
					$entry = Storage_Entry::fromUpload($file);
					$ret[] = is_object($entry) ? $sto->store($entry) : FALSE;
				}
				return $ret;
			}
			$entry = Storage_Entry::fromUpload($files);
			return is_object($entry) ? $sto->store($entry) : FALSE;

		} else {
			/*$file = array(
				'error' => isset($field->error) ? $field->error : 0,
				'name' => $field->name,
				'type' => $field->type,
				'size' => $field->size
			);
			if (isset($field->tmp_name)) {
				$file['tmp_name'] = $field->tmp_name;
			}
			elseif (isset($field->content)) {
				//$file['content'] = $field->content;
				// TODO: generate tmp file
				$tmp = tempnam(TEMP_ROOT, 'upload_image_');
				$ret = file_put_contents($tmp, $field->content, LOCK_EX);
				if ($ret === $field->size) $file['tmp_name'] = $tmp;
				else throw new Exception('write temp file error '.$tmp);
			}
			
			return $sto->store($file);*/
		}
		return FALSE;
		
	}

	/**
	 * 保存上传的文件并返回结果数组
	 *
	 * @param Storage_Entry $entry
	 * @return array
	 */
	public function store(Storage_Entry $entry)
	{
		Log::debug($entry, __METHOD__);
		if ($entry->error !== UPLOAD_ERR_OK) {
			return array('errno' => $entry->error, 'error' => self::errorText($entry->error));
		}
		
		$return_array = $this->getOption('return_array');
		$return_info = $this->getOption('return_info');
		if($return_info && !$return_array) $return_array = true;
		/*$hashed = static::hashFile($entry->tmp_name);
		$id = static::makeId($hashed);
		$item = $this->getMeta($id);
		if ($item) {
			Log::debug('id: '.$id.'meta exists', __METHOD__);
			$id = $item['id'];
			$arr = array('id'=>$id, 'item' => $item, 'errno' => 0, 'error' => 'exists');
			if (isset($item['path'])) {
				$arr['path'] = $item['path'];
			} elseif (isset($item['type'])) {
				$arr['path'] = static::makePath($id, $item['type']);
			} else {
				$arr['path'] = static::makePath($id, $entry->ext);
			}
			return $arr;
		}*/
		$retVal = $this->validate($entry);

		if ($retVal < 0) {
			$arr = array('errno' => $retVal, 'error' => self::errorText($retVal));
			if ($return_info ) {
				$arr['meta'] = $entry->meta;
			}
			return $arr;
		}
		$return_url = $this->getOption('return_url');
		$url_prefix = $this->getOption('url_prefix');
		$id = $this->put($entry);

		if (!$id) {
			Log::warning('put entry error', __METHOD__);
			return FALSE;
		}

		if (is_array($id)) {
			$ret = $id;
			$ret['errno'] = 0;
		} else {
			$ret = array('id' => $id, 'path' => static::makePath($id, $entry->ext), 'errno' => 0, 'error' => '');
		}

		if ($id && $return_url && $url_prefix ) {
			$ret['url'] = $url_prefix . ltrim($ret['path'], '/');
			if (!$return_array) return $ret['url'] ;
		}
		
		return $ret;
	}

	/**
	 * 补充更多的 Meta 信息
	 *
	 * @param Storage_Entry $entry
	 * @return object
	 **/
	protected function retrieveMeta(Storage_Entry $entry, $option = NULL)
	{
		$meta = $entry->meta;
		$meta['ext'] = $entry->ext;
		//$meta['oldname'] = $entry->name;

		if (!isset($option['app_id'])) $app_id = $this->getOption('app_id');
		if (is_null($app_id)) $app_id = 0;

		$meta['app_id'] = $app_id;

		$hashed = static::hashFile ( $entry->tmp_name, $entry->size );

		$meta['hash'] = $hashed;

		$id = static::makeId($hashed);
		$meta['id'] = $id;
		$meta['path'] = static::makePath($id, $entry->ext);
		$meta['size'] = $entry->size;

		return $meta;
	}

	/**
	 * 对上传的文件进行预处理
	 *
	 * @param object $entry
	 * @param string $name
	 * @param string $type
	 * @param string $size
	 * @param mixed $img_info
	 * @return int
	 */
	public function validate(Storage_Entry $entry)
	{
		if (!$entry->isValid()) {
			return -1;
		}

		$max_size = $this->getOption('max_size');
		if($entry->size > $max_size) {
			return -2;
		}

		$allowed_types = $this->getOption('allowed_types');
		if(is_array($allowed_types) && !in_array($entry->type, $allowed_types)) {
			return -3;
		}

		if (!$entry->isImage()) {
			return -3;
		}
		
		$ext = $entry->ext;//strtolower(substr(strrchr($entry->name, '.'), 1)); //var_dump($ext);
		$allowed_exts = $this->getOption('allowed_extensions');//var_dump($ext,$allowed_exts);
		if(is_array($allowed_exts) && !in_array($ext, $allowed_exts)) {
			return -4;
		}

		if (in_array($ext, array('gif','jpg','jpeg','jp2','png','swc','swf','tif'))){
			
			// strip image
			$strip_image = $this->getOption('strip_image', true);
			if ($strip_image) {
				$max_quality =  $this->getOption('max_quality');
				$max_quality || $max_quality = self::DFT_MAX_QUALITY;
				$entry->stripImage(['max_quality' => $max_quality]);
			}
			// end strip image

			$allowed_imagetypes = $this->getOption('allowed_imagetypes');
			if(is_array($allowed_imagetypes) && !in_array($entry->meta['imgtype'], $allowed_imagetypes)) {
				return -4;
			}

			$width = $this->getOption('width');
			$max_width = $this->getOption('max_width');
			if($width && $entry->meta['width'] != $width || $max_width && $entry->meta['width'] > $max_width ) {
				return -5;
			}

			$height = $this->getOption('height');
			$max_height = $this->getOption('max_height');
			if($height && $entry->meta['height'] != $height || $max_height && $entry->meta['height'] > $max_height ) {
				return -6;
			}
		}

		return 0;
	}

	/**
	 * 根据值返回错误描述
	 *
	 * @param int $status
	 * @return array
	 */
	public static function errorText($status)
	{
		switch($status){
			case 0: // UPLOAD_ERR_OK
				return 'OK';
			case 1: // UPLOAD_ERR_INI_SIZE
				return '上传的文件超出系统最大限制';
			case 2: // UPLOAD_ERR_FORM_SIZE
				return '上传的文件超出 MAX_FILE_SIZE 限制';
			case 3: // UPLOAD_ERR_PARTIAL
				return '只有部分被传输';
			case 4: // UPLOAD_ERR_NO_FILE
				return '没有选择文件';
			case 6: // UPLOAD_ERR_NO_TMP_DIR
				return '临时目录不可用';
			case 7: // UPLOAD_ERR_CANT_WRITE
				return '写错误';
			case 8: // UPLOAD_ERR_EXTENSION
				return '由于异常而停止';

			case -1:
				return "文件错误";
			break;
			case -2:
				return "文件尺寸过大";
			break;
			case -3:
				return "文件格式不对或不被允许";
			break;
			case -4:
				return "文件上传失败,不被允许的扩展名或图片格式";
			break;
			case -5:
				return "图片宽度不符合规定宽度";
			break;
			case -6:
				return "图片高度不符合规定高度";
			break;
			case -11:
				return "图片质量比率太高，请从原稿重新压缩，建议不超过72";
			break;
			default:
				return "文件上传失败";
			break;
		}
	}

	/**
	 * function description
	 *
	 * @param
	 * @return void
	 */
	public static function imageTypeToExtension($type, $dot = true)
	{
		static $e = array ( 1 => 'gif', 'jpg', 'png', 'swf', 'psd', 'bmp',
			'tiff', 'tiff', 'jpc', 'jp2', 'jpf', 'jb2', 'swc',
			'aiff', 'wbmp', 'xbm');
		$type = (int)$type;
		if ( $type > 0 && isset($e[$type]) ) {
			return ($dot ? '.' : '') . $e[$type];
		}
		return '';
	}

	protected function browseMeta(array $condition = array(), $limit = 20, $offset = 0, & $total = null)
	{
		if (!isset(static::$mg_keys)) {
			throw new Exception('property mg_keys undefined', 1);
		}
		extract($condition, EXTR_SKIP);
		$query = array();
		// TODO: 分析查询条件
		
		
		
		$sort = array();
		if (isset($sort_name) && array_key_exists($sort_name, static::$mg_keys)) {
			$sort[static::$mg_keys[$sort_name]] = isset($sort_order) && strtoupper($sort_order) == 'DESC' ? -1 : 1;
		}
		else {
			$sort = array(static::$mg_keys['created'] => -1);
		}
		
		$collection = $this->getCollection();
		$cursor = $collection->find($query)->limit($limit);
		$total = $cursor->count();
		if (!empty($sort)) $cursor = $cursor->sort($sort);
		if ($offset > 0) $cursor = $cursor->skip($offset);
		$data = array();
		while($cursor->hasNext()) {
			$item = $cursor->getNext();
			$row = self::makeItem($item);
			unset($item);
			$data[] = $row;
		}
		return $data;
	}

	/**
	 * return MongoDb
	 * 
	 * @return object
	 */
	protected function getDb()
	{
		if (!isset($this->_db_ns)) {
			throw new Exception('property _db_ns undefined', 1);
		}
		$mg = Da_Wrapper::mongo ( $this->_db_ns );
		return $mg->storage;
	}

	/**
	 * return Mongo Collection
	 * 
	 * @return object
	 */
	protected function getCollection($sub_name = 'files')
	{
		if (!isset($this->_db_prefix)) {
			throw new Exception('property _db_prefix undefined', 1);
		}
		return Da_Wrapper::mongoCollection($this->getDb(), $this->_db_prefix . '.' . $sub_name);
	}

	/**
	 * 处理mongo中提取的数据用于显示
	 * @param array $item
	 * @return array
	 */
	protected static function makeItem($item)
	{
		$arr = array (
			'id' => $item[static::$mg_keys['id']], 
			'size' => isset($item[static::$mg_keys['size']]) ? $item[static::$mg_keys['size']] : 0
		);
		if ($arr['size'] == 0 && isset($item['content_length'])) {
			$arr['size'] = $item['content_length'];
		}
		if ($arr['size'] == 0 && isset($item['length'])) {
			$arr['size'] = $item['length'];
		}
		isset($item['created']) && $arr['created'] = date("Y-m-d H:i:s",$item[static::$mg_keys['created']]->sec);
		isset($item['md5']) && $arr['md5'] = $item['md5'];
		isset($item['hash']) && $arr['hash'] = $item['hash'];
		isset($item['filename']) && $arr['filename'] = str_replace('.jpeg', '.jpg', $item['filename']);
		isset($item['app_id']) && $arr['app_id'] = $item['app_id'];
		isset($item['path']) && $arr['path'] = $item['path'];
		isset($item['type']) && $arr['type'] = $item['type'];
		isset($item['width']) && $arr['width'] = $item['width'];
		isset($item['height']) && $arr['height'] = $item['height'];
		isset($item['mime']) && $arr['mime'] = $item['mime'];

		if (!isset($arr['path'])) {
			if (isset($item['imgtype'])) {
				$arr['path'] = static::makePath($arr['id'], $item['imgtype']);
			}
			elseif (isset($item['ext'])) {
				$arr['path'] = static::makePath($arr['id'], $item['ext']);
			}
			elseif (isset($item['filename'])) {
				$arr['path'] = $item['filename'];
			}
		}
		
		return $arr;
		
	}

	/**
	 * 按文件 id （一般是 path）取 meta 信息
	 *
	 * @param mixed $hashed
	 * @return mixed id or FALSE
	 */
	public function getMeta($id)
	{
		$collection = $this->getCollection();
		$item = $collection->findOne(array("_id" => $id));
		if (!$item) return FALSE;
		return static::makeItem($item);
	}

}


