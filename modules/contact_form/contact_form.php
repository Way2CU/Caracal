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

	/**
	 * Constructor
	 */
	function __construct() {
		$this->file = __FILE__;
		parent::__construct();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'send_from_xml':
					$this->sendFromXML($level, $params, $children);
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
	function onInit() {
		global $db_active, $db;

		$sql = "";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDisable() {
		global $db_active, $db;

		$sql = "";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');
		}
	}

	/**
	 * Process mail sending request issued by template parser
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function sendFromXML($level, $params, $children) {
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
					$params['_to'] = $to;
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
	 * Actually send email
	 * @param string $to
	 * @param string $subject
	 * @param array $headers
	 * @param array $fields
	 * @return boolean
	 */
	function _sendMail($to, $subject, $headers, $fields) {
		$body = $this->_makeBody($fields);
		$headers_string = $this->_makeHeaders($headers);

		return mail($to, $subject, $body, $headers_string);
	}

	/**
	 * Create header string from specified array
	 * @param array $headers
	 * @return string
	 */
	function _makeHeaders($headers) {
		$result = array();

		foreach ($headers as $key => $value)
			$result[] = "{$key}: {$value}";

		return join("\r\n", $result);
	}

	/**
	 * Create message body from fields
	 * @param array $fields
	 * @return string
	 */
	function _makeBody($fields) {
		$result = "";
		$max_length = 0;

		foreach($fields as $name => $value)
			if (strlen($name) > $max_length) $max_length = strlen($name);

		foreach($fields as $name => $value)
			$result .= $name.str_repeat(" ", $max_length-strlen($name)).": {$value}\n";

		return $result;
	}
}
