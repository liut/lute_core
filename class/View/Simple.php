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
	 * Sets the initial view filename and local data.
	 *
	 *     $view = new View_Simple($opt);
	 *
	 * @param   string  view filename
	 * @param   array   array of values
	 * @return  void
	 * @uses    View::set_filename
	 */
	public function __construct($opt = NULL)
	{
		if (defined('VIEW_SKINS_ROOT') && defined('VIEW_SKIN_DEFAULT')) {
			// $this->_tpl_root = VIEW_SKINS_ROOT;
			// if ($this->_lang) {
			// 	$this->addTemplateDir(VIEW_SKINS_ROOT . $this->_lang, '0_lang');
			// }
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

		// TODO:
	}

	/**
	 * assign
	 */
	public function assign($key, $value = null)
	{
		is_string($key) && $this->data[$key] = $value;
	}

	/**
	 * bind
	 *
	 * @return void
	 **/
	public function bind($key, & $value)
	{
		$this->data[$key] = $value;
	}

	/**
	 *
	 */
	public function display($tpl_name)
	{
		// var_dump($this->data);
		foreach ($this->getTemplateDir() as $key => $dir) {
			// echo $key, ': ', $dir, PHP_EOL;
			if (is_file($dir.$tpl_name)) {
				extract($this->data, EXTR_SKIP);
				include $dir.$tpl_name;
				return;
			}
		}
		Log::warning($tpl_name. ' not found', __METHOD__);
	}

	/**
	 *
	 */
	public function fetch($tpl_name)
	{}

    /**
     * Add template directory(s)
     *
     * @param  string|array $template_dir directory(s) of template sources
     * @param  string       $key          of the array element to assign the template dir to
     *
     * @return Smarty          current Smarty instance for chaining
     * @throws SmartyException when the given template directory is not valid
     */
    public function addTemplateDir($template_dir, $key = null)
    {
        // make sure we're dealing with an array
        $this->_tpl_dirs = (array) $this->_tpl_dirs;

        if (is_array($template_dir)) {
            foreach ($template_dir as $k => $v) {
                $v = preg_replace('#(\w+)(/|\\\\){1,}#', '$1$2', rtrim($v, '/\\')) . DS;
                if (is_int($k)) {
                    // indexes are not merged but appended
                    $this->_tpl_dirs[] = $v;
                } else {
                    // string indexes are overridden
                    $this->_tpl_dirs[$k] = $v;
                }
            }
        } else {
            $v = preg_replace('#(\w+)(/|\\\\){1,}#', '$1$2', rtrim($template_dir, '/\\')) . DS;
            if ($key !== null) {
                // override directory at specified index
                $this->_tpl_dirs[$key] = $v;
            } else {
                // append new directory
                $this->_tpl_dirs[] = $v;
            }
        }
        // $this->joined_template_dir = join(DIRECTORY_SEPARATOR, $this->_tpl_dirs);

        return $this;
    }

    /**
     * Get template directories
     *
     * @param mixed $index index of directory to get, null to get all
     *
     * @return array|string list of template directories, or directory of $index
     */
    public function getTemplateDir($index = null)
    {
        if ($index !== null) {
            return isset($this->_tpl_dirs[$index]) ? $this->_tpl_dirs[$index] : [];
        }

        return (array) $this->_tpl_dirs;
    }

}


