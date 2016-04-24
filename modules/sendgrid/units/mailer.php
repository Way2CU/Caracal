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
	private $variables = array();

	protected $subject;
	protected $variables;
	protected $plain_body;
	protected $html_body;
	protected $sender;
	protected $sender_address;
	protected $recipients;
	protected $recipient_addresses;
	protected $recipients_cc;
	protected $recipients_bcc;
	protected $attachments;
	protected $attachment_names;
	protected $inline_attachments;
	protected $headers;

	public function __construct($language, $api_key) {
		$this->language = $language;
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
		$this->subject = null;
		$this->variables = array();
		$this->plain_body = null;
		$this->html_body = null;
		$this->sender = null;
		$this->recipients = array();
		$this->recipients_cc = array();
		$this->recipients_bcc = array();
		$this->attachments = array();
		$this->attachment_names = array();
		$this->inline_attachments = array();
		$this->headers = array();
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
	}

	/**
	 * Set sender of message.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function set_sender($address, $name=null) {
		if (is_null($name))
			$recipient = $address; else
			$recipient = $this->encode_string($name).' <'.$address.'>';

		$this->recipient_addresses[] = $address;
		$this->recipients[] = $recipient;
	}

	/**
	 * Add recipient for the message. Recipient name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_recipient($address, $name=null) {
		if (is_null($name))
			$recipient = $address; else
			$recipient = $this->encode_string($name).' <'.$address.'>';

		$this->recipients_cc[] = $recipient;
	}

	/**
	 * Add recipient to carbon copy (CC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_cc_recipient($address, $name=null) {
		if (is_null($name))
			$recipient = $address; else
			$recipient = $this->encode_string($name).' <'.$address.'>';

		$this->recipients_cc[] = $recipient;
	}

	/**
	 * Add recipient to blind carbon copy (BCC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_bcc_recipient($address, $name=null) {
		if (is_null($name))
			$recipient = $address; else
			$recipient = $this->encode_string($name).' <'.$address.'>';

		$this->recipients_bcc[] = $recipient;
	}

	/**
	 * Add custom header string.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function add_header_string($key, $value) {
		$this->headers[$key] = $value;
	}

	/**
	 * Set message subject.
	 *
	 * @param string $subject
	 */
	public function set_subject($subject) {
		$this->subject = $subject;
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
		$this->plain_body = $plain_body;
		if (!is_null($html_body) && !empty($html_body))
			$this->html_body = $html_body;
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
		if (!$inline)
			$this->attachments[] = $file_name; else
			$this->inline_attachments[] = $file_name;

		if (!is_null($attached_name))
			$this->attachment_names[$file_name] = $attached_name;
	}
}

?>
