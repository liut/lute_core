<?PHP


/**
 * Cache_Memcached
 *
 * @package core
 * @author liutao
 * @lastupdate 20120612
 **/
class Cache_Memcached extends Cache // implements Cache_Interface
{

	private $_memc;

	public function init($params = NULL)
	{
		if ($this->_inited) return TRUE;

		if(!class_exists('Memcached', false)) {
			$this->log('class not exists: Memcached');
			return FALSE;
		}

		$this->_memc = new Memcached;

		if ( is_array($params) ) foreach($params as $key => $value) {
			$this->setOption($key, $value);
		}
		$this->_inited = TRUE;
		return TRUE;
	}

	public function addServers(array $servers)
	{
		if ($this->init()) $this->_memc->addServers($servers);
	}

	/**
	 * 取
	 *
	 * @param string $key
	 * @return mixed | false
	 */
	public function get($key)
	{
		$ret = FALSE;
		if ($this->init())
		{
			$ret = $this->_memc->get($this->_group.$key);
			if ($this->_debug)
			{
				$message = ($ret ? 'hit' : 'miss') . ' ' . $key . ', code: '. $this->_memc->getResultCode() . ', message: ' . $this->_memc->getResultMessage();;
				$this->log($message);
			}
		}
		return $ret;
	}

	/**
	 * 存
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int expire
	 * @return void
	 */
	public function set($key, $value, $expire = null)
	{
		if (empty($value)) {
			Log::warning($value, __METHOD__." {$key}'s value is empty");
		}
		if ($this->init()) {
			isset($expire) or $expire = $this->getLifeTime();

			if ($this->_debug)
			{
				$message = 'set key: ' . $key . ' life: '. $expire;
				$this->log($message);
			}
			if( $this->_memc->set($this->_group.$key, $value, $expire) ) {
				return TRUE;
			}
			if ($this->_debug)
			{
				$message = 'set failed, key: ' . $key . ', code: '. $this->_memc->getResultCode() . ', message: ' . $this->_memc->getResultMessage();
				$this->log($message);
			}
		}
		return FALSE;
	}

	/**
	 * 删
	 *
	 * @param string $key
	 * @return void
	 */
	public function delete($key, $expire = 0)
	{
		if ($this->init()) {
			$ret = $this->_memc->delete($this->_group.$key, $expire);
			if ($this->_debug)
			{
				$message = 'delete key: ' . $key . ', code: '. $this->_memc->getResultCode() . ', message: ' . $this->_memc->getResultMessage();
				$this->log($message);
			}
			return $ret;
		}
		return FALSE;
	}

	/**
	 * 清除全部缓存条目
	 *
	 */
	public function clean()
	{

	}

	/**
	 * 取出多个条目
	 *
	 * @param array $keys
	 * @param array $keys
	 * @return array
	 * @author liut
	 **/
	public function getMulti(array $keys, array &$cas_tokens = NULL, $flags = 0)
	{
		if ($this->init()) {
			$this->_debug && $this->log(__METHOD__ . ' ' . implode(',', $keys));
			return $this->_memc->getMulti($keys, $cas_tokens, $flags);
		}

		return FALSE;
	}

	/**
	 * 存储多个条目
	 *
	 * @param array $items
	 * @param int $expire
	 * @return void
	 * @author liut
	 **/
	public function setMulti(array $items, $expire)
	{
		if ($this->init()) {
			$this->_debug && $this->log(__METHOD__ . ' ' . implode(',', array_keys($items)));
			return $this->_memc->setMulti($items, $expire);
		}

		return FALSE;
	}

	public function getAllKeys()
	{
		if ($this->init()) {
			return $this->_memc->getAllKeys();
		}

		return FALSE;
	}

} // END class
