<?php

namespace MadMatt\Flickr\Model;

use MadMatt\Flickr\Model\FlickrData;
use SilverStripe\Control\Director;

/**
 * Class FlickrPhoto
 *
 * Represents a single photo record retrieved from the Flickr API.
 */
class FlickrPhoto extends FlickrData
{
    /**
     * @var array
     */
    private static $casting = [
        'Title' => 'Varchar',
        'Description' => 'Varchar'
    ];

    /**
     * @param mixed $photo
     * @return void
     */
    public static function create_from_array($photo)
    {
        if (!isset($photo['id']) || 
            !isset($photo['farm']) || 
            !isset($photo['server']) || 
            !isset($photo['secret'])
        ) {
            return null;
        }

        return new FlickrPhoto($photo);
    }

    /**
     * @return string
     */
    public function getSmallSquareUrl()
    {
        return $this->getUrl('s');
    }

    /**
     * @return string
     */
    public function getLargeSquareUrl()
    {
        return $this->getUrl('q');
    }

    /**
     * @return string
     */
    public function getThumbnailUrl()
    {
        return $this->getUrl('t');
    }

    /**
     * @return string
     */
    public function getSmall240Url()
    {
        return $this->getUrl('m');
    }

    /**
     * @return string
     */
    public function getSmall320Url()
    {
        return $this->getUrl('n');
    }

    /**
     * @return string
     */
    public function getMedium640Url()
    {
        return $this->getUrl('z');
    }

    /**
     * @return string
     */
    public function getMedium800Url()
    {
        return $this->getUrl('c');
    }

    /**
     * @return string
     */
    public function getLarge1024Url()
    {
        return $this->getUrl('b');
    }

    /**
     * @return string
     */
    public function getLarge1600Url()
    {
        return $this->getUrl('h');
    }

    /**
     * @return string
     */
    public function getLarge2048Url()
    {
        return $this->getUrl('k');
    }

    /**
     * @return string
     */
    public function getOriginalUrl()
    {
        return $this->getUrl('o');
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->data['description']['_content'];
    }

    /**
     * @return string
     */
    private function getUrl($size = 'o')
    {
        return sprintf(
            '%sfarm%d.staticflickr.com/%d/%d_%s_%s.jpg',
            Director::protocol(),
            $this->data['farm'],
            $this->data['server'],
            $this->data['id'],
            $this->data['secret'],
            $size
        );
    }
}
