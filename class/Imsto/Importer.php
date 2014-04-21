<?PHP

/**
 * Imsto data Importer
 *
 * Usage:
 *  imsto import -s roof file1 [file2] [file3]
 * 	imsto import -s roof -dir directory
 * 	imsto import -s roof -archive archive.zip
 * 
 *
 *
 *
 * @package core
 * @author liut
 **/
class Imsto_Importer
{
	private $_bin_path = '';

	private $_roof = '';
	/**
	 * constructor
	 *
	 *
	 * @param $bin_path
	 * @return self
	 **/
	public function __construct($roof)
	{
		if (empty($roof)) {
			throw new Exception("Error: roof is empty");
		}
		$this->_roof = $roof;
	}

	public function setBinPath($path)
	{
		if (is_dir($path)) {
			$this->_bin_path = $path;
		}
	}

	public function getCmd()
	{
		$cmd = 'imsto';
		if (empty($this->_bin_path)) {
			$out = [];
			if (!exec('which '.$cmd, $out, $retval) || empty($out[0]) || substr($out[0], 0, 1) != '/') {
				// var_dump($out, $retval);
				throw new Exception("Error: $cmd not found");
			}
			return $out[0];
		}
		$cmd = $this->_bin_path . '/' . 'imsto';
		if (!is_executable($cmd)) {
			throw new Exception("Error: $cmd is not executable");
		}

		return $cmd;
	}

	/**
	 * 
	 * @param $args array
	 * @return array
	 */
	public function runCmd($args)
	{
		$cmd = $this->getCmd();
		$line = sprintf("%s import -s %s %s", $cmd, $this->_roof, implode(' ', $args));
		// echo $line, PHP_EOL;
		exec($line, $output, $retval);
		// TODO: process retval
		return $this->_strip($output);
	}

	/**
	 * add one or more files
	 * 
	 * @param $file string or array
	 * @return array
	 */
	public function addFile($file)
	{
		if (is_array($file)) {
			$args = $file;
		} else {
			$args = [$file];
		}

		return $this->runCmd($args);
	}

	/**
	 * add directory
	 * 
	 * @param $dir string
	 * @return array
	 */
	public function addDir($dir)
	{
		if (is_dir($dir)) {
			return $this->runCmd(['-dir', $dir]);
		}
		
		return false;
	}

	/**
	 * add all images from a zip file
	 * 
	 * @param $dir string
	 * @return array
	 */
	public function addZip($zipfile)
	{
		if (is_readable($zipfile)) {
			return $this->runCmd(['-archive', $zipfile]);
		}

		return false;
	}

	private function _strip($out)
	{
		$ret = [];
		foreach ($out as $line) {
			if (strncmp($line, 'ok', 2) == 0) {
				$ret[] = str_getcsv($line, ' ');
			}
			else {
				$ret[] = explode(' ', $line, 2);
			}
		}
		return $ret;
	}
} // END class Imsto_Import
