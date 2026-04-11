<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CI_Session_files_driver extends CI_Session_driver implements SessionHandlerInterface {

	protected $_save_path;
	protected $_file_handle;
	protected $_file_path;
	protected $_file_new;
	protected $_sid_regexp;
	protected static $func_overload;

	public function __construct(&$params)
	{
		parent::__construct($params);

		if (isset($this->_config['save_path']))
		{
			$this->_config['save_path'] = rtrim($this->_config['save_path'], '/\\');
			ini_set('session.save_path', $this->_config['save_path']);
		}
		else
		{
			$this->_config['save_path'] = rtrim(ini_get('session.save_path'), '/\\');
		}

		$this->_sid_regexp = $this->_config['_sid_regexp'];
		isset(self::$func_overload) OR self::$func_overload = (extension_loaded('mbstring') && ini_get('mbstring.func_overload'));
	}

	#[\ReturnTypeWillChange]
	public function open($save_path, $name)
	{
		if ( ! is_dir($save_path))
		{
			if ( ! mkdir($save_path, 0700, TRUE))
			{
				return $this->_failure;
			}
		}
		elseif ( ! is_writable($save_path))
		{
			return $this->_failure;
		}

		$this->_config['save_path'] = $save_path;
		$this->_file_path = $this->_config['save_path'].DIRECTORY_SEPARATOR
			.$name
			.($this->_config['match_ip'] ? md5($_SERVER['REMOTE_ADDR']) : '');

		$this->php5_validate_id();

		return $this->_success;
	}

	#[\ReturnTypeWillChange]
	public function read($session_id)
	{
		if ($this->_file_handle === NULL)
		{
			$this->_file_new = ! file_exists($this->_file_path.$session_id);

			if (($this->_file_handle = fopen($this->_file_path.$session_id, 'c+b')) === FALSE)
			{
				return $this->_failure;
			}

			if (flock($this->_file_handle, LOCK_EX) === FALSE)
			{
				fclose($this->_file_handle);
				$this->_file_handle = NULL;
				return $this->_failure;
			}

			$this->_session_id = $session_id;

			if ($this->_file_new)
			{
				chmod($this->_file_path.$session_id, 0600);
				$this->_fingerprint = md5('');
				return '';
			}
		}
		elseif ($this->_file_handle === FALSE)
		{
			return $this->_failure;
		}
		else
		{
			rewind($this->_file_handle);
		}

		$session_data = '';
		for ($read = 0, $length = filesize($this->_file_path.$session_id); $read < $length; $read += self::strlen($buffer))
		{
			if (($buffer = fread($this->_file_handle, $length - $read)) === FALSE)
			{
				break;
			}
			$session_data .= $buffer;
		}

		$this->_fingerprint = md5($session_data);
		return $session_data;
	}

	#[\ReturnTypeWillChange]
	public function write($session_id, $session_data)
	{
		if ($session_id !== $this->_session_id && ($this->close() === $this->_failure OR $this->read($session_id) === $this->_failure))
		{
			return $this->_failure;
		}

		if ( ! is_resource($this->_file_handle))
		{
			return $this->_failure;
		}
		elseif ($this->_fingerprint === md5($session_data))
		{
			return ( ! $this->_file_new && ! touch($this->_file_path.$session_id))
				? $this->_failure
				: $this->_success;
		}

		if ( ! $this->_file_new)
		{
			ftruncate($this->_file_handle, 0);
			rewind($this->_file_handle);
		}

		if (($length = strlen($session_data)) > 0)
		{
			for ($written = 0; $written < $length; $written += $result)
			{
				if (($result = fwrite($this->_file_handle, substr($session_data, $written))) === FALSE)
				{
					break;
				}
			}

			if ( ! is_int($result))
			{
				$this->_fingerprint = md5(substr($session_data, 0, $written));
				return $this->_failure;
			}
		}

		$this->_fingerprint = md5($session_data);
		return $this->_success;
	}

	#[\ReturnTypeWillChange]
	public function close()
	{
		if (is_resource($this->_file_handle))
		{
			flock($this->_file_handle, LOCK_UN);
			fclose($this->_file_handle);
			$this->_file_handle = $this->_file_new = $this->_session_id = NULL;
		}

		return $this->_success;
	}

	#[\ReturnTypeWillChange]
	public function destroy($session_id)
	{
		if ($this->close() === $this->_success)
		{
			if (file_exists($this->_file_path.$session_id))
			{
				$this->_cookie_destroy();
				return unlink($this->_file_path.$session_id)
					? $this->_success
					: $this->_failure;
			}
			return $this->_success;
		}
		return $this->_failure;
	}

	#[\ReturnTypeWillChange]
	public function gc($maxlifetime)
	{
		if ( ! is_dir($this->_config['save_path']) OR ($directory = opendir($this->_config['save_path'])) === FALSE)
		{
			return $this->_failure;
		}

		$ts = time() - $maxlifetime;

		while (($file = readdir($directory)) !== FALSE)
		{
			if (is_file($this->_config['save_path'].DIRECTORY_SEPARATOR.$file)
				&& filemtime($this->_config['save_path'].DIRECTORY_SEPARATOR.$file) < $ts)
			{
				unlink($this->_config['save_path'].DIRECTORY_SEPARATOR.$file);
			}
		}

		closedir($directory);
		return $this->_success;
	}

	public function validateSessionId($id)
	{
		return is_file($this->_file_path.$id);
	}

	protected static function strlen($str)
	{
		return (self::$func_overload)
			? mb_strlen($str, '8bit')
			: strlen($str);
	}
}