<?php

namespace MadMatt\Flickr\Services;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\ORM\ArrayList;
use Psr\SimpleCache\CacheInterface;
use MadMatt\Flickr\Model\FlickrPhoto;
use MadMatt\Flickr\Model\FlickrPhotoset;
use SilverStripe\Core\Injector\Injector;
use MadMatt\Flickr\Gateways\FlickrGateway;
use SilverStripe\Core\Config\Configurable;

class FlickrService
{
    use Configurable;

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
     * @var FlickrGateway
     */
    private $gateway;

    /**
     * @var array
     */
    private static $dependencies = [
        'FlickrGateway' => '%$' . FlickrGateway::class,
    ];

    public function __construct()
    {
        $this->setGateway(Injector::inst()->get(FlickrGateway::class));
    }

    /**
     * @param FlickrGateway $gateway
     * @return FlickrService
     */
    public function setGateway(FlickrGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * @return FlickrGateway
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * @param array
     * @return array
     */
    public function request($params)
    {
        return $this->gateway->request($params);
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
            $response = $this->request($params);

            if (!$response || $response['stat'] !== 'ok') {
                throw new Exception(sprintf('Response from Flickr not expected: %s', var_export($response, true)));
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
            $response = $this->request($params);

            if (!$response || $response['stat'] !== 'ok') {
                throw new Exception(sprintf('Response from Flickr not expected: %s', var_export($response, true)));
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

        $params = [
            'method' => 'flickr.photosets.getPhotos',
            'photoset_id' => $photosetId,
            'extras' => 'description,original_format'
        ];

        if ($userId) {
            $params['user_id'] = $userId;
        }

        try {
            $response = $this->request($params);

            if (!$response || !isset($response['stat']) || $response['stat'] !== 'ok') {
                throw new Exception(sprintf("Response from Flickr not expected: %s", var_export($response, true)));
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

        if (!($result = $cache->get($cacheKey))) {
            // try update the cache
            try {
                $result = call_user_func_array(array($this, $funcName), $args);

                // only update cache if result returned
                if ($result) {
                    $cache->set($cacheKey, $result, $this->config()->flickr_hard_cache_expiry);
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
        return $this->getGateway()->isAPIAvailable();
    }

    /**
     * Helper to get API key
     *
     * @return string
     * @return void
     */
    public function getApiKey()
    {
        return $this->gateway->getApiKey();
    }

    /**
     * Helper to set API key
     *
     * @param string $key
     * @return void
     */
    public function setApiKey($key)
    {
        $this->gateway->setApiKey($key);
        return $this;
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
}
