<?php

/**
 * Head Tag Module
 *
 * @author MeanEYE.rcf
 */

class head_tag extends Module {
	private static $_instance;
	private $tags = array();
	private $closeable_tags = array('script', 'style');

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($level, $params = array(), $children=array()) {
		switch ($params['action']) {
			case 'print_tag':
				$this->printTags($level);
				break;
		}
	}

	/**
	 * Adds head tag to the list
	 *
	 * @param string $name
	 * @param array $params
	 */
	public function addTag($name, $params) {
		$this->tags[] = array($name, $params);
	}

	/**
	 * Print previously added tags
	 *
	 * @param integer $level
	 */
	private function printTags($level) {
		$pretext = str_repeat("\t", $level);

		foreach ($this->tags as $tag)
			echo $pretext."<".$tag[0].$this->getTagParams($tag[1]).">".
				(in_array($tag[0], $this->closeable_tags) ? "</".$tag[0].">" : "")."\n";
	}

	/**
	 * Return formated parameter tags
	 *
	 * @param resource $params
	 */
	private function getTagParams($params) {
		$result = "";

		if (count($params))
			foreach ($params as $param=>$value)
				$result .= ' '.$param.'="'.$value.'"';

		return $result;
	}
}
?>
