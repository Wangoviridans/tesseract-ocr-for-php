<?php

namespace Wangoviridans\TesseractOCR;

use Wangoviridans\Config\Config;
use AdamBrett\ShellWrapper\Command\Builder as CommandBuilder;

/**
 * Class TesseractOCR
 * @package Wangoviridans\TesseractOCR
 */
class TesseractOCR {
	const RUNNER_EXEC = 'Exec';
	const RUNNER_PASSTHRU = 'Passthru';
	const RUNNER_SHELL_EXEC = 'ShellExec';
	const RUNNER_SYSTEM = 'System';

	/**
	 * @var \Wangoviridans\Config\Config
	 */
	protected $config;

	protected $shell;

	/**
	 * @param array $config
	 */
	public function __construct(array $config = array()) {
		$this->config = new Config(array_merge(array(
			'language' => 'eng',
			'psm' => 3,
			'temp' => sys_get_temp_dir(),

			'shell' => array(
				'runner' => self::RUNNER_EXEC,
				'command' => 'tesseract'
			)
		),$config));

		$runner = "\\AdamBrett\\ShellWrapper\\Runners\\" . $this->getShellRunner(self::RUNNER_EXEC);
		$this->shell = new $runner();

		if (false !== $this->getLocale(false)) {
			$this->setLocale($this->getLocale());
		}
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 * @throws \BadMethodCallException
	 */
	public function __call($name, $arguments) {
		if (strlen($name) > 3) {
			$type = strtolower(substr($name, 0, 3));
			if (in_array($type, array('get', 'set'))) {
				$optionName = Config::fromСamelСase(ucfirst(substr($name, 3)), '.');
				array_unshift($arguments, $optionName);

				return call_user_func_array(array($this->config, $type . 'Option'), $arguments);
			}
		}
		throw new \BadMethodCallException("Method {$name} doesn't exists");
	}

	/**
	 * @param string $locale
	 * @throws \InvalidArgumentException
	 */
	public function setLocale($locale) {
		if (!($result = setlocale(LC_CTYPE, $locale))) {
			throw new \InvalidArgumentException("Locale setting failed ({$locale}). The locale functionality is not implemented on your platform, the specified locale does not exist or the category name is invalid.");
		}
		$this->config->setOption('locale', $result);
	}

	/**
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function recognize() {
		if (!$this->getImage(false)) {
			throw new \InvalidArgumentException('No input file specified');
		}
		$this->generateConfigFile();
		$this->execute();
		$recognizedText = $this->readOutput();
		$this->removeTempFiles();

		return $recognizedText;
	}

	protected function execute() {
		$this->setOutput($this->getTemp() . rand());
		$this->shell->run($this->buildTesseractCommand());
	}

	/**
	 * @return CommandBuilder
	 */
	protected function buildTesseractCommand() {
		$command = new CommandBuilder($this->getShellCommand('tesseract'));
		$command->addSubCommand($this->getImage());
		$command->addSubCommand($this->getOutput());
		$command->addFlag('l', $this->getLanguage('eng'));
		$command->addFlag('psm', $this->getPsm(3));
		if ($this->config->hasOption('batch')) {
			$command->addParam($this->getBatch());
		}
		if ($this->config->hasOption('config')) {
			$command->addParam($this->getConfig());
		}
		$command->addParam('quiet');

		return $command;
	}

	protected function generateConfigFile() {
		$whiteList = $this->getWhitelist();
		if (is_array($whiteList)) {
			$whiteList = $this->buildWhiteListFromArray($whiteList);
		}
		$configFile = $this->getTemp() . rand() . '.conf';
		file_put_contents($configFile, 'tessedit_char_whitelist ' . $whiteList, LOCK_EX);
		$this->setConfig($configFile);
	}

	/**
	 * @param array $whiteListArray
	 * @return string
	 */
	protected function buildWhiteListFromArray(array $whiteListArray) {
		$whiteList = '';
		foreach($whiteListArray as $list) {
			$whiteList .= is_array($list) ? join('', $list) : $list;
		}
		return $whiteList;
	}

	/**
	 * @return string
	 */
	protected function readOutput() {
		if (file_exists($this->getOutput() . '.txt')) {
			return trim(file_get_contents($this->getOutput() . '.txt'));
		}
		return '';
	}

	/**
	 * Removes temporary files created by tesseract-ocr
	 */
	protected function removeTempFiles() {
		if (false !== ($config = $this->getConfig(false))) {
			@unlink($this->getConfig());
			$this->config->unsetOption('config');
		}
		@unlink($this->getOutput() . '.txt');
	}
}
