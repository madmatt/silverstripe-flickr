<?php

/**
 * Class FlickrData
 *
 * Represents a single object retrieved from the Flickr API. This shouldn't be used directly (hence being abstract), but
 * is extended by other objects - e.g. {@link FlickrPhoto}, {@link FlickrPhotoset}
 */
abstract class FlickrData extends ViewableData {
	protected $data;

	private static $casting = array(
		'ID' => 'Varchar' // ID values for Flickr data can either be varchars or integers
	);

	public function __construct($set) {
		$this->data = $set;
	}

	public function getID() {
		return $this->data['id'];
	}

	public function __get($property) {
		if($this->hasMethod($method = "get$property")) {
			return $this->$method();
		} else if(isset($this->data[strtolower($property)])) {
			return $this->data[strtolower($property)];
		} else {
			return parent::__get($property);
		}
	}
}