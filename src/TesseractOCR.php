<?php

namespace Wangoviridans\TesseractOCR;

use AdamBrett\ShellWrapper\Command\Builder as CommandBuilder;

class TesseractOCR {
	/**
	 * List of supported shell runners
	 * @var array
	 */
	protected static $supportedShellRunners = array(
		'exec'		=> "\\AdamBrett\\ShellWrapper\\Runners\\Exec",
		'passthru'	=> "\\AdamBrett\\ShellWrapper\\Runners\\Passthru",
		'shell'		=> "\\AdamBrett\\ShellWrapper\\Runners\\ShellExec",
		'system'	=> "\\AdamBrett\\ShellWrapper\\Runners\\System"
	);

	/**
	 * @var string|null
	 */
	protected $configFile;

	/**
	 * @var string|null
	 */
	protected $outputFile;

	/**
	 * @var
	 */
	protected $config;

	/**
	 * Current shell runner
	 * @var \AdamBrett\ShellWrapper\Runners\Runner
	 */
	protected $shell;

	/**
	 * @param array $config
	 */
	public function __construct(array $config = array()) {
		$this->config = new Config($config);

		$this->setLocale($this->config->getOption('locale', 'en_US.UTF-8'))
			->setTempDir($this->config->getOption('tempDir', sys_get_temp_dir()))
			->setLanguage($this->config->getOption('language', 'eng'))
			->setPsm($this->config->getOption('psm', 3))
			->setShellRunner($this->config->getOption('shell.runner', 'exec'))
			->setShellCommand($this->config->getOption('shell.command', 'tesseract'));

		if ($this->config->hasOption('file.input')) {
			$this->setImage($this->config->getOption('file.input'));
		}

		if ($this->config->hasOption('batch')) {
			$this->setBatch($this->config->getOption('batch'));
		}

		if ($this->config->hasOption('whiteList')) {
			$this->setWhiteList($this->config->getOption('whiteList'));
		}

		$this->shell = self::createShell($this->getShellRunner());
	}

	/**
	 * @param $runner
	 * @return $this
	 */
	public function setShellRunner($runner) {
		$runner = strtolower(trim(strval($runner)));
		if (empty($runner) || !in_array($runner, static::$supportedShellRunners)) {
			$runner = 'exec';
		}
		$this->config->setOption('shell.runner', $runner);

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getShellRunner() {
		return $this->config->getOption('shell.runner');
	}

	/**
	 * @param $command
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setShellCommand($command) {
		$command = trim(strval($command));
		if (empty($command)) {
			throw new \InvalidArgumentException("ShellCommand must be not empty string.");
		}
		$this->config->setOption('shell.command', $command);

		return $this;
	}

	/**
	 * @return string
	 */
	public function getShellCommand() {
		return $this->config->getOption('shell.command');
	}

	/**
	 * @param $newLocale
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setLocale($newLocale) {
		if (false === ($locale = setlocale(LC_CTYPE, $newLocale))) {
			throw new \InvalidArgumentException("Locale setting failed ({$newLocale}). The locale functionality is not implemented on your platform, the specified locale does not exist or the category name is invalid.");
		}
		$this->config->setOption('locale', $locale);

		return $this;
	}

	/**
	 * @return string
	 */
	public function getLocale() {
		return $this->config->getOption('locale', setlocale(LC_CTYPE, '0'));
	}

	/**
	 * Set the language to be used during the recognition
	 *
	 * @param string $language valid 3-letters string (eg. eng, rus, ukr)
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setLanguage($language) {
		$language = strtolower(trim(strval($language)));
		if (empty($language) || !ctype_alpha($language) || strlen($language) != 3) {
			throw new \InvalidArgumentException("Language must be a valid 3-letters string (eg eng, rus)");
		}
		$this->config->setOption('language', $language);

		return $this;
	}

	/**
	 * Get the language used during the recognition
	 *
	 * @return string
	 */
	public function getLanguage() {
		return $this->config->getOption('language', 'eng');
	}

	/**
	 * Set the psm to be used during the recognition
	 * 0 = Orientation and script detection (OSD) only.
	 * 1 = Automatic page segmentation with OSD.
	 * 2 = Automatic page segmentation, but no OSD, or OCR.
	 * 3 = Fully automatic page segmentation, but no OSD. (Default)
	 * 4 = Assume a single column of text of variable sizes.
	 * 5 = Assume a single uniform block of vertically aligned text.
	 * 6 = Assume a single uniform block of text.
	 * 7 = Treat the image as a single text line.
	 * 8 = Treat the image as a single word.
	 * 9 = Treat the image as a single word in a circle.
	 * 10 = Treat the image as a single character.
	 *
	 * @param int $psm
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setPsm($psm) {
		$psm = intval($psm);
		if ($psm < 0 || $psm > 10) {
			throw new \InvalidArgumentException("Psm must be a valid integer 0..10 (3 by default)");
		}
		$this->config->setOption('psm', $psm);

		return $this;
	}

	/**
	 * Get the psm used during the recognition
	 *
	 * @return int
	 */
	public function getPsm() {
		return $this->config->getOption('psm');
	}

	/**
	 * @param $batch
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setBatch($batch) {
		switch (strtolower(strval($batch))) {
			case '': $this->config->unsetOption('batch'); return $this;

			case 'nobatch':
			case 'no':
				$batch = 'nobatch';
				break;

			case 'batch.nochop':
			case 'nochop':
				$batch = 'batch.nochop';
				break;

			default:
				throw new \InvalidArgumentException("Unknown batch ({$batch}).");
		}
		$this->config->setOption('batch', $batch);

		return $this;
	}

	/**
	 * @return string
	 */
	public function getBatch() {
		return $this->config->getOption('batch', '');
	}

	/**
	 * Set whiteList to be used during the recognition
	 *
	 * @param array $whiteList
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setWhiteList($whiteList) {
		$this->config->setOption('whiteList', static::buildWhiteListFromArray($whiteList));

		return $this;
	}

	/**
	 * Get whiteList used during the recognition
	 *
	 * @return array
	 */
	public function getWhiteList() {
		return $this->config->getOption('whiteList');
	}

	/**
	 * Set the image to be used during the recognition
	 *
	 * @param $image
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setImage($image) {
		if (!file_exists($image)) {
			throw new \InvalidArgumentException("Image file doesn't exists ({$image}).");
		}
		$this->config->setOption('file.input', $image);

		return $this;
	}

	/**
	 * Get the image used during the recognition
	 *
	 * @return mixed
	 */
	public function getImage() {
		return $this->config->getOption('imageFile');
	}


	/**
	 * Set the outputFile to be used during the recognition
	 *
	 * @param $outputFile
	 * @return $this
	 */
	public function setOutputFile($outputFile) {
		$this->outputFile = $outputFile;

		return $this;
	}

	/**
	 * Get the outputFile used during the recognition
	 *
	 * @return string
	 */
	public function getOutputFile() {
		return $this->outputFile;
	}

	public function setConfigFile($configFile) {
		$this->configFile = $configFile;

		return $this;
	}

	/**
	 * Set the temporary directory to be used during the recognition
	 *
	 * @param string $tempDir
	 * @return $this
	 * @throws \InvalidArgumentException if tempDir doesn't exists
	 */
	public function setTempDir($tempDir) {
		if (!file_exists($tempDir) || !is_dir($tempDir)) {
			throw new \InvalidArgumentException("tempDir doesn't exists ({$tempDir}");
		}
		$this->config->setOption('tempDir', $tempDir);

		return $this;
	}

	/**
	 * Set the temporary directory used during the recognition
	 *
	 * @return string
	 */
	public function getTempDir() {
		return $this->config->getOption('tempDir');
	}

	/**
	 * @return CommandBuilder
	 * @throws \InvalidArgumentException
	 */
	protected function buildTesseractCommand() {
		$command = static::createCommand($this->getShellCommand());

		$command->addParam($this->getImage());
		$command->addParam($this->getOutputFile());

		$command->addArgument('l', $this->getLanguage());
		$command->addArgument('psm', $this->getPsm());

		$command->addParam($this->getBatch());

		if ($this->config->hasOption('configFile')) {
			$command->addParam($this->config->getOption('configFile'));
		} else if ($this->config->hasOption('whiteList')) {
			$command->addParam($this->getWhiteList());
		}

		$command->addParam('quiet');

		return $command;
	}

	/**
	 * Generates temporary config file used during the recognition
	 */
	protected function generateConfigFile() {
		$configFile = $this->getTempDir() . rand() . '.conf';
		file_put_contents($configFile, 'tessedit_char_whitelist ' . $this->getWhiteList(), LOCK_EX);
		$this->setConfigFile($configFile);
	}

	/**
	 * Executes the shell command
	 */
	protected function execute() {
		$this->setOutputFile($this->getTempDir() . rand());
		$this->shell->run($this->buildTesseractCommand());
	}

	/**
	 * @return null|string
	 */
	protected function readOutputFile() {
		if (file_exists($this->getOutputFile() . '.txt')) {
			return trim(file_get_contents($this->getOutputFile() . '.txt'));
		}

		return '';
	}

	/**
	 * Removes temporary files created by tesseract-ocr
	 */
	protected function removeTempFiles() {
		if (!is_null($this->configFile)) {
			@unlink($this->configFile);
			$this->configFile = null;
		}

		@unlink($this->outputFile . '.txt');
		$this->outputFile = null;
	}

	/**
	 * @return null|string
	 */
	public function recognize() {
		if (!$this->config->hasOption('file.input')) {
			throw new \InvalidArgumentException('No input file selected.');
		} else if ($this->config->hasOption('whiteList') && null !== $this->getWhiteList()) {
			$this->generateConfigFile();
		}

		$this->execute();
		$recognizedText = $this->readOutputFile();
		$this->removeTempFiles();

		return $recognizedText;
	}

	/**
	 * @param $runner
	 * @return \AdamBrett\ShellWrapper\Runners\Runner
	 */
	protected static function createShell($runner) {
		$runnerClass = static::$supportedShellRunners[$runner];
		return new $runnerClass();
	}

	/**
	 * @param $command
	 * @return \AdamBrett\ShellWrapper\Command\Builder
	 */
	protected static function createCommand($command) {
		return new CommandBuilder($command);
	}

	/**
	 * Flatten the lists of characters into a single string
	 *
	 * @param array $whiteListArray
	 * @return string
	 */
	protected static function buildWhiteListFromArray(array $whiteListArray) {
		$whiteList = '';
		foreach($whiteListArray as $list) {
			$whiteList .= is_array($list) ? join('', $list) : $list;
		}

		return $whiteList;
	}
}