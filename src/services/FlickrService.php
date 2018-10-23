<?php

namespace MadMatt\Flickr\Services;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\ORM\ArrayList;
use Psr\SimpleCache\CacheInterface;
use MadMatt\Flickr\Model\FlickrPhotoset;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;

class FlickrService
{
    use Configurable;

    /**
     * Handler for the GuzzleHttp client, has replaced the 'RestfulService' API
     * as it is now deprecated in 4.0
     *
     * @var GuzzleHttp
     */
    private $client;

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
    private static $skip_error_logging = false;

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

    /**
     * Instantiate Guzzle client with Flickr uri
     */
    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://www.flickr.com/services/rest/',
            'timeout' => $this->config()->flickr_soft_cache_expiry,
        ]);
    }

    public function setClient($client)
    {
        $this->client = $client;
    }

    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param string $userId The Flickr user_id to get all photosets for
     * @todo Currently returns all photosets. Optimisations could be made to only return a single page of results
     * @return ArrayList<FlickrPhotoset>
     */
    public function getPhotosetsForUser($userId)
    {
        if (!$this->isAPIAvailable()) {
            return null;
        }

        $params = [
            'method' => 'flickr.photosets.getList',
            'user_id' => $userId,
        ];

        try {
            $request = $this->client->request(
                'GET',
                'https://www.flickr.com/services/rest/',
                [
                    'query' => array_merge($this->defaultParams(), $params)
                ]
            );

            $rawResponse = $request->getBody();
            $response = unserialize($rawResponse);

            if (!$response || $response['stat'] !== 'ok') {
                throw new Exception(sprintf('Response from Flickr not expected: %s', var_export($rawResponse, true)));
            }

            $results = new ArrayList();

            foreach ($response['photosets']['photoset'] as $set) {
                $obj = FlickrPhotoset::create_from_array($set, $userId);

                if ($obj) {
                    $results->push($obj);
                }
            }

            return $results;
        } catch (Exception $e) {
            if (!$this->config()->skip_error_logging) {
                Injector::inst()->get(LoggerInterface::class)->error(
                    sprintf(
                        "Couldn't retrieve Flickr photosets for user '%s': Message: %s",
                        $userId,
                        $e->getMessage()
                    )
                );
            }

            return null;
        }
    }

    /**
     *
     * @param int $photosetId
     * @param int $userId
     * @return ArrayList<FlickrPhoto>
     */
    public function getPhotosetById($photosetId, $userId = null)
    {
        if (!$this->isAPIAvailable()) {
            return null;
        }

        $params = array(
            'method' => 'flickr.photosets.getInfo',
            'photoset_id' => $photosetId
        );

        if (!is_null($userId)) {
            $params['user_id'] = $userId;
        }

        try {
            $request = $this->client->request(
                'GET',
                'https://www.flickr.com/services/rest/',
                [
                    'query' => array_merge($this->defaultParams(), $params)
                ]
            );
            $rawResponse = $request->getBody();
            $response = unserialize($rawResponse);

            if (!$response || $response['stat'] !== 'ok') {
                throw new Exception(sprintf('Response from Flickr not expected: %s', var_export($rawResponse, true)));
            }

            $result = FlickrPhotoset::create_from_array($response['photoset'], $userId);
            return $result;
        } catch (Exception $e) {
            if (!$this->config()->skip_error_logging) {
                Injector::inst()->get(LoggerInterface::class)->error(
                    sprintf(
                        "Couldn't retrieve Flickr photoset for user '%s', photoset '%s': Message: %s",
                        $userId,
                        $photosetId,
                        $e->getMessage()
                    )
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
    public function getPhotosInPhotoset($photosetId, $userId = null)
    {
        if (!$this->isAPIAvailable()) {
            return null;
        }

        $params = array(
            'method' => 'flickr.photosets.getPhotos',
            'photoset_id' => $photosetId,
            'extras' => 'description,original_format'
        );

        if ($userId) {
            $params['user_id'] = $userId;
        }

        try {
            $request = $this->client->request(
                'GET',
                'https://www.flickr.com/services/rest/',
                [
                    'query' => array_merge($this->defaultParams(), $params)
                ]
            );
            $rawResponse = $request->getBody();
            $response = unserialize($rawResponse);

            if (!$response || !isset($response['stat']) || $response['stat'] !== 'ok') {
                throw new Exception(sprintf("Response from Flickr not expected: %s", var_export($rawResponse, true)));
            }

            $results = new ArrayList();

            foreach ($response['photoset']['photo'] as $photo) {
                $obj = FlickrPhoto::create_from_array($photo);

                if ($obj) {
                    $results->push($obj);
                }
            }

            return $results;
        } catch (Exception $e) {
            if (!$this->config()->skip_error_logging) {
                Injector::inst()->get(LoggerInterface::class)->error(
                    sprintf(
                        "Couldn't retrieve Flickr photos in photoset '%s' for optional user '%s'",
                        $photosetId,
                        $userId
                    )
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
    public function getCachedCall($funcName, $args = array())
    {
        $result = null;
        $argsCount = count($args);

        if ($argsCount < 1) {
            return $result;
        }

        // build up a unique cache name
        $cacheKey = [
            $funcName
        ];

        foreach ($args as $arg) {
            $cacheKey[] = $arg;
        }

        // to hide api key and remove non alphanumeric characters
        $cacheKey = md5(implode('_', $cacheKey));

        // setup cache
        $cache = Injector::inst()->get(CacheInterface::class . '.FlickrService');
        $cache->setOption('automatic_serialization', true);
        $cache::set_cache_lifetime('FlickrService', $this->config()->flickr_hard_cache_expiry);

        // check if cached response exists or soft expiry has elapsed
        $metadata = $cache->getBackend()->getMetadatas('FlickrService' . $cacheKey);
        if (!($result = $cache->load($cacheKey)) || $this->softCacheExpired($metadata['mtime'])) {
            // try update the cache
            try {
                $result = call_user_func_array(array($this, $funcName), $args);

                // only update cache if result returned
                if ($result) {
                    $cache->save($result, $cacheKey);
                }
            } catch (Exception $e) {
                Injector::inst()->get(LoggerInterface::class)->error(
                    sprintf(
                        "Couldn't retrieve Flickr photos using '%s': Message: %s",
                        $funcName,
                        $e->getMessage()
                    )
                );
            }
        }

        return $result;
    }

    /**
     * @return bool true if the API is available right now, or false if it isn't
     */
    public function isAPIAvailable()
    {
        // Ensure we always query this, so we don't cache stale information, but only query once per request
        if ($this->apiAvailable) {
            return $this->apiAvailable;
        }
        // $oldExpiry = $this->cache_expire;
        // $this->cache_expire = 0;

        $params = array(
            'method' => 'flickr.test.echo'
        );

        try {
            $request = $this->client->request(
                'GET',
                'https://www.flickr.com/services/rest/',
                [
                    'query' => array_merge($this->defaultParams(), $params)
                ]
            );

            $rawResponse = $request->getBody();
            $response = unserialize($rawResponse);

            $return = $response['stat'] === "ok";

            /*
             * $response contains an array, e.g.
             * {"stat":"fail", "code":100, "message":"Invalid API Key (Key has invalid format)"}
             */
            if ($response['stat'] === "ok") {
                $return = true;
            } else {
                // save the error code and message for service consumers to utilise
                $this->responseCode = $response['code'];
                $this->responseMessage = $response['message'];

                $return = false;
            }
        } catch (Exception $e) {
            $return = false;
        }

        // $this->cache_expire = $oldExpiry;

        $this->apiAvailable = $return;
        return $return;
    }

    /**
     * @param int $modifiedTime Timestamp of when cache file was last modified
     * @return boolean Check to see if the soft cache has expired
     */
    public function softCacheExpired($modifiedTime)
    {
        return time() > $modifiedTime + $this->config()->flickr_soft_cache_expiry;
    }

    /**
     * Helper to set API key
     *
     * @param string $key
     * @return void
     */
    public function setApiKey($key)
    {
        $this->apiKey = $key;
        return $this;
    }

    /**
     * Helper to get the API key
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Get the API response code
     *
     * @param Response
     * @return String
     */
    public function getApiResponseCode($response)
    {
        return $response->getStatusCode();
    }

    /**
     * Get the API response message
     *
     * @param Response
     * @return String
     */
    public function getApiResponseMessage($response)
    {
        return $response->getReasonPhrase();
    }

    /**
     * Helper to get default params of client
     *
     * @return array
     */
    private function defaultParams()
    {
        return array(
            'api_key' => $this->getApiKey(),
            'format' => 'php_serial'
        );
    }
}
