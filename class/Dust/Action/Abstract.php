<?PHP

/**
 * Dust_Action_Abstract
 * Admin 控制器 接口
 *
 * @author liut
 * @version $Id$
 * @created 14:26 2009-06-02
 */


abstract class Dust_Action_Abstract //implements Action_Interface
{

	protected $_request = null;

	/**
	 * constructor
	 * @param Request $request
	 */
	public function __construct(Request $request)
	{
		$this->_request = $request;
	}

	/**
	 * 执行一次行为请求
	 *
	 * @return mixed
	 */
	abstract public function execute($request);


	/**
	 * call grid data
	 *
	 * @param array $callback
	 * @param array $keys = array()
	 * @return array
	 */
	public function callGrid($callback, array $keys = [])
	{
		$request = $this->getRequest();
		$condition = [];
		foreach($keys as $k) {
			$v = $request->$k;
			if(!is_null($v)) $condition[$k] = $v;
		}

		return $this->fetchGrid($callback, $condition);
	}

	/**
	 * get grid data
	 *
	 * @param array $callback
	 * @param array $condition = array()
	 * @return array
	 */
	public function fetchGrid($callback, $condition = array())
	{
		$request = $this->getRequest();

		$sort_name = $request->sidx;
		$sort_order = $request->sord;
		if($sort_name) {
			$condition['sort_name'] = $sort_name;
			$condition['order_by'] = $sort_name;
			if($sort_order) {
				$condition['sort_order'] = $sort_order;
				$condition['order_by'] .= ' ' . $sort_order;
			}
		}

		$page = $request->page;
		$limit = $request->rows;
		if (!$page) $page = 1;
		if (!$limit) $limit = 10;

		$offset = (($page-1) * $limit);
		$total_records = -1;
		$data = call_user_func_array($callback, array($condition, $limit, $offset, &$total_records));
		$total_pages = ceil($total_records/$limit);
		return [
			'data' => $data,
			'context' => [
				'page' => $page,
				'total_pages' => $total_pages,
				'total_records' => $total_records,
				'__src' => $request->_source
			],
			'api_keys' => ['page', 'total_pages', 'total_records', '__src'],
		];
	}

	/**
	 * request
	 *
	 * @return object
	 */
	public function getRequest()
	{
		return $this->_request;
	}

	/**
	 * function description
	 *
	 * @return void
	 */
	public function getUser()
	{
		return $this->_request->getUser();
	}

	public function downCsvTpl($header, $name = 'tpl')
	{
		$request = $this->getRequest();
		// $ua = strtolower($request->getAgent());
		// $is_windows = strpos($ua, "windows") !== false;

		// $clean = function($v) use ($is_windows) {
		// 	$v = preg_replace("#\(.+\)#", '', $v);
		// 	if ($is_windows) return iconv("UTF-8", "GBK", $v);
		// 	return $v;
		// };

		$fp = fopen('php://temp', 'r+');
		// fputcsv($fp, array_map($clean, $header));
		fputcsv($fp, $header);
		rewind($fp);
		$csv = fgets($fp) . PHP_EOL;
		fclose($fp);

		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.$name.'.csv');
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . strlen($csv));
		echo $csv;
		exit;
	}
}

