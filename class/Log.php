<?php
/**
 * 日志处理
 *
 * @package	Core
 * @author liut
 * @version $Id$
 * @created 13:30 2009-04-21
 */


/**
 * 日志
 *
 */
class Log
{
	const LEVEL_EMERG = 	0;     /* System is unusable */
	const LEVEL_ALERT = 	1;     /* Immediate action required */
	const LEVEL_CRIT = 	 	2;     /* Critical conditions */
	const LEVEL_ERROR =  	3;     /* Error conditions */
	const LEVEL_WARNING = 	4;     /* Warning conditions */
	const LEVEL_NOTICE = 	5;     /* Normal but significant */
	const LEVEL_INFO = 	 	6;     /* Informational */
	const LEVEL_DEBUG = 	7;     /* Debug-level messages */

	static $_levels = array('emerg', 'alert', 'crit', 'error', 'warning',
							  'notice', 'info', 'debug');

	/* Log types for PHP's native error_log() function. */
	const TYPE_SYSTEM = 0; /* Use PHP's system logger */
	const TYPE_MAIL = 	1; /* Use PHP's mail() function */
	const TYPE_DEBUG = 	2; /* Use PHP's debugging connection */
	const TYPE_FILE = 	3; /* Append to a file */

	private static $_logs = array();
	private static $_lineFormat = '%1$s %2$s: [%3$s] %4$s';
	private static $_timeFormat = '%Y%m%d-%H%M%S';

	/**
	 * write a log message
	 *
	 * @param mixed $message
	 * @param int $priority
	 * @param string $title
	 * @param string $suffix
	 * @param string $title
	 * @return void
	 */
	public static function write($message, $priority, $title = '', $suffix = NULL, $key = 'sp')
	{
		$level = defined('LOG_LEVEL') ? LOG_LEVEL : self::LEVEL_ERROR;
		if($priority > $level) return FALSE;

		if (empty($key)) {
			$key = defined('LOG_NAME') && preg_match('#^[a-z][a-z]+$#i', LOG_NAME) ? strtolower(LOG_NAME) : 'sp';
		}

		if (empty($suffix) || !preg_match('#^[a-z][a-z_]*$#i', $suffix)) {
			$suffix = 'main';
		}

		$log_root = defined('LOG_ROOT') ? LOG_ROOT : '/tmp/';
		$destination = $log_root .$key.'_'.$suffix;

		'fpm-fcgi' === PHP_SAPI && ($destination .= '_fpm') || ($destination .= '_cli');

		/* Extract the string representation of the message. */
		$message = $title . ' ' . self::extractMessage($message);

		if(defined('_PS_DEBUG') && TRUE === _PS_DEBUG) {
			self::$_logs[] = $priority . ' ' . $message;
		}

		/* Build the string containing the complete log line. */
		$line = self::_format(static::$_lineFormat, strftime(static::$_timeFormat), $priority, $message);

		/* Pass the log line and parameters to the error_log() function. */
		return error_log($line, self::TYPE_FILE, $destination.'.log');
	}

	private static function _format($format, $timestamp, $priority, $message, $ident = '')
	{
		return sprintf($format . PHP_EOL,
					   $timestamp,
					   $ident,
					   self::$_levels[$priority],
					   $message,
					   isset($file) ? $file : '',
					   isset($line) ? $line : '',
					   isset($func) ? $func : '',
					   isset($class) ? $class : '');
	}


	public static function extractMessage($message)
	{
		/*
		 * If we've been given an object, attempt to extract the message using
		 * a known method.  If we can't find such a method, default to the
		 * "human-readable" version of the object.
		 *
		 * We also use the human-readable format for arrays.
		 */
		if (is_object($message)) {
			if (method_exists($message, '__toString')) {
				if (PHP_VERSION_ID > 50000) {
					return (string)$message;
				}
				return $message->__toString();
			}

			if ($message instanceof Exception) {
				return Loader::printException($message, TRUE);
			}

			if (method_exists($message, 'getmessage')) {
				return $message->getMessage();
			}

			// if (method_exists($message, 'tostring')) {
			// 	return $message->toString();
			// }

			return var_export($message, true);
		}

		if (is_array($message)) {
			if (isset($message['message']) && is_scalar($message['message'])) {
				$code = isset($message['code']) ? 'code: '.$message['code'] . ', ' : '';
				return $code . $message['message'];
			}

			return Arr::dullOut(array_map('static::extractMessage', $message));
		}

		if (is_bool($message) || is_null($message)) {
			return var_export($message, true);
		}

		/* Otherwise, we assume the message is a string. */
		return '"'.$message.'"';
	}



	/**
	 * function description
	 *
	 * @param
	 * @return void
	 */
	public static function error($message, $title = '', $suffix = NULL, $name = NULL)
	{
		return self::write($message, self::LEVEL_ERROR, $title, $suffix, $name);
	}

	/**
	 * function description
	 *
	 * @param
	 * @return void
	 */
	public static function warning($message, $title = '', $suffix = NULL, $name = NULL)
	{
		return self::write($message, self::LEVEL_WARNING, $title, $suffix, $name);
	}

	/**
	 * function description
	 *
	 * @param
	 * @return void
	 */
	public static function notice($message, $title = '', $suffix = NULL, $name = NULL)
	{
		return self::write($message, self::LEVEL_NOTICE, $title, $suffix, $name);
	}

	/**
	 * function description
	 *
	 * @param
	 * @return void
	 */
	public static function info($message, $title = '', $suffix = NULL, $name = NULL)
	{
		return self::write($message, self::LEVEL_INFO, $title, $suffix, $name);
	}

	/**
	 * function description
	 *
	 * @param
	 * @return void
	 */
	public static function debug($message, $title = '', $suffix = NULL, $name = NULL)
	{
		return self::write($message, self::LEVEL_DEBUG, $title, $suffix, $name);
	}

	/**
	 * function description
	 *
	 * @param
	 * @return void
	 */
	public static function getLogs()
	{
		return self::$_logs;
	}


}
