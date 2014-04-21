<?php

/**
 * Sp_Ftp
 *
 * @package    Sp
 * @author     Bourne
 * @version    $Id$
 */
 
class Sp_Storage_Ftp extends Sp_Storage
{
	
	private $_connectors = array();
	private $_logs = array();
	
	/**
	 * constructor
	 * 
	 * @param array $option
	 * @return void
	 */
	protected function __construct($option)
	{
		$this->_ini = $option;
		if(isset($this->_ini['servers'])) $ftp_servers = $this->_ini['servers'];
		elseif(defined(SP_FTP_SERVERS)) $ftp_servers = SP_FTP_SERVERS;
		else throw new Exception('error config: need service node');
		if(isset($this->_ini['account'])) $ftp_account = $this->_ini['account'];
		foreach(explode(" ", $ftp_servers) as $server){
			$this->addServer($server, $ftp_account['user'], $ftp_account['pass'], isset($ftp_account['pasv']) ? $ftp_account['pasv'] : false);
		}
	}


	/**
	 * 添加FTP服务器
	 * 
	 * @param string $ftp_server
	 * @param string $uname
	 * @param string $passwd
	 * @return void
	 */
	protected function addServer($ftp_server, $uname, $passwd, $pasv = false){
		$connector = $this->connect($ftp_server, $uname, $passwd, $pasv) or $this->write_log("error when connect to server $ftp_server");
		$this->_connectors[$ftp_server] = $connector;
	}
	
	/**
	 * 删除FTP服务器
	 * 
	 * @param string $ftp_server
	 * @return void
	 */
	protected function removeServer($ftp_server){
		if(isset($this->_connectors["$ftp_server"]))
			unset($this->_connectors["$ftp_server"]);
	}
	
	/**
	 * 连接FTP服务器
	 * 
	 * @param string $ftp_server
	 * @param string $uname
	 * @param string $passwd
	 * @return string
	 */
	function connect($ftp_server, $uname, $passwd, $pasv = false)
	{
		$ftp_port = $this->getOption('ftp_port');
		if (!$ftp_port) $ftp_port = 21;
		$timeout = $this->getOption('timeout');
		if (!$timeout) $timeout = 90;
		$connector = ftp_connect($ftp_server, $ftp_port, $timeout);
		if (!$connector) {
			$this->write_log("FTP connection has failed!");
			return false;
		}
		$login_result = ftp_login($connector, $uname, $passwd);
		if (!$login_result)
		{
			$this->write_log("Attempted to connect to $ftp_server for user $uname ");
			return false;
		} else {
			$this->write_log("Connected to $ftp_server, for user $uname ");
		}
		ftp_pasv($connector, (bool)$pasv);
		return $connector;
	}
	
	/**
	 * 上传文件
	 * 
	 * @param string $source_file
	 * @param string $dest_file
	 * @param boolean $is_binary
	 * @return bool
	 */
	function upload_file($dest_file, $source_file, $is_binary = true){
		$ftp_mode = $is_binary ? FTP_BINARY : FTP_ASCII;
		$ret = true;
		foreach($this->_connectors as $ftp_server => $connector){
			$this->mkdir_recursive($connector, 0755, dirname($dest_file));
			if(!ftp_put($connector, $dest_file, $source_file, $ftp_mode)){
				$ret = false;
				$this->write_log("ERROR when upload :$ftp_server: $dest_file : $source_file");
			}
		}
		return $ret;
	}
	
	/**
	 * 删除文件
	 * 
	 * @param string $delete_file
	 * @return bool
	 */
	function delete_file($delete_file){
		$ret = true;
		foreach($this->_connectors as $ftp_server => $connector){
			if(!@ftp_delete($connector, $delete_file)){
				$ret = false;
				$this->write_log("ERROR when delete :$ftp_server");
			}
		}
		return $ret;
	}	
	
	/**
	 * 创建文件夹
	 * 
	 * @param string $dir_name 针对FTP根目录的绝对路径,最前面用/
	 * @return none
	 */
	function mkdir($dir_name){
		foreach($this->_connectors as $ftp_server => $connector){
			$this->mkdir_recursive($connector, 0755, $dir_name);
		}
	}

	/**
	 * function description
	 * 
	 * @param
	 * @return void
	 */
	private function mkdir_recursive($ftpconn_id, $mode, $path)
	{
		if (strpos($path, '/') === FALSE) {
			return TRUE;
		}
		$dir = explode('/', trim($path, '/'));
		$path = '';
	    $ret = true;

		for ($i = 0,$len = count($dir); $i < $len; $i++)
		{
			$path .= '/' . $dir[$i];
			if(!@ftp_chdir($ftpconn_id, $path))
			{
				@ftp_chdir($ftpconn_id, '/');
				if(!ftp_mkdir($ftpconn_id, $path))
				{
					$ret = false;
	                break;
				} else {
	               @ftp_chmod($ftpconn_id, $mode, $path);
				}
			}
		}
		@ftp_chdir($ftpconn_id, '/');
	    return $ret;
	}
	
	/**
	 * 删除文件夹
	 * 
	 * @param string $dir_name 针对FTP根目录的绝对路径
	 * @return none
	 */
	function rmdir($dir_name){
		foreach($this->_connectors as $ftp_server => $connector){
			if(!@ftp_rmdir($connector, $dir_name)){
				$this->write_log("ERROR when rmdir :$ftp_server");
			}
		}
	}
	
	/**
	 * 退出众FTP
	 * 
	 * @param none
	 * @return none
	 */
	private function ftp_quit(){
		foreach($this->_connectors as $ftp_server => $connector){
			if(!@ftp_quit($connector)){
				$this->write_log("ERROR when quit: $ftp_server");
			}
		}
	}
	
	/**
	 * 写日志
	 * 
	 * @param string $msg
	 * @return none
	 */
	private function write_log($msg){
		array_push($this->_logs, $msg);
	}
	
	/**
	 * 打印日志
	 * 
	 * @param none
	 * @return none
	 */
	function print_log($return = FALSE){
		print_r($this->_logs, $return);
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
			$id = $this->getId($hash);
			$dir = substr($id, 0, 2) . '/' . substr($id, 2, 2). '/' . substr($id, 4, 2);
			$dest_file = $dir . '/' . substr($id, 6) . '.' . pathinfo($name, PATHINFO_EXTENSION);
		} else {
			$dest_file = $name;
		}
		$path_prefix = $this->getOption('path_prefix');
		if ($path_prefix) $dest_file = $path_prefix . $dest_file;
		$is_binary = isset($option['binary']) ? $option['binary'] : true;
		$ret = $this->upload_file($dest_file, $loc_file, $is_binary);
		return $ret ? $dest_file : false;
	}

	/**
	 * 根据id取出文件，由于是 FTP 存储，id一般是指一个文件的相对路径，此实例应用不广
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
	 * close
	 * 
	 * @return void
	 */
	public function close()
	{
		$this->ftp_quit();
	}
	
	/**
	 * Destructor
	 * 
	 * @return void
	 */
	public function __destruct()
	{
		$this->close();
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
		// TODO: 从FTP系统中浏览文件
		return ;
	}
}
