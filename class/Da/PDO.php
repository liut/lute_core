<?php
/**
 *  Custom PDO
 *
 * @author liut
 * @version $Id$
 * @created 14:39 2011-05-18
 */

/**
 * Da_PDO
 */
class Da_PDO extends PDO
{
	private $_manual_end = FALSE;
	private $_in_transaction = FALSE;
	private $_logs = NULL;

	public $key = NULL;

	/**
	 * @deprecated
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

			static::log(empty($params) ? '' : $params, $msg);

			$this->_logs[] = $msg . (count($params) > 0 ? ' :' . Arr::dullOut($params) : '');
			if ('cli' === PHP_SAPI) {
				Log::debug($msg, __CLASS__ . ' ' . $this->key);
			}
		}
	}

	private static function log($msg, $title = '', $key = NULL)
	{
		Log::write($msg, Log::LEVEL_INFO, $title, $key, 'db');
	}

	/**
	 * begin transaction
	 */
	public function begin($manual_end = FALSE)
	{
		if ($this->_in_transaction) {
			return $this->_in_transaction;
		}
		if ($this->getAttribute(PDO::ATTR_ERRMODE) != PDO::ERRMODE_EXCEPTION) {
			$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		$this->_manual_end = $manual_end;
		$this->_log('begin');
		return $this->_in_transaction = $this->beginTransaction();
	}

	/**
	 * 结束并提交事务
	 */
	public function end($force_end = FALSE)
	{
		if ((!$this->_manual_end || $force_end) && $this->inTransaction()) {
			$this->_in_transaction = FALSE;
			$this->_log('end');
			return $this->commit();
		}
		return $this->_in_transaction;
	}

	/**
	 * 中止事务
	 */
	public function abort()
	{
		if ($this->inTransaction()) {
			$this->rollback();
			$this->_in_transaction = FALSE;
		}
	}

	/**
	 *
	 * @param string $table
	 * @param array $condition
	 * @param string $columns
	 * @param array $opt option
	 * @return mixed
	 *
	 */
	public function select($table, array $cond = array(), $columns = '*', $opt = array())
	{
		static::validateTable($table);
		//static::validateWhere($cond);

		if (empty($columns)) {
			$columns = '*';
		}

		if (is_array($columns)) {
			$columns = implode(',', $columns);
		}
		if (is_string($columns)) {
			foreach (explode(',', $columns) as $column) {
				if (!$this->validateColumn($column)) {
					throw new InvalidArgumentException('Invalid column: '. $column);
				}
			}
		}
		else {
			throw new InvalidArgumentException('Invalid columns type: '. gettype($column));
		}

		$sql = 'SELECT '.$columns.' FROM ' . $table;

		$params = [];
		if (count($cond) > 0) {
			$sql .= ' WHERE ' . self::buildCondition($cond, $params);
		}

		if (isset($opt['group_by']) && preg_match('#^[a-z][a-z0-9_\,\s\(\)]+$#i', $opt['group_by'])) {
			$sql .= ' GROUP BY ' . $opt['group_by'];
		}

		$args = [];
		if (isset($opt['having']) && !empty($opt['having'])) {
			if (is_array($opt['having']) ) {
				$sql .= ' HAVING ' . self::buildCondition($opt['having'], $args);
				$params = array_merge($params, $args);
			}
		}

		if (isset($opt['order_by'])){
			if (is_array($opt['order_by'])) {
				$opt['order_by'] = implode(',', $opt['order_by']);
			}

			if (is_string($opt['order_by']) && preg_match('#^([a-z0-9_\,\s\(\)]+)(\s+(ASC|DESC))?$#i', $opt['order_by'])) {
				$sql .= ' ORDER BY ' . $opt['order_by'];
			}
		}

		if (isset($opt['limit']) && is_numeric($opt['limit']) && $opt['limit'] > 0) {
			$offset = isset($opt['offset']) && is_numeric($opt['offset']) ? intval($opt['offset']) : 0;
			if ($this->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
				$sql .= ' LIMIT ' . $opt['limit'] . ' OFFSET ' . $offset;
			} else {
				$sql .= ' LIMIT ' . $offset . ', ' . $opt['limit'];
			}
		}
		$sql .= ';';
		//echo $sql, print_r($params, true), PHP_EOL;
		isset($opt['fetch']) || $opt['fetch'] = 'all';
		$this->_log('select: '.$opt['fetch'].': '.$sql, $params);
		$sth = $this->prepare($sql);
		if($sth && $ret = $sth->execute($params)) {

			switch ($opt['fetch']) {
				case 'row':
					return $sth->fetch(PDO::FETCH_ASSOC);
				case 'one':
					return $sth->fetchColumn(0);
				case 'flat':
					return $sth->fetchAll(PDO::FETCH_COLUMN, 0);
				case 'group':
					return $sth->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);
				case 'fold': // 按指定主键分组
					$data = [];
					while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
						$pkv = isset($opt['fold_key']) && isset($row[$opt['fold_key']]) ? $row[$opt['fold_key']] : reset($row);
						$data[$pkv] = $row;
					}
					return $data;
				default: // all
					return $sth->fetchAll(PDO::FETCH_ASSOC);
			}
		}

		if(!$sth || isset($ret) && !$ret) {
			Log::warning(__CLASS__ . '::'. __FUNCTION__ .' : '.print_r($this->errorInfo(),true) . "\n" . ': ' . $sql);
		}
		return false;
	}

	public function validateColumn($column)
	{
		return preg_match('/(?: (?:`?\w+`?\.)? (?:`)?([a-z0-9_\(\)\+]+|\*)(?:`)? (?:\s*as\s*\w+)?\s*)+/ix', $column);
	}

	/**
	 * 根据指定条件更新一条数据
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $cond condition
	 * @return boolean
	 */
	public function update($table, array $data, array $cond = array())
	{
		static::validateTable($table);
		static::validateData($data);
		static::validateWhere($cond);
		$sql = 'UPDATE '.$table.' SET ';
		$sql .= self::buildAssignment($data);
		$params = [];
		if (count($cond) > 0) {
			$sql .= ' WHERE ' . self::buildCondition($cond, $params);
		}
		$sql .= ';';
		$this->_log('update: '.$sql, $params);
		$arr = array_merge(array_values($data), $params);
		return $this->execute($sql, $arr);
	}

	/**
	 * 插入一条数据
	 *
	 * @param string $table
	 * @param array $data
	 * @return boolean
	 */
	public function insert($table, array $data, $opt = array())
	{
		static::validateTable($table);
		static::validateData($data);
		$cols = array_keys($data);
		$func = function($k){
			return ':'.$k;
		};
		$sql = 'INSERT INTO '.$table.'('.implode(',',$cols).') VALUES('.implode(',',array_map($func, $cols)).')';

		if (isset($opt['ret_id']) && is_string($opt['ret_id']) && ctype_alpha($opt['ret_id'])) {
			$sql .= ' RETURNING ' . $opt['ret_id'];
			return $this->getOne($sql, $data);
		}

		$this->_log('insert: '.$sql, $data);
		$ret = $this->execute($sql, $data);

		if ($ret && !isset($data['id'])) {
			$new_id = 0;
			// TODO: check database engine for acquire last insert id
			if ($this->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
				$_seq = $table . '_id_seq';
				if ($this->checkSeq($_seq)) {
					$new_id = $this->lastInsertId($_seq);
				} else $new_id = TRUE;
				unset($_seq);
			} else {
				$new_id = $this->lastInsertId();
			}
			if (is_numeric($new_id)) return (int)$new_id;
			return $new_id;
		}
		return $ret;
	}

	/**
	 * check sequence exists
	 *
	 * @param string $seq
	 * @return boolean
	 **/
	public function checkSeq($seq)
	{
		$sql = 'SELECT start_value FROM information_schema.sequences WHERE ';
		$cond = explode('.', $seq, 2);
		if (count($cond) === 2) {
			$sql .= 'sequence_schema = ? AND sequence_name = ?';
		} else {
			$sql .= 'sequence_name = ?';
		}
		$value = $this->getOne($sql, $cond);
		if ($value) return TRUE;
		return FALSE;
	}

	/**
	 * Count all of the rows in the table.
	 *
	 * @param   string  Special table
	 * @param   array   Query where clause(s)
	 * @param   string  Column to count by
	 * @param   bool	Whether to count only distinct rows (by column)
	 * @return  int	 The number of rows OR false
	 */
	public function count($table, array $cond = array(), $column = 'id', $distinct = FALSE)
	{
		if (FALSE === $distinct && empty($cond) && 'id' == $column && $this->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
			return (int)$this->getOne("SELECT reltuples FROM pg_class WHERE oid = '".$table."'::regclass;");
		}
		$columns = 'COUNT('.($distinct ? 'DISTINCT ' : '').$column.')';
		$ret = $this->select($table, $cond, $columns, array('fetch' => 'one'));
		if (is_null($ret)) return FALSE;
		return (int)$ret;
	}

	/**
	 * 根据指定条件删除数据
	 *
	 * @param string $table
	 * @param array $cond condition
	 * @return boolean
	 */
	public function delete($table, array $cond = array())
	{
		static::validateTable($table);
		static::validateWhere($cond);
		$sql = 'DELETE FROM ' . $table;
		$params = [];
		if (count($cond) > 0) {
			$sql .= ' WHERE ' . self::buildCondition($cond, $params);
		}
		$sql .= ';';
		Log::debug($cond, __METHOD__ . ' ' . $table);
		$this->_log('delete: '.$sql, $params);
		return $this->execute($sql, $params);
	}

	/**
	 *
	 * 直接执行一条SQL，如 UPDATE、INSERT、DELETE等
	 *
	 * @param string $sql
	 * @param array $params
	 * @return boolean
	 */
	public function execute($sql, $params = NULL)
	{
		$this->_log('execute: '.$sql, $params);
		if(is_null($params) || ! is_array($params) || count($params) == 0) {
			return $this->exec($sql);
		}
		$sth = $this->prepare($sql);
		// TODO: check $sth
		if($sth) return $sth->execute($params);

		Log::warning(__CLASS__ . '::'. __FUNCTION__ .' : '.print_r($this->errorInfo(),true) . ': ' . $sql);
		return false;
	}

	/**
	 * 返回一行作为数组
	 *
	 * @param string $sql
	 * @param array $params
	 * @param int $fetch_style
	 * @return array
	 */
	public function getRow($sql, $params = null, $fetch_style = PDO::FETCH_ASSOC)
	{
		$this->_log('getRow: '.$sql, $params);
		if(is_null($params) || ! is_array($params) || count($params) == 0) {
			$sth = $this->query($sql);
			if($sth) return $sth->fetch($fetch_style);
		} else {
			$sth = $this->prepare($sql);
			if($sth && $ret = $sth->execute($params)) {
				return $sth->fetch($fetch_style);
			}
		}
		if(!$sth || isset($ret) && !$ret) {
			Log::warning(__CLASS__ . '::'. __FUNCTION__ .' : '.print_r($this->errorInfo(),true) . "\n" . ': ' . $sql);
		}
		return false;
	}

	/**
	 * 返回全部结果集
	 *
	 * @param string $sql
	 * @param array $params
	 * @param int $fetch_style
	 * @return array
	 */
	public function getAll($sql, $params = null, $fetch_style = PDO::FETCH_ASSOC)
	{
		$this->_log('getAll: '.$sql, $params);
		if(is_null($params) || ! is_array($params) || count($params) == 0) {
			$sth = $this->query($sql);
			if($sth) return $sth->fetchAll($fetch_style);
		} else {
			$sth = $this->prepare($sql);
			if($sth && $ret = $sth->execute($params)) {
				return $sth->fetchAll($fetch_style);
			}
		}
		if(!$sth || isset($ret) && !$ret) {
			Log::warning(__CLASS__ . '::'. __FUNCTION__ .' : '.print_r($this->errorInfo(),true) . "\n" . ': ' . $sql);
		}
		return false;
	}

	/**
	 * 返回第一行第一列的值
	 *
	 * @param string $sql
	 * @param array $params
	 * @return string
	 */
	public function getOne($sql, $params = NULL)
	{
		$this->_log('getOne: '.$sql, $params);
		if(is_null($params) || ! is_array($params) || count($params) == 0) {
			$sth = $this->query($sql);
			if($sth) return $sth->fetchColumn(0);
		} else {
			$sth = $this->prepare($sql);
			if($sth && $ret = $sth->execute($params)) {
				return $sth->fetchColumn(0);
			}
		}
		return FALSE;
	}

	/**
	 * 返回所有行第一列的值作为数组，或返回分组
	 *
	 * @param string $sql
	 * @param array $params
	 * @param boolean $group 是否按第一列分组
	 * @return array
	 */
	public function getFlat($sql, $params = NULL, $group = FALSE)
	{
		$this->_log('getFlat: '.$sql, $params);
		if(is_null($params) || ! is_array($params) || count($params) == 0) {
			$sth = $this->query($sql);
			if($sth) return $group ? $sth->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP) : $sth->fetchAll(PDO::FETCH_COLUMN, 0);
		} else {
			$sth = $this->prepare($sql);
			if($sth && $ret = $sth->execute($params)) {
				return $group ? $sth->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP) : $sth->fetchAll(PDO::FETCH_COLUMN, 0);
			}
		}
		if(!$sth) {
			Log::warning(__CLASS__ . '::'. __FUNCTION__ .' : '.print_r($this->errorInfo(),true) . "\n" . ': ' . $sql);
		}
		return FALSE;
	}


	/**
	 *
	 */
	public static function buildAssignment( array & $ht, $isWhere = FALSE, $pad = ',')
	{
		$arr = array();
		foreach($ht as $k => $v) {
			$str = $k;
			if ($isWhere && is_null($v)) {
				$str .= ' IS NULL';
				unset($ht[$k]);
			} else {
				if ($isWhere && is_array($v) && in_array($v[0], array('=', '>' , '<', '>=', '<=', '<>', '!=')) ) {
					$str .= $v[0] . '?';
					$ht[$k] = $v[1];
				} else {
					$str .= '=?';
					// TODO:: 实现 incr 和 decr， 即 a=a+1, a=a-1
				}

			}
			$arr[] = $str;
		}

		return implode($pad, $arr);
	}

	/**
	 * build where statement
	 * for replace buildAssignment(array, true)
	 *
	 * @param array $where
	 * @param array & $params
	 * @return string
	 *
	 */
	public static function buildCondition(array $where, array & $params)
	{
		$arr = [];
		$params = [];
		foreach ($where as $k => $v) {
			$str = $k . ' ';
			if (is_null($v)) {
				$str .= 'IS NULL';
			}
			elseif (is_array($v)) {
				if (count($v) == 0) {
					Log::error($where, __METHOD__);
					throw new Exception("Error condition array", 1);
				}

				// 数字字段特殊处理，允许省略 IN 关键字
				if (count($v) > 1 && is_numeric($v[0]) && is_numeric($v[1])) {
					$op = 'IN';
				}
				elseif (count($v) == 1) {
					$op = '=';
				}
				else {
					$op = strtoupper(array_shift($v));
				}

				// 兼容旧版的查询条件
				if (count($v) == 1 && is_array($v[0])) {
					$v = $v[0];
				}

				if (($op == 'BETWEEN' || $op == 'OVERLAPS' || $op == '@@') && count($v) < 2) {
					throw new Exception('BETWEEN, OVERLAPS or @@ need two arguments');
				}

				// 数组输入使用 '{,}'
				if (in_array($op, ['=', '>' , '<', '>=', '<=', '<>', '!=', 'LIKE', 'ILIKE', '@>', '<@', '&&'])) {
					$str .= $op . ' ?';
					$params[] = reset($v);
				}
				elseif (in_array($op, ['IN','NOT IN']) && count($v) > 0) {
					if (isset($v[0]) && is_array($v[0])) {
						$v = reset($v);
					}
					$str .= $op . '(';
					$tmp = '';
					foreach ($v as $val) {
						if (!is_scalar($val)) {
							Log::warning('Invalid value ' . $val . ' for ' . $k, __METHOD__);
						}
						$str .= $tmp . '?'; $tmp = ',';
						$params[] = $val;
					}
					$str .= ')';
				}
				elseif ($op == 'BETWEEN' && count($v) > 1) {
					$str .= $op . ' ? AND ?';
					$params[] = $v[0]; $params[] = $v[1];
				}
				elseif ($op == 'OVERLAPS' && count($v) > 1) {
					$str .= $op . ' (?, ?)';
					$params[] = $v[0]; $params[] = $v[1];
				}
				elseif ($op == '@@' && count($v) > 1) { // TODO: 添加支持 @>, <@, 和 to_tsquery
					# code..."kw_vector", '@@', "plainto_tsquery('".self::FTS_CONFIG."', '$kw') "
					$str .= $op . ' to_tsquery(?, ?)';
					$params[] = $v[0]; $params[] = $v[1];
				}
				else {
					Log::warning('Invalid op "' . $op . '" or value error', __METHOD__);
					Log::notice($v, __METHOD__);
				}
			}
			elseif (is_scalar($v)) {
				$str .= '=?';
				$params[] = $v;
			}
			else {
				Log::warning('condition ' . $k . ' value is invalid', __METHOD__);
			}
			$arr[] = $str;
		}
		return implode(' AND ', $arr);
	}

	/**
	 *
	 */
	private static function validateData(array $arr)
	{
		if (empty($arr)) {
			throw new InvalidArgumentException('Invalid input array: empty');
		}
		foreach($arr as $k => $v) {
			if (!is_string($k) || is_numeric($k)) {
				throw new InvalidArgumentException('Invalid key type: not a string');
			}
		}
	}

	/**
	 *
	 */
	private static function validateWhere(array $arr)
	{
		if (empty($arr)) {
			throw new InvalidArgumentException('Invalid input array: empty');
		}
	}

	/**
	 *
	 */
	private static function validateTable($table)
	{
		if (!is_string($table)) throw new InvalidArgumentException('Invalid table name: empty');
		if (!preg_match('#^[a-z][a-z0-9_\.]+$#i', $table)) {
			throw new InvalidArgumentException('Invalid table name: '.$table);
		}
	}

}
