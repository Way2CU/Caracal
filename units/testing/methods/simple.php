<?php

/**
 * Simple multiple-choice test.
 *
 * This test object will equally favor all choices and distribute
 * views among them. It's commonly called "AB test" but with added
 * ability of specifying more than two versions.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Testing\Methods;

use Core\Testing\Method;
use Core\Testing\Handler;


class Simple extends Method {
	private $handler;
	private $method_name;

	public function __construct($handler) {
		$this->method_name = 'simple';

		// register testing method
		$this->handler = $handler;
		$this->handler->register_method($this->method_name, $this);
	}

	/**
	 * Return version of template to display.
	 *
	 * @param string $name
	 * @param array $options
	 * @param array $versions
	 * @return string
	 */
	public function get_version($name, $options, $versions) {
		$result = null;
		$manager = $this->handler->get_manager();

		// get status of choices from database
		$choices = $manager->get_items(
				array('version'),
				array(
					'method' => $this->method_name,
					'name'   => $name
				),
				array('value'),  // sort descending by value
				false
			);

		// try to match database selection to template provided versions
		if (count($choices) > 0)
			foreach ($choices as $data)
				if (in_array($data->version, $versions)) {
					$result = $data->version;
					break;
				}

		// version couldn't be matched in database, insert all versions
		if (is_null($result)) {
			$result = $versions[0];

			foreach ($versions as $version)
				$manager->insert_item(array(
						'method'  => $this->method_name,
						'name'    => $name,
						'version' => $version,
						'value'   => $version == $result ? 1 : 0
					));
		}

		return $result;
	}
}

?>
