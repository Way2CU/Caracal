<?php

/**
 * Contact form SMTP extension.
 */

class SMTP {
	private $host = 'localhost';
	private $port = 25;

	private $username = null;
	private $password = null;
	private $sender = '';
	private $recipients = array();
	private $subject = '';

	private $socket = null;

	// socket connection timeout
	const TIMEOUT = 10;

	function __construct() {
	}

	/**
	 * Establish connection and handshake.
	 *
	 * @return boolean
	 */
	private function _connect() {
		$result = false;

		// try to connect
		$this->socket = fsockopen(
					$this->host,
					$this->port,
					$error_number,
					$error_string,
					smtp::TIMEOUT
				);

		// send handshake if we are connected
		if ($this->socket != false && $this->_validate_response('220')) {
			// send hello to the server
			$this->_send_command('EHLO '.$this->host);
			if ($this->_validate_response('250'))
				$result = true;
		}

		return $result;
	}

	/**
	 * Send quit command to the server.
	 */
	private function _quit() {
		fwrite($this->socket, 'QUIT'."\r\n");
		fclose($this->socket);
		$this->socket = null;
	}

	/**
	 * Send authentication data.
	 *
	 * @return boolean
	 */
	private function _authenticate() {
		$valid_username = false;
		$valid_password = false;

		// make sure we actually need to authenticate
		if (is_null($this->username) || is_null($this->password))
			return true;

		// send request for authentication
		$this->_send_command('AUTH LOGIN');
		if ($this->_validate_response('334')) {
			// send username
			$this->_send_command(base64_encode($this->username));
			$valid_username = $this->_validate_response('334');

			// send password
			$this->_send_command(base64_encode($this->password));
			$valid_password = $this->_validate_response('235');
		}

		return $valid_username && $valid_password;
	}

	/**
	 * Send data to server.
	 *
	 * @param string $data
	 */
	private function _send($data) {
		fwrite($this->socket, $data);
	}

	/**
	 * Send data folowed by \r\n.
	 *
	 * @param string $data
	 */
	private function _send_command($data) {
		fwrite($this->socket, $data."\r\n");
	}

	/**
	 * Send data followed by the single period.
	 *
	 * @param string $headers
	 * @param string $body
	 * @return boolean
	 */
	private function _send_data($headers, $body) {
		$result = false;

		trigger_error(json_encode($headers));

		// send command for starting data transfer
		$this->_send_command('DATA');
		if ($this->_validate_response('354')) {
			fwrite($this->socket, $headers."\r\n");
			fwrite($this->socket, "Subject: {$this->subject}\r\n");
			fwrite($this->socket, "\r\n\r\n");
			fwrite($this->socket, $body."\r\n");
			fwrite($this->socket, ".\r\n");
			$result = $this->_validate_response('250');
		}

		return $result;
	}

	/**
	 * Validate server response for any command.
	 *
	 * @param string $expected_code
	 * @return boolean
	 */
	private function _validate_response($expected_code) {
		$data = '';

		while (substr($data, 3, 1) != ' ')
			$data = fgets($this->socket, 256);

		return substr($data, 0, 3) == $expected_code;
	}

	/**
	 * Set sender address.
	 *
	 * @return boolean
	 */
	private function _set_sender() {
		$this->_send_command("MAIL FROM: <{$this->sender}>");
		return $this->_validate_response('250');
	}

	/**
	 * Add recipient.
	 *
	 * @return boolean
	 */
	private function _set_recipients() {
		$result = false;

		if (count($this->recipients) > 0) {
			$result = true;
			foreach ($this->recipients as $address) {
				$this->_send_command("RCPT TO: <{$address}>");
				$result &= $this->_validate_response('250');
			}
		}

		return $result;
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

	/**
	 * Set sender address.
	 *
	 * @param string $address
	 */
	public function set_sender($address) {
		$this->sender = $address;
	}

	/**
	 * Set list of recipient addresses.
	 *
	 * @param array $address_list
	 */
	public function set_recipients($address_list) {
		$this->recipients = $address_list;
	}

	/**
 	 * Add address to the recipient list.
	 *
	 * @param string $address
	 */
	public function add_recipient($address) {
		$this->recipients[] = $address;
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
	 * Send email using specified server.
	 *
	 * @param string $headers
	 * @param string $body
	 * @return boolean
	 */
	public function send($headers, $body) {
		$result = true;

		// connect and authenticate
		if ($this->_connect() && $this->_authenticate()) {
			$result &= $this->_set_sender();
			$result &= $this->_set_recipients();
			$result &= $this->_send_data($headers, $body);
		}

		return $result;
	}
}
