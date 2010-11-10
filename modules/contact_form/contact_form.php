<?php

/**
 * Contact Form
 *
 * This contact form provides multiple ways of contacting user. It can be
 * used WITHOUT database connection or with it.
 *
 * @author MeanEYE.rcf
 */

class contact_form extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);
	}
	
	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();
			
		return self::$_instance;
	}	

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	public function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'send_from_xml':
					$this->sendFromXML($level, $params, $children);
					break;
					
				case 'send_from_ajax':
					$this->sendFromAJAX();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$sql = "";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Process mail sending request issued by template parser
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	private function sendFromXML($level, $params, $children) {
		$to = "";
		$subject = "";
		$fields = array();
		$template_params = array();
		$headers = array();
		$message_success = null;
		$message_error = null;

		foreach($children as $param)
			switch ($param->tagName) {
				case 'to':
					$to = $param->tagData;
					$template_params['_to'] = $to;
					break;

				case 'subject':
					// TODO: Allow usage of form data in subject
					$subject = "=?utf-8?B?".base64_encode($param->tagData)."?=";
					$template_params['_subject'] = $subject;
					break;

				case 'from':
					if (array_key_exists('name', $param->tagAttrs))
						$name = "=?utf-8?B?".base64_encode($param->tagAttrs['name'])."?="; else
						$name = $param->tagData;

					$address = $param->tagData;
					$headers['From'] = "{$name} <{$address}>";
					$template_params['_from'] = "{$param->tagAttrs['name']} <{$param->tagData}>";
					break;

				case 'fields':
					foreach($param->tagChildren as $field) {
						$fields[$field->tagData] = isset($_REQUEST[$field->tagAttrs['name']]) ? fix_chars($_REQUEST[$field->tagAttrs['name']]) : '';
						$template_params[$field->tagAttrs['name']] = isset($_REQUEST[$field->tagAttrs['name']]) ? fix_chars($_REQUEST[$field->tagAttrs['name']]) : '';
					}

					break;

				case 'message_success':
					$message_success = $param->tagChildren;
					break;

				case 'message_error':
					$message_error = $param->tagChildren;
					break;
			}

		$headers['X-Mailer'] = "RCF-CMS/1.0";
		
		// if address is not specified by the XML, check for system setting
		if (empty($to) && isset($this->settings['default_address'])) {
			$to = $this->settings['default_address'];
			$template_params['_to'] = $to;
		}

		if ($this->_sendMail($to, $subject, $headers, $fields)) {
			// message successfuly sent
			if (!is_null($message_success)) {
				$template = new TemplateHandler();
				$template->setMappedModule($this->name);
				$template->setLocalParams($template_params);
				$template->parse($level, $message_success);
			}
		} else {
			// error sending
			if (!is_null($message_error)) {
				$template = new TemplateHandler();
				$template->setMappedModule($this->name);
				$template->setLocalParams($template_params);
				$template->parse($level, $message_error);
			}
		}
	}
	
	/**
	 * Send contact form data using AJAX request
	 */
	private function sendFromAJAX() {
		define('_OMIT_STATS', 1);
		
		$result = array(
					'error'		=> false,
					'message'	=> ''
				);
				
		if (isset($this->settings['default_address'])) {
			$to = $this->settings['default_address'];
			$subject = $this->settings['default_subject'];
			$fields = array();
			$headers = array(
							'X-Mailer'	=> "RCF-CMS/1.0"
						);
						
			foreach($_REQUEST as $key => $value)
				$fields[$key] = fix_chars($value);

			if ($this->_sendMail($to, $subject, $headers, $fields)) {
				// message successfuly sent
				$result['message'] = $this->getLanguageConstant('message_sent');
			} else {
				// error sending
				$result['error'] = true;
				$result['message'] = $this->getLanguageConstant('message_error');
			}
		} else {
			$result['error'] = true;
			$result['message'] = $this->getLanguageConstant('message_error_no_address');
		}
				
		print json_encode($result);
	}

	/**
	 * Perform send email
	 * 
	 * @param string $to
	 * @param string $subject
	 * @param array $headers
	 * @param array $fields
	 * @return boolean
	 */
	private function _sendMail($to, $subject, $headers, $fields) {
		$body = $this->_makeBody($fields);
		$headers_string = $this->_makeHeaders($headers);

		return mail($to, $subject, $body, $headers_string);
	}

	/**
	 * Create header string from specified array
	 * 
	 * @param array $headers
	 * @return string
	 */
	private function _makeHeaders($headers) {
		$result = array();

		foreach ($headers as $key => $value)
			$result[] = "{$key}: {$value}";

		return join("\r\n", $result);
	}

	/**
	 * Create message body from fields
	 * 
	 * @param array $fields
	 * @return string
	 */
	private function _makeBody($fields) {
		$result = "";
		$max_length = 0;

		foreach($fields as $name => $value)
			if (strlen($name) > $max_length) $max_length = strlen($name);

		foreach($fields as $name => $value)
			$result .= $name.str_repeat(" ", $max_length-strlen($name)).": {$value}\n";

		return $result;
	}
}
