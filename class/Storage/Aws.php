<?PHP

//Loader::import(APP_ROOT . 'third/aws-sdk-php/src');
//Loader::import(APP_ROOT . 'third/guzzle/src');
//Loader::import(APP_ROOT . 'third/symfony/event-dispatcher');

//use Aws\S3\S3Client;

include_once APP_ROOT . 'third' . DS . 'aws-sdk-1' . DS . 'sdk.class.php';

/**
 * Interface for Amazon Simple Storage Service (S3)
 *
 * 修改： 由于S3的API限制，浏览不易实现，故前端用 MongoDB 保存 Meta 信息
 *
 * @package Sp
 * @author liut@wholeport
 **/
class Storage_Aws extends Storage
{
	// protected vars
	protected $_db_ns = 'mongo.storage';
	protected $_db_prefix = 's3';
	
	private $s3client;
	private $bucket;

	private $_result;

	// static vars
	protected static $mg_keys = array('id' => '_id', 'size' => 'size', 'created' => 'created', 'hash' => 'hash');

	/**
	 * constructor
	 * 
	 * @param array $option
	 * @return void
	 */
	protected function __construct($option)
	{
		if (!isset($option['bucket']) || empty($option['bucket'])) {
			throw new Exception("need option[bucket]");
		}

		parent::__construct($option);

		$this->bucket = $this->getOption('bucket');
	}

	/**
	 * return S3 Client Instance
	 * 
	 * @return object
	 */
	protected function getClient()
	{
		if (is_null($this->s3client)) {
			$aws_config = Loader::config('aws_v1');

			if (is_array($aws_config)) {
				CFCredentials::set($aws_config);
			}

			$this->s3client = new AmazonS3();
		}

		return $this->s3client;
	}

	/**
	 * @return SimpleXMLIterator
	 */
	public function listBuckets()
	{
		$Buckets = $this->getClient()->listBuckets()->body;

		if (isset($Buckets->Buckets)) {
			$Buckets = $Buckets->Buckets;
		}

		if (isset($Buckets->Bucket)) {
			$Buckets = $Buckets->Bucket;
		}

		return $Buckets;
	}

	/**
	 * @param array $opt valid keys: 'delimiter', 'marker', 'max-keys', 'prefix'
	 */
	public function listObjects($opt = null)
	{
		$objects = $this->getClient()->listObjects($this->bucket, $opt)->body;
		return $objects;
	}

	/**
	 * 按 key 取得 Object 信息
	 * @param string $key
	 * @return array | FALSE
	 */
	public function getObjectInfo($key)
	{
		$response = $this->getClient()->getObjectHeaders($this->bucket, $key);

		if ($response->isOK()) {

			$ret = $this->_siftInfo($response->header);

			unset($response);

			return $ret;
		}

		$this->_result = $response->body;
		Log::notice((array)$this->_result, __CLASS__);

		return FALSE;

	}

	/**
	 * 分页浏览，可以添加查询条件
	 * 
	 * @param array $condition = array()
	 * @param int $limit = 20
	 * @param int $offset = 0
	 * @param int $total = null
	 * @return array | iterator
	 */
	public function browse($condition = array(), $limit = 20, $offset = 0, & $total = null)
	{
		return $this->browseMeta($condition, $limit, $offset, $total);
	}

	/**
	 * 根据 hash 值（一般是md5）判断文件是否存在
	 *
	 * @param mixed $hashed | $path
	 * @return mixed id or FALSE
	 */
	public function exists($path)
	{
		return $this->getMeta($path);
	}

	/**
	 * 根据 id 取出文件
	 *
	 * @param mixed $id or filename or file path
	 * @return mixed
	 */
	public function get($id)
	{
		/*return $this->getClient()->get_object_metadata($this->bucket, $id);

		$response = $this->getClient()->get_object_headers($this->bucket, $id);*/

		$response = $this->getClient()->getObject($this->bucket, $id);

		if ($response->isOK()) {
			//return $response;
			$ret = $this->_siftInfo($response->header);

			$ret['body'] = $response->body;

			unset($response);

			return $ret;
		}

		$this->_result = $response->body;
		Log::notice((array)$this->_result, __CLASS__);

		return NULL;
	}

	private function _siftInfo($header)
	{
		$ret = []; $meta = [];

		static $base_head = ['content-length', 'content-type', 'etag', 'last-modified'];

		foreach ($header as $key => $value) {
			if (in_array($key, $base_head)) {
				$ret[$key] = $value;
			}
			elseif (strncmp($key, 'x-amz-meta-', 11) === 0) {
				$meta[substr($key, 11)] = $value;
			}
		}

		$meta && $ret['meta'] = $meta;

		return $ret;
	}

	/**
	 * 根据id删除文件
	 *
	 * @param mixed $id
	 * @return boolean
	 */
	public function delete($id)
	{
		// TODO:
	}

	/**
	 * 存储文件
	 *
	 * @param Storage_Entry $entry
	 * @param array $option
	 * @return string
	 */
	public function put(Storage_Entry $entry, $option = array())
	{
		Log::debug($entry, __METHOD__ . ' start put');
		if (!$entry->isImage()) {
			Log::notice( $entry, 'is not a image');
			return FALSE;
		}
		$meta = $this->retrieveMeta($entry, $option);

		$id = $meta['id'];unset($meta['id']);
		$filename = $meta['path'];

		$row = $meta;
		$row['path'] = $filename;

		if ($this->getClient()->ifObjectExists($this->bucket, $filename)) {
			Log::notice($filename . ' exists!', __METHOD__);
			$this->saveMeta($id, $row);
			return $id;
		}

		$opt = [
			'fileUpload' => $entry->tmp_name,
			'contentType' => $meta['mime'],
			'length' => $entry->size,
			'meta' => $meta,
		];
		Log::debug($opt, __METHOD__ . ' start upload to s3');

		$response = $this->getClient()->createObject($this->bucket, $filename, $opt);

		if ($response->isOK()) {
			$ret = $this->saveMeta($id, $row);
			Log::debug('createObject OK, save meta: ' . $ret, __METHOD__);
			return $id;
		}

		$this->_result = $response->body;

		Log::notice($response, __METHOD__);

		return FALSE;
	}

	protected function saveMeta($id, $row)
	{
		isset($row['_id']) || $row['_id'] = $id;
		isset($row['created']) || $row['created'] = new MongoDate();
		$collection = $this->getCollection();
		$ret = $collection->update(['_id' => $id], $row, ['upsert' => TRUE]);
		if (!$ret) {
			Log::notice('save meta failed', __METHOD__);
		}
	}

	public function getResult()
	{
		return $this->_result;
	}

} // END class 
