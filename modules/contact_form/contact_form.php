<?php

/**
 * Contact Form
 *
 * This contact form provides multiple ways of contacting user. It can be
 * used WITHOUT database connection or with it.
 *
 * Author: Mladen Mijatov
 */

require_once('units/smtp.php');
require_once('units/form_manager.php');
require_once('units/form_field_manager.php');
require_once('units/template_manager.php');


class contact_form extends Module {
	private static $_instance;
	private $_invalid_params = array(
						'section', 'action', 'PHPSESSID', '__utmz', '__utma',
						'__utmc', '__utmb', '_', 'subject', 'MAX_FILE_SIZE', '_rewrite'
					);

	private $field_types = array(
						'text', 'email', 'textarea', 'hidden', 'checkbox', 'radio',
						'password', 'file', 'color', 'date', 'month', 'datetime', 'datetime-local',
						'time', 'week', 'url', 'number', 'range', 'honey-pot'
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
								$this->getLanguageConstant('menu_manage_forms'),
								url_GetFromFilePath($this->path.'images/forms.png'),

								window_Open( // on click open window
											'contact_forms',
											600,
											$this->getLanguageConstant('title_forms_manage'),
											true, true,
											backend_UrlMake($this->name, 'forms_manage')
										),
								$level=5
							));	
			$contact_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_manage_templates'),
								url_GetFromFilePath($this->path.'images/templates.png'),

								window_Open( // on click open window
											'contact_form_templates',
											550,
											$this->getLanguageConstant('title_templates_manage'),
											true, true,
											backend_UrlMake($this->name, 'templates_manage')
										),
								$level=5
							));	
			$contact_menu->addSeparator(5);
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
				case 'show':
					$this->tag_Form($params, $children);
					break;

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

				case 'templates_manage':
					$this->manageTemplates();
					break;

				case 'templates_add':
					$this->addTemplate();
					break;

				case 'templates_edit':
					$this->editTemplate();
					break;

				case 'templates_save':
					$this->saveTemplate();
					break;

				case 'templates_delete':
					$this->deleteTemplate();
					break;

				case 'templates_delete_commit':
					$this->deleteTemplate_Commit();
					break;

				case 'forms_manage':
					$this->manageForms();
					break;

				case 'forms_add':
					$this->addForm();
					break;

				case 'forms_edit':
					$this->editForm();
					break;

				case 'forms_save':
					$this->saveForm();
					break;

				case 'forms_delete':
					$this->deleteForm();
					break;

				case 'forms_delete_commit':
					$this->deleteForm_Commit();
					break;

				case 'fields_manage':
					$this->manageFields();
					break;

				case 'fields_add':
					$this->addField();
					break;

				case 'fields_edit':
					$this->editField();
					break;

				case 'fields_save':
					$this->saveField();
					break;

				case 'fields_delete':
					$this->deleteField();
					break;

				case 'fields_delete_commit':
					$this->deleteField_Commit();
					break;
					
				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		// predefined settings stored in system wide tables
		$this->saveSetting('use_smtp', 0);
		$this->saveSetting('sender_name', '');
		$this->saveSetting('sender_address', 'sample@email.com');
		$this->saveSetting('recipient_name', '');
		$this->saveSetting('recipient_address', 'sample@email.com');
		$this->saveSetting('recipient_subject', 'Caracal contact email');
		$this->saveSetting('smtp_server', 'smtp.gmail.com');
		$this->saveSetting('smtp_port', '465');
		$this->saveSetting('use_ssl', 1);
		$this->saveSetting('save_copy', 0);
		$this->saveSetting('save_location', '');

		// create templates table
		$sql = "
			CREATE TABLE `contact_form_templates` (
				`id` int NOT NULL AUTO_INCREMENT ,
				`text_id` varchar(32) NULL ,
			";

		foreach($list as $language) {
			$sql .= "`name_{$language}` varchar(50) NOT NULL DEFAULT '',";
			$sql .= "`subject_{$language}` varchar(255) NOT NULL DEFAULT '',";
			$sql .= "`plain_{$language}` text NOT NULL,";
			$sql .= "`html_{$language}` text NOT NULL,";
		}

		$sql .= "
				PRIMARY KEY(`id`),
				INDEX `contact_form_templates_by_text_id` (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create forms table
		$sql = "
			CREATE TABLE `contact_forms` (
				`id` int NOT NULL AUTO_INCREMENT,
				`text_id` varchar(32) NULL,
			";

		foreach($list as $language) 
			$sql .= "`name_{$language}` varchar(50) NOT NULL DEFAULT '',";

		$sql .= "
				`action` varchar(255) NULL,
				`template` varchar(32) NOT NULL,
				`show_submit` boolean NOT NULL DEFAULT '1',
				`show_reset` boolean NOT NULL DEFAULT '1',
				`show_cancel` boolean NOT NULL DEFAULT '0',
				PRIMARY KEY(`id`),
				INDEX `contact_forms_by_text_id` (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "
			CREATE TABLE `contact_form_fields` (
				`id` int NOT NULL AUTO_INCREMENT,
				`form` int NOT NULL,
				`name` varchar(32) NULL,
				`type` varchar(32) NOT NULL,
			";

		foreach($list as $language) {
			$sql .= "`label_{$language}` varchar(100) NOT NULL DEFAULT '',";
			$sql .= "`placeholder_{$language}` varchar(100) NOT NULL DEFAULT '',";
		}

		$sql .= "
				`min` int NOT NULL,
				`max` int NOT NULL,
				`maxlength` int NOT NULL,
				`value` varchar(255) NOT NULL,
				`pattern` varchar(255) NOT NULL,
				`disabled` boolean NOT NULL DEFAULT '0',
				`required` boolean NOT NULL DEFAULT '0',
				`autocomplete` boolean NOT NULL DEFAULT '0',
				PRIMARY KEY(`id`),
				INDEX `contact_form_fields_by_form` (`form`),
				INDEX `contact_form_fields_by_form_and_type` (`form`, `type`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('contact_form_templates', 'contact_forms', 'contact_form_fields');
		$db->drop_tables($tables);
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
		$attachments = array();

		foreach($children as $param)
			switch ($param->tagName) {
				case 'to':
					$to = $param->tagData;
					$template_params['_to'] = $to;
					break;

				case 'subject':
					$subject = $param->tagData;
					$subject = $this->generateSubjectField($subject, $fields);
					$template_params['_subject'] = $subject;
					break;

				case 'from':
					if (array_key_exists('name', $param->tagAttrs))
						$name = $param->tagAttrs['name']; else
						$name = $param->tagData;

					$address = $param->tagData;
					$headers['From'] = $this->generateAddressField($name, $address);
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

		$headers['X-Mailer'] = "Cracal-Framework/1.0";

		// if address is not specified by the XML, check for system setting
		if (empty($to) && isset($this->settings['recipient_address'])) {
			$to = $this->settings['recipient_address'];
			$template_params['_to'] = $to;
		}
	
		// attach speficied files
		if (count($_FILES) > 0)
			foreach($_FILES as $name => $data) {
				$temp_name = $data['tmp_name'];
				$name = $data['name'];
				$attachments[$temp_name] = $name;
			}

		if ($this->_sendMail($to, $subject, $headers, $fields, $attachments)) {
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
		$result = array(
					'error'		=> false,
					'message'	=> ''
				);
		$attachments = array();

		if (isset($this->settings['recipient_address'])) {
			$to = $this->settings['recipient_address'];
			$fields = array();
			$headers = array(
							'X-Mailer'	=> "Cracal-Framework/1.0"
						);

			// allow setting subject in request but sanitize heavily
			if (isset($_REQUEST['subject'])) {
				$subject = fix_chars($_REQUEST['subject']);
				$subject = str_replace(array('/', '\\', '.'), '-', $subject);

			} else {
				$subject = $this->settings['recipient_subject'];
			}

			// attach speficied files
			if (count($_FILES) > 0)
				foreach($_FILES as $name => $data) {
					$temp_name = $data['tmp_name'];
					$name = $data['name'];
					$attachments[$temp_name] = $name;
				}

			// prepare sender
			$name = $this->settings['sender_name'];
			$address = $this->settings['sender_address'];
			$headers['From'] = $this->generateAddressField($name, $address);

			foreach($_REQUEST as $key => $value)
				if (!in_array($key, $this->_invalid_params))
					$fields[$key] = fix_chars($value);

			// format subject
			$subject = $this->generateSubjectField($subject, $fields);

			// try sending message
			if ($this->_sendMail($to, $subject, $headers, $fields, $attachments)) {
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
	 *
	 * TODO: Simplify this code.
	 */
	public function sendFromModule($to, $subject, $text_body, $html_body, $bcc=null) {
		$headers = array();
		$headers['X-Mailer'] = "Cracal-Framework/1.0";

		// prepare recipient
		if ($to == '' || is_null($to)) 
			$to = $this->settings['recipient_address'];
		
		// prepare subject
		if ($subject == '' || is_null($subject))
			$subject = $this->settings['recipient_subject'];
		
		$subject = $this->generateSubjectField($subject);
		
		// prepare sender
		$name = $this->settings['sender_name'];
		$address = $this->settings['sender_address'];
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
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
			$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
			$body .= base64_encode($text_body)."\r\n";
	
			// make html body
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Type: text/html; charset=UTF-8\r\n";
			$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
			$body .= base64_encode($html_body)."\r\n";
	
			// make ending boundary
			$body .= "--{$boundary}--\r\n";
			
		} else {
			// no HTML specified, use plain text
			$body = "\r\n".$text_body;
		}		
		
		// get headers string
		$headers_string = $this->_makeHeaders($headers);

		if (!$this->detectBots())
			if ($this->settings['use_smtp']) {
				$smtp = new SMTP();
				$smtp->set_server(
							$this->settings['smtp_server'],
							$this->settings['smtp_port'],
							$this->settings['use_ssl']
						);
				
				if ($this->settings['smtp_authenticate'])
					$smtp->set_credentials(
								$this->settings['smtp_username'],
								$this->settings['smtp_password']
							);

				$smtp->set_sender($this->settings['sender_address']);

				// add recipients
				$recipient_list = explode(',', $to);
				foreach($recipient_list as $address)
					$smtp->add_recipient($address);

				$smtp->set_subject($subject);
				$result = $smtp->send($headers_string, $body);

			} else {
				// send mail using PHP function
				$result = mail($to, $subject, $body, $headers_string);
			}
		
		return $result;
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
				$name = $this->encodeString($name);
				$result = "{$name} <{$address}>";
			} else {
				$result = $address;
			}
		}

		return $result;
	}

	/**
	 * Generate subject using specified template.
	 *
	 * @param string $template
	 * @param array $fields
	 */
	public function generateSubjectField($template, $fields=array()) {
		return $this->encodeString($this->replaceFields($template, $fields));
	}

	/**
	 * Generate account verification code based on username, email and current time.
	 *
	 * @param string $username
	 * @param string $email
	 * @return string
	 */
	public function generateVerificationCode($username, $email) {
		$starting_hash = sha1((time() * 2) . '--email-verification--');
		return hash_hmac('sha256', $username.'-'.$email, $starting_hash);
	}

	/**
	 * Replace placeholders with field values in specified template.
	 *
	 * @param string $template
	 * @param array $fields
	 * @return $string
	 */
	public function replaceFields($template, $fields) {
		$keys = array_keys($fields);
		$values = array_values($fields);

		// preformat keys for replacement
		foreach ($keys as $index => $key)
			$keys[$index] = "%{$key}%";

		// replace field place holders with values
		return str_replace($keys, $values, $template);
	}

	/**
	 * Create UTF-8 base64 encoded string.
	 *
	 * @param string $string
	 * @return string
	 */
	public function encodeString($string) {
		return "=?utf-8?B?".base64_encode($string)."?=";
	}

	/**
	 * Perform send email
	 *
	 * @param string $to
	 * @param string $subject
	 * @param array $headers
	 * @param array $fields
	 * @param array $attachments
	 * @return boolean
	 * @deprecated
	 *
	 * TODO: Remove this function. It's not needed!
	 */
	private function _sendMail($to, $subject, $headers, $fields, $attachments=array()) {
		global $data_path;

		trigger_error('Deprecation warning: contact_form::_sendMail should not be used any more!', E_USER_NOTICE);

		$result = false;

		// generate boundary string
		$boundary = md5(time().'--global--'.(rand() * 10000));

		// add content type to headers
		if (count($attachments) == 0)
			$headers['Content-Type'] = "multipart/alternative; boundary={$boundary}"; else
			$headers['Content-Type'] = "multipart/mixed; boundary={$boundary}";

		// make body and headers
		$body = $this->_makeBody($fields, $boundary, $attachments);
		$headers_string = $this->_makeHeaders($headers);

		if (!$this->detectBots()) {
			if ($this->settings['use_smtp']) {
				// send email using SMTP
				$smtp = new SMTP();
				$smtp->set_server(
							$this->settings['smtp_server'],
							$this->settings['smtp_port'],
							$this->settings['use_ssl']
						);
				
				if ($this->settings['smtp_authenticate'])
					$smtp->set_credentials(
								$this->settings['smtp_username'],
								$this->settings['smtp_password']
							);

				$smtp->set_sender($this->settings['sender_address']);

				// add recipients
				$recipient_list = explode(',', $to);
				foreach($recipient_list as $address)
					$smtp->add_recipient($address);

				$smtp->set_subject($subject);
				$result = $smtp->send($headers_string, $body);

			} else {
				// send email using built-in function
				$result = mail($to, $subject, $body, $headers_string);
			}

			// store email after sending
			if (isset($this->settings['save_location']) && !empty($this->settings['save_location']))
				$location = $this->settings['save_location']; else
				$location = _BASEPATH.'/'.$data_path;

			$save_copy = isset($this->settings['save_copy']) ? $this->settings['save_copy'] : false;
			$location_okay = is_writable($location);

			if ($result && $save_copy && $location_okay) {
				$timestamp = strftime('%c');
				$file_name = $location."/{$subject} - {$timestamp}.eml";

				$data = "To: {$to}\r\n";
				$data .= "Subject: {$subject}\r\n";
				$data .= $headers_string."\r\n\r\n".$body;

				// try to open file for saving email
				$handle = fopen($file_name, 'w');
				
				if ($handle !== false) {
					// save email content to specifed file
					fwrite($handle, $data);
					fclose($handle);

				} else {
					// log error
					trigger_error("Unable to open file for saving: {$file_name}", E_USER_WARNING);
				}
			
			} else if (!$location_okay) {
				// log problem with destination directory
				trigger_error("Directory is not writable! Unable to save email to: {$location}", E_USER_WARNING);
			}
		}

		return $result;
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
	 * @param array $attachments Associative array of attachments
	 * @return string
	 *
	 * Example:
	 * 		$this->_makeBody(
	 * 					array(
	 * 						'field' => 'value'
	 * 					),
	 * 					'some-string',
	 * 					array(
	 * 						'/var/tmp/somefile-0000.tmp' => 'archive.zip'
	 * 					)
	 * 				);
	 */
	private function _makeBody($fields, $boundary, $attachments=array()) {
		$result = "";
		$content_boundary = md5(time().'--content--'.(rand() * 10000));

		// add global boundary
		$result .= "--{$boundary}\r\n";
		$result .= "Content-Type: multipart/alternative; boundary={$content_boundary}\r\n\r\n";

		// make plain text body
		$result .= "--{$content_boundary}\r\n";
		$result .= "Content-Type: text/plain; charset=UTF-8\r\n";
		$result .= "Content-Transfer-Encoding: base64\r\n\r\n";
		$result .= base64_encode($this->makePlainBody($fields))."\r\n";

		// make html body
		$result .= "--{$content_boundary}\r\n";
		$result .= "Content-Type: text/html; charset=UTF-8\r\n";
		$result .= "Content-Transfer-Encoding: base64\r\n\r\n";
		$result .= base64_encode($this->makeHtmlBody($fields))."\r\n";
		$result .= "--{$content_boundary}--\r\n";

		// attach files
		if (count($attachments) > 0)
			foreach ($attachments as $file => $name)
				$result .= $this->makeAttachment($file, $name, $boundary);

		// make ending boundary
		$result .= "--{$boundary}--\r\n";

		return $result;
	}

	/**
	 * Generate plain text message body from specified fields.
	 *
	 * @param array $fields
	 * @return string
	 */
	public function makePlainBody($fields) {
		$result = "";
		$max_length = 0;

		foreach($fields as $name => $value)
			if (strlen($name) > $max_length) $max_length = strlen($name);

		foreach($fields as $name => $value)
			$result .= $name.str_repeat(" ", $max_length-strlen($name)).": {$value}\r\n";

		return $result;
	}

	/**
	 * Generate HTML body containing table with specified fields.
	 *
	 * @param array $fields
	 * @return string
	 */
	public function makeHtmlBody($fields) {
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
	 * Create string for file to be attached to body of email.
	 *
	 * @param string $file_name Location of file to be attached
	 * @param string $attachment_name Name of file appearing in email
	 * @param string $boundary Boundary string used to separate content
	 * @return string
	 */
	public function makeAttachment($file_name, $attachment_name, $boundary) {
		$result = '';
		$attachment_name = $this->encodeString($attachment_name);

		if (file_exists($file_name)) {
			$data = file_get_contents($file_name);
			$data = base64_encode($data);

			// get file mime type
			$handle = finfo_open(FILEINFO_MIME_TYPE);
			$mime_type = finfo_file($handle, $file_name);
			finfo_close($handle);

			// create result
			$result = "--{$boundary}\r\n";
			$result .= "Content-Type: {$mime_type}; charset=US-ASCII; name=\"{$attachment_name}\"\r\n";
			$result .= "Content-Disposition: attachment; filename=\"{$attachment_name}\"\r\n";
			$result .= "Content-Transfer-Encoding: base64\r\n\r\n";
			$result .= $data."\r\n";
		}

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
		$save_copy = isset($_REQUEST['save_copy']) && ($_REQUEST['save_copy'] == 'on' || $_REQUEST['save_copy'] == '1') ? 1 : 0;

		$params = array(
			'sender_name', 'sender_address', 'recipient_name', 'recipient_address', 'recipient_subject',
			'smtp_server', 'smtp_port', 'smtp_authenticate', 'smtp_username', 'smtp_password', 'save_location'
		);

		// save settings
		foreach($params as $param) {
			$value = fix_chars($_REQUEST[$param]);
			$this->saveSetting($param, $value);
		}

		$this->saveSetting('use_smtp', $use_smtp);
		$this->saveSetting('use_ssl', $use_ssl);
		$this->saveSetting('save_copy', $save_copy);

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

	/**
	 * Show form for managing email templates.
	 */
	private function manageTemplates() {
		$template = new TemplateHandler('templates_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'contact_form_templates_add', 650,
										$this->getLanguageConstant('title_templates_add'),
										true, false,
										$this->name,
										'templates_add'
									),
				);

		$template->registerTagHandler('cms:list', $this, 'tag_TemplateList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for new template.
	 */
	private function addTemplate() {
		$template = new TemplateHandler('templates_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'templates_save'),
					'cancel_action'	=> window_Close('contact_form_templates_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for editing email template.
	 */
	private function editTemplate() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_TemplateManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('templates_change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'id'					=> $item->id,
						'text_id'				=> $item->text_id,
						'name'					=> $item->name,
						'subject'				=> $item->subject,
						'plain_text_content'	=> $item->plain,
						'html_content'			=> $item->html,
						'form_action'  			=> backend_UrlMake($this->name, 'templates_save'),
						'cancel_action'			=> window_Close('contact_form_templates_edit')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed template data.
	 */
	private function saveTemplate() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = fix_chars($_REQUEST['text_id']);
		$name = $this->getMultilanguageField('name');
		$subject = $this->getMultilanguageField('subject');
		$plain_text = $this->getMultilanguageField('plain_text_content');
		$html = $this->getMultilanguageField('html_content');

		$manager = ContactForm_TemplateManager::getInstance();
		$data = array(
				'text_id'	=> $text_id,
				'name'		=> $name,
				'subject'	=> $subject,
				'plain'		=> $plain_text,
				'html'		=> $html
			);

		if (is_null($id)) {
			$window = 'contact_form_templates_add';
			$manager->insertData($data);
		} else {
			$window = 'contact_form_templates_edit';
			$manager->updateData($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_template_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('contact_form_templates'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation form for deleting template.
	 */
	private function deleteTemplate() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_TemplateManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_template_delete"),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'contact_form_templates_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'templates_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('contact_form_templates_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete template.
	 */
	private function deleteTemplate_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_TemplateManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_template_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('contact_form_templates_delete').';'.window_ReloadContent('contact_form_templates')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show management window for forms.
	 */
	private function manageForms() {
		$template = new TemplateHandler('forms_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'contact_forms_add', 400,
										$this->getLanguageConstant('title_forms_add'),
										true, false,
										$this->name,
										'forms_add'
									),
				);

		$template->registerTagHandler('cms:list', $this, 'tag_FormList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show new contact form dialog.
	 */
	private function addForm() {
		$template = new TemplateHandler('forms_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'forms_save'),
					'cancel_action'	=> window_Close('contact_forms_add')
				);

		$template->registerTagHandler('cms:template_list', $this, 'tag_TemplateList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
 	 * Show change form dialog.
	 */
	private function editForm() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('forms_change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'id'				=> $item->id,
						'text_id'			=> $item->text_id,
						'name'				=> $item->name,
						'action'			=> $item->action,
						'template'			=> $item->template,
						'show_submit'		=> $item->show_submit,
						'show_reset'		=> $item->show_reset,
						'show_cancel'		=> $item->show_cancel,
						'form_action'  		=> backend_UrlMake($this->name, 'forms_save'),
						'cancel_action'		=> window_Close('contact_forms_edit')
					);

			$template->registerTagHandler('cms:template_list', $this, 'tag_TemplateList');

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new of changed form data.
	 */
	private function saveForm() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$data = array(
				'text_id'		=> fix_chars($_REQUEST['text_id']),
				'name'			=> $this->getMultilanguageField('name'),
				'action'		=> escape_chars($_REQUEST['action']),
				'template'		=> fix_chars($_REQUEST['template']),
				'show_submit'	=> isset($_REQUEST['show_submit']) && ($_REQUEST['show_submit'] == 'on' || $_REQUEST['show_submit'] == '1') ? 1 : 0,
				'show_reset'	=> isset($_REQUEST['show_reset']) && ($_REQUEST['show_reset'] == 'on' || $_REQUEST['show_reset'] == '1') ? 1 : 0,
				'show_cancel'	=> isset($_REQUEST['show_cancel']) && ($_REQUEST['show_cancel'] == 'on' || $_REQUEST['show_cancel'] == '1') ? 1 : 0
			);
		$manager = ContactForm_FormManager::getInstance();

		// insert or update data in database
		if (is_null($id)) {
			$window = 'contact_forms_add';
			$manager->insertData($data);
		} else {
			$window = 'contact_forms_edit';
			$manager->updateData($data,	array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_form_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('contact_forms'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before removing contact form.
	 */
	private function deleteForm() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_form_delete"),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'contact_forms_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'forms_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('contact_forms_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
 	 * Remove contact form and all of its fields.
	 * Note: This will remove contact form data as well.
	 */
	private function deleteForm_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormManager::getInstance();
		$field_manager = ContactForm_FormFieldManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$field_manager->deleteData(array('form' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_form_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('contact_forms_delete').';'.window_ReloadContent('contact_forms')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show field management window.
	 */
	private function manageFields() {
		$form_id = fix_id($_REQUEST['form']);
		$template = new TemplateHandler('fields_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form'		=> $form_id,
					'link_new'	=> url_MakeHyperlink(
										$this->getLanguageConstant('new'),
										window_Open(
											'contact_form_fields_add', 	// window id
											400,				// width
											$this->getLanguageConstant('title_fields_add'), // title
											false, false,
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'fields_add'),
												array('form', $form_id)
											)
										)
									)
				);

		$template->registerTagHandler('cms:list', $this, 'tag_FieldList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new field.
	 */
	private function addField() {
		$form_id = fix_id($_REQUEST['form']);

		$template = new TemplateHandler('fields_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form'			=> $form_id,
					'form_action'	=> backend_UrlMake($this->name, 'fields_save'),
					'cancel_action'	=> window_Close('contact_form_fields_add')
				);

		$template->registerTagHandler('cms:field_types', $this, 'tag_FieldTypes');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for editing existing field.
	 */
	private function editField() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormFieldManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('fields_change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'id'				=> $item->id,
						'form'				=> $item->form,
						'name'				=> $item->name,
						'type'				=> $item->type,
						'label'				=> $item->label,
						'placeholder'		=> $item->placeholder,
						'min'				=> $item->min,
						'max'				=> $item->max,
						'maxlength'			=> $item->maxlength,
						'value'				=> $item->value,
						'pattern'			=> $item->pattern,
						'disabled'			=> $item->disabled,
						'required'			=> $item->required,
						'autocomplete'		=> $item->autocomplete,
						'form_action'  		=> backend_UrlMake($this->name, 'fields_save'),
						'cancel_action'		=> window_Close('contact_form_fields_edit')
					);

			$template->registerTagHandler('cms:field_types', $this, 'tag_FieldTypes');

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed field data.
	 */
	private function saveField() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$form_id = fix_id($_REQUEST['form']);

		$data = array(
			'form'			=> $form_id,
			'name'			=> fix_chars($_REQUEST['name']),
			'type'			=> fix_chars($_REQUEST['type']),
			'label'			=> $this->getMultilanguageField('label'),
			'placeholder'	=> $this->getMultilanguageField('placeholder'),
			'min'			=> fix_id($_REQUEST['min']),
			'max'			=> fix_id($_REQUEST['max']),
			'maxlength'		=> fix_id($_REQUEST['maxlength']),
			'value'			=> escape_chars($_REQUEST['value']),
			'pattern'		=> escape_chars($_REQUEST['pattern']),
			'disabled'		=> isset($_REQUEST['disabled']) && ($_REQUEST['disabled'] == 'on' || $_REQUEST['disabled'] == '1') ? 1 : 0,
			'required'		=> isset($_REQUEST['required']) && ($_REQUEST['required'] == 'on' || $_REQUEST['required'] == '1') ? 1 : 0,
			'autocomplete'	=> isset($_REQUEST['autocomplete']) && ($_REQUEST['autocomplete'] == 'on' || $_REQUEST['autocomplete'] == '1') ? 1 : 0
		);
		$manager = ContactForm_FormFieldManager::getInstance();

		// insert or update data in database
		if (is_null($id)) {
			$window = 'contact_form_fields_add';
			$manager->insertData($data);
		} else {
			$window = 'contact_form_fields_edit';
			$manager->updateData($data,	array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_field_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('contact_form_fields_'.$form_id),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog before removing field.
	 */
	private function deleteField() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormFieldManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_field_delete"),
					'name'			=> $item->name,
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'contact_form_fields_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'fields_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('contact_form_fields_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perfrom field removal.
	 */
	private function deleteField_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormFieldManager::getInstance();

		$form = $manager->getItemValue('form', array('id' => $id));
		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_field_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('contact_form_fields_delete').';'.window_ReloadContent('contact_form_fields_'.$form)
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	public function tag_FieldTypes($tag_params, $children) {
		$selected = null;

		// get parameters
		if (isset($tag_params['selected']))
			$selected = fix_chars($tag_params['selected']);

		// load template
		$template = $this->loadTemplate($tag_params, 'field_option.xml');

		foreach ($this->field_types as $field) {
			$params = array(
				'selected'	=> $field == $selected,
				'type'		=> $field,
				'name'		=> $this->getLanguageConstant('field_'.$field)
			);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Handle drawing list of templates.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_TemplateList($tag_params, $children) {
		$conditions = array();
		$manager = ContactForm_TemplateManager::getInstance();
		$selected = isset($tag_params['selected']) ? fix_chars($tag_params['selected']) : null;

		// load template
		$template = $this->loadTemplate($tag_params, 'templates_list_item.xml');

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'name'			=> $item->name,
						'subject'		=> $item->subject,
						'plain'			=> $item->plain,
						'html'			=> $item->html,
						'selected'		=> $selected,
						'item_change'	=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													'contact_form_templates_edit', 	// window id
													650,				// width
													$this->getLanguageConstant('title_templates_edit'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'templates_edit'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'	=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													'contact_form_templates_delete', 	// window id
													400,				// width
													$this->getLanguageConstant('title_templates_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'templates_delete'),
														array('id', $item->id)
													)
												)
											),
					);

				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}

	/**
	 * Handle drawing fields for specified form.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_FieldList($tag_params, $children) {
		$conditions = array();
		$manager = ContactForm_FormFieldManager::getInstance();

		// get parameters
		if (isset($tag_params['form']))
			$conditions['form'] = fix_id($tag_params['form']);

		// load template
		$template = $this->loadTemplate($tag_params, 'field.xml');

		// get fields
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		trigger_error(json_encode($items));

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
					'id'			=> $item->id,
					'form'			=> $item->form,
					'name'			=> $item->name,
					'type'			=> $item->type,
					'label'			=> $item->label,
					'placeholder'	=> $item->placeholder,
					'min'			=> $item->min,
					'max'			=> $item->max,
					'maxlength'		=> $item->maxlength,
					'value'			=> $item->value,
					'pattern'		=> $item->pattern,
					'disabled'		=> $item->disabled,
					'required'		=> $item->required,
					'autocomplete'	=> $item->autocomplete,
					'item_change'	=> url_MakeHyperlink(
											$this->getLanguageConstant('change'),
											window_Open(
												'contact_form_fields_edit', 	// window id
												400,				// width
												$this->getLanguageConstant('title_fields_edit'), // title
												false, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'fields_edit'),
													array('id', $item->id)
												)
											)
										),
					'item_delete'	=> url_MakeHyperlink(
											$this->getLanguageConstant('delete'),
											window_Open(
												'contact_form_fields_delete', 	// window id
												400,				// width
												$this->getLanguageConstant('title_fields_delete'), // title
												false, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'fields_delete'),
													array('id', $item->id)
												)
											)
										)
				);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Handle drawing a single form.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Form($tag_params, $children) {
		$conditions = array();
		$manager = ContactForm_FormManager::getInstance();
		$field_manager = ContactForm_FormFieldManager::getInstance();

		// get parameters
		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars($tag_params['text_id']);

		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		// load template
		$template = $this->loadTemplate($tag_params, 'form.xml');
		$template->registerTagHandler('cms:fields', $this, 'tag_FieldList');

		// get form from the database
		$item = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		if (is_object($item)) {
			$fields = $field_manager->getItems(
				array('id'),
				array(
					'form'	=> $item->id,
					'type'	=> 'file'
				)
			);

			$params = array(
				'id'			=> $item->id,
				'text_id'		=> $item->text_id,
				'name'			=> $item->name,
				'action'		=> !empty($item->action) ? $item->action : url_Make('submit', $this->name),
				'template'		=> $item->template,
				'show_submit'	=> $item->show_submit,
				'show_reset'	=> $item->show_reset,
				'show_cancel'	=> $item->show_cancel,
				'show_controls'	=> $item->show_submit || $item->show_reset || $item->show_cancel,
				'has_files'		=> count($fields) > 0
			);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Handle drawing list of forms.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_FormList($tag_params, $children) {
		$conditions = array();
		$manager = ContactForm_FormManager::getInstance();

		// load template
		$template = $this->loadTemplate($tag_params, 'forms_list_item.xml');
		$template->registerTagHandler('cms:fields', $this, 'tag_FieldList');

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
					'id'			=> $item->id,
					'text_id'		=> $item->text_id,
					'name'			=> $item->name,
					'action'		=> $item->action,
					'template'		=> $item->template,
					'show_submit'	=> $item->show_submit,
					'show_reset'	=> $item->show_reset,
					'show_cancel'	=> $item->show_cancel,
					'item_fields'	=> url_MakeHyperlink(
											$this->getLanguageConstant('fields'),
											window_Open(
												'contact_form_fields_'.$item->id, 	// window id
												350,				// width
												$this->getLanguageConstant('title_form_fields'), // title
												true, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'fields_manage'),
													array('form', $item->id)
												)
											)
										),
					'item_change'	=> url_MakeHyperlink(
											$this->getLanguageConstant('change'),
											window_Open(
												'contact_forms_edit', 	// window id
												400,				// width
												$this->getLanguageConstant('title_forms_edit'), // title
												false, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'forms_edit'),
													array('id', $item->id)
												)
											)
										),
					'item_delete'	=> url_MakeHyperlink(
											$this->getLanguageConstant('delete'),
											window_Open(
												'contact_forms_delete', 	// window id
												400,				// width
												$this->getLanguageConstant('title_forms_delete'), // title
												false, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'forms_delete'),
													array('id', $item->id)
												)
											)
										)
				);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Create email from specified template.
	 *
	 * @param string $name
	 * @param array $fields
	 * @param array $attachments
	 * @return array
	 */
	public function makeEmailFromTemplate($name, $fields=array(), $attachments=array()) {
		global $language;

		$manager = ContactForm_TemplateManager::getInstance();
		$headers = array();
		$body = '';
		$boundary = md5(time().'--global--'.(rand() * 10000));
		$content_boundary = md5(time().'--content--'.(rand() * 10000));

		// create result template
		$result = array(
				'headers'		=> null,
				'subject'		=> '',
				'body'			=> '',
				'error'			=> true
			);

		// add content type to headers
		if (count($attachments) == 0)
			$headers['Content-Type'] = "multipart/alternative; boundary={$boundary}"; else
			$headers['Content-Type'] = "multipart/mixed; boundary={$boundary}";

		// prepare sender
		$sender_name = $this->settings['sender_name'];
		$sender_address = $this->settings['sender_address'];
		$headers['From'] = $this->generateAddressField($sender_name, $sender_address);

		// get templates
		$template = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $name));

		if (is_object($template)) {
			$plain_text_body = $template->plain[$language];
			$html_body = $template->html[$language];
			$subject = $template->subject[$language];

			// replace fields
			$plain_text_body = $this->replaceFields($plain_text_body, $fields);
			$html_body = $this->replaceFields($html_body, $fields);
			$subject = $this->generateSubjectField($subject, $fields);

			// make html from markdown
			$html_body = Markdown($html_body);

		} else {
			// no template with specified name found
			return $result;
		}

		// add global boundary
		$body .= "--{$boundary}\n";
		$body .= "Content-Type: multipart/alternative; boundary={$content_boundary}\n\n";

		// add plain text body
		$body .= "--{$content_boundary}\n";
		$body .= "Content-Type: text/plain; charset=UTF-8\n";
		$body .= "Content-Transfer-Encoding: base64\n\n";
		$body .= base64_encode($plain_text_body)."\n";

		// add html body
		$body .= "--{$content_boundary}\n";
		$body .= "Content-Type: text/html; charset=UTF-8\n";
		$body .= "Content-Transfer-Encoding: base64\n\n";
		$body .= base64_encode($html_body)."\n";
		$body .= "--{$content_boundary}--\n";

		// add attachments if needed
		if (count($attachments) > 0)
			foreach ($attachments as $fiel => $name)
				$body .= $this->makeAttachment($file, $name, $boundary);

		// add ending boundary
		$body .= "--{$boundary}--\n";

		// update result with proper values
		$result['headers'] = $headers;
		$result['body'] = $body;
		$result['subject'] = $subject;
		$result['error'] = false;

		return $result;
	}

	/**
	 * Send email using predefined parameters.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $body
	 * @param array $additional_headers
	 * @return boolean
	 */
	public function sendMail($to, $subject, $body, $headers) {
		global $data_path;

		$result = false;
		$headers_string = $this->_makeHeaders($headers);

		// make sure bot is not submitting email
		if (!$this->detectBots()) {
			if ($this->settings['use_smtp']) {
				// send email using SMTP
				$smtp = new SMTP();
				$smtp->set_server(
							$this->settings['smtp_server'],
							$this->settings['smtp_port'],
							$this->settings['use_ssl']
						);
				
				if ($this->settings['smtp_authenticate'])
					$smtp->set_credentials(
								$this->settings['smtp_username'],
								$this->settings['smtp_password']
							);

				$smtp->set_sender($this->settings['sender_address']);

				// add recipients
				$recipient_list = explode(',', $to);
				foreach($recipient_list as $address)
					$smtp->add_recipient($address);

				$smtp->set_subject($subject);
				$result = $smtp->send($headers_string, $body);

			} else {
				// send email using built-in function
				$result = mail($to, $subject, $body, $headers_string);
			}

			// store email after sending
			if (isset($this->settings['save_location']) && !empty($this->settings['save_location']))
				$location = $this->settings['save_location']; else
				$location = _BASEPATH.'/'.$data_path;

			$save_copy = isset($this->settings['save_copy']) ? $this->settings['save_copy'] : false;
			$location_okay = is_writable($location);

			if ($result && $save_copy && $location_okay) {
				$timestamp = strftime('%c');
				$file_name = $location."/{$subject} - {$timestamp}.eml";

				$data = "To: {$to}\r\n";
				$data .= "Subject: {$subject}\r\n";
				$data .= $headers_string."\r\n\r\n".$body;

				// try to open file for saving email
				$handle = fopen($file_name, 'w');
				
				if ($handle !== false) {
					// save email content to specifed file
					fwrite($handle, $data);
					fclose($handle);

				} else {
					// log error
					trigger_error("Unable to open file for saving: {$file_name}", E_USER_WARNING);
				}
			
			} else if (!$location_okay && $save_copy) {
				// log problem with destination directory
				trigger_error("Directory is not writable! Unable to save email to: {$location}", E_USER_WARNING);
			}
		}

		return $result;
	}
}
