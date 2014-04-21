<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * View_Smarty
 *
 * Smarty 模板引擎
 *
 * @package	Lib
 * @author	 liut
 * @copyright  2007 liut
 * @version	$Id$
 */

if(!class_exists('Smarty', FALSE) && defined('SMARTY_DIR'))
{
	include_once SMARTY_DIR . 'Smarty.class.php';
}

if(!class_exists('Smarty', FALSE))
{
	die('class Smarty not found!'.PHP_EOL);
}

/**
 * View_Smarty
 *
 */
class View_Smarty extends Smarty implements View_Interface
{

	/**
	 * language
	 *
	 * @var string
	 **/
	protected $_lang = NULL;

	/**
	 * template root
	 *
	 * @var string
	 **/
	protected $_tpl_root = NULL;

	/**
	 * template_dirs
	 *
	 * @var string
	 **/
	protected $_tpl_dirs = [];

	/**
	 * template_dirs
	 *
	 * @var string
	 **/
	protected $_cust_compile_dir = '';

	/**
	 * 构造函数
	 *
	 * @return View_Smarty
	 */
	public function __construct($opt = NULL)
	{
		//Smarty 3 !
		parent::__construct();

		if (is_string($opt) && $opt) {
			$this->_lang = $opt;
		}
		elseif (is_array($opt) && isset($opt['lang'])) {
			$this->_lang = $opt['lang'];
		}

		$this->bind('LANG', $this->_lang);

		if (defined('VIEW_SKINS_ROOT') && defined('VIEW_SKIN_DEFAULT')) {
			$this->_tpl_root = VIEW_SKINS_ROOT;
			if ($this->_lang) {
				$this->addTemplateDir(VIEW_SKINS_ROOT . $this->_lang, '0_lang');
			}
			if (defined('VIEW_SKIN_CURRENT')) {
				$this->addTemplateDir(VIEW_SKINS_ROOT . VIEW_SKIN_CURRENT, '1_curr');
			}
			$this->addTemplateDir(VIEW_SKINS_ROOT . VIEW_SKIN_DEFAULT, '2_default');
		}
		elseif (defined('APP_ROOT') && is_dir(APP_ROOT . 'templates')) {
			$this->addTemplateDir(APP_ROOT . 'templates');
		}
		elseif (defined('VIEW_TEMPLATE_DIR') && is_dir(VIEW_TEMPLATE_DIR)) {
			$this->addTemplateDir(VIEW_TEMPLATE_DIR);
		}
		else {
			$tpl_dir = isset($_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] . '/templates' : '';
			if(!empty($tpl_dir) && is_dir($tpl_dir)) {
				$this->addTemplateDir($tpl_dir);
			}
		}

		$this->_cust_compile_dir = defined('VIEW_COMPILE_DIR') ? VIEW_COMPILE_DIR : $this->getComplieDir();

		if ($this->_lang) {
			$this->setCompileDir($this->_cust_compile_dir . DS . $this->_lang);
		}
		else $this->setCompileDir($this->_cust_compile_dir);
		
		defined('VIEW_CONFIG_DIR') && $this->setConfigDir(VIEW_CONFIG_DIR);
		
		$this->addPluginsDir(LIB_ROOT.'function/smarty/plugins');

		if (defined('VIEW_SMARTY_HELPER')) {
			$class = VIEW_SMARTY_HELPER;
			new $class($this);
			$title = '';
			$this->setTitle($title);
		}
	}

	/**
	 * bind
	 *
	 * @return void
	 **/
	public function bind($key, & $value)
	{
		$this->assignByRef($key, $value);
	}
	
	/**
	 * 设置页面标题
	 * 
	 * @param string $title
	 * @return void
	 */
	public function setTitle($title)
	{
		$this->bind('head_title', $title);
	}
	
	/**
	 * function description
	 * 
	 * @param string $kw
	 * @return void
	 */
	public function setKeywords($kw)
	{
		$this->bind('head_keywords', $kw);
	}

	public function lang($lang = NULL)
	{
		if (is_null($lang)) {
			if (is_null($this->_lang)) {
				$this->_lang = 'en';
			}
			return $this->_lang;
		}

		if (Lang::available($lang)) {
			$this->_lang = $lang;
			$dirs = $this->getTemplateDir();
			if ($this->_tpl_root && isset($dirs['0_lang'])) {
				$dirs['0_lang'] = $this->_tpl_root . DS . $lang;
				$this->setTemplateDir($dirs);
			}
			$this->setCompileDir($this->_cust_compile_dir . DS . $lang);
		}
	}

}