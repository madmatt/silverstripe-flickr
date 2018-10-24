<?php

namespace MadMatt\Flickr\Model;

use SilverStripe\View\ViewableData;

/**
 * Class FlickrData
 *
 * Represents a single object retrieved from the Flickr API. This shouldn't be used directly (hence being abstract), but
 * is extended by other objects - e.g. {@link FlickrPhoto}, {@link FlickrPhotoset}
 */
abstract class FlickrData extends ViewableData
{
    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var array
     */
    private static $casting = [
        'ID' => 'Varchar' // ID values for Flickr data can either be varchars or integers
    ];

    /**
     * @param mixed $set
     */
    public function __construct($set)
    {
        $this->data = $set;
    }

    /**
     * @return int
     */
    public function getID()
    {
        return $this->data['id'];
    }

    /**
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        if ($this->hasMethod($method = "get$property")) {
            return $this->$method();
        } elseif (isset($this->data[strtolower($property)])) {
            return $this->data[strtolower($property)];
        } else {
            return parent::__get($property);
        }
    }
}
