<?PHP

/**
 * Model support mongodb
 *
 * @package core
 * @author liut
 **/
abstract class Model_Mongo extends Model
{
	/**
	 * Mongo Collection 的默认主键名
	 *
	 * @var string
	 **/
	// protected static $_primary_key = '_id';

	public function getId()
	{
		if (!array_key_exists('_id', $this->_data)) {
			return NULL;
		}
		$id = $this->_data['_id'];

		if (is_array($id)) {
			$id = (object) $id;
		}

		if ($id instanceof MongoId) {
			return $id;
		}
		if (is_object($id) && isset($id->{'$id'})) {
			$id = $id->{'$id'};
			return new MongoId($id);
		}
		if (is_string($id)) {
			return new MongoId($id);
		}

		return $id;
	}

	/**
	 * called before save
	 */
	protected function _preSave(& $row)
	{
		if ($this->isNew()) {
			if (array_key_exists('_id', $row) && is_null($row['_id'])) {
				unset($row['_id']);
			}

			if (!isset($row['created'])) {
				$row['created'] = new MongoDate();
			}
		}
		else {
			if (!isset($row['updated'])) {
				$row['updated'] = new MongoDate();
			}
		}

		return TRUE;		
	}

	/**
	 * called after save
	 */
	protected function _postSave($ret, $row, $related)
	{
		if ($this->isNew()) {
			if (isset($row['_id'])) {
				$this->_innerSet('_id', $row['_id']);
				$this->_unset('id');
			}
		}

		return $ret;
	}

	/**
	 * Convert object keys to array
	 *
	 * @param array $keys
	 * @return array
	 */
	public function toArray(array $keys = array())
	{
		$ret = parent::toArray($keys);
		foreach ($ret as &$value) {
			if ($value instanceof MongoId) {
				$value = (string)$value;
			}
			elseif ($value instanceof MongoDate) {
				$value = date('Y-m-d H:i:s', $value->sec);
			}
		}
		return $ret;
	}
} // END class 