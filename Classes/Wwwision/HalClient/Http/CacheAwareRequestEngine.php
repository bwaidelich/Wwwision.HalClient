<?php
namespace Wwwision\HalClient\Http;

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
use TYPO3\Flow\Cache\Frontend\VariableFrontend;
use TYPO3\Flow\Http\Client\RequestEngineInterface;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Http\Response;
use TYPO3\Flow\Utility\Now;

/**
 * An implementation of Flows RequestEngineInterface that supports common HTTP cache headers such as:
 * * Cache-Control
 * * Last-Modified
 * * ETag
 * To control caching of single requests.
 */
class CacheAwareRequestEngine implements RequestEngineInterface {

	/**
	 * @var RequestEngineInterface
	 */
	protected $decoratedRequestEngine;

	/**
	 * @Flow\Inject
	 * @var VariableFrontend
	 */
	protected $requestCache;

	/**
	 * @Flow\Inject
	 * @var Now
	 */
	protected $now;

	/**
	 * @param RequestEngineInterface $decoratedRequestEngine The actual request engine that should be used to send requests
	 */
	public function __construct(RequestEngineInterface $decoratedRequestEngine) {
		$this->decoratedRequestEngine = $decoratedRequestEngine;
	}

	/**
	 * Sends a prepared request and returns the respective response.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function sendRequest(Request $request) {
		$requestCacheIdentifier = $this->getRequestCacheIdentifier($request);
		$response = NULL;
		$cachedResponse = $this->requestCache->get($requestCacheIdentifier);
		if ($cachedResponse instanceof Response) {
			if (!$this->needsValidation($cachedResponse)) {
				return $cachedResponse;
			} else {
				$validationRequest = clone $request;
				if ($cachedResponse->hasHeader('ETag')) {
					$validationRequest->setHeader('If-None-Match', $cachedResponse->getHeader('ETag'), FALSE);
				}
				if ($cachedResponse->hasHeader('Last-Modified')) {
					$validationRequest->setHeader('If-Modified-Since', $cachedResponse->getHeader('Last-Modified'), FALSE);
				}
				$response = $this->decoratedRequestEngine->sendRequest($validationRequest);
				if ($response->getStatusCode() === 304) {
					return $cachedResponse;
				}
			}
		}

		if ($response === NULL) {
			$response = $this->decoratedRequestEngine->sendRequest($request);
			if ($response === NULL) {
				return NULL;
			}
		}
		$cacheLifetime = $this->calculateResponseCacheLifetime($response);
		if ($cacheLifetime !== NULL) {
			$this->requestCache->set($requestCacheIdentifier, $response, array(), $cacheLifetime);
		}
		return $response;
	}

	/**
	 * @param Request $request
	 * @return string unique cache identifier for the given $request
	 */
	protected function getRequestCacheIdentifier(Request $request) {
		return strtoupper($request->getMethod()) . '_' . md5($request->getUri());
	}

	/**
	 * @param Response $response
	 * @return boolean
	 */
	protected function needsValidation(Response $response) {
		if ($this->calculateResponseCacheLifetime($response) > 0) {
			return FALSE;
		}
		return ($response->hasHeader('ETag') ||$response->hasHeader('Last-Modified'));
	}

	/**
	 * @param Response $response
	 * @return integer or NULL if response must not be cached (0 = no expiration)
	 */
	protected function calculateResponseCacheLifetime(Response $response) {
		# for the "validation model" (see http://tools.ietf.org/html/rfc2616#section-13.3) the local cache must not expire
		if ($response->hasHeader('ETag') ||$response->hasHeader('Last-Modified')) {
			return 0;
		}

		# for the "expiration model" (http://tools.ietf.org/html/rfc2616#section-13.2) the local cache can be removed after the specified expiration
		$responseHeaders = $response->getHeaders();
		$maxAgeHeader = $responseHeaders->getCacheControlDirective('max-age');
		if ($maxAgeHeader !== NULL) {
			if ($maxAgeHeader <= 0) {
				return NULL;
			}
			return (integer)$maxAgeHeader;
		}
		$expiresHeader = $responseHeaders->get('Expires');
		if ($expiresHeader instanceof \DateTime) {
			$referenceDate = $this->getResponseDate($response);
			if ($referenceDate >= $expiresHeader) {
				return NULL;
			}
			return $expiresHeader->getTimestamp() - $referenceDate->getTimestamp();
		}
		return NULL;
	}

	/**
	 * @param $response
	 * @return \DateTime
	 */
	protected function getResponseDate(Response $response) {
		$dateHeader = $response->getHeader('Date');
		if ($dateHeader instanceof \DateTime) {
			return $dateHeader;
		}
		return $this->now;
	}
}