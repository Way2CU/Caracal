<?php

/**
 * File Cache Provider
 *
 * This provider offers simple caching system using files and
 * their timestamps. Configuration is done via variables in
 * config files.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Cache;


class FileProvider implements Provider {
	/**
	 * Initialization function.
	 */
	public function initialize() {
		global $cache_path, $cache_expire_period;

		$file_list = glob($cache_path.'*.cache');
		$time_limit = time() - $cache_expire_period;

		// remove files which passed the limit
		foreach ($file_list as $file_name)
			if (filemtime($file_name) < $time_limit)
				unlink($file_name);
	}

	/**
	 * Store specified data to cache under unique
	 * identifier and for period of time defined by
	 * $expires parameter.
	 *
	 * @param string $uid
	 * @param string $data
	 * @param int $expires
	 */
	public function storeData($uid, $data, $expires) {
		global $cache_path;

		// prepare for storing
		$file_name = $cache_path.$uid.'.cache';

		// make sure we are not overwriting a file
		if (file_exists($file_name))
			unlink($file_name);

		// store content
		file_put_contents($file_name, $data);
	};

	/**
	 * Retrieve data for specified unique identifier. If
	 * data has expired or doesn't exist functions returns
	 * null.
	 *
	 * @param string $uid
	 * @return mixed
	 */
	public function getData($uid) {
		global $cache_path, $cache_expire_period;

		$result = null;
		$file_name = $cache_path.$uid.'.cache';
		$time_limit = time() - $cache_expire_period;

		// load file content only if cache is still valid
		if (file_exists($file_name) && filemtime($file_name) > $time_limit)
			$result = file_get_contents($file_name);

		return $result;
	};

	/**
	 * Check if specified unique identified exists in database.
	 *
	 * @param string $uid
	 * @return boolean
	 */
	function isCached($uid) {
		global $cache_path;

		$file_name = $cache_path.$uid.'.cache';
		$time_limit = time() - $cache_expire_period;

		return = file_exists($file_name) && filemtime($file_name) > $time_limit;
	};

	/**
	 * Clear all cache.
	 */
	function clearCache() {
		global $cache_path;
		array_map('unlink', glob($cache_path.'*.cache'));
	};
}

?>
