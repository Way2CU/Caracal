<?php

/**
 * Event Handler
 *
 * Provides simple and effective way of communication between
 * modules by providing plugin-like interface.
 *
 * Author: Mladen Mijatov
 */
namespace Core;

class EventAlreadyExistsError extends \Exception {};
class UnknownEventError extends \Exception {};
class InvalidParamCountError extends \Exception {};


final class Events {
	private static $event_names = array();
	private static $callbacks = array();

	/**
	 * Register event and its parameters.
	 *
	 * @param string $module
	 * @param string $event_name
	 * @param integer $required_param_count
	 */
	public static function register($module, $event_name, $required_param_count=0) {
		// create storage for module
		if (!isset(self::$event_names[$module]))
			self::$event_names[$module] = array();

		// add event definition
		if (!isset(self::$event_names[$module][$event_name])) {
			self::$event_names[$module][$event_name] = $required_param_count;

		} else {
			throw new EventAlreadyExistsError("Unable to register module '{$module}' eventl '{$event_name}'.");
		}
	}

	/**
	 * Connect specified callback to event.
	 *
	 * @param string $module
	 * @param string $event_name
	 * @param callable $callback
	 * @param object $object [optional]
	 */
	public static function connect($module, $event_name, $callback, $object=null) {
		$callable = is_null($object) ? $callback : array($object, $callback);

		// create storage for callbacks
		if (!isset(self::$callbacks[$module]))
			self::$callbacks[$module] = array();

		if (!isset(self::$callbacks[$module][$event_name]))
			self::$callbacks[$module][$event_name] = array();

		// add callback to list
		self::$callbacks[$module][$event_name][] = $callable;
	}

	/**
	 * Trigger event by its name and pass all additional arguments.
	 *
	 * @param string $module
	 * @param string $event_name
	 * @param ...
	 * @return array
	 */
	public static function trigger($module, $event_name) {
		if (!isset(self::$event_names[$module][$event_name]))
			throw new UnknownEventError("Unable to trigger module '{$module}' event '{$event_name}'.");

		$result = array();
		$required_param_count = self::$event_names[$module][$event_name];

		// get function parameters
		$params = func_get_args();
		array_shift($params);
		array_shift($params);
		$param_count = count($params);

		// check for required param count
		if ($param_count < $required_param_count)
			throw new InvalidParamCountError(
				"Invalid number of parameters specified ({$param_count}) ".
				"when triggering module '{$module}' event '{$event_name}'. Required: {$required_param_count}"
			);

		// call all callback methods
		if (isset(self::callbacks[$module][$event_name]))
			foreach (self::$callbacks[$module][$event_name] as $callable)
				$result[] = call_user_func_array($callable, $params);

		return $result;
	}
}

?>
