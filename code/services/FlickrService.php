<?php
class FlickrService extends RestfulService {
	/**
	 * @var int Expiry time for API calls, measured in seconds. 3600 == 1 hour.
	 */
	private static $flickr_cache_expiry = 3600;

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

	public function __construct() {
		parent::__construct('https://www.flickr.com/services/rest/', $this->config()->flickr_cache_expiry);
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
			SS_Log::log(
				sprintf(
					"Couldn't retrieve Flickr photosets for user '%s': Message: %s",
					$userId,
					$e->getMessage()
				),
				SS_Log::ERR
			);

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

			$result = FlickrPhotoset::create_from_array($response['photoset']);
			return $result;
		} catch(Exception $e) {
			SS_Log::log(
				sprintf(
					"Couldn't retrieve Flickr photoset for user '%s', photoset '%s': Message: %s",
					$userId,
					$photosetId,
					$e->getMessage()
				),
				SS_Log::ERR
			);

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
			SS_Log::log(
				sprintf(
					"Couldn't retrieve Flickr photos in photoset '%s' for optional user '%s'",
					$photosetId,
					$userId
				),
				SS_Log::ERR
			);

			return null;
		}
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
		} catch(Exception $e) {
			$return = false;
		}

		$this->cache_expire = $oldExpiry;

		$this->apiAvailable = $return;
		return $return;
	}

	public function setApiKey($key) {
		$this->apiKey = $key;
		return $this;
	}

	public function getApiKey() {
		return $this->apiKey;
	}

	private function defaultParams() {
		return array(
			'api_key' => $this->getApiKey(),
			'format' => 'php_serial'
		);
	}
}