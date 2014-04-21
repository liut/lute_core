<?PHP


/**
 * Validation
 *
 * @package core
 * @author liut
 **/
class Validation
{

	/**
	 * 
	 * @param array $rules
	 * @return self
	 */
	public static function farm($key = 'default')
	{
		static $instances = [];
		if (!isset($instances[$key])) {
			$instances[$key] = new static();
		}
		return $instances[$key];
	}

	/**
	 * @var  array  contains values of fields
	 */
	private $fields = [];

	/**
	 * @var  array  contains values of fields that validated successfully
	 */
	protected $validated = [];

	/**
	 * @var  array  contains Validation_Error instances of encountered errors
	 */
	protected $errors = [];

	/**
	 * @var  array  contains validation error messages, will overwrite those from lang files
	 */
	protected $error_messages = [];

	/**
	 * @var  array  contains a list of classnames and objects that may contain validation methods
	 */
	protected $callables = [];

	/**
	 * @var  array or object available after validation started running: contains given input values
	 */
	protected $input = NULL;

	/**
	 * @var  is support mbstring
	 */
	protected $mbstring = FALSE;

	/**
	 * constructor
	 * 
	 * @param array
	 * 
	 */
	protected function __construct()
	{
		$this->callables = [$this];
	}


	/**
	 * add field and rule
	 *
	 * @return  this
	 */
	public function add($name, $label, $rules)
	{
		$field = new Validation_Field($name, $label);

		is_array($rules) || $rules = explode('|', $rules);
		foreach ($rules as $rule)
		{
			if (($pos = strpos($rule, '[')) !== false)
			{
				preg_match('#\[(.*)\]#', $rule, $param);
				$rule = substr($rule, 0, $pos);

				// deal with rules that have comma's in the rule parameter
				if (in_array($rule, array('match_pattern')))
				{
					call_user_func_array(array($field, 'addRule'), array_merge(array($rule), array($param[1])));
				}
				elseif (in_array($rule, array('valid_string')))
				{
					call_user_func_array(array($field, 'addRule'), array_merge(array($rule), array(explode(',', $param[1]))));
				}
				else
				{
					call_user_func_array(array($field, 'addRule'), array_merge(array($rule), explode(',', $param[1])));
				}
			}
			else
			{
				$field->addRule($rule);
			}
		}

		$this->fields[$name] = $field;

		return $this;
	}

	/**
	 * Run validation
	 *
	 * Performs validation with current fieldset and on given input, will try POST
	 * when input wasn't given.
	 *
	 * @param   array  input that overwrites POST values
	 * 
	 * @return  bool   whether validation succeeded
	 */
	public function run($input = NULL, $allow_partial = false)
	{
		$this->validated = [];
		$this->errors = [];
		$this->input = $input ?: [];
		$fields = $this->fields;
		foreach($fields as $field)
		{
			$value = $this->input($field->name);
			if (($allow_partial === TRUE and $value === NULL)
				or (is_array($allow_partial) and ! in_array($field->name, $allow_partial)))
			{
				continue;
			}
			try
			{
				foreach ($field->rules as $rule)
				{
					$callback  = $rule[0];
					$params    = $rule[1];
					$this->_runRule($callback, $value, $params, $field);
				}
				$this->validated[$field->name] = $value;
			}
			catch (Validation_Error $v)
			{
				$this->errors[$field->name] = $v;
			}
		}

		return empty($this->errors);
	}

	/**
	 * Fetches the input value from either post or given input
	 *
	 * @param   string
	 * @param   mixed
	 * @return  mixed|array  the input value or full input values array
	 */
	public function input($key = NULL, $default = NULL)
	{
		if ($key === NULL)
		{
			return $this->input;
		}

		if ($this->input instanceof Request && $this->input->$key !== NULL) {
			return $this->input->$key;
		}

		if ( is_array($this->input) && array_key_exists($key, $this->input))
		{
			return $this->input[$key];
		}

		return $default;
	}

	/**
	 * Validated
	 *
	 * Returns specific validated value or all validated field=>value pairs
	 *
	 * @param   string  fieldname
	 * @param   mixed   value to return when not validated
	 * @return  mixed|array  the validated value or full validated values array
	 */
	public function validated($field = NULL, $default = FALSE)
	{
		if ($field === NULL)
		{
			return $this->validated;
		}

		return array_key_exists($field, $this->validated) ? $this->validated[$field] : $default;
	}

	/**
	 * Error
	 *
	 * Return specific error or all errors thrown during validation
	 *
	 * @param   string  fieldname
	 * @param   mixed   value to return when not validated
	 * @return  Validation_Error|array  the validation error object or full array of error objects
	 */
	public function error($field = NULL, $default = FALSE)
	{
		if ($field === NULL)
		{
			return $this->errors;
		}

		return array_key_exists($field, $this->errors) ? $this->errors[$field] : $default;
	}

	/**
	 * Get Field instance
	 *
	 * @param   string|null           field name or null to fetch an array of all
	 * @return  Validation_Field|false  returns false when field wasn't found
	 */
	public function field($name = NULL)
	{
		if ($name === NULL) {
			return $this->fields;
		}

		if (array_key_exists($name, $this->fields))
		{
			return $this->fields[$name];
		}

		return FALSE;
	}

	/**
	 * Fetches a specific error message for this validation instance
	 *
	 * @param   string
	 * @return  string
	 */
	public function getMessage($rule)
	{
		if (array_key_exists($rule, $this->error_messages))
		{
			return $this->error_messages[$rule];
		}

		return FALSE;
	}

	/**
	 * This will overwrite lang file messages for this validation instance
	 *
	 * @param   string
	 * @param   string
	 * @return  Validation  this, to allow chaining
	 */
	public function setMessage($rule, $message)
	{
		if ($message !== NULL)
		{
			$this->error_messages[$rule] = $message;
		}
		else
		{
			unset($this->error_messages[$rule]);
		}

		return $this;
	}

	public function mbstring($mbstring = NULL)
	{
		if (is_null($mbstring)) {
			return $this->mbstring;
		}

		$this->mbstring = $mbstring;
	}

	/**
	 * Run rule
	 *
	 * Performs a single rule on a field and its value
	 *
	 * @param   callback
	 * @param   mixed     Value by reference, will be edited
	 * @param   array     Extra parameters
	 * @param   array     Validation field description
	 * @throws  Validation_Error
	 */
	protected function _runRule($rule, &$value, $params, $field)
	{
		if (($rule = $this->_findRule($rule)) === FALSE)
		{
			return;
		}

		$output = call_user_func_array(reset($rule), array_merge(array($value), $params));

		if ($output === FALSE && $value !== FALSE)
		{
			throw new Validation_Error($this, $field, $value, $rule, $params);
		}
		elseif ($output !== TRUE)
		{
			$value = $output;
		}
	}

	/**
	 * Takes the rule input and formats it into a name & callback
	 *
	 * @param   string|array  short rule to be called on Validation callables array or full callback
	 * @return  array|bool    rule array or false when it fails to find something callable
	 */
	protected function _findRule($callback)
	{
		// Rules are validated and only accepted when given as an array consisting of
		// array(callback, params) or just callbacks in an array.
		if (is_string($callback))
		{
			$callback_method = '_validate_'.$callback;
			foreach ($this->callables as $callback_class)
			{
				if (method_exists($callback_class, $callback_method))
				{
					return array($callback => array($callback_class, $callback_method));
				}
			}
		}

		// when no callable function was found, try regular callbacks
		if (is_callable($callback))
		{
			if ($callback instanceof Closure)
			{
				$callback_name = 'closure';
			}
			elseif (is_array($callback))
			{
				$callback_name = preg_replace('#^([a-z_]*\\\\)*#i', '',
					is_object($callback[0]) ? get_class($callback[0]) : $callback[0]).':'.$callback[1];
			}
			else
			{
				$callback_name = preg_replace('#^([a-z_]*\\\\)*#i', '', str_replace('::', ':', $callback));
			}
			return array($callback_name => $callback);
		}
		elseif (is_array($callback) and is_callable(reset($callback)))
		{
			return $callback;
		}
		else
		{
			$string = ! is_array($callback)
					? $callback
					: (is_object(@$callback[0])
						? get_class(@$callback[0]).'->'.@$callback[1]
						: @$callback[0].'::'.@$callback[1]);
			Log::notice('Invalid rule "'.$string.'" passed to Validation, not used.');
			return FALSE;
		}
	}

	/**
	 * Required
	 *
	 * Value may not be empty
	 *
	 * @param   mixed
	 * @return  bool
	 */
	public function _validate_required($val)
	{
		return ! $this->_empty($val);
	}

	/**
	 * Special empty method because 0 and '0' are non-empty values
	 *
	 * @param   mixed
	 * @return  bool
	 */
	public static function _empty($val)
	{
		return ($val === FALSE or $val === NULL or $val === '' or $val === []);
	}

	/**
	 * Match value against comparison input
	 *
	 * @param   mixed
	 * @param   mixed
	 * @param   bool  whether to do type comparison
	 * @return  bool
	 */
	public function _validate_match_value($val, $compare, $strict = FALSE)
	{
		// first try direct match
		if ($this->_empty($val) || $val === $compare || ( ! $strict && $val == $compare))
		{
			return TRUE;
		}

		// allow multiple input for comparison
		if (is_array($compare))
		{
			foreach($compare as $c)
			{
				if ($val === $c || ( ! $strict && $val == $c))
				{
					return TRUE;
				}
			}
		}

		// all is lost, return failure
		return FALSE;
	}

	/**
	 * Match PRCE pattern
	 *
	 * @param   string
	 * @param   string  a PRCE regex pattern
	 * @return  bool
	 */
	public function _validate_match_pattern($val, $pattern)
	{
		return $this->_empty($val) || preg_match($pattern, $val) > 0;
	}

	/**
	 * Match specific other submitted field string value
	 * (must be both strings, check is type sensitive)
	 *
	 * @param   string
	 * @param   string
	 * @return  bool
	 */
	public function _validate_match_field($val, $field)
	{
		return $this->_empty($val) || $this->input($field) === $val;
	}

	/**
	 * Minimum string length
	 *
	 * @param   string
	 * @param   int
	 * @return  bool
	 */
	public function _validate_min_length($val, $length)
	{
		return $this->_empty($val) || ($this->mbstring ? mb_strlen($val) : strlen($val)) >= $length;
	}

	/**
	 * Maximum string length
	 *
	 * @param   string
	 * @param   int
	 * @return  bool
	 */
	public function _validate_max_length($val, $length)
	{
		return $this->_empty($val) || ($this->mbstring ? mb_strlen($val) : strlen($val)) <= $length;
	}

	/**
	 * Exact string length
	 *
	 * @param   string
	 * @param   int
	 * @return  bool
	 */
	public function _validate_exact_length($val, $length)
	{
		return $this->_empty($val) || ($this->mbstring ? mb_strlen($val) : strlen($val)) == $length;
	}

	/**
	 * Validate email using PHP's filter_var()
	 *
	 * @param   string
	 * @return  bool
	 */
	public function _validate_valid_email($val)
	{
		return $this->_empty($val) || filter_var($val, FILTER_VALIDATE_EMAIL);
	}

	/**
	 * Validate email using PHP's filter_var()
	 *
	 * @param   string
	 * @return  bool
	 */
	public function _validate_valid_emails($val)
	{
		if ($this->_empty($val))
		{
			return TRUE;
		}

		$emails = explode(',', $val);

		foreach ($emails as $e)
		{
			if ( ! filter_var(trim($e), FILTER_VALIDATE_EMAIL))
			{
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * Validate URL using PHP's filter_var()
	 *
	 * @param   string
	 * @return  bool
	 */
	public function _validate_valid_url($val)
	{
		return $this->_empty($val) || filter_var($val, FILTER_VALIDATE_URL);
	}

	/**
	 * Validate IP using PHP's filter_var()
	 *
	 * @param   string
	 * @return  bool
	 */
	public function _validate_valid_ip($val)
	{
		return $this->_empty($val) || filter_var($val, FILTER_VALIDATE_IP);
	}

	/**
	 * Validate input string with many options
	 *
	 * @param   string
	 * @param   string|array  either a named filter or combination of flags
	 * @return  bool
	 */
	public function _validate_valid_string($val, $flags = array('alpha', 'utf8'))
	{
		if ($this->_empty($val))
		{
			return TRUE;
		}

		if ( ! is_array($flags))
		{
			if ($flags == 'alpha')
			{
				$flags = array('alpha', 'utf8');
			}
			elseif ($flags == 'alpha_numeric')
			{
				$flags = array('alpha', 'utf8', 'numeric');
			}
			elseif ($flags == 'url_safe')
			{
				$flags = array('alpha', 'numeric', 'dashes');
			}
			elseif ($flags == 'integer' or $flags == 'numeric')
			{
				$flags = array('numeric');
			}
			elseif ($flags == 'float')
			{
				$flags = array('numeric', 'dots');
			}
			elseif ($flags == 'all')
			{
				$flags = array('alpha', 'utf8', 'numeric', 'spaces', 'newlines', 'tabs', 'punctuation', 'dashes');
			}
			else
			{
				return FALSE;
			}
		}

		$pattern = ! in_array('uppercase', $flags) && in_array('alpha', $flags) ? 'a-z' : '';
		$pattern .= ! in_array('lowercase', $flags) && in_array('alpha', $flags) ? 'A-Z' : '';
		$pattern .= in_array('numeric', $flags) ? '0-9' : '';
		$pattern .= in_array('spaces', $flags) ? ' ' : '';
		$pattern .= in_array('newlines', $flags) ? "\n" : '';
		$pattern .= in_array('tabs', $flags) ? "\t" : '';
		$pattern .= in_array('dots', $flags) && ! in_array('punctuation', $flags) ? '\.' : '';
		$pattern .= in_array('punctuation', $flags) ? "\.,\!\?:;\&" : '';
		$pattern .= in_array('dashes', $flags) ? '_\-' : '';
		$pattern = empty($pattern) ? '/^(.*)$/' : ('/^(['.$pattern.'])+$/');
		$pattern .= in_array('utf8', $flags) ? 'u' : '';

		return preg_match($pattern, $val) > 0;
	}

	/**
	 * Checks whether numeric input has a minimum value
	 *
	 * @param   string|float|int
	 * @param   float|int
	 * @return  bool
	 */
	public function _validate_numeric_min($val, $min_val)
	{
		return $this->_empty($val) || floatval($val) >= floatval($min_val);
	}

	/**
	 * Checks whether numeric input has a maximum value
	 *
	 * @param   string|float|int
	 * @param   float|int
	 * @return  bool
	 */
	public function _validate_numeric_max($val, $max_val)
	{
		return $this->_empty($val) || floatval($val) <= floatval($max_val);
	}

} // END class

/**
 * Validation_Field
 *
 * @package default
 * @author liut
 **/
class Validation_Field
{
	/**
	 * @var  string  Name of this field
	 */
	protected $name = '';

	/**
	 * @var  string  Field label for validation errors and form label generation
	 */
	protected $label = '';

	/**
	 * @var  mixed  (Default) value of this field
	 */
	protected $value;

	/**
	 * @var  array  Rules for validation
	 */
	protected $rules = [];

	/**
	 * Constructor
	 *
	 * @param  string $name
	 * @param  string $label
	 * @param  array $rules
	 * 
	 */
	public function __construct($name, $label = '', array $rules = [])
	{
		$this->name = (string) $name;
		$label && $this->label = (string) $label;

		foreach ($rules as $rule)
		{
			call_user_func_array(array($this, 'addRule'), $rule);
		}
	}

	/**
	 * Add a validation rule
	 * any further arguements after the callback will be used as arguements for the callback
	 *
	 * @param   string|Callback	either a validation rule or full callback
	 * @return  Validation_Field this, to allow chaining
	 */
	public function addRule($callback)
	{
		$args = array_slice(func_get_args(), 1);
		$this->rules[] = array($callback, $args);

		return $this;
	}

	/**
	 * Magic get method to allow getting class properties but still having them protected
	 * to disallow writing.
	 *
	 * @return  mixed
	 */
	public function __get($key)
	{
		return $this->$key;
	}

	/**
	 * Change the field label
	 *
	 * @param   string
	 * @return  Validation_Field  this, to allow chaining
	 */
	public function label($label)
	{
		$this->label = $label;

		return $this;
	}

	/**
	 * Change the field value
	 *
	 * @param   string
	 * @return  Validation_Field  this, to allow chaining
	 */
	public function value($value)
	{
		$this->value = $value;

		return $this;
	}

} // END class


Validation_Error::init();

/**
 * Validation error
 *
 * Contains all the information about a validation error
 *
 * @package   core
 * 
 */
class Validation_Error extends Exception
{
	public static function init()
	{
		Lang::load('validation', TRUE);
	}

	/**
	 * @var  Validation  instance of validation
	 */
	protected $validation;

	/**
	 * @var  Validation_Field  the field that caused the error
	 */
	public $field;

	/**
	 * @var  mixed  value that failed to validate
	 */
	public $value;

	/**
	 * @var  string  validation rule string representation
	 */
	public $rule;

	/**
	 * @var  array  variables passed to rule other than the value
	 */
	public $params = [];

	/**
	 * Constructor
	 *
	 * @param  array  Validation_Field object
	 * @param  mixed  value that failed to validate
	 * @param  array  contains rule name as key and callback as value
	 * @param  array  additional rule params
	 */
	public function __construct(Validation $validation, Validation_Field $field, $value, $callback, $params)
	{
		$this->validation   = $validation;
		$this->field   = $field;
		$this->value   = $value;
		$this->params  = $params;
		$this->rule    = key($callback);
	}

	/**
	 * Get Message
	 *
	 * Shows the error message which can be taken from loaded language file.
	 *
	 * @param   string  HTML to prefix error message
	 * @param   string  HTML to postfix error message
	 * @param   string  Message to use, or false to try and load it from Lang class
	 * @return  string
	 */
	public function message($msg = FALSE, $prefix = '', $suffix = '')
	{
		if ($msg === FALSE)
		{
			$msg = $this->validation->getMessage($this->rule);
			// TODO: add lang support
			if ($msg === FALSE)
			{
				$msg = Lang::get('validation.'.$this->rule) ?: Lang::get('validation.'.Arr::get(explode(':', $this->rule), 0));
			}
		}
		if ($msg == FALSE)
		{
			return $prefix.'Validation rule '.$this->rule.' failed for '.$this->field->label.$suffix;
		}

		// only parse when there's tags in the message
		return $prefix.(strpos($msg, ':') === FALSE ? $msg : $this->_replace_tags($msg)).$suffix;
	}

	/**
	 * Replace templating tags with values
	 *
	 * @param   error message to parse
	 * @return  string
	 */
	protected function _replace_tags($msg)
	{
		// prepare label & value
		$label    = is_array($this->field->label) ? $this->field->label['label'] : $this->field->label;
		$value    = is_array($this->value) ? implode(', ', $this->value) : $this->value;

		// setup find & replace arrays
		$find     = array(':field', ':label', ':value', ':rule');
		$replace  = array($this->field->name, $label, $value, $this->rule);

		// add the params to the find & replace arrays
		foreach($this->params as $key => $val)
		{
			// Convert array to just a string "(array)", can't reliably implode as contents might be arrays/objects
			if (is_array($val))
			{
				$val = '(array)';
			}
			// Convert object with __toString or just the classname
			elseif (is_object($val))
			{
				$val = method_exists($val, '__toString') ? (string) $val : get_class($val);
			}

			$find[]     = ':param:'.($key + 1);
			$replace[]  = $val;
		}

		// execute find & replace and return
		return str_replace($find, $replace, $msg);
	}

	/**
	 * Generate the error message
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return $this->message();
	}

} // END class
