<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Storage_Mgfs
 *
 * 存储 base on MongGridFS
 *
 * @package    Sp
 * @version    $Id$
 * @created    15:59 2010-03-20
 */

/**
 * Storage_Mgfs
 *
 */
class Storage_Mgfs extends Storage
{
	// private vars
	protected $_db_ns = 'mongo.storage';
	protected $_db_prefix = 'fs';
	
	// static vars
	protected static $mg_keys = array('id' => '_id', 'size' => 'length', 'created' => 'uploadDate', 'hash' => 'md5');

	protected function __construct($option) {
		$this->_ini = $option;
		foreach ( $this->_ini as $key => $val ) {
			$_name = '_' . $key;
			if (isset ( $this->$_name )) {
				$this->$_name = $val;
			}
		}
	}

	/**
	 * 存储文件
	 *
	 * @param Storage_Entry $entry
	 * @param array $option
	 * @return string
	 */
	public function put(Storage_Entry $entry, $option = NULL)
	{
		Log::debug($entry, __METHOD__ . ' start put');
		if (!$entry->isImage()) {
			Log::notice( $entry, 'is not a image');
			return FALSE;
		}
		$meta = $this->retrieveMeta($entry, $option);

		$id = $meta['id'];
		unset($meta['id']);
		$filename = $meta['path'];

		$item = $this->getMeta($id);
		if ($item) {
			Log::debug('id: '.$id.'meta exists', __METHOD__);
			return $item;
			/*$id = $item['id'];
			$arr = array('id'=>$id, 'item' => $item, 'errno' => 0, 'error' => 'exists');
			if (isset($item['path'])) {
				$arr['path'] = $item['path'];
			} elseif (isset($item['type'])) {
				$arr['path'] = static::makePath($id, $item['type']);
			} else {
				$arr['path'] = static::makePath($id, $entry->ext);
			}
			return $arr;*/
		}
		$collection = $this->getCollection();
		// mongodb 存储在设计时设定了 md5 约束
		$item = $collection->findOne ( array ('md5' => md5_file($entry->tmp_name) ) );
		if ($item) {
			return $item['_id'];
		}

		$gridfs = $this->getGridFS();

		$extra = array_merge($meta, ['_id' => $id, 'filename' => $filename, 'app_id' => $app_id]);
		//if ($entry->type) {
		//	$extra['type'] = $entry->type;
		//}
		isset($created) && strtotime($created) && $extra['uploadDate'] = new MongoDate(strtotime($created));
		try {
			$ret = $gridfs->storeFile ( $entry->tmp_name, $extra, array ('safe' => true ) );
			return $id;
		} catch (MongoCursorException $ex) {
			Log::warning($ex, __METHOD__." Storage_Mgfs put Error");
			return FALSE;
		}

		if (is_array($ret) && isset($ret['ok'])) {
			return $id;
		}

		return FALSE;

	}

	/**
	 * 根据id取出文件
	 * 
	 * @param mixed $id
	 * @return mixed
	 */
	public function get($id)
	{
		$gridfs = $this->getGridFS();
		$gf = $gridfs->findOne(array('_id'=>$id));
		if (is_null($gf)) return null;
		$item = self::makeItem($gf->file);
		$item['bytes'] = $gf->getBytes();
		return $item;
	}

	/**
	 * 根据 hash 值（一般是md5）判断文件是否存在
	 *
	 * @param mixed $hashed
	 * @return mixed id or FALSE
	 */
	public function exists($path)
	{
		$collection = $this->getCollection();
		$item = $collection->findOne(array("path" => $id));
		if (!$item) return FALSE;
		return static::makeItem($item);
	}

	/**
	 * close connection & free resource
	 *
	 * @return void
	 */
	public function close()
	{
		
	}


	/**
	 * return MongoGridFS
	 * 
	 * @return object
	 */
	private function getGridFS()
	{
		$mgdb = $this->getDb();
		$gridfs = $mgdb->getGridFS ( $this->_db_prefix );
		
		return $gridfs;
	}

	/**
	 * 分页浏览，可以添加查询条件
	 * 
	 * @param array $condition = array()
	 * @param int $limit = 20
	 * @param int $offset = 0
	 * @param int $total = null
	 * @return array
	 */
	public function browse($condition = array(), $limit = 20, $offset = 0, & $total = null)
	{
		return $this->browseMeta($condition, $limit, $offset, $total);
	}
	
	/**
	 * 根据id删除文件
	 * 
	 * @param mixed $id
	 * @return boolean
	 */
	public function delete($id)
	{
		$gridfs = $this->getGridFS();
		return $gridfs->remove(array('_id' => $id));
	}
	
	
}