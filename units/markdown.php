<?php

/**
 * Markdown support for Caracal
 *
 * This class extends Parsedown and adds convenience features as well as
 * some static functions for easier access.
 *
 * Copyright © 2015. by Way2CU, http://way2cu.com
 * Author: Mladen Mijatov
 */

namespace Core\Markdown;

require_once(_LIBPATH.'parsedown/Parsedown.php');

use \Parsedown as Parsedown;


final class Markdown extends Parsedown {
	private static $parser = null;

	/**
	 * Parse markdown text and return HTML.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function parse($text) {
		if (is_null(self::$parser))
			self::$parser = Parsedown();

		return self::$parser->text($text);
	}

    protected function inlineImage($excerpt) {
        $image = parent::inlineImage($excerpt);
        $image['element']['attributes']['src'] = _BASEURL.$image['element']['attributes']['src'];

        return $image;
    }
}

?>
