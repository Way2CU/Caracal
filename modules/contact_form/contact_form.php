<?php

/**
 * CONTACT FORM
 *
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */

if (!defined('_DOMAIN') || _DOMAIN !== 'RCF_WebEngine') die ('Direct access to this file is not allowed!');

class contact_form extends Module {

	/**
	 * Constructor
	 *
	 * @return contact_form
	 */
	function contact_form() {
		$this->file = __FILE__;
		parent::Module();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children=array()) {
		switch ($params['action']) {
			case 'post':
				$this->sendMail($level);
				break;
			case 'print_backend_language_selector':
				$this->printLanguageSelector($level, $params);
				break;
			case 'print_backend_fields':
				$this->printFields($level, $params);
				break;
		}

		switch ($params['backend_action']) {
			case 'settings':
				$template = new TemplateHandler("backend_settings.xml", $this->path.'templates/');
				$template->setMappedModule($this->name);
				$template->parse($level);
				break;
			case 'settings_save':
				$this->saveSettings();
				break;
			case 'fields':
				$template = new TemplateHandler("backend_fields.xml", $this->path.'templates/');
				$template->setMappedModule($this->name);
				$template->parse($level);
				break;
			case 'fields_save':
				$this->saveFields();
				break;
			}
	}

	/**
	 * Saves changes made
	 */
	function saveSettings() {
		$mail_to = fix_chars($_REQUEST['mail_to']);
		$mail_subject = fix_chars($_REQUEST['mail_subject']);
		$mail_language = fix_chars($_REQUEST['mail_language']);

		if (isset($_REQUEST['mail_to']))
			$this->saveSetting('mail_to', $mail_to);

		if (isset($_REQUEST['mail_subject']))
			$this->saveSetting('mail_subject', $mail_subject);

		if (isset($_REQUEST['mail_language']))
			$this->saveSetting('mail_language', $mail_language);

		echo $this->language->getText('backend_message_saved');
	}

	/**
	 * Saves changes made
	 */
	function saveFields() {
		$fields = "";
		foreach ($_REQUEST as $key=>$value)
			if (preg_match('/^field_([\w]+)$/i', $key, $matches))
				$fields .= (empty($fields) ? '' : ',').$matches[1];

		$this->saveSetting('fields', $fields);
		echo $this->language->getText('backend_message_saved');
	}

	/**
	 * Print available fields
	 *
	 * @param integer $level
	 * @param array $params
	 */
	function printFields($level, $params) {
		$fields = explode(',', $this->settings['fields']);
		$available = $this->__getAvailableFields();
		$tag_space = str_repeat("\t", $level);

		foreach ($available as $field) {
			$checked = (in_array($field, $fields)) ? ' checked' : '';
			$text = $this->language->getText($field);
			echo "$tag_space<span class=\"label\"><input type=\"checkbox\" class=\"checkbox\" name=\"field_$field\"$checked/>$text</span>\n";
		}
	}

	/**
	 * Prints language selector
	 *
	 * @param integer $level level of tag spacing
	 * @param array $params
	 */
	function printLanguageSelector($level, $params) {
		$languages = $this->language->getLanguages(true);
		$tag_space = str_repeat("\t", $level);
		$selected_language = $this->settings['mail_language'];

		echo $tag_space.'<select name="'.$params['control_name'].'">'."\n";
		foreach ($languages as $short=>$long) {
			$selected = ($selected_language == $short) ? ' selected' : '';
			echo "$tag_space\t<option value=\"$short\"$selected>$long</option>\n";
		}
		echo "$tag_space</select>\n";
	}

	/**
	 * Sends actuall contact mail
	 */
	function sendMail($level) {
		// get fields to include
		$fields = explode(',', $this->settings['fields']);

		if (in_array('captcha', $fields) && ($_SESSION['captcha'] !== $_REQUEST['captcha'])) {
			// if captcha is defined but not correct
			$template = new TemplateHandler("message_captcha.xml", $this->path.'templates/');
			$template->setMappedModule($this->name);
			$template->parse($level);

		} else {
			// prepare message body
			$message = "";
			foreach ($_REQUEST as $field=>$value)
				if (in_array($field, $fields)) {
					$value = fix_chars($value);
					$field_in_lang = $this->language->getText($field, $this->settings['mail_language']);
					$message .= "\n$field_in_lang: $value\n";
				}

			// prepare headers
			preg_match('/^(http:\/\/)?([\w][^\/\.]*\.)?([\w-]+\.[\w]+)$/i', $_SERVER['HTTP_HOST'], $hostname);
			$headers = 'From: WebContact <no-reply@'.$hostname[3].'>';

			// send the mail
			if (mail($this->settings['mail_to'], $this->settings['mail_subject'], $message, $headers))
				$template = new TemplateHandler("message_sent.xml", $this->path.'templates/'); else
				$template = new TemplateHandler("message_error.xml", $this->path.'templates/');

			$template->setMappedModule($this->name);
			$template->parse($level);
		}

	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load CSS and JScript
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');

			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/contact.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
		}

		// get backend module
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$group = new backend_MenuGroup("Contact Form", "", $this->name);
			$group->addItem(new backend_MenuItem("Basic Options", "", "settings", 1));
			$group->addItem(new backend_MenuItem("Form Fields", "", "fields", 1));

			$backend->addMenu($group);
		}
	}

	/**
	 * Event called upon module initialisation
	 */
	function onInit() {
		global $db, $db_active, $default_language;

		if (!$db_active == 1) return;

		preg_match('/^(http:\/\/)?([\w][^\/\.]*\.)?([\w-]+\.[\w]+)$/i', $_SERVER['HTTP_HOST'], $hostname);

		$db->query("INSERT INTO `system_settings`(`module`, `variable`, `value`) VALUES ('$this->name', 'mail_to', 'contact@$hostname[3]');");
		$db->query("INSERT INTO `system_settings`(`module`, `variable`, `value`) VALUES ('$this->name', 'mail_subject', '$hostname[3]: Site Contact');");
		$db->query("INSERT INTO `system_settings`(`module`, `variable`, `value`) VALUES ('$this->name', 'mail_language', '$default_language');");
	}

	/**
	 * Returns available fields for contact
	 *
	 * @return array
	 */
	function __getAvailableFields() {
		$result = array();

		if (is_dir($this->path.'templates/')) {
		    if ($dh = opendir($this->path.'templates/')) {
		        while (($fname = readdir($dh)) !== false)
		        	if (is_file($this->path.'templates/'.$fname) && preg_match('/^field_([\w]+)\.xml$/i', $fname, $matches))
		        		$result[] = $matches[1];
		        closedir($dh);
		    }
		}

		return $result;
	}
}

?>
