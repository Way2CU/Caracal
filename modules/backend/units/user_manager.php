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
	}

	/**
	 * Save changed or new user data
	 */
	private function saveUser() {
	}

	/**
	 * Show confirmation form for user removal
	 */
	private function deleteUser() {
	}

	/**
	 * Perform user removal
	 */
	private function deleteUser_Commit() {
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
							'item_change'	=> url_MakeHyperlink(
													$this->parent->getLanguageConstant('change'),
													window_Open(
														'system_users_change', 	// window id
														730,				// width
														$this->parent->getLanguageConstant('title_edit_page'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->parent->name),
															array('backend_action', 'edit_page'),
															array('id', $user->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->parent->getLanguageConstant('delete'),
													window_Open(
														'system_users_change', // window id
														400,				// width
														$this->parent->getLanguageConstant('title_delete_page'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->parent->name),
															array('backend_action', 'delete_page'),
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
}

?>
