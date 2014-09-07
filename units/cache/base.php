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
	public function initialize();

	/**
	 * Store specified data to cache under unique
	 * identifier and for period of time defined by
	 * $expires parameter.
	 *
	 * @param string $uid
	 * @param string $data
	 * @param int $expires
	 */
	public function storeData($uid, $data, $expires);

	/**
	 * Retrieve data for specified unique identifier. If
	 * data has expired or doesn't exist functions returns
	 * null.
	 *
	 * @param string $uid
	 * @return mixed
	 */
	public function getData($uid);

	/**
	 * Check if specified unique identified exists in database.
	 *
	 * @param string $uid
	 * @return boolean
	 */
	public function isCached($uid);

	/**
	 * Clear all cache.
	 */
	public function clearCache();
}

?>
