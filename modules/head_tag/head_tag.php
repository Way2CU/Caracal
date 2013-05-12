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
				$this->tags[] = $data;
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
	 * Show specified tag.
	 * 
	 * @param object $tag
	 */
	private function printTag($tag, $body=null) {
		print "<{$tag[0]}{$this->getTagParams($tag[1])}>";
		print in_array($tag[0], $this->closeable_tags) || !is_null($body) ? "{$body}</{$tag[0]}>" : "";
	}

	/**
	 * Show file associated with specified tag.
	 *
	 * @param object $tag
	 */
	private function printFile($tag) {
		switch ($tag[0]) {
			case 'link':
				$body = null;
				if (array_key_exists('rel', $tag[1]) && $tag[1]['rel'] == 'stylesheet') {
					$body = file_get_contents($tag[1]['href']);
					$tag = array('style', array('type' => 'text/css'));
					unset($tag[1]['href']);
				}

				$this->printTag($tag, $body);
				break;

			case 'script':
				$body = file_get_contents($tag[1]['src']);
				unset($tag[1]['src']);
				$this->printTag($tag, $body);
				break;

			default:
				$this->printTag($tag);
				break;
		}
	}

	/**
	 * Print previously added tags
	 */
	private function printTags() {
		global $include_scripts, $optimize_code;

		// if page_info module is loaded, ask it to add its own tags
		if (class_exists('page_info'))
			page_info::getInstance()->addElements();

		// merge tag lists
		$tags = array_merge($this->tags, $this->meta_tags, $this->script_tags, $this->link_tags);
		
		if (class_exists('CodeOptimizer') && $optimize_code) {
			// use code optimizer if possible
			$optimizer = CodeOptimizer::getInstance();
			$unhandled_tags = array_merge($this->tags, $this->meta_tags);

			foreach ($this->script_tags as $script)
				if (!$optimizer->addScript($script[1]['src']))
					$unhandled_tags []= $script;

			foreach ($this->link_tags as $link)
				if (isset($link[1]['rel']) && $link[1]['rel'] == 'stylesheet' && !$optimizer->addStyle($link[1]['href']))
					$unhandled_tags [] = $link;

			foreach ($unhandled_tags as $tag)
				$this->printTag($tag);

			// print optimized code
			$optimizer->printData();

		} else if ($include_scripts) {
			// just include javascript in body
			foreach ($tags as $tag)
				$this->printFile($tag);

		} else {
			// no optimization
			foreach ($tags as $tag)
				$this->printTag($tag);
		}

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
