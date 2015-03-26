<?PHP


/**
 * 存储对象单元, 基本上模拟了 _FILES 里的成员，兼照顾图片
 *
 * @package Imsto
 * @author liut
 **/
class Imsto_Entry
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
	public $appid = 0;
	public $userid = 0;
	public $tags = '';


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
			$entry->error = isset($file['error']) ? $file['error'] : UPLOAD_ERR_OK;
			return $entry;
		}

		if (isset($file['error']) && $file['error'] != UPLOAD_ERR_OK) {
			$message = static::codeToMessage($file['error']);
			throw new Exception($message, $file['error']);
		}

		return FALSE;
	}

	private static function codeToMessage($code)
	{
		switch ($code) {
			case UPLOAD_ERR_INI_SIZE:
				$message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
				break;
			case UPLOAD_ERR_FORM_SIZE:
				$message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
				break;
			case UPLOAD_ERR_PARTIAL:
				$message = "The uploaded file was only partially uploaded";
				break;
			case UPLOAD_ERR_NO_FILE:
				$message = "No file was uploaded";
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
				$message = "Missing a temporary folder";
				break;
			case UPLOAD_ERR_CANT_WRITE:
				$message = "Failed to write file to disk";
				break;
			case UPLOAD_ERR_EXTENSION:
				$message = "File upload stopped by extension";
				break;

			default:
				$message = "Unknown upload error";
				break;
		}
		return $message;
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
