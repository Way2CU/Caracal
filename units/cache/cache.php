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

require_once('base.php');
require_once('file_provider.php');
require_once('memcached_provider.php');

use \Session as Session;
use \TemplateHandler as TemplateHandler;


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

	const TAG_OPEN = '{%{';
	const TAG_CLOSE = '}%}';

	private function __construct() {
		global $cache_path, $optimize_code, $cache_method, $section;

		// decide if we should cache current page
		$this->should_cache =
					$cache_method != Type::NONE &&
					!($section == 'backend' || $section == 'backend_module') &&
					$_SERVER['REQUEST_METHOD'] == 'GET' &&
					!_AJAX_REQUEST;

		// make sure cache directory exists
		if (($optimize_code || $cache_method != Type::NONE) && !file_exists($cache_path))
			if (mkdir($cache_path, 0775, true) === false)
				trigger_error('Cache manager: Unable to create storage path.', E_USER_WARNING);

		// create cache provider
		switch ($cache_method) {
			case Type::FILE_SYSTEM:
				$this->provider = new FileProvider();
				break;

			case Type::MEMCACHED:
				if (class_exists('Memcached')) {
					// create memcache provider
					$this->provider = new MemcachedProvider();

				} else {
					// fallback to file provider
					$this->provider = new FileProvider();
					trigger_error('Memcached not present. Falling back to file provider.', E_USER_NOTICE);
				}
				break;
		}

		// initialize cache provider
		if (!is_null($this->provider))
			$this->provider->initialize();

		// prepare for caching
		$this->uid = $this->generateUniqueID();
		$this->is_cached = file_exists($cache_path.$this->uid) && $this->should_cache;
	}

	/**
	 * Generate unique id based on URL.
	 *
	 * @param array $fields
	 * @return string
	 */
	private function generateUniqueID($fields=null) {
		global $cache_ignore_params;
		$data = '';

		if (is_null($fields))
			$fields = $_REQUEST;

		foreach ($fields as $key => $value)
			if (!in_array($key, $cache_ignore_params)) {
				if (!is_array($value))
					$data .= '/'.$key.'='.$value; else
					$data .= '/'.$key.'='.$this->generateUniqueID($value);
			}

		return md5($data).(_DESKTOP_VERSION ? '_d' : '_m');
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
		$result = false;

		if (!is_null($this->provider))
			$result = $this->provider->isCached($this->uid);

		return $result;
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
			$template = new TemplateHandler();
			$pattern = '/'.self::TAG_OPEN.'(.*?)'.self::TAG_CLOSE.'/u';

			// get all dirty areas
			preg_match_all($pattern, $data, $matches);

			if (count($matches) >= 2 && count($matches[1]) > 0)
				foreach ($matches[1] as $match) {
					// give template to handler
					$template->setXML('<document>'.$match.'</document>');

					// start output buffer and get data
					ob_start();
					$template->parse();
					$fresh_data = ob_get_clean();

					// replace output buffer with new data
					$data = preg_replace($pattern, $fresh_data, $data, 1);
				}

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
		if (!is_null($this->provider))
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
