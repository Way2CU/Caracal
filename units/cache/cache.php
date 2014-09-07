<?php

/**
 * Caching System
 *
 * This class provides global functions for page and system
 * response cashing.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Cache;

require_once('file_provider.php');

use \Session as Session;


class Type {
	const NONE = 0;
	const FILE_SYSTEM = 1;
	const MEMCACHED = 2;
}


class Manager {
	private static $_instance;
	private $provider = null;

	private $uid = null;
	private $should_cache = true;
	private $in_dirty_area = false;

	private $cache = '';
	private $output = '';

	/**
	 * Parameters to ignore when generating UID.
	 * @var array
	 */
	private $ignored_params = array(
			'gclid', '_rewrite', Session::COOKIE_ID, Session::COOKIE_TYPE
		);

	const TAG_OPEN = '{%{';
	const TAG_CLOSE = '}%}';
	const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

	private function __construct() {
		global $cache_path, $cache_method, $section;

		// decide if we should cache current page
		$this->should_cache = 
					$cache_method != Type::NONE && 
					!($section == 'backend' || $section == 'backend_module') && 
					$_SERVER['REQUEST_METHOD'] == 'GET' &&
					!_AJAX_REQUEST;

		// create cache provider
		switch ($cache_method) {
			case Type::FILE_SYSTEM:
				$this->provider = new FileProvider();
				break;
		}

		// initialize cache provider
		if (!is_null($this->provider))
			$this->provider->initialize();

		// prepare for caching
		$this->uid = $this->generateUniqueID();
		$this->cache_file = $cache_path.$this->uid.(_DESKTOP_VERSION ? '' : '_m');
		$this->is_cached = file_exists($this->cache_file) && $this->should_cache;
	}

	/**
	 * Generate unique id based on URL.
	 *
	 * @param array $fields
	 * @return string
	 */
	private function generateUniqueID($fields=null) {
		$data = '';

		if (is_null($fields))
			$fields = $_REQUEST;

		foreach ($fields as $key => $value)
			if (!in_array($key, $this->ignored_params)) {
				if (!is_array($value))
					$data .= '/'.$key.'='.$value; else
					$data .= '/'.$key.'='.$this->generateUniqueID($value);
			}

		return md5($data);
	}

	/**
	 * Store cached page to file.
	 */
	private function storeCache() {
		global $cache_expire_period;
		$this->provider->storeData(
					$this->uid,
					$this->cache,
					time() + $cache_expire_period
				);
	}

	/**
	 * Check if page is cached.
	 */
	public function isCached() {
		return $this->provider->isCached();
	}

	/**
	 * Whether cache is working for current URL.
	 * @return boolean
	 */
	public function isCaching() {
		return $this->should_cache;
	}

	/**
	 * Print cached page. Function returns false in case cache data doesn't
	 * exist or has been expired.
	 *
	 * @return boolean
	 */
	public function printCache() {
		$data = $this->provider->getData($this->uid);

		// show cached page
		if (!is_null($data)) {
			header('Content-Type: text/html; charset=UTF-8');
			print $data;
		}

		return $data != null;
	}

	/**
	 * Stop capturing data and take raw data in.
	 */
	public function startDirtyArea() {
		if (!$this->should_cache || $this->in_dirty_area)
			return;

		$this->in_dirty_area = true;

		// flush current buffer
		$data = ob_get_contents();
		$this->cache .= $data;
		$this->output .= $data;
		ob_clean();

		// append opening tag for cache
		$this->cache .= self::TAG_OPEN;
	}

	/**
	 * Start capturing data globally again after 
	 * appending data to output buffer.
	 */
	public function endDirtyArea() {
		if (!$this->should_cache || !$this->in_dirty_area)
			return;

		$this->in_dirty_area = false;

		// get dirty buffer
		$data = ob_get_contents();
		$this->output .= $data;
		ob_clean();

		// append closing tag to cache
		$this->cache .= self::TAG_CLOSE;
	}

	/**
	 * Set cached data for dirty area. This function can
	 * only be called after calling startDirtyArea.
	 * 
	 * @param string $data
	 */
	public function setCacheForDirtyArea($data) {
		if (!$this->should_cache || !$this->in_dirty_area)
			return;

		$this->cache .= $data;
	}

	/**
	 * Start capturing output of a page.
	 */
	public function startCapture() {
		if ($this->should_cache)
			ob_start();
	}

	/**
	 * Stop capturin output and store it to the cache.
	 */
	public function endCapture() {
		if (!$this->should_cache)
			return;

		// get cache from handler
		$data = ob_get_contents();
		
		// update local storage variables
		$this->cache .= $data; 
		$this->output .= $data;

		// end capture and clear buffer
		ob_end_clean();

		// store data
		$this->storeCache();

		// print output
		print $this->output;
	}

	/**
	 * Clear all cache
	 * 
	 * Please note that cached pages are automatically
	 * invalidated after specified period of time. Manual
	 * clearing of complete cache is recommended to be used
	 * only in case of a problem or important update.
	 */
	public function clearCache() {
		$this->provider->clearCache();
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
