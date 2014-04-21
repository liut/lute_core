<?PHP


/**
 * The Arr class provides a few nice functions for making
 * dealing with arrays easier
 *
 * @package core
 * @author liut
 **/
class Arr
{

	/**
	 * Gets a dot-notated key from an array, with a default value if it does
	 * not exist.
	 *
	 * @param   array   $array    The search array
	 * @param   mixed   $key      The dot-notated key or array of keys
	 * @param   string  $default  The default value
	 * @return  mixed
	 */
	public static function get($array, $key, $default = NULL)
	{
		if ( ! is_array($array) and ! $array instanceof \ArrayAccess)
		{
			throw new \InvalidArgumentException('First parameter must be an array or ArrayAccess object.');
		}

		if (is_null($key))
		{
			return $array;
		}

		if (is_array($key))
		{
			$return = array();
			foreach ($key as $k)
			{
				$return[$k] = static::get($array, $k, $default);
			}
			return $return;
		}

		foreach (explode('.', $key) as $key_part)
		{
			if (($array instanceof \ArrayAccess and isset($array[$key_part])) === false)
			{
				if ( ! is_array($array) or ! array_key_exists($key_part, $array))
				{
					return $default;
				}
			}

			$array = $array[$key_part];
		}

		return $array;
	}

	/**
	 * Set an array item (dot-notated) to the value.
	 *
	 * @param   array   $array  The array to insert it into
	 * @param   mixed   $key    The dot-notated key to set or array of keys
	 * @param   mixed   $value  The value
	 * @return  void
	 */
	public static function set(&$array, $key, $value = NULL)
	{
		if (is_null($key))
		{
			$array = $value;
			return;
		}

		if (is_array($key))
		{
			foreach ($key as $k => $v)
			{
				static::set($array, $k, $v);
			}
		}
		else
		{
			$keys = explode('.', $key);

			while (count($keys) > 1)
			{
				$key = array_shift($keys);

				if ( ! isset($array[$key]) or ! is_array($array[$key]))
				{
					$array[$key] = array();
				}

				$array =& $array[$key];
			}

			$array[array_shift($keys)] = $value;
		}
	}

	/**
	 * Pluck an array of values from an array.
	 *
	 * @param  array   $array  collection of arrays to pluck from
	 * @param  string  $key    key of the value to pluck
	 * @param  string  $index  optional return array index key, true for original index
	 * @return array   array of plucked values
	 */
	public static function pluck($array, $key, $index = NULL)
	{
		$return = array();
		$get_deep = strpos($key, '.') !== false;

		if ( ! $index)
		{
			foreach ($array as $i => $a)
			{
				$return[] = (is_object($a) and ! ($a instanceof \ArrayAccess)) ? $a->{$key} :
					($get_deep ? static::get($a, $key) : $a[$key]);
			}
		}
		else
		{
			foreach ($array as $i => $a)
			{
				$index !== true and $i = (is_object($a) and ! ($a instanceof \ArrayAccess)) ? $a->{$index} : $a[$index];
				$return[$i] = (is_object($a) and ! ($a instanceof \ArrayAccess)) ? $a->{$key} :
					($get_deep ? static::get($a, $key) : $a[$key]);
			}
		}

		return $return;
	}

	/**
	 * Array_key_exists with a dot-notated key from an array.
	 *
	 * @param   array   $array    The search array
	 * @param   mixed   $key      The dot-notated key or array of keys
	 * @return  mixed
	 */
	public static function key_exists($array, $key)
	{
		foreach (explode('.', $key) as $key_part)
		{
			if ( ! is_array($array) or ! array_key_exists($key_part, $array))
			{
				return false;
			}

			$array = $array[$key_part];
		}

		return true;
	}

	/**
	 * Unsets dot-notated key from an array
	 *
	 * @param   array   $array    The search array
	 * @param   mixed   $key      The dot-notated key or array of keys
	 * @return  mixed
	 */
	public static function delete(&$array, $key)
	{
		if (is_null($key))
		{
			return false;
		}

		if (is_array($key))
		{
			$return = array();
			foreach ($key as $k)
			{
				$return[$k] = static::delete($array, $k);
			}
			return $return;
		}

		$key_parts = explode('.', $key);

		if ( ! is_array($array) or ! array_key_exists($key_parts[0], $array))
		{
			return false;
		}

		$this_key = array_shift($key_parts);

		if ( ! empty($key_parts))
		{
			$key = implode('.', $key_parts);
			return static::delete($array[$this_key], $key);
		}
		else
		{
			unset($array[$this_key]);
		}

		return true;
	}

	/**
	 * Merge 2 arrays recursively, differs in 2 important ways from array_merge_recursive()
	 * - When there's 2 different values and not both arrays, the latter value overwrites the earlier
	 *   instead of merging both into an array
	 * - Numeric keys that don't conflict aren't changed, only when a numeric key already exists is the
	 *   value added using array_push()
	 *
	 * @param   array  multiple variables all of which must be arrays
	 * @return  array
	 * @throws  \InvalidArgumentException
	 */
	public static function merge()
	{
		$array  = func_get_arg(0);
		$arrays = array_slice(func_get_args(), 1);

		if ( ! is_array($array))
		{
			throw new \InvalidArgumentException('Arr::merge() - all arguments must be arrays.');
		}

		foreach ($arrays as $arr)
		{
			if ( ! is_array($arr))
			{
				throw new \InvalidArgumentException('Arr::merge() - all arguments must be arrays.');
			}

			foreach ($arr as $k => $v)
			{
				// numeric keys are appended
				if (is_int($k))
				{
					array_key_exists($k, $array) ? array_push($array, $v) : $array[$k] = $v;
				}
				elseif (is_array($v) and array_key_exists($k, $array) and is_array($array[$k]))
				{
					$array[$k] = static::merge($array[$k], $v);
				}
				else
				{
					$array[$k] = $v;
				}
			}
		}

		return $array;
	}

	/**
	 * 将数组返回成一维字串格式
	 * @param array $arr
	 */
	public static function dullOut(array $arr)
	{
		$ret = [];
		foreach ($arr as $key => $value) {
			if (is_scalar($value)) {
				$ret[] = ''.$key.'=>'.(string)$value;
			}
			else {
				$ret[] = ''.$key.'=>'.gettype($value).(is_array($value)?'('.count($value).')':'');
			}
		}
		return '[' . implode(',', $ret) . ']';
	}

} // END class 