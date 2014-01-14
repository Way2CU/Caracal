<?php

/**
 * Event Handler
 *
 * Provides simple and effective way of communication between
 * modules by providing plugin-like interface.
 *
 * Author: Mladen Mijatov
 */

class EventAlreadyExistsError extends Exception {};
class UnknownEventError extends Exception {};
class InvalidParamCountError extends Exception {};


final class EventHandler {
	private $event_names = array();
	private $callbacks = array();

	public function __construct() {
	}

	/**
	 * Register event and its parameters.
	 *
	 * @param string $event_name
	 * @param integer $required_param_count
	 */
	public function registerEvent($event_name, $required_param_count=0) {
		if (!isset($this->event_names[$event_name])) {
			$this->event_names[$event_name] = $required_param_count; 
			$this->callbacks[$event_name] = array();

		} else {
			throw new EventAlreadyExistsError("Unable to register '{$event_name}' event.");
		}
	}

	/**
	 * Connect specified callback to event.
	 *
	 * @param string $event_name
	 * @param callable $callback
	 * @param object $object [optional]
	 */
	public function connect($event_name, $callback, $object=null) {
		$callable = is_null($object) ? $callback : array($object, $callback);

		if (isset($this->event_names[$event_name]))
			$this->callbacks[$event_name][] = $callable; else
			throw new UnknownEventError("Unable to connect to '{$event_name}' event.");
	}

	/**
	 * Trigger event by its name and pass all additional arguments.
	 *
	 * @param string $event_name
	 * @param ...
	 * @return array
	 */
	public function trigger($event_name) {
		if (!isset($this->event_names[$event_name]))
			throw new UnknownEventError("Unable to trigger event ({$event_name}).");

		$result = array();
		$required_param_count = $this->event_names[$event_name];

		// get function parameters
		$params = func_get_args();
		array_shift($params);
		$param_count = count($params);

		// check for required param count
		if ($param_count < $required_param_count)
			throw new InvalidParamCountError(
				"Invalid number of parameters specified ({$param_count}) ".
				"when triggering event '{$event_name}'. Required: {$required_param_count}"
			);

		// call all callback methods
		foreach ($this->callbacks[$event_name] as $callable)
			$result[] = call_user_func_array($callable, $params);

		return $result;
	}
}

?>
