<?php
class FlickrPhotoset extends FlickrData {
	private static $casting = array(
		'Title' => 'Varchar',
		'ID' => 'Varchar'
	);

	/**
	 * @param array $set
	 * @return FlickrPhotoset|null
	 */
	public static function create_from_array($set) {
		// Validate input and return null if required params are not set
		if(!isset($set['id']) || !isset($set['title']) || !isset($set['title']['_content'])) {
			return null;
		}

		return new FlickrPhotoset($set);
	}



	/**
	 * Return photos within this photoset
	 * @return ArrayList<FlickrPhoto>
	 */
	public function getPhotos() {
		/** @var FlickrService $flickrService */
		$flickrService = Injector::inst()->get('FlickrService');

		return $flickrService->getPhotosInPhotoset($this->ID);
	}

	public function getTitle() {
		return $this->data['title']['_content'];
	}
}