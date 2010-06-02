<?php

/**
 * This is a shared functions file used in calculateAnswer function. All of these
 * functions *NEED* to return numbers as results. Data being passed to functions has
 * already been sanitized!
 */

/**
 * Convert single letter to number
 *
 * @param string $letter
 * @return integer
 */
function letter_to_number($letter) {
	switch ($letter) {
		case "0": $result = 0; break;
		case "1": $result = 1; break;
		case "2": $result = 2; break;
		case "3": $result = 3; break;
		case "4": $result = 4; break;
		case "5": $result = 5; break;
		case "6": $result = 6; break;
		case "7": $result = 7; break;
		case "8": $result = 8; break;
		case "9": $result = 9; break;
		case "א": $result = 1; break;
		case "ב": $result = 2; break;
		case "ג": $result = 3; break;
		case "ד": $result = 4; break;
		case "ה": $result = 5; break;
		case "ו": $result = 6; break;
		case "ז": $result = 7; break;
		case "ח": $result = 8; break;
		case "ט": $result = 9; break;
		case "י": $result = 10; break;
		case "כ": $result = 20; break;
		case "ך": $result = 20; break;
		case "ל": $result = 30; break;
		case "מ": $result = 40; break;
		case "ם": $result = 40; break;
		case "נ": $result = 50; break;
		case "ן": $result = 50; break;
		case "ס": $result = 60; break;
		case "ע": $result = 70; break;
		case "פ": $result = 80; break;
		case "ף": $result = 80; break;
		case "צ": $result = 90; break;
		case "ץ": $result = 90; break;
		case "ק": $result = 100; break;
		case "ר": $result = 200; break;
		case "ש": $result = 300; break;
		case "ת": $result = 400; break;
		default: $result = 0;
	}

	return $result;
}

/**
 * Return sum of all the characters in one word
 *
 * @param string $word
 * @return integer
 */
function letter_sum($word) {
	$result = 0;
	$encoding = mb_detect_encoding($word);

	for ($i=0; $i<mb_strlen($word, $encoding); $i++) {
		$letter = mb_substr($word, $i, 1, $encoding);
		$result += letter_to_number($letter);
	}

	return $result;
}

/**
 * Total sum of all characters in word where sum can't be > 9
 *
 * @param string $word
 * @return integer
 */
function letter_sum_total($word) {
	$result = letter_sum($word);

	while ($result > 9)
		$result = letter_sum($result);

	return $result;
}
?>
