<?PHP

Lang::init();

/**
 * The Lang class allows you to set language variables using language files in your application.
 *
 * @package core
 * @author liut
 **/
class Lang
{
	/**
	 * @var    array    $paths    array of paths
	 */
	protected static $paths = [];

	/**
	 * @var    array    $loaded    array of loaded files
	 */
	protected static $loaded = [];

	/**
	 * @var  array  language lines
	 */
	protected static $lines = [];

	/**
	 * @var  array  available langs
	 */
	protected static $available = [];

	/**
	 * @var  string current select language
	 */
	protected static $current = NULL;

	/**
	 * @var  array  language(s) to fall back on when loading a file from the current lang fails
	 */
	protected static $fallback = [];

	/**
	 * init
	 */
	public static function init()
	{
		$available = defined('LANG_AVAILABLE') ? LANG_AVAILABLE : 'en';
		static::$available = explode(' ', $available);

		$fallback = defined('LANG_FALLBACK') ? LANG_FALLBACK : 'en';
		static::$fallback = explode(',', $fallback);
	}

	/**
	 * Returns currently active language.
	 *
	 * @return   string    currently active language
	 */
	public static function current($language = NULL)
	{
		if (is_null($language)) {
			if (is_null(static::$current)) {
				return static::$fallback[0];
			}
			return static::$current;
		}

		if (in_array($language, static::$available)) {
			static::$current = $language;
		} else {
			throw new InvalidArgumentException('Invalid language `'.$language.'`');
		}
	}

	public static function fallback()
	{
		return static::$fallback[0];
	}

	/**
	 * 判断是否不是默认语言
	 *
	 * @param string $lang
	 * @return void
	 **/
	public static function changed($lang)
	{
		return $lang && $lang !== static::fallback();
	}

	/**
	 * check or return available language.
	 *
	 * @return   string    currently active language
	 */
	public static function available($language = NULL)
	{
		if (is_null($language)) {
			return static::$available;
		}

		return in_array($language, static::$available);
	}

	/**
	 * get all paths or add a valid path
	 *
	 * @param string $path
	 * @return void
	 **/
	public static function path($path = NULL)
	{
		if(is_null($path)) {
			if (empty(static::$paths)) {
				static::$paths = [LIB_ROOT . 'lang', APP_ROOT . 'lang', DATA_ROOT . 'lang'];
			}
			return static::$paths;
		}

		if (is_string($path)) {
			$path = rtrim($path, '\\/');
			if(is_dir($path) && !in_array($path, static::$paths)) {
				static::$paths[] = $path;
			}
		}
	}

	/**
	 * Loads a language file.
	 *
	 * @param    mixed        $file        string file | language array
	 * @param    mixed       $group        null for no group, true for group is filename, false for not storing in the master lang
	 * @param    string|null $language     name of the language to load, null for the configurated language
	 * @param    bool        $overwrite    true for array_merge, false for Arr::merge
	 * @param    bool        $reload       true to force a reload even if the file is already loaded
	 * @return   array                     the (loaded) language array
	 */
	public static function load($file, $group = NULL, $language = NULL, $overwrite = false, $reload = false)
	{
		// get the active language and all fallback languages
		$language or $language = static::current();
		$languages = static::$fallback;

		Log::debug('file: ' . $file . ' group: ' . $group . ' lang: ' . $language, __METHOD__);

		// make sure we don't have the active language in the fallback array
		if (in_array($language, $languages))
		{
			unset($languages[array_search($language, $languages)]);
		}

		// stick the active language to the front of the list
		array_unshift($languages, $language);

		//Log::debug($languages, __METHOD__);

		if ( ! $reload and
		     ! is_array($file) and
		     ! is_object($file) and
		    array_key_exists($file, static::$loaded))
		{
			$group === true and $group = $file;
			if ($group === NULL or $group === false or ! isset(static::$lines[$language][$group]))
			{
				return false;
			}
			return static::$lines[$language][$group];
		}

		$lang = [];
		if (is_array($file))
		{
			$lang = $file;
		}
		elseif (is_string($file))
		{
			foreach ($languages as $language) {
				foreach (static::path() as $path) {
					$name = $language . '.' . $file;
					$_lang = Loader::config($name, [], $path);//Log::debug($_lang, 'loader::config ' . $name);

					if (is_array($_lang) && !empty($_lang)) {
						$lang = array_merge($lang, $_lang);
						// Log::debug(' ' . $name . ' loaded ' . count($_lang) . ' from ' . Loader::safePath($path), __METHOD__);
						break;
					}
					// else {
					// 	if (strpos($file, '.') === FALSE) {
					// 		Log::info(Loader::safePath($path) . DS . $name . ' not found', __METHOD__);
					// 	}
					// }

					unset($_lang);
				}

				if ($lang) {
					break;
				}
			}

			if (empty($lang)) {
				Log::notice('lang: ' . $language . ', file: ' . $file . ' not found', __METHOD__);
			}

			static::$loaded[$file] = count($lang);
		}

		if ($group === NULL)
		{
			isset(static::$lines[$language]) or static::$lines[$language] = array();
			static::$lines[$language] = $overwrite ? array_merge(static::$lines[$language], $lang) : Arr::merge(static::$lines[$language], $lang);
		}
		else
		{
			$group = ($group === true) ? $file : $group;
			isset(static::$lines[$language][$group]) or static::$lines[$language][$group] = array();
			static::$lines[$language][$group] = $overwrite ? array_merge(static::$lines[$language][$group], $lang) : Arr::merge(static::$lines[$language][$group], $lang);
		}

		return $lang;
	}

	/**
	 * Save a language array to disk.
	 *
	 * @param   string          $file      desired file name
	 * @param   string|array    $lang      master language array key or language array
	 * @param   string|null     $language  name of the language to load, null for the configurated language
	 * @return  bool                       false when language is empty or invalid else \File::update result
	 */
	public static function save($file, $lang, $language = NULL)
	{
		($language === NULL) and $language = static::current();

		// prefix the file with the language
		if ( ! is_null($language))
		{
			$file = explode('::', $file);
			end($file);
			$file[key($file)] = $language.DS.end($file);
			$file = implode('::', $file);
		}

		if ( ! is_array($lang))
		{
			if ( ! isset(static::$lines[$language][$lang]))
			{
				return false;
			}
			$lang = static::$lines[$language][$lang];
		}

		$type = pathinfo($file, PATHINFO_EXTENSION);
		if( ! $type)
		{
			$type = 'php';
			$file .= '.'.$type;
		}

		$dir = end(static::$paths);

		$output = static::_export($lang, $type);

		file_put_contents($dir . DS . $file, $output);

	}

	/**
	 * Returns a (dot notated) language string
	 *
	 * @param   string       $line      key for the line
	 * @param   array        $params    array of params to str_replace
	 * @param   mixed        $default   default value to return
	 * @param   string|null  $language  name of the language to get, null for the configurated language
	 * @return  mixed                   either the line or default when not found
	 */
	public static function get($line, array $params = array(), $default = NULL, $language = NULL)
	{
		($language === NULL) and $language = static::current();

		Log::debug('line: ' . $line . ' default: ' . $default . ' lang: ' . $language, __METHOD__);

		if (isset(static::$lines[$language])) {
			return static::_str_tr(Arr::get(static::$lines[$language], $line, $default), $params);
		}
		return $default;
	}

	/**
	 * Sets a (dot notated) language string
	 *
	 * @param    string       $line      a (dot notated) language key
	 * @param    mixed        $value     the language string
	 * @param    string       $group     group
	 * @param    string|null  $language  name of the language to set, null for the configurated language
	 * @return   void                    the Arr::set result
	 */
	public static function set($line, $value, $group = NULL, $language = NULL)
	{
		$group === NULL or $line = $group.'.'.$line;

		($language === NULL) and $language = static::current();

		isset(static::$lines[$language]) or static::$lines[$language] = array();

		return Arr::set(static::$lines[$language], $line, $value);
	}

	/**
	 * Deletes a (dot notated) language string
	 *
	 * @param    string       $item      a (dot notated) language key
	 * @param    string       $group     group
	 * @param    string|null  $language  name of the language to set, null for the configurated language
	 * @return   array|bool              the Arr::delete result, success boolean or array of success booleans
	 */
	public static function delete($item, $group = NULL, $language = NULL)
	{
		$group === NULL or $line = $group.'.'.$line;

		($language === NULL) and $language = static::current();

		return isset(static::$lines[$language]) ? Arr::delete(static::$lines[$language], $item) : false;
	}

	/**
	 * Returns the formatted language file contents.
	 *
	 * @param   array   $content  config array
	 * @return  string  formatted config file contents
	 */
	protected static function _export($contents, $type = 'php')
	{
		if (is_string($contents)) {
			return $contents;
		}

		// TODO: add to support ini

		$output = <<<CONF
<?php

CONF;
		$output .= 'return '.str_replace(array('  ', 'array (', '\''.APP_ROOT, '\''.CONF_ROOT, '\''.WEB_ROOT, '\''.LIB_ROOT), array("\t", 'array(', 'APP_ROOT.\'', 'CONF_ROOT.\'', 'WEB_ROOT.\'', 'LIB_ROOT.\''), var_export($contents, true)).";\n";
		return $output;
	}

	/**
	 * Parse the params from a string using strtr()
	 *
	 * @param   string  string to parse
	 * @param   array   params to str_replace
	 * @return  string
	 */
	public static function _str_tr($string, $array = [])
	{
		if (is_string($string))
		{
			$tr_arr = array();

			foreach ($array as $from => $to)
			{
				substr($from, 0, 1) !== ':' and $from = ':'.$from;
				$tr_arr[$from] = $to;
			}
			unset($array);

			return strtr($string, $tr_arr);
		}
		else
		{
			return $string;
		}
	}

	public static function loaded()
	{
		return static::$loaded;
	}

	public static function lines()
	{
		return static::$lines;
	}

	public static function label($lang)
	{
		static $languages = NULL;
		if (is_null($languages)) {
			$languages = Loader::config('languages');
		}

		if (isset($languages[$lang])) {
			return $languages[$lang];
		}

		return $lang;
	}

} // END class
