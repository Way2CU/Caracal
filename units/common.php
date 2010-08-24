<?php

if (!defined('_DOMAIN') || _DOMAIN !== 'RCF_WebEngine') die ('Direct access to this file is not allowed!');

/**
 * Remove illegal characters and tags from input strings to avoid XSS.
 * It also replaces few tags such as [b] [small] [big] [i] [u] [tt] into
 * <b> <small> <big> <i> <u> <tt>
 *
 * @param string $string Input string
 * @return string
 * @author MeanEYE
 */
function fix_chars($string, $strip_tags=true) {
	if (!is_array($string)) {
		$string = strip_tags($string, '<b><small><big><i><u><tt><pre>');
		$string = str_replace("*","&#42;", $string);
		$string = str_replace(chr(92).chr(34),"&#34;", $string);
		$string = str_replace("\r\n","\n", $string);
		$string = str_replace("\'","&#39;", $string);
		$string = str_replace("'","&#39;", $string);
		$string = str_replace(chr(34),"&#34;", $string);
		$string = str_replace("<", "&lt;", $string);
		$string = str_replace(">", "&gt;", $string);
		$string = str_replace("\n", "<br>", $string);
		$string = preg_replace('/\[link\s*=\s*([^\]]+)\](.+)\[\/link\]/i', '<a href="$1">$2</a>', $string);
		$string = preg_replace('/\[image\s*=\s*([^\]]+)\](.+)\[\/image\]/i', '<img src="$1" alt="$2">', $string);
		$string = str_replace("[b]", "<b>", $string);
		$string = str_replace("[/b]", "</b>", $string);
		$string = str_replace("[small]", "<small>", $string);
		$string = str_replace("[/small]", "</small>", $string);
		$string = str_replace("[big]", "<big>", $string);
		$string = str_replace("[/big]", "</big>", $string);
		$string = str_replace("[i]", "<i>", $string);
		$string = str_replace("[/i]", "</i>", $string);
		$string = str_replace("[u]", "<u>", $string);
		$string = str_replace("[/u]", "</u>", $string);
		$string = str_replace("[tt]", "<tt>", $string);
		$string = str_replace("[/tt]", "</tt>", $string);
		$string = str_replace("[pre]", "<pre>", $string);
		$string = str_replace("[/pre]", "</pre>", $string);
	} else {
		foreach($string as $key => $value)
			$string[$key] = fix_chars($value);
	}
    return $string;
}

/**
 * Strip tags and escape the rest of the string
 *
 * @param mixed $string
 * @return mixed
 * @author MeanEYE.rcf
 */
function escape_chars($string) {
	if (!is_array($string)) {
		$string = mysql_real_escape_string(strip_tags($string));
	} else {
		foreach($string as $key => $value)
			$string[$key] = escape_chars($value);
	}

	return $string;
}

/**
 * Prevent potential SQL injection by calling this function brefore
 * using ID value from parameters.
 *
 * @param string $string
 * @return string
 * @author MeanEYE
 */
function fix_id($string) {
        $res = explode(' ', $string);
        $res = preg_replace('/[^\d]*/i', '', $res[0]);

        if (!is_numeric($res)) $res = 0;

        return $res;
}

/**
 * A rollback for fix_chars function. This function should be used to prepare text
 * for editing, when text is entered through web interface. This function replaces
 * <br> with \n and <b> with [b]...
 *
 * @param string $string
 * @return string
 * @author MeanEYE
 */
function unfix_chars($string) {
	if (!is_array($string)) {
		$string = str_replace("&#42;", "*", $string);
		$string = str_replace("&#34;", chr(34), $string);
		$string = str_replace("&#39;", "'", $string);
		$string = str_replace("&#39;", "'", $string);
		$string = str_replace("&lt;", "<", $string);
		$string = str_replace("&gt;", ">", $string);
		$string = str_replace("<br>", "\n", $string);
		$string = preg_replace('/<a[\s]+href=[\'\"]([^\'\"]+)[\'\"]>(.+)<\/a>/i', '[link=$1]$2[/link]', $string);
		$string = preg_replace('/<img[\s]+src=[\'\"]([^\'\"]+)[\'\"][\s]+alt=[\'\"](.+)[\'\"]>/i', '[image=$1]$2[/image]', $string);
		$string = str_replace("<b>", "[b]", $string);
		$string = str_replace("</b>", "[/b]", $string);
		$string = str_replace("<small>", "[small]", $string);
		$string = str_replace("</small>", "[/small]", $string);
		$string = str_replace("<big>", "[big]", $string);
		$string = str_replace("</big>", "[/big]", $string);
		$string = str_replace("<i>", "[i]", $string);
		$string = str_replace("</i>", "[/i]", $string);
		$string = str_replace("<u>", "[u]", $string);
		$string = str_replace("</u>", "[/u]", $string);
		$string = str_replace("<tt>", "[tt]", $string);
		$string = str_replace("</tt>", "[/tt]", $string);
		$string = str_replace("<pre>", "[pre]", $string);
		$string = str_replace("</pre>", "[/pre]", $string);
	} else {
		foreach($string as $key => $value)
			$string[$key] = unfix_chars($value);
	}

    return $string;
}

/**
 * Checks if browser is non-IE
 *
 * @return boolean
 * @author MeanEYE
 */
function is_browser_ok() {
	$res = false;
	if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') == false) $res = true;

	return $res;
}

/**
 * Return abbreviated string containing specified number of words ending
 * given string. If number of words in string is lower than limit whole
 * string is returned. This function is effective for all languages
 * that use space character. This function will not work on Mandarian,
 * Korean, Japanese and other laguages using the same formation. Hebrew
 * text will work properly but you might need to reverse string before
 * calling the function.
 *
 * @param string $str
 * @param integer $limit
 * @param string $end_char
 * @return string
 * @author MeanEYE
 */
function limit_words($str, $limit = 100, $end_char = '&#8230;') {
    if (trim($str) == '')
        return $str;

    preg_match('/\s*(?:\S*\s*){'. (int) $limit .'}/', $str, $matches);

    if (strlen($matches[0]) == strlen($str))
        $end_char = '';

    return rtrim($matches[0]) . $end_char;
}

/**
 * Reverse UTF8 text and leave numbers intact. This function was
 * made to compensate pre CS5 flash string handling with embeded fonts.
 *
 * @param string $str
 * @param boolean $revers_numbers
 * @return string
 */
function utf8_strrev($str, $reverse_numbers=false) {
	preg_match_all('/./us', $str, $ar);
	if ($reverse_numbers)
		return join('',array_reverse($ar[0]));
	else {
		$temp = array();
		foreach ($ar[0] as $value) {
			if (is_numeric($value) && !empty($temp[0]) && is_numeric($temp[0])) {
				foreach ($temp as $key => $value2) {
					if (is_numeric($value2))
						$pos = ($key + 1);
					else break;
				}
				$temp2 = array_splice($temp, $pos);
				$temp = array_merge($temp, array($value), $temp2);
			} else array_unshift($temp, $value);
		}
		return implode('', $temp);
	}
}

/**
 * Simple function that provides Google generated QR codes
 * Refer to:
 * 		http://code.google.com/apis/chart/types.html#qrcodes
 * 		http://code.google.com/p/zxing/wiki/BarcodeContents
 *
 * @param string $url
 * @param integer $size
 * @return string
 */
function get_qr_image($uri, $size=100, $error_correction="L") {
	$url = urlencode($uri);
	$result = "http://chart.apis.google.com/chart?".
				"chld={$error_correction}|1&amp;".
				"chs={$size}x{$size}&amp;".
				"cht=qr&amp;chl={$url}&amp;".
				"choe=UTF-8";

	return $result;
}
?>
