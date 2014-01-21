<?php
namespace Wwwision\HalClient\Tests\Unit;

use TYPO3\Flow\Tests\UnitTestCase;
use Wwwision\HalClient\UriTemplateProcessor;

/**
 * Testcase for UriTemplateProcessor class
 */
class UriTemplateProcessorTest extends UnitTestCase {

	/**
	 * @var UriTemplateProcessor
	 */
	protected $uriTemplateProcessor;

	public function setUp() {
		$this->uriTemplateProcessor = new UriTemplateProcessor();
	}

	/**
	 * @return array
	 */
	public function expandTestsDataProvider() {
		return array(
			array(
				'template' => 'http://example.com{+path}{/segments}{?query,data*,foo*}',
				'variables' => array(),
				'expectedResult' => 'http://example.com'
			),
			array(
				'template' => 'http://example.com{+path}{/segments}{?query,data*,foo*}',
				'variables' => array('path' => 'some path'),
				'expectedResult' => 'http://example.comsome%20path'
			),
			array(
				'template' => 'http://example.com{+path}{/segments}{?query,data*,foo*}',
				'variables' => array('segments' => array('segment1', 'segment2')),
				'expectedResult' => 'http://example.com/segment1,segment2'
			),
			array(
				'template' => 'http://example.com{+path}{/segments}{?query,data*,foo*}',
				'variables' => array('data' => array('foo' => array('bar', 'Baz'))),
				'expectedResult' => 'http://example.com?foo%5B0%5D=bar&foo%5B1%5D=Baz'
			),
			array(
				'template' => 'foo{?since,until}',
				'variables' => array('since' => '1980-12-13T20:10:50+0100'),
				'expectedResult' => 'foo?since=1980-12-13T20%3A10%3A50%2B0100'
			),
			array(
				'template' => 'foo{?since,until}',
				'variables' => array('until' => '2014-01-20T12:38:05+0100'),
				'expectedResult' => 'foo?until=2014-01-20T12%3A38%3A05%2B0100'
			),
			array(
				'template' => 'foo{?since,until}',
				'variables' => array('since' => '1980-12-13T20:10:50+0100', 'until' => '2014-01-20T12:38:05+0100'),
				'expectedResult' => 'foo?since=1980-12-13T20%3A10%3A50%2B0100&until=2014-01-20T12%3A38%3A05%2B0100'
			),
			array(
				'template' => 'foo?resourceName={rel}',
				'variables' => array('rel' => 'some-relation'),
				'expectedResult' => 'foo?resourceName=some-relation'
			),
		);
	}

	/**
	 * @param string $template
	 * @param array $variables
	 * @param string $expectedResult
	 * @test
	 * @dataProvider expandTestsDataProvider
	 */
	public function expandTests($template, array $variables, $expectedResult) {
		$this->assertSame($expectedResult, $this->uriTemplateProcessor->expand($template, $variables));
	}

}

?>