<?PHP

/**
 * undocumented class
 *
 * @package core
 * @author liut
 **/
class Da_Mongo extends MongoClient
{
	private $_logs = NULL;

	public $key = NULL; // unnecessary

	private $_db;

	private static $_conds = [
		'>'  => '$gt',
		'>=' => '$gte',
		'<'  => '$lt',
		'<=' => '$lte',
		'IN' => '$in',
		'NOT IN' => '$nin',
	];

	/**
	 * constructor
	 *
	 * @param mixed string $server  = "mongodb://localhost:27017"
	 * @param mixed array $options
	 */
	public function __construct($server, $db, array $options = array("connect" => TRUE))
	{
		if (empty($db)) {
			throw new InvalidArgumentException('db is empty');
		}

		Log::debug($server . ' ' . $db, __METHOD__);

		parent::__construct($server, $options);

		$this->_db = $db;
	}

	public function getDb()
	{
		return $this->selectDB($this->_db);
	}

	/**
	 *
	 * @param string $table
	 * @param array $condition
	 * @param string $columns
	 * @param array $options
	 * @return mixed
	 *
	 */
	public function select($table, array $cond = array(), $columns = '*', $opt = array())
	{
		if (is_array($columns)) {
			$fields = $columns;
		}
		elseif (is_string($columns) && strpos($columns, ',')) {
			$fields = array_map('trim', explode(',', $columns));
		}
		else {
			$fields = [];
		}

		$fetch = isset($opt['fetch']) ? $opt['fetch'] : 'all';
		$this->_log('select: '.$fetch.'.'.$table.':'.implode(',', $fields), $cond);

		$query = static::fixCondition($cond);

		if (defined('_DB_DEBUG') && TRUE === _DB_DEBUG) {
			Log::debug(compact('table', 'query', 'fields', 'opt'), __METHOD__);
		}

		$collection = $this->selectCollection($this->_db, $table);

		if ($fetch == 'row' && !isset($opt['order_by']) && !isset($opt['offset'])) {
			return $collection->findOne($query, $fields);
		}

		if ($fetch == 'one' && !isset($opt['order_by']) && !isset($opt['offset'])) {
			$row = $collection->findOne($query, $fields);
			return reset($row);
		}

		$cursor = $collection->find($query, $fields)->slaveOkay();

		if (isset($opt['order_by']) && is_string($opt['order_by'])) {
			$sort = [];
			foreach (explode(',', $opt['order_by']) as $line) {
				if (preg_match("/^([a-z][a-z0-9_]+)\s+(ASC|DESC)$/i", trim($line), $match)) {
					$sort[$match[1]] = strtoupper($match[2]) == 'DESC' ? MongoCollection::DESCENDING : MongoCollection::ASCENDING;
				}
			}
			$sort && $cursor->sort($sort);
		}

		if (isset($opt['limit']) && is_numeric($opt['limit'])) {
			if ($opt['limit'] > 0) {
				$cursor->limit((int)$opt['limit']);
			}

			if (isset($opt['offset']) && is_numeric($opt['offset']) && $opt['offset'] > 0) $cursor->skip((int)$opt['offset']);
		}

		if ($fetch == 'cursor') {
			return $cursor;
		}

		if ($fetch == 'row') {
			return $cursor->getNext();
		}

		if ($fetch == 'one') {
			$row = $cursor->getNext();
			return reset($row);
		}

		// TODO: support fetch == flat, fold, group

		// fetch == all
		return iterator_to_array($cursor);
	}

	/**
	 * 根据指定条件更新一条数据
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $cond condition
	 * @return boolean
	 */
	public function update($table, array $data, array $cond = array(), array $options = [])
	{
		$collection = $this->selectCollection($this->_db, $table);
		if (!isset($options['upsert'])) {
			$data = ['$set' => $data];
		}

		$query = static::fixCondition($cond);

		$ret = $collection->update($query, $data, $options);

		if (is_array($ret)) {
			if (isset($ret['err'])) {
				Log::warning($ret, __CLASS__.'::'.__METHOD__);
			}
		}

		return $ret;
	}

	/**
	 * 插入一条数据
	 *
	 * @param string $table
	 * @param array $data
	 * @return boolean
	 */
	public function insert($table, array $data, array $options = [])
	{
		$collection = $this->selectCollection($this->_db, $table);
		$ret = $collection->insert($data, $options);

		if (is_array($ret)) {
			// $ret['err'] is null if no error
			// return MongoId _id
			if (!isset($ret['err']) && isset($data['_id']) && $data['_id'] instanceof MongoId) {
				return $data['_id'];
			}

			if (isset($ret['err'])) {
				Log::warning($ret, __CLASS__.'::'.__METHOD__);
			}
		}

		return $ret;
	}

	/**
	 * 根据指定条件删除数据
	 *
	 * @param string $table
	 * @param array $cond condition
	 * @return boolean
	 */
	public function delete($table, array $cond = array(), array $options = [])
	{
		$collection = $this->selectCollection($this->_db, $table);

		$query = static::fixCondition($cond);

		$ret = $collection->remove($query, $options);

		if (is_array($ret)) {
			if (isset($ret['err'])) {
				Log::warning($ret, __CLASS__.'::'.__METHOD__);
			}
		}

		return $ret;
	}

	/**
	 * 统计行数
	 * @param string $table
	 * @param array $cond
	 * @return int
	 */
	public function count($table, array $cond = array())
	{
		$collection = $this->selectCollection($this->_db, $table);

		Log::debug($cond, __METHOD__ . ' ' . $table);

		$query = static::fixCondition($cond);

		return $collection->count($query);
	}

	public function group($table, $key, array $cond = array())
	{
		$collection = $this->selectCollection($this->_db, $table);

		Log::debug($cond, __METHOD__ . ' ' . $table . ' ' . $key);

		$keys = array($key => true);

		// set intial values
		$initial = array("count" => 0);

		// JavaScript function to perform
		$reduce = "function (doc, out) { out.count ++; }";

		$query = static::fixCondition($cond);

		$query[$key] = ['$exists' => true];

		$group = $collection->group($keys, $initial, $reduce, ['condition' => $query]);

		$data = array();
		foreach($group["retval"] as $row) {
			$v = $row[$key];
			if (is_array($v)) {
				//$v = reset($v);
				foreach ($v as $nv) {
					if (!isset($data[$nv])) {
						$data[$nv] = $row['count'];
					} else {
						$data[$nv] += $row['count'];
					}
				}
			} else {
				if (!isset($data[$v])) {
					$data[$v] = $row['count'];
				} else {
					$data[$v] += $row['count'];
				}
			}
			//$data[] = ['v'=> $v, 'c' => $row['count']];
		}
		return $data;
	}

	public function errorInfo()
	{
		# TODO: support return error is unnecessary
	}

	public function begin()
	{
		# code...
	}

	public function end()
	{
		# code...
	}

	public function abort()
	{
		# code...
	}

	/**
	 * build where statement
	 * for replace buildAssignment(array, true)
	 *
	 * @param array $cond
	 * @return void
	 *
	 */
	public static function fixCondition(array & $cond)
	{
		array_walk($cond, 'static::_fixConditionItem');
		return $cond;
	}

	private static function _fixConditionItem(& $v, $k)
	{
		if (is_array($v)) {
			if (count($v) <= 1) {
				throw new Exception("Error condition array", 1);
			}
			$op = array_shift($v);
			if ($k == '_id') {
				$v = array_map('static::_filterId', $v);
			}

			if (isset(static::$_conds[$op])) {
				if ($v[0] instanceof MongoId) {
					$v = [static::$_conds[$op] => $v[0]];
				} elseif ($v[0] instanceof MongoDate) {
					$v = [static::$_conds[$op] => $v[0]];
				} else {
					$v = [static::$_conds[$op] => $v];
				}
			}
		}
		elseif (is_string($v)) {
			if ($k == '_id') {
				$v = static::_filterId($v);
			}
		}
		// TODO: more others
	}

	private static function _filterId($id)
	{
		if ($id instanceof MongoId) {
			return $id;
		}

		return new MongoId($id);
	}

	/**
	 * @return array
	 */
	public function getLogs()
	{
		return $this->_logs;
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

} // END class
