<?php

/**
 * Memcached Cache Provider
 *
 * This provider offers simple caching system using memcached.
 * Configuration is done via variables in config files.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Cache;


class MemcachedProvider implements Provider {
	private $api = null;

	/**
	 * Initialization function.
	 */
	public function initialize() {
		global $memcached_config;

		// configure library
		$this->api->setOption(Memcached::OPT_PREFIX_KEY, _DOMAIN);
		$this->api->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

		// connect to memcache server
		$this->api = new Memcached();
		$this->api->addServer(
				$memcached_config['host'],
				$memcached_config['port']
			);
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
		$this->api->set($uid, $data, $expires);
	}

	/**
	 * Retrieve data for specified unique identifier. If
	 * data has expired or doesn't exist functions returns
	 * null.
	 *
	 * @param string $uid
	 * @return mixed
	 */
	public function getData($uid) {
		$result = null;

		// get data from server
		$data = $this->api->get($uid);
		if ($data !== false)
			$result = $data;

		return $result;
	}

	/**
	 * Check if specified unique identified exists in database.
	 *
	 * @param string $uid
	 * @return boolean
	 */
	public function isCached($uid) {
		$response = $this->api->add($uid, '');

		// remove dummy value
		if ($response)
			$this->api->delete($uid);

		return $response == false;
	}

	/**
	 * Clear all cache.
	 */
	public function clearCache() {
		$this->api->flush();
	}
}

?>
