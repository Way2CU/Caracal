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
	};

	/**
	 * Store specified data to cache under unique
	 * identifier and for period of time defined by
	 * $expires parameter.
	 *
	 * @param string $uid
	 * @param string $data
	 * @param int $expires
	 */
	public function store_data($uid, $data, $expires) {
	};

	/**
	 * Retrieve data for specified unique identifier. If
	 * data has expired or doesn't exist functions returns
	 * null.
	 *
	 * @param string $uid
	 * @return mixed
	 */
	public function get_data($uid) {
	};

	/**
	 * Check if specified unique identified exists in database.
	 *
	 * @param string $uid
	 * @return boolean
	 */
	function is_cached($uid) {
	};

	/**
	 * Clear all cache.
	 */
	function clear_cache() {
	};
}

?>
