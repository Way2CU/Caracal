<?php

/**
 * Head Tag Module
 *
 * This module provides simplified interface for adding links and scripts.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;


class head_tag extends Module {
	private static $_instance;
	private $tags = array();
	private $meta_tags = array();
	private $link_tags = array();
	private $script_tags = array();
	private $closeable_tags = array('script', 'style');

	private $analytics = null;
	private $analytics_domain = null;
	private $analytics_version = 'v1';

	private $optimizer = null;
	private $optimizer_key = '';
	private $optimizer_page = '';
	private $optimizer_show_control = false;

	private $supported_styles = array('stylesheet', 'stylesheet/less');

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		// register events
		Events::register('head-tag', 'before-print');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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
	public function transfer_control($params, $children) {
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
	public function initialize() {
	}

	public function cleanup() {
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
	 * @param string $domain
	 * @param string $version
	 */
	public function addGoogleAnalytics($code, $domain, $version) {
		$this->analytics = $code;
		$this->analytics_domain = $domain;
		$this->analytics_version = $version;
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
	 * Print previously added tags
	 */
	private function printTags() {
		global $optimize_code, $section;

		// determine whether we shuold optimize code
		$optimize_styles = $section == 'backend' || $optimize_code;
		$optimize_scripts = $optimize_code && !in_array($section, array('backend', 'backend_module'));

		// give modules chance to add elements
		Events::trigger('head-tag', 'before-print');

		// merge tag lists
		$tags = array_merge($this->tags, $this->meta_tags, $this->link_tags, $this->script_tags);

		if ($optimize_styles || $optimize_scripts) {
			// use code optimizer if possible
			$optimizer = CodeOptimizer::get_instance();
			$handled_tags = array();
			$unhandled_tags = array_merge($this->tags, $this->meta_tags);

			// add stylesheet tags for optimization
			if ($optimize_styles)
				foreach ($this->link_tags as $link) {
					$can_be_compiled = isset($link[1]['rel']) && in_array($link[1]['rel'], $this->supported_styles);

					if ($can_be_compiled)
						$added = $optimizer->add_style($link[1]['href']);

					if (!$can_be_compiled || !$added)
						$unhandled_tags []= $link;
				}

			// add script tags for compilation
			if ($optimize_scripts)
				foreach ($this->script_tags as $script)
					if (!$optimizer->add_script($script[1]['src']))
						$unhandled_tags []= $script; else
						$handled_tags []= $script;  // collect scripts in case compile fails

			// show unhandled tags manually
			foreach ($unhandled_tags as $tag)
				$this->printTag($tag);

			// print optimized code
			try {
				$optimizer->print_data();

			} catch (ScriptCompileError $error) {
				// there was a problem compiling script, show tags traditional way
				foreach ($handled_tags as $tag)
					$this->printTag($tag);
			}

		} else {
			// no optimization
			foreach ($tags as $tag)
				$this->printTag($tag);
		}

		// print google analytics code if needed
		if (!is_null($this->analytics)) {
			$template = new TemplateHandler("google_analytics_{$this->analytics_version}.xml", $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'code'   => $this->analytics,
						'domain' => $this->analytics_domain
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}

		// print google site optimizer code if needed
		if (!is_null($this->optimizer)) {
			$template = new TemplateHandler('google_site_optimizer.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
							'code'         => $this->optimizer,
							'key'          => $this->optimizer_key,
							'page'         => $this->optimizer_page,
							'show_control' => $this->optimizer_show_control
						);

			$template->restore_xml();
			$template->set_local_params($params);
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
