<?php

/**
 * SendGrid Mailer
 *
 * Author: Mladen Mijatov
 */

namespace Modules\SendGrid;

use Core\Events;
use \ContactForm_Mailer as ContactForm_Mailer;


class Mailer extends ContactForm_Mailer {
	private $language;
	private $mailer = null;
	private $message = null;
	private $variables = array();

	public function __construct($language, $api_key) {
		$this->language = $language;
		$this->mailer = new \SendGrid($api_key);
	}

	/**
	 * Get localized name.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->language->getText('mailer_title');
	}

	/**
	 * Prepare mailer for sending new message. This function
	 * is ideal place to prepare to initialize internal storage
	 * variables. No connections should be established at this
	 * point to avoid potential timeouts.
	 */
	public function start_message() {
		$this->message = new \SendGrid\Email();
	}

	/**
	 * Finalize message and send it to specified addresses.
	 *
	 * Note: Before sending, you *must* check if contact_form
	 * function detectBots returns false.
	 *
	 * @return boolean
	 */
	public function send() {
		// add variables
		foreach ($this->variables as $key => $value)
			$this->message->addSubstitution("%{$key}%", array($value));

		// send message
		$raw_response = $this->mailer->send($this->message);
		$response = json_decode($raw_response);

		// prepare result
		$result = $response->message == 'success';

		// trigger event
		if ($result)
			Events::trigger(
				'contact_form',
				'email-sent',
				'sendgrid',
				$this->message->to,
				$this->message->subject,
				$this->variables
			);

		return $result;
	}

	/**
	 * Set sender of message.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function set_sender($address, $name=null) {
		$this->message->setFrom($address);
		if (!is_null($name))
			$this->message->setFromName($name);
	}

	/**
	 * Add recipient for the message. Recipient name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_recipient($address, $name=null) {
		$this->message->addTo($address, $name);
	}

	/**
	 * Add recipient to carbon copy (CC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_cc_recipient($address, $name=null) {
		$this->message->addCc($address, $name);
	}

	/**
	 * Add recipient to blind carbon copy (BCC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_bcc_recipient($address, $name=null) {
		$this->message->addBcc($email, $name);
	}

	/**
	 * Add custom header string.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function add_header_string($key, $value) {
		$this->message->addHeader($key, $value);
	}

	/**
	 * Set message subject.
	 *
	 * @param string $subject
	 */
	public function set_subject($subject) {
		$this->message->setSubject($subject);
	}

	/**
	 * Set variables to be replaced in subject and body.
	 *
	 * @param array $params
	 */
	public function set_variables($variables) {
		$this->variables = $variables;
	}

	/**
	 * Set message body. HTML body is optional.
	 *
	 * @param string $plain_body
	 * @param string $html_body
	 */
	public function set_body($plain_body, $html_body=null) {
		$this->message->setText($plain_body);
		if (!is_null($html_body) && !empty($html_body))
			$this->message->setHtml($html_body);
	}

	/**
	 * Attach file to message. Inline attachments will have image name
	 * set as "Content-ID". Inline files can be addressed in HTML body
	 * like this:
	 *
	 * <img src="cid:example_file.png">
	 *
	 * @param string $file_name
	 * @param string $attached_name
	 * @param boolean $inline
	 */
	public function attach_file($file_name, $attached_name=null, $inline=false) {
		$this->message->addAttachment($file, $attachend_name, $inline ? $attached_name : null);
	}
}

?>
