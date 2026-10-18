<?php

/**
 * Database and files export archive handler class.
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
 * format. This is to ensure cross-platform compatibility. Key name length
 * is stored in a single byte so key names must be between 1 and 255 bytes
 * long. Maximum data size for each section is 4GB (this is format support,
 * maximum file size still depends on underlying file system).
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
	const MAX_KEY_LENGTH = 255;  // key size is stored as unsigned char
	const TEMPORARY_SUFFIX = '.part';

	private $handle;
	private $path;
	private $temporary_path;
	private $key;
	private $iv_size;
	private $is_new_file;
	private $writable;
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

		// export files live directly in the backup directory, never in a subpath;
		// reducing to the base name makes directory traversal impossible
		$file_name = basename($file_name);
		$this->path = $backup_path.$file_name;
		$this->temporary_path = $this->path.self::TEMPORARY_SUFFIX;

		// increase security a little bit by extending key length through hash function
		// as people don't have a tendency to choose long passwords
		$this->key = hash('sha512', $key, true);

		// existing files are opened read-only, new files are written under
		// temporary name and moved in place on close so partial exports never
		// show up as valid files
		if (file_exists($this->path)) {
			$this->handle = fopen($this->path, 'r');
			$this->is_new_file = false;
			$this->writable = false;

		} else {
			$this->handle = fopen($this->temporary_path, 'w');
			$this->is_new_file = true;
			$this->writable = true;
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
		$header = $this->read_bytes(strlen(Section::HEADER));

		// make sure out header strings match
		if ($header != Section::HEADER)
			throw new InvalidExportException('File header mismatch!');

		// read version
		$this->version = trim($this->read_packed(Format::VERSION));

		if (!in_array($this->version, $this->supported_versions))
			throw new InvalidExportException('Unsupported version: '.$this->version);

		// read encryption information, both sections are mandatory
		if (!$this->find_section(Section::KEY_HASH))
			throw new InvalidExportException('Key hash section is missing!');

		$hash_size = $this->read_size(Format::DATA);
		$hash = $this->read_bytes($hash_size);

		if ($verify_hash && !hash_equals(hash('sha256', $this->key, true), $hash))
			throw new InvalidKeyException('Hash mismatch!');

		if (!$this->find_section(Section::CIPHER))
			throw new InvalidExportException('Cipher section is missing!');

		$cipher_size = $this->read_size(Format::DATA);
		$this->cipher = $this->read_bytes($cipher_size);

		// make sure cipher stored in file is something we can actually use
		if (!in_array(strtolower($this->cipher), openssl_get_cipher_methods(true)))
			throw new InvalidExportException('Unsupported cipher: '.$this->cipher);
	}

	/**
	 * Read exact number of bytes from the archive. Short read means
	 * file was truncated or is not an export file at all.
	 *
	 * @param integer $length
	 * @return string
	 */
	private function read_bytes($length) {
		$data = $length > 0 ? fread($this->handle, $length) : '';

		if ($data === FALSE || strlen($data) != $length)
			throw new InvalidExportException('Unexpected end of export file.');

		return $data;
	}

	/**
	 * Read and unpack single value of specified format from the archive.
	 *
	 * @param string $format
	 * @return mixed
	 */
	private function read_packed($format) {
		$size = strlen(pack($format, 0));
		$unpacked_data = unpack($format, $this->read_bytes($size));
		return array_pop($unpacked_data);
	}

	/**
	 * Read size from the archive for specified format.
	 *
	 * @param string $format
	 * @return integer
	 */
	private function read_size($format) {
		return $this->read_packed($format);
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

		// jump to beginning of all sections
		$stats = fstat($this->handle);
		fseek($this->handle, strlen(Section::HEADER) + strlen(pack(Format::VERSION, '')));

		// search through sections until we run out of file
		while (ftell($this->handle) < $stats['size']) {
			$current_section = $this->read_packed(Format::SECTION);
			$current_key_name = null;

			// read key name if needed
			if (in_array($current_section, $this->extended_sections)) {
				$key_size = $this->read_size(Format::KEY_NAME);
				$current_key_name = $this->read_bytes($key_size);
			}

			// bail if we found requested section
			if ($current_section == $section && (is_null($key_name) || $current_key_name == $key_name)) {
				$result = true;
				break;
			}

			// move file pointer forward by data size
			$this->skip_data($stats['size']);
		}

		return $result;
	}

	/**
	 * Skip over data of the current section. File pointer must be
	 * positioned at the data size field.
	 *
	 * @param integer $file_size
	 */
	private function skip_data($file_size) {
		$data_size = $this->read_size(Format::DATA);
		fseek($this->handle, $data_size, SEEK_CUR);

		// section claims more data than file contains
		if (ftell($this->handle) > $file_size)
			throw new InvalidExportException('Unexpected end of export file.');
	}

	/**
	 * Write data to specified section in the file. Some sections
	 * such as DATA, SETTINGS and FILES require `$key_name` to be specified
	 * and it must be between 1 and `MAX_KEY_LENGTH` bytes long.
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

		if (!$this->writable)
			throw new InvalidExportException('Export file was opened for reading only.');

		// make sure key name fits the format
		$requires_key = in_array($section, $this->extended_sections);
		if ($requires_key && (strlen($key_name) == 0 || strlen($key_name) > self::MAX_KEY_LENGTH))
			throw new Exception("Key name for section {$section} must be between 1 and ".self::MAX_KEY_LENGTH." bytes long.");

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
		if ($requires_key) {
			fwrite($this->handle, pack(Format::KEY_NAME, strlen($key_name)));
			fwrite($this->handle, $key_name);
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

		// get size of data to read
		$size = $this->read_size(Format::DATA);

		if ($size == 0) {
			// section is empty
			$data = '';

		} else if ($encrypted) {
			// encrypted sections always start with initialization vector
			if ($size < $this->iv_size)
				throw new InvalidExportException("Section {$section} and/or {$key_name} is too short to be encrypted.");

			// empty string encrypts to empty string, read handles zero length
			$data_iv = $this->read_bytes($this->iv_size);
			$raw_data = $this->read_bytes($size - $this->iv_size);
			$data = openssl_decrypt($raw_data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $data_iv);

		} else {
			// read unencrypted data
			$data = $this->read_bytes($size);
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
	 * Close file and flush all buffers. Newly created files are moved
	 * from temporary to their final location.
	 */
	public function close() {
		if ($this->handle === FALSE)
			throw new InvalidExportException('No export file open.');

		fclose($this->handle);
		$this->handle = FALSE;

		if ($this->writable && !rename($this->temporary_path, $this->path))
			throw new InvalidExportException('Unable to move export file in place.');
	}

	/**
	 * Close file and remove it. Only newly created files can be
	 * discarded, existing files are left untouched. Safe to call
	 * on already closed file.
	 */
	public function discard() {
		if ($this->handle !== FALSE) {
			fclose($this->handle);
			$this->handle = FALSE;
		}

		if ($this->writable && file_exists($this->temporary_path))
			unlink($this->temporary_path);
	}

	/**
	 * Make sure unfinished exports don't linger around.
	 */
	public function __destruct() {
		if ($this->handle !== FALSE)
			$this->discard();
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

		// jump to beginning of all sections
		$stats = fstat($this->handle);
		fseek($this->handle, strlen(Section::HEADER) + strlen(pack(Format::VERSION, '')));

		// search through sections until we run out of file
		while (ftell($this->handle) < $stats['size']) {
			$current_section = $this->read_packed(Format::SECTION);

			// read key name if needed
			if (in_array($current_section, $this->extended_sections)) {
				$key_size = $this->read_size(Format::KEY_NAME);
				$current_key_name = $this->read_bytes($key_size);

				if ($current_section == $section)
					$result []= $current_key_name;
			}

			// move file pointer forward by data size
			$this->skip_data($stats['size']);
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

	/**
	 * Return boolean denoting if file can be written to. Only newly
	 * created files are writable, existing files are opened read-only.
	 *
	 * @return boolean
	 */
	public function is_writable() {
		return $this->writable;
	}
}

?>
