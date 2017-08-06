<?php

/**
 * Database and files export archive handle class.
 *
 * This class is used for handling export files. This format was designed to
 * avoid excessive memory and CPU usage while providing convenient and easy
 * to use functions.
 *
 * File format is the following:
 *	[header][version (5 chars)]
 *	[section][data-size][data]
 *
 * Some sections, like DATA, SETTINGS and FILES require key names. In such cases
 * each section has the following format
 *	[section][key-size][key-name][data-size][data]
 *
 * All numbers and ids are written in big endian order and in unsigned
 * format. This is to ensure cross-platform compatibility. Maximum key name
 * name length is 256 characters while maximum data size for each section is
 * 4GB (this is format support, maximum file size still depends on underlying
 * file system).
 *
 * Author: Mladen Mijatov
 */
namespace Core\Exports;
use \Exception;

// make sure constant exists, it was added in 5.4
if (!defined('OPENSSL_RAW_DATA'))
	define('OPENSSL_RAW_DATA', 1);


class InvalidKeyException extends Exception{};
class InvalidExportException extends Exception{};
class UnknownSectionException extends Exception{};


class Section {
	const HEADER = 'Caracal.Backup';
	const FORMAT_VERSION = 0;
	const TIMESTAMP = 1;
	const DESCRIPTION = 2;
	const DOMAIN = 3;
	const KEY_HASH = 4;
	const CIPHER = 5;
	const DATA = 6;
	const SETTINGS = 7;
	const FILES = 8;

	// values until number 50 are reserved for future
	// use by the backup system
}


/**
 * Pack formatting for specific parts.
 */
class Format {
	const SECTION = 'n';  // unsigned short (always 16 bit, big endian byte order)
	const VERSION = 'A5'; // SPACE-padded string
	const KEY_NAME = 'C'; // unsigned char
	const DATA = 'N';     // unsigned long (always 32 bit, big endian byte order)
}


class File {
	const CURRENT_VERSION = '1.0';
	const PREFERRED_CIPHER = 'aes-256-ctr';

	private $handle;
	private $key;
	private $iv_size;
	private $is_new_file;
	private $cipher = self::PREFERRED_CIPHER;
	private $version = self::CURRENT_VERSION;
	private $extended_sections = array(  // list of sections containing keys
			Section::FILES, Section::DATA, Section::SETTINGS
		);

	/**
	 * List of supported export file versions.
	 * @var array
	 */
	private $supported_versions = array('1.0');

	public function __construct($file_name, $key, $verify_hash=true) {
		global $backup_path;

		// increase security a little bit by extending key length through hash function
		// as people don't have a tendency to choose long passwords
		$this->key = hash('sha512', $key, true);

		// try to open file
		if (file_exists($backup_path.$file_name)) {
			$this->handle = fopen($backup_path.$file_name, 'r');
			$this->is_new_file = false;
		} else {
			$this->handle = fopen($backup_path.$file_name, 'w');
			$this->is_new_file = true;
		}

		if ($this->handle === FALSE)
			throw new InvalidExportException('Unable to open specified export file.');

		// create new file header
		if ($this->is_new_file)
			$this->write_header(); else
			$this->read_header($verify_hash);

		// get size of initialization vector to use
		$this->iv_size = openssl_cipher_iv_length($this->cipher);
	}

	/**
	 * Write header to file.
	 */
	private function write_header() {
		fseek($this->handle, 0);
		fwrite($this->handle, Section::HEADER);

		// write format version
		fwrite($this->handle, pack(Format::VERSION, $this->version));

		// store key hash and cipher used for protection
		$hash = hash('sha256', $this->key, true);
		fwrite($this->handle, pack(Format::SECTION, Section::KEY_HASH));
		fwrite($this->handle, pack(Format::DATA, strlen($hash)));
		fwrite($this->handle, $hash);

		fwrite($this->handle, pack(Format::SECTION, Section::CIPHER));
		fwrite($this->handle, pack(Format::DATA, strlen($this->cipher)));
		fwrite($this->handle, $this->cipher);
	}

	/**
	 * Read and verify header.
	 *
	 * @param boolean $verify_hash
	 */
	private function read_header($verify_hash) {
		fseek($this->handle, 0);
		$header = fread($this->handle, strlen(Section::HEADER));

		// make sure out header strings match
		if ($header != Section::HEADER)
			throw new InvalidExportException('File header mismatch!');

		// read version
		$version_length = strlen(pack(Format::VERSION, ''));
		$unpacked_data = unpack(Format::VERSION, fread($this->handle, $version_length));
		$this->version = trim(array_pop($unpacked_data));

		if (!in_array($this->version, $this->supported_versions))
			throw new InvalidExportException('Unsupported version: '.$this->version);

		// read encryption information
		if ($this->find_section(Section::KEY_HASH)) {
			$hash_size = $this->read_size(Format::DATA);
			$hash = fread($this->handle, $hash_size);

			if ($verify_hash && $hash != hash('sha256', $this->key, true))
				throw new InvalidKeyException('Hash mismatch!');
		}

		if ($this->find_section(Section::CIPHER)) {
			$cipher_size = $this->read_size(Format::DATA);
			$this->cipher = fread($this->handle, $cipher_size);
		}
	}

	/**
	 * Read size from the archive for specified format.
	 *
	 * @param string $format
	 * @return integer
	 */
	private function read_size($format) {
		$size = strlen(pack($format, 0));
		$unpacked_data = unpack($format, fread($this->handle, $size));
		return array_pop($unpacked_data);
	}

	/**
	 * Locate beginning of specified section. Returns boolean value
	 * denoting if section has been found. File pointer position will
	 * be left at position for reading data.
	 *
	 * @param integer $section
	 * @param string $key_name
	 * @return boolean
	 */
	private function find_section($section, $key_name=null) {
		$result = false;
		$section_size = strlen(pack(Format::SECTION, 0));

		// jump to beginning of all sections
		$stats = fstat($this->handle);
		fseek($this->handle, strlen(Section::HEADER) + strlen(pack(Format::VERSION, '')));

		// search through sections
		while (True) {
			$unpacked_data = unpack(Format::SECTION, fread($this->handle, $section_size));
			$current_section = array_pop($unpacked_data);
			$current_key_name = null;

			// read key name if needed
			if (in_array($current_section, $this->extended_sections)) {
				$key_size = $this->read_size(Format::KEY_NAME);
				$current_key_name = fread($this->handle, $key_size);
			}

			// bail if we found requested section
			if ($current_section == $section && (is_null($key_name) || $current_key_name == $key_name)) {
				$result = true;
				break;
			}

			// move file pointer forward by data size
			$data_size = $this->read_size(Format::DATA);
			fseek($this->handle, $data_size, SEEK_CUR);

			// make sure we stop parsing after we pass end of bounds
			if (ftell($this->handle) >= $stats['size'])
				break;
		}

		return $result;
	}

	/**
	 * Write data to specified section in the file. Some sections
	 * such as DATA, SETTINGS and FILES require `$key_name` to be specified.
	 *
	 * Return value denotes number of bytes written.
	 *
	 * @param integer $section
	 * @param string $raw_data
	 * @param string $key_name
	 * @param string $encrypted
	 * @return integer
	 */
	public function write($section, $raw_data, $key_name=null, $encrypted=true) {
		if ($this->handle === FALSE)
			throw new InvalidExportException('No export file open.');

		// protect data
		if ($encrypted) {
			$data_iv = openssl_random_pseudo_bytes($this->iv_size);
			$data = openssl_encrypt($raw_data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $data_iv);
		} else {
			$data_iv = '';
			$data = $raw_data;
		}

		// write section
		fseek($this->handle, 0, SEEK_END);
		$start = ftell($this->handle);
		fwrite($this->handle, pack(Format::SECTION, $section));

		// some sections require key name
		if (in_array($section, $this->extended_sections)) {
			fwrite($this->handle, pack(Format::KEY_NAME, strlen($key_name)));
			fwrite($this->handle, $key_name, 256);
		}

		// write data
		$size = strlen($data) + strlen($data_iv);
		fwrite($this->handle, pack(Format::DATA, $size));
		fwrite($this->handle, $data_iv);
		fwrite($this->handle, $data);

		return ftell($this->handle) - $start;
	}

	/**
	 * Store specified file in export. Returned is size of file
	 * stored.
	 *
	 * @param string $path
	 * @return integer
	 */
	public function write_file($path) {
		if ($this->handle === FALSE)
			throw new InvalidExportException('No export file open.');

		if (!file_exists($path))
			throw new Exception("Invalid file path specified '{$path}'.");

		// get file data
		if (substr($path, 0, strlen(_BASEPATH)) == _BASEPATH)
			$key_name = substr($path, strlen(_BASEPATH)); else
			$key_name = $path;
		$data = file_get_contents($path);

		// store file
		$result = $this->write(Section::FILES, $data, $key_name);

		return $result;
	}

	/**
	 * Read data from specified section. Some sections such as DATA, SETTINGS and FILES
	 * require `$key_name` to be specified. In order to get list of available keys call
	 * `get_key_names` function.
	 *
	 * @param integer $section
	 * @param string $key_name
	 * @param boolean $encrypted
	 * @return string
	 */
	public function read($section, $key_name=null, $encrypted=true) {
		if ($this->handle === FALSE)
			throw new InvalidExportException('No export file open.');

		if (!$this->find_section($section, $key_name))
			throw new UnknownSectionException("Unable to find section {$section} and/or {$key_name}.");

		// read raw data and initialization vector
		$size = $this->read_size(Format::DATA);

		if ($encrypted) {
			// decrypt data
			$data_iv = fread($this->handle, $this->iv_size);
			$raw_data = fread($this->handle, $size - $this->iv_size);
			$data = openssl_decrypt($raw_data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $data_iv);

		} else {
			// read unencrypted data
			$data = fread($this->handle, $size);
		}

		if ($data === FALSE)  // should not happen, but account for it anyway
			throw new Exception("Unable to decrypt data for section {$section} and/or {$key_name}.");

		return $data;
	}

	/**
	 * Read file data from the specified relative path which is stored
	 * as key name. To get list of all the stored files use  `get_key_names` function
	 * with `Section::FILES` passed as section.
	 *
	 * @param string $key_name
	 * @return string
	 */
	public function read_file($key_name) {
		if ($this->handle === FALSE)
			throw new InvalidExportException('No export file open.');

		return $this->read(Section::FILES, $key_name);
	}

	/**
	 * Close file and flush all buffers.
	 */
	public function close() {
		if ($this->handle === FALSE)
			throw new InvalidExportException('No export file open.');

		fclose($this->handle);
	}

	/**
	 * Get keys for specified section.
	 *
	 * @param integer $section
	 * @return array
	 */
	public function get_key_names($section) {
		if ($this->handle === FALSE)
			throw new InvalidExportException('No export file open.');

		// make sure section can contain keys in the first place
		if (!in_array($section, $this->extended_sections))
			throw new Exception("Section {$section} doesn't contain keys.");

		$result = array();
		$section_size = strlen(pack(Format::SECTION, 0));

		// jump to beginning of all sections
		$stats = fstat($this->handle);
		fseek($this->handle, strlen(Section::HEADER) + strlen(pack(Format::VERSION, '')));

		// search through sections
		while (True) {
			$unpacked_data = unpack(Format::SECTION, fread($this->handle, $section_size));
			$current_section = array_pop($unpacked_data);
			$current_key_name = null;

			// read key name if needed
			if (in_array($current_section, $this->extended_sections)) {
				$key_size = $this->read_size(Format::KEY_NAME);
				$current_key_name = fread($this->handle, $key_size);

				if ($current_section == $section)
					$result []= $current_key_name;
			}

			// move file pointer forward by data size
			$data_size = $this->read_size(Format::DATA);
			fseek($this->handle, $data_size, SEEK_CUR);

			// make sure we stop parsing after we pass end of bounds
			if (ftell($this->handle) >= $stats['size'])
				break;
		}

		return $result;
	}

	/**
	 * Return boolean denoting if file opened is new or existing.
	 *
	 * @return boolean
	 */
	public function is_new() {
		return $this->is_new_file;
	}
}

?>
