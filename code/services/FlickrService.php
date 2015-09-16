<?php
class FlickrService extends RestfulService {
	/**
	 * @var int Expiry time for API calls
	 * This determines how long the cache is used before another API request
	 * is made to update the cache, measured in seconds. 3600 == 1 hour.
	 */
	private static $flickr_soft_cache_expiry = 3600;

	/**
	 * @var int Expiry time for SS_Cache
	 * This determines how long the cache is kept before it is permanently cleared.
	 * We need to clear at some point to ensure photosets removed from Flickr are eventually
	 * hidden at the website end, measured in seconds. 86400 == 1 day.
	 */
	private static $flickr_hard_cache_expiry = 86400;

	/**
	 * @var boolean To determine if errors should be logged everytime
	 * This can be turned on when using the getCachedCall method,
	 * so errors are only logged if both the API response and SS_Cache fallback fails.
	 */
	private static $skipErrorLogging = false;

	/**
	 * @var string The API key to be used for the next request to the API. This may change between requests (with calls
	 * to {@link self::setApiKey()}), so it's not a config variable.
	 */
	private $apiKey;

	/**
	 * @see self::isApiAvailable()
	 * @var bool true if the API is available, false if not
	 */
	private $apiAvailable;

	/**
	 * @see self::isApiAvailable()
	 * @var Integer The api response code from calling flickr.test.echo
	 */
	private $responseCode;

	/**
	 * @see self::isApiAvailable()
	 * @var String The api response message from calling flickr.test.echo
	 */
	private $responseMessage;

	public function __construct() {
		parent::__construct('https://www.flickr.com/services/rest/', $this->config()->flickr_soft_cache_expiry);
		$this->checkErrors = true;
	}

	/**
	 * @param string $userId The Flickr user_id to get all photosets for
	 * @todo Currently returns all photosets. Optimisations could be made to only return a single page of results
	 * @return ArrayList<FlickrPhotoset>
	 */
	public function getPhotosetsForUser($userId) {
		if(!$this->isAPIAvailable()) return null;

		$params = array(
			'method' => 'flickr.photosets.getList',
			'user_id' => $userId,
		);

		$this->setQueryString(array_merge($this->defaultParams(), $params));

		try {
			$response = $this->request()->getBody();
			$response = unserialize($response);

			if(!$response || $response['stat'] !== 'ok') {
				throw new Exception(sprintf('Response from Flickr not expected: %s', var_export($response, true)));
			}

			$results = new ArrayList();

			foreach($response['photosets']['photoset'] as $set) {
				$obj = FlickrPhotoset::create_from_array($set, $userId);

				if($obj) {
					$results->push($obj);
				}
			}

			return $results;
		} catch(Exception $e) {
			if(!$this->config()->skipErrorLogging) {
				SS_Log::log(
					sprintf(
						"Couldn't retrieve Flickr photosets for user '%s': Message: %s",
						$userId,
						$e->getMessage()
					),
					SS_Log::ERR
				);
			}

			return null;
		}
	}

	public function getPhotosetById($photosetId, $userId = null) {
		if(!$this->isAPIAvailable()) return null;

		$params = array(
			'method' => 'flickr.photosets.getInfo',
			'photoset_id' => $photosetId
		);

		if(!is_null($userId)) {
			$params['user_id'] = $userId;
		}

		$this->setQueryString(array_merge($this->defaultParams(), $params));

		try {
			$response = $this->request()->getBody();
			$response = unserialize($response);

			if(!$response || $response['stat'] !== 'ok') {
				throw new Exception(sprintf('Response from Flickr not expected: %s', var_export($response, true)));
			}

			$result = FlickrPhotoset::create_from_array($response['photoset'], $userId);
			return $result;
		} catch(Exception $e) {
			if(!$this->config()->skipErrorLogging) {
				SS_Log::log(
					sprintf(
						"Couldn't retrieve Flickr photoset for user '%s', photoset '%s': Message: %s",
						$userId,
						$photosetId,
						$e->getMessage()
					),
					SS_Log::ERR
				);
			}

			return null;
		}
	}

	/**
	 * Returns all photos within a given photoset.
	 *
	 * @param int $photosetId
	 * @param int|null $userId Optional, but API will respond faster if this is specified
	 * @return ArrayList<FlickrPhoto>
	 */
	public function getPhotosInPhotoset($photosetId, $userId = null) {
		if(!$this->isAPIAvailable()) return null;

		$params = array(
			'method' => 'flickr.photosets.getPhotos',
			'photoset_id' => $photosetId,
			'extras' => 'description,original_format'

		);

		if($userId) {
			$params['user_id'] = $userId;
		}

		$this->setQueryString(array_merge($this->defaultParams(), $params));

		try {
			$response = $this->request()->getBody();
			$response = unserialize($response);

			if(!$response || !isset($response['stat']) || $response['stat'] !== 'ok') {
				throw new Exception(sprintf("Response from Flickr not expected: %s", var_export($response, true)));
			}

			$results = new ArrayList();

			foreach($response['photoset']['photo'] as $photo) {
				$obj = FlickrPhoto::create_from_array($photo);

				if($obj) {
					$results->push($obj);
				}
			}

			return $results;
		} catch(Exception $e) {
			if(!$this->config()->skipErrorLogging) {
				SS_Log::log(
					sprintf(
						"Couldn't retrieve Flickr photos in photoset '%s' for optional user '%s'",
						$photosetId,
						$userId
					),
					SS_Log::ERR
				);
			}

			return null;
		}
	}

	/**
	 * This returns API responses saved to a SS_Cache file instead of the API response directly
	 * as the Flickr API is often not reliable
	 * 
	 * @param String $funcName Name of the function to call if cache expired or does not exist
	 * @param  array $args Arguments for the function
	 * @return ArrayList<FlickrPhoto|FlickrPhotoset>
	 */
	public function getCachedCall($funcName, $args = array()) {
		$result = null;
		$argsCount = count($args);

		if($argsCount < 1) {
			return $result;
		}

		// build up a unique cache name
		$cacheKey = array(
			$funcName
		);

		foreach ($args as $arg) {
			$cacheKey[] = $arg;
		}

		// to hide api key and remove non alphanumeric characters
		$cacheKey = md5(implode('_', $cacheKey));

		// setup cache
		$cache = SS_Cache::factory('Flickr');
		$cache->setOption('automatic_serialization', true);
		SS_Cache::set_cache_lifetime('Flickr', $this->config()->flickr_hard_cache_expiry);

		// check if cached response exists or soft expiry has elapsed
		$metadata = $cache->getBackend()->getMetadatas('Flickr' . $cacheKey);
		if(!($result = $cache->load($cacheKey)) || $this->softCacheExpired($metadata['mtime'])) {
			// try update the cache
			try {
				if($argsCount == 1) {
					$result = $this->$funcName($args[0]);
				} elseif($argsCount == 2) {
					$result = $this->$funcName($args[0], $args[1]);
				}

				// only update cache if result returned
				if($result) {
					$cache->save($result, $cacheKey);
				}
			} catch(Exception $e) {
				SS_Log::log(
					sprintf(
						"Couldn't retrieve Flickr photos using '%s': Message: %s",
						$funcName,
						$e->getMessage()
					),
					SS_Log::ERR
				);
			}
		}

		return $result;
	}

	/**
	 * @return bool true if the API is available right now, or false if it isn't
	 */
	public function isAPIAvailable() {
		// Ensure we always query this, so we don't cache stale information, but only query once per request
		if($this->apiAvailable) return $this->apiAvailable;
		$oldExpiry = $this->cache_expire;
		$this->cache_expire = 0;

		$params = array(
			'method' => 'flickr.test.echo'
		);

		$this->setQueryString(array_merge($this->defaultParams(), $params));

		try {
			$response = $this->request()->getBody();
			$response = unserialize($response);

			$return = $response['stat'] === "ok";

			/*
			 * $response contains an array, e.g.
			 * {"stat":"fail", "code":100, "message":"Invalid API Key (Key has invalid format)"}
			 */
			if($response['stat'] === "ok") {
				$return = true;
			}
			else {
				// save the error code and message for service consumers to utilise
				$this->responseCode = $response['code'];
				$this->responseMessage = $response['message'];

				$return = false;
			}
		} catch(Exception $e) {
			$return = false;
		}

		$this->cache_expire = $oldExpiry;

		$this->apiAvailable = $return;
		return $return;
	}

	/** 
	 * @param int $modifiedTime Timestamp of when cache file was last modified
	 */
	public function softCacheExpired($modified) {
		return time() > $modified + $this->config()->flickr_soft_cache_expiry;
	}

	public function setApiKey($key) {
		$this->apiKey = $key;
		return $this;
	}

	public function getApiKey() {
		return $this->apiKey;
	}

	/**
	 * Get the API response code
	 * @return String
	 */
	public function getApiResponseCode() {
		return $this->responseCode;
	}

	/**
	 * Get the API response message
	 * @return String
	 */
	public function getApiResponseMessage() {
		return $this->responseMessage;
	}
		
	private function defaultParams() {
		return array(
			'api_key' => $this->getApiKey(),
			'format' => 'php_serial'
		);
	}
}
