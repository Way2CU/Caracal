<?php

/**
 * Backend User Manager
 */
use Core\Events;
use Core\Markdown;

class Backend_UserManager {
	private static $_instance;

	private $parent;

	protected function __construct() {
		$this->parent = backend::getInstance();
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
	 * Transfer control to this object
	 */
	public function transferControl() {
		$backend_action = isset($_REQUEST['backend_action']) ? $_REQUEST['backend_action'] : null;

		if (!is_null($backend_action))
			switch($backend_action) {
				case 'users_create':
					$this->createUser();
					break;

				case 'users_change':
					$this->changeUser();
					break;

				case 'users_save':
					$this->saveUser();
					break;

				case 'users_delete':
					$this->deleteUser();
					break;

				case 'users_delete_commit':
					$this->deleteUser_Commit();
					break;

				case 'change_password':
					$this->changePassword();
					break;

				case 'save_password':
					$this->savePassword();
					break;

				case 'email_templates':
					$this->showTemplateSelection();
					break;

				case 'email_templates_save':
					$this->saveTemplateSelection();
					break;

				default:
					$this->showUsers();
					break;
			}
	}

	/**
	 * Show user list
	 */
	private function showUsers() {
		$template = new TemplateHandler('users_list.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
				'link_new'	=> window_OpenHyperlink(
										$this->parent->getLanguageConstant('create_user'),
										'system_users_create',
										370,
										$this->parent->getLanguageConstant('title_users_create'),
										true, true,
										$this->parent->name,
										'users_create'
									),
				'link_templates'	=> window_OpenHyperlink(
										$this->parent->getLanguageConstant('email_templates'),
										'system_users_email_templates',
										370,
										$this->parent->getLanguageConstant('title_email_templates'),
										true, true,
										$this->parent->name,
										'email_templates'
									)
			);

 		$template->registerTagHandler('_user_list', $this, 'tag_UserList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show new user form
	 */
	private function createUser() {
		$template = new TemplateHandler('users_create.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);
 		$template->registerTagHandler('cms:level', $this, 'tag_Level');

		$params = array(
					'form_action'	=> backend_UrlMake($this->parent->name, 'users_save'),
					'cancel_action'	=> window_Close('system_users_create')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show edit user form
	 */
	private function changeUser() {
		$id = fix_id($_REQUEST['id']);
		$manager = UserManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('users_change.xml', $this->parent->path.'templates/');
	 		$template->registerTagHandler('cms:level', $this, 'tag_Level');

			$params = array(
						'id'			=> $item->id,
						'fullname'		=> $item->fullname,
						'username'		=> $item->username,
						'email'			=> $item->email,
						'level'			=> $item->level,
						'form_action'	=> backend_UrlMake($this->parent->name, 'users_save'),
						'cancel_action'	=> window_Close('system_users_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save timer for unpriviledged user submission.
	 */
	public function saveTimer() {
		$_SESSION['backend_user_timer'] = time();
	}

	/**
	 * Save changed or new user data
	 */
	private function saveUser() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;

		// get manager instance
		$manager = UserManager::getInstance();

		// grab new user data
		$data = array(
				'username'	=> escape_chars($_REQUEST['username']),
				'email'		=> escape_chars($_REQUEST['email']),
				'level'		=> fix_id($_REQUEST['level']),
				'verified'	=> 1
			);

		// prepare user's name
		if (isset($_REQUEST['fullname'])) {
			$data['fullname'] = escape_chars($_REQUEST['fullname']);
			$raw_data = explode(' ', $data['fullname'], 1);

			if (count($raw_data) == 2) {
				$data['first_name'] = $raw_data[0];
				$data['last_name'] = $raw_data[1];

			} else {
				$data['first_name'] = $data['fullname'];
				$data['last_name'] = '';
			}

		} else if (isset($_REQUEST['first_name'])) {
			$data['first_name'] = escape_chars($_REQUEST['first_name']);
			$data['last_name'] = escape_chars($_REQUEST['last_name']);
			$data['fullname'] = $data['first_name'].' '.$data['last_name'];
		}

		// check if password needs updating
		$password = '';
		$update_password = false;
		if (isset($_REQUEST['password'])) {
			$password = $_REQUEST['password'];
			$update_password = true;
		}

		if (isset($_REQUEST['new_password']) && !empty($_REQUEST['new_password'])) {
			$password = $_REQUEST['new_password'];
			$update_password = true;
		}

		// test level is ok to ensure security is on right level
		$level_is_ok = ($_SESSION['level'] > 5) || ($_SESSION['level'] <= 5 && $data['level'] < $_SESSION['level']);

		// save changes
		if (!is_null($id)) {
			$window = 'system_users_change';

			// get existing user
			$user = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

			if (($_SESSION['level'] == 10) || (is_object($user) && $user->level < $_SESSION['level'])) {
				// save changed user data
				$message = $this->parent->getLanguageConstant('message_users_data_saved');
				$manager->updateData($data, array('id' => $id));

				if ($update_password)
					$manager->change_password($data['username'], $password);

				// trigger event
				Events::trigger('backend', 'user-change', $user);

			} else {
				// we can't edit user with higher level than our own
				$message = $this->parent->getLanguageConstant('message_users_error_user_level_higher');
			}

		} else {
			$window = 'system_users_create';

			if ($level_is_ok) {
				// save new user data
				$message = $this->parent->getLanguageConstant('message_users_data_saved');
				$manager->insertData($data);
				$user = $manager->getSingleItem(
										$manager->getFieldNames(),
										array('id' => $manager->getInsertedID())
									);

				if ($update_password)
					$manager->change_password($data['username'], $password);

				// trigger event
				Events::trigger('backend', 'user-create', $user);

			} else {
				// can't assign specified level
				$message = $this->parent->getLanguageConstant('message_users_error_level_too_high');

			}
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'	=> $message,
					'button'	=> $this->parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('system_users'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Saves unpriviledged user to the system
	 */
	public function saveUnpriviledgedUser($tag_params, $children) {
		$result = array(
				'error'		=> false,
				'message'	=> ''
			);
		$manager = UserManager::getInstance();
		$user_id = null;
		$agreed = isset($_REQUEST['agreed']) && ($_REQUEST['agreed'] == 'on' || $_REQUEST['agreed'] == '1') ? 1 : 0;

		// grab new user data
		if (_AJAX_REQUEST)
			$source = $_REQUEST; else
			$source = $tag_params;

		$data = array(
				'username'	=> escape_chars($source['username']),
				'email'		=> escape_chars($source['email']),
				'level'		=> 0,
				'agreed'	=> $agreed
			);

		// prepare user's name
		if (isset($source['fullname'])) {
			$data['fullname'] = escape_chars($source['fullname']);
			$raw_data = explode(' ', $data['fullname'], 1);

			if (count($raw_data) == 2) {
				$data['first_name'] = $raw_data[0];
				$data['last_name'] = $raw_data[1];

			} else {
				$data['first_name'] = $data['fullname'];
				$data['last_name'] = '';
			}

		} else if (isset($source['first_name'])) {
			$data['first_name'] = escape_chars($source['first_name']);
			$data['last_name'] = escape_chars($source['last_name']);
			$data['fullname'] = $data['first_name'].' '.$data['last_name'];
		}

		// check for duplicates
		$duplicate_users = $manager->getItems(array('id'), array('username' => $data['username']));
		$duplicate_emails = $manager->getItems(array('id'), array('email' => $data['email']));

		if (class_exists('captcha') && isset($source['captcha'])) {
			// validate submission through captcha
			$captcha = captcha::getInstance();
			if (!$captcha->isCaptchaValid($source['captcha'])) {
				$result['error'] = true;
				$result['message'] = $this->parent->getLanguageConstant('message_users_error_captcha');
			}

		} else {
			// no captcha is present, validate through submission timer
			$timer = isset($_SESSION['backend_user_timer']) ? $_SESSION['backend_user_timer'] : null;

			if (is_null($timer) || (!is_null($timer) && time() - $timer < 5)) {
				$result['error'] = true;
				$result['message'] = $this->parent->getLanguageConstant('message_users_error_premature_submission');
			}

			unset($_SESSION['backend_user_timer']);
		}

		if (!$result['error'])
			if (count($duplicate_users) > 0 || count($duplicate_emails) > 0) {
				// we found a duplicate user
				$result['error'] = true;
				$result['message'] = $this->parent->getLanguageConstant('message_users_error_duplicate');

			} else {
				// insert data
				$manager->insertData($data);
				$user_id = $manager->getInsertedID();
				$manager->change_password($data['username'], $source['password']);

				// log user in
				$validation_required = $this->parent->settings['require_verified'];

				if (!$validation_required)
					$manager->login_user($data['username']);

				// assign message
				$result['message'] = $this->parent->getLanguageConstant('message_users_created');

				// trigger event
				$user = $manager->getSingleItem(
										$manager->getFieldNames(),
										array('id' => $user_id)
									);
				Events::trigger('backend', 'user-create', $user);
			}

		// show result
		if (isset($source['show_result']) && $source['show_result'] == 1)
			if (defined('_AJAX_REQUEST')) {
				print json_encode($result);

			} else {
				// show message
				$template = new TemplateHandler('message.xml', $this->parent->path.'templates/');
				$template->setMappedModule($this->parent->name);

				$params = array(
							'message'	=> $result['message'],
							'button'	=> $this->parent->getLanguageConstant('close'),
							'action'	=> window_Close($window).";".window_ReloadContent('system_users'),
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}

		// send notification email
		if (!$result['error'] && !is_null($user_id))
			$this->sendNotificationEmail($user_id);
	}

	/**
	 * Send notification email for specified user.
	 *
	 * @param integer $user_id
	 * @return boolean
	 */
	public function sendNotificationEmail($user_id) {
		$result = false;

		// make sure contact form is available
		if (!class_exists('contact_form'))
			return $result;

		// get managers
		$user_manager = UserManager::getInstance();
		$verification_manager = UserVerificationManager::getInstance();
		$contact_form = contact_form::getInstance();

		// get user for specified id
		$user = $user_manager->getSingleItem(
					$user_manager->getFieldNames(),
					array('id' => $user_id)
				);

		if (!is_object($user))
			return $result;

		// get new verification code
		$verification_code = $contact_form->generateVerificationCode(
										$user->username,
										$user->email
									);

		// insert verification code
		$verification_data = array(
					'user'	=> $user_id,
					'code'	=> $verification_code
				);
		$verification_manager->insertData($verification_data);

		// prepare email
		$fields = array(
				'fullname'		=> $user->fullname,
				'first_name'	=> $user->first_name,
				'last_name'		=> $user->last_name,
				'username'		=> $user->username,
				'email'			=> $user->email,
				'verify_code'	=> $verification_code
			);

		// get mailer
		$mailers = $contact_form->getMailers();
		$sender = $contact_form->getSender();
		$template = $contact_form->getTemplate($this->parent->settings['template_verify']);

		// start creating message
		$result = true;
		foreach ($mailers as $mailer_name => $mailer) {
			$mailer->start_message();
			$mailer->set_subject($template['subject']);
			$mailer->set_sender($sender['address'], $sender['name']);
			$mailer->add_recipient($fields['email'], $fields['fullname']);

			$mailer->set_body($template['plain_body'], Markdown::parse($template['html_body']));
			$mailer->set_variables($fields);

			// send email
			$send_result = $mailer->send();
			$result &= $send_result;

			// report error with mailer in case it failed
			if (!$send_result)
				trigger_error('Failed sending notification message with "'.$mailer_name.'".', E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Save password for unpriviledge user.
	 * Returns `true` if password was changed.
	 *
	 * @param array $tag_params
	 * @param array $children
	 * @return boolean
	 */
	public function saveUnpriviledgedPassword($tag_params, $children) {
		$result = array(
				'error'		=> true,
				'message'	=> ''
			);
		$manager = UserManager::getInstance();

		// grab new user data
		if (defined('_AJAX_REQUEST'))
			$source = $_REQUEST; else
			$source = $tag_params;

		// get user data
		$user_id = $_SESSION['logged'] ? $_SESSION['uid'] : null;
		$new_password = $source['new_password'];

		// we need user to be logged in
		if (is_null($user_id)) {
			if (defined('_AJAX_REQUEST'))
				print json_encode($result);

			return !$result['error'];
		}

		// get user from the database
		$user = $manager->getSingleItem(
								$manager->getFieldNames(),
								array('id' => $user_id)
							);

		if (is_object($user)) {
			// make sure old password is correct
			$old_password = hash_hmac('sha256', $source['current_password'], $user->salt);

			if ($old_password == $user->password) {
				// update password
				$manager->change_password($user->username, $new_password);

				// prepare result
				$result['error'] = false;
				$result['message'] = $this->parent->getLanguageConstant('message_password_changed');

				// trigger error
				$user = $manager->getSingleItem(
										$manager->getFieldNames(),
										array('id' => $user->id)
									);
				Events::trigger('backend', 'user-password-change', $user);

			} else {
				$result['message'] = $this->parent->getLanguageConstant('message_invalid_password');
			}

		} else {
			$result['message'] = $this->parent->getLanguageConstant('message_no_user');
		}

		// show result
		if (defined('_AJAX_REQUEST'))
			print json_encode($result);

		return !$result['error'];
	}

	/**
	 * Password recovery for user accounts using email or username.
	 * Password reset string is sent to users email.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function recoverPasswordByEmail($tag_params, $children) {
		$result = array(
					'error'		=> true,
					'message'	=> ''
				);

		// make sure contact form module is enabled
		if (!class_exists('contact_form'))
			if (_AJAX_REQUEST) {
				$result['message'] = $this->parent->getLanguageConstant('message_no_contact_form');
				print json_encode($result);
				return;

			} else {
				$template = $this->parent->loadTemplate($tag_params, 'message.xml');
				$template->setTemplateParamsFromArray($children);
				$result['message'] = $this->parent->getLanguageConstant('message_no_contact_form');

				$template->restoreXML();
				$template->setLocalParams($result);
				$template->parse();
				return;
			}

		if (!class_exists('captcha'))
			if (_AJAX_REQUEST) {
				$result['message'] = $this->parent->getLanguageConstant('message_no_captcha');
				print json_encode($result);
				return;

			} else {
				$template = $this->parent->loadTemplate($tag_params, 'message.xml');
				$template->setTemplateParamsFromArray($children);
				$result['message'] = $this->parent->getLanguageConstant('message_no_captcha');

				$template->restoreXML();
				$template->setLocalParams($result);
				$template->parse();
				return;
			}

		// get required module instances
		$manager = UserManager::getInstance();
		$verification_manager = UserVerificationManager::getInstance();
		$contact_form = contact_form::getInstance();
		$captcha_module = captcha::getInstance();
		$username = null;
		$email = null;
		$captcha = null;
		$conditions = array();

		// get username
		if (array_key_exists('username', $tag_params))
			$username = escape_chars($tag_params['username']);

		if (is_null($username) && array_key_exists('username', $_REQUEST))
			$username = escape_chars($_REQUEST['username']);

		// get email
		if (array_key_exists('email', $tag_params))
			$email = escape_chars($tag_params['email']);

		if (is_null($email) && array_key_exists('email', $_REQUEST))
			$email = escape_chars($_REQUEST['email']);

		// get captcha value
		if (array_key_exists('captcha', $tag_params))
			$captcha = escape_chars($tag_params['captcha']);

		if (is_null($captcha) && array_key_exists('captcha', $_REQUEST))
			$captcha = escape_chars($_REQUEST['captcha']);

		// get user from the database
		if (!is_null($username))
			$conditions['username'] = $username;

		if (!is_null($email))
			$conditions['email'] = $email;

		$user = $manager->getSingleItem($manager->getFieldNames(), $conditions);
		$captcha_valid = $captcha_module->isCaptchaValid($captcha);

		// send email
		if (is_object($user) && $captcha_valid) {
			$code = $contact_form->generateVerificationCode($user->username, $user->email);

			// insert verification code
			$verification_data = array(
						'user'	=> $user->id,
						'code'	=> $code
					);
			$verification_manager->insertData($verification_data);

			// prepare email
			$fields = array(
					'fullname'		=> $user->fullname,
					'username'		=> $user->username,
					'email'			=> $user->email,
					'code'			=> $code
				);

			$mailers = $contact_form->getMailers();
			$sender = $contact_form->getSender();
			$template = $contact_form->getTemplate($this->parent->settings['template_recovery']);

			// start creating message
			$result = true;
			foreach ($mailers as $mailer_name => $mailer) {
				$mailer->start_message();
				$mailer->set_subject($template['subject']);
				$mailer->set_sender($sender['address'], $sender['name']);
				$mailer->add_recipient($fields['email'], $fields['fullname']);

				$mailer->set_body($template['plain_body'], Markdown::parse($template['html_body']));
				$mailer->set_variables($fields);

				$send_result = $mailer->send();
				$result &= $send_result;

				// report error with mailer in case it failed
				if (!$send_result)
					trigger_error('Failed sending password recovery message with "'.$mailer_name.'".', E_USER_WARNING);
			}

			// send email
			$result['error'] = !$result;

			if (!$result['error'])
				$result['message'] = $this->parent->getLanguageConstant('message_password_recovery_email_sent'); else
				$result['message'] = $this->parent->getLanguageConstant('message_password_reocvery_email_error');

		} elseif (is_object($user) && !$captcha_valid) {
			$result['message'] = $this->parent->getLanguageConstant('message_users_error_captcha');

		} else {
			$result['message'] = $this->parent->getLanguageConstant('message_no_user');
		}

		// show response
		if (_AJAX_REQUEST) {
			print json_encode($result);

		} else {
			$template = $this->parent->loadTemplate($tag_params, 'message.xml');
			$template->setTemplateParamsFromArray($children);

			$template->restoreXML();
			$template->setLocalParams($result);
			$template->parse();
		}

		return !$result['error'];
	}

	/**
	 * Save new password for specified account.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function saveRecoveredPassword($tag_params, $children) {
		$manager = UserManager::getInstance();
		$verification_manager = UserVerificationManager::getInstance();
		$username = null;
		$email = null;
		$password = null;
		$code = null;
		$conditions = array();
		$result = array(
				'error'		=> true,
				'message'	=> ''
			);

		// get username
		if (array_key_exists('username', $tag_params))
			$username = escape_chars($tag_params['username']);

		if (is_null($username) && array_key_exists('username', $_REQUEST))
			$username = escape_chars($_REQUEST['username']);

		// get email
		if (array_key_exists('email', $tag_params))
			$email = escape_chars($tag_params['email']);

		if (is_null($email) && array_key_exists('email', $_REQUEST))
			$email = escape_chars($_REQUEST['email']);

		// get password
		if (array_key_exists('password', $tag_params))
			$password = $tag_params['password'];

		if (is_null($password) && array_key_exists('password', $_REQUEST))
			$password = $_REQUEST['password'];

		// get code
		if (array_key_exists('code', $tag_params))
			$code = escape_chars($tag_params['code']);

		if (is_null($code) && array_key_exists('code', $_REQUEST))
			$code = escape_chars($_REQUEST['code']);

		// get user with specified data
		if (!is_null($username))
			$conditions['username'] = $username;

		if (!is_null($email))
			$conditions['email'] = $email;

		$user = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		// store new password
		if (is_object($user)) {
			$verification = $verification_manager->getSingleItem(
													$verification_manager->getFieldNames(),
													array(
														'user'	=> $user->id,
														'code'	=> $code
													));

			if (is_object($verification)) {
				// remove verification code from the database
				$verification_manager->deleteData(array('user' => $verification->user));

				// store new password and mark account as verified
				$manager->verify_user($user->username);
				$manager->change_password($user->username, $password);

				// prepare response
				$result['error'] = false;
				$result['message'] = $this->parent->getLanguageConstant('message_password_changed');

				// trigger event
				Events::trigger('backend', 'user-password-change', $user);

			} else {
				// invalid code or user
				$result['message'] = $this->parent->getLanguageConstant('message_invalid_code');
			}

		} else {
			// no user in the system
			$result['message'] = $this->parent->getLanguageConstant('message_no_user');
		}

		// show response
		if (_AJAX_REQUEST) {
			print json_encode($result);

		} else {
			$template = $this->parent->loadTemplate($tag_params, 'message.xml');
			$template->setTemplateParamsFromArray($children);

			$template->restoreXML();
			$template->setLocalParams($result);
			$template->parse();
		}

		return !$result['error'];
	}

	/**
	 * Show confirmation form for user removal
	 */
	private function deleteUser() {
		$id = fix_id($_REQUEST['id']);
		$manager = UserManager::getInstance();

		$item = $manager->getSingleItem(array('fullname'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'		=> $this->parent->getLanguageConstant('message_users_delete'),
					'name'			=> $item->fullname,
					'yes_text'		=> $this->parent->getLanguageConstant('delete'),
					'no_text'		=> $this->parent->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'system_users_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->parent->name),
												array('backend_action', 'users_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('system_users_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform user removal
	 */
	private function deleteUser_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = UserManager::getInstance();

		// trigger event
		$user = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		Events::trigger('backend', 'user-delete', $user);

		// remove user from database
		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'	=> $this->parent->getLanguageConstant('message_users_deleted'),
					'button'	=> $this->parent->getLanguageConstant('close'),
					'action'	=> window_Close('system_users_delete').';'.window_ReloadContent('system_users')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show dialog for changing password
	 */
	private function changePassword() {
		$template = new TemplateHandler('change_password.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->parent->name, 'save_password'),
					'cancel_action'	=> window_Close('change_password_window')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Salt and save password
	 */
	private function savePassword() {
		$manager = UserManager::getInstance();

		$old_password = escape_chars($_REQUEST['old_password']);
		$new_password = escape_chars($_REQUEST['new_password']);
		$repeat_password = escape_chars($_REQUEST['repeat_password']);
		$user_id = fix_id($_SESSION['uid']);

		// get existing user entry
		$user = $manager->getSingleItem($manager->getFieldNames(), array('id' => $user_id));

		if (is_object($user)) {
			$new_password_ok = $new_password == $repeat_password && !empty($new_password);
			$old_password_ok = hash_hmac('sha256', $old_password, $user->salt) == $user->password;

			if ($new_password_ok && $old_password_ok) {
				// all conditions are met, change password
				$manager->change_password($user->username, $new_password);

				// prepare response
				$message = $this->parent->getLanguageConstant('message_password_changed');

				// trigger event
				$user = $manager->getSingleItem($manager->getFieldNames(), array('id' => $user->id));
				Events::trigger('backend', 'user-password-change', $user);

			} else {
				// mismatching passwords
				$message = $this->parent->getLanguageConstant('message_password_change_error');
			}
		}

		$template = new TemplateHandler('message.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'	=> $message,
					'button'	=> $this->parent->getLanguageConstant('close'),
					'action'	=> window_Close('change_password_window')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for selecting email templates for notifying users.
	 */
	private function showTemplateSelection() {
		if (class_exists('contact_form')) {
			// get contact form and show settings
			$contact_form = contact_form::getInstance();
			$template = new TemplateHandler('email_templates.xml', $this->parent->path.'templates/');
			$template->setMappedModule($this->parent->name);

			$template->registerTagHandler('cms:templates', $contact_form, 'tag_TemplateList');

			$params = array(
						'form_action'	=> backend_UrlMake($this->parent->name, 'email_templates_save'),
						'cancel_action'	=> window_Close('system_users_email_templates')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();

		} else {
			// contact form module is not active, show message instead
			$template = new TemplateHandler('message.xml', $this->parent->path.'templates/');
			$template->setMappedModule($this->parent->name);

			$params = array(
						'message'	=> $this->parent->getLanguageConstant('message_no_contact_form'),
						'button'	=> $this->parent->getLanguageConstant('close'),
						'action'	=> window_Close('system_users_email_templates')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}

	}

	/**
	 * Save selection of email templates.
	 */
	private function saveTemplateSelection() {
		// save configuration
		$template_verify = escape_chars($_REQUEST['template_verify']);
		$template_recovery = escape_chars($_REQUEST['template_recovery']);

		$this->parent->saveTemplateSelection(
							$template_verify,
							$template_recovery
						);

		// show message
		$template = new TemplateHandler('message.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'	=> $this->parent->getLanguageConstant('message_template_selection_saved'),
					'button'	=> $this->parent->getLanguageConstant('close'),
					'action'	=> window_Close('system_users_email_templates')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle drawing user list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_UserList($tag_params, $children) {
		$admin_manager = UserManager::getInstance();

		// make sure lower levels can't edit others
		if ($_SESSION['level'] < 5)
			$conditions = array('id' => $_SESSION['uid']); else
			$conditions = array();

		// create template
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->parent->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('users_list_item.xml', $this->parent->path.'templates/');
		}

		$template->setMappedModule($this->parent->name);

		// get users from database
		$users = $admin_manager->getItems($admin_manager->getFieldNames(), $conditions);

		// draw users
		if (count($users) > 0)
			foreach ($users as $user) {
				$params = array(
							'id'				=> $user->id,
							'fullname'			=> $user->fullname,
							'username'			=> $user->username,
							'level'				=> $user->level,
							'verified'			=> $user->verified,
							'verified_char'		=> $user->verified ? CHAR_CHECKED : CHAR_UNCHECKED,
							'agreed'			=> $user->agreed,
							'agreed_char'		=> $user->agreed ? CHAR_CHECKED : CHAR_UNCHECKED,
							'selected'			=> isset($tag_params['selected']) && ($tag_params['selected'] == $user->id),
							'item_change'		=> url_MakeHyperlink(
													$this->parent->getLanguageConstant('change'),
													window_Open(
														'system_users_change', 	// window id
														370,				// width
														$this->parent->getLanguageConstant('title_users_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->parent->name),
															array('backend_action', 'users_change'),
															array('id', $user->id)
														)
													)
												),
							'item_delete'		=> url_MakeHyperlink(
													$this->parent->getLanguageConstant('delete'),
													window_Open(
														'system_users_delete', // window id
														400,				// width
														$this->parent->getLanguageConstant('title_users_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->parent->name),
															array('backend_action', 'users_delete'),
															array('id', $user->id)
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
	 * Print level option
	 */
	public function tag_Level($tag_params, $children) {
		$max_level = 10;

		if ($_SESSION['level'] < 10)
			$max_level = $_SESSION['level'] - 1;

		$selected = -1;
		if (isset($tag_params['selected']))
			$selected = $tag_params['selected'];

		// create template
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->parent->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('users_level.xml', $this->parent->path.'templates/');
		}

		$template->setMappedModule($this->parent->name);

		for ($i = 0; $i <= $max_level; $i++) {
			$params = array(
						'level'		=> $i,
						'selected'	=> $selected
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Verify user account using code specified in either tag_params or _REQUEST.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function verifyAccount($tag_params, $children) {
		$manager = UserManager::getInstance();
		$verification_manager = UserVerificationManager::getInstance();

		$result = false;
		$username = null;
		$code = null;
		$verification = null;

		// get username
		if (isset($tag_params['username']))
			$username = escape_chars($tag_params['username']);

		if (isset($_REQUEST['username']) && is_null($username))
			$username = escape_chars($_REQUEST['username']);

		// get verification code
		if (isset($tag_params['code']))
			$code = escape_chars($tag_params['code']);

		if (isset($_REQUEST['code']) && is_null($code))
			$code = escape_chars($_REQUEST['code']);

		if (is_null($username) || is_null($code))
			return;

		// get user from database
		$user = $manager->getSingleItem($manager->getFieldNames(), array('username' => $username));

		if (is_object($user))
			$verification = $verification_manager->getSingleItem(
									$verification_manager->getFieldNames(),
									array(
										'user'	=> $user->id,
										'code'	=> $code
									));

		// data matches, mark account as verified
		if (is_object($verification)) {
			$manager->verify_user($user->username);
			$verification_manager->deleteData(array('user' => $user->id));

			// automatically log user in
			$manager->login_user($user->username);
		}
	}
}

?>
