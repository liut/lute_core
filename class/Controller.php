<?PHP


/**
* Controller Base
*/
abstract class Controller
{
	const MSGTYPE_DEFAULT = '';
	const MSGTYPE_ERROR = 'error';
	const MSGTYPE_WARNING = 'warning';
	
	/**
	 * @var  Request  The current Request object
	 */
	protected $_request;

	/**
	 * @var boolean is ajax
	 */
	protected $_is_ajax;

	/**
	 * @var current action name
	 */
	protected $_action;

	/**
	 * @var current dispatcher instance
	 */
	protected $_dispatcher;

	/**
	 * @var current lang
	 */
	protected $_lang;

	/**
	 * constructor
	 * @param Request $request
	 */
	public function __construct(Request $request)
	{
		$this->_request = $request;
	}

	public function getRequest()
	{
		return $this->_request;
	}

	public function getUser()
	{
		return $this->_request->getUser();
	}

	public function isAjax()
	{
		if (is_null($this->_is_ajax)) {
			$this->_is_ajax = $this->_request->isAjax() || $this->_request->isJson();
		}
		return $this->_is_ajax;
	}

	public function isMobile()
	{
		return $this->_request->isMobile();
	}

	/**
	 * @param $status boolean
	 * @param $data array 
	 * @deprecated  by $this->apiSend($status, $data)
	 */
	protected function jsonSend($status, $data)
	{
		return $this->apiSend($status, $data);
	}

	protected function apiSend($status, $data)
	{
		return ['api_status' => $status, 'data' => $data];
	}
	
	protected function message($message, $redirect = null, $icon = self::MSGTYPE_DEFAULT, $subject = null)
	{
		if (is_null($subject)) $subject = ucfirst($icon);
		return ['my/message', ['message' => $message, "redirect" => $redirect, "icon" => $icon, "subject" => $subject]];
		
	}

	protected function forward($control_name, $action_name = 'index', array $params = [])
	{
		$dispatcher = $this->dispatcher();
		if (!is_null($dispatcher)) {
			$dispatcher->forward($control_name, $action_name, $params);
			return NULL;
		}
		throw new Exception('dispatcher is null');
	}

	public function dispatcher(Dispatcher $dispatcher = NULL)
	{
		if (is_null($dispatcher)) {
			return $this->_dispatcher;
		}

		$this->_dispatcher = $dispatcher;
		$this->_lang = $dispatcher->lang;

		Log::debug('set controller lang: ' . $this->_lang, __METHOD__);
	}

	public function actionName($action = NULL)
	{
		if (is_null($action)) {
			return $this->_action;
		}

		$this->_action = $action;
	}

}



