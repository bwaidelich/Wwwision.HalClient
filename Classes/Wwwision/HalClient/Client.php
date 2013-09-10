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
	 * @var string
	 */
	protected $baseUri;

	/**
	 * @var string
	 */
	protected $apiRootPath;

	/**
	 * @var string
	 */
	protected $apiRootUri;

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
	 * @param string $baseUri
	 * @param array $defaultHeaders
	 */

	/**
	 * @param string $baseUri base URI of the API
	 * @param array $defaultHeaders optional headers to be sent with every request in the format array('<header-name>' => '<header-value>', ...)
	 * @param array $requestEngineOptions optional configuration options being passed to the request engine. Expected format: array('<option-name>' => '<option-value>', ...)
	 * @param string $apiRootPath optional root (home) path of the API. e.g. "api/"
	 */
	public function __construct($baseUri, array $defaultHeaders = array(), array $requestEngineOptions = array(), $apiRootPath = '') {
		$this->baseUri = $baseUri;
		$this->defaultHeaders = $defaultHeaders;
		$this->requestEngineOptions = $requestEngineOptions;
		$this->apiRootPath = $apiRootPath;
	}

	/**
	 * @return void
	 */
	public function initializeObject() {
		$this->browser = new Browser();
		$requestEngine = new CurlEngine();
		foreach ($this->requestEngineOptions as $optionName => $optionValue) {
			$requestEngine->setOption($optionName, $optionValue);
		}
		$this->browser->setRequestEngine($requestEngine);
	}

	/**
	 * @return void
	 */
	protected function initialize() {
		if (!$this->initialized) {
			$this->state = $this->sendRequest($this->apiRootPath);
			$this->initialized = TRUE;
		}
	}

	/**
	 * @param string $resourceName
	 * @return \Wwwision\HalClient\Domain\Dto\Resource
	 * @throws Exception\UnknownResourceException
	 */
	public function getResourceByName($resourceName) {
		$resourceUri = $this->getResourceUriByName($resourceName);
		return $this->getResourceByUri($resourceUri);
	}

	/**
	 * @param string $resourceName
	 * @return string
	 * @throws Exception\UnknownResourceException
	 */
	public function getResourceUriByName($resourceName) {
		$this->initialize();
		if (!isset($this->state['_links'][$resourceName])) {
			throw new Exception\UnknownResourceException('Resource "' . $resourceName . '" is unknown');
		}
		return $this->state['_links'][$resourceName]['href'];
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
		$uri = sprintf('%s/%s', rtrim($this->baseUri, '/'), ltrim($path, '/'));
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