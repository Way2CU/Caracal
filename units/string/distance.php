<?php

/**
 * String Distance Algorithms
 *
 * Generic implementations of different string distance (similarity) algorithms. All
 * of these algorithms are designed to work with Unicode support.
 *
 * Author: Mladen Mijatov
 */
namespace String\Distance;


/**
 * Jaro distance algorithm measures similarity between two strings. This
 * algorithm returns value between 0 and 1. The lower the result more two strings
 * differ from each other. Result of 1 means exact match.
 *
 * Note: Avoid comparing longer strings.
 */
class Jaro {
	/**
	 * Get common characters between two strings. It's important to note this
	 * function favors built-in array functions to manual iteration over arrays.
	 * While this is not an optimum solution in regards to number of operations,
	 * built-in functions are signifficantly faster and should result in better
	 * performance most of the time.
	 *
	 * @param array $first
	 * @param array $second
	 * @param integer $maximum_distance
	 * @return array
	 */
	private static function get_common_characters(&$first, &$second, $maximum_distance) {
		$result = array();
		$temporary = $second;

		for ($i = 0; $i < count($first); $i++) {
			$start = max(0, $i - $maximum_distance);
			$end = $i + $maximum_distance;
			$index = array_search($first[$i], $temporary);

			// no character was found, keep searching
			if ($index === false)
				continue;

			// check if found character is within distance
			if ($index >= $start && $index <= $end) {
				$result []= $first[$i];
				$temporary[$index] = null;
				continue;
			}
		}

		return $result;
	}

	/**
	 * Get distance between two strings.
	 *
	 * @param string $first
	 * @param string $second
	 * @return float
	 */
	public static function get($first, $second) {
		// convert to array of invididual unicode characters
		$first = preg_split('//u', $first, null, PREG_SPLIT_NO_EMPTY);
		$second = preg_split('//u', $second, null, PREG_SPLIT_NO_EMPTY);
		$size_first = count($first);
		$size_second = count($second);

		// get common characters
		$maximum_distance = (int) floor(min($size_first, $size_second) / 2.0);
		$common_chars = self::get_common_characters($first, $second, $maximum_distance);
		$common_chars_reversed = self::get_common_characters($second, $first, $maximum_distance);
		$common_size = count($common_chars);

		// make sure we avoid division by zero
		if ($common_size == 0)
			return 0.0;

		// calculate number of differences
		$differences = 0;
		for ($i = 0; $i < min($common_size, count($common_chars_reversed)); $i++)
			if ($common_chars[$i] != $common_chars_reversed[$i])
				$differences++;
		$differences /= 2.0;

		// calculate result
		$sum = 0.0;
		$sum += $common_size / $size_first;
		$sum += $common_size / $size_second;
		$sum += ($common_size - $differences) / $common_size;
		$result = (1/3) * $sum;

		return $result;
	}
}


/**
 * Jaro-Winkler distance algorithm measures similarity between two strings. This
 * algorithm returns value between 0 and 1. The lower the result more two strings
 * differ from each other. Result of 1 means exact match.
 *
 * This algorithm is based on Jaro distance metric with added weight towards
 * initially matching strings. This algorithm is best suited for comparison of
 * short strings such as names.
 *
 * Note: Avoid comparing longer strings.
 */
final class JaroWinkler extends Jaro {
	/**
	 * Return number of common initial characters.
	 *
	 * @param string $first
	 * @param string $second
	 * @return integer
	 */
	private static function get_common_prefix_length($first, $second) {
		$result = 0;

		// convert to array of invididual unicode characters
		$first = preg_split('//u', $first, null, PREG_SPLIT_NO_EMPTY);
		$second = preg_split('//u', $second, null, PREG_SPLIT_NO_EMPTY);
		$max_chars = min(4, count($first), count($second));

		while ($result < $max_chars && $first[$result] == $second[$result])
			$result++;

		return $result;
	}

	/**
	 * Return Jaro-Winkler distance adjusted for specified weight.
	 *
	 * @param string $first
	 * @param string $second
	 * @param float $weight
	 * @return float
	 */
	public static function get($first, $second, $weight=0.1) {
		$distance = parent::get($first, $second);
		$prefix_length = self::get_common_prefix_length($first, $second);

		return $distance + ($prefix_length * $weight * (1.0 - $distance));
	}
}

?>
