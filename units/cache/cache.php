<?php

/**
 * Page Caching Mechanism
 * Copyright (c) 2012. by Mladen Mijatov
 */


class CacheHandler {
	private static $_instance;

	private $uid = null;
	private $is_cached = false;
	private $should_cache = true;
	private $in_dirty_area = false;

	private $cache = '';
	private $output = '';

	const TAG_OPEN = '{%{';
	const TAG_CLOSE = '}%}';
	const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

	private function __construct() {
		global $cache_path, $cache_enabled, $section;

		//$manager = CacheManager();
		$this->should_cache = 
					$cache_enabled && 
					!($section == 'backend' || $section == 'backend_module') && 
					$_SERVER['REQUEST_METHOD'] == 'GET';

		$this->uid = $this->generateUniqueID();
		$this->is_cached = file_exists($cache_path.$this->uid) && $this->should_cache;
	}

	/**
	 * Generate unique id based on URL.
	 * @return string
	 */
	private function generateUniqueID() {
		$result = md5($_SERVER['REQUEST_URI'].'_cache_salt');

		return $result;
	}

	private function storeCache($data) {
		global $cache_path, $cache_expire_period, $cache_max_pages;

		$expires = date(self::TIMESTAMP_FORMAT, time() + $cache_expire_period);

		// add database entry
		$manager = CacheManager::getInstance();
		$manager->insertData(array(
						'uid'			=> $this->uid,
						'url'			=> $_SERVER['REQUEST_URI'],
						'times_used'	=> 0,
						'times_renewed'	=> 0,
						'expires'		=> $expires
					));

		// store content to disk
		file_put_contents($cache_path.$this->uid, $this->cache);

		// check if we reached maximum number of pages
		$page_count = $manager->sqlResult('SELECT count(`uid`) FROM `system_cache`');
		if ($page_count > $cache_max_pages) {
			$entries_to_drop = $manager->getItems(
								array('uid'), 
								array(),
								array('times_used'),
								true,
								10
							);

			$uid_list = array();

			foreach($entries_to_drop as $entry) {
				$uid_list[] = $entry->uid;
				unlink($cache_path.$entry->uid);
			}

			$manager->deleteData(array('uid' => $uid_list));
		}
	}

	/**
	 * Handle expired cache or update times_used counter
	 */
	private function validateCache() {
		global $cache_path;

		$manager = CacheManager::getInstance();
		$today = date(self::TIMESTAMP_FORMAT);
		$entry = $manager->getSingleItem(
								array('uid'),
								array(
										'uid'		=> $this->uid,
										'expires' 	=> array(
											'operator'	=> '<',
											'value'		=> $today
										)
								));

		if (is_object($entry)) {
			// object needs to be expired
			unlink($cache_path.$this->uid);
			$manager->deleteData(array('uid' => $this->uid));

		} else {
			// update times used
			$manager->updateData(array('times_used' => '`times_used` + 1'), array('uid' => $this->uid));
		}
	}

	/**
	 * Check if page is cached.
	 */
	public function isCached() {
		return $this->is_cached;
	}

	/**
	 * Whether cache is working for current URL.
	 * @return boolean
	 */
	public function isCaching() {
		return $this->should_cache;
	}

	/**
	 * Print cached page
	 */
	public function printCache() {
		global $cache_path;

		$filename = $cache_path.$this->uid;

		if (file_exists($filename)) { 
			// get data from file and prepare for parsing
			$data = file_get_contents($filename);
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
					$result = ob_get_contents();
					ob_end_clean();

					// replace output buffer with new data
					$data = preg_replace($pattern, $result, $data, 1);
				}

			// make sure we have specified cache type
			if (!_AJAX_REQUEST)
				header('Content-Type: text/html; charset=UTF-8');

			print $data;

			// validate or expire cache entry
			$this->validateCache();
		}
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
		$this->storeCache($data);

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
