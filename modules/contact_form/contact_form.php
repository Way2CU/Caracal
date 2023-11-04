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
use Core\Markdown;
use Core\CORS\Manager as CORS;

require_once('units/form_manager.php');
require_once('units/form_field_manager.php');
require_once('units/field_value_manager.php');
require_once('units/fieldset_manager.php');
require_once('units/fieldset_field_manager.php');
require_once('units/template_manager.php');
require_once('units/submission_manager.php');
require_once('units/submission_field_manager.php');
require_once('units/domain_manager.php');
require_once('units/mailer_manager.php');
require_once('units/mailer.php');
require_once('units/system_mailer.php');
require_once('units/smtp_mailer.php');


class contact_form extends Module {
	private static $_instance;

	private $mailers = array();
	private $field_types = array(
					'text', 'email', 'textarea', 'select', 'hidden', 'checkbox', 'radio',
					'password', 'file', 'color', 'date', 'month', 'datetime', 'datetime-local',
					'time', 'week', 'url', 'number', 'range', 'honey-pot', 'transfer-param',
					'site-version', 'button'
				);
	private $hidden_fields = array('hidden', 'honey-pot');
	private $virtual_fields = array('transfer-param', 'site-version');
	private $foreign_fields = array();

	private $form_templates = array(
					'empty'		=> null,
					'default'	=> array(
						'name'	=> array(
							'type'			=> 'text',
							'required'		=> true,
							'autocomplete'	=> true
						),
						'email'	=> array(
							'type'			=> 'email',
							'required'		=> true,
							'autocomplete'	=> true
						),
						'phone'	=> array(
							'type'			=> 'text',
							'required'		=> true,
							'autocomplete'	=> true,
							'pattern'		=> '\+?[\d\s]+'
						),
						'verify_email' => array(
							'type'			=> 'honey-pot'
						)
					)
				);
	private $form_template_names = array();

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// configure cross-domain resource sharing
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS' && isset($_REQUEST['form_id'])) {
			$form_id = isset($_REQUEST['form_id']) ? fix_id($_REQUEST['form_id']) : null;

			// get list of domains for specified form
			$domain_manager = ContactForm_DomainManager::get_instance();
			$raw_list = $domain_manager->get_instance(array('domain'), array('form' => $id));

			if (count($raw_list) > 0)
				foreach ($raw_list as $record) {
					$domain = CORS::add_domain($record->domain);
					CORS::allow_methods($domain, array('POST'));
					CORS::allow_headers($domain, array('Content-Type', 'X-Requested-With'));
				}
		}

		// register events
		Events::register($this->name, 'email-sent', 4);  // params: mailer, recipient, subject, data
		Events::register($this->name, 'submitted', 4);  // params: sender, recipients, template, data

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

		$smtp_mailer->set_credentials(
					$this->settings['smtp_username'],
					$this->settings['smtp_password']
				);

		// connect events
		Events::connect('head-tag', 'before-print', 'add_tags', $this);
		Events::connect('backend', 'add-tags', 'add_backend_tags', $this);
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);

		// get localized template names
		if ($section == 'backend_module')
			foreach ($this->form_templates as $name => $fields)
				$this->form_template_names[$name] = $this->get_language_constant('form_'.$name);

		// collect transfer params
		$this->collectTransferParams();
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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
	public function transfer_control($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->tag_Form($params, $children);
					break;

				case 'submit':
					$this->submitForm($params, $children);
					break;

				case 'amend_submission':
					$this->amendSubmission($params, $children);
					break;

				case 'json_form_list':
					$this->json_form_list();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
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

				case 'settings_show':
					$this->showSettings();
					break;

				case 'settings_save':
					$this->save_settings();
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

				case 'fieldsets':
					$this->manageFieldsets();
					break;

				case 'fieldsets_add':
					$this->addFieldset();
					break;

				case 'fieldsets_edit':
					$this->editFieldset();
					break;

				case 'fieldsets_save':
					$this->saveFieldset();
					break;

				case 'fieldsets_delete':
					$this->deleteFieldset();
					break;

				case 'fieldsets_delete_commit':
					$this->deleteFieldset_Commit();
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

				case 'values_manage':
					$this->manageValues();
					break;

				case 'values_add':
					$this->addValue();
					break;

				case 'values_edit':
					$this->editValue();
					break;

				case 'values_save':
					$this->saveValue();
					break;

				case 'values_import':
					$this->importValues();
					break;

				case 'values_import_commit':
					$this->importValues_Commit();
					break;

				case 'values_delete':
					$this->deleteValue();
					break;

				case 'values_delete_commit':
					$this->deleteValue_Commit();
					break;

				case 'json_settings':
					$this->json_settings();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
		global $db;

		// predefined settings stored in system wide tables
		$this->save_setting('sender_name', '');
		$this->save_setting('sender_address', 'sample@email.com');
		$this->save_setting('recipient_name', '');
		$this->save_setting('recipient_address', 'sample@email.com');
		$this->save_setting('smtp_server', 'smtp.gmail.com');
		$this->save_setting('smtp_port', '465');
		$this->save_setting('smtp_username', '');
		$this->save_setting('smtp_password', '');
		$this->save_setting('use_ssl', 1);
		$this->save_setting('mailer', '');

		// create tables
		$file_list = array(
				'templates.sql', 'forms.sql', 'mailers.sql', 'domains.sql', 'fieldsets.sql',
				'fieldset_fields.sql', 'fields.sql', 'field_values.sql', 'submissions.sql',
				'submission_fields.sql'
			);

		foreach ($file_list as $file_name) {
			$sql = Query::load_file($file_name, $this);
			$db->query($sql);
		}
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$tables = array(
			'contact_form_templates', 'contact_forms', 'contact_form_fields',
			'contact_form_submissions', 'contact_form_submission_fields', 'contact_form_field_values',
			'contact_form_domains', 'contact_form_fieldsets', 'contact_form_fieldset_fields',
			'contact_form_mailers'
		);
		$db->drop_tables($tables);
	}

	/**
	 * Add frontend tags.
	 */
	public function add_tags() {
		// include scripts from collection
		if (ModuleHandler::is_loaded('collection')) {
			$collection = collection::get_instance();
			$collection->includeScript(collection::DIALOG);
			$collection->includeScript(collection::COMMUNICATOR);
		}

		// add frontend scripts of our own
		$head_tag = head_tag::get_instance();
		$head_tag->add_tag('script',
					array(
						'src'  => URL::from_file_path($this->path.'include/contact_form.js'),
						'type' => 'text/javascript'
					));
		$head_tag->add_tag('link',
					array(
						'href' => URL::from_file_path($this->path.'include/contact_form.css'),
						'rel'  => 'stylesheet',
						'type' => 'text/css'
					));
	}

	/**
	 * Include tags needed for backend.
	 */
	public function add_backend_tags() {
			$head_tag = head_tag::get_instance();
			$head_tag->add_tag('script',
						array(
							'src' 	=> URL::from_file_path($this->path.'include/backend.js'),
							'type'	=> 'text/javascript'
						));
			$head_tag->add_tag('link',
						array(
							'href'	=> URL::from_file_path($this->path.'include/backend.css'),
							'rel'	=> 'stylesheet',
							'type'	=> 'text/css'
						));
	}

	/**
	 * Add items to backend menu.
	 */
	public function add_menu_items() {
		$backend = backend::get_instance();

		// create menu
		$contact_menu = new backend_MenuItem(
				$this->get_language_constant('menu_contact'),
				$this->path.'images/icon.svg',
				'javascript:void(0);',
				$level=5
			);

		$contact_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_manage_forms'),
							$this->path.'images/forms.svg',

							window_Open( // on click open window
										'contact_forms',
										750,
										$this->get_language_constant('title_forms_manage'),
										true, true,
										backend_UrlMake($this->name, 'forms_manage')
									),
							$level=5
						));
		$contact_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_manage_templates'),
							$this->path.'images/templates.svg',

							window_Open( // on click open window
										'contact_form_templates',
										550,
										$this->get_language_constant('title_templates_manage'),
										true, true,
										backend_UrlMake($this->name, 'templates_manage')
									),
							$level=5
						));
		$contact_menu->addSeparator(6);
		$contact_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_settings'),
							$this->path.'images/settings.svg',

							window_Open( // on click open window
										'contact_form_settings',
										400,
										$this->get_language_constant('title_settings'),
										true, true,
										backend_UrlMake($this->name, 'settings_show')
									),
							$level=6
						));
		$contact_menu->addSeparator(5);
		$contact_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_submissions'),
							$this->path.'images/submissions.svg',

							window_Open( // on click open window
										'contact_form_submissions',
										750,
										$this->get_language_constant('title_submissions'),
										true, true,
										backend_UrlMake($this->name, 'submissions')
									),
							$level=5
						));

		$backend->addMenu($this->name, $contact_menu);

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
	 * Collect all the params to be transfered to form.
	 */
	private function collectTransferParams() {
		$params = isset($_SESSION['contact_form_transfer_params']) ? $_SESSION['contact_form_transfer_params'] : array();
		$field_manager = ContactForm_FormFieldManager::get_instance();

		// get all transfer fields
		$fields = $field_manager->get_items(
				$field_manager->get_field_names(),
				array('type' => 'transfer-param')
			);

		// collect new data
		if (count($fields) > 0)
			foreach	($fields as $field) {
				// skip fields that are not in request parameters
				if (!isset($_REQUEST[$field->name]) || empty($_REQUEST[$field->name]))
					continue;

				// store parameter value
				$value = fix_chars($_REQUEST[$field->name]);
				$params[$field->name] = $value;
			}

		// store array to session
		$_SESSION['contact_form_transfer_params'] = $params;
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
			return $result;

		// get managers
		$manager = ContactForm_FormManager::get_instance();
		$field_manager = ContactForm_FormFieldManager::get_instance();
		$submission_manager = ContactForm_SubmissionManager::get_instance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::get_instance();

		// load form and fields
		$form = $manager->get_single_item(
						$manager->get_field_names(),
						array('id' => $id)
					);
		$fields = $field_manager->get_items(
						$field_manager->get_field_names(),
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
		$reply_to_address = null;
		$transfer_params = isset($_SESSION['contact_form_transfer_params']) ? $_SESSION['contact_form_transfer_params'] : array();

		foreach ($fields as $field) {
			$name = $field->name;
			$value = '';

			// get field value
			if (isset($_REQUEST[$name]))
				$value = fix_chars($_REQUEST[$name]);

			// add field to missing fields list
			switch ($field->type) {
				case 'file':
					if (isset($_FILES[$name]) && $_FILES[$name]['error'] == UPLOAD_ERR_OK) {
						$file_name = $_FILES[$name]['name'];
						$tmp_name = $_FILES[$name]['tmp_name'];
						$attachments[$file_name] = $tmp_name;

					} else if ($field->required) {
						$missing_fields[$name] = array(
												$field->label,
												$field->placeholder
											);
						$messages[] = $this->get_language_constant('message_upload_error');
					}
					break;

				case 'honey-pot':
					if (!empty($value)) {
						trigger_error('ContactFrom: Honey-pot field populated. Ignoring submission!', E_USER_NOTICE);
						return;
					}
					break;

				case 'transfer-param':
					if (isset($transfer_params[$field->name]))
						$value = $transfer_params[$field->name]; else
						$value = $field->value;

					// prepare data for insertion
					$data[] = array(
							'field'	=> $field->id,
							'value'	=> $value
						);
					$replacement_fields[$name] = $value;
					break;

				case 'site-version':
					// default computer parsable values
					$value = _DESKTOP_VERSION ? 'desktop' : 'mobile';

					// replace values with language specific
					if (empty($field->value) || $field->value == 0)
						$value = $this->get_language_constant('field_value_'.$value);

					// prepare data for insertion
					$data[] = array(
							'field'	=> $field->id,
							'value'	=> $value
						);
					$replacement_fields[$name] = $value;
					break;

				default:
					if (empty($value) && $field->required) {
						$missing_fields[$name] = array(
												$field->label,
												$field->placeholder
											);

						$message = $this->get_language_constant('message_missing_field');
						if (!in_array($message, $messages))
							$messages[] = $message;
					}

					// prepare data for insertion
					$data[] = array(
							'field'	=> $field->id,
							'value'	=> $value
						);

					// assign replacment key
					$replacement_fields[$name] = $value;

					// store reply-to value
					if ($form->include_reply_to == 1 && $form->reply_to_field == $field->id)
						$reply_to_address = $value;

					break;
			}
		}

		// store and email
		if (count($missing_fields) == 0) {
			// store form submission
			$submission_manager->insert_item(array(
					'form'		=> $form->id,
					'address'	=> $_SERVER['REMOTE_ADDR'],
				));
			$submission_id = $submission_manager->get_inserted_id();

			// store data to database
			foreach($data as $field_data) {
				$new_data = array(
						'submission'	=> $submission_id,
						'field'			=> $field_data['field'],
						'value'			=> $field_data['value']
					);

				$submission_field_manager->insert_item($new_data);
			}

			// TODO: Store files somewhere after submission, if needed.

			// get mailer
			$mailers = $this->getMailers($form->id);
			$sender = $this->getSender();
			$recipients = $this->getRecipients();
			$template = $this->getTemplate($form->template);

			// start creating message
			$result = true;
			foreach ($mailers as $mailer_name => $mailer) {
				$mailer->start_message();
				$mailer->set_subject($template['subject']);
				$mailer->set_sender($sender['address'], $sender['name']);

				// set reply address if specified
				if (!is_null($reply_to_address))
					$mailer->add_header_string('Reply-To', $reply_to_address);

				foreach ($recipients as $recipient)
					$mailer->add_recipient($recipient['address'], $recipient['name']);

				foreach ($attachments as $file_name => $tmp_name)
					$mailer->attach_file($tmp_name, $file_name);

				$mailer->set_body($template['plain_body'], Markdown::parse($template['html_body']));
				$mailer->set_variables($replacement_fields);

				// send email
				$send_result = $mailer->send();
				$result &= $send_result;

				// report error with mailer in case it failed
				if (!$send_result)
					trigger_error('Form submission failed with "'.$mailer_name.'".', E_USER_WARNING); else
					Events::trigger($this->name, 'submitted', $sender, $recipients, $template, $replacement_fields);
			}
		}

		// get messages
		$message_sent = Language::get_text('message_sent');
		if (empty($message_sent))
			$message_sent = $this->get_language_constant('message_sent');

		$message_form_error = Language::get_text('message_form_error');
		if (empty($message_form_error))
			$message_form_error = $this->get_language_constant('message_form_error');

		// show result
		if (_AJAX_REQUEST) {
			// return JSON object as reponse
			$response = array(
					'error'				=> !$result,
					'messages'			=> $messages,
					'missing_fields'	=> $missing_fields
				);

			if ($result)
				$response['message'] = $message_sent; else
				$response['message'] = $message_form_error;

			print json_encode($response);

		} else {
			// show response from template
			$template = $this->load_template($tag_params, 'reponse.xml');
			$template->set_template_params_from_array($children);

			$params = array(
					'error'				=> !$result,
					'messages'			=> $messages,
					'missing_fields'	=> $missing_fields
				);

			if ($result)
				$params['message'] = $message_sent; else
				$params['message'] = $message_form_error;

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}

		return $result;
	}

	/**
	 * Modify previous submission based on one or more references. This function takes
	 * parameters through children tags.
	 */
	public function amendSubmission($tag_params, $children) {
		$submission_manager = ContactForm_SubmissionManager::get_instance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::get_instance();
		$field_manager = ContactForm_FormFieldManager::get_instance();

		$data = array();
		$fields = array();
		$field_ids = array();
		$id_list = array();
		$conditions = array();
		$form_id = null;
		$text_id = null;

		// make sure we have form to work with
		if (isset($tag_params['id']))
			$form_id = fix_id($tag_params['id']);

		if (isset($tag_params['text_id']))
			$text_id = fix_chars($tag_params['text_id']);

		if (is_null($form_id) && is_null($text_id))
			return;

		// parse children
		foreach ($children as $child) {
			switch ($child->tagName) {
				case 'param':
					$param_name = fix_chars($child->tagAttrs['name']);
					$field_name = fix_chars($child->tagAttrs['field']);

					if ($child->tagAttrs['type'] == 'request') {
						$field_value = fix_chars($_REQUEST[$param_name]);
					}

					$fields[$field_name] = $field_value;
					break;

				case 'set':
					$field_name = fix_chars($child->tagAttrs['field']);
					$data[$field_name] = fix_chars($child->tagAttrs['value']);
					break;
			}
		}

		// get field ids
		$raw_fields = $field_manager->get_items(
				array('id', 'name'),
				array('name' => array_keys($fields))
			);

		foreach ($raw_fields as $field)
			$field_ids[$field->name] = $field->id;

		// get all the submissions for current IP address
		$conditions['address'] = $_SERVER['REMOTE_ADDR'];

		if (!is_null($text_id))
			$conditions['text_id'] = $text_id;

		if (!is_null($form_id))
			$conditions['form'] = $form_id;

		$score = array();
		$submissions = $submission_manager->get_items(array('id'), $conditions);

		if (count($submissions) > 0)
			foreach ($submissions as $submission) {
				$id_list[] = $submission->id;
				$score[$submission->id] = 0;
			}

		// collect all the matching data
		foreach ($fields as $name => $value) {
			$conditions = array(
				'submission'	=> $id_list,
				'field'			=> $field_ids[$name],
				'value'			=> $value
			);

			$data_list = $submission_field_manager->get_items(array('id', 'submission'), $conditions);

			if (count($data_list) > 0)
				$score[$data_list->submission]++;
		}

		// get the highest rated submission
		rsort($score);
		$submission_id = reset(array_keys($score));

		// update submission
		foreach ($data as $name => $value) {
			$submission_field_manager->update_items(
					array(
						'submission'	=> $submission_id,
						'field'			=> $field_ids[$name]
					),
					array(
						'value'			=> $value
					)
				);
		}
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
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:mailer_list', $this, 'tag_MailerList');

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
					);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function save_settings() {
		$params = array(
			'mailer', 'sender_name', 'sender_address', 'recipient_name', 'recipient_address',
			'smtp_server', 'smtp_port', 'smtp_authenticate', 'smtp_username', 'smtp_password'
		);
		$saved_params = array();

		foreach($params as $param) {
			if (!isset($_REQUEST[$param]))
				continue;

			$value = fix_chars($_REQUEST[$param]);
			$this->save_setting($param, $value);
			$saved_params []= $param;
		}

		if (isset($_REQUEST['use_ssl'])) {
			$use_ssl = $this->get_boolean_field('use_ssl') ? 1 : 0;
			$this->save_setting('use_ssl', $use_ssl);
			$saved_params []= 'use_ssl';
		}

		// show message
		if (_AJAX_REQUEST) {
			$result = array(
					'success'      => count($saved_params) > 0,
					'saved_params' => $params
				);

			header('Content-Type: json/application');
			print json_encode($result);

		} else {
			$template = new TemplateHandler('message.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'message'	=> $this->get_language_constant('message_saved'),
						'button'	=> $this->get_language_constant('close'),
						'action'	=> window_Close('contact_form_settings')
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Show sbumissions for specific form.
	 */
	private function showSubmissions() {
		// load template
		$template = new TemplateHandler('submissions_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$template->register_tag_handler('cms:form_list', $this, 'tag_FormList');
		$template->register_tag_handler('cms:field_list', $this, 'tag_FieldList');
		$template->register_tag_handler('cms:list', $this, 'tag_SubmissionList');

		// get variables
		$params = array();
		$form = isset($_REQUEST['form']) ? fix_id($_REQUEST['form']) : null;

		// export menu item
		if (!is_null($form)) {
			$export_url = URL::make_query(
				_BACKEND_SECTION_,
				'transfer_control',
				array('backend_action', 'export_submissions'),
				array('module', $this->name),
				array('form', $form)
			);

			$export_link = URL::make_hyperlink(
				$this->get_language_constant('menu_export'),
				window_Open(
					'contact_form_export',
					400,
					$this->get_language_constant('title_export'),
					true, false,
					$export_url
				)
			);

			$params['link_export'] = $export_link;
		}

		// parse template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show details for submission.
	 */
	private function showSubmissionDetails() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_SubmissionManager::get_instance();

		// load submission details
		$item = $manager->get_single_item(
				$manager->get_field_names(),
				array('id' => $id)
			);

		// report error and return if specified submission doesn't exist
		if (!is_object($item)) {
			trigger_error("Contact form: Unable to show submission details for {$id}.", E_USER_NOTICE);
			return;
		}

		// load template
		$template = new TemplateHandler('submission_details.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:fields', $this, 'tag_SubmissionFields');

		// prepare parameters
		$params = array(
				'id'		=> $item->id,
				'form'		=> $item->form,
				'timestamp'	=> $item->timestamp,
				'address'	=> $item->address,
				'button_action'	=> window_Close('contact_form_submission_details'.$item->id)
			);

		// parse template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show export configuration dialog.
	 */
	private function showExportOptions() {
		// load template
		$template = new TemplateHandler('export_options.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:fields', $this, 'tag_FieldList');

		// prepare options
		$form = fix_id($_REQUEST['form']);
		$params = array(
			'form'			=> $form,
			'filename'		=> 'export.csv',
			'form_action'	=> backend_UrlMake($this->name, 'export_submissions_commit'),
		);

		// parse template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Generate file and start downloading.
	 */
	private function exportSubmissions() {
		global $language;

		// get managers
		$form_manager = ContactForm_FormManager::get_instance();
		$form_field_manager = ContactForm_FormFieldManager::get_instance();
		$submission_manager = ContactForm_SubmissionManager::get_instance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::get_instance();

		// get options
		$form_id = fix_id($_REQUEST['form']);
		$filename = empty($_REQUEST['filename']) ? 'export.csv' : fix_chars($_REQUEST['filename']);
		$include_headers = $this->get_boolean_field('headers_included') ? 1 : 0;
		$export_ip = $this->get_boolean_field('export_ip') ? 1 : 0;
		$export_timestamp = $this->get_boolean_field('export_timestamp') ? 1 : 0;

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
		$form_fields = $form_field_manager->get_items(
			$form_field_manager->get_field_names(),
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
				$data[0][] = $this->get_language_constant('header_ip_address');

			if ($export_timestamp)
				$data[0][] = $this->get_language_constant('header_timestamp');

			foreach ($headers as $field_name => $header)
				if (in_array($field_name, $fields))
					$data[0][] = $header;
		}

		// get related submissions
		$submissions = $submission_manager->get_items(
			$submission_manager->get_field_names(),
			array('form' => $form_id)
		);

		if (count($submissions) > 0) {
			// get ids for all fields
			$field_ids = array();
			$form_fields = $form_field_manager->get_items(array('id'), array('name' => $fields));

			if (count($form_fields) > 0)
				foreach ($form_fields as $field)
					$field_ids[] = $field->id;

			// append submission fields to data array
			foreach ($submissions as $submission) {
				$record = array();
				$field_data = $submission_field_manager->get_items(
					$submission_field_manager->get_field_names(),
					array(
						'submission'	=> $submission->id,
						'field'			=> $field_ids
					),
					array('field'), true  // order by
				);

				// add ip address
				if ($export_ip)
					$record[] = $submission->address;

				// add timestamp
				if ($export_timestamp) {
					$timestamp = strtotime($submission->timestamp);
					$date = date($this->get_language_constant('format_date_short'), $timestamp);
					$time = date($this->get_language_constant('format_time_short'), $timestamp);

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
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->get_language_constant('new'),
										'contact_form_templates_add', 650,
										$this->get_language_constant('title_templates_add'),
										true, false,
										$this->name,
										'templates_add'
									),
				);

		$template->register_tag_handler('cms:list', $this, 'tag_TemplateList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for new template.
	 */
	private function addTemplate() {
		$template = new TemplateHandler('templates_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'templates_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for editing email template.
	 */
	private function editTemplate() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_TemplateManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('templates_change.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'id'					=> $item->id,
						'text_id'				=> $item->text_id,
						'name'					=> $item->name,
						'subject'				=> $item->subject,
						'plain_text_content'	=> $item->plain,
						'html_content'			=> $item->html,
						'form_action'  			=> backend_UrlMake($this->name, 'templates_save'),
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed template data.
	 */
	private function saveTemplate() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = fix_chars($_REQUEST['text_id']);
		$name = $this->get_multilanguage_field('name');
		$subject = $this->get_multilanguage_field('subject');
		$plain_text = $this->get_multilanguage_field('plain_text_content');
		$html = $this->get_multilanguage_field('html_content');

		$manager = ContactForm_TemplateManager::get_instance();
		$data = array(
				'text_id'	=> $text_id,
				'name'		=> $name,
				'subject'	=> $subject,
				'plain'		=> $plain_text,
				'html'		=> $html
			);

		if (is_null($id)) {
			$window = 'contact_form_templates_add';
			$manager->insert_item($data);
		} else {
			$window = 'contact_form_templates_edit';
			$manager->update_items($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_template_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('contact_form_templates'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation form for deleting template.
	 */
	private function deleteTemplate() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_TemplateManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_template_delete"),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'contact_form_templates_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'templates_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('contact_form_templates_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Delete template.
	 */
	private function deleteTemplate_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_TemplateManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_template_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('contact_form_templates_delete').';'.window_ReloadContent('contact_form_templates')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show management window for forms.
	 */
	private function manageForms() {
		$template = new TemplateHandler('forms_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->get_language_constant('new'),
										'contact_forms_add', 430,
										$this->get_language_constant('title_forms_add'),
										true, false,
										$this->name,
										'forms_add'
									),
				);

		$template->register_tag_handler('cms:list', $this, 'tag_FormList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show new contact form dialog.
	 */
	private function addForm() {
		$template = new TemplateHandler('forms_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'forms_save'),
				);

		$template->register_tag_handler('cms:template_list', $this, 'tag_TemplateList');
		$template->register_tag_handler('cms:field_template_list', $this, 'tag_FormTemplateList');
		$template->register_tag_handler('cms:mailer_list', $this, 'tag_MailerList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
 	 * Show change form dialog.
	 */
	private function editForm() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('forms_change.xml', $this->path.'templates/');
			$template->register_tag_handler('cms:domain_list', $this, 'tag_DomainList');
			$template->register_tag_handler('cms:mailer_list', $this, 'tag_MailerList');
			$template->set_mapped_module($this->name);

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
						'include_reply_to'	=> $item->include_reply_to,
						'reply_to_field'	=> $item->reply_to_field,
						'form_action'  		=> backend_UrlMake($this->name, 'forms_save'),
					);

			$template->register_tag_handler('cms:template_list', $this, 'tag_TemplateList');
			$template->register_tag_handler('cms:field_list', $this, 'tag_FieldList');

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save new of changed form data.
	 */
	private function saveForm() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$fields_template = isset($_REQUEST['fields_template']) ? fix_chars($_REQUEST['fields_template']) : null;
		$manager = ContactForm_FormManager::get_instance();

		$data = array(
				'text_id'		=> fix_chars($_REQUEST['text_id']),
				'name'			=> $this->get_multilanguage_field('name'),
				'action'		=> escape_chars($_REQUEST['action']),
				'template'		=> fix_chars($_REQUEST['template']),
				'use_ajax'		=>$this->get_boolean_field('use_ajax') ? 1 : 0,
				'show_submit'	=>$this->get_boolean_field('show_submit') ? 1 : 0,
				'show_reset'	=>$this->get_boolean_field('show_reset') ? 1 : 0,
				'show_cancel'	=>$this->get_boolean_field('show_cancel') ? 1 : 0
			);

		if (isset($_REQUEST['reply_to_field'])) {
			$data['reply_to_field'] = fix_id($_REQUEST['reply_to_field']);
			$data['include_reply_to'] = $this->get_boolean_field('include_reply_to') ? 1 : 0;
		}

		// insert or update data in database
		if (is_null($id)) {
			$window = 'contact_forms_add';
			$manager->insert_item($data);
			$id = $manager->get_inserted_id();

		} else {
			$window = 'contact_forms_edit';
			$manager->update_items($data,	array('id' => $id));
		}

		// create fields if needed
		if (!is_null($fields_template) && array_key_exists($fields_template, $this->form_templates)) {
			$field_manager = ContactForm_FormFieldManager::get_instance();
			$field_list = $this->form_templates[$fields_template];

			if (count($field_list) > 0)
				foreach ($field_list as $name => $field_data) {
					$field_manager->insert_item(array(
						'form'		=> $id,
						'name'		=> $name,
						'type'		=> isset($field_data['type']) ? $field_data['type'] : 'text',
						'required'	=> isset($field_data['required']) ? $field_data['required'] : 0,
						'autocomplete'	=> isset($field_data['autocomplete']) ? $field_data['autocomplete'] : 0,
						'pattern'	=> isset($field_data['pattern']) ? $field_data['pattern'] : '',
					));
				}
		}

		// gather domains in to list
		$domain_list = array();
		$domain_manager = ContactForm_DomainManager::get_instance();

		foreach ($_REQUEST as $key => $value) {
			if (strpos($key, 'domain_') === 0)
				$domain_list[] = $value;
		}

		// remove existing domains from database
		$domain_manager->delete_items(array('form' => $id));

		// insert all domains from list
		if (count($domain_list) > 0)
			foreach ($domain_list as $domain)
				$domain_manager->insert_item(array(
					'form'		=> $id,
					'domain'	=> $domain
				));

		// gather mailer list
		$mailer_list = array();
		$mailer_manager = ContactForm_MailerManager::get_instance();

		foreach ($_REQUEST as $key => $value) {
			if (strpos($key, 'mailer_') !== 0)
				continue;

			if ($value != 1)
				continue;

			$mailer_list[] = substr($key, 7);
		}

		// remove existing mailer associations
		$mailer_manager->delete_items(array('form' => $id));

		// record mailer associations
		if (count($mailer_list) > 0)
			foreach ($mailer_list as $mailer)
				$mailer_manager->insert_item(array(
						'form'		=> $id,
						'mailer'	=> $mailer
					));


		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_form_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('contact_forms'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before removing contact form.
	 */
	private function deleteForm() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_form_delete"),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'contact_forms_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'forms_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('contact_forms_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
 	 * Remove contact form and all of its fields.
	 * Note: This will remove contact form data as well.
	 */
	private function deleteForm_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormManager::get_instance();
		$field_manager = ContactForm_FormFieldManager::get_instance();
		$domain_manager = ContactForm_DomainManager::get_instance();
		$fieldset_manager = ContactForm_FieldsetManager::get_instance();
		$fieldset_membership_manager = ContactForm_FieldsetFieldsManager::get_instance();

		// remove all fieldsets
		$fieldsets = $fieldset_manager->get_items(array('id'), array('form' => $id));
		if (count($fieldsets) > 0)
			foreach ($fieldsets as $fieldset) {
				$fieldset_membership_manager->delete_items(array('fieldset' => $fieldset->id));
				$fieldset_manager->delete_items(array('id' => $fieldset->id));
			}

		// remove rest of the data
		$manager->delete_items(array('id' => $id));
		$field_manager->delete_items(array('form' => $id));
		$domain_manager->delete_items(array('form' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_form_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('contact_forms_delete').';'.window_ReloadContent('contact_forms')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show list of all fieldsets.
	 */
	private function manageFieldsets() {
		$form_id = fix_id($_REQUEST['form']);

		$template = new TemplateHandler('fieldsets_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form'			=> $form_id,
					'link_new'		=> URL::make_hyperlink(
										$this->get_language_constant('new'),
										window_Open(
											'contact_form_fieldset_add', 	// window id
											350,				// width
											$this->get_language_constant('title_fieldsets_add'), // title
											false, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'fieldsets_add'),
												array('form', $form_id)
											)
										)
									)
				);

		$template->register_tag_handler('cms:list', $this, 'tag_FieldsetList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding a new fieldset.
	 */
	private function addFieldset() {
		$form_id = fix_id($_REQUEST['form']);

		$template = new TemplateHandler('fieldsets_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form'			=> $form_id,
					'form_action'	=> backend_UrlMake($this->name, 'fieldsets_save'),
				);

		$template->register_tag_handler('cms:field_list', $this, 'tag_FieldList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for editing fieldset.
	 */
	private function editFieldset() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FieldsetManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('fieldsets_change.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
					'id'			=> $item->id,
					'form'			=> $item->form,
					'name'			=> $item->name,
					'legend'		=> $item->legend,
					'form_action'	=> backend_UrlMake($this->name, 'fieldsets_save'),
				);

			$template->register_tag_handler('cms:field_list', $this, 'tag_FieldList');

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save new or modified fieldset data.
	 */
	private function saveFieldset() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$form_id = fix_id($_REQUEST['form']);
		$manager = ContactForm_FieldsetManager::get_instance();
		$membership_manager = ContactForm_FieldsetFieldsManager::get_instance();

		// collect data
		$data = array(
			'name'		=> fix_chars($_REQUEST['name']),
			'legend'	=> $this->get_multilanguage_field('legend'),
			'form'		=> $form_id
		);

		// collect field ids
		$field_list = array();
		foreach ($_REQUEST as $key => $value)
			if (substr($key, 0, 6) == 'field_' && ($value == '1' || $value == 'on'))
				$field_list[] = fix_id(substr($key, 6));

		// insert or update data in database
		if (is_null($id)) {
			$window = 'contact_form_fieldset_add';
			$manager->insert_item($data);
			$id = $manager->get_inserted_id();

		} else {
			$window = 'contact_form_fieldset_edit';
			$manager->update_items($data,	array('id' => $id));
		}

		// update list assigned fields
		$membership_manager->delete_items(array('fieldset' => $id));
		if (count($field_list) > 0)
			foreach ($field_list as $field_id) {
				$membership_manager->insert_item(array(
									'fieldset'	=> $id,
									'field'		=> $field_id
								));
			}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_fieldset_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('contact_form_fieldsets_'.$form_id)
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before fieldset removal.
	 */
	private function deleteFieldset() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FieldsetManager::get_instance();
		$membership_manager = ContactForm_FieldsetFieldsManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_fieldset_delete'),
					'name'			=> $item->name,
					'yes_text'		=> $this->get_language_constant('delete'),
					'no_text'		=> $this->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'contact_form_fieldset_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'fieldsets_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('contact_form_fieldset_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Remove fieldset and associations.
	 */
	private function deleteFieldset_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FieldsetManager::get_instance();
		$membership_manager = ContactForm_FieldsetFieldsManager::get_instance();

		$form = $manager->get_item_value('form', array('id' => $id));
		$manager->delete_items(array('id' => $id));
		$membership_manager->delete_items(array('fieldset' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_fieldset_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('contact_form_fieldset_delete').';'.window_ReloadContent('contact_form_fieldsets_'.$form)
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show field management window.
	 */
	private function manageFields() {
		$form_id = fix_id($_REQUEST['form']);
		$template = new TemplateHandler('fields_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form'		=> $form_id,
					'link_new'	=> URL::make_hyperlink(
										$this->get_language_constant('new'),
										window_Open(
											'contact_form_fields_add', 	// window id
											460,				// width
											$this->get_language_constant('title_fields_add'), // title
											false, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'fields_add'),
												array('form', $form_id)
											)
										)
									),
					'link_fieldsets' => URL::make_hyperlink(
										$this->get_language_constant('fieldsets'),
										window_Open(
											'contact_form_fieldsets_'.$form_id, 	// window id
											350,				// width
											$this->get_language_constant('title_fieldsets_manage'), // title
											true, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'fieldsets'),
												array('form', $form_id)
											)
										)
									),
				);

		$template->register_tag_handler('cms:list', $this, 'tag_FieldList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding new field.
	 */
	private function addField() {
		$form_id = fix_id($_REQUEST['form']);

		$template = new TemplateHandler('fields_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form'			=> $form_id,
					'form_action'	=> backend_UrlMake($this->name, 'fields_save'),
				);

		$template->register_tag_handler('cms:field_types', $this, 'tag_FieldTypes');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for editing existing field.
	 */
	private function editField() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormFieldManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('fields_change.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

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
						'checked'			=> $item->checked,
						'autocomplete'		=> $item->autocomplete,
						'form_action'  		=> backend_UrlMake($this->name, 'fields_save'),
					);

			$template->register_tag_handler('cms:field_types', $this, 'tag_FieldTypes');

			$template->restore_xml();
			$template->set_local_params($params);
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
			'form'         => $form_id,
			'name'         => fix_chars($_REQUEST['name']),
			'type'         => fix_chars($_REQUEST['type']),
			'label'        => $this->get_multilanguage_field('label'),
			'placeholder'  => $this->get_multilanguage_field('placeholder'),
			'min'          => fix_id($_REQUEST['min']),
			'max'          => fix_id($_REQUEST['max']),
			'maxlength'    => fix_id($_REQUEST['maxlength']),
			'value'        => escape_chars($_REQUEST['value']),
			'pattern'      => escape_chars($_REQUEST['pattern']),
			'disabled'     => $this->get_boolean_field('disabled') ? 1 : 0,
			'required'     => $this->get_boolean_field('required') ? 1 : 0,
			'checked'      => $this->get_boolean_field('checked') ? 1 : 0,
			'autocomplete' => $this->get_boolean_field('autocomplete') ? 1 : 0,
			'order'        => fix_id($_REQUEST['order'])
		);
		$manager = ContactForm_FormFieldManager::get_instance();

		// insert or update data in database
		if (is_null($id)) {
			$window = 'contact_form_fields_add';
			$manager->insert_item($data);
		} else {
			$window = 'contact_form_fields_edit';
			$manager->update_items($data,	array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_field_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('contact_form_fields_'.$form_id),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog before removing field.
	 */
	private function deleteField() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormFieldManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_field_delete"),
					'name'			=> $item->name,
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'contact_form_fields_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'fields_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('contact_form_fields_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perfrom field removal.
	 */
	private function deleteField_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FormFieldManager::get_instance();
		$value_manager = ContactForm_FieldValueManager::get_instance();
		$membership_manager = ContactForm_FieldsetFieldsManager::get_instance();

		$form = $manager->get_item_value('form', array('id' => $id));
		$manager->delete_items(array('id' => $id));
		$value_manager->delete_items(array('field' => $id));
		$membership_manager->delete_items(array('field' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_field_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('contact_form_fields_delete').';'.window_ReloadContent('contact_form_fields_'.$form)
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
 	 * Show list of values for specified field.
	 */
	private function manageValues() {
		$field_id = fix_id($_REQUEST['field']);
		$template = new TemplateHandler('values_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'field'		=> $field_id,
					'link_new'	=> URL::make_hyperlink(
										$this->get_language_constant('new'),
										window_Open(
											'contact_form_field_value_add', 	// window id
											400,				// width
											$this->get_language_constant('title_field_value_add'), // title
											false, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'values_add'),
												array('field', $field_id)
											)
										)
									),
					'link_import'	=> URL::make_hyperlink(
										$this->get_language_constant('import'),
										window_Open(
											'contact_form_field_value_import', 	// window id
											400,				// width
											$this->get_language_constant('title_field_value_import'), // title
											false, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'values_import'),
												array('field', $field_id)
											)
										)
									),
				);

		$template->register_tag_handler('cms:list', $this, 'tag_FieldValueList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding new field value.
	 */
	private function addValue() {
		$field_id = fix_id($_REQUEST['field']);

		$template = new TemplateHandler('values_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'field'			=> $field_id,
					'form_action'	=> backend_UrlMake($this->name, 'values_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for editing field value.
	 */
	private function editValue() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FieldValueManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('values_change.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'id'				=> $item->id,
						'field'				=> $item->field,
						'name'				=> $item->name,
						'value'				=> $item->value,
						'form_action'  		=> backend_UrlMake($this->name, 'values_save'),
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save field value.
	 */
	private function saveValue() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$field_id = fix_id($_REQUEST['field']);

		$data = array(
			'field'	=> $field_id,
			'name'	=> $this->get_multilanguage_field('name'),
			'value'	=> fix_chars($_REQUEST['value'])
		);
		$manager = ContactForm_FieldValueManager::get_instance();

		// insert or update data in database
		if (is_null($id)) {
			$window = 'contact_form_field_value_add';
			$manager->insert_item($data);
		} else {
			$window = 'contact_form_field_value_edit';
			$manager->update_items($data,	array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_field_value_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('contact_form_field_values_'.$field_id),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show template for importing values to specified field.
	 */
	private function importValues() {
		$field_id = fix_id($_REQUEST['field']);

		$template = new TemplateHandler('values_import.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'field'			=> $field_id,
					'form_action'	=> backend_UrlMake($this->name, 'values_import_commit'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Import options to specified field.
	 */
	private function importValues_Commit() {
		$field_id = fix_id($_REQUEST['field']);
		$manager = ContactForm_FieldValueManager::get_instance();
		$remove_existing = $this->get_boolean_field('remove_existing');
		$languages = Language::get_languages(false);

		// make sure uploaded file is good
		if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
			trigger_error('Contact form: Import values: No file uploaded.', E_USER_WARNING);
			return;
		}

		// load csv file
		$values = array();
		$columns = array();
		$headers = array('value');
		$headers = array_merge($headers, Language::get_languages(false));

		if (($handle = fopen($_FILES['file']['tmp_name'], 'r')) !== false) {
			// get headers
			$row = fgetcsv($handle);
			if ($row !== false)
				$columns = $row;

			// read rows and parse them
			while (($row = fgetcsv($handle)) !== false) {
				$data = array(
						'field'	=> $field_id,
					);

				// collect data
				for ($i=0; $i<count($row); $i++) {
					$name = trim($columns[$i]);
					$value = $row[$i];

					// prefix language params
					if ($name != 'value')
						$name = 'name_'.$name;

					// add field to row data
					$data[$name] = fix_chars($value);
				}

				$values[] = $data;
			}

			// close file
			fclose($handle);
		}

		// remove existing if needed
		if ($remove_existing)
			$manager->delete_items(array('field' => $field_id));

		// insert data to database
		foreach ($values as $data)
			$manager->insert_item($data);

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$message = $this->get_language_constant('message_import_complete');
		$message = str_replace('%s', count($values), $message);
		$import_window = 'contact_form_field_value_import';
		$field_window = 'contact_form_field_values_'.$field_id;

		$params = array(
					'message'	=> $message,
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($import_window).';'.window_ReloadContent($field_window)
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog before removing field value.
	 */
	private function deleteValue() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FieldValueManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_field_value_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->get_language_constant('delete'),
					'no_text'		=> $this->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'contact_form_field_value_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'values_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('contact_form_field_value_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform field value removal.
	 */
	private function deleteValue_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ContactForm_FieldValueManager::get_instance();

		$field = $manager->get_item_value('field', array('id' => $id));
		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_field_value_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('contact_form_field_value_delete').';'.window_ReloadContent('contact_form_field_values_'.$field)
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Register form field template.
	 *
	 * @param string $name
	 * @param string $title
	 * @param array $fields
	 */
	public function registerFormTemplate($name, $title, $fields) {
		if (array_key_exists($name, $this->form_templates))
			return $result;

		$this->form_templates[$name] = $fields;
		$this->form_template_names[$name] = $title;
	}

	/**
	 * Register foreign field type.
	 *
	 * Callback function needs to accept a single array containing
	 * all field specific data.
	 *
	 * @param string $type
	 * @param string $name
	 * @param object $object
	 * @param string $function_name
	 */
	public function registerField($type, $name, $object, $function_name) {
		if (array_key_exists($type, $this->foreign_fields))
			return;

		$field = array(
				'name'		=> $name,
				'handler'	=> array(
					'object'	=> $object,
					'function'	=> $function_name
				)
			);

		$this->foreign_fields[$type] = $field;
	}

	/**
	 * Handle field type tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_FieldTypes($tag_params, $children) {
		$selected = null;

		// get parameters
		if (isset($tag_params['selected']))
			$selected = fix_chars($tag_params['selected']);

		// load template
		$template = $this->load_template($tag_params, 'field_type_option.xml');
		$template->set_template_params_from_array($children);

		foreach ($this->field_types as $field) {
			$params = array(
				'selected'	=> $field == $selected,
				'type'		=> $field,
				'name'		=> $this->get_language_constant('field_'.$field)
			);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}

		if (count($this->foreign_fields) > 0)
			foreach ($this->foreign_fields as $field => $data) {
				$params = array(
					'selected'	=> $field == $selected,
					'type'		=> $field,
					'name'		=> $data['name']
				);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

	/**
	 * Handle drawing list of form field templates.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_FormTemplateList($tag_params, $children) {
		$selected = isset($tag_params['selected']) ? $tag_params['selected'] : null;
		$template = $this->load_template($tag_params, 'form_template_option.xml');
		$template->set_template_params_from_array($children);

		foreach ($this->form_templates as $name => $fields) {
			$title = isset($this->form_template_names[$name]) ? $this->form_template_names[$name] : $name;

			$params = array(
					'name'		=> $name,
					'title'		=> $title,
					'selected'	=> $selected == $name
				);

			$template->restore_xml();
			$template->set_local_params($params);
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
		$manager = ContactForm_TemplateManager::get_instance();
		$selected = isset($tag_params['selected']) ? fix_chars($tag_params['selected']) : null;

		// load template
		$template = $this->load_template($tag_params, 'templates_list_item.xml');
		$template->set_template_params_from_array($children);

		// get items from database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

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
						'item_change'	=> URL::make_hyperlink(
												$this->get_language_constant('change'),
												window_Open(
													'contact_form_templates_edit', 	// window id
													650,				// width
													$this->get_language_constant('title_templates_edit'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'templates_edit'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'	=> URL::make_hyperlink(
												$this->get_language_constant('delete'),
												window_Open(
													'contact_form_templates_delete', 	// window id
													400,				// width
													$this->get_language_constant('title_templates_delete'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'templates_delete'),
														array('id', $item->id)
													)
												)
											),
					);

				$template->set_local_params($params);
				$template->restore_xml();
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
		$selected = null;
		$fieldset = null;
		$fieldset_fields = array();
		$manager = ContactForm_FormFieldManager::get_instance();

		// get parameters
		if (isset($tag_params['form']))
			$conditions['form'] = fix_id($tag_params['form']);

		$skip_hidden = false;
		if (isset($tag_params['skip_hidden']))
			$skip_hidden = $tag_params['skip_hidden'] == 1;

		$skip_virtual = true;
		if (isset($tag_params['skip_virtual']))
			$skip_virtual = $tag_params['skip_virtual'] == 1;

		$skip_foreign = true;
		if (isset($tag_params['skip_foreign']))
			$skip_foreign = $tag_params['skip_foreign'] == 1;

		if (isset($tag_params['types'])) {
			$types = fix_chars(explode(',', $tag_params['types']));
			$conditions['type'] = $types;
		}

		if (isset($tag_params['selected']))
			$selected = fix_id($tag_params['selected']);

		// show fields from a fieldset or at least as members of one
		if (isset($tag_params['fieldset'])) {
			$fieldset = fix_id($tag_params['fieldset']);
			$fieldset_manager = ContactForm_FieldsetFieldsManager::get_instance();
			$raw_data = $fieldset_manager->get_items(array('field'), array('fieldset' => $fieldset));

			if (count($raw_data) > 0)
				foreach ($raw_data as $data)
					$fieldset_fields[] = $data->field;

			// if specified, limit displayed fields only to members
			if (isset($tag_params['fieldset_members']) && $tag_params['fieldset_members'] == 1)
				$conditions['id'] = $fieldset_fields;
		}

		// only show fields that are not in any fieldset
		$fieldset_orphans = false;
		if (isset($tag_params['fieldset_orphans']) && isset($conditions['form'])) {
			$fieldset_orphans = $tag_params['fieldset_orphans'] == 1;
			$fieldset_manager = ContactForm_FieldsetManager::get_instance();
			$fieldset_memebership_manager = ContactForm_FieldsetFieldsManager::get_instance();

			// get all fieldsets
			$fieldsets = $fieldset_manager->get_items(array('id'), array('form' => $conditions['form']));
			$fieldset_ids = array();

			if (count($fieldsets) > 0)
				foreach ($fieldsets as $fieldset)
					$fieldset_ids[] = $fieldset->id;

			// get all fields belonging to fieldset
			$raw_data = $fieldset_memebership_manager->get_items(array('field'), array('fieldset' => $fieldset_ids));

			if (count($raw_data) > 0)
				foreach ($raw_data as $membership)
					$fieldset_fields[] = $membership->field;
		}

		$count = 0;
		$limit = null;
		if (isset($tag_params['limit']))
			$limit = fix_id($tag_params['limit']);

		$order_by = array('id');
		if (isset($tag_params['order_by']))
			$order_by = fix_chars(explode(',', $tag_params['order_by']));

		$order_asc = true;
		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 1;

		// load template
		$template = $this->load_template($tag_params, 'field.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:values', $this, 'tag_FieldValueList');

		// get fields
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$foreign_handler_missing = false;
				if (!in_array($item->type, $this->field_types))
					$foreign_handler_missing = !array_key_exists($item->type, $this->foreign_fields);

				// skip hidden fields
				if ($skip_hidden && in_array($item->type, $this->hidden_fields))
					continue;

				// skip virtual fields
				if ($skip_virtual && in_array($item->type, $this->virtual_fields))
					continue;

				// skip if field is not orphaned
				if ($fieldset_orphans && in_array($item->id, $fieldset_fields))
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
					'checked'		=> $item->checked,
					'autocomplete'	=> $item->autocomplete,
				);

				$backend_params = array(
					'selected'		=> $selected == $item->id,
					'in_fieldset'	=> in_array($item->id, $fieldset_fields),
					'skip_foreign'	=> $skip_foreign,
					'item_change'	=> URL::make_hyperlink(
											$this->get_language_constant('change'),
											window_Open(
												'contact_form_fields_edit', 	// window id
												460,				// width
												$this->get_language_constant('title_fields_edit'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'fields_edit'),
													array('id', $item->id)
												)
											)
										),
					'item_delete'	=> URL::make_hyperlink(
											$this->get_language_constant('delete'),
											window_Open(
												'contact_form_fields_delete', 	// window id
												400,				// width
												$this->get_language_constant('title_fields_delete'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'fields_delete'),
													array('id', $item->id)
												)
											)
										),
					'item_values'	=> URL::make_hyperlink(
											$this->get_language_constant('field_values'),
											window_Open(
												'contact_form_field_values_'.$item->id, 	// window id
												450,				// width
												$this->get_language_constant('title_field_values'), // title
												true, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'values_manage'),
													array('field', $item->id)
												)
											)
										)
				);

				if (in_array($item->type, $this->field_types) || $skip_foreign || $foreign_handler_missing) {
					// handle contact form field
					$params = array_merge($params, $backend_params);
					$template->restore_xml();
					$template->set_local_params($params);
					$template->parse();

				} else {
					// handle foreign fields
					$handler = $this->foreign_fields[$item->type]['handler'];
					$object = $handler['object'];
					$function = $handler['function'];

					$object->$function($params);
				}
			}
	}

	/**
	 * Handle drawing field values.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_FieldValueList($tag_params, $children) {
		$manager = ContactForm_FieldValueManager::get_instance();
		$selected = null;
		$conditions = array();
		$order_by = array('id');
		$order_asc = true;

		// get parameters
		if (isset($tag_params['field']))
			$conditions['field'] = fix_id($tag_params['field']);

		if (isset($tag_params['selected']))
			$selected = fix_chars($tag_params['selected']);

		// get items from the database
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc);

		// load template
		$template = $this->load_template($tag_params, 'values_list_item.xml');
		$template->set_template_params_from_array($children);

		if (count($items) > 0)
			foreach ($items as $item) {
				// prepare parameters for this item
				$params = array(
						'id'			=> $item->id,
						'field'			=> $item->field,
						'name'			=> $item->name,
						'value'			=> $item->value,
						'selected'		=> $selected == $item->value,
						'item_change'	=> URL::make_hyperlink(
												$this->get_language_constant('change'),
												window_Open(
													'contact_form_field_value_edit', 	// window id
													400,				// width
													$this->get_language_constant('title_field_value_edit'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'values_edit'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'	=> URL::make_hyperlink(
												$this->get_language_constant('delete'),
												window_Open(
													'contact_form_field_value_delete', 	// window id
													400,				// width
													$this->get_language_constant('title_field_value_delete'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'values_delete'),
														array('id', $item->id)
													)
												)
											)
					);

				// parse template
				$template->restore_xml();
				$template->set_local_params($params);
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
		$manager = ContactForm_FormManager::get_instance();
		$field_manager = ContactForm_FormFieldManager::get_instance();

		// get parameters
		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars($tag_params['text_id']);

		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		$show_fieldsets = true;
		if (isset($tag_params['show_fieldsets']))
			$show_fieldsets = $tag_params['show_fieldsets'] == 1;

		// assign transfer parameters
		if (count($children) > 0)
			foreach ($children as $tag) {
				if ($tag->tagName != 'transfer')
					continue;

				$transfer_name = fix_chars($tag->tagAttrs['name']);
				$transfer_value = fix_chars($tag->tagAttrs['value']);

				$_SESSION['contact_form_transfer_params'][$transfer_name] = $transfer_value;
			}

		// load template
		$template = $this->load_template($tag_params, 'form.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:fields', $this, 'tag_FieldList');
		$template->register_tag_handler('cms:fieldsets', $this, 'tag_FieldsetList');
		$template->set_tag_children('cms:fieldsets', $children);

		// get form from the database
		$item = $manager->get_single_item($manager->get_field_names(), $conditions);

		if (is_object($item)) {
			$fields = $field_manager->get_items(
				array('id'),
				array(
					'form'	=> $item->id,
					'type'	=> 'file'
				)
			);

			$params = array(
					'id'             => $item->id,
					'text_id'        => $item->text_id,
					'name'           => $item->name,
					'action'         => !empty($item->action) ? $item->action : URL::make_query($this->name, 'submit'),
					'template'       => $item->template,
					'use_ajax'       => $item->use_ajax,
					'show_submit'    => $item->show_submit,
					'show_reset'     => $item->show_reset,
					'show_cancel'    => $item->show_cancel,
					'show_controls'  => $item->show_submit || $item->show_reset || $item->show_cancel,
					'show_fieldsets' => $show_fieldsets,
					'has_files'      => count($fields) > 0
				);

			$template->restore_xml();
			$template->set_local_params($params);
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
		global $section;

		$conditions = array();
		$manager = ContactForm_FormManager::get_instance();

		// get params
		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : 0;

		// load template
		$template = $this->load_template($tag_params, 'forms_list_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:fields', $this, 'tag_FieldList');

		// get items from database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		if (count($items) == 0)
			return;

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
				'selected'		=> $selected == $item->id
			);

			if ($section == 'backend' || $section == 'backend_module') {
				$params['item_fields'] = URL::make_hyperlink(
								$this->get_language_constant('fields'),
								window_Open(
									'contact_form_fields_'.$item->id, 	// window id
									500,				// width
									$this->get_language_constant('title_form_fields'), // title
									true, false,
									URL::make_query(
										'backend_module',
										'transfer_control',
										array('module', $this->name),
										array('backend_action', 'fields_manage'),
										array('form', $item->id)
									)
								));
				$params['item_change'] = URL::make_hyperlink(
								$this->get_language_constant('change'),
								window_Open(
									'contact_forms_edit', 	// window id
									430,				// width
									$this->get_language_constant('title_forms_edit'), // title
									false, false,
									URL::make_query(
										'backend_module',
										'transfer_control',
										array('module', $this->name),
										array('backend_action', 'forms_edit'),
										array('id', $item->id)
									)
								));
				$params['item_delete'] = URL::make_hyperlink(
								$this->get_language_constant('delete'),
								window_Open(
									'contact_forms_delete', 	// window id
									400,				// width
									$this->get_language_constant('title_forms_delete'), // title
									false, false,
									URL::make_query(
										'backend_module',
										'transfer_control',
										array('module', $this->name),
										array('backend_action', 'forms_delete'),
										array('id', $item->id)
									)
								));
			}

			// render template
			$template->restore_xml();
			$template->set_local_params($params);
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
		$field_manager = ContactForm_FormFieldManager::get_instance();
		$submission_manager = ContactForm_SubmissionManager::get_instance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::get_instance();
		$fields = array();
		$conditions = array();

		// get parameters
		$conditions['form'] = -1;
		if (isset($tag_params['form']))
			$conditions['form'] = fix_id($tag_params['form']);

		// load template
		$template = $this->load_template($tag_params, 'submission.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:fields', $this, 'tag_SubmissionFields');

		// get submission
		$item = $submission_manager->get_single_item(
				$submission_manager->get_field_names(),
				$conditions
			);

		// load field definitions
		if ($conditions['form'] != -1) {
			$field_definitions = $field_manager->get_items(
				$field_manager->get_field_names(),
				array('form' => $conditions['form'])
			);

			if (count($field_definitions) > 0)
				foreach ($field_definitions as $field)
					$fields[$field->id] = $field;
		}

		// parse template
		if (is_object($item)) {
			// get submitted fields
			$submitted_data = $submission_field_manager->get_items(
					$submission_field_manager->get_field_names(),
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
			$date = date($this->get_language_constant('format_date_short'), $timestamp);
			$time = date($this->get_language_constant('format_time_short'), $timestamp);

			$params = array(
					'id'			=> $item->id,
					'form'			=> $item->form,
					'timestamp'		=> $item->timestamp,
					'time'			=> $time,
					'date'			=> $date,
					'address'		=> $item->address,
					'fields'		=> $field_data
				);

			$template->restore_xml();
			$template->set_local_params($params);
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
		$field_manager = ContactForm_FormFieldManager::get_instance();
		$submission_manager = ContactForm_SubmissionManager::get_instance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::get_instance();
		$fields = array();
		$conditions = array();
		$order_by = array('id');
		$order_asc = false;

		// get parameters
		$conditions['form'] = -1;
		if (isset($tag_params['form']))
			$conditions['form'] = fix_id($tag_params['form']);

		// load template
		$template = $this->load_template($tag_params, 'submissions_list_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:fields', $this, 'tag_SubmissionFields');

		// get submissions
		$items = $submission_manager->get_items(
				$submission_manager->get_field_names(),
				$conditions,
				$order_by,
				$order_asc
			);

		// load field definitions
		if ($conditions['form'] != -1) {
			$field_definitions = $field_manager->get_items(
				$field_manager->get_field_names(),
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
				$submitted_data = $submission_field_manager->get_items(
						$submission_field_manager->get_field_names(),
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
				$date = date($this->get_language_constant('format_date_short'), $timestamp);
				$time = date($this->get_language_constant('format_time_short'), $timestamp);

				$params = array(
						'id'			=> $item->id,
						'form'			=> $item->form,
						'timestamp'		=> $item->timestamp,
						'time'			=> $time,
						'date'			=> $date,
						'address'		=> $item->address,
						'fields'		=> $field_data,
						'item_details'	=> URL::make_hyperlink(
												$this->get_language_constant('details'),
												window_Open(
													'contact_form_submission_details'.$item->id, 	// window id
													400,				// width
													$this->get_language_constant('title_submission_details'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'submission_details'),
														array('id', $item->id)
													)
												)
											),
					);

				$template->restore_xml();
				$template->set_local_params($params);
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
		$form_field_manager = ContactForm_FormFieldManager::get_instance();
		$submission_manager = ContactForm_SubmissionManager::get_instance();
		$submission_field_manager = ContactForm_SubmissionFieldManager::get_instance();

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
		$submission = $submission_manager->get_single_item(
				$submission_manager->get_field_names(),
				array('id' => $submission_id)
			);

		if (!is_object($submission)) {
			trigger_error('Submission fields tag: Unknown submission.', E_USER_NOTICE);
			return;
		}

		// get form fields
		$raw_fields = $form_field_manager->get_items(
				$form_field_manager->get_field_names(),
				array('form' => $submission->form)
			);

		$fields = array();
		foreach ($raw_fields as $field)
			$fields[$field->id] = $field;

		// load submission data
		$items = $submission_field_manager->get_items(
				$submission_field_manager->get_field_names(),
				array('submission' => $submission->id)
			);

		// load template
		$template = $this->load_template($tag_params, 'submission_field.xml');
		$template->set_template_params_from_array($children);

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

				$template->restore_xml();
				$template->set_local_params($params);
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
		$selected = array();
		$template = $this->load_template($tag_params, 'mailer_option.xml');
		$template->set_template_params_from_array($children);

		if (isset($tag_params['form'])) {
			$form = fix_id($tag_params['form']);
			$manager = ContactForm_MailerManager::get_instance();
			$associations = $manager->get_items(array('mailer'), array('form' => $form));

			if (count($associations) > 0)
				foreach ($associations as $association)
					$selected[] = $association->mailer;

		} else {
			$selected[] = $this->settings['mailer'];
		}

		foreach ($this->mailers as $name => $mailer) {
			$params = array(
				'name'		=> $name,
				'title'		=> $mailer->get_title(),
				'selected'	=> in_array($name, $selected)
			);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Show list of domains for specified form.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DomainList($tag_params, $children) {
		$form_id = null;
		$manager = ContactForm_DomainManager::get_instance();

		// get form id
		if (isset($tag_params['form']))
			$form_id = fix_id($tag_params['form']);

		// make sure form is specified
		if (is_null($form_id))
			return;

		// get list of domains
		$domain_list = $manager->get_items($manager->get_field_names(), array('form' => $form_id));

		// load template
		$template = $this->load_template($tag_params, 'domain_list_item.xml');
		$template->set_template_params_from_array($children);

		// draw domains
		if (count($domain_list) > 0)
			foreach ($domain_list as $record) {
				$field_name = 'domain_'.$this->hash_code($record->domain);
				$params = array(
						'form'			=> $record->form,
						'domain'		=> $record->domain,
						'field_name'	=> $field_name
					);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

	/**
	 * Handle drawing fieldset.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Fieldset($tag_params, $children) {
		$conditions = array();
		$manager = ContactForm_FieldsetManager::get_instance();
		$membership_manager = ContactForm_FieldsetFieldsManager::get_instance();

		// get conditions
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['name']))
			$conditions['name'] = fix_chars($tag_params['name']);

		if (isset($tag_params['form']))
			$conditions['form'] = fix_id($tag_params['form']);

		// get fieldset from database
		$item = $manager->get_single_item($manager->get_field_names(), $conditions);

		// load template
		$template = $this->load_template($tag_params, 'fieldset.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:field_list', $this, 'tag_FieldList');

		// parse template
		if (is_object($item)) {
			$params = array(
				'id'		=> $item->id,
				'form'		=> $item->form,
				'name'		=> $item->name,
				'legend'	=> $item->legend
			);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Handle drawing list of fieldsets.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_FieldsetList($tag_params, $children) {
		$includes = array();
		$conditions = array();
		$manager = ContactForm_FieldsetManager::get_instance();
		$membership_manager = ContactForm_FieldsetFieldsManager::get_instance();

		// get conditions
		if (isset($tag_params['form']))
			$conditions['form'] = fix_id($tag_params['form']);

		// collect transfered options from form
		$fieldset_includes = array();

		if (count($children) > 0)
			foreach ($children as $tag) {
				// skip tags that are not ours
				if ($tag->tagName != 'fieldset')
					continue;

				// store template name
				$name = $tag->tagAttrs['name'];
				$template = $tag->tagAttrs['include'];

				$includes[$name] = $template;
			}

		// get fieldset from database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		// load template
		$template = $this->load_template($tag_params, 'fieldset.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:field_list', $this, 'tag_FieldList');

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
					'id'			=> $item->id,
					'form'			=> $item->form,
					'name'			=> $item->name,
					'legend'		=> $item->legend,
					'include'		=> array_key_exists($item->name, $includes) ? $includes[$item->name] : '',
					'item_change'	=> URL::make_hyperlink(
											$this->get_language_constant('change'),
											window_Open(
												'contact_form_fieldset_edit', 	// window id
												350,				// width
												$this->get_language_constant('title_fieldsets_edit'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'fieldsets_edit'),
													array('id', $item->id)
												)
											)
										),
					'item_delete'	=> URL::make_hyperlink(
											$this->get_language_constant('delete'),
											window_Open(
												'contact_form_fieldset_delete', 	// window id
												400,				// width
												$this->get_language_constant('title_fieldsets_delete'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'fieldsets_delete'),
													array('id', $item->id)
												)
											)
										),
				);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

	/**
	 * Return list of contact forms as JSON object.
	 */
	private function json_form_list() {
		$manager = ContactForm_FormManager::get_instance();
		$forms = $manager->get_items($manager->get_field_names(), array());
		$result = array();

		if (count($forms) > 0)
			foreach ($forms as $form)
				$result[] = array(
					'id'          => $form->id,
					'text_id'     => $form->text_id,
					'name'        => $form->name,
					'template'    => $form->template,
					'use_ajax'    => $form->use_ajax,
					'show_submit' => $form->show_submit,
					'show_reset'  => $form->show_reset,
					'show_cancel' => $form->show_cancel
				);

		header('Content-Type: json/application');
		print json_encode($result);
	}

	/**
	 * Return list of current configuration.
	 */
	private function json_settings() {
		// duplicate settings array
		$result = $this->settings;

		// generate list of mailers
		$mailers = array();

		foreach ($this->mailers as $name => $mailer)
			$mailers[] = array(
					'name'  => $name,
					'title' => $mailer->get_title(),
				);

		$result['mailer_list'] = $mailers;

		// show result
		header('Content-Type: json/application');
		print json_encode($result);
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
	 * @param integer $form
	 * @return array
	 */
	public function getMailers($form=null) {
		$result = array();
		$applicable = array();

		// add default mailer if no others were provided
		if (!is_null($form)) {
			$manager = ContactForm_MailerManager::get_instance();
			$association_list = $manager->get_items(array('mailer'), array('form' => $form));

			if (count($association_list) > 0)
				foreach ($association_list as $association)
					$applicable[] = $association->mailer;
		}

		// make sure result is not empty
		if (empty($applicable)) {
			$name = isset($this->settings['mailer']) ? $this->settings['mailer'] : null;
			if (isset($this->mailers[$name])) {
				$applicable[] = $name;

			} else {
				$names = array_keys($this->mailers);
				$applicable[] = $names[0];
			}
		}

		// prepare results array
		foreach ($applicable as $name)
			if (isset($this->mailers[$name]))
				$result[$name] = $this->mailers[$name];

		return $result;
	}

	/**
	 * Get mailer by name.
	 *
	 * @param string $name
	 * @return object
	 */
	public function getMailerByName($name) {
		$result = null;

		if (array_key_exists($name, $this->mailers))
			$result = $this->mailers[$name];

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
		$manager = ContactForm_TemplateManager::get_instance();

		// get template
		$template = $manager->get_single_item($manager->get_field_names(), array('text_id' => $name));

		if (is_object($template))
			$result = array(
					'name'       => $template->name[$language],
					'plain_body' => $template->plain[$language],
					'html_body'  => $template->html[$language],
					'subject'    => $template->subject[$language]
				);

		return $result;
	}

	/**
	 * Simple string hashing function.
	 *
	 * @param string $value
	 * @return string
	 */
	private function hash_code($value) {
		$result = 0;

		if (mb_strlen($value) == 0)
			return $result;

		for ($i = 0; $i < strlen($value); $i++) {
			$char = substr($value, $i, 1);
			$result = (($result << 5) - $result) + ord($char);
			$result = $result & $result;
		}

		return abs($result);
	}
}
