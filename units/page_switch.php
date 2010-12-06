<?php

/**
 * Page Switch
 *
 * @author MeanEYE.rcf
 */

class PageSwitch {
	/**
	 * Template handler
	 * @var resrouce
	 */
	private $template = null;

	/**
	 * Constructor
	 *
	 * @param array $items
	 * @param string $param_name
	 */
	public function __construct($items=null, $param_name='page') {
	}

	/**
	 * Set base URL to be used in links. You can provide additional
	 * parameters as array key pairs.
	 *
	 * @param string $action
	 * @param string $section
	 */
	public function setBaseURL($action, $section) {
	}

	/**
	 * Set base URL from current
	 */
	public function setCurrentAsBaseURL() {
		global $action, $section;
	}

	/**
	 * Set template file or handler for displaying page items.
	 *
	 * @param object/string $template Template handler object, filename string or full path string
	 * @param string $path Optional path
	 */
	public function setTemplate($template, $path=null) {
		if (is_string($template)) {
			// file path was provided, create template handler

			if (is_null($path)) {
				$path = dirname($path);
				$template = basename($template);
			}

			$this->template = new TemplateHandler($template, $path);

		} else if (is_object($template) && get_class($template) == 'TemplateHandler') {
			// template handler object was provided, just use it
			$this->template = $template;
		}
	}

	/**
	 * Set number of items per page
	 *
	 * @param integer $number
	 */
	public function setItemsPerPage($number=10) {
	}

	/**
	 * Page switcher tag handler
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PageSwitch($level, $tag_params, $children) {
	}
}
