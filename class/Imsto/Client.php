<?php

/**
 * Imsto Client
 *
 * @package core
 * @author liut
 **/
class Imsto_Client
{
    protected $curl = 0;
    protected $url;
    // protected $headers;
    // protected $user_agent;
    // protected $timeout = 0;
    // protected $connect_timeout = 0;
    protected $return_transfer = 1;
    protected $follow_location = 1;

    private $_error;
    private $_errno = 0;

    protected $_roof;
    protected $_app = 0;
    protected $_user = 0;
    protected $_token;
    protected $_last_stamp = 0;

    const TOKEN_TIMEOUT = 900;

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

    /**
     * return server url with special suffix
     *
     * @param string $suffix
     * @return string
     **/
    public static function url($suffix)
    {
        $url = self::config('server_addr');
        return rtrim($url, '/') . '/' . $suffix;
    }

    /**
     * return all administrable roofs
     *
     * @return array
     **/
    public static function roofs()
    {
        $client = new self('common');
        return $client->get(self::url('roofs'));
    }

    /**
     * 按 _FILES 内容上传
     * 基本兼容原 Storage::upload()
     *
	 * @param string $roof imsto_client 里的配置节点
	 * @param string | mixed $field 上传文件的input字段名 or object
     * @return void
     **/
    public static function upload($roof, $field)
    {
    	if (isset($_FILES[$field]) /*&& isset($_FILES[$field]['tmp_name'])*/) {
    		$client = new self($roof);
    		$files = self::fixPostFile($_FILES[$field]);
			if (isset($files[0])) {
				Log::debug('multiple upload', __METHOD__);
				$ret = [];
				foreach ($files as $file) {
					$entry = Imsto_Entry::fromUpload($file);
					$ret[] = is_object($entry) ? $client->addEntry($entry) : FALSE;
				}
				return $ret;
			}
			$entry = Imsto_Entry::fromUpload($files);
			return is_object($entry) ? [$client->addEntry($entry)] : FALSE;

    	}
    	return false;
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
    }

    /**
     * get a new token
     *
     * @param int $app
     * @param int $user
     * @return mixed
     */
    public function getToken($app = 0, $user = 0)
    {
        $now = time();
        if (!empty($this->_token) && ($this->_last_stamp + self::TOKEN_TIMEOUT) > $now) {
            return $this->_token;
        }

        $param = [
            'roof' => $this->_roof,
            'app' => $app,
            'user' => $user,
        ];

        $res = $this->post(http_build_query($param), self::url('token'));
        $r = $this->_extRes($res);
        if (!$r) {
            return false;
        }
        $this->_token = $r['token'];
        $this->_last_stamp = $now;

        return $this->_token;
    }

    private function _extRes($res)
    {
        if (0 !== $this->_errno) {
            return false;
        }

        $r = json_decode($res, true);
        if (is_null($r)) {
            Log::notice($res, __METHOD__ . ' json_decode error');
            return FALSE;
        }

        if (!$r || $r['status'] != 'ok') {
            if (isset($r['error'])) {
                $this->_error = $r['error'];
            }
            Log::notice($r, __METHOD__ . ' fail');
            return false;
        }

        return $r;
    }

    public function get($url = NULL)
    {
        if (is_null($url)) {
            $url = $this->url;
        }
        return $this->prepare($url);
    }

    public function post($data, $url = NULL)
    {
        if (is_null($url)) {
            $url = $this->url;
        }
        return $this->prepare($url, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $data]);
    }

    private function prepare($url, $options = [])
    {
        $this->init($url);

        $curl_opt = [
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => $this->follow_location,
            CURLOPT_RETURNTRANSFER => $this->return_transfer,
            CURLOPT_HEADER => 0,
        ];
        $curl_opt += $options;

        $this->setOptions($curl_opt);
        $ret = $this->exec();

        $this->_errno = curl_errno($this->curl);
        if (0 !== $this->_errno) {
            $this->_error = array($this->_errno, curl_error($this->curl), curl_getinfo($this->curl));
            Log::notice($this->_error, __METHOD__);
        }

        return $ret;
    }

    private function setOptions(array $options)
    {
        curl_setopt_array($this->curl, $options);
    }

    private function init($url)
    {
        // if($this->curl === 0) {
            $this->curl = curl_init($url);
        // }
    }

    private function exec()
    {
        $result = curl_exec($this->curl);
        $error = curl_error($this->curl);
        return $error ? $error : $result;
    }

    public function close()
    {
        if($this->curl !== 0) {
            curl_close($this->curl);
            $this->curl = 0;
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
    	$opt = ['name' => $entry->name];
    	$res = $this->addFile($entry->tmp_name, $opt);
    	if (isset($res['status']) && $res['status'] == 'ok') {
	    	$ret = $res['data'][0];
	    	$ret['errno'] = 0;
	    	return $ret;
    	}
    	return isset($res['data']) ? $res['data'][0] : $res;
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

        if (isset($opt['app'])) {
            $this->app($opt['app']);
        }

        if (isset($opt['uid'])) {
            $this->uid($opt['uid']);
        }

        if (!isset($opt['token'])) {
            $opt['token'] = $this->getToken($this->_app, $this->_user);
        }

        $post_data = [
            'roof' => $this->_roof,
            'token' => $opt['token'],
            'app' => $this->_app,
            'user' => $this->_user,
            'file' => '@' . $file . ';filename='.$opt['name']
        ];

        $res = $this->post($post_data);
        return $this->_extRes($res);
    }

    public function error()
    {
        return $this->_error;
    }

    public function app($app = NULL)
    {
        if (is_null($app) || !is_numeric($app)) {
            return $this->_app;
        }
        $this->_app = $app;
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
	 * @param int $page = 1
	 * @param int $total = null
	 * @return array | iterator
     **/
    public function browse(array $cond, $rows = 10, $page = 1, &$total = 0)
    {
    	$q = ['roof' => $this->_roof, 'rows' => $rows, 'page' => $page];
    	if (isset($cond['sort_name'])) {
    		$q['sort_name'] = $cond['sort_name'];
    	}
    	if (isset($cond['sort_order'])) {
    		$q['sort_order'] = $cond['sort_order'];
    	}
    	$url = self::url('meta?'.http_build_query($q));

    	$res = $this->get($url);

    	$ret = $this->_extRes($res);
    	if (isset($ret['total'])) {
    		$total = $ret['total'];
    	}
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
		$url = self::url($this->_roof . '/' . $id);
		$res = $this->prepare($url, [CURLOPT_CUSTOMREQUEST => 'DELETE']);
		return $this->_extRes($res);
	}

} // END class Imsto_Client
