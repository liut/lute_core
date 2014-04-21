<?PHP


/**
 * 存储对象单元, 基本上模拟了 _FILES 里的成员，兼照顾图片
 *
 * @package Sp
 * @author liut
 **/
class Storage_Entry 
{
	const ERR_NOT_IMAGE = -3;

	public $error = 0;
	public $name;
	public $type;
	public $size;
	public $tmp_name;
	public $content;
	private $_info;
	public $meta;
	public $ext;
	protected $_exif_data = NULL;


	/**
	 * build Entry instance from Upload Files
	 * 
	 * @param string or array $field
	 * @return object | FALSE
	 */
	public static function fromUpload(array $file)
	{
		if (isset($file['tmp_name']) && isset($file['name'])
			&& isset($file['type']) && isset($file['size']) && $file['tmp_name']) {
			$entry = new self($file['tmp_name'], $file['name'], $file['size'], $file['type']);
			$entry->error = isset($file['error']) ? $file['error'] : 0;
			return $entry;
		}

		return FALSE;
	}

	/**
	 * constructor
	 * 
	 * @return object
	 */
	public function __construct($file, $name = NULL, $size = NULL, $type = NULL)
	{
		// Check if file exists
		if (!file_exists($file)) {
			throw new Exception("Could not open " . $file . " for reading! File does not exist.");
		}

		if (!is_readable($file)) {
			throw new Exception("Error: $file is not readable", 1);
		}

		$this->tmp_name = $file;
		$this->name = is_null($name) ? basename($file) : $name;
		$this->size = is_null($size) ? filesize($file) : $size;
		is_null($type) || $this->type = $type;

		$this->_info = GetImageSize($file);
		if ($this->_info) {
			$this->type = $this->_info['mime'];
			$this->meta = [
				'width'=> $this->_info[0],
				'height' => $this->_info[1],
				'imgtype' => $this->_info[2],
				'mime' => $this->_info['mime'],
			];

			if ($this->_info[2] === 2) {
				$this->ext = 'jpg';
			} else {
				$this->ext = image_type_to_extension($this->_info[2], FALSE);
			}
		}
		else {
			// is not picture
			$this->error = self::ERR_NOT_IMAGE;

			// check mime type
			if (is_null($this->type) && is_file($this->tmp_name)) {
				$finfo = new finfo(FILEINFO_MIME);
				if (!$finfo) {
					Log::notice('Opening fileinfo database failed', __CLASS__);
				}
				$finfo && $this->type = $finfo->file($this->tmp_name);
			}
			$this->ext = strtolower(substr(strrchr($this->name, '.'), 1));
		}
	}

	public function isImage()
	{
		return ($this->_info !== FALSE);
	}

	public function isValid()
	{
		return !is_null($this->tmp_name) && !empty($this->tmp_name)
			|| $this->content;
	}

	public function stripImage($opt = NULL)
	{
		if ($this->isImage() && class_exists('Imagick', FALSE)) {
			$file = $this->tmp_name;
			$im = new Imagick($file);
			$im->stripImage(); // 清除图片中所有的注释和属性内容

			if( $this->_info[2] == IMAGETYPE_JPEG ) {
				$quality = $im->getImageCompressionQuality();
				$max_quality =  is_array($opt) && isset($opt['max_quality']) ? $opt['max_quality'] : $quality;
				if ($quality > $max_quality) {
					$im->setImageCompressionQuality($max_quality);
				}
			}

			if ($this->_info[2] == IMAGETYPE_GIF) {
				$im->writeImages($file, true);
			} else {
				$im->writeImage($file); // 覆盖原文件
			}

			$im->clear();
			$im->destroy();

			$this->size = filesize($file);
		}
	}

	public function getExifData()
	{
		if ($this->_exif_data === NULL) {
			if (!$this->isImage() || !function_exists('exif_read_data')) {
				return FALSE;
			}

			$this->_exif_data = exif_read_data($this->tmp_name);
		}

		return $this->_exif_data;
	}

} // END class 