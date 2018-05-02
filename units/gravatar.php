<?php

/**
 * Gravatar Support Functions
 * Author: Mladen Mijatov
 */

function gravatar_get($email, $size=50) {
	global $gravatar_url, $gravatar_rating, $gravatar_default;

	$result = str_replace('{email_hash}', md5(strtolower(trim($email))), $gravatar_url);
	$result = str_replace('{size}', $size, $result);
	$result = str_replace('{default}', $gravatar_default, $result);
	$result = str_replace('{rating}', $gravatar_rating, $result);

	return (_SECURE ? 'https://' : 'http://').$result;
}

?>
