<?php

/**
 * HEAD MODULE
 * 
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */ 

class head_tag extends Module {
	var $tags;
	var $closeable_tags = array('script', 'style');
	
	/**
	 * Constructor
	 *
	 * @return mod_head
	 */
	function head_tag() {
		$this->tags = array();

		$this->file = __FILE__;
		parent::Module();
	}
	
	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level 
	 */
	function transferControl($level, $params = array(), $children=array()) {
		switch ($params['action']) {
			case 'print_tag':
				$this->printTags($level); 
				break;
		}
	}
	
	/**
	 * Print previously added tags
	 *
	 * @param integer $level
	 */
	function printTags($level) {
		$pretext = str_repeat("\t", $level);
		
		sort($this->tags);
		foreach ($this->tags as $tag) 
			echo $pretext."<".$tag[0].$this->getTagParams($tag[1]).">".
				(in_array($tag[0], $this->closeable_tags) ? "</".$tag[0].">" : "")."\n";
	}
	
	/**
	 * Adds head tag to the list
	 *
	 * @param string $name
	 * @param array $params
	 */
	function addTag($name, $params) {
		$this->tags[] = array($name, $params);
	}
	
	/**
	 * Return formated parameter tags
	 *
	 * @param resource $params
	 */
	function getTagParams($params) {
		$result = "";
		
		if (count($params))
			foreach ($params as $param=>$value)
				$result .= ' '.$param.'="'.$value.'"';
		
		return $result;
	}	
}
?>