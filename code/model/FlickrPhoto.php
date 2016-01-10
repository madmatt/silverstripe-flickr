<?php
/**
 * Class FlickrPhoto
 *
 * Represents a single photo record retrieved from the Flickr API.
 */
class FlickrPhoto extends FlickrData
{
    private static $casting = array(
        'Title' => 'Varchar',
        'Description' => 'Varchar'
    );

    public static function create_from_array($photo)
    {
        if (!isset($photo['id']) || !isset($photo['farm']) || !isset($photo['server']) || !isset($photo['secret'])) {
            return null;
        }

        return new FlickrPhoto($photo);
    }

    public function getSmallSquareUrl()
    {
        return $this->getUrl('s');
    }

    public function getLargeSquareUrl()
    {
        return $this->getUrl('q');
    }

    public function getThumbnailUrl()
    {
        return $this->getUrl('t');
    }

    public function getSmall240Url()
    {
        return $this->getUrl('m');
    }

    public function getSmall320Url()
    {
        return $this->getUrl('n');
    }

    public function getMedium640Url()
    {
        return $this->getUrl('z');
    }

    public function getMedium800Url()
    {
        return $this->getUrl('c');
    }

    public function getLarge1024Url()
    {
        return $this->getUrl('b');
    }

    public function getLarge1600Url()
    {
        return $this->getUrl('h');
    }

    public function getLarge2048Url()
    {
        return $this->getUrl('k');
    }

    public function getOriginalUrl()
    {
        return $this->getUrl('o');
    }

    public function getDescription()
    {
        return $this->data['description']['_content'];
    }

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
