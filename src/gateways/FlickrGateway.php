<?php

namespace MadMatt\Flickr\Gateways;

use Exception;
use GuzzleHttp\Client;
use SilverStripe\Core\Config\Configurable;

class FlickrGateway
{
    use Configurable;

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
     * @var int timeout of the request in seconds
     * @link http://docs.guzzlephp.org/en/stable/request-options.html#timeout
     */
    private static $request_timeout = 0;

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
            'timeout' => $this->config()->request_timeout,
        ]);
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


    /**
     * @return bool true if the API is available right now, or false if it isn't
     */
    public function isAPIAvailable()
    {
        // Ensure we always query this, so we don't cache stale information, but only query once per request
        if ($this->apiAvailable) {
            return $this->apiAvailable;
        }

        $params = array(
            'method' => 'flickr.test.echo'
        );

        try {
            $response = $this->request($params);

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

        $this->apiAvailable = $return;
        return $return;
    }


    /**
     * @param array
     * @return array
     */
    public function request($params = [])
    {
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

        return $response;
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
}
