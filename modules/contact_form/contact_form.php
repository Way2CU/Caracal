<?php

/**
 * Contact Form
 *
 * This contact form provides multiple ways of contacting user. It can be
 * used WITHOUT database connection or with it.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;

require_once('units/form_manager.php');
require_once('units/form_field_manager.php');
require_once('units/template_manager.php');
require_once('units/submission_manager.php');
require_once('units/submission_field_manager.php');
require_once('units/mailer.php');
require_once('units/system_mailer.php');
require_once('units/smtp_mailer.php');


class contact_form extends Module {
	private static $_instance;

	private $mailers = array();
	private $field_types = array(
					'text', 'email', 'textarea', 'hidden', 'checkbox', 'radio',
					'password', 'file', 'color', 'date', 'month', 'datetime', 'datetime-local',
					'time', 'week', 'url', 'number', 'range', 'honey-pot'
				);
	private $hidden_fields = array('hidden', 'honey-pot');

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);

		// register events
		Events::register($this->name, 'email-sent', 3);  // params: mailer, recipient, data

		// create mailer support
		$system_mailer = new ContactForm_SystemMailer($this->language);
		$smtp_mailer = new ContactForm_SmtpMailer($this->language);

		$this->registerMailer('system', $system_mailer);
		$this->registerMailer('smtp', $smtp_mailer);

		// configure SMTP mailer
		$smtp_mailer->set_server(
					$this->settings['smtp_server'],
					$this->settings['smtp_port'],
					$this->settings['use_ssl']
				);
		
		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$contact_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_contact'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);
			
			$contact_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_manage_forms'),
								url_GetFromFilePath($this->path.'images/forms.svg'),

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
								url_GetFromFilePath($this->path.'images/templates.svg'),

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
								url_GetFromFilePath($this->path.'images/settings.svg'),

								window_Open( // on click open window
											'contact_form_settings',
											400,
											$this->getLanguageConstant('title_settings'),
											true, true,
											backend_UrlMake($this->name, 'settings_show')
										),
								$level=5
							));	
			$contact_menu->addSeparator(5);
			$contact_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_submissions'),
								url_GetFromFilePath($this->path.'images/submissions.svg'),

								window_Open( // on click open window
											'contact_form_submissions',
											650,
											$this->getLanguageConstant('title_submissions'),
											true, true,
											backend_UrlMake($this->name, 'submissions')
										),
								$level=5
							));

			$backend->addMenu($this->name, $contact_menu);

			// add backend support script
			$head_tag = head_tag::getInstance();
			$head_tag->addTag('script',
						array(
							'src' 	=> url_GetFromFilePath($this->path.'include/backend.js'),
							'type'	=> 'text/javascript'
						));
			$head_tag->addTag('link',
						array(
							'href'	=> url_GetFromFilePath($this->path.'include/backend.css'),
							'rel'	=> 'stylesheet',
							'type'	=> 'text/css'
						));
		}		

		if (class_exists('collection') && $section != 'backend') {
			$collection = collection::getInstance();
			$collection->includeScript(collection::DIALOG);
			$collection->includeScript(collection::COMMUNICATOR);
		}

		if (class_exists('head_tag') && $section != 'backend') {
			$head_tag = head_tag::getInstance();

			$head_tag->addTag('script',
						array(
							'src' 	=> url_GetFromFilePath($this->path.'include/contact_form.js'),
							'type'	=> 'text/javascript'
						));
			$head_tag->addTag('link',
						array(
							'href'	=> url_GetFromFilePath($this->path.'include/contact_form.css'),
							'rel'	=> 'stylesheet',
							'type'	=> 'text/css'
						));
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

				case 'submit':
					$this->submitForm($params, $children);
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

				case 'submissions':
					$this->showSubmissions();
					break;

				case 'submission_details':
					$this->showSubmissionDetails();
					break;

				case 'export_submissions':
					$this->showExportOptions();
					break;

				case 'export_submissions_commit':
					$this->exportSubmissions();
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
		$this->saveSetting('smtp_server', 'smtp.gmail.com');
		$this->saveSetting('smtp_port', '465');
		$this->saveSetting('use_ssl', 1);
		$this->saveSetting('save_copy', 0);
		$this->saveSetting('save_location', '');

		// templates table
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

		// contact form table
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
				`use_ajax` boolean NOT NULL DEFAULT '1',
				`show_submit` boolean NOT NULL DEFAULT '1',
				`show_reset` boolean NOT NULL DEFAULT '1',
				`show_cancel` boolean NOT NULL DEFAULT '0',
				PRIMARY KEY(`id`),
				INDEX `contact_forms_by_text_id` (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// table for storing contact form fields
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

		// form submissions table
		$sql = "
			CREATE TABLE `contact_form_submission` (
				`id` int NOT NULL AUTO_INCREMENT,
				`form` int NOT NULL,
				`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`address` varchar(45) NOT NULL,
				PRIMARY KEY(`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// form submission fields table
		$sql = "
			CREATE TABLE `contact_form_submission_fields` (
				`id` int NOT NULL AUTO_INCREMENT,
				`submission` int NOT NULL,
				`field` int NULL,
				`value` text NOT NULL,
				PRIMARY KEY(`id`),
				INDEX `contact_form_submissions` (`submission`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array(
			'contact_form_templates', 'contact_forms', 'contact_form_fields',
			'contact_form_submissions', 'contact_form_submission_fields'
		);
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
	 * Submit form.
	 *
	 * @param array $tag_params
	 * @param array $children
	 * @return boolean
	 */
	private function submitForm($tag_params, $children) {
		$id = isset($_REQUEST['form_id']) ? fix_id($_REQUEST['form_id']) : null;
		$result = false;

		// we need form id
		if (is_null($id))
			return;

		// get managers
		$manager = ContactForm_FormManager::getInstance();
		$field_manager = ContactForm_FormFieldManager::getInstance();
		$submission_manager = ContactForm_SubmissionManager::getInstance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::getInstance();

		// load form and fields
		$form = $manager->getSingleItem(
						$manager->getFieldNames(),
						array('id' => $id)
					);
		$fields = $field_manager->getItems(
						$field_manager->getFieldNames(),
						array('form' => $id)
					);

		// require both form and field
		if (!is_object($form) || !count($fields) > 0) {
			trigger_error('ContactForm: Unable to submit. Missing form or fields.', E_USER_WARNING);
			return;
		}

		// collect data
		$data = array();
		$replacement_fields = array();
		$attachments = array();
		$missing_fields = array();
		$messages = array();

		foreach ($fields as $field) {
			$name = $field->name;
			$value = '';

			// get field value
			if (isset($_REQUEST[$name]))
				$value = fix_chars($_REQUEST[$name]);

			// add field to missing fields list
			switch ($field->type) {
				case 'file':
					if ($_FILES[$name]['error'] == UPLOAD_ERR_OK) {
						$attachments[] = $_FILES[$name]['name'];

					} else if ($field->required) {
						$missing_fields[$name] = array(
												$field->label,
												$field->placeholder
											);
						$messages[] = $this->getLanguageConstant('message_upload_error');
					}
					break;

				case 'honey-pot':
					if (!empty($value)) {
						trigger_error('ContactFrom: Honey-pot field populated. Ignoring submission!', E_USER_NOTICE);
						return;
					}
					break;

				default:
					if (empty($value) && $field->required) {
						$missing_fields[$name] = array(
												$field->label,
												$field->placeholder
											);

						$message = $this->getLanguageConstant('message_missing_field');
						if (!in_array($message, $messages))
							$messages[] = $message;
					}

					$data[] = array(
							'field'	=> $field->id,
							'value'	=> $value
						);
					$replacement_fields[$name] = $value;
					break;
			}
		}

		// store and email
		if (count($missing_fields) == 0) {
			// store form submission
			$submission_manager->insertData(array(
					'form'		=> $form->id,
					'address'	=> $_SERVER['REMOTE_ADDR'],
				));
			$submission_id = $submission_manager->getInsertedID();

			// store data to database
			foreach($data as $field_data) {
				$new_data = array(
						'submission'	=> $submission_id,
						'field'			=> $field_data['field'],
						'value'			=> $field_data['value']
					);

				$submission_field_manager->insertData($new_data);
			}

			// TODO: Store files somewhere after submission, if needed.

			// get mailer
			$mailer = $this->getMailer();
			$sender = $this->getSender();
			$recipients = $this->getRecipients();
			$template = $this->getTemplate($form->template);

			// start creating message
			$mailer->start_message();
			$mailer->set_subject($template['subject']);
			$mailer->set_sender($sender['address'], $sender['name']);

			foreach ($recipients as $recipient)
				$mailer->add_recipient($recipient['address'], $recipient['name']);

			foreach ($attachments as $attachment)
				$mailer->add_attachment($attachment);

			$mailer->set_body($template['plain_body'], $template['html_body']);
			$mailer->set_variables($replacement_fields);

			// send email
			$result = $mailer->send();
		}

		// show result
		if (_AJAX_REQUEST) {
			// return JSON object as reponse
			$response = array(
					'error'				=> !$result,
					'messages'			=> $messages,
					'missing_fields'	=> $missing_fields
				);

			if ($result)
				$response['message'] = $this->getLanguageConstant('message_sent'); else
				$response['message'] = $this->getLanguageConstant('message_error');

			print json_encode($response);

		} else {
			// show response from template
			$template = $this->loadTemplate($tag_params, 'reponse.xml');

			$params = array(
					'error'				=> !$result,
					'messages'			=> $messages,
					'missing_fields'	=> $missing_fields
				);
			if ($result)
				$params['message'] = $this->getLanguageConstant('message_sent'); else
				$params['message'] = $this->getLanguageConstant('message_error');

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
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
	 * Show settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:mailer_list', $this, 'tag_MailerList');

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
		$use_ssl = isset($_REQUEST['use_ssl']) && ($_REQUEST['use_ssl'] == 'on' || $_REQUEST['use_ssl'] == '1') ? 1 : 0;
		$save_copy = isset($_REQUEST['save_copy']) && ($_REQUEST['save_copy'] == 'on' || $_REQUEST['save_copy'] == '1') ? 1 : 0;

		$params = array(
			'mailer', 'sender_name', 'sender_address', 'recipient_name', 'recipient_address',
			'smtp_server', 'smtp_port', 'smtp_authenticate', 'smtp_username', 'smtp_password',
			'save_location'
		);

		// save settings
		foreach($params as $param) {
			$value = fix_chars($_REQUEST[$param]);
			$this->saveSetting($param, $value);
		}

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
	 * Show sbumissions for specific form.
	 */
	private function showSubmissions() {
		// load template
		$template = new TemplateHandler('submissions_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$template->registerTagHandler('cms:form_list', $this, 'tag_FormList');
		$template->registerTagHandler('cms:field_list', $this, 'tag_FieldList');
		$template->registerTagHandler('cms:list', $this, 'tag_SubmissionList');

		// get variables
		$params = array();
		$form = isset($_REQUEST['form']) ? fix_id($_REQUEST['form']) : null;

		// export menu item
		if (!is_null($form)) {
			$export_url = url_Make(
				'transfer_control',
				_BACKEND_SECTION_,
				array('backend_action', 'export_submissions'),
				array('module', $this->name),
				array('form', $form)
			);

			$export_link = url_MakeHyperlink(
				$this->getLanguageConstant('menu_export'),
				window_Open(
					'contact_form_export',
					400,
					$this->getLanguageConstant('title_export'),
					true, false,
					$export_url
				)
			);

			$params['link_export'] = $export_link;
		}

		// parse template
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show details for submission.
	 */
	private function showSubmissionDetails() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_SubmissionManager::getInstance();

		// load submission details
		$item = $manager->getSingleItem(
				$manager->getFieldNames(),
				array('id' => $id)
			);

		// report error and return if specified submission doesn't exist
		if (!is_object($item)) {
			trigger_error("Contact form: Unable to show submission details for {$id}.", E_USER_NOTICE);
			return;
		}

		// load template
		$template = new TemplateHandler('submission_details.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:fields', $this, 'tag_SubmissionFields');

		// prepare parameters
		$params = array(
				'id'		=> $item->id,
				'form'		=> $item->form,
				'timestamp'	=> $item->timestamp,
				'address'	=> $item->address,
				'button_action'	=> window_Close('contact_form_submission_details'.$item->id)
			);

		// parse template
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show export configuration dialog.
	 */
	private function showExportOptions() {
		// load template
		$template = new TemplateHandler('export_options.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:fields', $this, 'tag_FieldList');

		// prepare options
		$form = fix_id($_REQUEST['form']);
		$params = array(
			'form'			=> $form,
			'filename'		=> 'export.csv',
			'form_action'	=> backend_UrlMake($this->name, 'export_submissions_commit'),
			'cancel_action'	=> window_Close('contact_form_export')
		);

		// parse template
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Generate file and start downloading.
	 */
	private function exportSubmissions() {
		global $language;

		// get managers
		$form_manager = ContactForm_FormManager::getInstance();
		$form_field_manager = ContactForm_FormFieldManager::getInstance();
		$submission_manager = ContactForm_SubmissionManager::getInstance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::getInstance();

		// get options
		$form_id = fix_id($_REQUEST['form']);
		$filename = empty($_REQUEST['filename']) ? 'export.csv' : fix_chars($_REQUEST['filename']);
		$include_headers = isset($_REQUEST['headers_included']) && ($_REQUEST['headers_included'] == 'on' || $_REQUEST['headers_included'] == '1') ? 1 : 0;
		$export_ip = isset($_REQUEST['export_ip']) && ($_REQUEST['export_ip'] == 'on' || $_REQUEST['export_ip'] == '1') ? 1 : 0;
		$export_timestamp = isset($_REQUEST['export_timestamp']) && ($_REQUEST['export_timestamp'] == 'on' || $_REQUEST['export_timestamp'] == '1') ? 1 : 0;

		switch (fix_id($_REQUEST['separator_type'])) {
			case 0:
				$separator = "\t";
				break;

			case 1:
				$separator = ";";
				break;

			case 2:
			default:
				$separator = ",";
				break;
		}

		// prepare for parsing data
		$data = array();
		$fields = array();
		$headers = array();

		// get fields
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 7) == 'include') {
				$include = ($value == 'on' || $value == '1') ? true : false;
				$field_name = substr($key, 8);

				if ($include)
					$fields[] = $field_name;

			} elseif (substr($key, 0, 6) == 'header') {
				$field_name = substr($key, 7);

				if (!empty($value))
					$headers[$field_name] = $value; else
					$headers[$field_name] = '';
			}
		}

		// populate missing header values
		$form_fields = $form_field_manager->getItems(
			$form_field_manager->getFieldNames(),
			array('form' => $form_id)
		);

		if (count($form_fields) > 0)
			foreach ($form_fields as $field) {
				if (!empty($headers[$field->name]))
					continue;

				// make header unacceptable
				$value = null;

				// try to get label
				if (!empty($field->label[$language]))
					$value = $field->label[$language];

				// try to get placeholder
				if (is_null($value) && !empty($field->placeholder[$language]))
					$value = $field->placeholder[$language];

				// finally just set header to field name
				if (is_null($value))
					$value = $field->name;

				$headers[$field->name] = $value;
			}

		// add headers to data array
		if ($include_headers) {
			$data[] = array();

			if ($export_ip)
				$data[0][] = $this->getLanguageConstant('header_ip_address');

			if ($export_timestamp)
				$data[0][] = $this->getLanguageConstant('header_timestamp');

			foreach ($headers as $field_name => $header)
				if (in_array($field_name, $fields))
					$data[0][] = $header;
		}

		// get related submissions
		$submissions = $submission_manager->getItems(
			$submission_manager->getFieldNames(),
			array('form' => $form_id)
		);

		if (count($submissions) > 0) {
			// get ids for all fields
			$field_ids = array();
			$form_fields = $form_field_manager->getItems(array('id'), array('name' => $fields));

			if (count($form_fields) > 0)
				foreach ($form_fields as $field)
					$field_ids[] = $field->id;
			
			// append submission fields to data array
			foreach ($submissions as $submission) {
				$record = array();
				$field_data = $submission_field_manager->getItems(
					$submission_field_manager->getFieldNames(),
					array(
						'submission'	=> $submission->id,
						'field'			=> $field_ids
					)
				);

				// add ip address
				if ($export_ip)
					$record[] = $submission->address;

				// add timestamp
				if ($export_timestamp) {
					$timestamp = strtotime($submission->timestamp);
					$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
					$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

					$record[] = $date.' '.$time;
				}

				// add remaining fields
				if (count($field_data) > 0)
					foreach ($field_data as $field)
						$record[] = $field->value;

				// add row to data array
				$data[] = $record;
			}
		}

		// generate raw file
		$raw_data = '';

		foreach ($data as $row) {
			$line = '"'.implode('"'.$separator.'"', $row).'"';
			$raw_data .= $line."\n";
		}

		define('_OMIT_STATS', 1);

		// send headers and data
    	header('Content-Type: text/csv; charset=utf-8');
    	header('Content-Disposition: attachment; filename="'.$filename.'"');
    	header('Content-Length: '.strlen($raw_data));
    	print $raw_data;
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
						'use_ajax'			=> $item->use_ajax,
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
				'use_ajax'		=> isset($_REQUEST['use_ajax']) && ($_REQUEST['use_ajax'] == 'on' || $_REQUEST['use_ajax'] == '1') ? 1 : 0,
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

		$skip_hidden = false;
		if (isset($tag_params['skip_hidden']))
			$skip_hidden = $tag_params['skip_hidden'] == 1;

		$count = 0;
		$limit = null;
		if (isset($tag_params['limit']))
			$limit = fix_id($tag_params['limit']);

		$order_by = array('id');
		if (isset($tag_params['order_by']))
			$order_by = explode(',', $tag_params['order_by']);

		$order_asc = true;
		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 1;

		// load template
		$template = $this->loadTemplate($tag_params, 'field.xml');

		// get fields
		$items = $manager->getItems($manager->getFieldNames(), $conditions, $order_by, $order_asc);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				// skip hidden fields
				if ($skip_hidden && in_array($item->type, $this->hidden_fields))
					continue;

				// respect limit
				$count++;
				if (!is_null($limit) && $count > $limit)
					break;

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
				'use_ajax'		=> $item->use_ajax,
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

		// get params
		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : 0;

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
					'use_ajax'		=> $item->use_ajax,
					'show_submit'	=> $item->show_submit,
					'show_reset'	=> $item->show_reset,
					'show_cancel'	=> $item->show_cancel,
					'selected'		=> $selected == $item->id,
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
	 * Handle rendering detailed submission.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Submission($tag_params, $children) {
		$field_manager = ContactForm_FormFieldManager::getInstance();
		$submission_manager = ContactForm_SubmissionManager::getInstance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::getInstance();
		$fields = array();
		$conditions = array();

		// get parameters
		$conditions['form'] = -1;
		if (isset($tag_params['form']))
			$conditions['form'] = fix_id($tag_params['form']);

		// load template
		$template = $this->loadTemplate($tag_params, 'submission.xml');
		$template->registerTagHandler('cms:fields', $this, 'tag_SubmissionFields');

		// get submission
		$item = $submission_manager->getSingleItem(
				$submission_manager->getFieldNames(),
				$conditions
			);

		// load field definitions
		if ($conditions['form'] != -1) {
			$field_definitions = $field_manager->getItems(
				$field_manager->getFieldNames(),
				array('form' => $conditions['form'])
			);

			if (count($field_definitions) > 0)
				foreach ($field_definitions as $field)
					$fields[$field->id] = $field;
		}

		// parse template
		if (is_object($item)) {
			// get submitted fields
			$submitted_data = $submission_field_manager->getItems(
					$submission_field_manager->getFieldNames(),
					array('submission' => $item->id)
				);

			$field_data = array();

			if (count($submitted_data) > 0)
				foreach ($submitted_data as $record)
					$field_data[] = array(
							'field'			=> $record->field,
							'value'			=> $record->value,
							'label'			=> $fields[$record->field]->label,
							'placeholder'	=> $fields[$record->field]->placeholder,
							'type'			=> $fields[$record->field]->type,
						);

			// prepare timestamps
			$timestamp = strtotime($item->timestamp);
			$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
			$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

			$params = array(
					'id'			=> $item->id,
					'form'			=> $item->form,
					'timestamp'		=> $item->timestamp,
					'time'			=> $time,
					'date'			=> $date,
					'address'		=> $item->address,
					'fields'		=> $field_data
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Handle rendering list of submissions.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_SubmissionList($tag_params, $children) {
		$field_manager = ContactForm_FormFieldManager::getInstance();
		$submission_manager = ContactForm_SubmissionManager::getInstance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::getInstance();
		$fields = array();
		$conditions = array();

		// get parameters
		$conditions['form'] = -1;
		if (isset($tag_params['form']))
			$conditions['form'] = fix_id($tag_params['form']);

		// load template
		$template = $this->loadTemplate($tag_params, 'submissions_list_item.xml');
		$template->registerTagHandler('cms:fields', $this, 'tag_SubmissionFields');

		// get submissions
		$items = $submission_manager->getItems(
				$submission_manager->getFieldNames(),
				$conditions
			);

		// load field definitions
		if ($conditions['form'] != -1) {
			$field_definitions = $field_manager->getItems(
				$field_manager->getFieldNames(),
				array('form' => $conditions['form'])
			);

			if (count($field_definitions) > 0)
				foreach ($field_definitions as $field)
					$fields[$field->id] = $field;
		}

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				// get submitted fields
				$submitted_data = $submission_field_manager->getItems(
						$submission_field_manager->getFieldNames(),
						array('submission' => $item->id)
					);

				$field_data = array();

				if (count($submitted_data) > 0)
					foreach ($submitted_data as $record)
						$field_data[] = array(
								'submission'	=> $record->submission,
								'field'			=> $record->field,
								'value'			=> $record->value,
								'label'			=> $fields[$record->field]->label,
								'placeholder'	=> $fields[$record->field]->placeholder,
								'type'			=> $fields[$record->field]->type,
							);

				// prepare timestamps
				$timestamp = strtotime($item->timestamp);
				$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
				$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

				$params = array(
						'id'			=> $item->id,
						'form'			=> $item->form,
						'timestamp'		=> $item->timestamp,
						'time'			=> $time,
						'date'			=> $date,
						'address'		=> $item->address,
						'fields'		=> $field_data,
						'item_details'	=> url_MakeHyperlink(
												$this->getLanguageConstant('details'),
												window_Open(
													'contact_form_submission_details'.$item->id, 	// window id
													400,				// width
													$this->getLanguageConstant('title_submission_details'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'submission_details'),
														array('id', $item->id)
													)
												)
											),
					);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Show submission data.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_SubmissionFields($tag_params, $children) {
		global $language;

		$conditions = array();
		$form_field_manager = ContactForm_FormFieldManager::getInstance();
		$submission_manager = ContactForm_SubmissionManager::getInstance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::getInstance();

		// get conditional parameters
		$submission_id = null;
		if (isset($tag_params['submission']))
			$submission_id = fix_id($tag_params['submission']);

		// we require submission to be specified
		if (is_null($submission_id)) {
			trigger_error('Submission fields tag: No submission id specified.', E_USER_NOTICE);
			return;
		}

		// get submission for specified id
		$submission = $submission_manager->getSingleItem(
				$submission_manager->getFieldNames(),
				array('id' => $submission_id)
			);

		if (!is_object($submission)) {
			trigger_error('Submission fields tag: Unknown submission.', E_USER_NOTICE);
			return;
		}

		// get form fields
		$raw_fields = $form_field_manager->getItems(
				$form_field_manager->getFieldNames(),
				array('form' => $submission->form)
			);

		$fields = array();
		foreach ($raw_fields as $field)
			$fields[$field->id] = $field;

		// load submission data
		$items = $submission_field_manager->getItems(
				$submission_field_manager->getFieldNames(),
				array('submission' => $submission->id)
			);

		// load template
		$template = $this->loadTemplate($tag_params, 'submission_field.xml');

		if (count($items) > 0)
			foreach ($items as $item) {
				$field = $fields[$item->field];
				$text = $field->name;

				if (!empty($field->placeholder[$language]))
					$text = $field->placeholder[$language];

				if (!empty($field->label[$language]))
					$text = $field->label[$language];

				$params = array(
					'submission'	=> $submission->id,
					'form'			=> $submission->form,
					'field'			=> $item->field,
					'value'			=> $item->value,
					'label'			=> $field->label,
					'placeholder'	=> $field->placeholder,
					'text'			=> $text,
					'type'			=> $field->type,
					'name'			=> $field->name
				);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Show list of mailers.
	 *
	 * @param array $tag_params
	 * @param array $childen
	 */
	public function tag_MailerList($tag_params, $children) {
		$template = $this->loadTemplate($tag_params, 'mailer_option.xml');

		foreach ($this->mailers as $name => $mailer) {
			$params = array(
				'name'		=> $name,
				'title'		=> $mailer->get_title(),
				'selected'	=> isset($this->settings['mailer']) && $this->settings['mailer'] == $name
			);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Register object for sending emails.
	 *
	 * @param string $name
	 * @param object $mailer
	 * @return boolean
	 */
	public function registerMailer($name, $mailer) {
		$result = false;

		// make sure name is unique
		if (isset($this->mailers[$name])) {
			trigger_error("Unable to register mailer '{$name}'. Name already taken.", E_USER_WARNING);
			return $result;
		}

		$this->mailers[$name] = $mailer;
	}

	/**
	 * Get selected mailer object.
	 *
	 * @return object
	 */
	public function getMailer() {
		$result = null;
		$name = isset($this->settings['mailer']) ? $this->settings['mailer'] : null;
		
		if (isset($this->mailers[$name]))
			$result = $this->mailers[$name]; else
			$result = $this->mailers[0];

		return $result;
	}

	/**
	 * Get default sender.
	 *
	 * @return array
	 */
	public function getSender() {
		$result = array(
				'name'		=> $this->settings['sender_name'],
				'address'	=> $this->settings['sender_address']
			);

		return $result;
	}

	/**
	 * Get default recipient.
	 *
	 * @return array
	 */
	public function getRecipient() {
		$name = explode(',', $this->settings['recipient_name']);
		$address = explode(',', $this->settings['recipient_address']);

		$result = array(
				'name'		=> $name[0],
				'address'	=> $address[0]
			);

		return $result;
	}

	/**
	 * Get list of all recipients.
	 *
	 * @return array
	 */
	public function getRecipients() {
		$result = array();
		$names = explode(',', $this->settings['recipient_name']);
		$addresses = explode(',', $this->settings['recipient_address']);

		for ($i=0; $i<count($addresses); $i++)
			$result[] = array(
					'name'		=> isset($names[$i]) ? $names[$i] : '',
					'address'	=> $addresses[$i]
				);

		return $result;
	}

	/**
	 * Get raw template.
	 *
	 * @return array
	 */
	public function getTemplate($name) {
		global $language;

		$result = null;
		$manager = ContactForm_TemplateManager::getInstance();

		// get template
		$template = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $name));

		if (is_object($template))
			$result = array(
					'plain_body'	=> $template->plain[$language],
					'html_body'		=> $template->html[$language],
					'subject'		=> $template->subject[$language]
				);

		return $result;
	}
}
