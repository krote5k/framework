<?php

namespace Media\Driver\Drivers;
use Symfony\Component\Process\Process;

class AsteriskShell extends \Media\Driver\Driver {
	private $track;
	private $version;
	private $mime;
	private $extension;
	public $background = false;
	static $supported;

	public function __construct($filename,$extension,$mime) {
		$this->loadTrack($filename);
		$this->version = $this->getVersion();
		$this->mime = $mime;
		$this->extension = $extension;
	}

	/**
	 * Check if Asterisk is installed
	 * @return string The version
	 */
	public static function installed() {
		$process = new Process('asterisk -V');
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
			return false;
		}
		return true;
	}

	/**
	 * Query Asterisk for the supported formats
	 * @param  array $formats Previously supported formats
	 * @return array          Array of formats
	 */
	public static function supportedCodecs(&$formats) {
		if(!empty(self::$supported)) {
			return self::$supported;
		}
		exec("asterisk -rx 'core show file formats'",$lines,$ret);
		foreach($lines as $line) {
			if(preg_match('/([a-z0-9\|]*)$/i',$line,$matches)) {
				$l = trim($matches[1]);
				$codecs = explode("|",$matches[1]);
				foreach($codecs as $codec) {
					if(!in_array($codec,array('wav', 'gsm', 'g722', 'alaw', 'ulaw', 'sln'))) {
						continue;
					}
					$formats["in"][$codec] = $codec;
					$formats["out"][$codec] = $codec;
				}
			}
		}
		$lines = null;
		exec("asterisk -rx 'g729 show licenses'",$lines,$ret);
		foreach($lines as $line) {
			if(preg_match('/licensed channels are currently in use/',$line)) {
				$formats["in"]['g729'] = 'g729';
				$formats["out"]['g729'] = 'g729';
			}
		}
		$formats["out"]["wav"] = "wav";
		$formats["out"]["WAV"] = "WAV";
		$formats["in"]["wav"] = "wav";
		$formats["in"]["WAV"] = "WAV";
		self::$supported = $formats;
		return self::$supported;
	}

	/**
	 * Check to see if a single format is supported by Asterisk
	 * @param  string  $codec     The codec
	 * @param  string  $direction The direction: in or out
	 * @return boolean            If it's supported or not
	 */
	public static function isCodecSupported($codec,$direction) {
		$formats = array();
		$formats = self::supportedCodecs($formats);
		return in_array($codec, $formats[$direction]);
	}

	/**
	 * Load path, make sure it's valid
	 * @param  string $track The full path to the file
	 */
	public function loadTrack($track) {
		if(empty($track)) {
			throw new \Exception("A track must be supplied");
		}
		if(!file_exists($track)) {
			throw new \Exception("Track [$track] not found");
		}
		if(!is_readable($track)) {
			throw new \Exception("Track [$track] not readable");
		}
		$this->track = $track;
	}

	/**
	 * Get the version of Asterisk
	 * @return string The version
	 */
	public function getVersion() {
		$process = new Process('asterisk -V');
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		//sox: Asterisk 13.5.0
		if(preg_match("/Asterisk (.*)/",$process->getOutput(),$matches)) {
			return $matches[1];
		} else {
			throw new \Exception("Unable to parse version");
		}
	}

	/**
	 * Convert file to format using Asterisk
	 * @param  string $newFilename The full path to the new file
	 * @param  string $extension   The new extension
	 * @param  string $mime        Mime type
	 */
	public function convert($newFilename,$extension,$mime) {
		$process = new Process("asterisk -rx 'file convert ".$this->track." ".$newFilename."'");
		if(!$this->background) {
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getErrorOutput());
			}
		} else {
			$process->start();
			if (!$process->isRunning()) {
				throw new \RuntimeException($process->getErrorOutput());
			}
		}
	}
}