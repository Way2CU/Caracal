<?php

/**
 * Page Switch
 *
 * @author MeanEYE.rcf
 */

class PageSwitch {
	private $param_name = 'page';
	private $current_page = 1;
	private $per_page = 10;

	/**
	 * Constructor
	 *
	 * @param string $param_name
	 */
	public function __construct($param_name=null) {
		if (!is_null($param_name))
			$this->param_name = $param_name;

		if (isset($_REQUEST[$this->param_name]))
			$this->current_page = fix_id($_REQUEST[$this->param_name]);
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
	 * Set number of items per page
	 *
	 * @param integer $number
	 */
	public function setItemsPerPage($number) {
		$this->per_page = $number;
	}

	/**
	 * Return filter paramters for item manager
	 * @return integer/array
	 */
	public function getFilterParams() {
		if ($this->current_page == 1)
			$result = $this->per_page; else
			$result = array(
						($this->current_page - 1) * $this->per_page,
						$this->per_page
					);

		return $result;
	}

	/**
	 * Page switcher tag handler
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Pages($tag_params, $children) {
	}
}
