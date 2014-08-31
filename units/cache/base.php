<?php

/**
 * Cache provider base class
 *
 * This class provides basic building structure for creating
 * caching system support.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Cache;


interface Provider {
	/**
	 * Initialization function.
	 */
	public abstract function initialize();

	/**
	 * Store specified data to cache under unique
	 * identifier and for period of time defined by
	 * $expires parameter.
	 *
	 * @param string $uid
	 * @param string $data
	 * @param int $expires
	 */
	public abstract function store_data($uid, $data, $expires);

	/**
	 * Retrieve data for specified unique identifier. If
	 * data has expired or doesn't exist functions returns
	 * null.
	 *
	 * @param string $uid
	 * @return mixed
	 */
	public abstract function get_data($uid);

	/**
	 * Check if specified unique identified exists in database.
	 *
	 * @param string $uid
	 * @return boolean
	 */
	public abstract function is_cached($uid);

	/**
	 * Clear all cache.
	 */
	public abstract function clear_cache();
}

?>
