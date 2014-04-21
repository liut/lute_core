<?php
/**
 *	作用于 Sitemap Write
 *  用来添加sitemap的url部分属性
 *
 *	@author qiuf
 */
class Sitemap_Url
{
	private $_elements = array();		//属性的集合

	public function __construct($loc, $priority = 1.00, $lastmod = NULL, $changefreq = 'daily')
	{
		if (empty($loc)) {
			throw new Exception('empty url loc value');
		}

		is_null($lastmod) && $lastmod = date(DATE_W3C);

		($priority < 0 || $priority > 1) && $priority = 1.00;

		empty($changefreq) && $changefreq = 'daily';

		$this->setLoc($loc);
		$this->setPriority($priority);
		$this->setLastmod($lastmod);
		$this->setChangefreq($changefreq); 
	}


	/**
	 *	属性集合的数组
	 */
	public function addElement($elementName,$content)
	{
		$this->_elements[$elementName]['name']     = $elementName;
		$this->_elements[$elementName]['content']  = $content;
	}
	 
	/**
	 *	 将多种Sitemap属性放入一个数组中
	 */
	public function addElementArray($elementArray)
	{
		foreach($elementArray as $elementName => $content)
		{
			$this->addElement($elementName,$content);
		}
	}
	/**
	 *	 返回在sitemap url中元素的集合
	 */
	public function getElements()
	{
		return $this->_elements;
	}
	/**
	 *	 将'loc'属性放入sitemap url中
	 */
	public function setLoc($loc)
	{
		$this->addElement('loc',$loc);
	}
	/**
	 *	 将'loc'属性放入sitemap url中
	 */
	public function setPriority($priority)
	{
		$this->addElement('priority',$priority);
	}
	/**
	 *	 将'lastmod'属性放入sitemap url中
	 */
	public function setLastmod($lastmod)
	{
		if(is_int($lastmod))
		{
			$lastmod = date(DATE_W3C, $lastmod);
		}
		$this->addElement('lastmod',$lastmod);
	}
	
	public function setChangefreq($changefreq)
	{
		$this->addElement('changefreq',$changefreq);
	}


	/**
	 * 添加图片s
	 * TODO: 图片允许添加 title
	 * 
	 * @param array $images
	 * @return void
	 **/
	public function addImages(array $images)
	{
		if ($images) {
			$imageLoc = [];

	 		foreach ($images as $img) {
	 			if (is_string($img) && $img) {
					$imageLoc[] = $this->makeSonElements(['name' => 'image:loc', 'content' => $img]);
	 			}
	 		}

	 		if ($imageLoc) {
				$this->addElement("image:image", $imageLoc);
	 		}
		}		
	}

	/**
	 * 返回对象的字符串形式
	 */
	public function __toString()
	{
		if (empty($this->_elements)) {
			return '';
		}

		$out = '<url>' . PHP_EOL;
		foreach($this->_elements as $el)
		{
			$content = $el['content'];

			if (is_array($el['content'])) {
				$content = '';
				foreach ($el['content'] as $row) {
					$content = sprintf("<%s>%s</%s>\n", $row['son_name'], $row['son_content'], $row['son_name']);
					$out .= sprintf("<%s>%s</%s>\n", $el['name'], $content, $el['name']);
				}
				
			} elseif (is_string($el['content'])) {
				$out .= sprintf("<%s>%s</%s>\n", $el['name'], $content, $el['name']);
			}

		}
		$out .= '</url>' . PHP_EOL;

		return $out;
	}

	/**
	 * 生成一个子标签
	 */
	private function makeSonElements($el)
	{
		return array('son_name' => $el['name'], 'son_content' => $el['content']);
	}
}