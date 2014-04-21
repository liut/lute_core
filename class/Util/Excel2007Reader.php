<?PHP


/**
 * Excel2007 Reader
 *
 * @package Util
 * @author liut
 **/
class Util_Excel2007Reader extends Util_OpenXml implements IteratorAggregate
{
	/**
	 * Xml Schema - SpreadsheetML
	 *
	 * @var string
	 */
	const SCHEMA_SPREADSHEETML = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

	/**
	 * Xml Schema - DrawingML
	 *
	 * @var string
	 */
	const SCHEMA_DRAWINGML = 'http://schemas.openxmlformats.org/drawingml/2006/main';

	/**
	 * Xml Schema - Shared Strings
	 *
	 * @var string
	 */
	const SCHEMA_SHAREDSTRINGS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings';

	/**
	 * Xml Schema - Worksheet relation
	 *
	 * @var string
	 */
	const SCHEMA_WORKSHEETRELATION = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet';

	/**
	 * Xml Schema - Slide notes relation
	 *
	 * @var string
	 */
	const SCHEMA_SLIDENOTESRELATION = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide';

	private $_sheetData = [];


	/**
	 * constructor
	 * 
	 * @param string fileName
	 */
	public function __construct($fileName)
	{
		// Check if file exists
		if (!file_exists($fileName)) {
			throw new Exception("Could not open " . $fileName . " for reading! File does not exist.");
		}

		// Check if zip class exists
		if (!class_exists('ZipArchive')) {
			throw new Exception("ZipArchive library is not enabled");
		}

		// Document data holders
		$sharedStrings = array();
		$worksheets = array();
		$documentBody = array();
		$coreProperties = array();

		// Open OpenXML package
		$package = new ZipArchive();
		$package->open($fileName);

		// Read relations and search for officeDocument
		$relationsXml = $package->getFromName('_rels/.rels');
		if ($relationsXml === false) {
			#require_once 'Zend/Search/Lucene/Exception.php';
			throw new Exception('Invalid archive or corrupted .xlsx file.');
		}
		$relations = simplexml_load_string($relationsXml);
		foreach ($relations->Relationship as $rel) {
			if ($rel["Type"] == static::SCHEMA_OFFICEDOCUMENT) {
				// Found office document! Read relations for workbook...
				$workbookRelations = simplexml_load_string($package->getFromName( $this->absoluteZipPath(dirname($rel["Target"]) . "/_rels/" . basename($rel["Target"]) . ".rels")) );
				$workbookRelations->registerXPathNamespace("rel", static::SCHEMA_RELATIONSHIP);

				// Read shared strings
				$sharedStringsPath = $workbookRelations->xpath("rel:Relationship[@Type='" . self::SCHEMA_SHAREDSTRINGS . "']");
				$sharedStringsPath = (string)$sharedStringsPath[0]['Target'];
				$xmlStrings = simplexml_load_string($package->getFromName( $this->absoluteZipPath(dirname($rel["Target"]) . "/" . $sharedStringsPath)) );
				if (isset($xmlStrings) && isset($xmlStrings->si)) {
					foreach ($xmlStrings->si as $val) {
						if (isset($val->t)) {
							$sharedStrings[] = (string)$val->t;
						} elseif (isset($val->r)) {
							$sharedStrings[] = $this->_parseRichText($val);
						}
					}
				}

				// Loop relations for workbook and extract worksheets...
				foreach ($workbookRelations->Relationship as $workbookRelation) {
					if ($workbookRelation["Type"] == self::SCHEMA_WORKSHEETRELATION) {
						$worksheets[ str_replace( 'rId', '', (string)$workbookRelation["Id"]) ] = simplexml_load_string(
							$package->getFromName( $this->absoluteZipPath(dirname($rel["Target"]) . "/" . dirname($workbookRelation["Target"]) . "/" . basename($workbookRelation["Target"])) )
						);
					}
				}

				break;
			}
		}

		// Sort worksheets
		ksort($worksheets);

		// Extract contents from worksheets
		foreach ($worksheets as $sheetKey => $worksheet) {
			$rows = [];
			foreach ($worksheet->sheetData->row as $row) {
				$line = [];
				foreach ($row->c as $c) {
					// Determine data type
					$dataType = (string)$c["t"];
					switch ($dataType) {
						case "s":
							// Value is a shared string
							if ((string)$c->v != '') {
								$value = $sharedStrings[intval($c->v)];
							} else {
								$value = '';
							}

							break;

						case "b":
							// Value is boolean
							$value = (string)$c->v;
							if ($value == '0') {
								$value = false;
							} else if ($value == '1') {
								$value = true;
							} else {
								$value = (bool)$c->v;
							}

							break;

						case "inlineStr":
							// Value is rich text inline
							$value = $this->_parseRichText($c->is);

							break;

						case "e":
							// Value is an error message
							if ((string)$c->v != '') {
								$value = (string)$c->v;
							} else {
								$value = '';
							}

							break;

						default:
							// Value is a string
							$value = (string)$c->v;

							// Check for numeric values
							if (is_numeric($value) && $dataType != 's') {
								if ($value == (int)$value) $value = (int)$value;
								elseif ($value == (float)$value) $value = (float)$value;
								elseif ($value == (double)$value) $value = (double)$value;
							}
					}

					$line[] = $value;
				}
				$rows[] = $line;
			}
			$this->_sheetData[] = $rows;
		}

		// Read core properties
		//$coreProperties = $this->extractMetaData($package);

		// Close file
		$package->close();


	}

	/**
	 * 返回第一列的Iterator
	 * 
	 */
	public function getIterator()
	{
		return new ArrayIterator(reset($this->_sheetData));
	}

	/**
	 * Parse rich text XML
	 *
	 * @param SimpleXMLElement $is
	 * @return string
	 */
	private function _parseRichText($is = null) {
		$value = array();

		if (isset($is->t)) {
			$value[] = (string)$is->t;
		} else {
			foreach ($is->r as $run) {
				$value[] = (string)$run->t;
			}
		}

		return implode('', $value);
	}

} // END class



/**
 * OpenXML document.
 *
 */
abstract class Util_OpenXml
{
	/**
	 * Xml Schema - Relationships
	 *
	 * @var string
	 */
	const SCHEMA_RELATIONSHIP = 'http://schemas.openxmlformats.org/package/2006/relationships';

	/**
	 * Xml Schema - Office document
	 *
	 * @var string
	 */
	const SCHEMA_OFFICEDOCUMENT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';

	/**
	 * Xml Schema - Core properties
	 *
	 * @var string
	 */
	const SCHEMA_COREPROPERTIES = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';

	/**
	 * Xml Schema - Dublin Core
	 *
	 * @var string
	 */
	const SCHEMA_DUBLINCORE = 'http://purl.org/dc/elements/1.1/';

	/**
	 * Xml Schema - Dublin Core Terms
	 *
	 * @var string
	 */
	const SCHEMA_DUBLINCORETERMS = 'http://purl.org/dc/terms/';

	/**
	 * Extract metadata from document
	 *
	 * @param ZipArchive $package	ZipArchive OpenXML package
	 * @return array	Key-value pairs containing document meta data
	 */
	protected function extractMetaData(ZipArchive $package)
	{
		// Data holders
		$coreProperties = array();

		// Read relations and search for core properties
		$relations = simplexml_load_string($package->getFromName("_rels/.rels"));
		foreach ($relations->Relationship as $rel) {
			if ($rel["Type"] == static::SCHEMA_COREPROPERTIES) {
				// Found core properties! Read in contents...
				$contents = simplexml_load_string(
					$package->getFromName(dirname($rel["Target"]) . "/" . basename($rel["Target"]))
				);

				foreach ($contents->children(static::SCHEMA_DUBLINCORE) as $child) {
					$coreProperties[$child->getName()] = (string)$child;
				}
				foreach ($contents->children(static::SCHEMA_COREPROPERTIES) as $child) {
					$coreProperties[$child->getName()] = (string)$child;
				}
				foreach ($contents->children(static::SCHEMA_DUBLINCORETERMS) as $child) {
					$coreProperties[$child->getName()] = (string)$child;
				}
			}
		}

		return $coreProperties;
	}

	/**
	 * Determine absolute zip path
	 *
	 * @param string $path
	 * @return string
	 */
	protected function absoluteZipPath($path) {
		$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
		$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
		$absolutes = array();
		foreach ($parts as $part) {
			if ('.' == $part) continue;
			if ('..' == $part) {
				array_pop($absolutes);
			} else {
				$absolutes[] = $part;
			}
		}
		return implode('/', $absolutes);
	}
}