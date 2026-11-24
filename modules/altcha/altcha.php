<?php

/**
 * ALTCHA Support
 *
 * This module provides self-hosted ALTCHA proof-of-work protection for other
 * modules. It issues signed challenges for the official widget and verifies
 * solutions submitted with forms. No external service is contacted, only the
 * widget script is loaded from CDN.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;
use Core\CSP\Policy as CSP;
use Core\Cache\Manager as Cache;


class Altcha_ConfigurationError extends Exception {}
class Altcha_VerificationError extends Exception {}


class altcha extends Module {
	const WIDGET_HOST = 'https://cdn.jsdelivr.net';
	const WIDGET_URL = 'https://cdn.jsdelivr.net/npm/altcha@3.7.0/dist/main/altcha.i18n.min.js';
	const WIDGET_INTEGRITY = 'sha384-HS7304y0OQTaIv/Az2uzzqLalTclMDfhBwZ3q3km7Xep6SrPnwIfQzCvR9Cy+RiG';

	const ALGORITHM = 'PBKDF2/SHA-256';
	const KEY_LENGTH = 32;
	const KEY_PREFIX_LENGTH = 16;
	const MAXIMUM_COUNTER = 0xffffffff;
	const MAXIMUM_PENDING = 20;
	const SESSION_KEY = 'altcha_challenges';

	private static $_instance;
	private $script_included = false;
	private $default_settings = array(
					'cost'            => 5000,
					'counter_minimum' => 5000,
					'counter_maximum' => 10000,
					'expiration'      => 600
				);
	private $auto_options = array('off', 'onfocus', 'onload', 'onsubmit');
	private $display_options = array('standard', 'bar', 'floating', 'overlay', 'invisible');

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		// allow widget script and workers it creates from bundled code
		CSP::add_value('script-src', self::WIDGET_HOST);

		if (is_null(CSP::get_values('worker-src')))
			CSP::add_value('worker-src', "'self'");
		CSP::add_value('worker-src', 'blob:');

		// connect events
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transfer_control($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->show_widget($params, $children);
					break;

				case 'json_challenge':
					$this->json_challenge();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'settings':
					$this->show_settings();
					break;

				case 'settings_save':
					$this->save_settings();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
		$this->save_setting('hmac_key', bin2hex(random_bytes(32)));

		foreach ($this->default_settings as $name => $value)
			$this->save_setting($name, $value);

		$this->save_setting('auto', $this->auto_options[0]);
		$this->save_setting('display', $this->display_options[0]);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
	}

	/**
	 * Add items to backend menu.
	 */
	public function add_menu_items() {
		$backend = backend::get_instance();

		$altcha_menu = new backend_MenuItem(
				$this->get_language_constant('menu_altcha'),
				$this->path.'images/icon.svg',
				window_Open(
						'altcha_settings',
						400,
						$this->get_language_constant('title_settings'),
						true, true,
						backend_UrlMake($this->name, 'settings')
					),
				$level=6
			);

		$backend->addMenu($this->name, $altcha_menu);
	}

	/**
	 * Check validity of payload submitted by the widget. Each challenge can be
	 * used only once and only from the session it was issued to, regardless of
	 * the verification outcome.
	 *
	 * @param string $payload
	 * @return boolean
	 */
	public function is_valid($payload) {
		$result = false;

		try {
			$data = $this->decode_payload($payload);
			$parameters = $data['challenge']['parameters'];

			$this->verify_signature($parameters, $data['challenge']['signature']);
			$this->claim_challenge($parameters);
			$this->verify_solution($parameters, $data['solution']);
			$result = true;

		} catch (Altcha_ConfigurationError $error) {
			trigger_error('Altcha: '.$error->getMessage(), E_USER_WARNING);

		} catch (Altcha_VerificationError $error) {
			trigger_error('Altcha: '.$error->getMessage(), E_USER_NOTICE);
		}

		return $result;
	}

	/**
	 * Return URL widget is supposed to get challenges from.
	 *
	 * @return string
	 */
	public function get_challenge_url() {
		return URL::make_query($this->name, 'json_challenge');
	}

	/**
	 * Return integer value of specified setting or its default when
	 * settings are not available.
	 *
	 * @param string $name
	 * @return integer
	 */
	private function get_setting($name) {
		$result = $this->default_settings[$name];

		if (isset($this->settings[$name]) && is_numeric($this->settings[$name]))
			$result = max(1, (int) $this->settings[$name]);

		return $result;
	}

	/**
	 * Return widget option from the first source holding one of allowed
	 * values. First allowed value is used when none of them does.
	 *
	 * @param string $name
	 * @param array $allowed
	 * @param array $sources
	 * @return string
	 */
	private function get_option($name, $allowed, $sources) {
		$result = null;

		foreach ($sources as $source)
			if (is_null($result) && isset($source[$name]) && in_array($source[$name], $allowed, true))
				$result = $source[$name];

		if (is_null($result))
			$result = $allowed[0];

		return $result;
	}

	/**
	 * Return key used for signing challenges.
	 *
	 * @return string
	 * @throws Altcha_ConfigurationError
	 */
	private function get_hmac_key() {
		if (empty($this->settings['hmac_key']))
			throw new Altcha_ConfigurationError('Missing signing key. Module needs to be initialized.');

		return $this->settings['hmac_key'];
	}

	/**
	 * Return signature for specified challenge parameters. Parameters are
	 * encoded the same way official libraries do, as JSON object with sorted
	 * keys and without whitespace.
	 *
	 * @param array $parameters
	 * @return string
	 * @throws Altcha_ConfigurationError
	 */
	private function sign_parameters($parameters) {
		ksort($parameters);
		$encoded = json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$result = hash_hmac('sha256', (string) $encoded, $this->get_hmac_key());

		return $result;
	}

	/**
	 * Return raw key derived from challenge parameters and specified counter.
	 *
	 * @param array $parameters
	 * @param integer $counter
	 * @return string
	 */
	private function derive_key($parameters, $counter) {
		$password = hex2bin($parameters['nonce']).pack('N', $counter);
		$result = hash_pbkdf2(
				'sha256',
				$password,
				hex2bin($parameters['salt']),
				$parameters['cost'],
				$parameters['keyLength'],
				true
			);

		return $result;
	}

	/**
	 * Create new signed challenge and remember it in session.
	 *
	 * @return array
	 * @throws Altcha_ConfigurationError
	 */
	private function create_challenge() {
		$counter_minimum = $this->get_setting('counter_minimum');
		$counter_maximum = max($counter_minimum, $this->get_setting('counter_maximum'));
		$counter = random_int($counter_minimum, min($counter_maximum, self::MAXIMUM_COUNTER));

		// prepare parameters, key prefix is added once solution is known
		$parameters = array(
				'algorithm' => self::ALGORITHM,
				'cost'      => $this->get_setting('cost'),
				'expiresAt' => time() + $this->get_setting('expiration'),
				'keyLength' => self::KEY_LENGTH,
				'nonce'     => bin2hex(random_bytes(16)),
				'salt'      => bin2hex(random_bytes(16))
			);

		// solve challenge for secret counter and publish only part of the key
		$derived_key = $this->derive_key($parameters, $counter);
		$parameters['keyPrefix'] = bin2hex(substr($derived_key, 0, self::KEY_PREFIX_LENGTH));
		ksort($parameters);

		$result = array(
				'parameters' => $parameters,
				'signature'  => $this->sign_parameters($parameters)
			);

		// remember challenge so it can be used only once by this session
		$this->store_challenge($parameters['nonce'], $parameters['expiresAt']);

		return $result;
	}

	/**
	 * Store issued challenge in session, dropping expired and excess ones.
	 *
	 * @param string $nonce
	 * @param integer $expires_at
	 */
	private function store_challenge($nonce, $expires_at) {
		$pending = array();
		$now = time();

		if (isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY]))
			foreach ($_SESSION[self::SESSION_KEY] as $pending_nonce => $pending_expires_at)
				if ($pending_expires_at >= $now)
					$pending[$pending_nonce] = $pending_expires_at;

		$pending[$nonce] = $expires_at;
		$_SESSION[self::SESSION_KEY] = array_slice($pending, -self::MAXIMUM_PENDING, null, true);
	}

	/**
	 * Decode payload submitted by the widget and make sure it has
	 * expected structure.
	 *
	 * @param string $payload
	 * @return array
	 * @throws Altcha_VerificationError
	 */
	private function decode_payload($payload) {
		if (!is_string($payload) || empty($payload))
			throw new Altcha_VerificationError('Payload was not submitted.');

		$decoded = base64_decode($payload, true);
		$result = $decoded !== false ? json_decode($decoded, true) : null;

		if (!is_array($result) || !isset($result['challenge'], $result['solution']))
			throw new Altcha_VerificationError('Payload is malformed.');

		$challenge = $result['challenge'];
		$solution = $result['solution'];

		$challenge_valid = is_array($challenge) && isset($challenge['parameters'], $challenge['signature']);
		$challenge_valid = $challenge_valid && is_array($challenge['parameters']) && is_string($challenge['signature']);
		$solution_valid = is_array($solution) && isset($solution['counter'], $solution['derivedKey']);
		$solution_valid = $solution_valid && is_int($solution['counter']) && is_string($solution['derivedKey']);

		if (!$challenge_valid || !$solution_valid)
			throw new Altcha_VerificationError('Payload is missing challenge or solution data.');

		return $result;
	}

	/**
	 * Make sure challenge parameters were issued by this system. Nothing
	 * in parameters can be trusted before this check passes.
	 *
	 * @param array $parameters
	 * @param string $signature
	 * @throws Altcha_ConfigurationError
	 * @throws Altcha_VerificationError
	 */
	private function verify_signature($parameters, $signature) {
		if (!hash_equals($this->sign_parameters($parameters), $signature))
			throw new Altcha_VerificationError('Challenge signature is not valid.');
	}

	/**
	 * Make sure challenge is not expired and was issued to current session,
	 * then remove it from session to prevent repeated use.
	 *
	 * @param array $parameters
	 * @throws Altcha_VerificationError
	 */
	private function claim_challenge($parameters) {
		$nonce = $parameters['nonce'];
		$pending = isset($_SESSION[self::SESSION_KEY]) ? $_SESSION[self::SESSION_KEY] : array();

		if (!is_array($pending) || !isset($pending[$nonce]))
			throw new Altcha_VerificationError('Challenge was not issued to this session or was already used.');

		unset($_SESSION[self::SESSION_KEY][$nonce]);

		if ($parameters['expiresAt'] < time())
			throw new Altcha_VerificationError('Challenge has expired.');
	}

	/**
	 * Make sure submitted solution solves the challenge.
	 *
	 * @param array $parameters
	 * @param array $solution
	 * @throws Altcha_VerificationError
	 */
	private function verify_solution($parameters, $solution) {
		$counter = $solution['counter'];

		if ($counter < 0 || $counter > self::MAXIMUM_COUNTER)
			throw new Altcha_VerificationError('Solution counter is out of range.');

		$derived_key = bin2hex($this->derive_key($parameters, $counter));
		$key_matches = hash_equals($derived_key, strtolower($solution['derivedKey']));
		$prefix_matches = strpos($derived_key, $parameters['keyPrefix']) === 0;

		if (!$key_matches || !$prefix_matches)
			throw new Altcha_VerificationError('Solution is not correct.');
	}

	/**
	 * Print new challenge as JSON object for the widget.
	 */
	private function json_challenge() {
		// every challenge is unique, keep it away from page and browser cache
		Cache::get_instance()->cancel_capture();
		header('Content-Type: application/json');
		header('Cache-Control: no-store');

		try {
			$response = $this->create_challenge();

		} catch (Altcha_ConfigurationError $error) {
			trigger_error('Altcha: '.$error->getMessage(), E_USER_WARNING);
			http_response_code(500);
			$response = array('error' => 'Unable to create challenge.');
		}

		print json_encode($response);
	}

	/**
	 * Print widget tag. Script for the widget is included with the first
	 * widget shown on the page.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function show_widget($tag_params, $children) {
		global $language;

		$template = $this->load_template($tag_params, 'widget.xml');
		$template->set_template_params_from_array($children);

		// options specified in tag take priority over the ones set in backend
		$sources = array($tag_params, $this->settings);

		$params = array(
				'name'             => isset($tag_params['field_name']) ? fix_chars($tag_params['field_name']) : 'altcha',
				'challenge_url'    => $this->get_challenge_url(),
				'language'         => $language,
				'auto'             => $this->get_option('auto', $this->auto_options, $sources),
				'display'          => $this->get_option('display', $this->display_options, $sources),
				'include_script'   => !$this->script_included,
				'script_url'       => self::WIDGET_URL,
				'script_integrity' => self::WIDGET_INTEGRITY
			);
		$this->script_included = true;

		$template->set_local_params($params);
		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Show settings form.
	 */
	private function show_settings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
				'form_action'     => backend_UrlMake($this->name, 'settings_save'),
				'cost'            => $this->get_setting('cost'),
				'counter_minimum' => $this->get_setting('counter_minimum'),
				'counter_maximum' => $this->get_setting('counter_maximum'),
				'expiration'      => $this->get_setting('expiration'),
				'auto'            => $this->get_option('auto', $this->auto_options, array($this->settings)),
				'display'         => $this->get_option('display', $this->display_options, array($this->settings))
			);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new settings.
	 */
	private function save_settings() {
		// collect numbers, falling back to current values for missing ones
		$values = array();
		foreach ($this->default_settings as $name => $default_value) {
			$value = isset($_REQUEST[$name]) ? (int) fix_id($_REQUEST[$name]) : 0;
			$values[$name] = $value > 0 ? $value : $this->get_setting($name);
		}

		// keep counter range valid as challenges can't be created otherwise
		$values['counter_minimum'] = min($values['counter_minimum'], self::MAXIMUM_COUNTER);
		$values['counter_maximum'] = min($values['counter_maximum'], self::MAXIMUM_COUNTER);
		$values['counter_maximum'] = max($values['counter_minimum'], $values['counter_maximum']);

		foreach ($values as $name => $value)
			$this->save_setting($name, $value);

		// save widget options
		$request = fix_chars($_REQUEST);
		$this->save_setting('auto', $this->get_option('auto', $this->auto_options, array($request)));
		$this->save_setting('display', $this->get_option('display', $this->display_options, array($request)));

		// replace signing key, this invalidates challenges not used yet
		if ($this->get_boolean_field('new_key'))
			$this->save_setting('hmac_key', bin2hex(random_bytes(32)));

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
				'message' => $this->get_language_constant('message_saved'),
				'button'  => $this->get_language_constant('close'),
				'action'  => window_Close('altcha_settings')
			);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}
}

?>
