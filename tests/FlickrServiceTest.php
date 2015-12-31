<?php
class FlickrServiceTest extends SapphireTest
{

    private $callCount = 0;

    public function setUpOnce()
    {
        parent::setUpOnce();

        Phockito::include_hamcrest();
    }

    /**
     * Test that the set/get API key methods work
     */
    public function testGetSetApiKey()
    {
        $service = Injector::inst()->create('FlickrService');
        $testApiKey = '1234';

        $service->setApiKey($testApiKey);
        $response = $service->getApiKey();

        $this->assertEquals($response, '1234');
    }

    /**
     * Basic test to check a successful mocked API response
     * returns an ArrayList with expected amount of items
     */
    public function testGetPhotosetsForUser()
    {
        $service = $this->getMockService();
        $userId = '132044853@N08';

        Phockito::when($service)->request()
            ->return($this->getMockResponse_getPhotosetsForUser());

        $response = $service->getPhotosetsForUser($userId);

        $this->assertEquals($response->count(), 11);
    }

    /**
     * Basic test to check a successful mocked API response
     * returns an ArrayList with expected amount of items
     */
    public function testGetPhotosInPhotoset()
    {
        $service = $this->getMockService();
        $photosetId = '72157658305686922';

        Phockito::when($service)->request()
            ->return($this->getMockResponse_getPhotosInPhotoset());

        $response = $service->getPhotosInPhotoset($photosetId);

        $this->assertEquals($response->count(), 9);
    }

    /**
     * Basic test to check that using the `getCachedCall` only makes an API request
     * if the soft cache expiry has elasped
     */
    public function testGetCachedCall()
    {
        $service = $this->getMockService();

        // temporarily reduce soft cache expiry for testing
        $service->config()->__set('flickr_soft_cache_expiry', 3);
        $userId = '132044853@N08';

        Phockito::when($service)->request()
            ->return($this->getMockResponse_getPhotosetsForUser_increaseCount());

        // this should make the first api request
        $response = $service->getCachedCall('getPhotosetsForUser', array($userId));
        $this->assertEquals($this->callCount, 1);

        // this should still use the cached version
        sleep(1);
        $response = $service->getCachedCall('getPhotosetsForUser', array($userId));
        $this->assertEquals($this->callCount, 1);
    }

    /**
     * Setup a mock service using Phockito::spy() to mock a basic version of `isAPIAvaiable`
     * @param boolean $available Can change this to account for when API is unavailable
     * @return __phockito_FlickrService_Spy
     */
    public function getMockService($available = true)
    {
        // setup mock
        $spy = Phockito::spy('FlickrService');

        Phockito::when($spy)->isAPIAvailable()
            ->return($available);

        return $spy;
    }

    /**
     * Mimic successful responses from the Flickr API
     * @return [type] [description]
     */
    private function getMockResponse_getPhotosetsForUser()
    {
        $body = 'a:2:{s:9:"photosets";a:5:{s:4:"page";i:1;s:5:"pages";i:1;s:7:"perpage";i:11;s:5:"total";i:11;s:8:"photoset";a:11:{i:0;a:16:{s:2:"id";s:17:"72157658305686922";s:7:"primary";s:11:"21231207825";s:6:"secret";s:10:"a0fb1361eb";s:6:"server";s:3:"756";s:4:"farm";d:1;s:6:"photos";s:1:"9";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:14:"September 2015";}s:11:"description";a:1:{s:8:"_content";s:0:"";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:1:"4";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1441674895";s:11:"date_update";s:10:"1441674982";}i:1;a:16:{s:2:"id";s:17:"72157658304270362";s:7:"primary";s:11:"21238593481";s:6:"secret";s:10:"ff04f3f874";s:6:"server";s:3:"709";s:4:"farm";d:1;s:6:"photos";s:1:"9";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:11:"August 2015";}s:11:"description";a:1:{s:8:"_content";s:0:"";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:1:"7";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1441672081";s:11:"date_update";s:10:"1441672148";}i:2;a:16:{s:2:"id";s:17:"72157655819225942";s:7:"primary";s:11:"19481589018";s:6:"secret";s:10:"44472ffb35";s:6:"server";s:3:"322";s:4:"farm";d:1;s:6:"photos";s:2:"13";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:9:"July 2015";}s:11:"description";a:1:{s:8:"_content";s:70:"Great North Road Interchange, State Highway 20 extension, Wiri Quarry.";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:1:"4";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1436822102";s:11:"date_update";s:10:"1436822848";}i:3;a:16:{s:2:"id";s:17:"72157655845986525";s:7:"primary";s:11:"19049020003";s:6:"secret";s:10:"4cbefbb8c6";s:6:"server";s:4:"3669";s:4:"farm";d:4;s:6:"photos";s:1:"4";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:9:"June 2015";}s:11:"description";a:1:{s:8:"_content";s:81:"Photos inside the second tunnel, cross passage and Southern Ventilation Building.";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:2:"22";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1436822579";s:11:"date_update";s:10:"1436843082";}i:4;a:16:{s:2:"id";s:17:"72157653859279968";s:7:"primary";s:11:"18432801058";s:6:"secret";s:10:"13584ca425";s:6:"server";s:3:"438";s:4:"farm";d:1;s:6:"photos";s:2:"10";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:8:"May 2015";}s:11:"description";a:1:{s:8:"_content";s:46:"Northern and Southern works plus Precast Yard.";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:3:"315";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1433809884";s:11:"date_update";s:10:"1436822584";}i:5;a:16:{s:2:"id";s:17:"72157652761474291";s:7:"primary";s:11:"17426687578";s:6:"secret";s:10:"dcb3e760be";s:6:"server";s:4:"5459";s:4:"farm";d:6;s:6:"photos";s:1:"8";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:10:"April 2015";}s:11:"description";a:1:{s:8:"_content";s:28:"Waterview Connection project";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:3:"491";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1431554789";s:11:"date_update";s:10:"1436822584";}i:6;a:16:{s:2:"id";s:17:"72157649940812643";s:7:"primary";s:11:"17128284710";s:6:"secret";s:10:"9402bed1e3";s:6:"server";s:4:"7785";s:4:"farm";d:8;s:6:"photos";s:1:"7";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:10:"March 2015";}s:11:"description";a:1:{s:8:"_content";s:0:"";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:2:"19";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1430345172";s:11:"date_update";s:10:"1436822584";}i:7;a:16:{s:2:"id";s:17:"72157652199046496";s:7:"primary";s:11:"16695533553";s:6:"secret";s:10:"6250241f15";s:6:"server";s:4:"7718";s:4:"farm";d:8;s:6:"photos";s:1:"7";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:13:"February 2015";}s:11:"description";a:1:{s:8:"_content";s:0:"";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:2:"17";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1430344844";s:11:"date_update";s:10:"1436822584";}i:8;a:16:{s:2:"id";s:17:"72157652258636791";s:7:"primary";s:11:"17315226711";s:6:"secret";s:10:"bcbe093279";s:6:"server";s:4:"7663";s:4:"farm";d:8;s:6:"photos";s:2:"12";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:12:"January 2015";}s:11:"description";a:1:{s:8:"_content";s:0:"";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:2:"20";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1430344401";s:11:"date_update";s:10:"1436822584";}i:9;a:16:{s:2:"id";s:17:"72157652258987911";s:7:"primary";s:11:"17108487017";s:6:"secret";s:10:"43849e04ca";s:6:"server";s:4:"8823";s:4:"farm";d:9;s:6:"photos";s:1:"7";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:13:"December 2014";}s:11:"description";a:1:{s:8:"_content";s:0:"";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:2:"10";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1430345971";s:11:"date_update";s:10:"1436822584";}i:10;a:16:{s:2:"id";s:17:"72157651847168619";s:7:"primary";s:11:"17315441261";s:6:"secret";s:10:"e6d5002801";s:6:"server";s:4:"8787";s:4:"farm";d:9;s:6:"photos";s:1:"9";s:6:"videos";i:0;s:5:"title";a:1:{s:8:"_content";s:14:"November 2014 ";}s:11:"description";a:1:{s:8:"_content";s:0:"";}s:18:"needs_interstitial";i:0;s:22:"visibility_can_see_set";i:1;s:11:"count_views";s:2:"24";s:14:"count_comments";s:1:"0";s:11:"can_comment";i:0;s:11:"date_create";s:10:"1430345552";s:11:"date_update";s:10:"1436822584";}}}s:4:"stat";s:2:"ok";}';
        $response = new RestfulService_Response($body);
        return $response;
    }

    private function getMockResponse_getPhotosInPhotoset()
    {
        $body = 'a:2:{s:8:"photoset";a:11:{s:2:"id";s:17:"72157658305686922";s:7:"primary";s:11:"21231207825";s:5:"owner";s:13:"132044853@N08";s:9:"ownername";s:20:"Waterview Connection";s:5:"photo";a:9:{i:0;a:10:{s:2:"id";s:11:"21231207825";s:6:"secret";s:10:"a0fb1361eb";s:6:"server";s:3:"756";s:4:"farm";d:1;s:5:"title";s:68:"Carrington Road retaining walls, installation of road-side barriers.";s:9:"isprimary";s:1:"1";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:0:"";}}i:1;a:10:{s:2:"id";s:11:"21220775302";s:6:"secret";s:10:"e582d4f000";s:6:"server";s:3:"756";s:4:"farm";d:1;s:5:"title";s:42:"Southern Ventilation Building ground level";s:9:"isprimary";s:1:"0";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:0:"";}}i:2;a:10:{s:2:"id";s:11:"21043089010";s:6:"secret";s:10:"914f537b96";s:6:"server";s:3:"635";s:4:"farm";d:1;s:5:"title";s:29:"Southern Ventilation Building";s:9:"isprimary";s:1:"0";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:0:"";}}i:3;a:10:{s:2:"id";s:11:"21204947056";s:6:"secret";s:10:"932deaa01d";s:6:"server";s:3:"582";s:4:"farm";d:1;s:5:"title";s:28:"Great North Road Interchange";s:9:"isprimary";s:1:"0";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:0:"";}}i:4;a:10:{s:2:"id";s:11:"21239142771";s:6:"secret";s:10:"a385f64946";s:6:"server";s:3:"763";s:4:"farm";d:1;s:5:"title";s:30:"Valonia Fields, Spoil Building";s:9:"isprimary";s:1:"0";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:0:"";}}i:5;a:10:{s:2:"id";s:11:"20608509954";s:6:"secret";s:10:"781df33636";s:6:"server";s:4:"5663";s:4:"farm";d:6;s:5:"title";s:69:"Maioro St Interchange, segment stacks, southern shared cycle/footpath";s:9:"isprimary";s:1:"0";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:0:"";}}i:6;a:10:{s:2:"id";s:11:"20608510074";s:6:"secret";s:10:"1c5c86d75d";s:6:"server";s:4:"5679";s:4:"farm";d:6;s:5:"title";s:26:"Dennis gantry on ramp four";s:9:"isprimary";s:1:"0";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:0:"";}}i:7;a:10:{s:2:"id";s:11:"20608511254";s:6:"secret";s:10:"0956468596";s:6:"server";s:4:"5748";s:4:"farm";d:6;s:5:"title";s:77:"Spoil Conveyor, Hendon Footbridge, culvert storage, tunnel team headquarters.";s:9:"isprimary";s:1:"0";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:0:"";}}i:8;a:10:{s:2:"id";s:11:"20608510974";s:6:"secret";s:10:"a0b7162da0";s:6:"server";s:4:"5710";s:4:"farm";d:6;s:5:"title";s:24:"Northern Approach Trench";s:9:"isprimary";s:1:"0";s:8:"ispublic";i:1;s:8:"isfriend";i:0;s:8:"isfamily";i:0;s:11:"description";a:1:{s:8:"_content";s:50:"Rise of the permanent roads and central corridors.";}}}s:4:"page";i:1;s:8:"per_page";i:500;s:7:"perpage";i:500;s:5:"pages";d:1;s:5:"total";s:1:"9";s:5:"title";s:14:"September 2015";}s:4:"stat";s:2:"ok";}';
        $response = new RestfulService_Response($body);
        return $response;
    }

    private function getMockResponse_getPhotosetsForUser_increaseCount()
    {
        $this->callCount++;
        return $this->getMockResponse_getPhotosetsForUser();
    }
}
