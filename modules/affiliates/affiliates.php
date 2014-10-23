<?php

/**
 * Shop Referrals Support
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */
use Core\Module;

require_once('units/affiliate_manager.php');
require_once('units/referrals_manager.php');


class affiliates extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$referrals_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_affiliates'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);
			$referrals_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_manage_affiliates'),
								url_GetFromFilePath($this->path.'images/affiliates.svg'),

								window_Open( // on click open window
											'affiliates',
											700,
											$this->getLanguageConstant('title_affiliates'),
											true, true,
											backend_UrlMake($this->name, 'affiliates')
										),
								$level=10
							));
			$referrals_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_referral_urls'),
								url_GetFromFilePath($this->path.'images/referrals.svg'),

								window_Open( // on click open window
											'referrals',
											750,
											$this->getLanguageConstant('title_referrals'),
											true, true,
											backend_UrlMake($this->name, 'referrals')
										),
								$level=4
							));
			$referrals_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_information'),
								url_GetFromFilePath($this->path.'images/information.svg'),

								window_Open( // on click open window
											'affiliate_information',
											400,
											$this->getLanguageConstant('title_affiliate_information'),
											true, true,
											backend_UrlMake($this->name, 'information')
										),
								$level=4
							));

			$backend->addMenu($this->name, $referrals_menu);
		}

		if (isset($_REQUEST['affiliate']) && $section != 'backend' && $section != 'backend_module')
			$this->createReferral();
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
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'affiliates':
					$this->showAffiliates();
					break;

				case 'affiliate_add':
					$this->addAffiliate();
					break;

				case 'affiliate_change':
					$this->changeAffiliate();
					break;

				case 'affiliate_save':
					$this->saveAffiliate();
					break;

				case 'affiliate_delete':
					$this->deleteAffiliate();
					break;

				case 'affiliate_delete_commit':
					$this->deleteAffiliate_Commit();
					break;

				case 'referrals':
					$this->showReferrals();
					break;

				case 'information':
					$this->showAffiliateInformation();
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

		$sql = "CREATE TABLE IF NOT EXISTS `affiliates` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`uid` varchar(30) NOT NULL,
					`name` varchar(50) NOT NULL,
					`user` int(11) NOT NULL,
					`clicks` int(11) NOT NULL DEFAULT '0',
					`conversions` int(11) NOT NULL DEFAULT '0',
					`active` tinyint(1) NOT NULL DEFAULT '1',
					`default` tinyint(1) NOT NULL DEFAULT '0',
					PRIMARY KEY (`id`),
					INDEX(`uid`),
					INDEX(`user`),
					INDEX(`default`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=1 ;";
		$db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `affiliate_referrals` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`affiliate` int(11) NOT NULL,
					`url` varchar(255) NOT NULL,
					`landing` varchar(255) NOT NULL,
					`transaction` int(11) NOT NULL,
					`conversion` tinyint(1) NOT NULL,
					PRIMARY KEY (`id`),
					INDEX(`affiliate`),
					INDEX(`url`),
					INDEX(`landing`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=1 ;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('affiliates', 'affiliate_referrals');
		$db->drop_tables($tables);
	}

	/**
	 * Show list of affiliates
	 */
	private function showAffiliates() {
		if ($_SESSION['level'] < 10)
			die('Access denied!');

		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new_affiliate'),
										'affiliates_new', 370,
										$this->getLanguageConstant('title_affiliates_add'),
										true, false,
										$this->name,
										'affiliate_add'
									),
					);

		$template->registerTagHandler('_affiliate_list', $this, 'tag_AffiliateList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new affiliate.
	 */
	private function addAffiliate() {
		if ($_SESSION['level'] < 10)
			die('Access denied!');

		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		// connect tag handlers
		$user_manager = UserManager::getInstance();
		$template->registerTagHandler('_user_list', $user_manager, 'tag_UserList');

		// generate UID
		$uid = uniqid();

		$params = array(
					'uid'			=> $uid,
					'form_action'	=> backend_UrlMake($this->name, 'affiliate_save'),
					'cancel_action'	=> window_Close('affiliates_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();

	}

	/**
	 * Show form for changing existing affiliate.
	 */
	private function changeAffiliate() {
		if ($_SESSION['level'] < 10)
			die('Access denied!');

		$id = fix_id($_REQUEST['id']);
		$manager = AffiliatesManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			// connect tag handlers
			$user_manager = UserManager::getInstance();
			$template->registerTagHandler('_user_list', $user_manager, 'tag_UserList');

			$params = array(
						'id'			=> $item->id,
						'uid'			=> $item->uid,
						'name'			=> $item->name,
						'user'			=> $item->user,
						'active'		=> $item->active,
						'default'		=> $item->default,
						'form_action'	=> backend_UrlMake($this->name, 'affiliate_save'),
						'cancel_action'	=> window_Close('affiliates_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed affiliate data.
	 */
	private function saveAffiliate() {
		if ($_SESSION['level'] < 10)
			die('Access denied!');

		$manager = AffiliatesManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$uid = escape_chars($_REQUEST['uid']);
		$user = fix_id($_REQUEST['user']);
		$name = fix_chars($_REQUEST['name']);
		$active = isset($_REQUEST['active']) && ($_REQUEST['active'] == 'on' || $_REQUEST['active'] == '1') ? 1 : 0;
		$default = isset($_REQUEST['default']) && ($_REQUEST['default'] == 'on' || $_REQUEST['default'] == '1') ? 1 : 0;

		$data = array(
				'name'		=> $name,
				'user'		=> $user,
				'active'	=> $active,
				'default'	=> $default
			);

		$existing_items = $manager->getItems(array('id'), array('uid' => $uid));

		if (is_null($id)) {
			if (count($existing_items) > 0 || empty($uid)) {
				// there are items with existing UID, show error
				$message = 'message_affiliate_not_unique';

			} else {
				// affiliate ID is unique, proceed
				$message = 'message_affiliate_saved';

				$data['uid'] = $uid;
				$manager->insertData($data);
			}

			$window = 'affiliates_new';

		} else {
			// update existing record
			$window = 'affiliates_change';
			$message = 'message_affiliate_saved';
			$manager->updateData($data, array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant($message),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('affiliates'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation from before deleting affiliate.
	 */
	private function deleteAffiliate() {
		if ($_SESSION['level'] < 10)
			die('Access denied!');

		$id = fix_id($_REQUEST['id']);
		$manager = AffiliatesManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_affiliate_delete"),
					'name'			=> $item->name,
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'affiliates_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'affiliate_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('affiliates_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform affiliate removal.
	 */
	private function deleteAffiliate_Commit() {
		if ($_SESSION['level'] < 10)
			die('Access denied!');

		$id = fix_id($_REQUEST['id']);
		$manager = AffiliatesManager::getInstance();
		$referrals_manager = AffiliateReferralsManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$referrals_manager->deleteData(array('affiliate' => $id));

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_affiliate_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('affiliates_delete').";".window_ReloadContent('affiliates')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show referalls for current affiliate
	 */
	private function showReferrals() {
		$template = new TemplateHandler('referral_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (isset($_REQUEST['group_by']) && $_REQUEST['group_by'] == 'landing')
			$column = $this->getLanguageConstant('column_landing'); else
			$column = $this->getLanguageConstant('column_url');

		$params = array(
				'column_group_by'	=> $column,
			);

		$template->registerTagHandler('_referral_list', $this, 'tag_ReferralList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show affiliate information.
	 */
	private function showAffiliateInformation() {
		global $url_rewrite;

		$manager = AffiliatesManager::getInstance();
		$user_id = $_SESSION['uid'];
		$affiliate = $manager->getSingleItem($manager->getFieldNames(), array('user' => $user_id));

		if (is_object($affiliate)) {
			$template = new TemplateHandler('information.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			if ($affiliate->clicks > 0)
				$rate = round((100 * $affiliate->conversions) / $affiliate->clicks, 2); else
				$rate = 0;

			$params = array(
					'uid'			=> $affiliate->uid,
					'name'			=> $affiliate->name,
					'clicks'		=> $affiliate->clicks,
					'conversions'	=> $affiliate->conversions,
					'rate'			=> $rate,
					'url_rewrite'	=> $url_rewrite ? 'true' : 'false',
					'cancel_action'	=> window_Close('affiliate_information')
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Register new referral
	 *
	 * @return boolean
	 */
	private function createReferral() {
		$result = false;

		$manager = AffiliatesManager::getInstance();
		$referrals_manager = AffiliateReferralsManager::getInstance();

		// prepare data
		$uid = fix_chars($_REQUEST['affiliate']);
		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
		$base_url = url_GetBaseURL();
		$landing = url_MakeFromArray($_REQUEST);
		$landing = mb_substr($landing, 0, mb_strlen($base_url));

		// get affiliate
		$affiliate = $manager->getSingleItem($manager->getFieldNames(), array('uid' => $uid));

		// if affiliate code is not valid, assign to default affiliate
		if (!is_object($affiliate))
			$affiliate = $manager->getSingleItem($manager->getFieldNames(), array('default' => 1));

		// if affiliate exists, update
		if (is_object($affiliate) && !is_null($referer)) {
			$referral_data = array(
						'url'			=> $referer,
						'landing'		=> $landing,
						'affiliate'		=> $affiliate->id,
						'conversion'	=> 0
					);

			$referrals_manager->insertData($data);
			$id = $referrals_manager->getInsertedID();
			$_SESSION['referral_id'] = $id;

			// increase referrals counter
			$manager->updateData(
						array('clicks' => '`clicks` + 1'),
						array('id' => $affiliate->id)
					);

			$result = true;
		}

		return result;
	}

	/**
	 * Mark a referral as conversion.
	 */
	public function convertReferral($id) {
		$manager = AffiliatesManager::getInstance();
		$referrals_manager = AffiliateReferralsManager::getInstance();

		// get referral entry by specified id
		$referral = $referrals_manager->getSingleItem(
								$referrals_manager->getFieldNames(),
								array('id' => $id)
							);

		// referral entry is valid, update affiliate and referral record
		if (is_object($referral)) {
			$manager->updateData(
						array('conversions' => '`conversions` + 1'),
						array('id' => $referral->affiliate)
					);
			$referrals_manager->updateData(
						array('conversion' => 1),
						array('id' => $referral->id)
					);
		}
	}

	/**
	 * Tag handler for affiliate list.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_AffiliateList($tag_params, $children) {
		$manager = AffiliatesManager::getInstance();
		$conditions = array();

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// load template
		$template = $this->loadTemplate($tag_params, 'list_item.xml');

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				if ($item->clicks > 0)
					$rate = round((100 * $item->conversions) / $item->clicks, 2); else
					$rate = 0;

				$params = array(
						'id'			=> $item->id,
						'uid'			=> $item->uid,
						'name'			=> $item->name,
						'clicks'		=> $item->clicks,
						'conversions'	=> $item->conversions,
						'rate'			=> $rate,
						'item_change'	=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													'affiliates_change', 	// window id
													370,				// width
													$this->getLanguageConstant('title_affiliates_change'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'affiliate_change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'	=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													'affiliates_delete', 	// window id
													400,				// width
													$this->getLanguageConstant('title_affiliates_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'affiliate_delete'),
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
	 * Handle drawing referral list tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ReferralList($tag_params, $children) {
		$manager = AffiliateReferralsManager::getInstance();
		$conditions = array();
	}
}

?>
