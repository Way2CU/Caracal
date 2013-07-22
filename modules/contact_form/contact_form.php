<?php

/**
 * Contact Form
 *
 * This contact form provides multiple ways of contacting user. It can be
 * used WITHOUT database connection or with it.
 *
 * Author: Mladen Mijatov
 */

class contact_form extends Module {
	private static $_instance;
	private $_invalid_params = array(
						'section', 'action', 'PHPSESSID', '__utmz', '__utma',
						'__utmc', '__utmb', '_'
					);

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);
		
		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$contact_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_contact'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);
			
			$contact_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_settings'),
								url_GetFromFilePath($this->path.'images/settings.png'),

								window_Open( // on click open window
											'contact_form_settings',
											400,
											$this->getLanguageConstant('title_settings'),
											true, true,
											backend_UrlMake($this->name, 'settings_show')
										),
								$level=5
							));	

			$backend->addMenu($this->name, $contact_menu);
		}		
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
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'send_from_xml':
					$this->sendFromXML($params, $children);
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
				case 'settings_show':
					$this->showSettings();
					break;

				case 'settings_save':
					$this->saveSettings();
					break;
					
				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		$this->saveSetting('use_smtp', 0);
		$this->saveSetting('sender_name', '');
		$this->saveSetting('sender_address', 'sample@email.com');
		$this->saveSetting('sender_subject', 'Caracal contact email');
		$this->saveSetting('recipient_name', '');
		$this->saveSetting('recipient_address', 'sample@email.com');
		$this->saveSetting('recipient_subject', 'Caracal contact email');
		$this->saveSetting('smtp_server', 'smtp.gmail.com');
		$this->saveSetting('smtp_port', '465');
		$this->saveSetting('use_ssl', 1);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;
	}

	/**
	 * Function that tries to figgure out if human is sending the data.
	 *
	 * @param boolean $strict Whether to be strict when checking for bots
	 * @return boolean
	 */
	public function detectBots($strict=false) {
		$result = false;

		// every browser sets user agent field, absence of one almost
		// always means user submitting data is actually a bot
		if (empty($_SERVER['HTTP_USER_AGENT']))
			$result = true;

		// most of modern browsers set referer field, however it's possible
		// for this field not to be set by some browsers when submitting form
		if ($strict && empty($_SERVER['HTTP_REFERER']))
			$result = true;

		return $result;
	}

	/**
	 * Process mail sending request issued by template parser
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function sendFromXML($params, $children) {
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
				$template->parse($message_success);
			}
		} else {
			// error sending
			if (!is_null($message_error)) {
				$template = new TemplateHandler();
				$template->setMappedModule($this->name);
				$template->setLocalParams($template_params);
				$template->parse($message_error);
			}
		}
	}

	/**
	 * Send contact form data using AJAX request
	 */
	public function sendFromAJAX($skip_message=False) {
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

			// prepare sender
			$name = array($this->settings['default_name']);
			$address = explode(',', $this->settings['default_address']);
			$headers['From'] = $this->generateAddressField($name, $address);

			foreach($_REQUEST as $key => $value)
				if (!in_array($key, $this->_invalid_params))
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

		if (!$skip_message)
			print json_encode($result);
	}
	
	/**
	 * Send mail from different module with specified parameters
	 * 
	 * @param string $to
	 * @param string $subject
	 * @param string $text_body
	 * @param string $html_body
	 * @param string $bcc
	 */
	public function sendFromModule($to, $subject, $text_body, $html_body, $bcc=null) {
		$headers = array();
		$headers['X-Mailer'] = "RCF-CMS/1.0";
		
		// prepare subject
		if ($subject == '' || is_null($subject))
			$subject = $this->settings['default_subject'];
		
		$subject = "=?utf-8?B?".base64_encode($subject)."?=";
		
		// prepare sender
		$name = array($this->settings['default_name']);
		$address = split(',', $this->settings['default_address']);
		$headers['From'] = $this->generateAddressField($name, $address);

		// add bcc if specified setting bcc = -1 will use from 
		// field effectively sending copy of email to sender
		if (!is_null($bcc)) 
			if (is_numeric($bcc) && $bcc == -1)
				$headers['Bcc'] = $headers['From']; else
				$headers['Bcc'] = $bcc;
		
		// create boundary string
		$boundary = md5(time().'--cms--'.(rand() * 10000));
		$headers['Content-Type'] = "multipart/alternative; boundary={$boundary}";
		
		// create mail body
		if (!empty($html_body)) {
			// make plain text body
			$body .= "--{$boundary}\n";
			$body .= "Content-Type: text/plain; charset=UTF-8\n";
			$body .= "Content-Transfer-Encoding: base64\n\n";
			$body .= base64_encode($text_body)."\n";
	
			// make html body
			$body .= "--{$boundary}\n";
			$body .= "Content-Type: text/html; charset=UTF-8\n";
			$body .= "Content-Transfer-Encoding: base64\n\n";
			$body .= base64_encode($html_body)."\n";
	
			// make ending boundary
			$body .= "--{$boundary}--\n";
			
		} else {
			// no HTML specified, use plain text
			$body = "\n".$text_body;
		}		
		
		// get headers string
		$headers_string = $this->_makeHeaders($headers);
		
		return !$this->detectBots() && mail($to, $subject, $body, $headers_string);
	}

	/**
	 * Generate address string
	 *
	 * @param array/string $name
	 * @param array/string $address
	 * @return string
	 */
	public function generateAddressField($name, $address) {
		$result = '';

		if (is_array($name)) {
			// generate from multiple addresses
			$temp = array();

			for ($i = 0; $i < count($address); $i++) {
				$name_value = isset($name[$i]) ? $name[$i] : '';
				$temp[] = $this->generateAddressField($name_value, $address[$i]);
			}

			$result = implode(',', $temp);

		} else {
			// generate from single address
			if (!empty($name)) {
				$name = '=?utf-8?B?' . base64_encode($name) . '?=';
				$result = "{$name} <{$address}>";
			} else {
				$result = $address;
			}
		}

		return $result;
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
		// generate boundary string
		$boundary = md5(time().'--cms--'.(rand() * 10000));

		// add content type to headers
		$headers['Content-Type'] = "multipart/alternative; boundary={$boundary}";

		// make body and headers
		$body = $this->_makeBody($fields, $boundary);
		$headers_string = $this->_makeHeaders($headers);

		return !$this->detectBots() && mail($to, $subject, $body, $headers_string);
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
	 * @param string $boundary
	 * @return string
	 */
	private function _makeBody($fields, $boundary) {
		$result = "";

		// make plain text body
		$result .= "--{$boundary}\n";
		$result .= "Content-Type: text/plain; charset=UTF-8\n";
		$result .= "Content-Transfer-Encoding: base64\n\n";
		$result .= base64_encode($this->_makePlainBody($fields))."\n";

		// make html body
		$result .= "--{$boundary}\n";
		$result .= "Content-Type: text/html; charset=UTF-8\n";
		$result .= "Content-Transfer-Encoding: base64\n\n";
		$result .= base64_encode($this->_makeHtmlBody($fields))."\n";

		// make ending boundary
		$result .= "--{$boundary}--\n";

		return $result;
	}

	/**
	 * Generate plain text message body
	 *
	 * @param array $fields
	 * @return string
	 */
	private function _makePlainBody($fields) {
		$result = "";
		$max_length = 0;

		foreach($fields as $name => $value)
			if (strlen($name) > $max_length) $max_length = strlen($name);

		foreach($fields as $name => $value)
			$result .= $name.str_repeat(" ", $max_length-strlen($name)).": {$value}\n";

		return $result;
	}

	/**
	 * Generate HTML message body
	 *
	 * @param array $fields
	 * @return string
	 */
	private function _makeHtmlBody($fields) {
		$is_rtl = MainLanguageHandler::getInstance()->isRTL();
		$direction = $is_rtl ? 'direction: rtl;' : 'direction: ltr;';
		$result = '<table width="100%" cellspacing="0" cellpadding="5" border="1" frame="box" rules="rows">';

		foreach($fields as $name => $value)
			if ($is_rtl) 
				$result .= '<tr><td valign="top" style="'.$direction.'">'.$value.'</td><td valign="top" style="'.$direction.'"><b>'.$name.'</b></td></tr>'; else
				$result .= '<tr><td valign="top" style="'.$direction.'"><b>'.$name.'</b></td><td valign="top" style="'.$direction.'">'.$value.'</td></tr>';

		$result .= '</table>';

		return $result;
	}
	
	/**
	 * Show settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
						'cancel_action'	=> window_Close('contact_form_settings')
					);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function saveSettings() {
		// grab parameters
		$use_smtp = isset($_REQUEST['use_smtp']) && ($_REQUEST['use_smtp'] == 'on' || $_REQUEST['use_smtp'] == '1') ? 1 : 0;
		$use_ssl = isset($_REQUEST['use_ssl']) && ($_REQUEST['use_ssl'] == 'on' || $_REQUEST['use_ssl'] == '1') ? 1 : 0;

		$params = array(
			'sender_name', 'sender_address', 'sender_subject',
			'recipient_name', 'recipient_address', 'recipient_subject',
			'smtp_server', 'smtp_port', 'smtp_authenticate', 'smtp_username', 'smtp_password'
		);

		// save settings
		foreach($params as $param) {
			$value = fix_chars($_REQUEST[$param]);
			$this->saveSetting($param, $value);
		}

		$this->saveSetting('use_smtp', $use_smtp);
		$this->saveSetting('use_ssl', $use_ssl);

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('contact_form_settings')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
}
