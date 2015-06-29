<?PHP

/**
 * undocumented class
 *
 * @package core
 * @author liut
 **/
class View_Engine_Simple implements View_Engine_Interface
{
	private $_opt = NULL;
	/**
	 * constructor
	 *
	 * @return self
	 */
	public function __construct($opt = NULL)
	{
		$this->_opt = NULL;
	}

	public function render($name, array $data = [])
	{
		$tpl = new View_Simple($this->_opt);
		$tpl->assign($data);
		return $tpl->fetch($name);
	}
} // END class View_Engine_Simple
