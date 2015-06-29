<?PHP

/**
 * undocumented class
 *
 * @package core
 * @author liut
 **/
class View_Engine_Smarty implements View_Engine_Interface
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
		$tpl = new View_Smarty($this->_opt);

		$config = Loader::config('view');
		extract($config, EXTR_PREFIX_ALL, 'view');
		isset($view_caching) or $view_caching = FALSE;
		isset($view_cache_lifetime) or $view_cache_lifetime = 3600;

		if ($view_caching === TRUE && class_exists('Smarty', FALSE) && $view instanceof Smarty) {
			$view->setCaching(Smarty::CACHING_LIFETIME_CURRENT);
			$view->setCacheLifetime($view_cache_lifetime);
		}

		if (isset($view_pre_vars) && is_array($view_pre_vars)) {
			$view->assign($view_pre_vars);
		}

		$tpl->assign($data);
		return $tpl->fetch($name);
	}
} // END class View_Engine_Smarty
