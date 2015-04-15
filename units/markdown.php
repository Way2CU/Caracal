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


final class Markdown {
	private static $parser = null;

	/**
	 * Get markdown parser.
	 *
	 * @return object
	 */
	private static function get_parser() {
		if (is_null(self::$parser))
			self::$parser = ExtendedParsedown();

		return self::$parser;
	}

	/**
	 * Parse markdown text and return HTML.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function parse($text) {
		return self::get_parser->text($text);
	}
}


final class ExtendedParsedown extends Parsedown {
	/**
	 * Modify inline image behavior.
	 *
	 * @param array $excerpt
	 * @return array
	 */
    protected function inlineImage($excerpt) {
        $image = parent::inlineImage($excerpt);
        $image['element']['attributes']['src'] = _BASEURL.$image['element']['attributes']['src'];

        return $image;
    }
}

?>
