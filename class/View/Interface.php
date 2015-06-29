<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * View_Interface
 *
 * View 接口
 *
 * @package    Lib
 * @author     liut
 * @copyright  2007 liut
 * @version    $Id$
 */


/**
 * View_Interface
 *
 */
interface View_Interface
{
	/**
	 * assign
	 */
	public function assign($key, $value = null);

	/**
	 * bind
	 *
	 * @return void
	 **/
	public function bind($key, & $value);

	/**
	 *
	 */
	public function display($tpl_name);

	/**
	 *
	 */
	public function fetch($tpl_name);

    /**
     * Render a specific template with context.
     * @param  string $name
     * @param  array  $context
     * @return string
     */
    public function render($name, array $context = []);
}
