<?PHP


/**
* 对Bo层调用的包装
*/
class Eb_BoRefer
{
	
	protected $bo_name;
	
	public function __construct($bo)
	{
		$this->bo_name = $bo;
	}
	
	public function __call($name, $args)
	{
		if (method_exists($this->bo_name, $name)) {
			return forward_static_call_array(array($this->bo_name, $name), $args);
		}
		$class = $this->bo_name;
		$obj_bo = new $class;
		if (method_exists($obj_bo, $name)) {
			return call_user_func_array(array($obj_bo, $name), $args);
		}
	}
	
}
