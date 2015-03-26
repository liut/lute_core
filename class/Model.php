<?php
/**
 * Data Object Model 数据模型基类，多表示为通用的、 以数字Id为主键的类的基类
 *
 * @author liut
 * @version $Id$
 * @created 2:20 2009年7月16日
 */

/**
 * Model Base
 *
 * 所有的静态变量，若需使用，子类必须定义
 *
 */
abstract class Model implements ArrayAccess, Serializable, JsonSerializable
{
	/**
	 * @var  string  table name to overwrite assumption, see also: Da_Wrapper::parseConfigKey()
	 */
	// protected static $_db_name;

	/**
	 * @var  string  table name to overwrite assumption
	 */
	// protected static $_table_name;

	/**
	 * @var  array  name or names of the primary keys
	 */
	protected static $_primary_key = 'id';

	/**
	 * @var  array  editable fields
	 */
	protected static $_editables = [];

	/**
	 * @var  array  sortable fields
	 */
	protected static $_sortables = ['id'];

	/**
	 * @var  array  for api UI (like JSON) model keys
	 */
	//protected static $_api_keys;

	/**
	 * @var  cache pond name
	 */
	protected static $_cache_pond = 'memcached';

	/**
	 * @var  cache lifetime, default 0 = nocache
	 */
	protected static $_cachable = 0;

	/**
	 * @var array $_rules filter rules
	 */
	// protected static $_v_rules = [];

	/**
	 * @var array $_hooks
	 */
	protected static $_hooks = [];

	protected static $_available_hooks = [
		'before_insert' => TRUE,
		'after_insert' => TRUE,
		'before_update' => TRUE,
		'after_update' => TRUE,
	];

	/**
	 * language code
	 *
	 * @var lang
	 **/
	protected static $_lang = NULL;

	/**
	 * tables per language
	 *
	 * @var lang_tables
	 **/
	//protected static $_lang_tables = [];

	/**
	 * Get the database name
	 */
	public static function db()
	{
		$class = get_called_class();
		// database name set in Model
		if (property_exists($class, '_db_name')) {
			return static::$_db_name;
		}
		else {
			$dac = Loader::config('da');
			if (isset($dac['default']) && is_string($dac['default'])) {
				return $dac['default'];
			}
		}
		// TODO: exception?
		throw new Exception('property _db_name undefined or no default db');
	}

	/**
	 * Get the database access object
	 */
	public static function dao()
	{
		return Da_Wrapper::dbo(static::db());
	}

	/**
	 * Get the table name for this class
	 *
	 * @param mixed $suffix string or NULL or FALSE
	 * @return  string
	 */
	public static function table($suffix = NULL)
	{
		$class = get_called_class();
		if (is_array($suffix)) {
			if (isset(static::$_table_depend) && isset($suffix[static::$_table_depend]) && is_scalar($suffix[static::$_table_depend])) {
				$suffix = $suffix[static::$_table_depend];
			} elseif (isset($suffix['suffix'])) {
				$suffix = $suffix['suffix'];
			}
		}
		if (is_string($suffix) && $suffix !== '' || is_int($suffix) && $suffix > 0) {
			if (isset(static::$_lang_tables) && isset(static::$_lang_tables[$suffix])) {
				return static::$_lang_tables[$suffix];
			}

			if (isset(static::$_table_prefix) && method_exists($class, 'tableSuffix')) {
				return static::$_table_prefix . static::tableSuffix($suffix);
			}

		}

		// Table name set in Model
		if (property_exists($class, '_table_name')) {
			return static::$_table_name;
		}
		throw new Exception('property _table_name undefined');
	}

	/**
	 * 设定或取得当前 Model 的 Language
	 *
	 * @param string $lang | NULL
	 * @return mixed
	 */
	public static function lang($lang = NULL)
	{
		if (is_null($lang)) { // get
			return static::$_lang;
		}

		if (FALSE === $lang) { // set NULL
			static::$_lang = NULL;
		}

		if (Lang::available($lang) /*&& Lang::changed($lang)*/) { // set lang
			static::$_lang = $lang;
		}

		return static::$_lang;
	}

	/**
	 * Get the primary key(s) of this class
	 *
	 * @return  array
	 */
	public static function primaryKey()
	{
		if (isset(static::$_primary_key)) {
			return static::$_primary_key;
		}
		return 'id';
	}

	/**
	 * return cache key
	 *
	 * @param mixed $id (string or array)
	 * @return mixed (string or array)
	 */
	public static function cacheKey($id, $pk = NULL, $lang = NULL)
	{
		$class = get_called_class();
		is_null($pk) && $pk = static::primaryKey();
		is_null($lang) && $lang = static::lang();
		if (is_array($id)) {
			$arr = [];
			foreach ($id as $val) {
				$arr[] = $class . '#' . $pk . '#' . $val . '#' . $lang;
			}
			return $arr;
		}
		return $class . '#' . $pk . '#' . $id . '#' . $lang;
	}

	/**
	 * undocumented function
	 *
	 * @param string $pond
	 * @return mixed
	 **/
	public static function cachePond($pond = NULL)
	{
		if (is_null($pond)) {
			return static::$_cache_pond;
		}

		static::$_cache_pond = $pond;
	}

	/**
	 * get,add,remove api keys
	 */
	public static function apiKeys($keys = NULL, $op = NULL)
	{
		if (!isset(static::$_api_keys)) {
			return NULL;
		}
			if ('get' === $op || is_null($keys)) {
				return static::$_api_keys;
			}
			if (is_string($keys)) {
				$keys = [$keys];
			}
			if ('add' === $op) {
				foreach ($keys as $k) {
					if (!in_array($k, static::$_api_keys)) {
						array_push(static::$_api_keys, $k);
					}
				}
			} elseif ('remove' === $op) {
				foreach ($keys as $k) {
					$i = array_search($k, static::$_api_keys);
					if ($i !== FALSE) {
						unset(static::$_api_keys[$i]);
					}
				}
			} else {
				static::$_api_keys = $keys;
			}
			return static::$_api_keys;
	}

	/**
	 * get or set cache lifetime
	 *
	 * @return mixed
	 */
	public static function cachable($value = NULL)
	{
		if ($value === NULL) {
			return static::$_cachable;
		}
		static::$_cachable = (int) $value;
	}

	/**
	 * Load instance with result by primary key
	 *
	 * @param mixed $id
	 * @param mixed $option array | int cachable | string pk
	 * @return object
	 */
	public static function load($id, $option = array())
	{
		if (is_null($id) || is_bool($id) || '' === $id) {
			throw new InvalidArgumentException('id is empty, class:'.get_called_class());
		}

		if (is_int($option)) $cachable = $option;
		elseif (is_string($option)) $pk = $option;
		elseif (is_array($option)) {
			isset($option['cachable']) && $cachable = $option['cachable'];
			isset($option['pk']) && $pk = $option['pk'];
			isset($option['lang']) && $lang = $option['lang'];
		}

		isset($pk) || $pk = static::primaryKey();
		isset($cachable) || $cachable = static::cachable();
		isset($lang) || $lang = static::lang();

		// TODO: add support array id
		if (is_array($id)) {
			return self::_loads($id, $pk, $cachable, $lang);
		}

		$key = static::cacheKey($id, $pk, $lang);

		static $_objs = array();

		if(!isset($_objs[$key]))
		{
			$obj = NULL;
			if($cachable > 0) {
				$cache = Cache::farm(static::$_cache_pond);
				$class = get_called_class();
				$obj = $cache->invoke($key, (int)$cachable, $class.'::farm', array($id, $pk));
			}
			if(is_null($obj) || !$obj) {
				$obj = static::farm($id, $option);
			}
			$_objs[$key] = &$obj;
		}

		return $_objs[$key];
	}

	/**
	 * Load instances with result by array primary keys
	 *
	 * @param array $ids
	 * @param mixed $option array | int cachable | string pk
	 * @return object
	 */
	private static function _loads(array $ids, $pk, $cachable = 0, $lang = NULL)
	{
		if (count($ids) == 0) {
			return [];
		}
		$ids = array_unique($ids);
		if($cachable > 0) {
			$cache = Cache::farm(static::$_cache_pond);
			$keys = static::cacheKey($ids, $pk, $lang);
			$items = $cache->getMulti($keys);

			$data = [];
			$pks = [];
			foreach ($ids as $id) {
				$key = static::cacheKey($id, $pk, $lang);
				if (!isset($items[$key]) || is_null($items[$key])) {
					$pks[] = $id;
					$data[$id] = NULL;
				} else {
					$data[$id] = $items[$key];
				}
			}

			if (count($pks) > 0) {
				//$str = implode(',', $pks);
				array_unshift($pks, 'IN');
				$items = [];

				foreach (static::findFold(['where' => [$pk => $pks]], $pk) as $id => $item) {
					$key = static::cacheKey($id, $pk, $lang);
					if (is_array($item)) {
						$item = static::farm($item);
					}
					$items[$key] = $item;
					$data[$id] = $item;
				}

				$cache->setMulti($items, (int)$cachable);
			}
			unset($items, $keys, $pks);

			return $data;
		}

		if (count($ids) > 99) {
			Log::notice($ids, get_called_class() . '::_loads too much ids');
			$ids = array_slice($ids, 0, 99);
		}

		$pks = $ids;
		array_unshift($pks, 'IN');
		return array_map('static::farm', static::findFold([
				'where' => [$pk => $pks],
				'order_by' => "FIELD($pk, ".implode(',', $ids).")"
			]));
	}

	/**
	 * farm a new class instance, 生产一个新实例
	 *
	 * @param  	array or mixed $data
	 * @return  object
	 */
	public static function farm($data = array(), $option = array())
	{
		$class = get_called_class();
		if ($data instanceof $class) {
			return $data;
		}
		if (empty($option)) $option = NULL;
		return new static($data, $option);
	}

	/**
	 * register a special hook
	 *
	 * @param   string $name
	 * @param  	callable $callback
	 * @return  void
	 */
	public static function bind($name, $callback = NULL)
	{
		$class = get_called_class();
		$key = $class . '-' . $name;
		if (is_null($callback)) {
			return isset(static::$_hooks[$key]) && is_callable(static::$_hooks[$key], TRUE);
		}
		if (isset(static::$_available_hooks[$name]) && is_callable($callback, TRUE, $callable_name)) {
			//Log::debug('Model::bind( ' . $key . ', ' . $class . ' ' . $callable_name . ' )');
			static::$_hooks[$key] = $callback;
		}
		return FALSE;
	}

	/**
	 * call a hook and return result
	 *
	 * @param   string $name
	 * @return  callable or FALSE
	 */
	protected static function binding($name,  array $args)
	{
		$class = get_called_class();
		$key = $class . '-' . $name;
		if (isset(static::$_hooks[$key]) && is_callable(static::$_hooks[$key], FALSE, $callable_name)) {
			Log::debug('Model::binding( ' . $key . ', ' . $class . ' ' . $callable_name . ' )');
			return call_user_func_array(static::$_hooks[$key], $args);
		}
		return TRUE;
	}

	/**
	 * hook: 准备Find之前执行.
	 *
	 * @param   array  $option  find arguments
	 * @return  void
	 */
	protected static function preFind(array & $option)
	{
	}

	/**
	 * hook: Find结果返回前执行.
	 *
	 * @param   array	$result	the result array or null when there was no result
	 * @return  array|null
	 */
	protected static function postFind(array $result)
	{
		return $result;
	}

	/**
	 * hook: 构造Where条件.
	 *
	 * @param   array	$option['where'] for findPage argument
	 * @return  array | mixed
	 */
	protected static function pageCondition(array $cond)
	{
		return isset($cond['where']) ? $cond['where'] : $cond;
	}

	/**
	 * Find one or more entries
	 *
	 * @param   mixed $id
	 * @param   string $pk
	 * @return  object|array
	 */
	public static function findByPk($id, $pk = NULL)
	{
		if (empty($id)) {
			throw new InvalidArgumentException("Invalid argument: empty id ($pk)", 10404);
		}

		is_null($pk) && $pk = static::primaryKey();

		if (is_array($id)) {
			if ($id[0] !== 'IN') {
				array_unshift($id, 'IN');
			}

			return static::find(array(
				'where' => array($pk => $id),
				//'columns' => '*',
				'fetch' => 'fold',
				'fold_key' => $pk
			));
		}

		return static::find(array(
			'where' => array($pk => $id),
			//'columns' => '*',
			'fetch' => 'row',
			'limit' => 1
		));
	}

	/**
	 * Find one or more entries
	 *
	 * @param   mixed $col
	 * @param   mixed $value
	 * @param   string $op
	 * @return  object|array
	 */
	public static function findBy($col, $value, $op = '=', $limit = NULL, $offset = 0)
	{
		// TODO: support custom operator and limit
		return static::find(array(
			'where' => array(
				$col => [$op, $value]
			),
			'limit' => $limit,
			'offset' => $offset
		));
	}

	/**
	 * Find one or more entries
	 *
	 * @param   array $option
	 * @return  object|array
	 */
	public static function find(array $option)
	{
		if(empty($option)) {
			throw new InvalidArgumentException('option is empty');
		}

		Log::info($option, get_called_class().'::find');

		if (!isset($option['fetch']) || $option['fetch'] == 'all' || $option['fetch'] == 'row' || $option['fetch'] == 'fold' ) {
			static::preFind($option);
		}

		$table = isset($option['table']) ? $option['table'] : (isset($option['lang']) ? static::table($option['lang']) : static::table($option));

		$where = isset($option['where']) ? $option['where'] : [];

		$columns = isset($option['columns']) ? $option['columns'] : '*';

		if (isset($option['sort_name']) ) {
			if (static::sortable($option['sort_name'])) {
				if ($option['sort_name'] == 'random') {
					if (!isset($option['limit'])) {
						$option['limit'] = 5;
					}
					return static::findRandom($table, $columns, $where, $option['limit']);
				}
				if (isset($option['sort_order']) && in_array(strtoupper($option['sort_order']), ['ASC', 'DESC'])) {
					$option['order_by'] = $option['sort_name'] . ' ' . strtoupper($option['sort_order']);
				} else {
					$option['order_by'] = $option['sort_name'];
				}
			}
			unset($option['sort_order'], $option['sort_name']);
		}

		unset($option['where'], $option['columns'], $option['table']);

		$result = Da_Wrapper::select(static::db(), $table, $where, $columns, $option);
		if (!is_array($result)) {
			return $result;
		}
		if (!isset($option['fetch']) || $option['fetch'] == 'all' || $option['fetch'] == 'fold') {
			return static::postFind($result);
		}
		return $result;
	}

	/**
	 * 按主键分组查询(即主键作Key)
	 *
	 * @param   mixed $option pkid or array
	 * @return array
	 **/
	public static function findFold(array $option, $pk = NULL)
	{
		if (!isset($option['fold_key'])) {
			is_null($pk) && $pk = static::primaryKey();
			$option['fold_key'] = $pk;
		}

		$option['fetch'] = 'fold';

		return static::find($option);
	}

	/**
	 * Count all of the rows in the table.
	 *
	 * @param   array   Query where clause(s)
	 * @param   string  Column to count by
	 * @param   mixed	Whether to count only distinct rows (by column) or array option
	 * @return  int	 The number of rows OR false
	 */
	public static function count(array $where = array(), $column = NULL, $distinct = FALSE)
	{
		// if (is_string($where) && is_bool($column) && is_array($distinct)) {
		// 	return static::dao()->count(static::table(), $distinct, $where, $column);
		// }

		if (is_array($distinct)) {
			$option = $distinct;
		} else {
			$option = ['distinct' => (bool)$distinct];
		}

		is_null($column) && $column = static::primaryKey();

		if (!isset($option['where'])) {
			$option['where'] = $where;
		}

		static::preFind($option);

		$table = isset($option['table']) ? $option['table'] : (isset($option['lang']) ? static::table($option['lang']) : static::table($option));
		$distinct = isset($option['distinct']) && $option['distinct'];

		return static::dao()->count($table, $option['where'], $column, $distinct);
	}

	/**
	 * Finds all records in the table.  Optionally limited and offset.
	 *
	 * @param   int	 $limit	 Number of records to return
	 * @param   int	 $offset	What record to start at
	 * @return  null|array		Null if not found or an array
	 */
	public static function findAll($limit = NULL, $offset = 0)
	{
		return static::find(array(
			'limit' => $limit,
			'offset' => $offset,
		));
	}

	/**
	 * Count all of the rows in the table.
	 *
	 * @param array $option
	 * @param int $limit
	 * @param int $offset
	 * @param int &$total
	 * @return mixed  result
	 */
	public static function findPage(array $option, $limit, $offset = 0, & $total = NULL)
	{
		if ($limit <= 0) {
			throw new InvalidArgumentException("invalid limit argument");
		}

		if (isset($option['where'])) {
			$option['where'] = static::pageCondition($option['where']);
		}

		if ($total === -1) {
			$total = static::count(isset($option['where']) ? $option['where'] : [], NULL);
		}

		$option['limit'] = (int)$limit;
		$option['offset'] = (int)$offset;

		return static::find($option);
	}

	/**
	 * 返回随机集合
	 *
	 * @param string $table
	 * @param string $columns
	 * @param array $condition
	 * @param int $limit
	 * @return array
	 * @author liut
	 **/
	public static function findRandom($table, $columns, array $where, $limit = 5)
	{
		$total = static::count($where, NULL);

		if ($limit < 1) {
			$limit = 1;
		}

		if ($total <= $limit) { // 总数小于分页时
			$result = Da_Wrapper::select(static::db(), $table, $where, $columns, ['fetch' => 'all']);
			if (!is_array($result) || empty($result)) {
				return $result;
			}
			shuffle($result);
			return static::postFind($result);
		}

		$params = [];
		if (count($where) > 0) {
			$sql_where = ' WHERE ' . Da_PDO::buildCondition($where, $params);
		} else {
			$sql_where = '';
		}

		$series = $limit * 4;

		$buffer = $limit * 25;

		if ($buffer < $series) {
			$buffer = $series;
		}

		$dbo = static::dao();
		$row = $dbo->getRow('SELECT MIN(id) as min_id, MAX(id) as max_id FROM '.$table.' '.$sql_where, $params);
		if (!$row) {
			return FALSE;
		}
		$min_id = (int)$row['min_id'];
		$max_id = (int)$row['max_id'];

		$format = <<< 'EOT'
SELECT %s
FROM  (
	SELECT %d + floor(random() * %d)::integer AS id
	FROM generate_series(1, %d) g GROUP BY 1
) r
JOIN %s USING (id) %s
LIMIT %d;
EOT;

		$sql = sprintf($format
			, $columns
			, $min_id
			, ($max_id - $min_id + $buffer)
			, $series
			, $table
			, $sql_where
			, $limit);

		Log::info($params, get_called_class().' findRandom '.$sql);
		$data = $dbo->getAll($sql, $params);

		return static::postFind($data);
	}

	/**
	 * @deprecated
	 */
	public static function doInsert(Array $data)
	{
		// TODO: create a new record
		return Da_Wrapper::insert(static::db(), static::table(), $data);
	}

	/**
	 * @deprecated
	 */
	public static function doUpdate(Array $data, $cond = array())
	{
		// TODO: update a exists record
		return Da_Wrapper::update(static::db(), static::table(), $data, $cond);
	}

	/**
	 * @deprecated
	 */
	public static function doDelete(Array $cond)
	{
		if (empty($cond)) {
			return FALSE;
		}

		return Da_Wrapper::delete(static::db(), static::table(), $cond);
	}

	/**
	 *
	 * @return Validation
	 */
	public static function validation()
	{
		$val = Validation::farm(get_called_class());
		if (isset(static::$_v_rules) && is_array(static::$_v_rules)) {
			$labels = isset(static::$_v_labels) ? static::$_v_labels : [];
			foreach (static::$_v_rules as $key => $rules) {
				$label = isset($labels[$key]) ? $labels[$key] : ucfirst($key);
				$val->add($key, $label, $rules);
			}
			return $val;
		}

		return static::customFilter($val);
	}

	/**
	 * hook: 定制验证规则
	 * @param Validation $val
	 */
	protected static function customFilter(Validation $val)
	{
		return $val;
	}

	/**
	 *
	 * @param array $input, 输入值，例如：$_POST
	 */
	public static function validate(array $input)
	{
		$val = static::validation();
		if ($val->run($input)) {
			return TRUE;
		}

		return $val->error();
	}

	// protected vars
	protected $_data = array();
	// protected $_key;
	// private vars
	private $_old_data = array();
	private $_new_data = array();

	/**
	 * @var  bool  $_is_new  If this is a new record
	 */
	protected $_is_new = FALSE;

	/**
	 * constructor
	 *
	 * @param mixed array or int (pk id) or other pk id
	 * @param mixed array or string (column name)
	 */
	protected function __construct($data = NULL, $option = NULL)
	{
		$this->init($data, $option);
	}

	/**
	 * initFromArgs
	 *
	 * @param mixed $id
	 * @param string $col
	 * @return void
	 */
	protected function init($data, $option = NULL)
	{
		if (!is_null($option) && !is_scalar($option) && !is_array($option)) {
			throw new InvalidArgumentException('second argument error: invalid option value type');
		}

		if (!(is_null($data) || is_array($data) || is_scalar($data) || is_object($data))) {
			throw new InvalidArgumentException('first argument error: invalid data value');
		}

		if (is_string($option)) $pk = $option;
		elseif (is_array($option)) {
			isset($option['pk']) && $pk = $option['pk'];
			isset($option['lang']) && $lang = $option['lang'];
			isset($option['suffix']) && $suffix = $option['suffix'];
		}

		isset($pk) || $pk = static::primaryKey();

		if (is_array($data)) {
			$this->_init($data);
			$this->_is_new = TRUE;
		}
		elseif ($data) {
			$option = [
				'where' => [$pk => $data],
				// 'table' => $lang ? static::table($lang) : static::table($suffix),
				'fetch' => 'row',
				'limit' => 1
			];
			isset($lang) && $option['lang'] = $lang;
			isset($suffix) && $option['suffix'] = $suffix;

			$row = static::find($option);
			if ($row === FALSE) { // 不存在的记录，但提供了有效主键，当作新记录，此策略暂定
				$row = [$pk => $data];
				$this->_is_new = TRUE;
			}

			if (is_array($row)) {
				$this->_init($row);
			}
			else {
				Log::info('row error or not found '.$pk.'='.$data, get_called_class().' init');
			}
		}
		// otherwise: NULL
	}

	private function _init(array $row)
	{
		//$row = array_change_key_case($row, CASE_LOWER);
		$this->_data = array_merge($this->_data, $row);
	}

	/**
	 * Either checks if the record is new or sets whether it is new or not.
	 *
	 * @param   bool|null  $new  Whether this is a new record
	 * @return  bool|$this
	 */
	public function isNew($new = NULL)
	{
		if ($new === null) {
			return $this->_is_new;
		}
		$this->_is_new = (bool) $new;
		return $this;
	}

	/**
	 *
	 * @param string $key
	 * @return array
	 */
	protected function _getData($key = '')
	{
		if ('' === $key) {
			// TODO: filter valid record values
			return $this->_data;
		}
		return null;
	}

	/**
	 * 经过包装的属性获取方法
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		// TODO: allow all object vars(->var) readonly, but fibid _var name style
		$_name = '_'.$name;
		if (property_exists($this, $_name)) {
			//echo 'exists ', $name, "\n";
			return $this->$_name;
		}
		if (array_key_exists($name, $this->_data)) {
			return $this->_data[$name];
		}
		$method = 'get'.self::_camelize($name);
		if(method_exists($this, $method))
		{
			$ret = $this->$method();
			$this->_data[$name] = $ret;
			return $ret;
		}
		return $this->_getData($name);
	}

	/**
	 * 将下划线式的名称转换成驼峰式的命名
	 *
	 *
	 * @param string $name
	 * @return string
	 */
	protected static function _camelize($name)
	{
		return strtr(ucwords(strtr($name, '_', ' ')), array(' ' => ''));
	}

	/**
	 * underscore transformation cache
	 *
	 * @var array
	 */
	private static $_underscoreCache = [];

	/**
	 * 将驼峰式的命名转换成下划线式的名称
	 *
	 * @param string $name
	 * @return string
	 */
	protected static function _underscore($name)
	{
		if (isset(self::$_underscoreCache[$name])) {
			return self::$_underscoreCache[$name];
		}
		$result = strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $name));
		self::$_underscoreCache[$name] = $result;
		return $result;
	}

	/**
	 * Check if key exists in data
	 *
	 * @param string $name
	 * @return void
	 */
	public function __isset($name)
	{
		$_name = '_'.$name;
		if (property_exists($this, $_name)) {
			return TRUE;
		}
		if (array_key_exists($name, $this->_data)) {
			return TRUE;
		}
		$method = 'get'.self::_camelize($name);
		if(method_exists($this, $method)) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * inner unset
	 *
	 * @param string $name
	 * @return void
	 */
	protected function _unset($name)
	{
		unset($this->_data[$name]);
	}

	/**
	 * 设置成员值
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function __set($name, $value)
	{
		if(empty($name)) {throw new InvalidArgumentException('invalid argument: name');}

		if ($name == static::primaryKey() and $this->{$name} !== null)
		{
			throw new InvalidArgumentException('Primary key cannot be changed.');
		}

		$old_value = array_key_exists($name, $this->_data) ? $this->__get($name) : NULL;
		$method = 'set'.self::_camelize($name);
		if(method_exists($this, $method)) {
			$this->$method($value);
			$new_value = $this->__get($name);
			$this->changed($name, $new_value, $old_value);
			return;
		}
		$this->_innerSet($name, $value);
		$this->changed($name, $value, $old_value);
	}

	/**
	 * 修改某个值时做记录
	 *
	 * @param string $name
	 * @param string $new_value
	 * @param string $old_value
	 * @return void
	 * @deprecated by changed()
	 */
	private function _setChanged($name, $new_value, $old_value)
	{
		$this->changed($name, $new_value, $old_value);
	}

	protected function changed($name = NULL, $new_value = NULL, $old_value = NULL)
	{
		if (is_null($name)) {
			return $this->_new_data;
		}

		if (is_null($new_value)) {
			return array_key_exists($name, $this->_new_data);
		}

		if (substr($name, 0, 1) == '_') return;

		if(TRUE === static::editable($name)) {
			if($new_value != $old_value) {
				$this->_old_data[$name] = $old_value;
				$this->_new_data[$name] = $new_value;
			}
		}
	}

	/**
	 * [保护]返回修改过的值
	 *
	 * @return array
	 */
	protected function _getChanged()
	{
		return $this->_new_data;
	}

	/**
	 * [保护]返回原来的值
	 *
	 * @return array
	 */
	protected function _getOriginal()
	{
		return $this->_old_data;
	}

	/**
	 *
	 *
	 * @param string $name | array
	 * @param string $value
	 * @return void
	 */
	public function set($name, $value = NULL)
	{
		if(is_array($name)) // init or load
		{
			foreach($name as $k => $v) {
				$this->__set($k, $v);
			}
		}
		elseif(is_string($name)) {

			$this->__set($name, $value);
		}
		return $this;
	}

	/**
	 * init set data value
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	protected function _innerSet($name, $value)
	{
		if ($name == 'data') return;
		$_name = '_'.$name;
		if (property_exists($this, $_name)) {
			$this->$_name = $value;
		}
		else
		{
			$this->_data[$name] = $value;
		}
	}

	/**
	 * 返回这个类是否为有效的类，以Id>0 且条目数多于1 为条件
	 *
	 * @return boolean
	 */
	public function isValid()
	{
		$pk = static::primaryKey();
		$id = $this->__get($pk);
		return count($this->_data) > 1 && $id;
	}

	/**
	 * called before save
	 */
	//protected function _preSave(& $row)
	//{
	//	return TRUE;
	//}

	/**
	 * called after save
	 */
	//protected function _postSave($ret, & $row)
	//{
	//	return $ret;
	//}

	/**
	 * 返回决定表名后缀内容的值
	 *
	 * @return string
	 **/
	public function tableValue()
	{
		if (isset(static::$_table_depend)) {
			return $this->__get(static::$_table_depend);
		}
		return NULL;
	}

	/**
	 * 保存对象的数据
	 * @return mixed
	 */
	public function save()
	{
		$pk = static::primaryKey();
		$valid = $this->isValid();
		if ($this->isNew()) {
			$row = $this->_getData();

			Log::info($row, get_called_class() . '::save new');

			$pre_ret = NULL;
			if (static::bind('before_insert')) {
				$pre_ret = static::binding('before_insert', [&$row]);
			}
			elseif (method_exists($this, '_preSave')) {
				$pre_ret = $this->_preSave($row);
			}

			if ($pre_ret === FALSE) {
				return FALSE;
			}
			if (is_array($pre_ret) && isset($pre_ret[0]) && $pre_ret[0] === FALSE) {
				return $pre_ret;
			}

			$dbo = static::dao();
			// TODO: 是否需要在插入前检查同主键是否已存在，暂未决
			$dbo->begin();
			static::_stripRelated($row, $related);
			$opt = 'id' === $pk ? ['ret_id' => static::$_primary_key] : [];
			$ret = $dbo->insert(static::table($this->tableValue()), $row, $opt);

			if (!$valid && (is_int($ret) || is_string($ret) && !empty($ret))) {
				$this->_innerSet($pk, $ret);
				//$this->_key = $this->getKey();
				Log::info($ret, get_called_class() . ' new '.$pk);
			}
			if ($ret) {
				if (static::bind('after_insert')) {
					$ret = static::binding('after_insert', [$ret, $row, $related]); // TODO: maybe to move to next 2 lines
				}
				elseif (method_exists($this, '_postSave')) {
					$ret = $this->_postSave($ret, $row, $related);
				}
				$this->isNew(FALSE);
			}
			$dbo->end();
			return $ret;
		}

		if (!$valid) {
			Log::warning('invalid model object ', get_called_class() . '::save');
			return FALSE;
		}

		$row = $this->_getChanged();

		// TODO: 添加验证！！！

		$id = $this->__get($pk);
		if (is_array($row) && count($row) > 0) {
			Log::info($row, get_called_class() . '::save change');

			$pre_ret = NULL;
			if (static::bind('before_update')) {
				$pre_ret = static::binding('before_update', [&$row]);
			}
			elseif (method_exists($this, '_preSave')) {
				$pre_ret = $this->_preSave($row);
			}

			if ($pre_ret === FALSE) {
				return FALSE;
			}
			if (is_array($pre_ret) && isset($pre_ret[0]) && $pre_ret[0] === FALSE) {
				return $pre_ret;
			}

			$dbo = static::dao();
			//$dbo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$dbo->begin();
			//$sql = "UPDATE " . static::table() . " SET ".implode('=?,',array_keys($row)).'=? WHERE ' . $pk . ' = ?';
			//$ret = Da_Wrapper::execute(static::db(), $sql, array_merge(array_values($row), array($id)));
			static::_stripRelated($row, $related);
			$ret = $dbo->update(static::table($this->tableValue()), $row, array($pk => $id));
			if ($ret) { // remove cached object
				if (static::bind('after_update')) {
					$ret = static::binding('after_update', [$ret, $row, $related]);
				}
				elseif (method_exists($this, '_postSave')) {
					$ret = $this->_postSave($ret, $row, $related);
				}

				$cache = Cache::farm(static::$_cache_pond);
				$cache->delete(static::cacheKey($id));
			}
			$dbo->end();
			return $ret;
		}

		// unchange or nothing to save
		Log::info('unchange ', get_called_class() . '::save');
		return -1;

	}

	/**
	 * 去除外键类或相关内容
	 */
	protected static function _stripRelated(array & $row, & $related = [])
	{
		$related = [];
		if (isset(static::$_rel_fields)) {
			foreach (static::$_rel_fields as $key) {
				if (isset($row[$key])) {
					$related[$key] = $row[$key];
					unset($row[$key]);
				}
			}
		}
	}

	/**
	 * 根据 id 返回用来在 Cache 中使用的本类的 键名
	 *
	 * @param int $id
	 * @return string
	 */
	public function getKey($pk = NULL)
	{
		is_null($pk) && $pk = static::primaryKey();
		return get_class($this) . '#' . $pk . '#' . $this->{$pk};
	}

	/**
	 * 检查某个字段是否可编辑
	 *
	 * @param string $name
	 * @return void
	 */
	public static function editable($name = NULL)
	{
		if (is_null($name)) return static::$_editables;
		if (is_string($name)) return in_array($name, static::$_editables);
		return FALSE;
	}

	/**
	 * 检查某个字段是否可排序
	 *
	 * @param string $name
	 * @return void
	 */
	public static function sortable($name = NULL)
	{
		if (is_null($name)) return static::$_sortables;
		if (is_string($name)) return in_array($name, static::$_sortables);
		return FALSE;
	}

	/**
	 * implements ArrayAccess
	 *
	 * @param string $offset
	 * @return boolean
	 */
	public function offsetExists($offset)
	{
		return $this->__isset($offset);
	}

	/**
	 * implements ArrayAccess
	 *
	 * @param string $offset
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		return $this->__get($offset);
	}

	/**
	 * implements ArrayAccess
	 *
	 * @param string $offset
	 * @param mixed $value
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		$this->__set($offset, $value);
	}

	/**
	 * implements ArrayAccess
	 *
	 * @param string $offset
	 * @return void
	 */
	public function offsetUnset($offset)
	{
		$this->_unset($offset);
	}

	// Serializable
	public function serialize() {
		return serialize($this->_data);
	}

	// Serializable
	public function unserialize($data) {
		$this->_data = unserialize($data);
	}

	public function jsonSerialize()
	{
		if (isset(static::$_api_keys) && !empty(static::$_api_keys)) {
			return $this->toArray(static::$_api_keys);
		}
		return $this->toArray();
	}

	/**
	 * Convert object keys to array
	 *
	 * @param array $keys
	 * @return array
	 */
	public function toArray(array $keys = array())
	{
		if (empty($keys)) {
			return $this->_getData();
		}
		$ret = array();
		foreach($keys as $k) {
			if ($this->__isset($k)) {
				$ret[$k] = $this->__get($k);
			}
			else {
				$ret[$k] = NULL;
			}
		}
		return $ret;
	}

	/**
	 * return instance string format
	 * @return string
	 */
	public function __toString()
	{
		if($this->__isset('name')) {
			return (string)$this->__get('name');
		}
		if ($this->isValid()) {
			return (string)$this->getKey();
		}
		return '';
	}

	/**
	 * Public wrapper for __toString
	 *
	 * Will use $format as an template and substitute {{key}} for attributes
	 *
	 * @param string $format
	 * @return string
	 */
	public function toString($format = '')
	{
		if (empty($format)) {
			return print_r($this->_data, TRUE);
			// return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} else {
			preg_match_all('/(\{\{[a-z0-9_]+\}\})/is', $format, $matches);
			$arr = [];
			foreach ($matches[1] as $var) {
				$arr[$var] = $this->__get(substr($var, 2, -2));
			}
			return strtr($format, $arr);
		}
	}

}

