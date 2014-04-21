<?php
/**
 *  网站sitemap输入接口
 *	Sitemap Write class
 *
 *  TODO: 请实现在指定目录直接生成 xml 和 txt 两个文件，如：添加一个 doc_root 参数
 *
 *	@author qiuf
 */
class Sitemap_Writer
{
	private $_filename = NULL;
	private $_direct_save = FALSE;
	private $_handle_xml = NULL;
	private $channels      = array();  // 标签元素集合
	private $urls           = array();  // 使用sitemapurl类写出的url标签的集合

	public function __construct($opt = NULL)
	{
		if (is_array($opt)) {
			if (isset($opt['filename']) && $opt['filename']) {
				$this->_filename = $opt['filename'];
			}

			if (isset($opt['direct_save'])) {
				$this->_direct_save = $opt['direct_save'];
			}

			if ($this->_filename && $this->_direct_save) {
				$this->_handle_xml = fopen($this->_filename, 'w');

				if (!$this->_handle_xml) {
					throw new Exception("Open file error " . $this->_filename, 101);
				}

				$head = $this->printHead();

				fwrite($this->_handle_xml, $head);
			}
		}
	}
	
	/**
	 *	产生一个simtmap文件
	 */
	public function genarateSitemap()
	{
		if ($this->_handle_xml) {
			return;
		}

		$out = $this->printHead();
		$out .= $this->printItems();
		$out .= $this->printTale();
		
		return $out;
	}
	
	/**
	 *	创建一个<url></url>部分
	 * @deprecated
	 */
	public function createNewUrl()
	{
		$Url = new Sitemap_Url();
		return $Url;
	}

	/**
	 *	 将一个URL部分加入到sitemap主体中
	 * @param string $loc 地址
	 * @param numeric $priority 优先级
	 * @param string $lastmod 修改时间
	 * @param string $changefreq 更新频度
	 */
	public function addUrl($loc, $priority = 1.00, $lastmod = NULL, $changefreq = 'daily', $images = NULL)
	{
		if (is_string($loc)) {
			$url = new Sitemap_Url($loc, $priority, $lastmod, $changefreq);

			if (is_array($images)) {
				$url->addImages($images);
			}
		}

		if (is_object($loc)) {
			$url = $loc;
		}

		if (isset($url) && is_object($url)) {
			if (is_resource($this->_handle_xml)) {
				fwrite($this->_handle_xml, (string)$url);
			}
			else {
				$this->urls[] = $url;
			}
			
		}

	}
	/**
	*	输出一个XML命名空间
	*/
	public function printHead()
	{
		$out  = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$out .=	'<urlset '.
			'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '.
			'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" '.
			'>'."\n";
		return $out;
	}


	/**
	*	输出<url>部分的格式
	* @deprecated
	*/
	public function printItems()
	{
			$out = '';
		foreach ($this->urls as $url){
			
			$out .= (string)$url;
		}
		return $out;
	}

	/**
	*	输出XML标签的关闭部分
	*/
	public function printTale()
	{
		return '</urlset>' . PHP_EOL;
	}

	/**
	 * 析构函数 关闭文件
	 */
	public function __destruct()
	{
		if ($this->_handle_xml) {
			$tail = $this->printTale();
			fwrite($this->_handle_xml, $tail);
			fclose($this->_handle_xml);
		}
	}
 }