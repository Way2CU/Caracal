<?php

/**
 * Base class for testing method.
 *
 * This class is used as a base for building different forms of automated
 * testing on sites.
 *
 * Object created, based on this class, must be registered with main
 * tests provider with unique identifying name for ease of use through
 * templates.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Testing;


abstract class Method {
	abstract public function __construct($handler);

	/**
	 * Return version of template to display. This value is automatically
	 * recorded by the main Tests class as a choice for current session. This
	 * function is not called if choice is already decided for session. It's
	 * important to note that system automatically stores choice only in
	 * session. It's up to test object to update database values by using
	 * `Core\Testing\Manager` object.
	 *
	 * @param string $name
	 * @param array $options
	 * @param array $versions
	 * @return string
	 */
	abstract public function get_version($name, $options, $versions);
}

?>
