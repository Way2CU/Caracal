<?php

use Core\Events;
use Library\Mandrill\Mandrill as API;
use Library\Mandrill\Mandrill_Error as API_Error;


class Mandrill_Mailer extends ContactForm_Mailer {
	private $language;
	private $variables;
	private $plain_body;
	private $html_body;
	private $subject;

	private $message = null;
	private $api_key = null;

	private $accepted_status = array('sent', 'queued', 'scheduled');

	public function __construct($language, $api_key) {
		$this->language = $language;
		$this->api_key = $api_key;
	}

	/**
	 * Replace placeholders with field values in specified template.
	 *
	 * @param string $template
	 * @param array $fields
	 * @return string
	 */
	private function replace_fields($template) {
		$keys = array_keys($this->variables);
		$values = array_values($this->variables);

		// preformat keys for replacement
		foreach ($keys as $index => $key)
			$keys[$index] = "%{$key}%";

		// replace field place holders with values
		return str_replace($keys, $values, $template);
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
		$this->message = array();
		$this->variables = array();
		$this->plain_body = null;
		$this->html_body = null;
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
		// replace variables for message body and subject
		$this->message['subject'] = $this->replace_fields($this->subject);
		$this->message['html'] = $this->replace_fields($this->html_body);
		$this->message['text'] = $this->replace_fields($this->plain_body);

		// send message
		try {
			$mandrill = new API($this->api_key);
			$response = $mandrill->messages->send($this->message);

		} catch (API_Error $error) {
			trigger_error(get_class($error).': '.$error->getMessage(), E_USER_WARNING);
		}

		// prepare result
		$result = in_array($response[0]['status'], $this->accepted_status);

		// trigger event
		if ($result)
			Events::trigger(
				'contact_form',
				'email-sent',
				'mandrill',
				$this->message['to'][0]['email'],
				$this->message['subject'],
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
		$this->message['from_email'] = $address;
		if (!is_null($name))
			$this->message['from_name'] = $name;
	}

	/**
	 * Add recipient for the message. Recipient name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_recipient($address, $name=null) {
		// make sure there's a list of recipients
		if (!isset($this->message['to']))
			$this->message['to'] = array();

		// create recipient array
		$recipient = array(
			'email'	=> $address,
			'type'	=> 'to'
		);

		if (!is_null($name))
			$recipient['name'] = $name;

		// add recipient
		$this->message['to'][] = $recipient;
	}

	/**
	 * Add recipient to carbon copy (CC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_cc_recipient($address, $name=null) {
		// make sure there's a list of recipients
		if (!isset($this->message['to']))
			$this->message['to'] = array();

		// create recipient array
		$recipient = array(
			'email'	=> $address,
			'type'	=> 'cc'
		);

		if (!is_null($name))
			$recipient['name'] = $name;

		// add recipient
		$this->message['to'][] = $recipient;
	}

	/**
	 * Add recipient to blind carbon copy (BCC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_bcc_recipient($address, $name=null) {
		// make sure there's a list of recipients
		if (!isset($this->message['to']))
			$this->message['to'] = array();

		// create recipient array
		$recipient = array(
			'email'	=> $address,
			'type'	=> 'bcc'
		);

		if (!is_null($name))
			$recipient['name'] = $name;

		// add recipient
		$this->message['to'][] = $recipient;
	}

	/**
	 * Add custom header string.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function add_header_string($key, $value) {
		// make sure there's a list of recipients
		if (!isset($this->message['headers']))
			$this->message['headers'] = array();

		// add header string
		$this->message['headers'][$key] = $value;
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
		// encode data
		$data = file_get_contents($file_name);
		$data = base64_encode($data);

		// get file mime type
		$handle = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_file($handle, $file_name);
		finfo_close($handle);

		// create structure
		$structure = array(
			'type'		=> $mime_type,
			'name'		=> is_null($attached_name) ? basename($file_name) : $attached_name,
			'content'	=> $data
		);

		// attach file
		if ($inline)
			$this->message['images'][] = $structure; else
			$this->message['attachments'][] = $structure;
	}
}

?>
