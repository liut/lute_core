<?PHP


/**
* TODO: 未完成
*/
class View_Simple implements View_Interface
{

	/**
	 * @var  array  The view's data
	 */
	protected $data = array();

	/**
	 * Sets the initial view filename and local data.
	 *
	 *     $view = new View_Simple($file);
	 *
	 * @param   string  view filename
	 * @param   array   array of values
	 * @return  void
	 * @uses    View::set_filename
	 */
	public function __construct($file = null, $data = null)
	{
		# TODO:
	}

	/**
	 * assign
	 */
	public function assign($key, $value = null)
	{}

	/**
	 * bind
	 *
	 * @return void
	 **/
	public function bind($key, & $value)
	{}

	/**
	 *
	 */
	public function display($tpl_name)
	{}

	/**
	 *
	 */
	public function fetch($tpl_name)
	{}
}


