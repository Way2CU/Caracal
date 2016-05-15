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
	const API_URL = 'https://api.sendgrid.com/api/mail.send.json';

	private $language;
	private $subject;
	private $variables;
	private $plain_body;
	private $html_body;
	private $sender;
	private $sender_name;
	private $recipients;
	private $recipients_name;
	private $recipients_cc;
	private $recipients_cc_name;
	private $recipients_bcc;
	private $recipients_bcc_name;
	private $reply_to;
	private $attachments;
	private $attachment_names;
	private $inline_attachments;
	private $headers;

	protected $api_key;

	public function __construct($language, $api_key) {
		$this->language = $language;
		$this->api_key = $api_key;
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
		$this->sender_name = null;
		$this->recipients = array();
		$this->recipients_name = array();
		$this->recipients_cc = array();
		$this->recipients_cc_name = array();
		$this->recipients_bcc = array();
		$this->recipients_bcc_name = array();
		$this->reply_to = null;
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
		$result = false;

		// prepare headers
		$headers = array(
				'Authorization: bearer '.$this->api_key
			);

		// prepare content
		$content = array(
				'to[]'      => $this->recipients,
				'toname[]'  => $this->recipients_name,
				'cc[]'      => $this->recipients_cc,
				'ccname[]'  => $this->recipients_cc_name,
				'bcc[]'     => $this->recipients_bcc,
				'bccname[]' => $this->recipients_bcc_name,
				'from'      => $this->sender,
				'fromname'  => $this->sender_name,
				'subject'   => $this->subject,
				'text'      => $this->plain_body,
			);

		if (!is_null($this->reply_to))
			$content['replyto'] = $this->reply_to;

		if (!is_null($this->html_body))
			$content['html'] = $this->html_body;

		$handle = curl_init();
		curl_setopt($handle, CURLOPT_URL, self::API_URL);
		curl_setopt($handle, CURLOPT_POST, 1);
		curl_setopt($handle, CURLOPT_HTTPGET, true);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_TIMEOUT, 10);
		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($handle, CURLOPT_POSTFIELDS, $content);
		curl_setopt($handle, CURLOPT_USERAGENT, 'Caracal '._VERSION);
		$response = curl_exec($handle);

		// parse response
		$response = json_decode($response);
		if (is_object($response))
			$result = $response->message == 'success';

		return $result;
	}

	/**
	 * Set sender of message.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function set_sender($address, $name=null) {
		$this->sender = $address;
		$this->sender_name = $name;
	}

	/**
	 * Add recipient for the message. Recipient name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_recipient($address, $name=null) {
		$this->recipients[] = $address;
		$this->recipients_name[] = is_null($name) ? '' : $name;
	}

	/**
	 * Add recipient to carbon copy (CC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_cc_recipient($address, $name=null) {
		$this->recipients_cc[] = $address;
		$this->recipients_cc_name[] = is_null($name) ? '' : $name;
	}

	/**
	 * Add recipient to blind carbon copy (BCC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_bcc_recipient($address, $name=null) {
		$this->recipients_bcc[] = $address;
		$this->recipients_bcc_name[] = is_null($name) ? '' : $name;
	}

	/**
	 * Add custom header string.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function add_header_string($key, $value) {
		if (strtolower($key) != 'reply-to') {
			// store regular header string
			$this->headers[$key] = $value;

		} else {
			// store address for reply
			$this->reply_to = $value;
		}
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

	/**
	 * Create content chunks from specified array.
	 *
	 * @param string $field_name
	 * @param array $list
	 * @param boolean $encode
	 * @return array
	 */
	private function build_array_chunks($field_name, $list, $encode=true) {
		$result = array();

		foreach ($list as $value) {
			$result[] = $this->build_chunk($field_name, $value, $encode);
		}

		return $result;
	}

	/**
	 * Create chunk from specified file name.
	 *
	 * @param string $field_name
	 * @param string $file_name
	 * @param string $attached_name
	 * @return array
	 */
	private function build_file_chunk($field_name, $file_name, $attached_name) {
		$metadata = array();
		$content = array();

		// get file content
		$data = file_get_contents($file_name);

		// get file mime type
		$handle = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_file($handle, $file_name);
		finfo_close($handle);

		// prepare result
		$metadata['Content-Disposition'] = "form-data; name=\"{$field_name}[{$attached_name}]\"; filename=\"{$attached_name}\"";

		return array(
				'metadata' => $metadata,
				'content'  => $data
			);
	}

	/**
	 * Create chunk from specified data.
	 *
	 * @param string $field_name
	 * @param string $value
	 * @return array
	 */
	private function build_chunk($field_name, $value) {
		// prepare metadata
		$metadata = array();
		$metadata['Content-Disposition'] = "form-data; name=\"{$field_name}\"";

		// create chunk
		return array(
				'metadata' => $metadata,
				'content'  => $value
			);
	}
}

?>
