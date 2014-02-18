<?php
namespace Wwwision\HalClient\Tests\Unit\Http;

use TYPO3\Flow\Cache\Frontend\VariableFrontend;
use TYPO3\Flow\Http\Client\RequestEngineInterface;
use TYPO3\Flow\Http\Headers;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Http\Response;
use TYPO3\Flow\Tests\UnitTestCase;
use TYPO3\Flow\Utility\Now;
use Wwwision\HalClient\Http\CacheAwareRequestEngine;

/**
 * Testcase for the CacheAwareRequestEngine class
 */
class CacheAwareRequestEngineTest extends UnitTestCase {

	/**
	 * @var CacheAwareRequestEngine
	 */
	protected $cacheAwareRequestEngine;

	/**
	 * A mock of \TYPO3\Flow\Utility\Now to test absolute expiration dates
	 *
	 * @var Now
	 */
	protected $mockNow;

	/**
	 * @var RequestEngineInterface
	 */
	protected $mockDecoratedRequestEngine;

	/**
	 * @var VariableFrontend
	 */
	protected $mockRequestCache;

	/**
	 * @var Request
	 */
	protected $mockRequest;

	/**
	 * @var Response
	 */
	protected $mockResponse;

	/**
	 * @var Headers
	 */
	protected $mockHeaders;

	/**
	 * Sets up this test case
	 */
	public function setUp() {
		$this->mockDecoratedRequestEngine = $this->getMockBuilder('TYPO3\Flow\Http\Client\RequestEngineInterface')->getMock();
		$this->cacheAwareRequestEngine = new CacheAwareRequestEngine($this->mockDecoratedRequestEngine);

		$this->mockRequestCache = $this->getMockBuilder('TYPO3\Flow\Cache\Frontend\VariableFrontend')->disableOriginalConstructor()->getMock();
		$this->inject($this->cacheAwareRequestEngine, 'requestCache', $this->mockRequestCache);

		# simulating a date in order to test absolute expiration dates
		$this->mockNow = new Now('Sat, 13 Dec 2014 20:00:00 +0100');
		$this->inject($this->cacheAwareRequestEngine, 'now', $this->mockNow);

		# we use real request/response objects by intention (otherwise there are too many mocks to create)
		$this->mockRequest = new Request(array(), array(), array(), array());
		$this->mockResponse = new Response();
	}

	/**
	 * @test
	 */
	public function sendRequestForwardsRequestToDecoratedRequestEngineByDefault() {
		$this->mockDecoratedRequestEngine->expects($this->once())->method('sendRequest')->with($this->mockRequest)->will($this->returnValue($this->mockResponse));
		$this->assertSame($this->mockResponse, $this->cacheAwareRequestEngine->sendRequest($this->mockRequest));
	}

	/**
	 * @test
	 */
	public function sendRequestDoesNotCacheResponseByDefault() {
		$this->mockDecoratedRequestEngine->expects($this->once())->method('sendRequest')->with($this->mockRequest)->will($this->returnValue($this->mockResponse));
		$this->mockRequestCache->expects($this->never())->method('set');
		$this->cacheAwareRequestEngine->sendRequest($this->mockRequest);
	}

	public function uncacheableResponseDataProvider() {
		return array(
			array(
				'responseHeaders' => array(
				),
			),
			array(
				'responseHeaders' => array(
					'Expires' => 'Sat, 13 Dec 2014 20:00:00 +0100',
				),
			),
			array(
				'responseHeaders' => array(
					'Expires' => 'Sat, 13 Dec 2014 18:30:00 +0100',
				),
			),
			array(
				'responseHeaders' => array(
					'Cache-Control' => 'max-age=0',
				),
			),
			array(
				'responseHeaders' => array(
					'Cache-Control' => 'max-age=-5',
				),
			),
		);
	}

	/**
	 * @param array $responseHeaders
	 * @test
	 * @dataProvider uncacheableResponseDataProvider
	 */
	public function uncacheableResponseTests(array $responseHeaders) {
		$response = new Response();
		foreach ($responseHeaders as $headerName => $headerValue) {
			$response->setHeader($headerName, $headerValue);
		}
		$this->mockDecoratedRequestEngine->expects($this->any())->method('sendRequest')->will($this->returnValue($response));
		$this->mockRequestCache->expects($this->never())->method('set');

		$this->cacheAwareRequestEngine->sendRequest($this->mockRequest);
	}

	public function cacheableResponseDataProvider() {
		return array(
			array(
				'responseHeaders' => array(
					'Cache-Control' => 'max-age=1234',
				),
				'expectedCacheLifetime' => 1234
			),
			array(
				'responseHeaders' => array(
					'Expires' => 'Sat, 13 Dec 2014 20:00:12 +0100',
				),
				'expectedCacheLifetime' => 12
			),
			array(
				'responseHeaders' => array(
					'Expires' => 'Sat, 13 Dec 2014 20:00:36 +0000',
				),
				'expectedCacheLifetime' => 3636
			),
			array(
				'responseHeaders' => array(
					'Expires' => 'Sun, 14 Dec 2014 20:12:34 +0000',
				),
				'expectedCacheLifetime' => 90754
			),
			array(
				'responseHeaders' => array(
					'Cache-Control' => 'max-age=123',
				),
				'expectedCacheLifetime' => 123
			),
			# max-age overrules Expires (see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.3)
			array(
				'responseHeaders' => array(
					'Expires' => 'Sun, 14 Dec 2014 20:12:34 +0000',
					'Cache-Control' => 'max-age=21600',
				),
				'expectedCacheLifetime' => 21600
			),
			# Date header is used as reference date if existent
			array(
				'responseHeaders' => array(
					'Date' => 'Sun, 14 Dec 2014 20:00:00 +0000',
					'Expires' => 'Sun, 14 Dec 2014 20:01:00 +0000',
				),
				'expectedCacheLifetime' => 60
			),

			# "validation model" cache entries must not expire (lifetime = 0)
			array(
				'responseHeaders' => array(
					'Last-Modified' => 'Sun, 14 Dec 2014 20:00:00 +0000',
				),
				'expectedCacheLifetime' => 0
			),
			array(
				'responseHeaders' => array(
					'Last-Modified' => 'Sun, 14 Dec 2014 20:00:00 +0000',
					'Expires' => 'Sat, 13 Dec 2014 18:30:00 +0100',
				),
				'expectedCacheLifetime' => 0
			),
			array(
				'responseHeaders' => array(
					'ETag' => 'SomeETag',
				),
				'expectedCacheLifetime' => 0
			),
			array(
				'responseHeaders' => array(
					'ETag' => 'SomeETag',
					'Cache-Control' => 'max-age=123',
				),
				'expectedCacheLifetime' => 0
			),
		);
	}

	/**
	 * @param array $responseHeaders
	 * @param integer $expectedCacheLifetime
	 * @test
	 * @dataProvider cacheableResponseDataProvider
	 */
	public function cacheableResponseTests(array $responseHeaders, $expectedCacheLifetime) {
		$response = new Response();
		foreach ($responseHeaders as $headerName => $headerValue) {
			$response->setHeader($headerName, $headerValue);
		}
		$this->mockDecoratedRequestEngine->expects($this->any())->method('sendRequest')->will($this->returnValue($response));
		$this->mockRequestCache->expects($this->once())->method('set')->with($this->anything(), $response, $this->anything(), $expectedCacheLifetime);

		$this->cacheAwareRequestEngine->sendRequest($this->mockRequest);
	}

	/**
	 * @test
	 */
	public function sendRequestReturnsCachedResponseIfExistent() {
		$this->mockRequestCache->expects($this->any())->method('get')->will($this->returnValue($this->mockResponse));
		$this->mockDecoratedRequestEngine->expects($this->never())->method('sendRequest');

		$this->cacheAwareRequestEngine->sendRequest($this->mockRequest);
	}

	/**
	 * @test
	 */
	public function sendRequestValidatesResponsesWithETagHeaderAndReturnsCachedResponseIfContentHasNotBeenModified() {
		$eTag = 'someUniqueHash';

		$cachedResponse = new Response();
		$cachedResponse->setHeader('ETag', $eTag);
		$this->mockRequestCache->expects($this->any())->method('get')->will($this->returnValue($cachedResponse));

		$validatedResponse = new Response();
		$validatedResponse->setStatus(304);

		$this->mockDecoratedRequestEngine->expects($this->once())->method('sendRequest')->will($this->returnCallback(function(Request $request) use ($validatedResponse, $eTag) {
			$this->assertSame($eTag, $request->getHeader('If-None-Match'));
			return $validatedResponse;
		}));

		$this->assertSame($cachedResponse, $this->cacheAwareRequestEngine->sendRequest($this->mockRequest));
	}

	/**
	 * @test
	 */
	public function sendRequestValidatesResponsesWithETagHeaderAndReturnsValidatedResponseIfContentHasBeenModified() {
		$eTag = 'someUniqueHash';

		$cachedResponse = new Response();
		$cachedResponse->setHeader('ETag', $eTag);
		$this->mockRequestCache->expects($this->any())->method('get')->will($this->returnValue($cachedResponse));

		$validatedResponse = new Response();
		$validatedResponse->setStatus(200);
		$validatedResponse->setHeader('ETag', 'someNewETag');

		$this->mockDecoratedRequestEngine->expects($this->once())->method('sendRequest')->will($this->returnCallback(function(Request $request) use ($validatedResponse, $eTag) {
			$this->assertSame($eTag, $request->getHeader('If-None-Match'));
			return $validatedResponse;
		}));

		$this->assertSame($validatedResponse, $this->cacheAwareRequestEngine->sendRequest($this->mockRequest));
	}

	/**
	 * @test
	 */
	public function sendRequestValidatesResponsesWithLastModifiedHeaderAndReturnsCachedResponseIfContentHasNotBeenModified() {
		$lastModified = $this->mockNow;

		$cachedResponse = new Response();
		$cachedResponse->setHeader('Last-Modified', $lastModified);
		$this->mockRequestCache->expects($this->any())->method('get')->will($this->returnValue($cachedResponse));

		$validatedResponse = new Response();
		$validatedResponse->setStatus(304);

		$this->mockDecoratedRequestEngine->expects($this->once())->method('sendRequest')->will($this->returnCallback(function(Request $request) use ($validatedResponse, $lastModified) {
			$this->assertSame($lastModified->getTimestamp(), $request->getHeader('If-Modified-Since')->getTimeStamp());
			return $validatedResponse;
		}));

		$this->assertSame($cachedResponse, $this->cacheAwareRequestEngine->sendRequest($this->mockRequest));
	}

	/**
	 * @test
	 */
	public function sendRequestValidatesResponsesWithLastModifiedHeaderAndReturnsValidatedResponseIfContentHasBeenModified() {
		$lastModified = $this->mockNow;
		$newLastModified = clone $lastModified;
		$newLastModified->modify('+1 day');

		$cachedResponse = new Response();
		$cachedResponse->setHeader('Last-Modified', $lastModified);
		$this->mockRequestCache->expects($this->any())->method('get')->will($this->returnValue($cachedResponse));

		$validatedResponse = new Response();
		$validatedResponse->setStatus(200);
		$validatedResponse->setHeader('Last-Modified', $newLastModified);

		$this->mockDecoratedRequestEngine->expects($this->once())->method('sendRequest')->will($this->returnCallback(function(Request $request) use ($validatedResponse, $lastModified) {
			$this->assertSame($lastModified->getTimestamp(), $request->getHeader('If-Modified-Since')->getTimeStamp());
			return $validatedResponse;
		}));

		$this->assertSame($validatedResponse, $this->cacheAwareRequestEngine->sendRequest($this->mockRequest));
	}

	/**
	 * @test
	 */
	public function sendRequestStoresValidatedResponseInCacheIfContentHasBeenModified() {
		$cachedResponse = new Response();
		$cachedResponse->setHeader('ETag', 'someUniqueHash');
		$this->mockRequestCache->expects($this->any())->method('get')->will($this->returnValue($cachedResponse));

		$validatedResponse = new Response();
		$validatedResponse->setStatus(200);
		$validatedResponse->setHeader('ETag', 'someNewETag');

		$this->mockDecoratedRequestEngine->expects($this->once())->method('sendRequest')->will($this->returnValue($validatedResponse));

		$this->mockRequestCache->expects($this->any())->method('set')->with($this->anything(), $validatedResponse, $this->anything(), 0);

		$this->cacheAwareRequestEngine->sendRequest($this->mockRequest);
	}

}