<?php

/**
 * SMTP mailer extension. This class provides mailing
 * through SMTP servers with or without SSL support. This
 * extension is usually slower than native system one
 * but provides additional flexibility.
 *
 * Author: Mladen Mijatov
 */

class ContactForm_SmtpMailer extends ContactForm_SystemMailer {
	protected $name = 'smtp';

	private $host = 'localhost';
	private $port = 25;
	private $socket = null;
	private $username = null;
	private $password = null;

	// socket connection timeout
	const TIMEOUT = 10;
	
	public function __construct($language) {
		parent::__construct($language);
	}

	/**
	 * Get localized name for this mailer.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->language->getText('mailer_smtp');
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
		$result = true;

		// connect and authenticate
		if ($this->connect() && $this->authenticate()) {
			// set sender
			$this->send_command("MAIL FROM: {$this->sender}");
			$result &= $this->validate_response('250');

			// set recipients
			foreach ($this->recipients as $recipient)
				$this->send_command("RCPT TO: {$recipient}");
				$result &= $this->validate_response('250');

			$result &= $this->send_data($headers, $body);
		}

		return $result;
	}

	/**
	 * Establish connection and handshake.
	 *
	 * @return boolean
	 */
	private function connect() {
		$result = false;

		// try to connect
		$this->socket = fsockopen(
					$this->host,
					$this->port,
					$error_number,
					$error_string,
					self::TIMEOUT
				);

		// send handshake if we are connected
		if ($this->socket != false && $this->validate_response('220')) {
			// send hello to the server
			$this->send_command('EHLO '.$this->host);
			if ($this->validate_response('250'))
				$result = true;
		}

		return $result;
	}

	/**
	 * Send authentication data.
	 *
	 * @return boolean
	 */
	private function authenticate() {
		$valid_username = false;
		$valid_password = false;

		// make sure we actually need to authenticate
		if (is_null($this->username) || is_null($this->password))
			return true;

		// send request for authentication
		$this->send_command('AUTH LOGIN');
		if ($this->validate_response('334')) {
			// send username
			$this->_send_command(base64_encode($this->username));
			$valid_username = $this->validate_response('334');

			// send password
			$this->_send_command(base64_encode($this->password));
			$valid_password = $this->validate_response('235');

			// be polite
			$this->quit();
		}

		return $valid_username && $valid_password;
	}

	/**
	 * Send quit command to the server.
	 */
	private function quit() {
		$this->send_command('QUIT');
		fclose($this->socket);
		$this->socket = null;
	}

	/**
	 * Send data folowed by \r\n.
	 *
	 * @param string $data
	 */
	private function send_command($data) {
		fwrite($this->socket, $data."\r\n");
	}

	/**
	 * Send data followed by the single period.
	 *
	 * @param string $subject
	 * @param string $headers
	 * @param string $body
	 * @return boolean
	 */
	private function send_data($subject, $headers, $body) {
		$result = false;

		// send command for starting data transfer
		$this->send_command('DATA');
		if ($this->validate_response('354')) {
			fwrite($this->socket, $headers."\r\n");
			fwrite($this->socket, "Subject: {$subject}\r\n");
			fwrite($this->socket, "\r\n\r\n");
			fwrite($this->socket, $body."\r\n");
			fwrite($this->socket, ".\r\n");
			$result = $this->validate_response('250');
		}

		return $result;
	}

	/**
	 * Validate server response for any command.
	 *
	 * @param string $expected_code
	 * @return boolean
	 */
	private function validate_response($expected_code) {
		$data = '';

		while (substr($data, 3, 1) != ' ')
			$data = fgets($this->socket, 256);

		return substr($data, 0, 3) == $expected_code;
	}

	/**
	 * Set server parameters.
	 *
	 * @param string $host
	 * @param integer $port
	 * @param boolean $use_ssl
	 */
	public function set_server($host, $port, $use_ssl) {
		$this->host = ($use_ssl ? 'ssl://' : '').$host;
		$this->port = $port;
	}

	/**
	 * Set authentication credentials.
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function set_credentials($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}
}

?>
