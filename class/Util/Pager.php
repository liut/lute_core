<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Util_Pager 分页计算和渲染器
 * 第一页 为 1
 *
 * @author liutao
 * @version $Id$
 */

// test
/*if(isset($_SERVER['argc']))
{
	$pager = new Util_Pager(900, 8);
	echo $pager->renderNav();
}*/
/**
 * Util_Pager
 *
 * <pre>
 * 需要用到的样式表(供参考，可根据需要修改)
 * .pager { margin: .8em auto; width: 95%; min-height: 600; padding: .8em 0; font: 1em/125% Tahoma, Arial, Helvetica, sans-serif; text-align: right; }
 * .pager a, .pager span.active { padding: .2em .6em; margin: auto .1em; border: 1px solid #eee; text-decoration:none; color: #666; }
 * .pager a:hover { color:#5B5B5B; border: 1px solid #666; }
 * .pager span.active {color:#fff; background-color:#5A5A5A; }
 *
 * </pre>
 *
 * example:
 * <code>
 * $total = 0; // $total = 900;
 * $page = 8; // $page = 1;
 * $size = 10;
 * $pager = new Util_Pager($total, $page, $size, "?page=".Util_Pager::MARK);
 * //$pager->setLabel('< prev', 'next >', 'first', 'last' );
 * $offset = $pager->getOffset();
 * $total = 200;
 * $pager->setTotal($total);
 * echo $pager->renderNav();
 * </code>
 *
 */
class Util_Pager
{
	/**
	 * 计算实际页码时的基数
	 *
	 * @var int
	 */
    public $baseIndex = 0;

	/**
	 * 每页记录数
	 *
	 * @var int
	 */
    public $size = -1;

	/**
	 * 符合条件的记录总数
	 *
	 * @var int
	 */
    public $total = -1;

	/**
	 * 符合条件的记录页数
	 *
	 * @var int
	 */
    public $pageCount = -1;

	/**
	 * 第一页的索引，从 0 开始
	 *
	 * @var int
	 */
    public $first = 0;

	/**
	 * 第一页的页码
	 *
	 * @var int
	 */
    public $firstNumber = -1;

	/**
	 * 最后一页的索引，从 0 开始
	 *
	 * @var int
	 */
    public $last = -1;

	/**
	 * 最后一页的页码
	 *
	 * @var int
	 */
    public $lastNumber = -1;

	/**
	 * 上一页的索引
	 *
	 * @var int
	 */
    public $prev = -1;

	/**
	 * 上一页的页码
	 *
	 * @var int
	 */
    public $prevNumber = -1;

	/**
	 * 下一页的索引
	 *
	 * @var int
	 */
    public $next = -1;

	/**
	 * 下一页的页码
	 *
	 * @var int
	 */
    public $nextNumber = -1;

	/**
	 * 当前页的索引
	 *
	 * @var int
	 */
    public $curr = -1;

	/**
	 * 当前页的页码
	 *
	 * @var int
	 */
    public $currNumber = -1;

    /**
     * 页码替换标识
     */
	const MARK = '~_PAGE_~';

	/**
	 * 页面URL格式
	 *
	 * @var int
	 */
    public $_format = "?page=~_PAGE_~";

	/**
	 * 偏移量
	 *
	 * @var int
	 */
    public $offset = 0;

    /**
	 * Label
	 *
	 * @var int
	 */
    private $_labels = array(
		'prev' => ' &lt; ', 	// &#8249;
		'next' => ' &gt; ', 	// &#8250;
		'first' => ' &lt;&lt; ', 	// &laquo;
		'last' => ' &gt;&gt; ', 	// &raquo;
	);

	/**
	 * 显示页条目数
	 *
	 * @var int
	 */
    public $_length = 8;

	/**
	 * 链接的属性
	 *
	 * @var string
	 */
    public $_link_attrs = '';

	/**
	 * 构造函数
	 *
	 *
	 * @param int $total
	 * @param int $cur_page
	 * @param int $size
	 * @param string $format
	 * @param bool $label
	 *
	 * @return Util_Pager
	 */
    public function __construct($total, $cur_page = 1, $size = 20, $format = '?page=~_PAGE_~', $labels = null)
    {
		if(is_array($total)){
			extract($total);
		}
		$total = intval($total);
		if($total < 0) $total = 0;
		if($cur_page < 1) $cur_page = 1;
		if($size < 1) $size = 1;
		//$this->pageCount = ceil($total/$size);
		//if($cur_page > $pageCount) $cur_page = $pageCount;
		$this->curr = $cur_page - 1;
        $this->size = $size;
		$this->_format = $format;
		if(is_array($labels)) {
			$this->setLabel($labels);
		}

		$this->offset = ($this->curr - $this->baseIndex) * $this->size;
		if($total > 0) $this->setTotal($total);
    }

	/**
	 * 设置记录总数，从而更新分页参数
	 *
	 * @param int $total
	 */
    public function setTotal($total)
    {
        $this->total = $total;
        $this->_calculate();
    }

	/**
	 * 设置URL格式
	 *
	 * @param string $format
	 */
    public function setUrlFormat($format)
    {
        $this->_format = $format;
    }

    /**
     * 返回分页信息，方便在模版中使用，不建议使用
     *
     * @return array
     */
    public function getData()
    {
        $data = array(
            'size' => $this->size,
            'totalCount' => $this->totalCount,
            'total' => $this->total,
            'pageCount' => $this->pageCount,
            'first' => $this->first,
            'firstNumber' => $this->firstNumber,
            'last' => $this->last,
            'lastNumber' => $this->lastNumber,
            'prev' => $this->prev,
            'prevNumber' => $this->prevNumber,
            'next' => $this->next,
            'nextNumber' => $this->nextNumber,
            'curr' => $this->curr,
            'currNumber' => $this->currNumber,
        );

        $data['pagesNumber'] = array();
        for ($i = 0; $i < $this->pageCount; $i++) {
            $data['pagesNumber'][$i] = $i + 1;
        }

        return $data;
    }

    /**
     * 产生指定范围内的页面索引和页号
     *
     * @param int $curr
     *
     * @return array
     */
    public function getNavbarIndexs($curr = 0)
    {
        $mid = intval($this->_length / 2);
        if ($curr < $this->first) {
            $curr = $this->first;
        }
        if ($curr > $this->last) {
            $curr = $this->last;
        }

        $begin = $curr - $mid;
        if ($begin < $this->first) { $begin = $this->first; }
        $end = $begin + $this->_length - 1;
        if ($end >= $this->last) {
            $end = $this->last;
            $begin = $end - $this->_length + 1;
            if ($begin < $this->first) { $begin = $this->first; }
        }

        $data = array();
        for ($i = $begin; $i <= $end; $i++) {
			$item = array();
			$item['idx'] = $i;
			$item['num'] = ($i + 1 - $this->baseIndex);
			if($i == $this->curr) $item['act'] = true;
			$data[] = $item;
        }
        return $data;
    }

    /**
     * 计算各项分页参数
     */
    protected function _calculate()
    {
        $this->pageCount = ceil($this->total / $this->size);
        $this->first = $this->baseIndex;
        $this->last = $this->pageCount + $this->baseIndex - 1;

        if ($this->last < $this->baseIndex) {
            $this->last = $this->baseIndex;
        }

        if ($this->curr >= $this->pageCount + $this->baseIndex) {
            $this->curr = $this->last;
        }

        if ($this->curr < $this->baseIndex) {
            $this->curr = $this->first;
        }


        if ($this->curr < $this->last - 1) {
            $this->next = $this->curr + 1;
        } else {
            $this->next = $this->last;
        }

        if ($this->curr > $this->baseIndex) {
            $this->prev = $this->curr - 1;
        } else {
            $this->prev = $this->baseIndex;
        }

        $this->firstNumber = $this->first + 1 - $this->baseIndex;
        $this->lastNumber = $this->last + 1 - $this->baseIndex;
        $this->nextNumber = $this->next + 1 - $this->baseIndex;
        $this->prevNumber = $this->prev + 1 - $this->baseIndex;
        $this->currNumber = $this->curr + 1 - $this->baseIndex;
		$this->offset = ($this->curr - $this->baseIndex) * $this->size;

    }


	/**
	 * 生成页面转向Panel
	 *
	 * @param boolean $is_return
	 */
	function renderNav($is_return = FALSE)
	{
		$out = '<div class="pager">'."\n";
		$out .= $this->renderLinks(true);
        $out .= "</div>\n";

		if ($is_return) return $out;
		echo $out;
	}

	/**
	 * 生成页面转向链接
	 *
	 * @param boolean $is_return
	 */
	function renderLinks($is_return = FALSE)
	{
		$out = '';
		$data = $this->getNavbarIndexs($this->curr);
		if($this->curr > $this->first + 1) {
			empty($this->_labels['first']) || $out .= "<a href=\"". $this->getUrl($this->firstNumber) ."\" ".$this->_link_attrs.">" . $this->_labels['first'] . "</a>" . PHP_EOL;
		}
		if($this->curr > $this->first) {
			empty($this->_labels['prev']) || $out .= "<a href=\"". $this->getUrl($this->prevNumber) ."\" ".$this->_link_attrs.">" . $this->_labels['prev'] . "</a>" . PHP_EOL;
		}
		foreach($data as $p)
		{
            if ($p['idx'] == $this->curr) {
                $out .= "<span class=\"active\">" . $p['num'] . "</span>";
            } else {
	            $out .= "<a href=\"". $this->getUrl($p['num']) ."\" ".$this->_link_attrs.">" . $p['num'] . "</a>";
			}
			$out .= PHP_EOL;
		}
		if($this->curr < $this->last) {
			empty($this->_labels['next']) || $out .= "<a href=\"". $this->getUrl($this->nextNumber) ."\" ".$this->_link_attrs.">" . $this->_labels['next'] . "</a>" . PHP_EOL;
			if ($this->next < $this->last)
			empty($this->_labels['last']) || $out .= "<a href=\"". $this->getUrl($this->lastNumber) ."\" ".$this->_link_attrs.">" . $this->_labels['last'] . "</a>" . PHP_EOL;
		}
/*
        for ($i = 0; $i < $this->pageCount; $i++) {

            if ($i == $this->curr) {
                $out .= "<span class=\"curr\">" . ($i + 1) . "</span>\n";
            } else {
	            $out .= "<a href=\"?page=". sprintf($this->_format, $i) ."{$i}\">" . ($i + 1) . "</a>\n";
			}

        }
*/
        //$out .= "\n";

		if ($is_return) return $out;
		echo $out;
	}

	/**
	 * 根据页号返回 URL
	 *
	 * @param int $page
	 * @return string
	 */
	 function getUrl($page = 1)
	{
		$pos = strpos($this->_format, self::MARK);
		if ($pos !== FALSE) {
			return strtr($this->_format, [self::MARK => $page]);
		}
		return sprintf($this->_format, $page);
	}

	/**
	 * 设置显示名称
	 *
	 * @param label
	 * @return mixed
	 */
	public function setLabel($prev, $next = null, $first = null, $last = null)
	{
		if(is_array($prev)) extract($prev);
		is_string($prev) && $this->_labels['prev'] = $prev;
		is_string($next) && $this->_labels['next'] = $next;
		is_string($first) && $this->_labels['first'] = $first;
		is_string($last) && $this->_labels['last'] = $last;
		return $this;
	}

	/**
	 * return offset
	 *
	 * @return int
	 */
	public function getOffset()
	{
		return $this->offset;
	}

	/**
	 * function description
	 *
	 * @param $length
	 * @return void
	 */
	public function setLength($length)
	{
		if($length > 5 && $length < 19) $this->_length = $length;
		return $this;
	}

	public function __toString()
	{
		return (string)$this->currNumber;
	}

	public function setLinkAttrs($str)
	{
		is_string($str) && $this->_link_attrs = $str;
	}
}
