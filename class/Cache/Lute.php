<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * 缓存操作类, 使用文件存储
 *
 * @author      liut
 * @package     Core
 * @version     $Id$
 * @lastupdate $Date$
 */


/**
 * Cache_Lute
 *
 *
 *
 * Example:
 *
 * 	$cache = Cache_Lute::getInstance();
 * 	$key = 'key1';
 * 	$data = 'abc';
 * 	$ret = $cache->set($key, $data, 60);	// set
 * 	$c_data = $cache->get($key);		// get
 */
class Cache_Lute extends Cache // implements Cache_Interface //,ArrayAccess
{
	protected static $availableOptions = array('cacheDir', 'caching', 'lifeTime', 'debug', 'group', 'fileSuffix', 'serialization', 'idPattern', 'idReplace', 'args_min', 'args_max', 'no_index', 'lang');
	protected $_cacheDir = '/tmp/';
	protected $_fileSuffix = '';
	protected $_filename = NULL;
	/** 是否需要序列化 */
	protected $_serialization = FALSE;
	/** id的正则 */
	protected $_idPattern = NULL;
	/** id的替代操作 */
	protected $_idReplace = NULL;
	/** 语言代号 */
	protected $_lang = NULL;

	/**
	 * get
	 *
	 *
	 */
	public function get($key)
	{
		$_file = $this->getFilename($key);
		$_refreshTime = time() - $this->_lifeTime;
		if ((file_exists($_file)) && (@filemtime($_file) > $_refreshTime)) {
			$this->log('hit '.$key);
			$data = file_get_contents($_file);
			if ($this->_serialization && is_string($data)) {
				return unserialize($data);
			}
			return $data;
		}
		$this->log('miss '.$key);
		return FALSE;
	}

	/**
	 * set
	 *
	 *
	 */
	public function set($key, $value, $expire = NULL)
	{
		if (empty($value)) {
			Log::warning($value, __METHOD__." {$key}'s value is empty");
		}
		isset($expire) or $expire = $this->getLifeTime();

		$root = $this->_cacheDir;
		if (!(is_dir($root))) {
			$this->log('create dir '.$root);
			mkdir($root, 0777);
		}

		$_file = $this->getFilename($key);
		$dir = dirname($_file);
		if (!(is_dir($dir))) {
			$this->log('create dir '.$dir);
			mkdir($dir, 0777, TRUE);
		}

		$this->log('set key: '.$key.' lifetime: '.$expire);

		if ($this->_serialization) {
			$value = serialize($value);
		}

		$ret = file_put_contents($_file, $value, LOCK_EX);
		@chmod($_file, 0666);
		return TRUE;
	}

	/**
	 * delete
	 *
	 *
	 */
	public function delete($key, $expire = NULL)
	{
		$_file = $this->getFilename($key);
		if (file_exists($_file)) {
			return unlink ($_file);
		}
		Log::notice($_file . ' not exists', __METHOD__);
		return [FALSE, -1, 'file not exists'];
	}

	/**
	 * clean
	 *
	 *
	 */
	public function clean()
	{
		// TODO: 清除缓存，待实现
	}

    public function getLifeTime()
    {
        return $this->_lifeTime;
    }

	/**
	 * 返回缓存的文件名
	 *
	 *
	 */
	public function getFilename($key)
	{
		static $filenames = array();
		if (!isset($filenames[$key])) {
			$filenames[$key] = $this->_genFilename($key);
		}
		return $filenames[$key];
	}

	public function getLastModified($key)
	{
		$_file = $this->getFilename($key);
		return @filemtime($_file);
	}

	private function _genFilename($key)
	{
		$_path = rtrim($this->_cacheDir,'/') . DS;
		if (!empty($this->_group)) {
			$_path .= trim(preg_replace("/[^a-z0-9_\-\.\/]/", '', $this->_group), './') . DS;
		}
		if (!empty($this->_lang) && ctype_alpha($this->_lang)) {
			$_path .= $this->_lang . DS;
		}
		$_ext = $this->_fileSuffix;
		if (empty($_ext)) {
			$_ext = 'htm';
		}
		$this->_filename = $_path . $this->_refine($key) . '.' . trim($_ext, '.');
		Log::info(Loader::safePath($this->_filename), __METHOD__);
		return $this->_filename;
	}

	private function _refine($key)
	{
		if ($this->_idPattern) {
			if (preg_match($this->_idPattern, $key, $matches)) {
				Log::info($matches, __METHOD__.' match '.$this->_idPattern);
				if ($this->_idReplace) {
					if (is_callable($this->_idReplace)) {
						return call_user_func($this->_idReplace, $matches);
					} else {
						return preg_replace($this->_idPattern, $this->_idReplace, $key);
					}
				}

				array_shift($matches); // remove first [0]
				return implode('/', $matches);
			}
			else {
				Log::notice('id pattern "'.$this->_idPattern.'" not match: '. $key, __METHOD__);
			}
		}

		Log::info($key, __METHOD__);
		return trim(preg_replace("/[^a-z0-9_\-\.\/]/", '', $key), './');

	}

}

