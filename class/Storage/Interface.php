<?PHP

interface Sp_Storage_Interface
{

	/**
	 * 分页浏览，可以添加查询条件
	 * 
	 * @param array $condition = array()
	 * @param int $limit = 20
	 * @param int $offset = 0
	 * @param int $total = null
	 * @return array
	 */
	public function browse($condition = array(), $limit = 20, $offset = 0, & $total = null);

	/**
	 * 根据 hash 值（一般是md5）判断文件是否存在
	 *
	 * @param mixed $hashed
	 * @return mixed id or FALSE
	 */
	public function exists($hashed);

	/**
	 * 根据id取出文件
	 *
	 * @param mixed $id or filename or file path
	 * @return mixed
	 */
	public function get($id);

	/**
	 * 根据id删除文件
	 *
	 * @param mixed $id
	 * @return boolean
	 */
	public function delete($id);

	/**
	 * 存储文件
	 *
	 * @param string $file
	 * @param string $new_name
	 * @param array $option option or other meta
	 * @return string
	 */
	public function put($file, $new_name, $option = array());


}

