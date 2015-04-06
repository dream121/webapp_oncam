<?php
include_once("ThumbnailImage.php");
require_once 'Zend/Cache.php';
require_once 'Zend/Cache/Backend/ExtendedInterface.php';
require_once 'Zend/Cache/Backend.php';
require_once 'Zend/Cache/Backend/ZendPlatform.php';
class CS_Mobileservice_Main
{

	static $defaultProductImage = "/media/customizer/no-Event.png";
	static $prefixForLocalTable = "cs";
	static $prefixForMagTable = "csm";
	static $EventImageFolder = "csusers";
	static $EventVideoFolder = "csusers";
	static $EventRecordingFolder = "csusers";
	static $productImageUrl = "";
	static $defaultEventImage = "/upload/blank.jpg";
	static $proflieUrlPrefix = "/csprofile/index/view/id/";
	
	static $notYourProduct = "Invalid Product or Product Owner";
	static $loginError = "Invalid Login or Session Timed Out";
	static $invalidLogin = "Invalid username or password";
	static $invalidShareWithList = "Invalid share with list";
	static $maxRecordCount = 20;
    static $caching = true;
    static $automatic_serialization = true;
    static $cacheDirectory = './var/csmobileservice/';
    static $cacheLifetime = 7200; // 2 hours (in seconds) - this is default
    static $cacheShortTimeSpan = 1200; // 15 minutes (in seconds)
    static $cacheMediumTimeSpan = 2400; //45 minutes (in seconds)
    static $cacheOneDaySpan = 86400; //1 day (in seconds)
    static $cacheFiveDaysSpan = 432000; //5 days (in seconds)
    static $cacheLongTimeSpan = 28800; // 8 hours (in seconds)

	private $isMailTransportActive = false;
	private $transport = "";
    public function Main()
    {
		
    }
    public function loadCache($lifetime = null) {
        if ($lifetime == null) {
            $lifetime = self::$cacheLifetime;
        }
        //
        $frontendOptions = array('caching' => self::$caching,
                'automatic_serialization' => self::$automatic_serialization,
                'lifetime' => $lifetime,
                'cache_id_prefix' => 'csmobileservice',
                'cached_entity' => $this
                );
        $backendOptions = array('cache_dir' => self::$cacheDirectory);
        //
        $backendName = 'File';
        $frontendName = 'Class';
        //
        $rp = realpath(self::$cacheDirectory);
        if (file_exists($rp) == false) {
            mkdir(self::$cacheDirectory, 0777, true);
        }
        //
        $cache = Zend_Cache::factory($frontendName, $backendName, $frontendOptions, $backendOptions);
        //
        return $cache;
    }
    public function cleanCache($secureCall) {
        if ($secureCall != 'cs') {
            throw new Exception('Invalid use of Clean Cache method. Try removeCache(id).');
        }
        $this->loadCache()->clean(Zend_Cache::CLEANING_MODE_ALL);
    }
    public function removeCache($id) {
        try {
            $this->loadCache()->remove($id);
        } catch (Exception $e) {
            throw new Exception('Invalid cache id. Use getCacheIds for a list of ids first. Error: ' . $e->getMessage());
        }
    }
    public function getCacheId($methodName, $parameters = "") {
        $ro = new ReflectionObject($this);
        $cacheId = md5('__' . $ro->getName() . '__' . $methodName . '__' . serialize(explode(",", $parameters)));
        if ($this->loadCache()->test($cacheId)) {
            return $cacheId;
        }
        return 'no cache available for \'' . $methodName . '\' with provided parameters.';
    }
    public function cleanCacheByTags($tags) {
        $this->loadCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, explode(",", $tags));
    }
	
	public function __hello($str = "Hello!") {
        return $str;
    }
    public function hello($str = "Hello!") {
        return $this->loadCache()->__hello($str);
    }
    public function hello2() {
        return "hello";
    }
	
	public function getUserInfo($user_id=0) {
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$returnVal = array();$zero = 0;
		if($user_id!=0){
			$returnVal['isLoggedIn'] = $this->isLoggedIn($user_id);
			$returnVal['userGroupId'] = $this->getUserGroupId();
			$returnVal['userId'] = $customer->getId();
			$returnVal['userName'] = $customer->getUsername();
			$returnVal['userEmail'] = $customer->getEmail();
			$returnVal['shortbio'] = $customer->getShortbio();
			$returnVal['location'] = $customer->getLocation();
			$returnVal['profile_url'] =  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).$customer->getUsername();
			$returnVal['realname'] = $customer->getFirstname()." ".$customer->getLastname();		 
			$returnVal['profilePicture'] = $this->getProfilePic($user_id);
			$returnVal['profilePicture_30x30'] = $this->getProfilePic($customer->getId());
			//$returnVal['followers'] = $this->getFollowers($user_id);
			$returnVal['followersCount'] = count($this->getFollowers($user_id));
			$returnVal['checkinsCount'] = $this->getRecentActivityCount($user_id, 'check-ins');
			$returnVal['recordingsCount'] =$this->getRecordingCount($user_id);
			
			if($customer->getTwitterId())
					$returnVal['twitterId'] = intval($customer->getTwitterId());
				else
					$returnVal['twitterId'] = intval($zero);
			$returnVal['twitterUsername'] = $customer->getTwitterUsername();
			$returnVal['twitterOauthToken'] = '';
			$returnVal['twitterOauthSecret'] = '';
			
			if($customer->getStwitter())
				$returnVal['twitterPost'] = 1;
			else
				$returnVal['twitterPost'] = 0;
			
			if($customer->getSfacebook())
				$returnVal['facebookPost'] = 1;
			else
				$returnVal['facebookPost'] = 0;
				
			if($customer->getFacebookUid())
					$returnVal['facebookId'] = intval($customer->getFacebookUid());
				else
					$returnVal['facebookId'] = intval($zero);
			$returnVal['facebookUsername'] = $customer->getFacebookUsername();
			$returnVal['facebookCode'] = '';
			
			if($customer->getYoutubename()){
				$returnVal['youtubename'] = $customer->getYoutubename();
				//$returnVal['youtubepassword'] = $customer->getYoutubepassword();
			}else{
				$returnVal['youtubename']='';
				//$returnVal['youtubepassword'] ='';
			}
		}else{
			$returnVal['isLoggedIn'] = $this->isLoggedIn($user_id);
			if ($returnVal['isLoggedIn']) {
				$returnVal['userGroupId'] = $this->getUserGroupId();
				$returnVal['userId'] = $customer->getId();
				$returnVal['userName'] = $customer->getUsername();
				$returnVal['userEmail'] = $customer->getEmail();
				$returnVal['shortbio'] = $customer->getShortbio();
				$returnVal['location'] = $customer->getLocation();
				$returnVal['profile_url'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).$customer->getUsername();
				$returnVal['realname'] = $customer->getFirstname()." ".$customer->getLastname();
				//$returnVal['profilePicture'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'chattrspace/'.$customer->getProfilePicture();
				$returnVal['profilePicture'] = $this->getProfilePic($customer->getId());
				$returnVal['profilePicture_30x30'] = $this->getProfilePic($customer->getId());
				//$returnVal['followers'] = $this->getFollowers($user_id);
				$returnVal['followersCount'] = count($this->getFollowers($user_id));
				$returnVal['checkinsCount'] = $this->getRecentActivityCount($user_id, 'check-ins');
			$returnVal['recordingsCount'] =$this->getRecordingCount($user_id);
				/* if($this->isLoggedIn($user_id)!=0){					
					$u_id = Mage::getSingleton( 'customer/session')->getCustomerId();
					$returnVal['is_follow_by_user'] = (bool)Mage::getModel('csmobileservice/csmobileservice')->isFollow($user_id);
					$returnVal['is_follow_to_user'] = (bool)Mage::getModel('csmobileservice/csmobileservice')->isFollow($u_id);
				} */
				
				if($customer->getTwitterId())
					$returnVal['twitterId'] = intval($customer->getTwitterId());
				else
					$returnVal['twitterId'] = intval($zero);
					
				$returnVal['twitterUsername'] = $customer->getTwitterUsername();
				if($customer->getTwitterOauthToken())
					$returnVal['twitterOauthToken'] = $customer->getTwitterOauthToken();
				else
					$returnVal['twitterOauthToken'] = '';
				if($customer->getTwitterOauthSecret())
					$returnVal['twitterOauthSecret'] = $customer->getTwitterOauthSecret();
				else
					$returnVal['twitterOauthSecret'] = '';
					
				if($customer->getFacebookUid())
					$returnVal['facebookId'] = intval($customer->getFacebookUid());
				else
					$returnVal['facebookId'] = intval($zero);
				$returnVal['facebookUsername'] = $customer->getFacebookUsername();
				if($customer->getFacebookCode())
					$returnVal['facebookCode'] = $customer->getFacebookCode();
				else
					$returnVal['facebookCode'] = '';
				if($customer->getYoutubename()){
					$returnVal['youtubename'] = $customer->getYoutubename();
					//$returnVal['youtubepassword'] = $customer->getYoutubepassword();
				}else{
					$returnVal['youtubename']='';
					$returnVal['youtubepassword'] ='';
				}
				
				if($customer->getStwitter())
				$returnVal['twitterPost'] = 1;
				else
					$returnVal['twitterPost'] = 0;
				
				if($customer->getSfacebook())
					$returnVal['facebookPost'] = 1;
				else
					$returnVal['facebookPost'] = 0;
					
			} else {
				
				$returnVal['userGroupId'] = "0";
				$returnVal['userId'] = "-1";
				$returnVal['userName'] = "Guest User";
				$returnVal['userEmail'] = "";
				$returnVal['realname'] = $customer->getFirstname()." ".$customer->getLastname();
				$returnVal['shortbio'] = "";
				$returnVal['location'] = "";
				$returnVal['profile_url'] = "/";
				$returnVal['profilePicture'] =  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)."/media/chattrspace/default/" . (rand()%10) . ".jpg";
				$returnVal['profilePicture_30x30'] =  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)."/media/chattrspace/default/" . (rand()%10) . ".jpg";
				//$returnVal['followers'] = $this->getFollowers($user_id);
				$returnVal['followersCount'] = 0;
				$returnVal['checkinsCount'] = 0;
				$returnVal['recordingsCount'] =0;
				$returnVal['twitterId'] = intval($zero);
				$returnVal['twitterUsername'] = '';
				$returnVal['twitterOauthToken'] = '';
				$returnVal['twitterOauthSecret'] = '';
				$returnVal['facebookId'] = intval($zero);
				$returnVal['facebookUsername'] = '';
				$returnVal['facebookCode'] = '';
				$returnVal['youtubename']='';
				//$returnVal['youtubepassword'] ='';
				$returnVal['twitterPost'] = 0;
				$returnVal['facebookPost'] = 0;
			}
			
		}
		return $returnVal;
	}
	
	public function getFollowers($user_id, $status=1, $notify=0) {
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$customer_entity = $resource->getTableName('customer_entity');
			
			$select = "select id, follower_id, follow, status, follow_on, notify from $follower, $customer_entity WHERE follow=".$user_id." and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follower_id";
			if($notify==1)
				$select.= " and notify=".$notify."";
				$select.=" order by id desc";
			if($limit!=0)
				$select.= " limit ".$limit;
			$follower = $read->fetchAll($select);	
			//echo count($follower);die;
			if(count($follower)>0)
				return $follower;
			else
				return 0;
	}
	
	public function getFollowersById($user_id, $status=1, $notify=0) {
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			
			$select = "select id, follower_id, follow, status, follow_on, notify from $follower WHERE follow=".$user_id." and follower_id<>follow and status=".$status."";
			if($notify==1)
				$select.= " and notify=".$notify."";
				
			return $followers = $read->fetchAll($select);	
			
	}
	
	public function isLoggedIn($user_id=0) {
        $returnVal = Mage::getSingleton( 'customer/session' )->isLoggedIn();
        //return $returnVal;
		if($user_id==$this->getUserId())
			return $this->getUserId();
		else
			return 0;        
    }

    public function getUserId(){
		if (Mage::getSingleton('customer/session')->isLoggedIn() == false) {
			return -1;
		}
        $returnVal = Mage::getSingleton( 'customer/session' )->getCustomerId();
        return $returnVal;
    }
	
	public function getProfilePic($user_id){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$filename = 'profile/48x48/'.$customer->getProfilePicture();
			$ext = end(explode('.', basename($filename)));
			
			if($ext=='jpg' || $ext=='JPG' || $ext=='png' || $ext=='gif' || $ext=='jpeg' || $ext== 'JPEG'){
				$filename = $filename;					
			}else{
				$cust_id = (($customer->getId())%10).".jpg";
				$filename = 'chattrspace/default/'.$cust_id;							
			}
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).$filename;
    }
	
	public function getLoggedInUserId(){
		$this->getUserId();
	}

	public function getUserGroupId(){
        $returnVal = Mage::getSingleton( 'customer/session' )->getCustomerGroupId();

        return $returnVal;
    }
	
	public function getUserEmail(){
		//getAttributes
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn() == false) {
			return 0;
		}
		return htmlspecialchars(Mage::getSingleton( 'customer/session' )->getCustomer()->getEmail());
	}
    
	public function getProfilePicture(){
		//getAttributes
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn() == false) {
			return 0;
		}
		$profilePicture = Mage::getSingleton( 'customer/session' )->getCustomer()->getProfilePicture();
		$profilePicture = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'chattrspace/'.$profilePicture;
		return htmlspecialchars($profilePicture);
	}
	
	public function getUserName(){
        if (Mage::getSingleton( 'customer/session' )->isLoggedIn()) {
            $returnVal = Mage::helper( 'customer/data' )->getCustomerName();
        } else {
            $returnVal = "Guest";
        }
        return $returnVal;
    }
        
	public function login($email, $password){
        $returnValLogin = Mage::getSingleton( 'customer/session' )->login($email, $password);
		$returnVal = array();
		$returnVal['result'] = $returnValLogin;
		if ($returnValLogin == true) {
			$customer = Mage::getModel('customer/customer')
					->setWebsiteId(1)
					->loadByEmail($email);
			//echo $customer->getId();
			$returnVal['getUserInfo'] = $this->getUserInfo($customer->getId());
		} else {
			throw new Exception(self::$invalidLogin);
		}
        return $returnVal;
    }
    
	public function loginByUsername($username, $password){
        $customer = Mage::getModel('customer/customer');
		$collection = $customer->getCollection()
						->addAttributeToFilter('username', (string)$username)
						->setPageSize(1);
		$uidExist = (bool)$collection->count();
		$returnVal = array();
		if ($uidExist) {
			$existingCustomer = $collection->getFirstItem();
			$email = $existingCustomer->getEmail();
			
			$returnValLogin = Mage::getSingleton( 'customer/session' )->login($email, $password);
			
			$returnVal['result'] = $returnValLogin;
			if ($returnValLogin == true) {
				//Mage::getSingleton( 'customer/session' )->setCustomerAsLoggedIn($existingCustomer);
				$returnVal['getUserInfo'] = $this->getUserInfo($existingCustomer->getId());
			} else {
				throw new Exception(self::$invalidLogin);
			}
		} else {
			throw new Exception(self::$invalidLogin);
		}
        return $returnVal;
    }
	
	public function logout(){
		$returnValLogin = Mage::getSingleton( 'customer/session' )->logout();
		$returnVal['result'] = true;
		$returnVal['getUserInfo'] = $this->getUserInfo();
        return $returnVal;
	}
	
	
	public function followUser($follower, $followed){
			$resource = Mage::getSingleton('core/resource');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('follower');
			
			if($this->isfollowing($follower, $followed, 0))
				$write->query("update $table set status=1, notify=1, follow_on=now()  WHERE follower_id=".$follower." and follow=".$followed);
			else
				$write->query("insert into $table (follower_id, follow, status, follow_on, notify) values(".$follower.", ".$followed." ,1, now(), 1)");
				
			$customer = Mage::getModel('customer/customer')->load($followed);	
			$notice = $customer->getNotice();
			$a = explode(",",$notice);	
			if(in_array(18,$a)){
				//Mage::getModel('csservice/csservice')->sendMail($followed, $follower);	
			}
	}
	
	public function unfollowUser($follower, $followed){
			$resource = Mage::getSingleton('core/resource');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('follower');
			
			$write->query("update $table set  status=0, notify=0 WHERE follower_id=".$follower." and follow=".$followed."");
	}
	
	public function isFollowing($follower, $followed, $status=1){
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			$table = $resource->getTableName('follower');
			
			$select = "select id, follower_id, follow, status, follow_on, notify from $table WHERE follow=".$followed." and follower_id=".$follower." and status=".$status."";
			$rs = $read->fetchRow($select);	
			
			if(count($rs['id'])>0)
				return true;
			else
				return false;
	}
	
	public function hasEventAccess($uid, $event_id=0){
			//$event = $this->getNextLiveEvent($event_id);
			$event = $this->getNextLiveEvent();
			$event_id = $event['entity_id'];
			
			$event_attending = $this->getAttendingEvents($uid, $event_id);
			
			if ($event['user_id'] == $uid || $event_attending == true) {
				$event['is_access'] =  true;
			}
			else
				$event['is_access'] =  false;
			
			return $event;
	}
	
	public function isLiveEvent($uid, $event_id){
			$event = $this->getEvent($event_id);
			//$today = strtotime(date("Y-m-d h:m"));			
			$event_date = $event->getNewsToDate();
			
			$diff = strtotime($event_date)-time();

			if ($diff > 0) {
				return true;
			}
			else
				return false;
	}
	
	public function getNextLiveEvent(){
			//$event = $this->getEvent($event_id);
			//$today = strtotime(date("Y-m-d h:m"));			
			//$event_date = $event->getNewsToDate();
			
			//$diff = strtotime($event_date)-time();

			$productCount = 1;	 
			$storeId    = Mage::app()->getStore()->getId();      
		 
			/**
			 * Get most viewed product collection
			 */
			/* $products = Mage::getResourceModel('catalog/product_collection')
				->addAttributeToSelect('*')
				->setStoreId($storeId)
				->addStoreFilter($storeId)
				->addFieldToFilter('user_id', array('eq'=> $uid))
				->addFieldToFilter('news_to_date', array('gteq'=> 'now()'))
				->setPageSize($productCount)->setOrder('ordered_qty', 'desc');
					Mage::getSingleton('catalog/product_status')
					->addVisibleFilterToCollection($products);
			Mage::getSingleton('catalog/product_visibility')
					->addVisibleInCatalogFilterToCollection($products); 
		// print_r($products);
		
		->addFieldToFilter('user_id', array('eq'=> $uid))
		  */
			$now = Mage::getModel('core/date')->timestamp(time());
			$dateTime = date('m/d/y H:i:s', $now);

			$events = Mage::getResourceModel('catalog/product_collection')
						->addAttributeToSelect('*')
					   ->addFieldToFilter('news_to_date', array('gteq'=> $dateTime))
					   ->setPageSize($productCount)->setOrder('news_to_date', 'asc')->setOrder('entity_id', 'desc')
					   ->load()->toArray(); 			
			foreach($events as $evt){
				$events = $evt;				
			}
			return $events;
	}
	
	
	public function getAttendingEvents($uid, $event_id=0, $status=1){
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			$table = $resource->getTableName('event_attending');
			
			if($event_id > 0){
				$select = "select $table.* from $table WHERE user_id=".$uid." and event_id=".$event_id."";
				//$rs = $read->fetchRow($select);
				$rs = mysql_query($select);
				$numResults = mysql_num_rows($rs);
				if($numResults>0)
					return true;
				else
					return false;
			}else{
				$select = "select $table.* from $table WHERE user_id=".$uid;
				$rs = $read->fetchAll($select);
				if(count($rs)>0)
					return $rs;
				else
					return '';				
			}			
	}
	
	//get video
	public function getVideoInfo($video_id) {
		$video = Mage::getModel('catalog/product')->load($video_id);
		$att_set_name = $this->getAttributeSetName($video->getAttributeSetId());
		
		$returnVal = array();
		if($video_id!=0 && $att_set_name=='Videos'){
			$returnVal['videoId'] = $video->getId();
			$returnVal['userId'] = $video->getUserId();
			$returnVal['profileId'] = $video->getProfileId();
			$returnVal['videoFile1'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."csvideos/".$video->getVideoFilePath1();
			$returnVal['videoFile2'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."csvideos/".$video->getVideoFilePath2();
			$returnVal['videoName'] = $video->getName();
			$returnVal['videoDuration'] = $video->getvideoDuration();
			$returnVal['status'] = $video->getStatus();
		}else{
			$returnVal['videoId'] = 0;
			$returnVal['userId'] = 0;
			$returnVal['profileId'] = 0;
			$returnVal['videoFile1'] = '';
			$returnVal['videoFile2'] = '';
			$returnVal['videoName'] = '';
			$returnVal['videoDuration'] = 0;
			$returnVal['status'] = 0;
		}
		return $returnVal;
	}
	
	public function getAttributeSetName($id) {
		$attributeSetModel = Mage::getModel("eav/entity_attribute_set");
		$attributeSetModel->load($id);
		return $attributeSetName  = $attributeSetModel->getAttributeSetName();
	}
	
	public function getEventInfo($event_id) {
		return $this->getEvent($event_id);
	}
	
	public function getEvent($event_id) {
			$event_id = intval($event_id);
			$theProduct = Mage::getModel('catalog/product')->load($event_id);
			$theProduct = $theProduct->toArray();
			return $theProduct;
	}
	
	public function addViewCountById($id) {
			$event_id = intval($id);
			$theProduct = Mage::getModel('catalog/product')->load($id);
			$viewCount = $theProduct->getViewCount()+1;
			
			$theProduct->setViewCount($viewCount);
			$theProduct->save();
			return $viewCount;
	}
			
	public function getEvents() {
        //return $this->loadCache()->__getEvents();
		return $this->__getEvents();
    }
	
	public function testFileUpload($data='') {
		//check for the posted data and decode it
		if (isset($_POST["data"]) && ($_POST["data"] !="")){
			$data = $_POST["data"];
			$data = base64_decode($data);
			$im = imagecreatefromstring($data);
		}
		//make a file name
		$filename = "test";

		//save the image to the disk
		if (isset($im) && $im != false) {
			$imgFile = $path = realpath('.')."/media/csimages/".$filename.".jpg";

			//delete the file if it already exists
			if(file_exists($imgFile)){
				unlink($imgFile);      
			}

			$result = imagepng($im, $imgFile);
			imagedestroy($im);
			return "/".$filename.".jpg";
		}
		else {
			return 'Error';
		}
	}
	
	public function userCheckInHttp($user_id=0, $profile_id=0, $type='check-ins', $group='', $data=null, $event_id=0, $webcam_on=0, $mesg=null, $fb='', $twit='') {
		
		//return $contentType." --- ".$roomId;
		$user_id = intval($user_id);
		$profile_id = intval($profile_id);
		
		/* if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} */
		//$UID = $this->getUserId();
		$UID = $user_id;
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_activities');
		//
		if($user_id!=0){
			$sqlInsert = " insert into $table(user_id, event_id, profile_id, type_of, group_of, created_on, status, webcam_on, mesg) values(" . $UID . ", ". $event_id ."," . $profile_id . ", '" . $type . "', '" . $group . "'
				, '" . date("Y-m-d G:i:s") . "', 1, ". $webcam_on .", '".$mesg."');";
			try {
				mysql_query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = mysql_insert_id();
		
			if (isset($_POST["data"]) && ($_POST["data"] !="")){
				$data = $_POST["data"];
				$data = base64_decode($data);
				$im = imagecreatefromstring($data);
			
				//make a file name
				$fileName = strtoupper( $UID . $thelastId . "-" . $this->getRandomString(8) );
				
				//save the image to the disk
				if (isset($im) && $im != false) {
					//$imgFile = $path = realpath('.')."/media/csimages/".$filename.".jpg";
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.jpg';	
					$path = realpath('.')."/media/csimages/";
					$fullFilePath = $path . $image_path;
					//delete the file if it already exists
					if(file_exists($fullFilePath)){
						unlink($fullFilePath);      
					}

					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//return "/".$filename.".jpg";
					
					$sqlUpdate = " UPDATE $table SET photo = '".mysql_real_escape_string($image_path)."', group_of='".$thelastId."'  WHERE id = " . $thelastId . ";";
		
					mysql_query($sqlUpdate);
				}
				else {
					//return 'Error';
				}
			}
		
		//end when pre-customized product then add a new product
		
		$sqlSelect = " Select profile_id, user_id, type_of, group_of, photo , created_on, status, event_id, webcam_on, mesg from $table where id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['profile_id'] = $rowTag[0];
		$item['user_id'] = $rowTag[1];
		$item['type'] = $rowTag[2];
		$item['group'] = $rowTag[3];
		$item['photo'] = $rowTag[4];
		$item['created_on'] = $rowTag[5];
		$item['status'] = $rowTag[6];
		$item['event_id'] = $rowTag[7];
		$item['webcam_on'] = $rowTag[8];
		$item['mesg'] = $rowTag[9];
		$returnVal['user'] = $item;		
		if($type=='comment' && $mesg!=null){
			$fb_id = $this->_getSession()->getCustomer()->getFacebookUid();
			$fb_code = $this->_getSession()->getCustomer()->getFacebookCode();
			$twitter_id = $this->_getSession()->getCustomer()->getTwitterId();
			$customer = Mage::getModel('customer/customer')->load($item['profile_id']);
			//$profilePicture = $customer->getProfilePicture();
			$username = $customer->getUsername();
				
			if($fb_id!="" && $fb=='true'){

			$my_url=Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$username;
				$args = array(
					'name' => 'I&#39;m talking with friends RIGHT NOW LIVE face to face at '.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$username.' -- Join us!',
					'message'   => $mesg,
					'link'      => $my_url,
					'picture' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN).'frontend/default/chattrspace/images/chattrspace-logo.png',
					'caption' => 'Be in the conversation. Chattrspace'
					
				);
				
				$facebook = Mage::getModel('facebook/facebook',array(
														  'appId'  => '141983765833638',
														  'secret' => '7037c4195e1e9cf5916d99d4291262c5',
														  'cookie' => true,
					)); 
				
				if($fb_id!='' && $this->_getSession()->getCustomer()->getSfacebook()){
					$token_url = "https://graph.facebook.com/oauth/access_token?"
					."client_id=141983765833638&client_secret=7037c4195e1e9cf5916d99d4291262c5&redirect_uri=".urlencode('http://dev0821.chattrspace.com/myfacebook.php')."&code=" . $fb_code;

					$response = file_get_contents($token_url);
					//print_r( $response);
					$params = null;
					parse_str($response, $params);
					
					//print_r( $params);
					if($params['access_token']){
						$post_id = $facebook->api("/".$fb_id."/feed", "post", $args);
					}
					
				}
						
			}
			
			if($twitter_id!="" && $twit=='true'){
				//sdsadgvgv
				//echo $oauth_token; die;
				try{
				
				$connection = Mage::getModel('csmobileservice/twitteroauth','', '', '', '');
				$connection->post('statuses/update', array('status' => $mesg." #CS" ));
				}  catch (Exception $e) {
					echo $e->getMessage();
				} 	
			}
		}//end post
		
		return $returnVal;
		}
		else
			return 'nodata';
	}
	
	public function testuserCheckIn($photo=null) {
		
		if ( isset ( $_FILES['image'] ))
		{
          // return $_FILES['image']['name'];
		   // print_r($_FILES['image']);die;
			$fileName = strtoupper( $UID . $thelastId . "-" . $this->getRandomString(8) );
			$fullFilePath = self::$EventImageFolder . $fileName;
			$image_path = $fullFilePath . '_img.jpg';	
			$path = realpath('.')."/media/csimages/";
			$fullFilePath = $path . $image_path;
			move_uploaded_file($_FILES['image']['tmp_name'] , $fullFilePath);
//			
            return $fullFilePath;
		}
		
	}
	
	public function userCheckIn($user_id=0, $profile_id=0, $type='', $group='', $photo=null) {
		
		//return $contentType." --- ".$roomId;
		$user_id = intval($user_id);
		$profile_id = intval($profile_id);
		
		/* if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} */
		//$UID = $this->getUserId();
		$UID = $user_id;
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_activities');
		//
			$sqlInsert = " insert into $table(user_id, profile_id, type_of, group_of, created_on, status) values(" .
				$UID . ", " . $profile_id . ", '" . $type . "', '" . $group . "'
				, now(), 1);";
			try {
				mysql_query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = mysql_insert_id();
		
		
		if ( isset ( $_FILES['image'] ))
		{
          // return $_FILES['image']['name'];
		   // print_r($_FILES['image']);die;
			$fileName = strtoupper( $UID . $thelastId . "-" . $this->getRandomString(8) );
			$fullFilePath = self::$EventImageFolder . $fileName;
			$image_path = $fullFilePath . '_img.jpg';	
			$path = realpath('.')."/media/csimages/";
			$fullFilePath = $path . $image_path;
			move_uploaded_file($_FILES['image']['tmp_name'] , $fullFilePath);
//			
            // create thumbnail:
           /*  $thumbnail = new ThumbnailImage($_FILES['image']['tmp_name'], 260, 260, true, 75);
            // move orignal uploaded file:
            move_uploaded_file($_FILES['image']['tmp_name'] , $fullFilePath);
			// save thumbnail
			$fullFilePath = $path . $thumbnail_path;
			$thumbnail->saveTo($fullFilePath, 60); */
			
			$sqlUpdate = " UPDATE $table SET photo = '".mysql_real_escape_string($image_path)."' WHERE id = " . $thelastId . ";";
		
			mysql_query($sqlUpdate);
		}
		
				
		
		//end when pre-customized product then add a new product
		
		$sqlSelect = " Select profile_id, user_id, type_of, group_of, photo , created_on, status from $table where id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['profile_id'] = $rowTag[0];
		$item['user_id'] = $rowTag[1];
		$item['type'] = $rowTag[2];
		$item['group'] = $rowTag[3];
		$item['photo'] = $rowTag[4];
		$item['created_on'] = $rowTag[5];
		$item['status'] = $rowTag[6];
		$returnVal['user'] = $item;		
		
		return $returnVal;
	}
	
	public function getRandomString($len, $chars=null)
    {
        if (is_null($chars)) {
            $chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789";
        }
        mt_srand(10000000*(double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }
	
	public function userChattring($user_id=0, $profile_id=0, $type='chattering', $group='', $data=null, $event_id=0) {
		return $this->userCheckInHttp($user_id, $profile_id, $type, $group, $data, $event_id);
	}

	//save chattr videos
	public function saveChattrVideos($user_id=0, $profile_id=0, $videofile1='', $videofile2='', $ip_address='', $tags='') {
		
		$user_id = intval($user_id);
		$profile_id = intval($profile_id);
		
		/* if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} */
		//$UID = $this->getUserId();
		$UID = $user_id;
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_video');
		//
			$sqlInsert = " insert into $table(user_id, profile_id, videofile1, videofile2, ip_address, tags, created_on) values(" .
				$UID . ", " . $profile_id . ", '" . $videofile1 . "', '" . $videofile2 . "'
				'". $ip_address . "', '". $tags ."' , '" . date("Y-m-d H:m:s") . "');";
			try {
				mysql_query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = mysql_insert_id();
		
		$sqlSelect = " Select profile_id, user_id, videofile1, videofile2, ip_address, tags, created_on from $table where id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['profile_id'] = $rowTag[0];
		$item['user_id'] = $rowTag[1];
		$item['videofile1'] = $rowTag[2];
		$item['videofile2'] = $rowTag[3];
		$item['ip_address'] = $rowTag[4];
		$item['tags'] = $rowTag[5];
		$item['created_on'] = $rowTag[6];
		$returnVal['video'] = $item;		
		
		return $returnVal;
	}
	
	public function getVideos($user_id=0, $profile_id=0, $videofile1='', $videofile2='', $ip_address='', $tags='') {
		$sqlSelect = " Select id, profile_id, user_id, videofile1, videofile2, ip_address, tags, created_on from $table where id > 0";
		
		if($user_id>0)$sqlSelect.=" and user_id = $user_id";
		if($profile_id>0)$sqlSelect.=" and profile_id = $profile_id";
		if($tags != '')$sqlSelect.=" and profile_id like('%".$tags."%')";
		
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['video_id'] = $rowTag[0];
		$item['profile_id'] = $rowTag[1];
		$item['user_id'] = $rowTag[2];
		$item['videofile1'] = $rowTag[3];
		$item['videofile2'] = $rowTag[4];
		$item['ip_address'] = $rowTag[5];
		$item['tags'] = $rowTag[6];
		$item['created_on'] = $rowTag[7];
		$returnVal['video'] = $item;		
		
		return $returnVal;
	}
	public function __getEvents() {
		$catId = $this->getCategoryIdByName("Live Events");
		$EventCat = $this->getCategoryById($catId);
		$returnVal = array('Events' => array());
		for ($i=0; $i<count($EventCat['children']); $i++) {
			$returnVal['Events'][] = $this->getEventInfo($EventCat['children'][$i]['entity_id'], true);
			$returnVal['Events'][$i]['url_path'] = $EventCat['children'][$i]['url_path'];
		}
		return $returnVal;
	}
	
	public function getRandomProducts($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		$categoryId = $this->getCategoryIdByName("Products");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );
		$storeId = Mage::app()->getStore()->getId();

		$coll = $category->getProductCollection()
            ->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('category_id')
			->addAttributeToFilter('visibility', $visibility);

		$returnVal = array('products' => array());

		$shuffled_array = array();
		foreach ($coll as $key => $value) {
			$shuffled_array[$key] = $value;
		}
		shuffle($shuffled_array);

		//
		$i = 0;
		foreach ($shuffled_array as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['products']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, true);
			$i++;
		}
		return $returnVal;
    }
	
    public function getRandomEvents($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		
		$categoryId = $this->getCategoryIdByName("Live Events");
		
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );

		$storeId = Mage::app()->getStore()->getId();

		
		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility);
		
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');
		
		$returnVal = array('Events' => array());

		$shuffled_array = array();
		foreach ($coll as $key => $value) {
			$shuffled_array[$key] = $value;
		}
		shuffle($shuffled_array);
		
		//
		$i = 0;
		$prod_ids = array();
		foreach ($shuffled_array as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['Events']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$EventUses = $this->getEventUses($prod_ids);
		foreach ($EventUses as $key => $value) {
			$returnVal['Events'][$key]['EventUses'] = $value;
		}
		return $returnVal;
    }
	
	public function getTopSellingProducts() {
		$categoryId = $this->getCategoryIdByName("Products");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );

		$storeId = Mage::app()->getStore()->getId();
		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility);
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');

		$coll->setOrder('ordered_qty', 'desc');
		//
		$returnVal = array('products' => array());
		foreach ($coll as $product) {
			$returnVal['products']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
		}
		return $returnVal;
	}
	public function getTopSellingEvents() {
		$categoryId = $this->getCategoryIdByName("Live Events");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );

		$storeId = Mage::app()->getStore()->getId();
		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility)
				->addOrderedQty();
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');

		$coll->setOrder('ordered_qty', 'desc');
		//
		//$prod_ids = array();
		$returnVal = array('Events' => array());
		foreach ($coll as $product) {
			$returnVal['Events']['' . $product->getId()] = $this->getEvent($product->getId());
			//$prod_ids[] = $product->getId();
		}
		
		return $returnVal;
	}
	
	public function getLatestEvents($count = 0) {
        return $this->loadCache()->__getLatestEvents($count);
    }

	public function __getLatestEvents($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		$categoryId = $this->getCategoryIdByName("Live Events");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		
		$visibility = array(
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
		);

		$storeId = Mage::app()->getStore()->getId();

		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility)
                ->addAttributeToSort('repeats_Event', 'desc');
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');

		$coll->setOrder('created_at', 'desc');
		//
		$prod_ids = array();
		$returnVal = array('Events' => array());
		$i = 0;
		foreach ($coll as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['Events']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$EventUses = $this->getEventUses($prod_ids);
		foreach ($EventUses as $key => $value) {
			$returnVal['Events'][$key]['EventUses'] = $value;
		}
		return $returnVal;
	}
	//
	public function getFeaturedEvents($count = 0) {
        return $this->loadCache(self::$cacheLongTimeSpan)->__getFeaturedEvents($count);
    }
	public function __getFeaturedEvents($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		$categoryId = $this->getCategoryIdByName("Live Events");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);

		$visibility = array(
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
		);

		$storeId = Mage::app()->getStore()->getId();

		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility)
				->addAttributeToFilter('featured_Event', 1);
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');
		$coll->setOrder('created_at', 'desc');
		//
		$prod_ids = array();
		$returnVal = array('Events' => array());
		$i = 0;
		foreach ($coll as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['Events']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$EventUses = $this->getEventUses($prod_ids);
		foreach ($EventUses as $key => $value) {
			$returnVal['Events'][$key]['EventUses'] = $value;
		}
		return $returnVal;
	}
    //
    
	//get Category image
	/* public function getCategoryImage($cid){
		$EventObj = new Event_Profile_Block_View();
		$rsCatImage = $EventObj->getCategoryImage($cid);
		if(isset($rsCatImage["small_image"]) && $rsCatImage["small_image"]!=''){
			return $rsCatImage["small_image"];
		}else{
			$imgUrl = '';
			$rsCatFirstPID = $EventObj->getFirstEventOfCollection($cid);
			if (count($rsCatFirstPID ) > 0) {
				$rsCatFirstPID = $rsCatFirstPID[0];
				$rsCatData = $EventObj->getEventInfo($rsCatFirstPID['product_id']);
				if(isset($rsCatData["small_image"]))
					$imgUrl =  $rsCatData["small_image"];
				else
					$imgUrl = "/default/small_image/small_image.jpg";				
			}
			else {
				$imgUrl = "/default/small_image/small_image.jpg";
			}
			return $imgUrl;
		}
	} */
	
	//
	/* public function getEventsByCollection($collectionId,$drep=1,$dpat=1,$dport=0,$ua=0, $sku='') {
		$collectionId = intval($collectionId);
		if ($collectionId == 0) {
			throw new Exception(self::$invalidCollectionId);
		}
		$tree = $this->getProducts($collectionId,$drep,$dpat,$dport,$ua,$sku);
		$returnVal = array();
		$returnVal['Events'] = $tree['products'];
		return $returnVal;
	} */
    
	
	
	//saveVideos
	public function getCategoryIdByName($name="Live Events", $id=1) {
        return $this->loadCache(self::$cacheFiveDaysSpan)->__getCategoryIdByName($name, $id);
    }
    public function __getCategoryIdByName($name="Live Events", $id=1) {
		$id = intval($id);
        $cat = $this->getCategoryByName($name, $id);
		if (isset($cat['entity_id'])) {
			return $cat['entity_id'];
		}
		throw new Exception(self::$invalidCollectionId);
    }
    public function getCategoryByName($name="Live Events", $id=1) {
        return $this->loadCache()->__getCategoryByName($name, $id);
    }
    public function __getCategoryByName($name="Live Events", $id=1) {
		$id = intval($id);
        $_category = new Mage_Catalog_Model_Category();
        $_category->load($id);
		
		if ($_category->getName() == $name) {
			 return $_category->toArray();
		}

        $tree = $_category->getCategories($_category->getId(), false, true);
       // $tree = $_category->getChildrenCategories();

	   $returnVal = array();

       foreach ($tree as $child) {
		   $returnVal = array_merge($returnVal, $this->__getCategoryByName($name, $child->getId()));
       }

	   return $returnVal;
    }
	
	private function _getSession()
	{
		return Mage::getSingleton('customer/session');
	}
	
	public function saveRecording($category=0, $name='', $desc='', $profile_id=0, $user_id=0, $video_path='', $thumbnail_path='',  $ip_address='', $yt=0, $fb=0, $tags='', $taggedUsers='') {
			
			$catId = $this->getCategoryIdByName("Chattrspace Videos", $id=1);
			$catId = $catId.','.$category;
			try 
			{	$filename = basename($video_path);
				
				Mage::getModel('uploadjob/youtube')->upload($user_id, $lastId, $video_path, $filename, $title, $description, $yt, $fb);
				
				//return $this->addVideoInfo($category, $name, $desc, $profile_id, $user_id, $video_path, $thumbnail_path, $ip_address, $tags, $taggedUsers);
				return $lastId;
				//return $lastId ;
			} catch (Exception $e) {
				throw new Exception('Error: ' . $e->getMessage());
			}
	}
	
	//add video/recording info
	public function addVideoInfo($category=0, $name='', $desc='', $profile_id=0, $user_id=0, $video_path='', $thumbnail_path='', $ip_address='', $tags, $taggedUsers='') {
		
		$resource = Mage::getSingleton('core/resource');
		//$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('video');
			
		$sqlInsert = " insert into $table (category, profile_id, user_id, video_path, thumbnail_path, ip_address, created_time, tags, taggedUsers) values(".$category.", ".$profile_id.", ".$user_id.", '".$video_path."', '".$thumbnail_path."', '".$ip_address."',  now(),'".$tags."', '".$taggedUsers."')";
			
		$write->query($sqlInsert);
		//return $returnVal;
	}
	
	private function _saveStock($lastId, $qty)
	{
			$stockItem = Mage::getModel('cataloginventory/stock_item');
		    $stockItem->loadByProduct($lastId);

		    if (!$stockItem->getId()) {
		        $stockItem->setProductId($lastId)->setStockId(1);
		    }
		    $stockItem->setData('is_in_stock', 1);
		    $savedStock = $stockItem->save();
		    $stockItem->load($savedStock->getId())->setQty($qty)->save();
	}
	
	//add recording info
	public function addRecordingInfo($recording_id=0, $category=0, $name='', $desc='', $profile_id=0, $user_id=0, $video_path='', $thumbnail_path='', $ip_address='', $tags, $taggedUsers='') {
		$recording = Mage::getModel('catalog/product')->load($recording_id);
		$att_set_name = $this->getAttributeSetName($recording->getAttributeSetId());
		
		$returnVal = array();
		if($recording_id!=0 && $att_set_name=='Videos'){
			$resource = Mage::getSingleton('core/resource');
			//$read = $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('recording');
			
			$sqlInsert = " insert into $table (recording_id, profile_id, user_id, video_path1, file_path1, ip_address, created_on, tags) values(".$recording_id.", ".$profile_id.", ".$user_id.", '".$video_path."', '".$thumbnail_path."', '".$ip_address."',  now(),'".$tags."')";
			
			$write->query($sqlInsert);
			//$rs = mysql_query($sqlInsert);
			return 'success';
		}else{
			
			return 'fail';
		}
		//return $returnVal;
	}
	
	//get video info
	public function getRecordingInfo($recording_id) {
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		
		$select = ' select *  from '.$videoTable.' where status = 1 and video_id = '.$recording_id;
		
		$video = $read->fetchRow($select);
		/* if(count($video['video_id'])>0){
			$item = array();			
			$item['id'] = $video['video_id'];
			$item['recording_id'] = $rowTag[1];
			$item['profile_id'] = $rowTag[2];
			$item['user_id'] = $rowTag[3];
			$item['video_path1'] = $prefix.$rowTag[4];
			$item['video_path2'] = $prefix.$rowTag[5];
			$item['video_path3'] = $prefix.$rowTag[6];
			$item['video_path4'] = $prefix.$rowTag[7];
			$item['file_path1'] = $prefix.'images/'.$rowTag[8];
			$item['file_path2'] = $prefix.'images/'.$rowTag[9];
			$item['file_path3'] = $prefix.'images/'.$rowTag[10];
			$item['file_path4'] = $prefix.'images/'.$rowTag[11];
			$item['ip_address'] = $rowTag[12];
			$item['tags'] = $rowTag[13];
			$item['views'] = $rowTag[14];
			$item['created_on'] = $rowTag[14];
			$returnVal['recording'][] = $item;	
		} */
		//print_r($video);
		return  $video;  
	}		
	//get recording info
	/* public function getRecordingInfo($recording_id) {
		$recording = Mage::getModel('catalog/product')->load($recording_id);
		$att_set_name = $this->getAttributeSetName($recording->getAttributeSetId());
		
		$returnVal = array();
		if($recording_id!=0 && $att_set_name=='Videos'){
			$returnVal['recordingId'] = $recording->getId();
			$returnVal['userId'] = $recording->getUserId();
			$returnVal['profileId'] = $recording->getProfileId();
			$returnVal['recordingFiles'] = $this->getRecordingData($recording_id);
			//$returnVal['recordingFile2'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."csrecordings/".$recording->getVideoFilePath2();
			$returnVal['recordingName'] = $recording->getName();
			//$returnVal['recordingDuration'] = $recording->getDuration();
			
			$returnVal['status'] = $recording->getStatus();
		}else{
			$returnVal['recordingId'] = 0;
			$returnVal['userId'] = 0;
			$returnVal['profileId'] = 0;
			$returnVal['recordingFiles']='';
			$returnVal['recordingName'] = '';
			$returnVal['status'] = 0;
		}
		return $returnVal;
	} */
	
	public function getRecordingData($recording_id=0, $profile_id=0, $user_id=0, $tags='') {
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		//$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('recording');
		
		$sqlSelect = " Select id, recording_id, profile_id, user_id, video_path1, video_path1, video_path3, video_path4, file_path1, file_path2, file_path3, file_path4, ip_address, tags, views, created_on from $table where recording_id = ".$recording_id;
		
		if($user_id>0)$sqlSelect.=" and user_id = $user_id";
		if($profile_id>0)$sqlSelect.=" and profile_id = $profile_id";
		if($tags != '')$sqlSelect.=" and tags like('%".$tags."%')";
		
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		//$rowTag = mysql_fetch_row($rs);
		$prefix = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."csrecording/";
		for($k=0; $k < $numResults; $k++)
		{
			$rowTag = mysql_fetch_row($rs);
			$item = array();			
			$item['id'] = $rowTag[0];
			$item['recording_id'] = $rowTag[1];
			$item['profile_id'] = $rowTag[2];
			$item['user_id'] = $rowTag[3];
			$item['video_path1'] = $prefix.$rowTag[4];
			$item['video_path2'] = $prefix.$rowTag[5];
			$item['video_path3'] = $prefix.$rowTag[6];
			$item['video_path4'] = $prefix.$rowTag[7];
			$item['file_path1'] = $prefix.'images/'.$rowTag[8];
			$item['file_path2'] = $prefix.'images/'.$rowTag[9];
			$item['file_path3'] = $prefix.'images/'.$rowTag[10];
			$item['file_path4'] = $prefix.'images/'.$rowTag[11];
			$item['ip_address'] = $rowTag[12];
			$item['tags'] = $rowTag[13];
			$item['views'] = $rowTag[14];
			$item['created_on'] = $rowTag[14];
			$returnVal['recording'][] = $item;		
		}
		return $returnVal;
	}
	
	public function getViewCountByRecordingId($id) {
			$id = intval($id);
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			//$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('recording');
			
			$sqlSelect = " Select views from $table where id = ".$id;
			$rs = mysql_query($sqlSelect);

			$numResults = mysql_num_rows($rs);
			$rowTag = mysql_fetch_row($rs);
			
			$viewCount = 1;
			if($rowTag[0] > 0){
				$viewCount = $rowTag[0];
			}
			
			return $viewCount;
	}
	
	public function addViewCountByRecordingId($id) {
			$viewCount = $this->getViewCountByRecordingId($id);
			$resource = Mage::getSingleton('core/resource');
			//$read = $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('recording');
			$viewCount = $viewCount+1;
			$write->query("update $table set views=$viewCount where id = ".$id);
			
			return $viewCount;
	}
	
	public function javaMobileserviceCall($saveVideo) {
			//print_r($saveVideo);
			return $saveVideo." Reddy";
	}
	
	public function saveYt($un='', $pw='') {
		//$user_id = intval($user_id);
		//return $un;
		if(Mage::getSingleton('customer/session')->isLoggedIn()){
			
			//$username = $this->encode($username);
			$yt_auth = Mage::getModel('uploadjob/uploadjob')->clientLoginAuth($un, $pw);
			if($yt_auth['username']){
				$password = $this->encode($pw);
				
				$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$customer->setYoutubename($un);
				$customer->setYoutubepassword($pw);
				$customer->save();
				return $user_id;
			}
			return 0;
		}
		else
			return 0;		
	}
	
	public function saveYoutubeInfo($username='', $password='') {
		//$user_id = intval($user_id);
		return Mage::getSingleton('customer/session')->getCustomerId();;
		if(Mage::getSingleton('customer/session')->isLoggedIn()){
			
			//$username = $this->encode($username);
			$password = $this->encode($password);
			
			$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setYoutubename($username);
			$customer->setYoutubepassword($password);
			$customer->save();
			return $user_id;
		}
		else
			return 0;		
	}
	
	public function unlinkFacebook($user_id) {
		//$user_id = intval($user_id);
		//if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			
			//$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setFacebookUid('');
			$customer->setFacebookUsername('');
			$customer->setFacebookCode('');
			$customer->save();
			return $user_id;
		//}
		//else
			//return 0;		
	}
	
	public function unlinkTwitter($user_id) {
		//$user_id = intval($user_id);
		//if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			//$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setTwitterId('');
			$customer->setTwitterUsername('');
			$customer->setTwitterOauthSecret('');
			$customer->setTwitterOauthToken('');
			$customer->setTwitterAccessToken('');
			$customer->save();
			return $user_id;
		//}
		//else
			//return 0;		
	}
	
	public function unlinkYoutube($user_id) {
		//$user_id = intval($user_id);
		//if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			
			//$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setYoutubename();
			$customer->setYoutubepassword();
			$customer->save();
			return $user_id;
		//}
		//else
			//return 0;		
	}
	
	public function uploadRecording($username ='moanindia', $password='madhureddy07', $rec_id=0, $fullFilePath='', $filename='test', $title ='Test by Mohan', $description='test description', $type='youtube', $user_id=0) {
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('uploadjob');
			
		if($type == 'youtube'){
			
			$sqlInsert = " Insert into $table (recording_id, user_id, attampt, status, type, message, created_time, title, filename, filepath, content) values (".$rec_id.", ".$user_id.", 1, 1, 1, 'request', now(), '".$title."', '".$filename."', '".$fullFilePath."', '".$description."');";
				$write->query($sqlInsert);
				return $lastInsertId = $write->lastInsertId();
				//$write->query("update $table set status=1, notify=1, follow_on=now()  WHERE follower_id=".$follower." and follow=".$followed);
			//$obj = Mage::getModel('uploadjob/youtube');
			//$result = $obj->upload($username, $password, $rec_id, $fullFilePath, $filename, $title , $description);
		}
	}
	
	public function encode($string,$key="chattrspace rocks") {
		$string = base64_encode(base64_encode($string));
		$key = sha1($key);
		$strLen = strlen($string);
		$keyLen = strlen($key);
		$j=0;$hash='';
		for ($i = 0; $i < $strLen; $i++) {
			$ordStr = ord(substr($string,$i,1));
			if ($j == $keyLen) { $j = 0; }
			$ordKey = ord(substr($key,$j,1));
			$j++;
			$hash .= strrev(base_convert(dechex($ordStr + $ordKey),16,36));
		}
		return $hash;
	}
	//sendUserIsSelectedByHostDuringLiveChatFollowersMail
	
	public function sendLiveChatMail2($pid) {
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn()) {
        $uid = Mage::helper( 'customer/session' )->getCustomerId();
        
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$jobs_mail = $resource->getTableName('jobs_mail');
		$sqlInsert = " Insert into jobs_mail (user_id, profile_id, type, schedule, fuction_name, message, created_on, status) values (".$uid.", ".$pid.",'livechat', 0, 'sendUserIsSelectedByHostDuringLiveChatFollowersMail', 'request', now(), 1);";
		$write->query($sqlInsert);
		//$uid = Mage::getModel('csmobileservice/mail')->sendUserIsSelectedByHostDuringLiveChatFollowersMail($uid, $pid)
		return $pid;
		}
		else
			throw new Exception(self::$loginError);
	}
	
	public function sendLiveChatMail($pid, $uid, $template='') {
		//if (Mage::getSingleton( 'customer/session' )->isLoggedIn()) {
        //$uid = Mage::helper( 'customer/session' )->getCustomerId();
        
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$jobs_mail = $resource->getTableName('jobs_mail');
		
			$follwer = Mage::getModel('csmobileservice/csmobileservice');
			$followers =$this->getFollowersById($uid);
			$i=0;
			foreach($followers as $follower)
			  {	$i++;
				$customer = Mage::getModel('customer/customer')->load($follower['follower_id']); 
				if($customer->getId()){
				$sqlInsert = " Insert into jobs_mail (user_id, profile_id, type, schedule, fuction_name, message, created_on, status, mail_to) values (".$uid.", ".$pid.",'livechat', 0, 'sendUserIsSelectedByHostDuringLiveChatFollowersMail2', 'request', now(), 1, ".$customer->getId().")";
				$write->query($sqlInsert);
				}
			}
		
		return $pid;
		//}
		//else
			//throw new Exception(self::$loginError);
	}
	
	public function reportFeedInHttp($feed_id=0, $reported_by=0, $profile_id=0, $data=null, $mesg=null) {
		
		$reported_by = intval($reported_by);
		$profile_id = intval($profile_id);
		
		/* if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} */
		//$UID = $this->getUserId();
		$UID = $reported_by;
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('feedreports');
		//
		$item = array();$thelastId=0;
		if($reported_by>0 && $feed_id){
			$sqlInsert = " insert into $table(feed_id, reported_by, reported_user, created_time, status) values(" . $feed_id . ", " . $reported_by . ", " . $profile_id . ", '" . date("Y-m-d G:i:s") . "', 1);";
			try {
				$write->query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = $write->lastInsertId();
			
			if (isset($_POST["data"]) && ($_POST["data"] !="")){
				$data = $_POST["data"];
				$data = base64_decode($data);
				$im = imagecreatefromstring($data);
			
				//make a file name
				$fileName = strtoupper( $reported_by . $thelastId . "-" . $this->getRandomString(8) );
				
				//save the image to the disk
				if (isset($im) && $im != false) {
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.jpg';	
					$path = realpath('.')."/media/csreport_images/";
					$fullFilePath = $path . $image_path;
					//delete the file if it already exists
					if(file_exists($fullFilePath)){
						unlink($fullFilePath);      
					}

					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					
					
					$sqlUpdate = " UPDATE $table SET filename = '".mysql_real_escape_string($image_path)."' WHERE feedreports_id = " . $thelastId . ";";
		
					mysql_query($sqlUpdate);
				}
				else {
					//return 'Error';
				}
			}
		
		//end when pre-customized product then add a new product
		
		$sqlSelect = " Select feed_id, reported_by, filename, status, created_time from $table where feedreports_id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['feed_id'] = $rowTag[0];
		$item['reported_by'] = $rowTag[1];
		$item['filename'] = $rowTag[2];
		$item['status'] = $rowTag[3];
		$item['created_time'] = $rowTag[4];
		
		$returnVal['report'] = $item;		
		//end post
		
		return $returnVal;
		}
		else
			return 'nodata';
	}
	
	public function getLiveRecentActivity($limit=10, $last_id=0){
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$table = $resource->getTableName('user_activities');
			//$feedreports = $resource->getTableName('feedreports');
			$table_customer_entity_varchar = $resource->getTableName('customer_entity_varchar');
			$sqlSelect = " Select profile_id, user_id, type_of, group_of, site, photo , created_on, status, id, webcam_on, mesg from $table, $table_customer_entity_varchar where status > 0 and $table_customer_entity_varchar.entity_id=$table.profile_id and $table_customer_entity_varchar.value !='23,24' and $table_customer_entity_varchar.value != '24'";
			
			if($last_id > 0){	
					$sqlSelect.=" and id < " . $last_id . "";
			}
			
			$sqlSelect.=" group by group_of ";
			if($limit>0)
				$sqlSelect.=" order by created_on desc LIMIT ".$limit;
			else
				$sqlSelect.=" order by created_on desc";
			
			$rs = mysql_query($sqlSelect);

			$numResults = mysql_num_rows($rs);
			$returnVal = array();
			$lastId=0;
			for($k=0; $k < $numResults; $k++)
			{
				$rowTag = mysql_fetch_row($rs);
				$customer = Mage::getModel('customer/customer')->load($rowTag[1]);
				$profilePicture = $customer->getProfilePicture();
				$username = $customer->getUsername();
				$item = array();			
				$item['profile_id'] = $rowTag[0];
				$item['user_id'] = $rowTag[1];
				$item['username'] = $username;
				$item['type_of'] = $rowTag[2];
				$item['group_of'] = $rowTag[3];
				$item['site'] = $rowTag[4];
				if($rowTag[9]==1){
					$item['photo'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."csimages/".$rowTag[5];
				}else{
					$item['photo'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."chattrspace/".$profilePicture;
				}
				$item['created_on'] = $rowTag[6];
				$item['status'] = $rowTag[7];
				$item['id'] = $rowTag[8];
				$item['webcam_on'] = $rowTag[9];
				$item['mesg'] = $rowTag[10];
				$returnVal['activities'][] = $item;		
				
				$lastId=$rowTag[8];
			}		
			$returnVal['last_id']=$lastId;
			return $returnVal;
	}
	
	public function getSuggestedPeople($user_id=0, $limit=10, $last_id=0) {
		$str='';	
		$collection = Mage::getResourceModel('customer/customer_collection')
			->addAttributeToSelect('*')
			->addAttributeToSelect('shortbio')
			->addFieldToFilter('is_suggested', array('gt'=> 0))
			->addFieldToFilter('entity_id', array('neq'=> $user_id));
		if($last_id > 0){	
				$collection->addFieldToFilter('entity_id', array('lt'=> $last_id));
		}
		
		if($limit > 0)
			$collection = $collection->setPageSize($limit);
			$collection = $collection->setOrder('entity_id', 'desc');
			
			$collection = $collection->load()->toArray();
			
			$returnVal = array();$lastId=0;
			foreach($collection as $k=>$user){				
				$returnVal['activities'][$k] = $this->getUserInfo($user['entity_id']);	
				$follower = Mage::getModel('csservice/csservice')->isFollow($user['entity_id']);	
				$returnVal['activities'][$k]['is_follow'] = (bool)$follower;
				$lastId = $user['entity_id'];
			}
			$returnVal['last_id']=$lastId;
			return $returnVal;
	}
	
	public function getPeopleSearch($user_id=0, $limit=10, $search='', $last_id=0) {
		$str='';	
		$collection = Mage::getResourceModel('customer/customer_collection')
			->addAttributeToSelect('*')
			->addAttributeToSelect('shortbio')
			->addFieldToFilter('entity_id', array('neq'=> $user_id));
		if($last_id > 0){	
			$collection->addFieldToFilter('entity_id', array('lt'=> $last_id));
		}
		
			
		if($search!=''){
			$collection->addAttributeToFilter(array(
								array(
									'attribute' => 'username',
									'like'        => $search.'%',
									),
								array(
									'attribute' => 'firstname',
									'like'        => $search.'%',
									),
								array(
									'attribute' => 'lastname',
									'like'        => $search.'%',
									),
								array(
									'attribute' => 'email',
									'like'        => $search.'%',
									),	
								/* array(
									'attribute' => 'shortbio',
									'like'        => $search.'%',
									),
								array(
									'attribute' => 'shortbio',
									'like'        => '%'.$search.'%',
									), */
								));
				}
		if($limit > 0)
			$collection = $collection->setPageSize($limit);
			
			$collection = $collection->setOrder('entity_id', 'desc');
			$collection = $collection->load()->toArray();
			
			$returnVal = array();$lastId=0;
			foreach($collection as $k=>$user){				
				$returnVal['activities'][$k] = $this->getUserInfo($user['entity_id']);	
				$follower = Mage::getModel('csservice/csservice')->isFollow($user['entity_id']);	
				$returnVal['activities'][$k]['is_follow'] = (bool)$follower;
				$lastId = $user['entity_id'];
			}
			$returnVal['last_id']=$lastId;
			return $returnVal;
	}
	//user sign up
	public function userSignUp($user_name='', $first_name='', $last_name='', $email='', $password='', $sex='', $dob='') {
		$errors = array();
		
		$websiteId = Mage::app()->getWebsite()->getId();
		$store = Mage::app()->getStore();
		$session = Mage::getSingleton("customer/session");
		
		if(Mage::getModel('profile/profile')->getAge($dob) < 13) {
			return 'Age must be greater than or equal to 13 years.';
			//$errors = array_merge($errors, array('dob'=>'Age must be greater than or equal to 13 years.'));			
		}
		if ($user_name!='') {
			if ( !preg_match('/^([a-zA-Z0-9_\.]+)$/', $user_name) ) {
					return 'Your username should contain only letters, numbers, dot and underscore.';
					//$errors = array_merge($errors, array('username'=>'Your username should contain only letters, numbers, dot and underscore.'));					
			}
			elseif(Mage::getModel('profile/profile')->isCSKeyword($user_name)){
				return 'Reserved key, try diffrent username.';
				//$errors = array_merge($errors, array('username'=>'Reserved key, try diffrent username.'));
			}
			elseif ( strlen($user_name) < 4 || strlen($user_name) > 15) {
					return 'Your username should be 4 to 15 characters long.';
					//$errors = array_merge($errors, array('username'=>'Your username should be 4 to 15 characters long.'));
			} else {
				#check if another customer has it
				$collection = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('username', $user_name);
				if ( $collection->count() > 0 ) {
					return 'Your username should be unique.';
					//$errors = array_merge($errors, array('username'=>'Your username should be unique.'));
				}
			}
		}
		if($email!=''){
			if ( !preg_match("/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/", $email) ) {
				return 'Doesn\'t look like a valid email.';
				//$errors = array_merge($errors, array('email'=>'Doesn\'t look like a valid email.'));
				
			} else {
				#check if another customer has it
				$collection = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', $email);
				if ( $collection->count() > 0 ) {
					return 'This user email already exists.';
					//$errors = array_merge($errors, array('email'=>'This user email already exists.'));
				}
			}
		}
		$validationResult = count($errors) == 0;
		if (true === $validationResult) {
			try{ 
				$customer = Mage::getModel('customer/customer');
				$customer->website_id = $websiteId; 
				$customer->store=0;
				
				$customer->username = $user_name;
				$customer->firstname = $first_name;
				$customer->lastname = $last_name;
				
				$customer->email = $email;
				$customer->sex = $sex;		
				$customer->dob = $dob;		
				$customer->password = $password;
				$customer->setConfirmation($password);
				
				$customer->timezone = 'Europe/London';			
				$customer->setAccGenBy('chattrspace');
				$customer->save();
				if ($customer->isConfirmationRequired()) {
					$customer->sendNewAccountEmail('confirmation');
				}
				$lastId = $customer->getId();
				//$session->setCustomerAsLoggedIn($customer);	
				
				$this->newUserUrl($user_name);
				
				return 'success';
				
			}catch (Exception $e) {
				throw new Exception('Error: Invalid user data: ' . $e->getMessage());
			}
		}
		else{
			throw new Exception('Error: Invalid user data');
			//$errors['error']='true';
			//return $errors;
		}
	}
	
	public function newUserUrl($user_name='') {
		if($user_name!=''){
			try{ 
				$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
				$uldURLCollection->getSelect()
					->where('id_path=?', 'csprofile/'.strtolower($user_name));

				$uldURLCollection->setPageSize(1)->load();

				if ( $uldURLCollection->count() > 0 ) {
					$uldURLCollection->getFirstItem()->delete();
				}					
				
				#add url rewrite
				$modelURLRewrite = Mage::getModel('core/url_rewrite');
				
				$modelURLRewrite->setIdPath('csprofile/'.strtolower($user_name))
					->setTargetPath('csprofile/index/view/username/'.$user_name)
					->setOptions('')
					->setDescription(null)
					->setRequestPath($user_name);

				$modelURLRewrite->save();
				
				
			}catch (Exception $e) {
				throw new Exception('Invalid usename: ' . $e->getMessage());
			}
		}
	}
	//get count
	public function getRecentActivityCount($user_id=0, $type='') {
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$table = $resource->getTableName('user_activities');
			
			$sqlSelect = " Select count(*) as total from $table where status > 0 ";
				
			if($user_id!=0)
				$sqlSelect.=" and user_id = " . $user_id . "";				
			
			if($type!='')
				$sqlSelect.=" and type_of = '" . $type . "'";
			
			$rs = $read->fetchRow($sqlSelect);
			
			return $rs['total'];
			
	}
	
	public function getRecordingCount($user_id) {
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		$select = ' select count(*) as count from '.$videoTable.' where status = 1 and user_id = '.$user_id;
		//die;
		$count = $read->fetchRow($select);
		return $count['count'];    
	}
}
?>