<?php
namespace Wwwision\HalClient\Domain\Dto;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Wwwision.HalClient".    *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use Wwwision\HalClient\Exception as Exception;

/**
 * A single HAL resource
 */
class Resource implements \ArrayAccess {

	/**
	 * @var array<Resource>
	 */
	protected $linkedResources = array();

	/**
	 * @var array<Resource>
	 */
	protected $embeddedResources = array();

	/**
	 * @var array
	 */
	protected $state;

	/**
	 * @var string
	 */
	protected $uri;

	/**
	 * @var boolean
	 */
	protected $fullyLoaded = FALSE;

	/**
	 * @var \Closure a closure that gets the resource URI and returns a Resource – usually \Wwwision\HalClient\Client::sendRequest()
	 */
	protected $loadResourceClosure;

	/**
	 * @param array $resourceData
	 * @param string $uri
	 * @param \Closure $loadResourceClosure a closure that gets the resource URI and returns a Resource – usually \Wwwision\HalClient\Client::sendRequest()
	 */
	public function __construct(array $resourceData, $uri = NULL, \Closure $loadResourceClosure = NULL) {
		$this->state = $resourceData;
		$this->uri = $uri;
		$this->loadResourceClosure = $loadResourceClosure;
		$this->extractEmbeddedResources();
		$this->extractLinkedResources();
	}

	/**
	 * @return void
	 */
	protected function extractEmbeddedResources() {
		if (!isset($this->state['_embedded'])) {
			return;
		}
		foreach ($this->state['_embedded'] as $embeddedResourceName => $embeddedResourcesData) {
			$embeddedResourceName = str_replace(':', '_', $embeddedResourceName);
			$this->embeddedResources[$embeddedResourceName] = array();
			foreach ($embeddedResourcesData as $embeddedResourceData) {
				$singleResourceUri = isset($embeddedResourceData['_links']['self']['href']) ? $embeddedResourceData['_links']['self']['href'] : NULL;
				$this->embeddedResources[$embeddedResourceName][] = new Resource($embeddedResourceData, $singleResourceUri, $this->loadResourceClosure);
			}
		}
		unset($this->state['_embedded']);
	}

	/**
	 * @return void
	 */
	protected function extractLinkedResources() {
		if (!isset($this->state['_links'])) {
			return;
		}
		$resourceUri = NULL;
		foreach ($this->state['_links'] as $linkName => $linkData) {
			if ($linkName === 'self') {
				continue;
			}
			$linkName = str_replace(':', '_', $linkName);
			if (isset($this->linkedResources[$linkName])) {
				continue;
			}
			$this->linkedResources[$linkName] = new Resource(array(), $linkData['href'], $this->loadResourceClosure);
		}
		unset($this->state['_links']);
	}

	/**
	 * load full state of this resource
	 */
	protected function load() {
		if ($this->fullyLoaded || $this->loadResourceClosure === NULL || $this->uri === NULL) {
			return;
		}
		$fullState = call_user_func($this->loadResourceClosure, $this->uri);
		$this->state = array_merge($this->state, $fullState);
		$this->extractEmbeddedResources();
		$this->extractLinkedResources();
		$this->fullyLoaded = TRUE;
	}

	/**
	 * @return string
	 */
	public function getUri() {
		return $this->uri;
	}

	/**
	 * @return array<Resource>
	 */
	public function getLinkedResources() {
		$this->load();
		return $this->linkedResources;
	}

	/**
	 * Returns the linked resource, or NULL if not existing
	 *
	 * @param string $resourceName
	 * @return Resource
	 */
	public function getLinkedResource($resourceName) {
		$resourceName = str_replace(':', '_', $resourceName);
		$linkedResources = $this->getLinkedResources();
		return isset($linkedResources[$resourceName]) ? $linkedResources[$resourceName] : NULL;
	}

	/**
	 * @return array<Resource>
	 */
	public function getEmbeddedResources() {
		$this->load();
		return $this->embeddedResources;
	}

	/**
	 * Returns the embedded resource, or NULL if not existing
	 *
	 * @param string $resourceName
	 * @return Resource
	 */
	public function getEmbeddedResource($resourceName) {
		$resourceName = str_replace(':', '_', $resourceName);
		$embeddedResources = $this->getEmbeddedResources();
		return isset($embeddedResources[$resourceName]) ? $embeddedResources[$resourceName] : NULL;
	}

	/**
	 * @param mixed $propertyName
	 * @return boolean
	 */
	public function offsetExists($propertyName) {
		if (isset($this->state[$propertyName])) {
			return TRUE;
		}
		if ($this->fullyLoaded) {
			return FALSE;
		}
		$this->load();
		return isset($this->state[$propertyName]);
	}

	/**
	 * @param mixed $propertyName
	 * @return mixed
	 */
	public function offsetGet($propertyName) {
		if (isset($this->state[$propertyName])) {
			return $this->state[$propertyName];
		}
		if ($this->fullyLoaded) {
			return NULL;
		}
		$this->load();
		return isset($this->state[$propertyName]) ? $this->state[$propertyName] : NULL;
	}

	/**
	 * @param mixed $propertyName
	 * @param mixed $value
	 */
	public function offsetSet($propertyName, $value) {
		$this->state[$propertyName] = $value;
	}

	/**
	 * @param mixed $propertyName
	 * @return void
	 */
	public function offsetUnset($propertyName) {
		unset($this->state[$propertyName]);
	}
}
?>