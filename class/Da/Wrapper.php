<?php
/**
 *  数据库访问的包装类
 *
 * @author liut
 * @version $Id$
 * @created 13:30 2009-04-21
 */

/**
 * Da_Wrapper for Da Drivers
 *
 * supported classes: Da_PDO, Da_Mongo
 * interface:
 *   begin()
 *   end()
 *   abort()
 *   select($table, array $cond = array(), mixed $columns = '*', array $opt = array())
 *   update($table, array $data, array $cond = array())
 *   insert($table, array $data, $opt = array())
 *   delete($table, array $cond = array())
 *   count($table, array $cond = array(), $column = 'id', $distinct = FALSE)
 *
 */
final class Da_Wrapper
{
	private static $dbos = array();
	private static $logs = array();

	const CONF_NAME = 'da';

	/**
	 * constructor
	 *
	 * @return void
	 */
	private function __construct()
	{
	}

	/**
	 * 获取数据库配置节点内容
	 *
	 * @param string $table_key
	 * @return array or boolean
	 */
	public static function config($table_key, & $key = NULL)
	{
		$arr = self::parseConfigKey($table_key);

		if ($arr) {
			$key = $arr['key'];
			return self::configArray($arr);
		}

		return FALSE;
	}

	/**
	 * function description
	 *
	 * @param string $table_key
	 * @param boolean $check_default
	 * @return PDO
	 */
	public static function dbo($table_key, $check_default = TRUE)
	{
		$arr = self::config($table_key, $key);
		if ($arr === FALSE && $check_default) {
			$cfg = Loader::config(self::CONF_NAME);
			if (isset($cfg['default'])) {
				$arr = self::config($cfg['default']);
			}
		}
		if ($arr === FALSE) {
			throw new InvalidArgumentException('invalid table key');
		}
		//list($ns, $db, $node) = $arr;
		return self::_loadDbo($arr, $key);
	}

	/**
	 * @param array $arr
	 * @param string $key
	 * @return PDO
	 */
	private static function _loadDbo(Array $cfg, $key)
	{
		if(!isset(self::$dbos[$key]) || self::$dbos[$key] == null) {
			// mongodb only
			if (isset($cfg['type']) && $cfg['type'] == 'mongo' && isset($cfg['servers'])) {
				isset($cfg['options']) || $cfg['options'] = ['connect' => TRUE];
				isset($cfg['db']) || $cfg['db'] = 'test'; // mongo default
				self::$dbos[$key] = new Da_Mongo($cfg['servers'], $cfg['db'], $cfg['options']);
				return self::$dbos[$key];
			}

			// normal pdo
			if(!isset($cfg['dsn']) || !isset($cfg['username'])) {
				throw new Exception('config '.self::CONF_NAME.'['.$key.'] dsn not found!');
			}

			isset($cfg['options']) || $cfg['options'] = NULL;
			self::$dbos[$key] = new Da_PDO($cfg['dsn'], $cfg['username'], $cfg['password'], $cfg['options']);
			if(!empty($cfg['charset'])) {
				if(self::$dbos[$key]->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
					self::$dbos[$key]->exec('SET character_set_connection='.$cfg['charset'].', character_set_results='.$cfg['charset'].', character_set_client=binary');
				}
			}
			if(defined('_DB_DEBUG') && TRUE === _DB_DEBUG) {
				self::$dbos[$key]->key = $key;
				//self::_addLog(self::$dbos[$key], 'dbo loaded');
				self::$dbos[$key]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
		}

		return self::$dbos[$key];
	}

	/**
	 * 切分出数据库配置项名称
	 * @param string $table_key format: ns.db[.node][[.schema].table]
	 * @return array
	 */
	public static function parseConfigKey($str)
	{
		if (preg_match("#^([a-z]{2,9})\.([a-z][a-z0-9_]{1,9})(\.[a-z]{1,2})?((\.\w{4,13})?\.([a-z][a-z0-9_]+))?$#i", $str, $match)) {

			$arr = array(
				'ns' => $match[1],
				'db' => $match[2]
			);
			$arr['node'] = isset($match[3]) ? $match[3] : '';
			if (isset($match[5])) {
				$arr['schema'] = $match[5];
				$arr['table'] = $match[6];
			}
			$arr['key'] = $arr['ns'] . '.' . $arr['db'] . $arr['node']; // ns.db.node
			!empty($arr['node']) && $arr['node'] = substr($arr['node'], 1);
			!empty($arr['schema']) && $arr['schema'] = substr($arr['schema'], 1);
			return $arr;
		}
		return FALSE;
	}

	/**
	 * function description
	 *
	 * @param string $config
	 * @return void
	 */
	public static function configArray($arr)
	{
		$ns = $arr['ns'];
		$db = $arr['db'];
		$node = isset($arr['node']) ? $arr['node'] : null;
		$cfg = Loader::config(self::CONF_NAME);
		if(isset($cfg[$ns])) $cfg = & $cfg[$ns];
		if(isset($cfg[$db])) $cfg = & $cfg[$db];
		if(!is_null($node) && isset($cfg[$node])) $cfg = & $cfg[$node];

		return $cfg;
	}

	public static function getDbos() {
		return self::$dbos;
	}

	/**
	 * Implements dynamic methods
	 * supported: select,update,insert,delete,count,getRow,getAll,getOne,getFlat.
	 *
	 * @param   string  $name  The method name
	 * @param   string  $args  The method args
	 * @return  mixed
	 * @throws  BadMethodCallException
	 */
	public static function __callStatic($name, $args)
	{
		//defined('_DB_DEBUG') && Log::debug($args, __CLASS__ . '::' . $name);
		$argc = count($args);
		if ($name == 'select' || $name == 'update' || $name == 'insert' || $name == 'delete' || $name == 'count') {

			if ( $argc > 1 && is_string($args[0]) && is_string($args[1]) ) {
				$db_ns = array_shift($args);
				$dbh = static::dbo($db_ns);
				//self::_addLog($dbh, $name . ' ' . print_r($args, true));
				return call_user_func_array(array($dbh, $name), $args);
			}

			if ($name == 'select') {
				$cond = (isset($args[0]) ? $args[0] : array());
				return Da_Wrapper_Abstract::select($cond);
			}

			return Da_Wrapper_Abstract::$name();
		}

		if (in_array($name, array('getAll', 'getRow', 'getOne', 'getFlat'))) {
			if ($argc < 2) {
				throw new InvalidArgumentException('need argument: $table_key and $sql');
			}
			$db_ns = $args[0];
			$sql = $args[1];
			$params = (isset($args[2]) ? $args[2] : NULL);
			$dbh = static::dbo($db_ns);
			//self::_addLog($dbh, $sql);

			$fetch_style = (isset($args[3]) ? $args[3]: PDO::FETCH_ASSOC);
			if ($name == 'getFlat' && $fetch_style === PDO::FETCH_ASSOC) $fetch_style = FALSE;
			return $dbh->$name($sql, $params, $fetch_style);
		}

		throw new \BadMethodCallException('Method "'.$name.'" does not exist.');
	}


	/**
	 * 直接执行一条SQL，如 UPDATE、INSERT、DELETE等
	 *
	 * @param string $table_key
	 * @param string $sql
	 * @param array $params
	 * @return int
	 */
	public static function execute($table_key, $sql, $params = null)
	{
		$dbh = self::dbo($table_key);
		//self::_addLog($dbh, $sql);
		return $dbh->execute($sql, $params);
	}

	/**
	 * 执行一条SQL，并返回 Statment 对象
	 *
	 * @param string $table_key
	 * @param string $sql
	 * @param array $params
	 * @return object
	 */
	public static function query($table_key, $sql, $params = null)
	{
		$dbh = self::dbo($table_key);
		//self::_addLog($dbh, $sql);
		if(is_null($params) || ! is_array($params) || count($params) == 0) {
			$sth = $dbh->query($sql);
		} else {
			$sth = $dbh->prepare($sql);
			if($sth) $sth->execute($params);
		}
		if(!$sth) {
			Log::warning(__CLASS__ . '::'. __FUNCTION__ .' : '.print_r($dbh->errorInfo(),true) . "\n" . $table_key . ': ' . $sql);
			return false;
		}
		return $sth;
	}

	/**
	 * 分析输入的关键词并转换为合法的 tsquery
	 *
	 * @param string $term
	 * @param boolean $default_and = FALSE
	 * @return string
	 */
	static function toTsQuery($term, $default_and = FALSE)
	{
		## No backslashes allowed
		$term = preg_replace('/\\\/', '', $term);
		$term = preg_replace("/[\(\)]/", '', $term);

		## Collapse parens into nearby words:
		// $term = preg_replace('/\s*\(\s*/', ' (', $term);
		// $term = preg_replace('/\s*\)\s*/', ') ', $term);

		## Treat colons as word separators:
		$term = preg_replace('/:/', ' ', $term);

		$searchstring = '';
		$m = array();
		if( preg_match_all('/([-!]?)(\S+)\s*/', $term, $m, PREG_SET_ORDER ) ) {
			foreach( $m as $terms ) {
				if (strlen($terms[1])) {
					$searchstring .= ' & !';
				}
				if (strtolower($terms[2]) === 'and') {
					$searchstring .= ' & ';
				}
				else if (strtolower($terms[2]) === 'or' or $terms[2] === '|') {
					$searchstring .= ' | ';
				}
				else if (strtolower($terms[2]) === 'not') {
					$searchstring .= ' & !';
				}
				else {
					// 默认改为 or
					$searchstring .= $default_and ? ' & ' : ' | ' . $terms[2];
				}
			}
		}

		## Strip out leading junk
		$searchstring = preg_replace('/^[\s\&\|]+/', '', $searchstring);

		## Remove any doubled-up operators
		$searchstring = preg_replace('/([\!\&\|]) +(?:[\&\|] +)+/', "$1 ", $searchstring);

		## Remove any non-spaced operators (e.g. "Zounds!")
		$searchstring = preg_replace('/([^ ])[\!\&\|]/', "$1", $searchstring);

		## Remove any trailing whitespace or operators
		$searchstring = preg_replace('/[\s\!\&\|]+$/', '', $searchstring);

		## Remove unnecessary quotes around everything
		$searchstring = preg_replace('/^[\'"](.*)[\'"]$/', "$1", $searchstring);

		return $searchstring;
	}

	/**
	 * 释放 dbos 对象
	 *
	 * @return void
	 */
	public static function destroy()
	{
		self::$dbos = array();
	}

	/**
	 * 返回 Mongo 对象
	 *
	 * @param string $table_key
	 * @return object
	 */
	public static function mongo($table_key)
	{
		$cfg = self::config($table_key, $key);

		if(!is_array($cfg) || !isset($cfg['servers'])) {
			throw new Exception('config dsn ['.$table_key.'] not found!');
		}

		static $mgs = array();
		if(!isset($mgs[$key]) || $mgs[$key] == null) {
			$mgs[$key] = new MongoClient($cfg['servers'], $cfg['options']);
		}
		return $mgs[$key];
	}

	/**
	 * 查找并返回指定的 MongoCollection 对象
	 *
	 * @param mixed $mongo_db
	 * @param string $collection_name
	 * @return mixed
	 */
	public static function mongoCollection($mongo_db, $name)
	{
		if (is_string($mongo_db) && preg_match("/^\w+\.\w+/i", $mongo_db)) {
			list(, $db_name) = explode(".", $mongo_db, 3);
			$mongo_db = self::mongo($mongo_db)->selectDB($db_name);
		}
		if (!is_object($mongo_db)) return false;
		$list = $mongo_db->listCollections();
		foreach ($list as $collection) {
			if ($collection->getName() == $name) return $collection;
			//echo 'collection name: ', $collection->getName(), PHP_EOL;
		}

		//return false;
		// MongoDb 会自动建立所引用的 Collection, 但这里抛出异常, 是为了实现定制及优化, 所有的 Collection 必须提前建立
		throw new Exception('collection ['.$name.'] not found!');
	}

	/**
	 * 返回 redis 对象
	 *
	 * @param string $table_key
	 * @return object
	 */
	public static function redis($table_key)
	{
		$cfg = self::config($table_key, $key);

		if(!is_array($cfg) || !isset($cfg['servers'])) {
			throw new Exception('config dsn ['.$table_key.'] not found!');
		}

		static $redis = array();
		if(!isset($redis[$key]) || $redis[$key] == null) {
			$obj_redis = new Redis();
			$res = $obj_redis->connect($cfg['servers']['host'], $cfg['servers']['port']);
			if(true == $res){
				$redis[$key] = $obj_redis;
			} else {
				throw new Exception('Redis connect failed');
			}
		}
		return $redis[$key];
	}

	private static function _addLog($dbh, $str)
	{
		if (defined('_DB_DEBUG') && TRUE === _DB_DEBUG) {
			static::$logs[] = $dbh->key . ': ' . $str;
		}

	}

	public static function getLogs()
	{
		return static::$logs;
	}

}
