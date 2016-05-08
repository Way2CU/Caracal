<?php

/**
 * Generic OnTop handler class used for easy sending of push
 * notifications to predefined Andriod applications.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\OnTop;

final class Handler {
	private static $targets = array();

	const API_VERSION = 1;
	const API_ENDPOINT = 'https://ontop.tech/api/batchPush';

	/**
	 * Get targets to send data to. If optional params are specified
	 * only targets that are marked to handle those events will be
	 * returned.
	 *
	 * The following events are supported:
	 * 		shop_transaction_complete
	 * 		contact_form_submit
	 *
	 * @param array $params
	 * @return array
	 */
	public static function get_targets($params=array()) {
		$result = array();
		$manager = Manager::getInstance();
		$conditions = array();

		// prepare conditions for query
		$available_params = array(
				'shop_transaction_complete',
				'contact_form_submit'
			);

		foreach ($params as $param)
			if (in_array($param, $available_params))
				$conditions[$param] = 1;

		// get applications for specified conditions
		$applications = $manager->getItems(array('uid', 'key'), $conditions);

		// prepare result
		if (count($applications) > 0)
			foreach ($applications as $application)
				$result[] = array(
					'id'  => $application->uid,
					'key' => $application->key
				);

		return $result;
	}

	/**
	 * Set target applications to receive notifications. Each
	 * target is an array with application uid and key.
	 *
	 * Example targets:
	 * $targets = array(
	 * 		'uid' => 'key'
	 * 	);
	 *
	 * @param array $targets
	 */
	public static function set_targets($targets) {
		self::$targets = $targets;
	}

	/**
	 * Clear previously set targets. If push is called with empty target
	 * set all applications defined in system will receive notification.
	 */
	public static function clear_targets() {
		self::$targets = array();
	}

	/**
	 * Push notification with custom parameters.
	 *
	 * @param string $message
	 * @param string $category
	 * @param string $action
	 * @param string $url
	 * @param array $custom
	 */
	public static function push($message=null, $category=null, $action=null, $url=null, $custom=null) {
		// get target applications
		$targets = empty(self::$targets) ? self::get_targets() : self::$targets;

		// we need targets to send to
		if (empty($targets))
			return;

		// prepare parameters
		$params = array(
				'api_ver' => self::API_VERSION,
				'is_post' => 1
			);
		$url = self::API_ENDPOINT.'?'.http_build_query($params);

		$data = array();
		if (!is_null($message))
			if (mb_strlen($message) <= 255)
				$data['message'] = $message; else
				trigger_error(
					'OnTop: "message" is longer than 255 characters. Silently dropped!',
					E_USER_NOTICE
				);

		if (!is_null($category))
			if (mb_strlen($category) <= 255)
				$data['category'] = $category; else
				trigger_error(
					'OnTop: "category" is longer than 255 characters. Silently dropped!',
					E_USER_NOTICE
				);

		if (!is_null($action))
		   	if (mb_strlen($action) <= 255)
				$data['action'] = $action; else
				trigger_error(
					'OnTop: "action" is longer than 255 characters. Silently dropped!',
					E_USER_NOTICE
				);

		if (!is_null($url))
			$data['noti_action_url'] = $url;

		if (!is_null($custom) && is_array($custom))
			$data['custom'] = json_encode($custom);

		// make sure we have something to send
		if (empty($data))
			return;

		// prepare for sending
		$options = array(
				'http' => array(
					'apps'    => json_encode($targets),
					'header'  => "Content-type: application/x-www-form-urlencoded",
					'method'  => 'POST',
					'content' => http_build_query($data)
				));
        $context  = stream_context_create($options);

		// send data
		$result = file_get_contents($url, false, $context);
	}
}

?>
