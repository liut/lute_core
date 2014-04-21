<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Sp_Storage_File
 *
 * File 存储
 *
 * @package    Sp
 * @version    $Id$
 * @created    17:33 2009-09-29
 */


/**
 * Sp_Storage_File
 * 
 */
class Sp_Storage_File extends Sp_Storage
{
	
	/**
	 * constructor
	 * 
	 * @param array $option
	 * @return void
	 */
	protected function __construct($option)
	{
		$this->_ini = $option;
		foreach($this->_ini as $key => $val) {
			$_name = '_' . $key;
			if(isset($this->$_name)) {
				$this->$_name = $val;
			}
		}
 	}

	/**
	 * 存储文件
	 * 
	 * @param string $local_file
	 * @param string $new_filename
	 * @param array $option
	 * @return string
	 */
	public function put($loc_file, $name, $option = null)
	{
		if(!file_exists($loc_file)) {
			return -101;
		}
		$hash_dir = $this->getOption('hash_dir');
		if($hash_dir) {
			$hash = md5_file($loc_file);
			$dir = substr($hash, 0, 2) . '/' . substr($hash, 2, 2). '/' . substr($hash, 4, 2);
			$dest_file = $dir . '/' . substr($hash, 6) . '.' . pathinfo($name, PATHINFO_EXTENSION);
		} else {
			$dest_file = $name;
		}
		$path_prefix = $this->getOption('path_prefix');
		if ($path_prefix) $dest_file = $path_prefix . $dest_file;
		$full_file = $this->getOption('path_root') . $dest_file;
		$dir = dirname($full_file); //var_dump($dir);
		if(!is_dir($dir)) @mkdir($dir, 0777, true);
		if(is_uploaded_file($loc_file)) {
			$ret = move_uploaded_file($loc_file, $full_file);
		} else {
			$ret = false; // TODO: 非上传文件处理
		}
		return $ret ? $dest_file : false;
	}
	
	/**
	 * 根据id取出文件，由于是 file 存储，id一般是指一个文件的相对路径
	 * 
	 * @param mixed $id
	 * @return mixed
	 */
	public function get($id)
	{
		// TODO:
		return ;
	}
	
	/**
	 * 根据 path 判断文件是否存在
	 *
	 * @param mixed $path
	 * @return mixed id or FALSE
	 */
	public function exists($path)
	{
		// TODO:
		return ;
	}

	/**
	 * 根据id删除文件
	 * 
	 * @param mixed $id
	 * @return boolean
	 */
	public function delete($id)
	{
		// TODO:
		return ;
	}
	
	/**
	 * 分页浏览，可以添加查询条件
	 * 
	 * @param array $condition = array()
	 * @param int $limit = 20
	 * @param int $offset = 0
	 * @param int $total = null
	 * @return array
	 */
	public function browse($condition = array(), $limit = 20, $offset = 0, & $total = null)
	{
		// TODO: 从文件系统中浏览文件
		
		if(is_array($condition)) {
			extract($condition, EXTR_SKIP);
		}
		$data = array();
		$dir =  $this -> getOption('path_root');

		//PHP遍历文件夹下所有文件
		$handle=opendir($dir); 
		$i = 0;
		
		while (false !== ($file = readdir($handle)))
		{	$i++;
			if ($i == $offset){	
				$j++;
				if ($i <= 20) {
					if ($file != "." && $file != "..") {
						$data[$j][id] = $j;   //输出文件名
						echo $file;
						$data[$j][$filename] = $file;
					}
				}
			}
		}
		exit;
		$total = $i;
		closedir($handle); 	
		return $data;
		
	}
}