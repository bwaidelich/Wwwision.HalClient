<?php
namespace Wwwision\HalClient\Exception;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Wwwision.HalClient".    *
 *                                                                        */

use TYPO3\Flow\Http\Response;
use Wwwision\HalClient\Exception;

/**
 * "Failed request" exception
 */
class FailedRequestException extends Exception {

	/**
	 * @var Response
	 */
	protected $response;

	/**
	 * Overwrites parent constructor to be able to inject current response object.
	 *
	 * @param Response $response
	 * @param string $message
	 * @param integer $code
	 * @param \Exception $previousException
	 * @see \Exception
	 */
	public function __construct(Response $response, $message = '', $code = 0, \Exception $previousException = NULL) {
		$this->response = $response;
		parent::__construct($message, $code, $previousException);
	}

	/**
	 * Returns the response object that exception belongs to.
	 *
	 * @return Response
	 */
	public function getResponse() {
		return $this->response;
	}
}
?>

