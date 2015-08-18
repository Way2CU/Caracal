<?php

/**
 * Base Menu Item Interface
 *
 * This interface requires all menu item classes to implement specified
 * functions and provide consistent behavior to module developers.
 *
 * Copyright © 2015 Way2CU. All Rights Reserved.
 * Author: Mladen Mijatov
 */

namespace Modules/Backend/Menu;


interface Item {
	public function draw();
}

?>
