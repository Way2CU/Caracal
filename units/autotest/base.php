<?php

/**
 * Base class for testing mode.
 *
 * This class is used as a base for building different forms of automated
 * testing on sites.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Testing;


abstract class Test {
	/**
	 * Return version of template to display. This value is automatically
	 * recorded by the main Tests class as a choice for current session. This
	 * function is not called if choice is already decided for session.
	 *
	 * @param string $name
	 * @param array $options
	 * @param array $versions
	 * @return string
	 */
	abstract public function get_version($name, $options, $versions);
}

?>
