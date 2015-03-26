<?php

/**
 * Imsto Client
 *
 * @package core
 * @author liut
 **/
class Imsto_Client
{
	protected $_ch = 0;
	protected $url;
	// protected $headers;
	// protected $user_agent;
	// protected $timeout = 0;
	// protected $connect_timeout = 0;
	protected $return_transfer = 1;
	protected $follow_location = 1;

	private $_error = NULL;
	private $_errno = 0;
	private $_error_info = NULL;

	protected $_roof;
	protected $_api_key = '';
	protected $_app = 0;
	protected $_user = 0;
	protected $_token;
	protected $_last_stamp = 0;

	const TOKEN_TIMEOUT = 900;
	const API_KEY_HEADER = 'X-Access-Key';

	/**
	 * return special config value from imsto_client.ini
	 *
	 * @param string $k
	 * @return mixed
	 **/
	public static function config($k)
	{
		$conf = Loader::config('imsto_client');
		return isset($conf[$k]) ? $conf[$k] : '';
	}

	public static function thumbPath($roof)
	{
		$cfg = static::config($roof);
		return isset($cfg['thumb_path']) ? trim($cfg['thumb_path'], '/') : $roof;
	}

	public static function urlPrefix($roof)
	{
		$stage_host = static::config('stage_host');
		if (empty($stage_host)) {
			$url = '/';
		} else {
			$url = 'http://'.$stage_host.'/';
		}

		return $url . static::thumbPath($roof) . '/';
	}

	public static function administrable($roof = NULL)
	{
		$cfg = Loader::config('imsto_client');
		if (is_null($roof)) {
			$arr = [];
			foreach ($cfg as $key => $sec) {
				if (isset($sec['administrable']) && $sec['administrable']) {
					$label = isset($sec['label']) ? $sec['label'] : ucfirst($key);
					// $api_key = isset($sec['api_key']) ? $sec['api_key'] : '';
					// $arr[$key] = ['label'=> $label, 'api_key' => $api_key];
					$arr[$key] = $label;
				}
			}
			return $arr;
		}

		if (isset($cfg[$roof]) && isset($cfg[$roof]['administrable']) && $cfg[$roof]['administrable']) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * return server url with special suffix
	 *
	 * @param string $suffix
	 * @return string
	 **/
	public function url($suffix)
	{
		$suffix = trim($suffix, '/');
		$url = self::config('server_addr');
		$url = rtrim($url, '/') . '/';
		if ($suffix == 'roofs') {
			return $url . '/roofs';
		}
		if (empty($suffix)) {
			return $url . $this->_roof;
		}
		return $url . $this->_roof . '/' . $suffix;
	}

	/**
	 * return all administrable roofs
	 *
	 * @return array
	 **/
	public static function roofs()
	{
		$client = new self('common');
		return $client->get('roofs');
	}

	/**
	 * 按 _FILES 内容上传
	 * 基本兼容原 Storage::upload()
	 *
	 * @param string $field 上传文件的input字段名
	 * @return void
	 **/
	public function upload($field, array $opt = [])
	{
		if (!isset($_FILES[$field]) || !isset($_FILES[$field]['tmp_name'])) {
			$this->_error = ['code' => -4, 'message' => 'not select file `'.$field.'`'];
			return FALSE;
		}

		$tags = '';
		if (isset($opt['tags'])) {
			$tags = $opt['tags'];
		} else {
			if (filter_has_var(INPUT_POST, 'tags')) {
				$tags = filter_input(INPUT_POST, 'tags', FILTER_SANITIZE_STRING);
			}
		}

		$files = static::fixPostFile($_FILES[$field]);
		Log::info($files, __METHOD__);
		if (isset($files[0])) {
			Log::debug('multiple upload', __METHOD__);
			$ret = [];
			foreach ($files as $file) {
				$entry = Imsto_Entry::fromUpload($file);
				empty($tags) || $entry->tags = $tags;
				$ret[] = is_object($entry) ? $this->addEntry($entry) : FALSE;
			}
			return $ret;
		}
		$entry = Imsto_Entry::fromUpload($files);
		empty($tags) || $entry->tags = $tags;
		return is_object($entry) ? [$this->addEntry($entry)] : FALSE;
	}

	/**
	 * @param array $post_file = _FILES['input_name']
	 * @return array
	 */
	public static function fixPostFile( $post_file )
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
	 * factory make
	 *
	 * @param string $roof
	 * @return instance of self
	 **/
	public static function farm($roof)
	{
		static $instances = [];
		if (!isset($instances[$roof])) {
			$instances[$roof] = new self($roof);
		}
		return $instances[$roof];
	}

	/**
	 * constructor
	 *
	 * @param string $roof
	 * @return instance of self
	 **/
	public function __construct($roof)
	{
		if (empty($roof)) {
			throw new Exception("empty roof", 101);
		}
		$this->_roof = $roof;
		// try {
			$this->url = self::config('server_addr');
		// } catch (Exception $e) {
		//     echo 'Caught exception: ',  $e->getMessage(), "\n";
		// }
		// TODO: more options from config
		$cfg = self::config($roof);
		$api_key = $cfg['api_key'];
		if (empty($api_key)) {
			throw new Exception("api_key not found in CONF/imsto_client.ini", 102);
		}

		$this->_api_key = $api_key;
	}

	/**
	 * get a new token
	 *
	 * @param int $app
	 * @param int $user
	 * @return mixed
	 */
	public function getToken($user = 0)
	{
		$now = time();
		if (!empty($this->_token) ) {
			// && ($this->_last_stamp + self::TOKEN_TIMEOUT) > $now
			if (Imsto_Token::verify($this->_token)) {
				return $this->_token;
			}
		}

		$param = [
			// 'roof' => $this->_roof,
			// 'api_key' => $this->_api_key,
			'user' => $user,
		];

		$r = $this->post(http_build_query($param), 'token/new');
		if (!$r) {
			return false;
		}
		$this->_token = $r['meta']['token'];
		$this->_last_stamp = $now;

		return $this->_token;
	}

	public function genTicket($prompt)
	{
		$r = $this->post(http_build_query(['token'=>$this->getToken(),'user'=>$this->uid(), 'prompt'=>$prompt]), 'ticket/new');
		if (!$r) {
			return false;
		}

		return $r['meta']['token'];
	}

	private function _extRes($res)
	{
		if (!$res || 0 !== $this->_errno) {
			return false;
		}

		$r = json_decode($res, TRUE);
		if (is_null($r)) {
			Log::notice($res, __METHOD__ . ' json_decode error');
			$this->_error = ['code' => -5, 'message' => 'invalid response: json_decode error'];
			return FALSE;
		}

		if (!is_array($r) || empty($r)) {
			$this->_error = ['code' => -5, 'message' => 'invalid response: empty'];
			return FALSE;
		}

		if ($r['meta']['ok'] !== TRUE) {
			Log::notice($r, __METHOD__ . ' not ok');
			if (isset($r['error'])) {
				$this->_error = $r['error'];
			}
			return FALSE;
		}

		return $r;
	}

	/**
	 * get a images meta by image id
	 */
	public function get($id)
	{
		$res = $this->prepare($id);
		return $this->_extRes($res);
	}

	public function post($data, $suffix = '')
	{
		$res = $this->prepare($suffix, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $data]);
		Log::info($res, __METHOD__.' response');
		return $this->_extRes($res);
	}

	private function prepare($suffix, $options = [])
	{
		$url = $this->url($suffix);
		$this->_init($url);
		$this->_clearError();

		$curl_opt = [
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => $this->follow_location,
			CURLOPT_RETURNTRANSFER => $this->return_transfer,
			CURLOPT_HEADER => 0,
			CURLOPT_HTTPHEADER => [self::API_KEY_HEADER . ': ' . $this->_api_key],
		];
		$curl_opt += $options;

		Log::info($curl_opt, __METHOD__.' '.$url);

		$this->_setOptions($curl_opt);
		$ret = curl_exec($this->_ch);

		$this->_errno = curl_errno($this->_ch);
		if (0 !== $this->_errno) {
			$this->_error = [
				'code' => $this->_errno,
				'message' => curl_error($this->_ch),
			];
			$this->_error_info = curl_getinfo($this->_ch);
			Log::warning($this->_error, __METHOD__.' error');
			Log::warning($this->_error_info, __METHOD__.' error info');

			// 重写连接失败的消息
			if ($this->_errno == CURLE_COULDNT_CONNECT) {
				$this->_error = [
					'code' => CURLE_COULDNT_CONNECT,
					'message' => 'An internal error has occurred and we are working on it. Please try later.'
				];
			}
		}
		// Log::info($ret, __METHOD__.' result');

		return $ret;
	}

	private function _clearError()
	{
		$this->_errno = 0;
		$this->_error = NULL;
		$this->_error_info = NULL;
	}

	private function _setOptions(array $options)
	{
		curl_setopt_array($this->_ch, $options);
	}

	private function _init($url)
	{
		// if($this->_ch === 0) {
			$this->_ch = curl_init($url);
		// }
	}

	public function close()
	{
		if($this->_ch !== 0) {
			curl_close($this->_ch);
			$this->_ch = 0;
		}
	}

	function __destruct()
	{
		$this->close();
	}

	/**
	 * store entry
	 *
	 * @param Imsto_Entry $entry
	 * @return mixed
	 **/
	public function addEntry(Imsto_Entry $entry)
	{
		$opt = ['name' => $entry->name, 'mime' => $entry->type, 'tags' => $entry->tags]; //
		$res = $this->addFile($entry->tmp_name, $opt);
		return isset($res[0]) ? $res[0] : $res;
	}

	/**
	 * 提交一个文件，可以指定名称
	 *
	 * @return void
	 * @author liut
	 **/
	public function addFile($file, array $opt)
	{
		if (!is_file($file) || !is_readable($file)) {
			return false;
		}

		if (!isset($opt['name']) || $opt['name'] == '') {
			$opt['name'] = basename($file);
		}

		if (!isset($opt['mime']) || $opt['mime'] == '') {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$opt['mime'] = finfo_file($finfo, $file);
			finfo_close($finfo);
		}

		if (isset($opt['uid'])) {
			$this->uid($opt['uid']);
		}

		if (!isset($opt['token'])) {
			$opt['token'] = $this->getToken($this->_user);
		}

		if (!isset($opt['tags'])) {
			$opt['tags'] = '';
		}

		$post_data = [
			// 'roof' => $this->_roof,
			'token' => $opt['token'],
			// 'api_key' => $this->_api_key,
			'user' => $this->_user,
			'tags' => $opt['tags'],
			// 'file' => '@' . $file . ';filename='.$opt['name']
			'file' => new CURLFile($file, $opt['mime'], $opt['name'])
		];

		$res = $this->post($post_data);
		if (!$res) {
			return FALSE;
		}

		$arr = isset($res['data']) ? $res['data'] : $res;

		foreach ($arr as $item) {
			if (isset($item['error'])) {
				Log::notice($item, __METHOD__.' has error');
				$this->_error = $item['error'];
				break;
			}
		}

		return isset($arr['file']) ? $arr['file'] : $arr;

	}

	public function error()
	{
		return is_string($this->_error) ? ['code' => -1, 'message' => $this->_error] : $this->_error;
	}

	public function errorInfo()
	{
		return $this->_error_info;
	}

	public function hasError()
	{
		return is_array($this->_error) || is_string($this->_error) && !empty($this->_error);
	}

	public function apiKey($api_key = NULL)
	{
		if (is_null($api_key) || !is_numeric($api_key) || empty($api_key)) {
			return $this->_api_key;
		}
		$this->_api_key = $api_key;
	}

	public function token($token)
	{
		if (is_string($token) && !empty($token)) {
			$this->_token = $token;
		}
	}

	public function uid($user = NULL)
	{
		if (is_null($user) || !is_numeric($user)) {
			return $this->_user;
		}
		$this->_user = $user;
	}

	/**
	 * 分页浏览，可以添加查询条件
	 *
	 * @param array $condition = []
	 * @param int $rows = 20
	 * @param int $skip = 0
	 * @param int $total = null
	 * @return array | iterator
	 **/
	public function browse(array $cond, $rows = 10, $skip = 0, &$total = 0)
	{
		$q = ['rows' => $rows, 'skip' => $skip];
		if (isset($cond['sort_name'])) {
			$q['sort_name'] = $cond['sort_name'];
		}
		if (isset($cond['sort_order'])) {
			$q['sort_order'] = $cond['sort_order'];
		}
		if (isset($cond['tags'])) {
			$q['tags'] = $cond['tags'];
		}

		$res = $this->get('metas?'.http_build_query($q));

		if (isset($res['meta']['total'])) {
			$total = $res['meta']['total'];
		}
		return $res;
	}

	public function count(array $cond)
	{
		$q = [];
		if (isset($cond['tags'])) {
			$q['tags'] = $cond['tags'];
		}
		$res = $this->get('metas/count?'.http_build_query($q));
		// if (isset($res['meta']['total'])) {
		// 	return $res['meta']['total'];
		// }

		return $res;
	}

	/**
	 * 根据id删除文件
	 *
	 * @param mixed $id
	 * @return boolean
	 */
	public function delete($id)
	{
		$res = $this->prepare($this->_roof . '/' . $id, [CURLOPT_CUSTOMREQUEST => 'DELETE']);
		return $this->_extRes($res);
	}

} // END class Imsto_Client
