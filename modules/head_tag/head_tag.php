<?php

/**
 * Head Tag Module
 *
 * @author MeanEYE.rcf
 */

class head_tag extends Module {
	private static $_instance;
	private $tags = array();
	private $meta_tags = array();
	private $link_tags = array();
	private $script_tags = array();
	private $closeable_tags = array('script', 'style');
	private $analytics = null;

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
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children=array()) {
		if (isset($params['action']))
			switch ($params['action']) {
				case 'print_tag':
					$this->printTags();
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
		$name = strtolower($name);
		$data = array($name, $params);
		
		switch ($name) {
			case 'meta':
				$this->meta_tags[] = $data;
				break;
				
			case 'link':
				$this->link_tags[] = $data;
				break;
				
			case 'script':
				$this->script_tags[] = $data;
				break;
			
			default:
				$this->tags[] = array($name, $params);
				break;
		}
	}
	
	/**
	 * Add Google Analytics script to the page
	 *  
	 * @param string $code
	 */
	public function addGoogleAnalytics($code) {
		$this->analytics = $code;
	}

	/**
	 * Print previously added tags
	 */
	private function printTags() {
		$tags = array_merge($this->meta_tags, $this->link_tags, $this->script_tags, $this->tags);
		
		foreach ($tags as $tag)
			echo "<".$tag[0].$this->getTagParams($tag[1]).">".
				(in_array($tag[0], $this->closeable_tags) ? "</".$tag[0].">" : "");

		if (!is_null($this->analytics)) {
			$template = new TemplateHandler('google_analytics.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);
	
			$params = array(
							'code'	=> $this->analytics
						);
	
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();		
		}
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
