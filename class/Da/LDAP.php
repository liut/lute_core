<?PHP


/**
 * LDAP as database access
 *
 * @package core/da
 * @author liut
 **/
class Da_LDAP
{
	private $_host = 'localhost';
	private $_port = 389;

	private $_base_dn = 'dc=example,dc=org';
	private $_bind_format = 'uid=%s,ou=people,dc=example,dc=org';

	private $_conn = NULL;

	private $_cache_bound = [];

	private $_logs = NULL;

	public function __construct(array $opt)
	{
		isset($opt['host']) && $this->_host = $opt['host'];
		isset($opt['port']) && $this->_port = $opt['port'];
		isset($opt['base_dn']) && $this->_base_dn = $opt['base_dn'];
		isset($opt['bind_format']) && $this->_bind_format = $opt['bind_format'];

	}

	public function baseDn($ou = NULL)
	{
		if ($ou == 'groups') {
			return 'ou=groups,'.$this->_base_dn;
		}

		if ($ou == 'people') {
			return 'ou=people,'.$this->_base_dn;
		}

		return $this->_base_dn;
	}

	public function connect()
	{
		if (is_resource($this->_conn)) {
			return $this->_conn;
		}

		Log::info('ldap connect to '.$this->host.':'.$this->_port, __METHOD__);
		$this->_conn = ldap_connect($this->_host, $this->_port);
		if (!is_resource($this->_conn)) {
			throw new Exception('Could not connect to '.$this->_host);
		}

		ldap_set_option($this->_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->_conn, LDAP_OPT_REFERRALS, 0);

		return $this->_conn;
	}

	public function login($uid, $password)
	{
		if (isset($this->_cache_bound[$uid]) && $this->_cache_bound[$uid]) {
			return TRUE;
		}

		$rdn = $this->rdn($uid);
		Log::info('ldap bind as '.$rdn, __METHOD__);
		$bound = @ldap_bind($this->connect(), $rdn, $password);
		if ($bound) {
			$this->_cache_bound[$uid] = TRUE;
		}

		return $bound;
	}

	public function __call($name, array $args)
	{
		$func = 'ldap_'.$name;
		if (function_exists($func)) {
			$this->_log(__METHOD__.' call '.$func.', args ', $args);

			static $need_link = ['bind', 'get_entries', 'add', 'get_values'
			, 'list', 'close', 'unbind', 'read', 'sort', 'errno', 'error'
			, 'delete', 'get_dn', 'modify', 'rename', 'search', 'compare'
			, 'mod_add', 'mod_del', 'sasl_bind', 'start_tls', 'get_option', 'next_entry', 'set_option'
			, 'first_entry', 'free_result', 'mod_replace', 'modify_batch'
			, 'parse_result', 'count_entries', 'get_attributes', 'get_values_len'
			, 'next_attribute', 'next_reference', 'first_attribute', 'first_reference'
			, 'parse_reference', 'set_rebind_proc', 'control_paged_result', 'control_paged_result_response'];

			if (in_array($name, $need_link)) {
				array_unshift($args, $this->connect());
			}

			return @call_user_func_array($func, $args);
		}

		throw new BadFunctionCallException('method '.$name.' not found ');
	}

	public function rdn($uid)
	{
		return sprintf($this->_bind_format, $uid);
	}

	protected function _log($msg, $params = [])
	{
		if (defined('_DB_DEBUG') && TRUE === _DB_DEBUG) {
			if ($this->_logs === NULL) {
				$this->_logs = [];
			}
			$this->_logs[] = $msg . (count($params) > 0 ? ' :' . Arr::dullOut($params) : '');
			//Log::debug($msg, __CLASS__ . ' ' . $this->key);
		}
	}

	/**
	 * @return array
	 */
	public function errorInfo()
	{
		return [$this->error()];
	}

	/**
	 * @return array
	 */
	public function getLogs()
	{
		return [];
	}

} // END class Da_LDAP
