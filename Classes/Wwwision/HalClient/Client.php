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
use TYPO3\Flow\Cache\Frontend\StringFrontend;
use TYPO3\Flow\Http\Client\Browser;
use TYPO3\Flow\Http\Client\CurlEngine;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Utility\Arrays;
use Wwwision\HalClient\Domain\Dto\Resource;

/**
 * The HAL Client
 */
class Client {

	/**
	 * @var Browser
	 */
	protected $browser;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var string
	 */
	protected $baseUri;

	/**
	 * @var array
	 */
	protected $defaultHeaders;

	/**
	 * @var boolean
	 */
	protected $initialized = FALSE;

	/**
	 * @var array
	 */
	protected $state = array();

	/**
	 * @var StringFrontend
	 * @Flow\Inject
	 */
	protected $requestCache;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings($settings) {
		$this->settings = $settings;
	}

	/**
	 * @param string $baseUri
	 * @param array $defaultHeaders
	 */
	public function __construct($baseUri, array $defaultHeaders = array()) {
		$this->baseUri = $baseUri;
		$this->defaultHeaders = $defaultHeaders;
	}

	/**
	 * @return void
	 */
	public function initializeObject() {
		$this->browser = new Browser();
		$requestEngine = new CurlEngine();
		if (isset($this->settings['requestEngineOptions'])) {
			foreach ($this->settings['requestEngineOptions'] as $optionName => $optionValue) {
				$requestEngine->setOption($optionName, $optionValue);
			}
		}
		$this->browser->setRequestEngine($requestEngine);
		$this->defaultHeaders = Arrays::arrayMergeRecursiveOverrule($this->defaultHeaders, $this->settings['defaultHeaders']);
	}

	/**
	 * @return void
	 */
	protected function initialize() {
		if (!$this->initialized) {
			$this->state = $this->sendRequest($this->settings['apiRootUri']);
			$this->initialized = TRUE;
		}
	}

	/**
	 * @param string $resourceName
	 * @return Resource
	 * @throws Exception\UnknownResourceException
	 */
	public function getResourceByName($resourceName) {
		$this->initialize();
		if (!isset($this->state['_links'][$resourceName])) {
			throw new Exception\UnknownResourceException('Resource "' . $resourceName . '" is unknown');
		}
		$resourceUri = $this->state['_links'][$resourceName]['href'];
		return $this->getResourceByUri($resourceUri);
	}

	/**
	 * @param string $resourceUri
	 * @return Resource
	 */
	public function getResourceByUri($resourceUri) {
		$result = $this->sendRequest($resourceUri, 'GET');
		return new Resource($result, $resourceUri, function($resourceUri) {
			return $this->sendRequest($resourceUri);
		});
	}

	/**
	 * @param string $path relative path to the resource, will be prefixed with rootUri
	 * @param string $method request method
	 * @param array $arguments
	 * @return array
	 * @throws Exception\FailedRequestException if response status code is != 2**
	 */
	public function sendRequest($path, $method = 'GET', array $arguments = array()) {
		$uri = $this->baseUri . $path;
		$cacheIdentifier = md5($uri);
		if ($method === 'GET' && $this->requestCache->has($cacheIdentifier)) {
			return json_decode($this->requestCache->get($cacheIdentifier), TRUE);
		}
		$request = Request::create(new Uri($uri), $method, $arguments);

		// FIXME this is currently required as work around for http://forge.typo3.org/issues/51763
		$request->setContent('');

		foreach ($this->defaultHeaders as $headerName => $headerValue) {
			$request->setHeader($headerName, $headerValue);
		}
		$response = $this->browser->sendRequest($request);
		if (substr($response->getStatusCode(), 0, 1) !== '2') {
			throw new Exception\FailedRequestException('Failed to request "' . $uri . '", status: ' . $response->getStatus() .' (' . $response->getStatusCode() . ')');
		}
		if ($method === 'GET') {
			$this->requestCache->set($cacheIdentifier, $response->getContent());
		}
		return json_decode($response->getContent(), TRUE);
	}
}
?>