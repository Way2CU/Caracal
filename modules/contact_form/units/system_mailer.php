<?php

/**
 * Simplest mailing extension. This class provides mailing
 * capabilities using system's "sendmail" command.
 *
 * Author: Mladen Mijatov
 */

class ContactForm_SystemMailer extends ContactForm_Mailer {
	protected $language;
	protected $name = 'system';

	private $subject;
	private $variables;
	private $plain_body;
	private $html_body;
	private $sender;
	private $recipients;
	private $recipients_cc;
	private $recipients_bcc;
	private $attachments;
	private $inline_attachments;

	public function __construct($language) {
		// store language handler for later use
		$this->language = $language;
	}

	/**
	 * Get localized name for this mailer.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->language->getText('mailer_system');
	}

	/**
	 * BASE64 string encode.
	 *
	 * @param string $string
	 * @return string
	 */
	private function encode_string($string) {
		return "=?utf-8?B?".base64_encode($string)."?=";
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
	 * Prepare headers for sending.
	 *
	 * @param array $headers
	 * @return string
	 */
	private function prepare_headers($headers) {
		$result = array();

		foreach ($headers as $key => $value)
			$result[] = "{$key}: {$value}";

		return implode('\n', $result);
	}

	/**
	 * Function that performs actuall sending of message.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param array $headers
	 * @param string $content
	 * @return boolean
	 */
	protected function perform_send($to, $subject, $headers, $content) {
		$result = false;

		// send email
		$result = mail($to, $subject, $content, $headers);

		return $result;
	}

	/**
	 * Prepare mailer for sending new message.
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
		$this->inline_attachments = array();
	}

	/**
	 * Finalize message and send it to specified addresses.
	 *
	 * @return boolean
	 */
	public function send() {
		$result = false;
		$content = '';
		$headers = array();
		$contact_form = contact_form::getInstance();

		// make sure we are not being scammed
		if ($contact_form->detectBots()) {
			trigger_error('Bot detected. Ignoring mail send request!', E_USER_WARNING);
			return $result;
		}

		// ensure we have all the required information
		if (is_null($this->sender) || empty($this->sender)) {
			trigger_error('No sender specified. Can not send email!', E_USER_WARNING);
			return $result;
		}

		if (is_null($this->subject)) {
			trigger_error('No subject specified. Can not send email!', E_USER_WARNING);
			return $result;
		}

		if (is_null($this->plain_body)) {
			trigger_error('Empty message body. Can not send email!', E_USER_WARNING);
			return $result;
		}

		if (count($this->recipients) + count($this->recipients_cc) + count($this->recipients_bcc) == 0) {
			trigger_error('Empty recipient list. Can not send email!', E_USER_WARNING);
			return $result;
		}

		// prepare recipients
		$to = implode(', ', $this->recipients);

		// prepare boundaries
		$boundary = md5(time().'--global--'.(rand() * 10000));
		$content_boundary = md5(time().'--content--'.(rand() * 10000));

		// prepare headers
		$headers['From'] = $this->sender;
		$headers['Cc'] = implode(', ', $this->recipients_cc);
		$headers['Bcc'] = implode(', ', $this->recipients_bcc);

		// add content type to headers
		if (count($attachments) == 0) {
			// no attachments available
			if (is_null($this->html_body)) {
				$headers['Content-Type'] = 'text/plain';
				$headers['Content-Transfer-Encoding'] = 'base64';

			} else {
				$headers['Content-Type'] = "multipart/alternative; boundary={$boundary}";
			}

		} else {
			// set proper content type for message with attachments
			$headers['Content-Type'] = "multipart/mixed; boundary={$boundary}";
		}

		// prepare content
		$plain_text_body = $this->replace_fields($this->plain_body);
		$html_body = $this->replace_fields($this->html_body);
		$subject = $this->replace_fields($this->subject);

		// create content
		if ($headers['Content-Type'] == 'text/plain') {
			$content .= base64_encode($plain_text_content)."\n";

		} else {
			// starting global boundary
			$content .= "--{$boundary}\n";

			if (is_null($this->html_body)) {
				// add plain text body
				$content .= "Content-Type: text/plain; charset=UTF-8\n";
				$content .= "Content-Transfer-Encoding: base64\n\n";
				$content .= base64_encode($plain_text_body)."\n";

			} else {
				$content .= "Content-Type: multipart/alternative; boundary={$content_boundary}\n\n";

				// add plain text body
				$content .= "--{$content_boundary}\n";
				$content .= "Content-Type: text/plain; charset=UTF-8\n";
				$content .= "Content-Transfer-Encoding: base64\n\n";
				$content .= base64_encode($plain_text_body)."\n";

				// add html body
				$content .= "--{$content_boundary}\n";
				$content .= "Content-Type: text/html; charset=UTF-8\n";
				$content .= "Content-Transfer-Encoding: base64\n\n";
				$content .= base64_encode($html_body)."\n";
				$content .= "--{$content_boundary}--\n";
			}

			// add attachments if needed
			if (count($this->attachments) > 0)
				foreach ($this->attachments as $file => $name)
					$body .= $this->makeAttachment($file, $name, $boundary);

			// add ending boundary
			$body .= "--{$boundary}--\n";
		}

		return $result;
	}

	/**
	 * Set sender of message.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function set_sender($address, $name=null) {
		if (is_null($name))
			$sender = $address; else
			$sender = $this->encode_string($name).' <'.$address.'>';

		$this->sender = $sender;
	}

	/**
	 * Add recipient for the message. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	public function add_recipient($address, $name=null) {
		if (is_null($name))
			$recipient = $address; else
			$recipient = $this->encode_string($name).' <'.$address.'>';

		$this->recipients[] = $recipient;
	}

	/**
	 * Add recipient to carbon copy field (CC). Name is optional.
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
	 * Add recipient to blind carbon copy field (BCC). Name is optional.
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
	 * @param boolean $inline
	 */
	public function attach_file($file_name, $inline=false) {
		if (!$inline)
			$this->attachments[] = $file_name; else
			$this->inline_attachments[] = $file_name;
	}
}

?>
