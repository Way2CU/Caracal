<?php

/**
 * Page Switch
 *
 * Widget for splitting long lists into pages. Each switch reads its current
 * page from the request parameter it was created with, which allows multiple
 * independent switches on a single page. Links are created through `URL::make`
 * by changing value of that parameter only.
 *
 * Author: Mladen Mijatov
 */

class PageSwitch {
	private $param_name;
	private $current_page = 1;
	private $max_pages = 9;
	private $per_page = 10;
	private $total_items = 0;
	private $url_params = array();
	private $url_file = null;
	private $link_handler = null;

	/**
	 * Create page switch controlled by specified request parameter.
	 *
	 * @param string $param_name
	 */
	public function __construct($param_name) {
		$this->param_name = $param_name;

		if (isset($_REQUEST[$this->param_name]))
			$this->current_page = fix_id($_REQUEST[$this->param_name]);

		if ($this->current_page < 1)
			$this->current_page = 1;
	}

	/**
	 * Set parameters and template file used for making links. Parameters need to
	 * be in the same order as in the section pattern. Parameter controlling
	 * this switch is added automatically.
	 *
	 * @param array $params
	 * @param string $file
	 */
	public function set_url($params, $file=null) {
		$this->url_params = $params;
		$this->url_file = $file;
	}

	/**
	 * Make links point to currently matched section, keeping its parameters.
	 */
	public function set_current_url() {
		$params = array();
		$matched_params = SectionHandler::get_matched_params();

		// collect values for parameters of matched pattern
		if (!is_null($matched_params))
			foreach ($matched_params[2] as $name)
				if (isset($_REQUEST[$name]))
					$params[$name] = fix_chars($_REQUEST[$name]);

		$this->set_url($params, SectionHandler::get_matched_file());
	}

	/**
	 * Set function used for making links instead of `URL::make`. Handler receives
	 * page number and returns link. This is used in places where links are not
	 * based on section patterns, such as backend windows.
	 *
	 * @param callable $handler
	 */
	public function set_link_handler($handler) {
		$this->link_handler = $handler;
	}

	/**
	 * Set number of items per page. Values smaller than one are ignored as
	 * they make page count impossible to calculate and would raise division
	 * by zero error.
	 *
	 * @param integer $number
	 */
	public function set_items_per_page($number) {
		$number = intval($number);

		if ($number > 0)
			$this->per_page = $number;
	}

	/**
	 * Set total number of items.
	 *
	 * @param integer $number
	 */
	public function set_total_items($number) {
		$this->total_items = intval($number);
	}

	/**
	 * Set maximum number of pages to be displayed.
	 *
	 * @param integer $number
	 */
	public function set_max_pages($number) {
		$number = intval($number);

		if ($number > 0)
			$this->max_pages = $number;
	}

	/**
	 * Return number of currently selected page.
	 *
	 * @return integer
	 */
	public function get_current_page() {
		return $this->current_page;
	}

	/**
	 * Return total number of pages. There is always at least one page.
	 *
	 * @return integer
	 */
	public function get_total_pages() {
		$result = (int) ceil($this->total_items / $this->per_page);

		if ($result < 1)
			$result = 1;

		return $result;
	}

	/**
	 * Return limit for current page in format used by item manager.
	 *
	 * @return integer/array
	 */
	public function get_limit() {
		if ($this->current_page == 1)
			$result = $this->per_page; else
			$result = array(
					($this->current_page - 1) * $this->per_page,
					$this->per_page
				);

		return $result;
	}

	/**
	 * Return link to specified page.
	 *
	 * @param integer $page
	 * @return string
	 */
	public function get_link($page) {
		if (!is_null($this->link_handler)) {
			$result = call_user_func($this->link_handler, $page);

		} else {
			// existing parameter keeps its position in the pattern
			$params = $this->url_params;
			$params[$this->param_name] = $page;

			$result = URL::make($params, $this->url_file);
		}

		return $result;
	}

	/**
	 * Handle drawing list of pages.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Pages($tag_params, $children) {
		$template = $this->load_template($tag_params, 'page_switch_page.xml', 'page_template');
		$template->set_template_params_from_array($children);

		$total_pages = $this->get_total_pages();
		$start = 1;
		$end = $total_pages;

		// keep current page in the middle when there are too many pages to show
		if ($total_pages > $this->max_pages) {
			$start = $this->current_page - (int) floor($this->max_pages / 2);
			$start = max(1, min($start, $total_pages - $this->max_pages + 1));
			$end = $start + $this->max_pages - 1;
		}

		// parse template
		for ($page = $start; $page <= $end; $page++) {
			$params = array(
					'number' => $page,
					'link'   => $this->get_link($page),
					'class'  => $page == $this->current_page ? 'active' : 'normal'
				);

			$template->set_local_params($params);
			$template->restore_xml();
			$template->parse();
		}
	}

	/**
	 * Handle drawing page switch.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PageSwitch($tag_params, $children) {
		$template = $this->load_template($tag_params, 'page_switch.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('_pages', $this, 'tag_Pages');

		$total_pages = $this->get_total_pages();
		$link_first = 'javascript: void(0);';
		$link_previous = 'javascript: void(0);';
		$link_next = 'javascript: void(0);';
		$link_last = 'javascript: void(0);';

		// create links for pages
		if ($this->current_page > 1) {
			$link_first = $this->get_link(1);
			$link_previous = $this->get_link($this->current_page - 1);
		}

		if ($this->current_page < $total_pages) {
			$link_next = $this->get_link($this->current_page + 1);
			$link_last = $this->get_link($total_pages);
		}

		// prepare template params
		$params = array(
				'total_pages'    => $total_pages,
				'items_per_page' => $this->per_page,
				'total_items'    => $this->total_items,
				'current_page'   => $this->current_page,
				'link_next'      => $link_next,
				'link_previous'  => $link_previous,
				'link_first'     => $link_first,
				'link_last'      => $link_last
			);

		// parse template
		$template->set_local_params($params);
		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Load template specified in tag parameters or default one from the system.
	 *
	 * @param array $tag_params
	 * @param string $default_file
	 * @param string $param_name
	 * @return TemplateHandler
	 */
	private function load_template($tag_params, $default_file, $param_name='template') {
		global $system_template_path;

		$path = $system_template_path;
		$file_name = $default_file;

		// specified template is loaded from site templates unless path is given
		if (isset($tag_params[$param_name])) {
			$file_name = $tag_params[$param_name];
			$path = isset($tag_params['template_path']) ? $tag_params['template_path'] : '';
		}

		$result = new TemplateHandler($file_name, $path);

		return $result;
	}
}

?>
