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
use TYPO3\Flow\Http\Client\Browser;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Http\Response;
use TYPO3\Flow\Http\Uri;
use Wwwision\HalClient\Domain\Dto\Resource;

/**
 * The HAL Client
 */
class Client {

	/**
	 * @Flow\Inject
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
	 * @Flow\Inject
	 * @var UriTemplateProcessor
	 */
	protected $uriTemplateProcessor;

	/**
	 * @param string $baseUri
	 * @param array $defaultHeaders
	 */

	/**
	 * @param string $baseUri base URI of the API
	 * @param array $defaultHeaders optional headers to be sent with every request in the format array('<header-name>' => '<header-value>', ...)
	 * @param string $apiRootPath optional root (home) path of the API. e.g. "api/"
	 */
	public function __construct($baseUri, array $defaultHeaders = array(), $apiRootPath = '') {
		$this->baseUri = $baseUri;
		$this->defaultHeaders = $defaultHeaders;
		$this->apiRootPath = $apiRootPath;
	}

	/**
	 * @return void
	 */
	protected function initialize() {
		if (!$this->initialized) {
			$this->state = $this->getResourceDataByUri($this->apiRootPath);
			$this->browser->setFollowRedirects(FALSE);
			$this->initialized = TRUE;
		}
	}

	/**
	 * @param string $resourceName
	 * @param array $variables If set, these variables will be replaced in the URI (URI template)
	 * @return \Wwwision\HalClient\Domain\Dto\Resource
	 * @throws Exception\UnknownResourceException
	 */
	public function getResourceByName($resourceName, array $variables = array()) {
		$resourceUri = $this->getResourceUriByName($resourceName, $variables);
		return $this->getResourceByUri($resourceUri);
	}

	/**
	 * @param string $resourceName
	 * @return string
	 * @param array $variables If set, these variables will be replaced in the URI (URI template)
	 * @throws Exception\UnknownResourceException
	 */
	public function getResourceUriByName($resourceName, array $variables = array()) {
		$this->initialize();
		if (!isset($this->state['_links'][$resourceName])) {
			throw new Exception\UnknownResourceException('Resource "' . $resourceName . '" is unknown');
		}
		$uri = $this->state['_links'][$resourceName]['href'];
		return $this->uriTemplateProcessor->expand($uri, $variables);
	}

	/**
	 * @param string $resourceUri
	 * @return \Wwwision\HalClient\Domain\Dto\Resource
	 */
	public function getResourceByUri($resourceUri) {
		$resourceData = $this->getResourceDataByUri($resourceUri);
		return new Resource($resourceData, $resourceUri, function($resourceUri) {
			return $this->getResourceDataByUri($resourceUri);
		});
	}

	/**
	 * @param string $resourceEndpoint
	 * @param array $resourceData
	 * @return Response
	 */
	public function createResource($resourceEndpoint, array $resourceData) {
		return $this->sendRequest($resourceEndpoint, 'POST', $resourceData);
	}

	/**
	 * @param string $resourceEndpoint
	 * @param array $resourceData
	 * @return Response
	 */
	public function updateResource($resourceEndpoint, array $resourceData) {
		return $this->sendRequest($resourceEndpoint, 'PUT', $resourceData);
	}

	/**
	 * @param string $resourceEndpoint
	 * @return Response
	 */
	public function deleteResource($resourceEndpoint) {
		return $this->sendRequest($resourceEndpoint, 'DELETE');
	}

	/**
	 * @param string $resourceUri
	 * @return array
	 * @throws Exception\FailedRequestException if response status code is != 2**
	 */
	public function getResourceDataByUri($resourceUri) {
		$response = $this->sendRequest($resourceUri);
		if (substr($response->getStatusCode(), 0, 1) !== '2') {
			throw new Exception\FailedRequestException($response, sprintf('Failed to request "%s", status: "%s" (%d)', $uri, $response->getStatus(), $response->getStatusCode()));
		}
		return json_decode($response->getContent(), TRUE);
	}

	/**
	 * @param string $path relative path to the resource, will be prefixed with rootUri
	 * @param string $method request method
	 * @param array $arguments
	 * @return Response
	 */
	public function sendRequest($path, $method = 'GET', array $arguments = array()) {
		$uri = sprintf('%s/%s', rtrim($this->baseUri, '/'), ltrim($path, '/'));
		$request = Request::create(new Uri($uri), $method, $arguments);

		// FIXME this is currently required as work around for http://forge.typo3.org/issues/51763
		$request->setContent('');

		foreach ($this->defaultHeaders as $headerName => $headerValue) {
			$request->setHeader($headerName, $headerValue);
		}
		return $this->browser->sendRequest($request);
	}
}
?>