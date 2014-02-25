<?php
namespace Wwwision\HalClient;

/*                                                                        *
 * This script belongs to the Flow package "Wwwision.HalClient".          *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * A HAL Client factory
 *
 * @Flow\scope("singleton")
 */
class ClientFactory {

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * Factory method which creates a HalClient with the default settings
	 *
	 * @return Client
	 */
	public function create() {
		$apiBaseUri = isset($this->settings['apiBaseUri']) ? $this->settings['apiBaseUri'] : '';
		$defaultHeaders = isset($this->settings['defaultHeaders']) ? $this->settings['defaultHeaders'] : array();
		$apiRootPath = isset($this->settings['apiRootPath']) ? $this->settings['apiRootPath'] : '';
		return new Client($apiBaseUri, $defaultHeaders, $apiRootPath);
	}
}

?>