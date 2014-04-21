<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Cache_Interface
 *
 * 缓存引擎类接口
 *
 * @package	Core
 * @author 	liut
 * @version	$Id$
 */



/**
 * Cache_Interface
 *
 */
interface Cache_Interface
{
	
	/**
	 * 按参数（数组）初始化
	 *
	 */
	public function init($params = NULL);

	/**
	 * 按 Key 取缓存条目
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get($key);

	/**
	 * 存缓存数据
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $expire
	 * @return void
	 */
	public function set($key, $value, $expire);

	/**
	 * 按 Key 删除缓存条目
	 *
	 * @param string $key
	 * @return void
	 */
	public function delete($key, $expire = NULL);

	/**
	 * 开始输出缓存
	 *
	 */
	public function start($key, $life = NULL);

	/**
	 * 结束输出
	 *
	 */
	public function end();

	
}

