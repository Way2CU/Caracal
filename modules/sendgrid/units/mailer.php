<?php

/**
 * SendGrid Mailer
 *
 * Author: Mladen Mijatov
 */

namespace Modules\SendGrid;
use Core\Events;
use ContactForm_Mailer;


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
		return $this->language->get_text('mailer_title');
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
				'to'       => $this->recipients,
				'toname'   => $this->recipients_name,
				'cc'       => $this->recipients_cc,
				'ccname'   => $this->recipients_cc_name,
				'bcc'      => $this->recipients_bcc,
				'bccname'  => $this->recipients_bcc_name,
				'from'     => $this->sender,
				'fromname' => $this->sender_name,
				'subject'  => $this->subject,
				'text'     => $this->plain_body,
			);

		if (!is_null($this->reply_to))
			$content['replyto'] = $this->reply_to;

		if (!is_null($this->html_body))
			$content['html'] = $this->html_body;

		// prepare array for sending
		$this->build_query_for_curl($content, $final_content);

		// add attachments
		if (count($this->attachments))
			foreach ($this->attachments as $file) {
				if (array_key_exists($file, $this->attachment_names))
					$name = $this->attachment_names[$file]; else
					$name = basename($file);

				$final_content['files['.$name.']'] = '@'.$file;
			}

		if (count($this->inline_attachments))
			foreach ($this->inline_attachments as $file) {
				if (array_key_exists($file, $this->attachment_names))
					$name = $this->attachment_names[$file]; else
					$name = basename($file);

				$final_content['files['.$name.']'] = '@'.$file;
				$final_content['content['.$name.']'] = $name;
			}

		// add variables
		$count = count($this->recipients);
		$substitutions = array();
		foreach ($this->variables as $key => $value) {
			$list = array_fill(0, $count, $value);
			$substitutions['%'.$key.'%'] = $list;
		}
		$final_content['x-smtpapi'] = json_encode(array('sub' => $substitutions));

		// make api call
		$handle = curl_init(self::API_URL);
		curl_setopt($handle, CURLOPT_POST, true);
		curl_setopt($handle, CURLOPT_HEADER, false);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($handle, CURLOPT_POSTFIELDS, $final_content);
		curl_setopt($handle, CURLOPT_USERAGENT, 'Caracal '._VERSION);
		$response = curl_exec($handle);
		curl_close($handle);

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
	 * Build query parameters for cURL to keep arrays.
	 *
	 * @param array $array
	 * @param pointer $result,
	 * @param string $prefix
	 */
	private function build_query_for_curl($array, &$result=array(), $prefix=null) {
		if (is_object($array))
			$array = get_object_vars($array);

		foreach ($array as $key => $value) {
			$index = isset($prefix) ? $prefix.'['.$key.']' : $key;
			if (is_array($value) || is_object($value))
				$this->build_query_for_curl($value, $result, $index); else
				$result[$index] = $value;
		}
	}
}

?>
