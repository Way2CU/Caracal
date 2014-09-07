<?php

/**
 * Base class for contact form mailer. Mailers provide
 * different ways of sending email messages alowing user
 * greater control over delivery method.
 *
 * Author: Mladen Mijatov
 */

abstract class ContactForm_Mailer {
	/**
	 * Get localized name.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Prepare mailer for sending new message. This function
	 * is ideal place to prepare to initialize internal storage
	 * variables. No connections should be established at this
	 * point to avoid potential timeouts.
	 */
	abstract public function start_message();

	/**
	 * Finalize message and send it to specified addresses.
	 * 
	 * Note: Before sending, you *must* check if contact_form
	 * function detectBots returns false.
	 *
	 * @return boolean
	 */
	abstract public function send();

	/**
	 * Set sender of message.
	 *
	 * @param string $address
	 * @param string $name
	 */
	abstract public function set_sender($address, $name=null);

	/**
	 * Add recipient for the message. Recipient name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	abstract public function add_recipient($address, $name=null);

	/**
	 * Add recipient to carbon copy (CC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	abstract public function add_cc_recipient($address, $name=null);

	/**
	 * Add recipient to blind carbon copy (BCC) field. Name is optional.
	 *
	 * @param string $address
	 * @param string $name
	 */
	abstract public function add_bcc_recipient($address, $name=null);

	/**
	 * Set message subject.
	 *
	 * @param string $subject
	 */
	abstract public function set_subject($subject);

	/**
	 * Set variables to be replaced in subject and body.
	 *
	 * @param array $params
	 */
	abstract public function set_variables($variables);

	/**
	 * Set message body. HTML body is optional.
	 *
	 * @param string $plain_body
	 * @param string $html_body
	 */
	abstract public function set_body($plain_body, $html_body=null);

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
	abstract public function attach_file($file_name, $attached_name=null, $inline=false);
}

?>
