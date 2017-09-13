<?php

/**
 * Window Action
 *
 * Convenience class used for generating fragments of `href` attributes which interract with
 * backend window management system.
 */

namespace Modules\Backend\Menu;
use Core\Action;


class WindowActionType {
	const OPEN = 0;
	const CLOSE = 1;
	const RELOAD = 2;
	const MINIMIZE = 3;
	const RESTORE = 4;
}


class WindowAction extends Action {
	protected $type;
	protected $window;
	protected $menu_item;

	private function __construct($type, $module=null, $name=null, $callable=null, $window=null) {
		parent::__construct($module, $name, $callable);

		$this->type = WindowActionType::OPEN;
		$this->window = $window;
	}

	/**
	 * Create action which will open new backend window and load its content from URL generated
	 * by the provided `$module` and action `$name`. Callable (`$callable`) is used to easily
	 * add backend menu item and register new action for module.
	 *
	 * Note: Parameter `$window` can be omitted only when class is used within `WindowItem`.
	 *
	 * @param string $module
	 * @param string $name
	 * @param string $callable
	 * @return object
	 */
	public static function open($module, $name, $callable, $window=null) {
		return new WindowAction(WindowActionType::OPEN, $module, $name, $callable, $window);
	}

	/**
	 * Create action which closes existing window with specified `$window` name in backend.
	 *
	 * Note: Parameter `$window` can be omitted only when class is used within `WindowItem`.
	 *
	 * @param string $window
	 * @return object
	 */
	public static function close($window=null) {
	}

	/**
	 * Create action which reloads content in existing window with specified `$window`
	 * name in backend.
	 *
	 * Note: Parameter `$window` can be omitted only when class is used within `WindowItem`.
	 *
	 * @param string $window
	 * @return object
	 */
	public static function reload($window=null) {
	}

	/**
	 * Create action which minimizes existing window with specified `$window` name in backend.
	 *
	 * Note: Parameter `$window` can be omitted only when class is used within `WindowItem`.
	 *
	 * @param string $window
	 * @return object
	 */
	public static function minimize($window=null) {
	}

	/**
	 * Create action which restores existing window with specified `$window` name in backend.
	 *
	 * Note: Parameter `$window` can be omitted only when class is used within `WindowItem`.
	 *
	 * @param string $window
	 * @return object
	 */
	public static function restore($window=null) {
	}

	/**
	 * Generate URL based on configured data.
	 *
	 * @return string
	 */
	public function get_url() {
		$result = 'Caracal.window_system.';

		switch ($this->type) {
			$result .= 'openWindow(';
			$result .= "'{$this->window}',";  // window name
			$result .= "'{$this->window}',";  // window name
		}
	}
}

?>
