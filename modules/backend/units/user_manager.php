<?php

/**
 * Backend User Manager
 *
 * @author: MeanEYE.rcf
 */

class UserManager {
	private static $_instance;

	var $parent;

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
									)
			);

 		$template->registerTagHandler('_user_list', &$this, 'tag_UserList');
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
 		$template->registerTagHandler('_level', &$this, 'tag_Level');

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
		$manager = AdministratorManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('users_change.xml', $this->parent->path.'templates/');
	 		$template->registerTagHandler('_level', &$this, 'tag_Level');

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
	 * Save changed or new user data
	 */
	private function saveUser() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;

		// get manager instance
		$manager = AdministratorManager::getInstance();

		// grab new user data
		$data = array(
				'fullname'	=> fix_chars($_REQUEST['fullname']),
				'username'	=> fix_chars($_REQUEST['username']),
				'email'		=> fix_chars($_REQUEST['email']),
				'level'		=> fix_id($_REQUEST['level']),
			);

		if (isset($_REQUEST['password']))
			$data['password'] = fix_chars($_REQUEST['password']);

		if (isset($_REQUEST['new_password']) && !empty($_REQUEST['new_password']))
			$data['password'] = fix_chars($_REQUEST['new_password']);

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
		$manager = AdministratorManager::getInstance();

		// grab new user data
		if (defined('_AJAX_REQUEST')) 
			$source = $_REQUEST; else
			$source = $tag_params;

		$data = array(
				'fullname'	=> fix_chars($source['fullname']),
				'username'	=> fix_chars($source['username']),
				'password'	=> fix_chars($source['password']),
				'email'		=> fix_chars($source['email']),
				'level'		=> 0,
			);

		// check for duplicates
		$duplicate_users = $manager->getItems(array('id'), array('username' => $data['username']));
		$duplicate_emails = $manager->getItems(array('id'), array('email' => $data['email']));

		if (class_exists('captcha')) {
			$captcha = captcha::getInstance();
			if (!$captcha->isCaptchaValid($source['captcha'])) {
				$result['error'] = true;
				$result['message'] = $this->parent->getLanguageConstant('message_users_error_captcha');
			}
		}

		if (!$result['error'])
			if (count($duplicate_users) > 0 || count($duplicate_emails) > 0) {
				// we found a duplicate user
				$result['error'] = true;
				$result['message'] = $this->parent->getLanguageConstant('message_users_error_duplicate');

			} else {
				// insert data
				$manager->insertData($data);
				$result['message'] = $this->parent->getLanguageConstant('message_users_created');
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
	}

	/**
	 * Send password to users email
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function recoverPasswordByEmail($tag_params, $children) {
		$result = false;
		$captcha_valid = false;
		$manager = AdministratorManager::getInstance();

		$subject = null;
		$body = null;

		$message_success = 'Mail sent!';
		$message_error = 'There was a problem sending email!';
		$message_bad_captcha = 'Invalid captcha value!';

		foreach ($children as $tag) {
			switch ($tag->tagName) {
				case 'subject':
					$subject = fix_chars($tag->tagData);
					break;

				case 'body':
					$body = fix_chars($tag->tagData);
					break;

				case 'message_success':
					$message_success = fix_chars($tag->tagData);
					break;

				case 'message_error':
					$message_error = fix_chars($tag->tagData);
					break;

				case 'message_bad_captcha':
					$message_bad_captcha = fix_chars($tag->tagData);
					break;
			}
		}

		if (class_exists('captcha')) {
			$captcha = captcha::getInstance();
			$captcha_valid = $captcha->isCaptchaValid($tag_params['captcha']);

		} else {
			$captcha_valid = true;
		}

		// send email
		if ($captcha_valid && class_exists('contact_form')) {
			$contact_form = contact_form::getInstance();
			$conditions = array();

			if (isset($tag_params['username']))
				$conditions['username'] = fix_chars($tag_params['username']);	

			if (isset($tag_params['email']))
				$conditions['email'] = fix_chars($tag_params['email']);

			$user = $manager->getSingleItem($manager->getFieldNames(), $conditions);

			if (is_object($user)) {
				$search = array(
							'%username%',
							'%password%',
							'%email%',
							'%fullname%'
						);

				$replace = array(
							$user->username,
							$user->password,
							$user->email,
							$user->fullname
						);

				// replace string
				$body = str_replace($search, $replace, $body);

				// send email
				$result = $contact_form->sendFromModule($user->email, $subject, $body);

				// show result
				if (isset($tag_params['show_result']))
					print $result ? $message_success : $message_error;
			}
		} else {
			if (isset($tag_params['show_result']))
				print $message_bad_captcha;
		}
		

		return $result;
	}

	/**
	 * Show confirmation form for user removal
	 */
	private function deleteUser() {
		$id = fix_id($_REQUEST['id']);
		$manager = AdministratorManager::getInstance();

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
		$manager = AdministratorManager::getInstance();

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
		$old_password = md5(SessionManager::SALT.$_REQUEST['old_password']);
		$new_password = md5(SessionManager::SALE.$_REQUEST['new_password']);
	}

	/**
	 * Handle drawing user list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_UserList($tag_params, $children) {
		$admin_manager = AdministratorManager::getInstance();

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
							'id'		=> $user->id,
							'fullname'	=> $user->fullname,
							'username'	=> $user->username,
							'level'		=> $user->level,
							'selected'	=> isset($tag_params['selected']) && ($tag_params['selected'] == $user->id),
							'item_change'	=> url_MakeHyperlink(
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
							'item_delete'	=> url_MakeHyperlink(
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
}

?>
