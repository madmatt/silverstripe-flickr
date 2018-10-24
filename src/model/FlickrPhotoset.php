<?php

namespace MadMatt\Flickr\Model;

use MadMatt\Flickr\Model\FlickrData;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Director;

class FlickrPhotoset extends FlickrData
{
    /**
     * @var array
     */
    private static $casting = [
        'Title' => 'Varchar',
        'ID' => 'Varchar'
    ];
    
    /**
     * @var string photoset owner identifier
     */
    private $userId = null;

    /**
     * Override constructor to set userId if passed along
     * @param array $set of data passed from request
     * @param string $userId|null
     */
    public function __construct($set, $userId = null)
    {
        parent::__construct($set);
        $this->userId = $userId;
    }

    /**
     * Transforms API response into parsed array
     * @param array $set
     * @param string $userId|null userId required for certain functions
     * @return FlickrPhotoset|null
     */
    public static function create_from_array($set, $userId = null)
    {
        // Validate input and return null if required params are not set
        if (!isset($set['id']) || 
            !isset($set['title']) || 
            !isset($set['title']['_content'])
        ) {
            return null;
        }

        return new FlickrPhotoset($set, $userId);
    }


    /**
     * Return photos within this photoset
     * @return ArrayList<FlickrPhoto>
     */
    public function getPhotos()
    {
        /** @var FlickrService $flickrService */
        $flickrService = Injector::inst()->get('FlickrService');

        return $flickrService->getPhotosInPhotoset($this->ID);
    }

    public function getTitle()
    {
        return $this->data['title']['_content'];
    }

    /**
     * Returns Url to the photoset
     * @return string|"" url of the photoset if userId is set
     */
    public function getUrl()
    {
        if ($this->userId === null || !is_string($this->userId)) {
            return "";
        }
        
        return sprintf(
            '%swww.flickr.com/photos/%s/sets/%s',
            Director::protocol(),
            $this->userId,
            $this->data['id']
        );
    }
}
