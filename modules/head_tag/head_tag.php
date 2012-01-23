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

	private $optimizer = null;
	private $optimizer_key = '';
	private $optimizer_page = '';
	private $optimizer_show_control = false;

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
	public function transferControl($params, $children) {
		if (isset($params['action']))
			switch ($params['action']) {
				case 'print_tag':
					$this->printTags();
					break;
			}
	}

	/**
	 * Redefine abstract methods
	 */
	public function onInit() {
	}

	public function onDisable() {
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
	 * Add Google Site optimizer script to the page
	 *
	 * @param string $code
	 * @param string $key
	 * @param string $page
	 * @param boolean $show_control
	 */
	public function addGoogleSiteOptimizer($code, $key, $page, $show_control) {
		$this->optimizer = $code;
		$this->optimizer_key = $key;
		$this->optimizer_page = $page;
		$this->optimizer_show_control = $show_control;
	}

	/**
	 * Print previously added tags
	 */
	private function printTags() {
		// if page_info module is loaded, ask it to add its own tags
		if (class_exists('page_info'))
			page_info::getInstance()->addElements();

		// merge tag lists
		$tags = array_merge($this->meta_tags, $this->link_tags, $this->script_tags, $this->tags);
		
		foreach ($tags as $tag)
			echo "<".$tag[0].$this->getTagParams($tag[1]).">".
				(in_array($tag[0], $this->closeable_tags) ? "</".$tag[0].">" : "");

		// print google analytics code if needed
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

		// print google site optimizer code if needed
		if (!is_null($this->optimizer)) {
			$template = new TemplateHandler('google_site_optimizer.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);
	
			$params = array(
							'code'	=> $this->optimizer,
							'key'	=> $this->optimizer_key,
							'page'	=> $this->optimizer_page,
							'show_control'	=> $this->optimizer_show_control
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
