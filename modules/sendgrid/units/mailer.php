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
	const API_PROTOCOL = 'ssl://';
	const API_HOST = 'api.sendgrid.com';
	const API_ENDPOINT = '/api/mail.send.json';

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
		$boundary = '--------------------------'.uniqid();
		$chunks = array();

		// create chunks
		$chunks = array_merge($chunks, $this->build_array_chunks('to[]', $this->recipients));
		$chunks = array_merge($chunks, $this->build_array_chunks('toname[]', $this->recipients_name));
		$chunks = array_merge($chunks, $this->build_array_chunks('cc[]', $this->recipients_cc));
		$chunks = array_merge($chunks, $this->build_array_chunks('ccname[]', $this->recipients_cc_name));
		$chunks = array_merge($chunks, $this->build_array_chunks('bcc[]', $this->recipients_bcc));
		$chunks = array_merge($chunks, $this->build_array_chunks('bccname[]', $this->recipients_bcc_name));

		$chunks[] = $this->build_chunk('from', $this->sender);
		$chunks[] = $this->build_chunk('fromname', $this->sender_name);

		if (!is_null($this->reply_to))
			$chunks[] = $this->build_chunk('replyto', $this->reply_to);

		$chunks[] = $this->build_chunk('subject', $this->subject);
		$chunks[] = $this->build_chunk('text', $this->plain_body);

		if (!is_null($this->html_body))
			$chunks[] = $this->build_chunk('html', $this->html_body);

		$chunks[] = $this->build_chunk('headers', json_encode($this->headers));

		// add attachments
		if (count($this->attachments) > 0)
			foreach ($this->attachments as $file) {
				if (in_array($file, $this->attachment_names))
					$name = $this->attachment_names[$file]; else
					$name = basename($file);

				$chunks[] = $this->build_file_chunk('files', $file, $name);
			}

		if (count($this->inline_attachments) > 0)
			foreach ($this->inline_attachments as $file) {
				if (in_array($file, $this->attachment_names))
					$name = $this->attachment_names[$file]; else
					$name = basename($file);

				$chunks[] = $this->build_file_chunk('content', $file, $name);
			}

		// prepare content
		$content = '';

		foreach ($chunks as $chunk) {
			$metadata = $chunk['metadata'];
			$chunk_content = $chunk['content'];

			// add boundary
			$content .= $boundary."\r\n";

			foreach ($metadata as $key => $value)
				$content .= "{$key}: {$value}\r\n";

			$content .= "\r\n".$chunk_content."\r\n";
		}

		// closing boundary
		$content .= $boundary."--\r\n";

		// prepare connection headers
		$headers = array(
				'Host'           => self::API_HOST,
				'Accept'         => '*/*',
				'Authorization'  => 'bearer '.$this->api_key,
				'Content-Length' => strlen($content),
				'Expect'         => '100-continue',
				'Content-Type'   => 'multipart/form-data; boundary='.$boundary,
			);

		$header_string = "POST ".self::API_ENDPOINT." HTTP/1.1\r\n";
		foreach ($headers as $key => $value)
			$header_string .= "{$key}: {$value}\r\n";

		$socket = fsockopen(self::API_PROTOCOL.self::API_HOST, 443, $error_number, $error_string, 5);

		// make sure we have a connection
		if (!$socket || $error_number != 0) {
			trigger_error("SendGrid: {$error_number} - {$error_string}", E_USER_WARNING);
			return false;
		}

		// send and receive data
		fwrite($socket, $header_string."\r\n");
		fflush($socket);
		$raw_data = fgets($socket);

		// make sure server gave us green light
		if (trim($raw_data) == 'HTTP/1.1 100 Continue') {
			// send email content
			fwrite($socket, $content);
			fflush($socket);

			// receive response
			$response_header = '';
			$response_content = '';
			$target = 0;
			while (($buffer = fgets($socket)) !== false) {
				if ($buffer == "\r\n")
					$target++;

				// store response
				switch ($target) {
					case 0:
						$response_header .= $buffer."\n";
						break;

					case 1:
						$response_content .= $buffer."\n";
						break;
				}

				// break on end
				if ($target == 2)
					break;
			}

			// parse response
			$response = json_decode($response_content);
			if (is_object($response))
				$result = $response->message == 'success';

		} else {
			error_log('SendGrid: Aborting send! Server responded '.$raw_data, E_USER_NOTICE);
			return false;
		}

		// close socket
		fclose($socket);

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
