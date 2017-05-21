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

namespace Core;
use \ModuleHandler;

require_once(_LIBPATH.'parsedown/Parsedown.php');


final class Markdown {
	private static $parser = null;

	/**
	 * Get markdown parser.
	 *
	 * @return object
	 */
	private static function get_parser() {
		if (is_null(self::$parser)) {
			self::$parser = new ExtendedParsedown();
			self::$parser->setBreaksEnabled(true);
		}

		return self::$parser;
	}

	/**
	 * Parse markdown text and return HTML.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function parse($text) {
		return self::get_parser()->text($text);
	}
}


final class ExtendedParsedown extends \Parsedown {
	/**
	 * Modify inline image behavior.
	 *
	 * @param array $excerpt
	 * @return array
	 */
	protected function inlineImage($excerpt) {
		global $language;

		// call parent to parse image
		$image = parent::inlineImage($excerpt);

		// make sure there's image to work with
		if (is_null($image))
			return;

		$original_source = $image['element']['attributes']['src'];

		if (is_numeric($original_source)) {
			if (ModuleHandler::is_loaded('gallery')) {
				// shorthand gallery image
				$gallery = \gallery::get_instance();
				$manager = \GalleryManager::get_instance();
				$gallery_image = $manager->get_single_item(
						array('title', 'filename', 'visible'),
						array(
							'id'      => fix_id($original_source),
							'visible' => 1
						));

				if (!is_object($gallery_image))
					return;

				// replace values
				$image['element']['attributes']['src'] = $gallery->get_raw_image($gallery_image);
				$image['element']['attributes']['alt'] = $gallery_image->title[$language];

			} else {
				// don't show gallery images if gallery is not loaded
				return;
			}

		} else {
			// external image or full url
			$image['element']['attributes']['src'] = _BASEURL.'/'.$original_source;
		}

		return $image;
	}
}

?>
