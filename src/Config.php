<?php

namespace Wangoviridans\TesseractOCR;

/**
 * Class Config
 * @package Wangoviriadns\TesseractOCR
 */
class Config {
	protected $container;

	/**
	 * @param array $container
	 */
	public function __construct(array $container = array()) {
		$this->container = $container;
	}

	/**
	 * @param string $option
	 * @param mixed $value
	 * @return $this
	 */
	public function setOption($option, $value) {
		$this->container[$option] = $value;
	}

	/**
	 * @param array $options
	 * @return $this
	 */
	public function setOptions(array $options) {
		$this->container = array_merge($this->container, $options);
	}

	/**
	 * @param string $option
	 * @param null $default
	 * @return mixed
	 */
	public function getOption($option, $default = null) {
		return $this->hasOption($option) ? $this->container[$option] : $default;
	}

	/**
	 * @param array $options
	 * @return array
	 */
	public function getOptions(array $options) {
		$result = array();
		foreach($options as $option => $default) {
			if (is_numeric($option)) {
				$option = $default;
				$default = null;
			}
			$result[] = $this->getOption($option, $default);
		}

		return $result;
	}

	/**
	 * @param $option
	 */
	public function unsetOption($option) {
		if ($this->hasOption($option)) {
			unset($this->container[$option]);
		}
	}

	/**
	 * @param array $options
	 */
	public function unsetOptions(array $options) {
		foreach($options as $option) {
			$this->unsetOption($option);
		}
	}

	/**
	 * @param $option
	 * @return bool
	 */
	public function hasOption($option) {
		return array_key_exists($option, $this->container);
	}

	/**
	 * @param $options
	 * @param bool $asArray
	 * @return array|bool
	 */
	public function hasOptions($options, $asArray = false) {
		if (!$asArray) {
			foreach($options as $option) {
				if (!$this->hasOption($option)) {
					return false;
				}
			}

			return false;
		}
		$result = array();
		foreach($options as $option) {
			$result[] = $this->hasOption($option);
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return $this->container;
	}
}